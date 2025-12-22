//
//  Models.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import SwiftUI

// MARK: - Community Model
struct Community: Identifiable, Hashable, Codable {
    let id: String
    let name: String
    let description: String
    let shortDescription: String?
    let memberCount: Int
    let eventCount: Int
    let campaignCount: Int
    let boardCount: Int
    let imageURL: String?
    let logoPath: String?
    let categories: [String] // Birden fazla kategori (max 3)
    let tags: [String]
    let isVerified: Bool
    let createdAt: Date
    let contactEmail: String?
    let website: String?
    let socialLinks: SocialLinks?
    let status: String?
    let university: String? // Üniversite adı (tümü seçiliyse gösterilecek)
    
    enum CodingKeys: String, CodingKey {
        case id
        case name
        case description
        case shortDescription
        case memberCount = "member_count"
        case eventCount = "event_count"
        case campaignCount = "campaign_count"
        case boardCount = "board_member_count"
        case imageURL = "image_url"
        case logoPath = "logo_path"
        case categories
        case category // Geriye dönük uyumluluk için
        case tags
        case isVerified = "is_verified"
        case createdAt = "created_at"
        case contactEmail = "contact_email"
        case website
        case socialLinks = "social_links"
        case status
        case university
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        id = try container.decode(String.self, forKey: .id)
        name = try container.decode(String.self, forKey: .name)
        description = try container.decodeIfPresent(String.self, forKey: .description) ?? ""
        shortDescription = try container.decodeIfPresent(String.self, forKey: .shortDescription)
        memberCount = try container.decode(Int.self, forKey: .memberCount)
        eventCount = try container.decode(Int.self, forKey: .eventCount)
        campaignCount = try container.decode(Int.self, forKey: .campaignCount)
        boardCount = try container.decode(Int.self, forKey: .boardCount)
        imageURL = try container.decodeIfPresent(String.self, forKey: .imageURL)
        logoPath = try container.decodeIfPresent(String.self, forKey: .logoPath)
        
        // Categories - array olarak decode et (geriye dönük uyumluluk için string de destekle)
        // Önce "categories" key'ini dene (yeni format)
        if let categoriesArray = try? container.decode([String].self, forKey: .categories) {
            categories = categoriesArray.filter { $0 != "other" } // "other" kategorisini filtrele
        } else if let categoryString = try? container.decode(String.self, forKey: .category) {
            // Eski format: tek kategori string olarak "category" key'inden
            if categoryString != "other" {
                categories = [categoryString]
        } else {
                categories = []
            }
        } else if let categoryString = try? container.decode(String.self, forKey: .categories) {
            // Eski format: tek kategori string olarak "categories" key'inden
            if categoryString != "other" {
                categories = [categoryString]
            } else {
                categories = []
            }
        } else {
            categories = []
        }
        
        tags = try container.decodeIfPresent([String].self, forKey: .tags) ?? []
        isVerified = try container.decodeIfPresent(Bool.self, forKey: .isVerified) ?? false
        
        // Date decoding
        if let dateString = try? container.decode(String.self, forKey: .createdAt) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: dateString) {
                createdAt = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd'T'HH:mm:ssZ"
                createdAt = dateFormatter.date(from: dateString) ?? Date()
            }
        } else {
            createdAt = Date()
        }
        
        contactEmail = try container.decodeIfPresent(String.self, forKey: .contactEmail)
        website = try container.decodeIfPresent(String.self, forKey: .website)
        socialLinks = try container.decodeIfPresent(SocialLinks.self, forKey: .socialLinks)
        status = try container.decodeIfPresent(String.self, forKey: .status)
        university = try container.decodeIfPresent(String.self, forKey: .university)
    }
    
    init(id: String, name: String, description: String, shortDescription: String? = nil, memberCount: Int, eventCount: Int, campaignCount: Int, boardCount: Int, imageURL: String? = nil, logoPath: String? = nil, categories: [String], tags: [String], isVerified: Bool, createdAt: Date, contactEmail: String? = nil, website: String? = nil, socialLinks: SocialLinks? = nil, status: String? = nil, university: String? = nil) {
        self.id = id
        self.name = name
        self.description = description
        self.shortDescription = shortDescription
        self.memberCount = memberCount
        self.eventCount = eventCount
        self.campaignCount = campaignCount
        self.boardCount = boardCount
        self.imageURL = imageURL
        self.logoPath = logoPath
        self.categories = categories.filter { $0 != "other" } // "other" kategorisini filtrele
        self.tags = tags
        self.isVerified = isVerified
        self.createdAt = createdAt
        self.contactEmail = contactEmail
        self.website = website
        self.socialLinks = socialLinks
        self.status = status
        self.university = university
    }
    
    // MARK: - Encodable
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        
        try container.encode(id, forKey: .id)
        try container.encode(name, forKey: .name)
        try container.encodeIfPresent(description, forKey: .description)
        try container.encodeIfPresent(shortDescription, forKey: .shortDescription)
        try container.encode(memberCount, forKey: .memberCount)
        try container.encode(eventCount, forKey: .eventCount)
        try container.encode(campaignCount, forKey: .campaignCount)
        try container.encode(boardCount, forKey: .boardCount)
        try container.encodeIfPresent(imageURL, forKey: .imageURL)
        try container.encodeIfPresent(logoPath, forKey: .logoPath)
        try container.encode(categories, forKey: .categories) // Array olarak encode et
        try container.encode(tags, forKey: .tags)
        try container.encode(isVerified, forKey: .isVerified)
        
        // Date encoding
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        try container.encode(formatter.string(from: createdAt), forKey: .createdAt)
        
        try container.encodeIfPresent(contactEmail, forKey: .contactEmail)
        try container.encodeIfPresent(website, forKey: .website)
        try container.encodeIfPresent(socialLinks, forKey: .socialLinks)
        try container.encodeIfPresent(status, forKey: .status)
    }
    
    // Topluluk kategorileri (other hariç)
    static let availableCategories: [String] = [
        "Mühendislik",
        "Bilim",
        "Sanat",
        "Spor",
        "Sosyal",
        "Akademik",
        "Teknoloji",
        "Kültür"
    ]
    
    // Kategori icon'ları
    static func icon(for category: String) -> String {
        switch category {
        case "Mühendislik": return "gearshape.fill"
        case "Bilim": return "atom"
        case "Sanat": return "paintbrush.fill"
        case "Spor": return "figure.run"
        case "Sosyal": return "person.3.fill"
        case "Akademik": return "book.fill"
        case "Teknoloji": return "laptopcomputer"
        case "Kültür": return "theatermasks.fill"
        default: return "person.3.fill"
            }
        }
        
    // Kategori renkleri
    static func color(for category: String) -> Color {
        switch category {
        case "Mühendislik": return Color(hex: "6366f1")
        case "Bilim": return Color(hex: "3b82f6")
        case "Sanat": return Color(hex: "ec4899")
        case "Spor": return Color(hex: "f59e0b")
        case "Sosyal": return Color(hex: "10b981")
        case "Akademik": return Color(hex: "8b5cf6")
        case "Teknoloji": return Color(hex: "06b6d4")
        case "Kültür": return Color(hex: "f97316")
        default: return Color(hex: "6b7280")
        }
    }
}

struct SocialLinks: Codable, Hashable {
    let instagram: String?
    let twitter: String?
    let linkedin: String?
    let facebook: String?
}

// MARK: - Verified Community Info
struct VerifiedCommunityInfo: Identifiable, Codable, Hashable {
    let communityId: String
    let communityName: String
    let documentPath: String?
    let documentUrl: String?
    let notes: String?
    let adminNotes: String?
    let reviewedAtRaw: String?
    let updatedAtRaw: String?
    
    var id: String { communityId }
    
    enum CodingKeys: String, CodingKey {
        case communityId = "community_id"
        case communityName = "community_name"
        case documentPath = "document_path"
        case documentUrl = "document_url"
        case notes
        case adminNotes = "admin_notes"
        case reviewedAtRaw = "reviewed_at"
        case updatedAtRaw = "updated_at"
    }
    
    private static let iso8601WithFractional: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }()
    
    private static let iso8601: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }()
    
    private static let legacyFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone(secondsFromGMT: 0)
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter
    }()
    
    private static let displayFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "tr_TR")
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        return formatter
    }()
    
    private static func parseDate(_ value: String?) -> Date? {
        guard let value = value, !value.isEmpty else { return nil }
        if let date = iso8601WithFractional.date(from: value) {
            return date
        }
        if let date = iso8601.date(from: value) {
            return date
        }
        return legacyFormatter.date(from: value)
    }
    
    var reviewedAt: Date? {
        Self.parseDate(reviewedAtRaw)
    }
    
    var updatedAt: Date? {
        Self.parseDate(updatedAtRaw)
    }
    
    var reviewedAtDisplayText: String? {
        guard let reviewedAt else { return nil }
        return Self.displayFormatter.string(from: reviewedAt)
    }
}

