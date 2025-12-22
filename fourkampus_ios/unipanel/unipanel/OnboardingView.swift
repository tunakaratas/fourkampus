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
    @State private var animationOffset: CGFloat = 0
    @State private var rotationAngle: Double = 0
    @State private var isCompleting = false
    @State private var viewOpacity: Double = 1.0
    
    private let pages: [OnboardingPage] = [
        OnboardingPage(
            title: "Four Kampüs'e Hoş Geldiniz",
            subtitle: "Üniversite topluluklarını keşfedin, etkinliklere katılın ve yeni insanlarla tanışın. Üniversite hayatınızı daha anlamlı kılın.",
            icon: "sparkles",
            gradientColors: [
                Color(hex: "6366f1"),
                Color(hex: "8b5cf6"),
                Color(hex: "a855f7")
            ],
            iconColor: Color(hex: "6366f1"),
            imageName: "onboard_1"
        ),
        OnboardingPage(
            title: "Toplulukları Keşfedin",
            subtitle: "İlgi alanlarınıza uygun toplulukları bulun, üye olun ve aktif bir şekilde katılın. Binlerce öğrenciyle bağlantı kurun.",
            icon: "person.3.fill",
            gradientColors: [
                Color(hex: "8b5cf6"),
                Color(hex: "a855f7"),
                Color(hex: "c084fc")
            ],
            iconColor: Color(hex: "8b5cf6"),
            imageName: "onboard_2"
        ),
        OnboardingPage(
            title: "Etkinlikleri Takip Edin",
            subtitle: "Yaklaşan etkinlikleri görüntüleyin, kayıt olun ve topluluk etkinliklerine katılın. Hiçbir fırsatı kaçırmayın.",
            icon: "calendar.badge.plus",
            gradientColors: [
                Color(hex: "6366f1"),
                Color(hex: "3b82f6"),
                Color(hex: "60a5fa")
            ],
            iconColor: Color(hex: "6366f1"),
            imageName: "onboard_3"
        )
    ]
    
    var body: some View {
        VStack(spacing: 0) {
            // Skip Button - Top Right
            HStack {
                Spacer()
                if currentPage < pages.count - 1 {
                    Button(action: {
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.impactOccurred()
                        withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                            completeOnboarding()
                        }
                    }) {
                        Text("Atla")
                            .font(.system(size: 16, weight: .medium, design: .rounded))
                            .foregroundColor(.black.opacity(0.6))
                            .padding(.horizontal, 20)
                            .padding(.vertical, 10)
                    }
                }
            }
            .padding(.top, 50)
            .padding(.horizontal, 24)
            .zIndex(1)
            
            // Page Content
            ZStack {
                TabView(selection: $currentPage) {
                    ForEach(Array(pages.enumerated()), id: \.element.id) { index, page in
                        OnboardingPageView(
                            page: page,
                            pageIndex: index,
                            totalPages: pages.count,
                            currentPage: $currentPage,
                            onNext: {
                                withAnimation(.spring(response: 0.4, dampingFraction: 0.7)) {
                                    currentPage += 1
                                }
                            },
                            onComplete: {
                                startCompletionAnimation()
                            },
                            loadImage: loadOnboardingImage
                        )
                        .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .never))
                .opacity(viewOpacity)
                
                // Fixed Bottom Controls - Outside TabView
                GeometryReader { geometry in
                    VStack {
                        Spacer()
                        
                        // White Background for Controls
                        VStack(spacing: 0) {
                            // Page Indicator and Button Row - Fixed
                            HStack(spacing: 16) {
                                // Page Indicator Dots
                                HStack(spacing: 10) {
                                    ForEach(0..<pages.count, id: \.self) { index in
                                        Capsule()
                                            .fill(index == currentPage ? Color(hex: "6366f1") : Color.gray.opacity(0.25))
                                            .frame(width: index == currentPage ? 24 : 8, height: 8)
                                            .animation(.spring(response: 0.3, dampingFraction: 0.7), value: currentPage)
                                    }
                                }
                                
                                Spacer()
                                
                                // Action Button (Circular with shadow) - Fixed
                                Button(action: {
                                    let generator = UIImpactFeedbackGenerator(style: .medium)
                                    generator.impactOccurred()
                                    
                                    if currentPage < pages.count - 1 {
                                        withAnimation(.spring(response: 0.4, dampingFraction: 0.7)) {
                                            currentPage += 1
                                        }
                                    } else {
                                        // Last page - Start with beautiful animation
                                        startCompletionAnimation()
                                    }
                                }) {
                                    ZStack {
                                        Circle()
                                            .fill(
                                                LinearGradient(
                                                    colors: [
                                                        Color(hex: "6366f1"),
                                                        Color(hex: "8b5cf6")
                                                    ],
                                                    startPoint: .topLeading,
                                                    endPoint: .bottomTrailing
                                                )
                                            )
                                            .shadow(color: Color(hex: "6366f1").opacity(0.4), radius: 12, x: 0, y: 6)
                                        
                                        Image(systemName: currentPage < pages.count - 1 ? "arrow.right" : "checkmark")
                                            .font(.system(size: 18, weight: .semibold))
                                            .foregroundColor(.white)
                                    }
                                    .frame(width: 60, height: 60)
                                }
                                .disabled(isCompleting)
                            }
                            .padding(.horizontal, 24)
                            .padding(.top, 16)
                            .padding(.bottom, geometry.safeAreaInsets.bottom + 24)
                            .background(Color.white)
                        }
                    }
                }
            }
        }
        .background(Color.white)
    }
    
    private func completeOnboarding() {
        hasCompletedOnboarding = true
    }
    
    private func startCompletionAnimation() {
        isCompleting = true
        
        // Haptic feedback
        let generator = UINotificationFeedbackGenerator()
        generator.notificationOccurred(.success)
        
        // Simple fade out without any scaling animations
        withAnimation(.easeInOut(duration: 0.3)) {
            viewOpacity = 0.0
        }
        
        // Complete onboarding after fade
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.3) {
            completeOnboarding()
        }
    }
    
    // Helper function to load onboarding images with multiple fallback strategies
    private func loadOnboardingImage(name: String) -> UIImage? {
        // Method 1: Try UIImage(named:) - standard asset catalog method (PREFERRED)
        if let image = UIImage(named: name) {
            #if DEBUG
            print("✅ Loaded \(name) via UIImage(named:)")
            #endif
            return image
        }
        
        // Method 2: Try with explicit bundle
        if let image = UIImage(named: name, in: .main, compatibleWith: nil) {
            #if DEBUG
            print("✅ Loaded \(name) via UIImage(named:in:compatibleWith:)")
            #endif
            return image
        }
        
        // Method 3: Try bundle resource paths (if added to Copy Bundle Resources)
        if let bundlePath = Bundle.main.path(forResource: name, ofType: "png") {
            if let image = UIImage(contentsOfFile: bundlePath) {
                #if DEBUG
                print("✅ Loaded \(name) from bundle: \(bundlePath)")
                #endif
                return image
            }
        }
        
        // Method 4: Try Resources folder (development fallback)
        if let resourcesPath = Bundle.main.path(forResource: name, ofType: "png", inDirectory: "Resources") {
            if let image = UIImage(contentsOfFile: resourcesPath) {
                #if DEBUG
                print("✅ Loaded \(name) from Resources folder")
                #endif
                return image
            }
        }
        
        #if DEBUG
        print("❌ Could not load image '\(name)' using any method")
        print("   Tried: UIImage(named:), Bundle.main.path(forResource:), Resources folder")
        #endif
        
        return nil
    }
}

