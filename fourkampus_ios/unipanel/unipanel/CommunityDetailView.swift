//
//  CommunityDetailView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import Combine

struct CommunityDetailView: View {
    let community: Community
    let verificationInfo: VerifiedCommunityInfo?
    @StateObject private var viewModel: CommunityDetailViewModel
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var cartViewModel: CartViewModel
    @State private var selectedTab = 0
    @State private var isFavorite = false
    @State private var selectedEvent: Event?
    @State private var selectedCampaign: Campaign?
    @State private var showLoginModal = false
    @State private var membershipStatus: MembershipStatus?
    @State private var isLoadingMembershipStatus = false
    @State private var showJoinSheet = false
    @State private var showQRCode = false
    @State private var showUniversityWarning = false
    @State private var pendingJoinAction: (() -> Void)?
    @State private var showLeaveConfirmation = false
    @State private var isLeavingCommunity = false
    @State private var showLeaveSuccess = false
    @State private var leaveErrorMessage: String?
    
    private var hasVerificationBadge: Bool {
        (verificationInfo != nil) || community.isVerified
    }
    
    init(community: Community, verificationInfo: VerifiedCommunityInfo? = nil) {
        self.community = community
        self.verificationInfo = verificationInfo
        _viewModel = StateObject(wrappedValue: CommunityDetailViewModel(communityId: community.id))
    }
    
    var body: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            // ScrollView kullan (tab içerikleri kendi scroll'larını yönetir)
            ScrollView {
                VStack(spacing: 0) {
                // Hero Section
                CommunityDetailHero(
                    community: community,
                    verificationInfo: verificationInfo
                )
                    .padding(.bottom, 24)
                
                if hasVerificationBadge {
                    VerifiedStatusCard(
                        communityName: community.name,
                        verificationInfo: verificationInfo
                    )
                    .padding(.horizontal, 16)
                    .padding(.bottom, 16)
                }
                
                // Membership Status & Join Button
                if authViewModel.isAuthenticated {
                    MembershipStatusCard(
                        community: community,
                        membershipStatus: membershipStatus,
                        isLoading: isLoadingMembershipStatus,
                        onJoin: {
                            // Üyelik durumu kontrolü: Eğer üyeyse buton gösterilmemeli
                            if let status = membershipStatus {
                                if status.isMember || status.status == "member" || status.status == "approved" {
                                    // Üye ise buton gösterilmemeli
                                    #if DEBUG
                                    print("⚠️ Kullanıcı zaten üye, katılma butonu gösterilmiyor")
                                    #endif
                                    return
                                }
                            }
                            
                            // Üniversite kontrolü
                            checkUniversityAndShowJoinSheet()
                        }
                    )
                    .padding(.horizontal, 16)
                    .padding(.bottom, 24)
                } else {
                    // Giriş yapılmamışsa katılma butonu göster
                    Button(action: {
                        showLoginModal = true
                    }) {
                        HStack(spacing: 12) {
                            Image(systemName: "person.badge.plus.fill")
                                .font(.system(size: 20))
                                .foregroundColor(.white)
                            Text("Topluluğa Katıl")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.white)
                            Spacer()
                            Image(systemName: "arrow.right")
                                .font(.system(size: 14, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .padding()
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                        .shadow(color: Color(hex: "6366f1").opacity(0.3), radius: 8, x: 0, y: 4)
                    }
                    .buttonStyle(PlainButtonStyle())
                    .padding(.horizontal, 16)
                    .padding(.bottom, 24)
                }
                
                // Tab Bar - Tüm sekmeler görünür, login kontrolü içerikte yapılır
                Picker("", selection: $selectedTab) {
                    Text("Genel Bakış").tag(0)
                    Text("Etkinlikler").tag(1)
                    Text("Kampanyalar").tag(2)
                    Text("Ürünler").tag(3)
                    Text("Üyeler").tag(4)
                    Text("Yönetim").tag(5)
                }
                .pickerStyle(.segmented)
                .padding(.horizontal, 16)
                .padding(.bottom, 24)
                
                // Content - Sadece aktif sekme render edilir (lazy loading için)
                tabContent
                .padding(.horizontal, 16)
                .padding(.bottom, 100)
                .frame(minHeight: 300)
                .allowsHitTesting(selectedEvent == nil && selectedCampaign == nil)
                }
            }
            .refreshable {
                switch selectedTab {
                case 1: await viewModel.refreshEvents()
                case 2: await viewModel.refreshCampaigns()
                case 3: await viewModel.refreshProducts()
                case 4: await viewModel.refreshMembers()
                case 5: await viewModel.refreshBoard()
                default: break
                }
            }
            
            // Success Toast - Topluluktan ayrılma başarılı
            if showLeaveSuccess {
                VStack {
                    HStack(spacing: 12) {
                        Image(systemName: "checkmark.circle.fill")
                            .font(.system(size: 20, weight: .semibold))
                            .foregroundColor(.white)
                        Text("Topluluktan başarıyla ayrıldınız")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.white)
                        Spacer()
                    }
                    .padding()
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "10b981"), Color(hex: "059669")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(16)
                    .shadow(color: Color(hex: "10b981").opacity(0.4), radius: 12, x: 0, y: 6)
                    .padding(.horizontal, 16)
                    .padding(.top, 16)
                    Spacer()
                }
                .transition(.move(edge: .top).combined(with: .opacity))
                .animation(.spring(response: 0.4, dampingFraction: 0.8), value: showLeaveSuccess)
            }
            
            // Error Toast - Topluluktan ayrılma hatası
            if let errorMessage = leaveErrorMessage {
                VStack {
                    HStack(spacing: 12) {
                        Image(systemName: "exclamationmark.triangle.fill")
                            .font(.system(size: 20, weight: .semibold))
                            .foregroundColor(.white)
                        Text(errorMessage)
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.white)
                            .lineLimit(2)
                        Spacer()
                    }
                    .padding()
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "ef4444"), Color(hex: "dc2626")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(16)
                    .shadow(color: Color(hex: "ef4444").opacity(0.4), radius: 12, x: 0, y: 6)
                    .padding(.horizontal, 16)
                    .padding(.top, 16)
                    Spacer()
                }
                .transition(.move(edge: .top).combined(with: .opacity))
                .animation(.spring(response: 0.4, dampingFraction: 0.8), value: leaveErrorMessage)
            }
        }
        .navigationTitle(community.name)
        .navigationBarTitleDisplayMode(.inline)
        .navigationDestination(for: Campaign.self) { campaign in
            CampaignDetailView(campaign: campaign)
                .environmentObject(authViewModel)
        }
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 16) {
                    // Share Button
                    Button(action: {
                        ShareManager.shareCommunity(
                            communityId: community.id,
                            communityName: community.name
                        )
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.impactOccurred()
                    }) {
                        Image(systemName: "square.and.arrow.up")
                            .font(.system(size: 20))
                            .foregroundColor(Color(hex: "8b5cf6"))
                    }
                    
                    // QR Code Button
                    Button(action: {
                        showQRCode = true
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.impactOccurred()
                    }) {
                        Image(systemName: "qrcode")
                            .font(.system(size: 20))
                            .foregroundColor(Color(hex: "8b5cf6"))
                    }
                    
                    // Favorite Button
                    Button(action: {
                        if authViewModel.isAuthenticated {
                            isFavorite.toggle()
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                        } else {
                            showLoginModal = true
                        }
                    }) {
                        Image(systemName: isFavorite ? "heart.fill" : "heart")
                            .font(.system(size: 20))
                            .foregroundColor(isFavorite ? Color(hex: "ec4899") : (authViewModel.isAuthenticated ? .secondary : Color(hex: "8b5cf6")))
                    }
                }
            }
        }
        .sheet(item: $selectedEvent) { event in
            if authViewModel.isAuthenticated {
                NavigationStack {
                    EventDetailView(
                        event: event,
                        verificationInfo: verificationInfo
                    )
                }
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
            } else {
                LoginModal(isPresented: .constant(true))
                    .presentationDetents([.large])
                    .presentationDragIndicator(.visible)
                    .onDisappear {
                        selectedEvent = nil
                    }
            }
        }
        .sheet(item: $selectedCampaign) { campaign in
            NavigationStack {
                CampaignDetailView(campaign: campaign)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
            .environmentObject(authViewModel)
        }
        .sheet(isPresented: $showJoinSheet) {
            if authViewModel.isAuthenticated {
                JoinCommunitySheet(community: community, onSuccess: {
                    loadMembershipStatus()
                })
                .environmentObject(authViewModel)
            } else {
                LoginModal(isPresented: .constant(true))
                    .onDisappear {
                        showJoinSheet = false
                    }
            }
        }
        .alert("Üniversite Uyarısı", isPresented: $showUniversityWarning) {
            Button("İptal", role: .cancel) {
                pendingJoinAction = nil
            }
            Button("Eminim, Devam Et") {
                if let action = pendingJoinAction {
                    action()
                }
                pendingJoinAction = nil
            }
        } message: {
            if let userUniversity = authViewModel.currentUser?.university,
               let communityUniversity = community.university {
                Text("Bu topluluk '\(communityUniversity)' üniversitesine aittir. Sizin üniversiteniz '\(userUniversity)' ile farklıdır.\n\nYine de bu topluluğa katılmak istediğinizden emin misiniz?")
            } else {
                Text("Bu topluluk farklı bir üniversiteye aittir. Yine de katılmak istediğinizden emin misiniz?")
            }
        }
        .alert("Topluluktan Ayrıl", isPresented: $showLeaveConfirmation) {
            Button("İptal", role: .cancel) {}
            Button("Evet, Ayrıl", role: .destructive) {
                Task {
                    await leaveCommunity()
                }
            }
        } message: {
            Text("Bu topluluktan ayrılmak istediğinizden emin misiniz? Bu işlem geri alınamaz.")
        }
        .onAppear {
            // View görünür olduğunda üyelik durumunu yükle
            if authViewModel.isAuthenticated {
                loadMembershipStatus()
            }
            // Sadece aktif tab için veri yükle (lazy loading)
                Task {
                switch selectedTab {
                case 0, 1: // Overview ve Events
                    if !viewModel.hasLoadedEvents && !viewModel.isLoadingEvents {
                        await viewModel.loadEvents()
                    }
                case 2: // Campaigns
                    if !viewModel.hasLoadedCampaigns && !viewModel.isLoadingCampaigns {
                        await viewModel.loadCampaigns()
                    }
                case 3: // Products
                    if !viewModel.hasLoadedProducts && !viewModel.isLoadingProducts {
                        await viewModel.loadProducts()
                    }
                case 4: // Members
                    if !viewModel.hasLoadedMembers && !viewModel.isLoadingMembers {
                        await viewModel.loadMembers()
                    }
                case 5: // Board
                    if !viewModel.hasLoadedBoard && !viewModel.isLoadingBoard {
                        await viewModel.loadBoardMembers()
                    }
                default:
                    break
                }
            }
        }
        .onChange(of: authViewModel.isAuthenticated) { newValue in
            if newValue {
                loadMembershipStatus()
            } else {
                membershipStatus = nil
            }
        }
        .onChange(of: community.id) { newValue in
            // Topluluk değiştiğinde üyelik durumunu yeniden yükle
            if authViewModel.isAuthenticated {
                membershipStatus = nil // Önce sıfırla
                loadMembershipStatus()
            }
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showQRCode) {
            let qrContent = QRCodeGenerator.createCommunityQRContent(communityId: community.id)
            QRCodeView(
                title: "\(community.name) QR Kodu",
                content: qrContent,
                isPresented: $showQRCode
            )
            .presentationDetents([.medium, .large])
            .presentationDragIndicator(.visible)
        }
        .onChange(of: selectedTab) { newValue in
            // Sekme değiştiğinde veri yükle
            Task {
                switch newValue {
                case 0: // Overview - events yeterli
                    if !viewModel.hasLoadedEvents && !viewModel.isLoadingEvents {
                        await viewModel.loadEvents()
                    }
                case 1: // Events
                    if !viewModel.hasLoadedEvents && !viewModel.isLoadingEvents {
                        await viewModel.loadEvents()
                    }
                case 2: // Campaigns
                    if !viewModel.hasLoadedCampaigns && !viewModel.isLoadingCampaigns {
                        await viewModel.loadCampaigns()
                    }
                case 3: // Products
                    if !viewModel.hasLoadedProducts && !viewModel.isLoadingProducts {
                        await viewModel.loadProducts()
                    }
                case 4: // Members
                    if !viewModel.hasLoadedMembers && !viewModel.isLoadingMembers {
                        await viewModel.loadMembers()
                    }
                case 5: // Board
                    if !viewModel.hasLoadedBoard && !viewModel.isLoadingBoard {
                        await viewModel.loadBoardMembers()
                    }
                default:
                    break
                }
            }
        }
    }
    
    // MARK: - Tab Content (Lazy Loading - Sadece aktif tab render edilir)
    @ViewBuilder
    private var tabContent: some View {
        // if-else kullanarak sadece aktif tab render edilir (switch yerine)
        if selectedTab == 0 {
            OverviewTab(
                community: community,
                verificationInfo: verificationInfo,
                isVerified: hasVerificationBadge,
                membershipStatus: membershipStatus,
                authViewModel: authViewModel,
                onLeaveRequest: {
                    showLeaveConfirmation = true
                }
            )
            .id("overview-\(community.id)-\(selectedTab)")
        } else if selectedTab == 1 {
            EventsTab(
                viewModel: viewModel,
                selectedEvent: $selectedEvent
            )
            .id("events-\(community.id)-\(selectedTab)")
        } else if selectedTab == 2 {
            CampaignsTab(
                viewModel: viewModel,
                selectedCampaign: $selectedCampaign
            )
            .id("campaigns-\(community.id)-\(selectedTab)")
        } else if selectedTab == 3 {
            ProductsTab(
                viewModel: viewModel,
                verificationInfo: verificationInfo
            )
            .id("products-\(community.id)-\(selectedTab)")
        } else if selectedTab == 4 {
            if authViewModel.isAuthenticated {
                MembersTab(
                    viewModel: viewModel,
                    community: community,
                    membershipStatus: membershipStatus,
                    authViewModel: authViewModel,
                    onJoinRequest: {
                        checkUniversityAndShowJoinSheet()
                    },
                    onLeaveRequest: {
                        showLeaveConfirmation = true
                    }
                )
                    .id("members-\(community.id)-\(selectedTab)")
            } else {
                LoginRequiredView(
                    title: "Giriş Yapın",
                    message: "Topluluk üyelerini görmek için giriş yapmanız gerekiyor.",
                    icon: "person.3.fill",
                    showLoginModal: $showLoginModal
                )
                .id("members-login-\(community.id)")
            }
        } else if selectedTab == 5 {
            if authViewModel.isAuthenticated {
                BoardTab(viewModel: viewModel)
                    .id("board-\(community.id)-\(selectedTab)")
            } else {
                LoginRequiredView(
                    title: "Giriş Yapın",
                    message: "Yönetim bilgilerini görmek için giriş yapmanız gerekiyor.",
                    icon: "person.badge.shield.checkmark.fill",
                    showLoginModal: $showLoginModal
                )
                .id("board-login-\(community.id)")
            }
        } else {
            EmptyView()
        }
    }
    
    private func loadMembershipStatus() {
        guard authViewModel.isAuthenticated && !isLoadingMembershipStatus else { return }
        
        isLoadingMembershipStatus = true
        Task {
            do {
                let status = try await APIService.shared.getMembershipStatus(communityId: community.id)
                await MainActor.run {
                    membershipStatus = status
                    isLoadingMembershipStatus = false
                }
            } catch {
                await MainActor.run {
                    isLoadingMembershipStatus = false
                    membershipStatus = nil
                }
            }
        }
    }
    
    private func leaveCommunity() async {
        guard !isLeavingCommunity else { return }
        
        isLeavingCommunity = true
        leaveErrorMessage = nil
        
        do {
            try await viewModel.leaveCommunity()
            
            // Haptic feedback
            let generator = UINotificationFeedbackGenerator()
            generator.notificationOccurred(.success)
            
            // Üyelik durumunu yenile
            await MainActor.run {
                loadMembershipStatus()
                showLeaveSuccess = true
                
                // 3 saniye sonra success mesajını kapat
                Task {
                    try? await Task.sleep(nanoseconds: 3_000_000_000)
                    await MainActor.run {
                        showLeaveSuccess = false
                    }
                }
            }
            
            // Üye listesini yenile
            await viewModel.refreshMembers()
            
            #if DEBUG
            print("✅ Topluluktan başarıyla ayrıldı")
            #endif
        } catch {
            // Haptic feedback (error)
            let generator = UINotificationFeedbackGenerator()
            generator.notificationOccurred(.error)
            
            await MainActor.run {
                leaveErrorMessage = error.localizedDescription
                
                // 5 saniye sonra hata mesajını kapat
                Task {
                    try? await Task.sleep(nanoseconds: 5_000_000_000)
                    await MainActor.run {
                        leaveErrorMessage = nil
                    }
                }
            }
            
            #if DEBUG
            print("❌ Topluluktan ayrılma hatası: \(error.localizedDescription)")
            #endif
        }
        
        isLeavingCommunity = false
    }
    
    // Üniversite kontrolü yap ve gerekirse uyarı göster
    private func checkUniversityAndShowJoinSheet() {
        #if DEBUG
        print("🔍 Üniversite kontrolü başlatılıyor...")
        #endif
        
        guard let user = authViewModel.currentUser else {
            // Kullanıcı bilgisi yoksa direkt aç
            #if DEBUG
            print("⚠️ Kullanıcı bilgisi yok, direkt açılıyor")
            #endif
            showJoinSheet = true
            return
        }
        
        // Topluluğun üniversitesi yoksa veya "Tümü" ise direkt aç
        guard let communityUniversity = community.university,
              !communityUniversity.isEmpty,
              communityUniversity.lowercased().trimmingCharacters(in: .whitespaces) != "tümü" else {
            #if DEBUG
            print("✅ Topluluk üniversitesi yok veya 'Tümü', direkt açılıyor")
            #endif
            showJoinSheet = true
            return
        }
        
        // Kullanıcının üniversitesi yoksa direkt aç
        let userUniversity = user.university.trimmingCharacters(in: .whitespaces)
        guard !userUniversity.isEmpty else {
            #if DEBUG
            print("⚠️ Kullanıcı üniversitesi yok, direkt açılıyor")
            #endif
            showJoinSheet = true
            return
        }
        
        let normalizedUserUni = userUniversity.lowercased().trimmingCharacters(in: .whitespaces)
        let normalizedCommunityUni = communityUniversity.lowercased().trimmingCharacters(in: .whitespaces)
        
        #if DEBUG
        print("🔍 Üniversite karşılaştırması:")
        print("   Kullanıcı: '\(userUniversity)' -> '\(normalizedUserUni)'")
        print("   Topluluk: '\(communityUniversity)' -> '\(normalizedCommunityUni)'")
        #endif
        
        // Üniversiteler farklı mı kontrol et (case-insensitive)
        if normalizedUserUni != normalizedCommunityUni {
            // Farklı üniversite - uyarı göster
            #if DEBUG
            print("⚠️ Farklı üniversite tespit edildi, uyarı gösteriliyor")
            #endif
            pendingJoinAction = {
                self.showJoinSheet = true
            }
            showUniversityWarning = true
        } else {
            // Aynı üniversite - direkt aç
            #if DEBUG
            print("✅ Aynı üniversite, direkt açılıyor")
            #endif
            showJoinSheet = true
        }
    }
}