// MARK: - Event Model
struct Event: Identifiable, Hashable, Codable {
    let id: String
    let title: String
    let description: String
    let date: Date
    let startTime: String
    let endTime: String?
    let time: String? // API'den gelen time field'ı
    let location: String?
    let locationDetails: String?
    let imageURL: String?
    let imagePath: String? // API'den gelen image_path (eski format - geriye dönük uyumluluk)
    let videoPath: String?
    let images: [EventMedia]? // Birden fazla görsel (yeni format)
    let videos: [EventMedia]? // Birden fazla video (yeni format)
    let communityId: String
    let communityName: String
    let category: EventCategory
    let capacity: Int?
    let registeredCount: Int
    let isRegistrationRequired: Bool
    let registrationRequired: Bool? // API'den gelen registration_required
    let registrationDeadline: Date?
    let tags: [String]
    let organizer: String
    let contactEmail: String?
    let contactPhone: String?
    let isOnline: Bool
    let onlineLink: String?
    let price: Double?
    let cost: Double? // API'den gelen cost
    let currency: String?
    let hasSurvey: Bool
    let status: String?
    let university: String? // Üniversite adı (API'den gelen university field'ı)
    let isMember: Bool? // Üyelik durumu (API'den gelen is_member)
    let membershipStatus: String? // Üyelik durumu string ("member", "none", "pending", etc.)
    let createdAt: Date? // Oluşturulma tarihi
    
    enum CodingKeys: String, CodingKey {
        case id
        case title
        case description
        case date
        case startTime = "start_time"
        case endTime = "end_time"
        case time
        case location
        case locationDetails = "location_details"
        case imageURL = "image_url"
        case imagePath = "image_path"
        case videoPath = "video_path"
        case images
        case videos
        case communityId = "community_id"
        case communityName = "community_name"
        case category
        case capacity
        case registeredCount = "registered_count"
        case isRegistrationRequired = "is_registration_required"
        case registrationRequired = "registration_required"
        case registrationDeadline = "registration_deadline"
        case tags
        case organizer
        case contactEmail = "contact_email"
        case contactPhone = "contact_phone"
        case isOnline = "is_online"
        case onlineLink = "online_link"
        case price
        case cost
        case currency
        case hasSurvey = "has_survey"
        case status
        case university
        case isMember = "is_member"
        case membershipStatus = "membership_status"
        case createdAt = "created_at"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID'yi Int veya String olarak handle et
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        title = try container.decode(String.self, forKey: .title)
        description = try container.decodeIfPresent(String.self, forKey: .description) ?? ""
        
        // Date decoding - multiple formats
        if let dateString = try? container.decode(String.self, forKey: .date) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: dateString) {
                self.date = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd"
                self.date = dateFormatter.date(from: dateString) ?? Date()
            }
        } else {
            self.date = Date()
        }
        
        // Time handling (geçici değişkene al, sonra tek atama yap)
        var tempStartTime = try container.decodeIfPresent(String.self, forKey: .startTime) ?? ""
        if tempStartTime.isEmpty {
            tempStartTime = try container.decodeIfPresent(String.self, forKey: .time) ?? ""
        }
        startTime = tempStartTime
        
        endTime = try container.decodeIfPresent(String.self, forKey: .endTime)
        time = try container.decodeIfPresent(String.self, forKey: .time)
        
        location = try container.decodeIfPresent(String.self, forKey: .location)
        locationDetails = try container.decodeIfPresent(String.self, forKey: .locationDetails)
        imageURL = try container.decodeIfPresent(String.self, forKey: .imageURL)
        imagePath = try container.decodeIfPresent(String.self, forKey: .imagePath)
        videoPath = try container.decodeIfPresent(String.self, forKey: .videoPath)
        images = try container.decodeIfPresent([EventMedia].self, forKey: .images)
        videos = try container.decodeIfPresent([EventMedia].self, forKey: .videos)
        
        // Community ID - Int veya String
        if let intCommunityId = try? container.decode(Int.self, forKey: .communityId) {
            communityId = String(intCommunityId)
        } else {
            communityId = try container.decodeIfPresent(String.self, forKey: .communityId) ?? ""
        }
        
        communityName = try container.decodeIfPresent(String.self, forKey: .communityName) ?? ""
        
        // Category
        if let categoryString = try? container.decode(String.self, forKey: .category) {
            // Kategori string'ini normalize et (trim, case-insensitive)
            let normalizedCategory = categoryString.trimmingCharacters(in: .whitespacesAndNewlines)
            // Önce tam eşleşme dene
            if let matchedCategory = EventCategory(rawValue: normalizedCategory) {
                category = matchedCategory
            } else {
                // Case-insensitive eşleşme dene
                let lowercased = normalizedCategory.lowercased()
                if let matchedCategory = EventCategory.allCases.first(where: { $0.rawValue.lowercased() == lowercased }) {
                    category = matchedCategory
                } else {
                    // Özel eşleştirmeler: API'den gelen kategorileri Swift kategorilerine map et
                    let categoryMapping: [String: EventCategory] = [
                        "eğitim": .education,
                        "egitim": .education,
                        "genel": .general,
                        "sosyal": .socialSimple,
                        "kültür": .culture,
                        "kultur": .culture,
                        "kültür & sanat": .culture,
                        "kultur & sanat": .culture,
                        "teknoloji": .technology,
                        "sosyal etkinlik": .social,
                        "diğer": .other,
                        "diger": .other
                    ]
                    
                    if let mappedCategory = categoryMapping[lowercased] {
                        category = mappedCategory
                    } else {
                        // Eşleşme yoksa .other
                        category = .other
                        #if DEBUG
                        print("⚠️ Bilinmeyen kategori: '\(normalizedCategory)', .other olarak ayarlandı")
                        #endif
                    }
                }
            }
        } else {
            category = .other
        }
        
        capacity = try container.decodeIfPresent(Int.self, forKey: .capacity)
        registeredCount = try container.decodeIfPresent(Int.self, forKey: .registeredCount) ?? 0
        registrationRequired = try container.decodeIfPresent(Bool.self, forKey: .registrationRequired)
        isRegistrationRequired = try container.decodeIfPresent(Bool.self, forKey: .isRegistrationRequired) ?? (registrationRequired ?? false)
        
        // Registration deadline
        if let deadlineString = try? container.decodeIfPresent(String.self, forKey: .registrationDeadline) {
            let formatter = ISO8601DateFormatter()
            registrationDeadline = formatter.date(from: deadlineString)
        } else {
            registrationDeadline = nil
        }
        
        tags = try container.decodeIfPresent([String].self, forKey: .tags) ?? []
        organizer = try container.decodeIfPresent(String.self, forKey: .organizer) ?? ""
        contactEmail = try container.decodeIfPresent(String.self, forKey: .contactEmail)
        contactPhone = try container.decodeIfPresent(String.self, forKey: .contactPhone)
        isOnline = try container.decodeIfPresent(Bool.self, forKey: .isOnline) ?? false
        onlineLink = try container.decodeIfPresent(String.self, forKey: .onlineLink)
        
        // Price ve cost - Int veya Double olarak gelebilir
        if let priceInt = try? container.decodeIfPresent(Int.self, forKey: .price) {
            price = Double(priceInt)
        } else {
            price = try container.decodeIfPresent(Double.self, forKey: .price)
        }
        
        if let costInt = try? container.decodeIfPresent(Int.self, forKey: .cost) {
            cost = Double(costInt)
        } else {
            cost = try container.decodeIfPresent(Double.self, forKey: .cost)
        }
        currency = try container.decodeIfPresent(String.self, forKey: .currency)
        hasSurvey = try container.decodeIfPresent(Bool.self, forKey: .hasSurvey) ?? false
        status = try container.decodeIfPresent(String.self, forKey: .status)
        university = try container.decodeIfPresent(String.self, forKey: .university)
        isMember = try container.decodeIfPresent(Bool.self, forKey: .isMember)
        membershipStatus = try container.decodeIfPresent(String.self, forKey: .membershipStatus)
        
        // Created At
        if let createdAtString = try? container.decodeIfPresent(String.self, forKey: .createdAt) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: createdAtString) {
                createdAt = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                createdAt = dateFormatter.date(from: createdAtString)
            }
        } else {
            createdAt = nil
        }
    }
    
    init(id: String, title: String, description: String, date: Date, startTime: String, endTime: String? = nil, time: String? = nil, location: String? = nil, locationDetails: String? = nil, imageURL: String? = nil, imagePath: String? = nil, videoPath: String? = nil, images: [EventMedia]? = nil, videos: [EventMedia]? = nil, communityId: String, communityName: String, category: EventCategory, capacity: Int? = nil, registeredCount: Int = 0, isRegistrationRequired: Bool = false, registrationRequired: Bool? = nil, registrationDeadline: Date? = nil, tags: [String] = [], organizer: String, contactEmail: String? = nil, contactPhone: String? = nil, isOnline: Bool = false, onlineLink: String? = nil, price: Double? = nil, cost: Double? = nil, currency: String? = nil, hasSurvey: Bool = false, status: String? = nil, university: String? = nil, isMember: Bool? = nil, membershipStatus: String? = nil, createdAt: Date? = nil) {
        self.id = id
        self.title = title
        self.description = description
        self.date = date
        self.startTime = startTime
        self.endTime = endTime
        self.time = time
        self.location = location
        self.locationDetails = locationDetails
        self.imageURL = imageURL
        self.imagePath = imagePath
        self.videoPath = videoPath
        self.images = images
        self.videos = videos
        self.communityId = communityId
        self.communityName = communityName
        self.category = category
        self.capacity = capacity
        self.registeredCount = registeredCount
        self.isRegistrationRequired = isRegistrationRequired || (registrationRequired ?? false)
        self.registrationRequired = registrationRequired
        self.registrationDeadline = registrationDeadline
        self.tags = tags
        self.organizer = organizer
        self.contactEmail = contactEmail
        self.contactPhone = contactPhone
        self.isOnline = isOnline
        self.onlineLink = onlineLink
        // Price ve cost - önce price, sonra cost kullan
        if let priceValue = price {
            self.price = priceValue
        } else if let costValue = cost {
            self.price = costValue
        } else {
            self.price = nil
        }
        self.cost = cost
        self.currency = currency
        self.hasSurvey = hasSurvey
        self.isMember = isMember
        self.membershipStatus = membershipStatus
        self.status = status
        self.university = university
        self.createdAt = createdAt
    }
    
    // MARK: - Event Media Model
    struct EventMedia: Identifiable, Codable, Hashable {
        let id: Int
        let imagePath: String?
        let imageURL: String?
        let videoPath: String?
        
        enum CodingKeys: String, CodingKey {
            case id
            case imagePath = "image_path"
            case imageURL = "image_url"
            case videoPath = "video_path"
            case videoURL = "video_url" // API'den gelebilir
        }
        
        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            
            // ID'yi Int veya String olarak handle et
            if let intId = try? container.decode(Int.self, forKey: .id) {
                id = intId
            } else if let stringId = try? container.decode(String.self, forKey: .id) {
                id = Int(stringId) ?? 0
            } else {
                id = 0
            }
            
            imagePath = try container.decodeIfPresent(String.self, forKey: .imagePath)
            imageURL = try container.decodeIfPresent(String.self, forKey: .imageURL)
            
            // videoPath - önce video_path, sonra video_url dene
            if let videoPathValue = try? container.decodeIfPresent(String.self, forKey: .videoPath) {
                videoPath = videoPathValue
            } else {
                videoPath = try container.decodeIfPresent(String.self, forKey: .videoURL)
            }
        }
        
        func encode(to encoder: Encoder) throws {
            var container = encoder.container(keyedBy: CodingKeys.self)
            try container.encode(id, forKey: .id)
            try container.encodeIfPresent(imagePath, forKey: .imagePath)
            try container.encodeIfPresent(imageURL, forKey: .imageURL)
            try container.encodeIfPresent(videoPath, forKey: .videoPath)
        }
        
        var displayPath: String? {
            return imagePath ?? imageURL ?? videoPath
        }
    }
    
    enum EventCategory: String, Codable, CaseIterable {
        case workshop = "Workshop"
        case seminar = "Seminer"
        case conference = "Konferans"
        case social = "Sosyal Etkinlik"
        case competition = "Yarışma"
        case exhibition = "Sergi"
        case concert = "Konser"
        case sports = "Spor"
        case education = "Eğitim"
        case general = "Genel"
        case socialSimple = "Sosyal"
        case culture = "Kültür"
        case technology = "Teknoloji"
        case other = "Diğer"
        
        var icon: String {
            switch self {
            case .workshop: return "wrench.and.screwdriver.fill"
            case .seminar: return "person.2.fill"
            case .conference: return "building.2.fill"
            case .social, .socialSimple: return "party.popper.fill"
            case .competition: return "trophy.fill"
            case .exhibition: return "photo.fill"
            case .concert: return "music.note"
            case .sports: return "figure.run"
            case .education: return "book.fill"
            case .general: return "square.grid.2x2.fill"
            case .culture: return "theatermasks.fill"
            case .technology: return "laptopcomputer"
            case .other: return "ellipsis.circle.fill"
            }
        }
        
        var color: Color {
            switch self {
            case .workshop: return Color(hex: "6366f1")
            case .seminar: return Color(hex: "3b82f6")
            case .conference: return Color(hex: "8b5cf6")
            case .social, .socialSimple: return Color(hex: "ec4899")
            case .competition: return Color(hex: "f59e0b")
            case .exhibition: return Color(hex: "06b6d4")
            case .concert: return Color(hex: "f97316")
            case .sports: return Color(hex: "10b981")
            case .education: return Color(hex: "3b82f6")
            case .general: return Color(hex: "6b7280")
            case .culture: return Color(hex: "8b5cf6")
            case .technology: return Color(hex: "6366f1")
            case .other: return Color(hex: "6b7280")
            }
        }
    }
    
    var hasMedia: Bool {
        return (imagePath != nil) || 
               (videoPath != nil) || 
               (!(images?.isEmpty ?? true)) || 
               (!(videos?.isEmpty ?? true))
    }
    
    var allImages: [String] {
        var result: [String] = []
        // Eski format (imagePath)
        if let imagePath = imagePath {
            result.append(imagePath)
        }
        // Yeni format (images array)
        if let images = images {
            for image in images {
                if let path = image.displayPath {
                    result.append(path)
                }
            }
        }
        return result
    }
    
    var allVideos: [String] {
        var result: [String] = []
        // Eski format (videoPath)
        if let videoPath = videoPath {
            result.append(videoPath)
        }
        // Yeni format (videos array)
        if let videos = videos {
            for video in videos {
                if let path = video.displayPath {
                    result.append(path)
                }
            }
        }
        return result
    }
    
    var isUpcoming: Bool {
        date > Date()
    }
    
    var isPast: Bool {
        date < Date()
    }
    
    var formattedDate: String {
        let formatter = DateFormatter()
        formatter.dateFormat = "d MMMM yyyy"
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.string(from: date)
    }
    
    var formattedTime: String {
        if let endTime = endTime {
            return "\(startTime) - \(endTime)"
        }
        return startTime
    }
    
    // Static DateFormatter cache - performans optimizasyonu
    private static let monthFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "MMM"
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter
    }()
    
    private static let dayFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "d"
        return formatter
    }()
    
    var monthAbbreviation: String {
        Self.monthFormatter.string(from: date).uppercased()
    }
    
    var dayNumber: String {
        Self.dayFormatter.string(from: date)
    }
}

