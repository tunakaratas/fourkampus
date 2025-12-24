//
//  MarketView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import Combine

struct MarketView: View {
    @StateObject private var viewModel: MarketViewModel
    @StateObject private var cartViewModel: CartViewModel
    @State private var selectedProduct: Product?
    @State private var showCart = false
    @State private var showFilters = false
    @State private var showOrderHistory = false
    @State private var layoutMode: LayoutMode = .list
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var communitiesViewModel: CommunitiesViewModel
    private let verificationInfoProvider: (String) -> VerifiedCommunityInfo?
    
    // Topluluk ve üniversite bilgilerini almak için helper fonksiyonlar
    private func getCommunityName(for product: Product) -> String? {
        return communitiesViewModel.communities.first(where: { $0.id == product.communityId })?.name
    }
    
    private func getUniversityName(for product: Product) -> String? {
        return communitiesViewModel.communities.first(where: { $0.id == product.communityId })?.university
    }
    
    enum LayoutMode {
        case list
        case grid
    }
    
    init(verificationInfoProvider: @escaping (String) -> VerifiedCommunityInfo? = { _ in nil }) {
        self._viewModel = StateObject(wrappedValue: MarketViewModel())
        self._cartViewModel = StateObject(wrappedValue: CartViewModel())
        self.verificationInfoProvider = verificationInfoProvider
    }
    
