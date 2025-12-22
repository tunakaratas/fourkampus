//
//  ProfileView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import UserNotifications

// MARK: - Common Departments List
fileprivate let commonDepartments = [
    "Bilgisayar Mühendisliği",
    "Yazılım Mühendisliği",
    "Elektrik-Elektronik Mühendisliği",
    "Endüstri Mühendisliği",
    "Makine Mühendisliği",
    "İnşaat Mühendisliği",
    "Mimarlık",
    "İç Mimarlık",
    "İşletme",
    "İktisat",
    "Siyaset Bilimi ve Kamu Yönetimi",
    "Uluslararası İlişkiler",
    "Hukuk",
    "Tıp",
    "Diş Hekimliği",
    "Eczacılık",
    "Hemşirelik",
    "Psikoloji",
    "Sosyoloji",
    "Felsefe",
    "Tarih",
    "Edebiyat",
    "İngiliz Dili ve Edebiyatı",
    "Matematik",
    "Fizik",
    "Kimya",
    "Biyoloji",
    "Gıda Mühendisliği",
    "Kimya Mühendisliği",
    "Çevre Mühendisliği",
    "Enerji Sistemleri Mühendisliği",
    "Mekatronik Mühendisliği",
    "Otomotiv Mühendisliği",
    "Gemi İnşaatı ve Denizcilik Mühendisliği",
    "Havacılık ve Uzay Mühendisliği",
    "Petrol ve Doğalgaz Mühendisliği",
    "Jeoloji Mühendisliği",
    "Madencilik Mühendisliği",
    "Harita Mühendisliği",
    "Ziraat Mühendisliği",
    "Veterinerlik",
    "Güzel Sanatlar",
    "Müzik",
    "Tiyatro",
    "Sinema ve Televizyon",
    "İletişim",
    "Gazetecilik",
    "Radyo, Televizyon ve Sinema",
    "Halkla İlişkiler ve Tanıtım",
    "Reklamcılık",
    "Grafik Tasarım",
    "Endüstriyel Tasarım",
    "Moda Tasarımı",
    "Turizm ve Otel İşletmeciliği",
    "Gastronomi ve Mutfak Sanatları",
    "Spor Bilimleri",
    "Beden Eğitimi ve Spor",
    "Antrenörlük Eğitimi",
    "Fizyoterapi ve Rehabilitasyon",
    "Beslenme ve Diyetetik",
    "Çocuk Gelişimi",
    "Okul Öncesi Öğretmenliği",
    "Sınıf Öğretmenliği",
    "Türkçe Öğretmenliği",
    "Matematik Öğretmenliği",
    "Fen Bilgisi Öğretmenliği",
    "İngilizce Öğretmenliği",
    "Almanca Öğretmenliği",
    "Fransızca Öğretmenliği",
    "Tarih Öğretmenliği",
    "Coğrafya Öğretmenliği",
    "Müzik Öğretmenliği",
    "Resim Öğretmenliği",
    "Beden Eğitimi Öğretmenliği",
    "Rehberlik ve Psikolojik Danışmanlık",
    "Özel Eğitim Öğretmenliği",
    "Zihin Engelliler Öğretmenliği",
    "İşitme Engelliler Öğretmenliği",
    "Görme Engelliler Öğretmenliği",
    "Üstün Zekalılar Öğretmenliği",
    "Diğer"
]

// MARK: - Profile Navigation Destination
enum ProfileDestination: Hashable {
    case profileDetails
    case settings
    case about
    case support
}

// MARK: - Profile Tab View
struct ProfileView: View {
    @ObservedObject var viewModel: ProfileViewModel
    @EnvironmentObject var authViewModel: AuthViewModel
    @AppStorage("appearanceMode") private var appearanceMode: String = "system"
    @State private var navigationPath = NavigationPath()
    @State private var showLoginModal = false
    
    var body: some View {
        NavigationStack(path: $navigationPath) {
            Group {
                if authViewModel.isAuthenticated {
                    if viewModel.isLoading && viewModel.user == nil {
                        loadingView
                    } else if let user = viewModel.user {
                        profileTabView(user: user)
                    } else if let error = viewModel.errorMessage {
                        errorView(error: error)
                    } else {
                        loadingView
                    }
                } else {
                    loginRequiredView
                }
            }
            .refreshable {
                await viewModel.loadUser()
            }
            .onAppear {
                if authViewModel.isAuthenticated && viewModel.user == nil && !viewModel.isLoading {
                    #if DEBUG
                    print("📱 ProfileView onAppear: Kullanıcı bilgileri yükleniyor...")
                    #endif
                    Task {
                        await viewModel.loadUser()
                    }
                }
                // Üniversiteleri yükle (profil düzenleme için)
                if viewModel.universities.isEmpty && !viewModel.isLoadingUniversities {
                    Task {
                        await viewModel.loadUniversities()
                    }
                }
            }
            .onChange(of: authViewModel.isAuthenticated) { newValue in
                if newValue {
                    if viewModel.user == nil && !viewModel.isLoading {
                        #if DEBUG
                        print("📱 ProfileView onChange: Giriş yapıldı, kullanıcı bilgileri yükleniyor...")
                        #endif
                        Task {
                            await viewModel.loadUser()
                        }
                    }
                } else {
                    // Logout yapıldı - navigation'ı sıfırla ve login modal'ı aç
                    #if DEBUG
                    print("📱 ProfileView onChange: Logout yapıldı, navigation sıfırlanıyor ve login modal açılıyor...")
                    #endif
                    // NavigationPath güncellemesini bir sonraki run loop'a ertele
                    Task { @MainActor in
                    navigationPath = NavigationPath() // Navigation stack'i temizle
                    }
                    showLoginModal = true
                }
            }
            .navigationTitle("Hesabım")
            .navigationBarTitleDisplayMode(.large)
            .navigationDestination(for: ProfileDestination.self) { destination in
                switch destination {
                case .profileDetails:
                    if let user = viewModel.user {
                        ProfileDetailsView(user: user, viewModel: viewModel)
                    }
                case .settings:
                    if let user = viewModel.user {
                        SettingsView(
                            user: user,
                            viewModel: viewModel,
                            appearanceMode: $appearanceMode
                        )
                    } else {
                        // Login olmadan ayarlar görünümü - sadece görünüm ayarları
                        SettingsView(
                            user: nil,
                            viewModel: viewModel,
                            appearanceMode: $appearanceMode
                        )
                    }
                case .about:
                    if let user = viewModel.user {
                        AboutView(user: user)
                    } else {
                        AboutView(user: nil)
                    }
                case .support:
                    SupportView()
                }
            }

            .preferredColorScheme(appearanceMode == "system" ? nil : (appearanceMode == "dark" ? .dark : .light))
        }
    }
    
