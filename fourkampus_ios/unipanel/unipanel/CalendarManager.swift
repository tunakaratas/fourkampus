//
//  CalendarManager.swift
//  Four Kampüs
//
//  Calendar Integration
//

import Foundation
import EventKit

class CalendarManager {
    static let shared = CalendarManager()
    private let eventStore = EKEventStore()
    
    private init() {}
    
    func requestAccess(completion: @escaping @Sendable (Bool) -> Void) {
        if #available(iOS 17.0, *) {
            eventStore.requestFullAccessToEvents { granted, error in
                DispatchQueue.main.async {
                    completion(granted)
                }
            }
        } else {
            eventStore.requestAccess(to: .event) { granted, error in
                DispatchQueue.main.async {
                    completion(granted)
                }
            }
        }
    }
    
    func addEvent(
        title: String,
        startDate: Date,
        endDate: Date,
        location: String?,
        notes: String?,
        completion: @escaping @Sendable (Bool, String?) -> Void
    ) {
        requestAccess { granted in
            guard granted else {
                completion(false, "Takvim erişim izni verilmedi")
                return
            }
            
            // Access eventStore on main actor since EKEventStore is main actor-isolated
            Task { @MainActor in
                let event = EKEvent(eventStore: self.eventStore)
                event.title = title
                event.startDate = startDate
                event.endDate = endDate
                event.location = location
                event.notes = notes
                event.calendar = self.eventStore.defaultCalendarForNewEvents
                
                // Reminder 15 minutes before
                let alarm = EKAlarm(relativeOffset: -15 * 60) // 15 minutes
                event.addAlarm(alarm)
                
                do {
                    try self.eventStore.save(event, span: .thisEvent)
                    completion(true, nil)
                } catch {
                    completion(false, error.localizedDescription)
                }
            }
        }
    }
}