    var body: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            marketContent
        }
        .navigationTitle("Market")
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .navigationBarLeading) {
                // Sipariş Geçmişi Butonu
                if authViewModel.isAuthenticated {
                    Button(action: {
                        showOrderHistory = true
                    }) {
                        Image(systemName: "clock.arrow.circlepath")
                            .font(.system(size: 18))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                }
            }
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 12) {
                    Button(action: {
                        showFilters = true
                    }) {
                        Image(systemName: "slider.horizontal.3")
                            .font(.system(size: 18))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    UniversitySelectorButton(viewModel: communitiesViewModel)
                }
            }
        }
        .sheet(item: $selectedProduct) { product in
            NavigationStack {
                ProductDetailView(product: product, onAddToCart: {
                    showCart = true
                })
                    .environmentObject(cartViewModel)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showCart) {
            NavigationStack {
                CartView()
                    .environmentObject(cartViewModel)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showFilters) {
            NavigationStack {
                MarketFiltersView(viewModel: viewModel)
                    .environmentObject(communitiesViewModel)
            }
            .presentationDetents([.medium, .large])
            .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showOrderHistory) {
            OrderHistoryView()
                .environmentObject(authViewModel)
        }
        .onAppear {
            // MarketViewModel'e topluluk listesini ver (topluluk isimlerini göstermek için)
            viewModel.availableCommunities = communitiesViewModel.communities
            // Üniversite filtresini senkronize et
            viewModel.selectedUniversity = communitiesViewModel.selectedUniversity?.name
        }
        .onChange(of: communitiesViewModel.communities) { newCommunities in
            viewModel.availableCommunities = newCommunities
        }
        .onChange(of: communitiesViewModel.selectedUniversity) { newUniversity in
            // Üniversite değiştiğinde MarketViewModel'i güncelle
            if let uni = newUniversity, uni.id != "all" {
                viewModel.selectedUniversity = uni.name
            } else {
                viewModel.selectedUniversity = nil
            }
        }
        .task {
            if !viewModel.hasInitiallyLoaded {
                await viewModel.loadProducts(universityId: nil)
            }
        }
    }
    
    private var marketContent: some View {
        ScrollView {
            VStack(spacing: 0) {
                searchAndFilterSection
                
                categoryFilterSection
                
                productsSection
            }
        }
        .refreshable {
            await viewModel.refreshProducts(universityId: nil)
        }
    }
    
    private var searchAndFilterSection: some View {
        VStack(spacing: 12) {
            HStack(spacing: 12) {
                // Search Bar
                HStack {
                    Image(systemName: "magnifyingglass")
                        .foregroundColor(.gray)
                    TextField("Ürün, kategori veya topluluk ara...", text: $viewModel.searchText)
                        .textFieldStyle(PlainTextFieldStyle())
                    
                    if !viewModel.searchText.isEmpty {
                        Button(action: {
                            viewModel.searchText = ""
                        }) {
                            Image(systemName: "xmark.circle.fill")
                                .foregroundColor(.gray)
                        }
                    }
                }
                .padding(12)
                .background(Color(UIColor.secondarySystemBackground))
                .cornerRadius(12)
                
                // Layout Toggle
                Button(action: {
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                        layoutMode = layoutMode == .list ? .grid : .list
                    }
                }) {
                    Image(systemName: layoutMode == .list ? "square.grid.2x2" : "list.bullet")
                        .font(.system(size: 18))
                        .foregroundColor(Color(hex: "6366f1"))
                        .frame(width: 44, height: 44)
                        .background(Color(UIColor.secondarySystemBackground))
                        .clipShape(RoundedRectangle(cornerRadius: 12))
                }
                
                // Cart Button
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .light)
                    generator.prepare()
                    generator.impactOccurred()
                    showCart = true
                }) {
                    ZStack(alignment: .topTrailing) {
                        Image(systemName: "cart.fill")
                            .font(.system(size: 20))
                            .foregroundColor(Color(hex: "6366f1"))
                            .frame(width: 44, height: 44)
                            .background(Color(UIColor.secondarySystemBackground))
                            .clipShape(RoundedRectangle(cornerRadius: 12))
                        
                        if cartViewModel.totalItems > 0 {
                            Text("\(cartViewModel.totalItems)")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundColor(.white)
                                .padding(4)
                                .background(Color.red)
                                .clipShape(Circle())
                                .offset(x: 8, y: -8)
                        }
                    }
                }
            }
            
            // Sort & Filter Bar
            HStack(spacing: 12) {
                sortMenu
                
                filterButton
                
                Spacer()
                
                // Results Count
                Text("\(viewModel.filteredProducts.count) ürün")
                    .font(.system(size: 12))
                    .foregroundColor(.secondary)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 16)
    }
    
    private var sortMenu: some View {
        Menu {
            Button(action: {
                viewModel.sortOption = .priceLowToHigh
            }) {
                HStack {
                    Text("Fiyat: Düşükten Yükseğe")
                    if viewModel.sortOption == .priceLowToHigh {
                        Image(systemName: "checkmark")
                    }
                }
            }
            
            Button(action: {
                viewModel.sortOption = .priceHighToLow
            }) {
                HStack {
                    Text("Fiyat: Yüksekten Düşüğe")
                    if viewModel.sortOption == .priceHighToLow {
                        Image(systemName: "checkmark")
                    }
                }
            }
            
            Button(action: {
                viewModel.sortOption = .nameAZ
            }) {
                HStack {
                    Text("İsim: A-Z")
                    if viewModel.sortOption == .nameAZ {
                        Image(systemName: "checkmark")
                    }
                }
            }
            
            Button(action: {
                viewModel.sortOption = .nameZA
            }) {
                HStack {
                    Text("İsim: Z-A")
                    if viewModel.sortOption == .nameZA {
                        Image(systemName: "checkmark")
                    }
                }
            }
            
            Button(action: {
                viewModel.sortOption = .newest
            }) {
                HStack {
                    Text("En Yeni")
                    if viewModel.sortOption == .newest {
                        Image(systemName: "checkmark")
                    }
                }
            }
        } label: {
            HStack(spacing: 6) {
                Image(systemName: "arrow.up.arrow.down")
                    .font(.system(size: 14))
                Text(viewModel.sortOption.displayName)
                    .font(.system(size: 13, weight: .medium))
            }
            .foregroundColor(Color(hex: "6366f1"))
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Color(hex: "6366f1").opacity(0.1))
            .cornerRadius(10)
        }
    }
    
    private var filterButton: some View {
        Button(action: {
            showFilters = true
        }) {
            HStack(spacing: 6) {
                Image(systemName: "slider.horizontal.3")
                    .font(.system(size: 14))
                Text("Filtrele")
                    .font(.system(size: 13, weight: .medium))
                
                if viewModel.hasActiveFilters {
                    Circle()
                        .fill(Color.red)
                        .frame(width: 8, height: 8)
                }
            }
            .foregroundColor(Color(hex: "6366f1"))
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Color(hex: "6366f1").opacity(0.1))
            .cornerRadius(10)
        }
    }
    
    private var categoryFilterSection: some View {
        Group {
            if !viewModel.productCategories.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 12) {
                        CategoryChip(
                            title: "Tümü",
                            icon: "square.grid.2x2",
                            isSelected: viewModel.selectedCategory == nil,
                            color: Color(hex: "6366f1"),
                            action: {
                                viewModel.selectedCategory = nil
                            }
                        )
                        
                        ForEach(viewModel.productCategories) { category in
                            CategoryChip(
                                title: category.name,
                                icon: category.icon,
                                isSelected: viewModel.selectedCategory == category.name,
                                color: Color(hex: "6366f1"),
                                action: {
                                    viewModel.selectedCategory = viewModel.selectedCategory == category.name ? nil : category.name
                                }
                            )
                        }
                    }
                    .padding(.horizontal, 16)
                }
                .padding(.top, 12)
            }
        }
    }
    
    private var productsSection: some View {
        Group {
            if viewModel.isLoading && viewModel.products.isEmpty && !viewModel.hasInitiallyLoaded {
                // Skeleton Loading
                LazyVStack(spacing: 16) {
                    ForEach(0..<5) { _ in
                        ProductCardSkeleton()
                    }
                }
                .padding(16)
                .padding(.top, 32)
            } else if viewModel.filteredProducts.isEmpty && viewModel.hasInitiallyLoaded {
                // Empty State
                EmptyStateView(
                    icon: "bag",
                    title: viewModel.products.isEmpty ? "Ürün bulunamadı" : "Arama sonucu bulunamadı",
                    message: viewModel.products.isEmpty
                        ? (communitiesViewModel.selectedUniversity == nil
                            ? "Henüz hiç ürün eklenmemiş."
                            : "Seçili üniversitede henüz ürün yok.")
                        : "Arama kriterlerinize uygun ürün bulunamadı."
                )
                .padding(.top, 80)
            } else {
                if layoutMode == .grid {
                    gridLayout
                } else {
                    listLayout
                }
            }
        }
    }
    
    private var gridLayout: some View {
        let columns = [
            GridItem(.flexible(), spacing: 20),
            GridItem(.flexible(), spacing: 20)
        ]
        
        return VStack {
            LazyVGrid(columns: columns, alignment: .leading, spacing: 20) {
                ForEach(Array(viewModel.filteredProducts.enumerated()), id: \.element.id) { index, product in
                    let verificationInfo = verificationInfoProvider(product.communityId)
                    let communityName = getCommunityName(for: product)
                    let universityName = getUniversityName(for: product)
                    ProductGridCard(
                        product: product,
                        verificationInfo: verificationInfo,
                        communityName: communityName,
                        universityName: universityName
                    ) {
                        withAnimation(.spring(response: 0.2, dampingFraction: 0.8)) {
                            selectedProduct = product
                        }
                    }
                    .transition(.asymmetric(
                        insertion: .opacity,
                        removal: .opacity
                    ))
                    .onAppear {
                        if index >= viewModel.filteredProducts.count - 3 && viewModel.hasMoreProducts && !viewModel.isLoadingMore {
                            Task {
                                await viewModel.loadMoreProducts()
                            }
                        }
                    }
                }
            }
            .padding(20)
            .padding(.top, 20)
            
            if viewModel.isLoadingMore {
                HStack {
                    Spacer()
                    ProgressView()
                        .padding()
                    Spacer()
                }
            }
        }
    }
    
    private var listLayout: some View {
        VStack {
            LazyVStack(spacing: 16) {
                ForEach(Array(viewModel.filteredProducts.enumerated()), id: \.element.id) { index, product in
                    let verificationInfo = verificationInfoProvider(product.communityId)
                    ProductCard(
                        product: product,
                        verificationInfo: verificationInfo
                    ) {
                        withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                            selectedProduct = product
                        }
                    }
                    .transition(.asymmetric(
                        insertion: .move(edge: .bottom).combined(with: .opacity),
                        removal: .opacity
                    ))
                    .onAppear {
                        if index >= viewModel.filteredProducts.count - 3 && viewModel.hasMoreProducts && !viewModel.isLoadingMore {
                            Task {
                                await viewModel.loadMoreProducts()
                            }
                        }
                    }
                }
                
                if viewModel.isLoadingMore {
                    HStack {
                        Spacer()
                        ProgressView()
                            .padding()
                        Spacer()
                    }
                }
            }
            .padding(16)
            .padding(.top, 16)
        }
    }
}