// MARK: - Community Detail Hero (Modern Minimal Design)
struct CommunityDetailHero: View {
    let community: Community
    let verificationInfo: VerifiedCommunityInfo?
    
    private var isVerified: Bool {
        (verificationInfo != nil) || community.isVerified
    }
    
    var body: some View {
        VStack(spacing: 0) {
            // Modern gradient background with subtle pattern
            ZStack {
                // Base gradient
                LinearGradient(
                    gradient: Gradient(stops: [
                        .init(color: Color(hex: "8b5cf6"), location: 0.0),
                        .init(color: Color(hex: "7c3aed"), location: 0.5),
                        .init(color: Color(hex: "6366f1"), location: 1.0)
                    ]),
                    startPoint: .topLeading,
                    endPoint: .bottomTrailing
                )
                
                // Subtle pattern overlay
                GeometryReader { geometry in
                    Path { path in
                        let spacing: CGFloat = 40
                        for i in stride(from: 0, through: geometry.size.width + geometry.size.height, by: spacing) {
                            path.move(to: CGPoint(x: i, y: 0))
                            path.addLine(to: CGPoint(x: i - geometry.size.height, y: geometry.size.height))
                        }
                    }
                    .stroke(Color.white.opacity(0.03), lineWidth: 1)
                }
                
                // Logo centered
                VStack(spacing: 0) {
                    if let logoPath = community.logoPath, !logoPath.isEmpty {
                        CachedAsyncImage(url: logoPath) { image in
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                        } placeholder: {
                            ZStack {
                                RoundedRectangle(cornerRadius: 20)
                                    .fill(
                                        LinearGradient(
                                            colors: [Color.white.opacity(0.3), Color.white.opacity(0.15)],
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                    )
                                    .frame(width: 120, height: 120)
                                
                                Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                    .font(.system(size: 56, weight: .medium))
                                    .foregroundColor(.white)
                            }
                        }
                        .frame(width: 120, height: 120)
                        .clipShape(RoundedRectangle(cornerRadius: 20))
                        .overlay(
                            RoundedRectangle(cornerRadius: 20)
                                .stroke(
                                    LinearGradient(
                                        colors: [Color.white.opacity(0.5), Color.white.opacity(0.2)],
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    ),
                                    lineWidth: 3
                                )
                        )
                        .shadow(color: .black.opacity(0.25), radius: 20, x: 0, y: 10)
                    } else {
                        ZStack {
                            RoundedRectangle(cornerRadius: 20)
                                .fill(
                                    LinearGradient(
                                        colors: [Color.white.opacity(0.3), Color.white.opacity(0.15)],
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    )
                                )
                                .frame(width: 120, height: 120)
                            
                            Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                .font(.system(size: 56, weight: .medium))
                                .foregroundColor(.white)
                        }
                        .overlay(
                            RoundedRectangle(cornerRadius: 20)
                                .stroke(
                                    LinearGradient(
                                        colors: [Color.white.opacity(0.5), Color.white.opacity(0.2)],
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    ),
                                    lineWidth: 3
                                )
                        )
                        .shadow(color: .black.opacity(0.25), radius: 20, x: 0, y: 10)
                    }
                }
                
            }
            .frame(height: 180)
            .clipShape(
                UnevenRoundedRectangle(
                    cornerRadii: .init(
                        topLeading: 24,
                        bottomLeading: 0,
                        bottomTrailing: 0,
                        topTrailing: 24
                    )
                )
            )
        }
        .padding(.horizontal, 16)
    }
    
    private func formatCount(_ count: Int) -> String {
        if count >= 1000 {
            return String(format: "%.1fK", Double(count) / 1000.0)
        }
        return "\(count)"
    }
}

// MARK: - Overview Tab (Enhanced with all data)
struct OverviewTab: View {
    let community: Community
    let verificationInfo: VerifiedCommunityInfo?
    let isVerified: Bool
    let membershipStatus: MembershipStatus?
    @ObservedObject var authViewModel: AuthViewModel
    let onLeaveRequest: () -> Void
    
    // Kullanıcı üye mi kontrolü
    private var isUserMember: Bool {
        guard let status = membershipStatus else { return false }
        return status.isMember || status.status == "member"
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Leave Community Button - Sadece üyeyse göster
            if isUserMember && authViewModel.isAuthenticated {
                Button(action: {
                    onLeaveRequest()
                }) {
                    HStack {
                        Image(systemName: "person.badge.minus")
                            .font(.system(size: 18, weight: .semibold))
                        Text("Topluluktan Ayrıl")
                            .font(.system(size: 16, weight: .semibold))
                        Spacer()
                        Image(systemName: "arrow.right")
                            .font(.system(size: 14, weight: .bold))
                    }
                    .foregroundColor(.white)
                    .padding()
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "ef4444"), Color(hex: "dc2626")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(16)
                    .shadow(color: Color(hex: "ef4444").opacity(0.3), radius: 8, x: 0, y: 4)
                }
            }
            
