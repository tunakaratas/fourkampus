//
//  QRScannerView.swift
//  Four Kampüs
//
//  QR Code Scanner
//

import SwiftUI
@preconcurrency import AVFoundation
import AudioToolbox

struct QRScannerView: View {
    let onScanResult: (String) -> Void
    let onDismiss: () -> Void
    
    @State private var showPermissionRequest = false
    @State private var hasCameraPermission = false
    
    var body: some View {
        Group {
            if hasCameraPermission {
                QRScannerViewControllerWrapper(
                    onScanResult: onScanResult,
                    onDismiss: onDismiss
                )
            } else {
                PermissionRequestView(
                    permissionType: .camera,
                    onAllow: {
                        requestCameraPermission()
                    },
                    onSkip: {
                        onDismiss()
                    }
                )
            }
        }
        .onAppear {
            checkInitialPermission()
        }
    }
    
    private func checkInitialPermission() {
        let status = AVCaptureDevice.authorizationStatus(for: .video)
        hasCameraPermission = status == .authorized
        showPermissionRequest = status == .notDetermined
    }
    
    private func requestCameraPermission() {
        AVCaptureDevice.requestAccess(for: .video) { granted in
            DispatchQueue.main.async {
                hasCameraPermission = granted
                if !granted {
                    // İzin reddedildi, ayarlara yönlendir
                    showPermissionDeniedAlert()
                }
            }
        }
    }
    
    private func showPermissionDeniedAlert() {
        // Alert göster - bu UIViewController'da olacak
    }
}

struct QRScannerViewControllerWrapper: UIViewControllerRepresentable {
    let onScanResult: (String) -> Void
    let onDismiss: () -> Void
    
    func makeUIViewController(context: Context) -> QRScannerViewController {
        let controller = QRScannerViewController()
        controller.onScanResult = onScanResult
        controller.onDismiss = onDismiss
        return controller
    }
    
    func updateUIViewController(_ uiViewController: QRScannerViewController, context: Context) {}
}

