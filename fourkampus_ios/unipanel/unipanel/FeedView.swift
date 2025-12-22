//
//  FeedView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct FeedView: View {
    @StateObject private var viewModel = FeedViewModel()
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var showLoginModal = false
    @State private var selectedPost: Post?
    
    var body: some View {
        ZStack {
            Color(UIColor.systemBackground)
                .ignoresSafeArea()
            
            if viewModel.isLoading && viewModel.posts.isEmpty {
                // Skeleton Loading
                ScrollView {
                    LazyVStack(spacing: 16) {
                        ForEach(0..<5) { _ in
                            PostCardSkeleton()
                        }
                    }
                    .padding(.top, 8)
                }
            } else if viewModel.posts.isEmpty {
                // Empty State
                VStack(spacing: 20) {
                    Image(systemName: "photo.on.rectangle.angled")
                        .font(.system(size: 64))
                        .foregroundColor(.secondary.opacity(0.5))
                    Text("Henüz Post Yok")
                        .font(.system(size: 22, weight: .bold))
                        .foregroundColor(.primary)
                    Text("Takip ettiğiniz toplulukların paylaşımları burada görünecek")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 40)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                ScrollView {
                    LazyVStack(spacing: 0) {
                        ForEach(viewModel.posts) { post in
                            PostCard(
                                post: post,
                                isAuthenticated: authViewModel.isAuthenticated,
                                onLike: {
                                    if authViewModel.isAuthenticated {
                                        Task {
                                            await viewModel.toggleLike(postId: post.id)
                                        }
                                    } else {
                                        showLoginModal = true
                                    }
                                },
                                onComment: {
                                    if authViewModel.isAuthenticated {
                                        selectedPost = post
                                    } else {
                                        showLoginModal = true
                                    }
                                },
                                onTap: {
                                    selectedPost = post
                                }
                            )
                            .padding(.bottom, 8)
                        }
                    }
                    .padding(.top, 8)
                }
                .refreshable {
                    await viewModel.loadPosts()
                }
            }
        }
        .navigationTitle("Feed")
        .navigationBarTitleDisplayMode(.large)
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
        }
        .sheet(item: $selectedPost) { post in
            PostDetailView(post: post, viewModel: viewModel)
        }
        .task {
            await viewModel.loadPosts()
        }
    }
}

// MARK: - Post Card
struct PostCard: View {
    let post: Post
    let isAuthenticated: Bool
    let onLike: () -> Void
    let onComment: () -> Void
    let onTap: () -> Void
    @State private var isLiked: Bool
    @State private var likeCount: Int
    @State private var showFullImage = false
    @State private var selectedImageIndex = 0
    
