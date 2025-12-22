//
//  CartView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct CartView: View {
    @EnvironmentObject var cartViewModel: CartViewModel
    @State private var selectedProduct: Product?
    @State private var acceptedTerms = false
    @State private var showTermsAlert = false
    @State private var showCheckout = false
    @State private var showClearCartAlert = false
    
    var body: some View {
        Group {
            if cartViewModel.items.isEmpty {
                // Empty Cart - Geliştirilmiş
                VStack(spacing: 32) {
                    Spacer()
                    
                    ZStack {
                        Circle()
                            .fill(
                                LinearGradient(
                                    colors: [Color(hex: "6366f1").opacity(0.1), Color(hex: "8b5cf6").opacity(0.05)],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                            .frame(width: 160, height: 160)
                        
                        Image(systemName: "cart.badge.plus")
                            .font(.system(size: 64, weight: .light))
                            .foregroundColor(Color(hex: "6366f1").opacity(0.4))
                    }
                    
                    VStack(spacing: 12) {
                        Text("Sepetiniz Boş")
                            .font(.system(size: 28, weight: .bold))
                            .foregroundColor(.primary)
                        
                        Text("Henüz sepetinize ürün eklemediniz")
                            .font(.system(size: 16))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 40)
                        
                        Text("Market sayfasından ürünleri keşfedin ve sepete ekleyin")
                            .font(.system(size: 14))
                            .foregroundColor(.secondary.opacity(0.8))
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 40)
                    }
                    
                    Spacer()
                }
            } else {
                VStack(spacing: 0) {
                    // Header Summary
                    VStack(spacing: 12) {
                        HStack {
                            VStack(alignment: .leading, spacing: 4) {
                                Text("\(cartViewModel.items.count) ürün")
                                    .font(.system(size: 16, weight: .semibold))
                                    .foregroundColor(.primary)
                                
                                Text("Toplam: \(cartViewModel.formattedTotalPrice)")
                                    .font(.system(size: 14))
                                    .foregroundColor(.secondary)
                            }
                            
                            Spacer()
                            
                            Button(action: {
                                showClearCartAlert = true
                            }) {
                                HStack(spacing: 6) {
                                    Image(systemName: "trash")
                                        .font(.system(size: 14))
                                    Text("Temizle")
                                        .font(.system(size: 14, weight: .medium))
                                }
                                .foregroundColor(.red)
                                .padding(.horizontal, 12)
                                .padding(.vertical, 8)
                                .background(Color.red.opacity(0.1))
                                .cornerRadius(10)
                            }
                        }
                        .padding(.horizontal, 20)
                        .padding(.top, 8)
                        
                        // Quick Summary Card
                        HStack {
                            VStack(alignment: .leading, spacing: 4) {
                                Text("Toplam Tutar")
                                    .font(.system(size: 12))
                                    .foregroundColor(.secondary)
                                Text(cartViewModel.formattedTotalPrice)
                                    .font(.system(size: 20, weight: .bold))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                            
                            Spacer()
                            
                            Image(systemName: "mappin.circle.fill")
                                .font(.system(size: 24))
                                .foregroundColor(Color(hex: "6366f1").opacity(0.6))
                        }
                        .padding(16)
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "6366f1").opacity(0.1), Color(hex: "8b5cf6").opacity(0.05)],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                        .padding(.horizontal, 20)
                    }
                    .padding(.bottom, 16)
                    
                    // Cart Items
                    ScrollView {
                        LazyVStack(spacing: 16) {
                            ForEach(Array(cartViewModel.items.enumerated()), id: \.element.id) { index, item in
                                CartItemRow(item: item) {
                                    cartViewModel.updateQuantity(item.id, quantity: $0)
                                } onRemove: {
                                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                        cartViewModel.removeItem(item.id)
                                    }
                                } onTap: {
                                    selectedProduct = item.product
                                }
                                .transition(.asymmetric(
                                    insertion: .move(edge: .leading).combined(with: .opacity),
                                    removal: .scale.combined(with: .opacity)
                                ))
                            }
                        }
                        .padding(.horizontal, 20)
                        .padding(.bottom, 20)
                    }
                }
                .safeAreaInset(edge: .bottom) {
                    // Bottom Summary - Fixed
                    VStack(spacing: 0) {
                        Divider()
                            .background(Color(UIColor.separator))
                        
                        VStack(spacing: 16) {
                            // Total Summary
                            HStack {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text("Toplam")
                                        .font(.system(size: 14))
                                        .foregroundColor(.secondary)
                                    Text(cartViewModel.formattedTotalPrice)
                                        .font(.system(size: 24, weight: .bold))
                                        .foregroundColor(Color(hex: "6366f1"))
                                }
                                
                                Spacer()
                                
                                VStack(alignment: .trailing, spacing: 4) {
                                    Text("\(cartViewModel.totalItems) ürün")
                                        .font(.system(size: 14))
                                        .foregroundColor(.secondary)
                                    Text("KDV dahil")
                                        .font(.system(size: 12))
                                        .foregroundColor(.secondary.opacity(0.7))
                                }
                            }
                            
                            // Stant Teslimat Sözleşmesi Onayı
                            HStack(spacing: 12) {
                                Button(action: {
                                    withAnimation(.spring(response: 0.2, dampingFraction: 0.8)) {
                                        acceptedTerms.toggle()
                                    }
                                    let generator = UISelectionFeedbackGenerator()
                                    generator.selectionChanged()
                                }) {
                                    Image(systemName: acceptedTerms ? "checkmark.square.fill" : "square")
                                        .font(.system(size: 20))
                                        .foregroundColor(acceptedTerms ? Color(hex: "6366f1") : .gray)
                                }
                                
                                VStack(alignment: .leading, spacing: 4) {
                                    HStack(spacing: 4) {
                                        Text("Stant Teslimat Sözleşmesi")
                                            .font(.system(size: 13, weight: .medium))
                                            .foregroundColor(.primary)
                                        
                                        Button(action: {
                                            if let url = URL(string: "https://foursoftware.net/marketing/stand-delivery-contract.php") {
                                                UIApplication.shared.open(url)
                                            }
                                        }) {
                                            Image(systemName: "arrow.up.right.square")
                                                .font(.system(size: 12))
                                                .foregroundColor(Color(hex: "6366f1"))
                                        }
                                    }
                                    
                                    Text("ve İptal & İade Koşullarını okudum, kabul ediyorum.")
                                        .font(.system(size: 11))
                                        .foregroundColor(.secondary)
                                }
                                
                                Spacer()
                            }
                            .padding(.vertical, 8)
                            
                            // Ödeme Güvenliği Bilgisi
                            HStack(spacing: 8) {
                                Image(systemName: "lock.shield.fill")
                                    .font(.system(size: 14))
                                    .foregroundColor(Color(hex: "10b981"))
                                Text("Ödemeleriniz SSL ile güvenli bir şekilde işlenmektedir.")
                                    .font(.system(size: 11))
                                    .foregroundColor(.secondary)
                            }
                            .padding(.vertical, 8)
                            .padding(.horizontal, 12)
                            .background(Color(hex: "10b981").opacity(0.1))
                            .cornerRadius(8)
                            
                            // Checkout Button - Geliştirilmiş ve Güzel
                            Button(action: {
                                if !acceptedTerms {
                                    showTermsAlert = true
                                    let generator = UINotificationFeedbackGenerator()
                                    generator.notificationOccurred(.error)
                                } else {
                                    showCheckout = true
                                    let generator = UINotificationFeedbackGenerator()
                                    generator.notificationOccurred(.success)
                                }
                            }) {
                                HStack(spacing: 16) {
                                    ZStack {
                                        Circle()
                                            .fill(Color.white.opacity(0.2))
                                            .frame(width: 44, height: 44)
                                        
                                        Image(systemName: "creditcard.fill")
                                            .font(.system(size: 20, weight: .semibold))
                                            .foregroundColor(.white)
                                    }
                                    
                                    VStack(alignment: .leading, spacing: 4) {
                                        Text("Ödemeye Geç")
                                            .font(.system(size: 18, weight: .bold))
                                            .foregroundColor(.white)
                                        
                                        Text(cartViewModel.formattedTotalPrice)
                                            .font(.system(size: 14, weight: .medium))
                                            .foregroundColor(.white.opacity(0.9))
                                    }
                                    
                                    Spacer()
                                    
                                    Image(systemName: "arrow.right.circle.fill")
                                        .font(.system(size: 24))
                                        .foregroundColor(.white.opacity(0.9))
                                }
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 20)
                                .padding(.horizontal, 20)
                                .background(
                                    ZStack {
                                        // Gradient Background
                                        LinearGradient(
                                            colors: acceptedTerms ? 
                                                [Color(hex: "6366f1"), Color(hex: "8b5cf6"), Color(hex: "a855f7")] : 
                                                [Color.gray.opacity(0.6), Color.gray.opacity(0.4)],
                                            startPoint: .topLeading,
                                            endPoint: .bottomTrailing
                                        )
                                        
                                        // Shine effect
                                        if acceptedTerms {
                                            LinearGradient(
                                                colors: [Color.white.opacity(0.2), Color.clear],
                                                startPoint: .topLeading,
                                                endPoint: .bottomTrailing
                                            )
                                        }
                                    }
                                )
                                .cornerRadius(20)
                                .shadow(color: acceptedTerms ? Color(hex: "6366f1").opacity(0.4) : Color.clear, radius: 12, x: 0, y: 6)
                                .overlay(
                                    RoundedRectangle(cornerRadius: 20)
                                        .stroke(Color.white.opacity(0.2), lineWidth: 1)
                                )
                            }
                            .disabled(!acceptedTerms)
                            .scaleEffect(acceptedTerms ? 1.0 : 0.98)
                            .animation(.spring(response: 0.3, dampingFraction: 0.7), value: acceptedTerms)
                            
                            // Satın Alma Sözleşmeleri
                            VStack(spacing: 8) {
                                HStack(spacing: 12) {
                                    // Stant Teslimat Sözleşmesi
                                    Button(action: {
                                        if let url = URL(string: "https://foursoftware.net/marketing/stand-delivery-contract.php") {
                                            UIApplication.shared.open(url)
                                        }
                                    }) {
                                        Text("Stant Teslimat")
                                            .font(.system(size: 11, weight: .medium))
                                            .foregroundColor(Color(hex: "6366f1"))
                                            .multilineTextAlignment(.center)
                                            .lineLimit(2)
                                    }
                                    .frame(maxWidth: .infinity)
                                
                                    // Ön Bilgilendirme Formu
                                    Button(action: {
                                        if let url = URL(string: "https://foursoftware.net/marketing/pre-information-form.php") {
                                            UIApplication.shared.open(url)
                                        }
                                    }) {
                                        Text("Ön Bilgilendirme")
                                            .font(.system(size: 11, weight: .medium))
                                            .foregroundColor(Color(hex: "6366f1"))
                                            .multilineTextAlignment(.center)
                                            .lineLimit(2)
                                    }
                                    .frame(maxWidth: .infinity)
                                    
                                    // İptal & İade Koşulları
                                    Button(action: {
                                        if let url = URL(string: "https://foursoftware.net/marketing/cancellation-refund.php") {
                                            UIApplication.shared.open(url)
                                        }
                                    }) {
                                        Text("İptal & İade")
                                            .font(.system(size: 11, weight: .medium))
                                            .foregroundColor(Color(hex: "6366f1"))
                                            .multilineTextAlignment(.center)
                                            .lineLimit(2)
                                    }
                                    .frame(maxWidth: .infinity)
                                }
                            }
                            .padding(.top, 8)
                        }
                        .padding(20)
                        .background(
                            Color(UIColor.systemBackground)
                                .shadow(color: Color.black.opacity(0.1), radius: 10, x: 0, y: -5)
                        )
                    }
                }
            }
        }
        .navigationTitle("Sepetim")
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                if !cartViewModel.items.isEmpty {
                    Menu {
                        Button(role: .destructive, action: {
                            showClearCartAlert = true
                        }) {
                            Label("Sepeti Temizle", systemImage: "trash")
                        }
                    } label: {
                        Image(systemName: "ellipsis.circle")
                            .font(.system(size: 18))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                }
            }
        }
        .sheet(item: $selectedProduct) { product in
            NavigationStack {
                ProductDetailView(product: product)
                    .environmentObject(cartViewModel)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
        }
        .alert("Sözleşme Onayı Gerekli", isPresented: $showTermsAlert) {
            Button("Tamam", role: .cancel) { }
        } message: {
            Text("Devam edebilmek için Stant Teslimat Sözleşmesi ve İptal & İade Koşullarını kabul etmelisiniz.")
        }
        .alert("Sepeti Temizle", isPresented: $showClearCartAlert) {
            Button("İptal", role: .cancel) { }
            Button("Temizle", role: .destructive) {
                withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                    cartViewModel.clearCart()
                }
                let generator = UINotificationFeedbackGenerator()
                generator.notificationOccurred(.success)
            }
        } message: {
            Text("Sepetinizdeki tüm ürünleri silmek istediğinize emin misiniz?")
        }
        .sheet(isPresented: $showCheckout) {
            NavigationStack {
                CheckoutView()
                    .environmentObject(cartViewModel)
            }
            .presentationDetents([.large])
            .presentationDragIndicator(.visible)
        }
    }
}

