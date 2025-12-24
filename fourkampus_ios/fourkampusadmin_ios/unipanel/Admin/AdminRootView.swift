//
//  AdminRootView.swift
//  Four Admin
//
//  Created by Tuna Karataş on 24.12.2025.
//

import SwiftUI

struct AdminRootView: View {
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var selectedTab = 0
    
    var body: some View {
        TabView(selection: $selectedTab) {
            AdminDashboardView()
                .tabItem {
                    Label("Panel", systemImage: "chart.bar.doc.horizontal")
                }
                .tag(0)
            
            Text("Üyeler Yönetimi")
                .tabItem {
                    Label("Üyeler", systemImage: "person.2.fill")
                }
                .tag(1)
            
            Text("Etkinlik Yönetimi")
                .tabItem {
                    Label("Etkinlikler", systemImage: "calendar.badge.plus")
                }
                .tag(2)
            
            Text("Ayarlar")
                .tabItem {
                    Label("Ayarlar", systemImage: "gearshape.fill")
                }
                .tag(3)
        }
        .tint(Color(hex: "6366f1"))
    }
}

#Preview {
    AdminRootView()
        .environmentObject(AuthViewModel())
}
