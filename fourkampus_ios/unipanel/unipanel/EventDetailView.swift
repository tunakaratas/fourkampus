//
//  EventDetailView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import AVKit

struct EventDetailView: View {
    let event: Event
    let verificationInfo: VerifiedCommunityInfo?
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var showQRCode = false
    @State private var membershipStatus: MembershipStatus?
    @State private var isLoadingMembership = false
    
    // Üyelik kontrolü - Event'ten gelen bilgiyi veya API'den çekilen bilgiyi kullan
    private var isMember: Bool {
        if let eventIsMember = event.isMember {
            return eventIsMember
        }
        if let status = membershipStatus {
            return status.isMember || status.status == "member" || status.status == "approved"
        }
        return false
    }
    
    var body: some View {
        ScrollView {
            VStack(spacing: 0) {
                // Hero Section
                EventHeroSection(event: event)
                
                // Content
                VStack(spacing: 24) {
                    // Basic Info
                    EventBasicInfoSection(event: event)
                    
                    // Additional Info
                    EventAdditionalInfoSection(event: event)
                    
                    // Üyelik kontrolü mesajı - Üye değilse göster
                    if !isMember && authViewModel.isAuthenticated {
                        MembershipRequiredCard(
                            message: "Bu etkinliğin üyeye özel özelliklerini görmek için topluluğa üye olmalısınız.",
                            onJoin: {
                                // Topluluk detay sayfasına yönlendir
                            }
                        )
                    }
                    
                    // RSVP Section - Sadece üye ise göster
                    if isMember || !authViewModel.isAuthenticated {
                        EventRSVPView(event: event)
                    }
                    
                    // Survey Section (if available) - Sadece üye ise göster
                    if event.hasSurvey && (isMember || !authViewModel.isAuthenticated) {
                        EventSurveyView(event: event)
                    }
                    
                    // Media Section (if available)
                    if event.hasMedia {
                        EventMediaSection(event: event)
                    }
                }
                .padding(16)
                .padding(.top, 24)
            }
        }
        .background(Color(UIColor.systemBackground).ignoresSafeArea())
        .navigationTitle(event.title)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 16) {
                    // Share Button
                    Button(action: {
                        let dateFormatter = DateFormatter()
                        dateFormatter.dateFormat = "yyyy-MM-dd"
                        let dateString = dateFormatter.string(from: event.date)
                        // Share event
                        let shareText = "\(event.title) - \(event.communityName)\nTarih: \(dateString)"
                        let activityVC = UIActivityViewController(activityItems: [shareText], applicationActivities: nil)
                        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
                           let rootViewController = windowScene.windows.first?.rootViewController {
                            rootViewController.present(activityVC, animated: true)
                        }
                        let generator = UIImpactFeedbackGenerator(style: .light)
                        generator.impactOccurred()
                    }) {
                        Image(systemName: "square.and.arrow.up")
                            .font(.system(size: 20))
                            .foregroundColor(Color(hex: "8b5cf6"))
                    }
                    
                    // QR Code Button
                    Button(action: {
                        showQRCode = true
                    }) {
                        Image(systemName: "qrcode")
                            .font(.system(size: 20))
                            .foregroundColor(Color(hex: "8b5cf6"))
                    }
                }
            }
        }
        .sheet(isPresented: $showQRCode) {
            let qrContent = QRCodeGenerator.createEventQRContent(communityId: event.communityId, eventId: event.id)
            QRCodeView(
                title: event.title,
                content: qrContent,
                isPresented: $showQRCode
            )
        }
        .onAppear {
            // Event'ten gelen is_member bilgisi yoksa API'den çek
            if event.isMember == nil && authViewModel.isAuthenticated {
                loadMembershipStatus()
            }
        }
    }
    
    private func loadMembershipStatus() {
        guard authViewModel.isAuthenticated && !isLoadingMembership else { return }
        
        isLoadingMembership = true
        Task {
            do {
                let status = try await APIService.shared.getMembershipStatus(communityId: event.communityId)
                await MainActor.run {
                    membershipStatus = status
                    isLoadingMembership = false
                }
            } catch {
                await MainActor.run {
                    isLoadingMembership = false
                    membershipStatus = nil
                }
            }
        }
    }
}

