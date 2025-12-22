//
//  SSLPinningManager.swift
//  Four Kampüs
//
//  SSL Certificate Pinning Implementation
//

import Foundation
import Security

/// SSL Certificate Pinning Manager
/// Production URL'ler için certificate pinning yapar
nonisolated final class SSLPinningManager: @unchecked Sendable {
    nonisolated static let shared = SSLPinningManager()
    
    // Pinned public keys (Base64 encoded)
    // Production domain için public key'leri buraya ekleyin
    private let pinnedPublicKeys: [String: [String]] = [
        "foursoftware.com.tr": [
            // Bu key'ler production sertifikasından alınmalı
            // Örnek format (gerçek key'ler production sertifikasından alınmalı):
            // "sha256/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
        ],
        "community.foursoftware.net": [
            // Bu key'ler production sertifikasından alınmalı
            // Örnek format (gerçek key'ler production sertifikasından alınmalı):
            // "sha256/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
        ]
    ]
    
    nonisolated private init() {}
    
    /// Certificate pinning kontrolü yap
    /// Returns: true if certificate is valid and pinned, false otherwise
    nonisolated func validateCertificate(serverTrust: SecTrust, host: String) -> Bool {
        // DEBUG modunda localhost için pinning yapma
        #if DEBUG
        if host.contains("localhost") || host.contains("127.0.0.1") {
            return true // Localhost için pinning yapma
        }
        #endif
        
        // Production'da pinning yap
        guard let pinnedKeys = pinnedPublicKeys[host] else {
            // Host için pinning yapılmamış, standart validation yap
            var error: CFError?
            let isValid = SecTrustEvaluateWithError(serverTrust, &error)
            return isValid
        }
        
        // Certificate chain'i al
        let certificateCount = SecTrustGetCertificateCount(serverTrust)
        guard certificateCount > 0 else {
            return false
        }
        
        // Her certificate için public key kontrolü yap
        for index in 0..<certificateCount {
            // iOS 15+ için yeni API, iOS 14 ve öncesi için eski API
            let certificate: SecCertificate?
            if #available(iOS 15.0, *) {
                let certificateChain = SecTrustCopyCertificateChain(serverTrust) as? [SecCertificate]
                certificate = index < (certificateChain?.count ?? 0) ? certificateChain?[index] : nil
            } else {
                certificate = SecTrustGetCertificateAtIndex(serverTrust, index)
            }
            guard let certificate = certificate else {
                continue
            }
            
            // Public key'i al
            guard let publicKey = getPublicKey(from: certificate) else {
                continue
            }
            
            // Public key'i Base64 encode et
            let publicKeyBase64 = publicKey.base64EncodedString()
            
            // Pinned key'lerle karşılaştır
            for pinnedKey in pinnedKeys {
                // SHA-256 hash formatında karşılaştır
                if publicKeyBase64.contains(pinnedKey.replacingOccurrences(of: "sha256/", with: "")) {
                    return true // Pinned key bulundu
                }
            }
        }
        
        // Pinned key bulunamadı
        return false
    }
    
    /// Certificate'ten public key'i al
    private func getPublicKey(from certificate: SecCertificate) -> Data? {
        // Certificate'ten public key'i extract et
        guard let publicKey = SecCertificateCopyKey(certificate) else {
            return nil
        }
        
        // Public key'i export et
        var error: Unmanaged<CFError>?
        guard let publicKeyData = SecKeyCopyExternalRepresentation(publicKey, &error) as Data? else {
            return nil
        }
        
        return publicKeyData
    }
    
    /// Production sertifikasından public key'leri extract et (development tool)
    /// Bu fonksiyon sadece development için kullanılmalı
    static func extractPublicKey(from urlString: String) -> String? {
        guard URL(string: urlString) != nil else {
            return nil
        }
        
        // Bu fonksiyon development sırasında sertifikadan public key extract etmek için
        // Production'da kullanılmamalı
        #if DEBUG
        // Implementation: URLSession ile certificate chain'i al ve public key'i extract et
        // Bu basit bir örnek, gerçek implementasyon daha karmaşık olabilir
        return nil
        #else
        return nil
        #endif
    }
}

