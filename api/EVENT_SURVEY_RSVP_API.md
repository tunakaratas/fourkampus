# Event Survey & RSVP API Documentation

Bu dokümantasyon, etkinlik anketleri ve RSVP yönetimi için RESTful API endpoint'lerini açıklar.

## Base URL
```
https://yourdomain.com/api/
```

## Authentication
Çoğu endpoint için authentication opsiyoneldir. Admin işlemleri için authentication zorunludur.

### Headers
```
Content-Type: application/json
Authorization: Bearer {token} (opsiyonel)
```

---

## Event Survey API

### 1. Get Survey (Anketi Getir)

**Endpoint:** `GET /api/event_survey.php`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si

**Example Request:**
```swift
let url = URL(string: "https://yourdomain.com/api/event_survey.php?community_id=\(communityId)&event_id=\(eventId)")!
var request = URLRequest(url: url)
request.httpMethod = "GET"
request.setValue("application/json", forHTTPHeaderField: "Content-Type")
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "event_id": 123,
    "title": "Etkinlik Değerlendirme Anketi",
    "description": "Etkinlik hakkında görüşlerinizi paylaşın",
    "is_active": true,
    "questions": [
      {
        "id": 1,
        "question_text": "Etkinliği nasıl değerlendirirsiniz?",
        "question_type": "multiple_choice",
        "display_order": 0,
        "options": [
          {
            "id": 1,
            "text": "Çok İyi",
            "order": 0
          },
          {
            "id": 2,
            "text": "İyi",
            "order": 1
          },
          {
            "id": 3,
            "text": "Orta",
            "order": 2
          }
        ]
      }
    ],
    "created_at": "2024-01-15 10:00:00",
    "updated_at": "2024-01-15 10:00:00"
  },
  "message": null,
  "error": null
}
```

**Swift Example:**
```swift
struct SurveyResponse: Codable {
    let success: Bool
    let data: Survey?
    let message: String?
    let error: String?
}

struct Survey: Codable {
    let id: Int
    let eventId: Int
    let title: String
    let description: String?
    let isActive: Bool
    let questions: [Question]
    let createdAt: String?
    let updatedAt: String?
    
    enum CodingKeys: String, CodingKey {
        case id, title, description, questions
        case eventId = "event_id"
        case isActive = "is_active"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
}

struct Question: Codable {
    let id: Int
    let questionText: String
    let questionType: String
    let displayOrder: Int
    let options: [Option]
    
    enum CodingKeys: String, CodingKey {
        case id, options
        case questionText = "question_text"
        case questionType = "question_type"
        case displayOrder = "display_order"
    }
}

struct Option: Codable {
    let id: Int
    let text: String
    let order: Int
}
```

---

### 2. Create/Update Survey (Anket Oluştur/Güncelle)

**Endpoint:** `POST /api/event_survey.php`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si

**Authentication:** Required (Admin)

**Request Body:**
```json
{
  "title": "Etkinlik Değerlendirme Anketi",
  "description": "Etkinlik hakkında görüşlerinizi paylaşın",
  "questions": [
    {
      "question_text": "Etkinliği nasıl değerlendirirsiniz?",
      "question_type": "multiple_choice",
      "options": [
        { "text": "Çok İyi" },
        { "text": "İyi" },
        { "text": "Orta" },
        { "text": "Kötü" }
      ]
    },
    {
      "question_text": "Bir sonraki etkinlikte ne görmek istersiniz?",
      "question_type": "text"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "survey_id": 1
  },
  "message": "Anket başarıyla kaydedildi",
  "error": null
}
```

**Swift Example:**
```swift
struct CreateSurveyRequest: Codable {
    let title: String
    let description: String?
    let questions: [QuestionRequest]
}

struct QuestionRequest: Codable {
    let questionText: String
    let questionType: String
    let options: [String]?
    
    enum CodingKeys: String, CodingKey {
        case questionText = "question_text"
        case questionType = "question_type"
        case options
    }
}

func createSurvey(communityId: String, eventId: Int, survey: CreateSurveyRequest) async throws {
    let url = URL(string: "https://yourdomain.com/api/event_survey.php?community_id=\(communityId)&event_id=\(eventId)")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.httpBody = try JSONEncoder().encode(survey)
    
    let (data, _) = try await URLSession.shared.data(for: request)
    let response = try JSONDecoder().decode(SurveyResponse.self, from: data)
    
    guard response.success else {
        throw APIError.serverError(response.error ?? "Unknown error")
    }
}
```

---

### 3. Submit Survey Response (Anket Yanıtı Gönder)

**Endpoint:** `POST /api/event_survey.php?action=submit`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si
- `action` (required): "submit"

