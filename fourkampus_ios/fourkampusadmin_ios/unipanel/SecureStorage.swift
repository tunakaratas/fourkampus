//
//  SecureStorage.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import Security

/// Güvenli token saklama için Keychain kullanımı
class SecureStorage {
    static let shared = SecureStorage()
    
    private let service = "com.tunakaratas.unifour"
    private let tokenKey = "auth_token"
    private let tokenExpirationKey = "auth_token_expiration"
    private let tokenRefreshKey = "auth_token_refresh"
    
    // Token expiration süresi (30 gün)
    private let tokenExpirationTime: TimeInterval = 30 * 24 * 60 * 60
    
    private init() {}
    
    /// Token'ı Keychain'e kaydet (süresiz - sadece manuel logout ile silinir)
    func saveToken(_ token: String, expirationDate: Date? = nil) {
        guard let data = token.data(using: .utf8) else {
            #if DEBUG
            SecureLogger.e("SecureStorage", "Token data'ya çevrilemedi", nil)
            #endif
            return
        }
        
        // Expiration date kaydetme - token süresiz
        // Eski expiration date'leri temizle
        UserDefaults.standard.removeObject(forKey: tokenExpirationKey)
        
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: tokenKey,
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly // Güvenlik: sadece bu cihazda, kilitliyken erişilemez
        ]
        
        // Önce mevcut item'ı sil
        SecItemDelete(query as CFDictionary)
        
        // Yeni item'ı ekle
        let status = SecItemAdd(query as CFDictionary, nil)
        
        if status == errSecSuccess {
            #if DEBUG
            SecureLogger.d("SecureStorage", "Token güvenli şekilde kaydedildi (süresiz)")
            #endif
        } else {
            // Keychain başarısız - hata fırlat (UserDefaults fallback kaldırıldı - güvenlik)
            #if DEBUG
            SecureLogger.e("SecureStorage", "Token kaydedilemedi, Keychain hatası: \(status)", nil)
            #endif
            // Keychain başarısız olursa token kaydedilemez - güvenlik için
            // Kullanıcıya hata gösterilmeli
        }
    }
    
    /// Token'ı Keychain'den oku (süresiz - expiration kontrolü yok)
    func getToken() -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: tokenKey,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne
        ]
        
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        
        if status == errSecSuccess,
           let data = result as? Data,
           let token = String(data: data, encoding: .utf8) {
            // Expiration kontrolü yok - token süresiz
            // Eski expiration date'leri temizle (migration için)
            UserDefaults.standard.removeObject(forKey: tokenExpirationKey)
            return token
        } else {
            // Keychain'den okunamadı - UserDefaults fallback kaldırıldı (güvenlik)
            // Migration için geçici kontrol (sadece bir kez)
            if let token = UserDefaults.standard.string(forKey: tokenKey) {
                // Expiration kontrolü yok - token süresiz
                // UserDefaults'tan Keychain'e migrate et (sadece bir kez)
                // Migration sonrası UserDefaults'tan sil
                saveToken(token)
                UserDefaults.standard.removeObject(forKey: tokenKey)
                UserDefaults.standard.removeObject(forKey: tokenExpirationKey)
                return token
            }
            return nil
        }
    }
    
    /// Token'ın geçerli olup olmadığını kontrol et (süresiz - sadece varlık kontrolü)
    func isTokenValid() -> Bool {
        guard let token = getToken() else {
            return false
        }
        // Token varsa geçerli (expiration kontrolü yok - süresiz)
        return !token.isEmpty
    }
    
    /// Token'ı otomatik yenile - DEVRE DIŞI (token süresiz)
    func refreshTokenIfNeeded() async -> Bool {
        // Token süresiz olduğu için yenileme gerekmez
        // Sadece token varlığını kontrol et
        return isTokenValid()
    }
    
    /// Token'ı Keychain'den sil
    func deleteToken() {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: tokenKey
        ]
        
        let status = SecItemDelete(query as CFDictionary)
        
        if status == errSecSuccess {
            #if DEBUG
            SecureLogger.d("SecureStorage", "Token silindi")
            #endif
        } else {
            #if DEBUG
            SecureLogger.w("SecureStorage", "Token silinemedi, status: \(status)")
            #endif
        }
        
        // UserDefaults'tan da sil (fallback ve expiration)
        UserDefaults.standard.removeObject(forKey: tokenKey)
        UserDefaults.standard.removeObject(forKey: tokenExpirationKey)
        UserDefaults.standard.removeObject(forKey: tokenRefreshKey)
    }
}

