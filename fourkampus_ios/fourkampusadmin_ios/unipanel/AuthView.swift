//
//  AuthView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import Combine

// MARK: - Authentication View Model
@MainActor
class AuthViewModel: ObservableObject {
    @Published var isAuthenticated = false
    @Published var isLoading = false
    @Published var isLoggingOut = false
    @Published var errorMessage: String?
    @Published var successMessage: String?
    @Published var currentUser: User?
    
    // Rate limiting - gerçek hayat senaryoları için
    private var loginAttempts: [Date] = []
    private let maxLoginAttempts = 5
    private let loginAttemptWindow: TimeInterval = 300 // 5 dakika
    
    init() {
        // Token varsa kullanıcıyı yükle (async, blocking olmadan)
        // Token süresiz - expiration kontrolü yok
        if SecureStorage.shared.isTokenValid() {
            Task {
                await checkAuthentication()
            }
        }
        // Token yoksa veya geçersizse bile temizleme - kullanıcı manuel logout yapabilir
    }
    
    func checkAuthentication() async {
        guard !isLoading else { return }
        
        do {
            #if DEBUG
            print("🔍 Authentication kontrol ediliyor...")
            #endif
            let user = try await AsyncUtils.withTimeout(seconds: 5) {
                try await APIService.shared.getCurrentUser()
            }
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            currentUser = user
            isAuthenticated = true
            #if DEBUG
            print("✅ Authentication başarılı, kullanıcı: \(user.displayName)")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Authentication kontrolü başarısız (token korunuyor): \(error.localizedDescription)")
            #endif
            // Token süresiz - hata olsa bile logout yapma
            // Sadece authentication state'ini false yap (kullanıcı manuel logout yapabilir)
            // Token Keychain'de kalır, kullanıcı istediğinde tekrar deneyebilir
        }
    }
    
    func login(email: String, password: String) async {
        // Zaten yükleniyorsa tekrar başlatma (duplicate request prevention)
        guard !isLoading else {
            #if DEBUG
            print("⚠️ Login zaten devam ediyor, atlanıyor")
            #endif
            return
        }
        
        // Input validation - gerçek hayat senaryoları için
        let trimmedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        let trimmedPassword = password.trimmingCharacters(in: .whitespacesAndNewlines)
        
        guard !trimmedEmail.isEmpty else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "E-posta adresi boş olamaz"
            return
        }
        
