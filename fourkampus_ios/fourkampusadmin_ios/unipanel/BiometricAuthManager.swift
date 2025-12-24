//
//  BiometricAuthManager.swift
//  Four Kampüs
//
//  Biometric Authentication
//

import Foundation
import LocalAuthentication

class BiometricAuthManager {
    static let shared = BiometricAuthManager()
    
    private init() {}
    
    func isBiometricAvailable() -> Bool {
        let context = LAContext()
        var error: NSError?
        return context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error)
    }
    
    func authenticate(
        reason: String = "Kimlik doğrulama için biyometrik giriş gereklidir",
        completion: @escaping @Sendable (Bool, String?) -> Void
    ) {
        let context = LAContext()
        var error: NSError?
        
        guard context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error) else {
            completion(false, "Biyometrik kimlik doğrulama kullanılamıyor")
            return
        }
        
        context.evaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, localizedReason: reason) { success, error in
            DispatchQueue.main.async {
                if success {
                    completion(true, nil)
                } else {
                    completion(false, error?.localizedDescription ?? "Kimlik doğrulama başarısız")
                }
            }
        }
    }
}

