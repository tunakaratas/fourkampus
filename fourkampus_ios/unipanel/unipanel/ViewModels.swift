//
//  ViewModels.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import SwiftUI
import Combine

// MARK: - Communities ViewModel
@MainActor
class CommunitiesViewModel: ObservableObject {
    @Published var communities: [Community] = []
    @Published var universities: [University] = []
    @Published var selectedUniversity: University?
    @Published var isLoading = false // İlk yüklemede false başlat, view'da kontrol edilecek
    @Published var errorMessage: String?
    @Published var searchText = ""
    @Published var selectedCategories: Set<String> = [] // Çoklu kategori seçimi (max 3)
    @Published var sortOption: SortOption = .name
    @Published var favoriteIds: Set<String> = []
    @Published var hasInitiallyLoaded = false // İlk yükleme tamamlandı mı?
    @Published var showOnlyMyCommunities = false
    @Published var memberCommunityIds: Set<String> = []
    @Published var isLoadingMembershipStatuses = false // Üyelik durumları yükleniyor mu?
    @Published var verifiedCommunityMap: [String: VerifiedCommunityInfo] = [:]
    @Published var isLoadingVerifiedCommunities = false
    @Published var isLoadingMore = false // Lazy loading için
    private var isRefreshing = false // Refresh durumu - lazy loading'i engellemek için
    
    // Cache için: Hangi topluluklar için üyelik durumu yüklendi
    private var loadedMembershipForCommunityIds: Set<String> = []
    private var lastMembershipLoadTime: Date?
    private let membershipCacheDuration: TimeInterval = 300 // 5 dakika cache
    private var hasLoadedVerifiedCommunities = false
    
    // Lazy loading için (scroll-based)
    private var currentOffset: Int = 0 // API pagination için
    private var hasMoreFromAPI: Bool = true // API'de daha fazla topluluk var mı?
    private let loadMoreBatchSize: Int = 50 // Her seferinde yüklenecek sayı (artırıldı)
    
    enum SortOption: String, CaseIterable {
        case name = "İsme Göre"
        case members = "Üye Sayısına Göre"
        case events = "Etkinlik Sayısına Göre"
        case campaigns = "Kampanya Sayısına Göre"
        case date = "Tarihe Göre"
    }
    
    // Filtrelenmiş topluluklar
    var filteredCommunities: [Community] {
        var filtered = communities
        
        // YENİ SİSTEM: Üniversite filtresi - Client-side filtreleme
        // API'ye university_id parametresi gönderilmiyor, tüm topluluklar yükleniyor
        // Burada client-side filtreleme yapılıyor
        if let selectedUni = selectedUniversity, selectedUni.id != "all" {
            // Üniversite seçiliyse, o üniversiteye ait toplulukları filtrele
            // Community modelinde university field'ı olmalı
            filtered = filtered.filter { community in
                // Community modelinde university field'ı varsa kullan
                if let communityUniversity = community.university {
                    // Üniversite adını normalize et ve karşılaştır
                    let normalizedCommunity = communityUniversity.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    let normalizedSelected = selectedUni.name.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    return normalizedCommunity == normalizedSelected
                }
                return false
            }
        }
        
        // "Yalnızca üyesi olduklarım" filtresi
        if showOnlyMyCommunities {
            filtered = filtered.filter { community in
                memberCommunityIds.contains(community.id)
            }
        }
        
        // Search filter
        if !searchText.isEmpty {
            filtered = filtered.filter { community in
                community.name.localizedCaseInsensitiveContains(searchText) ||
                community.description.localizedCaseInsensitiveContains(searchText) ||
                community.tags.contains { $0.localizedCaseInsensitiveContains(searchText) }
            }
        }
        
        // Category filter - Seçili kategorilerden en az birine sahip olanları göster
        if !selectedCategories.isEmpty {
            filtered = filtered.filter { community in
                !Set(community.categories).isDisjoint(with: selectedCategories)
            }
        }
        
        // Sort
        switch sortOption {
        case .name:
            filtered.sort { $0.name < $1.name }
        case .members:
            filtered.sort { $0.memberCount > $1.memberCount }
        case .events:
            filtered.sort { $0.eventCount > $1.eventCount }
        case .campaigns:
            filtered.sort { $0.campaignCount > $1.campaignCount }
        case .date:
            filtered.sort { $0.createdAt > $1.createdAt }
        }
        
        return filtered
    }
    
    func loadUniversities() async {
        do {
            universities = try await APIService.shared.getUniversities()
        } catch {
            #if DEBUG
            print("Universities yüklenemedi: \(error.localizedDescription)")
            #endif
        }
    }
    
    func loadCommunities(forceReload: Bool = false) async {
        // ÖNEMLİ: Eğer veri varsa ve hasInitiallyLoaded true ise, forceReload olmadıkça tekrar yükleme yapma
        // Bu tab değişiminde gereksiz yüklemeleri önler
        if !forceReload && hasInitiallyLoaded && !communities.isEmpty {
            #if DEBUG
            print("⚠️ CommunitiesViewModel.loadCommunities: Veri zaten yüklü ve hasInitiallyLoaded=true, yükleme atlanıyor")
            #endif
            return
        }
        
        // Eğer zaten yükleniyorsa ve veri varsa, tekrar yükleme (forceReload değilse)
        if !forceReload && isLoading && !communities.isEmpty {
            #if DEBUG
            print("⚠️ CommunitiesViewModel.loadCommunities zaten yükleniyor ve veri var, atlanıyor")
            #endif
            return
        }
        
        // Eğer forceReload değilse ve zaten yükleniyorsa, bekle
        if !forceReload {
            // Eğer zaten yükleniyorsa, mevcut yüklemeyi bekle (polling yerine async wait)
            if isLoading {
                #if DEBUG
                print("⚠️ CommunitiesViewModel.loadCommunities zaten yükleniyor, bekleniyor...")
                #endif
                // Async wait - polling yerine daha verimli
                let startTime = Date()
                let maxWait = hasInitiallyLoaded ? 5.0 : 10.0 // İlk yükleme için daha uzun timeout
                while isLoading && Date().timeIntervalSince(startTime) < maxWait {
                    // Kısa aralıklarla kontrol et (100ms)
                    try? await Task.sleep(nanoseconds: 100_000_000)
                    // Eğer veri geldiyse hemen çık
                    if !communities.isEmpty {
                        return
                    }
                }
                // Timeout sonrası hala yükleniyorsa, yeni yükleme başlat
                if isLoading {
                    #if DEBUG
                    print("⚠️ CommunitiesViewModel.loadCommunities timeout, yeni yükleme başlatılıyor...")
                    #endif
                }
            }
        }
        
        #if DEBUG
        print("🔄 CommunitiesViewModel: Topluluklar yükleniyor... (forceReload: \(forceReload))")
        #endif
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoading = true
        errorMessage = nil
        
        do {
            // YENİ SİSTEM: Üniversite filtresi kaldırıldı - her zaman tüm topluluklar yükleniyor
            // Client-side filtreleme yapılacak (filteredCommunities computed property)
            
            #if DEBUG
            print("🔄 loadCommunities: Tüm topluluklar yükleniyor (üniversite filtresi kaldırıldı)")
            #endif
            
            // İlk yükleme - offset 0'dan başla
            // Refresh sırasında offset'i sıfırla
            if forceReload {
            currentOffset = 0
            }
            // Üniversite filtresi kaldırıldı - her zaman nil gönder
            let response = try await APIService.shared.getCommunities(universityId: nil, limit: loadMoreBatchSize, offset: currentOffset)
            #if DEBUG
            print("✅ CommunitiesViewModel: \(response.communities.count) topluluk yüklendi (offset: 0, hasMore: \(response.hasMore))")
            #endif
            
            // Thread safety - @MainActor ile işaretlendiği için MainActor.run gereksiz
            // Toplulukları sakla
            communities = response.communities
            currentOffset = response.communities.count
            hasMoreFromAPI = response.hasMore
            
            #if DEBUG
            print("🔄 CommunitiesViewModel: communities array'e atandı - count: \(communities.count)")
            print("🔄 CommunitiesViewModel: selectedUniversity: \(selectedUniversity?.id ?? "nil")")
            #endif
            
            // Filtreleme uygula
            updateDisplayedCommunities()
            
            #if DEBUG
            print("🔄 CommunitiesViewModel: filteredCommunities count: \(filteredCommunities.count)")
            #endif
            
            hasInitiallyLoaded = true
            isLoading = false
            
            Task {
                await self.loadVerifiedCommunities(forceRefresh: false)
            }
            
            // Topluluklar yüklendikten sonra üyelik durumlarını yükle (sadece giriş yapılmışsa ve cache yoksa)
            // NOT: Otomatik yükleme kaldırıldı - sadece kullanıcı istediğinde yüklenecek
            // Bu gereksiz API çağrılarını önler
        } catch {
            #if DEBUG
            print("❌ CommunitiesViewModel yükleme hatası: \(error.localizedDescription)")
            #endif
            // Cancelled hatalarını ve timeout hatalarını ignore et
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            let isTimeout = String(describing: type(of: error)).contains("TimeoutError")
            
            if isCancelled || isTimeout {
                // Cancelled/timeout hatası - refresh sırasında normal bir durum
                // Eğer forceReload ise (refresh), bu hatayı ignore et ve devam et
                if forceReload {
                    // Refresh sırasında cancelled hatası normal - yeni istek zaten başlatıldı
                    isLoading = false
                    // Veri varsa koru, hasInitiallyLoaded'i değiştirme
                    // Eğer veri yoksa ve ilk yükleme ise, hasInitiallyLoaded'i false tut (yeniden deneme yapılacak)
                    return
                }
                
                // Normal yükleme sırasında cancelled hatası
                if !communities.isEmpty {
                    // Zaten veri varsa, sadece isLoading'i false yap
                    isLoading = false
                    // hasInitiallyLoaded zaten true olmalı (veri var)
                    if !hasInitiallyLoaded {
                        hasInitiallyLoaded = true
                    }
                    return
                }
                // Veri yoksa, boş array kullan (yeniden deneme yapılacak)
                // ÖNEMLİ: hasInitiallyLoaded'i true yapma - yeniden deneme yapılacak
                communities = []
                currentOffset = 0
                hasMoreFromAPI = false
                isLoading = false
                // hasInitiallyLoaded = false kalacak, böylece skeleton gösterilmeye devam edecek
                return
            }
            
            // Eğer önceden veri varsa, refresh sırasında hata oluştuysa errorMessage'ı set etme
            // Çünkü kullanıcı zaten veriyi görebiliyor
            let hadData = !communities.isEmpty
            
            isLoading = false
            
            if !hadData {
                // İlk yükleme başarısız - otomatik retry mekanizması
                // İlk yüklemede hata olursa 1.5 saniye sonra otomatik olarak tekrar dene
                if !hasInitiallyLoaded {
                    #if DEBUG
                    print("🔄 İlk yükleme başarısız (\(error.localizedDescription)), 1.5 saniye sonra otomatik retry yapılacak...")
                    #endif
                    // isLoading'i false yap ki retry başlatılabilsin
                    isLoading = false
                    // Otomatik retry - 1.5 saniye bekle ve tekrar dene
                    Task {
                        try? await Task.sleep(nanoseconds: 1_500_000_000) // 1.5 saniye
                        // Eğer hala veri yoksa ve yüklenmiyorsa tekrar dene
                        await MainActor.run {
                            if self.communities.isEmpty && !self.isLoading {
                                #if DEBUG
                                print("🔄 Otomatik retry başlatılıyor...")
                                #endif
                                Task {
                                    await self.loadCommunities(forceReload: true)
                                }
                            }
                        }
                    }
                    // İlk retry'ı beklemeden errorMessage'ı set etme
                    // Sadece retry da başarısız olursa error göster
                    // hasInitiallyLoaded'i false tut ki skeleton gösterilmeye devam etsin
                    return
                }
                
                // Retry sonrası hala başarısızsa hata mesajı göster
                hasInitiallyLoaded = true
                errorMessage = ErrorHandler.userFriendlyMessage(from: error)
                communities = []
            } else {
                // Önceden veri varsa, sadece errorMessage'ı temizle
                // hasInitiallyLoaded zaten true olmalı
                if !hasInitiallyLoaded {
                    hasInitiallyLoaded = true
                }
                errorMessage = nil
            }
        }
        
        // Ekstra güvence: Eğer hala true ise false yap
        if isLoading {
            #if DEBUG
            print("⚠️ CommunitiesViewModel: isLoading hala true, zorla false yapılıyor")
            #endif
            isLoading = false
        }
    }
    
    /// Gösterilecek toplulukları güncelle (filtreleme sonrası)
    /// NOT: filteredCommunities zaten computed property, bu fonksiyon sadece notification için
    private func updateDisplayedCommunities() {
        // filteredCommunities computed property otomatik olarak güncellenecek
        // Bu fonksiyon sadece SwiftUI'ya değişikliği bildirmek için var
        // communities array'i zaten güncellendi, filteredCommunities otomatik hesaplanacak
    }
    
