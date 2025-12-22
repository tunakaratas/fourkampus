//
//  LoginModal.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

// MARK: - Universities Cache
actor UniversitiesCache {
    static let shared = UniversitiesCache()
    private var cachedUniversities: [University] = []
    private var isLoading = false
    private var lastLoadTime: Date?
    private let cacheDuration: TimeInterval = 300 // 5 dakika
    
    private init() {}
    
    func getUniversities() async throws -> [University] {
        // Cache kontrolü
        if let lastLoad = lastLoadTime,
           Date().timeIntervalSince(lastLoad) < cacheDuration,
           !cachedUniversities.isEmpty {
            return cachedUniversities
        }
        
        // Zaten yükleniyorsa bekle
        if isLoading {
            while isLoading {
                try? await Task.sleep(nanoseconds: 100_000_000) // 100ms
            }
            return cachedUniversities
        }
        
        isLoading = true
        defer { isLoading = false }
        
        do {
            let universities = try await APIService.shared.getUniversities()
            cachedUniversities = universities
            lastLoadTime = Date()
            return universities
        } catch {
            // Cache'de varsa onu döndür
            if !cachedUniversities.isEmpty {
                return cachedUniversities
            }
            throw error
        }
    }
    
    func clearCache() {
        cachedUniversities = []
        lastLoadTime = nil
    }
}

struct LoginModal: View {
    @Environment(\.colorScheme) private var colorScheme
    @EnvironmentObject var authViewModel: AuthViewModel
    @Binding var isPresented: Bool
    @State private var isLoginMode = true
    @State private var email = ""
    @State private var password = ""
    @State private var firstName = ""
    @State private var lastName = ""
    @State private var confirmPassword = ""
    @State private var selectedUniversity: University?
    @State private var selectedDepartment: String = ""
    @State private var universities: [University] = []
    @State private var showUniversityPicker = false
    @State private var showDepartmentPicker = false
    @State private var universitySearchText = ""
    @State private var departmentSearchText = ""
    @State private var showEmailVerification = false
    @State private var verificationCode = ""
    @State private var isSendingCode = false
    @State private var isEmailVerified = false
    @FocusState private var focusedField: Field?
    
    enum Field {
        case email, password, firstName, lastName, confirmPassword
    }
    
