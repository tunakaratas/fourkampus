//
//  CampaignDetailView.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI
import CoreImage

struct CampaignDetailView: View {
    let campaign: Campaign
    @Environment(\.dismiss) var dismiss
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var membershipStatus: MembershipStatus?
    @State private var isLoadingMembership = false
    @State private var showLoginModal = false
    @State private var userCampaignCode: APIService.CampaignCodeResponse?
    @State private var isLoadingCode = false
    @State private var codeError: String?
    
    var body: some View {
        ScrollView {
            VStack(spacing: 0) {
                heroSection
                contentSection
            }
        }
        .background(Color(UIColor.systemBackground).ignoresSafeArea())
        .navigationTitle(campaign.title)
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showLoginModal) {
            LoginModal(isPresented: $showLoginModal)
                .environmentObject(authViewModel)
        }
        .task {
            if authViewModel.isAuthenticated {
                // Her zaman üyelik durumunu kontrol et
                    await loadMembershipStatus()
                // Kampanya kodunu yükle (şartlar yoksa veya şartlar karşılanmışsa)
                if !hasRequirements || areRequirementsFulfilled {
                    loadUserCampaignCode()
                }
            }
        }
        .onChange(of: areRequirementsFulfilled) { fulfilled in
            // Şartlar karşılandığında kodu yükle
            if fulfilled && authViewModel.isAuthenticated && userCampaignCode == nil {
                loadUserCampaignCode()
            }
        }
    }
    
    // MARK: - Hero Section
    private var heroSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Category Badge
            HStack {
                Text(campaign.category.rawValue)
                    .font(.system(size: 12, weight: .semibold))
                            .foregroundColor(.white)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(campaign.category.color)
                    .cornerRadius(8)
                Spacer()
            }
            
            // Title
                        Text(campaign.title)
                .font(.system(size: 28, weight: .bold))
                .foregroundColor(.primary)
            
            // Community Name
            HStack(spacing: 6) {
                            Image(systemName: "person.3.fill")
                    .font(.system(size: 14))
                    .foregroundColor(.secondary)
                            Text(campaign.communityName)
                    .font(.system(size: 16, weight: .medium))
                    .foregroundColor(.secondary)
            }
        }
        .padding(20)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color(UIColor.secondarySystemBackground))
    }
                
    // MARK: - Content Section
    private var contentSection: some View {
                VStack(spacing: 24) {
            // Basic Info Section
            CampaignBasicInfoSection(campaign: campaign)
            
            // Partner Info
            if let partnerName = campaign.partnerName {
                CampaignPartnerSection(campaign: campaign, partnerName: partnerName)
            }
            
            // Requirements Section
            if hasRequirements {
                RequirementsSection(
                    campaign: campaign,
                    membershipStatus: membershipStatus,
                    isLoading: isLoadingMembership,
                    onJoinCommunity: {
                        if !authViewModel.isAuthenticated {
                            showLoginModal = true
                        }
                    }
                        )
                    }
                    
                    // Partner Info
                    if let partnerName = campaign.partnerName {
                        HStack(spacing: 18) {
                            if let partnerLogo = campaign.partnerLogo, !partnerLogo.isEmpty {
                                AsyncImage(url: URL(string: APIService.fullImageURL(from: partnerLogo) ?? partnerLogo)) { phase in
                                    switch phase {
                                    case .success(let image):
                                    image
                                        .resizable()
                                        .aspectRatio(contentMode: .fit)
                                    case .failure(_), .empty:
                                    Circle()
                                        .fill(Color.gray.opacity(0.2))
                                        .overlay(
                                            Image(systemName: "building.2")
                                                .foregroundColor(.gray)
                                        )
                                    @unknown default:
                                        Circle()
                                            .fill(Color.gray.opacity(0.2))
                                    }
                                }
                                .frame(width: 70, height: 70)
                                .cornerRadius(16)
                            } else {
                                ZStack {
                                    Circle()
                                        .fill(campaign.category.color.opacity(0.2))
                                        .frame(width: 70, height: 70)
                                    Image(systemName: "building.2")
                                        .font(.system(size: 32))
                                        .foregroundColor(campaign.category.color)
                                }
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Partner")
                                    .font(.system(size: 13, weight: .semibold))
                                    .foregroundColor(.secondary)
                                    .textCase(.uppercase)
                                Text(partnerName)
                                    .font(.system(size: 20, weight: .bold))
                                    .foregroundColor(.primary)
                            }
                            
                            Spacer()
                        }
                        .padding(20)
                        .background(
                            RoundedRectangle(cornerRadius: 20)
                                .fill(Color(UIColor.secondarySystemBackground))
                                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
                        )
                    }
                    
                    // Requirements Section
                    if hasRequirements {
                        RequirementsSection(
                            campaign: campaign,
                            membershipStatus: membershipStatus,
                            isLoading: isLoadingMembership,
                            onJoinCommunity: {
                                if !authViewModel.isAuthenticated {
                                    showLoginModal = true
                                } else {
                                    // Topluluğa katılma işlemi - CommunityDetailView'e yönlendirilebilir
                                }
                            }
                        )
                    }
                    
            // Campaign Code - Sadece üye olanlar görebilir (QR kod ve normal kod kısmı olduğu gibi kalacak)
                    if let code = campaign.code, !code.isEmpty {
                        if authViewModel.isAuthenticated {
                    // Üyelik kontrolü - sadece üye olanlar kodu görebilir
                    if let membership = membershipStatus, (membership.isMember || membership.status == "member" || membership.status == "approved") {
                        // Üye ise kod göster
                            if hasRequirements && !areRequirementsFulfilled {
                                // Şartlar var ama karşılanmamış - bilgilendirme göster
                        RequirementsNotMetCard(
                            campaign: campaign,
                            membershipStatus: membershipStatus,
                            onJoinCommunity: {
                                if !authViewModel.isAuthenticated {
                                    showLoginModal = true
                                }
                            }
                        )
                            } else {
                                // Şartlar yok veya karşılanmış - kod göster
                                if let userCode = userCampaignCode {
                                    UserCampaignCodeCard(
                                        code: userCode.code,
                                        qrCodeData: userCode.qr_code_data,
                                        used: userCode.used,
                                        usedAt: userCode.used_at,
                                        isLoading: isLoadingCode
                                    )
                                } else if isLoadingCode {
                                    // Kod yükleniyor
                                    VStack(spacing: 12) {
                                        ProgressView()
                                        Text("Kodunuz hazırlanıyor...")
                                            .font(.system(size: 14))
                                            .foregroundColor(.secondary)
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(20)
                                    .background(
                                    RoundedRectangle(cornerRadius: 16)
                                            .fill(Color(UIColor.secondarySystemBackground))
                                    )
                                } else if let error = codeError {
                                    // Hata durumu
                                    VStack(spacing: 12) {
                                        Image(systemName: "exclamationmark.triangle.fill")
                                            .font(.system(size: 24))
                                            .foregroundColor(.orange)
                                        Text(error)
                                            .font(.system(size: 14))
                                            .foregroundColor(.secondary)
                                            .multilineTextAlignment(.center)
                                        Button("Tekrar Dene") {
                                            loadUserCampaignCode()
                                        }
                                        .buttonStyle(.borderedProminent)
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(20)
                                    .background(
                                    RoundedRectangle(cornerRadius: 16)
                                            .fill(Color(UIColor.secondarySystemBackground))
                                    )
                                } else {
                                    // Kod henüz yüklenmedi - yükle
                                    VStack(spacing: 12) {
                                        ProgressView()
                                        Text("Kodunuz hazırlanıyor...")
                                            .font(.system(size: 14))
                                            .foregroundColor(.secondary)
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(20)
                                    .background(
                                    RoundedRectangle(cornerRadius: 16)
                                            .fill(Color(UIColor.secondarySystemBackground))
                                    )
                                    .onAppear {
                                        loadUserCampaignCode()
                                    }
                                }
                            }
                        } else {
                        // Üye değilse kod gösterilmez
                        VStack(spacing: 16) {
                            Image(systemName: "lock.fill")
                                .font(.system(size: 40))
                                .foregroundColor(.orange)
                            Text("Kampanya Kodunu Görmek İçin Üye Olmalısınız")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.primary)
                                .multilineTextAlignment(.center)
                            Text("Bu topluluğa üye olarak kampanya koduna ve QR koduna erişebilirsiniz.")
                                .font(.system(size: 14))
                                .foregroundColor(.secondary)
                                .multilineTextAlignment(.center)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(24)
                        .background(
                            RoundedRectangle(cornerRadius: 16)
                                .fill(Color(UIColor.secondarySystemBackground))
                        )
                    }
                } else {
                    // Giriş yapılmamışsa kod gösterilmez
                    VStack(spacing: 16) {
                        Image(systemName: "lock.fill")
                            .font(.system(size: 40))
                            .foregroundColor(.orange)
                        Text("Kampanya Kodunu Görmek İçin Giriş Yapmalısınız")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.primary)
                            .multilineTextAlignment(.center)
                        Text("Giriş yaparak kampanya koduna ve QR koduna erişebilirsiniz.")
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                        Button(action: {
                            showLoginModal = true
                        }) {
                            Text("Giriş Yap")
                                .font(.system(size: 15, weight: .semibold))
                                .foregroundColor(.white)
                                .padding(.horizontal, 24)
                                .padding(.vertical, 12)
                                .background(Color(hex: "6366f1"))
                                .cornerRadius(10)
                        }
                    }
                    .frame(maxWidth: .infinity)
                    .padding(24)
                        .background(
                        RoundedRectangle(cornerRadius: 16)
                                .fill(Color(UIColor.secondarySystemBackground))
                        )
                    }
            }
            
            // Additional Info Section
            CampaignAdditionalInfoSection(campaign: campaign)
                }
                .padding(16)
                .padding(.top, 24)
            }
    
    // MARK: - Helper Functions
    private func formatDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "d MMMM yyyy"
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.string(from: date)
    }
    
    private func loadMembershipStatus() async {
        isLoadingMembership = true
        do {
            let status = try await APIService.shared.getMembershipStatus(communityId: campaign.communityId)
            await MainActor.run {
                membershipStatus = status
                isLoadingMembership = false
            }
        } catch {
            await MainActor.run {
        isLoadingMembership = false
    }
        }
    }
    
    private func loadUserCampaignCode() {
        isLoadingCode = true
        codeError = nil
        Task {
            do {
                let code = try await APIService.shared.getCampaignCode(campaignId: campaign.id, communityId: campaign.communityId)
                await MainActor.run {
                    userCampaignCode = code
                    isLoadingCode = false
                }
            } catch {
                await MainActor.run {
                    codeError = error.localizedDescription
                    isLoadingCode = false
                }
                }
            }
        }
    }
    