            // General Info Section
            ModernCard {
                VStack(alignment: .leading, spacing: 16) {
                    Label("Genel Bilgiler", systemImage: "info.circle.fill")
                        .font(.system(size: 20, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                    
                    if isVerified {
                        VerifiedStatusRow(verificationInfo: verificationInfo)
                        Divider()
                    }
                    
                    VStack(spacing: 12) {
                        // Member Count
                        HStack(spacing: 12) {
            ZStack {
                Circle()
                    .fill(Color(hex: "8b5cf6").opacity(0.15))
                                    .frame(width: 40, height: 40)
                                Image(systemName: "person.2.fill")
                                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(Color(hex: "8b5cf6"))
            }
                            VStack(alignment: .leading, spacing: 2) {
                                Text("\(community.memberCount)")
                                    .font(.system(size: 18, weight: .bold))
                .foregroundColor(.primary)
                                Text("Üye")
                .font(.system(size: 13, weight: .medium))
                .foregroundColor(.secondary)
        }
                            Spacer()
                        }
                        
                        Divider()
                        
                        // Events Count
                        HStack(spacing: 12) {
                            ZStack {
                                Circle()
                                    .fill(Color(hex: "6366f1").opacity(0.15))
                                    .frame(width: 40, height: 40)
                                Image(systemName: "calendar")
                                    .font(.system(size: 18, weight: .semibold))
                                    .foregroundColor(Color(hex: "6366f1"))
    }
                            VStack(alignment: .leading, spacing: 2) {
                                Text("\(community.eventCount)")
                                    .font(.system(size: 18, weight: .bold))
                                    .foregroundColor(.primary)
                                Text("Etkinlik")
                                    .font(.system(size: 13, weight: .medium))
                                    .foregroundColor(.secondary)
                            }
                            Spacer()
                        }
                        
                        Divider()
                        
                        // Campaigns Count
                        HStack(spacing: 12) {
                            ZStack {
                                Circle()
                                    .fill(Color(hex: "10b981").opacity(0.15))
                                    .frame(width: 40, height: 40)
                                Image(systemName: "tag.fill")
                                    .font(.system(size: 18, weight: .semibold))
                                    .foregroundColor(Color(hex: "10b981"))
                            }
                            VStack(alignment: .leading, spacing: 2) {
                                Text("\(community.campaignCount)")
                                    .font(.system(size: 18, weight: .bold))
                        .foregroundColor(.primary)
                                Text("Kampanya")
                                    .font(.system(size: 13, weight: .medium))
                                    .foregroundColor(.secondary)
                            }
                            Spacer()
                        }
                        
                        if community.boardCount > 0 {
                            Divider()
                            
                            // Board Members Count
                            HStack(spacing: 12) {
                                ZStack {
                                    Circle()
                                        .fill(Color(hex: "f59e0b").opacity(0.15))
                                        .frame(width: 40, height: 40)
                                    Image(systemName: "person.badge.shield.checkmark.fill")
                                        .font(.system(size: 18, weight: .semibold))
                                        .foregroundColor(Color(hex: "f59e0b"))
                                }
                                VStack(alignment: .leading, spacing: 2) {
                                    Text("\(community.boardCount)")
                                        .font(.system(size: 18, weight: .bold))
                                        .foregroundColor(.primary)
                                    Text("Yönetim Üyesi")
                                        .font(.system(size: 13, weight: .medium))
                                        .foregroundColor(.secondary)
                                }
                                Spacer()
                            }
                        }
                    }
                }
            }
            
            // About Section
            if !community.description.isEmpty {
                ModernCard {
                    VStack(alignment: .leading, spacing: 16) {
                        Label("Hakkında", systemImage: "info.circle.fill")
                            .font(.system(size: 20, weight: .bold, design: .rounded))
                            .foregroundColor(.primary)
                        
                        Text(community.description)
                            .font(.system(size: 16, weight: .regular))
                            .foregroundColor(.secondary)
                            .lineSpacing(8)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                }
            }
            
            // Contact Section
            if (community.contactEmail?.isEmpty == false) || (community.website?.isEmpty == false) {
                ModernCard {
                    VStack(alignment: .leading, spacing: 16) {
                        Label("İletişim", systemImage: "envelope.fill")
                            .font(.system(size: 20, weight: .bold, design: .rounded))
                            .foregroundColor(.primary)
                        
                        VStack(spacing: 12) {
                            if let email = community.contactEmail, !email.isEmpty {
                                ModernContactRow(
                                    icon: "envelope.fill",
                                    title: "Email",
                                    value: email,
                                    color: Color(hex: "3b82f6"),
                                    action: {
                                        if let url = URL(string: "mailto:\(email)") {
                                            UIApplication.shared.open(url)
                                        }
                                    }
                                )
                            }
                            
                            if let website = community.website, !website.isEmpty {
                                ModernContactRow(
                                    icon: "globe",
                                    title: "Web Sitesi",
                                    value: website,
                                    color: Color(hex: "10b981"),
                                    action: {
                                        var urlString = website
                                        if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
                                            urlString = "https://\(urlString)"
                                        }
                                        if let url = URL(string: urlString) {
                                            UIApplication.shared.open(url)
                                        }
                                    }
                                )
                            }
                        }
                    }
                }
            }
            
            // Tags Section
                if !community.tags.isEmpty {
                    ModernCard {
                    VStack(alignment: .leading, spacing: 16) {
                            Label("Etiketler", systemImage: "tag.fill")
                                .font(.system(size: 20, weight: .bold, design: .rounded))
                                .foregroundColor(.primary)
                            
                        FlowLayout(spacing: 10) {
                                ForEach(community.tags, id: \.self) { tag in
                                    Text(tag)
                                        .font(.system(size: 14, weight: .medium))
                                        .foregroundColor(Color(hex: "8b5cf6"))
                                    .padding(.horizontal, 16)
                                    .padding(.vertical, 10)
                                        .background(Color(hex: "8b5cf6").opacity(0.15))
                                    .cornerRadius(12)
                                }
                            }
                        }
                    }
                }
                
                // Category Badge
                ModernCard {
                HStack(spacing: 16) {
                    ZStack {
                        Circle()
                            .fill(Color(hex: "8b5cf6").opacity(0.15))
                            .frame(width: 48, height: 48)
                        
                        Image(systemName: Community.icon(for: community.categories.first ?? ""))
                            .font(.system(size: 22, weight: .semibold))
                            .foregroundColor(Color(hex: "8b5cf6"))
                    }
                        
                        VStack(alignment: .leading, spacing: 4) {
                            Text("Kategori")
                                .font(.system(size: 13, weight: .medium))
                                .foregroundColor(.secondary)
                        Text(community.categories.first ?? "")
                            .font(.system(size: 17, weight: .semibold))
                                .foregroundColor(.primary)
                        }
                        
                        Spacer()
                }
            }
            
            // Social Media Section - En altta
            if hasSocialMedia {
                ModernCard {
                    VStack(alignment: .leading, spacing: 16) {
                        Label("Sosyal Medya", systemImage: "link.circle.fill")
                            .font(.system(size: 20, weight: .bold, design: .rounded))
                            .foregroundColor(.primary)
                        
                        VStack(spacing: 12) {
                            if let social = community.socialLinks {
                                if let instagram = social.instagram, !instagram.isEmpty {
                                    ModernContactRow(
                                        icon: "camera.fill",
                                        title: "Instagram",
                                        value: instagram,
                                        color: Color(hex: "ec4899"),
                                        action: {
                                            var urlString = instagram
                                            if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
                                                if !urlString.hasPrefix("@") {
                                                    urlString = "@\(urlString)"
                                                }
                                                urlString = "https://instagram.com/\(urlString.replacingOccurrences(of: "@", with: ""))"
                                            }
                                            if let url = URL(string: urlString) {
                                                UIApplication.shared.open(url)
                                            }
                                        }
                                    )
                                }
                                
                                if let twitter = social.twitter, !twitter.isEmpty {
                                    ModernContactRow(
                                        icon: "at",
                                        title: "Twitter",
                                        value: twitter,
                                        color: Color(hex: "06b6d4"),
                                        action: {
                                            var urlString = twitter
                                            if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
                                                if !urlString.hasPrefix("@") {
                                                    urlString = "@\(urlString)"
                                                }
                                                urlString = "https://twitter.com/\(urlString.replacingOccurrences(of: "@", with: ""))"
                                            }
                                            if let url = URL(string: urlString) {
                                                UIApplication.shared.open(url)
                                            }
                                        }
                                    )
                                }
                                
                                if let linkedin = social.linkedin, !linkedin.isEmpty {
                                    ModernContactRow(
                                        icon: "link",
                                        title: "LinkedIn",
                                        value: linkedin,
                                        color: Color(hex: "0077b5"),
                                        action: {
                                            var urlString = linkedin
                                            if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
                                                urlString = "https://linkedin.com/in/\(urlString)"
                                            }
                                            if let url = URL(string: urlString) {
                                                UIApplication.shared.open(url)
                                            }
                                        }
                                    )
                                }
                                
                                if let facebook = social.facebook, !facebook.isEmpty {
                                    ModernContactRow(
                                        icon: "f.circle.fill",
                                        title: "Facebook",
                                        value: facebook,
                                        color: Color(hex: "1877f2"),
                                        action: {
                                            var urlString = facebook
                                            if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
                                                urlString = "https://facebook.com/\(urlString)"
                                            }
                                            if let url = URL(string: urlString) {
                                                UIApplication.shared.open(url)
                                            }
                                        }
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    private func formatCount(_ count: Int) -> String {
        if count >= 1000 {
            return String(format: "%.1fK", Double(count) / 1000.0)
        }
        return "\(count)"
    }
    
    private var hasContactInfo: Bool {
        (community.contactEmail?.isEmpty == false) ||
        (community.website?.isEmpty == false) ||
        (community.socialLinks != nil && (
            (community.socialLinks?.instagram?.isEmpty == false) ||
            (community.socialLinks?.twitter?.isEmpty == false) ||
            (community.socialLinks?.linkedin?.isEmpty == false) ||
            (community.socialLinks?.facebook?.isEmpty == false)
        ))
    }
    
    private var hasSocialMedia: Bool {
        community.socialLinks != nil && (
            (community.socialLinks?.instagram?.isEmpty == false) ||
            (community.socialLinks?.twitter?.isEmpty == false) ||
            (community.socialLinks?.linkedin?.isEmpty == false) ||
            (community.socialLinks?.facebook?.isEmpty == false)
        )
    }
}

// MARK: - Verification Helpers
private struct VerifiedStatusCard: View {
    let communityName: String
    let verificationInfo: VerifiedCommunityInfo?
    @State private var showVerificationInfo = false
    
    private var reviewedText: String {
        verificationInfo?.reviewedAtDisplayText ?? "Bu topluluk Four Kampüs ekibi tarafından doğrulandı."
    }
    
    var body: some View {
        Button(action: {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            showVerificationInfo = true
        }) {
        HStack(alignment: .center, spacing: 16) {
            Image("BlueTick")
                .resizable()
                .frame(width: 44, height: 44)
                .shadow(color: Color.blue.opacity(0.3), radius: 8, x: 0, y: 4)
            
            VStack(alignment: .leading, spacing: 6) {
                Text("Onaylı Topluluk")
                    .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                Text(reviewedText)
                    .font(.system(size: 14))
                    .foregroundColor(.secondary)
                if let adminNotes = verificationInfo?.adminNotes, !adminNotes.isEmpty {
                    Text(adminNotes)
                        .font(.system(size: 13))
                        .foregroundColor(.secondary)
                        .lineLimit(2)
                }
            }
            
            Spacer()
                
                Image(systemName: "info.circle")
                    .font(.system(size: 18))
                    .foregroundColor(.secondary)
        }
        .padding(18)
        .background(
            RoundedRectangle(cornerRadius: 20)
                .fill(Color.blue.opacity(0.08))
                .overlay(
                    RoundedRectangle(cornerRadius: 20)
                        .stroke(Color.blue.opacity(0.2), lineWidth: 1)
                )
        )
    }
        .buttonStyle(PlainButtonStyle())
        .sheet(isPresented: $showVerificationInfo) {
            VerificationInfoView(communityName: communityName, verificationInfo: verificationInfo)
        }
    }
}

// MARK: - Verification Info View
private struct VerificationInfoView: View {
    let communityName: String
    let verificationInfo: VerifiedCommunityInfo?
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 24) {
                    // Header
                    VStack(spacing: 16) {
            Image("BlueTick")
                .resizable()
                            .frame(width: 80, height: 80)
                            .shadow(color: Color.blue.opacity(0.3), radius: 12, x: 0, y: 6)
                        
                        Text("Topluluk Onayı Nedir?")
                            .font(.system(size: 24, weight: .bold))
                            .multilineTextAlignment(.center)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.top, 20)
                    
                    // Content
                    VStack(alignment: .leading, spacing: 16) {
                        Text("Onaylı Topluluk, Four Kampüs ekibi tarafından doğrulanmış ve güvenilir olduğu kanıtlanmış topluluklardır. Bu topluluklar:")
                            .font(.system(size: 16))
                            .foregroundColor(.primary)
                        
                        VStack(alignment: .leading, spacing: 12) {
                            VerificationInfoRow(icon: "checkmark.circle.fill", text: "Gerçek ve aktif topluluklardır")
                            VerificationInfoRow(icon: "checkmark.circle.fill", text: "Topluluk kurallarına uygun içerik paylaşırlar")
                            VerificationInfoRow(icon: "checkmark.circle.fill", text: "Üyelerine güvenli bir ortam sağlarlar")
                            VerificationInfoRow(icon: "checkmark.circle.fill", text: "Four Kampüs topluluk standartlarına uyarlar")
                        }
                        
                        Divider()
                        
                        if let verificationInfo = verificationInfo {
                            VStack(alignment: .leading, spacing: 8) {
                                Text("Bu Topluluk Hakkında")
                                    .font(.system(size: 18, weight: .semibold))
                                
                                if let reviewedText = verificationInfo.reviewedAtDisplayText {
                    Text(reviewedText)
                                        .font(.system(size: 15))
                                        .foregroundColor(.secondary)
                                }
                                
                                if let adminNotes = verificationInfo.adminNotes, !adminNotes.isEmpty {
                                    Text(adminNotes)
                                        .font(.system(size: 15))
                                        .foregroundColor(.secondary)
                                        .padding(.top, 8)
                                }
                }
            }
        }
                    .padding()
                }
            }
            .navigationTitle("Onaylı Topluluk")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        dismiss()
                    }
                }
            }
        }
    }
}