    private var backgroundGradient: LinearGradient {
        // Sistem arka planı - beyaz modda beyaz, siyah modda siyah
        if colorScheme == .dark {
            return LinearGradient(
                colors: [Color(UIColor.systemBackground), Color(UIColor.systemBackground)],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        }
        return LinearGradient(
            colors: [Color(UIColor.systemBackground), Color(UIColor.systemBackground)],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }
    
    private var cardBackground: Color {
        colorScheme == .dark ? Color.white.opacity(0.03) : Color.white
    }
    
    private var cardBorder: Color {
        colorScheme == .dark ? Color.white.opacity(0.12) : Color.black.opacity(0.05)
    }
    
    private var cardShadow: Color {
        colorScheme == .dark ? Color.black.opacity(0.55) : Color.black.opacity(0.12)
    }
    
    // Genel bölümler listesi - static olarak tanımlı
    private static let departments = [
        "Bilgisayar Mühendisliği",
        "Yazılım Mühendisliği",
        "Elektrik-Elektronik Mühendisliği",
        "Endüstri Mühendisliği",
        "Makine Mühendisliği",
        "İnşaat Mühendisliği",
        "Mimarlık",
        "İşletme",
        "İktisat",
        "İşletme Mühendisliği",
        "Tıp",
        "Diş Hekimliği",
        "Eczacılık",
        "Hemşirelik",
        "Hukuk",
        "Siyaset Bilimi",
        "Uluslararası İlişkiler",
        "Psikoloji",
        "Sosyoloji",
        "Eğitim Bilimleri",
        "İngilizce Öğretmenliği",
        "Matematik",
        "Fizik",
        "Kimya",
        "Biyoloji",
        "Tarih",
        "Türk Dili ve Edebiyatı",
        "Güzel Sanatlar",
        "Müzik",
        "Grafik Tasarım",
        "İletişim",
        "Gazetecilik",
        "Radyo, Televizyon ve Sinema"
    ]
    
    var body: some View {
        NavigationStack {
            ZStack {
                Color(UIColor.systemBackground)
                    .ignoresSafeArea()
                
                ScrollView {
                    VStack(spacing: 0) {
                        // Logo Section
                        LogoHeader(isLoginMode: isLoginMode)
                            .padding(.bottom, 32)
                        
                        // Form - Conditional rendering ile optimize (animasyon yok, hızlı geçiş)
                        if isLoginMode {
                            LoginFormView(
                                email: $email,
                                password: $password,
                                focusedField: $focusedField,
                                authViewModel: authViewModel,
                                onLogin: {
                                    handleLogin()
                                }
                            )
                            .id("login") // View identity için
                        } else {
                            // Register Form kaldırıldı - kayıt altyapısı kaldırıldı
                            Text("Kayıt özelliği kaldırıldı")
                                .foregroundColor(.secondary)
                                .padding()
                                .id("register")
                            .onChange(of: authViewModel.isAuthenticated) { newValue in
                                // Kayıt sonrası otomatik giriş yapıldığında modal'ı kapat
                                if newValue {
                                    Task { @MainActor in
                                        try? await Task.sleep(nanoseconds: 500_000_000) // 0.5 saniye
                                        isPresented = false
                                    }
                                }
                            }
                        }
                        
                        // Toggle
                        ModeToggleView(
                            isLoginMode: $isLoginMode,
                            authViewModel: authViewModel
                        )
                        .padding(.top, 8)
                    }
                    .padding(.horizontal, 24)
                    .padding(.vertical, 32)
                    .padding(.horizontal, 16)
                    .padding(.top, 24)
                    .padding(.bottom, 40)
                }
                .scrollIndicators(.hidden)
            }
            .navigationTitle("")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.prepare()
                        generator.impactOccurred()
                        isPresented = false
                    }) {
                        Image(systemName: "xmark.circle.fill")
                            .font(.system(size: 24))
                            .foregroundColor(.secondary)
                    }
                    .buttonStyle(PlainButtonStyle())
                }
            }
        }
        .sheet(isPresented: $showUniversityPicker) {
            UniversityPickerSheet(
                universities: universities,
                selectedUniversity: $selectedUniversity,
                searchText: $universitySearchText,
                isPresented: $showUniversityPicker
            )
        }
        .sheet(isPresented: $showDepartmentPicker) {
            DepartmentPickerSheet(
                departments: Self.departments,
                selectedDepartment: $selectedDepartment,
                searchText: $departmentSearchText,
                isPresented: $showDepartmentPicker
            )
        }
        .task {
            await loadUniversities()
        }
    }
    
    // MARK: - Actions
    private func handleLogin() {
        // Keyboard'u hemen kapat
        focusedField = nil
        
        // Haptic feedback zaten button'da var, burada sadece async işlemi başlat
        Task { @MainActor in
            await authViewModel.login(
                email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                password: password
            )
            if authViewModel.isAuthenticated {
                isPresented = false
            }
        }
    }
    
    private func sendVerificationCode() async {
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
    
    private func loadUniversities() async {
        do {
            universities = try await UniversitiesCache.shared.getUniversities()
        } catch {
            #if DEBUG
            print("Üniversiteler yüklenemedi: \(error.localizedDescription)")
            #endif
        }
    }
}

// MARK: - Logo Header
struct LogoHeader: View {
    let isLoginMode: Bool
    
    var body: some View {
        VStack(spacing: 20) {
            // Modern Logo
            Image(systemName: "graduationcap.fill")
                .font(.system(size: 36, weight: .light))
                .foregroundColor(Color(hex: "6366f1"))
                .padding(.top, 24)
            
            VStack(spacing: 8) {
                Text("Four Kampüs")
                    .font(.system(size: 28, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
                
                Text(isLoginMode ? "Hesabınıza giriş yapın" : "Yeni hesap oluşturun")
                    .font(.system(size: 15, weight: .regular))
                    .foregroundColor(.secondary)
            }
        }
    }
}

// MARK: - Login Form View
struct LoginFormView: View {
    @Binding var email: String
    @Binding var password: String
    @FocusState.Binding var focusedField: LoginModal.Field?
    @ObservedObject var authViewModel: AuthViewModel
    let onLogin: () -> Void
    
    var body: some View {
        VStack(spacing: 16) {
            // Email
            AuthTextField(
                title: "E-posta",
                text: $email,
                icon: "envelope.fill",
                placeholder: "ornek@email.com",
                keyboardType: .emailAddress,
                focusedField: $focusedField,
                field: .email
            )
            
            // Password
            AuthSecureField(
                title: "Şifre",
                text: $password,
                icon: "lock.fill",
                placeholder: "••••••••",
                focusedField: $focusedField,
                field: .password
            )
            
            // Success Message
            if let success = authViewModel.successMessage, !success.isEmpty {
                SuccessMessageView(message: success)
            }
            
            // Error
            if let error = authViewModel.errorMessage, !error.isEmpty {
                ErrorMessageView(message: error)
            }
            
            // Login Button
            AuthButton(
                title: "Giriş Yap",
                icon: "arrow.right.circle.fill",
                isLoading: authViewModel.isLoading,
                isDisabled: authViewModel.isLoading || email.isEmpty || password.isEmpty,
                action: onLogin
            )
        }
    }
}

// MARK: - Auth Text Field
struct AuthTextField: View {
    @Environment(\.colorScheme) private var colorScheme
    let title: String
    @Binding var text: String
    let icon: String
    let placeholder: String
    var keyboardType: UIKeyboardType = .default
    @FocusState.Binding var focusedField: LoginModal.Field?
    let field: LoginModal.Field
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if !title.isEmpty {
                Text(title)
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(.secondary)
            }
            
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 16))
                    .foregroundColor(colorScheme == .dark ? Color.white.opacity(0.6) : Color(hex: "6366f1"))
                    .frame(width: 20)
                
