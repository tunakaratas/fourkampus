//
//  RegisterForm.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct RegisterForm: View {
    @ObservedObject var authViewModel: AuthViewModel
    
    @State private var firstName = ""
    @State private var lastName = ""
    @State private var email = ""
    @State private var password = ""
    @State private var confirmPassword = ""
    @State private var university = ""
    @State private var department = ""
    @State private var showEmailVerification = false
    @State private var verificationCode = ""
    @State private var isSendingCode = false
    @State private var isEmailVerified = false
    
    @FocusState private var focusedField: Field?
    
    enum Field {
        case firstName, lastName, email, password, confirmPassword, university, department
    }
    
    var body: some View {
        VStack(spacing: 0) {
            // Glassmorphism Card
            ScrollView {
                VStack(spacing: 20) {
                    headerSection
                    nameFieldsSection
                    emailFieldSection
                    passwordFieldsSection
                    universityFieldsSection
                    errorMessageSection
                    buttonsSection
                }
                .padding(28)
            }
            .background(
                RoundedRectangle(cornerRadius: 24)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: 24)
                            .stroke(
                                LinearGradient(
                                    colors: [Color.white.opacity(0.3), Color.white.opacity(0.1)],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ),
                                lineWidth: 1.5
                            )
                    )
            )
            .shadow(color: Color.black.opacity(0.2), radius: 20, x: 0, y: 10)
            .frame(maxHeight: 600)
        }
        .sheet(isPresented: $showEmailVerification) {
            EmailVerificationView(
                email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                onVerified: { code in
                    #if DEBUG
                    print("✅ E-posta doğrulandı, kod: \(code)")
                    #endif
                    verificationCode = code
                    isEmailVerified = true
                    showEmailVerification = false
                    
                    // Şifre validasyonu zaten yapıldı, direkt kayıt işlemini başlat
                    Task { @MainActor in
                        await authViewModel.register(
                            firstName: firstName.trimmingCharacters(in: .whitespacesAndNewlines),
                            lastName: lastName.trimmingCharacters(in: .whitespacesAndNewlines),
                            email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                            password: password,
                            confirmPassword: confirmPassword,
                            university: university.trimmingCharacters(in: .whitespacesAndNewlines),
                            department: department.trimmingCharacters(in: .whitespacesAndNewlines),
                            verificationCode: code
                        )
                        
                        // Kayıt başarılı olduğunda otomatik giriş yapıldı
                        #if DEBUG
                        print("✅ Kayıt tamamlandı, isAuthenticated: \(authViewModel.isAuthenticated)")
                        #endif
                    }
                },
                onCancel: {
                    showEmailVerification = false
                }
            )
        }
        .onChange(of: authViewModel.isAuthenticated) { newValue in
            // Kayıt sonrası otomatik giriş yapıldığında bildirim göster
            if newValue {
                #if DEBUG
                print("✅ RegisterForm - Otomatik giriş yapıldı, kullanıcı giriş yaptı")
                #endif
            }
        }
    }
    
    // MARK: - Subviews
    
    private var headerSection: some View {
        VStack(spacing: 8) {
            Text("Hesap Oluştur")
                .font(.system(size: 28, weight: .bold, design: .rounded))
                .foregroundColor(.white)
            
            Text("Yeni hesabınızı oluşturun")
                .font(.system(size: 15, weight: .medium))
                .foregroundColor(.white.opacity(0.8))
        }
        .padding(.bottom, 8)
    }
    
    private var nameFieldsSection: some View {
        HStack(spacing: 12) {
            // First Name
            VStack(alignment: .leading, spacing: 8) {
                Text("Ad")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "person.fill")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    TextField("Adınız", text: $firstName)
                        .textInputAutocapitalization(.words)
                        .focused($focusedField, equals: .firstName)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .firstName ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .firstName ? 2 : 1
                                )
                        )
                )
            }
            
            // Last Name
            VStack(alignment: .leading, spacing: 8) {
                Text("Soyad")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "person.fill")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    TextField("Soyadınız", text: $lastName)
                        .textInputAutocapitalization(.words)
                        .focused($focusedField, equals: .lastName)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .lastName ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .lastName ? 2 : 1
                                )
                        )
                )
            }
        }
    }
    
    private var emailFieldSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("E-posta")
                .font(.system(size: 14, weight: .semibold))
                .foregroundColor(.white.opacity(0.9))
            
            HStack(spacing: 14) {
                Image(systemName: "envelope.fill")
                    .font(.system(size: 18))
                    .foregroundColor(.white.opacity(0.7))
                    .frame(width: 24)
                
                TextField("ornek@email.com", text: $email)
                    .keyboardType(.emailAddress)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .focused($focusedField, equals: .email)
                    .foregroundColor(.white)
                    .tint(.white)
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 14)
                    .fill(Color.white.opacity(0.15))
                    .overlay(
                        RoundedRectangle(cornerRadius: 14)
                            .stroke(
                                focusedField == .email ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                lineWidth: focusedField == .email ? 2 : 1
                            )
                    )
            )
        }
    }
    
    private var passwordFieldsSection: some View {
        HStack(spacing: 12) {
            // Password
            VStack(alignment: .leading, spacing: 8) {
                Text("Şifre")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "lock.fill")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    SecureField("••••••••", text: $password)
                        .focused($focusedField, equals: .password)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .password ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .password ? 2 : 1
                                )
                        )
                )
            }
            
            // Confirm Password
            VStack(alignment: .leading, spacing: 8) {
                Text("Tekrar")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "lock.rotation")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    SecureField("••••••••", text: $confirmPassword)
                        .focused($focusedField, equals: .confirmPassword)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .confirmPassword ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .confirmPassword ? 2 : 1
                                )
                        )
                )
            }
        }
    }
    
    private var universityFieldsSection: some View {
        HStack(spacing: 12) {
            // University
            VStack(alignment: .leading, spacing: 8) {
                Text("Üniversite")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "graduationcap.fill")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    TextField("Üniversite", text: $university)
                        .textInputAutocapitalization(.words)
                        .focused($focusedField, equals: .university)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .university ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .university ? 2 : 1
                                )
                        )
                )
            }
            
            // Department
            VStack(alignment: .leading, spacing: 8) {
                Text("Bölüm")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                
                HStack(spacing: 10) {
                    Image(systemName: "building.2.fill")
                        .font(.system(size: 16))
                        .foregroundColor(.white.opacity(0.7))
                        .frame(width: 20)
                    
                    TextField("Bölüm", text: $department)
                        .textInputAutocapitalization(.words)
                        .focused($focusedField, equals: .department)
                        .foregroundColor(.white)
                        .tint(.white)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color.white.opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(
                                    focusedField == .department ? Color.white.opacity(0.5) : Color.white.opacity(0.2),
                                    lineWidth: focusedField == .department ? 2 : 1
                                )
                        )
                )
            }
        }
    }
    
    private var errorMessageSection: some View {
        Group {
            if let error = authViewModel.errorMessage, !error.isEmpty {
                HStack(spacing: 8) {
                    Image(systemName: "exclamationmark.triangle.fill")
                        .font(.system(size: 14))
                    Text(error)
                        .font(.system(size: 14, weight: .medium))
                }
                .foregroundColor(Color(hex: "fbbf24"))
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(12)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color(hex: "fbbf24").opacity(0.15))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(Color(hex: "fbbf24").opacity(0.3), lineWidth: 1)
                        )
                )
                .transition(.asymmetric(
                    insertion: .move(edge: .top).combined(with: .opacity),
                    removal: .move(edge: .top).combined(with: .opacity)
                ))
            }
        }
    }
    
    private var buttonsSection: some View {
        Group {
            // Email Verification Button
            if !isEmailVerified {
                Button(action: {
                    focusedField = nil
                    
                    // Şifre validasyonu yap
                    let trimmedPassword = password.trimmingCharacters(in: .whitespacesAndNewlines)
                    let trimmedConfirmPassword = confirmPassword.trimmingCharacters(in: .whitespacesAndNewlines)
                    
                    if trimmedPassword != trimmedConfirmPassword {
                        authViewModel.errorMessage = "Şifreler eşleşmiyor"
                        return
                    }
                    
                    if trimmedPassword.count < 8 {
                        authViewModel.errorMessage = "Şifre en az 8 karakter olmalıdır"
                        return
                    }
                    
                    // Password strength validation
                    let hasUpperCase = trimmedPassword.rangeOfCharacter(from: CharacterSet.uppercaseLetters) != nil
                    let hasLowerCase = trimmedPassword.rangeOfCharacter(from: CharacterSet.lowercaseLetters) != nil
                    let hasNumbers = trimmedPassword.rangeOfCharacter(from: CharacterSet.decimalDigits) != nil
                    
                    if !hasUpperCase || !hasLowerCase || !hasNumbers {
                        authViewModel.errorMessage = "Şifre büyük harf, küçük harf ve rakam içermelidir"
                        return
                    }
                    
                    // Şifre geçerliyse email doğrulama gönder
                    authViewModel.errorMessage = nil
                    Task {
                        await sendVerificationCode()
                    }
                }) {
                    HStack(spacing: 12) {
                        if isSendingCode {
                            ProgressView()
                                .tint(.white)
                        } else {
                            Image(systemName: "envelope.badge.fill")
                                .font(.system(size: 20))
                        }
                        Text("E-posta Doğrula")
                            .font(.system(size: 17, weight: .semibold))
                    }
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 16)
                    .background(
                        Group {
                            if isSendingCode ||
                                firstName.isEmpty || lastName.isEmpty ||
                                email.isEmpty || password.isEmpty || confirmPassword.isEmpty ||
                                university.isEmpty || department.isEmpty {
                                LinearGradient(
                                    colors: [Color.gray.opacity(0.5), Color.gray.opacity(0.3)],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            } else {
                                LinearGradient(
                                    colors: [Color.white.opacity(0.25), Color.white.opacity(0.15)],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            }
                        }
                    )
                    .cornerRadius(16)
                }
                .   disabled(isSendingCode ||
                          firstName.isEmpty || lastName.isEmpty ||
                          email.isEmpty || password.isEmpty || confirmPassword.isEmpty ||
                          university.isEmpty || department.isEmpty)
            }
            
            // Register Button
            if isEmailVerified {
                if authViewModel.isLoading {
                    HStack(spacing: 12) {
                        ProgressView()
                            .tint(.white)
                        Text("Kayıt yapılıyor...")
                            .font(.system(size: 17, weight: .semibold))
                    }
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 16)
                    .background(
                        LinearGradient(
                            colors: [Color.white.opacity(0.25), Color.white.opacity(0.15)],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(14)
                } else if authViewModel.errorMessage != nil {
                    Button(action: {
                        focusedField = nil
                        Task { @MainActor in
                            await authViewModel.register(
                                firstName: firstName.trimmingCharacters(in: .whitespacesAndNewlines),
                                lastName: lastName.trimmingCharacters(in: .whitespacesAndNewlines),
                                email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                                password: password,
                                confirmPassword: confirmPassword,
                                university: university.trimmingCharacters(in: .whitespacesAndNewlines),
                                department: department.trimmingCharacters(in: .whitespacesAndNewlines),
                                verificationCode: verificationCode
                            )
                        }
                    }) {
                        HStack(spacing: 12) {
                            Image(systemName: "arrow.clockwise")
                                .font(.system(size: 20))
                            Text("Tekrar Kayıt Ol")
                                .font(.system(size: 17, weight: .semibold))
                        }
                        .foregroundColor(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 16)
                        .background(
                            LinearGradient(
                                colors: [Color.white.opacity(0.25), Color.white.opacity(0.15)],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                    }
                    .disabled(
                        firstName.isEmpty || lastName.isEmpty ||
                        email.isEmpty || password.isEmpty || confirmPassword.isEmpty ||
                        university.isEmpty || department.isEmpty
                    )
                }
            }
        }
    }
    
    func sendVerificationCode() async {
        isSendingCode = true
        authViewModel.errorMessage = nil
        
        let trimmedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        
        do {
            try await APIService.shared.sendVerificationCode(email: trimmedEmail)
            showEmailVerification = true
        } catch {
            if let apiError = error as? APIError {
                authViewModel.errorMessage = apiError.errorDescription ?? "Kod gönderilemedi"
            } else {
                authViewModel.errorMessage = "Bir hata oluştu: \(error.localizedDescription)"
            }
        }
        
        isSendingCode = false
    }
}

#Preview {
    RegisterForm(authViewModel: AuthViewModel())
}
