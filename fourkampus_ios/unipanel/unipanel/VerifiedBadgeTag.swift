//
//  VerifiedBadgeTag.swift
//  Four Kampüs
//
//  Created by AI on 27.11.2025.
//

import SwiftUI

struct VerifiedBadgeTag: View {
    enum Style {
        case prominent
        case subtle
    }
    
    let text: String
    var style: Style = .prominent
    
    private var backgroundColor: Color {
        switch style {
        case .prominent:
            return Color(hex: "2563eb").opacity(0.12)
        case .subtle:
            return Color(hex: "1d4ed8").opacity(0.08)
        }
    }
    
    private var foregroundColor: Color {
        switch style {
        case .prominent:
            return Color(hex: "1d4ed8")
        case .subtle:
            return Color(hex: "1e3a8a")
        }
    }
    
    var body: some View {
        HStack(spacing: 6) {
            Image("BlueTick")
                .resizable()
                .frame(width: 14, height: 14)
            Text(text)
                .font(.system(size: 11, weight: .semibold))
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 4)
        .background(backgroundColor)
        .foregroundColor(foregroundColor)
        .clipShape(Capsule())
        .accessibilityLabel(text)
    }
}