// MARK: - Product Grid Card
struct ProductGridCard: View {
    let product: Product
    let verificationInfo: VerifiedCommunityInfo?
    let communityName: String?
    let universityName: String?
    var onTap: (() -> Void)? = nil
    
    private var isVerified: Bool {
        verificationInfo != nil
    }
    
    var body: some View {
        Button(action: {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.prepare()
            generator.impactOccurred()
            onTap?()
        }) {
            VStack(alignment: .leading, spacing: 0) {
                // Image Section
                let imageURL: String? = {
                    if let imageURLs = product.imageURLs, !imageURLs.isEmpty {
                        return imageURLs[0]
                    }
                    return product.imageURL ?? product.imagePath
                }()
                
                ZStack(alignment: .topTrailing) {
                    Group {
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
                                    ZStack {
                                        Color(UIColor.secondarySystemBackground)
                                        Image(systemName: "bag.fill")
                                            .font(.system(size: 32))
                                            .foregroundColor(Color(hex: "6366f1").opacity(0.3))
                                    }
                                case .empty:
                                    ZStack {
                                        Color(UIColor.secondarySystemBackground)
                                        Image(systemName: "photo")
                                            .font(.system(size: 28))
                                            .foregroundColor(Color(hex: "6366f1").opacity(0.2))
                                    }
                                @unknown default:
                                    EmptyView()
                                }
                            }
                        } else {
                            ZStack {
                                Color(UIColor.secondarySystemBackground)
                                Image(systemName: "bag.fill")
                                    .font(.system(size: 32))
                                    .foregroundColor(Color(hex: "6366f1").opacity(0.3))
                            }
                        }
                    }
                    .frame(height: 160)
                    .frame(maxWidth: .infinity)
                    .clipped()
                    
                    // Badges
                    VStack(alignment: .trailing, spacing: 6) {
                        if isVerified {
                            Text("Onaylı")
                                .font(.system(size: 10, weight: .bold))
                                .foregroundColor(.white)
                                .padding(.horizontal, 8)
                                .padding(.vertical, 4)
                                .background(Color(hex: "6366f1"))
                                .cornerRadius(6)
                        }
                        
                        if product.stock <= 0 {
                            Text("Tükendi")
                                .font(.system(size: 10, weight: .bold))
                                .foregroundColor(.white)
                                .padding(.horizontal, 8)
                                .padding(.vertical, 4)
                                .background(Color(hex: "ef4444"))
                                .cornerRadius(6)
                        }
                    }
                    .padding(8)
                }
                