    @ViewBuilder
    private func profileTabView(user: User) -> some View {
        ZStack {
            // Modern gradient background
            LinearGradient(
                colors: [
                    Color(UIColor.systemBackground),
                    Color(hex: "6366f1").opacity(0.03)
                ],
                startPoint: .top,
                endPoint: .bottom
            )
            .ignoresSafeArea()
            
            ScrollView {
                VStack(spacing: 0) {
                    // Modern Profile Header with gradient card
                    VStack(spacing: 20) {
                        // Profile Photo with glow effect
                        ModernProfileHeaderView(
                            firstName: user.firstName,
                            lastName: user.lastName,
                            email: user.email,
                            profileImageURL: user.profileImageURL
                        )
                    }
                    .padding(.top, 24)
                    .padding(.horizontal, 20)
                    .padding(.bottom, 32)
                    
                    // Action Buttons with modern cards
                    VStack(spacing: 12) {
                        Text("Hesap Yönetimi")
                            .font(.system(size: 13, weight: .semibold))
                            .foregroundColor(.secondary)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(.horizontal, 20)
                            .padding(.bottom, 4)
                        
                        ModernProfileActionButton(
                            title: "Profil Detayları",
                            subtitle: "Bilgilerinizi görüntüleyin ve düzenleyin",
                            icon: "person.circle.fill",
                            iconColor: Color(hex: "8b5cf6"),
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.profileDetails)
                                }
                            }
                        )
                        
                        ModernProfileActionButton(
                            title: "Ayarlar",
                            subtitle: "Uygulama tercihlerinizi yönetin",
                            icon: "gearshape.fill",
                            iconColor: Color(hex: "6366f1"),
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.settings)
                                }
                            }
                        )
                        
                        Text("Yardım & Destek")
                            .font(.system(size: 13, weight: .semibold))
                            .foregroundColor(.secondary)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(.horizontal, 20)
                            .padding(.top, 16)
                            .padding(.bottom, 4)
                        
                        ModernProfileActionButton(
                            title: "Hakkında",
                            subtitle: "Uygulama hakkında bilgi edinin",
                            icon: "info.circle.fill",
                            iconColor: Color(hex: "10b981"),
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.about)
                                }
                            }
                        )
                        
                        ModernProfileActionButton(
                            title: "Destek",
                            subtitle: "Yardım alın, bize ulaşın",
                            icon: "lifepreserver.fill",
                            iconColor: Color(hex: "f59e0b"),
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.support)
                                }
                            }
                        )
                    }
                    .padding(.horizontal, 16)
                    .padding(.bottom, 32)
                }
            }
        }
    }
    
    private var loadingView: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            ProgressView()
                .scaleEffect(1.5)
        }
    }
    
    private func errorView(error: String) -> some View {
        VStack(spacing: 20) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.system(size: 56))
                .foregroundColor(Color(hex: "ef4444"))
            Text("Yükleme Hatası")
                .font(.system(size: 20, weight: .semibold))
                .foregroundColor(.primary)
            Text(error)
                .font(.system(size: 15))
                .foregroundColor(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 40)
            Button(action: {
                Task {
                    await viewModel.loadUser()
                }
            }) {
                Text("Tekrar Dene")
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundColor(.white)
                    .padding(.horizontal, 24)
                    .padding(.vertical, 12)
                    .background(Color(hex: "6366f1"))
                    .cornerRadius(10)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(UIColor.systemBackground))
    }
    
    private var loginRequiredView: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            ScrollView {
                VStack(spacing: 24) {
                    // Header with Login Prompt
                    VStack(spacing: 16) {
                        // Modern gradient icon
                        ZStack {
                            Circle()
                                .fill(
                                    LinearGradient(
                                        colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    )
                                )
                                .frame(width: 80, height: 80)
                                .shadow(color: Color(hex: "6366f1").opacity(0.3), radius: 20, x: 0, y: 10)
                            
                            Image(systemName: "person.circle.fill")
                                .font(.system(size: 40, weight: .semibold))
                                .foregroundColor(.white)
                        }
                        .padding(.top, 32)
                        
                        VStack(spacing: 8) {
                            Text("Hoş Geldiniz!")
                                .font(.system(size: 28, weight: .bold, design: .rounded))
                                .foregroundColor(.primary)
                            
                            Text("Profil özelliklerine erişmek için giriş yapın")
                                .font(.system(size: 15, weight: .regular))
                                .foregroundColor(.secondary)
                                .multilineTextAlignment(.center)
                                .padding(.horizontal, 32)
                        }
                        
                        // Login Button
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .medium)
                            generator.impactOccurred()
                            showLoginModal = true
                        }) {
                            HStack(spacing: 10) {
                                Text("Giriş Yap")
                                    .font(.system(size: 16, weight: .semibold))
                                Image(systemName: "arrow.right")
                                    .font(.system(size: 14, weight: .semibold))
                            }
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .background(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(14)
                            .shadow(color: Color(hex: "6366f1").opacity(0.4), radius: 15, x: 0, y: 8)
                        }
                        .buttonStyle(PlainButtonStyle())
                        .padding(.horizontal, 32)
                        .padding(.top, 8)
                    }
                    .padding(.bottom, 16)
                    
                    // Divider
                    HStack {
                        Rectangle()
                            .fill(Color.gray.opacity(0.3))
                            .frame(height: 1)
                        
                        Text("veya")
                            .font(.system(size: 13, weight: .medium))
                            .foregroundColor(.secondary)
                            .padding(.horizontal, 12)
                        
                        Rectangle()
                            .fill(Color.gray.opacity(0.3))
                            .frame(height: 1)
                    }
                    .padding(.horizontal, 32)
                    
                    // Public Access Sections
                    VStack(spacing: 12) {
                        Text("Giriş yapmadan erişebilirsiniz")
                            .font(.system(size: 13, weight: .medium))
                            .foregroundColor(.secondary)
                            .padding(.bottom, 4)
                        
                        // Settings Button
                        ProfileActionButton(
                            title: "Ayarlar",
                            icon: "gearshape.fill",
                            iconColor: Color(hex: "6366f1"),
                            isSelected: false,
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.settings)
                                }
                            }
                        )
                        
                        // About Button
                        ProfileActionButton(
                            title: "Hakkında",
                            icon: "info.circle.fill",
                            iconColor: Color(hex: "10b981"),
                            isSelected: false,
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.about)
                                }
                            }
                        )
                        
                        // Support Button
                        ProfileActionButton(
                            title: "Destek",
                            icon: "lifepreserver.fill",
                            iconColor: Color(hex: "f59e0b"),
                            isSelected: false,
                            action: {
                                Task { @MainActor in
                                    navigationPath.append(ProfileDestination.support)
                                }
                            }
                        )
                    }
                    .padding(.horizontal, 16)
                    .padding(.bottom, 32)
                }
            }
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
    }
}

// MARK: - Profile Header View

// MARK: - Profile Header View
struct ProfileHeaderView: View {
    let firstName: String
    let lastName: String
    let email: String
    let profileImageURL: String?
    
    var displayName: String {
        if !firstName.isEmpty && !lastName.isEmpty {
            return "\(firstName) \(lastName)"
        } else if !firstName.isEmpty {
            return firstName
        } else if !lastName.isEmpty {
            return lastName
        } else {
            return "Kullanıcı"
        }
    }
    
    var initials: String {
        let first = firstName.prefix(1).uppercased()
        let last = lastName.prefix(1).uppercased()
        if !first.isEmpty && !last.isEmpty {
            return "\(first)\(last)"
        } else if !first.isEmpty {
            return first
        } else {
            return "K"
        }
    }
    