// MARK: - Onboarding Page View
struct OnboardingPageView: View {
    let page: OnboardingPage
    let pageIndex: Int
    let totalPages: Int
    @Binding var currentPage: Int
    let onNext: () -> Void
    let onComplete: () -> Void
    let loadImage: (String) -> UIImage?
    
    var body: some View {
        GeometryReader { geometry in
            ZStack(alignment: .top) {
                // Top Image Section - Moved up with offset
                if let imageName = page.imageName {
                    Group {
                        if let uiImage = loadImage(imageName) {
                            Image(uiImage: uiImage)
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                                .frame(width: geometry.size.width, height: geometry.size.height * 0.7)
                                .clipped()
                                .offset(y: -geometry.safeAreaInsets.top - 20)
                        } else {
                            // Fallback gradient
                            LinearGradient(
                                colors: page.gradientColors,
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                            .frame(height: geometry.size.height * 0.7)
                            .offset(y: -geometry.safeAreaInsets.top - 20)
                        }
                    }
                    .onAppear {
                        #if DEBUG
                        let loaded = loadImage(imageName) != nil
                        if loaded {
                            print("✅ Onboarding: Image '\(imageName)' loaded successfully")
                        } else {
                            print("⚠️ Onboarding: Image '\(imageName)' NOT found!")
                        }
                        #endif
                    }
                } else {
                    // Last page - gradient background
                    LinearGradient(
                        colors: page.gradientColors,
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                    .frame(height: geometry.size.height * 0.7)
                    .offset(y: -geometry.safeAreaInsets.top - 20)
                }
                
                // White Content Section - Sharp transition at bottom
                VStack(spacing: 0) {
                    Spacer()
                    
                    // White Content Card - Sharp edge
                    VStack(spacing: 16) {
                        // Title
                        Text(page.title)
                            .font(.system(size: 30, weight: .bold, design: .rounded))
                            .foregroundColor(.black)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 32)
                            .padding(.top, 28)
                        
                        // Subtitle
                        Text(page.subtitle)
                            .font(.system(size: 17, weight: .regular, design: .rounded))
                            .foregroundColor(.black.opacity(0.65))
                            .multilineTextAlignment(.center)
                            .lineSpacing(6)
                            .padding(.horizontal, 40)
                            .fixedSize(horizontal: false, vertical: true)
                        
                        Spacer(minLength: 0)
                    }
                    .frame(height: geometry.size.height * 0.38)
                    .frame(maxWidth: .infinity)
                    .background(Color.white)
                }
                .frame(maxHeight: .infinity, alignment: .bottom)
            }
        }
    }
}

// MARK: - Preview
struct OnboardingView_Previews: PreviewProvider {
    static var previews: some View {
        OnboardingView()
            .preferredColorScheme(.light)
        
        OnboardingView()
            .preferredColorScheme(.dark)
    }
}
