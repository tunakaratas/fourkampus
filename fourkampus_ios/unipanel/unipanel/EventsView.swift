//
//  EventsView.swift
//  fourkampus
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct EventsView: View {
    @ObservedObject var viewModel: EventsViewModel
    @Binding var navigationPath: NavigationPath
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var communitiesViewModel: CommunitiesViewModel
    @State private var showFilters = false
    @State private var showLoginModal = false
    @State private var pendingEvent: Event?
    @State private var isActive = false
    let verificationInfoProvider: (String) -> VerifiedCommunityInfo?
    
    var body: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            // Normal view - arama ve filtreler her zaman görünür, sadece kartlar skeleton olacak
            if true {
                ScrollView {
                    VStack(spacing: 0) {
                        // Search Bar
                        HStack(spacing: 12) {
                            Image(systemName: "magnifyingglass")
                                        .foregroundColor(.secondary)
                            TextField("Etkinlik ara...", text: $viewModel.searchText)
                                .font(.system(size: 16))
                            }
                            .padding(.horizontal, 16)
                        .padding(.vertical, 12)
                            .background(Color(UIColor.secondarySystemBackground))
                            .cornerRadius(12)
                        .padding(.horizontal, 16)
                        .padding(.bottom, 20)
                        
                        // Filters
                        EventFiltersView(
                            showOnlyMyCommunities: $viewModel.showOnlyMyCommunities,
                            showOnlyVerifiedEvents: $viewModel.showOnlyVerifiedEvents,
                            showOnlyToday: $viewModel.showOnlyToday,
                            showOnlyThisWeek: $viewModel.showOnlyThisWeek,
                            showOnlyThisMonth: $viewModel.showOnlyThisMonth,
                            showOnlyFree: $viewModel.showOnlyFree,
                            showOnlyFeatured: $viewModel.showOnlyFeatured
                        )
                        .environmentObject(authViewModel)
                            .padding(.horizontal, 16)
                        .padding(.bottom, 20)
                        
                        // Events List
                        VStack(spacing: 0) {
                            if viewModel.state == .loading && viewModel.displayedEvents.isEmpty {
                                // Skeleton Loading - Sadece kartlar için
                                LazyVStack(spacing: 16) {
                                    ForEach(0..<8) { _ in
                                        EventRowCardSkeleton()
                                    }
                                }
                                .padding(.horizontal, 16)
                                .padding(.top, 32)
                            } else if viewModel.displayedEvents.isEmpty && (viewModel.state == .loaded || viewModel.hasInitiallyLoaded) {
                                EmptyStateView(
                                    icon: "calendar.badge.exclamationmark",
                                    title: "Etkinlik bulunamadı",
                                    message: communitiesViewModel.selectedUniversity == nil
                                        ? "Arama kriterlerinize uygun etkinlik bulunamadı."
                                        : "Seçili üniversitede etkinlik bulunamadı."
                                )
                                .padding(.top, 32)
                            } else {
                                LazyVStack(spacing: 16) {
                                    ForEach(Array(viewModel.displayedEvents.enumerated()), id: \.offset) { index, event in
                                        let verificationInfo = verificationInfoProvider(event.communityId)
                                        EventRowCard(
                                            event: event,
                                            verificationInfo: verificationInfo
                                        ) {
                                            // NavigationPath güncellemesini bir sonraki run loop'a ertele
                                            Task { @MainActor in
                                                navigationPath.append(event)
                                            }
                                        }
                                        .onAppear {
                                            // Lazy loading - Son 3 item'dan birine gelindiğinde daha fazla yükle
                                            // TODO: hasMoreEvents logic needs to be checked in VM
                                            if index >= viewModel.displayedEvents.count - 3 && !viewModel.isLoadingMore {
                                                Task {
                                                    await viewModel.loadMoreEvents()
                                                }
                                            }
                                        }
                                        
                                        // Her 5 etkinlikten sonra reklam göster (ilk etkinlikten sonra değil)
                                        if (index + 1) % 5 == 0 && index < viewModel.displayedEvents.count - 1 {
                                            EventAdCard(
                                                onTap: {
                                                    // Reklam tıklama işlemi
                                                    let generator = UIImpactFeedbackGenerator(style: .light)
                                                    generator.impactOccurred()
                                                    // TODO: Reklam URL'sine yönlendirme veya reklam ağı entegrasyonu
                                                }
                                            )
                                        }
                                    }
                                    
                                    // Loading indicator (daha fazla yükleniyorsa)
                                    if viewModel.isLoadingMore {
                                        HStack {
                                            Spacer()
                                            ProgressView()
                                                .padding()
                                            Spacer()
                                        }
                                    }
                                }
                                .padding(.horizontal, 16)
                            }
                        }
                        .padding(.bottom, 100)
                    }
                }
                .refreshable {
                    // Üniversite filtresi kaldırıldı - her zaman nil gönder
                    await viewModel.refreshEvents(universityId: nil)
                    // Refresh sonrası giriş yapılmışsa üyelik durumlarını kontrol et
                    if authViewModel.isAuthenticated && !viewModel.displayedEvents.isEmpty {
                        await viewModel.loadMemberCommunityIds(from: viewModel.displayedEvents)
                    }
                }
            }
        }
        .navigationTitle("Etkinlikler")
        .navigationBarTitleDisplayMode(.large)
        .onAppear {
            isActive = true
            Task {
                // Her girişte taze veri çek (maxAge: 3sn - kazara çift tetiklemeyi önlemek için)
                await viewModel.refreshIfStale(maxAge: 3)
            }
        }
        .onDisappear {
            isActive = false
        }
        .task(id: isActive) {
            guard isActive else { return }
            while isActive {
                // Her 30 saniyede bir sessizce yenile (Eskiden 60 idi)
                try? await Task.sleep(nanoseconds: 30_000_000_000)
                if isActive {
                    await viewModel.refreshIfStale(maxAge: 30)
                }
            }
        }
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 12) {
                    Button(action: {
                        showFilters.toggle()
                    }) {
                        Image(systemName: "slider.horizontal.3")
                            .font(.system(size: 18))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    UniversitySelectorButton(viewModel: communitiesViewModel)
                }
            }
        }
        .navigationDestination(for: Event.self) { event in
            if authViewModel.isAuthenticated {
                EventDetailView(
                    event: event,
                    verificationInfo: verificationInfoProvider(event.communityId)
                )
            } else {
                LoginRequiredView(
                    title: "Giriş Yapın",
                    message: "Bu etkinliğin detaylarını görmek, kayıt olmak ve anketlere katılmak için giriş yapmanız gerekiyor.",
                    icon: "calendar.badge.exclamationmark",
                    showLoginModal: $showLoginModal
                )
                .sheet(isPresented: $showLoginModal) {
                    LoginModal(isPresented: $showLoginModal)
                        .presentationDetents([.large])
                        .presentationDragIndicator(.visible)
                }
            }
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .task(id: communitiesViewModel.selectedUniversity) {
            // Üniversite değiştiğinde veya View ilk yüklendiğinde çalışır
            // ViewModel'e university bilgisini aktar
            // NOT: ViewModel.selectedUniversity didSet bloğu otomatik olarak loadEvents'i çağıracaktır.
            if viewModel.selectedUniversity?.id != communitiesViewModel.selectedUniversity?.id {
                viewModel.selectedUniversity = communitiesViewModel.selectedUniversity
            }
            
            // Onaylı etkinlik ID'lerini yükle
            await viewModel.loadVerifiedEventIds(verificationInfoProvider: verificationInfoProvider)
        }
        .task(id: authViewModel.isAuthenticated) {
            // Giriş durumu değiştiğinde üyelik durumlarını kontrol et
            if authViewModel.isAuthenticated && !viewModel.displayedEvents.isEmpty {
                await viewModel.loadMemberCommunityIds(from: viewModel.displayedEvents)
            } else if !authViewModel.isAuthenticated {
                // Çıkış yapıldığında üyelik listesini temizle ve filtreyi kapat
                await MainActor.run {
                    viewModel.memberCommunityIds = []
                    viewModel.showOnlyMyCommunities = false
                }
            }
        }
        // Timeout logic removed - handled in ViewModel
        .sheet(isPresented: $showFilters) {
            EventFiltersSheet(
                viewModel: viewModel,
                isPresented: $showFilters
            )
        }
        // Manual onChange handlers removed - ViewModel handles observable property changes internally via didSet/applyFilters
    }
}

