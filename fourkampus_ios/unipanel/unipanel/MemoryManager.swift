//
//  MemoryManager.swift
//  Four Kampüs
//
//  Memory Management for Real-World Scenarios
//

import Foundation
@preconcurrency import UIKit
import Darwin

/// Memory management utility
@MainActor
class MemoryManager {
    static let shared = MemoryManager()
    
    nonisolated(unsafe) private var memoryWarningObserver: (any NSObjectProtocol)?
    nonisolated(unsafe) private var backgroundObserver: (any NSObjectProtocol)?
    
    private init() {
        setupObservers()
    }
    
    /// Setup memory warning and background observers
    private func setupObservers() {
        // Memory warning observer
        memoryWarningObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.didReceiveMemoryWarningNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleMemoryWarning()
            }
        }
        
        // Background observer
        backgroundObserver = NotificationCenter.default.addObserver(
            forName: UIApplication.didEnterBackgroundNotification,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            Task { @MainActor in
                self?.handleBackgroundTransition()
            }
        }
    }
    
    /// Handle memory warning
    private func handleMemoryWarning() {
        #if DEBUG
        print("⚠️ MemoryManager: Memory warning received")
        #endif
        
        optimizeMemory(level: .critical)
    }
    
    /// Handle background transition
    private func handleBackgroundTransition() {
        #if DEBUG
        print("📱 MemoryManager: App entered background, optimizing memory")
        #endif
        
        optimizeMemory(level: .moderate)
    }
    
    /// Optimize memory based on pressure level
    func optimizeMemory(level: MemoryPressureLevel) {
        switch level {
        case .low:
            // Light cleanup
            ImageCache.shared.cleanupCacheIfNeeded()
            
        case .moderate:
            // Moderate cleanup
            ImageCache.shared.cleanupCacheIfNeeded()
            URLCache.shared.removeAllCachedResponses()
            
        case .critical:
            // Aggressive cleanup
            ImageCache.shared.clearCache()
            URLCache.shared.removeAllCachedResponses()
            
            // Force garbage collection
            autoreleasepool {
                // Clear any autoreleased objects
            }
        }
        
        #if DEBUG
        let memory = getCurrentMemoryUsage()
        print("🧹 MemoryManager: Memory optimized (level: \(level)), current usage: \(String(format: "%.2f", memory)) MB")
        #endif
    }
    
    /// Get current memory usage in MB
    func getCurrentMemoryUsage() -> Double {
        var info = mach_task_basic_info()
        var count = mach_msg_type_number_t(MemoryLayout<mach_task_basic_info>.size)/4
        
        let kerr: kern_return_t = withUnsafeMutablePointer(to: &info) {
            $0.withMemoryRebound(to: integer_t.self, capacity: 1) {
                task_info(mach_task_self_,
                         task_flavor_t(MACH_TASK_BASIC_INFO),
                         $0,
                         &count)
            }
        }
        
        if kerr == KERN_SUCCESS {
            return Double(info.resident_size) / 1024.0 / 1024.0
        }
        return 0.0
    }
    
    /// Get memory pressure level
    func getMemoryPressureLevel() -> MemoryPressureLevel {
        let usage = getCurrentMemoryUsage()
        let totalMemory = ProcessInfo.processInfo.physicalMemory / 1024 / 1024 // MB
        let usagePercent = (usage / Double(totalMemory)) * 100.0
        
        if usagePercent > 80 {
            return .critical
        } else if usagePercent > 60 {
            return .moderate
        } else {
            return .low
        }
    }
    
    nonisolated deinit {
        // NotificationCenter.removeObserver is thread-safe
        if let observer = memoryWarningObserver {
            NotificationCenter.default.removeObserver(observer)
        }
        if let observer = backgroundObserver {
            NotificationCenter.default.removeObserver(observer)
        }
    }
}

enum MemoryPressureLevel {
    case low
    case moderate
    case critical
}

