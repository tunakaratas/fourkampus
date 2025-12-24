//
//  CheckoutView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct CheckoutView: View {
    @EnvironmentObject var cartViewModel: CartViewModel
    @EnvironmentObject var authViewModel: AuthViewModel
    @Environment(\.dismiss) var dismiss
    
    @State private var customerName: String = ""
    @State private var customerEmail: String = ""
    @State private var customerPhone: String = ""
    
    @State private var isProcessing = false
    @State private var showSuccess = false
    @State private var errorMessage: String?
    @State private var orderNumber: String?
    
    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 24) {
                    // Müşteri Bilgileri
                    VStack(alignment: .leading, spacing: 16) {
                        Text("Müşteri Bilgileri")
                            .font(.system(size: 20, weight: .bold))
                            .foregroundColor(.primary)
                        
                        TextField("Ad Soyad *", text: $customerName)
                            .textFieldStyle(RoundedTextFieldStyle())
                        
                        TextField("E-posta *", text: $customerEmail)
                            .textFieldStyle(RoundedTextFieldStyle())
                            .keyboardType(.emailAddress)
                            .autocapitalization(.none)
                        
                        TextField("Telefon *", text: $customerPhone)
                            .textFieldStyle(RoundedTextFieldStyle())
                            .keyboardType(.phonePad)
                            .onChange(of: customerPhone) { newValue in
                                customerPhone = InputValidator.formatPhoneNumber(newValue) ?? newValue
                            }
                    }
                    .padding(20)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(16)
                    
                    // Sipariş Özeti
                    VStack(alignment: .leading, spacing: 16) {
                        Text("Sipariş Özeti")
                            .font(.system(size: 20, weight: .bold))
                            .foregroundColor(.primary)
                        
                        ForEach(cartViewModel.items) { item in
                            HStack {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text(item.product.name)
                                        .font(.system(size: 16, weight: .semibold))
                                        .foregroundColor(.primary)
                                    
                                    Text("\(item.quantity) adet × \(item.product.formattedPrice)")
                                        .font(.system(size: 14))
                                        .foregroundColor(.secondary)
                                }
                                
                                Spacer()
                                
                                Text(item.formattedTotalPrice)
                                    .font(.system(size: 16, weight: .bold))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                            .padding(.vertical, 8)
                            
                            if item.id != cartViewModel.items.last?.id {
                                Divider()
                            }
                        }
                        
                        Divider()
                        
                        HStack {
                            Text("Toplam")
                                .font(.system(size: 18, weight: .bold))
                                .foregroundColor(.primary)
                            
                            Spacer()
                            
                            Text(cartViewModel.formattedTotalPrice)
                                .font(.system(size: 24, weight: .bold))
                                .foregroundColor(Color(hex: "6366f1"))
                        }
                    }
                    .padding(20)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(16)
                    
                    // Teslimat Bilgisi - Stant Teslimatı
                    VStack(alignment: .leading, spacing: 12) {
                        HStack {
                            Image(systemName: "mappin.circle.fill")
                                .font(.system(size: 16))
                                .foregroundColor(Color(hex: "6366f1"))
                            Text("Stant Teslimatı")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.primary)
                        }
                        
                        VStack(alignment: .leading, spacing: 8) {
                            Text("• Ürünler topluluk stantlarından elden teslim edilecektir.")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                            
                            Text("• Sipariş onayı e-posta ile gönderilecektir.")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                            
                            Text("• Stant konumu ve teslimat tarihi topluluk tarafından belirlenir.")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                            
                            Text("• Four Kampüs sadece aracı platformdur, teslimat sorumluluğu topluluğa aittir.")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                                .italic()
                        }
                    }
                    .padding(16)
                    .background(Color(hex: "6366f1").opacity(0.1))
                    .cornerRadius(12)
                    
                    // Ödeme Butonu
                    Button(action: {
                        processOrder()
                    }) {
                        HStack {
                            if isProcessing {
                                ProgressView()
                                    .progressViewStyle(CircularProgressViewStyle(tint: .white))
                            } else {
                                Image(systemName: "creditcard.fill")
                                Text("Siparişi Onayla")
                                    .font(.system(size: 17, weight: .semibold))
                            }
                        }
                        .foregroundColor(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 16)
                        .background(
                            LinearGradient(
                                colors: isFormValid ? [Color(hex: "6366f1"), Color(hex: "8b5cf6")] : [Color.gray, Color.gray],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                        .cornerRadius(16)
                    }
                    .disabled(!isFormValid || isProcessing)
                    
                    if let errorMessage = errorMessage {
                        Text(errorMessage)
                            .font(.system(size: 14))
                            .foregroundColor(.red)
                            .padding(.horizontal)
                    }
                }
                .padding(20)
            }
            .navigationTitle("Ödeme")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("İptal") {
                        dismiss()
                    }
                }
            }
            .alert("Sipariş Başarılı", isPresented: $showSuccess) {
                Button("Tamam") {
                    cartViewModel.clearCart()
                    dismiss()
                }
            } message: {
                if let orderNumber = orderNumber {
                    Text("Siparişiniz alındı!\n\nSipariş No: \(orderNumber)\n\nÜrünler topluluk stantından elden teslim edilecektir. Stant konumu ve teslimat tarihi topluluk tarafından belirlenir ve size bildirilecektir.\n\nSipariş onayı e-posta ile gönderilecektir.")
                } else {
                    Text("Siparişiniz alındı!\n\nÜrünler topluluk stantından elden teslim edilecektir. Stant konumu ve teslimat tarihi topluluk tarafından belirlenir ve size bildirilecektir.\n\nSipariş onayı e-posta ile gönderilecektir.")
                }
            }
        }
        .onAppear {
            loadUserInfo()
        }
    }
    
    private var isFormValid: Bool {
        !customerName.isEmpty &&
        !customerEmail.isEmpty &&
        !customerPhone.isEmpty &&
        customerEmail.contains("@")
    }
    
    private func loadUserInfo() {
        if let user = authViewModel.currentUser {
            customerName = user.fullName ?? "\(user.firstName) \(user.lastName)"
            customerEmail = user.email
            customerPhone = user.phoneNumber ?? ""
        }
    }
    
    private func processOrder() {
        guard isFormValid else { return }
        
        isProcessing = true
        errorMessage = nil
        
        Task {
            do {
                // Yeni v2 API ile sipariş oluştur
                let response = try await APIService.shared.createOrder(
                    items: cartViewModel.items,
                    customerName: customerName,
                    customerEmail: customerEmail,
                    customerPhone: customerPhone
                )
                
                await MainActor.run {
                    orderNumber = response.orderNumber
                    isProcessing = false
                    
                    // Eğer payment form varsa, ödeme sayfasına yönlendir
                    if let paymentForm = response.paymentForm, !paymentForm.isEmpty {
                        // TODO: WebView ile ödeme sayfasını göster
                        // Şimdilik başarılı olarak işaretle
                        showSuccess = true
                    } else {
                        // Ödeme formu yoksa direkt başarılı
                        showSuccess = true
                    }
                    
                    // Haptic feedback
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.success)
                }
            } catch {
                await MainActor.run {
                    isProcessing = false
                    errorMessage = "Sipariş işlenirken bir hata oluştu: \(error.localizedDescription)"
                    
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.error)
                }
            }
        }
    }
}

// MARK: - Rounded TextField Style
struct RoundedTextFieldStyle: TextFieldStyle {
    func _body(configuration: TextField<Self._Label>) -> some View {
        configuration
            .padding(12)
            .background(Color(UIColor.systemBackground))
            .cornerRadius(10)
            .overlay(
                RoundedRectangle(cornerRadius: 10)
                    .stroke(Color(UIColor.separator), lineWidth: 1)
            )
    }
}

