//
//  ShareManager.swift
//  Four Kampüs
//
//  Share Functionality
//

import Foundation
import SwiftUI

struct ShareManager {
    static func shareCommunity(communityId: String, communityName: String) {
        let shareText = "\(communityName) topluluğunu keşfet!\n\n" +
            "Four Kampüs uygulamasından eriş: unifour://community/\(communityId)\n\n" +
            "Web'den eriş: \(APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: ""))community/\(communityId)"
        
        let activityVC = UIActivityViewController(
            activityItems: [shareText],
            applicationActivities: nil
        )
        
        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootViewController = windowScene.windows.first?.rootViewController {
            rootViewController.present(activityVC, animated: true)
        }
    }
    
    static func shareEvent(
        communityId: String,
        eventId: String,
        eventTitle: String,
        eventDate: String
    ) {
        let shareText = "\(eventTitle) etkinliğine katıl!\n\n" +
            "Tarih: \(eventDate)\n\n" +
            "Four Kampüs uygulamasından eriş: unifour://event/\(communityId)/\(eventId)\n\n" +
            "Web'den eriş: \(APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: ""))community/\(communityId)/event/\(eventId)"
        
        let activityVC = UIActivityViewController(
            activityItems: [shareText],
            applicationActivities: nil
        )
        
        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootViewController = windowScene.windows.first?.rootViewController {
            rootViewController.present(activityVC, animated: true)
        }
    }
    
    static func shareProduct(
        productId: String,
        productName: String,
        productPrice: String
    ) {
        let shareText = "\(productName) - \(productPrice)\n\n" +
            "Four Kampüs Market'ten satın al: \(APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: ""))product/\(productId)"
        
        let activityVC = UIActivityViewController(
            activityItems: [shareText],
            applicationActivities: nil
        )
        
        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootViewController = windowScene.windows.first?.rootViewController {
            rootViewController.present(activityVC, animated: true)
        }
    }
    
    static func shareCampaign(
        campaignId: String,
        communityId: String,
        campaignTitle: String,
        offerText: String?
    ) {
        let offer = offerText ?? "Özel kampanya"
        let shareText = "\(campaignTitle)\n\n\(offer)\n\n" +
            "Four Kampüs uygulamasından eriş: unifour://community/\(communityId)\n\n" +
            "Web'den eriş: \(APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: ""))community/\(communityId)"
        
        let activityVC = UIActivityViewController(
            activityItems: [shareText],
            applicationActivities: nil
        )
        
        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootViewController = windowScene.windows.first?.rootViewController {
            rootViewController.present(activityVC, animated: true)
        }
    }
    
    static func shareUrl(
        url: String,
        title: String,
        description: String
    ) -> some View {
        ShareSheet(activityItems: [
            "\(title)\n\n\(description)\n\n\(url)"
        ])
    }
}

// MARK: - Share Sheet for SwiftUI
struct ShareSheet: UIViewControllerRepresentable {
    let activityItems: [Any]
    let applicationActivities: [UIActivity]? = nil
    
    func makeUIViewController(context: Context) -> UIActivityViewController {
        let controller = UIActivityViewController(
            activityItems: activityItems,
            applicationActivities: applicationActivities
        )
        return controller
    }
    
    func updateUIViewController(_ uiViewController: UIActivityViewController, context: Context) {
        // No update needed
    }
}

