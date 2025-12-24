//
//  SecureLogger.swift
//  Four Kampüs
//
//  Secure Logging for Real-World Scenarios
//

import Foundation
import os.log

/// Secure logging utility - removes sensitive data
nonisolated class SecureLogger {
    private static let subsystem = "com.ffoursoftware.unifour"
    private static let category = "App"
    
    private static let logger = Logger(subsystem: subsystem, category: category)
    
    /// Log debug message (only in DEBUG builds)
    nonisolated static func d(_ tag: String, _ message: String) {
        #if DEBUG
        logger.debug("[\(tag)] \(sanitize(message))")
        #endif
    }
    
    /// Log info message
    nonisolated static func i(_ tag: String, _ message: String) {
        logger.info("[\(tag)] \(sanitize(message))")
    }
    
    /// Log warning message
    nonisolated static func w(_ tag: String, _ message: String) {
        logger.warning("[\(tag)] \(sanitize(message))")
    }
    
    /// Log error message
    nonisolated static func e(_ tag: String, _ message: String, _ error: Error? = nil) {
        var logMessage = message
        if let error = error {
            logMessage += " - Error: \(error.localizedDescription)"
        }
        logger.error("[\(tag)] \(sanitize(logMessage))")
    }
    
    /// Sanitize log message - remove sensitive data
    nonisolated static func sanitize(_ message: String) -> String {
        var sanitized = message
        
        // Remove tokens (Bearer token, auth token, etc.)
        sanitized = sanitized.replacingOccurrences(
            of: #"token["\s]*[:=]["\s]*[^"\s,}]+"#,
            with: "token=***",
            options: .regularExpression
        )
        sanitized = sanitized.replacingOccurrences(
            of: #"Bearer\s+[A-Za-z0-9]+"#,
            with: "Bearer ***",
            options: .regularExpression
        )
        sanitized = sanitized.replacingOccurrences(
            of: #"Authorization["\s]*[:=]["\s]*Bearer\s+[A-Za-z0-9]+"#,
            with: "Authorization: Bearer ***",
            options: .regularExpression
        )
        
        // Remove passwords
        sanitized = sanitized.replacingOccurrences(
            of: #"password["\s]*[:=]["\s]*[^"\s,}]+"#,
            with: "password=***",
            options: .regularExpression
        )
        
        // Remove email addresses from logs (production safety)
        sanitized = sanitized.replacingOccurrences(
            of: #"\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b"#,
            with: "***@***.***",
            options: .regularExpression
        )
        
        // Remove phone numbers
        sanitized = sanitized.replacingOccurrences(
            of: #"phone[_\s]*number["\s]*[:=]["\s]*[^"\s,}]+"#,
            with: "phone_number=***",
            options: .regularExpression
        )
        
        // Remove student IDs
        sanitized = sanitized.replacingOccurrences(
            of: #"student[_\s]*id["\s]*[:=]["\s]*[^"\s,}]+"#,
            with: "student_id=***",
            options: .regularExpression
        )
        
        // Remove full user data objects (JSON içinde)
        sanitized = sanitized.replacingOccurrences(
            of: #""user"\s*:\s*\{[^}]*\}"#,
            with: "\"user\": {***}",
            options: .regularExpression
        )
        
        return sanitized
    }
    
    /// Sanitize JSON response - remove sensitive fields
    nonisolated static func sanitizeJSON(_ jsonString: String) -> String {
        var sanitized = jsonString
        
        // Remove sensitive fields from JSON
        let sensitiveFields = ["token", "password", "password_hash", "email", "phone_number", "student_id", "user"]
        for field in sensitiveFields {
            // Match field with various formats
            sanitized = sanitized.replacingOccurrences(
                of: #""# + field + #"["\s]*:\s*"[^"]*""#,
                with: "\"\(field)\": \"***\"",
                options: .regularExpression
            )
            sanitized = sanitized.replacingOccurrences(
                of: #""# + field + #"["\s]*:\s*[^,}\]]+"#,
                with: "\"\(field)\": \"***\"",
                options: .regularExpression
            )
        }
        
        return sanitize(sanitized)
    }
}