// MARK: - Campaign Model
struct Campaign: Identifiable, Hashable, Codable {
    let id: String
    let title: String
    let description: String
    let shortDescription: String?
    let offerText: String? // API'den gelen offer_text
    let communityId: String
    let communityName: String
    let university: String? // Üniversite adı
    let imageURL: String?
    let imagePath: String? // API'den gelen image_path
    let discount: Double?
    let discountPercentage: Int? // API'den gelen discount_percentage
    let discountType: DiscountType
    let startDate: Date
    let endDate: Date
    let partnerName: String?
    let partnerLogo: String?
    let terms: String?
    let tags: [String]
    let category: CampaignCategory
    let code: String? // Kampanya kodu
    let isActiveFromAPI: Bool? // API'den gelen is_active
    let createdAt: Date?
    let requiresMembership: Bool? // Topluluğa üye olma şartı
    let requirements: [CampaignRequirement]? // Kampanya şartları
    
    enum CodingKeys: String, CodingKey {
        case id
        case title
        case description
        case shortDescription
        case offerText = "offer_text"
        case code = "campaign_code"
        case communityId = "community_id"
        case communityName = "community_name"
        case university
        case imageURL = "image_url"
        case imagePath = "image_path"
        case discount
        case discountPercentage = "discount_percentage"
        case discountType = "discount_type"
        case startDate = "start_date"
        case endDate = "end_date"
        case partnerName = "partner_name"
        case partnerLogo = "partner_logo"
        case terms
        case tags
        case category
        case isActiveFromAPI = "is_active"
        case createdAt = "created_at"
        case requiresMembership = "requires_membership"
        case requirements
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID'yi Int veya String olarak handle et
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        title = try container.decode(String.self, forKey: .title)
        description = try container.decodeIfPresent(String.self, forKey: .description) ?? ""
        shortDescription = try container.decodeIfPresent(String.self, forKey: .shortDescription)
        offerText = try container.decodeIfPresent(String.self, forKey: .offerText)
        
        // Community ID - Int veya String
        if let intCommunityId = try? container.decode(Int.self, forKey: .communityId) {
            communityId = String(intCommunityId)
        } else {
            communityId = try container.decodeIfPresent(String.self, forKey: .communityId) ?? ""
        }
        
        communityName = try container.decodeIfPresent(String.self, forKey: .communityName) ?? ""
        university = try container.decodeIfPresent(String.self, forKey: .university)
        imageURL = try container.decodeIfPresent(String.self, forKey: .imageURL)
        imagePath = try container.decodeIfPresent(String.self, forKey: .imagePath)
        discount = try container.decodeIfPresent(Double.self, forKey: .discount)
        discountPercentage = try container.decodeIfPresent(Int.self, forKey: .discountPercentage)
        