@MainActor
class QRScannerViewController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    nonisolated(unsafe) var captureSession: AVCaptureSession?
    nonisolated(unsafe) var previewLayer: AVCaptureVideoPreviewLayer?
    var onScanResult: ((String) -> Void)?
    var onDismiss: (() -> Void)?
    private var isProcessingResult = false
    
    override func viewDidLoad() {
        super.viewDidLoad()
        
        view.backgroundColor = UIColor.black
        
        // Kamera izni kontrolü
        checkCameraPermission()
    }
    
    private func checkCameraPermission() {
        switch AVCaptureDevice.authorizationStatus(for: .video) {
        case .authorized:
            setupCamera()
        case .notDetermined:
            // İzin iste - sebebini kibarca anlatan bir cümle
            AVCaptureDevice.requestAccess(for: .video) { [weak self] granted in
                DispatchQueue.main.async {
                    if granted {
                        self?.setupCamera()
                    } else {
                        self?.showCameraPermissionDenied()
                    }
                }
            }
        case .denied, .restricted:
            showCameraPermissionDenied()
        @unknown default:
            showCameraPermissionDenied()
        }
    }
    
    private func showCameraPermissionDenied() {
        let ac = UIAlertController(
            title: "Kamera İzni Gerekli",
            message: "QR kodları taramak için kamera erişimine ihtiyacımız var. Etkinlik ve topluluk QR kodlarını hızlıca tarayabilmek için lütfen kamera iznini verin.",
            preferredStyle: .alert
        )
        ac.addAction(UIAlertAction(title: "Ayarlara Git", style: .default) { _ in
            if let settingsUrl = URL(string: UIApplication.openSettingsURLString) {
                UIApplication.shared.open(settingsUrl)
            }
        })
        ac.addAction(UIAlertAction(title: "İptal", style: .cancel) { [weak self] _ in
            self?.onDismiss?()
        })
        present(ac, animated: true)
    }
    
    private func setupCamera() {
        // Session'ı background thread'de oluştur
        Task.detached(priority: .userInitiated) { [weak self] in
            guard let self = self else { return }
            
            // Eski session varsa temizle - Main Actor'dan al
            let oldSession = await MainActor.run {
                return self.captureSession
            }
            if let oldSession = oldSession, oldSession.isRunning {
                oldSession.stopRunning()
            }
            
            let session = AVCaptureSession()
            session.sessionPreset = .high
            
            // Session configuration'ı başlat
            session.beginConfiguration()
        
        guard let videoCaptureDevice = AVCaptureDevice.default(for: .video) else {
                session.commitConfiguration()
                await MainActor.run {
                    self.failed()
                }
            return
        }
            
        let videoInput: AVCaptureDeviceInput
        do {
            videoInput = try AVCaptureDeviceInput(device: videoCaptureDevice)
        } catch {
                session.commitConfiguration()
                await MainActor.run {
                    self.failed()
                }
            return
        }
        
            if session.canAddInput(videoInput) {
                session.addInput(videoInput)
        } else {
                session.commitConfiguration()
                await MainActor.run {
                    self.failed()
                }
            return
        }
        
        let metadataOutput = AVCaptureMetadataOutput()
        
            if session.canAddOutput(metadataOutput) {
                session.addOutput(metadataOutput)
            
                // Metadata output delegate'i main thread'de çalıştır
                // Main actor isolation için MainActor.run kullan
                await MainActor.run {
            metadataOutput.setMetadataObjectsDelegate(self, queue: DispatchQueue.main)
                }
            metadataOutput.metadataObjectTypes = [.qr]
        } else {
                session.commitConfiguration()
                await MainActor.run {
                    self.failed()
                }
            return
        }
        
            session.commitConfiguration()
            
            // Main thread'de UI güncellemeleri yap
            await MainActor.run { [weak self] in
                guard let self = self else { return }
                
                self.captureSession = session
                
                let previewLayer = AVCaptureVideoPreviewLayer(session: session)
                previewLayer.frame = self.view.layer.bounds
        previewLayer.videoGravity = .resizeAspectFill
                self.view.layer.addSublayer(previewLayer)
                self.previewLayer = previewLayer
        
        // Close button
        var buttonConfig = UIButton.Configuration.filled()
        buttonConfig.title = "Kapat"
        buttonConfig.baseForegroundColor = .white
        buttonConfig.baseBackgroundColor = UIColor.black.withAlphaComponent(0.5)
        buttonConfig.contentInsets = NSDirectionalEdgeInsets(top: 8, leading: 16, bottom: 8, trailing: 16)
        buttonConfig.titleTextAttributesTransformer = UIConfigurationTextAttributesTransformer { incoming in
            var outgoing = incoming
            outgoing.font = .systemFont(ofSize: 16, weight: .semibold)
            return outgoing
        }
        let closeButton = UIButton(configuration: buttonConfig)
        closeButton.layer.cornerRadius = 8
                closeButton.addTarget(self, action: #selector(self.closeTapped), for: .touchUpInside)
        closeButton.translatesAutoresizingMaskIntoConstraints = false
                self.view.addSubview(closeButton)
        NSLayoutConstraint.activate([
                    closeButton.topAnchor.constraint(equalTo: self.view.safeAreaLayoutGuide.topAnchor, constant: 16),
                    closeButton.trailingAnchor.constraint(equalTo: self.view.trailingAnchor, constant: -16)
        ])
            }
            
            // Session'ı başlat - background thread'de
            if !session.isRunning {
                session.startRunning()
            }
        }
    }
    
    func failed() {
        let ac = UIAlertController(title: "Tarama desteklenmiyor", message: "Cihazınız QR kod taramayı desteklemiyor.", preferredStyle: .alert)
        ac.addAction(UIAlertAction(title: "Tamam", style: .default) { [weak self] _ in
            self?.onDismiss?()
        })
        present(ac, animated: true)
        captureSession = nil
    }
    
    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        
        // Preview layer frame'i güncelle
        previewLayer?.frame = view.layer.bounds
        
        // Session'ı başlat
        let session = captureSession
        if let session = session, !session.isRunning {
            DispatchQueue.global(qos: .userInitiated).async { [session] in
                guard !session.isRunning else { return }
                session.startRunning()
            }
        }
    }
    
    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        // Preview layer frame'i layout değişikliklerinde güncelle
        previewLayer?.frame = view.layer.bounds
    }
    
    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        
        // Session'ı güvenli şekilde durdur
        stopSession()
    }
    
    private func stopSession() {
        guard let session = captureSession else { return }
        
        // Delegate'i önce temizle
        for output in session.outputs {
            if let metadataOutput = output as? AVCaptureMetadataOutput {
                metadataOutput.setMetadataObjectsDelegate(nil, queue: nil)
            }
        }
        
        // Session'ı durdur
        if session.isRunning {
            session.stopRunning()
        }
    }
    
    deinit {
        // Cleanup - session'ı temizle
        // deinit'te async işlem yapmamaya dikkat et
        let session = captureSession
        
        // Session'ı durdur - eğer çalışıyorsa
        if let session = session, session.isRunning {
            // Delegate'i önce temizle
            for output in session.outputs {
                if let metadataOutput = output as? AVCaptureMetadataOutput {
                    // Delegate'i temizle - main thread'de ama sync kullanma (deadlock riski)
                    if Thread.isMainThread {
                        metadataOutput.setMetadataObjectsDelegate(nil, queue: nil)
                    } else {
                        // Main thread'de değilsek, async kullan
                        DispatchQueue.main.async {
                            metadataOutput.setMetadataObjectsDelegate(nil, queue: nil)
                        }
                    }
                }
            }
            
            // Session'ı durdur
            session.stopRunning()
        }
        
        // Property'leri temizle
        captureSession = nil
        previewLayer = nil
    }
    
    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        // Eğer zaten bir sonuç işleniyorsa, yeni sonuçları ignore et
        guard !isProcessingResult else { return }
        
        // Eğer session çalışmıyorsa işleme devam etme
        guard let session = captureSession, session.isRunning else { return }
        
        guard let metadataObject = metadataObjects.first else { return }
            guard let readableObject = metadataObject as? AVMetadataMachineReadableCodeObject else { return }
            guard let stringValue = readableObject.stringValue else { return }
        
        // İşleme başladığını işaretle
        isProcessingResult = true
        
        // Session'ı durdur - background thread'de
        let sessionToStop = session
        DispatchQueue.global(qos: .userInitiated).async { [sessionToStop] in
            sessionToStop.stopRunning()
        }
        
        // Main thread'de işle
        Task { @MainActor [weak self] in
            guard let self = self else { return }
            
            let trimmed = stringValue.trimmingCharacters(in: .whitespacesAndNewlines)
            
            #if DEBUG
            print("📷 QR Kod okundu: \(trimmed)")
            #endif
            
            guard !trimmed.isEmpty else {
                // Boş sonuç - tekrar taramaya başla
                #if DEBUG
                print("⚠️ QR Kod boş")
                #endif
                self.isProcessingResult = false
                let sessionToStart = self.captureSession
                if let sessionToStart = sessionToStart, !sessionToStart.isRunning {
                    DispatchQueue.global(qos: .userInitiated).async { [sessionToStart] in
                        sessionToStart.startRunning()
                    }
                }
                return
            }
            
            // Haptic feedback
            AudioServicesPlaySystemSound(SystemSoundID(kSystemSoundID_Vibrate))
            
            // URL Validation - Güvenlik için
            if trimmed.hasPrefix("http://") || trimmed.hasPrefix("https://") || 
               trimmed.hasPrefix("unifour://") || trimmed.hasPrefix("fourkampus://") {
                
                #if DEBUG
                print("🔗 QR Kod URL formatında: \(trimmed)")
                #endif
                
                // URL validate et
                if let validatedURL = URLValidator.sanitizeAndValidate(trimmed) {
                    #if DEBUG
                    print("✅ QR Kod URL geçerli: \(validatedURL.absoluteString)")
                    #endif
                    
                    // ÖNEMLİ: Önce sonucu gönder, sonra kapat
                    // onScanResult closure'ını capture et
                    let resultHandler = self.onScanResult
                    let dismissHandler = self.onDismiss
                    
                    // Sonucu gönder
                    resultHandler?(validatedURL.absoluteString)
                    
                    // Kısa bir gecikme sonra kapat
                    DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
                        dismissHandler?()
                    }
                    
                    self.isProcessingResult = false
                } else {
                    #if DEBUG
                    print("❌ QR Kod URL geçersiz: \(trimmed)")
                    #endif
                    // Geçersiz URL - kullanıcıya uyarı göster
                    self.isProcessingResult = false
                    self.showInvalidURLError()
                }
            } else {
                #if DEBUG
                print("📝 QR Kod düz metin: \(trimmed)")
                #endif
                
                // URL değil, direkt string olarak kabul et (QR kod içeriği olabilir)
                // ÖNEMLİ: Önce sonucu gönder, sonra kapat
                let resultHandler = self.onScanResult
                let dismissHandler = self.onDismiss
                
                // Sonucu gönder
                resultHandler?(trimmed)
                
                // Kısa bir gecikme sonra kapat
                DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
                    dismissHandler?()
                }
                
                self.isProcessingResult = false
            }
        }
    }
    
    private func showInvalidURLError() {
        let ac = UIAlertController(
            title: "Geçersiz QR Kod",
            message: "Taranan QR kod güvenli değil veya geçersiz bir bağlantı içeriyor.",
            preferredStyle: .alert
        )
        ac.addAction(UIAlertAction(title: "Tamam", style: .default) { [weak self] _ in
            // Tekrar taramaya başla
            guard let self = self else { return }
            self.isProcessingResult = false
            
            if let session = self.captureSession, !session.isRunning {
                DispatchQueue.global(qos: .userInitiated).async {
                    session.startRunning()
                }
            }
        })
        present(ac, animated: true)
    }
    
    @objc func closeTapped() {
        onDismiss?()
    }
    
    override var prefersStatusBarHidden: Bool {
        return true
    }
    
    override var supportedInterfaceOrientations: UIInterfaceOrientationMask {
        return .portrait
    }
}