// MARK: - Campaign Basic Info Section
struct CampaignBasicInfoSection: View {
    let campaign: Campaign
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Detaylar")
                .font(.system(size: 20, weight: .bold))
                .foregroundColor(.primary)
            
            // Discount
            InfoRow(icon: "tag.fill", title: "İndirim", value: campaign.formattedDiscount)
            
            // Dates
            InfoRow(icon: "calendar", title: "Başlangıç", value: formatDate(campaign.startDate))
            InfoRow(icon: "calendar.badge.exclamationmark", title: "Bitiş", value: formatDate(campaign.endDate))
            
            // Offer Text
            if let offerText = campaign.offerText, !offerText.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    Text("Kampanya Detayı")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(offerText)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                }
            }
            
            // Description
            if !campaign.description.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    Text("Açıklama")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                    Text(campaign.description)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                }
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
        )
    }
    
    private func formatDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "d MMMM yyyy"
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.string(from: date)
    }
}

// MARK: - Campaign Partner Section
struct CampaignPartnerSection: View {
    let campaign: Campaign
    let partnerName: String
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Partner")
                .font(.system(size: 20, weight: .bold))
                .foregroundColor(.primary)
            
            HStack(spacing: 16) {
                if let partnerLogo = campaign.partnerLogo, !partnerLogo.isEmpty {
                    AsyncImage(url: URL(string: APIService.fullImageURL(from: partnerLogo) ?? partnerLogo)) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .aspectRatio(contentMode: .fit)
                        case .failure(_), .empty:
                            Circle()
                                .fill(Color.gray.opacity(0.2))
                                .overlay(
                                    Image(systemName: "building.2")
                                        .foregroundColor(.gray)
                                )
                        @unknown default:
                            Circle()
                                .fill(Color.gray.opacity(0.2))
                        }
                    }
                    .frame(width: 60, height: 60)
                    .cornerRadius(12)
                } else {
                    ZStack {
                        Circle()
                            .fill(campaign.category.color.opacity(0.2))
                            .frame(width: 60, height: 60)
                        Image(systemName: "building.2")
                            .font(.system(size: 28))
                            .foregroundColor(campaign.category.color)
                    }
                }
                
                VStack(alignment: .leading, spacing: 4) {
                    Text(partnerName)
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundColor(.primary)
                }
                
                Spacer()
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 16)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
        )
    }
}