// MARK: - Membership Required Card
struct MembershipRequiredCard: View {
    let message: String
    let onJoin: () -> Void
    
    var body: some View {
        VStack(spacing: 12) {
            HStack(spacing: 12) {
                Image(systemName: "info.circle.fill")
                    .font(.system(size: 20))
                    .foregroundColor(Color(hex: "6366f1"))
                
                Text(message)
                    .font(.system(size: 14))
                    .foregroundColor(.primary)
                
                Spacer()
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 12)
                    .fill(Color(UIColor.secondarySystemBackground))
            )
        }
    }
}

// MARK: - Event Hero Section
struct EventHeroSection: View {
    let event: Event
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
                // Category Badge
            HStack {
                    Text(event.category.rawValue)
                    .font(.system(size: 12, weight: .semibold))
                .foregroundColor(.white)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(event.category.color)
                    .cornerRadius(8)
                Spacer()
            }
                
                // Title
                Text(event.title)
                .font(.system(size: 28, weight: .bold))
                .foregroundColor(.primary)
                
                // Community Name
                HStack(spacing: 6) {
                    Image(systemName: "person.3.fill")
                        .font(.system(size: 14))
                    .foregroundColor(.secondary)
                    Text(event.communityName)
                        .font(.system(size: 16, weight: .medium))
                    .foregroundColor(.secondary)
            }
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color(UIColor.secondarySystemBackground))
    }
}

// MARK: - Event Basic Info Section
struct EventBasicInfoSection: View {
    let event: Event
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Detaylar")
                .font(.system(size: 20, weight: .bold))
                .foregroundColor(.primary)
            
            // Date & Time
            InfoRow(icon: "calendar", title: "Tarih", value: event.formattedDate)
            InfoRow(icon: "clock.fill", title: "Saat", value: event.formattedTime)
            
            // Location
            if let location = event.location {
                InfoRow(icon: "location.fill", title: "Konum", value: location)
}

            // Description
            if !event.description.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                    Text("Açıklama")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(event.description)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                }
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
        )
    }
}

struct InfoRow: View {
    let icon: String
    let title: String
    let value: String
    
    var body: some View {
        HStack(spacing: 12) {
                Image(systemName: icon)
                .font(.system(size: 16))
                .foregroundColor(Color(hex: "6366f1"))
                .frame(width: 24)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.system(size: 13))
                    .foregroundColor(.secondary)
                Text(value)
                    .font(.system(size: 15, weight: .medium))
                    .foregroundColor(.primary)
            }
            Spacer()
                }
            }
        }

// MARK: - Event Additional Info Section
struct EventAdditionalInfoSection: View {
    let event: Event
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Capacity
            if let capacity = event.capacity {
                InfoRow(icon: "person.2.fill", title: "Kapasite", value: "\(event.registeredCount) / \(capacity)")
            }
            
            // Price
            if let price = event.price ?? event.cost {
                let priceText = event.currency == "TRY" || event.currency == nil ? "\(Int(price)) ₺" : "\(Int(price)) \(event.currency ?? "")"
                InfoRow(icon: "tag.fill", title: "Ücret", value: priceText)
                        }
            
            // Organizer
            if !event.organizer.isEmpty {
                InfoRow(icon: "person.fill", title: "Organizatör", value: event.organizer)
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
        )
            }
}

// MARK: - Event Media Section
struct EventMediaSection: View {
    let event: Event
    @State private var selectedImageIndex: Int? = nil
    @State private var currentImageIndex: Int = 0
    