private struct VerificationInfoRow: View {
    let icon: String
    let text: String
    
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: icon)
                .font(.system(size: 18))
                .foregroundColor(Color(hex: "6366f1"))
                .frame(width: 24)
            Text(text)
                .font(.system(size: 15))
                .foregroundColor(.primary)
        }
    }
}

private struct VerifiedStatusRow: View {
    let verificationInfo: VerifiedCommunityInfo?
    
    var body: some View {
        HStack(spacing: 12) {
            Image("BlueTick")
                .resizable()
                .frame(width: 26, height: 26)
            VStack(alignment: .leading, spacing: 2) {
                Text("Topluluk Onaylandı")
                    .font(.system(size: 15, weight: .semibold))
                Text(verificationInfo?.reviewedAtDisplayText ?? "Mavi tik aktif, üyeler güvenle katılabilir.")
                    .font(.system(size: 13))
                    .foregroundColor(.secondary)
            }
            Spacer()
        }
    }
}

// MARK: - Modern Card
struct ModernCard<Content: View>: View {
    let content: Content
    
    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }
    
    var body: some View {
        content
            .frame(maxWidth: .infinity, alignment: .leading)
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

// MARK: - Modern Contact Row
struct ModernContactRow: View {
    let icon: String
    let title: String
    let value: String
    let color: Color
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                ZStack {
                    Circle()
                        .fill(color.opacity(0.15))
                        .frame(width: 44, height: 44)
                    
                    Image(systemName: icon)
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(color)
                }
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(.secondary)
                    Text(value)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(.primary)
                        .lineLimit(1)
                }
                
                Spacer()
                
                Image(systemName: "arrow.up.right.square")
                    .font(.system(size: 16, weight: .medium))
                    .foregroundColor(.secondary)
            }
            .padding(12)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(12)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Contact Row (Legacy - kept for compatibility)
struct ContactRow: View {
    let icon: String
    let text: String
    let color: Color
    
    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.system(size: 18))
                .foregroundColor(color)
                .frame(width: 24)
            
            Text(text)
                .font(.system(size: 16, weight: .regular))
                .foregroundColor(.primary)
            
            Spacer()
        }
    }
}

// MARK: - Events Tab
struct EventsTab: View {
    @ObservedObject var viewModel: CommunityDetailViewModel
    @Binding var selectedEvent: Event?
    