// MARK: - Campaign Additional Info Section
struct CampaignAdditionalInfoSection: View {
    let campaign: Campaign
    
    var body: some View {
        VStack(spacing: 24) {
            // Terms
            if let terms = campaign.terms, !terms.isEmpty {
                VStack(alignment: .leading, spacing: 12) {
                    Text("Şartlar ve Koşullar")
                        .font(.system(size: 20, weight: .bold))
                        .foregroundColor(.primary)
                    Text(terms)
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .lineSpacing(4)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(20)
                .background(
                    RoundedRectangle(cornerRadius: 16)
                        .fill(Color(UIColor.secondarySystemBackground))
                        .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
                )
            }
            
            // Tags
            if !campaign.tags.isEmpty {
                VStack(alignment: .leading, spacing: 12) {
                    Text("Etiketler")
                        .font(.system(size: 20, weight: .bold))
                        .foregroundColor(.primary)
                    FlowLayout(spacing: 8) {
                        ForEach(campaign.tags, id: \.self) { tag in
                            Text(tag)
                                .font(.system(size: 13, weight: .medium))
                                .foregroundColor(campaign.category.color)
                                .padding(.horizontal, 12)
                                .padding(.vertical, 6)
                                .background(campaign.category.color.opacity(0.1))
                                .cornerRadius(8)
                        }
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(20)
                .background(
                    RoundedRectangle(cornerRadius: 16)
                        .fill(Color(UIColor.secondarySystemBackground))
                        .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
                )
            }
        }
    }
}

// MARK: - Date Info Card (Legacy - kept for compatibility)
struct DateInfoCard: View {
    let icon: String
    let title: String
    let date: Date
    let color: Color
    
    private func formatDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "d MMMM yyyy"
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.string(from: date)
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 8) {
                Image(systemName: icon)
                    .font(.system(size: 16))
                    .foregroundColor(color)
                Text(title)
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(.secondary)
            }
            Text(formatDate(date))
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(.primary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(Color(UIColor.secondarySystemBackground))
        )
    }
}

// MARK: - Requirements Section
struct RequirementsSection: View {
    let campaign: Campaign
    let membershipStatus: MembershipStatus?
    let isLoading: Bool
    let onJoinCommunity: () -> Void
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Şartlar")
                .font(.system(size: 22, weight: .bold, design: .rounded))
            
