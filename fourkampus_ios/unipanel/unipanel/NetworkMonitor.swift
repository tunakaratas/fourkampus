//
//  NetworkMonitor.swift
//  Four Kampüs
//
//  Network Connectivity Monitoring for Real-World Scenarios
//

import Foundation
import Network
import Combine
import SwiftUI

/// Network connectivity status
enum NetworkStatus {
    case connected
    case disconnected
    case connecting
    case requiresConnection
    
    var isConnected: Bool {
        return self == .connected
    }
    
    var displayName: String {
        switch self {
        case .connected:
            return "Bağlı"
        case .disconnected:
            return "Bağlantı Yok"
        case .connecting:
            return "Bağlanıyor..."
        case .requiresConnection:
            return "Bağlantı Gerekli"
        }
    }
}

/// Network connection type
enum NetworkConnectionType {
    case wifi
    case cellular
    case ethernet
    case other
    case none
    
    var displayName: String {
        switch self {
        case .wifi:
            return "Wi-Fi"
        case .cellular:
            return "Mobil Veri"
        case .ethernet:
            return "Ethernet"
        case .other:
            return "Diğer"
        case .none:
            return "Bağlantı Yok"
        }
    }
    
    var isExpensive: Bool {
        return self == .cellular
    }
}

/// Network connectivity monitor
@MainActor
class NetworkMonitor: ObservableObject {
    static let shared = NetworkMonitor()
    
    @Published var status: NetworkStatus = .connecting
    @Published var connectionType: NetworkConnectionType = .none
    @Published var isExpensive: Bool = false
    @Published var isConstrained: Bool = false
    
    private let monitor = NWPathMonitor()
    private let queue = DispatchQueue(label: "NetworkMonitor")
    private var cancellables = Set<AnyCancellable>()
    
    private init() {
        startMonitoring()
    }
    
    /// Start monitoring network connectivity
    private func startMonitoring() {
        monitor.pathUpdateHandler = { [weak self] path in
            guard let self = self else { return }
            Task { @MainActor [weak self] in
                guard let self = self else { return }
                self.updateStatus(path: path)
            }
        }
        
        monitor.start(queue: queue)
        
        #if DEBUG
        print("📡 NetworkMonitor: Monitoring started")
        #endif
    }
    
    /// Update network status based on path
    private func updateStatus(path: NWPath) {
        // Connection status
        if path.status == .satisfied {
            status = .connected
        } else if path.status == .requiresConnection {
            status = .requiresConnection
        } else {
            status = .disconnected
        }
        
        // Connection type
        if path.usesInterfaceType(.wifi) {
            connectionType = .wifi
        } else if path.usesInterfaceType(.cellular) {
            connectionType = .cellular
        } else if path.usesInterfaceType(.wiredEthernet) {
            connectionType = .ethernet
        } else if path.status == .satisfied {
            connectionType = .other
        } else {
            connectionType = .none
        }
        
        // Expensive connection (cellular)
        isExpensive = path.isExpensive
        
        // Constrained connection (low data mode)
        isConstrained = path.isConstrained
        
        #if DEBUG
        print("📡 NetworkMonitor: Status updated - \(status.displayName), Type: \(connectionType.displayName), Expensive: \(isExpensive), Constrained: \(isConstrained)")
        #endif
        
        // Post notification for other parts of the app
        NotificationCenter.default.post(
            name: NSNotification.Name("NetworkStatusChanged"),
            object: nil,
            userInfo: [
                "status": status,
                "connectionType": connectionType,
                "isExpensive": isExpensive,
                "isConstrained": isConstrained
            ]
        )
    }
    
    /// Check if network is currently available
    func isNetworkAvailable() -> Bool {
        return status.isConnected
    }
    
    /// Check if connection is expensive (cellular)
    func isConnectionExpensive() -> Bool {
        return isExpensive
    }
    
    /// Check if connection is constrained (low data mode)
    func isConnectionConstrained() -> Bool {
        return isConstrained
    }
    
    /// Stop monitoring
    nonisolated func stopMonitoring() {
        monitor.cancel()
        #if DEBUG
        print("📡 NetworkMonitor: Monitoring stopped")
        #endif
    }
    
    deinit {
        stopMonitoring()
    }
}

// MARK: - Network Status View Modifier
struct NetworkStatusViewModifier: ViewModifier {
    @ObservedObject private var networkMonitor = NetworkMonitor.shared
    
    func body(content: Content) -> some View {
        content
            .overlay(
                // Offline banner
                Group {
                    if !networkMonitor.isNetworkAvailable() {
                        VStack {
                            HStack {
                                Image(systemName: "wifi.slash")
                                    .foregroundColor(.white)
                                Text("İnternet bağlantısı yok")
                                    .font(.system(size: 14, weight: .medium))
                                    .foregroundColor(.white)
                                Spacer()
                            }
                            .padding(.horizontal, 16)
                            .padding(.vertical, 12)
                            .background(Color.red)
                            .cornerRadius(8)
                            .padding(.horizontal, 16)
                            .padding(.top, 8)
                            
                            Spacer()
                        }
                    }
                },
                alignment: .top
            )
    }
}

extension View {
    /// Add network status monitoring to view
    func networkStatus() -> some View {
        modifier(NetworkStatusViewModifier())
    }
}