                TextField(placeholder, text: $text)
                    .keyboardType(keyboardType)
                    .textInputAutocapitalization(keyboardType == .emailAddress ? .never : .words)
                    .autocorrectionDisabled()
                    .focused($focusedField, equals: field)
                    .foregroundColor(colorScheme == .dark ? .white : .primary)
                    .tint(colorScheme == .dark ? .white : Color(hex: "6366f1"))
            }
            .padding(.vertical, 14)
            .padding(.horizontal, 4)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(Color.clear)
            )
            .overlay(
                Rectangle()
                    .frame(height: 2)
                    .foregroundColor(
                        focusedField == field
                            ? Color(hex: "6366f1")
                            : (colorScheme == .dark ? Color.white.opacity(0.2) : Color.gray.opacity(0.2))
                    ),
                alignment: .bottom
            )
        }
    }
}

// MARK: - Auth Secure Field
struct AuthSecureField: View {
    @Environment(\.colorScheme) private var colorScheme
    let title: String
    @Binding var text: String
    let icon: String
    let placeholder: String
    @FocusState.Binding var focusedField: LoginModal.Field?
    let field: LoginModal.Field
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title)
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(.secondary)
            
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 16))
                    .foregroundColor(colorScheme == .dark ? Color.white.opacity(0.6) : Color(hex: "6366f1"))
                    .frame(width: 20)
                
                SecureField(placeholder, text: $text)
                    .focused($focusedField, equals: field)
                    .foregroundColor(colorScheme == .dark ? .white : .primary)
                    .tint(colorScheme == .dark ? .white : Color(hex: "6366f1"))
            }
            .padding(.vertical, 14)
            .padding(.horizontal, 4)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(Color.clear)
            )
            .overlay(
                Rectangle()
                    .frame(height: 2)
                    .foregroundColor(
                        focusedField == field
                            ? Color(hex: "6366f1")
                            : (colorScheme == .dark ? Color.white.opacity(0.2) : Color.gray.opacity(0.2))
                    ),
                alignment: .bottom
            )
        }
    }
}

// MARK: - Picker Button
struct PickerButton: View {
    @Environment(\.colorScheme) private var colorScheme
    let title: String
    let icon: String
    let selectedText: String?
    let placeholder: String
    let action: () -> Void
    @State private var isPressed = false
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if !title.isEmpty {
                Text(title)
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.primary)
            }
            
            Button(action: {
                // Haptic feedback - anında (prepare edilmiş)
                let generator = UIImpactFeedbackGenerator(style: .light)
                generator.prepare()
                generator.impactOccurred()
                action()
            }) {
                HStack(spacing: 10) {
                    Image(systemName: icon)
                        .font(.system(size: 14))
                        .foregroundColor(colorScheme == .dark ? Color.white.opacity(0.9) : Color(hex: "6366f1"))
                        .frame(width: 20)
                    
                    Text(selectedText ?? placeholder)
                        .font(.system(size: 15))
                        .foregroundColor(selectedText == nil ? .secondary : (colorScheme == .dark ? .white : .primary))
                        .frame(maxWidth: .infinity, alignment: .leading)
                    
                    Spacer()
                    
                    Image(systemName: "chevron.down")
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(colorScheme == .dark ? .white.opacity(0.7) : .secondary)
                }
                .padding(14)
                .background(
                    RoundedRectangle(cornerRadius: 10)
                        .fill(colorScheme == .dark ? Color.white.opacity(0.08) : Color.white)
                        .overlay(
                            RoundedRectangle(cornerRadius: 10)
                                .stroke(colorScheme == .dark ? Color.white.opacity(0.12) : Color.gray.opacity(0.15), lineWidth: 1)
                        )
                )
            }
            .buttonStyle(PlainButtonStyle())
            .scaleEffect(isPressed ? 0.97 : 1.0)
            .animation(.easeOut(duration: 0.08), value: isPressed)
            .onLongPressGesture(minimumDuration: 0, maximumDistance: .infinity, pressing: { pressing in
                isPressed = pressing
            }, perform: {})
        }
    }
}