    var body: some View {
        VStack(spacing: 20) {
            // Profile Photo
            if let imageURL = profileImageURL, !imageURL.isEmpty {
                let fullImageURL = APIService.fullImageURL(from: imageURL) ?? imageURL
                AsyncImage(url: URL(string: fullImageURL)) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                    case .failure(_), .empty:
                        ZStack {
                            Circle()
                                .fill(
                                    LinearGradient(
                                        gradient: Gradient(colors: [
                                            Color(hex: "8b5cf6"),
                                            Color(hex: "6366f1")
                                        ]),
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    )
                                )
                                .frame(width: 120, height: 120)
                            Text(initials)
                                .font(.system(size: 48, weight: .bold))
                                .foregroundColor(.white)
                        }
                    @unknown default:
                        EmptyView()
                    }
                }
                .frame(width: 120, height: 120)
                .clipShape(Circle())
                .overlay(
                    Circle()
                        .stroke(
                            LinearGradient(
                                gradient: Gradient(colors: [
                                    Color.white.opacity(0.6),
                                    Color.white.opacity(0.3)
                                ]),
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 4
                        )
                )
                .shadow(color: Color(hex: "8b5cf6").opacity(0.3), radius: 20, x: 0, y: 10)
            } else {
                ZStack {
                    Circle()
                        .fill(
                            LinearGradient(
                                gradient: Gradient(colors: [
                                    Color(hex: "8b5cf6"),
                                    Color(hex: "6366f1")
                                ]),
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                        .frame(width: 120, height: 120)
                    Text(initials)
                        .font(.system(size: 48, weight: .bold))
                        .foregroundColor(.white)
                }
                .overlay(
                    Circle()
                        .stroke(
                            LinearGradient(
                                gradient: Gradient(colors: [
                                    Color.white.opacity(0.6),
                                    Color.white.opacity(0.3)
                                ]),
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 4
                        )
                )
                .shadow(color: Color(hex: "8b5cf6").opacity(0.3), radius: 20, x: 0, y: 10)
            }
            
            // Name
            Text(displayName)
                .font(.system(size: 28, weight: .bold, design: .rounded))
                .foregroundColor(.primary)
            
            // Email
            if !email.isEmpty {
                Text(email)
                    .font(.system(size: 15, weight: .regular))
                    .foregroundColor(.secondary)
            }
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Profile Action Button
struct ProfileActionButton: View {
    let title: String
    let icon: String
    let iconColor: Color
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            action()
        }) {
            HStack(spacing: 16) {
                ZStack {
                    Circle()
                        .fill(isSelected ? iconColor.opacity(0.2) : iconColor.opacity(0.1))
                        .frame(width: 48, height: 48)
                    
                    Image(systemName: icon)
                        .font(.system(size: 20, weight: .semibold))
                        .foregroundColor(isSelected ? iconColor : iconColor.opacity(0.7))
                }
                
                Text(title)
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(.primary)
                
                Spacer()
                
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.secondary.opacity(0.6))
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 16)
                    .fill(isSelected ? iconColor.opacity(0.05) : Color(UIColor.secondarySystemBackground))
            )
            .overlay(
                RoundedRectangle(cornerRadius: 16)
                    .stroke(isSelected ? iconColor.opacity(0.3) : Color.gray.opacity(0.1), lineWidth: isSelected ? 2 : 1)
            )
            .shadow(color: isSelected ? iconColor.opacity(0.1) : Color.black.opacity(0.05), radius: isSelected ? 8 : 4, x: 0, y: 2)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Profile Details View
struct ProfileDetailsView: View {
    let user: User?
    @ObservedObject var viewModel: ProfileViewModel
    
    var body: some View {
        ScrollView {
            VStack(spacing: 16) {
                // Personal Info
                        ModernProfileCard(
                            title: "Kişisel Bilgiler",
                            icon: "person.fill",
                            iconColor: Color(hex: "8b5cf6")
                        ) {
                            VStack(spacing: 16) {
                                ProfileInfoRow(
                                    label: "Ad",
                                    value: user?.firstName ?? "",
                                    isEditing: viewModel.isEditing,
                                    text: Binding(
                                        get: { viewModel.user?.firstName ?? "" },
                                        set: { 
                                            if var user = viewModel.user {
                                                user.firstName = $0
                                                viewModel.user = user
                                            }
                                        }
                                    )
                                )
                                
                                Divider()
                                    .background(Color.gray.opacity(0.1))
                                
                                ProfileInfoRow(
                                    label: "Soyad",
                                    value: user?.lastName ?? "",
                                    isEditing: viewModel.isEditing,
                                    text: Binding(
                                        get: { viewModel.user?.lastName ?? "" },
                                        set: { 
                                            if var user = viewModel.user {
                                                user.lastName = $0
                                                viewModel.user = user
                                            }
                                        }
                                    )
                                )
                            }
                        }
                        
                        // Contact Info
                        ModernProfileCard(
                            title: "İletişim",
                            icon: "envelope.fill",
                            iconColor: Color(hex: "3b82f6")
                        ) {
                            VStack(spacing: 16) {
                                // Email - Değiştirilemez ve Onaylı
                                HStack(alignment: .top, spacing: 12) {
                                    VStack(alignment: .leading, spacing: 4) {
                                        HStack(spacing: 6) {
                                            Text("Email")
                                                .font(.system(size: 13, weight: .medium))
                                                .foregroundColor(.secondary)
                                            
                                            // Email onaylandı işareti
                                            HStack(spacing: 4) {
                                                Image(systemName: "checkmark.seal.fill")
                                                    .font(.system(size: 11, weight: .semibold))
                                                    .foregroundColor(Color(hex: "10b981"))
                                                Text("Onaylandı")
                                                    .font(.system(size: 11, weight: .medium))
                                                    .foregroundColor(Color(hex: "10b981"))
                                            }
                                            .padding(.horizontal, 6)
                                            .padding(.vertical, 2)
                                            .background(Color(hex: "10b981").opacity(0.1))
                                            .cornerRadius(6)
                                        }
                                        
                                        // Email her zaman salt okunur
                                        Text((user?.email ?? "").isEmpty ? "—" : (user?.email ?? ""))
                                            .font(.system(size: 16, weight: .regular))
                                            .foregroundColor((user?.email ?? "").isEmpty ? .secondary : .primary)
                                            .frame(maxWidth: .infinity, alignment: .leading)
                                            .padding(.vertical, 4)
                                    }
                                }
                                
                                if let phone = user?.phoneNumber {
                                    Divider()
                                        .background(Color.gray.opacity(0.1))
                                    
                                    ProfileInfoRow(
                                        label: "Telefon",
                                        value: phone,
                                        isEditing: viewModel.isEditing,
                                        text: Binding(
                                            get: { viewModel.user?.phoneNumber ?? "" },
                                            set: { newValue in
                                                // Telefon numarasını otomatik formatla
                                                let digits = newValue.replacingOccurrences(of: "[^0-9]", with: "", options: .regularExpression)
                                                
                                                // Maksimum 11 karakter (0 + 10 rakam)
                                                let limitedDigits = String(digits.prefix(11))
                                                
                                                // Formatlanmış numarayı göster (0 ile başlayan format)
                                                var formattedPhone = limitedDigits
                                                if limitedDigits.count == 10 && limitedDigits.hasPrefix("5") {
                                                    // 10 haneli ve 5 ile başlıyorsa, 0 ekle
                                                    formattedPhone = "0\(limitedDigits)"
                                                } else if limitedDigits.count == 11 && limitedDigits.hasPrefix("0") {
                                                    // 11 haneli ve 0 ile başlıyorsa, olduğu gibi bırak
                                                    formattedPhone = limitedDigits
                                                } else if limitedDigits.count > 0 && limitedDigits.count < 10 {
                                                    // Henüz tamamlanmamış numara
                                                    formattedPhone = limitedDigits
                                                } else if !limitedDigits.isEmpty {
                                                    formattedPhone = limitedDigits
                                                }
                                                
                                                if var user = viewModel.user {
                                                    user.phoneNumber = formattedPhone
                                                    viewModel.user = user
                                                }
                                            }
                                        ),
                                        keyboardType: .phonePad
                                    )
                                }
                            }
                        }
                        
                        // University Info
                        ModernProfileCard(
                            title: "Üniversite",
                            icon: "graduationcap.fill",
                            iconColor: Color(hex: "10b981")
                        ) {
                            VStack(spacing: 16) {
                                // Üniversite Picker
                                HStack(alignment: .top, spacing: 12) {
                                    VStack(alignment: .leading, spacing: 4) {
                                        Text("Üniversite")
                                            .font(.system(size: 13, weight: .medium))
                                            .foregroundColor(.secondary)
                                        
                                        // Üniversite değiştirilemez - her zaman sadece göster
                                        Text((user?.university ?? "").isEmpty ? "—" : (user?.university ?? ""))
                                            .font(.system(size: 16, weight: .regular))
                                            .foregroundColor((user?.university ?? "").isEmpty ? .secondary : .primary)
                                            .frame(maxWidth: .infinity, alignment: .leading)
                                            .padding(.vertical, 4)
                                    }
                                }
                                
                                Divider()
                                    .background(Color.gray.opacity(0.1))
                                
                                // Bölüm - Değiştirilemez
                                HStack(alignment: .top, spacing: 12) {
                                    VStack(alignment: .leading, spacing: 4) {
                                        Text("Bölüm")
                                            .font(.system(size: 13, weight: .medium))
                                            .foregroundColor(.secondary)
                                        
                                        // Bölüm değiştirilemez - her zaman sadece göster
                                        Text((user?.department ?? "").isEmpty ? "—" : (user?.department ?? ""))
                                            .font(.system(size: 16, weight: .regular))
                                            .foregroundColor((user?.department ?? "").isEmpty ? .secondary : .primary)
                                            .frame(maxWidth: .infinity, alignment: .leading)
                                            .padding(.vertical, 4)
                                    }
                                }
                                
                                if let studentNumber = user?.studentNumber {
                                    Divider()
                                        .background(Color.gray.opacity(0.1))
                                    
                                    ProfileInfoRow(
                                        label: "Öğrenci No",
                                        value: studentNumber,
                                        isEditing: viewModel.isEditing,
                                        text: Binding(
                                            get: { viewModel.user?.studentNumber ?? "" },
                                            set: { 
                                                if var user = viewModel.user {
                                                    user.studentNumber = $0
                                                    viewModel.user = user
                                                }
                                            }
                                        ),
                                        keyboardType: .numberPad
                                    )
                                }
                            }
                        }
            }
            .padding(.horizontal, 16)
            .padding(.top, 16)
            .padding(.bottom, 100)
        }
        .navigationTitle("Profil Detayları")
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.impactOccurred()
                    withAnimation(.spring(response: 0.3)) {
                        if viewModel.isEditing {
                            Task {
                                await viewModel.saveProfile()
                            }
                        } else {
                            viewModel.isEditing.toggle()
                        }
                    }
                }) {
                    Text(viewModel.isEditing ? "Kaydet" : "Düzenle")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(Color(hex: "8b5cf6"))
                }
                .buttonStyle(PlainButtonStyle())
            }
        }
        .alert("Hata", isPresented: Binding(
            get: { viewModel.errorMessage != nil },
            set: { if !$0 { viewModel.errorMessage = nil } }
        )) {
            Button("Tamam", role: .cancel) {
                viewModel.errorMessage = nil
            }
        } message: {
            if let error = viewModel.errorMessage {
                Text(error)
            }
        }
    }
}

