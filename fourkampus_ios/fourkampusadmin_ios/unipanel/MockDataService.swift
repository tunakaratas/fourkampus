//
//  MockDataService.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation

class MockDataService {
    static let shared = MockDataService()
    
    private init() {}
    
    func getCommunities() -> [Community] {
        return [
            Community(
                id: "1",
                name: "Bilgisayar Mühendisliği",
                description: "Teknoloji ve yazılım alanında faaliyet gösteren, öğrencileri bilgisayar bilimleri konusunda geliştirmeyi amaçlayan aktif bir topluluk. Düzenli workshop'lar, hackathon'lar ve teknik seminerler düzenliyoruz.",
                shortDescription: "Teknoloji ve yazılım topluluğu",
                memberCount: 150,
                eventCount: 25,
                campaignCount: 5,
                boardCount: 8,
                imageURL: nil,
                categories: ["Teknoloji"],
                tags: ["Yazılım", "Teknoloji", "Programlama", "AI"],
                isVerified: true,
                createdAt: Date().addingTimeInterval(-86400 * 365),
                contactEmail: "cs@university.edu",
                website: "https://cs.university.edu",
                socialLinks: SocialLinks(
                    instagram: "@cs_community",
                    twitter: "@cs_uni",
                    linkedin: "cs-community",
                    facebook: "cscommunity"
                )
            ),
            Community(
                id: "2",
                name: "Elektrik Elektronik",
                description: "Elektronik projeler, robotik ve IoT alanlarında çalışmalar yapan, öğrencilere pratik deneyim kazandıran bir topluluk.",
                shortDescription: "Elektronik projeler ve etkinlikler",
                memberCount: 120,
                eventCount: 18,
                campaignCount: 3,
                boardCount: 6,
                imageURL: nil,
                categories: ["Mühendislik"],
                tags: ["Elektronik", "Robotik", "IoT"],
                isVerified: true,
                createdAt: Date().addingTimeInterval(-86400 * 300),
                contactEmail: "ee@university.edu",
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "3",
                name: "Makine Mühendisliği",
                description: "Mühendislik ve tasarım alanında projeler geliştiren, endüstriyel uygulamalar üzerine çalışan bir topluluk.",
                shortDescription: "Mühendislik ve tasarım",
                memberCount: 90,
                eventCount: 12,
                campaignCount: 2,
                boardCount: 5,
                imageURL: nil,
                categories: ["Mühendislik"],
                tags: ["Tasarım", "Mühendislik"],
                isVerified: false,
                createdAt: Date().addingTimeInterval(-86400 * 200),
                contactEmail: nil,
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "4",
                name: "Endüstri Mühendisliği",
                description: "Süreç optimizasyonu, yönetim ve verimlilik konularında çalışmalar yapan, öğrencileri endüstriyel uygulamalara hazırlayan topluluk.",
                shortDescription: "Süreç optimizasyonu ve yönetim",
                memberCount: 80,
                eventCount: 15,
                campaignCount: 4,
                boardCount: 7,
                imageURL: nil,
                categories: ["Mühendislik"],
                tags: ["Yönetim", "Optimizasyon"],
                isVerified: true,
                createdAt: Date().addingTimeInterval(-86400 * 250),
                contactEmail: "ie@university.edu",
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "5",
                name: "İnşaat Mühendisliği",
                description: "Yapı ve proje yönetimi konularında uzmanlaşmış, öğrencilere pratik deneyim kazandıran aktif bir topluluk.",
                shortDescription: "Yapı ve proje yönetimi",
                memberCount: 110,
                eventCount: 20,
                campaignCount: 3,
                boardCount: 6,
                imageURL: nil,
                categories: ["Mühendislik"],
                tags: ["İnşaat", "Proje"],
                isVerified: true,
                createdAt: Date().addingTimeInterval(-86400 * 180),
                contactEmail: nil,
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "6",
                name: "Kimya Mühendisliği",
                description: "Kimya ve proses teknolojileri alanında araştırmalar yapan, laboratuvar çalışmaları düzenleyen bir topluluk.",
                shortDescription: "Kimya ve proses teknolojileri",
                memberCount: 75,
                eventCount: 10,
                campaignCount: 2,
                boardCount: 4,
                imageURL: nil,
                categories: ["Mühendislik"],
                tags: ["Kimya", "Proses"],
                isVerified: false,
                createdAt: Date().addingTimeInterval(-86400 * 150),
                contactEmail: nil,
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "7",
                name: "Fotoğrafçılık",
                description: "Fotoğraf sanatı ve teknikleri üzerine çalışmalar yapan, sergiler düzenleyen sanat topluluğu.",
                shortDescription: "Fotoğraf sanatı ve teknikleri",
                memberCount: 65,
                eventCount: 8,
                campaignCount: 1,
                boardCount: 3,
                imageURL: nil,
                categories: ["Sanat"],
                tags: ["Fotoğraf", "Sanat"],
                isVerified: false,
                createdAt: Date().addingTimeInterval(-86400 * 100),
                contactEmail: nil,
                website: nil,
                socialLinks: nil
            ),
            Community(
                id: "8",
                name: "Basketbol",
                description: "Basketbol sporu ve turnuvalar düzenleyen, öğrencileri spora teşvik eden aktif bir spor topluluğu.",
                shortDescription: "Basketbol sporu ve turnuvalar",
                memberCount: 45,
                eventCount: 6,
                campaignCount: 0,
                boardCount: 2,
                imageURL: nil,
                categories: ["Spor"],
                tags: ["Basketbol", "Spor"],
                isVerified: false,
                createdAt: Date().addingTimeInterval(-86400 * 80),
                contactEmail: nil,
                website: nil,
                socialLinks: nil
            )
        ]
    }
    
