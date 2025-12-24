//
//  MainAppView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct MainAppView: View {
    @ObservedObject var authViewModel: AuthViewModel
    
    var body: some View {
        AdminRootView()
            .environmentObject(authViewModel)
            .onAppear {
                print("🎯 MainAppView göründü - Veriler yükleniyor...")
            }
    }
}

#Preview {
    MainAppView(authViewModel: AuthViewModel())
}
