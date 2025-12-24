//
//  CryptoHelper.swift
//  Four Kampüs
//
//  Cryptographic Helper Functions - Secure Hashing
//

import Foundation
import CryptoKit

/// Cryptographic helper functions for secure operations
class CryptoHelper {
    /// SHA-256 hash hesapla (güvenli request signature için)
    static func sha256Hash(_ data: Data) -> String {
        let hash = SHA256.hash(data: data)
        return hash.compactMap { String(format: "%02x", $0) }.joined()
    }
    
    /// SHA-256 hash hesapla (String için)
    static func sha256Hash(_ string: String) -> String {
        guard let data = string.data(using: .utf8) else {
            return ""
        }
        return sha256Hash(data)
    }
    
    /// HMAC-SHA256 hesapla (secret key ile)
    static func hmacSHA256(data: Data, key: Data) -> String {
        let hmac = HMAC<SHA256>.authenticationCode(for: data, using: SymmetricKey(data: key))
        return Data(hmac).base64EncodedString()
    }
    
    /// HMAC-SHA256 hesapla (String için)
    static func hmacSHA256(data: String, key: String) -> String {
        guard let dataBytes = data.data(using: .utf8),
              let keyBytes = key.data(using: .utf8) else {
            return ""
        }
        return hmacSHA256(data: dataBytes, key: keyBytes)
    }
}