        // Discount type
        if let discountTypeString = try? container.decode(String.self, forKey: .discountType) {
            discountType = DiscountType(rawValue: discountTypeString) ?? .special
        } else {
            discountType = .special
        }
        
        // Date decoding
        if let startDateString = try? container.decode(String.self, forKey: .startDate) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: startDateString) {
                startDate = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd"
                startDate = dateFormatter.date(from: startDateString) ?? Date()
            }
        } else {
            startDate = Date()
        }
        
        if let endDateString = try? container.decode(String.self, forKey: .endDate) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: endDateString) {
                endDate = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd"
                endDate = dateFormatter.date(from: endDateString) ?? Date()
            }
        } else {
            endDate = Date()
        }
        
        partnerName = try container.decodeIfPresent(String.self, forKey: .partnerName)
        partnerLogo = try container.decodeIfPresent(String.self, forKey: .partnerLogo)
        terms = try container.decodeIfPresent(String.self, forKey: .terms)
        tags = try container.decodeIfPresent([String].self, forKey: .tags) ?? []
        
        // Category
        if let categoryString = try? container.decode(String.self, forKey: .category) {
            category = CampaignCategory(rawValue: categoryString) ?? .other
        } else {
            category = .other
        }
        
        code = try container.decodeIfPresent(String.self, forKey: .code)
        isActiveFromAPI = try container.decodeIfPresent(Bool.self, forKey: .isActiveFromAPI)
        requiresMembership = try container.decodeIfPresent(Bool.self, forKey: .requiresMembership)
        requirements = try container.decodeIfPresent([CampaignRequirement].self, forKey: .requirements)
        
        // Created at
        if let createdAtString = try? container.decodeIfPresent(String.self, forKey: .createdAt) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            createdAt = formatter.date(from: createdAtString)
        } else {
            createdAt = nil
        }
    }
    
    init(id: String, title: String, description: String, shortDescription: String? = nil, offerText: String? = nil, communityId: String, communityName: String, university: String? = nil, imageURL: String? = nil, imagePath: String? = nil, discount: Double? = nil, discountPercentage: Int? = nil, discountType: DiscountType = .special, startDate: Date, endDate: Date, partnerName: String? = nil, partnerLogo: String? = nil, terms: String? = nil, tags: [String] = [], category: CampaignCategory = .other, code: String? = nil, isActiveFromAPI: Bool? = nil, createdAt: Date? = nil, requiresMembership: Bool? = nil, requirements: [CampaignRequirement]? = nil) {
        self.id = id
        self.title = title
        self.description = description
        self.shortDescription = shortDescription
        self.offerText = offerText
        self.communityId = communityId
        self.communityName = communityName
        self.university = university
        self.imageURL = imageURL
        self.imagePath = imagePath
        // Discount hesaplama - önce discount, sonra discountPercentage
        if let discountValue = discount {
            self.discount = discountValue
        } else if let percentage = discountPercentage {
            self.discount = Double(percentage)
        } else {
            self.discount = nil
        }
        self.discountPercentage = discountPercentage
        self.discountType = discountType
        self.startDate = startDate
        self.endDate = endDate
        self.partnerName = partnerName
        self.partnerLogo = partnerLogo
        self.terms = terms
        self.tags = tags
        self.category = category
        self.code = code
        self.isActiveFromAPI = isActiveFromAPI
        self.createdAt = createdAt
        self.requiresMembership = requiresMembership
        self.requirements = requirements
    }
    
    enum DiscountType: String, Codable {
        case percentage = "percentage"
        case fixed = "fixed"
        case special = "special"
    }
    
    enum CampaignCategory: String, Codable, CaseIterable {
        case food = "Yemek"
        case shopping = "Alışveriş"
        case entertainment = "Eğlence"
        case education = "Eğitim"
        case services = "Hizmetler"
        case other = "Diğer"
        
        var icon: String {
            switch self {
            case .food: return "fork.knife"
            case .shopping: return "bag.fill"
            case .entertainment: return "tv.fill"
            case .education: return "book.fill"
            case .services: return "wrench.and.screwdriver.fill"
            case .other: return "tag.fill"
            }
        }
        
        var color: Color {
            switch self {
            case .food: return Color(hex: "f59e0b")
            case .shopping: return Color(hex: "8b5cf6")
            case .entertainment: return Color(hex: "ec4899")
            case .education: return Color(hex: "3b82f6")
            case .services: return Color(hex: "10b981")
            case .other: return Color(hex: "6b7280")
            }
        }
    }
    
    var formattedDiscount: String {
        if let discountPercentage = discountPercentage {
            return "%\(discountPercentage) İndirim"
        }
        if let discount = discount {
            switch discountType {
            case .percentage:
                return "%\(Int(discount)) İndirim"
            case .fixed:
                return "\(Int(discount))₺ İndirim"
            case .special:
                return "Özel Fırsat"
            }
        }
        if let offerText = offerText, !offerText.isEmpty {
            return offerText
        }
        return "Özel Kampanya"
    }
    
    var isActive: Bool {
        let now = Date()
        // API'den gelen is_active değerini kontrol et, yoksa tarih aralığına bak
        if let apiActive = isActiveFromAPI {
            return apiActive && now >= startDate && now <= endDate
        }
        return now >= startDate && now <= endDate
    }
    
    var daysRemaining: Int {
        let calendar = Calendar.current
        let components = calendar.dateComponents([.day], from: Date(), to: endDate)
        return max(0, components.day ?? 0)
    }
}

// MARK: - Campaign Requirement
struct CampaignRequirement: Codable, Identifiable, Hashable {
    let id: String
    let type: RequirementType
    let description: String
    let isFulfilled: Bool?
    
    enum RequirementType: String, Codable, Hashable {
        case membership = "membership" // Topluluğa üye olma
        case verified = "verified" // Doğrulanmış üye
        case boardMember = "board_member" // Yönetim kurulu üyesi
        case custom = "custom" // Özel şart
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case type
        case description
        case isFulfilled = "is_fulfilled"
    }
}

// MARK: - Notification Model
struct AppNotification: Identifiable, Codable {
    let id: String
    let title: String
    let message: String
    let type: NotificationType
    let date: Date
    var isRead: Bool
    let relatedId: String?
    let relatedType: RelatedType?
    let actionURL: String?
    
    enum NotificationType: String, Codable {
        case event = "event"
        case campaign = "campaign"
        case community = "community"
        case system = "system"
        case announcement = "announcement"
    }
    
    enum RelatedType: String, Codable {
        case event = "event"
        case campaign = "campaign"
        case community = "community"
    }
    
    var icon: String {
        switch type {
        case .event: return "calendar"
        case .campaign: return "tag.fill"
        case .community: return "person.3.fill"
        case .system: return "bell.fill"
        case .announcement: return "megaphone.fill"
        }
    }
    
    var color: Color {
        switch type {
        case .event: return Color(hex: "6366f1")
        case .campaign: return Color(hex: "f59e0b")
        case .community: return Color(hex: "8b5cf6")
        case .system: return Color(hex: "6b7280")
        case .announcement: return Color(hex: "3b82f6")
        }
    }
    
    var timeAgo: String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.localizedString(for: date, relativeTo: Date())
    }
}

// Hashable conformance için extension (isRead mutable olduğu için ayrı)
extension AppNotification: Hashable {
    func hash(into hasher: inout Hasher) {
        hasher.combine(id)
        hasher.combine(title)
        hasher.combine(message)
        hasher.combine(type)
        hasher.combine(date)
        hasher.combine(isRead)
        hasher.combine(relatedId)
        hasher.combine(relatedType)
        hasher.combine(actionURL)
    }
    
    static func == (lhs: AppNotification, rhs: AppNotification) -> Bool {
        lhs.id == rhs.id && lhs.isRead == rhs.isRead
    }
}

// MARK: - User Model
struct User: Identifiable, Codable {
    let id: String
    var firstName: String
    var lastName: String
    var email: String
    var phoneNumber: String?
    var university: String
    var department: String
    var fullName: String? // API'den gelen full_name
    var studentNumber: String?
    var studentId: String? // API'den gelen student_id
    var profileImageURL: String?
    var bio: String?
    var joinedDate: Date
    var createdAt: Date? // API'den gelen created_at
    var lastLogin: Date? // API'den gelen last_login
    var favoriteCommunities: [String]
    var registeredEvents: [String]
    var savedCampaigns: [String]
    var notificationSettings: NotificationSettings
    
    enum CodingKeys: String, CodingKey {
        case id
        case userId = "user_id" // Register endpoint'inden gelen user_id
        case firstName = "first_name"
        case lastName = "last_name"
        case fullName = "full_name"
        case email
        case phoneNumber = "phone_number"
        case university
        case department
        case studentNumber = "student_number"
        case studentId = "student_id"
        case profileImageURL = "profile_image_url"
        case bio
        case joinedDate = "joined_date"
        case createdAt = "created_at"
        case lastLogin = "last_login"
        case favoriteCommunities = "favorite_communities"
        case registeredEvents = "registered_events"
        case savedCampaigns = "saved_campaigns"
        case notificationSettings = "notification_settings"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID'yi Int veya String olarak handle et - önce id, sonra user_id dene
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else if let stringId = try? container.decode(String.self, forKey: .id) {
            id = stringId
        } else if let userIdInt = try? container.decode(Int.self, forKey: .userId) {
            // Register endpoint'inden gelen user_id
            id = String(userIdInt)
        } else if let userIdString = try? container.decode(String.self, forKey: .userId) {
            id = userIdString
        } else {
            // ID yoksa default değer
            id = UUID().uuidString
            #if DEBUG
            print("⚠️ User ID bulunamadı, yeni ID oluşturuldu")
            #endif
        }
        
