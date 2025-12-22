//
//  AppConfig.swift
//  Four Kampüs
//
//  Configuration Management - Secure Base URL Handling
//

import Foundation

/// Application Configuration Manager
/// Base URL'leri Info.plist'ten okur, güvenli yönetim sağlar
class AppConfig {
    static let shared = AppConfig()
    
    private init() {}
    
    /// Base URL'i Info.plist'ten oku
    /// Info.plist'te "APIBaseURL" key'i olmalı
    /// Hosting bağlantısı kaldırıldı - sadece localhost kullanılacak
    /// Base URL her zaman sonunda / olmadan döner (URL oluşturma için)
    var baseURL: String {
        // 1) Environment override (en hızlı test / cihaz IP geçişi)
        // Xcode Scheme -> Run -> Arguments -> Environment Variables:
        // FOURKAMPUS_API_BASE_URL = http://<ip>/fourkampus/api (eski: UNIFOUR_API_BASE_URL)
        if let env = ProcessInfo.processInfo.environment["FOURKAMPUS_API_BASE_URL"] ?? ProcessInfo.processInfo.environment["UNIFOUR_API_BASE_URL"], !env.isEmpty {
            return env.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        }
        
        // 2) Info.plist
        let plistURL: String = {
            if let baseURL = Bundle.main.object(forInfoDictionaryKey: "APIBaseURL") as? String,
               !baseURL.isEmpty {
                return baseURL
            }
            // Default: Production sunucusunu kullan
            return "https://fourkampus.com.tr/api"
        }()
        return plistURL.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
    }
    
    /// Image base URL'i Info.plist'ten oku
    var imageBaseURL: String {
        if let imageURL = Bundle.main.object(forInfoDictionaryKey: "APIImageBaseURL") as? String,
           !imageURL.isEmpty {
            return imageURL
        }
        
        // Base URL'den türet (image path için)
        let base = baseURL.replacingOccurrences(of: "/api", with: "")
        return base
    }
    
    /// Environment bilgisi
    var environment: String {
        #if DEBUG
        return "development"
        #else
        return "production"
        #endif
    }
    
    /// Production modunda mı?
    var isProduction: Bool {
        #if DEBUG
        return false
        #else
        return true
        #endif
    }
}