// MARK: - Auth Button
struct AuthButton: View {
    @Environment(\.colorScheme) private var colorScheme
    let title: String
    let icon: String
    let isLoading: Bool
    let isDisabled: Bool
    let action: () -> Void
    @State private var isPressed = false
    
    private var backgroundGradient: LinearGradient {
        if isDisabled {
            return LinearGradient(
                colors: [Color.gray.opacity(0.4), Color.gray.opacity(0.3)],
                startPoint: .leading,
                endPoint: .trailing
            )
        }
        return LinearGradient(
            colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
            startPoint: .leading,
            endPoint: .trailing
        )
    }
    
    var body: some View {
        Button(action: {
            // Haptic feedback - anında (prepare edilmiş)
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.prepare()
            generator.impactOccurred()
            action()
        }) {
            HStack(spacing: 12) {
                if isLoading {
                    ProgressView()
                        .tint(.white)
                } else {
                    Image(systemName: icon)
                        .font(.system(size: 20))
                }
                Text(title)
                    .font(.system(size: 17, weight: .semibold))
            }
            .foregroundColor(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 18)
            .background(backgroundGradient)
            .cornerRadius(10)
        }
        .buttonStyle(PlainButtonStyle())
        .scaleEffect(isPressed ? 0.97 : 1.0)
        .animation(.easeOut(duration: 0.08), value: isPressed)
        .disabled(isDisabled)
        .onLongPressGesture(minimumDuration: 0, maximumDistance: .infinity, pressing: { pressing in
            if !isDisabled && !isLoading {
                isPressed = pressing
            }
        }, perform: {})
    }
}

// MARK: - Error Message View
struct ErrorMessageView: View {
    let message: String
    @Environment(\.colorScheme) private var colorScheme
    
    var body: some View {
        HStack(spacing: 8) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.system(size: 14))
            Text(message)
                .font(.system(size: 14, weight: .medium))
        }
        .foregroundColor(Color(hex: "ef4444"))
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12)
        .padding(.leading, 4)
        .background(
            RoundedRectangle(cornerRadius: 8)
                .fill(Color(hex: "ef4444").opacity(colorScheme == .dark ? 0.15 : 0.08))
        )
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .frame(width: 4)
                .foregroundColor(Color(hex: "ef4444")),
            alignment: .leading
        )
    }
}

// MARK: - Success Message View
struct SuccessMessageView: View {
    let message: String
    @Environment(\.colorScheme) private var colorScheme
    
    var body: some View {
        HStack(spacing: 8) {
            Image(systemName: "checkmark.circle.fill")
                .font(.system(size: 14))
            Text(message)
                .font(.system(size: 14, weight: .medium))
        }
        .foregroundColor(Color(hex: "10b981"))
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12)
        .padding(.leading, 4)
        .background(
            RoundedRectangle(cornerRadius: 8)
                .fill(Color(hex: "10b981").opacity(colorScheme == .dark ? 0.15 : 0.08))
        )
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .frame(width: 4)
                .foregroundColor(Color(hex: "10b981")),
            alignment: .leading
        )
    }
}

// MARK: - Mode Toggle View
struct ModeToggleView: View {
    @Binding var isLoginMode: Bool
    @ObservedObject var authViewModel: AuthViewModel
    @State private var isPressed = false
    
    var body: some View {
        HStack(spacing: 6) {
            Text(isLoginMode ? "Hesabınız yok mu?" : "Zaten hesabınız var mı?")
                .font(.system(size: 15, weight: .regular))
                .foregroundColor(.secondary)
            
            Button(action: {
                // Haptic feedback - anında (prepare edilmiş)
                let generator = UIImpactFeedbackGenerator(style: .light)
                generator.prepare()
                generator.impactOccurred()
                isLoginMode.toggle()
                authViewModel.errorMessage = nil
            }) {
                Text(isLoginMode ? "Kayıt Ol" : "Giriş Yap")
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundColor(Color(hex: "6366f1"))
            }
            .buttonStyle(PlainButtonStyle())
            .scaleEffect(isPressed ? 0.95 : 1.0)
            .animation(.easeOut(duration: 0.08), value: isPressed)
            .onLongPressGesture(minimumDuration: 0, maximumDistance: .infinity, pressing: { pressing in
                isPressed = pressing
            }, perform: {})
        }
    }
}