    func getEvents() -> [Event] {
        let calendar = Calendar.current
        let now = Date()
        
        return [
            Event(
                id: "e1",
                title: "Yazılım Geliştirme Workshop",
                description: "Modern yazılım geliştirme teknikleri, best practices ve araçlar hakkında kapsamlı bir workshop. Swift, React ve Python üzerine odaklanacağız.",
                date: calendar.date(byAdding: .day, value: 5, to: now) ?? now,
                startTime: "14:00",
                endTime: "17:00",
                location: "A101",
                locationDetails: "Bilgisayar Mühendisliği Binası",
                imageURL: nil,
                communityId: "1",
                communityName: "Bilgisayar Mühendisliği",
                category: .workshop,
                capacity: 50,
                registeredCount: 32,
                isRegistrationRequired: true,
                registrationDeadline: calendar.date(byAdding: .day, value: 3, to: now),
                tags: ["Yazılım", "Workshop", "Swift"],
                organizer: "CS Topluluğu",
                contactEmail: "cs@university.edu",
                isOnline: false,
                onlineLink: nil,
                price: nil,
                currency: nil
            ),
            Event(
                id: "e2",
                title: "AI ve Makine Öğrenmesi Semineri",
                description: "Yapay zeka ve makine öğrenmesi alanındaki son gelişmeler, uygulama alanları ve kariyer fırsatları.",
                date: calendar.date(byAdding: .day, value: 10, to: now) ?? now,
                startTime: "15:30",
                endTime: "17:30",
                location: "Konferans Salonu",
                locationDetails: "Merkez Bina",
                imageURL: nil,
                communityId: "1",
                communityName: "Bilgisayar Mühendisliği",
                category: .seminar,
                capacity: 200,
                registeredCount: 145,
                isRegistrationRequired: true,
                registrationDeadline: calendar.date(byAdding: .day, value: 8, to: now),
                tags: ["AI", "ML", "Seminer"],
                organizer: "CS Topluluğu",
                contactEmail: nil,
                isOnline: false,
                onlineLink: nil,
                price: nil,
                currency: nil
            ),
            Event(
                id: "e3",
                title: "Hackathon 2025",
                description: "48 saatlik hackathon etkinliği. Takımlar halinde projeler geliştirin ve ödüller kazanın!",
                date: calendar.date(byAdding: .day, value: 15, to: now) ?? now,
                startTime: "09:00",
                endTime: nil,
                location: "Teknoloji Merkezi",
                locationDetails: nil,
                imageURL: nil,
                communityId: "1",
                communityName: "Bilgisayar Mühendisliği",
                category: .competition,
                capacity: 100,
                registeredCount: 78,
                isRegistrationRequired: true,
                registrationDeadline: calendar.date(byAdding: .day, value: 12, to: now),
                tags: ["Hackathon", "Yarışma"],
                organizer: "CS Topluluğu",
                contactEmail: "hackathon@university.edu",
                isOnline: false,
                onlineLink: nil,
                price: nil,
                currency: nil
            ),
            Event(
                id: "e4",
                title: "Robotik Proje Sergisi",
                description: "Öğrencilerin geliştirdiği robotik projelerin sergilendiği etkinlik.",
                date: calendar.date(byAdding: .day, value: 7, to: now) ?? now,
                startTime: "10:00",
                endTime: "16:00",
                location: "Sergi Salonu",
                locationDetails: nil,
                imageURL: nil,
                communityId: "2",
                communityName: "Elektrik Elektronik",
                category: .exhibition,
                capacity: nil,
                registeredCount: 0,
                isRegistrationRequired: false,
                registrationDeadline: nil,
                tags: ["Robotik", "Sergi"],
                organizer: "EE Topluluğu",
                contactEmail: nil,
                isOnline: false,
                onlineLink: nil,
                price: nil,
                currency: nil
            ),
            Event(
                id: "e5",
                title: "Topluluk Tanışma Etkinliği",
                description: "Yeni dönem tanışma etkinliği. Tüm üyelerimizi bekliyoruz!",
                date: calendar.date(byAdding: .day, value: 3, to: now) ?? now,
                startTime: "18:00",
                endTime: "20:00",
                location: "Kampüs Kafeterya",
                locationDetails: nil,
                imageURL: nil,
                communityId: "4",
                communityName: "Endüstri Mühendisliği",
                category: .social,
                capacity: 80,
                registeredCount: 45,
                isRegistrationRequired: false,
                registrationDeadline: nil,
                tags: ["Tanışma", "Sosyal"],
                organizer: "IE Topluluğu",
                contactEmail: nil,
                isOnline: false,
                onlineLink: nil,
                price: nil,
                currency: nil
            )
        ]
    }
    
