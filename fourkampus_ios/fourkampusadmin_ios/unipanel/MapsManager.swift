//
//  MapsManager.swift
//  Four Kampüs
//
//  Maps Integration
//

import Foundation
import MapKit
import UIKit

class MapsManager {
    static let shared = MapsManager()
    
    private init() {}
    
    func openLocation(location: String) {
        // Use direct URL opening with Apple Maps (works on all iOS versions)
        // This is the simplest and most reliable approach
        if let encoded = location.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
           let url = URL(string: "https://maps.apple.com/?q=\(encoded)") {
            UIApplication.shared.open(url, options: [:], completionHandler: nil)
        }
    }
    
    func getDirections(to destination: String) {
        // Use direct URL opening with directions
        if let encoded = destination.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
           let url = URL(string: "https://maps.apple.com/?daddr=\(encoded)") {
            UIApplication.shared.open(url, options: [:], completionHandler: nil)
        }
    }
}

