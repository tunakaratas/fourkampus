//
//  AdminDashboardView.swift
//  Four Admin
//
//  Created by Tuna Karataş on 24.12.2025.
//

import SwiftUI

struct AdminDashboardView: View {
    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    // Stats Cards
                    HStack(spacing: 15) {
                        StatCard(title: "Toplam Üye", value: "124", icon: "person.3.fill", color: .blue)
                        StatCard(title: "Aktif Etkinlik", value: "3", icon: "calendar", color: .orange)
                    }
                    .padding(.horizontal)
                    
                    HStack(spacing: 15) {
                        StatCard(title: "Bekleyen", value: "5", icon: "clock.fill", color: .purple)
                        StatCard(title: "Bakiye", value: "₺1,250", icon: "creditcard.fill", color: .green)
                    }
                    .padding(.horizontal)
                    
                    // Recent Activity
                    VStack(alignment: .leading, spacing: 15) {
                        Text("Son Aktiviteler")
                            .font(.headline)
                            .padding(.horizontal)
                        
                        ForEach(0..<5) { item in
                            HStack {
                                Image(systemName: "person.fill")
                                    .padding(8)
                                    .background(Color.gray.opacity(0.1))
                                    .clipShape(Circle())
                                VStack(alignment: .leading) {
                                    Text("Ali Yılmaz topluluğa katıldı")
                                        .font(.subheadline)
                                        .fontWeight(.medium)
                                    Text("2 saat önce")
                                        .font(.caption)
                                        .foregroundColor(.secondary)
                                }
                                Spacer()
                            }
                            .padding(.horizontal)
                            Divider()
                                .padding(.leading)
                        }
                    }
                    .padding(.vertical)
                    .background(Color(UIColor.secondarySystemBackground))
                    .cornerRadius(12)
                    .padding()
                }
                .padding(.vertical)
            }
            .navigationTitle("Yönetim Paneli")
            .background(Color(UIColor.systemGroupedBackground))
        }
    }
}

struct StatCard: View {
    let title: String
    let value: String
    let icon: String
    let color: Color
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Image(systemName: icon)
                    .foregroundColor(color)
                    .font(.title2)
                Spacer()
            }
            
            VStack(alignment: .leading, spacing: 4) {
                Text(value)
                    .font(.title2)
                    .fontWeight(.bold)
                Text(title)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding()
        .background(Color(UIColor.systemBackground))
        .cornerRadius(12)
        .shadow(color: Color.black.opacity(0.05), radius: 5, x: 0, y: 2)
    }
}

#Preview {
    AdminDashboardView()
}