        // First name ve last name - çok esnek
        if let fn = try? container.decode(String.self, forKey: .firstName) {
            firstName = fn
        } else {
            firstName = ""
        }
        
        if let ln = try? container.decode(String.self, forKey: .lastName) {
            lastName = ln
        } else {
            lastName = ""
        }
        
        fullName = try? container.decodeIfPresent(String.self, forKey: .fullName)
        
        // Eğer firstName veya lastName boşsa, fullName'den ayır
        if firstName.isEmpty && lastName.isEmpty, let full = fullName, !full.isEmpty {
            let components = full.components(separatedBy: " ")
            if components.count > 0 {
                firstName = components[0]
            }
            if components.count > 1 {
                lastName = components[1...].joined(separator: " ")
            }
        }
        
        // Email - zorunlu ama yoksa boş string
        if let emailValue = try? container.decode(String.self, forKey: .email) {
            email = emailValue
        } else {
            email = ""
            #if DEBUG
            print("⚠️ User email bulunamadı")
            #endif
        }
        
        phoneNumber = try? container.decodeIfPresent(String.self, forKey: .phoneNumber)
        university = (try? container.decodeIfPresent(String.self, forKey: .university)) ?? ""
        department = (try? container.decodeIfPresent(String.self, forKey: .department)) ?? ""
        studentNumber = try? container.decodeIfPresent(String.self, forKey: .studentNumber)
        studentId = try? container.decodeIfPresent(String.self, forKey: .studentId)
        profileImageURL = try? container.decodeIfPresent(String.self, forKey: .profileImageURL)
        bio = try? container.decodeIfPresent(String.self, forKey: .bio)
        
        // Date decoding - joinedDate - çok esnek
        joinedDate = Date() // Default değer
        if let joinedDateString = try? container.decodeIfPresent(String.self, forKey: .joinedDate), !joinedDateString.isEmpty {
            // Önce ISO8601 formatını dene
            let isoFormatter = ISO8601DateFormatter()
            isoFormatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = isoFormatter.date(from: joinedDateString) {
                joinedDate = date
            } else {
                // ISO8601 değilse, "yyyy-MM-dd HH:mm:ss" formatını dene
                let dateFormatter = DateFormatter()
                dateFormatter.locale = Locale(identifier: "en_US_POSIX")
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                if let date = dateFormatter.date(from: joinedDateString) {
                    joinedDate = date
                } else {
                    // Son çare: sadece tarih kısmını dene
                    dateFormatter.dateFormat = "yyyy-MM-dd"
                    if let date = dateFormatter.date(from: joinedDateString) {
                        joinedDate = date
                    }
                }
            }
        } else if let createdAtString = try? container.decodeIfPresent(String.self, forKey: .createdAt), !createdAtString.isEmpty {
            // Eğer joinedDate yoksa, createdAt'i kullan
            // Önce ISO8601 formatını dene
            let isoFormatter = ISO8601DateFormatter()
            isoFormatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = isoFormatter.date(from: createdAtString) {
                joinedDate = date
            } else {
                // ISO8601 değilse, "yyyy-MM-dd HH:mm:ss" formatını dene
                let dateFormatter = DateFormatter()
                dateFormatter.locale = Locale(identifier: "en_US_POSIX")
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                if let date = dateFormatter.date(from: createdAtString) {
                    joinedDate = date
                } else {
                    // Son çare: sadece tarih kısmını dene
                    dateFormatter.dateFormat = "yyyy-MM-dd"
                    if let date = dateFormatter.date(from: createdAtString) {
                        joinedDate = date
                    }
                }
            }
        }
        
        // Created at - çok esnek date parsing
        createdAt = nil
        if let createdAtString = try? container.decodeIfPresent(String.self, forKey: .createdAt), !createdAtString.isEmpty {
            // Önce ISO8601 formatını dene
            let isoFormatter = ISO8601DateFormatter()
            isoFormatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = isoFormatter.date(from: createdAtString) {
                createdAt = date
            } else {
                // ISO8601 değilse, "yyyy-MM-dd HH:mm:ss" formatını dene
                let dateFormatter = DateFormatter()
                dateFormatter.locale = Locale(identifier: "en_US_POSIX")
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                if let date = dateFormatter.date(from: createdAtString) {
                    createdAt = date
                } else {
                    // Son çare: sadece tarih kısmını dene
                    dateFormatter.dateFormat = "yyyy-MM-dd"
                    createdAt = dateFormatter.date(from: createdAtString)
                }
            }
        }
        
        // Last login - çok esnek date parsing
        lastLogin = nil
        if let lastLoginString = try? container.decodeIfPresent(String.self, forKey: .lastLogin), !lastLoginString.isEmpty {
            // Önce ISO8601 formatını dene
            let isoFormatter = ISO8601DateFormatter()
            isoFormatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = isoFormatter.date(from: lastLoginString) {
                lastLogin = date
            } else {
                // ISO8601 değilse, "yyyy-MM-dd HH:mm:ss" formatını dene
                let dateFormatter = DateFormatter()
                dateFormatter.locale = Locale(identifier: "en_US_POSIX")
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                if let date = dateFormatter.date(from: lastLoginString) {
                    lastLogin = date
                } else {
                    // Son çare: sadece tarih kısmını dene
                    dateFormatter.dateFormat = "yyyy-MM-dd"
                    lastLogin = dateFormatter.date(from: lastLoginString)
                }
            }
        }
        
        // Arrays - default empty arrays
        favoriteCommunities = (try? container.decodeIfPresent([String].self, forKey: .favoriteCommunities)) ?? []
        registeredEvents = (try? container.decodeIfPresent([String].self, forKey: .registeredEvents)) ?? []
        savedCampaigns = (try? container.decodeIfPresent([String].self, forKey: .savedCampaigns)) ?? []
        
        // Notification settings
        notificationSettings = (try? container.decodeIfPresent(NotificationSettings.self, forKey: .notificationSettings)) ?? NotificationSettings()
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        
        try container.encode(id, forKey: .id)
        try container.encode(firstName, forKey: .firstName)
        try container.encode(lastName, forKey: .lastName)
        try container.encodeIfPresent(fullName, forKey: .fullName)
        try container.encode(email, forKey: .email)
        try container.encodeIfPresent(phoneNumber, forKey: .phoneNumber)
        try container.encode(university, forKey: .university)
        try container.encode(department, forKey: .department)
        try container.encodeIfPresent(studentNumber, forKey: .studentNumber)
        try container.encodeIfPresent(studentId, forKey: .studentId)
        try container.encodeIfPresent(profileImageURL, forKey: .profileImageURL)
        try container.encodeIfPresent(bio, forKey: .bio)
        
        // Date encoding
        let dateFormatter = ISO8601DateFormatter()
        dateFormatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        try container.encode(dateFormatter.string(from: joinedDate), forKey: .joinedDate)
        
        if let createdAt = createdAt {
            try container.encode(dateFormatter.string(from: createdAt), forKey: .createdAt)
        }
        
        if let lastLogin = lastLogin {
            try container.encode(dateFormatter.string(from: lastLogin), forKey: .lastLogin)
        }
        
        try container.encode(favoriteCommunities, forKey: .favoriteCommunities)
        try container.encode(registeredEvents, forKey: .registeredEvents)
        try container.encode(savedCampaigns, forKey: .savedCampaigns)
        try container.encode(notificationSettings, forKey: .notificationSettings)
    }
    
    struct NotificationSettings: Codable {
        var eventReminders: Bool = true
        var campaignUpdates: Bool = true
        var communityAnnouncements: Bool = true
        var systemNotifications: Bool = true
        
        enum CodingKeys: String, CodingKey {
            case eventReminders = "event_reminders"
            case campaignUpdates = "campaign_updates"
            case communityAnnouncements = "community_announcements"
            case systemNotifications = "system_notifications"
        }
    }
    
    var displayName: String {
        if let full = fullName, !full.isEmpty {
            return full
        }
        // firstName ve lastName boş olabilir, güvenli birleştirme
        let fn = firstName.isEmpty ? "" : firstName
        let ln = lastName.isEmpty ? "" : lastName
        if !fn.isEmpty && !ln.isEmpty {
            return "\(fn) \(ln)"
        } else if !fn.isEmpty {
            return fn
        } else if !ln.isEmpty {
            return ln
        } else if !email.isEmpty {
            return email
        } else {
            return "Kullanıcı"
        }
    }
    