            if campaign.requiresMembership == true {
                RequirementRow(
                    icon: "person.3.fill",
                    title: "Topluluğa Üye Olma",
                    description: "Bu kampanyadan yararlanmak için topluluğa üye olmanız gerekmektedir.",
                    isFulfilled: membershipStatus?.isMember == true || membershipStatus?.status == "member",
                    isLoading: isLoading,
                    onAction: onJoinCommunity
                )
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 20)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        )
    }
}

// MARK: - Campaign Code Card
struct CampaignCodeCard: View {
    let code: String
    
    var body: some View {
        VStack(spacing: 16) {
            Text("Kampanya Kodu")
                .font(.system(size: 20, weight: .bold, design: .rounded))
            
            Text(code)
                .font(.system(size: 32, weight: .black, design: .monospaced))
                .foregroundColor(Color(hex: "6366f1"))
                .padding(.vertical, 12)
                .padding(.horizontal, 24)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color(hex: "6366f1").opacity(0.1))
                )
            }
        .frame(maxWidth: .infinity)
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 20)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        )
    }
}


// MARK: - QR Code Sheet
struct QRCodeSheet: View {
    let code: String
    @Environment(\.dismiss) var dismiss
    @State private var qrImage: UIImage?
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 24) {
                if let qrImage = qrImage {
                    Image(uiImage: qrImage)
                        .resizable()
                        .interpolation(.none)
                        .scaledToFit()
                        .frame(width: 280, height: 280)
                        .background(Color.white)
                        .cornerRadius(16)
                        .shadow(color: Color.black.opacity(0.1), radius: 10, x: 0, y: 5)
                } else {
                    ProgressView()
                }
            }
            .padding()
            .navigationTitle("QR Kod")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        dismiss()
                    }
                }
            }
            .onAppear {
                generateQRCode()
            }
        }
    }
    
    private func generateQRCode() {
        guard let data = code.data(using: .utf8) else { return }
        guard let filter = CIFilter(name: "CIQRCodeGenerator") else { return }
        filter.setValue(data, forKey: "inputMessage")
        filter.setValue("H", forKey: "inputCorrectionLevel")
        
        guard let ciImage = filter.outputImage else { return }
        let scale = 512 / ciImage.extent.width
        let transformedImage = ciImage.transformed(by: CGAffineTransform(scaleX: scale, y: scale))
        
        let context = CIContext()
        guard let cgImage = context.createCGImage(transformedImage, from: transformedImage.extent) else { return }
        qrImage = UIImage(cgImage: cgImage)
    }
}