// MARK: - Notification Toggle Row
struct NotificationToggleRow: View {
    let title: String
    @Binding var isOn: Bool
    let action: () -> Void
    @State private var debounceTask: Task<Void, Never>?
    
    var body: some View {
        HStack {
            Text(title)
                .font(.system(size: 15, weight: .medium))
                .foregroundColor(.primary)
            
            Spacer()
            
            Toggle("", isOn: $isOn)
                .labelsHidden()
                .tint(Color(hex: "6366f1"))
                .onChange(of: isOn) { newValue in
                    debounceTask?.cancel()
                    debounceTask = Task {
                        try? await Task.sleep(nanoseconds: 500_000_000)
                        if !Task.isCancelled {
                            await MainActor.run {
                                action()
                            }
                        }
                    }
                }
        }
        .padding(.vertical, 4)
    }
}

// MARK: - Settings View
struct SettingsView: View {
    let user: User?
    @ObservedObject var viewModel: ProfileViewModel
    @Binding var appearanceMode: String
    @EnvironmentObject var authViewModel: AuthViewModel
    @StateObject private var localizationManager = LocalizationManager.shared
    @State private var notificationPermissionStatus: UNAuthorizationStatus = .notDetermined
    @State private var showLanguagePicker = false
    @State private var showDeleteAccountConfirmation = false
    @State private var showLoginModal = false
    
    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                // Appearance Settings
                SettingsSectionCard(
                    title: "Görünüm",
                    icon: "paintbrush.fill",
                    iconColor: Color(hex: "8b5cf6")
                ) {
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text("Tema")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.primary)
                            Text("Uygulama görünümünü seçin")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        Spacer()
                        