    init(id: String, firstName: String, lastName: String, email: String, phoneNumber: String? = nil, university: String, department: String, studentNumber: String? = nil, studentId: String? = nil, profileImageURL: String? = nil, bio: String? = nil, joinedDate: Date, createdAt: Date? = nil, lastLogin: Date? = nil, favoriteCommunities: [String] = [], registeredEvents: [String] = [], savedCampaigns: [String] = [], notificationSettings: NotificationSettings = NotificationSettings(), fullName: String? = nil) {
        self.id = id
        self.firstName = firstName
        self.lastName = lastName
        self.email = email
        self.phoneNumber = phoneNumber
        self.university = university
        self.department = department
        self.studentNumber = studentNumber ?? studentId
        self.studentId = studentId
        self.profileImageURL = profileImageURL
        self.bio = bio
        self.joinedDate = joinedDate
        self.createdAt = createdAt
        self.lastLogin = lastLogin
        self.favoriteCommunities = favoriteCommunities
        self.registeredEvents = registeredEvents
        self.savedCampaigns = savedCampaigns
        self.notificationSettings = notificationSettings
        self.fullName = fullName
    }
}

// MARK: - Board Member Model
struct BoardMember: Identifiable, Hashable, Codable {
    let id: String
    let name: String
    let fullName: String? // API'den gelen full_name
    let role: String
    let email: String?
    let contactEmail: String? // API'den gelen contact_email
    let phone: String?
    let profileImageURL: String?
    let photoPath: String? // API'den gelen photo_path
    let communityId: String
    let bio: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case name
        case fullName = "full_name"
        case role
        case email
        case contactEmail = "contact_email"
        case phone
        case profileImageURL = "profile_image_url"
        case photoPath = "photo_path"
        case communityId = "community_id"
        case bio
    }
    
    init(id: String, name: String, fullName: String? = nil, role: String, email: String? = nil, contactEmail: String? = nil, phone: String? = nil, profileImageURL: String? = nil, photoPath: String? = nil, communityId: String, bio: String? = nil) {
        self.id = id
        self.name = name
        self.fullName = fullName
        self.role = role
        self.email = email ?? contactEmail
        self.contactEmail = contactEmail
        self.phone = phone
        self.profileImageURL = profileImageURL
        self.photoPath = photoPath
        self.communityId = communityId
        self.bio = bio
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID'yi Int veya String olarak handle et - çok güvenli
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else if let stringId = try? container.decode(String.self, forKey: .id), !stringId.isEmpty {
            id = stringId
        } else {
            id = "0" // Default değer
        }
        
        // Name - full_name varsa onu kullan, yoksa name - çok güvenli
        // try? decodeIfPresent -> String?? (double optional), iki kez unwrap et
        let fullNameDecoded = (try? container.decodeIfPresent(String.self, forKey: .fullName)) ?? nil
        if let fullNameValue = fullNameDecoded, !fullNameValue.isEmpty {
            name = fullNameValue.trimmingCharacters(in: .whitespacesAndNewlines)
            self.fullName = fullNameValue.trimmingCharacters(in: .whitespacesAndNewlines)
        } else {
            let nameValueDecoded = (try? container.decodeIfPresent(String.self, forKey: .name)) ?? nil
            if let nameVal = nameValueDecoded, !nameVal.isEmpty {
                name = nameVal.trimmingCharacters(in: .whitespacesAndNewlines)
            } else {
                name = "İsimsiz Üye"
            }
            fullName = fullNameDecoded?.trimmingCharacters(in: .whitespacesAndNewlines)
        }
        
        // Role - boş olsa bile default değer
        // try? decodeIfPresent -> String?? (double optional), iki kez unwrap et
        let roleDecoded = (try? container.decodeIfPresent(String.self, forKey: .role)) ?? nil
        if let roleValue = roleDecoded, !roleValue.isEmpty {
            role = roleValue.trimmingCharacters(in: .whitespacesAndNewlines)
        } else {
            role = "Üye" // Default değer
        }
        
        // Email - email veya contact_email - güvenli
        let emailDecoded = try? container.decodeIfPresent(String.self, forKey: .email)?.trimmingCharacters(in: .whitespacesAndNewlines)
        email = (emailDecoded?.isEmpty == false) ? emailDecoded : nil
        
        let contactEmailDecoded = try? container.decodeIfPresent(String.self, forKey: .contactEmail)?.trimmingCharacters(in: .whitespacesAndNewlines)
        contactEmail = (contactEmailDecoded?.isEmpty == false) ? contactEmailDecoded : nil
        
        // Phone - güvenli
        let phoneDecoded = try? container.decodeIfPresent(String.self, forKey: .phone)?.trimmingCharacters(in: .whitespacesAndNewlines)
        phone = (phoneDecoded?.isEmpty == false) ? phoneDecoded : nil
        
        // Profile Image - profile_image_url veya photo_path - güvenli
        let profileImageURLDecoded = try? container.decodeIfPresent(String.self, forKey: .profileImageURL)?.trimmingCharacters(in: .whitespacesAndNewlines)
        profileImageURL = (profileImageURLDecoded?.isEmpty == false) ? profileImageURLDecoded : nil
        
        let photoPathDecoded = try? container.decodeIfPresent(String.self, forKey: .photoPath)?.trimmingCharacters(in: .whitespacesAndNewlines)
        photoPath = (photoPathDecoded?.isEmpty == false) ? photoPathDecoded : nil
        
        // Community ID - Int veya String - güvenli
        // try? decodeIfPresent -> String?? (double optional), iki kez unwrap et
        if let intCommunityId = try? container.decode(Int.self, forKey: .communityId) {
            communityId = String(intCommunityId)
        } else {
            let stringCommunityIdDecoded = (try? container.decodeIfPresent(String.self, forKey: .communityId)) ?? nil
            if let stringCommunityId = stringCommunityIdDecoded, !stringCommunityId.isEmpty {
                communityId = stringCommunityId.trimmingCharacters(in: .whitespacesAndNewlines)
            } else {
                communityId = "" // Default değer
            }
        }
        
        // Bio - güvenli
        let bioDecoded = try? container.decodeIfPresent(String.self, forKey: .bio)?.trimmingCharacters(in: .whitespacesAndNewlines)
        bio = (bioDecoded?.isEmpty == false) ? bioDecoded : nil
    }
}

// MARK: - Member Model
struct Member: Identifiable, Hashable, Codable {
    let id: String
    let fullName: String
    let email: String?
    let studentId: String?
    let phoneNumber: String?
    let registrationDate: Date?
    let communityId: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case fullName = "full_name"
        case email
        case studentId = "student_id"
        case phoneNumber = "phone_number"
        case registrationDate = "registration_date"
        case communityId = "community_id"
    }
    
    init(id: String, fullName: String, email: String? = nil, studentId: String? = nil, phoneNumber: String? = nil, registrationDate: Date? = nil, communityId: String? = nil) {
        self.id = id
        self.fullName = fullName
        self.email = email
        self.studentId = studentId
        self.phoneNumber = phoneNumber
        self.registrationDate = registrationDate
        self.communityId = communityId
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // id'yi hem String hem Int olarak handle et - çok güvenli
        if let idString = try? container.decode(String.self, forKey: .id), !idString.isEmpty {
            self.id = idString
        } else if let idInt = try? container.decode(Int.self, forKey: .id) {
            self.id = String(idInt)
        } else {
            // ID yoksa veya geçersizse default değer
            self.id = "0"
        }
        
        // full_name - çok güvenli decode
        if let fullNameString = try? container.decode(String.self, forKey: .fullName), !fullNameString.isEmpty {
            self.fullName = fullNameString.trimmingCharacters(in: .whitespacesAndNewlines)
        } else if let fullNameString = try? container.decodeIfPresent(String.self, forKey: .fullName), !fullNameString.isEmpty {
            self.fullName = fullNameString.trimmingCharacters(in: .whitespacesAndNewlines)
        } else {
            self.fullName = "İsimsiz Üye" // Default değer
        }
        
        // Optional alanlar - güvenli decode
        let emailDecoded = try? container.decodeIfPresent(String.self, forKey: .email)?.trimmingCharacters(in: .whitespacesAndNewlines)
        self.email = (emailDecoded?.isEmpty == false) ? emailDecoded : nil
        
        let studentIdDecoded = try? container.decodeIfPresent(String.self, forKey: .studentId)?.trimmingCharacters(in: .whitespacesAndNewlines)
        self.studentId = (studentIdDecoded?.isEmpty == false) ? studentIdDecoded : nil
        
        let phoneNumberDecoded = try? container.decodeIfPresent(String.self, forKey: .phoneNumber)?.trimmingCharacters(in: .whitespacesAndNewlines)
        self.phoneNumber = (phoneNumberDecoded?.isEmpty == false) ? phoneNumberDecoded : nil
        
        let communityIdDecoded = try? container.decodeIfPresent(String.self, forKey: .communityId)?.trimmingCharacters(in: .whitespacesAndNewlines)
        self.communityId = (communityIdDecoded?.isEmpty == false) ? communityIdDecoded : nil
        
        // registration_date'i flexible decode et
        if let dateString = try? container.decodeIfPresent(String.self, forKey: .registrationDate), !dateString.isEmpty {
            let dateFormatter = DateFormatter()
            dateFormatter.locale = Locale(identifier: "en_US_POSIX")
            dateFormatter.dateFormat = "yyyy-MM-dd"
            if let date = dateFormatter.date(from: dateString) {
                self.registrationDate = date
            } else {
                // Alternatif formatları dene
                dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                self.registrationDate = dateFormatter.date(from: dateString)
            }
        } else {
            self.registrationDate = nil
        }
    }
}