// MARK: - Campaign Detail View Extension
extension CampaignDetailView {
    var hasRequirements: Bool {
        campaign.requiresMembership == true
    }
    
    var areRequirementsFulfilled: Bool {
        if campaign.requiresMembership == true {
            return membershipStatus?.isMember == true || membershipStatus?.status == "member"
        }
        return true
    }
}

// MARK: - User Campaign Code Card (Yeni güvenli sistem)
struct UserCampaignCodeCard: View {
    let code: String
    let qrCodeData: String?
    let used: Bool
    let usedAt: String?
    let isLoading: Bool
    
    @State private var isCopied = false
    @State private var showQRCode = false
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                Text("Kampanya Kodunuz")
                    .font(.system(size: 22, weight: .bold, design: .rounded))
                
                Spacer()
                
                // Kullanım durumu badge
                if used {
                    HStack(spacing: 4) {
                        Image(systemName: "checkmark.circle.fill")
                            .font(.system(size: 12))
                        Text("Kullanıldı")
                            .font(.system(size: 12, weight: .semibold))
                    }
                    .foregroundColor(.white)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(Color(hex: "10b981"))
                    .cornerRadius(8)
                }
            }
            
            // QR Kod ve Kod
            HStack(spacing: 16) {
                // QR Kod - Direkt kod string'ini kullan
                if !used {
                    Button(action: {
                        showQRCode = true
                    }) {
                        ZStack {
                            RoundedRectangle(cornerRadius: 12)
                                .fill(Color.white)
                                .frame(width: 100, height: 100)
                            
                            // QR kod görüntüsü (CoreImage kullanarak) - direkt code string'ini kullan
                            if let qrImage = generateQRCode(from: code) {
                                Image(uiImage: qrImage)
                                    .resizable()
                                    .interpolation(.none)
                                    .scaledToFit()
                                    .frame(width: 90, height: 90)
                            } else {
                                Image(systemName: "qrcode")
                                    .font(.system(size: 40))
                                    .foregroundColor(.gray)
                            }
                        }
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(Color(hex: "6366f1").opacity(0.3), lineWidth: 2)
                        )
                    }
                    .buttonStyle(PlainButtonStyle())
                }
                
                // Kod
                VStack(alignment: .leading, spacing: 8) {
                    Button(action: {
                        UIPasteboard.general.string = code
                        isCopied = true
                        
                        let generator = UINotificationFeedbackGenerator()
                        generator.notificationOccurred(.success)
                        
                        DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                            isCopied = false
                        }
                    }) {
                        HStack {
                            Text(code)
                                .font(.system(size: 20, weight: .bold, design: .monospaced))
                                .foregroundColor(isCopied ? Color(hex: "10b981") : Color(hex: "6366f1"))
                            
                            Spacer()
                            
                            if isCopied {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 18, weight: .semibold))
                                    .foregroundColor(Color(hex: "10b981"))
                            } else {
                                Image(systemName: "doc.on.doc")
                                    .font(.system(size: 16))
                                    .foregroundColor(Color(hex: "6366f1"))
                            }
                        }
                        .padding(.horizontal, 16)
                        .padding(.vertical, 12)
                        .frame(maxWidth: .infinity)
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(isCopied ? Color(hex: "10b981").opacity(0.12) : Color(hex: "6366f1").opacity(0.12))
                        )
                    }
                    .buttonStyle(PlainButtonStyle())
                    
                    if used, let usedAt = usedAt {
                        Text("Kullanım tarihi: \(formatUsedDate(usedAt))")
                            .font(.system(size: 12))
                            .foregroundColor(.secondary)
                    } else if !used {
                        Text("Bu kod sadece size özeldir ve tek kullanımlıktır")
                            .font(.system(size: 12))
                            .foregroundColor(.secondary)
                    }
                }
            }
            
            // Bilgilendirme
            if !used {
                HStack(spacing: 8) {
                    Image(systemName: "info.circle.fill")
                        .font(.system(size: 14))
                        .foregroundColor(Color(hex: "6366f1"))
                    Text("Dükkanda bu kodu gösterin veya QR kodu okutun")
                        .font(.system(size: 13))
                        .foregroundColor(.secondary)
                }
                .padding(12)
                .background(Color(hex: "6366f1").opacity(0.1))
                .cornerRadius(10)
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 20)
                .fill(Color(UIColor.secondarySystemBackground))
                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        )
        .sheet(isPresented: $showQRCode) {
            if let qrImage = generateQRCode(from: code) {
                NavigationStack {
                    VStack(spacing: 24) {
                        Image(uiImage: qrImage)
                            .resizable()
                            .interpolation(.none)
                            .scaledToFit()
                            .frame(width: 300, height: 300)
                            .padding(20)
                            .background(Color.white)
                            .cornerRadius(20)
                        
                        Text(code)
                            .font(.system(size: 24, weight: .bold, design: .monospaced))
                            .foregroundColor(.primary)
                        
                        Text("Dükkanda bu QR kodu okutun")
                            .font(.system(size: 16))
                            .foregroundColor(.secondary)
                    }
                    .padding()
                    .navigationTitle("QR Kod")
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar {
                        ToolbarItem(placement: .navigationBarTrailing) {
                            Button("Kapat") {
                                showQRCode = false
                            }
                        }
                    }
                }
            }
        }
    }
    
    private func generateQRCode(from string: String) -> UIImage? {
        // QR kod için direkt kod string'ini kullan
        guard let data = string.data(using: .utf8) else { return nil }
        
        let filter = CIFilter(name: "CIQRCodeGenerator")
        filter?.setValue(data, forKey: "inputMessage")
        
        guard let qrImage = filter?.outputImage else { return nil }
        
        let transform = CGAffineTransform(scaleX: 10, y: 10)
        let scaledImage = qrImage.transformed(by: transform)
        
        let context = CIContext()
        guard let cgImage = context.createCGImage(scaledImage, from: scaledImage.extent) else { return nil }
        
        return UIImage(cgImage: cgImage)
    }
    
    private func formatUsedDate(_ dateString: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        formatter.locale = Locale(identifier: "tr_TR")
        
        if let date = formatter.date(from: dateString) {
            formatter.dateFormat = "dd MMMM yyyy, HH:mm"
            return formatter.string(from: date)
        }
        
        return dateString
    }
}