                // Content Section
                VStack(alignment: .leading, spacing: 6) {
                    // Title
                    Text(product.name)
                        .font(.system(size: 14, weight: .bold))
                        .foregroundColor(.primary)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                        .frame(height: 36, alignment: .topLeading)
                    
                    // Meta info
                    VStack(alignment: .leading, spacing: 2) {
                        if let communityName = communityName {
                            HStack(spacing: 4) {
                                Image(systemName: "person.3.fill")
                                    .font(.system(size: 10))
                                Text(communityName)
                                    .font(.system(size: 10))
                            }
                            .foregroundColor(.secondary)
                        }
                        
                        if let universityName = universityName {
                            HStack(spacing: 4) {
                                Image(systemName: "building.2.fill")
                                    .font(.system(size: 10))
                                Text(universityName)
                                    .font(.system(size: 10))
                            }
                            .foregroundColor(.secondary)
                        }
                    }
                    .lineLimit(1)
                    
                    Spacer(minLength: 4)
                    
                    // Price Section
                    VStack(alignment: .leading, spacing: 0) {
                        if product.totalPrice != nil {
                            Text(product.formattedTotalPrice)
                                .font(.system(size: 16, weight: .bold))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text(product.formattedPrice)
                                .font(.system(size: 10))
                                .foregroundColor(.secondary)
                                .strikethrough()
                        } else {
                            Text(product.formattedPrice)
                                .font(.system(size: 16, weight: .bold))
                                .foregroundColor(Color(hex: "6366f1"))
                        }
                    }
                }
                .padding(12)
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(16)
            .shadow(color: Color.black.opacity(0.05), radius: 10, x: 0, y: 5)
            .frame(height: 320)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

// MARK: - Market Filters View
struct MarketFiltersView: View {
    @ObservedObject var viewModel: MarketViewModel
    @Environment(\.dismiss) var dismiss
    @EnvironmentObject var communitiesViewModel: CommunitiesViewModel
    @State private var tempMinPrice: String = ""
    @State private var tempMaxPrice: String = ""
    @State private var tempShowOnlyInStock: Bool = false
    