    init(post: Post, isAuthenticated: Bool, onLike: @escaping () -> Void, onComment: @escaping () -> Void, onTap: @escaping () -> Void) {
        self.post = post
        self.isAuthenticated = isAuthenticated
        self.onLike = onLike
        self.onComment = onComment
        self.onTap = onTap
        _isLiked = State(initialValue: post.isLiked)
        _likeCount = State(initialValue: post.likeCount)
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Header
            HStack(spacing: 12) {
                // Community Logo
                if let logo = post.communityLogo, !logo.isEmpty {
                    CachedAsyncImage(url: logo) { image in
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                    } placeholder: {
                        Circle()
                            .fill(
                                LinearGradient(
                                    colors: [Color(hex: "8b5cf6"), Color(hex: "6366f1")],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                            .overlay(
                                Text(post.communityName.prefix(1).uppercased())
                                    .font(.system(size: 18, weight: .bold))
                                    .foregroundColor(.white)
                            )
                    }
                    .frame(width: 40, height: 40)
                    .clipShape(Circle())
                } else {
                    Circle()
                        .fill(
                            LinearGradient(
                                colors: [Color(hex: "8b5cf6"), Color(hex: "6366f1")],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                        .frame(width: 40, height: 40)
                        .overlay(
                            Text(post.communityName.prefix(1).uppercased())
                                .font(.system(size: 18, weight: .bold))
                                .foregroundColor(.white)
                        )
                }
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(post.communityName)
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundColor(.primary)
                    
                    HStack(spacing: 4) {
                        Text(post.timeAgo)
                            .font(.system(size: 13))
                            .foregroundColor(.secondary)
                        
                        if post.type != .general {
                            Text("•")
                                .font(.system(size: 13))
                                .foregroundColor(.secondary)
                            
                            Image(systemName: post.type == .event ? "calendar" : "tag.fill")
                                .font(.system(size: 11))
                                .foregroundColor(.secondary)
                        }
                    }
                }
                
                Spacer()
                
                Button(action: {}) {
                    Image(systemName: "ellipsis")
                        .font(.system(size: 16))
                        .foregroundColor(.secondary)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            
            // Content
            if !post.content.isEmpty {
                Text(post.content)
                    .font(.system(size: 15))
                    .foregroundColor(.primary)
                    .lineLimit(nil)
                    .padding(.horizontal, 16)
                    .padding(.bottom, 12)
            }
            
            // Images
            if !post.images.isEmpty {
                TabView(selection: $selectedImageIndex) {
                    ForEach(Array(post.images.enumerated()), id: \.offset) { index, imageUrl in
                        CachedAsyncImage(url: imageUrl) { image in
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fill)
                                .onTapGesture {
                                    showFullImage = true
                                }
                        } placeholder: {
                            Rectangle()
                                .fill(Color.gray.opacity(0.2))
                                .overlay(ProgressView())
                        }
                        .frame(height: 400)
                        .clipped()
                        .tag(index)
                    }
                }
                .frame(height: 400)
                .tabViewStyle(.page)
                .indexViewStyle(.page(backgroundDisplayMode: .always))
                .padding(.bottom, 12)
            }
            
            // Video
            if let video = post.video, !video.isEmpty {
                VideoThumbnailView(videoPath: video)
                    .frame(height: 400)
                    .padding(.bottom, 12)
            }
            
            // Actions
            HStack(spacing: 24) {
                // Like Button
                Button(action: {
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.6)) {
                        isLiked.toggle()
                        likeCount += isLiked ? 1 : -1
                    }
                    onLike()
                }) {
                    HStack(spacing: 6) {
                        Image(systemName: isLiked ? "heart.fill" : "heart")
                            .font(.system(size: 24))
                            .foregroundColor(isLiked ? Color(hex: "ef4444") : .primary)
                            .scaleEffect(isLiked ? 1.2 : 1.0)
                        
                        if likeCount > 0 {
                            Text("\(likeCount)")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.primary)
                        }
                    }
                }
                
                // Comment Button
                Button(action: onComment) {
                    HStack(spacing: 6) {
                        Image(systemName: "bubble.right")
                            .font(.system(size: 24))
                            .foregroundColor(.primary)
                        
                        if post.commentCount > 0 {
                            Text("\(post.commentCount)")
                                .font(.system(size: 15, weight: .medium))
                                .foregroundColor(.primary)
                        }
                    }
                }
                
                Spacer()
                
                // Share Button
                Button(action: {}) {
                    Image(systemName: "paperplane")
                        .font(.system(size: 24))
                        .foregroundColor(.primary)
                }
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 12)
            
            // Like count text
            if likeCount > 0 {
                Text("\(likeCount) beğeni")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.primary)
                    .padding(.horizontal, 16)
                    .padding(.bottom, 8)
            }
        }
        .background(Color(UIColor.secondarySystemBackground))
        .padding(.bottom, 8)
        .onTapGesture {
            onTap()
        }
        .sheet(isPresented: $showFullImage) {
            ImageViewer(images: post.images, selectedIndex: selectedImageIndex)
        }
    }
}

// MARK: - Video Thumbnail View
struct VideoThumbnailView: View {
    let videoPath: String
    
    var body: some View {
        ZStack {
            Rectangle()
                .fill(Color.black.opacity(0.1))
            
            VStack(spacing: 12) {
                Image(systemName: "play.circle.fill")
                    .font(.system(size: 60))
                    .foregroundColor(.white)
                Text("Video")
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(.white)
            }
        }
    }
}

// MARK: - Post Card Skeleton
struct PostCardSkeleton: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Header Skeleton
            HStack(spacing: 12) {
                SkeletonView()
                    .frame(width: 40, height: 40)
                    .clipShape(Circle())
                
                VStack(alignment: .leading, spacing: 6) {
                    SkeletonView()
                        .frame(width: 120, height: 16)
                    SkeletonView()
                        .frame(width: 80, height: 12)
                }
                
                Spacer()
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            
            // Content Skeleton
            SkeletonView()
                .frame(height: 200)
                .padding(.bottom, 12)
            
            // Actions Skeleton
            HStack(spacing: 24) {
                SkeletonView()
                    .frame(width: 60, height: 24)
                SkeletonView()
                    .frame(width: 60, height: 24)
                Spacer()
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 12)
        }
        .background(Color(UIColor.secondarySystemBackground))
        .padding(.bottom, 8)
    }
}

// MARK: - Image Viewer
struct ImageViewer: View {
    let images: [String]
    @State var selectedIndex: Int
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationStack {
            TabView(selection: $selectedIndex) {
                ForEach(Array(images.enumerated()), id: \.offset) { index, imageUrl in
                    CachedAsyncImage(url: imageUrl) { image in
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fit)
                    } placeholder: {
                        ProgressView()
                    }
                    .tag(index)
                }
            }
            .tabViewStyle(.page)
            .indexViewStyle(.page(backgroundDisplayMode: .always))
            .navigationTitle("\(selectedIndex + 1) / \(images.count)")
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

