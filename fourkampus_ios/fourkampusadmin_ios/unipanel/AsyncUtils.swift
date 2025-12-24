//
//  AsyncUtils.swift
//  Four Kampüs
//
//  Created by Auto on 2025.
//

import Foundation

/// Ortak async utility fonksiyonları
enum AsyncUtils {
    /// Timeout ile async işlem yürütür - Task'ları iptal etmez
    static func withTimeout<T: Sendable>(
        seconds: TimeInterval,
        operation: @escaping @Sendable () async throws -> T
    ) async throws -> T {
        return try await withThrowingTaskGroup(of: Result<T, Error>.self) { group in
            // Operation task'ı
            group.addTask { @Sendable in
                do {
                    let result = try await operation()
                    return .success(result)
                } catch {
                    return .failure(error)
                }
            }
            
            // Timeout task'ı
            group.addTask { @Sendable in
                try await Task.sleep(nanoseconds: UInt64(seconds * 1_000_000_000))
                return .failure(TimeoutError())
            }
            
            // İlk tamamlanan task'ı al
            guard let firstResult = try await group.next() else {
                throw TimeoutError()
            }
            
            // Diğer task'ları iptal et (ama operation task'ı zaten tamamlandıysa sorun yok)
            group.cancelAll()
            
            // Result'ı handle et
            switch firstResult {
            case .success(let value):
                return value
            case .failure(let error):
                throw error
            }
        }
    }
    
    private struct TimeoutError: Error {
        var localizedDescription: String {
            "İstek zaman aşımına uğradı"
        }
    }
}

