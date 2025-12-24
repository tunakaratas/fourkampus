//
//  ContentView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import UserNotifications

private struct PendingEventLink: Equatable {
    let communityId: String
    let eventId: String
}

// MARK: - Main App View
struct ContentView: View {
    @EnvironmentObject var authViewModel: AuthViewModel
    @StateObject private var communitiesVM = CommunitiesViewModel()
    @StateObject private var eventsVM = EventsViewModel()
    @StateObject private var campaignsVM = CampaignsViewModel()
    @StateObject private var profileVM = ProfileViewModel()
    @StateObject private var marketVM = MarketViewModel()
    @StateObject private var cartVM = CartViewModel()
    @Environment(\.scenePhase) private var scenePhase
    
    @State private var selectedTab = 0
    @State private var communitiesNavigationPath = NavigationPath()
    @State private var eventsNavigationPath = NavigationPath()
    @State private var campaignsNavigationPath = NavigationPath()
    @State private var marketNavigationPath = NavigationPath()
    @State private var deepLinkCommunity: Community? = nil
    @State private var deepLinkEvent: Event? = nil
    @State private var showQRScanner = false
    @State private var pendingCommunityId: String?
    @State private var pendingEventLink: PendingEventLink?
    @State private var didRequestCommunityReloadForDeepLink = false
    @State private var didRequestEventReloadForDeepLink = false
    @AppStorage("hasShownNotificationPermission") private var hasShownNotificationPermission = false
    @State private var showNotificationPermission = false
    
    var body: some View {
        mainTabView
    }
    
    // MARK: - Tab Views (Extracted for Compiler Performance)
    private var communitiesTab: some View {
        NavigationStack(path: $communitiesNavigationPath) {
            CommunitiesView(
                viewModel: communitiesVM,
                navigationPath: $communitiesNavigationPath,
                showQRScanner: $showQRScanner
            )
            .environmentObject(cartVM)
        }
        .tabItem {
            Label("Topluluklar", systemImage: "house.fill")
        }
        .tag(0)
    }
    
    private var eventsTab: some View {
        NavigationStack(path: $eventsNavigationPath) {
            EventsView(
                viewModel: eventsVM,
                navigationPath: $eventsNavigationPath,
                verificationInfoProvider: { communityId in
                    communitiesVM.verificationInfo(for: communityId)
                }
            )
            .onAppear {
                eventsVM.selectedUniversity = communitiesVM.selectedUniversity
            }
            .onChange(of: communitiesVM.selectedUniversity) { newUniversity in
                eventsVM.selectedUniversity = newUniversity
            }
        }
        .tabItem {
            Label("Etkinlikler", systemImage: "calendar")
        }
        .tag(1)
    }
    
    private var marketTab: some View {
        NavigationStack(path: $marketNavigationPath) {
            MarketView(
                verificationInfoProvider: { communityId in
                    communitiesVM.verificationInfo(for: communityId)
                }
            )
            .environmentObject(marketVM)
            .environmentObject(cartVM)
            .onAppear {
                marketVM.availableCommunities = communitiesVM.communities
                marketVM.selectedUniversity = communitiesVM.selectedUniversity?.name
            }
            .onChange(of: communitiesVM.communities) { newCommunities in
                marketVM.availableCommunities = newCommunities
            }
            .onChange(of: communitiesVM.selectedUniversity) { newUniversity in
                marketVM.selectedUniversity = newUniversity?.name
            }
        }
        .tabItem {
            Label("Market", systemImage: "bag.fill")
        }
        .tag(2)
    }
    
    private var campaignsTab: some View {
        NavigationStack(path: $campaignsNavigationPath) {
            CampaignsView(navigationPath: $campaignsNavigationPath)
        }
        .tabItem {
            Label("Kampanyalar", systemImage: "tag.fill")
        }
        .tag(3)
    }
    
    private var profileTab: some View {
        NavigationStack {
            ProfileView(viewModel: profileVM)
        }
        .tabItem {
            Label("Hesabım", systemImage: "person.fill")
        }
        .tag(4)
    }
    
    private var mainTabView: some View {
        TabView(selection: $selectedTab) {
            communitiesTab
            eventsTab
            marketTab
            campaignsTab
            profileTab
        }
        .onChange(of: selectedTab) { newTab in
            Task {
                switch newTab {
                case 0:
                    await communitiesVM.refreshCommunities()
                case 1:
                    await eventsVM.refreshEvents(universityId: nil)
                case 2:
                    await marketVM.refreshProducts()
                case 3:
                    await campaignsVM.refreshCampaigns()
                default:
                    break
                }
            }
        }
        .onChange(of: scenePhase) { newPhase in
            if newPhase == .active {
                #if DEBUG
                print("📱 App became active - Refreshing data...")
                #endif
                Task {
                    await communitiesVM.backgroundRefresh()
                    await eventsVM.backgroundRefresh()
                    await campaignsVM.backgroundRefresh()
                }
            }
        }

        // Üniversite filtresi tüm sekmelerde ortak olsun
        .environmentObject(communitiesVM)

        .tint(Color(hex: "6366f1"))
        .onOpenURL { url in
            handleDeepLink(url: url)
        }
        .onReceive(NotificationCenter.default.publisher(for: NSNotification.Name("ShowNotificationPermission"))) { _ in
            if !hasShownNotificationPermission {
                showNotificationPermission = true
            }
        }
        .sheet(isPresented: $showNotificationPermission) {
            PermissionRequestView(
                permissionType: .notifications,
                onAllow: {
                    requestNotificationPermission()
                    hasShownNotificationPermission = true
                    showNotificationPermission = false
                },
                onSkip: {
                    hasShownNotificationPermission = true
                    showNotificationPermission = false
                }
            )
        }
        .onChange(of: pendingCommunityId) { _ in
            resolvePendingCommunityDeepLink()
        }
        .onChange(of: pendingEventLink) { _ in
            resolvePendingEventDeepLink(forceReload: true)
        }
        .onChange(of: communitiesVM.communities) { _ in
            resolvePendingCommunityDeepLink()
        }
        .onChange(of: eventsVM.displayedEvents.map(\.id)) { _ in
            resolvePendingEventDeepLink(forceReload: false)
        }
        .sheet(isPresented: $showQRScanner) {
            QRScannerView(
                onScanResult: { result in
                    // Scanner zaten kapanmış olacak (QRScannerView içinde)
                    // Sadece sonucu işle
                    handleQRScanResult(result)
                },
                onDismiss: { 
                    showQRScanner = false 
                }
            )
        }
        .networkStatus() // Network connectivity monitoring
        .task {
            // Network durumunu kontrol et
            if !NetworkMonitor.shared.isNetworkAvailable() {
                #if DEBUG
                print("⚠️ Network not available, data loading may fail")
                #endif
            }
            
            // Uygulama açıldığında tüm verileri otomatik yükle
            #if DEBUG
            print("🚀 Uygulama başlatılıyor, veriler yükleniyor...")
            #endif
            
            // Öncelikli verileri önce yükle (kullanıcı görmeden önce)
            await withTaskGroup(of: Void.self) { group in
                // En önemli veriler - öncelikli
                group.addTask(priority: .userInitiated) {
                    await communitiesVM.loadUniversities()
                }
                group.addTask(priority: .userInitiated) {
                    await communitiesVM.loadCommunities()
                }
                
                // İkinci öncelik - arka planda yüklenecek
                group.addTask(priority: .utility) {
                    await eventsVM.loadEvents()
                }
                
                // Düşük öncelik - kullanıcı görmeden yüklenebilir
                group.addTask(priority: .background) {
                    await AdManager.shared.fetchAds()
                }
                group.addTask(priority: .background) {
                    await profileVM.loadUser()
                }
            }
            
            #if DEBUG
            print("✅ Tüm veriler yüklendi!")
            #endif
        }
    }
}

// MARK: - Communities View
struct CommunitiesView: View {
    @ObservedObject var viewModel: CommunitiesViewModel
    @Binding var navigationPath: NavigationPath
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var cartViewModel: CartViewModel
    @State private var showFilters = false
    @Binding var showQRScanner: Bool
    @State private var showLoginModal = false
    
