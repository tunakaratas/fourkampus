//
//  APIService.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import UIKit
import CryptoKit

// MARK: - API Response Models
private struct SuccessResponse: Codable {
    let success: Bool
}

private struct FavoriteResponse: Codable {
    let isFavorite: Bool
}

private struct SavedResponse: Codable {
    let isSaved: Bool
}

// Boş response için (DELETE işlemleri gibi)
private struct EmptyResponse: Codable {
    // Boş struct - sadece Codable protokolü için
}

private struct LoginResponse: Codable {
    let token: String
    let user: User
}

private struct LoginRequest: Codable {
    let email: String
    let password: String
}

// PHP API Response Wrapper
private struct APIResponseWrapper<ResponseData: Codable>: Codable {
    let success: Bool
    let data: ResponseData?
    let message: String?
    let error: String?
    let pagination: [String: AnyCodable]?
    // Events API için pagination alanları (geriye dönük uyumluluk)
    let count: Int?
    let limit: Int?
    let offset: Int?
    let has_more: Bool?
    
    enum CodingKeys: String, CodingKey {
        case success
        case data
        case message
        case error
        case pagination
        case count
        case limit
        case offset
        case has_more
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        success = try container.decode(Bool.self, forKey: .success)
        data = try container.decodeIfPresent(ResponseData.self, forKey: .data)
        message = try container.decodeIfPresent(String.self, forKey: .message)
        error = try container.decodeIfPresent(String.self, forKey: .error)
        pagination = try container.decodeIfPresent([String: AnyCodable].self, forKey: .pagination)
        count = try container.decodeIfPresent(Int.self, forKey: .count)
        limit = try container.decodeIfPresent(Int.self, forKey: .limit)
        offset = try container.decodeIfPresent(Int.self, forKey: .offset)
        has_more = try container.decodeIfPresent(Bool.self, forKey: .has_more)
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(success, forKey: .success)
        try container.encodeIfPresent(data, forKey: .data)
        try container.encodeIfPresent(message, forKey: .message)
        try container.encodeIfPresent(error, forKey: .error)
        try container.encodeIfPresent(pagination, forKey: .pagination)
        try container.encodeIfPresent(count, forKey: .count)
        try container.encodeIfPresent(limit, forKey: .limit)
        try container.encodeIfPresent(offset, forKey: .offset)
        try container.encodeIfPresent(has_more, forKey: .has_more)
    }
}

// Communities Response with Pagination
struct CommunitiesResponse {
    let communities: [Community]
    let hasMore: Bool
}

// Helper for decoding Any type in pagination
private struct AnyCodable: Codable {
    let value: Any
    
    init(_ value: Any) {
        self.value = value
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if let bool = try? container.decode(Bool.self) {
            value = bool
        } else if let int = try? container.decode(Int.self) {
            value = int
        } else if let string = try? container.decode(String.self) {
            value = string
        } else {
            throw DecodingError.dataCorruptedError(in: container, debugDescription: "AnyCodable value cannot be decoded")
        }
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        if let bool = value as? Bool {
            try container.encode(bool)
        } else if let int = value as? Int {
            try container.encode(int)
        } else if let string = value as? String {
            try container.encode(string)
        }
    }
}

// APIResponse typealias for convenience
fileprivate typealias APIResponse<T: Codable> = APIResponseWrapper<T>

// MARK: - API Service
class APIService {
    static let shared = APIService()
    
    // Base URL - AppConfig'den alınır (Info.plist'ten okunur)
    private let baseURLLock = NSLock()
    private var lastSuccessfulBaseURL: String
    
    // Base URL getter - AppConfig kullanır (artık local/production ayrımı yok)
    
    // Aktif base URL - QR kod vb. için kullanılıyor; varsayılan local
    private var currentBaseURL: String {
        baseURLLock.lock()
        defer { baseURLLock.unlock() }
        return lastSuccessfulBaseURL
    }
    
    // Public baseURL property (QR kod ve diğer kullanımlar için)
    var baseURL: String {
        baseURLLock.lock()
        defer { baseURLLock.unlock() }
        return lastSuccessfulBaseURL
    }
    
    private let session: URLSession
    private static let sessionDelegate = APISessionDelegate()
    
    private func updateLastSuccessfulBaseURL(_ url: String) {
        baseURLLock.lock()
        lastSuccessfulBaseURL = url
        baseURLLock.unlock()
    }
    
    // MARK: - Local Cache (Güvenli Fallback - Çok Kısa Süreli)
    private let fileManager = FileManager.default
    private let cacheDirectory: URL
    // Cache çok kısa süreli (1-2 saniye) - fresh data + network hatası koruması
    private let cacheExpirationTime: TimeInterval = 2 // 2 saniye (fresh data garantisi)
    private let communitiesCacheExpirationTime: TimeInterval = 1 // Topluluklar için 1 saniye
    private let maxCacheSize: Int64 = 100 * 1024 * 1024 // 100 MB max cache size
    private let maxCacheFiles: Int = 500 // Max 500 cache dosyası
    private let cacheEnabled: Bool = true // Cache açık (fallback için)
    
    private init() {
        // AppConfig'den base URL'i al
        lastSuccessfulBaseURL = AppConfig.shared.baseURL
        
        // Cache dizini oluştur
        let urls = fileManager.urls(for: .cachesDirectory, in: .userDomainMask)
        cacheDirectory = urls[0].appendingPathComponent("APICache", isDirectory: true)
        try? fileManager.createDirectory(at: cacheDirectory, withIntermediateDirectories: true)
        
        let configuration = URLSessionConfiguration.default
        // Timeout ayarları - Network gecikmeleri için optimize edildi
        configuration.timeoutIntervalForRequest = 30 // 30 saniye (network gecikmeleri için artırıldı)
        configuration.timeoutIntervalForResource = 60 // 60 saniye (büyük dosyalar için artırıldı)
        
        // HTTP caching - optimized for real-world scenarios
        let cacheSize = 50 * 1024 * 1024 // 50 MB memory cache
        let diskCacheSize = 100 * 1024 * 1024 // 100 MB disk cache
        configuration.urlCache = URLCache(
            memoryCapacity: cacheSize,
            diskCapacity: diskCacheSize,
            diskPath: "APICache"
        )
        configuration.requestCachePolicy = .useProtocolCachePolicy // HTTP caching enabled
        
        // Network ayarları - binlerce kullanıcı için optimizasyonlar
        configuration.waitsForConnectivity = false // Bağlantı bekleme devre dışı (timeout ile kontrol ediliyor)
        configuration.allowsCellularAccess = true // Cellular data'ya izin ver
        configuration.allowsConstrainedNetworkAccess = true // Constrained network'lere izin ver
        configuration.httpShouldSetCookies = false
        configuration.httpCookieAcceptPolicy = .never
        
        // Connection pooling ve retry ayarları - yüksek yük için optimize
        configuration.httpMaximumConnectionsPerHost = 6 // Paralel bağlantı sayısı (rate limiting ile kontrol ediliyor)
        // httpShouldUsePipelining deprecated in iOS 18.4 - HTTP/2 ve HTTP/3 otomatik olarak pipelining kullanır
        
        // Network service type - optimize edilmiş
        configuration.networkServiceType = .default
        
        // HTTP header'ları - güvenlik için
        configuration.httpAdditionalHeaders = [
            "Accept-Encoding": "gzip, deflate",
            "Accept-Language": Locale.preferredLanguages.first ?? "tr-TR"
        ]
        
        // URLSession delegate ile SSL ve network hatalarını handle et
        self.session = URLSession(configuration: configuration, delegate: Self.sessionDelegate, delegateQueue: nil)
    }

    // MARK: - URL Helpers
    /// Query param değerlerini güvenli biçimde URL-encode et (Türkçe karakterler dahil).
    private func encodeQueryValue(_ value: String) -> String {
        // URL encoding için UTF-8 kullan ve tüm özel karakterleri encode et
        // Türkçe karakterler dahil tüm non-ASCII karakterler encode edilmeli
        // RFC3986: Sadece unreserved karakterler (ALPHA, DIGIT, "-", ".", "_", "~") encode edilmez
        guard let encoded = value.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) else {
            return value
        }
        // URL query allowed bazı karakterleri encode etmeyebilir, manuel olarak kontrol et
        // Türkçe karakterler ve özel karakterler için ekstra encoding yap
        var result = encoded
        // Eğer hala Türkçe karakter varsa (normalize edilmiş değerlerde olmamalı ama güvenlik için)
        let turkishChars = "çğıöşüÇĞIİÖŞÜ"
        for char in turkishChars {
            let charStr = String(char)
            if let charEncoded = charStr.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) {
                result = result.replacingOccurrences(of: charStr, with: charEncoded)
            }
        }
        return result
    }
    
    // Ortak JSONDecoder: birden fazla tarih formatını destekler
    private static let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
            decoder.dateDecodingStrategy = .custom { decoder in
                let container = try decoder.singleValueContainer()
                let string = try container.decode(String.self)
                // Create formatters inside closure to avoid Sendable capture issues
                let iso8601 = ISO8601DateFormatter()
                iso8601.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
                if let date = iso8601.date(from: string) { return date }
                let iso = ISO8601DateFormatter()
                if let date = iso.date(from: string) { return date }
                let fmts = [
                    "yyyy-MM-dd'T'HH:mm:ssZ",
                    "yyyy-MM-dd'T'HH:mm:ss.SSSZ",
                    "yyyy-MM-dd HH:mm:ss",
                    "yyyy-MM-dd"
                ]
                let df = DateFormatter()
                df.locale = Locale(identifier: "en_US_POSIX")
                for f in fmts {
                    df.dateFormat = f
                    if let d = df.date(from: string) { return d }
                }
                if let ts = Double(string) {
                    return Date(timeIntervalSince1970: string.count > 10 ? ts / 1000.0 : ts)
                }
                throw DecodingError.dataCorruptedError(in: container, debugDescription: "Unsupported date format: \(string)")
        }
        return decoder
    }()
    
    // Ortak JSONEncoder: request body'leri encode etmek için (tarihleri ISO8601 ile yollar)
    static let encoder: JSONEncoder = {
        let encoder = JSONEncoder()
        encoder.outputFormatting = []
        encoder.keyEncodingStrategy = .useDefaultKeys
        encoder.dateEncodingStrategy = .iso8601
        return encoder
    }()
    
    // MARK: - Local Cache Helpers
    private func cacheKey(for endpoint: String) -> String {
        // Endpoint'i cache key'e çevir (özel karakterleri temizle)
        return endpoint
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: "?", with: "_")
            .replacingOccurrences(of: "&", with: "_")
            .replacingOccurrences(of: "=", with: "_")
            .addingPercentEncoding(withAllowedCharacters: .alphanumerics) ?? endpoint
    }
    
    private func cacheURL(for key: String) -> URL {
        return cacheDirectory.appendingPathComponent("\(key).json")
    }
    
    private func metadataURL(for key: String) -> URL {
        return cacheDirectory.appendingPathComponent("\(key)_meta.json")
    }
    
    private struct CacheMetadata: Codable {
        let timestamp: Date
        let endpoint: String
    }
    