// MARK: - Event Filters View
struct EventFiltersView: View {
    @Binding var showOnlyMyCommunities: Bool
    @Binding var showOnlyVerifiedEvents: Bool
    @Binding var showOnlyToday: Bool
    @Binding var showOnlyThisWeek: Bool
    @Binding var showOnlyThisMonth: Bool
    @Binding var showOnlyFree: Bool
    @Binding var showOnlyFeatured: Bool
    @EnvironmentObject var authViewModel: AuthViewModel
    
    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 12) {
                // Onaylı Etkinlikler Filtresi
                FilterChip(
                    title: "Onaylı Etkinlikler",
                    icon: "checkmark.seal.fill",
                    isSelected: showOnlyVerifiedEvents,
                    color: Color(hex: "10b981")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyVerifiedEvents.toggle()
                    }
                }
                
                // Bugün Filtresi
                FilterChip(
                    title: "Bugün",
                    icon: "calendar",
                    isSelected: showOnlyToday,
                    color: Color(hex: "6366f1")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyToday.toggle()
                        // Diğer zaman filtrelerini kapat
                        if showOnlyToday {
                            showOnlyThisWeek = false
                            showOnlyThisMonth = false
                        }
                    }
                }
                
                // Bu Hafta Filtresi
                FilterChip(
                    title: "Bu Hafta",
                    icon: "calendar.badge.clock",
                    isSelected: showOnlyThisWeek,
                    color: Color(hex: "6366f1")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyThisWeek.toggle()
                        // Diğer zaman filtrelerini kapat
                        if showOnlyThisWeek {
                            showOnlyToday = false
                            showOnlyThisMonth = false
                        }
                    }
                }
                
                // Bu Ay Filtresi
                FilterChip(
                    title: "Bu Ay",
                    icon: "calendar.badge.plus",
                    isSelected: showOnlyThisMonth,
                    color: Color(hex: "6366f1")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyThisMonth.toggle()
                        // Diğer zaman filtrelerini kapat
                        if showOnlyThisMonth {
                            showOnlyToday = false
                            showOnlyThisWeek = false
                        }
                    }
                }
                
                // Ücretsiz Filtresi
                FilterChip(
                    title: "Ücretsiz",
                    icon: "tag.fill",
                    isSelected: showOnlyFree,
                    color: Color(hex: "10b981")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyFree.toggle()
                    }
                }
                
                // Öne Çıkan Filtresi
                FilterChip(
                    title: "Öne Çıkan",
                    icon: "star.fill",
                    isSelected: showOnlyFeatured,
                    color: Color(hex: "f59e0b")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyFeatured.toggle()
                    }
                }
                
                // Yalnızca Üyesi Olduklarım (sadece giriş yapılmışsa)
                if authViewModel.isAuthenticated {
                    FilterChip(
                        title: "Üyesi Olduklarım",
                        icon: "person.2.fill",
                        isSelected: showOnlyMyCommunities,
                        color: Color(hex: "6366f1")
                    ) {
                        withAnimation(.spring(response: 0.3)) {
                            showOnlyMyCommunities.toggle()
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
        }
    }
}

