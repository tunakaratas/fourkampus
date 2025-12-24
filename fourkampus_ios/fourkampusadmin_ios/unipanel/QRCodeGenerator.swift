//
//  QRCodeGenerator.swift
//  Four Kampüs
//
//  QR Kod Oluşturma Utility'si
//

import Foundation
import CoreImage
import SwiftUI

struct QRCodeGenerator {
    /**
     * QR kod oluştur
     * @param content QR kod içeriği (deep link URL)
     * @param size QR kod boyutu (pixel)
     * @return UIImage QR kod görseli
     */
    nonisolated static func generateQRCode(content: String, size: CGFloat = 512) -> UIImage? {
        guard let data = content.data(using: .utf8) else {
            return nil
        }
        
        guard let filter = CIFilter(name: "CIQRCodeGenerator") else {
            return nil
        }
        
        filter.setValue(data, forKey: "inputMessage")
        filter.setValue("H", forKey: "inputCorrectionLevel") // Yüksek hata düzeltme
        
        guard let ciImage = filter.outputImage else {
            return nil
        }
        
        // Scale up the image
        let scale = size / ciImage.extent.width
        let transformedImage = ciImage.transformed(by: CGAffineTransform(scaleX: scale, y: scale))
        
        // Convert to UIImage
        let context = CIContext()
        guard let cgImage = context.createCGImage(transformedImage, from: transformedImage.extent) else {
            return nil
        }
        
        return UIImage(cgImage: cgImage)
    }
    
    /**
     * Topluluk için QR kod içeriği oluştur
     * Format: fourkampus://community/{id}
     */
    static func createCommunityQRContent(communityId: String) -> String {
        return "fourkampus://community/\(communityId)"
    }
    
    /**
     * Etkinlik için QR kod içeriği oluştur
     * Format: fourkampus://event/{communityId}/{eventId}
     */
    static func createEventQRContent(communityId: String, eventId: String) -> String {
        return "fourkampus://event/\(communityId)/\(eventId)"
    }
    
    /**
     * Web URL formatında QR kod içeriği (paylaşım için)
     */
    static func createCommunityWebURL(communityId: String) -> String {
        // Base URL'i al (API endpoint'inden /api/ kısmını kaldır)
        let baseUrl = APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: "")
        return "\(baseUrl)community/\(communityId)"
    }
    
    static func createEventWebURL(communityId: String, eventId: String) -> String {
        // Base URL'i al (API endpoint'inden /api/ kısmını kaldır)
        let baseUrl = APIService.shared.baseURL.replacingOccurrences(of: "/api/", with: "")
        return "\(baseUrl)community/\(communityId)/event/\(eventId)"
    }
}
