//
//  OnboardingView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import UIKit
import Foundation

// MARK: - Onboarding Page Model
struct OnboardingPage: Identifiable {
    let id = UUID()
    let title: String
    let subtitle: String
    let icon: String
    let gradientColors: [Color]
    let iconColor: Color
    let imageName: String?
}

// MARK: - Onboarding View
struct OnboardingView: View {
    @AppStorage("hasCompletedOnboarding") private var hasCompletedOnboarding = false
    @Environment(\.colorScheme) var colorScheme
    @State private var currentPage = 0
    @State private var isCompleting = false
    @State private var viewOpacity: Double = 1.0
    
    private let pages: [OnboardingPage] = [
        OnboardingPage(
            title: "Four Kampüs'e Hoş Geldiniz",
            subtitle: "Üniversite topluluklarını keşfedin, etkinliklere katılın ve yeni insanlarla tanışın.",
            icon: "sparkles",
            gradientColors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
            iconColor: Color(hex: "6366f1"),
            imageName: "onboard_1"
        ),
        OnboardingPage(
            title: "Toplulukları Keşfedin",
            subtitle: "İlgi alanlarınıza uygun toplulukları bulun, üye olun ve aktif bir şekilde katılın.",
            icon: "person.3.fill",
            gradientColors: [Color(hex: "8b5cf6"), Color(hex: "a855f7")],
            iconColor: Color(hex: "8b5cf6"),
            imageName: "onboard_2"
        ),
        OnboardingPage(
            title: "Etkinlikleri Takip Edin",
            subtitle: "Yaklaşan etkinlikleri görüntüleyin, kayıt olun ve hiçbir fırsatı kaçırmayın.",
            icon: "calendar.badge.plus",
            gradientColors: [Color(hex: "6366f1"), Color(hex: "3b82f6")],
            iconColor: Color(hex: "6366f1"),
            imageName: "onboard_3"
        )
    ]
    
    var body: some View {
        ZStack(alignment: .top) {
            // Background Color
            Color.white.ignoresSafeArea()
            
            // 1. Pages (Carousel)
            TabView(selection: $currentPage) {
                ForEach(Array(pages.enumerated()), id: \.element.id) { index, page in
                    OnboardingPageView(page: page)
                        .tag(index)
                }
            }
            .tabViewStyle(.page(indexDisplayMode: .never))
            .animation(.easeInOut, value: currentPage)
            
            // 2. Skip Button Removed

            
            // 3. Bottom Controls
            VStack {
                Spacer()
                
                HStack {
                    // Page Indicators
                    HStack(spacing: 8) {
                        ForEach(0..<pages.count, id: \.self) { index in
                            Circle()
                                .fill(index == currentPage ? Color(hex: "6366f1") : Color.gray.opacity(0.3))
                                .frame(width: 8, height: 8)
                                .scaleEffect(index == currentPage ? 1.2 : 1.0)
                                .animation(.spring(response: 0.3), value: currentPage)
                        }
                    }
                    
                    Spacer()
                    
                    // Next / Complete Button
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .medium)
                        generator.impactOccurred()
                        
                        if currentPage < pages.count - 1 {
                            withAnimation {
                                currentPage += 1
                            }
                        } else {
                            completeWithAnimation()
                        }
                    }) {
                        ZStack {
                            Circle()
                                .fill(Color(hex: "6366f1"))
                                .shadow(color: Color(hex: "6366f1").opacity(0.4), radius: 10, x: 0, y: 5)
                            
                            Image(systemName: currentPage < pages.count - 1 ? "arrow.right" : "checkmark")
                                .font(.system(size: 20, weight: .bold))
                                .foregroundColor(.white)
                        }
                        .frame(width: 60, height: 60)
                    }
                }
                .padding(.horizontal, 32)
                .padding(.bottom, 50)
            }
        }
        .opacity(viewOpacity)
    }
    
    private func completeWithAnimation() {
        withAnimation(.easeOut(duration: 0.3)) {
            viewOpacity = 0
        }
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.3) {
            hasCompletedOnboarding = true
        }
    }
}

// MARK: - Onboarding Page View
struct OnboardingPageView: View {
    let page: OnboardingPage
    
    var body: some View {
        GeometryReader { geometry in
            VStack(spacing: 0) {
                // Top Image Area (60% height)
                ZStack {
                    LinearGradient(
                        colors: page.gradientColors,
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                    
                    if let imageName = page.imageName, let image = UIImage(named: imageName) {
                        Image(uiImage: image)
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                            .frame(width: geometry.size.width)
                            .clipped()
                    }
                    
                    // Icon Overlay if no image found (Fallback)
                    if UIImage(named: page.imageName ?? "") == nil {
                        Image(systemName: page.icon)
                            .font(.system(size: 80))
                            .foregroundColor(.white.opacity(0.3))
                    }
                }
                .frame(height: geometry.size.height * 0.6)
                // Curved Bottom Edge
                .clipShape(CustomCorner(radius: 24, corners: [.bottomLeft, .bottomRight]))
                .edgesIgnoringSafeArea(.top)
                
                // Content Area
                VStack(spacing: 20) {
                    Text(page.title)
                        .font(.system(size: 28, weight: .bold))
                        .foregroundColor(.black)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal)
                    
                    Text(page.subtitle)
                        .font(.system(size: 16, weight: .regular))
                        .foregroundColor(.black.opacity(0.6))
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 30)
                        .lineSpacing(4)
                }
                .padding(.top, 40)
                
                Spacer()
            }
        }
    }
}

// Custom Shape for rounded bottom corners
struct CustomCorner: Shape {
    var radius: CGFloat = .infinity
    var corners: UIRectCorner = .allCorners
    
    func path(in rect: CGRect) -> Path {
        let path = UIBezierPath(roundedRect: rect, byRoundingCorners: corners, cornerRadii: CGSize(width: radius, height: radius))
        return Path(path.cgPath)
    }
}

// MARK: - Preview
struct OnboardingView_Previews: PreviewProvider {
    static var previews: some View {
        OnboardingView()
    }
}