// MARK: - Filter Chip
struct FilterChip: View {
    let title: String
    let icon: String
    let isSelected: Bool
    let color: Color
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack(spacing: 6) {
                Image(systemName: icon)
                    .font(.system(size: 12, weight: .semibold))
                Text(title)
                    .font(.system(size: 13, weight: .medium))
            }
            .foregroundColor(isSelected ? .white : color)
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(isSelected ? color : color.opacity(0.1))
            .cornerRadius(20)
            .overlay(
                RoundedRectangle(cornerRadius: 20)
                    .stroke(isSelected ? Color.clear : color.opacity(0.3), lineWidth: 1)
            )
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Event Filters Sheet
struct EventFiltersSheet: View {
    @ObservedObject var viewModel: EventsViewModel
    @Binding var isPresented: Bool
    
    var body: some View {
        NavigationView {
            Form {
                Section("Zaman Filtreleri") {
                    Toggle("Sadece Yaklaşanlar", isOn: $viewModel.showOnlyUpcoming)
                    Toggle("Bugün", isOn: $viewModel.showOnlyToday)
                    Toggle("Bu Hafta", isOn: $viewModel.showOnlyThisWeek)
                    Toggle("Bu Ay", isOn: $viewModel.showOnlyThisMonth)
                }
                
                Section("Etkinlik Filtreleri") {
                    Toggle("Sadece Onaylı Etkinlikler", isOn: $viewModel.showOnlyVerifiedEvents)
                }
                
                Section("Diğer Filtreler") {
                    Toggle("Sadece Ücretsiz", isOn: $viewModel.showOnlyFree)
                    Toggle("Sadece Öne Çıkanlar", isOn: $viewModel.showOnlyFeatured)
                }
                
                Section("Kategori ve Durum") {
                    Picker("Kategori", selection: $viewModel.selectedCategory) {
                        Text("Tümü").tag(Event.EventCategory?.none)
                        ForEach(Event.EventCategory.allCases, id: \.self) { category in
                            Text(category.rawValue).tag(Event.EventCategory?.some(category))
                        }
                    }
                    
                    Picker("Durum", selection: $viewModel.selectedStatus) {
                        Text("Tümü").tag(String?.none)
                        Text("Yaklaşan").tag(String?.some("upcoming"))
                        Text("Devam Ediyor").tag(String?.some("ongoing"))
                        Text("Tamamlandı").tag(String?.some("completed"))
                    }
                }
                
                Section("Sıralama") {
                    Picker("Sırala", selection: $viewModel.sortOption) {
                        Text("Tarihe Göre").tag(EventsViewModel.SortOption.date)
                        Text("İsme Göre").tag(EventsViewModel.SortOption.name)
                        Text("Kategoriye Göre").tag(EventsViewModel.SortOption.category)
                        Text("En Yeni").tag(EventsViewModel.SortOption.newest)
                    }
                }
            }
            .navigationTitle("Filtreler")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Tamam") {
                        isPresented = false
                    }
                }
            }
        }
    }
}

