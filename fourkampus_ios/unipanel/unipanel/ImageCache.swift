//
//  ImageCache.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import Foundation

/// Image caching için helper class
class ImageCache {
    static let shared = ImageCache()
    
    private let cache = NSCache<NSString, UIImage>()
    private let fileManager = FileManager.default
    private let cacheDirectory: URL
    
    private init() {
        // Memory cache ayarları - gerçek hayat senaryoları için optimize edildi
        cache.countLimit = 150 // Maksimum 150 image (artırıldı)
        cache.totalCostLimit = 100 * 1024 * 1024 // 100 MB (artırıldı, daha fazla görsel için)
        
        // Disk cache dizini - ÖNCE initialize et
        let urls = fileManager.urls(for: .cachesDirectory, in: .userDomainMask)
        cacheDirectory = urls[0].appendingPathComponent("ImageCache", isDirectory: true)
        
        // Cache dizinini oluştur
        try? fileManager.createDirectory(at: cacheDirectory, withIntermediateDirectories: true)
        
        // Memory warning notification - düşük bellek durumunda cache'i temizle
        // cacheDirectory initialize edildikten SONRA observer ekle
        NotificationCenter.default.addObserver(
            self,
            selector: #selector(handleMemoryWarning),
            name: UIApplication.didReceiveMemoryWarningNotification,
            object: nil
        )
        
        // Eski cache dosyalarını temizle (30 günden eski)
        // cacheDirectory initialize edildikten SONRA çağır
        cleanOldCacheFiles()
    }
    
    @objc private func handleMemoryWarning() {
        #if DEBUG
        print("⚠️ ImageCache: Memory warning, cache temizleniyor")
        #endif
        // Memory cache'i yarıya indir
        cache.countLimit = cache.countLimit / 2
        cache.totalCostLimit = cache.totalCostLimit / 2
    }
    
    /// Eski cache dosyalarını temizle (30 günden eski)
    private func cleanOldCacheFiles() {
        guard let files = try? fileManager.contentsOfDirectory(at: cacheDirectory, includingPropertiesForKeys: [.contentModificationDateKey]) else {
            return
        }
        
        let thirtyDaysAgo = Date().addingTimeInterval(-30 * 24 * 60 * 60)
        var cleanedCount = 0
        
        for file in files {
            if let modificationDate = try? file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate,
               modificationDate < thirtyDaysAgo {
                try? fileManager.removeItem(at: file)
                cleanedCount += 1
            }
        }
        
        #if DEBUG
        if cleanedCount > 0 {
            print("🧹 ImageCache: \(cleanedCount) eski cache dosyası temizlendi")
        }
        #endif
    }
    
    /// Image'ı cache'den al veya yükle
    func image(for urlString: String) async -> UIImage? {
        let key = urlString as NSString
        
        // Memory cache'den kontrol et
        if let cachedImage = cache.object(forKey: key) {
            return cachedImage
        }
        
        // Disk cache'den kontrol et
        if let diskImage = loadFromDisk(key: urlString) {
            cache.setObject(diskImage, forKey: key)
            return diskImage
        }
        
        // Network'ten yükle - retry mekanizması ile
        guard let url = URL(string: urlString) else {
            #if DEBUG
            print("❌ ImageCache: Geçersiz URL: \(urlString)")
            #endif
            return nil
        }
        
        // Retry mekanizması (3 deneme)
        var lastError: Error?
        for attempt in 1...3 {
            do {
                // Timeout ile yükleme (10 saniye)
                let (data, response) = try await URLSession.shared.data(from: url)
                
                // HTTP status kontrolü
                if let httpResponse = response as? HTTPURLResponse {
                    guard (200...299).contains(httpResponse.statusCode) else {
                        #if DEBUG
                        print("❌ ImageCache: HTTP \(httpResponse.statusCode) - \(urlString)")
                        #endif
                        if attempt < 3 {
                            try await Task.sleep(nanoseconds: UInt64(pow(2.0, Double(attempt - 1)) * 1_000_000_000))
                            continue
                        }
                        return nil
                    }
                }
                
                guard let image = UIImage(data: data) else {
                    #if DEBUG
                    print("❌ ImageCache: Geçersiz image data - \(urlString)")
                    #endif
                    if attempt < 3 {
                        try await Task.sleep(nanoseconds: UInt64(pow(2.0, Double(attempt - 1)) * 1_000_000_000))
                        continue
                    }
                    return nil
                }
                
                // Memory ve disk'e kaydet
                cache.setObject(image, forKey: key)
                saveToDisk(image: image, key: urlString)
                
                #if DEBUG
                print("✅ ImageCache: Image yüklendi - \(urlString)")
                #endif
                return image
            } catch {
                lastError = error
                #if DEBUG
                print("⚠️ ImageCache: Yükleme hatası (deneme \(attempt)/3): \(error.localizedDescription)")
                #endif
                if attempt < 3 {
                    // Exponential backoff
                    try? await Task.sleep(nanoseconds: UInt64(pow(2.0, Double(attempt - 1)) * 1_000_000_000))
                }
            }
        }
        
        #if DEBUG
        if let error = lastError {
            print("❌ ImageCache: Tüm denemeler başarısız - \(error.localizedDescription)")
        }
        #endif
        return nil
    }
    