// Cache'den oku
private func loadFromCache<T: Decodable>(_ type: T.Type, for endpoint: String) -> T? {
    let key = cacheKey(for: endpoint)
    let cacheFile = cacheURL(for: key)
    let metadataFile = metadataURL(for: key)
    
    // Metadata'yı kontrol et (expiration)
    guard let metadataData = try? Data(contentsOf: metadataFile),
          let metadata = try? JSONDecoder().decode(CacheMetadata.self, from: metadataData) else {
        return nil
    }
    
    // Topluluklar için özel cache süresi (daha kısa)
    let expirationTime = endpoint.contains("communities.php") ? communitiesCacheExpirationTime : cacheExpirationTime
    
    // Cache süresi dolmuş mu?
    let age = Date().timeIntervalSince(metadata.timestamp)
    if age > expirationTime {
        // Cache'i sil
        try? fileManager.removeItem(at: cacheFile)
        try? fileManager.removeItem(at: metadataFile)
        return nil
    }
        
        // Cache dosyasını oku
        guard let cacheData = try? Data(contentsOf: cacheFile) else {
            return nil
        }
        
        // Decode et
        do {
            let cached = try APIService.decoder.decode(T.self, from: cacheData)
            #if DEBUG
            print("✅ Cache'den yüklendi: \(endpoint)")
            #endif
            return cached
        } catch {
            #if DEBUG
            print("⚠️ Cache decode hatası: \(error.localizedDescription)")
            #endif
            // Bozuk cache'i sil
            try? fileManager.removeItem(at: cacheFile)
            try? fileManager.removeItem(at: metadataFile)
            return nil
        }
    }
    
    // Cache'e kaydet
    private func saveToCache<T: Encodable>(_ data: T, for endpoint: String) {
        let key = cacheKey(for: endpoint)
        let cacheFile = cacheURL(for: key)
        let metadataFile = metadataURL(for: key)
        
        do {
            // Data'yı encode et
            let encoded = try APIService.encoder.encode(data)
            
            // Cache boyutu kontrolü - limit aşıldıysa temizle
            cleanupCacheIfNeeded()
            
            // Cache dosyasına kaydet
            try encoded.write(to: cacheFile)
            
            // Metadata'yı kaydet
            let metadata = CacheMetadata(timestamp: Date(), endpoint: endpoint)
            let metadataEncoded = try JSONEncoder().encode(metadata)
            try metadataEncoded.write(to: metadataFile)
            
            #if DEBUG
            print("💾 Cache'e kaydedildi: \(endpoint)")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Cache kaydetme hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    // Cache temizleme - boyut limiti aşıldığında
    private func cleanupCacheIfNeeded() {
        guard let files = try? fileManager.contentsOfDirectory(at: cacheDirectory, includingPropertiesForKeys: [.fileSizeKey, .contentModificationDateKey]) else {
            return
        }
        
        // Cache dosyalarını boyut ve tarihe göre sırala
        var cacheFiles: [(url: URL, size: Int64, date: Date)] = []
        for file in files {
            if file.pathExtension == "json" {
                let resourceValues = try? file.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
                let size = Int64(resourceValues?.fileSize ?? 0)
                let date = resourceValues?.contentModificationDate ?? Date.distantPast
                cacheFiles.append((url: file, size: size, date: date))
            }
        }
        
        // Toplam boyutu hesapla
        let totalSize = cacheFiles.reduce(0) { $0 + $1.size }
        let fileCount = cacheFiles.count
        
        // Limit aşıldıysa en eski dosyaları sil
        if totalSize > maxCacheSize || fileCount > maxCacheFiles {
            // Tarihe göre sırala (en eski önce)
            cacheFiles.sort { $0.date < $1.date }
            
            // %20'sini sil (en eski dosyalar)
            let filesToDelete = max(1, cacheFiles.count / 5)
            for i in 0..<filesToDelete {
                try? fileManager.removeItem(at: cacheFiles[i].url)
                // Metadata dosyasını da sil (dosya adı_meta.json formatında)
                let fileName = cacheFiles[i].url.deletingPathExtension().lastPathComponent
                let metadataURL = cacheDirectory.appendingPathComponent("\(fileName)_meta.json")
                try? fileManager.removeItem(at: metadataURL)
            }
            
            #if DEBUG
            print("🧹 APICache: \(filesToDelete) eski cache dosyası temizlendi (boyut: \(totalSize / 1024 / 1024) MB, dosya: \(fileCount))")
            #endif
        }
    }
    
    // MARK: - Generic Request Method
    // Güvenli yaklaşım: Önce API'den çek, başarısız olursa cache'den göster (fallback)
    func request<T: Codable>(
        endpoint: String,
        method: String = "GET",
        body: Data? = nil,
        headers: [String: String]? = nil,
        useCache: Bool = true // Default true - fallback için cache açık
    ) async throws -> T {
        // Endpoint'i temizle (başında / varsa kaldır)
        let cleanEndpoint = endpoint.hasPrefix("/") ? String(endpoint.dropFirst()) : endpoint
        
        // Request deduplication KALDIRILDI - Paralel isteklere izin ver (hızlı yükleme için)
        let requestKey = RequestManager.generateRequestKey(endpoint: cleanEndpoint, method: method, body: body)
        
        // Sadece cache'den kontrol et (deduplication yok)
        if method == "GET" && useCache {
            if let cached: T = loadFromCache(T.self, for: cleanEndpoint) {
                #if DEBUG
                print("✅ Cache'den hemen döndürüldü")
                #endif
                return cached
            }
        }
        
        // Rate limiting - istek göndermeden önce bekle
        await RequestManager.shared.waitForRateLimit()
        await RequestManager.shared.requestStarted(key: requestKey)
        
        defer {
            Task {
                await RequestManager.shared.requestCompleted()
                await RequestManager.shared.requestFinished(key: requestKey)
            }
        }
        
        // Base URL artık her zaman production (AppConfig'de localhost kontrolü var)
        // Direkt API'den çekmeyi dene (fresh data)
        let startTime = Date()
        do {
            #if DEBUG
            print("🌐 API'den fresh data çekiliyor: \(cleanEndpoint)")
            #endif
            let result: T = try await requestWithoutCache(endpoint: cleanEndpoint, method: method, body: body, headers: headers)
            
            // Performance monitoring
            let duration = Date().timeIntervalSince(startTime)
            await PerformanceMonitor.shared.recordAPIResponse(endpoint: cleanEndpoint, duration: duration, success: true)
            
            // Başarılı - cache'e kaydet (fallback için)
            if method == "GET" && useCache {
                saveToCache(result, for: cleanEndpoint)
                await PerformanceMonitor.shared.recordCacheMiss()
            }
            
            // Request sonucunu deduplication için kaydet
            // RequestManager wraps non-Sendable types in SendableWrapper
            // Use SendableWrapper to safely send non-Sendable types to actor
            await RequestManager.shared.requestFinished(key: requestKey, result: SendableWrapper(result))
            
            return result
        } catch {
            // Cancelled hatalarını özel olarak handle et
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            
            #if DEBUG
            if isCancelled {
                print("⚠️ İstek iptal edildi, cache'den kontrol ediliyor...")
            } else {
                print("⚠️ API hatası, fallback deneniyor: \(error.localizedDescription)")
            }
            #endif
            
            // Performance monitoring
            let duration = Date().timeIntervalSince(startTime)
            await PerformanceMonitor.shared.recordAPIResponse(endpoint: cleanEndpoint, duration: duration, success: false)
            
            // Önce cache'den dene (cancelled hataları için özellikle önemli)
            if method == "GET" && useCache {
                if let cached: T = loadFromCache(T.self, for: cleanEndpoint) {
                    #if DEBUG
                    print("✅ Cache'den fallback veri bulundu")
                    #endif
                    await PerformanceMonitor.shared.recordCacheHit()
                    // RequestManager wraps non-Sendable types in SendableWrapper
                    // Use SendableWrapper to safely send non-Sendable types to actor
                    await RequestManager.shared.requestFinished(key: requestKey, result: SendableWrapper(cached))
                    return cached
                }
            }
            
            // Cancelled hatası ise ve cache'de veri yoksa, sessizce ignore et
            if isCancelled {
                #if DEBUG
                print("⚠️ İstek iptal edildi ve cache'de veri yok, sessizce ignore ediliyor")
                #endif
                await RequestManager.shared.requestFinished(key: requestKey)
                // Cancelled hatasını fırlat - ViewModel'de handle edilecek (boş array kullanılacak)
                throw error
            }
            
            // Hosting fallback kaldırıldı - sadece localhost API kullanılacak
            
            // Tüm fallback'ler başarısız - hatayı fırlat
            #if DEBUG
            print("❌ API ve cache'de veri yok, hata fırlatılıyor")
            #endif
            await RequestManager.shared.requestFinished(key: requestKey)
            throw error
        }
    }
    
    // MARK: - Request Without Cache (Internal)
    private func requestWithoutCache<T: Codable>(
        endpoint: String,
        method: String,
        body: Data?,
        headers: [String: String]?,
        customBaseURL: String? = nil
    ) async throws -> T {
        // Endpoint'i temizle (başında / varsa kaldır)
        let cleanEndpoint = endpoint.hasPrefix("/") ? String(endpoint.dropFirst()) : endpoint
        
        // Eğer endpoint zaten tam URL ise direkt kullan
        let requestURL: String
        if cleanEndpoint.hasPrefix("http://") || cleanEndpoint.hasPrefix("https://") {
            requestURL = cleanEndpoint
        } else {
            // Custom base URL varsa kullan, yoksa AppConfig'den al
            let baseURL = customBaseURL ?? AppConfig.shared.baseURL
            // Base URL'in sonunda / olmamalı, endpoint'in başında / olmamalı
            let trimmedBaseURL = baseURL.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            requestURL = "\(trimmedBaseURL)/\(cleanEndpoint)"
        }
        
        #if DEBUG
        SecureLogger.d("APIService", "API İsteği: \(method) \(requestURL)")
        SecureLogger.d("APIService", "Base URL: \(AppConfig.shared.baseURL)")
        SecureLogger.d("APIService", "Endpoint: \(cleanEndpoint)")
        // Request body loglanmıyor - hassas bilgi içerebilir
        #endif
        
        // Tek URL kullan (AppConfig'den geliyor)
        let result: T = try await performRequest(
            urlString: requestURL,
            method: method,
            body: body,
            headers: headers
        )
        
        if method == "GET" {
            saveToCache(result, for: endpoint)
        }
        
        // Base URL'i güncelle (başarılı istek için)
        let actualBaseURL = customBaseURL ?? AppConfig.shared.baseURL
        updateLastSuccessfulBaseURL(actualBaseURL)
        return result
    }
    
    // MARK: - Perform Request With Custom Timeout
    private func performRequestWithTimeout<T: Codable>(
        urlString: String,
        method: String,
        body: Data?,
        headers: [String: String]?,
        timeout: TimeInterval
    ) async throws -> T {
        // Timeout ile request oluştur
        guard let url = URL(string: urlString) else {
            throw APIError.invalidURL
        }
        
        var request = URLRequest(url: url)
        request.timeoutInterval = timeout
        request.cachePolicy = .reloadIgnoringLocalCacheData
        request.httpMethod = method.uppercased()
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        
        if let body = body {
            request.httpBody = body
        }
        
        if let token = getAuthToken(), !token.isEmpty {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        
        if let headers = headers {
            for (key, value) in headers {
                request.setValue(value, forHTTPHeaderField: key)
            }
        }
        
        let (data, response) = try await session.data(for: request)
        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }
        
        // 400 durumunda bile response'u decode etmeyi dene (API hatası mesajı için)
        if !(200...299).contains(httpResponse.statusCode) {
            // Önce response'u decode etmeyi dene (hata mesajı için)
            if let apiResponse = try? APIService.decoder.decode(APIResponseWrapper<T>.self, from: data) {
                let errorMsg = apiResponse.error ?? apiResponse.message ?? "API hatası"
                switch httpResponse.statusCode {
                case 401: throw APIError.unauthorized
                case 404: throw APIError.notFound
                default: throw APIError.apiError(errorMsg)
                }
            } else {
                // Decode edilemezse HTTP error fırlat
            switch httpResponse.statusCode {
            case 401: throw APIError.unauthorized
            case 404: throw APIError.notFound
            default: throw APIError.httpError(httpResponse.statusCode)
                }
            }
        }
        
        // Decode response
        do {
            let apiResponse = try APIService.decoder.decode(APIResponseWrapper<T>.self, from: data)
            guard apiResponse.success, let responseData = apiResponse.data else {
                throw APIError.apiError(apiResponse.error ?? apiResponse.message ?? "API hatası")
            }
            return responseData
        } catch {
            // Direkt decode dene
            return try APIService.decoder.decode(T.self, from: data)
        }
    }
    
    // MARK: - Perform Request Helper (with Retry)
    private func performRequest<T: Codable>(
        urlString: String,
        method: String,
        body: Data?,
        headers: [String: String]?,
        retryCount: Int = 0,
        maxRetries: Int = 3
    ) async throws -> T {
        // Network connectivity check - gerçek hayat senaryoları için
        await MainActor.run {
            if !NetworkMonitor.shared.isNetworkAvailable() {
                #if DEBUG
                print("⚠️ APIService: Network not available, checking connectivity...")
                #endif
            }
        }
        
        // Retry mekanizması - gerçek hayat senaryoları için
        do {
            return try await performRequestInternal(urlString: urlString, method: method, body: body, headers: headers)
        } catch let error as URLError {
            // Retry edilebilir hatalar
            let retryableErrors: [URLError.Code] = [
                .timedOut,
                .networkConnectionLost,
                .cannotConnectToHost,
                .cannotFindHost,
                .dnsLookupFailed,
                .notConnectedToInternet,
                .dataNotAllowed
            ]
            
            if retryableErrors.contains(error.code) && retryCount < maxRetries {
                // Use ErrorHandler for backoff calculation
                let delay = ErrorHandler.calculateBackoffDelay(attempt: retryCount)
                #if DEBUG
                print("🔄 Retry \(retryCount + 1)/\(maxRetries) after \(String(format: "%.1f", delay))s - \(error.localizedDescription)")
                #endif
                try await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                return try await performRequest(urlString: urlString, method: method, body: body, headers: headers, retryCount: retryCount + 1, maxRetries: maxRetries)
            }
            throw error
        } catch let error as APIError {
            // HTTP 5xx hataları için retry
            if case .httpError(let code) = error, code >= 500 && retryCount < maxRetries {
                let delay = ErrorHandler.calculateBackoffDelay(attempt: retryCount)
                #if DEBUG
                print("🔄 Retry \(retryCount + 1)/\(maxRetries) after \(String(format: "%.1f", delay))s - HTTP \(code)")
                #endif
                try await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                return try await performRequest(urlString: urlString, method: method, body: body, headers: headers, retryCount: retryCount + 1, maxRetries: maxRetries)
            }
            throw error
        }
    }
    
    // MARK: - Perform Request Internal (Actual Implementation)
    private func performRequestInternal<T: Codable>(
        urlString: String,
        method: String,
        body: Data?,
        headers: [String: String]?
    ) async throws -> T {
        guard let url = URL(string: urlString) else {
            #if DEBUG
            print("❌ Geçersiz URL: \(urlString)")
            #endif
            throw APIError.invalidURL
        }
        
        var request = URLRequest(url: url)
        // Cache'i devre dışı bırak
        request.cachePolicy = .reloadIgnoringLocalCacheData
        
        // HTTP Method'u büyük harfle ayarla (POST, GET, PUT, DELETE)
        let httpMethod = method.uppercased()
        request.httpMethod = httpMethod
        
        #if DEBUG
        print("🔧 HTTP Method: \(httpMethod)")
        #endif
        
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        
        // POST/PUT/DELETE için özel header'lar
        if ["POST", "PUT", "DELETE", "PATCH"].contains(httpMethod) {
            request.setValue("no-cache", forHTTPHeaderField: "Cache-Control")
        }
        
        // Güvenlik header'ları - Binlerce kullanıcı için kritik
        let requestID = UUID().uuidString
        request.setValue(requestID, forHTTPHeaderField: "X-Request-ID")
        request.setValue(String(Int(Date().timeIntervalSince1970)), forHTTPHeaderField: "X-Request-Timestamp")
        
        // User-Agent header (güvenlik ve analytics için)
        let appVersion = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0"
        let systemVersion = UIDevice.current.systemVersion
        let deviceModel = UIDevice.current.model
        request.setValue("FourKampus-iOS/\(appVersion) (\(deviceModel); iOS \(systemVersion))", forHTTPHeaderField: "User-Agent")
        
        // Request signature (SHA-256 hash - güvenli)
        if let body = body {
            let bodyHash = CryptoHelper.sha256Hash(body)
            request.setValue(bodyHash, forHTTPHeaderField: "X-Request-Hash")
        }
        
        // Custom headers
        if let headers = headers {
            for (key, value) in headers {
                request.setValue(value, forHTTPHeaderField: key)
            }
        }
        
        // Authorization header (token varsa) - ZORUNLU POST/PUT/DELETE için
        if let token = getAuthToken(), !token.isEmpty {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
            #if DEBUG
            // Token bilgisi loglanmıyor - güvenlik için
            SecureLogger.d("APIService", "Authorization header eklendi")
            #endif
        } else {
            #if DEBUG
            if ["POST", "PUT", "DELETE", "PATCH"].contains(httpMethod) {
                SecureLogger.w("APIService", "POST/PUT/DELETE isteği için token bulunamadı")
            }
            #endif
        }
        
        // Request body - POST/PUT/DELETE için zorunlu
        if let body = body {
            request.httpBody = body
            #if DEBUG
            print("📦 Request body size: \(body.count) bytes")
            #endif
            
            // POST/PUT/DELETE için Content-Length header'ı ekle
            if ["POST", "PUT", "DELETE", "PATCH"].contains(httpMethod) {
                request.setValue("\(body.count)", forHTTPHeaderField: "Content-Length")
            }
        }
        
        // Timeout ayarları - Gerçek hayat senaryoları için optimize edildi
        request.timeoutInterval = 30 // 30 saniye (network gecikmeleri için artırıldı)
        
        // Network service type - optimize edilmiş
        request.networkServiceType = .default
        
        do {
            #if DEBUG
            print("📡 İstek gönderiliyor: \(urlString)")
            print("   Base URL: \(AppConfig.shared.baseURL)")
            print("   Method: \(httpMethod)")
            if let body = body {
                print("   Body Size: \(body.count) bytes")
            }
            #endif
            
            let (data, response): (Data, URLResponse)
            do {
                (data, response) = try await session.data(for: request)
            } catch let error as URLError where error.code == .cancelled {
                #if DEBUG
                print("⚠️ İstek iptal edildi (cancelled), cache'den kontrol ediliyor...")
                #endif
                // Cancelled hatası - cache'den dön (yukarıda handle edilecek)
                throw error
            } catch is CancellationError {
                #if DEBUG
                print("⚠️ İstek iptal edildi (CancellationError), cache'den kontrol ediliyor...")
                #endif
                // CancellationError - URLError'a çevir
                throw URLError(.cancelled)
            }
            
            guard let httpResponse = response as? HTTPURLResponse else {
                #if DEBUG
                print("❌ Geçersiz HTTP yanıtı")
                #endif
                throw APIError.invalidResponse
            }
            
            #if DEBUG
            SecureLogger.d("APIService", "HTTP Yanıt: \(httpResponse.statusCode) - \(urlString)")
            SecureLogger.d("APIService", "Response Size: \(data.count) bytes")
            
            // 400 hatası durumunda response body'yi logla
            if httpResponse.statusCode == 400 {
                if let responseString = String(data: data, encoding: .utf8) {
                    print("❌ 400 Response Body: \(responseString)")
                }
            }
            
            // Response headers'ı logla (hassas veriler sanitize edilmiş)
            if let headers = httpResponse.allHeaderFields as? [String: Any] {
                var headerLog = "Response Headers:"
                for (key, value) in headers {
                    let sanitizedValue = SecureLogger.sanitize("\(value)")
                    headerLog += "\n   \(key): \(sanitizedValue)"
                }
                SecureLogger.d("APIService", headerLog)
            }
            #endif
            
            guard (200...299).contains(httpResponse.statusCode) else {
                #if DEBUG
                print("❌ HTTP Hatası: \(httpResponse.statusCode)")
                // Response body'yi logla (hassas veriler sanitize edilmiş)
                if let responseString = String(data: data, encoding: .utf8) {
                    let sanitized = SecureLogger.sanitizeJSON(responseString)
                    SecureLogger.e("APIService", "Error Response Body: \(sanitized)")
                }
                // Request headers'ı logla (hassas veriler sanitize edilmiş)
                if let allHeaders = request.allHTTPHeaderFields {
                    var headerLog = "Request Headers:"
                    for (key, value) in allHeaders {
                        let sanitizedValue = SecureLogger.sanitize("\(value)")
                        headerLog += "\n   \(key): \(sanitizedValue)"
                    }
                    SecureLogger.e("APIService", headerLog)
                }
                switch httpResponse.statusCode {
                case 401: 
                    SecureLogger.w("APIService", "Yetkilendirme hatası (401)")
                    // Token bilgisi loglanmıyor - güvenlik için
                case 404: 
                    print("🔍 Bulunamadı (404) - Endpoint: \(urlString)")
                    print("   Lütfen base URL ve endpoint path'ini kontrol edin")
                case 500...599:
                    print("🔴 Sunucu Hatası (\(httpResponse.statusCode)) - Endpoint: \(urlString)")
                    print("   Base URL: \(AppConfig.shared.baseURL)")
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("   Response Body: \(responseString.prefix(500))")
                    }
                default:
                    break
                }
                #endif
                switch httpResponse.statusCode {
                case 401: 
                    // 401 hatasında response body'den detaylı mesaj al
                    if let responseString = String(data: data, encoding: .utf8),
                       let jsonData = responseString.data(using: .utf8),
                       let json = try? JSONSerialization.jsonObject(with: jsonData) as? [String: Any],
                       let errorMessage = json["error"] as? String {
                        throw APIError.apiError(errorMessage)
                    }
                    throw APIError.unauthorized
                case 404: 
                    throw APIError.notFound
                case 400:
                    // 400 hatasında response body'den detaylı mesaj al
                    if !data.isEmpty {
                        if let responseString = String(data: data, encoding: .utf8),
                           !responseString.isEmpty {
                            // JSON parse etmeyi dene
                            if let jsonData = responseString.data(using: .utf8),
                               let json = try? JSONSerialization.jsonObject(with: jsonData) as? [String: Any],
                               let errorMessage = json["error"] as? String ?? json["message"] as? String {
                                throw APIError.apiError(errorMessage)
                            } else {
                                // JSON değilse, direkt string olarak göster
                                throw APIError.apiError("HTTP Hatası (400): \(responseString.prefix(200))")
                            }
                        }
                    }
                    throw APIError.httpError(400)
                case 500...599:
                    // 500 hatasında response body'den detaylı mesaj al
                    if !data.isEmpty {
                        if let responseString = String(data: data, encoding: .utf8),
                           !responseString.isEmpty {
                            // JSON parse etmeyi dene
                            if let jsonData = responseString.data(using: .utf8),
                               let json = try? JSONSerialization.jsonObject(with: jsonData) as? [String: Any],
                               let errorMessage = json["error"] as? String ?? json["message"] as? String {
                                throw APIError.apiError("Sunucu Hatası (\(httpResponse.statusCode)): \(errorMessage)")
                            } else {
                                // JSON değilse, direkt string olarak göster
                                throw APIError.apiError("Sunucu Hatası (\(httpResponse.statusCode)): \(responseString.prefix(200))")
                            }
                        }
                    }
                    // Response body boşsa, genel mesaj
                    throw APIError.apiError("Sunucu Hatası (\(httpResponse.statusCode)): Sunucu yanıt vermedi. Lütfen sunucu loglarını kontrol edin.")
                default: 
                    throw APIError.httpError(httpResponse.statusCode)
                }
            }
            
            // Response body kontrolü
            guard !data.isEmpty else {
                #if DEBUG
                print("⚠️ Boş response body - Status Code: \(httpResponse.statusCode)")
                #endif
                // HTTP 2xx ise ve boş body varsa, Bool bekleniyorsa true döndür (başarılı kabul)
                if (200...299).contains(httpResponse.statusCode) {
                if T.self == Bool.self, let boolValue = true as? T {
                    return boolValue
                    }
                }
                // Boş body ve hata kodu varsa, özel hata mesajı
                if !(200...299).contains(httpResponse.statusCode) {
                    throw APIError.apiError("Sunucu yanıt vermedi (Status: \(httpResponse.statusCode))")
                }
                throw APIError.invalidResponse
            }
            
            // Response string'i logla (debug için - production'da kapalı, hassas veriler sanitize edilmiş)
            #if DEBUG
            if let responseString = String(data: data, encoding: .utf8) {
                let sanitized = SecureLogger.sanitizeJSON(responseString)
                SecureLogger.d("APIService", "Response: \(sanitized.prefix(500))")
            }
            #endif
            
            do {
                // PHP API'den gelen response formatı: {"success": true, "data": {...}, "message": "...", "error": null}
                // Önce APIResponseWrapper olarak decode et
                do {
                    let apiResponse = try APIService.decoder.decode(APIResponseWrapper<T>.self, from: data)
                    
                    // Success kontrolü
                    guard apiResponse.success else {
                        let errorMsg = apiResponse.error ?? apiResponse.message ?? "API hatası"
                        #if DEBUG
                        print("❌ API hatası: \(errorMsg)")
                        #endif
                        throw APIError.apiError(errorMsg)
                    }
                    
                    // Data kontrolü
                    if let responseData = apiResponse.data {
                        #if DEBUG
                        print("✅ Başarıyla decode edildi (wrapper ile): \(T.self)")
                        #endif
                        return responseData
                    } else {
                        // Data null ise ama success true ise, boş array veya default değer döndürmeyi dene
                        #if DEBUG
                        print("⚠️ API'den data=null geldi, boş array döndürülüyor")
                        #endif
                        // Boş array'ler için
                        if T.self == [Community].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [Event].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [Campaign].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [AppNotification].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [Member].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [Product].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        if T.self == [University].self, let emptyArray = [] as? T {
                            return emptyArray
                        }
                        // Eğer boş array değilse, direkt decode etmeyi dene
                        throw DecodingError.valueNotFound(T.self, DecodingError.Context(codingPath: [], debugDescription: "data is null"))
                    }
                } catch let wrapperError as DecodingError {
                    // Wrapper decode başarısız oldu, direkt decode etmeyi dene
                    #if DEBUG
                    print("⚠️ Wrapper decode başarısız, direkt decode deneniyor: \(wrapperError.localizedDescription)")
                    #endif
                    do {
                        let directData = try APIService.decoder.decode(T.self, from: data)
                        #if DEBUG
                        print("✅ Başarıyla decode edildi (direkt): \(T.self)")
                        #endif
                        return directData
                    } catch {
                        // Her iki yöntem de başarısız
                        #if DEBUG
                        print("❌ Hem wrapper hem direkt decode başarısız")
                        #endif
                        throw wrapperError
                    }
                }
            } catch let decodingError as DecodingError {
                #if DEBUG
                print("❌ Decoding hatası: \(decodingError)")
                // DecodingError detaylarını göster
                switch decodingError {
                case .typeMismatch(let type, let context):
                    print("   Type mismatch: \(type), path: \(context.codingPath.map { $0.stringValue }.joined(separator: "."))")
                case .valueNotFound(let type, let context):
                    print("   Value not found: \(type), path: \(context.codingPath.map { $0.stringValue }.joined(separator: "."))")
                case .keyNotFound(let key, let context):
                    print("   Key not found: \(key.stringValue), path: \(context.codingPath.map { $0.stringValue }.joined(separator: "."))")
                case .dataCorrupted(let context):
                    print("   Data corrupted: \(context.debugDescription), path: \(context.codingPath.map { $0.stringValue }.joined(separator: "."))")
                @unknown default:
                    print("   Unknown decoding error")
                }
                if let string = String(data: data, encoding: .utf8) {
                    let sanitized = SecureLogger.sanitizeJSON(string)
                    SecureLogger.e("APIService", "Decoding error - Full response: \(sanitized)")
                }
                #endif
                throw APIError.decodingError(decodingError)
            } catch {
                #if DEBUG
                print("❌ Decoding hatası: \(error)")
                if let string = String(data: data, encoding: .utf8) {
                    let sanitized = SecureLogger.sanitizeJSON(string)
                    SecureLogger.e("APIService", "Decoding error - Response: \(sanitized.prefix(500))")
                }
                #endif
                throw APIError.decodingError(error)
            }
        } catch let error as APIError {
            #if DEBUG
            print("❌ API Error: \(error.errorDescription ?? "Bilinmeyen")")
            #endif
            throw error
        } catch {
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if isCancelled {
                #if DEBUG
                print("⚠️ Network request cancelled")
                #endif
                throw error
            }
            #if DEBUG
            print("❌ Network Error: \(error.localizedDescription)")
            #endif
            throw APIError.networkError(error)
        }
    }
    
    // MARK: - Void Request (decode yapmaz)
    private func requestVoid(
        endpoint: String,
        method: String = "POST",
        body: Data? = nil,
        headers: [String: String]? = nil
    ) async throws {
        // Endpoint'i temizle (başında / varsa kaldır)
        let cleanEndpoint = endpoint.hasPrefix("/") ? String(endpoint.dropFirst()) : endpoint
        
        // AppConfig'den base URL'i al
        let baseURL = AppConfig.shared.baseURL
        let requestURL = "\(baseURL)/\(cleanEndpoint)"
        
        // Request gönder
        try await performVoidRequest(
            urlString: requestURL,
            method: method,
            body: body,
            headers: headers
        )
    }
    
    // MARK: - Perform Void Request Helper
    private func performVoidRequest(
        urlString: String,
        method: String,
        body: Data?,
        headers: [String: String]?
    ) async throws {
        guard let url = URL(string: urlString) else {
            throw APIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if let headers = headers {
            for (k, v) in headers { request.setValue(v, forHTTPHeaderField: k) }
        }
        if let token = getAuthToken() {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        if let body = body { request.httpBody = body }
        
        do {
            let (_, response) = try await session.data(for: request)
            guard let httpResponse = response as? HTTPURLResponse else {
                throw APIError.invalidResponse
            }
            guard (200...299).contains(httpResponse.statusCode) else {
                switch httpResponse.statusCode {
                case 401: throw APIError.unauthorized
                case 404: throw APIError.notFound
                default: throw APIError.httpError(httpResponse.statusCode)
                }
            }
        } catch let error as APIError {
            #if DEBUG
            print("❌ API Error: \(error.errorDescription ?? "Bilinmeyen")")
            #endif
            throw error
        } catch {
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if isCancelled {
                #if DEBUG
                print("⚠️ Network request cancelled")
                #endif
                throw error
            }
            #if DEBUG
            print("❌ Network Error: \(error.localizedDescription)")
            #endif
            throw APIError.networkError(error)
        }
    }
    
    // MARK: - Auth Token Management (Secure Storage)
    func getAuthToken() -> String? {
        return SecureStorage.shared.getToken()
    }
    
    func setAuthToken(_ token: String) {
        SecureStorage.shared.saveToken(token)
    }
    
    func clearAuthToken() {
        SecureStorage.shared.deleteToken()
    }
    
    // MARK: - Communities
    func getUniversities() async throws -> [University] {
        let result: [University] = try await request(endpoint: "universities.php")
        return result
    }
    
    func getCommunities(universityId: String? = nil, limit: Int = 30, offset: Int = 0) async throws -> CommunitiesResponse {
        var endpoint = "communities.php"
        var queryParams: [String] = []
        
        if let universityId = universityId, !universityId.isEmpty, universityId != "all" {
            let encodedId = encodeQueryValue(universityId)
            queryParams.append("university_id=\(encodedId)")
            #if DEBUG
            SecureLogger.d("APIService", "getCommunities - universityId: '\(universityId)' -> encoded: '\(encodedId)'")
            #endif
        }
        
        queryParams.append("limit=\(limit)")
        queryParams.append("offset=\(offset)")
        
        if !queryParams.isEmpty {
            endpoint += "?" + queryParams.joined(separator: "&")
        }
        
        #if DEBUG
        SecureLogger.d("APIService", "getCommunities endpoint: \(endpoint)")
        #endif
        
        // Topluluklar için cache'i devre dışı bırak (her zaman fresh data)
        let response: APIResponse<[Community]> = try await request(endpoint: endpoint, useCache: false)
        
        #if DEBUG
        SecureLogger.d("APIService", "getCommunities response - success: \(response.success), data count: \(response.data?.count ?? 0)")
        if let data = response.data, !data.isEmpty {
            SecureLogger.d("APIService", "getCommunities - First community: \(data[0].id) - \(data[0].name)")
        } else {
            SecureLogger.d("APIService", "getCommunities - WARNING: response.data is empty or nil!")
        }
        #endif
        
        // Pagination bilgilerini al
        let pagination = response.pagination
        let hasMore = pagination?["has_more"] as? Bool ?? false
        
        return CommunitiesResponse(
            communities: response.data ?? [],
            hasMore: hasMore
        )
    }
    
    // Eski API uyumluluğu için (geriye dönük uyumluluk)
    func getCommunitiesLegacy(universityId: String? = nil) async throws -> [Community] {
        let response = try await getCommunities(universityId: universityId, limit: 1000, offset: 0)
        return response.communities
    }
    
    func getVerifiedCommunities() async throws -> [VerifiedCommunityInfo] {
        struct VerifiedCommunitiesResponse: Codable {
            struct DataBlock: Codable {
                let count: Int
                let items: [VerifiedCommunityInfo]
            }
            let success: Bool
            let data: DataBlock?
            let message: String?
            let error: String?
        }
        
        let response: VerifiedCommunitiesResponse = try await request(
            endpoint: "verified_communities.php",
            useCache: false
        )
        
        guard response.success else {
            let message = response.error ?? response.message ?? "Onaylı topluluklar alınamadı."
            throw APIError.apiError(message)
        }
        
        return response.data?.items ?? []
    }
    
    func getCommunity(id: String) async throws -> Community {
        let result: Community = try await request(endpoint: "communities.php?id=\(id)")
        return result
    }
    
    // MARK: - Hosting Fallback Helpers (Kaldırıldı)
    // Hosting bağlantısı kaldırıldı - sadece localhost API kullanılacak
    
    func toggleFavoriteCommunity(communityId: String) async throws -> Bool {
        struct FavoriteRequest: Codable {
            let community_id: String
        }
        let body = try APIService.encoder.encode(FavoriteRequest(community_id: communityId))
        let response: FavoriteResponse = try await request(
            endpoint: "favorites.php",
            method: "POST",
            body: body
        )
        return response.isFavorite
    }
    
    // MARK: - Events
    /// communityId verilirse sadece o topluluğun etkinlikleri döner.
    /// universityId verilirse (ve communityId nil ise) sadece seçili üniversitenin etkinlikleri döner.
    /// communityId verilirse sadece o topluluğun etkinlikleri döner.
    /// universityId verilirse (ve communityId nil ise) sadece seçili üniversitenin etkinlikleri döner.
    func getEvents(communityId: String? = nil, universityId: String? = nil, search: String? = nil, limit: Int = 200, offset: Int = 0, sort: String? = nil) async throws -> [Event] {
        var endpoint = "events.php"
        var params: [String] = []
        
        if let universityId = universityId, !universityId.isEmpty, universityId != "all" {
            let encodedId = encodeQueryValue(universityId)
            params.append("university_id=\(encodedId)")
            #if DEBUG
            SecureLogger.d("APIService", "getEvents - universityId: '\(universityId)' -> encoded: '\(encodedId)'")
            #endif
        }
        
        if let search = search, !search.isEmpty {
            params.append("q=\(encodeQueryValue(search))")
        }
        
        if let communityId = communityId {
            params.append("community_id=\(encodeQueryValue(communityId))")
            // Pagination artık community_id ile de destekleniyor
            params.append("limit=\(limit)")
            params.append("offset=\(offset)")
        } else {
            // Pagination tüm etkinlikler için
            params.append("limit=\(limit)")
            params.append("offset=\(offset)")
        }
        
        // Sıralama parametresi
        if let sort = sort {
            params.append("sort=\(sort)")
        }
        
        if !params.isEmpty {
            endpoint += "?" + params.joined(separator: "&")
        }
        #if DEBUG
        SecureLogger.d("APIService", "getEvents endpoint: \(endpoint)")
        #endif
        
        let result: [Event] = try await request(endpoint: endpoint)
        return result
    }
    
    func getEvent(id: String) async throws -> Event {
        let result: Event = try await request(endpoint: "events.php?id=\(id)")
        return result
    }
    
    func registerForEvent(eventId: String) async throws -> Bool {
        // Sunucu 204 dönerse true döneriz
        let success: Bool = try await request(
            endpoint: "events/\(eventId)/register",
            method: "POST"
        )
        return success
    }
    
    // MARK: - Campaigns
    /// communityId verilirse sadece o topluluğun kampanyaları döner.
    /// universityId verilirse (ve communityId nil ise) sadece seçili üniversitenin kampanyaları döner.
    func getCampaigns(communityId: String? = nil, universityId: String? = nil) async throws -> [Campaign] {
        var endpoint = "campaigns.php"
        var params: [String] = []
        
        if let universityId = universityId, !universityId.isEmpty, universityId != "all" {
            params.append("university_id=\(encodeQueryValue(universityId))")
        }
        if let communityId = communityId {
            params.append("community_id=\(encodeQueryValue(communityId))")
        }
        if !params.isEmpty {
            endpoint += "?" + params.joined(separator: "&")
        }
        #if DEBUG
        SecureLogger.d("APIService", "getCampaigns endpoint: \(endpoint)")
        #endif
        let result: [Campaign] = try await request(endpoint: endpoint, useCache: false)
        return result
    }
    
    func getCampaign(id: String) async throws -> Campaign {
        let result: Campaign = try await request(endpoint: "campaigns.php?id=\(id)")
        return result
    }
    
    func toggleSaveCampaign(campaignId: String, communityId: String) async throws -> Bool {
        struct SaveRequest: Codable {
            let campaign_id: String
            let community_id: String
        }
        let body = try APIService.encoder.encode(SaveRequest(campaign_id: campaignId, community_id: communityId))
        let response: SavedResponse = try await request(
            endpoint: "saved_campaigns.php",
            method: "POST",
            body: body
        )
        return response.isSaved
    }
    
    // MARK: - Campaign Codes
    struct CampaignCodeResponse: Codable {
        let code: String
        let qr_code_data: String?
        let used: Bool
        let used_at: String?
        let campaign_id: String
        let campaign_title: String
    }
    
    /// Kullanıcıya özel kampanya kodu al
    func getCampaignCode(campaignId: String, communityId: String) async throws -> CampaignCodeResponse {
        let result: CampaignCodeResponse = try await request(
            endpoint: "get_campaign_code.php?campaign_id=\(campaignId)&community_id=\(communityId)"
        )
        return result
    }
    
    struct VerifyCampaignCodeRequest: Codable {
        let code: String
        let campaign_id: String
    }
    
    struct VerifyCampaignCodeResponse: Codable {
        let campaign_id: String
        let campaign_title: String
        let code: String
        let used_at: String
        let user_id: String
    }
    
    /// Kampanya kodunu doğrula (dükkanda kullanım için)
    func verifyCampaignCode(code: String, campaignId: String) async throws -> VerifyCampaignCodeResponse {
        let body = try APIService.encoder.encode(VerifyCampaignCodeRequest(code: code, campaign_id: campaignId))
        let result: VerifyCampaignCodeResponse = try await request(
            endpoint: "verify_campaign_code.php",
            method: "POST",
            body: body
        )
        return result
    }
    
    // MARK: - Notifications
    func getNotifications() async throws -> [AppNotification] {
        let result: [AppNotification] = try await request(endpoint: "notifications.php")
        return result
    }
    
    func markNotificationAsRead(id: String) async throws -> Bool {
        struct ReadRequest: Codable {
            let id: String
        }
        let body = try APIService.encoder.encode(ReadRequest(id: id))
        let response: [String: Bool] = try await request(
            endpoint: "notifications.php",
            method: "POST",
            body: body
        )
        return response["is_read"] ?? false
    }
    
    func markAllNotificationsAsRead() async throws -> Bool {
        let response: [String: Bool] = try await request(
            endpoint: "notifications.php",
            method: "PUT"
        )
        return response["updated"] ?? false
    }
    
    func deleteNotification(id: String) async throws -> Bool {
        // 204 dönerse true
        let success: Bool = try await request(
            endpoint: "notifications/\(id)",
            method: "DELETE"
        )
        return success
    }
    
    // MARK: - User
    func getCurrentUser() async throws -> User {
        // Token kontrolü
        guard getAuthToken() != nil else {
            #if DEBUG
            SecureLogger.w("APIService", "getCurrentUser: Token bulunamadı")
            #endif
            throw APIError.unauthorized
        }
        
        // user_id parametresi göndermeden çağır - API token'dan user_id'yi çıkaracak
        #if DEBUG
        SecureLogger.d("APIService", "getCurrentUser çağrılıyor")
        #endif
        let result: User = try await request(endpoint: "user.php")
        #if DEBUG
        print("✅ getCurrentUser başarılı: \(result.displayName)")
        #endif
        return result
    }
    
    func updateUserProfile(_ user: User) async throws -> User {
        // Token kontrolü
        guard getAuthToken() != nil else {
            throw APIError.unauthorized
        }
        
        // API'ye gönderilecek request body formatı (snake_case)
        struct UpdateProfileRequest: Codable {
            let firstName: String?
            let lastName: String?
            let email: String?
            let studentId: String?
            let phoneNumber: String?
            let university: String?
            let department: String?
            
            enum CodingKeys: String, CodingKey {
                case firstName = "first_name"
                case lastName = "last_name"
                case email
                case studentId = "student_id"
                case phoneNumber = "phone_number"
                case university
                case department
            }
        }
        
        let updateRequest = UpdateProfileRequest(
            firstName: user.firstName.isEmpty ? nil : user.firstName,
            lastName: user.lastName.isEmpty ? nil : user.lastName,
            email: user.email.isEmpty ? nil : user.email,
            studentId: user.studentId,
            phoneNumber: user.phoneNumber,
            university: user.university.isEmpty ? nil : user.university,
            department: user.department.isEmpty ? nil : user.department
        )
        
        let body = try APIService.encoder.encode(updateRequest)
        
        // API'den sadece success mesajı dönüyor, User objesi dönmüyor
        // Bu yüzden önce update yap, sonra getCurrentUser çağır
        // user_id parametresi göndermeden çağır - API token'dan user_id'yi çıkaracak
        struct UpdateResponse: Codable {
            let success: Bool
            let message: String?
            let error: String?
        }
        
        let _: UpdateResponse = try await request(
            endpoint: "user.php",
            method: "PUT",
            body: body
        )
        
        // Güncelleme başarılı, şimdi güncel kullanıcı bilgilerini çek
        return try await getCurrentUser()
    }
    
    func updateNotificationSettings(_ settings: User.NotificationSettings) async throws -> Bool {
        let body = try APIService.encoder.encode(settings)
        // 204 dönerse true
        let success: Bool = try await request(
            endpoint: "user/notification-settings",
            method: "PUT",
            body: body
        )
        return success
    }
    
    // MARK: - Authentication
    func login(email: String, password: String) async throws -> String {
        #if DEBUG
        SecureLogger.d("APIService", "Login işlemi başlatılıyor")
        #endif
        
        // Input validation - gerçek hayat senaryoları için
        let trimmedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        let trimmedPassword = password.trimmingCharacters(in: .whitespacesAndNewlines)
        
        guard !trimmedEmail.isEmpty else {
            throw APIError.apiError("Email adresi boş olamaz")
        }
        
        guard !trimmedPassword.isEmpty else {
            throw APIError.apiError("Şifre boş olamaz")
        }
        
        // Email format validation
        let emailRegex = "[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,64}"
        let emailPredicate = NSPredicate(format:"SELF MATCHES %@", emailRegex)
        guard emailPredicate.evaluate(with: trimmedEmail) else {
            throw APIError.apiError("Geçerli bir e-posta adresi giriniz")
        }
        
        // Password length validation
        guard trimmedPassword.count >= 6 && trimmedPassword.count <= 128 else {
            throw APIError.apiError("Şifre 6-128 karakter arasında olmalıdır")
        }
        
        // Request deduplication - aynı login isteğinin tekrar gönderilmesini önle
        let requestKey = RequestManager.generateRequestKey(endpoint: "login.php", method: "POST", body: nil)
        
        // Eğer aynı istek zaten devam ediyorsa bekle
        if await RequestManager.shared.isRequestInProgress(key: requestKey) {
            #if DEBUG
            print("⏳ Login zaten devam ediyor, bekleniyor...")
            #endif
            try await Task.sleep(nanoseconds: 500_000_000) // 0.5 saniye bekle
            if let token = getAuthToken() {
                return token
            }
        }
        
        await RequestManager.shared.requestStarted(key: requestKey)
        defer {
            Task {
                await RequestManager.shared.requestCompleted()
                await RequestManager.shared.requestFinished(key: requestKey)
            }
        }
        
        let loginRequest = LoginRequest(email: trimmedEmail, password: trimmedPassword)
        let body = try APIService.encoder.encode(loginRequest)
        
        // Login isteği
        let response: LoginResponse = try await request(
            endpoint: "login.php",
            method: "POST",
            body: body
        )
        
        // Token'ı kaydet
        setAuthToken(response.token)
        
        #if DEBUG
        SecureLogger.d("APIService", "Login başarılı, token kaydedildi")
        #endif
        
        return response.token
    }
    
    func register(userData: [String: Any]) async throws -> User {
        #if DEBUG
        SecureLogger.d("APIService", "Register işlemi başlatılıyor")
        #endif
        
        let body = try JSONSerialization.data(withJSONObject: userData)
        
        // Register isteği - auth_register.php
        // Bu endpoint hem token hem de user bilgisini döner
        let response: LoginResponse = try await request(
            endpoint: "auth_register.php",
            method: "POST",
            body: body
        )
        
        // Token'ı kaydet (Otomatik giriş)
        setAuthToken(response.token)
        
        #if DEBUG
        SecureLogger.d("APIService", "Register başarılı, token kaydedildi")
        #endif
        
        return response.user
    }
    
    // MARK: - Verification

    
    func sendVerificationCode(email: String) async throws {
        #if DEBUG
        SecureLogger.d("APIService", "E-posta doğrulama kodu gönderiliyor: \(email)")
        #endif
        
        struct SendCodeRequestBody: Codable {
            let email: String
        }
        
        let body = try APIService.encoder.encode(SendCodeRequestBody(email: email))
        
        do {
            struct SendCodeResponse: Codable {
                let success: Bool
                let message: String?
                let error: String?
            }
            
            let _: SendCodeResponse = try await request(
                endpoint: "send_verification_code.php",
                method: "POST",
                body: body
            )
            
            #if DEBUG
            SecureLogger.d("APIService", "E-posta doğrulama kodu gönderildi")
            #endif
        } catch {
            #if DEBUG
            SecureLogger.d("APIService", "E-posta doğrulama kodu gönderme hatası: \(error.localizedDescription)")
            #endif
            throw error
        }
    }
    
    func verifyEmailCode(email: String, code: String) async throws -> Bool {
        #if DEBUG
        SecureLogger.d("APIService", "E-posta doğrulama kodu kontrol ediliyor")
        #endif
        
        struct VerifyCodeRequestBody: Codable {
            let email: String
            let code: String
        }
        
        let body = try APIService.encoder.encode(VerifyCodeRequestBody(email: email, code: code))
        
        do {
            struct VerifyCodeResponse: Codable {
                let success: Bool
                let data: VerifyData?
                let message: String?
                let error: String?
                
                struct VerifyData: Codable {
                    let email: String
                    let verified: Bool
                }
            }
            
            let response: VerifyCodeResponse = try await request(
                endpoint: "verify_email_code.php",
                method: "POST",
                body: body
            )
            
            #if DEBUG
            SecureLogger.d("APIService", "E-posta doğrulama kodu kontrol edildi: \(response.success)")
            #endif
            
            return response.success && (response.data?.verified ?? false)
        } catch {
            #if DEBUG
            SecureLogger.d("APIService", "E-posta doğrulama kodu kontrol hatası: \(error.localizedDescription)")
            #endif
            throw error
        }
    }
    
    func logout() async throws {
        // Boş dönebilir, sadece status kontrolü yeterli
        try await requestVoid(
            endpoint: "auth/logout",
            method: "POST"
        )
        clearAuthToken()
    }
    
    // MARK: - 2FA Registration - KALDIRILDI (Kayıt altyapısı kaldırıldı)
    // Tüm register2FA fonksiyonları kaldırıldı - kayıt altyapısı kaldırıldı
    
    // MARK: - Members
    func getMembers(communityId: String) async throws -> [Member] {
        let result: [Member] = try await request(endpoint: "members.php?community_id=\(communityId)")
        return result
    }
    
    // MARK: - Membership Request
    /// Request membership to a community
    func requestMembership(communityId: String) async throws -> MembershipStatus {
        struct MembershipStatusResponse: Codable {
            let status: String?
            let isMember: Bool?
            let isPending: Bool?
            let requestId: String?
            let createdAt: String?
            let message: String?
            
            enum CodingKeys: String, CodingKey {
                case status
                case isMember = "is_member"
                case isPending = "is_pending"
                case requestId = "request_id"
                case createdAt = "created_at"
                case message
            }
            
            init(from decoder: Decoder) throws {
                let container = try decoder.container(keyedBy: CodingKeys.self)
                status = try? container.decodeIfPresent(String.self, forKey: .status)
                isMember = try? container.decodeIfPresent(Bool.self, forKey: .isMember)
                isPending = try? container.decodeIfPresent(Bool.self, forKey: .isPending)
                message = try? container.decodeIfPresent(String.self, forKey: .message)
                
                // requestId Int veya String olabilir
                if let intId = try? container.decode(Int.self, forKey: .requestId) {
                    requestId = String(intId)
                } else {
                    requestId = try? container.decodeIfPresent(String.self, forKey: .requestId)
                }
                
                createdAt = try? container.decodeIfPresent(String.self, forKey: .createdAt)
            }
        }
        
        let endpoint = "membership_status.php?community_id=\(encodeQueryValue(communityId))"
        
        // Use wrapper to handle both success and error responses
        let wrapper: APIResponseWrapper<MembershipStatusResponse> = try await request(
            endpoint: endpoint,
            method: "POST",
            body: nil
        )
        
        guard wrapper.success else {
            throw APIError.apiError(wrapper.error ?? wrapper.message ?? "Üyelik başvurusu gönderilemedi")
        }
        
        // data mevcut ve başarılı bir yanıt ise
        if let data = wrapper.data {
            return MembershipStatus(
                status: data.status ?? "pending",
                isMember: data.isMember ?? false,
                isPending: data.isPending ?? true,
                requestId: data.requestId,
                createdAt: data.createdAt
            )
        }
        
        // data null ama success true ise, pending olarak kabul et
        return MembershipStatus(
            status: "pending",
            isMember: false,
            isPending: true,
            requestId: nil,
            createdAt: nil
        )
    }
    
    // MARK: - Board Members
    func getBoardMembers(communityId: String) async throws -> [BoardMember] {
        let result: [BoardMember] = try await request(endpoint: "board.php?community_id=\(communityId)")
        return result
    }
    
    // MARK: - Products
    func getProducts(communityId: String, limit: Int? = nil, offset: Int? = nil) async throws -> [Product] {
        #if DEBUG
        print("🛍️ Ürünler çekiliyor... (communityId: \(communityId), limit: \(limit ?? -1), offset: \(offset ?? -1))")
        #endif
        // Ürünler için cache'i devre dışı bırak (her zaman fresh data)
        var endpoint = "products.php?community_id=\(communityId)"
        if let limit = limit {
            endpoint += "&limit=\(limit)"
        }
        if let offset = offset {
            endpoint += "&offset=\(offset)"
        }
        let result: [Product] = try await request(endpoint: endpoint, useCache: false)
        #if DEBUG
        print("✅ \(result.count) ürün çekildi")
        #endif
        return result
    }
    
    func getAllProducts(universityId: String? = nil, limit: Int = 20, offset: Int = 0) async throws -> [Product] {
        #if DEBUG
        print("🛍️ Ürünler çekiliyor (limit: \(limit), offset: \(offset))...")
        #endif
        // Pagination ile ürünleri çek (cache devre dışı - her zaman fresh data)
        var endpoint = "products.php"
        var params: [String] = []
        if let universityId = universityId, !universityId.isEmpty, universityId != "all" {
            params.append("university_id=\(encodeQueryValue(universityId))")
        }
        params.append("limit=\(limit)")
        params.append("offset=\(offset)")
        endpoint += "?" + params.joined(separator: "&")
        #if DEBUG
        SecureLogger.d("APIService", "getAllProducts endpoint: \(endpoint)")
        #endif
        let result: [Product] = try await request(endpoint: endpoint, useCache: false)
        #if DEBUG
        print("✅ \(result.count) ürün çekildi (limit: \(limit), offset: \(offset))")
        #endif
        return result
    }
    
    func getProduct(id: String, communityId: String) async throws -> Product {
        let result: Product = try await request(endpoint: "products.php?id=\(id)&community_id=\(communityId)")
        return result
    }
    
    // MARK: - Market v2 API
    
    /// Product filters for v2 API
    // ProductFilters Models.swift'e taşındı
    
    /// Get products using v2 API with advanced filters
    func getProductsV2(filters: ProductFilters) async throws -> ProductsV2Response {
        #if DEBUG
        print("🛍️ [v2] Ürünler çekiliyor...")
        #endif
        
        let queryString = filters.toQueryString()
        let endpoint = "v2/market/products.php?\(queryString)"
        
        let result: ProductsV2Response = try await request(endpoint: endpoint, useCache: false)
        
        #if DEBUG
        print("✅ [v2] \(result.products.count) ürün çekildi (toplam: \(result.pagination.total))")
        #endif
        
        return result
    }
    
    /// Get single product using v2 API
    func getProductV2(id: Int, communityId: String) async throws -> Product {
        let endpoint = "v2/market/products.php?id=\(id)&community_id=\(communityId)"
        let result: Product = try await request(endpoint: endpoint, useCache: false)
        return result
    }
    
    /// Get product categories
    func getProductCategories(communityId: String? = nil, universityId: String? = nil) async throws -> [ProductCategory] {
        #if DEBUG
        print("📂 Kategoriler çekiliyor...")
        #endif
        
        var params: [String] = []
        if let communityId = communityId, !communityId.isEmpty {
            params.append("community_id=\(communityId)")
        }
        if let universityId = universityId, !universityId.isEmpty, universityId != "all" {
            params.append("university_id=\(universityId.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? universityId)")
        }
        
        let endpoint = params.isEmpty 
            ? "v2/market/categories.php" 
            : "v2/market/categories.php?\(params.joined(separator: "&"))"
        
        struct CategoriesResponse: Codable {
            let categories: [ProductCategory]
            let total: Int
        }
        
        let result: CategoriesResponse = try await request(endpoint: endpoint, useCache: true)
        
        #if DEBUG
        print("✅ \(result.categories.count) kategori çekildi")
        #endif
        
        return result.categories
    }
    
    // MARK: - Orders v2 API
    
    /// Create a new order
    func createOrder(items: [CartItem], customerName: String, customerEmail: String, customerPhone: String) async throws -> CreateOrderResponse {
        #if DEBUG
        print("📦 Sipariş oluşturuluyor...")
        #endif
        
        struct OrderRequest: Codable {
            struct OrderItem: Codable {
                let productId: Int
                let communityId: String
                let quantity: Int
                
                enum CodingKeys: String, CodingKey {
                    case productId = "product_id"
                    case communityId = "community_id"
                    case quantity
                }
            }
            
            struct Customer: Codable {
                let name: String
                let email: String
                let phone: String
            }
            
            let items: [OrderItem]
            let customer: Customer
        }
        
        let orderItems = items.map { item in
            OrderRequest.OrderItem(
                productId: Int(item.product.id) ?? 0,
                communityId: item.product.communityId,
                quantity: item.quantity
            )
        }
        
        let orderRequest = OrderRequest(
            items: orderItems,
            customer: OrderRequest.Customer(
                name: customerName,
                email: customerEmail,
                phone: customerPhone
            )
        )
        
        let body = try APIService.encoder.encode(orderRequest)
        
        let result: CreateOrderResponse = try await request(
            endpoint: "v2/market/orders.php",
            method: "POST",
            body: body
        )
        
        #if DEBUG
        print("✅ Sipariş oluşturuldu: \(result.orderNumber)")
        #endif
        
        return result
    }
    
    /// Get user's orders
    func getOrders(page: Int = 1, limit: Int = 20) async throws -> OrdersV2Response {
        #if DEBUG
        print("📋 Siparişler çekiliyor (sayfa: \(page))...")
        #endif
        
        let endpoint = "v2/market/orders.php?page=\(page)&limit=\(limit)"
        let result: OrdersV2Response = try await request(endpoint: endpoint, useCache: false)
        
        #if DEBUG
        print("✅ \(result.orders.count) sipariş çekildi")
        #endif
        
        return result
    }
    
    /// Get single order details
    func getOrder(id: String) async throws -> Order {
        #if DEBUG
        print("📋 Sipariş detayı çekiliyor: \(id)...")
        #endif
        
        let endpoint = "v2/market/orders.php?id=\(id)"
        let result: Order = try await request(endpoint: endpoint, useCache: false)
        
        #if DEBUG
        print("✅ Sipariş çekildi: \(result.orderNumber)")
        #endif
        
        return result
    }
    
    // MARK: - Event Surveys (New API)
    /// Get survey for a specific event
    func getEventSurvey(communityId: String, eventId: Int) async throws -> Survey? {
        struct SurveyAPIResponse: Codable {
            let success: Bool
            let data: Survey?
            let message: String?
            let error: String?
        }
        
        let endpoint = "event_survey.php?community_id=\(encodeQueryValue(communityId))&event_id=\(eventId)"
        let response: SurveyAPIResponse = try await request(endpoint: endpoint)
        
        guard response.success else {
            if let error = response.error {
                throw APIError.apiError(error)
            }
            return nil
        }
        
        return response.data
    }
    
    /// Submit survey response
    func submitEventSurvey(communityId: String, eventId: Int, userEmail: String, userName: String?, responses: [SurveyResponseItem]) async throws {
        struct SurveySubmissionRequest: Codable {
            let userEmail: String
            let userName: String?
            let responses: [SurveyResponseItem]
            
            enum CodingKeys: String, CodingKey {
                case userEmail = "user_email"
                case userName = "user_name"
                case responses
            }
        }
        
        struct SurveySubmissionAPIResponse: Codable {
            let success: Bool
            let data: EmptyCodable?
            let message: String?
            let error: String?
        }
        
        struct EmptyCodable: Codable {}
        
        let requestBody = SurveySubmissionRequest(
            userEmail: userEmail,
            userName: userName,
            responses: responses
        )
        
        let body = try APIService.encoder.encode(requestBody)
        let endpoint = "event_survey.php?community_id=\(encodeQueryValue(communityId))&event_id=\(eventId)&action=submit"
        
        let response: SurveySubmissionAPIResponse = try await request(
            endpoint: endpoint,
            method: "POST",
            body: body
        )
        
        guard response.success else {
            throw APIError.apiError(response.error ?? "Anket yanıtı gönderilemedi")
        }
    }
    
    // MARK: - Event RSVP (New API)
    /// Get user's RSVP status for an event
    func getEventRSVPStatus(communityId: String, eventId: Int, userEmail: String) async throws -> RSVPStatusResponse {
        struct RSVPStatusAPIResponse: Codable {
            let success: Bool
            let data: RSVPStatusResponse?
            let message: String?
            let error: String?
        }
        
        let endpoint = "event_rsvp.php?community_id=\(encodeQueryValue(communityId))&event_id=\(eventId)&user_email=\(encodeQueryValue(userEmail))"
        let response: RSVPStatusAPIResponse = try await request(endpoint: endpoint)
        
        guard response.success, let data = response.data else {
            throw APIError.apiError(response.error ?? "RSVP durumu alınamadı")
        }
        
        return data
    }
    
    /// Create or update RSVP
    func createOrUpdateRSVP(communityId: String, eventId: Int, memberName: String, memberEmail: String, memberPhone: String?, status: RSVP.RSVPStatus) async throws -> RSVPCreateResponse {
        struct RSVPRequest: Codable {
            let memberName: String
            let memberEmail: String
            let memberPhone: String?
            let status: String
            
            enum CodingKeys: String, CodingKey {
                case memberName = "member_name"
                case memberEmail = "member_email"
                case memberPhone = "member_phone"
                case status
            }
        }
        
        struct RSVPCreateAPIResponse: Codable {
            let success: Bool
            let data: RSVPCreateResponse?
            let message: String?
            let error: String?
        }
        
        let rsvpRequest = RSVPRequest(
            memberName: memberName,
            memberEmail: memberEmail,
            memberPhone: memberPhone,
            status: status.rawValue
        )
        
        let body = try APIService.encoder.encode(rsvpRequest)
        let endpoint = "event_rsvp.php?community_id=\(encodeQueryValue(communityId))&event_id=\(eventId)"
        
        let response: RSVPCreateAPIResponse = try await request(
            endpoint: endpoint,
            method: "POST",
            body: body
        )
        
        guard response.success, let data = response.data else {
            throw APIError.apiError(response.error ?? "RSVP kaydedilemedi")
        }
        
        return data
    }
    
    /// Cancel RSVP
    func cancelRSVP(communityId: String, eventId: Int, userEmail: String) async throws {
        struct CancelRSVPAPIResponse: Codable {
            let success: Bool
            let data: RSVPStatistics?
            let message: String?
            let error: String?
        }
        
        let endpoint = "event_rsvp.php?community_id=\(encodeQueryValue(communityId))&event_id=\(eventId)&user_email=\(encodeQueryValue(userEmail))"
        
        let response: CancelRSVPAPIResponse = try await request(
            endpoint: endpoint,
            method: "DELETE",
            body: nil
        )
        
        guard response.success else {
            throw APIError.apiError(response.error ?? "RSVP iptal edilemedi")
        }
    }
    
    // MARK: - Legacy Surveys (Backward Compatibility)
    func getSurveys(communityId: String, eventId: String? = nil) async throws -> [Survey] {
        var endpoint = "surveys.php?community_id=\(encodeQueryValue(communityId))"
        if let eventId = eventId {
            endpoint += "&event_id=\(encodeQueryValue(eventId))"
        }
        let result: [Survey] = try await request(endpoint: endpoint)
        return result
    }
    
    func submitSurvey(communityId: String, surveyId: String, responses: [String: String]) async throws -> SurveySubmissionResponse {
        struct SurveySubmissionRequest: Codable {
            let surveyId: String
            let responses: [String: String]
            
            enum CodingKeys: String, CodingKey {
                case surveyId = "survey_id"
                case responses
            }
        }
        
        let requestBody = SurveySubmissionRequest(surveyId: surveyId, responses: responses)
        let body = try APIService.encoder.encode(requestBody)
        
        let result: SurveySubmissionResponse = try await request(
            endpoint: "submit_survey.php?community_id=\(encodeQueryValue(communityId))",
            method: "POST",
            body: body
        )
        return result
    }
    
    // MARK: - Legacy RSVP (Backward Compatibility)
    func getRSVP(communityId: String, eventId: String) async throws -> RSVPResponse {
        let result: RSVPResponse = try await request(endpoint: "rsvp.php?community_id=\(encodeQueryValue(communityId))&event_id=\(encodeQueryValue(eventId))")
        return result
    }
    
    func submitRSVP(communityId: String, eventId: String, memberName: String, status: RSVP.RSVPStatus, memberEmail: String? = nil, memberPhone: String? = nil) async throws -> RSVPResponse {
        struct RSVPRequest: Codable {
            let eventId: String
            let memberName: String
            let memberEmail: String?
            let memberPhone: String?
            let status: String
            
            enum CodingKeys: String, CodingKey {
                case eventId = "event_id"
                case memberName = "member_name"
                case memberEmail = "member_email"
                case memberPhone = "member_phone"
                case status
            }
        }
        
        let rsvpRequest = RSVPRequest(
            eventId: eventId,
            memberName: memberName,
            memberEmail: memberEmail,
            memberPhone: memberPhone,
            status: status.rawValue
        )
        let body = try APIService.encoder.encode(rsvpRequest)
        
        #if DEBUG
        if let jsonString = String(data: body, encoding: .utf8) {
            print("📤 RSVP Request Body: \(jsonString)")
        }
        print("📤 RSVP Endpoint: rsvp.php?community_id=\(communityId)")
        print("📤 RSVP Event ID: \(eventId)")
        #endif
        
        let result: RSVPResponse = try await request(
            endpoint: "rsvp.php?community_id=\(encodeQueryValue(communityId))",
            method: "POST",
            body: body
        )
        return result
    }
    
    // MARK: - Device Token Registration
    func registerDeviceToken(deviceToken: String, platform: String, communityId: String? = nil) async throws -> DeviceTokenResponse {
        struct DeviceTokenRequest: Codable {
            let deviceToken: String
            let platform: String
            let communityId: String?
            
            enum CodingKeys: String, CodingKey {
                case deviceToken = "device_token"
                case platform
                case communityId = "community_id"
            }
        }
        
        let requestBody = DeviceTokenRequest(deviceToken: deviceToken, platform: platform, communityId: communityId)
        let body = try APIService.encoder.encode(requestBody)
        
        let result: DeviceTokenResponse = try await request(
            endpoint: "register_device_token.php",
            method: "POST",
            body: body
        )
        return result
    }
    
    // MARK: - Community Member Registration
    func registerToCommunity(communityId: String, fullName: String, email: String? = nil, phoneNumber: String? = nil, studentId: String? = nil) async throws -> MemberRegistrationResponse {
        struct RegistrationRequest: Codable {
            let communityId: String
            let fullName: String
            let email: String?
            let phoneNumber: String?
            let studentId: String?
            
            enum CodingKeys: String, CodingKey {
                case communityId = "community_id"
                case fullName = "full_name"
                case email
                case phoneNumber = "phone_number"
                case studentId = "student_id"
            }
        }
        
        // Esnek response modeli - API'den farklı formatlar gelebilir
        struct RegisterAPIResponse: Codable {
            let memberId: String?
            let requestId: StringOrInt?
            
            enum CodingKeys: String, CodingKey {
                case memberId = "member_id"
                case requestId = "request_id"
            }
        }
        
        // String veya Int olabilen helper struct (private modifier kaldırıldı - local scope'ta kullanılamaz)
        struct StringOrInt: Codable {
            let value: String
            
            init(from decoder: Decoder) throws {
                let container = try decoder.singleValueContainer()
                if let intValue = try? container.decode(Int.self) {
                    value = String(intValue)
                } else if let stringValue = try? container.decode(String.self) {
                    value = stringValue
                } else {
                    value = ""
                }
            }
            
            func encode(to encoder: Encoder) throws {
                var container = encoder.singleValueContainer()
                try container.encode(value)
            }
        }
        
        let registrationRequest = RegistrationRequest(
            communityId: communityId,
            fullName: fullName,
            email: email,
            phoneNumber: phoneNumber,
            studentId: studentId
        )
        let body = try APIService.encoder.encode(registrationRequest)
        
        #if DEBUG
        if let bodyString = String(data: body, encoding: .utf8) {
            print("📤 Register request body: \(bodyString)")
        }
        #endif
        
        // API wrapper içinde data geliyor
        let wrapper: APIResponseWrapper<RegisterAPIResponse> = try await request(
            endpoint: "register.php",
            method: "POST",
            body: body
        )
        
        #if DEBUG
        print("📥 Register response wrapper: success=\(wrapper.success), error=\(wrapper.error ?? "nil"), message=\(wrapper.message ?? "nil")")
        #endif
        
        guard wrapper.success else {
            throw APIError.apiError(wrapper.error ?? wrapper.message ?? "Üyelik başvurusu başarısız")
        }
        
        // Data varsa kullan, yoksa başarılı kabul et (bazı API'ler data döndürmeyebilir)
        if let data = wrapper.data {
            // memberId veya requestId'den birini kullan
            let id = data.memberId ?? data.requestId?.value ?? ""
            #if DEBUG
            print("✅ Register response: memberId=\(data.memberId ?? "nil"), requestId=\(data.requestId?.value ?? "nil"), finalId=\(id)")
            #endif
            return MemberRegistrationResponse(memberId: id.isEmpty ? "success" : id)
        } else {
            // Data yoksa ama success true ise, başarılı kabul et
            #if DEBUG
            print("⚠️ Register response: data=nil ama success=true, başarılı kabul ediliyor")
            #endif
            return MemberRegistrationResponse(memberId: "success")
        }
    }
    
    // MARK: - Leave Community
    func leaveCommunity(communityId: String) async throws {
        struct LeaveRequest: Codable {
            let communityId: String
            
            enum CodingKeys: String, CodingKey {
                case communityId = "community_id"
            }
        }
        
        let leaveRequest = LeaveRequest(communityId: communityId)
        let body = try APIService.encoder.encode(leaveRequest)
        
        #if DEBUG
        print("🚪 Topluluktan ayrılıyor: \(communityId)")
        #endif
        
        let wrapper: APIResponseWrapper<EmptyResponse> = try await request(
            endpoint: "leave_community.php?community_id=\(communityId)",
            method: "DELETE",
            body: body
        )
        
        guard wrapper.success else {
            throw APIError.apiError(wrapper.error ?? wrapper.message ?? "Topluluktan ayrılma işlemi başarısız")
        }
        
        #if DEBUG
        print("✅ Topluluktan başarıyla ayrıldı: \(communityId)")
        #endif
    }
    
    // MARK: - Membership Status
    func getMembershipStatus(communityId: String) async throws -> MembershipStatus {
        struct MembershipStatusResponse: Codable {
            let status: String
            let isMember: Bool
            let isPending: Bool
            let requestId: String?
            let createdAt: String?
            
            enum CodingKeys: String, CodingKey {
                case status
                case isMember = "is_member"
                case isPending = "is_pending"
                case requestId = "request_id"
                case createdAt = "created_at"
            }
            
            init(from decoder: Decoder) throws {
                let container = try decoder.container(keyedBy: CodingKeys.self)
                status = try container.decode(String.self, forKey: .status)
                isMember = try container.decode(Bool.self, forKey: .isMember)
                isPending = try container.decode(Bool.self, forKey: .isPending)
                
                // requestId Int veya String olabilir
                if let intId = try? container.decode(Int.self, forKey: .requestId) {
                    requestId = String(intId)
                } else {
                    requestId = try? container.decodeIfPresent(String.self, forKey: .requestId)
                }
                
                createdAt = try? container.decodeIfPresent(String.self, forKey: .createdAt)
            }
        }
        
        // API'den membership status çek
        do {
            let data: MembershipStatusResponse = try await request(
                endpoint: "membership_status.php?community_id=\(communityId)",
                method: "GET"
            )
            
            return MembershipStatus(
                status: data.status,
                isMember: data.isMember,
                isPending: data.isPending,
                requestId: data.requestId,
                createdAt: data.createdAt
            )
        } catch {
            let errorMsg = error.localizedDescription
            if errorMsg.contains("429") || errorMsg.contains("Çok fazla istek") {
                // Rate limiting - üye değil olarak döndür
                return MembershipStatus(
                    status: "none",
                    isMember: false,
                    isPending: false,
                    requestId: nil,
                    createdAt: nil
                )
            }
            throw error
        }
    }
    
    // MARK: - User Profile (Extended)
    func getUserProfile(userId: String) async throws -> User {
        let result: User = try await request(endpoint: "user.php?user_id=\(userId)")
        return result
    }
    
    func updateUserProfileExtended(userId: String, firstName: String? = nil, lastName: String? = nil, email: String? = nil, studentId: String? = nil, phoneNumber: String? = nil, university: String? = nil, department: String? = nil, password: String? = nil) async throws -> User {
        struct UpdateProfileRequest: Codable {
            let firstName: String?
            let lastName: String?
            let email: String?
            let studentId: String?
            let phoneNumber: String?
            let university: String?
            let department: String?
            let password: String?
            
            enum CodingKeys: String, CodingKey {
                case firstName = "first_name"
                case lastName = "last_name"
                case email
                case studentId = "student_id"
                case phoneNumber = "phone_number"
                case university
                case department
                case password
            }
        }
        
        let updateRequest = UpdateProfileRequest(
            firstName: firstName,
            lastName: lastName,
            email: email,
            studentId: studentId,
            phoneNumber: phoneNumber,
            university: university,
            department: department,
            password: password
        )
        let body = try APIService.encoder.encode(updateRequest)
        let result: User = try await request(
            endpoint: "user.php?user_id=\(userId)",
            method: "PUT",
            body: body
        )
        return result
    }
    
    // MARK: - Image URL Helper
    static func imageURL(from path: String?) -> URL? {
        guard let path = path, !path.isEmpty else {
            return nil
        }
        
        // Eğer path zaten tam URL ise
        if path.hasPrefix("http://") || path.hasPrefix("https://") {
            return URL(string: path)
        }
        
        // Base URL ekle (APIService'in baseURL'ini kullan)
        // Not: Bu static method olduğu için baseURL'e direkt erişemiyoruz
        // Bu yüzden path'i olduğu gibi döndürüyoruz, view'larda baseURL eklenebilir
        return URL(string: path)
    }
    
    // ⚠️ HOSTING'E ALDIĞINIZDA: baseURL parametresini kendi domain'inizle değiştirin!
    static func fullImageURL(from path: String?, baseURL: String? = nil) -> String? {
        guard let path = path, !path.isEmpty else {
            return nil
        }
        
        // Eğer path zaten tam URL ise
        if path.hasPrefix("http://") || path.hasPrefix("https://") {
            return path
        }
        
        // Base URL belirle - AppConfig kullan
        let actualBaseURL: String
        if let providedBaseURL = baseURL {
            actualBaseURL = providedBaseURL
        } else {
            // AppConfig'den image base URL'i al
            actualBaseURL = AppConfig.shared.imageBaseURL
        }
        
        // Base URL ekle
        let cleanPath = path.hasPrefix("/") ? path : "/\(path)"
        return "\(actualBaseURL)\(cleanPath)"
    }
    
    // MARK: - Posts (Feed)
    func getPosts() async throws -> [Post] {
        let result: [Post] = try await request(endpoint: "posts.php")
        return result
    }
    
    func togglePostLike(postId: String) async throws -> Post {
        struct LikeRequest: Codable {
            var action: String = "like" // var kullan (initial value ile immutable property decode edilemez)
        }
        
        let body = try APIService.encoder.encode(LikeRequest())
        let result: Post = try await request(
            endpoint: "posts.php?post_id=\(postId)",
            method: "POST",
            body: body
        )
        return result
    }
    
    func getComments(postId: String) async throws -> [Comment] {
        let result: [Comment] = try await request(endpoint: "posts.php?post_id=\(postId)&action=comments")
        return result
    }
    
    func addComment(postId: String, content: String) async throws -> Comment {
        struct CommentRequest: Codable {
            let postId: String
            let content: String
            
            enum CodingKeys: String, CodingKey {
                case postId = "post_id"
                case content
            }
        }
        
        let commentRequest = CommentRequest(postId: postId, content: content)
        let body = try APIService.encoder.encode(commentRequest)
        let result: Comment = try await request(
            endpoint: "posts.php?action=comment",
            method: "POST",
            body: body
        )
        return result
    }
    
    // MARK: - Order Confirmation
    func confirmOrder(orderData: [String: Any]) async throws {
        struct OrderConfirmationResponse: Codable {
            let success: Bool
            let message: String?
            let order: OrderInfo?
            
            struct OrderInfo: Codable {
                let orderNumber: String
                let emailSent: Bool
                
                enum CodingKeys: String, CodingKey {
                    case orderNumber = "order_number"
                    case emailSent = "email_sent"
                }
            }
        }
        
        let body = try JSONSerialization.data(withJSONObject: orderData)
        
        #if DEBUG
        print("📦 Sipariş onayı gönderiliyor...")
        #endif
        
        let result: OrderConfirmationResponse = try await request(
            endpoint: "order_confirmation.php",
            method: "POST",
            body: body
        )
        
        if !result.success {
            throw APIError.apiError("Sipariş onayı başarısız: \(result.message ?? "Bilinmeyen hata")")
        }
        
        #if DEBUG
        print("✅ Sipariş onaylandı: \(result.order?.orderNumber ?? "Bilinmiyor")")
        #endif
    }
}

// MARK: - API Error
enum APIError: LocalizedError {
    case invalidURL
    case invalidResponse
    case httpError(Int)
    case decodingError(Error)
    case networkError(Error)
    case unauthorized
    case notFound
    case apiError(String)
    
    var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "Geçersiz URL"
        case .invalidResponse:
            return "Geçersiz yanıt"
        case .httpError(let code):
            return "HTTP Hatası: \(code)"
        case .decodingError(let error):
            return "Veri çözümleme hatası: \(error.localizedDescription)"
        case .networkError(let error):
            return "Ağ hatası: \(error.localizedDescription)"
        case .unauthorized:
            return "Yetkilendirme hatası"
        case .notFound:
            return "Bulunamadı"
        case .apiError(let message):
            return message
        }
    }
}

