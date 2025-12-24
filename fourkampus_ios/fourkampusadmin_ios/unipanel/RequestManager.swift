//
//  RequestManager.swift
//  Four Kampüs
//
//  Created for high-scale optimization
//

import Foundation

/// Sendable wrapper for non-Sendable types stored in actor cache
struct SendableWrapper: @unchecked Sendable {
    nonisolated(unsafe) let value: Any
    nonisolated init(_ value: Any) {
        self.value = value
    }
}

/// Request throttling ve rate limiting için manager
actor RequestManager {
    static let shared = RequestManager()
    
    // Rate limiting ayarları - Optimize edildi (hızlı yükleme için)
    private let maxRequestsPerSecond: Int = 30 // Saniyede max 30 istek (artırıldı)
    private let maxConcurrentRequests: Int = 12 // Aynı anda max 12 istek (artırıldı)
    private let requestWindow: TimeInterval = 1.0 // 1 saniyelik pencere
    
    // Request tracking
    private var requestTimestamps: [Date] = []
    private var activeRequests: Int = 0
    
    // Request deduplication - Çok az agresif (sadece gerçek duplicate'ler için)
    private var activeRequestKeys: Set<String> = []
    private var requestCache: [String: (Date, SendableWrapper)] = [:]
    private let deduplicationWindow: TimeInterval = 0.1 // 100ms içinde aynı istek tekrar gönderilmez (çok kısaltıldı)
    
    private init() {}
    
    /// Rate limit kontrolü - istek göndermeden önce çağrılmalı
    func waitForRateLimit() async {
        let now = Date()
        
        // Eski timestamp'leri temizle
        requestTimestamps = requestTimestamps.filter { now.timeIntervalSince($0) < requestWindow }
        
        // Rate limit kontrolü
        if requestTimestamps.count >= maxRequestsPerSecond {
            // En eski request'in süresini bekle
            if let oldestTimestamp = requestTimestamps.first {
                let waitTime = requestWindow - now.timeIntervalSince(oldestTimestamp)
                if waitTime > 0 {
                    try? await Task.sleep(nanoseconds: UInt64(waitTime * 1_000_000_000))
                }
            }
        }
        
        // Concurrent request limit kontrolü
        while activeRequests >= maxConcurrentRequests {
            // Bir request'in bitmesini bekle
            try? await Task.sleep(nanoseconds: 100_000_000) // 100ms
        }
        
        // Request'i kaydet
        requestTimestamps.append(Date())
        activeRequests += 1
    }
    
    /// Request tamamlandığında çağrılmalı
    func requestCompleted() {
        activeRequests = max(0, activeRequests - 1)
    }
    
    /// Request deduplication - aynı isteğin tekrar gönderilmesini önler (daha az agresif)
    func shouldDeduplicateRequest(key: String) -> Bool {
        let now = Date()
        
        // Eski cache'leri temizle
        requestCache = requestCache.filter { now.timeIntervalSince($0.value.0) < deduplicationWindow }
        
        // Aynı istek zaten aktif mi? (sadece çok kısa süre içinde)
        if activeRequestKeys.contains(key) {
            // Aktif istek var - ama çok kısa süre içindeyse duplicate say
            // Eğer 100ms'den fazla geçtiyse, yeni istek göndermeye izin ver
            return true // Duplicate, gönderme (cache'den dönecek)
        }
        
        // Cache'de var mı? (sadece çok kısa süre içinde)
        if let (timestamp, _) = requestCache[key], now.timeIntervalSince(timestamp) < deduplicationWindow {
            // Cache'de var - ama çok eskiyse yeni istek göndermeye izin ver
            return true // Duplicate, gönderme (cache'den dönecek)
        }
        
        // Yeni istek, kaydet
        activeRequestKeys.insert(key)
        return false // Duplicate değil, gönder
    }
    
    /// Request başladığında çağrılmalı
    func requestStarted(key: String) {
        activeRequestKeys.insert(key)
    }
    
    /// Request'in şu an devam edip etmediğini kontrol eder (state'i değiştirmez)
    func isRequestInProgress(key: String) -> Bool {
        return activeRequestKeys.contains(key)
    }
    
    /// Request tamamlandığında çağrılmalı (deduplication için)
    func requestFinished(key: String, result: SendableWrapper? = nil) {
        activeRequestKeys.remove(key)
        if let result = result {
            // Store SendableWrapper directly
            requestCache[key] = (Date(), result)
        }
    }
    
    /// Request key oluştur (endpoint + method + body hash)
    static func generateRequestKey(endpoint: String, method: String, body: Data? = nil) -> String {
        var key = "\(method)_\(endpoint)"
        if let body = body {
            // Body'nin hash'ini al (ilk 32 byte yeterli)
            let hash = body.prefix(32).hashValue
            key += "_\(hash)"
        }
        return key
    }
}

/// Request queue management
actor RequestQueue {
    static let shared = RequestQueue()
    
    private var queue: [() async throws -> Void] = []
    private var isProcessing = false
    private let maxQueueSize = 100
    
    private init() {}
    
    /// Request'i queue'ya ekle
    func enqueue(_ request: @escaping () async throws -> Void) async throws {
        if queue.count >= maxQueueSize {
            throw APIError.apiError("Request queue is full. Please try again later.")
        }
        queue.append(request)
        await processQueue()
    }
    
    /// Queue'yu işle
    private func processQueue() async {
        guard !isProcessing else { return }
        isProcessing = true
        
        while !queue.isEmpty {
            let request = queue.removeFirst()
            do {
                try await request()
            } catch {
                #if DEBUG
                print("⚠️ RequestQueue: Request failed: \(error.localizedDescription)")
                #endif
            }
            // Rate limiting için kısa bir bekleme
            try? await Task.sleep(nanoseconds: 100_000_000) // 100ms
        }
        
        isProcessing = false
    }
}

