//
//  LoginRequiredView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct LoginRequiredView: View {
    @Environment(\.colorScheme) private var colorScheme
    let title: String
    let message: String
    let icon: String
    @Binding var showLoginModal: Bool
    var body: some View {
        ZStack {
            // System Background - Beyaz modda beyaz, siyah modda siyah
            Color(UIColor.systemBackground)
            .ignoresSafeArea()
            
            VStack(spacing: 0) {
                Spacer()
                
                VStack(spacing: 28) {
                    // Modern Icon - Minimal tasarım
                    Image(systemName: icon)
                        .font(.system(size: 44, weight: .light))
                        .foregroundColor(Color(hex: "6366f1"))
                        .padding(.bottom, 8)
                
                // Content
                    VStack(spacing: 16) {
                    Text(title)
                            .font(.system(size: 26, weight: .bold, design: .rounded))
                        .foregroundColor(.primary)
                        .multilineTextAlignment(.center)
                    
                    Text(message)
                            .font(.system(size: 15, weight: .regular))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                            .lineSpacing(3)
                            .padding(.horizontal, 40)
                    
                    // Benefits List
                        VStack(spacing: 12) {
                        BenefitRow(icon: "checkmark.circle.fill", text: "Detaylı bilgilere erişim")
                        BenefitRow(icon: "checkmark.circle.fill", text: "Etkinlik ve kampanyalara kayıt")
                        BenefitRow(icon: "checkmark.circle.fill", text: "Favori toplulukları kaydetme")
                    }
                        .padding(.top, 4)
                        .padding(.horizontal, 32)
                }
                
                    // Modern Login Button
                Button(action: {
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                        showLoginModal = true
                    }
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
                        .background(Color(hex: "6366f1"))
                        .cornerRadius(14)
                        .shadow(color: Color(hex: "6366f1").opacity(0.25), radius: 12, x: 0, y: 6)
                }
                    .padding(.horizontal, 32)
                }
                
                Spacer()
            }
        }
    }
}

// MARK: - Benefit Row
struct BenefitRow: View {
    @Environment(\.colorScheme) private var colorScheme
    let icon: String
    let text: String
    
    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(Color(hex: "6366f1"))
            
            Text(text)
                .font(.system(size: 15, weight: .medium))
                .foregroundColor(.primary)
            
            Spacer()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(colorScheme == .dark ? Color.white.opacity(0.05) : Color(hex: "6366f1").opacity(0.08))
        )
    }
}

