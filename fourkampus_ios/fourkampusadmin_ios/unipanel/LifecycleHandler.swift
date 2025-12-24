//
//  LifecycleHandler.swift
//  Four Kampüs
//
//  App Lifecycle Management
//

import Foundation
@preconcurrency import UIKit
import Combine

/// App lifecycle handler
@MainActor
class LifecycleHandler: ObservableObject {
    static let shared = LifecycleHandler()
    
    @Published var isInForeground = true
    @Published var lastBackgroundTime: Date?
    
    nonisolated(unsafe) private var foregroundObserver: (any NSObjectProtocol)?
    nonisolated(unsafe) private var backgroundObserver: (any NSObjectProtocol)?
    nonisolated(unsafe) private var willResignActiveObserver: (any NSObjectProtocol)?
    nonisolated(unsafe) private var didBecomeActiveObserver: (any NSObjectProtocol)?
    
    private init() {
        setupObservers()
    }
    
    /// Setup lifecycle observers
    private func setupObservers() {
        // Foreground observer
        foregroundObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.willEnterForegroundNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleWillEnterForeground()
            }
        }
        
        // Background observer
        backgroundObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.didEnterBackgroundNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleDidEnterBackground()
            }
        }
        
        // Will resign active
        willResignActiveObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.willResignActiveNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleWillResignActive()
            }
        }
        
        // Did become active
        didBecomeActiveObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.didBecomeActiveNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleDidBecomeActive()
            }
        }
    }
    
    /// Handle will enter foreground
    private func handleWillEnterForeground() {
        #if DEBUG
        print("📱 LifecycleHandler: App will enter foreground")
        #endif
        
        isInForeground = true
        
        // Refresh data if needed
        if let lastBackground = lastBackgroundTime {
            let timeInBackground = Date().timeIntervalSince(lastBackground)
            if timeInBackground > 300 { // 5 minutes
                #if DEBUG
                print("📱 LifecycleHandler: App was in background for \(Int(timeInBackground))s, refreshing data")
                #endif
                // Trigger data refresh
                NotificationCenter.default.post(name: NSNotification.Name("AppWillRefreshData"), object: nil)
            }
        }
    }
    
    /// Handle did enter background
    private func handleDidEnterBackground() {
        #if DEBUG
        print("📱 LifecycleHandler: App did enter background")
        #endif
        
        isInForeground = false
        lastBackgroundTime = Date()
        
        // Optimize memory
        MemoryManager.shared.optimizeMemory(level: .moderate)
        
        // Save any pending data
        NotificationCenter.default.post(name: NSNotification.Name("AppWillSaveData"), object: nil)
    }
    
    /// Handle will resign active
    private func handleWillResignActive() {
        #if DEBUG
        print("📱 LifecycleHandler: App will resign active")
        #endif
    }
    
    /// Handle did become active
    private func handleDidBecomeActive() {
        #if DEBUG
        print("📱 LifecycleHandler: App did become active")
        #endif
        
        // Record memory usage
        Task {
            await PerformanceMonitor.shared.recordMemoryUsage()
        }
    }
    
    nonisolated deinit {
        // NotificationCenter.removeObserver is thread-safe, so we can call it from deinit
        if let observer = foregroundObserver {
            NotificationCenter.default.removeObserver(observer)
        }
        if let observer = backgroundObserver {
            NotificationCenter.default.removeObserver(observer)
        }
        if let observer = willResignActiveObserver {
            NotificationCenter.default.removeObserver(observer)
        }
        if let observer = didBecomeActiveObserver {
            NotificationCenter.default.removeObserver(observer)
        }
    }
}