    var allMediaItems: [MediaItem] {
        var items: [MediaItem] = []
        
        // Görselleri ekle
        for (index, imagePath) in event.allImages.enumerated() {
            items.append(MediaItem(id: "img_\(index)", type: .image, path: imagePath))
        }
        
        // Videoları ekle
        for (index, videoPath) in event.allVideos.enumerated() {
            items.append(MediaItem(id: "vid_\(index)", type: .video, path: videoPath))
        }
        
        return items
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Medya")
                .font(.system(size: 20, weight: .bold, design: .default))
                .foregroundColor(.primary)
            
            if allMediaItems.isEmpty {
                Text("Medya bulunamadı")
                    .font(.system(size: 14))
                .foregroundColor(.secondary)
                    .frame(maxWidth: .infinity, alignment: .center)
                    .padding(.vertical, 40)
            } else if allMediaItems.count == 1 {
                // Tek medya varsa direkt göster
                MediaItemView(item: allMediaItems[0], onTap: {
                    selectedImageIndex = 0
                })
            } else {
                // Birden fazla medya varsa carousel göster
                TabView(selection: $currentImageIndex) {
                    ForEach(Array(allMediaItems.enumerated()), id: \.element.id) { index, item in
                        MediaItemView(item: item, onTap: {
                            selectedImageIndex = index
                        })
                        .tag(index)
                    }
                }
                .tabViewStyle(.page)
                .frame(height: 250)
                .onAppear {
                    // Page indicator stilini ayarla
                    UIPageControl.appearance().currentPageIndicatorTintColor = UIColor(Color(hex: "6366f1"))
                    UIPageControl.appearance().pageIndicatorTintColor = UIColor.gray.withAlphaComponent(0.3)
                }
                
                // Görsel sayısı göstergesi
            HStack {
                Spacer()
                    Text("\(currentImageIndex + 1) / \(allMediaItems.count)")
                        .font(.system(size: 12, weight: .medium))
                        .foregroundColor(.secondary)
                                    .padding(.horizontal, 12)
                        .padding(.vertical, 4)
                        .background(Color(UIColor.secondarySystemBackground))
                                    .cornerRadius(12)
                            }
                .padding(.top, 8)
                        }
                    }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
        )
        .sheet(item: Binding(
            get: { 
                guard let index = selectedImageIndex, index < allMediaItems.count else { return nil }
                return allMediaItems[index]
            },
            set: { (newValue: MediaItem?) in selectedImageIndex = nil }
        )) { (item: MediaItem) in
            if item.type == .image {
                ImageDetailView(imagePath: item.path)
            } else if item.type == .video {
                // Video için full screen player
                let videoURL = APIService.fullImageURL(from: item.path) ?? item.path
                if let url = URL(string: videoURL) {
                    VideoFullScreenView(player: nil, videoURL: url)
                }
            }
        }
    }
}

struct MediaItem: Identifiable {
    let id: String
    let type: MediaType
    let path: String
    
    enum MediaType {
        case image
        case video
    }
}

struct MediaItemView: View {
    let item: MediaItem
    let onTap: () -> Void
    
    var body: some View {
        Group {
            if item.type == .image {
                let imageURL = APIService.fullImageURL(from: item.path) ?? item.path
                AsyncImage(url: URL(string: imageURL)) { phase in
                    switch phase {
                    case .success(let image):
                    image
                        .resizable()
                        .aspectRatio(contentMode: .fill)
                    case .failure(_):
                        Image(systemName: "photo")
                            .font(.system(size: 40))
                            .foregroundColor(.gray)
                    case .empty:
                    Rectangle()
                        .fill(Color(UIColor.secondarySystemBackground))
                        .overlay(
                            ProgressView()
                                .tint(Color(hex: "6366f1"))
                        )
                    @unknown default:
                        EmptyView()
                    }
                }
                .frame(height: 250)
                .cornerRadius(16)
                .onTapGesture {
                    onTap()
                }
            } else {
                VideoPlayerView(videoPath: item.path)
                    .frame(height: 250)
                    .cornerRadius(16)
            }
        }
    }
}

