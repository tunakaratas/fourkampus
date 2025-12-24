//
//  AppDelegate.swift
//  Four Kampüs
//
//  Push Notification Handling
//

import UIKit
import UserNotifications

class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    
    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey : Any]? = nil) -> Bool {
        // Notification delegate ayarla
        UNUserNotificationCenter.current().delegate = self
        return true
    }
    
    // Device token alındığında
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        let tokenString = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
        print("✅ Device token alındı: \(tokenString)")
        
        // NotificationCenter ile bildir
        NotificationCenter.default.post(
            name: NSNotification.Name("DeviceTokenReceived"),
            object: nil,
            userInfo: ["deviceToken": deviceToken]
        )
        
        // Token'ı kaydet
        Task {
            await registerDeviceToken(tokenString)
        }
    }
    
    // Device token alınamadığında
    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        print("❌ Device token alınamadı: \(error.localizedDescription)")
    }
    
    // Notification gösterildiğinde (foreground)
    func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification, withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void) {
        // Foreground'da da bildirim göster
        completionHandler([.banner, .sound, .badge])
    }
    
    // Notification'a tıklandığında
    func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse, withCompletionHandler completionHandler: @escaping () -> Void) {
        let userInfo = response.notification.request.content.userInfo
        
        if let type = userInfo["type"] as? String, type == "event",
           let eventId = userInfo["related_id"] as? String ?? userInfo["event_id"] as? String,
           let communityId = userInfo["community_id"] as? String {
            // Etkinlik detayına yönlendir
            DispatchQueue.main.async {
                NotificationDelegate.shared.pendingNavigation = (communityId: communityId, eventId: eventId)
            }
        }
        
        completionHandler()
    }
    
    private func registerDeviceToken(_ token: String) async {
        // Kullanıcı giriş yapmışsa token'ı kaydet
        if APIService.shared.getAuthToken() != nil {
            do {
                // Platform belirle
                #if os(iOS)
                let platform = "ios"
                #else
                let platform = "unknown"
                #endif
                
                // Community ID'yi al (eğer varsa)
                let communityId: String? = nil // Şimdilik null, sonra güncellenecek
                
                _ = try await APIService.shared.registerDeviceToken(
                    deviceToken: token,
                    platform: platform,
                    communityId: communityId
                )
                print("✅ Device token kaydedildi")
            } catch {
                print("❌ Device token kaydedilemedi: \(error.localizedDescription)")
            }
        }
    }
}