    /// Disk'ten yükle
    private func loadFromDisk(key: String) -> UIImage? {
        let fileName = key.replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: ":", with: "_")
            .addingPercentEncoding(withAllowedCharacters: .alphanumerics) ?? key
        let fileURL = cacheDirectory.appendingPathComponent(fileName)
        
        guard let data = try? Data(contentsOf: fileURL),
              let image = UIImage(data: data) else {
            return nil
        }
        
        return image
    }
    
    /// Disk'e kaydet
    private func saveToDisk(image: UIImage, key: String) {
        guard let data = image.jpegData(compressionQuality: 0.8) else { return }
        
        let fileName = key.replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: ":", with: "_")
            .addingPercentEncoding(withAllowedCharacters: .alphanumerics) ?? key
        let fileURL = cacheDirectory.appendingPathComponent(fileName)
        
        try? data.write(to: fileURL)
    }
    
    /// Cache'i temizle
    func clearCache() {
        cache.removeAllObjects()
        try? fileManager.removeItem(at: cacheDirectory)
        try? fileManager.createDirectory(at: cacheDirectory, withIntermediateDirectories: true)
    }
    
    /// Cache boyutunu kontrol et ve gerekirse temizle (memory management)
    func cleanupCacheIfNeeded() {
        // Memory cache limit kontrolü
        if cache.countLimit > 150 {
            // Limit aşıldı, yarısını temizle
            cache.countLimit = 75
            cache.totalCostLimit = 50 * 1024 * 1024 // 50 MB
            #if DEBUG
            print("🧹 ImageCache: Memory cache limiti aşıldı, temizlendi")
            #endif
        }
        
        // Disk cache temizliği (30 günden eski dosyalar)
        cleanOldCacheFiles()
    }
}

/// Cached AsyncImage view
struct CachedAsyncImage<Content: View, Placeholder: View>: View {
    let url: String?
    let content: (Image) -> Content
    let placeholder: () -> Placeholder
    
    @State private var image: UIImage?
    @State private var isLoading = true
    
    init(
        url: String?,
        @ViewBuilder content: @escaping (Image) -> Content,
        @ViewBuilder placeholder: @escaping () -> Placeholder
    ) {
        self.url = url
        self.content = content
        self.placeholder = placeholder
    }
    
    var body: some View {
        Group {
            if let image = image {
                content(Image(uiImage: image))
                    .transition(.opacity.combined(with: .scale(scale: 0.95)))
            } else if isLoading {
                placeholder()
            } else {
                placeholder()
            }
        }
        .task {
            guard let url = url, !url.isEmpty else {
                isLoading = false
                return
            }
            
            // Base URL ekle (eğer yoksa)
            let fullURL: String
            if url.hasPrefix("http://") || url.hasPrefix("https://") {
                fullURL = url
            } else {
                #if DEBUG
                #if targetEnvironment(simulator)
                let baseURL = "http://127.0.0.1/fourkampus"
                #else
                let baseURL = "http://localhost/fourkampus"
                #endif
                #else
                let baseURL = "https://foursoftware.com.tr/fourkampus"
                #endif
                let cleanPath = url.hasPrefix("/") ? url : "/\(url)"
                fullURL = "\(baseURL)\(cleanPath)"
            }
            
            isLoading = true
            image = await ImageCache.shared.image(for: fullURL)
            isLoading = false
        }
    }
}