// MARK: - Event Ad Card (EventRowCard Formatında)
struct EventAdCard: View {
    @StateObject private var adManager = AdManager.shared
    @State private var adData: AdData?
    var onTap: (() -> Void)? = nil
    
    var body: some View {
        HStack(spacing: 16) {
            // Date Badge - Reklam için özel tasarım (EventRowCard ile aynı boyut)
            VStack(spacing: 4) {
                // Reklam badge
                HStack(spacing: 2) {
                    Image(systemName: "megaphone.fill")
                        .font(.system(size: 8, weight: .bold))
                    Text("AD")
                        .font(.system(size: 8, weight: .bold))
                }
                .foregroundColor(.white)
                .padding(.horizontal, 6)
                .padding(.vertical, 2)
                .background(
                    LinearGradient(
                        colors: [Color(hex: "f59e0b"), Color(hex: "ef4444")],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(4)
                
                // Star icon (reklam için) - EventRowCard'daki tarih boyutuna benzer
                Image(systemName: "star.fill")
                    .font(.system(size: 20, weight: .bold, design: .rounded))
                    .foregroundColor(Color(hex: "f59e0b"))
            }
            .frame(width: 60)
            .padding(.vertical, 12)
            .background(
                LinearGradient(
                    colors: [
                        Color(hex: "f59e0b").opacity(0.15),
                        Color(hex: "ef4444").opacity(0.1)
                    ],
                    startPoint: .topLeading,
                    endPoint: .bottomTrailing
                )
            )
            .cornerRadius(12)
            
            // Content - EventRowCard ile aynı yapı
            VStack(alignment: .leading, spacing: 8) {
                // Title - EventRowCard ile aynı font ve boyut
                Text(adData?.title ?? "Özel Fırsatlar")
                    .font(.system(size: 16, weight: .semibold, design: .rounded))
                    .foregroundColor(.primary)
                    .lineLimit(2)
                
                // Description - EventRowCard'daki zaman/konum yerine
                Text(adData?.description ?? "Size özel kampanyalar ve avantajlar keşfedin")
                    .font(.system(size: 14, weight: .regular))
                    .foregroundColor(.secondary)
                    .lineLimit(2)
                
                // Advertiser and CTA - EventRowCard'daki community/category yerine
                HStack(spacing: 8) {
                    Text(adData?.advertiser ?? "Sponsor")
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(.secondary)
                    
                    Circle()
                        .fill(Color.gray.opacity(0.3))
                        .frame(width: 4, height: 4)
                    
                    if let rating = adData?.rating {
                        HStack(spacing: 4) {
                            Image(systemName: "star.fill")
                                .font(.system(size: 10))
                            Text(String(format: "%.1f", rating))
                                .font(.system(size: 12, weight: .medium))
                        }
                        .foregroundColor(Color(hex: "f59e0b"))
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color(hex: "f59e0b").opacity(0.1))
                        .cornerRadius(8)
                    } else {
                        // Call to Action badge
                        Text(adData?.callToAction ?? "Keşfet")
                            .font(.system(size: 12, weight: .medium))
                            .foregroundColor(Color(hex: "6366f1"))
                            .padding(.horizontal, 8)
                            .padding(.vertical, 4)
                            .background(Color(hex: "6366f1").opacity(0.1))
                            .cornerRadius(8)
                    }
                }
            }
            
            Spacer()
        }
        .padding(16)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color(hex: "f59e0b").opacity(0.2),
                            Color(hex: "ef4444").opacity(0.15)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1
                )
        )
        .onTapGesture {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            onTap?()
        }
        .onAppear {
            // Reklam yükle
            if adData == nil {
                adManager.loadNativeAd { loadedAd in
                    adData = loadedAd
                }
            }
        }
    }
}