    // Filtrelenmiş topluluklar (seçili üniversiteye göre)
    private var filteredCommunities: [Community] {
        if let selectedUniversity = communitiesViewModel.selectedUniversity, selectedUniversity.id != "all" {
            return viewModel.availableCommunities.filter { community in
                if let communityUniversity = community.university {
                    let normalizedSelected = selectedUniversity.name.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    let normalizedCommunity = communityUniversity.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    return normalizedCommunity == normalizedSelected
                }
                return false
            }
        }
        return viewModel.availableCommunities
    }
    
    var body: some View {
        NavigationStack {
            Form {
                Section("Topluluk") {
                    Picker("Topluluk", selection: $viewModel.selectedCommunityId) {
                        Text("Tümü").tag(nil as String?)
                        ForEach(filteredCommunities, id: \.id) { community in
                            Text(community.name).tag(community.id as String?)
                        }
                    }
                }
                
                Section("Kategori") {
                    Picker("Kategori", selection: $viewModel.selectedCategory) {
                        Text("Tümü").tag(nil as String?)
                        ForEach(viewModel.categories, id: \.self) { category in
                            Text(category).tag(category as String?)
                        }
                    }
                }
                
                Section("Fiyat Aralığı") {
                    HStack {
                        TextField("Min", text: $tempMinPrice)
                            .keyboardType(.decimalPad)
                            .onChange(of: tempMinPrice) { newValue in
                                viewModel.minPrice = Double(newValue)
                            }
                        Text("₺")
                            .foregroundColor(.secondary)
                        
                        Text("-")
                            .foregroundColor(.secondary)
                        
                        TextField("Max", text: $tempMaxPrice)
                            .keyboardType(.decimalPad)
                            .onChange(of: tempMaxPrice) { newValue in
                                viewModel.maxPrice = Double(newValue)
                            }
                        Text("₺")
                            .foregroundColor(.secondary)
                    }
                }
                
                Section("Stok Durumu") {
                    Toggle("Sadece Stokta Olanlar", isOn: $viewModel.showOnlyInStock)
                }
                
                Section {
                    Button("Filtreleri Temizle") {
                        viewModel.selectedCategory = nil
                        viewModel.minPrice = nil
                        viewModel.maxPrice = nil
                        viewModel.showOnlyInStock = false
                        viewModel.selectedCommunityId = nil
                        tempMinPrice = ""
                        tempMaxPrice = ""
                    }
                    .foregroundColor(.red)
                }
            }
            .navigationTitle("Filtreler")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Tamam") {
                        dismiss()
                    }
                }
            }
        }
        .onAppear {
            if let minPrice = viewModel.minPrice {
                tempMinPrice = String(format: "%.0f", minPrice)
            }
            if let maxPrice = viewModel.maxPrice {
                tempMaxPrice = String(format: "%.0f", maxPrice)
            }
            tempShowOnlyInStock = viewModel.showOnlyInStock
        }
    }
}

// MARK: - Product Card Skeleton
struct ProductCardSkeleton: View {
    var body: some View {
        HStack(spacing: 16) {
            RoundedRectangle(cornerRadius: 12)
                .fill(Color(UIColor.secondarySystemBackground))
                .frame(width: 100, height: 100)
            
            VStack(alignment: .leading, spacing: 8) {
                RoundedRectangle(cornerRadius: 4)
                    .fill(Color(UIColor.secondarySystemBackground))
                    .frame(width: 200, height: 20)
                
                RoundedRectangle(cornerRadius: 4)
                    .fill(Color(UIColor.secondarySystemBackground))
                    .frame(width: 150, height: 16)
                
                RoundedRectangle(cornerRadius: 4)
                    .fill(Color(UIColor.secondarySystemBackground))
                    .frame(width: 100, height: 24)
            }
            
            Spacer()
        }
        .padding(16)
        .background(Color(UIColor.systemBackground))
        .cornerRadius(16)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(Color(UIColor.separator).opacity(0.1), lineWidth: 1)
        )
    }
}