// MARK: - Requirement Row
struct RequirementRow: View {
    let icon: String
    let title: String
    let description: String
    let isFulfilled: Bool
    let isLoading: Bool
    let onAction: (() -> Void)?
    
    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            ZStack {
                Circle()
                    .fill(isFulfilled ? Color(hex: "10b981").opacity(0.15) : Color(hex: "ef4444").opacity(0.15))
                    .frame(width: 40, height: 40)
                
                if isLoading {
                    ProgressView()
                        .scaleEffect(0.8)
                } else {
                    Image(systemName: isFulfilled ? "checkmark.circle.fill" : "xmark.circle.fill")
                        .font(.system(size: 20, weight: .semibold))
                        .foregroundColor(isFulfilled ? Color(hex: "10b981") : Color(hex: "ef4444"))
                }
            }
            
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(.primary)
                
                Text(description)
                    .font(.system(size: 14, weight: .regular))
                    .foregroundColor(.secondary)
                    .lineSpacing(2)
                
                if !isFulfilled, let onAction = onAction {
                    Button(action: onAction) {
                        Text("Şimdi Katıl")
                            .font(.system(size: 14, weight: .semibold))
                            .foregroundColor(.white)
                            .padding(.horizontal, 16)
                            .padding(.vertical, 8)
                            .background(
                                RoundedRectangle(cornerRadius: 8)
                                    .fill(Color(hex: "6366f1"))
                            )
                    }
                    .padding(.top, 4)
                }
            }
            
            Spacer()
        }
        .padding(.vertical, 8)
    }
}