// MARK: - Upcoming Event Card
struct UpcomingEventCard: View {
    let event: Event
    var onTap: (() -> Void)? = nil
    
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Date Badge
            VStack(spacing: 4) {
                Text(event.monthAbbreviation)
                    .font(.system(size: 10, weight: .bold, design: .rounded))
                    .foregroundColor(.secondary)
                Text(event.dayNumber)
                    .font(.system(size: 32, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
            }
            .frame(width: 80, height: 80)
            .background(
                LinearGradient(
                    colors: [event.category.color.opacity(0.2), event.category.color.opacity(0.1)],
                    startPoint: .topLeading,
                    endPoint: .bottomTrailing
                )
            )
            .cornerRadius(16)
            
            Spacer().frame(height: 12)
            
            // Content
            VStack(alignment: .leading, spacing: 8) {
                Text(event.title)
                    .font(.system(size: 16, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
                    .lineLimit(2)
                
                HStack(spacing: 8) {
                    Label(event.formattedTime, systemImage: "clock")
                    if let location = event.location {
                        Label(location, systemImage: "mappin.circle")
                    }
                }
                .font(.system(size: 12, weight: .regular))
                .foregroundColor(.secondary)
                
                // Category Badge
                HStack {
                    Image(systemName: event.category.icon)
                        .font(.system(size: 10))
                    Text(event.category.rawValue)
                        .font(.system(size: 10, weight: .semibold))
                }
                .foregroundColor(event.category.color)
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(event.category.color.opacity(0.1))
                .cornerRadius(8)
            }
        }
        .padding(16)
        .frame(width: 200)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(20)
        .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        .contentShape(Rectangle())
        .onTapGesture {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.impactOccurred()
                    onTap?()
                }
    }
}

// MARK: - Event Row Card
struct EventRowCard: View {
    let event: Event
    let verificationInfo: VerifiedCommunityInfo?
    var onTap: (() -> Void)? = nil
    
    private var isVerified: Bool {
        verificationInfo != nil
    }
    
    var body: some View {
        HStack(spacing: 16) {
            // Date Badge
            VStack(spacing: 4) {
                Text(event.monthAbbreviation)
                    .font(.system(size: 10, weight: .bold, design: .rounded))
                    .foregroundColor(.secondary)
                Text(event.dayNumber)
                    .font(.system(size: 24, weight: .bold, design: .rounded))
                    .foregroundColor(.primary)
            }
            .frame(width: 60)
            .padding(.vertical, 12)
            .background(Color(UIColor.tertiarySystemBackground))
            .cornerRadius(12)
            
            // Content
            VStack(alignment: .leading, spacing: 8) {
                HStack(alignment: .center, spacing: 8) {
                Text(event.title)
                    .font(.system(size: 16, weight: .semibold, design: .rounded))
                    .foregroundColor(.primary)
                    .lineLimit(2)
                    Spacer(minLength: 4)
                    if isVerified {
                        VerifiedBadgeTag(text: "Onaylı Etkinlik", style: .prominent)
                    }
                }
                
                HStack(spacing: 12) {
                    Label(event.formattedTime, systemImage: "clock")
                    if let location = event.location {
                        Label(location, systemImage: "mappin.circle")
                    }
                }
                .font(.system(size: 14, weight: .regular))
                .foregroundColor(.secondary)
                
                // Community and Category
                HStack(spacing: 8) {
                    Text(event.communityName)
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(.secondary)
                    
                    Circle()
                        .fill(Color.gray.opacity(0.3))
                        .frame(width: 4, height: 4)
                    
                    HStack(spacing: 4) {
                        Image(systemName: event.category.icon)
                            .font(.system(size: 10))
                        Text(event.category.rawValue)
                            .font(.system(size: 12, weight: .medium))
                    }
                    .foregroundColor(event.category.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(event.category.color.opacity(0.1))
                    .cornerRadius(8)
                }
            }
            
            Spacer()
        }
        .padding(16)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
        .contentShape(Rectangle())
        .onTapGesture {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.impactOccurred()
                    onTap?()
                }
    }
}

// MARK: - Skeleton Views
struct UpcomingEventCardSkeleton: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            SkeletonView()
                .frame(height: 120)
                .cornerRadius(16)
            
            SkeletonView()
                .frame(height: 18)
                .cornerRadius(4)
            
            SkeletonView()
                .frame(height: 14)
                .frame(maxWidth: .infinity)
                .cornerRadius(4)
        }
        .frame(width: 280)
        .padding(16)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
    }
}

struct EventRowCardSkeleton: View {
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

// EventDetailView artık ayrı bir dosyada (EventDetailView.swift)