                        Picker("", selection: $appearanceMode) {
                            Text("Otomatik").tag("system")
                            Text("Açık").tag("light")
                            Text("Koyu").tag("dark")
                        }
                        .pickerStyle(.menu)
                        .labelsHidden()
                        .onChange(of: appearanceMode) { newValue in
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                        }
                    }
                    .padding(.vertical, 4)
                }
                
                // Notification Permission
                SettingsSectionCard(
                    title: "Bildirimler",
                    icon: "bell.fill",
                    iconColor: Color(hex: "ec4899")
                ) {
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text("Bildirim İzni")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.primary)
                            Text(notificationStatusText)
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        Spacer()
                        
                        Button(action: {
                            if notificationPermissionStatus == .denied {
                                if let settingsUrl = URL(string: UIApplication.openSettingsURLString) {
                                    UIApplication.shared.open(settingsUrl)
                                }
                            } else {
                                requestNotificationPermission()
                            }
                        }) {
                            Text(notificationButtonText)
                                .font(.system(size: 14, weight: .semibold))
                                .foregroundColor(.white)
                                .padding(.horizontal, 16)
                                .padding(.vertical, 8)
                                .background(
                                    LinearGradient(
                                        colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                        startPoint: .leading,
                                        endPoint: .trailing
                                    )
                                )
                                .cornerRadius(8)
                        }
                        .disabled(notificationPermissionStatus == .authorized)
                    }
                    .padding(.vertical, 4)
                }
                
                // Notification Settings - Sadece giriş yapmış kullanıcılar için
                if user != nil {
                    SettingsSectionCard(
                        title: "Bildirim Tercihleri",
                        icon: "bell.badge.fill",
                        iconColor: Color(hex: "ec4899")
                    ) {
                        VStack(spacing: 0) {
                            NotificationToggleRow(
                                title: "Etkinlik Hatırlatıcıları",
                                isOn: Binding(
                                    get: { viewModel.user?.notificationSettings.eventReminders ?? false },
                                    set: { 
                                        if var user = viewModel.user {
                                            user.notificationSettings.eventReminders = $0
                                            viewModel.user = user
                                        }
                                    }
                                ),
                                action: {
                                    Task {
                                        await viewModel.updateNotificationSettings()
                                    }
                                }
                            )
                            
                            Divider()
                                .padding(.vertical, 8)
                            
                            NotificationToggleRow(
                                title: "Kampanya Güncellemeleri",
                                isOn: Binding(
                                    get: { viewModel.user?.notificationSettings.campaignUpdates ?? false },
                                    set: { 
                                        if var user = viewModel.user {
                                            user.notificationSettings.campaignUpdates = $0
                                            viewModel.user = user
                                        }
                                    }
                                ),
                                action: {
                                    Task {
                                        await viewModel.updateNotificationSettings()
                                    }
                                }
                            )
                            
                            Divider()
                                .padding(.vertical, 8)
                            
                            NotificationToggleRow(
                                title: "Topluluk Duyuruları",
                                isOn: Binding(
                                    get: { viewModel.user?.notificationSettings.communityAnnouncements ?? false },
                                    set: { 
                                        if var user = viewModel.user {
                                            user.notificationSettings.communityAnnouncements = $0
                                            viewModel.user = user
                                        }
                                    }
                                ),
                                action: {
                                    Task {
                                        await viewModel.updateNotificationSettings()
                                    }
                                }
                            )
                        }
                        .padding(.vertical, 4)
                    }
                }
                
                // Language Settings
                SettingsSectionCard(
                    title: "Dil",
                    icon: "globe",
                    iconColor: Color(hex: "10b981")
                ) {
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text("Uygulama Dili")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.primary)
                            Text(localizationManager.currentLanguage.displayName)
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        Spacer()
                        
                        Button(action: {
                            showLanguagePicker = true
                        }) {
                            Text("Değiştir")
                                .font(.system(size: 14, weight: .medium))
                                .foregroundColor(Color(hex: "6366f1"))
                        }
                    }
                    .padding(.vertical, 4)
                }
                
                // Action Buttons - Sadece giriş yapmış kullanıcılar için
                if user != nil {
                    VStack(spacing: 12) {
                        // Logout Button
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .medium)
                            generator.impactOccurred()
                            
                            Task {
                                await authViewModel.logout()
                                await MainActor.run {
                                    showLoginModal = true
                                }
                            }
                        }) {
                            HStack(spacing: 12) {
                                if authViewModel.isLoggingOut {
                                    ProgressView()
                                        .tint(.white)
                                } else {
                                    Image(systemName: "arrow.right.square.fill")
                                        .font(.system(size: 18, weight: .semibold))
                                }
                                Text(authViewModel.isLoggingOut ? "Çıkış yapılıyor..." : "Çıkış Yap")
                                    .font(.system(size: 16, weight: .semibold))
                            }
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .background(
                                LinearGradient(
                                    colors: authViewModel.isLoggingOut ? 
                                        [Color(hex: "ef4444").opacity(0.7), Color(hex: "dc2626").opacity(0.7)] :
                                        [Color(hex: "ef4444"), Color(hex: "dc2626")],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(12)
                        }
                        .buttonStyle(PlainButtonStyle())
                        .disabled(authViewModel.isLoggingOut)
                        
                        // Delete Account Button
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .medium)
                            generator.impactOccurred()
                            showDeleteAccountConfirmation = true
                        }) {
                            HStack(spacing: 12) {
                                Image(systemName: "trash.fill")
                                    .font(.system(size: 18, weight: .semibold))
                                Text("Hesabı Sil")
                                    .font(.system(size: 16, weight: .semibold))
                            }
                            .foregroundColor(Color(hex: "ef4444"))
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .background(
                                Color(hex: "ef4444").opacity(0.1)
                            )
                            .cornerRadius(12)
                            .overlay(
                                RoundedRectangle(cornerRadius: 12)
                                    .stroke(Color(hex: "ef4444").opacity(0.3), lineWidth: 1)
                            )
                        }
                        .buttonStyle(PlainButtonStyle())
                    }
                    .padding(.top, 8)
                }
            }
            .padding(.horizontal, 20)
            .padding(.top, 20)
            .padding(.bottom, 40)
        }
        .navigationTitle("Ayarlar")
        .navigationBarTitleDisplayMode(.large)
        .sheet(isPresented: $showLanguagePicker) {
            LanguagePickerView(
                currentLanguage: $localizationManager.currentLanguage,
                isPresented: $showLanguagePicker
            )
        }
        .alert("Hesabı Sil", isPresented: $showDeleteAccountConfirmation) {
            Button("İptal", role: .cancel) {
                // Haptic feedback - iptal
                let generator = UIImpactFeedbackGenerator(style: .light)
                generator.impactOccurred()
            }
            Button("Sil", role: .destructive) {
                // Haptic feedback - silme onayı
                let generator = UIImpactFeedbackGenerator(style: .heavy)
                generator.prepare()
                generator.impactOccurred()
                
                Task {
                    // Hesabı sil ve logout yap
                    // TODO: Delete account API call eklenecek
                    // Şimdilik sadece logout yapıyoruz
                    await authViewModel.logout()
                    // Logout sonrası navigation'ı sıfırla ve login modal'ı aç
                    await MainActor.run {
                        // SettingsView içinde olduğumuz için navigationPath'e erişemiyoruz
                        // Ama ProfileView'deki onChange ile handle edilecek
                        showLoginModal = true
                    }
                }
            }
        } message: {
            Text("Hesabınızı ve tüm verilerinizi kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.")
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .onAppear {
            checkNotificationPermission()
        }
    }
    
    private var notificationStatusText: String {
        switch notificationPermissionStatus {
        case .authorized:
            return "Bildirimler açık"
        case .denied:
            return "Bildirimler kapalı - Ayarlardan açabilirsiniz"
        case .notDetermined:
            return "Bildirim izni verilmedi"
        case .provisional:
            return "Geçici bildirim izni"
        case .ephemeral:
            return "Geçici bildirim izni"
        @unknown default:
            return "Bilinmeyen durum"
        }
    }
    
    private var notificationButtonText: String {
        switch notificationPermissionStatus {
        case .authorized:
            return "Açık"
        case .denied:
            return "Ayarlar"
        default:
            return "İzin Ver"
        }
    }
    
    private func checkNotificationPermission() {
        UNUserNotificationCenter.current().getNotificationSettings { settings in
            // Capture only the Sendable authorizationStatus enum value
            let authorizationStatus = settings.authorizationStatus
            DispatchQueue.main.async {
                notificationPermissionStatus = authorizationStatus
            }
        }
    }
    
    private func requestNotificationPermission() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
            DispatchQueue.main.async {
                if granted {
                    notificationPermissionStatus = .authorized
                    UIApplication.shared.registerForRemoteNotifications()
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.success)
                } else {
                    notificationPermissionStatus = .denied
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.warning)
                }
                if let error = error {
                    print("⚠️ Bildirim izni hatası: \(error.localizedDescription)")
                }
            }
        }
    }
}

// MARK: - Profile Hero Section
struct ProfileHeroSection: View {
    let firstName: String
    let lastName: String
    let email: String
    let profileImageURL: String?
    
    var displayName: String {
        if !firstName.isEmpty && !lastName.isEmpty {
            return "\(firstName) \(lastName)"
        } else if !firstName.isEmpty {
            return firstName
        } else if !lastName.isEmpty {
            return lastName
        } else {
            return "Kullanıcı"
        }
    }
    
    var initials: String {
        let first = firstName.prefix(1).uppercased()
        let last = lastName.prefix(1).uppercased()
        if !first.isEmpty && !last.isEmpty {
            return "\(first)\(last)"
        } else if !first.isEmpty {
            return first
        } else {
            return "K"
        }
    }
    
    var body: some View {
        ZStack(alignment: .bottom) {
            Color(UIColor.secondarySystemBackground)
                .frame(height: 280)
            
            VStack(spacing: 20) {
                ZStack(alignment: .bottomTrailing) {
                    if let imageURL = profileImageURL, !imageURL.isEmpty {
                        let fullImageURL = APIService.fullImageURL(from: imageURL) ?? imageURL
                        AsyncImage(url: URL(string: fullImageURL)) { image in
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                        } placeholder: {
                            ZStack {
                                Circle()
                                    .fill(
                                        LinearGradient(
                                            gradient: Gradient(colors: [
                                                Color(hex: "8b5cf6"),
                                                Color(hex: "6366f1")
                                            ]),
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                    )
                                    .frame(width: 120, height: 120)
                                Text(initials)
                                    .font(.system(size: 48, weight: .bold))
                                    .foregroundColor(.white)
                            }
                        }
                        .frame(width: 120, height: 120)
                        .clipShape(Circle())
                        .overlay(
                            Circle()
                                .stroke(
                                    LinearGradient(
                                        gradient: Gradient(colors: [
                                            Color.white.opacity(0.6),
                                            Color.white.opacity(0.3)
                                        ]),
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    ),
                                    lineWidth: 4
                                )
                        )
                        .shadow(color: Color(hex: "8b5cf6").opacity(0.3), radius: 20, x: 0, y: 10)
                    } else {
                        ZStack {
                            Circle()
                                .fill(
                                    LinearGradient(
                                        gradient: Gradient(colors: [
                                            Color(hex: "8b5cf6"),
                                            Color(hex: "6366f1")
                                        ]),
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    )
                                )
                                .frame(width: 120, height: 120)
                            Text(initials)
                                .font(.system(size: 48, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .overlay(
                            Circle()
                                .stroke(
                                    LinearGradient(
                                        gradient: Gradient(colors: [
                                            Color.white.opacity(0.6),
                                            Color.white.opacity(0.3)
                                        ]),
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    ),
                                    lineWidth: 4
                                )
                        )
                        .shadow(color: Color(hex: "8b5cf6").opacity(0.3), radius: 20, x: 0, y: 10)
                    }
                    
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.impactOccurred()
                    }) {
                        ZStack {
                            Circle()
                                .fill(Color(UIColor.systemBackground))
                                .frame(width: 40, height: 40)
                            Image(systemName: "camera.fill")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(Color(hex: "8b5cf6"))
                        }
                        .shadow(color: .black.opacity(0.15), radius: 8, x: 0, y: 4)
                    }
                    .buttonStyle(PlainButtonStyle())
                    .offset(x: 8, y: 8)
                }
                
                Text(displayName)
                    .font(.system(size: 32, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
                    .multilineTextAlignment(.center)
                
                if !email.isEmpty {
                    Text(email)
                        .font(.system(size: 16, weight: .regular))
                        .foregroundColor(.secondary)
                }
            }
            .padding(.vertical, 32)
            .padding(.bottom, 24)
        }
    }
}

// MARK: - Settings Section Card
struct SettingsSectionCard<Content: View>: View {
    let title: String
    let icon: String
    let iconColor: Color
    let content: Content
    
    init(title: String, icon: String, iconColor: Color, @ViewBuilder content: () -> Content) {
        self.title = title
        self.icon = icon
        self.iconColor = iconColor
        self.content = content()
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(iconColor)
                    .frame(width: 24)
                
                Text(title)
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(.primary)
            }
            
            content
        }
        .padding(18)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(Color(UIColor.secondarySystemBackground))
        )
    }
}

// MARK: - Modern Profile Card (Legacy - kept for compatibility)
struct ModernProfileCard<Content: View>: View {
    let title: String
    let icon: String
    let iconColor: Color
    let content: Content
    
