# 📱 UniPanel Mobil API Dokümantasyonu

## 🌐 Base URL
```
http://your-domain.com/unipanel/api/
```

## 📋 Endpoint'ler

### 1. Communities (Topluluklar)

#### Tüm Toplulukları Listele
```
GET /api/communities.php
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "deneme",
      "name": "Deneme Topluluğu",
      "description": "Açıklama",
      "logo_path": "/communities/deneme/logo.png",
      "status": "active",
      "member_count": 50,
      "event_count": 10,
      "board_member_count": 5
    }
  ],
  "message": null,
  "error": null
}
```

#### Topluluk Detayı
```
GET /api/communities.php?id={community_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "deneme",
    "name": "Deneme Topluluğu",
    "description": "Açıklama",
    "logo_path": "/communities/deneme/logo.png",
    "status": "active",
    "member_count": 50,
    "event_count": 10,
    "board_member_count": 5
  }
}
```

---

### 2. Events (Etkinlikler)

#### Etkinlikleri Listele
```
GET /api/events.php?community_id={community_id}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Etkinlik Başlığı",
      "description": "Açıklama",
      "date": "2024-01-15",
      "time": "14:00",
      "location": "Konum",
      "image_path": "/communities/deneme/assets/images/events/image.jpg",
      "video_path": null,
      "has_survey": false,
      "category": "Eğitim",
      "status": "upcoming",
      "organizer": "Organizatör Adı",
      "contact_email": "contact@example.com",
      "contact_phone": "555-1234",
      "capacity": 100,
      "cost": 0.0,
      "registration_required": true
    }
  ]
}
```

---

### 3. Members (Üyeler)

#### Üyeleri Listele
```
GET /api/members.php?community_id={community_id}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "full_name": "Ad Soyad",
      "email": "email@example.com",
      "student_id": "123456",
      "phone_number": "555-1234",
      "registration_date": "2024-01-01"
    }
  ]
}
```

---

### 4. Board Members (Yönetim Kurulu)

#### Yönetim Kurulu Üyelerini Listele
```
GET /api/board.php?community_id={community_id}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "full_name": "Ad Soyad",
      "role": "Başkan",
      "contact_email": "email@example.com",
      "phone": "555-1234",
      "bio": "Biyografi",
      "photo_path": "/communities/deneme/assets/images/board/photo.jpg"
    }
  ]
}
```

---

### 5. Surveys (Anketler)

#### Anketleri Listele
```
GET /api/surveys.php?community_id={community_id}
GET /api/surveys.php?community_id={community_id}&event_id={event_id}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "event_id": 1,
      "title": "Anket Başlığı",
      "description": "Açıklama",
      "created_at": "2024-01-15 10:00:00",
      "questions": [
        {
          "id": 1,
          "question_text": "Soru metni",
          "question_type": "text",
          "question_order": 1,
          "options": null
        }
      ]
    }
  ]
}
```

---

### 6. RSVP (Katılım Bildirimi)

#### RSVP Durumunu Getir
```
GET /api/rsvp.php?community_id={community_id}&event_id={event_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "event_id": 1,
    "rsvps": [
      {
        "id": 1,
        "event_id": 1,
        "member_name": "Ad Soyad",
        "member_email": "email@example.com",
        "member_phone": "555-1234",
        "status": "attending",
        "created_at": "2024-01-15 10:00:00"
      }
    ],
    "statistics": {
      "attending_count": 10,
      "not_attending_count": 2,
      "total_count": 12
    }
  }
}
```

#### RSVP Kaydı Oluştur/Güncelle
```
POST /api/rsvp.php?community_id={community_id}
Content-Type: application/json

{
  "event_id": 1,
  "member_name": "Ad Soyad",
  "member_email": "email@example.com",
  "member_phone": "555-1234",
  "status": "attending"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "message": "RSVP kaydedildi"
}
```

---

## 🔒 Güvenlik

- Tüm endpoint'ler CORS desteği ile açık (production'da kısıtlanabilir)
- Community ID doğrulaması yapılıyor (basename ile güvenlik)
- SQL injection koruması (prepared statements)
- Hata mesajları kullanıcı dostu

## 📝 Notlar

- Tüm endpoint'ler JSON formatında yanıt döner
- Başarılı isteklerde `success: true`
- Hata durumlarında `success: false` ve `error` mesajı
- Image ve video path'leri tam URL olarak döner (base URL eklenmeli)

## 🚀 Kullanım Örneği

```bash
# Tüm toplulukları listele
curl http://localhost/unipanel/api/communities.php

# Belirli bir topluluğun etkinliklerini getir
curl http://localhost/unipanel/api/events.php?community_id=deneme

# RSVP kaydı oluştur
curl -X POST http://localhost/unipanel/api/rsvp.php?community_id=deneme \
  -H "Content-Type: application/json" \
  -d '{"event_id":1,"member_name":"Test User","status":"attending"}'
```

