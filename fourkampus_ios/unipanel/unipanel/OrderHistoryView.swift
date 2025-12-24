//
//  OrderHistoryView.swift
//  Four Kampüs
//
//  Market tab için sipariş geçmişi görünümü
//

import SwiftUI

struct OrderHistoryView: View {
    @StateObject private var viewModel = OrdersViewModel()
    @EnvironmentObject var authViewModel: AuthViewModel
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            Group {
                if !authViewModel.isAuthenticated {
                    notLoggedInView
                } else if viewModel.isLoading && !viewModel.hasInitiallyLoaded {
                    loadingView
                } else if viewModel.isEmpty {
                    emptyView
                } else {
                    ordersList
                }
            }
            .navigationTitle("Siparişlerim")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button(action: { dismiss() }) {
                        Image(systemName: "xmark")
                            .font(.system(size: 16, weight: .medium))
                            .foregroundColor(.primary)
                    }
                }
            }
            .refreshable {
                await viewModel.refresh()
            }
        }
        .task {
            if authViewModel.isAuthenticated {
                await viewModel.loadOrders()
            }
        }
    }
    
    // MARK: - Not Logged In View
    private var notLoggedInView: some View {
        VStack(spacing: 20) {
            Image(systemName: "person.crop.circle.badge.exclamationmark")
                .font(.system(size: 64))
                .foregroundColor(.secondary)
            
            Text("Giriş Yapmanız Gerekiyor")
                .font(.headline)
            
            Text("Siparişlerinizi görüntülemek için lütfen giriş yapın.")
                .font(.subheadline)
                .foregroundColor(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 40)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(.systemGroupedBackground))
    }
    
    // MARK: - Loading View
    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .scaleEffect(1.2)
            Text("Siparişler yükleniyor...")
                .font(.subheadline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(.systemGroupedBackground))
    }
    
    // MARK: - Empty View
    private var emptyView: some View {
        VStack(spacing: 20) {
            Image(systemName: "bag")
                .font(.system(size: 64))
                .foregroundColor(.secondary)
            
            Text("Henüz Sipariş Yok")
                .font(.headline)
            
            Text("Marketten alışveriş yaptığınızda siparişleriniz burada görünecek.")
                .font(.subheadline)
                .foregroundColor(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 40)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(.systemGroupedBackground))
    }
    
    // MARK: - Orders List
    private var ordersList: some View {
        ScrollView {
            LazyVStack(spacing: 12) {
                ForEach(viewModel.orders) { order in
                    OrderCard(order: order)
                        .onAppear {
                            // Lazy loading
                            if order.id == viewModel.orders.last?.id {
                                Task {
                                    await viewModel.loadMore()
                                }
                            }
                        }
                }
                
                if viewModel.isLoadingMore {
                    HStack {
                        ProgressView()
                        Text("Yükleniyor...")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                    .padding()
                }
            }
            .padding()
        }
        .background(Color(.systemGroupedBackground))
    }
}

// MARK: - Order Card
struct OrderCard: View {
    let order: Order
    @State private var isExpanded = false
    
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Header
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(order.orderNumber)
                        .font(.system(size: 14, weight: .semibold, design: .monospaced))
                        .foregroundColor(.primary)
                    
                    Text(order.formattedDate)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
                
                Spacer()
                
                VStack(alignment: .trailing, spacing: 4) {
                    OrderStatusBadge(status: order.status)
                    
                    Text(order.formattedTotal)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(.primary)
                }
            }
            .padding(16)
            .contentShape(Rectangle())
            .onTapGesture {
                withAnimation(.spring(response: 0.3)) {
                    isExpanded.toggle()
                }
            }
            
            // Expanded content
            if isExpanded {
                Divider()
                
                VStack(alignment: .leading, spacing: 12) {
                    // Items count
                    if let itemsCount = order.itemsCount, itemsCount > 0 {
                        HStack {
                            Image(systemName: "bag")
                                .foregroundColor(.secondary)
                            Text("\(itemsCount) ürün")
                                .font(.subheadline)
                                .foregroundColor(.secondary)
                        }
                    }
                    
                    // Order items (if available)
                    if let items = order.items, !items.isEmpty {
                        ForEach(items) { item in
                            HStack {
                                Text("\(item.quantity)x")
                                    .font(.caption)
                                    .foregroundColor(.secondary)
                                    .frame(width: 30, alignment: .leading)
                                
                                Text(item.productName)
                                    .font(.subheadline)
                                    .lineLimit(1)
                                
                                Spacer()
                                
                                Text(item.formattedLineTotal)
                                    .font(.subheadline)
                                    .foregroundColor(.secondary)
                            }
                        }
                    }
                    
                    Divider()
                    
                    // Totals
                    HStack {
                        Text("Ara Toplam")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                        Spacer()
                        Text(order.formattedSubtotal)
                            .font(.subheadline)
                    }
                    
                    HStack {
                        Text("Toplam")
                            .font(.subheadline)
                            .fontWeight(.semibold)
                        Spacer()
                        Text(order.formattedTotal)
                            .font(.subheadline)
                            .fontWeight(.semibold)
                    }
                    
                    // Payment status
                    HStack {
                        Image(systemName: order.paymentStatus == .paid ? "checkmark.circle.fill" : "clock")
                            .foregroundColor(order.paymentStatus == .paid ? .green : .orange)
                        Text(order.paymentStatus.displayName)
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
                .padding(16)
                .background(Color(.systemGray6))
            }
        }
        .background(Color(.systemBackground))
        .cornerRadius(12)
        .shadow(color: Color.black.opacity(0.05), radius: 5, x: 0, y: 2)
    }
}

// MARK: - Order Status Badge
struct OrderStatusBadge: View {
    let status: OrderStatus
    
    var body: some View {
        Text(status.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(Color(hex: status.color).opacity(0.15))
            .foregroundColor(Color(hex: status.color))
            .cornerRadius(6)
    }
}

#Preview {
    OrderHistoryView()
        .environmentObject(AuthViewModel())
}
