//
//  InputValidator.swift
//  Four Kampüs
//
//  Input Validation and Sanitization
//

import Foundation

/// Input validation and sanitization utility
struct InputValidator {
    // Password constraints
    static let MIN_PASSWORD_LENGTH = 8
    static let MAX_PASSWORD_LENGTH = 128
    
    // Email validation pattern
    private static let emailPattern = #"^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}$"#
    
    /// Validate email address
    static func isValidEmail(_ email: String) -> Bool {
        // Güvenli regex oluşturma - crash prevention
        guard let emailRegex = try? NSRegularExpression(pattern: emailPattern, options: .caseInsensitive) else {
            // Regex oluşturulamazsa basit kontrol yap
            return email.contains("@") && email.contains(".") && email.count > 5
        }
        let range = NSRange(location: 0, length: email.utf16.count)
        return emailRegex.firstMatch(in: email, options: [], range: range) != nil
    }
    
    /// Validate password
    static func isValidPassword(_ password: String) -> Bool {
        guard password.count >= MIN_PASSWORD_LENGTH,
              password.count <= MAX_PASSWORD_LENGTH else {
            return false
        }
        
        // At least one letter and one number
        let hasLetter = password.rangeOfCharacter(from: .letters) != nil
        let hasNumber = password.rangeOfCharacter(from: .decimalDigits) != nil
        
        return hasLetter && hasNumber
    }
    
    /// Sanitize string input (remove dangerous characters)
    static func sanitize(_ input: String) -> String {
        // Remove null bytes
        var sanitized = input.replacingOccurrences(of: "\0", with: "")
        
        // Trim whitespace
        sanitized = sanitized.trimmingCharacters(in: .whitespacesAndNewlines)
        
        // Limit length (prevent DoS)
        if sanitized.count > 10000 {
            sanitized = String(sanitized.prefix(10000))
        }
        
        return sanitized
    }
    
    /// Sanitize email (lowercase and trim)
    static func sanitizeEmail(_ email: String) -> String {
        return email.lowercased().trimmingCharacters(in: .whitespacesAndNewlines)
    }
    
    /// Validate phone number (Turkish format)
    static func isValidPhoneNumber(_ phone: String) -> Bool {
        // Remove spaces, dashes, and parentheses
        let cleaned = phone.replacingOccurrences(of: " ", with: "")
            .replacingOccurrences(of: "-", with: "")
            .replacingOccurrences(of: "(", with: "")
            .replacingOccurrences(of: ")", with: "")
        
        // Turkish phone number patterns
        let patterns = [
            "^05[0-9]{9}$", // 05XX XXX XX XX
            "^\\+905[0-9]{9}$", // +905XX XXX XX XX
            "^905[0-9]{9}$" // 905XX XXX XX XX
        ]
        
        for pattern in patterns {
            if let regex = try? NSRegularExpression(pattern: pattern),
               regex.firstMatch(in: cleaned, range: NSRange(location: 0, length: cleaned.utf16.count)) != nil {
                return true
            }
        }
        
        return false
    }
    
    /// Format phone number to Turkish format (10 digits, starting with 5)
    /// Returns: 10-digit phone number (e.g., "5551234567") or nil if invalid
    static func formatPhoneNumber(_ phone: String?) -> String? {
        guard let phone = phone, !phone.isEmpty else { return nil }
        
        // Remove all non-digit characters
        let digits = phone.replacingOccurrences(of: "[^0-9]", with: "", options: .regularExpression)
        
        // Handle different formats
        var cleaned = digits
        
        // Remove leading 00
        if cleaned.hasPrefix("00") {
            cleaned = String(cleaned.dropFirst(2))
        }
        
        // Remove leading +90 or 90
        if cleaned.hasPrefix("90") && cleaned.count > 10 {
            cleaned = String(cleaned.dropFirst(2))
        }
        
        // Remove leading 0 if 11 digits
        if cleaned.count == 11 && cleaned.hasPrefix("0") {
            cleaned = String(cleaned.dropFirst(1))
        }
        
        // Final validation: must be 10 digits and start with 5
        if cleaned.count == 10 && cleaned.hasPrefix("5") {
            return cleaned
        }
        
        return nil
    }
    
    /// Validate URL
    static func isValidURL(_ urlString: String) -> Bool {
        guard let url = URL(string: urlString),
              url.scheme != nil,
              url.host != nil else {
            return false
        }
        
        // Only allow http and https
        guard let scheme = url.scheme?.lowercased(),
              scheme == "http" || scheme == "https" else {
            return false
        }
        
        return true
    }
    
    /// Validate and sanitize search query
    static func sanitizeSearchQuery(_ query: String) -> String {
        var sanitized = sanitize(query)
        
        // Remove SQL injection patterns (basic)
        let dangerousPatterns = [
            "';",
            "--",
            "/*",
            "*/",
            "xp_",
            "sp_",
            "exec",
            "union",
            "select",
            "insert",
            "update",
            "delete",
            "drop",
            "create",
            "alter"
        ]
        
        for pattern in dangerousPatterns {
            sanitized = sanitized.replacingOccurrences(
                of: pattern,
                with: "",
                options: .caseInsensitive
            )
        }
        
        return sanitized
    }
}

