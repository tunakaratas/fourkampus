//
//  Post.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import Foundation
import SwiftUI

// MARK: - Post Model
struct Post: Identifiable, Hashable, Codable {
    let id: String
    let type: PostType
    let communityId: String
    let communityName: String
    let communityLogo: String?
    let authorId: String?
    let authorName: String?
    let content: String
    let images: [String]
    let video: String?
    let eventId: String? // Eğer post bir etkinlikten geliyorsa
    let campaignId: String? // Eğer post bir kampanyadan geliyorsa
    let likeCount: Int
    let commentCount: Int
    let isLiked: Bool
    let createdAt: Date
    let updatedAt: Date?
    
    enum PostType: String, Codable {
        case event = "event"
        case campaign = "campaign"
        case general = "general"
        case announcement = "announcement"
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case type
        case communityId = "community_id"
        case communityName = "community_name"
        case communityLogo = "community_logo"
        case authorId = "author_id"
        case authorName = "author_name"
        case content
        case images
        case video
        case eventId = "event_id"
        case campaignId = "campaign_id"
        case likeCount = "like_count"
        case commentCount = "comment_count"
        case isLiked = "is_liked"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        // ID
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        type = try container.decode(PostType.self, forKey: .type)
        communityId = try container.decode(String.self, forKey: .communityId)
        communityName = try container.decode(String.self, forKey: .communityName)
        communityLogo = try container.decodeIfPresent(String.self, forKey: .communityLogo)
        authorId = try container.decodeIfPresent(String.self, forKey: .authorId)
        authorName = try container.decodeIfPresent(String.self, forKey: .authorName)
        content = try container.decode(String.self, forKey: .content)
        images = try container.decodeIfPresent([String].self, forKey: .images) ?? []
        video = try container.decodeIfPresent(String.self, forKey: .video)
        eventId = try container.decodeIfPresent(String.self, forKey: .eventId)
        campaignId = try container.decodeIfPresent(String.self, forKey: .campaignId)
        likeCount = try container.decodeIfPresent(Int.self, forKey: .likeCount) ?? 0
        commentCount = try container.decodeIfPresent(Int.self, forKey: .commentCount) ?? 0
        isLiked = try container.decodeIfPresent(Bool.self, forKey: .isLiked) ?? false
        
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
        
        if let dateString = try? container.decodeIfPresent(String.self, forKey: .updatedAt) {
            let formatter = ISO8601DateFormatter()
            formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = formatter.date(from: dateString) {
                updatedAt = date
            } else {
                let dateFormatter = DateFormatter()
                dateFormatter.dateFormat = "yyyy-MM-dd'T'HH:mm:ssZ"
                updatedAt = dateFormatter.date(from: dateString)
            }
        } else {
            updatedAt = nil
        }
    }
    
    var timeAgo: String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.localizedString(for: createdAt, relativeTo: Date())
    }
}

// MARK: - Comment Model
struct Comment: Identifiable, Hashable, Codable {
    let id: String
    let postId: String
    let userId: String
    let userName: String
    let userAvatar: String?
    let content: String
    let likeCount: Int
    let isLiked: Bool
    let createdAt: Date
    let replies: [Comment]?
    
    enum CodingKeys: String, CodingKey {
        case id
        case postId = "post_id"
        case userId = "user_id"
        case userName = "user_name"
        case userAvatar = "user_avatar"
        case content
        case likeCount = "like_count"
        case isLiked = "is_liked"
        case createdAt = "created_at"
        case replies
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        if let intId = try? container.decode(Int.self, forKey: .id) {
            id = String(intId)
        } else {
            id = try container.decode(String.self, forKey: .id)
        }
        
        postId = try container.decode(String.self, forKey: .postId)
        userId = try container.decode(String.self, forKey: .userId)
        userName = try container.decode(String.self, forKey: .userName)
        userAvatar = try container.decodeIfPresent(String.self, forKey: .userAvatar)
        content = try container.decode(String.self, forKey: .content)
        likeCount = try container.decodeIfPresent(Int.self, forKey: .likeCount) ?? 0
        isLiked = try container.decodeIfPresent(Bool.self, forKey: .isLiked) ?? false
        replies = try container.decodeIfPresent([Comment].self, forKey: .replies)
        
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
    }
    
    var timeAgo: String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        formatter.locale = Locale(identifier: "tr_TR")
        return formatter.localizedString(for: createdAt, relativeTo: Date())
    }
}