        guard !trimmedPassword.isEmpty else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "Şifre boş olamaz"
            return
        }
        
        // Email format validation
        let emailRegex = "[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,64}"
        let emailPredicate = NSPredicate(format:"SELF MATCHES %@", emailRegex)
        guard emailPredicate.evaluate(with: trimmedEmail) else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "Geçerli bir e-posta adresi giriniz"
            return
        }
        
        // Password length validation
        guard trimmedPassword.count >= 6 && trimmedPassword.count <= 128 else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "Şifre 6-128 karakter arasında olmalıdır"
            return
        }
        
        // Rate limiting kontrolü (client-side - ekstra güvenlik)
        let now = Date()
        loginAttempts = loginAttempts.filter { now.timeIntervalSince($0) < loginAttemptWindow }
        
        if loginAttempts.count >= maxLoginAttempts {
            let remainingTime = Int(loginAttemptWindow - (now.timeIntervalSince(loginAttempts.first ?? now)))
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "Çok fazla deneme yaptınız. Lütfen \(remainingTime / 60) dakika sonra tekrar deneyin."
            return
        }
        
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        #if DEBUG
        SecureLogger.d("AuthView", "Giriş yapılıyor")
        #endif
        
        // Exponential backoff için retry sayacı
        var retryCount = 0
        let maxRetries = 2 // Maksimum 2 retry (toplam 3 deneme)
        
        while retryCount <= maxRetries {
            do {
                // Login işlemi (APIService içinde zaten retry var, burada ekstra retry)
                _ = try await APIService.shared.login(email: trimmedEmail, password: trimmedPassword)
                #if DEBUG
                // Token bilgisi loglanmıyor - güvenlik için
                SecureLogger.d("AuthView", "Giriş başarılı")
                #endif
                
                // Rate limiting'i temizle (başarılı giriş)
                loginAttempts.removeAll()
                
                // Token zaten APIService'de kaydedildi, burada sadece kontrol ediyoruz
                #if DEBUG
                // Token bilgisi loglanmıyor - güvenlik için
                SecureLogger.d("AuthView", "Token kontrolü tamamlandı")
                #endif
                
                // Kullanıcı bilgilerini yükle (timeout ile)
                do {
                    let user = try await AsyncUtils.withTimeout(seconds: 10) {
                        try await APIService.shared.getCurrentUser()
                    }
                    // @MainActor ile işaretlendiği için MainActor.run gereksiz
                    currentUser = user
                    isAuthenticated = true
                    isLoading = false
                    errorMessage = nil
                    successMessage = "Giriş başarılı! Hoş geldiniz, \(user.displayName)"
                    
                    #if DEBUG
                    print("✅ Authentication state güncellendi: isAuthenticated = \(isAuthenticated)")
                    print("   Current user: \(user.displayName) (ID: \(user.id))")
                    #endif
                    
                    // Başarılı login haptic feedback
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.success)
                    
                    #if DEBUG
                    print("✅ Kullanıcı bilgileri yüklendi: \(user.displayName)")
                    #endif
                    
                    // Success mesajını 3 saniye sonra temizle
                    try? await Task.sleep(nanoseconds: 3_000_000_000)
                    // @MainActor ile işaretlendiği için MainActor.run gereksiz
                    successMessage = nil
                    return // Başarılı, çık
                } catch {
                    #if DEBUG
                    print("⚠️ Kullanıcı bilgileri yüklenemedi, ama login başarılı: \(error.localizedDescription)")
                    #endif
                    // Login başarılı ama user bilgisi alınamadı, yine de giriş yap
                    // @MainActor ile işaretlendiği için MainActor.run gereksiz
                    // Token var, giriş yapılmış sayılabilir
                    isAuthenticated = true
                    isLoading = false
                    errorMessage = nil
                    successMessage = "Giriş başarılı!"
                    
                    // Başarılı login haptic feedback
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.success)
                    
                    // Success mesajını 3 saniye sonra temizle
                    try? await Task.sleep(nanoseconds: 3_000_000_000)
                    // @MainActor ile işaretlendiği için MainActor.run gereksiz
                    successMessage = nil
                    return // Başarılı, çık
                }
            } catch {
                // Başarısız giriş
                retryCount += 1
                
                #if DEBUG
                print("❌ Giriş hatası (deneme \(retryCount)/\(maxRetries + 1)): \(error.localizedDescription)")
                #endif
                
                // Retry edilebilir hatalar için exponential backoff
                let shouldRetry: Bool
                let errorMsg = ErrorHandler.userFriendlyMessage(from: error)
                
                if let urlError = error as? URLError {
                    let retryableErrors: [URLError.Code] = [
                        .timedOut,
                        .networkConnectionLost,
                        .cannotConnectToHost,
                        .cannotFindHost,
                        .dnsLookupFailed,
                        .notConnectedToInternet
                    ]
                    shouldRetry = retryableErrors.contains(urlError.code) && retryCount <= maxRetries
                } else if let apiError = error as? APIError {
                    // Rate limit veya kilit hatası için retry yapma
                    if errorMsg.contains("kilit") || errorMsg.contains("rate limit") || errorMsg.contains("çok fazla") {
                        shouldRetry = false
                    } else if case .httpError(let code) = apiError, code >= 500 && retryCount <= maxRetries {
                        // Server hataları için retry
                        shouldRetry = true
                    } else {
                        shouldRetry = false
                    }
                } else {
                    shouldRetry = false
                }
                
                if shouldRetry && retryCount <= maxRetries {
                    // Exponential backoff: 1s, 2s
                    let delay = pow(2.0, Double(retryCount - 1))
                    #if DEBUG
                    print("🔄 Retry \(retryCount)/\(maxRetries) after \(delay)s...")
                    #endif
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    continue // Tekrar dene
                } else {
                    // Retry yapılmayacak veya max retry'a ulaşıldı
                    // Başarısız giriş - rate limiting'e ekle
                    loginAttempts.append(Date())
                    
                    // @MainActor ile işaretlendiği için MainActor.run gereksiz
                    errorMessage = errorMsg
                    isLoading = false
                    return // Hata, çık
                }
            }
        }
    }
    
    func logout() async {
        // Zaten logout yapılıyorsa tekrar başlatma
        guard !isLoggingOut else {
            #if DEBUG
            print("⚠️ Logout zaten devam ediyor, atlanıyor")
            #endif
            return
        }
        
        isLoggingOut = true
        errorMessage = nil
        successMessage = nil
        
        // Haptic feedback - başlangıç
        let impactGenerator = UIImpactFeedbackGenerator(style: .medium)
        impactGenerator.impactOccurred()
        
        #if DEBUG
        print("🚪 Çıkış yapılıyor...")
        #endif
        
        do {
            // API'ye logout isteği gönder (timeout ile)
            try await AsyncUtils.withTimeout(seconds: 5) {
                try await APIService.shared.logout()
            }
        } catch {
            #if DEBUG
            print("⚠️ Logout hatası (devam ediliyor): \(error.localizedDescription)")
            #endif
            // Hata olsa bile logout işlemini tamamla (local logout)
        }
        
        // Token'ı temizle ve state'i güncelle
        APIService.shared.clearAuthToken()
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        // Önce isAuthenticated = false yap (UI güncellenmesi için)
        isAuthenticated = false
        currentUser = nil
        isLoggingOut = false
        
        // Başarılı logout haptic feedback
        let notificationGenerator = UINotificationFeedbackGenerator()
        notificationGenerator.notificationOccurred(.success)
        
        // UI güncellenmesi için kısa bir bekleme
        try? await Task.sleep(nanoseconds: 100_000_000) // 0.1 saniye
        
        #if DEBUG
        print("✅ Çıkış yapıldı - isAuthenticated: \(isAuthenticated), currentUser: \(currentUser?.displayName ?? "nil")")
        #endif
    }
    
    func register(
        firstName: String,
        lastName: String,
        email: String,
        password: String,
        confirmPassword: String,
        university: String,
        department: String,
        studentId: String = "",
        phoneNumber: String = "",
        verificationCode: String = ""
    ) async {
        // Zaten yükleniyorsa tekrar başlatma
        guard !isLoading else {
            #if DEBUG
            print("⚠️ Register zaten devam ediyor, atlanıyor")
            #endif
            return
        }
        
        // Input validation
        let trimmedFirstName = firstName.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedLastName = lastName.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        let trimmedPassword = password.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedConfirmPassword = confirmPassword.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedUniversity = university.trimmingCharacters(in: .whitespacesAndNewlines)
        let trimmedDepartment = department.trimmingCharacters(in: .whitespacesAndNewlines)
        
        guard !trimmedFirstName.isEmpty else {
            errorMessage = "Ad boş olamaz"
            return
        }
        
        guard !trimmedLastName.isEmpty else {
            errorMessage = "Soyad boş olamaz"
            return
        }
        
        guard !trimmedEmail.isEmpty else {
            errorMessage = "E-posta adresi boş olamaz"
            return
        }
        
        guard !trimmedPassword.isEmpty else {
            errorMessage = "Şifre boş olamaz"
            return
        }
        
        guard trimmedPassword == trimmedConfirmPassword else {
            errorMessage = "Şifreler eşleşmiyor"
            return
        }
        
        guard trimmedPassword.count >= 8 else {
            errorMessage = "Şifre en az 8 karakter olmalıdır"
            return
        }
        
        guard !trimmedUniversity.isEmpty else {
            errorMessage = "Üniversite boş olamaz"
            return
        }
        
        guard !trimmedDepartment.isEmpty else {
            errorMessage = "Bölüm boş olamaz"
            return
        }
        
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        #if DEBUG
        print("🔄 Kayıt işlemi başlatılıyor...")
        #endif
        
        do {
            // API için veri hazırlığı
            var userData: [String: Any] = [
                "first_name": trimmedFirstName,
                "last_name": trimmedLastName,
                "email": trimmedEmail,
                "password": trimmedPassword,
                "university": trimmedUniversity,
                "department": trimmedDepartment
            ]
            
            if !studentId.isEmpty {
                userData["student_id"] = studentId
            }
            if !phoneNumber.isEmpty {
                userData["phone_number"] = phoneNumber
            }
            if !verificationCode.isEmpty {
                userData["verification_code"] = verificationCode
            }
            
            // Register API call
            let user = try await APIService.shared.register(userData: userData)
            
            // Başarılı kayıt sonrası user zaten dönüyor ve token kaydediliyor
            currentUser = user
            isAuthenticated = true
            isLoading = false
            errorMessage = nil
            successMessage = "Kayıt başarılı! Hoş geldiniz, \(user.displayName)"
            
            #if DEBUG
            print("✅ Kayıt başarılı: \(user.displayName)")
            #endif
            
            // Başarılı kayıt haptic feedback
            let generator = UINotificationFeedbackGenerator()
            generator.notificationOccurred(.success)
            
            // Success mesajını 3 saniye sonra temizle
            try? await Task.sleep(nanoseconds: 3_000_000_000)
            successMessage = nil
        } catch {
            #if DEBUG
            print("❌ Kayıt hatası: \(error.localizedDescription)")
            #endif
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            isLoading = false
        }
    }
}

// MARK: - Authentication View
struct AuthView: View {
    @StateObject private var authVM = AuthViewModel()
    @AppStorage("hasCompletedOnboarding") private var hasCompletedOnboarding = false
    
    var body: some View {
        Group {
            if hasCompletedOnboarding {
                // Her zaman ana uygulamayı göster, login kontrolü detay sayfalarında yapılacak
                MainAppView(authViewModel: authVM)
                    .environmentObject(authVM)
            } else {
                // Onboarding göster
                OnboardingView()
            }
        }
    }
}

// ... (dosyanın geri kalanı aynı)
