//
//  URLValidator.swift
//  Four Kampüs
//
//  URL Validation for QR Codes and Deep Links
//

import Foundation

/// URL Validation Helper - QR Code ve Deep Link güvenliği için
class URLValidator {
    // Whitelist domain'ler (güvenli domain'ler)
    private static let allowedDomains: [String] = [
        "foursoftware.com.tr",
        "community.foursoftware.net",
        "foursoftware.net",
        "fourkampus.com.tr",
        "localhost", // DEBUG için
        "127.0.0.1"  // DEBUG için
    ]
    
    // Allowed URL schemes
    private static let allowedSchemes: [String] = [
        "https",
        "http",  // Sadece DEBUG için
        "unifour", // Deep link scheme
        "fourkampus"  // Deep link scheme
    ]
    
    /// URL'i validate et
    /// Returns: true if URL is valid and safe, false otherwise
    static func isValidURL(_ urlString: String) -> Bool {
        guard let url = URL(string: urlString) else {
            return false
        }
        
        // Scheme kontrolü
        guard let scheme = url.scheme?.lowercased(),
              allowedSchemes.contains(scheme) else {
            return false
        }
        
        // Deep link scheme'leri için host kontrolü yapma - direkt geçerli say
        if scheme == "unifour" || scheme == "fourkampus" {
            #if DEBUG
            print("✅ URLValidator: Deep link scheme tespit edildi: \(scheme)")
            #endif
            return true
        }
        
        // DEBUG modunda localhost'a izin ver
        #if DEBUG
        if url.host?.contains("localhost") == true || url.host?.contains("127.0.0.1") == true {
            return true
        }
        #else
        // Production'da http'ye izin verme
        if scheme == "http" {
            return false
        }
        #endif
        
        // Host kontrolü
        guard let host = url.host?.lowercased() else {
            return false
        }
        
        // Domain whitelist kontrolü
        let isAllowed = allowedDomains.contains { domain in
            host == domain || host.hasSuffix("." + domain)
        }
        
        return isAllowed
    }
    
    /// URL'i sanitize et ve validate et
    /// Returns: Validated URL or nil
    static func sanitizeAndValidate(_ urlString: String) -> URL? {
        // Önce temizle
        let cleaned = urlString.trimmingCharacters(in: .whitespacesAndNewlines)
        
        // Boş kontrolü
        guard !cleaned.isEmpty else {
            return nil
        }
        
        // JavaScript injection kontrolü
        let dangerousPatterns = [
            "javascript:",
            "data:",
            "vbscript:",
            "file:",
            "about:"
        ]
        
        let lowercased = cleaned.lowercased()
        for pattern in dangerousPatterns {
            if lowercased.hasPrefix(pattern) {
                return nil
            }
        }
        
        // URL oluştur
        guard let url = URL(string: cleaned) else {
            return nil
        }
        
        // Validate et
        guard isValidURL(cleaned) else {
            return nil
        }
        
        return url
    }
    
    /// Deep link URL'i validate et
    static func isValidDeepLink(_ urlString: String) -> Bool {
        guard let url = URL(string: urlString),
              let scheme = url.scheme?.lowercased() else {
            return false
        }
        
        // Deep link scheme'leri kontrol et
        return scheme == "unifour" || scheme == "fourkampus"
    }
}

