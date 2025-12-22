//
//  NotificationManager.swift
//  Four Kampüs
//
//  Notification Management Utility
//

import Foundation
import UserNotifications

class NotificationManager {
    static let shared = NotificationManager()
    
    private init() {}
    
    func requestAuthorization() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
            if let error = error {
                print("❌ Notification authorization error: \(error.localizedDescription)")
            } else {
                print("✅ Notification authorization granted: \(granted)")
            }
        }
    }
    
    func createNotificationChannels() {
        // iOS doesn't need channels like Android, but we can set up categories
        let eventCategory = UNNotificationCategory(
            identifier: "EVENT_REMINDER",
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        
        let campaignCategory = UNNotificationCategory(
            identifier: "CAMPAIGN_NOTIFICATION",
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        
        let communityCategory = UNNotificationCategory(
            identifier: "COMMUNITY_UPDATE",
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        
        let orderCategory = UNNotificationCategory(
            identifier: "ORDER_STATUS",
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        
        UNUserNotificationCenter.current().setNotificationCategories([
            eventCategory,
            campaignCategory,
            communityCategory,
            orderCategory
        ])
    }
    
    func scheduleEventReminder(
        eventId: String,
        communityId: String,
        eventTitle: String,
        eventDate: String,
        eventTime: String,
        minutesBefore: Int = 60
    ) {
        let content = UNMutableNotificationContent()
        content.title = "Etkinlik Hatırlatıcısı"
        content.body = "\(eventTitle) - \(eventDate) \(eventTime)"
        content.sound = .default
        content.categoryIdentifier = "EVENT_REMINDER"
        content.userInfo = [
            "type": "event",
            "event_id": eventId,
            "community_id": communityId
        ]
        
        // Parse date and time
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm"
        let dateTimeString = "\(eventDate) \(eventTime)"
        
        guard let eventDate = dateFormatter.date(from: dateTimeString) else {
            print("❌ Failed to parse event date: \(dateTimeString)")
            return
        }
        
        // Calculate reminder time
        let reminderDate = eventDate.addingTimeInterval(-Double(minutesBefore * 60))
        
        if reminderDate < Date() {
            print("⚠️ Reminder time is in the past, skipping")
            return
        }
        
        let trigger = UNCalendarNotificationTrigger(
            dateMatching: Calendar.current.dateComponents([.year, .month, .day, .hour, .minute], from: reminderDate),
            repeats: false
        )
        
        let request = UNNotificationRequest(
            identifier: "event_\(eventId)",
            content: content,
            trigger: trigger
        )
        
        UNUserNotificationCenter.current().add(request) { error in
            if let error = error {
                print("❌ Failed to schedule event reminder: \(error.localizedDescription)")
            } else {
                print("✅ Event reminder scheduled: \(eventTitle)")
            }
        }
    }
    
    func showCampaignNotification(
        campaignId: String,
        communityId: String,
        campaignTitle: String
    ) {
        let content = UNMutableNotificationContent()
        content.title = "Yeni Kampanya"
        content.body = campaignTitle
        content.sound = .default
        content.categoryIdentifier = "CAMPAIGN_NOTIFICATION"
        content.userInfo = [
            "type": "campaign",
            "campaign_id": campaignId,
            "community_id": communityId
        ]
        
        let trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
        let request = UNNotificationRequest(
            identifier: "campaign_\(campaignId)",
            content: content,
            trigger: trigger
        )
        
        UNUserNotificationCenter.current().add(request)
    }
    
    func showCommunityUpdate(
        communityId: String,
        title: String,
        message: String
    ) {
        let content = UNMutableNotificationContent()
        content.title = title
        content.body = message
        content.sound = .default
        content.categoryIdentifier = "COMMUNITY_UPDATE"
        content.userInfo = [
            "type": "community",
            "community_id": communityId
        ]
        
        let trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
        let request = UNNotificationRequest(
            identifier: "community_\(communityId)_\(UUID().uuidString)",
            content: content,
            trigger: trigger
        )
        
        UNUserNotificationCenter.current().add(request)
    }
    
    func showOrderStatusUpdate(
        orderId: String,
        status: String,
        message: String
    ) {
        let content = UNMutableNotificationContent()
        content.title = "Sipariş Durumu: \(status)"
        content.body = message
        content.sound = .default
        content.categoryIdentifier = "ORDER_STATUS"
        content.userInfo = [
            "type": "order",
            "order_id": orderId
        ]
        
        let trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
        let request = UNNotificationRequest(
            identifier: "order_\(orderId)",
            content: content,
            trigger: trigger
        )
        
        UNUserNotificationCenter.current().add(request)
    }
    
    func cancelEventReminder(eventId: String) {
        UNUserNotificationCenter.current().removePendingNotificationRequests(withIdentifiers: ["event_\(eventId)"])
    }
    
    func cancelAllNotifications() {
        UNUserNotificationCenter.current().removeAllPendingNotificationRequests()
        UNUserNotificationCenter.current().removeAllDeliveredNotifications()
    }
}