    var body: some View {
        // Empty state kontrolü: API'den veri geldi ama filteredCommunities boşsa empty state göster
        // ÖNEMLİ: API'den gelen veriler zaten üniversite filtresiyle filtrelenmiş, bu yüzden communities.isEmpty kontrolü yeterli
        let isEmptyState = viewModel.communities.isEmpty && !viewModel.isLoading && viewModel.hasInitiallyLoaded
        
        #if DEBUG
        let _ = print("🔍 CommunitiesView body - isEmptyState: \(isEmptyState), communities.count: \(viewModel.communities.count), filteredCommunities.count: \(viewModel.filteredCommunities.count), isLoading: \(viewModel.isLoading), hasInitiallyLoaded: \(viewModel.hasInitiallyLoaded)")
        #endif
        
        return ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            if isEmptyState {
                // Boş durum - yükleme tamamlandı ama veri yok
                ScrollView {
                    VStack(spacing: 0) {
                        // Search and Filters
                        VStack(spacing: 16) {
                            SearchAndSortSection(
                                searchText: $viewModel.searchText,
                                sortBy: $viewModel.sortOption
                            )
                            
                            // "Yalnızca Üyesi Olduğum Topluluklar" Filtre Butonu
                            if authViewModel.isAuthenticated {
                                Button(action: {
                                    let generator = UIImpactFeedbackGenerator(style: .light)
                                    generator.impactOccurred()
                                    
                                    // Toggle'ı hemen yap, arka planda yükle (kullanıcı deneyimi için)
                                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                        viewModel.showOnlyMyCommunities.toggle()
                                    }
                                    
                                    // Arka planda üyelik durumlarını yükle (eğer henüz yüklenmemişse)
                                    if viewModel.memberCommunityIds.isEmpty && !viewModel.isLoadingMembershipStatuses {
                                        Task {
                                            await viewModel.loadMembershipStatuses(forceRefresh: false)
                                        }
                                    }
                                }) {
                                    let primaryColor = Color(hex: "6366f1")
                                    HStack(spacing: 8) {
                                        if viewModel.isLoadingMembershipStatuses {
                                            ProgressView()
                                                .scaleEffect(0.8)
                                                .tint(primaryColor)
                                        } else {
                                            Image(systemName: viewModel.showOnlyMyCommunities ? "checkmark.circle.fill" : "circle")
                                                .font(.system(size: 16, weight: .semibold))
                                                .foregroundColor(viewModel.showOnlyMyCommunities ? primaryColor : .secondary)
                                        }
                                        Text("Yalnızca Üyesi Olduğum Topluluklar")
                                            .font(.system(size: 14, weight: .medium))
                                            .foregroundColor(.primary)
                                        Spacer()
                                        if viewModel.showOnlyMyCommunities {
                                            Group {
                                                if viewModel.isLoadingMembershipStatuses {
                                                    ProgressView()
                                                        .scaleEffect(0.6)
                                                        .tint(.white)
                                                } else {
                                                    Text("\(viewModel.memberCommunityIds.count)")
                                                        .font(.system(size: 12, weight: .semibold))
                                                        .foregroundColor(.white)
                                                }
                                            }
                                            .padding(.horizontal, 8)
                                            .padding(.vertical, 4)
                                            .background(primaryColor)
                                            .cornerRadius(8)
                                        }
                                    }
                                    .padding(.horizontal, 16)
                                    .padding(.vertical, 12)
                                    .background(
                                        RoundedRectangle(cornerRadius: 12)
                                            .fill(viewModel.showOnlyMyCommunities ? primaryColor.opacity(0.1) : Color(UIColor.secondarySystemBackground))
                                    )
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 12)
                                            .stroke(viewModel.showOnlyMyCommunities ? primaryColor : Color.clear, lineWidth: 1.5)
                                    )
                                }
                                .buttonStyle(PlainButtonStyle())
                            }
                            
                            CategoryFilterChips(
                                selectedCategories: $viewModel.selectedCategories
                            )
                        }
                        .padding(.horizontal, 16)
                        .padding(.top, 16)
                        .padding(.bottom, 24)
                        
                        // Empty State
                        if let error = viewModel.errorMessage, !error.isEmpty {
                            VStack(spacing: 12) {
                                Text("Topluluk bulunamadı")
                                    .font(.system(size: 18, weight: .semibold))
                                Text(error)
                                    .font(.system(size: 14))
                                    .foregroundColor(.red)
                                    .multilineTextAlignment(.center)
                                    .padding(.horizontal)
                                Button("Yeniden Dene") {
                                    Task {
                                        await viewModel.loadCommunities()
                                    }
                                }
                                .buttonStyle(.borderedProminent)
                            }
                            .padding(.top, 64)
                        } else {
                            EmptyStateView(
                                icon: "magnifyingglass",
                                title: "Topluluk bulunamadı",
                                message: viewModel.selectedUniversity == nil
                                    ? "Arama kriterlerinize uygun topluluk bulunamadı."
                                    : "Seçili üniversitede arama kriterlerinize uygun topluluk bulunamadı."
                            )
                            .padding(.top, 64)
                        }
                    }
                    .padding(.bottom, 100)
                }
            } else {
                ScrollView {
                    VStack(spacing: 0) {
                        // Search and Filters
                        VStack(spacing: 16) {
                            SearchAndSortSection(
                                searchText: $viewModel.searchText,
                                sortBy: $viewModel.sortOption
                            )
                            
                            // "Yalnızca Üyesi Olduğum Topluluklar" Filtre Butonu
                            if authViewModel.isAuthenticated {
                                Button(action: {
                                    let generator = UIImpactFeedbackGenerator(style: .light)
                                    generator.impactOccurred()
                                    
                                    // Toggle'ı hemen yap, arka planda yükle (kullanıcı deneyimi için)
                                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                        viewModel.showOnlyMyCommunities.toggle()
                                    }
                                    
                                    // Arka planda üyelik durumlarını yükle (eğer henüz yüklenmemişse)
                                    if viewModel.memberCommunityIds.isEmpty && !viewModel.isLoadingMembershipStatuses {
                                        Task {
                                            await viewModel.loadMembershipStatuses(forceRefresh: false)
                                        }
                                    }
                                }) {
                                    let primaryColor = Color(hex: "6366f1")
                                    HStack(spacing: 8) {
                                        if viewModel.isLoadingMembershipStatuses {
                                            ProgressView()
                                                .scaleEffect(0.8)
                                                .tint(primaryColor)
                                        } else {
                                            Image(systemName: viewModel.showOnlyMyCommunities ? "checkmark.circle.fill" : "circle")
                                                .font(.system(size: 16, weight: .semibold))
                                                .foregroundColor(viewModel.showOnlyMyCommunities ? primaryColor : .secondary)
                                        }
                                        Text("Yalnızca Üyesi Olduğum Topluluklar")
                                            .font(.system(size: 14, weight: .medium))
                                            .foregroundColor(.primary)
                                        Spacer()
                                        if viewModel.showOnlyMyCommunities {
                                            Group {
                                                if viewModel.isLoadingMembershipStatuses {
                                                    ProgressView()
                                                        .scaleEffect(0.6)
                                                        .tint(.white)
                                                } else {
                                                    Text("\(viewModel.memberCommunityIds.count)")
                                                        .font(.system(size: 12, weight: .semibold))
                                                        .foregroundColor(.white)
                                                }
                                            }
                                            .padding(.horizontal, 8)
                                            .padding(.vertical, 4)
                                            .background(primaryColor)
                                            .cornerRadius(8)
                                        }
                                    }
                                    .padding(.horizontal, 16)
                                    .padding(.vertical, 12)
                                    .background(
                                        RoundedRectangle(cornerRadius: 12)
                                            .fill(viewModel.showOnlyMyCommunities ? primaryColor.opacity(0.1) : Color(UIColor.secondarySystemBackground))
                                    )
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 12)
                                            .stroke(viewModel.showOnlyMyCommunities ? primaryColor : Color.clear, lineWidth: 1.5)
                                    )
                                }
                                .buttonStyle(PlainButtonStyle())
                            }
                            
                            // Category Filter Chips
                            CategoryFilterChips(
                                selectedCategories: $viewModel.selectedCategories
                            )
                        }
                        .padding(.horizontal, 16)
                        .padding(.top, 16)
                        .padding(.bottom, 24)
                        
                        // Results Info
                        if !viewModel.searchText.isEmpty || !viewModel.selectedCategories.isEmpty || viewModel.showOnlyMyCommunities {
                            HStack {
                                Image(systemName: "info.circle.fill")
                                    .foregroundColor(Color(hex: "6366f1"))
                                Text("\(viewModel.filteredCommunities.count) topluluk bulundu")
                                    .font(.system(size: 14, weight: .medium))
                                    .foregroundColor(.secondary)
                            }
                            .padding(.horizontal, 16)
                            .padding(.bottom, 16)
                        }
                        
                        // Skeleton Loading - Sadece kartlar için (arama ve filtreler görünür)
                        // İlk yüklemede veya yükleme devam ederken skeleton göster
                        // ÖNEMLİ: hasInitiallyLoaded false ise VEYA (isLoading true VE communities boş) ise skeleton göster
                        // Tab değişiminde veri varsa skeleton gösterme
                        if (!viewModel.hasInitiallyLoaded && viewModel.communities.isEmpty) || (viewModel.isLoading && viewModel.communities.isEmpty) {
                            LazyVStack(spacing: 16) {
                                ForEach(0..<6) { _ in
                                    CommunityCardSkeleton()
                                }
                            }
                            .padding(.horizontal, 16)
                            .padding(.bottom, 100)
                        }
                        // Error State - Sadece gerçek hata varsa, veri yoksa ve ilk yükleme tamamlandıysa göster
                        // Refresh sırasında hata oluşursa ve önceden veri varsa, error state gösterilmez
                        // ÖNEMLİ: Tab değişiminde veri varsa error state gösterme
                        else if viewModel.communities.isEmpty && !viewModel.isLoading && viewModel.hasInitiallyLoaded {
                            if let error = viewModel.errorMessage, !error.isEmpty {
                                VStack(spacing: 12) {
                                    Text("Topluluk bulunamadı")
                                        .font(.system(size: 18, weight: .semibold))
                                    Text(error)
                                        .font(.system(size: 14))
                                        .foregroundColor(.red)
                                        .multilineTextAlignment(.center)
                                        .padding(.horizontal)
                                    Button("Yeniden Dene") {
                                        Task {
                                            // Yeniden deneme için hasInitiallyLoaded'i false yap
                                            viewModel.hasInitiallyLoaded = false
                                            await viewModel.loadCommunities(forceReload: true)
                                        }
                                    }
                                    .buttonStyle(.borderedProminent)
                                }
                                .padding(.top, 64)
                            } else {
                                // Hata mesajı yoksa ama veri de yoksa, genel empty state göster
                                EmptyStateView(
                                    icon: "person.3.fill",
                                    title: "Topluluk bulunamadı",
                                    message: viewModel.selectedUniversity == nil
                                        ? "Henüz hiç topluluk eklenmemiş."
                                        : "Seçili üniversitede topluluk bulunamadı."
                                )
                                .padding(.top, 64)
                            }
                        }
                        // Communities Grid - Sadece ilk yükleme tamamlandıysa ve veri varsa ama filtreleme sonucu boşsa empty state göster
                        else if viewModel.filteredCommunities.isEmpty && viewModel.hasInitiallyLoaded && !viewModel.communities.isEmpty {
                            EmptyStateView(
                                icon: "magnifyingglass",
                                title: "Topluluk bulunamadı",
                                message: viewModel.selectedUniversity == nil
                                    ? "Arama kriterlerinize uygun topluluk bulunamadı."
                                    : "Seçili üniversitede arama kriterlerinize uygun topluluk bulunamadı."
                            )
                            .padding(.top, 64)
                        } else if !viewModel.filteredCommunities.isEmpty {
                            CommunitiesGrid(
                                communities: viewModel.filteredCommunities,
                                favoriteIds: viewModel.favoriteIds,
                                navigationPath: $navigationPath,
                                isAuthenticated: authViewModel.isAuthenticated,
                                verificationInfoProvider: { community in
                                    viewModel.verificationInfo(for: community.id)
                                },
                                onFavoriteToggle: { id in
                                    if authViewModel.isAuthenticated {
                                        Task {
                                            await viewModel.toggleFavorite(id)
                                        }
                                    }
                                },
                                onLoginRequired: {
                                    showLoginModal = true
                                },
                                viewModel: viewModel
                            )
                            .padding(.bottom, 100)
                        }
                    }
                }
                .refreshable {
                    await viewModel.refreshCommunities()
                }
            }
        }
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .principal) {
                HStack(spacing: 8) {
                    Image("LogoHeader")
                        .resizable()
                        .aspectRatio(contentMode: .fit)
                        .frame(width: 44, height: 44) // Daha büyük
                    Text("Four Kampüs")
                        .font(.system(size: 20, weight: .bold))
                        .foregroundColor(.primary)
                }
            }
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 12) {
                    // QR Scanner Button
                    Button(action: {
                        showQRScanner = true
                    }) {
                        Image(systemName: "qrcode.viewfinder")
                            .font(.system(size: 18))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    UniversitySelectorButton(viewModel: viewModel)
                }
            }
        }
        .navigationDestination(for: Community.self) { community in
            CommunityDetailView(
                community: community,
                verificationInfo: viewModel.verificationInfo(for: community.id)
            )
                .environmentObject(cartViewModel)
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .task {
            // View görünür olduğunda toplulukları yükle - SADECE ilk yükleme için
            // Tab değişiminde tekrar yükleme yapma - veri zaten var
            guard !viewModel.hasInitiallyLoaded || viewModel.communities.isEmpty else {
                #if DEBUG
                print("📱 CommunitiesView task: Veri zaten yüklü, yükleme atlanıyor")
                #endif
                // Veri zaten yüklüyse bile reklamları yükle (eğer yüklenmemişse)
                if AdManager.shared.ads.isEmpty && !AdManager.shared.isLoading {
                    await AdManager.shared.fetchAds()
                }
                return
            }
            
            #if DEBUG
            print("📱 CommunitiesView task: Topluluklar yükleniyor... (universityId: \(viewModel.selectedUniversity?.id ?? "nil"))")
            #endif
            
            // Toplulukları ve reklamları paralel yükle
            async let communitiesTask: Void = viewModel.loadCommunities()
            async let adsTask: Void = AdManager.shared.fetchAds()
            
            await communitiesTask
            await adsTask
        }
        .onChange(of: viewModel.selectedUniversity) { newUniversity in
            // Üniversite değişince toplulukları yeniden yükle
            Task { @MainActor in
                // YENİ SİSTEM: Üniversite filtresi kaldırıldı - sadece UI state'i güncelle
                // API'ye istek gönderilmiyor, client-side filtreleme yapılacak
                #if DEBUG
                print("🔄 CommunitiesView.onChange: Üniversite değişti - ID: \(newUniversity?.id ?? "nil (Tümü)") - Sadece UI state güncellendi (API filtresi kaldırıldı)")
                #endif
                
                // Üniversite değiştiğinde sadece client-side filtreleme yapılacak
                // API'ye istek gönderilmiyor, filteredCommunities computed property otomatik güncellenecek
            }
        }
        .onChange(of: authViewModel.isAuthenticated) { newValue in
            if newValue {
                // Kullanıcı giriş yaptığında üyelik durumlarını yükle
                if !viewModel.communities.isEmpty {
                    Task {
                        await viewModel.loadMembershipStatuses(forceRefresh: true)
                    }
                }
            } else {
                // Çıkış yapıldığında cache'i temizle
                viewModel.memberCommunityIds = []
                viewModel.showOnlyMyCommunities = false
            }
        }
        .task(id: viewModel.isLoading) {
            // isLoading değiştiğinde kontrol et - eğer 20 saniye boyunca true ise false yap
            if viewModel.isLoading {
                #if DEBUG
                print("⏱️ CommunitiesView: isLoading true, 20 saniye timeout başlatılıyor...")
                #endif
                try? await Task.sleep(nanoseconds: 5_000_000_000) // 5 saniye (optimize edildi)
                if viewModel.isLoading {
                    #if DEBUG
                    print("⚠️ CommunitiesView: 20 saniye geçti ama hala loading, isLoading = false yapılıyor")
                    #endif
                    await MainActor.run {
                        viewModel.isLoading = false
                        if !viewModel.hasInitiallyLoaded {
                            viewModel.errorMessage = "Topluluklar yüklenemedi. Lütfen tekrar deneyin."
                        }
                    }
                }
            }
        }
    }
}