**Request Body:**
```json
{
  "user_email": "user@example.com",
  "user_name": "John Doe",
  "responses": [
    {
      "question_id": 1,
      "option_id": 1
    },
    {
      "question_id": 2,
      "response_text": "Daha fazla workshop görmek istiyorum"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Anket yanıtınız başarıyla kaydedildi",
  "error": null
}
```

**Swift Example:**
```swift
struct SurveySubmissionRequest: Codable {
    let userEmail: String
    let userName: String?
    let responses: [SurveyResponseItem]
    
    enum CodingKeys: String, CodingKey {
        case userEmail = "user_email"
        case userName = "user_name"
        case responses
    }
}

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

func submitSurvey(communityId: String, eventId: Int, submission: SurveySubmissionRequest) async throws {
    let url = URL(string: "https://yourdomain.com/api/event_survey.php?community_id=\(communityId)&event_id=\(eventId)&action=submit")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.httpBody = try JSONEncoder().encode(submission)
    
    let (data, _) = try await URLSession.shared.data(for: request)
    let response = try JSONDecoder().decode(APIResponse.self, from: data)
    
    guard response.success else {
        throw APIError.serverError(response.error ?? "Unknown error")
    }
}
```

---

### 4. Get Survey Responses (Anket Yanıtlarını Getir - Admin)

**Endpoint:** `GET /api/event_survey.php?action=responses`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si
- `action` (required): "responses"

**Authentication:** Required (Admin)

**Response:**
```json
{
  "success": true,
  "data": {
    "survey_id": 1,
    "participant_count": 25,
    "questions": [
      {
        "id": 1,
        "question_text": "Etkinliği nasıl değerlendirirsiniz?",
        "question_type": "multiple_choice",
        "total_responses": 25,
        "options": [
          {
            "id": 1,
            "text": "Çok İyi",
            "response_count": 15
          },
          {
            "id": 2,
            "text": "İyi",
            "response_count": 8
          },
          {
            "id": 3,
            "text": "Orta",
            "response_count": 2
          }
        ]
      }
    ]
  },
  "message": null,
  "error": null
}
```

---

## Event RSVP API

### 1. Get User's RSVP Status (Kullanıcının RSVP Durumunu Getir)

**Endpoint:** `GET /api/event_rsvp.php`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si
- `user_email` (optional): E-posta adresi (auth token yoksa gerekli)

**Response (RSVP var):**
```json
{
  "success": true,
  "data": {
    "has_rsvp": true,
    "id": 1,
    "status": "attending",
    "member_name": "John Doe",
    "member_email": "user@example.com",
    "member_phone": "+905551234567",
    "created_at": "2024-01-15 10:00:00",
    "updated_at": "2024-01-15 10:00:00",
    "event_id": 123
  },
  "message": null,
  "error": null
}
```

**Response (RSVP yok):**
```json
{
  "success": true,
  "data": {
    "has_rsvp": false,
    "status": null,
    "event_id": 123
  },
  "message": null,
  "error": null
}
```

**Swift Example:**
```swift
struct RSVPStatusResponse: Codable {
    let success: Bool
    let data: RSVPStatus?
    let message: String?
    let error: String?
}

struct RSVPStatus: Codable {
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

func getRSVPStatus(communityId: String, eventId: Int, userEmail: String) async throws -> RSVPStatus {
    let url = URL(string: "https://yourdomain.com/api/event_rsvp.php?community_id=\(communityId)&event_id=\(eventId)&user_email=\(userEmail)")!
    var request = URLRequest(url: url)
    request.httpMethod = "GET"
    
    let (data, _) = try await URLSession.shared.data(for: request)
    let response = try JSONDecoder().decode(RSVPStatusResponse.self, from: data)
    
    guard response.success, let rsvpStatus = response.data else {
        throw APIError.serverError(response.error ?? "Unknown error")
    }
    
    return rsvpStatus
}
```

---

### 2. Create/Update RSVP (RSVP Oluştur/Güncelle)

**Endpoint:** `POST /api/event_rsvp.php`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si

**Request Body:**
```json
{
  "member_name": "John Doe",
  "member_email": "user@example.com",
  "member_phone": "+905551234567",
  "status": "attending"
}
```

**Status Values:**
- `"attending"`: Katılacak
- `"not_attending"`: Katılmayacak

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "attending",
    "action": "created",
    "statistics": {
      "attending_count": 15,
      "not_attending_count": 3,
      "total_count": 18
    }
  },
  "message": "RSVP kaydedildi",
  "error": null
}
```

**Swift Example:**
```swift
struct CreateRSVPRequest: Codable {
    let memberName: String
    let memberEmail: String
    let memberPhone: String?
    let status: RSVPStatus
    
    enum CodingKeys: String, CodingKey {
        case memberName = "member_name"
        case memberEmail = "member_email"
        case memberPhone = "member_phone"
        case status
    }
}

enum RSVPStatus: String, Codable {
    case attending = "attending"
    case notAttending = "not_attending"
}

