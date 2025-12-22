//
//  CampaignsView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import Combine

struct CampaignsView: View {
    @StateObject private var viewModel = CampaignsViewModel()
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var communitiesViewModel: CommunitiesViewModel
    @Binding var navigationPath: NavigationPath
    @State private var showFilters = false
    @State private var showLoginModal = false
    @State private var selectedCampaign: Campaign?
    @State private var showJoinSheet = false
    @State private var showUniversityWarning = false
    @State private var pendingJoinAction: (() -> Void)?
    @State private var membershipStatuses: [String: MembershipStatus] = [:]
    @State private var isLoadingMembership = false
    
    var body: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            ScrollView {
                VStack(spacing: 0) {
                    // Search Bar
                        HStack(spacing: 12) {
                            Image(systemName: "magnifyingglass")
                            .foregroundColor(.secondary)
                            TextField("Kampanya ara...", text: $viewModel.searchText)
                            .font(.system(size: 16))
                        }
                        .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .padding(.horizontal, 16)
                    .padding(.bottom, 20)
                    
                    // Filters
                    CampaignFiltersView(
                        selectedCategory: $viewModel.selectedCategory,
                        showOnlyActive: $viewModel.showOnlyActive
                    )
                    .padding(.horizontal, 16)
                    .padding(.bottom, 20)
                    
                    // Campaigns List
                    VStack(spacing: 0) {
                        if viewModel.isLoading && viewModel.campaigns.isEmpty && !viewModel.hasInitiallyLoaded {
                            // Skeleton Loading - Sadece kartlar için
                            LazyVStack(spacing: 16) {
                                ForEach(0..<8) { _ in
                                    CampaignRowCardSkeleton()
                                }
                            }
                            .padding(.horizontal, 16)
                            .padding(.top, 32)
                        } else if viewModel.filteredCampaigns.isEmpty && viewModel.hasInitiallyLoaded {
                            EmptyStateView(
                                icon: "tag.slash",
                                title: "Kampanya bulunamadı",
                                message: viewModel.campaigns.isEmpty
                                    ? (communitiesViewModel.selectedUniversity == nil
                                        ? "Henüz hiç kampanya eklenmemiş."
                                        : "Seçili üniversitede henüz kampanya yok.")
                                    : "Arama kriterlerinize uygun kampanya bulunamadı."
                            )
                            .padding(.top, 32)
                        } else {
                            LazyVStack(spacing: 16) {
                                ForEach(Array(viewModel.filteredCampaigns.enumerated()), id: \.element.id) { index, campaign in
                                    CampaignRowCard(
                                        campaign: campaign,
                                        isSaved: viewModel.isSaved(campaign.id),
                                        membershipStatus: membershipStatuses[campaign.communityId],
                                        onTap: {
                                            // NavigationPath güncellemesini bir sonraki run loop'a ertele
                                            Task { @MainActor in
                                            navigationPath.append(campaign)
                                            }
                                        },
                                        onSave: {
                                            if authViewModel.isAuthenticated {
                                                Task {
                                                    await viewModel.toggleSave(campaign.id)
                                                }
                                            } else {
                                                showLoginModal = true
                                            }
                                        },
                                        onJoin: {
                                            handleJoinCommunity(campaign.communityId, campaign.communityName)
                                        }
                                    )
                                    .onAppear {
                                        // Lazy loading - Son 3 item'dan birine gelindiğinde daha fazla yükle
                                        if index >= viewModel.filteredCampaigns.count - 3 && viewModel.hasMoreCampaigns && !viewModel.isLoadingMore {
                                            Task {
                                                await viewModel.loadMoreCampaigns()
                                            }
                                        }
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
                await viewModel.refreshCampaigns(universityId: nil)
                await loadMembershipStatuses()
            }
        }
        .navigationTitle("Kampanyalar")
        .navigationBarTitleDisplayMode(.large)
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
        .navigationDestination(for: Campaign.self) { campaign in
            if authViewModel.isAuthenticated {
                CampaignDetailView(campaign: campaign)
            } else {
                LoginRequiredView(
                    title: "Giriş Yapın",
                    message: "Bu kampanyanın detaylarını görmek ve kampanyaya katılmak için giriş yapmanız gerekiyor.",
                    icon: "tag.fill",
                    showLoginModal: $showLoginModal
                )
            }
        }
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showJoinSheet) {
            if let selectedCampaign = selectedCampaign, authViewModel.isAuthenticated {
                JoinCommunitySheet(
                    community: Community(
                        id: selectedCampaign.communityId,
                        name: selectedCampaign.communityName,
                        description: "",
                        shortDescription: nil,
                        memberCount: 0,
                        eventCount: 0,
                        campaignCount: 0,
                        boardCount: 0,
                        imageURL: nil,
                        logoPath: nil,
                        categories: [],
                        tags: [],
                        isVerified: false,
                        createdAt: Date(),
                        contactEmail: nil,
                        website: nil,
                        socialLinks: nil,
                        status: nil,
                        university: nil
                    ),
                    onSuccess: {
                        Task {
                            await loadMembershipStatuses()
                        }
                    }
                )
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
            Text("Bu topluluk farklı bir üniversiteye aittir. Yine de katılmak istediğinizden emin misiniz?")
        }
        .onAppear {
            // CampaignsViewModel'e CommunitiesViewModel referansını ver
            viewModel.communitiesViewModel = communitiesViewModel
        }
        .onChange(of: communitiesViewModel.selectedUniversity) { _ in
            // Üniversite değiştiğinde SwiftUI'ya bildir (filteredCampaigns yeniden hesaplanacak)
            viewModel.objectWillChange.send()
        }
        .task {
            if !viewModel.hasInitiallyLoaded {
                await viewModel.loadCampaigns(universityId: nil)
            }
            await loadMembershipStatuses()
        }
        .sheet(isPresented: $showFilters) {
            CampaignFiltersSheet(viewModel: viewModel, isPresented: $showFilters)
        }
    }
    
    // MARK: - Helper Functions
    func handleJoinCommunity(_ communityId: String, _ communityName: String) {
        if !authViewModel.isAuthenticated {
            showLoginModal = true
            return
        }
        
        // Find campaign for this community
        if let campaign = viewModel.campaigns.first(where: { $0.communityId == communityId }) {
            selectedCampaign = campaign
            checkUniversityAndShowJoinSheet(communityId: communityId, communityName: communityName)
        }
    }
    
    func checkUniversityAndShowJoinSheet(communityId: String, communityName: String) {
        guard authViewModel.currentUser != nil else {
            showJoinSheet = true
            return
        }
        
        // For now, directly show join sheet
        // You can add university check logic here if needed
        pendingJoinAction = {
            self.showJoinSheet = true
        }
        showJoinSheet = true
    }
    
    func loadMembershipStatuses() async {
        guard authViewModel.isAuthenticated else { return }
        
        isLoadingMembership = true
        let uniqueCommunityIds = Set(viewModel.campaigns.map { $0.communityId })
        
        for communityId in uniqueCommunityIds {
            do {
                let status = try await APIService.shared.getMembershipStatus(communityId: communityId)
                membershipStatuses[communityId] = status
            } catch {
                #if DEBUG
                print("❌ Membership status error for \(communityId): \(error.localizedDescription)")
                #endif
            }
        }
        
        isLoadingMembership = false
    }
}

// MARK: - Campaign Filters View
struct CampaignFiltersView: View {
    @Binding var selectedCategory: Campaign.CampaignCategory?
    @Binding var showOnlyActive: Bool
    
    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 12) {
                // Tümü
                FilterChip(
                    title: "Tümü",
                    icon: "square.grid.2x2",
                    isSelected: selectedCategory == nil && !showOnlyActive,
                    color: Color(hex: "6366f1")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        selectedCategory = nil
                        showOnlyActive = false
                    }
                }
                
                // Aktif Kampanyalar
                FilterChip(
                    title: "Aktif",
                    icon: "flame.fill",
                    isSelected: showOnlyActive,
                    color: Color(hex: "f59e0b")
                ) {
                    withAnimation(.spring(response: 0.3)) {
                        showOnlyActive.toggle()
                        if showOnlyActive {
                            selectedCategory = nil
                        }
                    }
                }
                
                // Kategori Filtreleri
                ForEach(Campaign.CampaignCategory.allCases, id: \.self) { category in
                    FilterChip(
                        title: category.rawValue,
                        icon: category.icon,
                        isSelected: selectedCategory == category,
                        color: category.color
                    ) {
                        withAnimation(.spring(response: 0.3)) {
                            if selectedCategory == category {
                                selectedCategory = nil
                            } else {
                                selectedCategory = category
                                showOnlyActive = false
                            }
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
        }
    }
}

// MARK: - Campaign Row Card (EventRowCard Formatında)
struct CampaignRowCard: View {
    let campaign: Campaign
    let isSaved: Bool
    let membershipStatus: MembershipStatus?
    let onTap: () -> Void
    let onSave: () -> Void
    let onJoin: () -> Void
    
    var isMember: Bool {
        membershipStatus?.isMember == true || membershipStatus?.status == "member"
    }
    
    var body: some View {
        Button(action: onTap) {
            HStack(spacing: 16) {
                // Campaign Image or Discount Badge
                let imageURL: String? = campaign.imageURL ?? campaign.imagePath
                
                if let imagePath = imageURL, !imagePath.isEmpty {
                    // Görsel varsa göster
                    let finalImageURL = (imagePath.hasPrefix("http://") || imagePath.hasPrefix("https://")) 
                        ? imagePath 
                        : (APIService.fullImageURL(from: imagePath) ?? imagePath)
                    
                    AsyncImage(url: URL(string: finalImageURL)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                        case .failure(_), .empty:
                            // Görsel yüklenemezse badge göster
                            DiscountBadgeView(campaign: campaign)
                        @unknown default:
                            DiscountBadgeView(campaign: campaign)
                    }
                }
                    .frame(width: 80, height: 80)
                    .clipShape(RoundedRectangle(cornerRadius: 14))
                } else {
                    // Görsel yoksa badge göster
                    DiscountBadgeView(campaign: campaign)
                }
                
                // Content
                VStack(alignment: .leading, spacing: 10) {
                    Text(campaign.title)
                        .font(.system(size: 17, weight: .semibold, design: .rounded))
                        .foregroundColor(.primary)
                        .lineLimit(2)
                    
                    // Description
                    if !campaign.description.isEmpty {
                        Text(campaign.description)
                            .font(.system(size: 14, weight: .regular))
                        .foregroundColor(.secondary)
                            .lineLimit(2)
                    }
                    
                    // Community Name
                    Text(campaign.communityName)
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(.secondary)
                        .lineLimit(1)
                    
                    // Badge'ler - Ayrı satırda, daha düzenli
                    HStack(spacing: 8) {
                        // Category Badge
                        HStack(spacing: 4) {
                            Image(systemName: campaign.category.icon)
                                .font(.system(size: 10))
                            Text(campaign.category.rawValue)
                                .font(.system(size: 11, weight: .semibold))
                        }
                        .foregroundColor(campaign.category.color)
                        .padding(.horizontal, 10)
                        .padding(.vertical, 5)
                        .background(campaign.category.color.opacity(0.1))
                        .cornerRadius(8)
                        
                        // Days Remaining Badge
                        if campaign.daysRemaining > 0 {
                            HStack(spacing: 4) {
                                Image(systemName: "clock.fill")
                                    .font(.system(size: 9))
                                Text("\(campaign.daysRemaining) gün")
                                    .font(.system(size: 11, weight: .semibold))
                            }
                            .foregroundColor(campaign.daysRemaining <= 3 ? Color(hex: "ef4444") : Color(hex: "6b7280"))
                            .padding(.horizontal, 10)
                            .padding(.vertical, 5)
                            .background((campaign.daysRemaining <= 3 ? Color(hex: "ef4444") : Color(hex: "6b7280")).opacity(0.1))
                            .cornerRadius(8)
                        }
                        
                        Spacer()
                    }
                    
                    // Join Button - Badge'lerin altında, sadece üye değilse göster
                    if !isMember {
                        Button(action: onJoin) {
                            HStack(spacing: 6) {
                                Image(systemName: "person.badge.plus")
                                    .font(.system(size: 12, weight: .bold))
                                Text("Katıl")
                                    .font(.system(size: 13, weight: .bold))
                            }
                            .foregroundColor(.white)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .background(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(10)
                        }
                        .buttonStyle(PlainButtonStyle())
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                
                // Right side actions
                VStack(spacing: 12) {
                    // Bookmark Button
                    Button(action: onSave) {
                        Image(systemName: isSaved ? "bookmark.fill" : "bookmark")
                            .font(.system(size: 20, weight: .semibold))
                            .foregroundColor(isSaved ? Color(hex: "f59e0b") : .secondary)
                            .frame(width: 36, height: 36)
                    }
                    .buttonStyle(PlainButtonStyle())
                    
                    // Chevron
                    Image(systemName: "chevron.right")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.secondary.opacity(0.4))
                }
                .frame(minWidth: 36)
            }
            .padding(20)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(18)
            .shadow(color: Color.black.opacity(0.06), radius: 10, x: 0, y: 3)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Discount Badge View
struct DiscountBadgeView: View {
    let campaign: Campaign
    
    var body: some View {
        VStack(spacing: 0) {
            if let discountPercentage = campaign.discountPercentage {
                Text("%\(discountPercentage) indirim")
                    .font(.system(size: 13, weight: .bold, design: .rounded))
                    .foregroundColor(.white)
                    .multilineTextAlignment(.center)
                    .lineLimit(2)
                    .minimumScaleFactor(0.8)
            } else {
                Image(systemName: campaign.category.icon)
                    .font(.system(size: 28, weight: .semibold))
                    .foregroundColor(campaign.category.color)
            }
        }
        .frame(width: 80)
        .frame(minHeight: 80)
        .padding(.horizontal, 10)
        .padding(.vertical, 14)
        .background(
            RoundedRectangle(cornerRadius: 14)
                .fill(Color(UIColor.tertiarySystemBackground))
        )
    }
}

// MARK: - Campaign Row Card Skeleton
struct CampaignRowCardSkeleton: View {
    var body: some View {
        HStack(spacing: 16) {
            // Discount Badge Skeleton
            SkeletonView()
                .frame(width: 80, height: 80)
                .cornerRadius(14)
            
            // Content Skeleton
            VStack(alignment: .leading, spacing: 10) {
                SkeletonView()
                    .frame(height: 20)
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
                
                SkeletonView()
                    .frame(width: 80, height: 32)
                    .cornerRadius(10)
            }
            
            Spacer()
            
            // Actions Skeleton
            VStack(spacing: 12) {
                SkeletonView()
                    .frame(width: 36, height: 36)
                    .cornerRadius(8)
                
                SkeletonView()
                    .frame(width: 20, height: 20)
                    .cornerRadius(4)
            }
        }
        .padding(20)
        .background(Color(UIColor.secondarySystemBackground))
        .cornerRadius(18)
        .shadow(color: Color.black.opacity(0.06), radius: 10, x: 0, y: 3)
    }
}

// MARK: - Campaign Filters Sheet
struct CampaignFiltersSheet: View {
    @ObservedObject var viewModel: CampaignsViewModel
    @Binding var isPresented: Bool
    
    var body: some View {
        NavigationView {
            Form {
                Section("Filtreler") {
                    Toggle("Sadece Aktif", isOn: $viewModel.showOnlyActive)
                }
                
                Section("Kategori") {
                    Picker("Kategori", selection: $viewModel.selectedCategory) {
                        Text("Tümü").tag(Campaign.CampaignCategory?.none)
                        ForEach(Campaign.CampaignCategory.allCases, id: \.self) { category in
                            Text(category.rawValue).tag(Campaign.CampaignCategory?.some(category))
                        }
                    }
                }
                
                Section("Sıralama") {
                    Picker("Sırala", selection: $viewModel.sortOption) {
                        Text("En Yeni").tag(CampaignsViewModel.SortOption.newest)
                        Text("Aktif Önce").tag(CampaignsViewModel.SortOption.active)
                        Text("İsme Göre").tag(CampaignsViewModel.SortOption.name)
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