// MARK: - Cart Item Row
struct CartItemRow: View {
    let item: CartItem
    let onQuantityChange: (Int) -> Void
    let onRemove: () -> Void
    let onTap: () -> Void
    @State private var quantity: Int
    
    init(item: CartItem, onQuantityChange: @escaping (Int) -> Void, onRemove: @escaping () -> Void, onTap: @escaping () -> Void) {
        self.item = item
        self.onQuantityChange = onQuantityChange
        self.onRemove = onRemove
        self.onTap = onTap
        _quantity = State(initialValue: item.quantity)
    }
    
    var body: some View {
        Button(action: onTap) {
            HStack(spacing: 16) {
                // Product Image
                if let imagePath = item.product.imageURL ?? item.product.imagePath {
                    let imageURL = APIService.fullImageURL(from: imagePath) ?? imagePath
                    AsyncImage(url: URL(string: imageURL)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                        case .failure(_), .empty:
                            ZStack {
                                RoundedRectangle(cornerRadius: 12)
                                    .fill(Color(UIColor.secondarySystemBackground))
                                Image(systemName: "bag.fill")
                                    .font(.system(size: 24))
                                    .foregroundColor(Color(hex: "6366f1").opacity(0.5))
                            }
                        @unknown default:
                            EmptyView()
                        }
                    }
                    .frame(width: 80, height: 80)
                    .clipShape(RoundedRectangle(cornerRadius: 12))
                } else {
                    ZStack {
                        RoundedRectangle(cornerRadius: 12)
                            .fill(Color(UIColor.secondarySystemBackground))
                        Image(systemName: "bag.fill")
                            .font(.system(size: 24))
                            .foregroundColor(Color(hex: "6366f1").opacity(0.5))
                    }
                    .frame(width: 80, height: 80)
                }
                
                // Product Info
                VStack(alignment: .leading, spacing: 8) {
                    Text(item.product.name)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                        .lineLimit(2)
                    
                    Text(item.formattedTotalPrice)
                        .font(.system(size: 18, weight: .bold))
                        .foregroundColor(Color(hex: "6366f1"))
                    
                    // Quantity Controls - Geliştirilmiş
                    HStack(spacing: 12) {
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                            if quantity > 1 {
                                withAnimation(.spring(response: 0.2, dampingFraction: 0.8)) {
                                    quantity -= 1
                                    onQuantityChange(quantity)
                                }
                            }
                        }) {
                            Image(systemName: "minus.circle.fill")
                                .font(.system(size: 22))
                                .foregroundColor(quantity > 1 ? Color(hex: "6366f1") : .gray.opacity(0.5))
                        }
                        .disabled(quantity <= 1)
                        
                        Text("\(quantity)")
                            .font(.system(size: 16, weight: .bold))
                            .foregroundColor(.primary)
                            .frame(minWidth: 40)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 6)
                            .background(Color(UIColor.secondarySystemBackground))
                            .cornerRadius(8)
                        
                        Button(action: {
                            let generator = UIImpactFeedbackGenerator(style: .light)
                            generator.impactOccurred()
                            if quantity < item.product.stock {
                                withAnimation(.spring(response: 0.2, dampingFraction: 0.8)) {
                                    quantity += 1
                                    onQuantityChange(quantity)
                                }
                            }
                        }) {
                            Image(systemName: "plus.circle.fill")
                                .font(.system(size: 22))
                                .foregroundColor(quantity < item.product.stock ? Color(hex: "6366f1") : .gray.opacity(0.5))
                        }
                        .disabled(quantity >= item.product.stock)
                    }
                    .padding(.top, 4)
                }
                
                Spacer()
                
                // Remove Button - Geliştirilmiş
                Button(action: {
                    let generator = UIImpactFeedbackGenerator(style: .medium)
                    generator.impactOccurred()
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                        onRemove()
                    }
                }) {
                    Image(systemName: "trash.fill")
                        .font(.system(size: 18))
                        .foregroundColor(.red)
                        .padding(10)
                        .background(Color.red.opacity(0.1))
                        .clipShape(Circle())
                }
                .buttonStyle(PlainButtonStyle())
            }
            .padding(18)
            .background(Color(UIColor.secondarySystemBackground))
            .cornerRadius(18)
            .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        }
        .buttonStyle(PlainButtonStyle())
        .onChange(of: item.quantity) { newValue in
            quantity = newValue
        }
    }
}

