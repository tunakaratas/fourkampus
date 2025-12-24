//
//  ResourceManager.swift
//  Four Kampüs
//
//  Resource Management for Real-World Scenarios
//

import Foundation

/// Resource management utility
actor ResourceManager {
    static let shared = ResourceManager()
    
    private let fileManager = FileManager.default
    private var cleanupTask: Task<Void, Never>?
    private var isInitialized = false
    
    private init() {
        // Init is empty - initialization happens in initialize() method
    }
    
    /// Initialize cleanup task (call after initialization)
    func initialize() async {
        guard !isInitialized else { return }
        isInitialized = true
        
        cleanupTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 24 * 60 * 60 * 1_000_000_000) // 24 hours
                await performCleanup()
            }
        }
    }
    
    /// Perform cleanup of old files and caches
    func performCleanup() async {
        #if DEBUG
        print("🧹 ResourceManager: Starting cleanup")
        #endif
        
        await cleanupOldCacheFiles()
        await cleanupOldTempFiles()
        await optimizeCaches()
        
        #if DEBUG
        print("✅ ResourceManager: Cleanup completed")
        #endif
    }
    
    /// Cleanup old cache files (30+ days)
    private func cleanupOldCacheFiles() async {
        let cacheURLs = [
            fileManager.urls(for: .cachesDirectory, in: .userDomainMask).first,
            fileManager.urls(for: .documentDirectory, in: .userDomainMask).first
        ].compactMap { $0 }
        
        let thirtyDaysAgo = Date().addingTimeInterval(-30 * 24 * 60 * 60)
        var cleanedCount = 0
        
        for cacheURL in cacheURLs {
            guard let files = try? fileManager.contentsOfDirectory(
                at: cacheURL,
                includingPropertiesForKeys: [.contentModificationDateKey]
            ) else { continue }
            
            for file in files {
                if let modificationDate = try? file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate,
                   modificationDate < thirtyDaysAgo {
                    try? fileManager.removeItem(at: file)
                    cleanedCount += 1
                }
            }
        }
        
        #if DEBUG
        if cleanedCount > 0 {
            print("🧹 ResourceManager: Cleaned \(cleanedCount) old cache files")
        }
        #endif
    }
    
    /// Cleanup old temporary files
    private func cleanupOldTempFiles() async {
        let tempURL = fileManager.temporaryDirectory
        let oneDayAgo = Date().addingTimeInterval(-24 * 60 * 60)
        
        guard let files = try? fileManager.contentsOfDirectory(
            at: tempURL,
            includingPropertiesForKeys: [.contentModificationDateKey]
        ) else { return }
        
        var cleanedCount = 0
        for file in files {
            if let modificationDate = try? file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate,
               modificationDate < oneDayAgo {
                try? fileManager.removeItem(at: file)
                cleanedCount += 1
            }
        }
        
        #if DEBUG
        if cleanedCount > 0 {
            print("🧹 ResourceManager: Cleaned \(cleanedCount) old temp files")
        }
        #endif
    }
    
    /// Optimize caches
    private func optimizeCaches() async {
        // Clear URL cache if too large
        let urlCache = URLCache.shared
        let currentSize = urlCache.currentDiskUsage + urlCache.currentMemoryUsage
        let maxSize = 100 * 1024 * 1024 // 100 MB
        
        if currentSize > maxSize {
            urlCache.removeAllCachedResponses()
            #if DEBUG
            print("🧹 ResourceManager: URL cache cleared (size: \(currentSize / 1024 / 1024) MB)")
            #endif
        }
        
        // Optimize image cache (MainActor'da çalışıyor)
        await MainActor.run {
            ImageCache.shared.cleanupCacheIfNeeded()
        }
    }
    
    /// Get cache sizes
    func getCacheSizes() async -> CacheSizes {
        let urlCache = URLCache.shared
        
        // Calculate image cache size (approximate)
        let imageCacheSize = await estimateImageCacheSize()
        
        return CacheSizes(
            urlCacheMemory: urlCache.currentMemoryUsage,
            urlCacheDisk: urlCache.currentDiskUsage,
            imageCache: imageCacheSize,
            total: urlCache.currentMemoryUsage + urlCache.currentDiskUsage + imageCacheSize
        )
    }
    
    /// Estimate image cache size
    private func estimateImageCacheSize() async -> Int {
        // Calculate actual image cache size from disk
        let cacheURL = fileManager.urls(for: .cachesDirectory, in: .userDomainMask).first?
            .appendingPathComponent("ImageCache", isDirectory: true)
        
        guard let cacheURL = cacheURL,
              let files = try? fileManager.contentsOfDirectory(at: cacheURL, includingPropertiesForKeys: [.fileSizeKey]) else {
            return 0
        }
        
        var totalSize: Int = 0
        for file in files {
            if let size = try? file.resourceValues(forKeys: [.fileSizeKey]).fileSize {
                totalSize += size
            }
        }
        
        return totalSize
    }
    
    /// Cancel cleanup task
    func cancel() {
        cleanupTask?.cancel()
    }
}

struct CacheSizes {
    let urlCacheMemory: Int
    let urlCacheDisk: Int
    let imageCache: Int
    let total: Int
}

