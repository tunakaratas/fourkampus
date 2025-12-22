//
//  AdManager.swift
//  Four Kampüs
//
//  AdMob Native Ad Manager
//

import Foundation
import SwiftUI
import Combine

// MARK: - Ad Data Model
struct AdData: Codable, Equatable {
    let id: String
    let title: String
    let description: String
    let imageURL: String?
    let logoURL: String?
    let callToAction: String
    let advertiser: String
    let rating: Double?
    let clickURL: String?
    let priority: Int?
    
    enum CodingKeys: String, CodingKey {
        case id
        case title
        case description
        case imageURL = "image_url"
        case logoURL = "logo_url"
        case callToAction = "call_to_action"
        case advertiser
        case rating
        case clickURL = "click_url"
        case priority
    }
    
    init(id: String, title: String, description: String, imageURL: String? = nil, logoURL: String? = nil, callToAction: String = "Keşfet", advertiser: String, rating: Double? = nil, clickURL: String? = nil, priority: Int? = nil) {
        self.id = id
        self.title = title
        self.description = description
        self.imageURL = imageURL
        self.logoURL = logoURL
        self.callToAction = callToAction
        self.advertiser = advertiser
        self.rating = rating
        self.clickURL = clickURL
        self.priority = priority
    }
    
    // Test reklam verileri - Sadece API'den reklam gelmezse kullanılacak (boş liste)
    static let testAds: [AdData] = []
}

// MARK: - Ad Manager
class AdManager: ObservableObject {
    static let shared = AdManager()
    
    @Published var isAdMobInitialized = false
    @Published var ads: [AdData] = []
    @Published var isLoading = false
    
    private var apiAds: [AdData] = []
    private var lastFetchTime: Date?
    private let cacheExpiration: TimeInterval = 300 // 5 dakika
    
    // API'den reklamları çek (async)
    @MainActor
    func fetchAds() async {
        guard !isLoading else {
            return
        }
        
        // Cache kontrolü
        if let lastFetch = lastFetchTime,
           Date().timeIntervalSince(lastFetch) < cacheExpiration,
           !apiAds.isEmpty {
            self.ads = apiAds
            return
        }
        
        isLoading = true
        
        let apiService = APIService.shared
        let endpoint = "ads.php"
        
        do {
            // API yanıtı {success: true, data: [AdData]} formatında dönüyor
            // Önce wrapper response'u parse et
            let response: AdsAPIResponse = try await apiService.request(
                endpoint: endpoint,
                method: "GET",
                useCache: false
            )
            
            if response.success, let adsData = response.data, !adsData.isEmpty {
                self.apiAds = adsData
                self.ads = adsData
                self.lastFetchTime = Date()
                self.isLoading = false
                #if DEBUG
                print("✅ Reklamlar API'den yüklendi: \(adsData.count) adet")
                for ad in adsData {
                    print("   📢 Reklam: \(ad.title)")
                    print("      - imageURL: \(ad.imageURL ?? "nil")")
                    print("      - logoURL: \(ad.logoURL ?? "nil")")
                }
                #endif
            } else {
                // API'den reklam gelmezse boş liste
                self.ads = []
                self.isLoading = false
                #if DEBUG
                print("⚠️ API'den reklam gelmedi veya liste boş")
                #endif
            }
        } catch {
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if isCancelled {
                self.isLoading = false
                #if DEBUG
                print("⚠️ Reklam yükleme iptal edildi, mevcut reklamlar korunuyor")
                #endif
                return
            }
            // Hata durumunda boş liste
            self.ads = []
            self.isLoading = false
            #if DEBUG
            print("❌ Reklam yükleme hatası: \(error.localizedDescription)")
            // Detaylı hata bilgisi
            if let decodingError = error as? DecodingError {
                switch decodingError {
                case .typeMismatch(let type, let context):
                    print("   Type mismatch: \(type), path: \(context.codingPath)")
                case .keyNotFound(let key, let context):
                    print("   Key not found: \(key), path: \(context.codingPath)")
                case .dataCorrupted(let context):
                    print("   Data corrupted: \(context.debugDescription)")
                default:
                    print("   Decoding error: \(decodingError)")
                }
            }
            #endif
        }
    }
    
    // API Response wrapper
    private struct AdsAPIResponse: Codable {
        let success: Bool
        let data: [AdData]?
        let count: Int?
        let message: String?
        let error: String?
    }
    
    // Rastgele reklam seç (sadece API'den gelen reklamlar)
    func getRandomAd() -> AdData? {
        // Sadece API'den gelen reklamları kullan
        guard !ads.isEmpty else {
            return nil // Reklam yoksa nil döndür
        }
        
        // Priority'ye göre sırala (yüksek priority önce)
        let sortedAds = ads.sorted { (ad1, ad2) -> Bool in
            let priority1 = ad1.priority ?? 0
            let priority2 = ad2.priority ?? 0
            if priority1 != priority2 {
                return priority1 > priority2
            }
            // Priority aynıysa rastgele seç
            return Bool.random()
        }
        
        // Öncelikli reklamları daha fazla göster (weighted random)
        // İlk %50'lik dilimden %70, geri kalanından %30 seç
        let topHalf = Array(sortedAds.prefix(max(1, sortedAds.count / 2)))
        let bottomHalf = Array(sortedAds.suffix(max(1, sortedAds.count - topHalf.count)))
        
        if Int.random(in: 1...10) <= 7 && !topHalf.isEmpty {
            return topHalf.randomElement() ?? sortedAds.randomElement()
        } else if !bottomHalf.isEmpty {
            return bottomHalf.randomElement() ?? sortedAds.randomElement()
        }
        return sortedAds.randomElement()
    }
    
    // AdMob Native Ad Unit ID (Test ID - Production'da değiştirilecek)
    // Test ID: ca-app-pub-3940256099942544/3986624511 (Native Advanced)
    let nativeAdUnitID = "ca-app-pub-3940256099942544/3986624511"
    
    // AdMob Banner Ad Unit ID (Test ID)
    let bannerAdUnitID = "ca-app-pub-3940256099942544/2934735716"
    
    private init() {
        // AdMob initialization burada yapılacak
        // TODO: GoogleMobileAds SDK entegrasyonu
        #if DEBUG
        print("📢 AdManager initialized (Test Mode)")
        #endif
    }
    
    // AdMob'u başlat (gelecekte kullanılacak)
    func initializeAdMob() {
        // TODO: GADMobileAds.sharedInstance().start(completionHandler: nil)
        isAdMobInitialized = true
    }
    
    // Reklam yükleme (sadece API'den çek)
    func loadNativeAd(completion: @escaping (AdData?) -> Void) {
        // Eğer reklamlar yüklenmemişse önce API'den çek
        if ads.isEmpty && !isLoading {
            Task { @MainActor in
                await self.fetchAds()
                DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
                    completion(self.getRandomAd())
                }
            }
        } else {
            // Reklamlar zaten yüklenmişse direkt döndür
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
                completion(self.getRandomAd())
            }
        }
    }
}
