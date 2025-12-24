//
//  EventRSVPView.swift
//  Four Kampüs
//
//  Created for Event RSVP Feature
//

import SwiftUI

struct EventRSVPView: View {
    let event: Event
    @EnvironmentObject var authViewModel: AuthViewModel
    @State private var rsvpStatus: RSVPStatusResponse?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var showRSVPSheet = false
    @State private var selectedStatus: RSVP.RSVPStatus = .attending
    @State private var isSubmitting = false
    @State private var showSuccessAlert = false
    @State private var statistics: RSVPStatistics?
    @State private var membershipStatus: MembershipStatus?
    @State private var isLoadingMembership = false
    
    var body: some View {
        VStack(spacing: 16) {
            if isLoading || isLoadingMembership {
                ProgressView()
                    .frame(maxWidth: .infinity, minHeight: 60)
            } else if let membership = membershipStatus, (membership.isMember || membership.status == "member" || membership.status == "approved") {
                // Üye ise RSVP göster
                // RSVP Status Card
                if let rsvpStatus = rsvpStatus {
                    if rsvpStatus.hasRsvp {
                        // User has RSVP
                        VStack(spacing: 12) {
                            HStack {
                                Image(systemName: rsvpStatus.status == "attending" ? "checkmark.circle.fill" : "xmark.circle.fill")
                                    .font(.system(size: 24))
                                    .foregroundColor(rsvpStatus.status == "attending" ? .green : .red)
                                
                                VStack(alignment: .leading, spacing: 4) {
                                    Text(rsvpStatus.status == "attending" ? "Katılıyorsunuz" : "Katılmıyorsunuz")
                                        .font(.system(size: 16, weight: .semibold))
                                        .foregroundColor(.primary)
                                    
                                    if let memberName = rsvpStatus.memberName {
                                        Text(memberName)
                                            .font(.system(size: 14))
                                            .foregroundColor(.secondary)
                                    }
                                }
                                
                                Spacer()
                                
                                Button(action: {
                                    showRSVPSheet = true
                                }) {
                                    Text("Değiştir")
                                        .font(.system(size: 14, weight: .medium))
                                        .foregroundColor(Color(hex: "6366f1"))
                                }
                            }
                            
                            Divider()
                            
                            // Statistics
                            if let stats = statistics {
                                HStack(spacing: 20) {
                                    RSVPStatItem(
                                        icon: "checkmark.circle.fill",
                                        value: "\(stats.attendingCount)",
                                        label: "Katılıyor",
                                        color: .green
                                    )
                                    
                                    RSVPStatItem(
                                        icon: "xmark.circle.fill",
                                        value: "\(stats.notAttendingCount)",
                                        label: "Katılmıyor",
                                        color: .red
                                    )
                                    
                                    RSVPStatItem(
                                        icon: "person.2.fill",
                                        value: "\(stats.totalCount)",
                                        label: "Toplam",
                                        color: .blue
                                    )
                                }
                            }
                        }
                        .padding(16)
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(Color(UIColor.secondarySystemBackground))
                        )
                    } else {
                        // No RSVP yet
                        VStack(spacing: 12) {
                            Text("Bu etkinliğe katılacak mısınız?")
                                .font(.system(size: 16, weight: .semibold))
                                .foregroundColor(.primary)
                            
                            HStack(spacing: 12) {
                                Button(action: {
                                    selectedStatus = .attending
                                    submitRSVP()
                                }) {
                                    HStack {
                                        Image(systemName: "checkmark.circle.fill")
                                        Text("Katılıyorum")
                                    }
                                    .font(.system(size: 15, weight: .medium))
                                    .foregroundColor(.white)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 12)
                                    .background(Color.green)
                                    .cornerRadius(10)
                                }
                                .disabled(isSubmitting)
                                
                                Button(action: {
                                    selectedStatus = .notAttending
                                    submitRSVP()
                                }) {
                                    HStack {
                                        Image(systemName: "xmark.circle.fill")
                                        Text("Katılmıyorum")
                                    }
                                    .font(.system(size: 15, weight: .medium))
                                    .foregroundColor(.white)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 12)
                                    .background(Color.red)
                                    .cornerRadius(10)
                                }
                                .disabled(isSubmitting)
                            }
                            
                            if isSubmitting {
                                ProgressView()
                                    .padding(.top, 8)
                            }
                        }
                        .padding(16)
                        .background(
                            RoundedRectangle(cornerRadius: 12)
                                .fill(Color(UIColor.secondarySystemBackground))
                        )
                    }
                } else if let error = errorMessage {
                    VStack(spacing: 8) {
                        Image(systemName: "exclamationmark.triangle")
                            .font(.system(size: 24))
                            .foregroundColor(.orange)
                        Text(error)
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                    }
                    .padding()
                }
            } else {
                // Üye değilse uyarı mesajı göster
                VStack(spacing: 16) {
                    Image(systemName: "lock.fill")
                        .font(.system(size: 40))
                        .foregroundColor(.orange)
                    Text("Katılım Durumunu Belirtmek İçin Üye Olmalısınız")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.primary)
                        .multilineTextAlignment(.center)
                    Text("Bu topluluğa üye olarak etkinliklere katılım durumunuzu belirtebilirsiniz.")
                        .font(.system(size: 14))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity, minHeight: 150)
                .padding(20)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(Color(UIColor.secondarySystemBackground))
                )
            }
        }
        .onAppear {
            if authViewModel.isAuthenticated {
                loadMembershipStatus()
            }
            loadRSVPStatus()
        }
        .sheet(isPresented: $showRSVPSheet) {
            RSVPEditSheet(
                event: event,
                currentStatus: rsvpStatus?.status ?? "attending",
                onStatusChanged: { newStatus in
                    selectedStatus = newStatus
                    submitRSVP()
                },
                onCancel: {
                    showRSVPSheet = false
                }
            )
        }
        .alert("Başarılı", isPresented: $showSuccessAlert) {
            Button("Tamam", role: .cancel) {
                loadRSVPStatus()
            }
        } message: {
            Text("Katılım durumunuz güncellendi.")
        }
    }
    
    private func loadMembershipStatus() {
        guard authViewModel.isAuthenticated && !isLoadingMembership else { return }
        
        isLoadingMembership = true
        Task {
            do {
                let status = try await APIService.shared.getMembershipStatus(communityId: event.communityId)
                await MainActor.run {
                    membershipStatus = status
                    isLoadingMembership = false
                }
            } catch {
                await MainActor.run {
                    isLoadingMembership = false
                    membershipStatus = nil
                }
            }
        }
    }
    
    private func loadRSVPStatus() {
        guard let eventId = Int(event.id),
              let userEmail = authViewModel.currentUser?.email else {
            errorMessage = "Kullanıcı bilgisi bulunamadı"
            return
        }
        
        isLoading = true
        errorMessage = nil
        
        Task {
            do {
                let status = try await APIService.shared.getEventRSVPStatus(
                    communityId: event.communityId,
                    eventId: eventId,
                    userEmail: userEmail
                )
                
                await MainActor.run {
                    self.rsvpStatus = status
                    self.isLoading = false
                }
                
                // Load statistics separately
                loadStatistics()
            } catch {
                await MainActor.run {
                    self.errorMessage = "Katılım durumu yüklenemedi: \(error.localizedDescription)"
                    self.isLoading = false
                }
            }
        }
    }
    
    private func loadStatistics() {
        guard let eventId = Int(event.id) else { return }
        
        Task {
            do {
                // Get RSVP list to calculate statistics
                let response = try await APIService.shared.getRSVP(
                    communityId: event.communityId,
                    eventId: String(eventId)
                )
                
                await MainActor.run {
                    self.statistics = response.statistics
                }
            } catch {
                // Statistics loading failed, but don't show error
                print("Statistics loading failed: \(error)")
            }
        }
    }
    
    private func submitRSVP() {
        guard let eventId = Int(event.id),
              let userEmail = authViewModel.currentUser?.email,
              let userName = authViewModel.currentUser?.displayName else {
            return
        }
        
        isSubmitting = true
        
        Task {
            do {
                let response = try await APIService.shared.createOrUpdateRSVP(
                    communityId: event.communityId,
                    eventId: eventId,
                    memberName: userName,
                    memberEmail: userEmail,
                    memberPhone: authViewModel.currentUser?.phoneNumber,
                    status: selectedStatus
                )
                
                await MainActor.run {
                    self.isSubmitting = false
                    self.showSuccessAlert = true
                    self.statistics = response.statistics
                    self.showRSVPSheet = false
                }
            } catch {
                await MainActor.run {
                    self.isSubmitting = false
                    self.errorMessage = "Katılım durumu kaydedilemedi: \(error.localizedDescription)"
                }
            }
        }
    }
}