    init(title: String, icon: String, iconColor: Color, @ViewBuilder content: () -> Content) {
        self.title = title
        self.icon = icon
        self.iconColor = iconColor
        self.content = content()
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 12) {
                ZStack {
                    Circle()
                        .fill(iconColor.opacity(0.15))
                        .frame(width: 40, height: 40)
                    
                    Image(systemName: icon)
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(iconColor)
                }
                
                Text(title)
                    .font(.system(size: 18, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
            }
            
            content
        }
        .padding(20)
        .background(Color(UIColor.secondarySystemBackground))
        .clipShape(RoundedRectangle(cornerRadius: 16))
        .shadow(color: Color.black.opacity(0.06), radius: 10, x: 0, y: 4)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(Color.gray.opacity(0.08), lineWidth: 0.5)
        )
    }
}

// MARK: - Profile Info Row
struct ProfileInfoRow: View {
    let label: String
    let value: String
    let isEditing: Bool
    @Binding var text: String
    var keyboardType: UIKeyboardType = .default
    
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                Text(label)
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(.secondary)
                
                if isEditing {
                    TextField("", text: $text)
                        .keyboardType(keyboardType)
                        .textFieldStyle(.plain)
                        .font(.system(size: 16, weight: .regular))
                        .padding(12)
                        .background(Color(UIColor.tertiarySystemBackground))
                        .cornerRadius(10)
                        .overlay(
                            RoundedRectangle(cornerRadius: 10)
                                .stroke(Color.gray.opacity(0.2), lineWidth: 1)
                        )
                } else {
                    Text(value.isEmpty ? "—" : value)
                        .font(.system(size: 16, weight: .regular))
                        .foregroundColor(value.isEmpty ? .secondary : .primary)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(.vertical, 4)
                }
            }
        }
    }
}

// MARK: - About View
struct AboutView: View {
    let user: User?
    @State private var showShareSheet = false
    
    var appVersion: String {
        if let version = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String {
            if let build = Bundle.main.infoDictionary?["CFBundleVersion"] as? String {
                return "\(version) (\(build))"
            }
            return version
        }
        return "1.0.0"
    }
    
    var body: some View {
        ScrollView {
            VStack(spacing: 24) {
                // Hero Section - Simple and Clean
                VStack(spacing: 16) {
                    Image("LogoHeader")
                        .resizable()
                        .aspectRatio(contentMode: .fit)
                        .frame(width: 80, height: 80)
                    
                    VStack(spacing: 8) {
                        Text("Four Kampüs")
                            .font(.system(size: 28, weight: .bold))
                            .foregroundColor(.primary)
                        
                        Text("Üniversite Toplulukları Platformu")
                            .font(.system(size: 15, weight: .regular))
                            .foregroundColor(.secondary)
                        
                        Text("v\(appVersion)")
                            .font(.system(size: 13, weight: .medium))
                            .foregroundColor(.secondary)
                            .padding(.top, 4)
                    }
                }
                .padding(.top, 20)
                .padding(.bottom, 8)
                
                // About Section
                SettingsSectionCard(
                    title: "Hakkımızda",
                    icon: "info.circle.fill",
                    iconColor: Color(hex: "6366f1")
                ) {
                    Text("Four Kampüs, üniversite öğrencilerinin topluluklarını keşfetmesi, etkinliklere katılması, kampanyalardan haberdar olması ve ürün satın alması için tasarlanmış kapsamlı bir platformdur.")
                        .font(.system(size: 15, weight: .regular))
                        .foregroundColor(.primary)
                        .lineSpacing(4)
                        .padding(.vertical, 4)
                }
                
                // App Info Section
                SettingsSectionCard(
                    title: "Uygulama Bilgileri",
                    icon: "app.badge.fill",
                    iconColor: Color(hex: "6366f1")
                ) {
                    VStack(spacing: 0) {
                        AboutInfoRow(title: "Versiyon", value: appVersion)
                        Divider().padding(.vertical, 12)
                        AboutInfoRow(title: "Geliştirici", value: "Four Software")
                        Divider().padding(.vertical, 12)
                        AboutInfoRow(title: "Platform", value: "iOS")
                        Divider().padding(.vertical, 12)
                        AboutInfoRow(title: "Yıl", value: "2025")
                    }
                    .padding(.vertical, 4)
                }
                
                // Contact Section
                SettingsSectionCard(
                    title: "İletişim",
                    icon: "envelope.fill",
                    iconColor: Color(hex: "6366f1")
                ) {
                    VStack(spacing: 0) {
                        LinkRow(
                            icon: "globe",
                            title: "Web Sitesi",
                            value: "foursoftware.com.tr",
                            url: "https://foursoftware.com.tr",
                            color: Color(hex: "6366f1")
                        )
                        Divider().padding(.vertical, 12)
                        LinkRow(
                            icon: "envelope.fill",
                            title: "E-posta",
                            value: "info@foursoftware.com.tr",
                            url: "mailto:info@foursoftware.com.tr",
                            color: Color(hex: "6366f1")
                        )
                    }
                    .padding(.vertical, 4)
                }
                
                // Share Button
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .medium)
                    generator.impactOccurred()
                    showShareSheet = true
                }) {
                    HStack {
                        Image(systemName: "square.and.arrow.up")
                            .font(.system(size: 16, weight: .semibold))
                        Text("Uygulamayı Paylaş")
                            .font(.system(size: 16, weight: .semibold))
                    }
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 16)
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(12)
                }
                .buttonStyle(PlainButtonStyle())
                
                // Footer
                VStack(spacing: 8) {
                    HStack(spacing: 4) {
                        Text("© 2025")
                            .font(.system(size: 13, weight: .medium))
                        Text("Four Kampüs")
                            .font(.system(size: 13, weight: .bold))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    .foregroundColor(.secondary)
                    
                    Text("Four Software tarafından geliştirilmiştir")
                        .font(.system(size: 12, weight: .regular))
                        .foregroundColor(.secondary.opacity(0.7))
                }
                .padding(.top, 8)
                .padding(.bottom, 40)
            }
            .padding(.horizontal, 20)
            .padding(.top, 20)
        }
        .navigationTitle("Hakkında")
        .navigationBarTitleDisplayMode(.large)
        .sheet(isPresented: $showShareSheet) {
            ShareManager.shareUrl(
                url: "foursoftware.com.tr",
                title: "Four Kampüs - Üniversite Toplulukları Platformu",
                description: "Üniversite topluluklarını keşfet, etkinliklere katıl, kampanyalardan haberdar ol!"
            )
        }
    }
}

// MARK: - About Section Card
struct AboutSectionCard<Content: View>: View {
    let title: String
    let icon: String
    let iconColor: Color
    let content: Content
    
