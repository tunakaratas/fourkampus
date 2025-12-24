//
//  EventSurveyView.swift
//  Four Kampüs
//
//  Created for Event Survey Feature
//

import SwiftUI

struct EventSurveyView: View {
    let event: Event
    @EnvironmentObject var authViewModel: AuthViewModel
    @EnvironmentObject var communitiesViewModel: CommunitiesViewModel
    @State private var survey: Survey?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var selectedResponses: [Int: Int] = [:] // questionId: optionId
    @State private var textResponses: [Int: String] = [:] // questionId: responseText
    @State private var showSuccessAlert = false
    @State private var isSubmitting = false
    @State private var membershipStatus: MembershipStatus?
    @State private var isLoadingMembership = false
    
    // Üyelik kontrolü - En güncel veriyi öncelikli kullan
    private var isMember: Bool {
        // 1. En güncel: API'den çekilen yerel membershipStatus'ü kontrol et
        if let status = membershipStatus {
            return status.isMember || status.status == "member" || status.status == "approved"
        }
        // 2. Cache: CommunitiesViewModel içindeki preloaded veriyi kontrol et
        if let status = communitiesViewModel.getMembershipStatus(for: event.communityId) {
            return status.isMember || status.status == "member" || status.status == "approved"
        }
        // 3. Fallback: Event'ten gelen is_member bilgisini kontrol et
        if let eventIsMember = event.isMember {
            return eventIsMember
        }
        return false
    }
    
