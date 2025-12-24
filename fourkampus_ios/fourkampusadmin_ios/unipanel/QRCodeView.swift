//
//  QRCodeView.swift
//  Four Kampüs
//
//  QR Kod Görüntüleme View'ı
//

import SwiftUI

struct QRCodeView: View {
    let title: String
    let content: String
    @Binding var isPresented: Bool
    
    @State private var qrImage: UIImage?
    @State private var isLoading = true
    @State private var errorMessage: String?
    
    var body: some View {
        NavigationView {
            VStack(spacing: 24) {
                if isLoading {
                    ProgressView()
                        .scaleEffect(1.5)
                        .padding(.top, 100)
                } else if let qrImage = qrImage {
                    VStack(spacing: 20) {
                        // QR Code Image
                        Image(uiImage: qrImage)
                            .resizable()
                            .interpolation(.none)
                            .scaledToFit()
                            .frame(width: 280, height: 280)
                            .background(Color.white)
                            .cornerRadius(16)
                            .shadow(color: Color.black.opacity(0.1), radius: 10, x: 0, y: 5)
                        
                        // Info Text
                        Text("QR kodu tarayarak hızlıca erişebilirsiniz")
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 32)
                        
                        // Share Button
                        Button(action: {
                            shareQRCode()
                        }) {
                            HStack {
                                Image(systemName: "square.and.arrow.up")
                                Text("Paylaş")
                            }
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(
                                LinearGradient(
                                    gradient: Gradient(colors: [
                                        Color(hex: "8b5cf6"),
                                        Color(hex: "6366f1")
                                    ]),
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .cornerRadius(12)
                        }
                        .padding(.horizontal, 32)
                        .padding(.top, 8)
                    }
                    .padding(.top, 40)
                } else if let errorMessage = errorMessage {
                    VStack(spacing: 16) {
                        Image(systemName: "exclamationmark.triangle")
                            .font(.system(size: 48))
                            .foregroundColor(.red)
                        Text(errorMessage)
                            .font(.system(size: 16))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 32)
                    }
                    .padding(.top, 100)
                }
                
                Spacer()
            }
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        isPresented = false
                    }
                }
            }
            .onAppear {
                generateQRCode()
            }
        }
    }
    
    private func generateQRCode() {
        isLoading = true
        errorMessage = nil
        
        DispatchQueue.global(qos: .userInitiated).async {
            // QRCodeGenerator.generateQRCode is not actor-isolated, safe to call
            if let image = QRCodeGenerator.generateQRCode(content: content, size: 512) {
                DispatchQueue.main.async {
                    self.qrImage = image
                    self.isLoading = false
                }
            } else {
                DispatchQueue.main.async {
                    self.errorMessage = "QR kod oluşturulamadı"
                    self.isLoading = false
                }
            }
        }
    }
    
    private func shareQRCode() {
        guard let qrImage = qrImage else { return }
        
        let activityVC = UIActivityViewController(
            activityItems: [qrImage, content],
            applicationActivities: nil
        )
        
        if let windowScene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootViewController = windowScene.windows.first?.rootViewController {
            rootViewController.present(activityVC, animated: true)
        }
    }
}