    init(title: String, icon: String, iconColor: Color, @ViewBuilder content: () -> Content) {
        self.title = title
        self.icon = icon
        self.iconColor = iconColor
        self.content = content()
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(iconColor)
                    .frame(width: 32, height: 32)
                
                Text(title)
                    .font(.system(size: 20, weight: .bold))
                    .foregroundColor(.primary)
                
                Spacer()
            }
            
            content
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
        )
        .padding(.horizontal, 20)
    }
}

// MARK: - Feature Item (Legacy - kept for compatibility)
struct FeatureItem: View {
    let icon: String
    let title: String
    let color: Color
    
    var body: some View {
        VStack(spacing: 8) {
            Image(systemName: icon)
                .font(.system(size: 24, weight: .semibold))
                .foregroundColor(color)
                .frame(height: 32)
            
            Text(title)
                .font(.system(size: 13, weight: .medium))
                .foregroundColor(.primary)
                .multilineTextAlignment(.center)
                .lineLimit(2)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 16)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(color.opacity(0.1))
        )
    }
}

// MARK: - About Info Row
struct AboutInfoRow: View {
    let title: String
    let value: String
    
    var body: some View {
        HStack {
            Text(title)
                .font(.system(size: 15, weight: .regular))
                .foregroundColor(.secondary)
            
            Spacer()
            
            Text(value)
                .font(.system(size: 15, weight: .semibold))
                .foregroundColor(.primary)
        }
    }
}

// MARK: - Link Row
struct LinkRow: View {
    let icon: String
    let title: String
    let value: String
    let url: String
    let color: Color
    
    var body: some View {
        Button(action: {
            if let url = URL(string: url) {
                UIApplication.shared.open(url)
            }
        }) {
            HStack {
                Image(systemName: icon)
                    .font(.system(size: 16, weight: .medium))
                    .foregroundColor(color)
                    .frame(width: 24)
                
                Text(title)
                    .font(.system(size: 15, weight: .regular))
                    .foregroundColor(.secondary)
                
                Spacer()
                
                Text(value)
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundColor(.primary)
                    .lineLimit(1)
                
                Image(systemName: "chevron.right")
                    .font(.system(size: 12, weight: .semibold))
                    .foregroundColor(.secondary.opacity(0.5))
            }
        }
        .buttonStyle(PlainButtonStyle())
        .padding(.vertical, 4)
    }
}

// MARK: - Modern Feature Card
struct ModernFeatureCard: View {
    let icon: String
    let title: String
    let description: String
    let color: Color
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            ZStack {
                RoundedRectangle(cornerRadius: 12)
                    .fill(color.opacity(0.15))
                    .frame(width: 50, height: 50)
                
                Image(systemName: icon)
                    .font(.system(size: 22, weight: .semibold))
                    .foregroundColor(color)
            }
            
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.system(size: 15, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
                    .lineLimit(2)
                
                Text(description)
                    .font(.system(size: 12, weight: .regular))
                    .foregroundColor(.secondary)
                    .lineLimit(2)
            }
            
            Spacer()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .frame(height: 140)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.tertiarySystemBackground))
                .overlay(
                    RoundedRectangle(cornerRadius: 16)
                        .stroke(color.opacity(0.2), lineWidth: 1)
                )
        )
    }
}

// MARK: - Modern Info Row
struct ModernInfoRow: View {
    let icon: String
    let title: String
    let value: String
    let iconColor: Color
    
    var body: some View {
        HStack(spacing: 16) {
            ZStack {
                Circle()
                    .fill(iconColor.opacity(0.15))
                    .frame(width: 40, height: 40)
                
                Image(systemName: icon)
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(iconColor)
            }
            
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(.secondary)
                
                Text(value)
                    .font(.system(size: 16, weight: .semibold, design: .rounded))
                    .foregroundColor(.primary)
            }
            
            Spacer()
        }
    }
}

// MARK: - Feature Row (Legacy - kept for compatibility)
struct FeatureRow: View {
    let icon: String
    let text: String
    let description: String?
    
    init(icon: String, text: String, description: String? = nil) {
        self.icon = icon
        self.text = text
        self.description = description
    }
    
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: icon)
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(Color(hex: "6366f1"))
                .frame(width: 28)
                .padding(.top, 2)
            
            VStack(alignment: .leading, spacing: 4) {
                Text(text)
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundColor(.primary)
                
                if let description = description {
                    Text(description)
                        .font(.system(size: 13, weight: .regular))
                        .foregroundColor(.secondary)
                        .lineSpacing(2)
                }
            }
            
            Spacer()
        }
    }
}

// MARK: - Language Picker View
struct LanguagePickerView: View {
    @Binding var currentLanguage: LocalizationManager.Language
    @Binding var isPresented: Bool
    
    var body: some View {
        NavigationStack {
            List {
                ForEach(LocalizationManager.Language.allCases, id: \.self) { language in
                    Button(action: {
                        LocalizationManager.shared.setLanguage(language)
                        currentLanguage = language
                        isPresented = false
                    }) {
                        HStack {
                            Text(language.displayName)
                                .foregroundColor(.primary)
                            Spacer()
                            if currentLanguage == language {
                                Image(systemName: "checkmark")
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                    }
                }
            }
            .navigationTitle("Dil Seçimi")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        isPresented = false
                    }
                }
            }
        }
    }
}

// MARK: - Support View
struct SupportView: View {
    @State private var showShareSheet = false
    