// MARK: - API Session Delegate
class APISessionDelegate: NSObject, URLSessionDelegate, @unchecked Sendable {
    // SSL Certificate Pinning - Production için güvenli
    func urlSession(_ session: URLSession, didReceive challenge: URLAuthenticationChallenge, completionHandler: @escaping (URLSession.AuthChallengeDisposition, URLCredential?) -> Void) {
        let host = challenge.protectionSpace.host
        
        // DEBUG modunda localhost için SSL doğrulamasını atla
        #if DEBUG
        if host.contains("localhost") || host.contains("127.0.0.1") {
            if let serverTrust = challenge.protectionSpace.serverTrust {
                let credential = URLCredential(trust: serverTrust)
                completionHandler(.useCredential, credential)
            } else {
                completionHandler(.performDefaultHandling, nil)
            }
            return
        }
        #endif
        
        // Production için SSL pinning
        guard let serverTrust = challenge.protectionSpace.serverTrust else {
            completionHandler(.cancelAuthenticationChallenge, nil)
            return
        }
        
        // SSL Pinning kontrolü - SSLPinningManager is not actor-isolated, safe to call
        let isPinned = SSLPinningManager.shared.validateCertificate(serverTrust: serverTrust, host: host)
        
        if isPinned {
            // Certificate pinned ve geçerli
            let credential = URLCredential(trust: serverTrust)
            completionHandler(.useCredential, credential)
        } else {
            // Certificate pinning başarısız veya pinning yapılmamış
            // Standart trust kontrolü yap
            var error: CFError?
            let isValid = SecTrustEvaluateWithError(serverTrust, &error)
            
            if isValid {
                // Standart validation başarılı (pinning yapılmamış host için)
                let credential = URLCredential(trust: serverTrust)
                completionHandler(.useCredential, credential)
            } else {
                #if DEBUG
                // SecureLogger is not actor-isolated, safe to call from nonisolated context
                if let error = error {
                    SecureLogger.e("APISessionDelegate", "SSL doğrulama hatası", error)
                } else {
                    SecureLogger.e("APISessionDelegate", "SSL doğrulama hatası", nil)
                }
                #endif
                completionHandler(.cancelAuthenticationChallenge, nil)
            }
        }
    }
    
    // Task tamamlandığında hata kontrolü
    func urlSession(_ session: URLSession, task: URLSessionTask, didCompleteWithError error: Error?) {
        if let error = error {
            #if DEBUG
            print("🔴 URLSession Task Error: \(error.localizedDescription)")
            if let urlError = error as? URLError {
                switch urlError.code {
                case .notConnectedToInternet:
                    print("   📶 İnternet bağlantısı yok")
                case .timedOut:
                    print("   ⏱️ Timeout - Sunucu yanıt vermiyor")
                case .cannotFindHost:
                    print("   🔍 Host bulunamadı - URL'yi kontrol edin")
                case .cannotConnectToHost:
                    print("   🔌 Host'a bağlanılamıyor - Sunucu çalışıyor mu?")
                case .networkConnectionLost:
                    print("   📡 Ağ bağlantısı kesildi")
                case .secureConnectionFailed:
                    print("   🔒 SSL bağlantı hatası")
                default:
                    print("   ❓ Diğer ağ hatası: \(urlError.code.rawValue)")
                }
            }
            #endif
        }
    }
}
