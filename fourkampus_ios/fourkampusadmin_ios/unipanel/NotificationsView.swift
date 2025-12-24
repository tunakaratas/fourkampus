//
//  NotificationsView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct NotificationsView: View {
    @ObservedObject var viewModel: NotificationsViewModel
    @State private var showOnlyUnread = false
    
    var body: some View {
        NavigationView {
            ZStack {
                Color(hex: "F8FAFC")
                    .ignoresSafeArea()
                
                if viewModel.isLoading && viewModel.notifications.isEmpty {
                    ProgressView()
                        .scaleEffect(1.5)
                } else {
                    ScrollView {
                        VStack(spacing: 0) {
                            // Filter Toggle
                            HStack {
                                Toggle("Sadece okunmamışlar", isOn: $showOnlyUnread)
                                    .font(.system(size: 14, weight: .medium))
                                
                                if viewModel.unreadCount > 0 {
                                    Spacer()
                                    Button(action: {
                                        Task {
                                            await viewModel.markAllAsRead()
                                        }
                                    }) {
                                        Text("Tümünü okundu işaretle")
                                            .font(.system(size: 14, weight: .semibold))
                                            .foregroundColor(Color(hex: "6366f1"))
                                    }
                                }
                            }
                            .padding(16)
                            
                            // Notifications List
                            let filteredNotifications = showOnlyUnread ? viewModel.unreadNotifications : viewModel.notifications
                            
                            if filteredNotifications.isEmpty {
                                EmptyStateView(
                                    icon: "bell.slash",
                                    title: showOnlyUnread ? "Okunmamış bildirim yok" : "Bildirim yok",
                                    message: showOnlyUnread ? "Tüm bildirimleriniz okundu." : "Henüz bildiriminiz bulunmuyor."
                                )
                                .padding(.top, 64)
                            } else {
                                LazyVStack(spacing: 12) {
                                    ForEach(filteredNotifications) { notification in
                                        NotificationCard(
                                            notification: notification,
                                            onTap: {
                                                Task {
                                                    await viewModel.markAsRead(notification.id)
                                                }
                                            },
                                            onDelete: {
                                                Task {
                                                    await viewModel.deleteNotification(notification.id)
                                                }
                                            }
                                        )
                                    }
                                }
                                .padding(.horizontal, 16)
                                .padding(.bottom, 100)
                            }
                        }
                    }
                    .refreshable {
                        await viewModel.loadNotifications()
                    }
                }
            }
            .navigationTitle("Bildirimler")
            .navigationBarTitleDisplayMode(.large)
        }
    }
}

// MARK: - Notification Card
struct NotificationCard: View {
    let notification: AppNotification
    let onTap: () -> Void
    let onDelete: () -> Void
    @State private var isPressed = false
    
    var body: some View {
        HStack(spacing: 16) {
            // Icon
            ZStack {
                Circle()
                    .fill(notification.color.opacity(0.1))
                    .frame(width: 50, height: 50)
                
                Image(systemName: notification.icon)
                    .font(.system(size: 20, weight: .semibold))
                    .foregroundColor(notification.color)
            }
            
            // Content
            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text(notification.title)
                        .font(.system(size: 16, weight: .semibold, design: .rounded))
                        .foregroundColor(.primary)
                    
                    if !notification.isRead {
                        Circle()
                            .fill(notification.color)
                            .frame(width: 8, height: 8)
                    }
                    
                    Spacer()
                    
                    Text(notification.timeAgo)
                        .font(.system(size: 12, weight: .regular))
                        .foregroundColor(.secondary)
                }
                
                Text(notification.message)
                    .font(.system(size: 14, weight: .regular))
                    .foregroundColor(.secondary)
                    .lineLimit(2)
            }
            
            // Delete Button
            Button(action: {
                let generator = UIImpactFeedbackGenerator(style: .light)
                generator.impactOccurred()
                onDelete()
            }) {
                Image(systemName: "xmark.circle.fill")
                    .font(.system(size: 20))
                    .foregroundColor(.gray.opacity(0.4))
            }
        }
        .padding(16)
        .background(notification.isRead ? Color.white : Color(hex: "F8FAFC"))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.05), radius: 4, x: 0, y: 2)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(notification.isRead ? Color.clear : notification.color.opacity(0.3), lineWidth: 1)
        )
        .scaleEffect(isPressed ? 0.98 : 1.0)
        .animation(.spring(response: 0.3), value: isPressed)
        .onTapGesture {
            let generator = UIImpactFeedbackGenerator(style: .light)
            generator.impactOccurred()
            onTap()
        }
        .onLongPressGesture(minimumDuration: 0, maximumDistance: .infinity, pressing: { pressing in
            isPressed = pressing
        }, perform: {})
    }
}