// MARK: - Survey Model
struct Survey: Identifiable, Hashable, Codable {
    let id: Int
    let eventId: Int
    let title: String
    let description: String?
    let isActive: Bool
    let questions: [SurveyQuestion]
    let createdAt: String?
    let updatedAt: String?
    let hasUserResponse: Bool
    
    enum CodingKeys: String, CodingKey {
        case id
        case eventId = "event_id"
        case title
        case description
        case isActive = "is_active"
        case questions
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case hasUserResponse = "has_user_response"
    }
    
    init(id: Int, eventId: Int, title: String, description: String? = nil, isActive: Bool = true, questions: [SurveyQuestion] = [], createdAt: String? = nil, updatedAt: String? = nil, hasUserResponse: Bool = false) {
        self.id = id
        self.eventId = eventId
        self.title = title
        self.description = description
        self.isActive = isActive
        self.questions = questions
        self.createdAt = createdAt
        self.updatedAt = updatedAt
        self.hasUserResponse = hasUserResponse
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = intId
        } else if let stringId = try? container.decode(String.self, forKey: .id), let intId = Int(stringId) {
            id = intId
        } else {
            id = 0
        }
        
        // eventId Int veya String olabilir
        if let intEventId = try? container.decode(Int.self, forKey: .eventId) {
            eventId = intEventId
        } else if let stringEventId = try? container.decode(String.self, forKey: .eventId), let intEventId = Int(stringEventId) {
            eventId = intEventId
        } else {
            eventId = 0
        }
        
        title = try container.decode(String.self, forKey: .title)
        description = try container.decodeIfPresent(String.self, forKey: .description)
        isActive = try container.decodeIfPresent(Bool.self, forKey: .isActive) ?? true
        questions = try container.decodeIfPresent([SurveyQuestion].self, forKey: .questions) ?? []
        createdAt = try container.decodeIfPresent(String.self, forKey: .createdAt)
        updatedAt = try container.decodeIfPresent(String.self, forKey: .updatedAt)
        hasUserResponse = try container.decodeIfPresent(Bool.self, forKey: .hasUserResponse) ?? false
    }
}

// MARK: - Survey Question Model
struct SurveyQuestion: Identifiable, Hashable, Codable {
    let id: Int
    let questionText: String
    let questionType: String
    let displayOrder: Int
    let options: [SurveyOption]
    let userResponse: SurveyUserResponse?
    
    enum CodingKeys: String, CodingKey {
        case id
        case questionText = "question_text"
        case questionType = "question_type"
        case displayOrder = "display_order"
        case options
        case userResponse = "user_response"
    }
    
    init(id: Int, questionText: String, questionType: String, displayOrder: Int, options: [SurveyOption] = [], userResponse: SurveyUserResponse? = nil) {
        self.id = id
        self.questionText = questionText
        self.questionType = questionType
        self.displayOrder = displayOrder
        self.options = options
        self.userResponse = userResponse
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = intId
        } else if let stringId = try? container.decode(String.self, forKey: .id), let intId = Int(stringId) {
            id = intId
        } else {
            id = 0
        }
        
        questionText = try container.decode(String.self, forKey: .questionText)
        questionType = try container.decodeIfPresent(String.self, forKey: .questionType) ?? "multiple_choice"
        
        // displayOrder veya questionOrder
        if let order = try? container.decode(Int.self, forKey: .displayOrder) {
            displayOrder = order
        } else if let order = try? container.decode(Int.self, forKey: CodingKeys(stringValue: "question_order")!) {
            displayOrder = order
        } else {
            displayOrder = 0
        }
        
        // Options - yeni format (SurveyOption array) veya eski format (String array)
        if let optionArray = try? container.decode([SurveyOption].self, forKey: .options) {
            options = optionArray
        } else if let stringArray = try? container.decode([String].self, forKey: .options) {
            // Eski format - String array'i SurveyOption array'e çevir
            options = stringArray.enumerated().map { index, text in
                SurveyOption(id: index, text: text, order: index)
            }
        } else {
            options = []
        }
        
        // User response (kullanıcının daha önce verdiği cevap)
        userResponse = try? container.decodeIfPresent(SurveyUserResponse.self, forKey: .userResponse)
    }
    
    func hash(into hasher: inout Hasher) {
        hasher.combine(id)
        hasher.combine(questionText)
        hasher.combine(questionType)
        hasher.combine(displayOrder)
        hasher.combine(options)
        hasher.combine(userResponse)
    }
    
    static func == (lhs: SurveyQuestion, rhs: SurveyQuestion) -> Bool {
        return lhs.id == rhs.id &&
               lhs.questionText == rhs.questionText &&
               lhs.questionType == rhs.questionType &&
               lhs.displayOrder == rhs.displayOrder &&
               lhs.options == rhs.options &&
               lhs.userResponse == rhs.userResponse
    }
}

// MARK: - Survey User Response Model
struct SurveyUserResponse: Codable, Hashable, Equatable {
    let optionId: Int?
    let responseText: String?
    
    enum CodingKeys: String, CodingKey {
        case optionId = "option_id"
        case responseText = "response_text"
    }
    
    func hash(into hasher: inout Hasher) {
        hasher.combine(optionId)
        hasher.combine(responseText)
    }
    
    static func == (lhs: SurveyUserResponse, rhs: SurveyUserResponse) -> Bool {
        return lhs.optionId == rhs.optionId && lhs.responseText == rhs.responseText
    }
}

// MARK: - Survey Option Model
struct SurveyOption: Identifiable, Hashable, Codable {
    let id: Int
    let text: String
    let order: Int
    
    enum CodingKeys: String, CodingKey {
        case id, text, order
    }
    
    init(id: Int, text: String, order: Int) {
        self.id = id
        self.text = text
        self.order = order
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = intId
        } else if let stringId = try? container.decode(String.self, forKey: .id), let intId = Int(stringId) {
            id = intId
        } else {
            id = 0
        }
        
        text = try container.decode(String.self, forKey: .text)
        order = try container.decodeIfPresent(Int.self, forKey: .order) ?? 0
    }
}

// MARK: - Survey Response Item Model
struct SurveyResponseItem: Codable {
    let questionId: Int
    let optionId: Int?
    let responseText: String?
    
    enum CodingKeys: String, CodingKey {
        case questionId = "question_id"
        case optionId = "option_id"
        case responseText = "response_text"
    }
}

// MARK: - Survey Submission Response Model
struct SurveySubmissionResponse: Codable {
    let surveyId: String
    let message: String?
    
    enum CodingKeys: String, CodingKey {
        case surveyId = "survey_id"
        case message
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // survey_id Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .surveyId) {
            surveyId = String(intId)
        } else {
            surveyId = try container.decode(String.self, forKey: .surveyId)
        }
        
        message = try container.decodeIfPresent(String.self, forKey: .message)
    }
}

// MARK: - Device Token Response Model
struct DeviceTokenResponse: Codable {
    let id: Int
    let message: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case message
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // id Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = intId
        } else if let stringId = try? container.decode(String.self, forKey: .id), let intId = Int(stringId) {
            id = intId
        } else {
            id = 0
        }
        
        message = try container.decodeIfPresent(String.self, forKey: .message)
    }
}

// MARK: - RSVP Model
struct RSVP: Identifiable, Hashable, Codable {
    let id: String
    let eventId: String
    let memberName: String
    let memberEmail: String?
    let memberPhone: String?
    let status: RSVPStatus
    let createdAt: Date
    
    enum RSVPStatus: String, Codable {
        case attending = "attending"
        case notAttending = "not_attending"
        case maybe = "maybe"
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case eventId = "event_id"
        case memberName = "member_name"
        case memberEmail = "member_email"
        case memberPhone = "member_phone"
        case status
        case createdAt = "created_at"
    }
    
    init(id: String, eventId: String, memberName: String, memberEmail: String? = nil, memberPhone: String? = nil, status: RSVPStatus, createdAt: Date) {
        self.id = id
        self.eventId = eventId
        self.memberName = memberName
        self.memberEmail = memberEmail
        self.memberPhone = memberPhone
        self.status = status
        self.createdAt = createdAt
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // id Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        // event_id Int veya String olabilir
        if let intEventId = try? container.decode(Int.self, forKey: .eventId) {
            eventId = String(intEventId)
        } else {
            eventId = try container.decode(String.self, forKey: .eventId)
        }
        
        memberName = try container.decode(String.self, forKey: .memberName)
        memberEmail = try? container.decodeIfPresent(String.self, forKey: .memberEmail)
        memberPhone = try? container.decodeIfPresent(String.self, forKey: .memberPhone)
        status = try container.decode(RSVPStatus.self, forKey: .status)
        
        // createdAt flexible date parsing
        if let dateString = try? container.decode(String.self, forKey: .createdAt) {
            let dateFormatter = DateFormatter()
            dateFormatter.locale = Locale(identifier: "en_US_POSIX")
            dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
            if let date = dateFormatter.date(from: dateString) {
                createdAt = date
            } else {
                dateFormatter.dateFormat = "yyyy-MM-dd"
                createdAt = dateFormatter.date(from: dateString) ?? Date()
            }
        } else {
            createdAt = Date()
        }
    }
}

