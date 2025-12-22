//
//  PerformanceMonitor.swift
//  Four Kampüs
//
//  Performance Monitoring for Real-World Scenarios
//

import Foundation
import Combine
import Darwin

/// Performance monitoring utility
actor PerformanceMonitor {
    static let shared = PerformanceMonitor()
    
    // Metrics tracking
    private var apiResponseTimes: [String: [TimeInterval]] = [:]
    private var apiSuccessCount: [String: Int] = [:]
    private var apiFailureCount: [String: Int] = [:]
    private var cacheHitCount: Int = 0
    private var cacheMissCount: Int = 0
    private var memoryUsage: [Date: Double] = [:]
    
    // Monitoring state
    private var isMonitoring = false
    private var monitoringStartTime: Date?
    
    private init() {}
    
    /// Start performance monitoring
    func startMonitoring() {
        isMonitoring = true
        monitoringStartTime = Date()
        #if DEBUG
        print("📊 PerformanceMonitor: Monitoring started")
        #endif
    }
    
    /// Stop performance monitoring
    func stopMonitoring() {
        isMonitoring = false
        #if DEBUG
        print("📊 PerformanceMonitor: Monitoring stopped")
        #endif
    }
    
    /// Record API response time
    func recordAPIResponse(endpoint: String, duration: TimeInterval, success: Bool) {
        guard isMonitoring else { return }
        
        if apiResponseTimes[endpoint] == nil {
            apiResponseTimes[endpoint] = []
        }
        apiResponseTimes[endpoint]?.append(duration)
        
        // Keep only last 100 measurements per endpoint
        if let times = apiResponseTimes[endpoint], times.count > 100 {
            apiResponseTimes[endpoint] = Array(times.suffix(100))
        }
        
        if success {
            apiSuccessCount[endpoint, default: 0] += 1
        } else {
            apiFailureCount[endpoint, default: 0] += 1
        }
    }
    
    /// Record cache hit
    func recordCacheHit() {
        guard isMonitoring else { return }
        cacheHitCount += 1
    }
    
    /// Record cache miss
    func recordCacheMiss() {
        guard isMonitoring else { return }
        cacheMissCount += 1
    }
    
    /// Record memory usage
    func recordMemoryUsage() {
        guard isMonitoring else { return }
        let usage = getCurrentMemoryUsage()
        memoryUsage[Date()] = usage
        
        // Keep only last 100 measurements
        if memoryUsage.count > 100 {
            let sorted = memoryUsage.sorted { $0.key < $1.key }
            memoryUsage = Dictionary(uniqueKeysWithValues: Array(sorted.suffix(100)))
        }
    }
    
    /// Get current memory usage in MB
    private func getCurrentMemoryUsage() -> Double {
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
    
    /// Get performance summary
    func getSummary() -> PerformanceSummary {
        var endpointStats: [String: EndpointStats] = [:]
        
        for (endpoint, times) in apiResponseTimes {
            let avgTime = times.isEmpty ? 0 : times.reduce(0, +) / Double(times.count)
            let minTime = times.min() ?? 0
            let maxTime = times.max() ?? 0
            let success = apiSuccessCount[endpoint] ?? 0
            let failure = apiFailureCount[endpoint] ?? 0
            let total = success + failure
            let successRate = total > 0 ? Double(success) / Double(total) * 100.0 : 0.0
            
            endpointStats[endpoint] = EndpointStats(
                averageResponseTime: avgTime,
                minResponseTime: minTime,
                maxResponseTime: maxTime,
                successCount: success,
                failureCount: failure,
                successRate: successRate
            )
        }
        
        let totalCacheRequests = cacheHitCount + cacheMissCount
        let cacheHitRate = totalCacheRequests > 0 ? Double(cacheHitCount) / Double(totalCacheRequests) * 100.0 : 0.0
        
        let avgMemory = memoryUsage.values.isEmpty ? 0 : memoryUsage.values.reduce(0, +) / Double(memoryUsage.values.count)
        let maxMemory = memoryUsage.values.max() ?? 0
        
        return PerformanceSummary(
            endpointStats: endpointStats,
            cacheHitRate: cacheHitRate,
            cacheHits: cacheHitCount,
            cacheMisses: cacheMissCount,
            averageMemoryUsage: avgMemory,
            maxMemoryUsage: maxMemory,
            monitoringDuration: monitoringStartTime.map { Date().timeIntervalSince($0) } ?? 0
        )
    }
    
    /// Reset all metrics
    func reset() {
        apiResponseTimes.removeAll()
        apiSuccessCount.removeAll()
        apiFailureCount.removeAll()
        cacheHitCount = 0
        cacheMissCount = 0
        memoryUsage.removeAll()
        monitoringStartTime = nil
    }
}

// MARK: - Performance Summary Models
struct PerformanceSummary {
    let endpointStats: [String: EndpointStats]
    let cacheHitRate: Double
    let cacheHits: Int
    let cacheMisses: Int
    let averageMemoryUsage: Double
    let maxMemoryUsage: Double
    let monitoringDuration: TimeInterval
}

struct EndpointStats {
    let averageResponseTime: TimeInterval
    let minResponseTime: TimeInterval
    let maxResponseTime: TimeInterval
    let successCount: Int
    let failureCount: Int
    let successRate: Double
}

