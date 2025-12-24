//
//  PostDetailView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct PostDetailView: View {
    let post: Post
    @ObservedObject var viewModel: FeedViewModel
    @Environment(\.dismiss) var dismiss
    @State private var comments: [Comment] = []
    @State private var isLoadingComments = false
    
    var body: some View {
        NavigationStack {
            ScrollView {
                PostCard(
                    post: post,
                    isAuthenticated: true,
                    onLike: {
                        Task {
                            await viewModel.toggleLike(postId: post.id)
                        }
                    },
                    onComment: {},
                    onTap: {}
                )
                
                // Comments Section
                    CommentsSection(post: post, comments: $comments, viewModel: viewModel)
            }
            .navigationTitle("Post")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        dismiss()
                    }
                }
            }
            .task {
                await loadComments()
            }
        }
    }
    
    private func loadComments() async {
        isLoadingComments = true
        do {
            let loadedComments = try await viewModel.getComments(postId: post.id)
            await MainActor.run {
                comments = loadedComments
                isLoadingComments = false
            }
        } catch {
            await MainActor.run {
                isLoadingComments = false
            }
        }
    }
}

// MARK: - Comments Section
struct CommentsSection: View {
    let post: Post
    @Binding var comments: [Comment]
    @ObservedObject var viewModel: FeedViewModel
    @State private var newComment = ""
    @State private var isSubmitting = false
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Yorumlar")
                .font(.system(size: 18, weight: .bold))
                .padding(.horizontal, 16)
            
            if comments.isEmpty {
                Text("Henüz yorum yok")
                    .font(.system(size: 14))
                    .foregroundColor(.secondary)
                    .frame(maxWidth: .infinity, alignment: .center)
                    .padding(.vertical, 40)
            } else {
                ForEach(comments) { comment in
                    CommentRow(comment: comment)
                        .padding(.horizontal, 16)
                }
            }
            
            // Add Comment
            HStack(spacing: 12) {
                TextField("Yorum yaz...", text: $newComment, axis: .vertical)
                    .textFieldStyle(.roundedBorder)
                    .lineLimit(1...4)
                
                Button(action: {
                    Task {
                        await submitComment()
                    }
                }) {
                    Image(systemName: "paperplane.fill")
                        .font(.system(size: 18))
                        .foregroundColor(newComment.isEmpty ? .secondary : Color(hex: "6366f1"))
                }
                .disabled(newComment.isEmpty || isSubmitting)
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 16)
        }
    }
    
    private func submitComment() async {
        guard !newComment.isEmpty else { return }
        
        isSubmitting = true
        let commentText = newComment
        newComment = ""
        
        do {
            let comment = try await viewModel.addComment(postId: post.id, content: commentText)
            await MainActor.run {
                comments.append(comment)
                isSubmitting = false
            }
        } catch {
            await MainActor.run {
                newComment = commentText
                isSubmitting = false
            }
        }
    }
}

// MARK: - Comment Row
struct CommentRow: View {
    let comment: Comment
    
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            // Avatar
            if let avatar = comment.userAvatar, !avatar.isEmpty {
                CachedAsyncImage(url: avatar) { image in
                    image
                        .resizable()
                        .aspectRatio(contentMode: .fill)
                } placeholder: {
                    Circle()
                        .fill(Color.gray.opacity(0.3))
                }
                .frame(width: 32, height: 32)
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
                    .frame(width: 32, height: 32)
                    .overlay(
                        Text(comment.userName.prefix(1).uppercased())
                            .font(.system(size: 12, weight: .bold))
                            .foregroundColor(.white)
                    )
            }
            
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 8) {
                    Text(comment.userName)
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.primary)
                    
                    Text(comment.timeAgo)
                        .font(.system(size: 12))
                        .foregroundColor(.secondary)
                }
                
                Text(comment.content)
                    .font(.system(size: 14))
                    .foregroundColor(.primary)
                
                // Like button
                if comment.likeCount > 0 || comment.isLiked {
                    HStack(spacing: 4) {
                        Button(action: {}) {
                            Image(systemName: comment.isLiked ? "heart.fill" : "heart")
                                .font(.system(size: 12))
                                .foregroundColor(comment.isLiked ? Color(hex: "ef4444") : .secondary)
                        }
                        
                        if comment.likeCount > 0 {
                            Text("\(comment.likeCount)")
                                .font(.system(size: 12))
                                .foregroundColor(.secondary)
                        }
                    }
                    .padding(.top, 4)
                }
            }
            
            Spacer()
        }
        .padding(.vertical, 8)
    }
}

// MARK: - Comments View
struct CommentsView: View {
    let post: Post
    @ObservedObject var viewModel: FeedViewModel
    @Environment(\.dismiss) var dismiss
    @State private var comments: [Comment] = []
    @State private var isLoading = false
    @State private var newComment = ""
    @State private var isSubmitting = false
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                if isLoading {
                    ProgressView()
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    ScrollView {
                        LazyVStack(alignment: .leading, spacing: 0) {
                            ForEach(comments) { comment in
                                CommentRow(comment: comment)
                                    .padding(.horizontal, 16)
                            }
                        }
                        .padding(.vertical, 8)
                    }
                }
                
                // Add Comment Input
                HStack(spacing: 12) {
                    TextField("Yorum yaz...", text: $newComment, axis: .vertical)
                        .textFieldStyle(.roundedBorder)
                        .lineLimit(1...4)
                    
                    Button(action: {
                        Task {
                            await submitComment()
                        }
                    }) {
                        Image(systemName: "paperplane.fill")
                            .font(.system(size: 18))
                            .foregroundColor(newComment.isEmpty ? .secondary : Color(hex: "6366f1"))
                    }
                    .disabled(newComment.isEmpty || isSubmitting)
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(Color(hex: "F8FAFC"))
            }
            .navigationTitle("Yorumlar")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        dismiss()
                    }
                }
            }
            .task {
                await loadComments()
            }
        }
    }
    
    private func loadComments() async {
        isLoading = true
        do {
            let loadedComments = try await viewModel.getComments(postId: post.id)
            await MainActor.run {
                comments = loadedComments
                isLoading = false
            }
        } catch {
            await MainActor.run {
                isLoading = false
            }
        }
    }
    
    private func submitComment() async {
        guard !newComment.isEmpty else { return }
        
        isSubmitting = true
        let commentText = newComment
        newComment = ""
        
        do {
            let comment = try await viewModel.addComment(postId: post.id, content: commentText)
            await MainActor.run {
                comments.append(comment)
                isSubmitting = false
            }
        } catch {
            await MainActor.run {
                newComment = commentText
                isSubmitting = false
            }
        }
    }
}