// MARK: - University Picker Sheet
struct UniversityPickerSheet: View {
    let universities: [University]
    @Binding var selectedUniversity: University?
    @Binding var searchText: String
    @Binding var isPresented: Bool
    
    var filteredUniversities: [University] {
        if searchText.isEmpty {
            return universities
        }
        return universities.filter { $0.name.localizedCaseInsensitiveContains(searchText) }
    }
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Search Bar
                HStack {
                    Image(systemName: "magnifyingglass")
                        .foregroundColor(.secondary)
                    TextField("Üniversite ara...", text: $searchText)
                }
                .padding(12)
                .background(Color(UIColor.secondarySystemBackground))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(Color(UIColor.separator), lineWidth: 1)
                )
                .padding(.horizontal, 16)
                .padding(.top, 16)
                .padding(.bottom, 12)
                
                // Universities List
                ScrollView {
                    LazyVStack(spacing: 0) {
                        ForEach(filteredUniversities) { university in
                            Button(action: {
                                let generator = UIImpactFeedbackGenerator(style: .light)
                                generator.prepare()
                                generator.impactOccurred()
                                selectedUniversity = university
                                isPresented = false
                            }) {
                                HStack {
                                    Text(university.name)
                                        .font(.system(size: 16))
                                        .foregroundColor(.primary)
                                    Spacer()
                                    if selectedUniversity?.id == university.id {
                                        Image(systemName: "checkmark")
                                            .foregroundColor(Color(hex: "6366f1"))
                                            .font(.system(size: 16, weight: .semibold))
                                    }
                                }
                                .padding(.horizontal, 16)
                                .padding(.vertical, 14)
                                .background(selectedUniversity?.id == university.id ? Color(hex: "6366f1").opacity(0.1) : Color(UIColor.secondarySystemBackground))
                            }
                            .buttonStyle(PlainButtonStyle())
                            
                            Divider()
                                .padding(.leading, 16)
                        }
                    }
                }
            }
            .background(Color(UIColor.systemBackground))
            .navigationTitle("Üniversite Seç")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.prepare()
                        generator.impactOccurred()
                        isPresented = false
                    }) {
                        Text("Kapat")
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    .buttonStyle(PlainButtonStyle())
                }
            }
        }
    }
}

// MARK: - Department Picker Sheet
struct DepartmentPickerSheet: View {
    @Environment(\.colorScheme) private var colorScheme
    let departments: [String]
    @Binding var selectedDepartment: String
    @Binding var searchText: String
    @Binding var isPresented: Bool
    
    var filteredDepartments: [String] {
        if searchText.isEmpty {
            return departments
        }
        return departments.filter { $0.localizedCaseInsensitiveContains(searchText) }
    }
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Search Bar
                HStack {
                    Image(systemName: "magnifyingglass")
                    .foregroundColor(.secondary)
                    TextField("Bölüm ara...", text: $searchText)
                }
                .padding(12)
            .background(Color(UIColor.secondarySystemBackground))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                    .stroke(Color(UIColor.separator), lineWidth: 1)
                )
                .padding(.horizontal, 16)
                .padding(.top, 16)
                .padding(.bottom, 12)
                
                // Departments List
                ScrollView {
                    LazyVStack(spacing: 0) {
                        ForEach(filteredDepartments, id: \.self) { department in
                            Button(action: {
                                let generator = UIImpactFeedbackGenerator(style: .light)
                                generator.prepare()
                                generator.impactOccurred()
                                selectedDepartment = department
                                isPresented = false
                            }) {
                                HStack {
                                    Text(department)
                                        .font(.system(size: 16))
                                        .foregroundColor(.primary)
                                    Spacer()
                                    if selectedDepartment == department {
                                        Image(systemName: "checkmark")
                                            .foregroundColor(Color(hex: "6366f1"))
                                            .font(.system(size: 16, weight: .semibold))
                                    }
                                }
                                .padding(.horizontal, 16)
                                .padding(.vertical, 14)
                                .background(
                                    selectedDepartment == department
                                    ? Color(hex: "6366f1").opacity(colorScheme == .dark ? 0.2 : 0.08)
                                    : Color(UIColor.secondarySystemBackground)
                                )
                            }
                            .buttonStyle(PlainButtonStyle())
                            
                            Divider()
                                .padding(.leading, 16)
                        }
                    }
                }
            }
            .background(Color(UIColor.systemBackground))
            .navigationTitle("Bölüm Seç")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.prepare()
                        generator.impactOccurred()
                        isPresented = false
                    }) {
                        Text("Kapat")
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    .buttonStyle(PlainButtonStyle())
                }
            }
        }
    }
}