// MARK: - Requirements Not Met Card
struct RequirementsNotMetCard: View {
    let campaign: Campaign
    let membershipStatus: MembershipStatus?
    let onJoinCommunity: () -> Void
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 12) {
                Image(systemName: "lock.fill")
                    .font(.system(size: 24, weight: .semibold))
                    .foregroundColor(Color(hex: "ef4444"))
                
                VStack(alignment: .leading, spacing: 4) {
                    Text("Kampanya Kodu Kilitli")
                        .font(.system(size: 18, weight: .bold, design: .rounded))
                    
                    Text("Kampanya kodunu görmek için şartları karşılamanız gerekmektedir.")
                        .font(.system(size: 14, weight: .regular))
                        .foregroundColor(.secondary)
                }
            }
            
            if campaign.requiresMembership == true {
                if membershipStatus?.isMember != true && membershipStatus?.status != "member" {
                    Button(action: onJoinCommunity) {
                        HStack {
                            Image(systemName: "person.3.fill")
                            Text("Topluluğa Katıl")
                        }
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 12)
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(Color(hex: "6366f1"))
                        )
                    }
                }
            }
        }
        .padding(20)
        .background(
            RoundedRectangle(cornerRadius: 20)
                .fill(Color(hex: "ef4444").opacity(0.1))
                .overlay(
                    RoundedRectangle(cornerRadius: 20)
                        .stroke(Color(hex: "ef4444").opacity(0.3), lineWidth: 1)
                )
                .shadow(color: Color.black.opacity(0.05), radius: 8, x: 0, y: 2)
        )
    }
}

