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
            // Gradient background
            LinearGradient(
                colors: [
                    Color(hex: "6366f1"),
                    Color(hex: "8b5cf6")
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
            .ignoresSafeArea()
            
            ScrollView {
                VStack(spacing: 32) {
                    Spacer()
                        .frame(height: 40)
                    
                    // Icon
                    ZStack {
                        Circle()
                            .fill(Color.white.opacity(0.2))
                            .frame(width: 120, height: 120)
                        
                        Image(systemName: permissionType.icon)
                            .font(.system(size: 60, weight: .semibold))
                            .foregroundColor(.white)
                    }
                    .scaleEffect(isAnimating ? 1.0 : 0.8)
                    .animation(.spring(response: 0.6, dampingFraction: 0.7), value: isAnimating)
                    
                    // Title
                    Text(permissionType.title)
                        .font(.system(size: 32, weight: .bold, design: .rounded))
                        .foregroundColor(.white)
                        .multilineTextAlignment(.center)
                    
                    // Description
                    Text(permissionType.description)
                        .font(.system(size: 18, weight: .regular))
                        .foregroundColor(.white.opacity(0.95))
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                        .lineSpacing(4)
                    
                    // Benefits
                    VStack(alignment: .leading, spacing: 16) {
                        Text("Bu izin sayesinde:")
                            .font(.system(size: 20, weight: .semibold, design: .rounded))
                            .foregroundColor(.white)
                        
                        ForEach(permissionType.benefits, id: \.self) { benefit in
                            HStack(spacing: 12) {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 20))
                                    .foregroundColor(.white)
                                
                                Text(benefit)
                                    .font(.system(size: 16, weight: .regular))
                                    .foregroundColor(.white.opacity(0.95))
                                
                                Spacer()
                            }
                        }
                    }
                    .padding(24)
                    .background(
                        RoundedRectangle(cornerRadius: 20)
                            .fill(Color.white.opacity(0.15))
                    )
                    .padding(.horizontal, 24)
                    
                    Spacer()
                        .frame(height: 40)
                    
                    // Buttons
                    VStack(spacing: 16) {
                        Button(action: onAllow) {
                            HStack {
                                Image(systemName: "checkmark.circle.fill")
                                Text("İzni Ver")
                            }
                            .font(.system(size: 18, weight: .semibold))
                            .foregroundColor(Color(hex: "6366f1"))
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .background(
                                RoundedRectangle(cornerRadius: 16)
                                    .fill(Color.white)
                            )
                        }
                        .buttonStyle(PlainButtonStyle())
                        
                        Button(action: onSkip) {
                            Text("Şimdilik Atla")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundColor(.white.opacity(0.8))
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
