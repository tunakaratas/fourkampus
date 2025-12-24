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
            // System Background
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            VStack(spacing: 0) {
                ScrollView {
                    VStack(spacing: 32) {
                        // Header Section
                        VStack(spacing: 24) {
                            // Icon with modern style
                            ZStack {
                                Circle()
                                    .fill(Color(hex: "6366f1").opacity(0.1))
                                    .frame(width: 100, height: 100)
                                
                                Image(systemName: "envelope.badge.fill")
                                    .font(.system(size: 48))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                            .padding(.top, 40)
                            
                            VStack(spacing: 12) {
                                Text("Doğrulama Kodu")
                                    .font(.system(size: 28, weight: .bold, design: .rounded))
                                    .foregroundColor(.primary)
                                
                                Text("Lütfen e-posta adresinize gönderilen\n6 haneli kodu girin.")
                                    .font(.system(size: 16, weight: .regular))
                                    .foregroundColor(.secondary)
                                    .multilineTextAlignment(.center)
                                    .lineSpacing(4)
                                
                                // Email display card
                                HStack(spacing: 10) {
                                    Image(systemName: "envelope.fill")
                                        .font(.system(size: 14))
                                        .foregroundColor(.secondary)
                                    Text(email)
                                        .font(.system(size: 15, weight: .semibold))
                                        .foregroundColor(.primary)
                                }
                                .padding(.horizontal, 16)
                                .padding(.vertical, 12)
                                .background(
                                    RoundedRectangle(cornerRadius: 12)
                                        .fill(Color(UIColor.secondarySystemBackground))
                                )
                                .padding(.top, 8)
                            }
                        }
                        .padding(.horizontal, 24)
                    
                        // Code Input Section
                        VStack(spacing: 24) {
                            // Code Input with modern design
                            HStack(spacing: 10) {
                                ForEach(0..<6, id: \.self) { index in
                                    ZStack {
                                        RoundedRectangle(cornerRadius: 14)
                                            .fill(Color(UIColor.secondarySystemBackground))
                                            .frame(width: 48, height: 64)
                                            .overlay(
                                                RoundedRectangle(cornerRadius: 14)
                                                    .stroke(
                                                        isCodeFocused && code.count == index ? Color(hex: "6366f1") : Color.clear,
                                                        lineWidth: 2
                                                    )
                                            )
                                        
                                        if index < code.count {
                                            let charIndex = code.index(code.startIndex, offsetBy: index)
                                            Text(String(code[charIndex]))
                                                .font(.system(size: 28, weight: .bold, design: .monospaced))
                                                .foregroundColor(.primary)
                                                .transition(.scale.combined(with: .opacity))
                                        }
                                    }
                                }
                            }
                            
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
                                    .scaleEffect(1.2)
                                    .padding(.top, 8)
                            }
                        }
                        .padding(.horizontal, 24)
                    
                        // Status Messages
                        if let error = errorMessage, !error.isEmpty {
                            HStack(spacing: 12) {
                                Image(systemName: "exclamationmark.triangle.fill")
                                    .font(.system(size: 16))
                                Text(error)
                                    .font(.system(size: 14, weight: .medium))
                            }
                            .foregroundColor(.red)
                            .padding()
                            .frame(maxWidth: .infinity)
                            .background(Color.red.opacity(0.1))
                            .cornerRadius(12)
                            .padding(.horizontal, 24)
                        }
                        
                        if let success = successMessage, !success.isEmpty {
                            HStack(spacing: 12) {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 16))
                                Text(success)
                                    .font(.system(size: 14, weight: .medium))
                            }
                            .foregroundColor(.green)
                            .padding()
                            .frame(maxWidth: .infinity)
                            .background(Color.green.opacity(0.1))
                            .cornerRadius(12)
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
                                HStack(spacing: 8) {
                                    if resendCooldown > 0 {
                                        Text("Tekrar göndermek için bekleyin")
                                            .foregroundColor(.secondary)
                                        Text("\(resendCooldown)s")
                                            .font(.system(size: 15, weight: .bold, design: .monospaced))
                                            .foregroundColor(.primary)
                                    } else {
                                        Image(systemName: "arrow.clockwise")
                                        Text("Kodu Tekrar Gönder")
                                    }
                                }
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(resendCooldown > 0 ? .secondary : Color(hex: "6366f1"))
                                .padding(.vertical, 12)
                                .padding(.horizontal, 20)
                                .background(
                                    Capsule()
                                        .fill(Color(hex: "6366f1").opacity(0.1))
                                )
                            }
                            .disabled(resendCooldown > 0 || isLoading)
                            
                            Spacer().frame(height: 20)
                            
                            // Cancel Button
                            Button(action: {
                                onCancel()
                            }) {
                                Text("Vazgeç")
                                    .font(.system(size: 16, weight: .medium))
                                    .foregroundColor(.secondary)
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
            // İlk açılışta cooldown başlatma, çünkü kod zaten gönderildi
            if resendCooldown == 0 {
                startResendCooldown()
            }
        }
    }
    
    private func verifyCode() async {
        guard code.count == 6 else { return }
        
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        // Klavye kapat
        isCodeFocused = false
        
        do {
            let verified = try await APIService.shared.verifyEmailCode(email: email, code: code)
            if verified {
                successMessage = "Doğrulama başarılı!"
                // Haptic feedback
                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.success)
                
                try? await Task.sleep(nanoseconds: 500_000_000) // 0.5 saniye bekle
                onVerified(code)
            } else {
                errorMessage = "Girdiğiniz kod hatalı, lütfen tekrar deneyin."
                code = ""
                isCodeFocused = true // Tekrar odaklan
                
                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.error)
            }
        } catch {
            errorMessage = "Bir hata oluştu: \(error.localizedDescription)"
            code = ""
            isCodeFocused = true
        }
        
        isLoading = false
    }
    
    private func sendCode() async {
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        do {
            try await APIService.shared.sendVerificationCode(email: email)
            successMessage = "Yeni kod gönderildi!"
            code = ""
            startResendCooldown()
            isCodeFocused = true
            
            let generator = UINotificationFeedbackGenerator()
            generator.notificationOccurred(.success)
        } catch {
            errorMessage = "Kod gönderilemedi: \(error.localizedDescription)"
        }
        
        isLoading = false
    }
    
    private func startResendCooldown() {
        resendCooldown = 60
        Task {
            while resendCooldown > 0 {
                try? await Task.sleep(nanoseconds: 1_000_000_000)
                resendCooldown -= 1
            }
        }
    }
}