// MARK: - Search and Sort Section
struct SearchAndSortSection: View {
    @Binding var searchText: String
    @Binding var sortBy: CommunitiesViewModel.SortOption
    @FocusState private var isSearchFocused: Bool
    
    var body: some View {
        HStack(spacing: 12) {
            // Search Box
            HStack(spacing: 12) {
                Image(systemName: "magnifyingglass")
                    .foregroundColor(Color.gray.opacity(0.5))
                    .font(.system(size: 16))
                
                TextField("Topluluk ara...", text: $searchText)
                    .focused($isSearchFocused)
                    .textFieldStyle(PlainTextFieldStyle())
                    .font(.system(size: 15))
                
                if !searchText.isEmpty {
                    Button(action: {
                        withAnimation(.spring(response: 0.3)) {
                            searchText = ""
                        }
                        let generator = UISelectionFeedbackGenerator()
                        generator.selectionChanged()
                    }) {
                        Image(systemName: "xmark.circle.fill")
                            .foregroundColor(Color.gray.opacity(0.5))
                            .font(.system(size: 18))
                    }
                    .transition(.scale.combined(with: .opacity))
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(12)
            .shadow(
                color: isSearchFocused ? Color(hex: "6366f1").opacity(0.2) : Color.black.opacity(0.05),
                radius: isSearchFocused ? 8 : 2,
                x: 0,
                y: 1
            )
            .animation(.spring(response: 0.3), value: isSearchFocused)
            
            // Sort Menu
            Menu {
                ForEach(CommunitiesViewModel.SortOption.allCases, id: \.self) { option in
                    Button(action: {
                        withAnimation(.spring(response: 0.3)) {
                            sortBy = option
                        }
                        let generator = UISelectionFeedbackGenerator()
                        generator.selectionChanged()
                    }) {
                        HStack {
                            Text(option.rawValue)
                            if sortBy == option {
                                Spacer()
                                Image(systemName: "checkmark")
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                    }
                }
            } label: {
                HStack(spacing: 6) {
                    Text(sortBy.rawValue)
                        .font(.system(size: 14, weight: .medium))
                    Image(systemName: "chevron.down")
                        .font(.system(size: 11))
                }
                .foregroundColor(.primary)
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(Color(UIColor.secondarySystemBackground))
                .cornerRadius(12)
                .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)
            }
        }
    }
}

// MARK: - Category Filter Chips
struct CategoryFilterChips: View {
    @Binding var selectedCategories: Set<String>
    private let maxSelection = 3
    
    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 12) {
                // All Categories Chip
                CategoryChip(
                    title: "Tümü",
                    icon: "square.grid.2x2",
                    isSelected: selectedCategories.isEmpty,
                    color: Color(hex: "6366f1")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        selectedCategories.removeAll()
                    }
                }
                
                ForEach(Community.availableCategories, id: \.self) { category in
                    CategoryChip(
                        title: category,
                        icon: Community.icon(for: category),
                        isSelected: selectedCategories.contains(category),
                        color: Community.color(for: category)
                    ) {
                        withAnimation(.spring(response: 0.3)) {
                            if selectedCategories.contains(category) {
                                selectedCategories.remove(category)
                            } else {
                                // Max 3 kategori seçilebilir
                                if selectedCategories.count < maxSelection {
                                    selectedCategories.insert(category)
                                }
                            }
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
        }
    }
}

// MARK: - Category Chip
struct CategoryChip: View {
    let title: String
    let icon: String
    let isSelected: Bool
    let color: Color
    let action: () -> Void
    
    var body: some View {
        Button(action: {
            let generator = UISelectionFeedbackGenerator()
            generator.selectionChanged()
            action()
        }) {
            HStack(spacing: 6) {
                Image(systemName: icon)
                    .font(.system(size: 12, weight: .semibold))
                Text(title)
                    .font(.system(size: 14, weight: .medium))
            }
            .foregroundColor(isSelected ? .white : .primary)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(isSelected ? color : Color(UIColor.secondarySystemBackground))
            .cornerRadius(20)
            .shadow(
                color: isSelected ? color.opacity(0.3) : Color.black.opacity(0.05),
                radius: isSelected ? 8 : 2,
                x: 0,
                y: 2
            )
        }
    }
}

// MARK: - Communities Grid
struct CommunitiesGrid: View {
    let communities: [Community]
    let favoriteIds: Set<String>
    @Binding var navigationPath: NavigationPath
    let isAuthenticated: Bool
    let verificationInfoProvider: (Community) -> VerifiedCommunityInfo?
    let onFavoriteToggle: (String) -> Void
    let onLoginRequired: () -> Void
    let viewModel: CommunitiesViewModel? // Lazy loading için
    
    // Reklam gösterim sıklığı: Her 3 topluluktan sonra 1 reklam
    private let adFrequency = 3
    
    var body: some View {
        LazyVStack(spacing: 16) {
            // Skeleton Loading - Yedek (ana view'da zaten gösteriliyor ama burada da göster)
            if let vm = viewModel, vm.isLoading && vm.communities.isEmpty && !vm.hasInitiallyLoaded {
                ForEach(0..<6) { _ in
                    CommunityCardSkeleton()
                }
            } else {
            ForEach(Array(communities.enumerated()), id: \.element.id) { index, community in
                let verificationInfo = verificationInfoProvider(community)
                CommunityCard(
                    community: community,
                    verificationInfo: verificationInfo,
                    isFavorite: favoriteIds.contains(community.id),
                    isAuthenticated: isAuthenticated,
                    selectedUniversity: viewModel?.selectedUniversity,
                    onFavoriteToggle: {
                        if isAuthenticated {
                            onFavoriteToggle(community.id)
                        } else {
                            onLoginRequired()
                        }
                    },
                    onTap: {
                        let generator = UIImpactFeedbackGenerator(style: .medium)
                        generator.impactOccurred()
                        
                        // NavigationPath güncellemesini bir sonraki run loop'a ertele
                        Task { @MainActor in
                            navigationPath.append(community)
                        }
                    }
                )
                .onAppear {
                    // Lazy loading - Son 3 item'dan birine gelindiğinde daha fazla yükle
                    if let vm = viewModel {
                        // filteredCommunities kullanıyoruz çünkü CommunitiesGrid'e filteredCommunities geçiliyor
                        let filteredCount = vm.filteredCommunities.count
                        
                        // Refresh veya yükleme sırasında lazy loading'i engelle
                        guard !vm.isLoading && !vm.isLoadingMore else {
                            return
                        }
                        
                        let shouldLoad = index >= filteredCount - 3 && vm.hasMoreCommunities
                        
                        #if DEBUG
                        if shouldLoad {
                            print("🔄 Lazy loading tetiklendi: index=\(index), filteredCount=\(filteredCount), hasMore=\(vm.hasMoreCommunities), isLoadingMore=\(vm.isLoadingMore), isLoading=\(vm.isLoading), totalCommunities=\(vm.communities.count)")
                        }
                        #endif
                        
                        if shouldLoad {
                            Task {
                                await vm.loadMoreCommunities()
                            }
                        }
                    }
                }
                
                // Her 3 topluluktan sonra reklam göster (sadece API'den reklam varsa)
                if (index + 1) % adFrequency == 0 && index < communities.count - 1 {
                    NativeAdCard()
                }
            }
            
            // Loading indicator (daha fazla yükleniyorsa)
            if let vm = viewModel, vm.isLoadingMore {
                HStack {
                    Spacer()
                    ProgressView()
                        .padding()
                    Spacer()
                }
            }
            }
        }
        .padding(.horizontal, 16)
    }
}

// MARK: - Community Card (iOS Modern Design)
struct CommunityCard: View {
    let community: Community
    let verificationInfo: VerifiedCommunityInfo?
    let isFavorite: Bool
    let isAuthenticated: Bool
    let selectedUniversity: University? // Üniversite seçiliyse nil, tümü seçiliyse nil değil
    let onFavoriteToggle: () -> Void
    var onTap: (() -> Void)? = nil
    @State private var showLogoDetail = false
    
    private var isVerified: Bool {
        (verificationInfo != nil) || community.isVerified
    }
    
    // Üniversite adını göster (sadece tümü seçiliyse ve university bilgisi varsa)
    private var shouldShowUniversity: Bool {
        selectedUniversity == nil && (community.university?.isEmpty == false)
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Main Content Section - Tek arka plan, tüm alanı kaplıyor
            ZStack(alignment: .topTrailing) {
                // Background with image or gradient - Tüm alanı kaplıyor
                if let imageURL = community.imageURL, !imageURL.isEmpty {
                    CachedAsyncImage(url: imageURL) { image in
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                    } placeholder: {
                        LinearGradient(
                            gradient: Gradient(colors: [
                                Color(hex: "8b5cf6"),
                                Color(hex: "6366f1")
                            ]),
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    }
                } else {
                    LinearGradient(
                        gradient: Gradient(colors: [
                            Color(hex: "8b5cf6"),
                            Color(hex: "6366f1")
                        ]),
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                }
                
                // Dark gradient overlay for text readability
                LinearGradient(
                    gradient: Gradient(colors: [
                        Color.black.opacity(0.6),
                        Color.black.opacity(0.3),
                        Color.clear
                    ]),
                    startPoint: .bottom,
                    endPoint: .top
                )
                
                // Content - VStack ile logo ve isimler alta yakın
                VStack(alignment: .leading, spacing: 0) {
                    Spacer() // Üstte boşluk bırak
                    
                    // Logo Section - Altta, sol tarafta
                    HStack(spacing: 0) {
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                            showLogoDetail = true
                        }) {
                            ZStack {
                                // Kare logo ortada
                        if let logoPath = community.logoPath, !logoPath.isEmpty {
                            CachedAsyncImage(url: logoPath) { image in
                                image
                                    .resizable()
                                            .aspectRatio(contentMode: .fill)
                            } placeholder: {
                                ZStack {
                                            RoundedRectangle(cornerRadius: 10)
                                                .fill(Color.white.opacity(0.2))
                                                .frame(width: 60, height: 60)
                                    Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                                .font(.system(size: 24, weight: .medium))
                                        .foregroundColor(.white)
                                }
                            }
                                    .frame(width: 60, height: 60)
                                    .clipShape(RoundedRectangle(cornerRadius: 10))
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 10)
                                            .stroke(Color.white.opacity(0.3), lineWidth: 1.5)
                                    )
                                    .shadow(color: Color.black.opacity(0.2), radius: 6, x: 0, y: 3)
                        } else {
                            ZStack {
                                        RoundedRectangle(cornerRadius: 10)
                                            .fill(Color.white.opacity(0.2))
                                            .frame(width: 60, height: 60)
                                Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                            .font(.system(size: 24, weight: .medium))
                                    .foregroundColor(.white)
                            }
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 10)
                                            .stroke(Color.white.opacity(0.3), lineWidth: 1.5)
                                    )
                                    .shadow(color: Color.black.opacity(0.2), radius: 6, x: 0, y: 3)
                        }
                            }
                        }
                        .buttonStyle(PlainButtonStyle())
                        .frame(width: 100)
                        
                        Spacer()
                    }
                    
                    // Info Section - Altta, sol taraftan başlıyor
                    VStack(alignment: .leading, spacing: 8) {
                        // Name - Kutunun en solundan başlıyor, logonun altında
                    HStack(spacing: 5) {
                        Text(community.name)
                            .font(.system(size: 16, weight: .bold, design: .rounded))
                            .foregroundColor(.white)
                            .lineLimit(1)
                            .truncationMode(.tail)
                            .shadow(color: Color.black.opacity(0.3), radius: 2, x: 0, y: 1)
                        
                        if isVerified {
                            Image("BlueTick")
                                .resizable()
                                .frame(width: 18, height: 18)
                                .accessibilityLabel("Onaylı topluluk")
                        }
                        Spacer()
                    }
                    
                    // Description (eğer varsa)
                    if !community.description.isEmpty {
                        Text(community.description)
                            .font(.system(size: 12, weight: .regular))
                            .foregroundColor(.white.opacity(0.9))
                            .lineLimit(2)
                            .shadow(color: Color.black.opacity(0.3), radius: 2, x: 0, y: 1)
                    }
                }
                    .padding(.horizontal, 16)
                    .padding(.top, 8) // Logo ile isim arası boşluk
                .padding(.bottom, 12)
                }
                
                // Favorite Button - Top Right
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.impactOccurred()
                    onFavoriteToggle()
                }) {
                    Image(systemName: isFavorite ? "heart.fill" : "heart")
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(isFavorite ? Color(hex: "ec4899") : (isAuthenticated ? .white : Color(hex: "8b5cf6")))
                        .padding(10)
                        .background(
                            Circle()
                                .fill(.ultraThinMaterial)
                        )
                }
                .buttonStyle(PlainButtonStyle())
                .padding(12)
            }
            .frame(height: 140)
            .clipped()
            
            // Stats Section
            VStack(spacing: 0) {
                HStack(spacing: 0) {
                    CompactStatItem(
                        icon: "person.2.fill",
                        value: formatCount(community.memberCount),
                        color: Color(hex: "8b5cf6")
                    )
                    
                    Divider()
                        .frame(height: 32)
                        .background(Color.gray.opacity(0.2))
                    
                    CompactStatItem(
                        icon: "calendar",
                        value: formatCount(community.eventCount),
                        color: Color(hex: "8b5cf6")
                    )
                    
                    Divider()
                        .frame(height: 32)
                        .background(Color.gray.opacity(0.2))
                    
                    CompactStatItem(
                        icon: "tag.fill",
                        value: formatCount(community.campaignCount),
                        color: Color(hex: "8b5cf6")
                    )
                }
                .padding(.vertical, 10)
                
                // Üniversite adı (sadece tümü seçiliyse göster)
                if shouldShowUniversity {
                    Divider()
                        .background(Color.gray.opacity(0.2))
                    
                    HStack(spacing: 6) {
                        Image(systemName: "building.2.fill")
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(Color(hex: "8b5cf6"))
                        
                        Text(community.university ?? "")
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(Color(UIColor.secondaryLabel))
                            .lineLimit(1)
                            .truncationMode(.tail)
                        
                        Spacer()
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 8)
                }
            }
            .background(Color(UIColor.secondarySystemBackground))
        }
        .clipShape(RoundedRectangle(cornerRadius: 16))
        .shadow(color: Color.black.opacity(0.08), radius: 8, x: 0, y: 4)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(Color.gray.opacity(0.1), lineWidth: 0.5)
        )
        .contentShape(Rectangle())
        .onTapGesture {
            let generator = UIImpactFeedbackGenerator(style: .medium)
            generator.impactOccurred()
            onTap?()
        }
        .sheet(isPresented: $showLogoDetail) {
            LogoDetailView(community: community)
        }
    }
    
    private func formatCount(_ count: Int) -> String {
        if count >= 1000 {
            return String(format: "%.1fK", Double(count) / 1000.0)
        }
        return "\(count)"
    }
}

