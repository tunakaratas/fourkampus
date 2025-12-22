//
//  ErrorHandler.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation

/// Kullanıcı dostu hata mesajları için helper
struct ErrorHandler {
    /// Check if error is retryable
    static func isRetryable(_ error: Error) -> Bool {
        if let urlError = error as? URLError {
            let retryableCodes: [URLError.Code] = [
                .timedOut,
                .networkConnectionLost,
                .cannotConnectToHost,
                .cannotFindHost,
                .dnsLookupFailed,
                .notConnectedToInternet,
                .dataNotAllowed
            ]
            return retryableCodes.contains(urlError.code)
        }
        
        if let apiError = error as? APIError {
            switch apiError {
            case .httpError(let code):
                return code == 429 || code >= 500 // Rate limit or server error
            case .networkError:
                return true
            default:
                return false
            }
        }
        
        return false
    }
    
    /// Calculate backoff delay for retry
    static func calculateBackoffDelay(attempt: Int, baseDelay: TimeInterval = 1.0) -> TimeInterval {
        return baseDelay * pow(2.0, Double(attempt))
    }
    /// API hatasını kullanıcı dostu mesaja çevir
    static func userFriendlyMessage(from error: Error) -> String {
        if let apiError = error as? APIError {
            switch apiError {
            case .unauthorized:
                return "Yetkilendirme hatası. Lütfen tekrar deneyin."
            case .networkError(let underlyingError):
                if let urlError = underlyingError as? URLError {
                    switch urlError.code {
                    case .notConnectedToInternet, .networkConnectionLost:
                        return "İnternet bağlantınızı kontrol edin."
                    case .timedOut:
                        return "Bağlantı zaman aşımına uğradı. Lütfen tekrar deneyin."
                    case .cannotFindHost, .cannotConnectToHost:
                        return "Sunucuya bağlanılamıyor. Lütfen daha sonra tekrar deneyin."
                    default:
                        return "Ağ hatası oluştu. Lütfen tekrar deneyin."
                    }
                }
                return "Bağlantı hatası. Lütfen tekrar deneyin."
            case .apiError(let message):
                // API'den gelen mesajı kullanıcı dostu hale getir
                let sanitized = sanitizeErrorMessage(message)
                
                // Email zaten kayıtlı durumu için özel mesaj
                if sanitized.lowercased().contains("zaten kayıtlı") || 
                   sanitized.lowercased().contains("already") ||
                   sanitized.lowercased().contains("exists") ||
                   sanitized.lowercased().contains("kayıtlı") {
                    return "Bu e-posta adresi zaten kayıtlı. Giriş yapmayı deneyin veya farklı bir e-posta adresi kullanın."
                }
                
                return sanitized
            case .invalidResponse:
                return "Sunucudan geçersiz yanıt alındı. Lütfen tekrar deneyin."
            case .invalidURL:
                return "Geçersiz bağlantı adresi."
            case .notFound:
                return "İstenen içerik bulunamadı."
            case .httpError(let code):
                if code == 429 {
                    return "Çok fazla istek gönderildi. Lütfen bir süre bekleyip tekrar deneyin."
                } else if code >= 500 {
                    return "Sunucu hatası oluştu. Lütfen bilgilerinizi kontrol edip tekrar deneyin. Sorun devam ederse daha sonra tekrar deneyin."
                }
                return "Bir hata oluştu. Lütfen tekrar deneyin."
            case .decodingError(let error):
                // Decoding hatalarını kullanıcı dostu mesaja çevir
                if let decodingError = error as? DecodingError {
                    switch decodingError {
                    case .dataCorrupted, .keyNotFound, .typeMismatch, .valueNotFound:
                        return "Veri formatı hatası. Lütfen daha sonra tekrar deneyin."
                    @unknown default:
                        return "Veri işleme hatası. Lütfen tekrar deneyin."
                    }
                }
                return "Veri işleme hatası. Lütfen tekrar deneyin."
            }
        }
        
        // Genel hata mesajı
        let errorDescription = error.localizedDescription
        if errorDescription.contains("timeout") || errorDescription.contains("zaman aşımı") {
            return "Bağlantı zaman aşımına uğradı. Lütfen tekrar deneyin."
        }
        if errorDescription.contains("network") || errorDescription.contains("ağ") {
            return "Ağ hatası. İnternet bağlantınızı kontrol edin."
        }
        
        return "Bir hata oluştu. Lütfen tekrar deneyin."
    }
    
    /// Hata mesajını temizle (hassas bilgileri kaldır)
    private static func sanitizeErrorMessage(_ message: String) -> String {
        let sanitized = message
        
        // SQL hatalarını gizle
        if sanitized.lowercased().contains("sql") || sanitized.lowercased().contains("database") {
            return "Veritabanı hatası. Lütfen daha sonra tekrar deneyin."
        }
        
        // Stack trace'leri kaldır
        if sanitized.contains("Stack trace") || sanitized.contains("at ") {
            let lines = sanitized.components(separatedBy: .newlines)
            if let firstLine = lines.first {
                return firstLine
            }
        }
        
        // Çok uzun mesajları kısalt
        if sanitized.count > 200 {
            return String(sanitized.prefix(200)) + "..."
        }
        
        return sanitized
    }
}