    var body: some View {
        Group {
            if isLoading {
                ProgressView()
                    .frame(maxWidth: .infinity, minHeight: 100)
            } else if let survey = survey {
                // Üyelik kontrolü - Event'ten gelen bilgiyi kullan
                if !isMember && authViewModel.isAuthenticated {
                    // Üye değilse uyarı mesajı göster
                    VStack(spacing: 16) {
                        Image(systemName: "lock.fill")
                            .font(.system(size: 40))
                            .foregroundColor(.orange)
                        Text("Bu Anketi Görmek İçin Üye Olmalısınız")
                            .font(.system(size: 16, weight: .semibold))
                            .foregroundColor(.primary)
                            .multilineTextAlignment(.center)
                        Text("Bu topluluğa üye olarak anketlere katılabilirsiniz.")
                            .font(.system(size: 14))
                            .foregroundColor(.secondary)
                            .multilineTextAlignment(.center)
                    }
                    .frame(maxWidth: .infinity, minHeight: 150)
                    .padding(20)
                    .background(
                        RoundedRectangle(cornerRadius: 16)
                            .fill(Color(UIColor.secondarySystemBackground))
                    )
                } else if isMember || !authViewModel.isAuthenticated {
                    // Üye ise veya giriş yapılmamışsa anketi göster
                    // Üye ise anketi göster
                    VStack(alignment: .leading, spacing: 20) {
                        // Survey Header
                        VStack(alignment: .leading, spacing: 8) {
                            Text(survey.title)
                                .font(.system(size: 20, weight: .bold))
                                .foregroundColor(.primary)
                            
                            if let description = survey.description, !description.isEmpty {
                                Text(description)
                                    .font(.system(size: 14))
                                    .foregroundColor(.secondary)
                            }
                        }
                        .padding(.bottom, 8)
                        
                        // Questions
                        ForEach(survey.questions.sorted(by: { $0.displayOrder < $1.displayOrder })) { question in
                            QuestionView(
                                question: question,
                                selectedOptionId: selectedResponses[question.id],
                                textResponse: textResponses[question.id] ?? "",
                                onOptionSelected: { optionId in
                                    selectedResponses[question.id] = optionId
                                    textResponses.removeValue(forKey: question.id)
                                },
                                onTextChanged: { text in
                                    textResponses[question.id] = text
                                    selectedResponses.removeValue(forKey: question.id)
                                }
                            )
                        }
                        
                        // Submit Button - Sadece kullanıcı daha önce cevap vermediyse göster
                        if !survey.hasUserResponse {
                            Button(action: submitSurvey) {
                                HStack {
                                    if isSubmitting {
                                        ProgressView()
                                            .progressViewStyle(CircularProgressViewStyle(tint: .white))
                                    } else {
                                        Text("Anketi Gönder")
                                            .font(.system(size: 16, weight: .semibold))
                                    }
                                }
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 14)
                                .background(
                                    canSubmit ? Color(hex: "6366f1") : Color.gray.opacity(0.3)
                                )
                                .foregroundColor(.white)
                                .cornerRadius(12)
                            }
                            .disabled(!canSubmit || isSubmitting)
                            .padding(.top, 8)
                        } else {
                            // Kullanıcı zaten cevap vermiş - bilgi mesajı göster
                            HStack(spacing: 12) {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 20))
                                    .foregroundColor(Color(hex: "10b981"))
                                Text("Anketi gönderdiniz")
                                    .font(.system(size: 15, weight: .medium))
                                    .foregroundColor(.secondary)
                            }
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(Color(UIColor.secondarySystemBackground))
                            .cornerRadius(12)
                            .padding(.top, 8)
                        }
                    }
                    .padding(20)
                    .background(
                        RoundedRectangle(cornerRadius: 16)
                            .fill(Color(UIColor.secondarySystemBackground))
                            .shadow(color: Color.black.opacity(0.08), radius: 12, x: 0, y: 4)
                    )
                }
            } else if let error = errorMessage {
                VStack(spacing: 12) {
                    Image(systemName: "exclamationmark.triangle")
                        .font(.system(size: 40))
                        .foregroundColor(.orange)
                    Text(error)
                        .font(.system(size: 14))
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity, minHeight: 100)
                .padding()
            } else {
                VStack(spacing: 12) {
                    Image(systemName: "doc.text")
                        .font(.system(size: 40))
                        .foregroundColor(.gray)
                    Text("Bu etkinlik için anket bulunmamaktadır")
                        .font(.system(size: 14))
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, minHeight: 100)
                .padding()
            }
        }
        .onAppear {
            // Event'ten gelen is_member bilgisi yoksa API'den çek
            if event.isMember == nil && authViewModel.isAuthenticated {
                loadMembershipStatus()
            }
            // Üye ise veya giriş yapılmamışsa anketi yükle
            if isMember || !authViewModel.isAuthenticated {
                loadSurvey()
            }
        }
        .alert("Başarılı", isPresented: $showSuccessAlert) {
            Button("Tamam", role: .cancel) {}
        } message: {
            Text("Anket yanıtınız başarıyla kaydedildi.")
        }
    }
    
    private var canSubmit: Bool {
        guard let survey = survey else { return false }
        
        for question in survey.questions {
            if question.questionType == "multiple_choice" {
                if selectedResponses[question.id] == nil {
                    return false
                }
            } else if question.questionType == "text" {
                if let text = textResponses[question.id], text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    return false
                } else if textResponses[question.id] == nil {
                    return false
                }
            }
        }
        
        return true
    }
    
    private func loadMembershipStatus() {
        guard authViewModel.isAuthenticated && !isLoadingMembership else { return }
        
        // Önce preloaded cache'i kontrol et
        if let preloadedStatus = communitiesViewModel.getMembershipStatus(for: event.communityId) {
            #if DEBUG
            print("✅ EventSurveyView: Preloaded üyelik durumu kullanıldı: \(preloadedStatus.status)")
            #endif
            self.membershipStatus = preloadedStatus
            return
        }
        
        isLoadingMembership = true
        Task {
            do {
                #if DEBUG
                print("🔄 EventSurveyView: Preloaded veri yok, API'den üyelik durumu yükleniyor: \(event.communityId)")
                #endif
                let status = try await APIService.shared.getMembershipStatus(communityId: event.communityId)
                
                // Cache'i güncelle
                communitiesViewModel.updateMembershipStatus(communityId: event.communityId, status: status)
                
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
    
    private func loadSurvey() {
        guard let eventId = Int(event.id) else { return }
        
        isLoading = true
        errorMessage = nil
        
        Task {
            do {
                let loadedSurvey = try await APIService.shared.getEventSurvey(
                    communityId: event.communityId,
                    eventId: eventId
                )
                
                await MainActor.run {
                    self.survey = loadedSurvey
                    // Kullanıcının daha önce verdiği cevapları yükle
                    if let survey = loadedSurvey {
                        for question in survey.questions {
                            if let userResponse = question.userResponse {
                                if let optionId = userResponse.optionId {
                                    self.selectedResponses[question.id] = optionId
                                }
                                if let responseText = userResponse.responseText, !responseText.isEmpty {
                                    self.textResponses[question.id] = responseText
                                }
                            }
                        }
                    }
                    self.isLoading = false
                }
            } catch {
                await MainActor.run {
                    self.errorMessage = "Anket yüklenemedi: \(error.localizedDescription)"
                    self.isLoading = false
                }
            }
        }
    }
    
    private func submitSurvey() {
        guard let survey = survey,
              let eventId = Int(event.id),
              let userEmail = authViewModel.currentUser?.email else {
            return
        }
        
        isSubmitting = true
        
        var responses: [SurveyResponseItem] = []
        
        for question in survey.questions {
            if question.questionType == "multiple_choice" {
                if let optionId = selectedResponses[question.id] {
                    responses.append(SurveyResponseItem(
                        questionId: question.id,
                        optionId: optionId,
                        responseText: nil
                    ))
                }
            } else if question.questionType == "text" {
                if let text = textResponses[question.id], !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    responses.append(SurveyResponseItem(
                        questionId: question.id,
                        optionId: nil,
                        responseText: text
                    ))
                }
            }
        }
        
        Task {
            do {
                try await APIService.shared.submitEventSurvey(
                    communityId: event.communityId,
                    eventId: eventId,
                    userEmail: userEmail,
                    userName: authViewModel.currentUser?.displayName,
                    responses: responses
                )
                
                await MainActor.run {
                    self.isSubmitting = false
                    self.showSuccessAlert = true
                    // Anketi yeniden yükle (kullanıcının cevaplarını görmek için)
                    self.loadSurvey()
                }
            } catch {
                await MainActor.run {
                    self.isSubmitting = false
                    self.errorMessage = "Anket gönderilemedi: \(error.localizedDescription)"
                }
            }
        }
    }
}

// MARK: - Question View
struct QuestionView: View {
    let question: SurveyQuestion
    let selectedOptionId: Int?
    let textResponse: String
    let onOptionSelected: (Int) -> Void
    let onTextChanged: (String) -> Void
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(question.questionText)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(.primary)
            
            if question.questionType == "multiple_choice" {
                // Multiple choice options
                ForEach(question.options.sorted(by: { $0.order < $1.order })) { option in
                    Button(action: {
                        onOptionSelected(option.id)
                    }) {
                        HStack {
                            Image(systemName: selectedOptionId == option.id ? "checkmark.circle.fill" : "circle")
                                .font(.system(size: 20))
                                .foregroundColor(selectedOptionId == option.id ? Color(hex: "6366f1") : .gray)
                            
                            Text(option.text)
                                .font(.system(size: 15))
                                .foregroundColor(.primary)
                            
                            Spacer()
                        }
                        .padding(.vertical, 8)
                        .padding(.horizontal, 12)
                        .background(
                            RoundedRectangle(cornerRadius: 8)
                                .fill(selectedOptionId == option.id ? Color(hex: "6366f1").opacity(0.1) : Color(UIColor.secondarySystemBackground))
                        )
                    }
                    .buttonStyle(PlainButtonStyle())
                }
            } else {
                // Text response
                TextField("Yanıtınızı yazın...", text: Binding(
                    get: { textResponse },
                    set: { onTextChanged($0) }
                ), axis: .vertical)
                .textFieldStyle(.plain)
                .padding(12)
                .background(
                    RoundedRectangle(cornerRadius: 8)
                        .fill(Color(UIColor.secondarySystemBackground))
                )
                .lineLimit(3...6)
            }
        }
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(Color(UIColor.systemBackground))
        )
    }
}