// MARK: - Logo Detail View
struct LogoDetailView: View {
    let community: Community
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            ZStack {
                Color(UIColor.systemBackground)
                    .ignoresSafeArea()
                
                VStack(spacing: 24) {
                    if let logoPath = community.logoPath, !logoPath.isEmpty {
                        CachedAsyncImage(url: logoPath) { image in
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fit)
                        } placeholder: {
                            ZStack {
                                RoundedRectangle(cornerRadius: 20)
                                    .fill(
                                        LinearGradient(
                                            colors: [Color(hex: "8b5cf6"), Color(hex: "6366f1")],
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                    )
                                    .frame(width: 200, height: 200)
                                Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                    .font(.system(size: 80, weight: .medium))
                                    .foregroundColor(.white)
                            }
                        }
                        .frame(maxWidth: 300, maxHeight: 300)
                        .clipShape(RoundedRectangle(cornerRadius: 20))
                        .shadow(color: Color.black.opacity(0.2), radius: 20, x: 0, y: 10)
                    } else {
                        ZStack {
                            RoundedRectangle(cornerRadius: 20)
                                .fill(
                                    LinearGradient(
                                        colors: [Color(hex: "8b5cf6"), Color(hex: "6366f1")],
                                        startPoint: .topLeading,
                                        endPoint: .bottomTrailing
                                    )
                                )
                                .frame(width: 200, height: 200)
                            Image(systemName: Community.icon(for: community.categories.first ?? ""))
                                .font(.system(size: 80, weight: .medium))
                                .foregroundColor(.white)
                        }
                        .shadow(color: Color.black.opacity(0.2), radius: 20, x: 0, y: 10)
                    }
                    
                    Text(community.name)
                        .font(.system(size: 24, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                }
                .padding()
            }
            .navigationTitle("Logo")
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

// MARK: - Native Ad Card (Topluluk Boxu ile Birebir Aynı Format)
struct NativeAdCard: View {
    @ObservedObject private var adManager = AdManager.shared
    @State private var adData: AdData?
    @State private var showLogoDetail = false
    
    var body: some View {
        Group {
            if let ad = adData {
                VStack(alignment: .leading, spacing: 0) {
                // Main Content Section - Tek arka plan, tüm alanı kaplıyor
                ZStack(alignment: .topTrailing) {
                        // Background with image or gradient - Tüm alanı kaplıyor
                        if let imageURLString = ad.imageURL, !imageURLString.isEmpty {
                            // URL'i oluştur - CommunityCard ile aynı mantık
                            let imageURL: String? = {
                                if imageURLString.hasPrefix("http://") || imageURLString.hasPrefix("https://") {
                                    var correctedURL = imageURLString
                                    #if targetEnvironment(simulator)
                                    correctedURL = correctedURL.replacingOccurrences(of: "http://localhost", with: "http://127.0.0.1")
                                    correctedURL = correctedURL.replacingOccurrences(of: "https://localhost", with: "https://127.0.0.1")
                                    #endif
                                    return correctedURL
                                } else {
                                    let baseURL = AppConfig.shared.imageBaseURL
                                    let cleanPath = imageURLString.hasPrefix("/") ? imageURLString : "/\(imageURLString)"
                                    return "\(baseURL)\(cleanPath)"
                                }
                            }()
                            
                            if let imageURL = imageURL {
                                CachedAsyncImage(url: imageURL) { image in
                                    image
                                        .resizable()
                                        .aspectRatio(contentMode: .fill)
                                } placeholder: {
                                    Color(hex: "6366f1")
                                }
                            } else {
                                Color(hex: "6366f1")
                            }
                        } else {
                            Color(hex: "6366f1")
                        }
                        
                        // Dark gradient overlay for text readability
                        LinearGradient(
                            gradient: Gradient(colors: [
                                Color.black.opacity(0.6),
                                Color.black.opacity(0.3),
                                Color.clear
                            ]),
                            startPoint: .bottom,
                            endPoint: .top
                        )
                        
                        // Content - VStack ile logo ve isimler alta yakın
                        VStack(alignment: .leading, spacing: 0) {
                            Spacer() // Üstte boşluk bırak
                            
                            // Logo Section - Altta, sol tarafta
                            HStack(spacing: 0) {
                                Button(action: {
                                    let generator = UIImpactFeedbackGenerator(style: .light)
                                    generator.impactOccurred()
                                    showLogoDetail = true
                                }) {
                                    ZStack {
                                        // Kare logo ortada
                                        if let logoURLString = ad.logoURL, !logoURLString.isEmpty {
                                            // URL'i oluştur - CommunityCard ile aynı mantık
                                            let logoPath: String = {
                                                if logoURLString.hasPrefix("http://") || logoURLString.hasPrefix("https://") {
                                                    var correctedURL = logoURLString
                                                    #if targetEnvironment(simulator)
                                                    correctedURL = correctedURL.replacingOccurrences(of: "http://localhost", with: "http://127.0.0.1")
                                                    correctedURL = correctedURL.replacingOccurrences(of: "https://localhost", with: "https://127.0.0.1")
                                                    #endif
                                                    return correctedURL
                                                } else {
                                                    let baseURL = AppConfig.shared.imageBaseURL
                                                    let cleanPath = logoURLString.hasPrefix("/") ? logoURLString : "/\(logoURLString)"
                                                    return "\(baseURL)\(cleanPath)"
                                                }
                                            }()
                                            
                                            CachedAsyncImage(url: logoPath) { image in
                                                image
                                                    .resizable()
                                                    .aspectRatio(contentMode: .fill)
                                            } placeholder: {
                                                ZStack {
                                                    RoundedRectangle(cornerRadius: 10)
                                                        .fill(Color.white.opacity(0.2))
                                                        .frame(width: 60, height: 60)
                                                    Image(systemName: "megaphone.fill")
                                                        .font(.system(size: 24, weight: .medium))
                                                        .foregroundColor(.white)
                                                }
                                            }
                                            .frame(width: 60, height: 60)
                                            .clipShape(RoundedRectangle(cornerRadius: 10))
                                            .overlay(
                                                RoundedRectangle(cornerRadius: 10)
                                                    .stroke(Color.white.opacity(0.3), lineWidth: 1.5)
                                            )
                                            .shadow(color: Color.black.opacity(0.2), radius: 6, x: 0, y: 3)
                                        } else {
                                            ZStack {
                                                RoundedRectangle(cornerRadius: 10)
                                                    .fill(Color.white.opacity(0.2))
                                                    .frame(width: 60, height: 60)
                                                Image(systemName: "megaphone.fill")
                                                    .font(.system(size: 24, weight: .medium))
                                                    .foregroundColor(.white)
                                            }
                                            .overlay(
                                                RoundedRectangle(cornerRadius: 10)
                                                    .stroke(Color.white.opacity(0.3), lineWidth: 1.5)
                                            )
                                            .shadow(color: Color.black.opacity(0.2), radius: 6, x: 0, y: 3)
                                        }
                                    }
                                }
                                .buttonStyle(PlainButtonStyle())
                                .frame(width: 100)
                                
                                Spacer()
                            }
                            
                            // Info Section - Altta, sol taraftan başlıyor
                            VStack(alignment: .leading, spacing: 8) {
                                // Name - Kutunun en solundan başlıyor, logonun altında
                                HStack(spacing: 5) {
                                    Text(ad.title)
                                        .font(.system(size: 16, weight: .bold, design: .rounded))
                                        .foregroundColor(.white)
                                        .lineLimit(1)
                                        .truncationMode(.tail)
                                        .shadow(color: Color.black.opacity(0.3), radius: 2, x: 0, y: 1)
                                    
                                    Spacer()
                                }
                                
                                // Description (eğer varsa)
                                if !ad.description.isEmpty {
                                    Text(ad.description)
                                        .font(.system(size: 12, weight: .regular))
                                        .foregroundColor(.white.opacity(0.9))
                                        .lineLimit(2)
                                        .shadow(color: Color.black.opacity(0.3), radius: 2, x: 0, y: 1)
                                }
                            }
                            .padding(.horizontal, 16)
                            .padding(.top, 6) // Logo ile isim arası boşluk - hafif azaltıldı
                            .padding(.bottom, 16) // Alt kısma daha fazla boşluk
                        }
                        .padding(.top, -3) // Çok hafif yukarı taşıma
                    }
                    .frame(height: 140)
                    .clipped()
                    .overlay(alignment: .topTrailing) {
                        // Ad Badge - Top Right (CommunityCard'daki favorite button ile birebir aynı konum ve stil)
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                            // Reklam tıklama işlemi ana onTapGesture'da yapılıyor
                        }) {
                                Image(systemName: "megaphone.fill")
                                .font(.system(size: 18, weight: .semibold))
                            .foregroundColor(.white)
                            .padding(10)
                            .background(
                                Circle()
                                    .fill(.ultraThinMaterial)
                            )
                        }
                        .buttonStyle(PlainButtonStyle())
                        .padding(12)
                    }
                    
                    // Stats Section - CommunityCard ile birebir aynı yapı (yazılar biraz daha küçük)
                    VStack(spacing: 0) {
                        HStack(spacing: 0) {
                            CompactStatItemSmall(
                                icon: "megaphone.fill",
                                value: "Reklam",
                                color: Color(hex: "8b5cf6")
                            )
                            
                            Divider()
                                .frame(height: 32)
                                .background(Color.gray.opacity(0.2))
                            
                            CompactStatItemSmall(
                                icon: "sparkles",
                                value: ad.advertiser,
                                color: Color(hex: "8b5cf6")
                            )
                            
                            Divider()
                                .frame(height: 32)
                                .background(Color.gray.opacity(0.2))
                            
                            CompactStatItemSmall(
                                icon: "hand.tap.fill",
                                value: ad.callToAction,
                                color: Color(hex: "8b5cf6")
                            )
                        }
                        .padding(.vertical, 10)
                    }
                    .background(Color(UIColor.secondarySystemBackground))
                }
                .clipShape(RoundedRectangle(cornerRadius: 16))
                .shadow(color: Color.black.opacity(0.08), radius: 8, x: 0, y: 4)
                .overlay(
                    RoundedRectangle(cornerRadius: 16)
                        .stroke(Color.gray.opacity(0.1), lineWidth: 0.5)
                )
                .contentShape(Rectangle())
                .onTapGesture {
                    let generator = UIImpactFeedbackGenerator(style: .medium)
                    generator.impactOccurred()
                    
                    // Reklamın click_url'ine yönlendir
                    if let clickURL = ad.clickURL, !clickURL.isEmpty, let url = URL(string: clickURL) {
                        UIApplication.shared.open(url)
                    }
                }
                .sheet(isPresented: $showLogoDetail) {
                    if let logoURLString = ad.logoURL, !logoURLString.isEmpty {
                        // URL'i oluştur - CommunityCard ile aynı mantık
                        let logoPath: String = {
                            if logoURLString.hasPrefix("http://") || logoURLString.hasPrefix("https://") {
                                var correctedURL = logoURLString
                                #if targetEnvironment(simulator)
                                correctedURL = correctedURL.replacingOccurrences(of: "http://localhost", with: "http://127.0.0.1")
                                correctedURL = correctedURL.replacingOccurrences(of: "https://localhost", with: "https://127.0.0.1")
                                #endif
                                return correctedURL
                            } else {
                                let baseURL = AppConfig.shared.imageBaseURL
                                let cleanPath = logoURLString.hasPrefix("/") ? logoURLString : "/\(logoURLString)"
                                return "\(baseURL)\(cleanPath)"
                            }
                        }()
                        
                        AdLogoDetailView(logoPath: logoPath, advertiser: ad.advertiser)
                    }
                }
            } else {
                // Reklam yoksa hiçbir şey gösterme (ama onAppear tetiklensin)
                Color.clear
                    .frame(height: 0)
            }
        }
        .task {
            // Reklamları API'den yükle (eğer henüz yüklenmemişse)
            if adManager.ads.isEmpty && !adManager.isLoading {
                await adManager.fetchAds()
            }
            
            // Reklamları yüklendikten sonra rastgele bir reklam seç
            if adData == nil {
                // Reklamlar yüklenene kadar bekle (maksimum 3 saniye)
                var attempts = 0
                while adManager.ads.isEmpty && adManager.isLoading && attempts < 30 {
                    try? await Task.sleep(nanoseconds: 100_000_000) // 0.1 saniye bekle
                    attempts += 1
                }
                
                // Reklamları yüklenmişse rastgele bir reklam seç
                if !adManager.ads.isEmpty {
                    adData = adManager.getRandomAd()
                    #if DEBUG
                    print("📢 NativeAdCard: Reklam seçildi - \(adData?.title ?? "nil")")
                    #endif
                } else {
                    #if DEBUG
                    print("⚠️ NativeAdCard: Reklam bulunamadı (ads.count: \(adManager.ads.count), isLoading: \(adManager.isLoading))")
                    #endif
                }
            }
        }
        .onChange(of: adManager.ads) { newAds in
            // Reklamlar yüklendiğinde veya güncellendiğinde yeni bir reklam seç
            if adData == nil && !newAds.isEmpty {
                adData = adManager.getRandomAd()
                #if DEBUG
                print("📢 NativeAdCard: onChange - Reklam seçildi - \(adData?.title ?? "nil")")
                #endif
            }
        }
        .onChange(of: adManager.isLoading) { isLoading in
            // Yükleme tamamlandığında reklam seç
            if !isLoading && adData == nil && !adManager.ads.isEmpty {
                adData = adManager.getRandomAd()
                #if DEBUG
                print("📢 NativeAdCard: isLoading değişti - Reklam seçildi - \(adData?.title ?? "nil")")
                #endif
            }
        }
    }
}

// MARK: - Ad Logo Detail View
struct AdLogoDetailView: View {
    let logoPath: String
    let advertiser: String
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            ZStack {
                Color(UIColor.systemBackground)
                    .ignoresSafeArea()
                
                VStack(spacing: 24) {
                    CachedAsyncImage(url: logoPath) { image in
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fit)
                    } placeholder: {
                        ZStack {
                            RoundedRectangle(cornerRadius: 20)
                                .fill(Color(hex: "6366f1"))
                                .frame(width: 200, height: 200)
                            Image(systemName: "megaphone.fill")
                                .font(.system(size: 80, weight: .medium))
                                .foregroundColor(.white)
                        }
                    }
                    .frame(maxWidth: 300, maxHeight: 300)
                    .clipShape(RoundedRectangle(cornerRadius: 20))
                    .shadow(color: Color.black.opacity(0.2), radius: 20, x: 0, y: 10)
                    
                    Text(advertiser)
                        .font(.system(size: 24, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                }
                .padding()
            }
            .navigationTitle("Reklam Logosu")
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

// MARK: - Compact Stat Item
struct CompactStatItem: View {
    let icon: String
    let value: String
    let color: Color
    
    var body: some View {
        VStack(spacing: 4) {
            Image(systemName: icon)
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(color)
            
            Text(value)
                .font(.system(size: 16, weight: .bold, design: .rounded))
                .foregroundColor(.primary)
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Compact Stat Item Small (Reklam Box için küçük versiyon)
struct CompactStatItemSmall: View {
    let icon: String
    let value: String
    let color: Color
    
    var body: some View {
        VStack(spacing: 4) {
            Image(systemName: icon)
                .font(.system(size: 13, weight: .medium))
                .foregroundColor(color)
            
            Text(value)
                .font(.system(size: 14, weight: .semibold, design: .rounded))
                .foregroundColor(.primary)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Stat Item
struct StatItem: View {
    let icon: String
    let value: String
    let label: String
    
    var body: some View {
        VStack(spacing: 6) {
            HStack(spacing: 4) {
                Image(systemName: icon)
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(Color(hex: "6366f1"))
                
                Text(value)
                    .font(.system(size: 20, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
            }
            
            Text(label)
                .font(.system(size: 10, weight: .semibold, design: .rounded))
                .foregroundColor(.secondary)
                .textCase(.uppercase)
                .tracking(0.5)
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Empty State View
struct EmptyStateView: View {
    let icon: String
    let title: String
    let message: String
    
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: icon)
                .font(.system(size: 56, weight: .light))
                .foregroundColor(.gray.opacity(0.4))
            
            Text(title)
                .font(.system(size: 20, weight: .bold, design: .rounded))
                .foregroundColor(.primary)
            
            Text(message)
                .font(.system(size: 15, weight: .regular))
                .foregroundColor(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
        }
        .padding(.vertical, 64)
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Color Extension
// MARK: - University Selector Button
struct UniversitySelectorButton: View {
    @ObservedObject var viewModel: CommunitiesViewModel
    @State private var showUniversitySheet = false
    
    var displayName: String {
        let name = viewModel.selectedUniversity?.name ?? "Tümü"
        if name.count > 15 {
            return String(name.prefix(12)) + "..."
        }
        return name
    }
    
    var body: some View {
        Button(action: {
            showUniversitySheet = true
        }) {
            HStack(spacing: 6) {
                Image(systemName: "building.2.fill")
                    .font(.system(size: 12, weight: .semibold))
                Text(displayName)
                    .font(.system(size: 12, weight: .medium))
                    .lineLimit(1)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .foregroundColor(Color(hex: "6366f1"))
            .padding(.horizontal, 10)
            .padding(.vertical, 6)
            .frame(maxWidth: 140)
            .background(Color(hex: "6366f1").opacity(0.1))
            .cornerRadius(8)
        }
        .sheet(isPresented: $showUniversitySheet) {
            UniversitySelectionSheet(viewModel: viewModel, isPresented: $showUniversitySheet)
        }
    }
}

// MARK: - University Selection Sheet
struct UniversitySelectionSheet: View {
    @ObservedObject var viewModel: CommunitiesViewModel
    @Binding var isPresented: Bool
    @State private var searchText = ""
    
    var filteredUniversities: [University] {
        if searchText.isEmpty {
            return viewModel.universities
        }
        return viewModel.universities.filter { university in
            university.name.localizedCaseInsensitiveContains(searchText)
        }
    }
    
    var body: some View {
        NavigationStack {
            contentView
                .navigationTitle("Üniversite Seç")
                .navigationBarTitleDisplayMode(.inline)
                .toolbar {
                    ToolbarItem(placement: .navigationBarTrailing) {
                        Button("Kapat") {
                            isPresented = false
                        }
                        .foregroundColor(Color(hex: "6366f1"))
                    }
                }
        }
    }
    
    @ViewBuilder
    private var contentView: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            if viewModel.universities.isEmpty {
                loadingView
            } else {
                universitiesListView
            }
        }
    }
    
    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .scaleEffect(1.5)
            Text("Üniversiteler yükleniyor...")
                .font(.system(size: 16, weight: .medium))
                .foregroundColor(.secondary)
        }
    }
    
    private var universitiesListView: some View {
        VStack(spacing: 0) {
            searchBarView
            universitiesList
        }
    }
    
    private var searchBarView: some View {
        HStack {
            Image(systemName: "magnifyingglass")
                .foregroundColor(.secondary)
            TextField("Üniversite ara...", text: $searchText)
                .textFieldStyle(PlainTextFieldStyle())
        }
        .padding(12)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(12)
        .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .padding(.bottom, 12)
    }
    
    private var universitiesList: some View {
        ScrollView {
            LazyVStack(spacing: 0) {
                // "Tümü" seçeneği - Her zaman en üstte
                UniversityRow(
                    university: University(id: "all", name: "Tümü", communityCount: 0),
                    isSelected: viewModel.selectedUniversity == nil
                ) {
                    // Haptic feedback - anında
                    let generator = UIImpactFeedbackGenerator(style: .medium)
                    generator.prepare()
                    generator.impactOccurred()
                    
                        // Hemen state'i güncelle ve sheet'i kapat
                        Task { @MainActor in
                            // Sheet'i hemen kapat
                            isPresented = false
                            
                            // Üniversite seçimini yap (içinde loadCommunities var)
                            await viewModel.selectUniversity(nil)
                        }
                }
                
                Divider()
                    .padding(.leading, 16)
                
                // Üniversiteler listesi (id="all" olanları filtrele - zaten yukarıda "Tümü" var)
                ForEach(filteredUniversities.filter { $0.id != "all" }) { university in
                    UniversityRow(
                        university: university,
                        isSelected: viewModel.selectedUniversity?.id == university.id
                    ) {
                        // Haptic feedback - anında
                        let generator = UIImpactFeedbackGenerator(style: .medium)
                        generator.prepare()
                        generator.impactOccurred()
                        
                        // Hemen state'i güncelle ve sheet'i kapat
                        Task { @MainActor in
                            // Sheet'i hemen kapat
                            isPresented = false
                            
                            // Üniversite seçimini yap (içinde loadCommunities var)
                            await viewModel.selectUniversity(university)
                        }
                    }
                }
            }
        }
    }
}

// MARK: - University Row
struct UniversityRow: View {
    let university: University
    let isSelected: Bool
    let onTap: () -> Void
    @State private var isPressed = false
    
    var body: some View {
        Button(action: {
            // Haptic feedback zaten UniversitySelectionSheet'te var, burada tekrar gerek yok
            onTap()
        }) {
            HStack(spacing: 12) {
                Image(systemName: "building.2.fill")
                    .font(.system(size: 20))
                    .foregroundColor(isSelected ? Color(hex: "6366f1") : .secondary)
                    .frame(width: 32)
                
                VStack(alignment: .leading, spacing: 4) {
                    Text(university.name)
                        .font(.system(size: 16, weight: isSelected ? .semibold : .regular))
                        .foregroundColor(.primary)
                    
                    if university.communityCount > 0 {
                        Text("\(university.communityCount) topluluk")
                            .font(.system(size: 13))
                            .foregroundColor(.secondary)
                    }
                }
                
                Spacer()
                
                if isSelected {
                    Image(systemName: "checkmark.circle.fill")
                        .font(.system(size: 20))
                        .foregroundColor(Color(hex: "6366f1"))
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .background(isSelected ? Color(hex: "6366f1").opacity(0.1) : Color(UIColor.secondarySystemBackground))
            .contentShape(Rectangle())
        }
        .buttonStyle(PlainButtonStyle())
        .scaleEffect(isPressed ? 0.97 : 1.0)
        .animation(.easeOut(duration: 0.1), value: isPressed)
        .onLongPressGesture(minimumDuration: 0, maximumDistance: .infinity, pressing: { pressing in
            isPressed = pressing
        }, perform: {})
        
        Divider()
            .padding(.leading, 60)
    }
}

// MARK: - Skeleton View
struct SkeletonView: View {
    @State private var shimmerOffset: CGFloat = -200
    @Environment(\.colorScheme) var colorScheme
    
    var body: some View {
        GeometryReader { geometry in
            ZStack {
                // Base color - Koyu moda uygun
                Rectangle()
                    .fill(colorScheme == .dark 
                          ? Color(white: 0.2) 
                          : Color.gray.opacity(0.2))
                
                // Shimmer effect - Koyu moda uygun
                Rectangle()
                    .fill(
                        LinearGradient(
                            gradient: Gradient(colors: [
                                Color.clear,
                                colorScheme == .dark 
                                    ? Color.white.opacity(0.15)
                                    : Color.white.opacity(0.4),
                                Color.clear
                            ]),
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .offset(x: shimmerOffset)
                    .frame(width: 200)
            }
        }
        .onAppear {
            withAnimation(
                Animation.linear(duration: 1.5)
                    .repeatForever(autoreverses: false)
            ) {
                shimmerOffset = 400
            }
        }
    }
}

// MARK: - Skeleton Loading Components
struct CommunityCardSkeleton: View {
    @Environment(\.colorScheme) var colorScheme
    
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Hero Image Skeleton - Görsel yok, sadece skeleton (normal kartla aynı yükseklik: 140)
            ZStack(alignment: .bottomLeading) {
                SkeletonView()
                    .frame(height: 140)
                    .cornerRadius(16, corners: [.topLeft, .topRight])
                
                // Bottom overlay
                VStack(alignment: .leading, spacing: 12) {
                    HStack(spacing: 12) {
                        // Logo Skeleton
                        SkeletonView()
                            .frame(width: 48, height: 48)
                            .clipShape(Circle())
                        
                        VStack(alignment: .leading, spacing: 6) {
                            // Name Skeleton
                            SkeletonView()
                                .frame(width: 150, height: 20)
                                .cornerRadius(4)
                            
                            // Info Skeleton
                            HStack(spacing: 8) {
                                SkeletonView()
                                    .frame(width: 60, height: 14)
                                    .cornerRadius(4)
                                SkeletonView()
                                    .frame(width: 50, height: 14)
                                    .cornerRadius(4)
                            }
                        }
                        
                        Spacer()
                    }
                    .padding(16)
                }
            }
            
            // Content Skeleton
            VStack(alignment: .leading, spacing: 12) {
                SkeletonView()
                    .frame(height: 16)
                    .cornerRadius(4)
                
                SkeletonView()
                    .frame(height: 16)
                    .frame(maxWidth: .infinity)
                    .cornerRadius(4)
                
                SkeletonView()
                    .frame(width: 120, height: 16)
                    .cornerRadius(4)
            }
            .padding(16)
        }
        .background(colorScheme == .dark 
                    ? Color(white: 0.15) 
                    : Color.white)
        .cornerRadius(16)
        .shadow(color: colorScheme == .dark 
                ? Color.black.opacity(0.3) 
                : Color.black.opacity(0.06), 
                radius: 10, x: 0, y: 4)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(colorScheme == .dark 
                        ? Color.white.opacity(0.1) 
                        : Color.gray.opacity(0.08), 
                        lineWidth: 0.5)
        )
    }
}

// Extension for corner radius
extension View {
    func cornerRadius(_ radius: CGFloat, corners: UIRectCorner) -> some View {
        clipShape(RoundedCorner(radius: radius, corners: corners))
    }
}

struct RoundedCorner: Shape {
    var radius: CGFloat = .infinity
    var corners: UIRectCorner = .allCorners

    func path(in rect: CGRect) -> Path {
        let path = UIBezierPath(
            roundedRect: rect,
            byRoundingCorners: corners,
            cornerRadii: CGSize(width: radius, height: radius)
        )
        return Path(path.cgPath)
    }
}

// MARK: - Deep Link Handling
extension ContentView {
    
    func handleDeepLink(url: URL) {
        guard let scheme = url.scheme?.lowercased() else { return }
        
        switch scheme {
        case "unifour":
            handleCustomSchemeDeepLink(url)
        case "http", "https":
            handleWebDeepLink(url)
        default:
            break
        }
    }
    
    func handleQRScanResult(_ result: String) {
        let trimmed = result.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else {
            #if DEBUG
            print("⚠️ QR Scan Result: Empty or invalid result")
            #endif
            return
        }
        
        #if DEBUG
        print("🔍 QR Scan Result: \(trimmed)")
        #endif
        
        // URL oluştur ve deep link'i işle
        // Önce direkt URL olarak dene
        var url: URL?
        if let directURL = URL(string: trimmed) {
            url = directURL
        } else if let encodedString = trimmed.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
                  let encodedURL = URL(string: encodedString) {
            url = encodedURL
        }
        
        guard let finalURL = url else {
            #if DEBUG
            print("⚠️ QR Scan Result: Invalid URL format")
            #endif
            return
        }
        
        // Deep link'i main thread'de işle - network request yapmadan
        // Eğer local deep link ise direkt işle, network request yapma
        Task { @MainActor in
            // Local deep link kontrolü - network request yapmadan işle
            if finalURL.scheme?.lowercased() == "unifour" || finalURL.scheme?.lowercased() == "fourkampus" {
                // Local deep link - network request yok
                await MainActor.run {
                    self.handleDeepLink(url: finalURL)
                }
            } else {
                // Web URL - timeout ile işle
                await withTaskGroup(of: Void.self) { group in
                    group.addTask { @MainActor in
                        self.handleDeepLink(url: finalURL)
                    }
                    
                    group.addTask {
                        // 3 saniye timeout (daha kısa)
                        try? await Task.sleep(nanoseconds: 3_000_000_000)
                    }
                    
                    // İlk tamamlanan task'i bekle
                    _ = await group.next()
                    group.cancelAll()
                }
            }
        }
    }
    
    private func requestNotificationPermission() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
            if granted {
                print("✅ Bildirim izni verildi")
                DispatchQueue.main.async {
                    UIApplication.shared.registerForRemoteNotifications()
                }
            } else {
                print("❌ Bildirim izni reddedildi")
            }
            if let error = error {
                print("⚠️ Bildirim izni hatası: \(error.localizedDescription)")
            }
        }
        
        // Notification delegate ayarla
        UNUserNotificationCenter.current().delegate = NotificationDelegate.shared
    }
    
    private func handleCustomSchemeDeepLink(_ url: URL) {
        var components: [String] = []
        if let host = url.host, !host.isEmpty {
            components.append(host)
        }
        components.append(contentsOf: url.pathComponents.filter { $0 != "/" })
        guard !components.isEmpty else { return }
        
        let action = components[0].lowercased()
        let params = Array(components.dropFirst())
        
        switch action {
        case "community":
            guard let communityId = params.first else { return }
            pendingCommunityId = communityId
            didRequestCommunityReloadForDeepLink = false
            resolvePendingCommunityDeepLink()
        case "event":
            guard params.count >= 2 else { return }
            pendingEventLink = PendingEventLink(communityId: params[0], eventId: params[1])
            didRequestEventReloadForDeepLink = false
            resolvePendingEventDeepLink(forceReload: true)
        default:
            break
        }
    }
    
    private func handleWebDeepLink(_ url: URL) {
        // Network request yapmadan direkt URL'den bilgileri çıkar
        let segments = url.pathComponents.filter { $0 != "/" }
        guard !segments.isEmpty else { return }
        
        // URL'den direkt bilgileri çıkar - network request yapma
        if let eventIndex = segments.firstIndex(where: { $0.lowercased() == "event" }),
           eventIndex + 1 < segments.count {
            let communityId = eventIndex > 0 ? segments[eventIndex - 1] : ""
            let eventId = segments[eventIndex + 1]
            pendingEventLink = PendingEventLink(communityId: communityId, eventId: eventId)
            didRequestEventReloadForDeepLink = false
            
            // Timeout ile resolve et
            Task { @MainActor in
                await withTaskGroup(of: Void.self) { group in
                    group.addTask { @MainActor in
                        self.resolvePendingEventDeepLink(forceReload: true)
                    }
                    
                    group.addTask {
                        // 2 saniye timeout
                        try? await Task.sleep(nanoseconds: 2_000_000_000)
                    }
                    
                    _ = await group.next()
                    group.cancelAll()
                }
            }
            return
        }
        
        if let communityIndex = segments.firstIndex(where: { $0.lowercased() == "community" }),
           communityIndex + 1 < segments.count {
            pendingCommunityId = segments[communityIndex + 1]
            didRequestCommunityReloadForDeepLink = false
            
            // Timeout ile resolve et
            Task { @MainActor in
                await withTaskGroup(of: Void.self) { group in
                    group.addTask { @MainActor in
                        self.resolvePendingCommunityDeepLink()
                    }
                    
                    group.addTask {
                        // 2 saniye timeout
                        try? await Task.sleep(nanoseconds: 2_000_000_000)
                    }
                    
                    _ = await group.next()
                    group.cancelAll()
                }
            }
        }
    }
    
    private func resolvePendingCommunityDeepLink() {
        guard let communityId = pendingCommunityId, !communityId.isEmpty else { return }
        
        if let community = communitiesVM.communities.first(where: { $0.id == communityId }) {
            pendingCommunityId = nil
            didRequestCommunityReloadForDeepLink = false
            navigateToCommunity(community)
        } else if !communitiesVM.isLoading && !didRequestCommunityReloadForDeepLink {
            didRequestCommunityReloadForDeepLink = true
            Task {
                // Timeout ile load et
                await withTaskGroup(of: Void.self) { group in
                    group.addTask {
                        await self.communitiesVM.loadCommunities()
                        await MainActor.run { @MainActor in
                            self.resolvePendingCommunityDeepLink()
                        }
                    }
                    
                    group.addTask {
                        // 5 saniye timeout
                        try? await Task.sleep(nanoseconds: 5_000_000_000)
                    }
                    
                    _ = await group.next()
                    group.cancelAll()
                }
            }
        }
    }
    
    private func resolvePendingEventDeepLink(forceReload: Bool) {
        guard let pendingEventLink, !pendingEventLink.eventId.isEmpty else { return }
        
        if let event = eventsVM.findEvent(
            eventId: pendingEventLink.eventId,
            communityId: pendingEventLink.communityId
        ) {
            self.pendingEventLink = nil
            didRequestEventReloadForDeepLink = false
            navigateToEvent(event)
        } else if eventsVM.state != .loading && (forceReload || !didRequestEventReloadForDeepLink) {
            didRequestEventReloadForDeepLink = true
            Task {
                // Timeout ile load et
                await withTaskGroup(of: Void.self) { group in
                    group.addTask {
                        await self.eventsVM.loadEvents()
                        await MainActor.run { @MainActor in
                            self.resolvePendingEventDeepLink(forceReload: false)
                        }
                    }
                    
                    group.addTask {
                        // 5 saniye timeout
                        try? await Task.sleep(nanoseconds: 5_000_000_000)
                    }
                    
                    _ = await group.next()
                    group.cancelAll()
                }
            }
        }
    }
    
    private func navigateToCommunity(_ community: Community) {
        deepLinkCommunity = community
        selectedTab = 0
        // NavigationPath'i tek bir işlemde güncelle - aynı frame içinde birden fazla güncelleme yapma
        Task { @MainActor in
            var newPath = NavigationPath()
            newPath.append(community)
            communitiesNavigationPath = newPath
        }
    }
    
    private func navigateToEvent(_ event: Event) {
        deepLinkEvent = event
        selectedTab = 1
        // NavigationPath'i tek bir işlemde güncelle - aynı frame içinde birden fazla güncelleme yapma
        Task { @MainActor in
            var newPath = NavigationPath()
            newPath.append(event)
            eventsNavigationPath = newPath
        }
    }
    
    // Notification'dan gelen etkinlik yönlendirmesi
    private func handleNotificationNavigation() {
        if let pendingNav = NotificationDelegate.shared.pendingNavigation {
            // Etkinliği yükle ve göster
            Task {
                await eventsVM.loadEvents()
                await MainActor.run {
                    if let event = eventsVM.findEvent(
                        eventId: pendingNav.eventId,
                        communityId: pendingNav.communityId
                    ) {
                        navigateToEvent(event)
                        NotificationDelegate.shared.pendingNavigation = nil
                    }
                }
            }
        }
    }
}

#Preview {
    ContentView()
        .environmentObject(AuthViewModel())
}
