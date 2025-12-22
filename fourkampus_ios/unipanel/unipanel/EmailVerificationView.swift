//
//  EmailVerificationView.swift
//  Four Kampüs
//
//  E-posta doğrulama kodu girme ekranı
//

import SwiftUI

struct EmailVerificationView: View {
    let email: String
    let onVerified: (String) -> Void
    let onCancel: () -> Void
    
    @State private var code: String = ""
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var successMessage: String?
    @State private var resendCooldown = 0
    
    @FocusState private var isCodeFocused: Bool
    
    var body: some View {
        ZStack {
            // White Background
            Color.white
                .ignoresSafeArea()
            
            VStack(spacing: 0) {
                ScrollView {
                    VStack(spacing: 32) {
                        // Header Section
                        VStack(spacing: 20) {
                            // Icon with animation
                            ZStack {
                                Circle()
                                    .fill(Color.gray.opacity(0.1))
                                    .frame(width: 120, height: 120)
                                
                                Image(systemName: "envelope.badge.fill")
                                    .font(.system(size: 60))
                                    .foregroundColor(.gray)
                            }
                            .padding(.top, 40)
                            
                            VStack(spacing: 12) {
                                Text("E-posta Doğrulama")
                                    .font(.system(size: 32, weight: .bold, design: .rounded))
                                    .foregroundColor(.black)
                                
                                Text("Doğrulama kodunuz e-posta adresinize gönderildi")
                                    .font(.system(size: 16, weight: .medium))
                                    .foregroundColor(.gray)
                                    .multilineTextAlignment(.center)
                                
                                // Email display
                                HStack(spacing: 8) {
                                    Image(systemName: "envelope.fill")
                                        .font(.system(size: 14))
                                    Text(email)
                                        .font(.system(size: 15, weight: .semibold))
                                }
                                .foregroundColor(.black)
                                .padding(.horizontal, 16)
                                .padding(.vertical, 10)
                                .background(
                                    RoundedRectangle(cornerRadius: 12)
                                        .fill(Color.gray.opacity(0.1))
                                )
                            }
                        }
                        .padding(.horizontal, 24)
                    
                        // Code Input Section
                        VStack(spacing: 20) {
                            Text("6 Haneli Doğrulama Kodunu Girin")
                                .font(.system(size: 18, weight: .semibold))
                                .foregroundColor(.black)
                            
                            // Code Input with better design
                            HStack(spacing: 12) {
                                ForEach(0..<6, id: \.self) { index in
                                    ZStack {
                                        RoundedRectangle(cornerRadius: 16)
                                            .fill(Color.gray.opacity(0.1))
                                            .frame(width: 50, height: 70)
                                            .overlay(
                                                RoundedRectangle(cornerRadius: 16)
                                                    .stroke(
                                                        isCodeFocused && code.count == index ? Color.gray : Color.gray.opacity(0.3),
                                                        lineWidth: isCodeFocused && code.count == index ? 3 : 2
                                                    )
                                            )
                                        
                                        if index < code.count {
                                            let charIndex = code.index(code.startIndex, offsetBy: index)
                                            Text(String(code[charIndex]))
                                                .font(.system(size: 32, weight: .bold, design: .rounded))
                                                .foregroundColor(.black)
                                        } else {
                                            Text("")
                                                .font(.system(size: 32, weight: .bold))
                                        }
                                    }
                                }
                            }
                            .padding(.vertical, 8)
                            
                            // Hidden TextField for input
                            TextField("", text: $code)
                                .keyboardType(.numberPad)
                                .textContentType(.oneTimeCode)
                                .opacity(0)
                                .frame(width: 0, height: 0)
                                .focused($isCodeFocused)
                                .onChange(of: code) { newValue in
                                    // Sadece rakamları kabul et, maksimum 6 karakter
                                    let filtered = newValue.filter { $0.isNumber }
                                    if filtered.count <= 6 {
                                        code = filtered
                                    } else {
                                        code = String(filtered.prefix(6))
                                    }
                                    
                                    // Otomatik doğrulama (6 haneli kod girildiğinde)
                                    if code.count == 6 {
                                        Task {
                                            await verifyCode()
                                        }
                                    }
                                }
                            
                            if isLoading {
                                ProgressView()
                                    .progressViewStyle(CircularProgressViewStyle(tint: .gray))
                                    .scaleEffect(1.2)
                                    .padding(.top, 8)
                            }
                        }
                        .padding(.horizontal, 24)
                    
                        // Error Message
                        if let error = errorMessage, !error.isEmpty {
                            HStack(spacing: 12) {
                                Image(systemName: "exclamationmark.triangle.fill")
                                    .font(.system(size: 18))
                                Text(error)
                                    .font(.system(size: 15, weight: .medium))
                            }
                            .foregroundColor(.red)
                            .frame(maxWidth: .infinity)
                            .padding(16)
                            .background(
                                RoundedRectangle(cornerRadius: 16)
                                    .fill(Color.red.opacity(0.1))
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 16)
                                            .stroke(Color.red.opacity(0.3), lineWidth: 2)
                                    )
                            )
                            .padding(.horizontal, 24)
                        }
                        
                        // Success Message
                        if let success = successMessage, !success.isEmpty {
                            HStack(spacing: 12) {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 18))
                                Text(success)
                                    .font(.system(size: 15, weight: .medium))
                            }
                            .foregroundColor(.green)
                            .frame(maxWidth: .infinity)
                            .padding(16)
                            .background(
                                RoundedRectangle(cornerRadius: 16)
                                    .fill(Color.green.opacity(0.1))
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 16)
                                            .stroke(Color.green.opacity(0.3), lineWidth: 2)
                                    )
                            )
                            .padding(.horizontal, 24)
                        }
                    
                        // Action Buttons
                        VStack(spacing: 16) {
                            // Resend Code Button
                            Button(action: {
                                Task {
                                    await sendCode()
                                }
                            }) {
                                HStack(spacing: 12) {
                                    Image(systemName: "arrow.clockwise")
                                        .font(.system(size: 18))
                                    if resendCooldown > 0 {
                                        Text("Tekrar Gönder (\(resendCooldown)s)")
                                            .font(.system(size: 16, weight: .semibold))
                                    } else {
                                        Text("Kodu Tekrar Gönder")
                                            .font(.system(size: 16, weight: .semibold))
                                    }
                                }
                                .foregroundColor(.black)
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 16)
                                .background(
                                    RoundedRectangle(cornerRadius: 16)
                                        .fill(Color.gray.opacity(resendCooldown > 0 ? 0.1 : 0.2))
                                )
                            }
                            .disabled(resendCooldown > 0 || isLoading)
                            
                            // Cancel Button
                            Button(action: {
                                onCancel()
                            }) {
                                Text("İptal")
                                    .font(.system(size: 16, weight: .medium))
                                    .foregroundColor(.gray)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 14)
                            }
                        }
                        .padding(.horizontal, 24)
                        .padding(.bottom, 40)
                    }
                }
            }
        }
        .onAppear {
            isCodeFocused = true
            startResendCooldown()
        }
    }
    
    private func verifyCode() async {
        guard code.count == 6 else {
            errorMessage = "Lütfen 6 haneli kodu girin"
            return
        }
        
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        do {
            let verified = try await APIService.shared.verifyEmailCode(email: email, code: code)
            if verified {
                successMessage = "E-posta doğrulandı!"
                // Haptic feedback
                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.success)
                
                #if DEBUG
                print("✅ EmailVerificationView - Kod doğrulandı: \(code), callback çağrılıyor...")
                #endif
                
                // Kısa bir gecikme sonrası callback çağır
                try? await Task.sleep(nanoseconds: 500_000_000) // 0.5 saniye
                onVerified(code)
                
                #if DEBUG
                print("✅ EmailVerificationView - Callback çağrıldı")
                #endif
            } else {
                errorMessage = "Geçersiz doğrulama kodu"
                code = ""
            }
        } catch {
            if let apiError = error as? APIError {
                errorMessage = apiError.errorDescription ?? "Doğrulama kodu kontrol edilemedi"
            } else {
                errorMessage = "Bir hata oluştu: \(error.localizedDescription)"
            }
            code = ""
        }
        
        isLoading = false
    }
    
    private func sendCode() async {
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        do {
            try await APIService.shared.sendVerificationCode(email: email)
            successMessage = "Doğrulama kodu gönderildi!"
            code = ""
            startResendCooldown()
            
            // Haptic feedback
            let generator = UINotificationFeedbackGenerator()
            generator.notificationOccurred(.success)
        } catch {
            if let apiError = error as? APIError {
                errorMessage = apiError.errorDescription ?? "Kod gönderilemedi"
            } else {
                errorMessage = "Bir hata oluştu: \(error.localizedDescription)"
            }
        }
        
        isLoading = false
    }
    
    private func startResendCooldown() {
        resendCooldown = 60 // 60 saniye
        Task {
            while resendCooldown > 0 {
                try? await Task.sleep(nanoseconds: 1_000_000_000) // 1 saniye
                resendCooldown -= 1
            }
        }
    }
}