    /// Lazy loading - Scroll-based: Aşağı indirdikçe daha fazla topluluk yükle
    func loadMoreCommunities() async {
        // Refresh sırasında lazy loading'i engelle
        guard !isLoadingMore && hasMoreFromAPI && !isLoading && !isRefreshing else {
            #if DEBUG
            print("⚠️ loadMoreCommunities atlandı: isLoadingMore=\(isLoadingMore), hasMoreFromAPI=\(hasMoreFromAPI), isLoading=\(isLoading), isRefreshing=\(isRefreshing)")
            #endif
            return
        }
        
        isLoadingMore = true
        
        do {
            // API'den bir sonraki batch'i çek
            // "Tümü" seçiliyse (selectedUniversity nil veya id "all") universityId nil olmalı
            let universityId: String? = {
                if selectedUniversity == nil {
                    return nil // Tümü seçili
                } else if selectedUniversity?.id == "all" {
                    return nil // Tümü seçili
                } else {
                    return selectedUniversity?.id
                }
            }()
            
            #if DEBUG
            print("📄 Lazy loading başlatılıyor: offset=\(currentOffset), limit=\(loadMoreBatchSize), universityId=\(universityId ?? "nil (Tümü)")")
            #endif
            
            let response = try await APIService.shared.getCommunities(universityId: universityId, limit: loadMoreBatchSize, offset: currentOffset)
            
            if response.communities.isEmpty {
                // Daha fazla topluluk yok
                hasMoreFromAPI = false
                isLoadingMore = false
                #if DEBUG
                print("📄 Lazy loading: Daha fazla topluluk yok")
                #endif
                return
            }
            
            // Yeni toplulukları ekle (filteredCommunities computed property otomatik güncellenecek)
            // Duplicate ID'leri filtrele
            let existingIds = Set(communities.map { $0.id })
            let newUniqueCommunities = response.communities.filter { !existingIds.contains($0.id) }
            
            if !newUniqueCommunities.isEmpty {
                communities.append(contentsOf: newUniqueCommunities)
                currentOffset += response.communities.count // Offset API'den gelen kadar artmalı
            }
            
            hasMoreFromAPI = response.hasMore
            
            // updateDisplayedCommunities() çağrısını kaldırdık - filteredCommunities computed property otomatik güncellenecek
            
            isLoadingMore = false
            
            #if DEBUG
            print("✅ Lazy loading: \(response.communities.count) yeni topluluk yüklendi. Toplam: \(communities.count), hasMore: \(hasMoreFromAPI), currentOffset: \(currentOffset)")
            #endif
        } catch {
            isLoadingMore = false
            
            // Cancelled hatalarını ignore et - refresh sırasında normal
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if isCancelled {
                #if DEBUG
                print("⚠️ Lazy loading iptal edildi (normal - refresh sırasında)")
                #endif
                return
            }
            
            #if DEBUG
            print("❌ Lazy loading hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    /// Daha fazla topluluk var mı?
    var hasMoreCommunities: Bool {
        hasMoreFromAPI
    }
    
    func refreshCommunities() async {
        // Refresh flag'ini set et - lazy loading'i engelle
        isRefreshing = true
        
        // ÖNEMLİ: Mevcut veriyi koru - refresh sırasında boşluk olmasın
        // Yeni veri gelene kadar eski veri görünmeye devam etsin
        let previousCommunities = communities
        let hadData = !communities.isEmpty
        
        defer { 
            isRefreshing = false
        }
        
        // Lazy loading'i durdur - refresh sırasında lazy loading tetiklenmesin
        isLoadingMore = false
        
        // Offset'i sıfırla ama verileri temizleme
        currentOffset = 0
        hasMoreFromAPI = true
        errorMessage = nil
        
        // isLoading'i true yap - lazy loading'i engelle
        isLoading = true
        
        do {
            // API'den yeni verileri çek
            let response = try await APIService.shared.getCommunities(universityId: nil, limit: loadMoreBatchSize, offset: 0)
            
            // Sadece başarılı olursa verileri güncelle
            communities = response.communities
            currentOffset = response.communities.count
            hasMoreFromAPI = response.hasMore
            
            #if DEBUG
            print("✅ refreshCommunities: \(response.communities.count) topluluk yüklendi")
            #endif
            
            isLoading = false
            
            // Verified communities de yenile
            await loadVerifiedCommunities(forceRefresh: true)
            
        } catch {
            #if DEBUG
            print("❌ refreshCommunities hatası: \(error.localizedDescription)")
            #endif
            
            // Cancelled hatalarını ignore et
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            
            if isCancelled {
                // Cancelled hatası - mevcut veriyi koru
                isLoading = false
                return
            }
            
            // Hata olursa mevcut veriyi koru
            if hadData && communities.isEmpty {
                communities = previousCommunities
            }
            
            isLoading = false
            // Refresh hatası kullanıcıya gösterilmez - mevcut veri görünmeye devam eder
        }
    }
    
    /// Background refresh - uygulama arka planda olduğunda çağrılır
    func backgroundRefresh() async {
        // Sadece veri varsa refresh yap (ilk yükleme değil)
        guard hasInitiallyLoaded && !communities.isEmpty else {
            return
        }
        
        // Arka planda sessizce refresh yap (errorMessage gösterme)
        do {
            let universityId = selectedUniversity?.id == "all" ? nil : selectedUniversity?.id
            let response = try await APIService.shared.getCommunities(universityId: universityId, limit: loadMoreBatchSize, offset: 0)
            communities = response.communities
            currentOffset = response.communities.count
            hasMoreFromAPI = response.hasMore
            #if DEBUG
            print("✅ Background refresh: \(response.communities.count) topluluk güncellendi")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Background refresh hatası (gösterilmiyor): \(error.localizedDescription)")
            #endif
            // Arka planda hata oluşursa sessizce ignore et
        }
    }
    
    func selectUniversity(_ university: University?) async {
        // YENİ SİSTEM: Üniversite filtresi kaldırıldı - sadece UI state'i güncelle
        // API'ye university_id parametresi gönderilmiyor, tüm topluluklar gösteriliyor
        selectedUniversity = university
        
        #if DEBUG
        print("🔄 selectUniversity: \(university?.id ?? "nil") - Sadece UI state güncellendi (API filtresi kaldırıldı)")
        #endif
        
        // Üniversite değiştiğinde sadece client-side filtreleme yapılacak
        // API'ye istek gönderilmiyor
    }
    
    func toggleFavorite(_ id: String) async {
        do {
            let isFavorite = try await APIService.shared.toggleFavoriteCommunity(communityId: id)
            if isFavorite {
                favoriteIds.insert(id)
            } else {
                favoriteIds.remove(id)
            }
        } catch {
            #if DEBUG
            print("Favorite toggle hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    /// Üyelik durumlarını yükle (paralel API çağrıları ile)
    func loadMembershipStatuses(forceRefresh: Bool = false) async {
        // Eğer zaten yükleniyorsa, mevcut yükleme tamamlanana kadar bekle
        if isLoadingMembershipStatuses {
            #if DEBUG
            print("⚠️ loadMembershipStatuses: Zaten yükleniyor, bekleniyor...")
            #endif
            // Async wait - polling yerine daha verimli
            let startTime = Date()
            while isLoadingMembershipStatuses && Date().timeIntervalSince(startTime) < 5.0 {
                try? await Task.sleep(nanoseconds: 100_000_000) // 100ms
                // Eğer yükleme tamamlandıysa çık
                if !isLoadingMembershipStatuses && !memberCommunityIds.isEmpty {
                    #if DEBUG
                    print("✅ loadMembershipStatuses: Önceki yükleme tamamlandı, mevcut veri kullanılıyor")
                    #endif
                    return
                }
            }
        }
        
        // Tüm toplulukların ID'lerini topla
        let uniqueCommunityIds = Set(communities.map { $0.id })
        
        guard !uniqueCommunityIds.isEmpty else {
            #if DEBUG
            print("⚠️ loadMembershipStatuses: Topluluk listesi boş")
            #endif
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            memberCommunityIds = []
            isLoadingMembershipStatuses = false
            return
        }
        
        // Cache kontrolü: Eğer zaten yüklenmişse ve cache geçerliyse, tekrar yükleme
        if !forceRefresh {
            let now = Date()
            let cacheValid = lastMembershipLoadTime != nil && 
                           now.timeIntervalSince(lastMembershipLoadTime!) < membershipCacheDuration &&
                           loadedMembershipForCommunityIds == uniqueCommunityIds &&
                           !memberCommunityIds.isEmpty
            
            if cacheValid {
                #if DEBUG
                print("✅ loadMembershipStatuses: Cache geçerli, tekrar yükleme yapılmıyor")
                print("   Cache süresi: \(Int(now.timeIntervalSince(lastMembershipLoadTime!))) saniye önce")
                print("   Yüklenen topluluk sayısı: \(loadedMembershipForCommunityIds.count)")
                print("   Üye olunan topluluk sayısı: \(memberCommunityIds.count)")
                #endif
                return
            }
        }
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoadingMembershipStatuses = true
        
        #if DEBUG
        print("🔄 loadMembershipStatuses: \(uniqueCommunityIds.count) topluluk için üyelik durumu kontrol ediliyor...")
        #endif
        
        var memberIds: Set<String> = []
        var successCount = 0
        var errorCount = 0
        
        // Rate limiting: Her seferinde maksimum 10 topluluk kontrol et (429 hatasını önlemek için)
        let communityIdsArray = Array(uniqueCommunityIds)
        let batchSize = 10
        let batches = stride(from: 0, to: communityIdsArray.count, by: batchSize).map {
            Array(communityIdsArray[$0..<min($0 + batchSize, communityIdsArray.count)])
        }
        
        // Her batch'i sırayla işle (rate limiting için)
        for (batchIndex, batch) in batches.enumerated() {
            // Batch'ler arasında kısa bir bekleme (rate limiting için)
            if batchIndex > 0 {
                try? await Task.sleep(nanoseconds: 500_000_000) // 0.5 saniye
            }
            
            // Her batch içinde paralel işle
            await withTaskGroup(of: (String, Bool, Bool).self) { group in
                for communityId in batch {
                    group.addTask {
                        do {
                            // Timeout ile yükleme (2 saniye max per request - daha hızlı)
                            let status = try await AsyncUtils.withTimeout(seconds: 2) {
                                try await APIService.shared.getMembershipStatus(communityId: communityId)
                            }
                            let isMember = status.isMember || status.status == "member"
                            #if DEBUG
                            if isMember {
                                print("✅ Üyelik bulundu: community_id=\(communityId), isMember=\(status.isMember), status=\(status.status)")
                            }
                            #endif
                            return (communityId, isMember, true)
                        } catch {
                            // 429 hatası veya timeout - sessizce ignore et
                            let errorMsg = error.localizedDescription
                            #if DEBUG
                            if !errorMsg.contains("429") && !errorMsg.contains("zaman aşımı") && !errorMsg.contains("timeout") && !errorMsg.contains("cancelled") {
                                print("❌ Üyelik kontrolü hatası (community_id=\(communityId)): \(errorMsg)")
                            }
                            #endif
                            // Hata durumunda üye değil kabul et
                            return (communityId, false, false)
                        }
                    }
                }
                
                // Batch timeout: 5 saniye içinde tamamlanmalı
                let batchStartTime = Date()
                var batchProcessedCount = 0
                let batchTotalCount = batch.count
                
                for await (communityId, isMember, success) in group {
                    batchProcessedCount += 1
                    
                    // Batch timeout kontrolü - 5 saniye sonra iptal et
                    if Date().timeIntervalSince(batchStartTime) > 5 {
                        #if DEBUG
                        print("⚠️ loadMembershipStatuses: Batch timeout, kalan istekler iptal ediliyor (\(batchProcessedCount)/\(batchTotalCount) tamamlandı)")
                        #endif
                        group.cancelAll()
                        break
                    }
                    
                    if success {
                        successCount += 1
                    } else {
                        errorCount += 1
                    }
                    if isMember {
                        memberIds.insert(communityId)
                    }
                    
                    // Progressive loading: İlk sonuçlar geldiğinde UI'ı güncelle
                    if (batchProcessedCount <= 3 || batchProcessedCount % 5 == 0) && !memberIds.isEmpty {
                        memberCommunityIds = memberIds
                    }
                }
            }
            
            // Her batch sonrası UI'ı güncelle
            memberCommunityIds = memberIds
        }
        
        #if DEBUG
        print("✅ loadMembershipStatuses tamamlandı:")
        print("   Toplam topluluk: \(uniqueCommunityIds.count)")
        print("   Başarılı kontrol: \(successCount)")
        print("   Hatalı kontrol: \(errorCount)")
        print("   Üye olunan topluluk sayısı: \(memberIds.count)")
        print("   Üye olunan topluluk ID'leri: \(Array(memberIds).sorted())")
        #endif
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        memberCommunityIds = memberIds
        isLoadingMembershipStatuses = false
        
        // Cache'i güncelle
        loadedMembershipForCommunityIds = uniqueCommunityIds
        lastMembershipLoadTime = Date()
        
        // Eğer "Yalnızca üyesi olduklarım" filtresi açıksa ama hiç üye yoksa, filtreyi kapat
        if showOnlyMyCommunities && memberIds.isEmpty {
            showOnlyMyCommunities = false
        }
    }

    func verificationInfo(for communityId: String) -> VerifiedCommunityInfo? {
        verifiedCommunityMap[communityId]
    }
    
    func isCommunityVerified(_ community: Community) -> Bool {
        verificationInfo(for: community.id) != nil || community.isVerified
    }
    
    func loadVerifiedCommunities(forceRefresh: Bool) async {
        if isLoadingVerifiedCommunities {
            return
        }
        if hasLoadedVerifiedCommunities && !forceRefresh {
            return
        }
        
        isLoadingVerifiedCommunities = true
        defer { isLoadingVerifiedCommunities = false }
        
        do {
            let verifiedList = try await APIService.shared.getVerifiedCommunities()
            #if DEBUG
            print("✅ \(verifiedList.count) onaylı topluluk yüklendi")
            #endif
            verifiedCommunityMap = Dictionary(uniqueKeysWithValues: verifiedList.map { ($0.communityId, $0) })
            hasLoadedVerifiedCommunities = true
        } catch {
            // Cancelled hatalarını ignore et - refresh sırasında normal
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if isCancelled {
                #if DEBUG
                print("⚠️ Onaylı topluluklar yükleme iptal edildi (normal - refresh sırasında)")
                #endif
                return
            }
            
            #if DEBUG
            print("⚠️ Onaylı topluluklar yüklenemedi: \(error.localizedDescription)")
            #endif
            if forceRefresh {
                hasLoadedVerifiedCommunities = false
            }
        }
    }
}

// MARK: - Events ViewModel
@MainActor
class EventsViewModel: ObservableObject {
    enum LoadingState: Equatable {
        case idle
        case loading
        case loaded
        case error(String)
    }

    @Published var state: LoadingState = .idle
    @Published var errorMessage: String?
    @Published var hasInitiallyLoaded = false
    @Published var displayedEvents: [Event] = []
    @Published var isLoadingMore = false
    @Published var searchText = ""
    @Published var selectedCategory: Event.EventCategory? {
        didSet { applyFilters() }
    }
    @Published var selectedStatus: String? {
        didSet { applyFilters() }
    }
    @Published var sortOption: SortOption = .newest {
        didSet { applyFilters() }
    }
    @Published var showOnlyUpcoming = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyMyCommunities = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyVerifiedEvents = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyToday = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyThisWeek = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyThisMonth = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyFree = false {
        didSet { applyFilters() }
    }
    @Published var showOnlyFeatured = false {
        didSet { applyFilters() }
    }
    
    @Published var memberCommunityIds: Set<String> = [] {
        didSet { applyFilters() }
    }
    @Published var verifiedEventIds: Set<String> = [] {
        didSet { applyFilters() }
    }
    
    // Internal Data Storage
    private var allEvents: [Event] = [] 
    
    // Lazy Loading State
    private let loadMoreBatchSize: Int = 20
    private var currentOffset: Int = 0
    private var hasMoreFromAPI: Bool = true
    private var lastRefreshAt: Date?
    
    // Deprecated properties kept for compatibility if needed (but unused internally)
    // var events: [Event] is removed in favor of displayedEvents

    
    enum SortOption: String, CaseIterable {
        case date = "Tarihe Göre"
        case name = "İsme Göre"
        case category = "Kategoriye Göre"
        case newest = "En Yeni"
    }
    
    var upcomingEvents: [Event] {
        let now = Date()
        return displayedEvents.filter { event in
            event.date >= now
        }
        .sorted { $0.date < $1.date }
        .prefix(5)
        .map { $0 }
    }
    
    // YENİ SİSTEM: Üniversite filtresi için selectedUniversity property'si
    // CommunitiesViewModel'den alınacak (ContentView'den geçirilecek)
    // YENİ SİSTEM: Üniversite filtresi - Client-side filtreleme
    var selectedUniversity: University? = nil {
        didSet {
            // Üniversite değiştiğinde sadece filtreleme yetmez, server'dan da çekmeliyiz
            // Çünkü artık üniversite filtrelemesi server-side yapılıyor.
            if oldValue?.id != selectedUniversity?.id {
                #if DEBUG
                print("🔄 EventsViewModel: University changed to \(selectedUniversity?.name ?? "All"), triggering reload...")
                #endif
                Task {
                    await loadEvents(forceReload: true)
                }
            } else {
                applyFilters()
            }
        }
    }
    
    // Search debounce için
    private var searchCancellable: AnyCancellable?
    
    private func normalizeUniversityName(_ value: String) -> String {
        let trimmed = value.trimmingCharacters(in: .whitespacesAndNewlines)
        let folded = trimmed.folding(options: [.diacriticInsensitive, .caseInsensitive], locale: .current)
        return folded
            .replacingOccurrences(of: " ", with: "")
            .replacingOccurrences(of: "-", with: "")
            .replacingOccurrences(of: "_", with: "")
    }
    
    init() {
        // Search text changes debounce - Enable server-side search
        $searchText
            .dropFirst() // Skip initial value
            .debounce(for: .milliseconds(500), scheduler: RunLoop.main)
            .sink { [weak self] _ in
                Task {
                    await self?.loadEvents(forceReload: true)
                }
            }
            .store(in: &cancellables)
    }
    
    private var cancellables = Set<AnyCancellable>()
    
    func applyFilters() {
        var result = allEvents
        
        #if DEBUG
        print("🔍 EventsViewModel: Filtering started. Total events: \(result.count)")
        print("   Selected University: \(selectedUniversity?.name ?? "None/All")")
        #endif

        // 1. University Filter is now handled server-side. 
        // We trust the server to return events for the selected university.
        // No client-side filtering needed here to avoid normalization mismatches.
        
        // 2. My Communities Filter
        if showOnlyMyCommunities {
            result = result.filter { event in
                memberCommunityIds.contains(event.communityId)
            }
        }
        
        // 3. Verified Filter
        if showOnlyVerifiedEvents {
            result = result.filter { event in
                verifiedEventIds.contains(event.id)
            }
        }
        
        // 4. Upcoming Filter
        if showOnlyUpcoming {
            let now = Date()
            result = result.filter { $0.date >= now }
        }
        
        // 5. Today Filter
        if showOnlyToday {
            let calendar = Calendar.current
            let today = calendar.startOfDay(for: Date())
            let tomorrow = calendar.date(byAdding: .day, value: 1, to: today)!
            result = result.filter { event in
                let eventDate = calendar.startOfDay(for: event.date)
                return eventDate >= today && eventDate < tomorrow
            }
        }

        // 6. This Week Filter
        if showOnlyThisWeek {
            let calendar = Calendar.current
            let now = Date()
            guard let startOfWeek = calendar.date(from: calendar.dateComponents([.yearForWeekOfYear, .weekOfYear], from: now)),
                  let endOfWeek = calendar.date(byAdding: .day, value: 7, to: startOfWeek) else { return }
            
            result = result.filter { event in
                event.date >= startOfWeek && event.date < endOfWeek
            }
        }

        // 7. This Month Filter
        if showOnlyThisMonth {
            let calendar = Calendar.current
            let now = Date()
            guard let startOfMonth = calendar.date(from: calendar.dateComponents([.year, .month], from: now)),
                  let endOfMonth = calendar.date(byAdding: DateComponents(month: 1, day: -1), to: startOfMonth) else { return }
            
            result = result.filter { event in
                event.date >= startOfMonth && event.date <= endOfMonth
            }
        }
        
        // 8. Free Filter
        if showOnlyFree {
            result = result.filter { event in
                (event.price == nil || event.price == 0)
            }
        }
        
        // 9. Search Filter (Local fallback/secondary)
        if !searchText.isEmpty {
            let searchLower = searchText.lowercased()
            result = result.filter { event in
                event.title.lowercased().contains(searchLower) ||
                event.description.lowercased().contains(searchLower) ||
                event.communityName.lowercased().contains(searchLower) ||
                (event.location?.lowercased().contains(searchLower) ?? false) ||
                (event.university?.lowercased().contains(searchLower) ?? false) ||
                event.id.contains(searchLower)
            }
        }
        
        // 10. Category Filter
        if let category = selectedCategory {
            result = result.filter { $0.category == category }
        }
        
        // 11. Status Filter
        if let status = selectedStatus {
            result = result.filter { $0.status == status || ($0.status == nil && status.isEmpty) }
        }
        
        // 12. Sort
        switch sortOption {
        case .date:
            result.sort { $0.date > $1.date }
        case .name:
            result.sort {
                if $0.title != $1.title {
                    return $0.title < $1.title
                }
                return (Int($0.id) ?? 0) > (Int($1.id) ?? 0)
            }
        case .category:
            result.sort {
                if $0.category.rawValue != $1.category.rawValue {
                    return $0.category.rawValue < $1.category.rawValue
                }
                return (Int($0.id) ?? 0) > (Int($1.id) ?? 0)
            }
        case .newest:
            result.sort {
                let d1 = $0.createdAt ?? $0.date
                let d2 = $1.createdAt ?? $1.date
                if d1 != d2 {
                    return d1 > d2
                }
                // Fallback to numeric ID comparison if dates are identical
                let id1 = Int($0.id) ?? 0
                let id2 = Int($1.id) ?? 0
                return id1 > id2
            }
        }
        
        #if DEBUG
        print("✅ EventsViewModel: Filter complete. Result: \(result.count) events")
        #endif
        
        self.displayedEvents = result
    }
    
    // Gösterilecek etkinlikler (lazy loading ile)
    // filteredEvents computed property is removed in favor of displayedEvents state property
    
    func loadEvents(universityId: String? = nil, forceReload: Bool = false) async {
        // Eğer zaten yükleniyorsa ve forceReload değilse çık
        if state == .loading && !forceReload {
             return
        }
        
        if allEvents.isEmpty {
            state = .loading
        }
        
        do {
            // Pagination sıfırla
            currentOffset = 0
            
            // Sort parametresini belirle (Backend sorting için)
            let sortParam = "created_at"
            
            // Hedef üniversite ID'sini belirle
            let targetUniversityId = universityId ?? selectedUniversity?.id
            
            // API isteği
            let loadedEvents = try await APIService.shared.getEvents(
                communityId: nil,
                universityId: targetUniversityId,
                search: searchText.isEmpty ? nil : searchText,
                limit: 200, // İlk yüklemede 200 adet çek (hızlı)
                offset: 0,
                sort: sortParam
            )
            
            // Veriyi kaydet
            self.allEvents = loadedEvents
            self.hasMoreFromAPI = loadedEvents.count >= 200
            
            // Filtreleri uygula
            applyFilters()
            
            errorMessage = nil
            hasInitiallyLoaded = true
            lastRefreshAt = Date()
            state = .loaded
            
        } catch {
            print("❌ EventsViewModel loadEvents Error: \(error.localizedDescription)")
            
            // Eğer task cancelled ise ignore et, aksi halde hata durumuna geç
            if (error as? URLError)?.code == .cancelled || error is CancellationError {
                // Cancelled ise mevcut state kalsın veya idle'a dön
                state = .idle
            } else {
                let message = ErrorHandler.userFriendlyMessage(from: error)
                errorMessage = message
                state = .error(message)
            }
        }
    }

    func findEvent(eventId: String, communityId: String?) -> Event? {
        allEvents.first { event in
            guard event.id == eventId else { return false }
            guard let communityId, !communityId.isEmpty else { return true }
            return event.communityId == communityId
        }
    }
    
    /// Gösterilecek etkinlikleri güncelle (filtreleme sonrası) - Optimize edildi
    // updateDisplayedEvents is removed as logic is now in applyFilters()
    
    /// Lazy loading - Daha fazla etkinlik yükle
    func loadMoreEvents() async {
        guard !isLoadingMore && hasMoreFromAPI else { return }
        
        isLoadingMore = true
        
        do {
            // Sort parametresini belirle
            let sortParam = "created_at"
            let offset = allEvents.count
            
            // API'den yeni batch çek
            let newEvents = try await APIService.shared.getEvents(
                communityId: nil,
                universityId: selectedUniversity?.id,
                search: searchText.isEmpty ? nil : searchText,
                limit: loadMoreBatchSize,
                offset: offset,
                sort: sortParam
            )
            
            if newEvents.isEmpty {
                hasMoreFromAPI = false
            } else {
                // Mevcut listeye ekle
                allEvents.append(contentsOf: newEvents)
                hasMoreFromAPI = newEvents.count >= loadMoreBatchSize
                
                // Filtreleri tekrar uygula (yeni eklenenler de filtrelensin)
                applyFilters()
            }
            
            #if DEBUG
            print("✅ Load More: \(newEvents.count) yeni etkinlik yüklendi (Toplam: \(allEvents.count))")
            #endif
            
        } catch {
            print("❌ Load More Hatası: \(error.localizedDescription)")
        }
        
        isLoadingMore = false
    }

    
    // Üye olduğu topluluk ID'lerini yükle
    func loadMemberCommunityIds(from events: [Event]) async {
        // Unique community ID'leri topla
        let uniqueCommunityIds = Set(events.map { $0.communityId })
        
        guard !uniqueCommunityIds.isEmpty else {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            memberCommunityIds = []
            return
        }
        
        var memberIds: Set<String> = []
        
        // Her topluluk için membership status kontrolü yap (paralel)
        await withTaskGroup(of: (String, Bool).self) { group in
            for communityId in uniqueCommunityIds {
            group.addTask {
                    do {
                        let status = try await APIService.shared.getMembershipStatus(communityId: communityId)
                        return (communityId, status.isMember || status.status == "member")
                    } catch {
                        // Hata durumunda üye değil kabul et
                        return (communityId, false)
                    }
                }
            }
            
            for await (communityId, isMember) in group {
                if isMember {
                    memberIds.insert(communityId)
        }
    }
        }
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        memberCommunityIds = memberIds
        // Eğer "Yalnızca üyesi olduklarım" filtresi açıksa ama hiç üye yoksa, filtreyi kapat
        if showOnlyMyCommunities && memberIds.isEmpty {
            showOnlyMyCommunities = false
        }
    }
    
    
    /// Onaylı etkinlik ID'lerini yükle (filtreleme için)
    func loadVerifiedEventIds(verificationInfoProvider: (String) -> VerifiedCommunityInfo?) async {
        // Tüm etkinliklerden onaylı olanları bul
        var verifiedIds: Set<String> = []
        
        for event in allEvents {
            // Etkinliğin topluluğu onaylıysa, etkinlik de onaylı sayılır
            if let _ = verificationInfoProvider(event.communityId) {
                verifiedIds.insert(event.id)
            }
        }
        
        verifiedEventIds = verifiedIds
        
        #if DEBUG
        print("✅ EventsViewModel: \(verifiedIds.count) onaylı etkinlik ID'si yüklendi (toplam \(allEvents.count) etkinlik)")
        #endif
    }
    
    func refreshEvents(universityId: String? = nil) async {
        // Hedef üniversite ID'sini belirle
        let targetId = universityId ?? selectedUniversity?.id
        
        // Refresh işlemi - force load
        errorMessage = nil
        
        // Force refresh
        // Force refresh - don't clear allEvents here to avoid UI flicker
        // loadEvents will replace allEvents when done
        await loadEvents(universityId: targetId, forceReload: true)
    }
    
    func refreshIfStale(maxAge: TimeInterval = 30) async {
        let now = Date()
        if let lastRefreshAt, now.timeIntervalSince(lastRefreshAt) < maxAge {
            return
        }
        await refreshEvents(universityId: selectedUniversity?.id)
    }
    
    /// Background refresh - uygulama arka planda olduğunda çağrılır
    func backgroundRefresh() async {
        // Sadece veri varsa refresh yap (ilk yükleme değil)
        guard hasInitiallyLoaded && !allEvents.isEmpty else {
            return
        }
        
        // Arka planda sessizce refresh yap (isLoading gösterme)
        do {
            // Sort parametresini belirle
            let sortParam = "created_at"
            
            // Seçili üniversiteye göre çek
            let targetId = selectedUniversity?.id
            
            let loadedEvents = try await APIService.shared.getEvents(
                communityId: nil,
                universityId: targetId,
                search: searchText.isEmpty ? nil : searchText,
                limit: 200,
                offset: 0,
                sort: sortParam
            )
            
            // State'i güncelle
            allEvents = loadedEvents
            hasMoreFromAPI = loadedEvents.count >= 200
            
            // Filtrelemeyi uygula ve listeyi güncelle
            applyFilters()
            lastRefreshAt = Date()
            
            #if DEBUG
            print("✅ Background refresh: \(loadedEvents.count) etkinlik güncellendi")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Background refresh hatası (gösterilmiyor): \(error.localizedDescription)")
            #endif
            // Arka planda hata oluşursa sessizce ignore et
        }
    }
}

// MARK: - Campaigns ViewModel
@MainActor
class CampaignsViewModel: ObservableObject {
    @Published var campaigns: [Campaign] = []
    @Published var isLoading = false // İlk yüklemede false başlat, view'da kontrol edilecek
    @Published var errorMessage: String?
    @Published var hasInitiallyLoaded = false // İlk yükleme tamamlandı mı?
    @Published var searchText = ""
    @Published var savedIds: Set<String> = []
    @Published var selectedCategory: Campaign.CampaignCategory? = nil
    @Published var showOnlyActive = false
    @Published var isLoadingMore = false
    @Published var hasMoreCampaigns = false
    @Published var sortOption: SortOption = .newest
    
    enum SortOption: String, CaseIterable {
        case newest = "En Yeni"
        case active = "Aktif"
        case name = "İsme Göre"
    }
    
    // CommunitiesViewModel referansı - üniversite filtresi için
    weak var communitiesViewModel: CommunitiesViewModel?
    
    var activeCampaigns: [Campaign] {
        let now = Date()
        return campaigns.filter { campaign in
            campaign.endDate >= now && (campaign.isActiveFromAPI ?? true)
        }
    }
    
    var filteredCampaigns: [Campaign] {
        var filtered = campaigns
        
        // Üniversite filtresi - CommunitiesViewModel'deki selectedUniversity'ye göre
        if let communitiesVM = communitiesViewModel,
           let selectedUni = communitiesVM.selectedUniversity,
           selectedUni.id != "all" {
            filtered = filtered.filter { campaign in
                if let campaignUniversity = campaign.university {
                    // Üniversite adını normalize et ve karşılaştır
                    let normalizedCampaign = campaignUniversity.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    let normalizedSelected = selectedUni.name.lowercased().replacingOccurrences(of: " ", with: "").replacingOccurrences(of: "-", with: "").replacingOccurrences(of: "_", with: "")
                    return normalizedCampaign == normalizedSelected
                }
                return false
            }
        }
        
        // Active filter
        if showOnlyActive {
            let now = Date()
            filtered = filtered.filter { campaign in
                campaign.endDate >= now && (campaign.isActiveFromAPI ?? true)
            }
        }
        
        // Category filter
        if let category = selectedCategory {
            filtered = filtered.filter { $0.category == category }
        }
        
        // Search filter
        if !searchText.isEmpty {
            filtered = filtered.filter { campaign in
                campaign.title.localizedCaseInsensitiveContains(searchText) ||
                campaign.description.localizedCaseInsensitiveContains(searchText) ||
                (campaign.shortDescription?.localizedCaseInsensitiveContains(searchText) ?? false)
            }
        }
        
        // Sort
        switch sortOption {
        case .newest:
            filtered.sort { $0.id > $1.id }
        case .active:
            let now = Date()
            filtered.sort { (c1, c2) -> Bool in
                let active1 = c1.endDate >= now && (c1.isActiveFromAPI ?? true)
                let active2 = c2.endDate >= now && (c2.isActiveFromAPI ?? true)
                if active1 != active2 { return active1 }
                return c1.id > c2.id
            }
        case .name:
            filtered.sort { $0.title < $1.title }
        }
        
        return filtered
    }
    
    func isSaved(_ id: String) -> Bool {
        savedIds.contains(id)
    }
    
    func loadCampaigns(universityId: String? = nil) async {
        guard !isLoading else { 
            #if DEBUG
            print("⚠️ CampaignsViewModel.loadCampaigns zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        #if DEBUG
        print("🔄 CampaignsViewModel: Kampanyalar yükleniyor... (üniversite filtresi kaldırıldı)")
        #endif
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoading = true
        errorMessage = nil
        
        do {
            // Timeout ile yükleme (15 saniye - daha kısa)
            // Üniversite filtresi kaldırıldı - her zaman nil gönder
            let loadedCampaigns = try await AsyncUtils.withTimeout(seconds: 15) {
                try await APIService.shared.getCampaigns(
                    communityId: nil,
                    universityId: nil
                )
            }
            #if DEBUG
            print("✅ CampaignsViewModel: \(loadedCampaigns.count) kampanya yüklendi")
            #endif
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            // Duplicate ID'leri kaldır - aynı ID'ye sahip campaign'lerden sadece birini tut
            var uniqueCampaigns: [Campaign] = []
            var seenIds: Set<String> = []
            for campaign in loadedCampaigns {
                if !seenIds.contains(campaign.id) {
                    uniqueCampaigns.append(campaign)
                    seenIds.insert(campaign.id)
                }
            }
            campaigns = uniqueCampaigns
            #if DEBUG
            if loadedCampaigns.count != uniqueCampaigns.count {
                print("⚠️ CampaignsViewModel: \(loadedCampaigns.count - uniqueCampaigns.count) duplicate kampanya kaldırıldı")
            }
            #endif
            hasInitiallyLoaded = true
            isLoading = false // Başarılı durumda false yap
        } catch {
            #if DEBUG
            print("❌ CampaignsViewModel yükleme hatası: \(error.localizedDescription)")
            #endif
            // Cancelled hatalarını ignore et
            if let urlError = error as? URLError, urlError.code == .cancelled {
                isLoading = false
                return
            }
            // Kullanıcı dostu hata mesajı
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            
            // İlk yüklemede hata varsa tekrar denemek için hasInitiallyLoaded = false bırak
            if !hasInitiallyLoaded {
                errorMessage = nil
            }
            campaigns = []
            isLoading = false // Hata durumunda da kesinlikle false yap
        }
        
        // Ekstra güvence: Eğer hala true ise false yap
        if isLoading {
            #if DEBUG
            print("⚠️ CampaignsViewModel: isLoading hala true, zorla false yapılıyor")
            #endif
            isLoading = false
        }
    }
    
    func refreshCampaigns(universityId: String? = nil) async {
        // YENİ SİSTEM: Üniversite filtresi kaldırıldı - universityId parametresi artık kullanılmıyor
        // Refresh sırasında hasInitiallyLoaded'i false yapma - bu "kampanya bulunamadı" mesajına neden olur
        // ÖNEMLİ: loadCampaigns zaten campaigns'i güncelliyor, bu yüzden verileri temizlemeye gerek yok
        
        // State'i resetle
        errorMessage = nil
        
        // Verileri yeniden yükle
        await loadCampaigns(universityId: nil)
    }
    
    /// Background refresh - uygulama arka planda olduğunda çağrılır
    func backgroundRefresh() async {
        // Sadece veri varsa refresh yap (ilk yükleme değil)
        guard hasInitiallyLoaded && !campaigns.isEmpty else {
            return
        }
        
        // Arka planda sessizce refresh yap (errorMessage gösterme)
        do {
            // Üniversite filtresi kaldırıldı - her zaman nil gönder
            let loadedCampaigns = try await APIService.shared.getCampaigns(
                communityId: nil,
                universityId: nil
            )
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            // Duplicate ID'leri kaldır
            var uniqueCampaigns: [Campaign] = []
            var seenIds: Set<String> = []
            for campaign in loadedCampaigns {
                if !seenIds.contains(campaign.id) {
                    uniqueCampaigns.append(campaign)
                    seenIds.insert(campaign.id)
                }
            }
            campaigns = uniqueCampaigns
            #if DEBUG
            print("✅ Background refresh: \(uniqueCampaigns.count) kampanya güncellendi")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Background refresh hatası (gösterilmiyor): \(error.localizedDescription)")
            #endif
            // Arka planda hata oluşursa sessizce ignore et
        }
    }
    
    func toggleSave(_ id: String) async {
        do {
            // Campaign'den communityId'yi bul
            guard let campaign = campaigns.first(where: { $0.id == id }) else {
                #if DEBUG
                print("⚠️ Campaign bulunamadı: \(id)")
                #endif
                return
            }
            
            let isSaved = try await APIService.shared.toggleSaveCampaign(
                campaignId: id,
                communityId: campaign.communityId
            )
            if isSaved {
                savedIds.insert(id)
            } else {
                savedIds.remove(id)
            }
        } catch {
            #if DEBUG
            print("Save toggle hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    func loadMoreCampaigns() async {
        guard !isLoadingMore && hasMoreCampaigns else { return }
        
        isLoadingMore = true
        defer { isLoadingMore = false }
        
        // Şimdilik tüm kampanyalar zaten yüklendiği için daha fazla yükleme yok
        // Gelecekte pagination desteği eklendiğinde buraya eklenebilir
        hasMoreCampaigns = false
    }
}

// MARK: - Notifications ViewModel
@MainActor
class NotificationsViewModel: ObservableObject {
    @Published var notifications: [AppNotification] = []
    @Published var isLoading = false
    @Published var errorMessage: String?
    
    var unreadCount: Int {
        notifications.filter { !$0.isRead }.count
    }
    
    var unreadNotifications: [AppNotification] {
        notifications.filter { !$0.isRead }
    }
    
    func loadNotifications() async {
        isLoading = true
        errorMessage = nil
        
        do {
            notifications = try await APIService.shared.getNotifications()
        } catch {
            errorMessage = "Bildirimler yüklenemedi: \(error.localizedDescription)"
            notifications = []
        }
        
        isLoading = false
    }
    
    /// Background refresh - uygulama arka planda olduğunda çağrılır
    func backgroundRefresh() async {
        // Sadece veri varsa refresh yap
        guard !notifications.isEmpty else {
            return
        }
        
        // Arka planda sessizce refresh yap (errorMessage gösterme)
        do {
            let loadedNotifications = try await APIService.shared.getNotifications()
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            notifications = loadedNotifications
            #if DEBUG
            print("✅ Background refresh: \(loadedNotifications.count) bildirim güncellendi")
            #endif
        } catch {
            #if DEBUG
            print("⚠️ Background refresh hatası (gösterilmiyor): \(error.localizedDescription)")
            #endif
            // Arka planda hata oluşursa sessizce ignore et
        }
    }
    
    func markAsRead(_ id: String) async {
        do {
            _ = try await APIService.shared.markNotificationAsRead(id: id)
            if let index = notifications.firstIndex(where: { $0.id == id }) {
                notifications[index].isRead = true
            }
        } catch {
            #if DEBUG
            print("Mark as read hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    func markAllAsRead() async {
        do {
            _ = try await APIService.shared.markAllNotificationsAsRead()
            for index in notifications.indices {
                notifications[index].isRead = true
            }
        } catch {
            #if DEBUG
            print("Mark all as read hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    func deleteNotification(_ id: String) async {
        do {
            _ = try await APIService.shared.deleteNotification(id: id)
            notifications.removeAll { $0.id == id }
        } catch {
            #if DEBUG
            print("Delete notification hatası: \(error.localizedDescription)")
            #endif
        }
    }
}

// MARK: - Profile ViewModel
@MainActor
class ProfileViewModel: ObservableObject {
    @Published var user: User?
    @Published var isLoading = false
    @Published var errorMessage: String?
    @Published var isEditing = false
    @Published var universities: [University] = []
    @Published var isLoadingUniversities = false
    
    func loadUser() async {
        guard !isLoading else {
            #if DEBUG
            print("⚠️ ProfileViewModel.loadUser zaten yükleniyor, atlanıyor")
            #endif
            return
        }
        
        #if DEBUG
        print("🔄 ProfileViewModel: Kullanıcı bilgileri yükleniyor...")
        #endif
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoading = true
        errorMessage = nil
        
        do {
            let loadedUser = try await APIService.shared.getCurrentUser()
            #if DEBUG
            print("✅ ProfileViewModel: Kullanıcı bilgileri yüklendi: \(loadedUser.displayName)")
            #endif
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            user = loadedUser
            isLoading = false
        } catch {
            #if DEBUG
            print("❌ ProfileViewModel yükleme hatası: \(error.localizedDescription)")
            #endif
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            isLoading = false
            // Hata durumunda user'ı nil bırak (tekrar deneme için)
        }
        
        // Ekstra güvence: Eğer hala true ise false yap
        if isLoading {
            #if DEBUG
            print("⚠️ ProfileViewModel: isLoading hala true, zorla false yapılıyor")
            #endif
            isLoading = false
        }
    }
    
    func updateProfile(name: String, email: String, phone: String?) async {
        guard var user = user else { return }
        
        // displayName computed property olduğu için firstName ve lastName'i güncelle
        let nameParts = name.split(separator: " ", maxSplits: 1, omittingEmptySubsequences: true)
        if nameParts.count >= 2 {
            user.firstName = String(nameParts[0])
            user.lastName = String(nameParts[1])
        } else if nameParts.count == 1 {
            user.firstName = String(nameParts[0])
            user.lastName = ""
        }
        user.email = email
        user.phoneNumber = phone
        
        isLoading = true
        errorMessage = nil
        
        do {
            let updatedUser = try await APIService.shared.updateUserProfile(user)
            self.user = updatedUser
        } catch {
            errorMessage = error.localizedDescription
        }
        
        isLoading = false
    }
    
    func saveProfile() async {
        guard let user = user else { return }
        
        // Telefon numarasını formatla (API'ye göndermeden önce)
        var formattedPhone: String? = user.phoneNumber
        if let phone = user.phoneNumber, !phone.isEmpty {
            // InputValidator ile formatla (10 haneli, 5 ile başlayan format döner)
            if let formatted = InputValidator.formatPhoneNumber(phone) {
                // API 10 haneli format bekliyor (5 ile başlayan)
                formattedPhone = formatted
            } else {
                // Formatlanamazsa, sadece rakamları al ve kontrol et
                let digits = phone.replacingOccurrences(of: "[^0-9]", with: "", options: .regularExpression)
                if digits.count == 11 && digits.hasPrefix("0") {
                    // 0 ile başlayan 11 haneli numaradan 0'ı kaldır
                    formattedPhone = String(digits.dropFirst())
                } else if digits.count == 10 && digits.hasPrefix("5") {
                    formattedPhone = digits
                } else {
                    // Geçersiz format, nil yap (API hata döndürecek)
                    formattedPhone = nil
                }
            }
        }
        
        await updateProfile(
            name: "\(user.firstName) \(user.lastName)".trimmingCharacters(in: .whitespaces),
            email: user.email,
            phone: formattedPhone
        )
        
        isEditing = false
    }
    
    func updateNotificationSettings(_ settings: User.NotificationSettings) async {
        guard var user = user else { return }
        
        user.notificationSettings = settings
        
        isLoading = true
        errorMessage = nil
        
        do {
            // Gerçek API çağrısı
            let success = try await APIService.shared.updateNotificationSettings(user.notificationSettings)
            if !success {
                errorMessage = "Bildirim ayarları güncellenemedi"
            } else {
                self.user = user
            }
        } catch {
            errorMessage = error.localizedDescription
        }
        
        isLoading = false
    }
    
    func updateNotificationSettings() async {
        guard let user = user else { return }
        await updateNotificationSettings(user.notificationSettings)
    }
    
    func loadUniversities() async {
        guard !isLoadingUniversities else { return }
        isLoadingUniversities = true
        
        do {
            let loadedUniversities = try await APIService.shared.getUniversities()
            // "Tümü" seçeneğini kaldır (profil düzenleme için gerekli değil)
            universities = loadedUniversities.filter { $0.id != "all" }
        } catch {
            #if DEBUG
            print("❌ Üniversiteler yüklenemedi: \(error.localizedDescription)")
            #endif
        }
        
        isLoadingUniversities = false
    }
}

// MARK: - Community Detail ViewModel
@MainActor
class CommunityDetailViewModel: ObservableObject {
    let communityId: String
    
    @Published var events: [Event] = []
    @Published var campaigns: [Campaign] = []
    @Published var members: [Member] = []
    @Published var boardMembers: [BoardMember] = []
    @Published var products: [Product] = []
    
    @Published var isLoadingEvents = false
    @Published var isLoadingCampaigns = false
    @Published var isLoadingMembers = false
    @Published var isLoadingBoard = false
    @Published var isLoadingProducts = false
    
    // Lazy loading için
    @Published var isLoadingMoreEvents = false
    @Published var isLoadingMoreCampaigns = false
    @Published var isLoadingMoreProducts = false
    @Published var isLoadingMoreMembers = false
    
    // Lazy loading için tüm veriler (artık API'den pagination ile çekiliyor)
    private var allEvents: [Event] = []
    private var allCampaigns: [Campaign] = []
    private var allProducts: [Product] = []
    private var allMembers: [Member] = []
    private var allBoardMembers: [BoardMember] = []
    
    // Lazy loading için sayacılar
    private var displayedEventsCount: Int = 0
    private var displayedCampaignsCount: Int = 0
    private var displayedProductsCount: Int = 0
    private var displayedMembersCount: Int = 0
    private var displayedBoardMembersCount: Int = 0
    
    // Pagination için
    private var eventsOffset: Int = 0
    private var campaignsOffset: Int = 0
    private var productsOffset: Int = 0
    private var membersOffset: Int = 0
    private var boardMembersOffset: Int = 0
    
    private let loadMoreBatchSize: Int = 20
    private var hasMoreEventsFromAPI: Bool = true
    private var hasMoreCampaignsFromAPI: Bool = true
    private var hasMoreProductsFromAPI: Bool = true
    private var hasMoreMembersFromAPI: Bool = true
    
    @Published var eventsError: String?
    @Published var campaignsError: String?
    @Published var membersError: String?
    @Published var boardError: String?
    @Published var productsError: String?
    
    // Arama için
    @Published var eventsSearchText = ""
    @Published var campaignsSearchText = ""
    @Published var productsSearchText = ""
    @Published var membersSearchText = ""
    @Published var boardSearchText = ""
    
    @Published var hasLoadedEvents = false
    @Published var hasLoadedCampaigns = false
    @Published var hasLoadedMembers = false
    @Published var hasLoadedBoard = false
    @Published var hasLoadedProducts = false
    
    init(communityId: String) {
        self.communityId = communityId
    }
    
    func loadAllData() async {
        // Paralel yükleme yerine sıralı yükleme - daha güvenilir
        await loadEvents()
        await loadCampaigns()
        await loadMembers()
        await loadBoardMembers()
    }
    
    func loadEvents() async {
        guard !isLoadingEvents else { 
            #if DEBUG
            print("⚠️ loadEvents zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoadingEvents = true
        eventsError = nil
        eventsOffset = 0
        allEvents = []
        displayedEventsCount = 0
        
        #if DEBUG
        print("🔄 Etkinlikler yükleniyor... (communityId: \(communityId), offset: \(eventsOffset))")
        #endif
        
        // İlk yüklemede otomatik retry mekanizması
        let isFirstLoad = !hasLoadedEvents
        let maxRetries = isFirstLoad ? 3 : 0 // İlk yüklemede 3 kez dene
        var retryCount = 0
        
        while retryCount <= maxRetries {
            do {
                // İlk batch'i çek (20 etkinlik)
                let loadedEvents = try await APIService.shared.getEvents(communityId: communityId, limit: loadMoreBatchSize, offset: eventsOffset)
                #if DEBUG
                print("✅ \(loadedEvents.count) etkinlik yüklendi (offset: \(eventsOffset))")
                #endif
                // İlk batch'i sakla ve göster
                allEvents = loadedEvents
                displayedEventsCount = loadedEvents.count
                events = loadedEvents
                eventsOffset = loadedEvents.count
                hasMoreEventsFromAPI = loadedEvents.count >= loadMoreBatchSize
                hasLoadedEvents = true
                isLoadingEvents = false
                return // Başarılı, çık
            } catch {
                #if DEBUG
                print("❌ Etkinlik yükleme hatası (deneme \(retryCount + 1)/\(maxRetries + 1)): \(error.localizedDescription)")
                #endif
                
                // Cancelled hatalarını ve timeout hatalarını ignore et
                if let urlError = error as? URLError, urlError.code == .cancelled {
                    isLoadingEvents = false
                    return
                }
                if error is CancellationError {
                    isLoadingEvents = false
                    return
                }
                // AsyncUtils.TimeoutError kontrolü
                if String(describing: type(of: error)).contains("TimeoutError") {
                    isLoadingEvents = false
                    return
                }
                
                // Retry yapılacak mı kontrol et
                if retryCount < maxRetries {
                    // Exponential backoff: 1s, 2s, 4s
                    let delay = pow(2.0, Double(retryCount))
                    #if DEBUG
                    print("⏳ \(delay) saniye bekleyip tekrar denenecek...")
                    #endif
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    retryCount += 1
                    continue // Tekrar dene
                } else {
                    // Tüm denemeler başarısız, hata göster
                    #if DEBUG
                    print("❌ Tüm denemeler başarısız, hata gösteriliyor")
                    #endif
                    eventsError = "Etkinlikler yüklenemedi: \(error.localizedDescription)"
                    events = []
                    hasLoadedEvents = true
                    isLoadingEvents = false
                    return
                }
            }
        }
    }
    
    func loadCampaigns() async {
        guard !isLoadingCampaigns else { 
            #if DEBUG
            print("⚠️ loadCampaigns zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoadingCampaigns = true
        campaignsError = nil
        
        #if DEBUG
        print("🔄 Kampanyalar yükleniyor... (communityId: \(communityId))")
        #endif
        
        // İlk yüklemede otomatik retry mekanizması
        let isFirstLoad = !hasLoadedCampaigns
        let maxRetries = isFirstLoad ? 3 : 0
        var retryCount = 0
        
        while retryCount <= maxRetries {
            do {
                let loadedCampaigns = try await APIService.shared.getCampaigns(communityId: communityId)
                #if DEBUG
                print("✅ \(loadedCampaigns.count) kampanya yüklendi")
                #endif
                // Tüm kampanyaları sakla (lazy loading için)
                allCampaigns = loadedCampaigns
                displayedCampaignsCount = min(loadMoreBatchSize, loadedCampaigns.count)
                // İlk batch'i göster
                campaigns = Array(loadedCampaigns.prefix(displayedCampaignsCount))
                hasLoadedCampaigns = true
                isLoadingCampaigns = false
                return
            } catch {
                #if DEBUG
                print("❌ Kampanya yükleme hatası (deneme \(retryCount + 1)/\(maxRetries + 1)): \(error.localizedDescription)")
                #endif
                // Cancelled hatalarını ignore et
                if let urlError = error as? URLError, urlError.code == .cancelled {
                    isLoadingCampaigns = false
                    return
                }
                
                if retryCount < maxRetries {
                    let delay = pow(2.0, Double(retryCount))
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    retryCount += 1
                    continue
                } else {
                    campaignsError = "Kampanyalar yüklenemedi: \(error.localizedDescription)"
                    campaigns = []
                    hasLoadedCampaigns = true
                    isLoadingCampaigns = false
                    return
                }
            }
        }
    }
    
    func loadMembers() async {
        guard !isLoadingMembers else { return }
        isLoadingMembers = true
        membersError = nil
        
        // İlk yüklemede otomatik retry mekanizması
        let isFirstLoad = !hasLoadedMembers
        let maxRetries = isFirstLoad ? 3 : 0
        var retryCount = 0
        
        while retryCount <= maxRetries {
            do {
                let loadedMembers = try await APIService.shared.getMembers(communityId: communityId)
                // Tüm üyeleri sakla (lazy loading için)
                allMembers = loadedMembers
                displayedMembersCount = min(50, loadedMembers.count)
                // İlk batch'i göster
                members = Array(loadedMembers.prefix(displayedMembersCount))
                hasLoadedMembers = true
                isLoadingMembers = false
                return
            } catch {
                // Cancelled hatalarını ignore et
                if let urlError = error as? URLError, urlError.code == .cancelled {
                    isLoadingMembers = false
                    return
                }
                
                if retryCount < maxRetries {
                    let delay = pow(2.0, Double(retryCount))
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    retryCount += 1
                    continue
                } else {
                    // DecodingError'ları daha anlaşılır hale getir
                    var errorMessage = "Üyeler yüklenemedi"
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            errorMessage = "Veri çözümleme hatası: Beklenmeyen veri tipi (\(type)) - \(context.debugDescription)"
                        case .valueNotFound(let type, let context):
                            errorMessage = "Veri çözümleme hatası: Değer bulunamadı (\(type)) - \(context.debugDescription)"
                        case .keyNotFound(let key, let context):
                            errorMessage = "Veri çözümleme hatası: Anahtar bulunamadı (\(key.stringValue)) - \(context.debugDescription)"
                        case .dataCorrupted(let context):
                            errorMessage = "Veri çözümleme hatası: Bozuk veri - \(context.debugDescription)"
                        @unknown default:
                            errorMessage = "Veri çözümleme hatası: \(error.localizedDescription)"
                        }
                    } else {
                        errorMessage = "Üyeler yüklenemedi: \(error.localizedDescription)"
                    }
                    
                    membersError = errorMessage
                    members = []
                    hasLoadedMembers = true
                    isLoadingMembers = false
                    return
                }
            }
        }
    }
    
    func loadBoardMembers() async {
        guard !isLoadingBoard else { return }
        isLoadingBoard = true
        boardError = nil
        
        // İlk yüklemede otomatik retry mekanizması
        let isFirstLoad = !hasLoadedBoard
        let maxRetries = isFirstLoad ? 3 : 0
        var retryCount = 0
        
        while retryCount <= maxRetries {
            do {
                let loadedBoard = try await APIService.shared.getBoardMembers(communityId: communityId)
                // Tüm yönetim kurulu üyelerini sakla (lazy loading için)
                allBoardMembers = loadedBoard
                displayedBoardMembersCount = min(loadMoreBatchSize, loadedBoard.count)
                // İlk batch'i göster
                boardMembers = Array(loadedBoard.prefix(displayedBoardMembersCount))
                hasLoadedBoard = true
                isLoadingBoard = false
                return
            } catch {
                // Cancelled hatalarını ignore et
                if let urlError = error as? URLError, urlError.code == .cancelled {
                    isLoadingBoard = false
                    return
                }
                
                if retryCount < maxRetries {
                    let delay = pow(2.0, Double(retryCount))
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    retryCount += 1
                    continue
                } else {
                    // DecodingError'ları daha anlaşılır hale getir
                    var errorMessage = "Yönetim kurulu yüklenemedi"
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            errorMessage = "Veri çözümleme hatası: Beklenmeyen veri tipi (\(type)) - \(context.debugDescription)"
                        case .valueNotFound(let type, let context):
                            errorMessage = "Veri çözümleme hatası: Değer bulunamadı (\(type)) - \(context.debugDescription)"
                        case .keyNotFound(let key, let context):
                            errorMessage = "Veri çözümleme hatası: Anahtar bulunamadı (\(key.stringValue)) - \(context.debugDescription)"
                        case .dataCorrupted(let context):
                            errorMessage = "Veri çözümleme hatası: Bozuk veri - \(context.debugDescription)"
                        @unknown default:
                            errorMessage = "Veri çözümleme hatası: \(error.localizedDescription)"
                        }
                    } else {
                        errorMessage = "Yönetim kurulu yüklenemedi: \(error.localizedDescription)"
                    }
                    
                    boardError = errorMessage
                    boardMembers = []
                    hasLoadedBoard = true
                    isLoadingBoard = false
                    return
                }
            }
        }
    }
    
    func refreshEvents() async {
        hasLoadedEvents = false
        allEvents = []
        displayedEventsCount = 0
        eventsOffset = 0
        hasMoreEventsFromAPI = true
        await loadEvents()
    }
    
    func loadMoreEvents() async {
        guard !isLoadingMoreEvents else { 
            #if DEBUG
            print("⚠️ loadMoreEvents zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        // Önce local'de daha fazla gösterilmemiş etkinlik var mı kontrol et
        if displayedEventsCount < allEvents.count {
            // Local'de daha fazla etkinlik var, göster
            isLoadingMoreEvents = true
            
            // Yeni batch'i ekle (gecikme kaldırıldı - performans optimizasyonu)
            let nextBatch = min(displayedEventsCount + loadMoreBatchSize, allEvents.count)
            displayedEventsCount = nextBatch
            events = Array(allEvents.prefix(displayedEventsCount))
            
            isLoadingMoreEvents = false
            
            #if DEBUG
            print("📄 Lazy loading (local): \(displayedEventsCount)/\(allEvents.count) etkinlik gösteriliyor")
            #endif
            return
        }
        
        // Local'de daha fazla etkinlik yok, API'den çek
        guard hasMoreEventsFromAPI else {
            // API'de de daha fazla etkinlik yok
            isLoadingMoreEvents = false
            #if DEBUG
            print("📄 Lazy loading: Daha fazla etkinlik yok (local: \(allEvents.count), displayed: \(displayedEventsCount))")
            #endif
            return
        }
        
        isLoadingMoreEvents = true
        defer { isLoadingMoreEvents = false } // Güvenli state management - hata durumunda da false yap
        
        #if DEBUG
        print("📄 Daha fazla etkinlik yükleniyor... (offset: \(eventsOffset))")
        #endif
        
        do {
            // API'den yeni batch çek
            let loadedEvents = try await APIService.shared.getEvents(communityId: communityId, limit: loadMoreBatchSize, offset: eventsOffset)
            
            if loadedEvents.isEmpty {
                // Daha fazla etkinlik yok
                hasMoreEventsFromAPI = false
                #if DEBUG
                print("📄 Lazy loading: API'de daha fazla etkinlik yok")
                #endif
                return
            }
            
            // Yeni batch'i ekle
            allEvents.append(contentsOf: loadedEvents)
            eventsOffset += loadedEvents.count
            hasMoreEventsFromAPI = loadedEvents.count >= loadMoreBatchSize
            
            // Gösterilecek sayıyı artır
            let nextBatch = min(displayedEventsCount + loadMoreBatchSize, allEvents.count)
            displayedEventsCount = nextBatch
            events = Array(allEvents.prefix(displayedEventsCount))
            
            #if DEBUG
            print("✅ \(loadedEvents.count) yeni etkinlik yüklendi. Toplam: \(allEvents.count), Gösterilen: \(displayedEventsCount)")
            #endif
        } catch {
            #if DEBUG
            print("❌ Daha fazla etkinlik yüklenemedi: \(error.localizedDescription)")
            #endif
            
            // Cancelled hatalarını ignore et
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if !isCancelled {
                // Diğer hatalar için hasMoreEventsFromAPI'yi false yap (sonsuz retry önle)
                hasMoreEventsFromAPI = false
            }
            // isLoadingMoreEvents defer ile false yapılacak
        }
    }
    
    var hasMoreEvents: Bool {
        // Hem local'de daha fazla etkinlik var mı hem de API'den daha fazla çekilebilir mi kontrol et
        displayedEventsCount < allEvents.count || hasMoreEventsFromAPI
    }
    
    func refreshCampaigns() async {
        hasLoadedCampaigns = false
        allCampaigns = []
        displayedCampaignsCount = 20
        await loadCampaigns()
    }
    
    func refreshMembers() async {
        hasLoadedMembers = false
        allMembers = []
        displayedMembersCount = 50
        await loadMembers()
    }
    
    func leaveCommunity() async throws {
        #if DEBUG
        print("🚪 Topluluktan ayrılıyor: \(communityId)")
        #endif
        
        try await APIService.shared.leaveCommunity(communityId: communityId)
        
        // Üye listesini yenile
        await refreshMembers()
        
        #if DEBUG
        print("✅ Topluluktan başarıyla ayrıldı: \(communityId)")
        #endif
    }
    
    func refreshBoard() async {
        hasLoadedBoard = false
        allBoardMembers = []
        displayedBoardMembersCount = 20
        await loadBoardMembers()
    }
    
    func loadMoreBoardMembers() async {
        guard displayedBoardMembersCount < allBoardMembers.count else { return }
        
        let nextBatch = min(displayedBoardMembersCount + loadMoreBatchSize, allBoardMembers.count)
        displayedBoardMembersCount = nextBatch
        boardMembers = Array(allBoardMembers.prefix(displayedBoardMembersCount))
        
        #if DEBUG
        print("📄 Lazy loading: \(displayedBoardMembersCount)/\(allBoardMembers.count) yönetim kurulu üyesi gösteriliyor")
        #endif
    }
    
    var hasMoreBoardMembers: Bool {
        displayedBoardMembersCount < allBoardMembers.count
    }
    
    func loadProducts() async {
        guard !isLoadingProducts else { 
            #if DEBUG
            print("⚠️ loadProducts zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        // @MainActor ile işaretlendiği için MainActor.run gereksiz
        isLoadingProducts = true
        productsError = nil
        productsOffset = 0
        allProducts = []
        displayedProductsCount = 0
        
        #if DEBUG
        print("🔄 Ürünler yükleniyor... (communityId: \(communityId), offset: \(productsOffset))")
        #endif
        
        // İlk yüklemede otomatik retry mekanizması
        let isFirstLoad = !hasLoadedProducts
        let maxRetries = isFirstLoad ? 3 : 0
        var retryCount = 0
        
        while retryCount <= maxRetries {
            do {
                // İlk batch'i çek (20 ürün)
                let loadedProducts = try await APIService.shared.getProducts(communityId: communityId, limit: loadMoreBatchSize, offset: productsOffset)
                #if DEBUG
                print("✅ \(loadedProducts.count) ürün yüklendi (offset: \(productsOffset))")
                #endif
                // İlk batch'i sakla ve göster
                allProducts = loadedProducts
                displayedProductsCount = loadedProducts.count
                products = loadedProducts
                productsOffset = loadedProducts.count
                hasMoreProductsFromAPI = loadedProducts.count >= loadMoreBatchSize
                hasLoadedProducts = true
                isLoadingProducts = false
                return
            } catch {
                #if DEBUG
                print("❌ Ürün yükleme hatası (deneme \(retryCount + 1)/\(maxRetries + 1)): \(error.localizedDescription)")
                #endif
                // Cancelled hatalarını ve timeout hatalarını ignore et
                if let urlError = error as? URLError, urlError.code == .cancelled {
                    isLoadingProducts = false
                    return
                }
                if error is CancellationError {
                    isLoadingProducts = false
                    return
                }
                // AsyncUtils.TimeoutError kontrolü
                if String(describing: type(of: error)).contains("TimeoutError") {
                    isLoadingProducts = false
                    return
                }
                
                if retryCount < maxRetries {
                    let delay = pow(2.0, Double(retryCount))
                    try? await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
                    retryCount += 1
                    continue
                } else {
                    productsError = "Ürünler yüklenemedi: \(error.localizedDescription)"
                    products = []
                    hasLoadedProducts = true
                    isLoadingProducts = false
                    return
                }
            }
        }
    }
    
    func refreshProducts() async {
        // Refresh sırasında hasLoadedProducts'i false yapma - bu "ürün bulunamadı" mesajına neden olur
        // Sadece verileri temizle ve yeniden yükle
        allProducts = []
        displayedProductsCount = 0
        productsOffset = 0
        hasMoreProductsFromAPI = true
        productsError = nil
        isLoadingProducts = false
        
        // Verileri yeniden yükle
        await loadProducts()
    }
    
    func loadMoreCampaigns() async {
        guard !isLoadingMoreCampaigns && displayedCampaignsCount < allCampaigns.count else { return }
        isLoadingMoreCampaigns = true
        
        // Gereksiz delay kaldırıldı - direkt yükleme daha hızlı
        let nextBatch = min(displayedCampaignsCount + loadMoreBatchSize, allCampaigns.count)
        displayedCampaignsCount = nextBatch
        campaigns = Array(allCampaigns.prefix(displayedCampaignsCount))
        
        isLoadingMoreCampaigns = false
        
        #if DEBUG
        print("📄 Lazy loading: \(displayedCampaignsCount)/\(allCampaigns.count) kampanya gösteriliyor")
        #endif
    }
    
    func loadMoreProducts() async {
        guard !isLoadingMoreProducts else { 
            #if DEBUG
            print("⚠️ loadMoreProducts zaten yükleniyor, atlanıyor")
            #endif
            return 
        }
        
        // Önce local'de daha fazla gösterilmemiş ürün var mı kontrol et
        if displayedProductsCount < allProducts.count {
            // Local'de daha fazla ürün var, göster
            isLoadingMoreProducts = true
            
            // Yumuşak yükleme için kısa bir gecikme (kastırmadan yükleme)
            try? await Task.sleep(nanoseconds: 300_000_000) // 0.3 saniye
            
            // Yeni batch'i ekle
            let nextBatch = min(displayedProductsCount + loadMoreBatchSize, allProducts.count)
            displayedProductsCount = nextBatch
            products = Array(allProducts.prefix(displayedProductsCount))
            
            isLoadingMoreProducts = false
            
            #if DEBUG
            print("📄 Lazy loading (local): \(displayedProductsCount)/\(allProducts.count) ürün gösteriliyor")
            #endif
            return
        }
        
        // Local'de daha fazla ürün yok, API'den çek
        guard hasMoreProductsFromAPI else {
            // API'de de daha fazla ürün yok
            isLoadingMoreProducts = false
            #if DEBUG
            print("📄 Lazy loading: Daha fazla ürün yok (local: \(allProducts.count), displayed: \(displayedProductsCount))")
            #endif
            return
        }
        
        isLoadingMoreProducts = true
        defer { isLoadingMoreProducts = false } // Güvenli state management - hata durumunda da false yap
        
        #if DEBUG
        print("📄 Daha fazla ürün yükleniyor... (offset: \(productsOffset))")
        #endif
        
        do {
            // API'den yeni batch çek
            let loadedProducts = try await APIService.shared.getProducts(communityId: communityId, limit: loadMoreBatchSize, offset: productsOffset)
            
            if loadedProducts.isEmpty {
                // Daha fazla ürün yok
                hasMoreProductsFromAPI = false
                #if DEBUG
                print("📄 Lazy loading: API'de daha fazla ürün yok")
                #endif
                return
            }
            
            // Yeni batch'i ekle
            allProducts.append(contentsOf: loadedProducts)
            productsOffset += loadedProducts.count
            hasMoreProductsFromAPI = loadedProducts.count >= loadMoreBatchSize
            
            // Gösterilecek sayıyı artır
            let nextBatch = min(displayedProductsCount + loadMoreBatchSize, allProducts.count)
            displayedProductsCount = nextBatch
            products = Array(allProducts.prefix(displayedProductsCount))
            
            #if DEBUG
            print("✅ \(loadedProducts.count) yeni ürün yüklendi. Toplam: \(allProducts.count), Gösterilen: \(displayedProductsCount)")
            #endif
        } catch {
            #if DEBUG
            print("❌ Daha fazla ürün yüklenemedi: \(error.localizedDescription)")
            #endif
            
            // Cancelled hatalarını ignore et
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            if !isCancelled {
                // Diğer hatalar için hasMoreProductsFromAPI'yi false yap (sonsuz retry önle)
                hasMoreProductsFromAPI = false
            }
            // isLoadingMoreProducts defer ile false yapılacak
        }
    }
    
    func loadMoreMembers() async {
        guard !isLoadingMoreMembers && displayedMembersCount < allMembers.count else { return }
        isLoadingMoreMembers = true
        
        // Gereksiz delay kaldırıldı - direkt yükleme daha hızlı
        let nextBatch = min(displayedMembersCount + 50, allMembers.count)
        displayedMembersCount = nextBatch
        members = Array(allMembers.prefix(displayedMembersCount))
        
        isLoadingMoreMembers = false
        
        #if DEBUG
        print("📄 Lazy loading: \(displayedMembersCount)/\(allMembers.count) üye gösteriliyor")
        #endif
    }
    
    var hasMoreCampaigns: Bool {
        displayedCampaignsCount < allCampaigns.count
    }
    
    var hasMoreProducts: Bool {
        // Hem local'de daha fazla ürün var mı hem de API'den daha fazla çekilebilir mi kontrol et
        displayedProductsCount < allProducts.count || hasMoreProductsFromAPI
    }
    
    var hasMoreMembers: Bool {
        displayedMembersCount < allMembers.count
    }
    
    // Arama Sonuçları - Computed Properties
    var filteredEvents: [Event] {
        if eventsSearchText.isEmpty {
            return events
        }
        return allEvents.filter { 
            $0.title.localizedCaseInsensitiveContains(eventsSearchText) ||
            ($0.description.localizedCaseInsensitiveContains(eventsSearchText)) ||
            ($0.location?.localizedCaseInsensitiveContains(eventsSearchText) ?? false)
        }
    }
    
    var filteredCampaigns: [Campaign] {
        if campaignsSearchText.isEmpty {
            return campaigns
        }
        return allCampaigns.filter { 
            $0.title.localizedCaseInsensitiveContains(campaignsSearchText) ||
            $0.description.localizedCaseInsensitiveContains(campaignsSearchText)
        }
    }
    
    var filteredProducts: [Product] {
        if productsSearchText.isEmpty {
            return products
        }
        return allProducts.filter { 
            $0.name.localizedCaseInsensitiveContains(productsSearchText) ||
            ($0.description?.localizedCaseInsensitiveContains(productsSearchText) ?? false)
        }
    }
    
    var filteredMembers: [Member] {
        if membersSearchText.isEmpty {
            return members
        }
        return allMembers.filter { 
            $0.fullName.localizedCaseInsensitiveContains(membersSearchText)
        }
    }
    
    var filteredBoardMembers: [BoardMember] {
        if boardSearchText.isEmpty {
            return boardMembers
        }
        return allBoardMembers.filter { 
            $0.name.localizedCaseInsensitiveContains(boardSearchText) ||
            $0.role.localizedCaseInsensitiveContains(boardSearchText)
        }
    }
}

// MARK: - Feed ViewModel
@MainActor
class FeedViewModel: ObservableObject {
    @Published var posts: [Post] = []
    @Published var isLoading = false
    @Published var errorMessage: String?
    @Published var hasInitiallyLoaded = false
    
    func loadPosts() async {
        guard !isLoading else { return }
        
        isLoading = true
        errorMessage = nil
        
        do {
            let loadedPosts = try await APIService.shared.getPosts()
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            posts = loadedPosts
            hasInitiallyLoaded = true
            isLoading = false
        } catch {
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            isLoading = false
        }
    }
    
    func toggleLike(postId: String) async {
        do {
            let updatedPost = try await APIService.shared.togglePostLike(postId: postId)
            // @MainActor ile işaretlendiği için MainActor.run gereksiz
            if let index = posts.firstIndex(where: { $0.id == postId }) {
                posts[index] = updatedPost
            }
        } catch {
            #if DEBUG
            print("Like hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    func addComment(postId: String, content: String) async throws -> Comment {
        return try await APIService.shared.addComment(postId: postId, content: content)
    }
    
    func getComments(postId: String) async throws -> [Comment] {
        return try await APIService.shared.getComments(postId: postId)
    }
}

// MARK: - Market ViewModel
@MainActor
class MarketViewModel: ObservableObject {
    @Published var products: [Product] = []
    @Published var isLoading = false
    @Published var isLoadingMore = false // Lazy loading için
    @Published var errorMessage: String?
    @Published var hasInitiallyLoaded = false
    @Published var searchText = ""
    @Published var selectedCategory: String? = nil {
        didSet {
            // Kategori değiştiğinde API'den yeniden yükle
            Task {
                await loadProducts(isRefresh: true)
            }
        }
    }
    @Published var sortOption: SortOption = .newest
    @Published var minPrice: Double? = nil
    @Published var maxPrice: Double? = nil
    @Published var showOnlyInStock: Bool = false
    @Published var selectedCommunityId: String? = nil // Topluluk filtresi
    @Published var selectedUniversity: String? = nil { // Üniversite filtresi (market için eklendi)
        didSet {
            // Üniversite değiştiğinde API'den yeniden yükle
            Task {
                await loadProducts(isRefresh: true)
            }
            #if DEBUG
            print("🎯 MarketViewModel: selectedUniversity değişti: \(selectedUniversity ?? "nil")")
            #endif
        }
    }
    
    @Published var productCategories: [ProductCategory] = []
    @Published var isLoadingCategories = false
    
    enum SortOption: String, CaseIterable {
        case priceLowToHigh = "price_low"
        case priceHighToLow = "price_high"
        case nameAZ = "name_az"
        case nameZA = "name_za"
        case newest = "newest"
        
        var displayName: String {
            switch self {
            case .priceLowToHigh: return "Fiyat ↑"
            case .priceHighToLow: return "Fiyat ↓"
            case .nameAZ: return "İsim A-Z"
            case .nameZA: return "İsim Z-A"
            case .newest: return "En Yeni"
            }
        }
    }
    
    var hasActiveFilters: Bool {
        selectedCategory != nil || minPrice != nil || maxPrice != nil || showOnlyInStock || selectedCommunityId != nil || (selectedUniversity != nil && !selectedUniversity!.isEmpty)
    }
    
    // Lazy loading için
    private var allProducts: [Product] = [] // Tüm yüklenen ürünler
    private var displayedCount: Int = 20 // İlk yüklemede gösterilecek sayı (optimize edildi)
    private let loadMoreBatchSize: Int = 20 // Her seferinde yüklenecek sayı (optimize edildi)
    private var currentOffset: Int = 0 // API pagination için
    private var hasMoreFromAPI: Bool = true // API'de daha fazla ürün var mı?
    // YENİ SİSTEM: Üniversite filtresi kaldırıldı - client-side filtreleme yapılacak
    // activeUniversityId kaldırıldı
    
    // Topluluk listesi - CommunitiesViewModel'den alınacak (ürünlerin hangi topluluktan olduğunu göstermek için)
    @Published var availableCommunities: [Community] = []
    
    // Filtrelenmiş tüm ürünler (lazy loading için)
    private var filteredAllProducts: [Product] {
        var filtered = allProducts
        
        // NOT: Üniversite ve Topluluk filtreleri artık SERVER tarafında yapılıyor.
        // Burada tekrar filtrelemek, availableCommunities listesi eksikse ürünlerin gizlenmesine neden olur.
        // Sadece kategori (opsiyonel hızlı geçiş için) ve arama filtresini tutuyoruz.
        
        // Kategori filtresi (Hızlı geçiş için yerel filtreleme)
        if let selectedCategory = selectedCategory {
            filtered = filtered.filter { $0.category == selectedCategory }
        }
        
        // Search filter - geliştirilmiş arama
        if !searchText.isEmpty {
            filtered = filtered.filter { product in
                product.name.localizedCaseInsensitiveContains(searchText) ||
                (product.description?.localizedCaseInsensitiveContains(searchText) ?? false) ||
                product.category.localizedCaseInsensitiveContains(searchText)
            }
        }
        
        // Category filter
        if let category = selectedCategory, !category.isEmpty {
            filtered = filtered.filter { $0.category == category }
        }
        
        // Price filters
        if let minPrice = minPrice {
            filtered = filtered.filter { ($0.totalPrice ?? $0.price) >= minPrice }
        }
        if let maxPrice = maxPrice {
            filtered = filtered.filter { ($0.totalPrice ?? $0.price) <= maxPrice }
        }
        
        // Stock filter
        if showOnlyInStock {
            filtered = filtered.filter { $0.stock > 0 }
        }
        
        // Sort
        filtered = sortProducts(filtered)
        
        return filtered
    }
    
    private func sortProducts(_ products: [Product]) -> [Product] {
        var sorted = products
        
        switch sortOption {
        case .priceLowToHigh:
            sorted.sort { ($0.totalPrice ?? $0.price) < ($1.totalPrice ?? $1.price) }
        case .priceHighToLow:
            sorted.sort { ($0.totalPrice ?? $0.price) > ($1.totalPrice ?? $1.price) }
        case .nameAZ:
            sorted.sort { $0.name.localizedCompare($1.name) == .orderedAscending }
        case .nameZA:
            sorted.sort { $0.name.localizedCompare($1.name) == .orderedDescending }
        case .newest:
            sorted.sort { ($0.createdAt ?? Date.distantPast) > ($1.createdAt ?? Date.distantPast) }
        }
        
        return sorted
    }
    
    // Gösterilecek ürünler (lazy loading ile)
    var filteredProducts: [Product] {
        let filtered = filteredAllProducts
        // Filtreleme değiştiğinde displayedCount'u reset et (eğer filtered count daha azsa)
        if displayedCount > filtered.count {
            displayedCount = min(loadMoreBatchSize, filtered.count)
        }
        return Array(filtered.prefix(displayedCount))
    }
    
    var categories: [String] {
        if !productCategories.isEmpty {
            return productCategories.map { $0.name }.sorted()
        }
        return Array(Set(allProducts.map { $0.category })).sorted()
    }
    
    var availableUniversities: [String] {
        Array(Set(availableCommunities.compactMap { $0.university })).sorted()
    }
    
    func loadProducts(universityId: String? = nil, isRefresh: Bool = false) async {
        guard !isLoading else { return }
        
        isLoading = true
        errorMessage = nil
        currentOffset = 0
        // Refresh modunda allProducts'u temizleme - yeni veriler yüklenene kadar eski veriler gösterilsin
        if !isRefresh {
            allProducts = []
            displayedCount = 0
        }
        
        // Kategorileri de yükle
        Task {
            await loadCategories()
        }
        
        do {
            // v2 API kullan
            let filters = ProductFilters(
                category: selectedCategory,
                community: selectedCommunityId,
                university: selectedUniversity,
                minPrice: minPrice,
                maxPrice: maxPrice,
                inStock: showOnlyInStock ? 1 : nil,
                sort: sortOption.rawValue,
                limit: 20,
                offset: 0
            )
            
            let response = try await APIService.shared.getProductsV2(filters: filters)
            
            allProducts = response.products
            displayedCount = min(loadMoreBatchSize, response.products.count)
            currentOffset = response.products.count
            hasMoreFromAPI = response.pagination.hasMore
            
            updateDisplayedProducts()
            hasInitiallyLoaded = true
            isLoading = false
        } catch {
            let isCancelled = (error as? URLError)?.code == .cancelled || error is CancellationError
            let isTimeout = String(describing: type(of: error)).contains("TimeoutError")
            
            if isCancelled || isTimeout {
                if !products.isEmpty {
                    isLoading = false
                    return
                }
                allProducts = []
                displayedCount = 0
                products = []
                isLoading = false
                hasInitiallyLoaded = true
                return
            }
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            isLoading = false
        }
    }
    
    func loadCategories() async {
        guard !isLoadingCategories else { return }
        isLoadingCategories = true
        
        do {
            let loadedCategories = try await APIService.shared.getProductCategories()
            self.productCategories = loadedCategories
            isLoadingCategories = false
        } catch {
            #if DEBUG
            print("❌ Kategoriler yüklenemedi: \(error.localizedDescription)")
            #endif
            isLoadingCategories = false
        }
    }
    
    /// Gösterilecek ürünleri güncelle (filtreleme sonrası)
    private func updateDisplayedProducts() {
        // filteredProducts computed property otomatik olarak güncellenecek
        // Sadece products'i senkronize et
        products = filteredProducts
    }
    
    /// Lazy loading - Daha fazla ürün yükle
    func loadMoreProducts() async {
        let filtered = filteredAllProducts
        guard !isLoadingMore else { return }
        
        // Önce local'de daha fazla ürün var mı kontrol et
        if displayedCount < filtered.count {
            // Local'de daha fazla ürün var, göster
            isLoadingMore = true
            // Delay kaldırıldı - direkt yükleme daha hızlı
            // Yeni batch'i ekle
            let nextBatch = min(displayedCount + loadMoreBatchSize, filtered.count)
            displayedCount = nextBatch
            products = filteredProducts
            isLoadingMore = false
            #if DEBUG
            print("📄 Lazy loading (local): \(displayedCount)/\(filtered.count) ürün gösteriliyor")
            #endif
            return
        }
        
        // Local'de daha fazla ürün yok, API'den çek
        guard hasMoreFromAPI else {
            // API'de de daha fazla ürün yok
            return
        }
        
        isLoadingMore = true
        
        do {
            // v2 API kullan
            let filters = ProductFilters(
                category: selectedCategory,
                community: selectedCommunityId,
                university: selectedUniversity,
                minPrice: minPrice,
                maxPrice: maxPrice,
                inStock: showOnlyInStock ? 1 : nil,
                sort: sortOption.rawValue,
                limit: 20,
                offset: currentOffset
            )
            
            let response = try await APIService.shared.getProductsV2(filters: filters)
            
            if response.products.isEmpty {
                // Daha fazla ürün yok
                hasMoreFromAPI = false
                isLoadingMore = false
                return
            }
            
            // Yeni ürünleri ekle
            allProducts.append(contentsOf: response.products)
            currentOffset += response.products.count
            hasMoreFromAPI = response.pagination.hasMore
            
            // Gösterilecek sayıyı güncelle
            displayedCount = filteredAllProducts.count
            products = filteredProducts
            
            isLoadingMore = false
            
            #if DEBUG
            print("📄 Lazy loading (API): \(displayedCount) ürün gösteriliyor (API'den \(response.products.count) yeni ürün)")
            #endif
        } catch {
            isLoadingMore = false
            #if DEBUG
            print("⚠️ Lazy loading hatası: \(error.localizedDescription)")
            #endif
        }
    }
    
    /// Daha fazla ürün var mı?
    var hasMoreProducts: Bool {
        displayedCount < filteredAllProducts.count
    }
    
    func refreshProducts(universityId: String? = nil) async {
        // YENİ SİSTEM: Üniversite filtresi kaldırıldı - universityId parametresi artık kullanılmıyor
        // Refresh sırasında hasInitiallyLoaded'i false yapma - bu "ürün bulunamadı" mesajına neden olur
        // ÖNEMLİ: Verileri temizlemeden önce yeni verileri yükle ki UI boş kalmasın
        
        // State'i resetle
        displayedCount = 0
        currentOffset = 0
        hasMoreFromAPI = true
        isLoading = false
        errorMessage = nil
        
        // Verileri yeniden yükle - isRefresh: true ile allProducts'u temizleme
        await loadProducts(universityId: nil, isRefresh: true)
        
        // Yükleme sonrası displayedCount'u güncelle
        if !allProducts.isEmpty {
            displayedCount = min(loadMoreBatchSize, allProducts.count)
            updateDisplayedProducts()
        }
    }
}

// MARK: - Cart ViewModel
@MainActor
class CartViewModel: ObservableObject {
    @Published var items: [CartItem] = []
    
    var totalItems: Int {
        items.reduce(0) { $0 + $1.quantity }
    }
    
    var totalPrice: Double {
        items.reduce(0) { $0 + $1.totalPrice }
    }
    
    var formattedTotalPrice: String {
        return String(format: "%.2f", totalPrice).replacingOccurrences(of: ".", with: ",") + " ₺"
    }
    
    func addItem(_ product: Product, quantity: Int = 1) {
        if let existingIndex = items.firstIndex(where: { $0.product.id == product.id }) {
            // Ürün zaten sepette, miktarı artır
            items[existingIndex].quantity += quantity
        } else {
            // Yeni ürün ekle
            items.append(CartItem(product: product, quantity: quantity))
        }
    }
    
    func removeItem(_ itemId: String) {
        items.removeAll { $0.id == itemId }
    }
    
    func updateQuantity(_ itemId: String, quantity: Int) {
        if let index = items.firstIndex(where: { $0.id == itemId }) {
            if quantity <= 0 {
                items.remove(at: index)
            } else {
                items[index].quantity = quantity
            }
        }
    }
    
    func clearCart() {
        items.removeAll()
    }
    
    func isInCart(_ productId: String) -> Bool {
        return items.contains { $0.product.id == productId }
    }
}

// MARK: - Orders ViewModel
@MainActor
class OrdersViewModel: ObservableObject {
    @Published var orders: [Order] = []
    @Published var selectedOrder: Order?
    @Published var isLoading = false
    @Published var isLoadingMore = false
    @Published var errorMessage: String?
    @Published var hasInitiallyLoaded = false
    
    private var currentPage = 1
    private var hasMore = true
    private let pageSize = 20
    
    var isEmpty: Bool {
        orders.isEmpty && hasInitiallyLoaded && !isLoading
    }
    
    /// Load orders from API
    func loadOrders(forceRefresh: Bool = false) async {
        if isLoading && !forceRefresh {
            return
        }
        
        if hasInitiallyLoaded && !forceRefresh && !orders.isEmpty {
            return
        }
        
        isLoading = true
        errorMessage = nil
        
        if forceRefresh {
            currentPage = 1
            hasMore = true
        }
        
        do {
            let response = try await APIService.shared.getOrders(page: currentPage, limit: pageSize)
            
            if forceRefresh {
                orders = response.orders
            } else {
                orders = response.orders
            }
            
            hasMore = response.pagination.hasMore
            hasInitiallyLoaded = true
            isLoading = false
            
            #if DEBUG
            print("✅ \(orders.count) sipariş yüklendi")
            #endif
        } catch {
            #if DEBUG
            print("❌ Siparişler yüklenemedi: \(error.localizedDescription)")
            #endif
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
            isLoading = false
            hasInitiallyLoaded = true
        }
    }
    
    /// Load more orders (pagination)
    func loadMore() async {
        guard !isLoadingMore && hasMore && !isLoading else {
            return
        }
        
        isLoadingMore = true
        currentPage += 1
        
        do {
            let response = try await APIService.shared.getOrders(page: currentPage, limit: pageSize)
            
            orders.append(contentsOf: response.orders)
            hasMore = response.pagination.hasMore
            isLoadingMore = false
            
            #if DEBUG
            print("✅ \(response.orders.count) ek sipariş yüklendi (toplam: \(orders.count))")
            #endif
        } catch {
            #if DEBUG
            print("❌ Ek siparişler yüklenemedi: \(error.localizedDescription)")
            #endif
            currentPage -= 1
            isLoadingMore = false
        }
    }
    
    /// Refresh orders
    func refresh() async {
        await loadOrders(forceRefresh: true)
    }
    
    /// Load single order details
    func loadOrderDetails(orderId: String) async {
        do {
            let order = try await APIService.shared.getOrder(id: orderId)
            selectedOrder = order
            
            // Update in list if present
            if let index = orders.firstIndex(where: { $0.id == order.id }) {
                orders[index] = order
            }
            
            #if DEBUG
            print("✅ Sipariş detayı yüklendi: \(order.orderNumber)")
            #endif
        } catch {
            #if DEBUG
            print("❌ Sipariş detayı yüklenemedi: \(error.localizedDescription)")
            #endif
            errorMessage = ErrorHandler.userFriendlyMessage(from: error)
        }
    }
    
    /// Create a new order
    func createOrder(items: [CartItem], customerName: String, customerEmail: String, customerPhone: String) async throws -> CreateOrderResponse {
        #if DEBUG
        print("📦 Sipariş oluşturuluyor...")
        #endif
        
        let response = try await APIService.shared.createOrder(
            items: items,
            customerName: customerName,
            customerEmail: customerEmail,
            customerPhone: customerPhone
        )
        
        #if DEBUG
        print("✅ Sipariş oluşturuldu: \(response.orderNumber)")
        #endif
        
        // Refresh orders list
        await loadOrders(forceRefresh: true)
        
        return response
    }
}
