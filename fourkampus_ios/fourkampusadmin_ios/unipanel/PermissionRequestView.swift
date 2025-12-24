//
//  PermissionRequestView.swift
//  Four Kampüs
//
//  Permission Request Information Screen
//

import SwiftUI

enum PermissionType {
    case camera
    case notifications
    
    var title: String {
        switch self {
        case .camera:
            return "Kamera Erişimi"
        case .notifications:
            return "Bildirim İzni"
        }
    }
    
    var icon: String {
        switch self {
        case .camera:
            return "camera.fill"
        case .notifications:
            return "bell.fill"
        }
    }
    
    var description: String {
        switch self {
        case .camera:
            return "QR kodları taramak ve etkinlik/topluluk bilgilerine hızlıca erişmek için kamera erişimine ihtiyacımız var."
        case .notifications:
            return "Katıldığınız topluluklardaki yeni etkinliklerden, önemli duyurulardan ve kampanyalardan haberdar olmak için bildirim izni gereklidir."
        }
    }
    
    var benefits: [String] {
        switch self {
        case .camera:
            return [
                "Etkinlik QR kodlarını hızlıca tarayın",
                "Topluluk QR kodlarıyla kolayca katılın",
                "Kampanya kodlarını anında görüntüleyin"
            ]
        case .notifications:
            return [
                "Yeni etkinliklerden anında haberdar olun",
                "Önemli duyuruları kaçırmayın",
                "Kampanya ve fırsatları takip edin"
            ]
        }
    }
}

struct PermissionRequestView: View {
    let permissionType: PermissionType
    let onAllow: () -> Void
    let onSkip: () -> Void
    
    @State private var isAnimating = false
    
    var body: some View {
        ZStack {
            // Theme adaptive background
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            ScrollView {
                VStack(spacing: 32) {
                    Spacer()
                        .frame(height: 40)
                    
                    // Icon
                    ZStack {
                        Circle()
                            .fill(Color(hex: "6366f1").opacity(0.08))
                            .frame(width: 120, height: 120)
                        
                        Image(systemName: permissionType.icon)
                            .font(.system(size: 50, weight: .semibold))
                            .foregroundColor(Color(hex: "6366f1"))
                    }
                    .scaleEffect(isAnimating ? 1.0 : 0.8)
                    .animation(.spring(response: 0.6, dampingFraction: 0.7), value: isAnimating)
                    
                    // Header Group
                    VStack(spacing: 16) {
                        Text(permissionType.title)
                            .font(.system(size: 28, weight: .bold, design: .rounded))
                            .foregroundColor(.primary)
                            .multilineTextAlignment(.center)
                        
                        Text(permissionType.description)
                            .font(.system(size: 16, weight: .regular))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 24)
                            .lineSpacing(4)
                    }
                    
                    // Benefits Card
                    VStack(alignment: .leading, spacing: 20) {
                        Text("Bu izin sayesinde:")
                            .font(.system(size: 18, weight: .semibold, design: .rounded))
                            .foregroundColor(.primary)
                        
                        ForEach(permissionType.benefits, id: \.self) { benefit in
                            HStack(spacing: 16) {
                                ZStack {
                                    Circle()
                                        .fill(Color(hex: "6366f1").opacity(0.1))
                                        .frame(width: 32, height: 32)
                                    Image(systemName: "checkmark")
                                        .font(.system(size: 14, weight: .bold))
                                        .foregroundColor(Color(hex: "6366f1"))
                                }
                                
                                Text(benefit)
                                    .font(.system(size: 15, weight: .medium))
                                    .foregroundColor(.primary.opacity(0.9))
                                    .fixedSize(horizontal: false, vertical: true)
                                
                                Spacer()
                            }
                        }
                    }
                    .padding(24)
                    .background(
                        RoundedRectangle(cornerRadius: 24)
                            .fill(Color(UIColor.secondarySystemBackground))
                    )
                    .padding(.horizontal, 20)
                    
                    Spacer()
                        .frame(height: 40)
                    
                    // Buttons
                    VStack(spacing: 16) {
                        Button(action: onAllow) {
                            HStack {
                                Text("İzni Ver")
                                    .font(.system(size: 16, weight: .semibold))
                            }
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .background(
                                RoundedRectangle(cornerRadius: 16)
                                    .fill(Color(hex: "6366f1"))
                            )
                        }
                        .buttonStyle(PlainButtonStyle())
                        .shadow(color: Color(hex: "6366f1").opacity(0.25), radius: 10, x: 0, y: 5)
                        
                        Button(action: onSkip) {
                            Text("Şimdilik Atla")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.secondary)
                                .padding(.vertical, 8)
                        }
                        .buttonStyle(PlainButtonStyle())
                    }
                    .padding(.horizontal, 24)
                    .padding(.bottom, 40)
                }
            }
        }
        .onAppear {
            isAnimating = true
        }
    }
}

#Preview {
    PermissionRequestView(
        permissionType: .camera,
        onAllow: {},
        onSkip: {}
    )
}