// MARK: - RSVP Stat Item
struct RSVPStatItem: View {
    let icon: String
    let value: String
    let label: String
    let color: Color
    
    var body: some View {
        VStack(spacing: 4) {
            Image(systemName: icon)
                .font(.system(size: 20))
                .foregroundColor(color)
            Text(value)
                .font(.system(size: 18, weight: .bold))
                .foregroundColor(.primary)
            Text(label)
                .font(.system(size: 12))
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - RSVP Edit Sheet
struct RSVPEditSheet: View {
    let event: Event
    let currentStatus: String
    let onStatusChanged: (RSVP.RSVPStatus) -> Void
    let onCancel: () -> Void
    
    @State private var selectedStatus: RSVP.RSVPStatus
    
    init(event: Event, currentStatus: String, onStatusChanged: @escaping (RSVP.RSVPStatus) -> Void, onCancel: @escaping () -> Void) {
        self.event = event
        self.currentStatus = currentStatus
        self.onStatusChanged = onStatusChanged
        self.onCancel = onCancel
        _selectedStatus = State(initialValue: currentStatus == "attending" ? .attending : .notAttending)
    }
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 24) {
                Text("Katılım Durumunu Güncelle")
                    .font(.system(size: 20, weight: .bold))
                    .padding(.top)
                
                VStack(spacing: 16) {
                    RSVPStatusButton(
                        title: "Katılıyorum",
                        icon: "checkmark.circle.fill",
                        color: .green,
                        isSelected: selectedStatus == .attending
                    ) {
                        selectedStatus = .attending
                    }
                    
                    RSVPStatusButton(
                        title: "Katılmıyorum",
                        icon: "xmark.circle.fill",
                        color: .red,
                        isSelected: selectedStatus == .notAttending
                    ) {
                        selectedStatus = .notAttending
                    }
                }
                .padding(.horizontal)
                
                Spacer()
                
                Button(action: {
                    onStatusChanged(selectedStatus)
                }) {
                    Text("Kaydet")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(Color(hex: "6366f1"))
                        .cornerRadius(12)
                }
                .padding(.horizontal)
                .padding(.bottom)
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("İptal") {
                        onCancel()
                    }
                }
            }
        }
    }
}

// MARK: - RSVP Status Button
struct RSVPStatusButton: View {
    let title: String
    let icon: String
    let color: Color
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack {
                Image(systemName: icon)
                    .font(.system(size: 24))
                    .foregroundColor(isSelected ? color : .gray)
                
                Text(title)
                    .font(.system(size: 16, weight: .medium))
                    .foregroundColor(.primary)
                
                Spacer()
                
                if isSelected {
                    Image(systemName: "checkmark")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(color)
                }
            }
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 12)
                    .fill(isSelected ? color.opacity(0.1) : Color(UIColor.secondarySystemBackground))
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(isSelected ? color : Color.clear, lineWidth: 2)
                    )
            )
        }
        .buttonStyle(PlainButtonStyle())
    }
}