    var body: some View {
        Group {
            if viewModel.isLoadingEvents && viewModel.events.isEmpty {
                VStack(spacing: 20) {
                    ProgressView()
                        .scaleEffect(1.2)
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if let error = viewModel.eventsError {
                VStack(spacing: 20) {
                    Image(systemName: "exclamationmark.circle.fill")
                        .font(.system(size: 56))
                        .foregroundColor(Color(hex: "ef4444"))
                    Text("Yükleme Hatası")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(error)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                    Button(action: {
                        Task {
                            await viewModel.refreshEvents()
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
                .padding(.top, 80)
            } else if viewModel.events.isEmpty && viewModel.hasLoadedEvents {
                VStack(spacing: 16) {
                    Image(systemName: "calendar")
                        .font(.system(size: 56))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text("Etkinlik Bulunmuyor")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text("Bu topluluk için henüz etkinlik kaydı bulunmamaktadır.")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if !viewModel.filteredEvents.isEmpty || !viewModel.eventsSearchText.isEmpty {
                VStack(spacing: 16) {
                    // Search Bar
                    HStack {
                        Image(systemName: "magnifyingglass")
                            .foregroundColor(.gray.opacity(0.5))
                        TextField("Etkinlik ara...", text: $viewModel.eventsSearchText)
                            .textFieldStyle(PlainTextFieldStyle())
                    }
                    .padding(12)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)

                    if viewModel.filteredEvents.isEmpty {
                        VStack(spacing: 16) {
                            Image(systemName: "magnifyingglass")
                                .font(.system(size: 40))
                                .foregroundColor(.secondary.opacity(0.5))
                            Text("Sonuç Bulunamadı")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundColor(.secondary)
                        }
                        .padding(.top, 40)
                    } else {
                        LazyVStack(spacing: 12) {
                            ForEach(viewModel.filteredEvents.indices, id: \.self) { index in
                                let event = viewModel.filteredEvents[index]
                                ProfessionalEventCard(event: event) {
                                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                        selectedEvent = event
                                    }
                                }
                                .onAppear {
                                    // Sadece son item'a gelindiğinde daha fazla yükle (performans optimizasyonu)
                                    let eventsCount = viewModel.filteredEvents.count
                                    if index == eventsCount - 1,
                                       viewModel.hasMoreEvents,
                                       !viewModel.isLoadingMoreEvents,
                                       viewModel.eventsSearchText.isEmpty {
                                        Task {
                                            await viewModel.loadMoreEvents()
                                        }
                                    }
                                }
                            }
                            
                            // Loading indicator
                            if viewModel.isLoadingMoreEvents {
                                HStack {
                                    Spacer()
                                    ProgressView()
                                        .padding()
                                    Spacer()
                                }
                            }
                        }
                    }
                }
            }
        }
        .refreshable {
            await viewModel.refreshEvents()
        }
    }
}

// MARK: - Campaigns Tab
struct CampaignsTab: View {
    @ObservedObject var viewModel: CommunityDetailViewModel
    @Binding var selectedCampaign: Campaign?
    @StateObject private var campaignsVM = CampaignsViewModel()
    
    var body: some View {
        Group {
            if viewModel.isLoadingCampaigns && viewModel.campaigns.isEmpty {
                VStack(spacing: 20) {
                    ProgressView()
                        .scaleEffect(1.2)
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if let error = viewModel.campaignsError {
                VStack(spacing: 20) {
                    Image(systemName: "exclamationmark.circle.fill")
                        .font(.system(size: 56))
                        .foregroundColor(Color(hex: "ef4444"))
                    Text("Yükleme Hatası")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(error)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                    Button(action: {
                        Task {
                            await viewModel.refreshCampaigns()
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
                .padding(.top, 80)
            } else if viewModel.campaigns.isEmpty && viewModel.hasLoadedCampaigns {
                VStack(spacing: 16) {
                    Image(systemName: "tag")
                        .font(.system(size: 56))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text("Kampanya Bulunmuyor")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text("Bu topluluk için henüz kampanya kaydı bulunmamaktadır.")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if !viewModel.filteredCampaigns.isEmpty || !viewModel.campaignsSearchText.isEmpty {
                VStack(spacing: 16) {
                    // Search Bar
                    HStack {
                        Image(systemName: "magnifyingglass")
                            .foregroundColor(.gray.opacity(0.5))
                        TextField("Kampanya ara...", text: $viewModel.campaignsSearchText)
                            .textFieldStyle(PlainTextFieldStyle())
                    }
                    .padding(12)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)

                    if viewModel.filteredCampaigns.isEmpty {
                        VStack(spacing: 16) {
                            Image(systemName: "magnifyingglass")
                                .font(.system(size: 40))
                                .foregroundColor(.secondary.opacity(0.5))
                            Text("Sonuç Bulunamadı")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundColor(.secondary)
                        }
                        .padding(.top, 40)
                    } else {
                        LazyVStack(spacing: 12) {
                            ForEach(viewModel.filteredCampaigns) { campaign in
                                ProfessionalCampaignCard(
                                    campaign: campaign,
                                    isSaved: campaignsVM.isSaved(campaign.id),
                                    onTap: {
                                        let generator = UIImpactFeedbackGenerator(style: .light)
                                        generator.impactOccurred()
                                        withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                            selectedCampaign = campaign
                                        }
                                    }
                                )
                                .onAppear {
                                    // Son 3 kampanyadan birine gelindiğinde daha fazla yükle
                                    if let index = viewModel.filteredCampaigns.firstIndex(where: { $0.id == campaign.id }),
                                       index >= viewModel.filteredCampaigns.count - 3,
                                       viewModel.hasMoreCampaigns,
                                       !viewModel.isLoadingMoreCampaigns,
                                       viewModel.campaignsSearchText.isEmpty {
                                        Task {
                                            await viewModel.loadMoreCampaigns()
                                        }
                                    }
                                }
                            }
                            
                            // Loading indicator
                            if viewModel.isLoadingMoreCampaigns {
                                HStack {
                                    Spacer()
                                    ProgressView()
                                        .padding()
                                    Spacer()
                                }
                            }
                        }
                    }
                }
            }
        }
        .refreshable {
            await viewModel.refreshCampaigns()
        }
    }
}

// MARK: - Flow Layout
struct FlowLayout: Layout {
    var spacing: CGFloat = 8
    
    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        let result = FlowResult(
            in: proposal.replacingUnspecifiedDimensions().width,
            subviews: subviews,
            spacing: spacing
        )
        return result.size
    }
    
    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let result = FlowResult(
            in: bounds.width,
            subviews: subviews,
            spacing: spacing
        )
        for (index, subview) in subviews.enumerated() {
            subview.place(at: CGPoint(x: bounds.minX + result.frames[index].minX, y: bounds.minY + result.frames[index].minY), proposal: .unspecified)
        }
    }
    
    struct FlowResult {
        var size: CGSize = .zero
        var frames: [CGRect] = []
        
        init(in maxWidth: CGFloat, subviews: Subviews, spacing: CGFloat) {
            var currentX: CGFloat = 0
            var currentY: CGFloat = 0
            var lineHeight: CGFloat = 0
            
            for subview in subviews {
                let size = subview.sizeThatFits(.unspecified)
                
                if currentX + size.width > maxWidth && currentX > 0 {
                    currentX = 0
                    currentY += lineHeight + spacing
                    lineHeight = 0
                }
                
                frames.append(CGRect(x: currentX, y: currentY, width: size.width, height: size.height))
                lineHeight = max(lineHeight, size.height)
                currentX += size.width + spacing
            }
            
            self.size = CGSize(width: maxWidth, height: currentY + lineHeight)
        }
    }
}

// MARK: - Blur View
struct BlurView: UIViewRepresentable {
    let style: UIBlurEffect.Style
    
    func makeUIView(context: Context) -> UIVisualEffectView {
        return UIVisualEffectView(effect: UIBlurEffect(style: style))
    }
    
    func updateUIView(_ uiView: UIVisualEffectView, context: Context) {}
}

// MARK: - View Modifier for Backdrop
extension View {
    func backdrop(_ blurView: BlurView) -> some View {
        self.background(blurView)
    }
}

// MARK: - Members Tab
struct MembersTab: View {
    @ObservedObject var viewModel: CommunityDetailViewModel
    let community: Community
    let membershipStatus: MembershipStatus?
    @ObservedObject var authViewModel: AuthViewModel
    let onJoinRequest: () -> Void
    let onLeaveRequest: () -> Void
    
    // Kullanıcı üye mi kontrolü
    private var isUserMember: Bool {
        guard let status = membershipStatus else { return false }
        return status.isMember || status.status == "member"
    }
    
    // Kullanıcının kendi bilgileri
    private var currentUser: User? {
        authViewModel.currentUser
    }
    
    // Kullanıcı bu member mı kontrolü (ID veya email ile)
    private func isCurrentUser(member: Member) -> Bool {
        guard let user = currentUser else { return false }
        // Önce ID ile kontrol et
        if member.id == user.id {
            return true
        }
        // ID eşleşmezse email ile kontrol et
        if let memberEmail = member.email, !memberEmail.isEmpty {
            return memberEmail.lowercased() == user.email.lowercased()
        }
        return false
    }
    
    // Üye listesini sırala - kullanıcı en başta
    private var sortedMembers: [Member] {
        guard isUserMember else {
            return viewModel.members
        }
        
        var members = viewModel.members
        // Kullanıcıyı bul ve en başa al
        if let userIndex = members.firstIndex(where: { isCurrentUser(member: $0) }) {
            let userMember = members.remove(at: userIndex)
            members.insert(userMember, at: 0)
        }
        return members
    }
    
    var filteredMembers: [Member] {
        let members = viewModel.membersSearchText.isEmpty ? sortedMembers : sortedMembers.filter { member in
            member.fullName.localizedCaseInsensitiveContains(viewModel.membersSearchText)
        }
        
        // Arama yapılıyorsa ve kullanıcı üyeyse, kullanıcıyı en başta göster
        if !viewModel.membersSearchText.isEmpty, isUserMember {
            if let userIndex = members.firstIndex(where: { isCurrentUser(member: $0) }) {
                var filtered = members
                let userMember = filtered.remove(at: userIndex)
                filtered.insert(userMember, at: 0)
                return filtered
            }
        }
        
        return members
    }
    
    var body: some View {
        VStack(spacing: 16) {
            // Search Bar
            HStack {
                Image(systemName: "magnifyingglass")
                    .foregroundColor(.gray.opacity(0.5))
                TextField("Üye ara...", text: $viewModel.membersSearchText)
                    .textFieldStyle(PlainTextFieldStyle())
            }
            .padding(12)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(12)
            .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)
            
            // Join Button - Sadece üye değilse göster
            if !isUserMember && authViewModel.isAuthenticated {
                Button(action: {
                    onJoinRequest()
                }) {
                    HStack {
                        Image(systemName: "person.badge.plus")
                        Text("Topluluğa Katıl")
                    }
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "8b5cf6"), Color(hex: "6366f1")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(14)
                }
            }
            
            // Leave Button - Sadece üyeyse göster
            if isUserMember && authViewModel.isAuthenticated {
                Button(action: {
                    onLeaveRequest()
                }) {
                    HStack {
                        Image(systemName: "person.badge.minus")
                        Text("Topluluktan Ayrıl")
                    }
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "ef4444"), Color(hex: "dc2626")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(14)
                }
            }
            
            // Members List
            if viewModel.isLoadingMembers && viewModel.members.isEmpty {
                VStack(spacing: 20) {
                    ProgressView()
                        .scaleEffect(1.2)
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if let error = viewModel.membersError {
                VStack(spacing: 20) {
                    Image(systemName: "exclamationmark.circle.fill")
                        .font(.system(size: 56))
                        .foregroundColor(Color(hex: "ef4444"))
                    Text("Yükleme Hatası")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(error)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                    Button(action: {
                        Task {
                            await viewModel.refreshMembers()
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
                .padding(.top, 80)
            } else if filteredMembers.isEmpty && viewModel.hasLoadedMembers {
                VStack(spacing: 16) {
                    Image(systemName: "person.2.slash")
                        .font(.system(size: 56))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text(viewModel.membersSearchText.isEmpty ? "Üye Bulunmuyor" : "Sonuç Bulunamadı")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(viewModel.membersSearchText.isEmpty ? "Bu topluluk için henüz üye kaydı bulunmamaktadır." : "Arama kriterlerinize uygun üye bulunamadı.")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if !filteredMembers.isEmpty {
                LazyVStack(spacing: 12) {
                    ForEach(Array(filteredMembers.enumerated()), id: \.element.id) { index, member in
                        MemberRowCard(
                            member: member,
                            isCurrentUser: isCurrentUser(member: member)
                        )
                            .onAppear {
                                // Son 10 üyeden birine gelindiğinde daha fazla yükle (sadece search yoksa)
                                if viewModel.membersSearchText.isEmpty,
                                   index >= filteredMembers.count - 10,
                                   viewModel.hasMoreMembers,
                                   !viewModel.isLoadingMoreMembers {
                                    Task {
                                        await viewModel.loadMoreMembers()
                                    }
                                }
                            }
                    }
                    
                    // Loading indicator
                    if viewModel.isLoadingMoreMembers && viewModel.membersSearchText.isEmpty {
                        HStack {
                            Spacer()
                            ProgressView()
                                .padding()
                            Spacer()
                        }
                    }
                }
            }
        }
        .refreshable {
            await viewModel.refreshMembers()
        }
    }
}

// MARK: - Member Row Card
struct MemberRowCard: View {
    let member: Member
    var isCurrentUser: Bool = false
    
    // Soyadı yıldızlı yap
    private var maskedName: String {
        let components = member.fullName.components(separatedBy: " ")
        guard components.count > 1 else {
            return member.fullName
        }
        
        let firstName = components[0]
        let lastName = components[1]
        let maskedLastName = String(repeating: "*", count: max(lastName.count, 3))
        
        return "\(firstName) \(maskedLastName)"
    }
    
    var body: some View {
        HStack(spacing: 16) {
            // Avatar
            ZStack {
                Circle()
                    .fill(
                        LinearGradient(
                            colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .frame(width: 50, height: 50)
                
                Text(member.fullName.prefix(1).uppercased())
                    .font(.system(size: 20, weight: .bold))
                    .foregroundColor(.white)
            }
            
            // Name
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 6) {
                    Text(maskedName)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    
                    if isCurrentUser {
                        Text("(Sen)")
                            .font(.system(size: 14, weight: .medium))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                }
            }
            
            Spacer()
        }
        .padding(16)
        .background(isCurrentUser ? Color(hex: "6366f1").opacity(0.1) : Color(UIColor.secondarySystemBackground))
        .cornerRadius(16)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(isCurrentUser ? Color(hex: "6366f1").opacity(0.3) : Color.clear, lineWidth: 1)
        )
        .shadow(color: Color.black.opacity(0.05), radius: 4, x: 0, y: 2)
    }
}

// MARK: - Products Tab
struct ProductsTab: View {
    @ObservedObject var viewModel: CommunityDetailViewModel
    @EnvironmentObject var cartViewModel: CartViewModel
    @State private var selectedProduct: Product?
    let verificationInfo: VerifiedCommunityInfo?
    
    var body: some View {
        Group {
            if viewModel.isLoadingProducts && viewModel.products.isEmpty {
                VStack(spacing: 20) {
                    ProgressView()
                        .scaleEffect(1.2)
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if let error = viewModel.productsError {
                VStack(spacing: 20) {
                    Image(systemName: "exclamationmark.circle.fill")
                        .font(.system(size: 56))
                        .foregroundColor(Color(hex: "ef4444"))
                    Text("Yükleme Hatası")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(error)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                    Button(action: {
                        Task {
                            await viewModel.refreshProducts()
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
                .padding(.top, 80)
            } else if viewModel.products.isEmpty && viewModel.hasLoadedProducts {
                VStack(spacing: 16) {
                    Image(systemName: "bag")
                        .font(.system(size: 56))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text("Ürün Bulunmuyor")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text("Bu topluluk için henüz ürün kaydı bulunmamaktadır.")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if !viewModel.filteredProducts.isEmpty || !viewModel.productsSearchText.isEmpty {
                VStack(spacing: 16) {
                    // Search Bar
                    HStack {
                        Image(systemName: "magnifyingglass")
                            .foregroundColor(.gray.opacity(0.5))
                        TextField("Ürün ara...", text: $viewModel.productsSearchText)
                            .textFieldStyle(PlainTextFieldStyle())
                    }
                    .padding(12)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)

                    if viewModel.filteredProducts.isEmpty {
                        VStack(spacing: 16) {
                            Image(systemName: "magnifyingglass")
                                .font(.system(size: 40))
                                .foregroundColor(.secondary.opacity(0.5))
                            Text("Sonuç Bulunamadı")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundColor(.secondary)
                        }
                        .padding(.top, 40)
                    } else {
                        LazyVStack(spacing: 12) {
                            ForEach(Array(viewModel.filteredProducts.enumerated()), id: \.element.id) { index, product in
                                ProductCard(
                                    product: product,
                                    verificationInfo: verificationInfo
                                ) {
                                    let generator = UIImpactFeedbackGenerator(style: .light)
                                    generator.impactOccurred()
                                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                        selectedProduct = product
                                    }
                                }
                                .onAppear {
                                    // Sadece son item'a gelindiğinde daha fazla yükle
                                    if index == viewModel.filteredProducts.count - 1,
                                       viewModel.hasMoreProducts,
                                       !viewModel.isLoadingMoreProducts,
                                       viewModel.productsSearchText.isEmpty {
                                        Task {
                                            await viewModel.loadMoreProducts()
                                        }
                                    }
                                }
                            }
                            
                            // Loading indicator
                            if viewModel.isLoadingMoreProducts {
                                HStack {
                                    Spacer()
                                    ProgressView()
                                        .padding()
                                    Spacer()
                                }
                            }
                        }
                    }
                }
            }
        }
        .refreshable {
            await viewModel.refreshProducts()
        }
        .sheet(item: $selectedProduct) { product in
            NavigationStack {
                ProductDetailView(product: product)
                    .environmentObject(cartViewModel)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
        }
    }
}

// MARK: - Product Card
struct ProductCard: View {
    let product: Product
    let verificationInfo: VerifiedCommunityInfo?
    let communityName: String? = nil
    let universityName: String? = nil
    var onTap: (() -> Void)? = nil
    
    private var isVerified: Bool {
        verificationInfo != nil
    }
    
    var body: some View {
        Button(action: {
            // Haptic feedback
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.prepare()
            generator.impactOccurred()
            onTap?()
        }) {
            HStack(spacing: 16) {
                // Product Image - Çoklu görsel desteği (ilk görseli göster)
                let imageURL: String? = {
                    if let imageURLs = product.imageURLs, !imageURLs.isEmpty {
                        return imageURLs[0]
                    }
                    return product.imageURL ?? product.imagePath
                }()
                
                if let imagePath = imageURL {
                    let finalImageURL = (imagePath.hasPrefix("http://") || imagePath.hasPrefix("https://")) 
                        ? imagePath 
                        : (APIService.fullImageURL(from: imagePath) ?? imagePath)
                    AsyncImage(url: URL(string: finalImageURL)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                        case .failure(_):
                            // Hata durumunda placeholder göster
                            ZStack {
                                RoundedRectangle(cornerRadius: 12)
                                    .fill(
                                        LinearGradient(
                                            colors: [Color(hex: "6366f1").opacity(0.2), Color(hex: "8b5cf6").opacity(0.1)],
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                    )
                                Image(systemName: "bag.fill")
                                    .font(.system(size: 24))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        case .empty:
                            // Yükleniyor
                            ZStack {
                                RoundedRectangle(cornerRadius: 12)
                                    .fill(Color(UIColor.secondarySystemBackground))
                                ProgressView()
                                    .tint(Color(hex: "6366f1"))
                            }
                        @unknown default:
                            EmptyView()
                        }
                    }
                    .frame(width: 100, height: 100)
                    .clipShape(RoundedRectangle(cornerRadius: 12))
                } else {
                    ZStack {
                        RoundedRectangle(cornerRadius: 12)
                            .fill(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1").opacity(0.2), Color(hex: "8b5cf6").opacity(0.1)],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                        Image(systemName: "bag.fill")
                            .font(.system(size: 24))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    .frame(width: 100, height: 100)
                }
                
                // Content
                VStack(alignment: .leading, spacing: 8) {
                    VStack(alignment: .leading, spacing: 6) {
                    Text(product.name)
                        .font(.system(size: 17, weight: .semibold))
                        .foregroundColor(.primary)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                        
                        if isVerified {
                            VerifiedBadgeTag(text: "Onaylı Ürün", style: .subtle)
                        }
                    }
                    
                    if let description = product.description, !description.isEmpty {
                        Text(description)
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .lineLimit(2)
                    }
                    
                    // Topluluk ve Üniversite Bilgisi (sadece MarketView'den gelirse göster)
                    if communityName != nil || universityName != nil {
                        VStack(alignment: .leading, spacing: 4) {
                            if let communityName = communityName {
                                HStack(spacing: 4) {
                                    Image(systemName: "person.3.fill")
                                        .font(.system(size: 10))
                                        .foregroundColor(.secondary)
                                    Text(communityName)
                                        .font(.system(size: 12))
                                        .foregroundColor(.secondary)
                                        .lineLimit(1)
                                }
                            }
                            if let universityName = universityName {
                                HStack(spacing: 4) {
                                    Image(systemName: "building.2.fill")
                                        .font(.system(size: 10))
                                        .foregroundColor(.secondary)
                                    Text(universityName)
                                        .font(.system(size: 12))
                                        .foregroundColor(.secondary)
                                        .lineLimit(1)
                                }
                            }
                        }
                    }
                    
                    HStack(spacing: 12) {
                        // Price
                        VStack(alignment: .leading, spacing: 2) {
                            if product.totalPrice != nil {
                                Text(product.formattedTotalPrice)
                                    .font(.system(size: 18, weight: .bold))
                                    .foregroundColor(Color(hex: "6366f1"))
                                Text(product.formattedPrice)
                                    .font(.system(size: 12))
                                    .foregroundColor(.secondary)
                                    .strikethrough()
                            } else {
                                Text(product.formattedPrice)
                                    .font(.system(size: 18, weight: .bold))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                        
                        Spacer()
                        
                        // Stock Badge
                        if product.stock > 0 {
                            HStack(spacing: 4) {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 12))
                                Text("Stokta")
                                    .font(.system(size: 12, weight: .medium))
                            }
                            .foregroundColor(Color(hex: "10b981"))
                            .padding(.horizontal, 10)
                            .padding(.vertical, 6)
                            .background(Color(hex: "10b981").opacity(0.1))
                            .cornerRadius(8)
                        } else {
                            HStack(spacing: 4) {
                                Image(systemName: "xmark.circle.fill")
                                    .font(.system(size: 12))
                                Text("Tükendi")
                                    .font(.system(size: 12, weight: .medium))
                            }
                            .foregroundColor(Color(hex: "ef4444"))
                            .padding(.horizontal, 10)
                            .padding(.vertical, 6)
                            .background(Color(hex: "ef4444").opacity(0.1))
                            .cornerRadius(8)
                        }
                    }
                }
                
                Spacer()
                
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.secondary.opacity(0.6))
            }
            .padding(18)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(18)
            .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Product Detail View
struct ProductDetailView: View {
    let product: Product
    var onAddToCart: (() -> Void)? = nil
    @Environment(\.dismiss) var dismiss
    @EnvironmentObject var cartViewModel: CartViewModel
    @State private var quantity: Int = 1
    @State private var showSuccessMessage = false
    
    var body: some View {
        ScrollView {
            VStack(spacing: 24) {
                // Product Images - Çoklu görsel desteği
                if let imageURLs = product.imageURLs, !imageURLs.isEmpty {
                    // Çoklu görsel varsa TabView ile carousel göster
                    TabView {
                        ForEach(imageURLs.indices, id: \.self) { index in
                            let imageURL = imageURLs[index]
                            AsyncImage(url: URL(string: imageURL)) { phase in
                                switch phase {
                                case .success(let image):
                                    image
                                        .resizable()
                                        .aspectRatio(contentMode: .fit)
                                        .frame(maxWidth: .infinity)
                                        .frame(maxHeight: 400)
                                case .failure(_):
                                    // Hata durumunda placeholder göster
                                    ZStack {
                                        Rectangle()
                                            .fill(
                                                LinearGradient(
                                                    colors: [Color(hex: "6366f1").opacity(0.2), Color(hex: "8b5cf6").opacity(0.1)],
                                                    startPoint: .topLeading,
                                                    endPoint: .bottomTrailing
                                                )
                                            )
                                        Image(systemName: "bag.fill")
                                            .font(.system(size: 48))
                                            .foregroundColor(Color(hex: "6366f1"))
                                    }
                                    .frame(height: 300)
                                case .empty:
                                    // Yükleniyor
                                    ZStack {
                                        Rectangle()
                                            .fill(Color(UIColor.secondarySystemBackground))
                                        ProgressView()
                                    }
                                    .frame(height: 300)
                                @unknown default:
                                    EmptyView()
                                }
                            }
                        }
                    }
                    .tabViewStyle(.page)
                    .frame(height: 400)
                    .clipShape(RoundedRectangle(cornerRadius: 20))
                } else if let imagePath = product.imageURL ?? product.imagePath {
                    // Tek görsel (geriye dönük uyumluluk)
                    // Eğer zaten tam URL ise direkt kullan, değilse fullImageURL ile formatla
                    let finalImageURL = (imagePath.hasPrefix("http://") || imagePath.hasPrefix("https://")) 
                        ? imagePath 
                        : (APIService.fullImageURL(from: imagePath) ?? imagePath)
                    
                    AsyncImage(url: URL(string: finalImageURL)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fit)
                                .frame(maxWidth: .infinity)
                                .frame(maxHeight: 400)
                        case .failure(_):
                            // Hata durumunda placeholder göster
                            ZStack {
                                Rectangle()
                                    .fill(
                                        LinearGradient(
                                            colors: [Color(hex: "6366f1").opacity(0.2), Color(hex: "8b5cf6").opacity(0.1)],
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                    )
                                Image(systemName: "bag.fill")
                                    .font(.system(size: 48))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                            .frame(height: 300)
                        case .empty:
                            // Yükleniyor
                            ZStack {
                                Rectangle()
                                    .fill(Color(UIColor.secondarySystemBackground))
                                ProgressView()
                                    .tint(Color(hex: "6366f1"))
                            }
                        @unknown default:
                            EmptyView()
                        }
                    }
                    .frame(maxWidth: .infinity)
                    .frame(maxHeight: 400)
                    .clipShape(RoundedRectangle(cornerRadius: 20))
                    .onAppear {
                        #if DEBUG
                        print("🖼️ Product Detail Image - imagePath: \(imagePath), final URL: \(finalImageURL)")
                        #endif
                    }
                }
                
                // Product Info
                VStack(alignment: .leading, spacing: 16) {
                    Text(product.name)
                        .font(.system(size: 28, weight: .bold))
                        .foregroundColor(.primary)
                    
                    if let description = product.description, !description.isEmpty {
                        Text(description)
                            .font(.system(size: 16))
                            .foregroundColor(.secondary)
                            .lineSpacing(4)
                    }
                    
                    Divider()
                    
                    // Price Section
                    VStack(alignment: .leading, spacing: 8) {
                        // Sadece toplam fiyatı göster (komisyonlar gizlendi)
                        if product.totalPrice != nil {
                            Text(product.formattedTotalPrice)
                                .font(.system(size: 32, weight: .bold))
                                .foregroundColor(Color(hex: "6366f1"))
                        } else {
                            Text(product.formattedPrice)
                                .font(.system(size: 32, weight: .bold))
                                .foregroundColor(Color(hex: "6366f1"))
                        }
                    }
                    
                    Divider()
                    
                    // Stock Info
                    HStack {
                        Label(product.stock > 0 ? "Stokta Var" : "Stokta Yok", systemImage: product.stock > 0 ? "checkmark.circle.fill" : "xmark.circle.fill")
                            .font(.system(size: 16, weight: .medium))
                            .foregroundColor(product.stock > 0 ? Color(hex: "10b981") : Color(hex: "ef4444"))
                        
                        if product.stock > 0 {
                            Text("(\(product.stock) adet)")
                                .font(.system(size: 14))
                                .foregroundColor(.secondary)
                        }
                    }
                    
                    // Category
                    if !product.category.isEmpty {
                        HStack {
                            Label(product.category, systemImage: "tag.fill")
                                .font(.system(size: 14, weight: .medium))
                                .foregroundColor(Color(hex: "6366f1"))
                                .padding(.horizontal, 12)
                                .padding(.vertical, 6)
                                .background(Color(hex: "6366f1").opacity(0.1))
                                .cornerRadius(8)
                        }
                    }
                    
                    Divider()
                    
                    // Yasal Bilgilendirmeler
                    VStack(alignment: .leading, spacing: 12) {
                        Text("Ürün Bilgileri")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.primary)
                        
                        // Fiyat Bilgilendirme (KDV Dahil)
                        HStack {
                            Image(systemName: "info.circle.fill")
                                .font(.system(size: 14))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("Fiyatlar KDV dahildir.")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        // Teslimat Bilgisi - Stant Teslimatı
                        HStack {
                            Image(systemName: "mappin.circle.fill")
                                .font(.system(size: 14))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("Stant teslimatı - Topluluk stantından alınacak")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        // Garanti Bilgisi
                        HStack {
                            Image(systemName: "checkmark.shield.fill")
                                .font(.system(size: 14))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("2 yıl garanti")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        // İade Hakkı
                        HStack {
                            Image(systemName: "arrow.uturn.backward.circle.fill")
                                .font(.system(size: 14))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("14 gün içinde iade hakkı")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                    }
                    .padding(.vertical, 8)
                    
                    Divider()
                    
                    // Yasal Linkler
                    VStack(alignment: .leading, spacing: 8) {
                        Text("Yasal Bilgiler")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.primary)
                        
                        Link(destination: URL(string: "https://foursoftware.net/marketing/stand-delivery-contract.php")!) {
                            HStack {
                                Text("Stant Teslimat Sözleşmesi")
                                    .font(.system(size: 13))
                                    .foregroundColor(Color(hex: "6366f1"))
                                Spacer()
                                Image(systemName: "arrow.up.right.square")
                                    .font(.system(size: 12))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                        
                        Link(destination: URL(string: "https://foursoftware.net/marketing/cancellation-refund.php")!) {
                            HStack {
                                Text("İptal & İade Koşulları")
                                    .font(.system(size: 13))
                                    .foregroundColor(Color(hex: "6366f1"))
                                Spacer()
                                Image(systemName: "arrow.up.right.square")
                                    .font(.system(size: 12))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                        
                        Link(destination: URL(string: "https://foursoftware.net/marketing/privacy-policy.php")!) {
                            HStack {
                                Text("Gizlilik Politikası")
                                    .font(.system(size: 13))
                                    .foregroundColor(Color(hex: "6366f1"))
                                Spacer()
                                Image(systemName: "arrow.up.right.square")
                                    .font(.system(size: 12))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                        
                        Link(destination: URL(string: "https://foursoftware.net/marketing/terms-of-use.php")!) {
                            HStack {
                                Text("Kullanım Şartları")
                                    .font(.system(size: 13))
                                    .foregroundColor(Color(hex: "6366f1"))
                                Spacer()
                                Image(systemName: "arrow.up.right.square")
                                    .font(.system(size: 12))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                    }
                    .padding(.vertical, 8)
                    
                    Divider()
                    
                    // Satıcı Bilgileri
                    VStack(alignment: .leading, spacing: 12) {
                        Text("Satıcı Bilgileri")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.primary)
                        
                        HStack {
                            Image(systemName: "bag.fill")
                                .font(.system(size: 14))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("Topluluk ID: \(product.communityId)")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                        }
                        
                        Text("Bu ürün bir topluluk tarafından satılmaktadır. Ürünle ilgili sorularınız için topluluk sayfasından iletişime geçebilirsiniz.")
                            .font(.system(size: 12))
                            .foregroundColor(.secondary)
                            .lineSpacing(2)
                    }
                    .padding(.vertical, 8)
                }
                .padding(.horizontal, 20)
            }
            .padding(.bottom, 100)
        }
        .navigationTitle("Ürün Detayı")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                Button("Kapat") {
                    dismiss()
                }
            }
        }
        .safeAreaInset(edge: .bottom) {
            if product.isAvailable {
                VStack(spacing: 12) {
                    // Quantity Selector
                    HStack {
                        Text("Adet:")
                            .font(.system(size: 15, weight: .medium))
                            .foregroundColor(.secondary)
                        
                        Spacer()
                        
                        HStack(spacing: 16) {
                            Button(action: {
                                if quantity > 1 {
                                    quantity -= 1
                                }
                            }) {
                                Image(systemName: "minus.circle.fill")
                                    .font(.system(size: 24))
                                    .foregroundColor(quantity > 1 ? Color(hex: "6366f1") : .gray)
                            }
                            .disabled(quantity <= 1)
                            
                            Text("\(quantity)")
                                .font(.system(size: 18, weight: .semibold))
                                .foregroundColor(.primary)
                                .frame(minWidth: 40)
                            
                            Button(action: {
                                if quantity < product.stock {
                                    quantity += 1
                                }
                            }) {
                                Image(systemName: "plus.circle.fill")
                                    .font(.system(size: 24))
                                    .foregroundColor(quantity < product.stock ? Color(hex: "6366f1") : .gray)
                            }
                            .disabled(quantity >= product.stock)
                        }
                    }
                    .padding(.horizontal, 20)
                    
                    // Action Buttons
                    HStack(spacing: 12) {
                        // Sepete Ekle
                        Button(action: {
                            cartViewModel.addItem(product, quantity: quantity)
                            let generator = UINotificationFeedbackGenerator()
                            generator.notificationOccurred(.success)
                            showSuccessMessage = true
                            // Sepete eklendiğinde callback'i çağır
                            onAddToCart?()
                            DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                                showSuccessMessage = false
                            }
                        }) {
                            HStack {
                                Image(systemName: cartViewModel.isInCart(product.id) ? "checkmark.cart.fill" : "cart.badge.plus")
                                Text(cartViewModel.isInCart(product.id) ? "Sepette" : "Sepete Ekle")
                                    .font(.system(size: 16, weight: .semibold))
                            }
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(
                                LinearGradient(
                                    colors: [Color(hex: "10b981"), Color(hex: "059669")],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(14)
                        }
                        
                        // Satın Al
                        Button(action: {
                            cartViewModel.addItem(product, quantity: quantity)
                            let generator = UINotificationFeedbackGenerator()
                            generator.notificationOccurred(.success)
                            // Sepete eklendiğinde callback'i çağır
                            onAddToCart?()
                            // TODO: Ödeme sayfasına yönlendir
                        }) {
                            HStack {
                                Image(systemName: "creditcard.fill")
                                Text("Satın Al")
                                    .font(.system(size: 16, weight: .semibold))
                            }
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(14)
                        }
                    }
                    .padding(.horizontal, 20)
                    .padding(.bottom, 20)
                }
                .background(Color(UIColor.systemBackground))
                .overlay(
                    Group {
                        if showSuccessMessage {
                            VStack {
                                HStack {
                                    Image(systemName: "checkmark.circle.fill")
                                        .foregroundColor(Color(hex: "10b981"))
                                    Text("Sepete eklendi!")
                                        .font(.system(size: 14, weight: .semibold))
                                        .foregroundColor(.primary)
                                }
                                .padding(.horizontal, 16)
                                .padding(.vertical, 10)
                                .background(Color(UIColor.secondarySystemBackground))
                                .cornerRadius(12)
                                .shadow(color: Color.black.opacity(0.1), radius: 8, x: 0, y: 4)
                                .transition(.move(edge: .top).combined(with: .opacity))
                                Spacer()
                            }
                            .padding(.top, 12)
                        }
                    }
                )
            }
        }
    }
}

// MARK: - Board Tab
struct BoardTab: View {
    @ObservedObject var viewModel: CommunityDetailViewModel
    
    var body: some View {
        Group {
            if viewModel.isLoadingBoard && viewModel.boardMembers.isEmpty {
                VStack(spacing: 20) {
                    ProgressView()
                        .scaleEffect(1.2)
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if let error = viewModel.boardError {
                VStack(spacing: 20) {
                    Image(systemName: "exclamationmark.circle.fill")
                        .font(.system(size: 56))
                        .foregroundColor(Color(hex: "ef4444"))
                    Text("Yükleme Hatası")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(error)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                    Button(action: {
                        Task {
                            await viewModel.refreshBoard()
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
                .padding(.top, 80)
            } else if viewModel.boardMembers.isEmpty && viewModel.hasLoadedBoard {
                VStack(spacing: 16) {
                    Image(systemName: "person.2.slash")
                        .font(.system(size: 56))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text("Yönetim Kurulu Bulunmuyor")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                    Text("Bu topluluk için henüz yönetim kurulu bilgisi kaydedilmemiştir.")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.top, 80)
            } else if !viewModel.filteredBoardMembers.isEmpty || !viewModel.boardSearchText.isEmpty {
                VStack(spacing: 16) {
                    // Search Bar
                    HStack {
                        Image(systemName: "magnifyingglass")
                            .foregroundColor(.gray.opacity(0.5))
                        TextField("Yönetici ara...", text: $viewModel.boardSearchText)
                            .textFieldStyle(PlainTextFieldStyle())
                    }
                    .padding(12)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)

                    if viewModel.filteredBoardMembers.isEmpty {
                        VStack(spacing: 16) {
                            Image(systemName: "magnifyingglass")
                                .font(.system(size: 40))
                                .foregroundColor(.secondary.opacity(0.5))
                            Text("Sonuç Bulunamadı")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundColor(.secondary)
                        }
                        .padding(.top, 40)
                    } else {
                        LazyVStack(spacing: 12) {
                            ForEach(viewModel.filteredBoardMembers) { boardMember in
                                ProfessionalBoardMemberCard(boardMember: boardMember)
                                    .onAppear {
                                        // Son 3 yönetim kurulu üyesinden birine gelindiğinde daha fazla yükle
                                        if let index = viewModel.filteredBoardMembers.firstIndex(where: { $0.id == boardMember.id }),
                                           index >= viewModel.filteredBoardMembers.count - 3,
                                           viewModel.hasMoreBoardMembers,
                                           viewModel.boardSearchText.isEmpty {
                                            Task {
                                                await viewModel.loadMoreBoardMembers()
                                            }
                                        }
                                    }
                            }
                            
                            // Loading indicator (eğer daha fazla varsa)
                            if viewModel.hasMoreBoardMembers && viewModel.boardSearchText.isEmpty {
                                HStack {
                                    Spacer()
                                    ProgressView()
                                        .padding()
                                    Spacer()
                                }
                            }
                        }
                    }
                }
            }
        }
        .refreshable {
            await viewModel.refreshBoard()
        }
    }
}

// MARK: - Board Member Card
struct BoardMemberCard: View {
    let boardMember: BoardMember
    
    var body: some View {
        HStack(spacing: 16) {
            // Avatar
            ZStack {
                Circle()
                    .fill(
                        LinearGradient(
                            colors: [Color(hex: "f59e0b"), Color(hex: "ec4899")],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .frame(width: 60, height: 60)
                
                Image(systemName: "person.badge.shield.checkmark.fill")
                    .font(.system(size: 28))
                    .foregroundColor(.white)
            }
            
            // Info
            VStack(alignment: .leading, spacing: 6) {
                Text(boardMember.fullName ?? "İsimsiz")
                    .font(.system(size: 18, weight: .bold))
                    .foregroundColor(.primary)
                
                if !boardMember.role.isEmpty {
                    Text(boardMember.role)
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(Color(hex: "f59e0b"))
                        .padding(.horizontal, 12)
                        .padding(.vertical, 4)
                        .background(Color(hex: "f59e0b").opacity(0.1))
                        .cornerRadius(8)
                }
            }
            
            Spacer()
        }
        .padding(20)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(20)
        .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
    }
}

// MARK: - Membership Status Card
struct MembershipStatusCard: View {
    let community: Community
    let membershipStatus: MembershipStatus?
    let isLoading: Bool
    let onJoin: () -> Void
    
    var body: some View {
        Group {
            if isLoading {
                HStack {
                    ProgressView()
                        .tint(Color(hex: "6366f1"))
                    Text("Yükleniyor...")
                        .font(.system(size: 15, weight: .medium))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity)
                .padding()
                .background(Color(UIColor.secondarySystemBackground))
                .cornerRadius(16)
                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
            } else if let status = membershipStatus {
                // ÜYE İSE: Üyelik durumu kartı göster, buton YOK
                if status.isMember || status.status == "member" || status.status == "approved" {
                    Button(action: {}) {
                        HStack(spacing: 12) {
                            Image(systemName: "checkmark.circle.fill")
                                .font(.system(size: 20))
                                .foregroundColor(.white)
                            Text("Bu Topluluğa Üyesiniz")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.white)
                            Spacer()
                            Image(systemName: "checkmark")
                                .font(.system(size: 14, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .padding()
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "10b981"), Color(hex: "059669")],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                        .shadow(color: Color(hex: "10b981").opacity(0.3), radius: 8, x: 0, y: 4)
                    }
                    .buttonStyle(PlainButtonStyle())
                    .disabled(true)
                } else if status.isPending || status.status == "pending" {
                    // BEKLEMEDE İSE: Beklemede mesajı göster
                    Button(action: {}) {
                        HStack(spacing: 12) {
                            Image(systemName: "clock.fill")
                                .font(.system(size: 20))
                                .foregroundColor(.white)
                            Text("Üyelik Başvurunuz Beklemede")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.white)
                            Spacer()
                            Image(systemName: "hourglass")
                                .font(.system(size: 14, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .padding()
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "f59e0b"), Color(hex: "d97706")],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                        .shadow(color: Color(hex: "f59e0b").opacity(0.3), radius: 8, x: 0, y: 4)
                    }
                    .buttonStyle(PlainButtonStyle())
                    .disabled(true)
                } else {
                    // ÜYE DEĞİLSE: Buton göster
                    Button(action: onJoin) {
                        HStack(spacing: 12) {
                            Image(systemName: "person.badge.plus.fill")
                                .font(.system(size: 20))
                                .foregroundColor(.white)
                            Text("Topluluğa Katıl")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.white)
                            Spacer()
                            Image(systemName: "arrow.right")
                                .font(.system(size: 14, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .padding()
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                        .shadow(color: Color(hex: "6366f1").opacity(0.3), radius: 8, x: 0, y: 4)
                    }
                    .buttonStyle(PlainButtonStyle())
                }
            } else {
                // Durum yüklenemedi - varsayılan olarak üye değil, buton göster
                Button(action: onJoin) {
                    HStack(spacing: 12) {
                        Image(systemName: "person.badge.plus.fill")
                            .font(.system(size: 20))
                            .foregroundColor(.white)
                        Text("Topluluğa Katıl")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.white)
                        Spacer()
                        Image(systemName: "arrow.right")
                            .font(.system(size: 14, weight: .bold))
                            .foregroundColor(.white)
                    }
                    .padding()
                    .background(
                        LinearGradient(
                            colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .cornerRadius(16)
                    .shadow(color: Color(hex: "6366f1").opacity(0.3), radius: 8, x: 0, y: 4)
                }
                .buttonStyle(PlainButtonStyle())
            }
        }
    }
}

// MARK: - Join Community Sheet
struct JoinCommunitySheet: View {
    let community: Community
    let onSuccess: () -> Void
    @Environment(\.dismiss) var dismiss
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var fullName = ""
    @State private var email = ""
    @State private var phoneNumber = ""
    @State private var studentId = ""
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var successMessage: String?
    
    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Ad Soyad *", text: $fullName)
                    TextField("Email", text: $email)
                        .keyboardType(.emailAddress)
                        .autocapitalization(.none)
                    TextField("Telefon", text: $phoneNumber)
                        .keyboardType(.phonePad)
                        .onChange(of: phoneNumber) { newValue in
                            // Telefon numarasını otomatik formatla
                            // Sadece rakamları al
                            let digits = newValue.replacingOccurrences(of: "[^0-9]", with: "", options: .regularExpression)
                            
                            // Maksimum 11 karakter (0 + 10 rakam)
                            let limitedDigits = String(digits.prefix(11))
                            
                            // Eğer sadece rakamlar değiştiyse, formatlanmış halini göster
                            if limitedDigits != newValue {
                                // Formatlanmış numarayı göster
                                if let formatted = InputValidator.formatPhoneNumber(limitedDigits) {
                                    phoneNumber = "0\(formatted)"
                                } else if limitedDigits.count == 10 && limitedDigits.hasPrefix("5") {
                                    phoneNumber = "0\(limitedDigits)"
                                } else {
                                    phoneNumber = limitedDigits
                                }
                            }
                        }
                    TextField("Öğrenci No", text: $studentId)
                        .keyboardType(.numberPad)
                } header: {
                    Text("Üyelik Bilgileri")
                } footer: {
                    Text("Ad soyad zorunludur. Telefon numarası otomatik formatlanacaktır (örn: 05551234567).")
                }
                
                if let errorMessage = errorMessage {
                    Section {
                        Text(errorMessage)
                            .foregroundColor(.red)
                            .font(.system(size: 14))
                    }
                }
                
                if let successMessage = successMessage {
                    Section {
                        Text(successMessage)
                            .foregroundColor(.green)
                            .font(.system(size: 14))
                    }
                }
            }
            .navigationTitle("Topluluğa Katıl")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("İptal") {
                        dismiss()
                    }
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    if isLoading {
                        ProgressView()
                            .tint(Color(hex: "6366f1"))
                    } else {
                        Button("Gönder") {
                            #if DEBUG
                            print("🔄 Gönder butonuna basıldı")
                            #endif
                            Task {
                                await submitJoinRequest()
                            }
                        }
                        .disabled(fullName.isEmpty)
                    }
                }
            }
            .task {
                // Load current user info if available
                if APIService.shared.getAuthToken() != nil {
                    do {
                        let user = try await APIService.shared.getCurrentUser()
                        fullName = user.displayName
                        email = user.email
                        phoneNumber = user.phoneNumber ?? ""
                        studentId = user.studentId ?? ""
                    } catch {
                        #if DEBUG
                        print("User bilgisi yüklenemedi: \(error.localizedDescription)")
                        #endif
                    }
                }
            }
        }
    }
    
    private func submitJoinRequest() async {
        isLoading = true
        errorMessage = nil
        successMessage = nil
        
        guard !fullName.isEmpty else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = "Lütfen ad soyad bilgisini girin"
            isLoading = false
            return
        }
        
        // Token kontrolü - eğer token yoksa hata göster
        guard let token = APIService.shared.getAuthToken(), !token.isEmpty else {
            errorMessage = "Giriş yapmanız gerekiyor. Lütfen giriş yapın."
            isLoading = false
            #if DEBUG
            print("⚠️ Token bulunamadı veya boş (token süresiz - logout yapılmıyor)")
            print("   AuthViewModel.isAuthenticated: \(authViewModel.isAuthenticated)")
            print("   SecureStorage.isTokenValid(): \(SecureStorage.shared.isTokenValid())")
            #endif
            // Token süresiz - token yoksa bile logout yapma
            // Kullanıcı manuel logout yapabilir
            return
        }
        
        // Telefon numarasını formatla (otomatik düzeltme)
        var formattedPhone: String? = nil
        if !phoneNumber.isEmpty {
            formattedPhone = InputValidator.formatPhoneNumber(phoneNumber)
            if formattedPhone == nil {
                // Formatlanamazsa hata göster
                errorMessage = "Geçersiz telefon numarası formatı. Lütfen Türkiye telefon numarası girin (örn: 05551234567 veya 5551234567)"
                isLoading = false
                return
            }
            #if DEBUG
            print("📞 Telefon numarası formatlandı: \(phoneNumber) -> \(formattedPhone ?? "nil")")
            #endif
        }
        
        #if DEBUG
        print("🔄 Topluluğa katılma başvurusu gönderiliyor: \(community.id)")
        print("🔑 Token kontrolü: \(token.prefix(8))... (uzunluk: \(token.count))")
        print("   AuthViewModel.isAuthenticated: \(authViewModel.isAuthenticated)")
        print("   SecureStorage.isTokenValid(): \(SecureStorage.shared.isTokenValid())")
        print("   Formatted phone: \(formattedPhone ?? "nil")")
        #endif
        
        do {
            let status = try await APIService.shared.requestMembership(communityId: community.id)
            
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            // MembershipStatus başarılı ise
            if status.isPending || status.status == "pending" {
                successMessage = "Üyelik başvurunuz başarıyla alındı! Onay sürecinde size bilgi verilecektir."
                #if DEBUG
                print("✅ Üyelik başvurusu başarılı: \(status.status)")
                #endif
                DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                    dismiss()
                    onSuccess()
                }
            } else if status.isMember || status.status == "member" || status.status == "approved" {
                successMessage = "Topluluğa başarıyla katıldınız!"
                #if DEBUG
                print("✅ Üyelik onaylandı: \(status.status)")
                #endif
                DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                    dismiss()
                    onSuccess()
                }
            } else {
                errorMessage = "Üyelik başvurusu alınamadı. Lütfen tekrar deneyin."
                isLoading = false
                #if DEBUG
                print("⚠️ Üyelik başvurusu yanıtı beklenmeyen durum: \(status.status)")
                #endif
            }
        } catch {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            if let apiError = error as? APIError {
                switch apiError {
                case .apiError(let message):
                    errorMessage = message
                case .httpError(let code):
                    errorMessage = "Sunucu hatası (\(code)). Lütfen daha sonra tekrar deneyin."
                case .unauthorized:
                    // Yetkilendirme hatası - token süresiz, logout yapma
                    errorMessage = "Yetkilendirme hatası. Lütfen tekrar deneyin."
                    #if DEBUG
                    print("⚠️ Yetkilendirme hatası (token korunuyor)")
                    #endif
                    // Token süresiz - otomatik logout yapma
                case .notFound:
                    errorMessage = "Topluluk bulunamadı."
                default:
                    errorMessage = "Bir hata oluştu: \(error.localizedDescription)"
                }
            } else {
                errorMessage = "Bağlantı hatası. İnternet bağlantınızı kontrol edin."
            }
            #if DEBUG
            print("❌ Üyelik başvurusu gönderilemedi: \(error.localizedDescription)")
            print("   Error type: \(type(of: error))")
            if let apiError = error as? APIError {
                print("   API Error details: \(apiError.errorDescription ?? "N/A")")
            }
            #endif
            isLoading = false
        }
    }
}


// MARK: - Professional Event Card
struct ProfessionalEventCard: View {
    let event: Event
    var onTap: (() -> Void)? = nil
    
    // Static haptic generator - performans optimizasyonu
    private static let hapticGenerator = UIImpactFeedbackGenerator(style: .light)
    
    var body: some View {
        Button(action: {
            Self.hapticGenerator.impactOccurred()
            onTap?()
        }) {
        HStack(spacing: 16) {
            // Date Section
            VStack(spacing: 4) {
                Text(event.monthAbbreviation)
                    .font(.system(size: 11, weight: .semibold))
                    .foregroundColor(.secondary)
                Text(event.dayNumber)
                    .font(.system(size: 28, weight: .bold))
                    .foregroundColor(.primary)
            }
            .frame(width: 70)
            .padding(.vertical, 16)
            .background(
                RoundedRectangle(cornerRadius: 14)
                    .fill(Color(UIColor.secondarySystemBackground))
            )
            
            // Content
            VStack(alignment: .leading, spacing: 10) {
                Text(event.title)
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(.primary)
                    .lineLimit(2)
                    .multilineTextAlignment(.leading)
                
                HStack(spacing: 16) {
                    Label(event.formattedTime, systemImage: "clock.fill")
                        .font(.system(size: 14))
                        .foregroundColor(.secondary)
                    
                    if let location = event.location {
                        Label(location, systemImage: "mappin.circle.fill")
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .lineLimit(1)
                    }
                }
                
                // Category Badge
                HStack(spacing: 6) {
                    Image(systemName: event.category.icon)
                        .font(.system(size: 11))
                    Text(event.category.rawValue)
                        .font(.system(size: 12, weight: .medium))
                }
                .foregroundColor(event.category.color)
                .padding(.horizontal, 10)
                .padding(.vertical, 5)
                .background(event.category.color.opacity(0.1))
                .cornerRadius(8)
            }
            
            Spacer()
            
            Image(systemName: "chevron.right")
                .font(.system(size: 14, weight: .semibold))
                .foregroundColor(.secondary.opacity(0.6))
        }
        .padding(18)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(18)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Professional Campaign Card
struct ProfessionalCampaignCard: View {
    let campaign: Campaign
    let isSaved: Bool
    var onTap: (() -> Void)? = nil
    
    var body: some View {
        Button(action: {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            onTap?()
        }) {
            VStack(spacing: 0) {
                // Header with gradient background
                HStack(spacing: 16) {
                    // Content
                    VStack(alignment: .leading, spacing: 8) {
                        HStack(spacing: 8) {
                            Text(campaign.title)
                                .font(.system(size: 18, weight: .bold))
                                .foregroundColor(.white)
                                .lineLimit(2)
                                .multilineTextAlignment(.leading)
                            
                            Spacer()
                            
                            // Discount Badge - Modern tasarım
                        if let discount = campaign.discountPercentage, discount > 0 {
                                VStack(spacing: 2) {
                                Text("%\(Int(discount))")
                                        .font(.system(size: 16, weight: .bold))
                                    .foregroundColor(.white)
                                Text("İNDİRİM")
                                        .font(.system(size: 8, weight: .bold))
                                        .foregroundColor(.white.opacity(0.9))
                            }
                                .padding(.horizontal, 10)
                                .padding(.vertical, 6)
                            .background(
                                    RoundedRectangle(cornerRadius: 8)
                                        .fill(
                                            LinearGradient(
                                                colors: [Color(hex: "f59e0b"), Color(hex: "f97316")],
                                                startPoint: .topLeading,
                                                endPoint: .bottomTrailing
                                            )
                                        )
                                )
                                .shadow(color: Color.black.opacity(0.2), radius: 4, x: 0, y: 2)
                            }
                        }
                        
                        if let description = campaign.shortDescription, !description.isEmpty {
                            Text(description)
                                .font(.system(size: 14, weight: .regular))
                                .foregroundColor(.white.opacity(0.9))
                                .lineLimit(2)
                        }
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }
                .padding(20)
                .background(
                    LinearGradient(
                        colors: [
                            Color(hex: "6366f1"),
                            Color(hex: "7c3aed"),
                            Color(hex: "8b5cf6")
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                
                // Footer with details
                HStack(spacing: 12) {
                    // Time Remaining
                    if campaign.daysRemaining > 0 {
                        HStack(spacing: 6) {
                            Image(systemName: "clock.fill")
                                .font(.system(size: 12, weight: .semibold))
                            Text("\(campaign.daysRemaining) gün kaldı")
                                .font(.system(size: 13, weight: .medium))
                        }
                        .foregroundColor(campaign.daysRemaining <= 7 ? Color(hex: "ef4444") : Color(hex: "6366f1"))
                        .padding(.horizontal, 10)
                        .padding(.vertical, 6)
                        .background(
                            (campaign.daysRemaining <= 7 ? Color(hex: "ef4444") : Color(hex: "6366f1"))
                                .opacity(0.1)
                        )
                        .cornerRadius(8)
                    }
                    
                    Spacer()
                    
                    // Category Badge
                    HStack(spacing: 4) {
                        Image(systemName: "tag.fill")
                            .font(.system(size: 10))
                        Text(campaign.category.rawValue)
                            .font(.system(size: 12, weight: .medium))
                    }
                    .foregroundColor(Color(hex: "6366f1"))
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(Color(hex: "6366f1").opacity(0.1))
                    .cornerRadius(6)
                    
                    // Arrow
                    Image(systemName: "chevron.right")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.secondary.opacity(0.6))
                }
                .padding(.horizontal, 20)
                .padding(.vertical, 14)
                .background(Color(UIColor.secondarySystemBackground))
            }
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(16)
            .shadow(color: Color.black.opacity(0.08), radius: 8, x: 0, y: 4)
            .overlay(
                RoundedRectangle(cornerRadius: 16)
                    .stroke(Color.gray.opacity(0.1), lineWidth: 0.5)
            )
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Professional Board Member Card
struct ProfessionalBoardMemberCard: View {
    let boardMember: BoardMember
    
    var body: some View {
        HStack(spacing: 16) {
            // Avatar
            ZStack {
                Circle()
                    .fill(
                        LinearGradient(
                            colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .frame(width: 64, height: 64)
                
                if let photoPath = boardMember.photoPath, !photoPath.isEmpty {
                    let imageURL = APIService.fullImageURL(from: photoPath) ?? photoPath
                    AsyncImage(url: URL(string: imageURL)) { image in
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                    } placeholder: {
                        Text((boardMember.fullName ?? "Ü").prefix(1).uppercased())
                            .font(.system(size: 24, weight: .bold))
                            .foregroundColor(.white)
                    }
                    .frame(width: 64, height: 64)
                    .clipShape(Circle())
                } else {
                    Text((boardMember.fullName ?? "Ü").prefix(1).uppercased())
                        .font(.system(size: 24, weight: .bold))
                        .foregroundColor(.white)
                }
            }
            
            // Info
            VStack(alignment: .leading, spacing: 8) {
                Text(boardMember.fullName ?? "İsimsiz")
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundColor(.primary)
                
                if !boardMember.role.isEmpty {
                    Text(boardMember.role)
                        .font(.system(size: 14, weight: .medium))
                        .foregroundColor(Color(hex: "6366f1"))
                        .padding(.horizontal, 12)
                        .padding(.vertical, 5)
                        .background(Color(hex: "6366f1").opacity(0.1))
                        .cornerRadius(8)
                }
                
                // Email göster
                if let email = boardMember.email ?? boardMember.contactEmail, !email.isEmpty {
                    HStack(spacing: 6) {
                        Image(systemName: "envelope.fill")
                            .font(.system(size: 12))
                            .foregroundColor(.secondary)
                        Text(email)
                            .font(.system(size: 13))
                            .foregroundColor(.secondary)
                    }
                }
                
                if let bio = boardMember.bio, !bio.isEmpty {
                    Text(bio)
                        .font(.system(size: 13))
                        .foregroundColor(.secondary)
                        .lineLimit(2)
                }
            }
            
            Spacer()
        }
        .padding(18)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(18)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
    }
}

// MARK: - Skeleton Views for Community Detail
struct EventCardSkeleton: View {
    var body: some View {
        HStack(spacing: 16) {
            // Date Badge Skeleton
            SkeletonView()
                .frame(width: 60, height: 80)
                .cornerRadius(12)
            
            // Content Skeleton
            VStack(alignment: .leading, spacing: 8) {
                SkeletonView()
                    .frame(height: 18)
                    .cornerRadius(4)
                
                SkeletonView()
                    .frame(height: 18)
                    .frame(maxWidth: .infinity)
                    .cornerRadius(4)
                
                HStack(spacing: 12) {
                    SkeletonView()
                        .frame(width: 80, height: 14)
                        .cornerRadius(4)
                    
                    SkeletonView()
                        .frame(width: 100, height: 14)
                        .cornerRadius(4)
                }
            }
            
            Spacer()
        }
        .padding(16)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
    }
}