    var body: some View {
        ScrollView {
            VStack(spacing: 24) {
                // Header
                VStack(spacing: 12) {
                    ZStack {
                        Circle()
                            .fill(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                            .frame(width: 80, height: 80)
                        
                        Image(systemName: "lifepreserver.fill")
                            .font(.system(size: 36, weight: .semibold))
                            .foregroundColor(.white)
                    }
                    .padding(.top, 20)
                    
                    Text("Destek")
                        .font(.system(size: 28, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                    
                    Text("Size nasıl yardımcı olabiliriz?")
                        .font(.system(size: 15, weight: .regular))
                        .foregroundColor(.secondary)
                }
                .padding(.bottom, 8)
                
                // Support Options
                VStack(spacing: 12) {
                    SupportOptionCard(
                        icon: "envelope.fill",
                        title: "E-posta Desteği",
                        description: "Sorularınız için bize e-posta gönderin",
                        email: "destek@foursoftware.com.tr",
                        color: Color(hex: "6366f1")
                    )
                    
                    SupportOptionCard(
                        icon: "phone.fill",
                        title: "Telefon Desteği",
                        description: "Bizi arayın, size yardımcı olalım",
                        phone: "+90 850 302 25 68",
                        color: Color(hex: "10b981")
                    )
                    
                    SupportOptionCard(
                        icon: "globe",
                        title: "Web Sitemiz",
                        description: "Daha fazla bilgi için web sitemizi ziyaret edin",
                        website: "https://foursoftware.com.tr",
                        color: Color(hex: "f59e0b")
                    )
                }
                .padding(.horizontal, 20)
                
                // FAQ Section
                VStack(alignment: .leading, spacing: 16) {
                    Text("Sık Sorulan Sorular")
                        .font(.system(size: 20, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                        .padding(.horizontal, 20)
                    
                    VStack(spacing: 12) {
                        FAQCard(
                            question: "Four Kampüs nedir?",
                            answer: "Four Kampüs, üniversite öğrencilerinin toplulukları keşfetmesini, etkinliklere katılmasını ve kampanyalardan haberdar olmasını sağlayan kapsamlı bir platformdur."
                        )
                        
                        FAQCard(
                            question: "Nasıl üye olabilirim?",
                            answer: "Uygulamayı indirdikten sonra üniversite e-posta adresinizle kayıt olabilirsiniz. E-posta doğrulaması sonrası tüm özelliklere erişebilirsiniz."
                        )
                        
                        FAQCard(
                            question: "Üyelik ücretsiz mi?",
                            answer: "Evet! Four Kampüs tamamen ücretsizdir. Tüm özelliklere herhangi bir ücret ödemeden erişebilirsiniz."
                        )
                        
                        FAQCard(
                            question: "Topluluk nasıl oluşturabilirim?",
                            answer: "Topluluk oluşturmak için lütfen destek ekibimizle iletişime geçin. Size yardımcı olmaktan mutluluk duyarız."
                        )
                    }
                    .padding(.horizontal, 20)
                }
                .padding(.top, 8)
                
                // Share App Button
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.impactOccurred()
                    showShareSheet = true
                }) {
                    HStack(spacing: 12) {
                        Image(systemName: "square.and.arrow.up")
                            .font(.system(size: 16, weight: .medium))
                        
                        Text("Uygulamayı Paylaş")
                            .font(.system(size: 16, weight: .semibold))
                        
                        Spacer()
                    }
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 16)
                    .padding(.horizontal, 20)
                    .background(
                        RoundedRectangle(cornerRadius: 14)
                            .fill(Color(hex: "6366f1"))
                    )
                }
                .padding(.horizontal, 20)
                .padding(.top, 12)
                .padding(.bottom, 40)
            }
        }
        .navigationTitle("Destek")
        .navigationBarTitleDisplayMode(.large)
        .sheet(isPresented: $showShareSheet) {
            ShareManager.shareUrl(
                url: "foursoftware.com.tr",
                title: "Four Kampüs - Üniversite Toplulukları Platformu",
                description: "Üniversite topluluklarını keşfet, etkinliklere katıl, kampanyalardan haberdar ol!"
            )
        }
    }
}

// MARK: - Support Option Card
struct SupportOptionCard: View {
    let icon: String
    let title: String
    let description: String
    var email: String?
    var phone: String?
    var website: String?
    let color: Color
    
    var body: some View {
        Button(action: {
            handleAction()
        }) {
            HStack(spacing: 16) {
                ZStack {
                    Circle()
                        .fill(color.opacity(0.15))
                        .frame(width: 50, height: 50)
                    
                    Image(systemName: icon)
                        .font(.system(size: 20, weight: .semibold))
                        .foregroundColor(color)
                }
                
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    
                    Text(description)
                        .font(.system(size: 13, weight: .regular))
                        .foregroundColor(.secondary)
                        .lineLimit(2)
                    
                    if let email = email {
                        Text(email)
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(color)
                            .padding(.top, 2)
                    } else if let phone = phone {
                        Text(phone)
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(color)
                            .padding(.top, 2)
                    } else if let website = website {
                        Text(website.replacingOccurrences(of: "https://", with: ""))
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(color)
                            .padding(.top, 2)
                    }
                }
                
                Spacer()
                
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.secondary)
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 16)
                    .fill(Color(UIColor.secondarySystemBackground))
            )
        }
        .buttonStyle(PlainButtonStyle())
    }
    
    private func handleAction() {
        let generator = UIImpactFeedbackGenerator(style: .light)
        generator.impactOccurred()
        
        if let email = email {
            if let url = URL(string: "mailto:\(email)") {
                UIApplication.shared.open(url)
            }
        } else if let phone = phone {
            let cleanPhone = phone.replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "+", with: "")
            if let url = URL(string: "tel://\(cleanPhone)") {
                UIApplication.shared.open(url)
            }
        } else if let website = website, let url = URL(string: website) {
            UIApplication.shared.open(url)
        }
    }
}

// MARK: - FAQ Card
struct FAQCard: View {
    let question: String
    let answer: String
    @State private var isExpanded = false
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Button(action: {
                withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                    isExpanded.toggle()
                }
                let generator = UIImpactFeedbackGenerator(style: .light)
                generator.impactOccurred()
            }) {
                HStack {
                    Text(question)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(.primary)
                        .multilineTextAlignment(.leading)
                    
                    Spacer()
                    
                    Image(systemName: "chevron.right")
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundColor(.secondary)
                        .rotationEffect(.degrees(isExpanded ? 90 : 0))
                }
            }
            .buttonStyle(PlainButtonStyle())
            
            if isExpanded {
                Text(answer)
                    .font(.system(size: 14, weight: .regular))
                    .foregroundColor(.secondary)
                    .lineSpacing(4)
                    .transition(.opacity.combined(with: .move(edge: .top)))
            }
        }
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 14)
                .fill(Color(UIColor.secondarySystemBackground))
        )
    }
}

// MARK: - Modern Profile Header View
struct ModernProfileHeaderView: View {
    let firstName: String
    let lastName: String
    let email: String
    let profileImageURL: String?
    
    var displayName: String {
        if !firstName.isEmpty && !lastName.isEmpty {
            return "\(firstName) \(lastName)"
        } else if !firstName.isEmpty {
            return firstName
        } else if !lastName.isEmpty {
            return lastName
        } else {
            return "Kullanıcı"
        }
    }
    
    var initials: String {
        let first = firstName.prefix(1).uppercased()
        let last = lastName.prefix(1).uppercased()
        if !first.isEmpty && !last.isEmpty {
            return "\(first)\(last)"
        } else if !first.isEmpty {
            return first
        } else {
            return "K"
        }
    }
    
    var body: some View {
        VStack(spacing: 16) {
            // Profile Photo with modern glow
            ZStack {
                // Glow effect
                Circle()
                    .fill(
                        LinearGradient(
                            colors: [Color(hex: "6366f1").opacity(0.3), Color(hex: "8b5cf6").opacity(0.2)],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .frame(width: 110, height: 110)
                    .blur(radius: 20)
                
                // Profile photo
                if let imageURL = profileImageURL, !imageURL.isEmpty {
                    let fullImageURL = APIService.fullImageURL(from: imageURL) ?? imageURL
                    AsyncImage(url: URL(string: fullImageURL)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                                .frame(width: 100, height: 100)
                                .clipShape(Circle())
                                .overlay(
                                    Circle()
                                        .stroke(
                                            LinearGradient(
                                                colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                                startPoint: .topLeading,
                                                endPoint: .bottomTrailing
                                            ),
                                            lineWidth: 3
                                        )
                                )
                        case .failure(_):
                            initialsView
                        case .empty:
                            ProgressView()
                                .frame(width: 100, height: 100)
                        @unknown default:
                            initialsView
                        }
                    }
                } else {
                    initialsView
                }
            }
            
            // User info
            VStack(spacing: 6) {
                Text(displayName)
                    .font(.system(size: 24, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
                
                Text(email)
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(.secondary)
            }
        }
    }
    
    private var initialsView: some View {
        ZStack {
            Circle()
                .fill(
                    LinearGradient(
                        colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .frame(width: 100, height: 100)
            
            Text(initials)
                .font(.system(size: 36, weight: .bold, design: .rounded))
                .foregroundColor(.white)
        }
        .overlay(
            Circle()
                .stroke(
                    LinearGradient(
                        colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 3
                )
        )
    }
}

// MARK: - Modern Profile Action Button
struct ModernProfileActionButton: View {
    let title: String
    let subtitle: String
    let icon: String
    let iconColor: Color
    let action: () -> Void
    
    var body: some View {
        Button(action: {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            action()
        }) {
            HStack(spacing: 16) {
                // Icon with modern background
                ZStack {
                    RoundedRectangle(cornerRadius: 12)
                        .fill(iconColor.opacity(0.15))
                        .frame(width: 50, height: 50)
                    
                    Image(systemName: icon)
                        .font(.system(size: 20, weight: .semibold))
                        .foregroundColor(iconColor)
                }
                
                // Titles
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    
                    Text(subtitle)
                        .font(.system(size: 13, weight: .regular))
                        .foregroundColor(.secondary)
                        .lineLimit(1)
                }
                
                Spacer()
                
                // Chevron
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.secondary.opacity(0.5))
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 16)
                    .fill(Color(UIColor.secondarySystemBackground))
            )
            .overlay(
                RoundedRectangle(cornerRadius: 16)
                    .stroke(iconColor.opacity(0.1), lineWidth: 1)
            )
        }
        .buttonStyle(PlainButtonStyle())
        .padding(.horizontal, 4)
    }
}