struct RSVPResponse: Codable {
    let success: Bool
    let data: RSVPData?
    let message: String?
    let error: String?
}

struct RSVPData: Codable {
    let id: Int
    let status: String
    let action: String
    let statistics: RSVPStatistics
}

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

func createRSVP(communityId: String, eventId: Int, rsvp: CreateRSVPRequest) async throws -> RSVPData {
    let url = URL(string: "https://yourdomain.com/api/event_rsvp.php?community_id=\(communityId)&event_id=\(eventId)")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.httpBody = try JSONEncoder().encode(rsvp)
    
    let (data, _) = try await URLSession.shared.data(for: request)
    let response = try JSONDecoder().decode(RSVPResponse.self, from: data)
    
    guard response.success, let rsvpData = response.data else {
        throw APIError.serverError(response.error ?? "Unknown error")
    }
    
    return rsvpData
}
```

---

### 3. Cancel RSVP (RSVP İptal Et)

**Endpoint:** `DELETE /api/event_rsvp.php`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si
- `user_email` (optional): E-posta adresi (auth token yoksa gerekli)

**Response:**
```json
{
  "success": true,
  "data": {
    "statistics": {
      "attending_count": 14,
      "not_attending_count": 3,
      "total_count": 17
    }
  },
  "message": "RSVP iptal edildi",
  "error": null
}
```

**Swift Example:**
```swift
func cancelRSVP(communityId: String, eventId: Int, userEmail: String) async throws {
    let url = URL(string: "https://yourdomain.com/api/event_rsvp.php?community_id=\(communityId)&event_id=\(eventId)&user_email=\(userEmail)")!
    var request = URLRequest(url: url)
    request.httpMethod = "DELETE"
    
    let (data, _) = try await URLSession.shared.data(for: request)
    let response = try JSONDecoder().decode(RSVPResponse.self, from: data)
    
    guard response.success else {
        throw APIError.serverError(response.error ?? "Unknown error")
    }
}
```

---

### 4. Get RSVP List (RSVP Listesini Getir - Admin)

**Endpoint:** `GET /api/event_rsvp.php?action=list`

**Query Parameters:**
- `community_id` (required): Topluluk ID'si
- `event_id` (required): Etkinlik ID'si
- `action` (required): "list"

**Authentication:** Required (Admin)

**Response:**
```json
{
  "success": true,
  "data": {
    "event_id": 123,
    "rsvps": [
      {
        "id": 1,
        "member_name": "John Doe",
        "member_email": "user@example.com",
        "member_phone": "+905551234567",
        "status": "attending",
        "created_at": "2024-01-15 10:00:00",
        "updated_at": "2024-01-15 10:00:00"
      }
    ],
    "statistics": {
      "attending_count": 15,
      "not_attending_count": 3,
      "total_count": 18
    }
  },
  "message": null,
  "error": null
}
```

---

## Error Responses

Tüm endpoint'ler aşağıdaki hata formatını kullanır:

```json
{
  "success": false,
  "data": null,
  "message": null,
  "error": "Hata mesajı"
}
```

### Common Error Codes

- `400`: Bad Request - Geçersiz parametreler
- `401`: Unauthorized - Authentication gerekli
- `404`: Not Found - Kaynak bulunamadı
- `429`: Too Many Requests - Rate limit aşıldı
- `500`: Internal Server Error - Sunucu hatası

---

## Rate Limiting

- **Limit:** 100 istek / dakika
- **Response:** HTTP 429 status code

---

## Notes

1. Tüm tarih/saat formatları: `YYYY-MM-DD HH:MM:SS`
2. Tüm ID'ler integer
3. Boolean değerler: `true` / `false` veya `1` / `0`
4. Null değerler: `null` (JSON)
5. String değerler UTF-8 encoded
6. Tüm endpoint'ler CORS desteği ile çalışır

---

## Swift Helper Functions

```swift
enum APIError: Error {
    case invalidURL
    case serverError(String)
    case decodingError
    case networkError
}

struct APIResponse<T: Codable>: Codable {
    let success: Bool
    let data: T?
    let message: String?
    let error: String?
}

class EventAPIService {
    static let shared = EventAPIService()
    private let baseURL = "https://yourdomain.com/api"
    
    func getSurvey(communityId: String, eventId: Int) async throws -> Survey {
        // Implementation
    }
    
    func submitSurvey(communityId: String, eventId: Int, submission: SurveySubmissionRequest) async throws {
        // Implementation
    }
    
    func getRSVPStatus(communityId: String, eventId: Int, userEmail: String) async throws -> RSVPStatus {
        // Implementation
    }
    
    func createRSVP(communityId: String, eventId: Int, rsvp: CreateRSVPRequest) async throws -> RSVPData {
        // Implementation
    }
    
    func cancelRSVP(communityId: String, eventId: Int, userEmail: String) async throws {
        // Implementation
    }
}
```