// MARK: - Image Detail View
struct ImageDetailView: View {
    let imagePath: String
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            ScrollView {
                let imageURL = APIService.fullImageURL(from: imagePath) ?? imagePath
                AsyncImage(url: URL(string: imageURL)) { phase in
                    switch phase {
                    case .success(let image):
                    image
                        .resizable()
                        .aspectRatio(contentMode: .fit)
                    case .failure(_):
                        VStack {
                            Image(systemName: "photo")
                                .font(.system(size: 60))
                                .foregroundColor(.gray)
                            Text("Görsel yüklenemedi")
                                .font(.system(size: 16))
                                .foregroundColor(.secondary)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                    case .empty:
                    ProgressView()
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                    @unknown default:
                        EmptyView()
                    }
                }
                .padding()
            }
            .navigationTitle("Fotoğraf")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        dismiss()
                    }
                }
            }
        }
    }
}

// MARK: - Video Player View
struct VideoPlayerView: View {
    let videoPath: String
    @State private var player: AVPlayer?
    @State private var showFullScreen = false
    
    var videoURL: URL? {
        let fullURL = APIService.fullImageURL(from: videoPath) ?? videoPath
        return URL(string: fullURL)
    }
    
    var body: some View {
        Group {
            if let url = videoURL {
                ZStack {
                    // Video player
                    if let player = player {
                        VideoPlayer(player: player)
                            .onAppear {
                                player.play()
                            }
                            .onDisappear {
                                player.pause()
                            }
                    } else {
                        // Loading state
                        VStack {
                            ProgressView()
                                .tint(.white)
                            Text("Video yükleniyor...")
                                .font(.system(size: 14))
                                .foregroundColor(.white)
                                .padding(.top, 8)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                        .background(Color.black)
                    }
                    
                    // Play button overlay (ilk yüklemede)
                    if player == nil {
                        Button(action: {
                            loadVideo()
                        }) {
                            VStack(spacing: 8) {
                                Image(systemName: "play.circle.fill")
                                    .font(.system(size: 60))
                                    .foregroundColor(.white)
                                Text("Video Oynat")
                                    .font(.system(size: 16, weight: .semibold))
                                    .foregroundColor(.white)
                            }
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                        .background(
                            LinearGradient(
                                colors: [Color(hex: "6366f1").opacity(0.8), Color(hex: "8b5cf6").opacity(0.8)],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                    }
                }
                .onTapGesture {
                    if player != nil {
                        showFullScreen = true
                    }
                }
                .fullScreenCover(isPresented: $showFullScreen) {
                    VideoFullScreenView(player: player, videoURL: url)
                }
            } else {
                // Invalid URL
                VStack {
                    Image(systemName: "exclamationmark.triangle")
                        .font(.system(size: 40))
                        .foregroundColor(.gray)
                    Text("Video URL'si geçersiz")
                        .font(.system(size: 14))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Color(UIColor.secondarySystemBackground))
            }
        }
        .onAppear {
            // Video'yu otomatik yükleme (kullanıcı play'e basana kadar)
        }
    }
    
    private func loadVideo() {
        guard let url = videoURL else { return }
        let newPlayer = AVPlayer(url: url)
        player = newPlayer
        
        // Video hazır olduğunda otomatik oynat
        NotificationCenter.default.addObserver(
            forName: .AVPlayerItemDidPlayToEndTime,
            object: newPlayer.currentItem,
            queue: .main
        ) { _ in
            newPlayer.seek(to: .zero)
        }
    }
}

// MARK: - Video Full Screen View
struct VideoFullScreenView: View {
    let player: AVPlayer?
    let videoURL: URL
    @Environment(\.dismiss) var dismiss
    @State private var fullScreenPlayer: AVPlayer?
    
    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()
                
                if let player = fullScreenPlayer {
                    VideoPlayer(player: player)
                        .onAppear {
                            player.play()
                        }
                        .onDisappear {
                            player.pause()
                        }
                } else {
                    ProgressView()
                        .tint(.white)
                }
            }
            .navigationTitle("Video")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        fullScreenPlayer?.pause()
                        dismiss()
                    }
                    .foregroundColor(.white)
                }
            }
            .onAppear {
                // Full screen için yeni player oluştur
                fullScreenPlayer = AVPlayer(url: videoURL)
                fullScreenPlayer?.play()
            }
            .onDisappear {
                fullScreenPlayer?.pause()
                fullScreenPlayer = nil
            }
        }
    }
}
