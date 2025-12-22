//
//  Debouncer.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import Combine
import SwiftUI

/// Search input için debounce helper
class Debouncer: ObservableObject {
    @Published var inputValue: String = ""
    @Published var debouncedValue: String = ""
    private var cancellables = Set<AnyCancellable>()
    
    init(delay: TimeInterval = 0.5) {
        $inputValue
            .debounce(for: .seconds(delay), scheduler: RunLoop.main)
            .assign(to: &$debouncedValue)
    }
}

/// View modifier için debounce helper
extension View {
    func onDebounce<T: Equatable>(
        of value: T,
        delay: TimeInterval = 0.5,
        perform action: @escaping (T) -> Void
    ) -> some View {
        self.onChange(of: value) { newValue in
            Task {
                try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                await MainActor.run {
                    action(newValue)
                }
            }
        }
    }
}