    func getCampaigns() -> [Campaign] {
        let calendar = Calendar.current
        let now = Date()
        
        return [
            Campaign(
                id: "c1",
                title: "Kafe XYZ Özel Kampanya",
                description: "Tüm üyelerimize özel %20 indirim fırsatı! Kampüs içindeki Kafe XYZ'de geçerlidir.",
                shortDescription: "%20 indirim fırsatı",
                communityId: "1",
                communityName: "Bilgisayar Mühendisliği",
                imageURL: nil,
                discount: 20,
                discountType: .percentage,
                startDate: now,
                endDate: calendar.date(byAdding: .day, value: 30, to: now) ?? now,
                partnerName: "Kafe XYZ",
                partnerLogo: nil,
                terms: "Sadece öğrenci kimliği ile geçerlidir. Diğer kampanyalarla birleştirilemez.",
                tags: ["Yemek", "İndirim"],
                category: .food
            ),
            Campaign(
                id: "c2",
                title: "Kitapçı ABC Eğitim İndirimi",
                description: "Tüm akademik kitaplarda %15 indirim. Kampüs içindeki Kitapçı ABC'de geçerlidir.",
                shortDescription: "Akademik kitaplarda %15 indirim",
                communityId: "4",
                communityName: "Endüstri Mühendisliği",
                imageURL: nil,
                discount: 15,
                discountType: .percentage,
                startDate: now,
                endDate: calendar.date(byAdding: .day, value: 45, to: now) ?? now,
                partnerName: "Kitapçı ABC",
                partnerLogo: nil,
                terms: nil,
                tags: ["Eğitim", "Kitap"],
                category: .education
            ),
            Campaign(
                id: "c3",
                title: "Sinema Özel Gösterim",
                description: "Sinema XYZ'de öğrencilere özel gösterimlerde %25 indirim!",
                shortDescription: "Sinema gösterimlerinde %25 indirim",
                communityId: "7",
                communityName: "Fotoğrafçılık",
                imageURL: nil,
                discount: 25,
                discountType: .percentage,
                startDate: calendar.date(byAdding: .day, value: -5, to: now) ?? now,
                endDate: calendar.date(byAdding: .day, value: 20, to: now) ?? now,
                partnerName: "Sinema XYZ",
                partnerLogo: nil,
                terms: nil,
                tags: ["Eğlence", "Sinema"],
                category: .entertainment
            )
        ]
    }
    
