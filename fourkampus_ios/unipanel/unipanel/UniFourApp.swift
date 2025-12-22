//
//  FourKampusApp.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import UserNotifications
import UIKit
import Combine

@main
struct FourKampusApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) var appDelegate
    @AppStorage("appearanceMode") private var appearanceMode: String = "system"
    
    // Static property to hold the monitoring task (struct is immutable)
    private static var memoryMonitoringTask: Task<Void, Never>?
    
    var colorScheme: ColorScheme? {
        switch appearanceMode {
        case "light":
            return .light
        case "dark":
            return .dark
        default:
            return nil // nil = system
        }
    }
    
    @AppStorage("hasShownNotificationPermission") private var hasShownNotificationPermission = false
    @AppStorage("hasShownCameraPermission") private var hasShownCameraPermission = false
    
    init() {
        // Initialize managers
        initializeManagers()
        
        // Notification channels oluştur (izin verilmişse)
        NotificationManager.shared.createNotificationChannels()
    }
    
    private func initializeManagers() {
        // Start performance monitoring
        Task {
            await PerformanceMonitor.shared.startMonitoring()
        }
        
        // Initialize lifecycle handler
        _ = LifecycleHandler.shared
        
        // Initialize memory manager
        _ = MemoryManager.shared
        
        // Initialize network monitor
        _ = NetworkMonitor.shared
        
        // Initialize resource manager and start cleanup
        Task {
            await ResourceManager.shared.initialize()
            await ResourceManager.shared.performCleanup()
        }
        
        // Periodic memory usage recording
        Self.startMemoryMonitoring()
    }
    
    private static func startMemoryMonitoring() {
        // Cancel previous task if exists
        memoryMonitoringTask?.cancel()
        
        // Start new monitoring task
        memoryMonitoringTask = Task { @MainActor in
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 60 * 1_000_000_000) // Every minute
                guard !Task.isCancelled else { break }
                await PerformanceMonitor.shared.recordMemoryUsage()
            }
        }
    }
    
    var body: some Scene {
        WindowGroup {
            AuthView()
                .preferredColorScheme(colorScheme)
                .networkStatus() // Network connectivity monitoring
                .onAppear {
                    // Uygulama açıldığında cache temizliği yap
                    ImageCache.shared.cleanupCacheIfNeeded()
                    
                    // Bildirim izni kontrolü - sadece bir kez göster
                    checkNotificationPermission()
                }
                .onReceive(NotificationCenter.default.publisher(for: NSNotification.Name("DeviceTokenReceived"))) { notification in
                    // Device token AppDelegate'den geldiğinde kaydet
                    if let deviceToken = notification.userInfo?["deviceToken"] as? Data {
                        let tokenString = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
                        Task {
                            await registerDeviceToken(tokenString)
                        }
                    }
                }
        }
    }
    
    private func checkNotificationPermission() {
        UNUserNotificationCenter.current().getNotificationSettings { settings in
            // Capture only the needed value to avoid Sendable issues
            let authorizationStatus = settings.authorizationStatus
            DispatchQueue.main.async {
                // Eğer izin henüz belirlenmemişse ve daha önce gösterilmemişse
                if authorizationStatus == .notDetermined && !hasShownNotificationPermission {
                    // İzin ekranı gösterilecek (ContentView'de)
                    NotificationCenter.default.post(name: NSNotification.Name("ShowNotificationPermission"), object: nil)
                    }
                }
        }
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
    
    private func requestNotificationPermission() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
            if granted {
                print("✅ Bildirim izni verildi")
                DispatchQueue.main.async {
                    UIApplication.shared.registerForRemoteNotifications()
                }
            } else {
                print("❌ Bildirim izni reddedildi")
            }
            if let error = error {
                print("⚠️ Bildirim izni hatası: \(error.localizedDescription)")
            }
        }
        
        // Notification delegate ayarla
        UNUserNotificationCenter.current().delegate = NotificationDelegate.shared
    }
}

// MARK: - Notification Delegate
class NotificationDelegate: NSObject, UNUserNotificationCenterDelegate, ObservableObject {
    static let shared = NotificationDelegate()
    
    @Published var pendingNavigation: (communityId: String, eventId: String)?
    
    override init() {
        super.init()
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
                self.pendingNavigation = (communityId: communityId, eventId: eventId)
                // NotificationCenter ile bildir
                NotificationCenter.default.post(name: NSNotification.Name("PendingNavigationChanged"), object: nil)
            }
        }
        
        completionHandler()
    }
}