// MARK: - RSVP Status Response Model (User's RSVP status)
struct RSVPStatusResponse: Codable {
    let hasRsvp: Bool
    let id: Int?
    let status: String?
    let memberName: String?
    let memberEmail: String?
    let memberPhone: String?
    let createdAt: String?
    let updatedAt: String?
    let eventId: Int
    
    enum CodingKeys: String, CodingKey {
        case hasRsvp = "has_rsvp"
        case id, status
        case memberName = "member_name"
        case memberEmail = "member_email"
        case memberPhone = "member_phone"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case eventId = "event_id"
    }
}

// MARK: - RSVP Response Model (List)
struct RSVPResponse: Codable {
    let eventId: Int
    let rsvps: [RSVP]?
    let statistics: RSVPStatistics?
    
    enum CodingKeys: String, CodingKey {
        case eventId = "event_id"
        case rsvps
        case statistics
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // event_id Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .eventId) {
            eventId = intId
        } else if let stringId = try? container.decode(String.self, forKey: .eventId), let intId = Int(stringId) {
            eventId = intId
        } else {
            eventId = 0
        }
        
        rsvps = try container.decodeIfPresent([RSVP].self, forKey: .rsvps)
        statistics = try container.decodeIfPresent(RSVPStatistics.self, forKey: .statistics)
    }
}

// MARK: - RSVP Create/Update Response Model
struct RSVPCreateResponse: Codable {
    let id: Int
    let status: String
    let action: String
    let statistics: RSVPStatistics
    
    enum CodingKeys: String, CodingKey {
        case id, status, action, statistics
    }
}

// MARK: - RSVP Statistics Model
struct RSVPStatistics: Codable {
    let attendingCount: Int
    let notAttendingCount: Int
    let totalCount: Int
    
    enum CodingKeys: String, CodingKey {
        case attendingCount = "attending_count"
        case notAttendingCount = "not_attending_count"
        case totalCount = "total_count"
    }
}

// MARK: - Member Registration Response Model
struct MembershipStatus: Codable {
    let status: String // "none", "pending", "approved", "rejected", "member"
    let isMember: Bool
    let isPending: Bool
    let requestId: String?
    let createdAt: String?
}

struct MemberRegistrationResponse: Codable {
    let memberId: String
    
    enum CodingKeys: String, CodingKey {
        case memberId = "member_id"
    }
    
    init(memberId: String) {
        self.memberId = memberId
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        // member_id Int veya String olabilir
        if let intId = try? container.decode(Int.self, forKey: .memberId) {
            memberId = String(intId)
        } else {
            memberId = try container.decode(String.self, forKey: .memberId)
        }
    }
}

// MARK: - University Model
struct University: Identifiable, Hashable, Codable {
    let id: String
    let name: String
    let communityCount: Int
    
    enum CodingKeys: String, CodingKey {
        case id
        case name
        case communityCount = "community_count"
    }
}

// MARK: - Product Model
struct Product: Identifiable, Hashable, Codable {
    let id: String
    let name: String
    let description: String?
    let price: Double
    let stock: Int
    let category: String
    let imagePath: String?
    let imageURL: String? // Geriye dönük uyumluluk için (ilk görsel)
    let imageURLs: [String]? // Çoklu görsel desteği
    let status: String
    let communityId: String
    let commissionRate: Double?
    let iyzicoCommission: Double?
    let platformCommission: Double?
    let totalPrice: Double?
    let createdAt: Date?
    
    enum CodingKeys: String, CodingKey {
        case id
        case name
        case description
        case price
        case stock
        case category
        case imagePath = "image_path"
        case imageURL = "image_url"
        case imageURLs = "image_urls"
        case status
        case communityId = "community_id"
        case commissionRate = "commission_rate"
        case iyzicoCommission = "iyzico_commission"
        case platformCommission = "platform_commission"
        case totalPrice = "total_price"
        case createdAt = "created_at"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID'yi Int veya String olarak handle et
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        name = try container.decode(String.self, forKey: .name)
        description = try container.decodeIfPresent(String.self, forKey: .description)
        price = try container.decode(Double.self, forKey: .price)
        stock = try container.decodeIfPresent(Int.self, forKey: .stock) ?? 0
        category = try container.decodeIfPresent(String.self, forKey: .category) ?? "Genel"
        imagePath = try container.decodeIfPresent(String.self, forKey: .imagePath)
        imageURL = try container.decodeIfPresent(String.self, forKey: .imageURL)
        // Çoklu görsel desteği - image_urls array'i varsa kullan, yoksa image_url'den array oluştur
        if let imageURLsArray = try? container.decode([String].self, forKey: .imageURLs) {
            self.imageURLs = imageURLsArray.isEmpty ? nil : imageURLsArray
        } else if let singleImageURL = imageURL, !singleImageURL.isEmpty {
            // Geriye dönük uyumluluk: tek image_url varsa array'e çevir
            self.imageURLs = [singleImageURL]
        } else {
            self.imageURLs = nil
        }
        status = try container.decodeIfPresent(String.self, forKey: .status) ?? "active"
        
        // Community ID - Int veya String
        if let intCommunityId = try? container.decode(Int.self, forKey: .communityId) {
            communityId = String(intCommunityId)
        } else {
            communityId = try container.decodeIfPresent(String.self, forKey: .communityId) ?? ""
        }
        
        commissionRate = try container.decodeIfPresent(Double.self, forKey: .commissionRate)
        iyzicoCommission = try container.decodeIfPresent(Double.self, forKey: .iyzicoCommission)
        platformCommission = try container.decodeIfPresent(Double.self, forKey: .platformCommission)
        totalPrice = try container.decodeIfPresent(Double.self, forKey: .totalPrice)
        
        // Created at
        if let createdAtString = try? container.decodeIfPresent(String.self, forKey: .createdAt) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            createdAt = formatter.date(from: createdAtString)
        } else {
            createdAt = nil
        }
    }
    
    var formattedPrice: String {
        return String(format: "%.2f", price).replacingOccurrences(of: ".", with: ",") + " ₺"
    }
    
    var formattedTotalPrice: String {
        if let total = totalPrice {
            return String(format: "%.2f", total).replacingOccurrences(of: ".", with: ",") + " ₺"
        }
        return formattedPrice
    }
    
    var isAvailable: Bool {
        return status == "active" && stock > 0
    }
    
    // MARK: - Encodable
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        
        try container.encode(id, forKey: .id)
        try container.encode(name, forKey: .name)
        try container.encodeIfPresent(description, forKey: .description)
        try container.encode(price, forKey: .price)
        try container.encode(stock, forKey: .stock)
        try container.encode(category, forKey: .category)
        try container.encodeIfPresent(imagePath, forKey: .imagePath)
        try container.encodeIfPresent(imageURL, forKey: .imageURL)
        try container.encodeIfPresent(imageURLs, forKey: .imageURLs)
        try container.encode(status, forKey: .status)
        try container.encode(communityId, forKey: .communityId)
        try container.encodeIfPresent(commissionRate, forKey: .commissionRate)
        try container.encodeIfPresent(iyzicoCommission, forKey: .iyzicoCommission)
        try container.encodeIfPresent(platformCommission, forKey: .platformCommission)
        try container.encodeIfPresent(totalPrice, forKey: .totalPrice)
        
        // Created at
        if let createdAt = createdAt {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            try container.encode(formatter.string(from: createdAt), forKey: .createdAt)
        }
    }
}

// MARK: - Cart Item Model
struct CartItem: Identifiable, Hashable {
    let id: String
    let product: Product
    var quantity: Int
    
    init(product: Product, quantity: Int = 1) {
        self.id = product.id
        self.product = product
        self.quantity = quantity
    }
    
    var totalPrice: Double {
        let price = product.totalPrice ?? product.price
        return price * Double(quantity)
    }
    
    var formattedTotalPrice: String {
        return String(format: "%.2f", totalPrice).replacingOccurrences(of: ".", with: ",") + " ₺"
    }
}
// MARK: - Color Extension
extension Color {
    init(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0
        Scanner(string: hex).scanHexInt64(&int)
        let a, r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (a, r, g, b) = (255, (int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (a, r, g, b) = (255, int >> 16, int >> 8 & 0xFF, int & 0xFF)
        case 8: // ARGB (32-bit)
            (a, r, g, b) = (int >> 24, int >> 16 & 0xFF, int >> 8 & 0xFF, int & 0xFF)
        default:
            (a, r, g, b) = (255, 0, 0, 0)
        }
        self.init(
            .sRGB,
            red: Double(r) / 255,
            green: Double(g) / 255,
            blue: Double(b) / 255,
            opacity: Double(a) / 255
        )
    }
}