    func getNotifications() -> [AppNotification] {
        let calendar = Calendar.current
        let now = Date()
        
        return [
            AppNotification(
                id: "n1",
                title: "Yeni Etkinlik",
                message: "Yazılım Geliştirme Workshop kayıtları başladı!",
                type: .event,
                date: calendar.date(byAdding: .hour, value: -2, to: now) ?? now,
                isRead: false,
                relatedId: "e1",
                relatedType: .event,
                actionURL: nil
            ),
            AppNotification(
                id: "n2",
                title: "Yeni Kampanya",
                message: "Kafe XYZ'de %20 indirim fırsatı!",
                type: .campaign,
                date: calendar.date(byAdding: .hour, value: -5, to: now) ?? now,
                isRead: false,
                relatedId: "c1",
                relatedType: .campaign,
                actionURL: nil
            ),
            AppNotification(
                id: "n3",
                title: "Etkinlik Hatırlatıcı",
                message: "AI ve Makine Öğrenmesi Semineri yarın saat 15:30'da başlıyor.",
                type: .event,
                date: calendar.date(byAdding: .hour, value: -12, to: now) ?? now,
                isRead: true,
                relatedId: "e2",
                relatedType: .event,
                actionURL: nil
            ),
            AppNotification(
                id: "n4",
                title: "Topluluk Duyurusu",
                message: "Bilgisayar Mühendisliği topluluğu yeni üyeler arıyor!",
                type: .community,
                date: calendar.date(byAdding: .day, value: -1, to: now) ?? now,
                isRead: true,
                relatedId: "1",
                relatedType: .community,
                actionURL: nil
            ),
            AppNotification(
                id: "n5",
                title: "Sistem Güncellemesi",
                message: "Uygulama yeni özelliklerle güncellendi. Detaylar için tıklayın.",
                type: .system,
                date: calendar.date(byAdding: .day, value: -2, to: now) ?? now,
                isRead: true,
                relatedId: nil,
                relatedType: nil,
                actionURL: nil
            )
        ]
    }
    
    func getCurrentUser() -> User {
        return User(
            id: "u1",
            firstName: "Tuna",
            lastName: "Karataş",
            email: "tuna@example.com",
            phoneNumber: "+90 555 123 4567",
            university: "İstanbul Teknik Üniversitesi",
            department: "Bilgisayar Mühendisliği",
            studentNumber: "2020123456",
            profileImageURL: nil,
            bio: "Yazılım geliştirme ve teknoloji tutkunu. Mobil uygulama geliştirme konusunda deneyimli.",
            joinedDate: Date().addingTimeInterval(-86400 * 180),
            favoriteCommunities: ["1", "2"],
            registeredEvents: ["e1", "e2"],
            savedCampaigns: ["c1"],
            notificationSettings: User.NotificationSettings()
        )
    }
    
    func getFavoriteCommunityIds() -> [String] {
        return ["1", "2"]
    }
    
    func getSavedCampaignIds() -> [String] {
        return ["c1"]
    }
}

