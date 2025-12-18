---
name: Mail ve Mesaj Merkezi Gelişmiş Özellikler
overview: "Mail ve mesaj merkezine 8 ana özellik ekleniyor: Taslak Kaydet, Şablon Yönetimi, Raporlar, Zamanlama, Acil Gönderim, Tekrar Gönder, Etiketleme ve A/B Testi. Email için tüm özellikler, SMS için kısmi özellikler (A/B testi hariç) implement edilecek."
todos:
  - id: create_db_schema
    content: "Veritabanı şemasını oluştur: email_drafts, sms_drafts, email_templates, sms_templates, email_scheduled, sms_scheduled, email_tags, sms_tags, email_campaign_tags, sms_campaign_tags, email_ab_tests, email_ab_test_results, email_tracking tabloları ve mevcut tablolara yeni kolonlar ekle"
    status: pending
  - id: implement_draft_save
    content: "Taslak Kaydet özelliğini implement et: Email ve SMS için taslak kaydetme, yükleme, listeleme, silme fonksiyonları ve UI"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_template_management
    content: "Şablonları Yönet özelliğini implement et: Email ve SMS şablonları için CRUD işlemleri, kategori yönetimi, şablon önizleme"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_scheduling
    content: "Zamanla özelliğini implement et: Email ve SMS için zamanlama, cron job scriptleri, zamanlanmış gönderimleri listeleme ve iptal etme"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_urgent_priority
    content: "Acil özelliğini implement et: Priority flag ekleme, acil gönderimlerin öncelikli işlenmesi, UI vurgulama"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_retry_send
    content: "Tekrar Gönder özelliğini implement et: Başarısız gönderimleri filtreleme, seçili gönderimleri tekrar kuyruğa ekleme, maksimum deneme kontrolü"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_tagging
    content: "Etiket Ekle özelliğini implement et: Etiket CRUD, kampanyalara etiket ekleme/çıkarma, etiket bazlı filtreleme ve raporlama"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_email_tracking
    content: "Email tracking sistemini implement et: Tracking pixel, link tracking, email_tracking tablosu, track_email.php API endpoint"
    status: pending
    dependencies:
      - create_db_schema
  - id: implement_reporting
    content: "Raporları Gör özelliğini implement et: Kampanya bazlı raporlar, açılma/tıklama oranları, bounce rate, zaman bazlı grafikler, export özelliği"
    status: pending
    dependencies:
      - implement_email_tracking
  - id: implement_ab_testing
    content: "A/B Testi özelliğini implement et: Test oluşturma, variant yönetimi, sonuç izleme, kazanan variant belirleme (sadece Email için)"
    status: pending
    dependencies:
      - implement_email_tracking
---

# Mail ve Mesaj Merkezi Gelişmiş Özellikler Planı

## Genel Bakış

Mail ve mesaj merkezine 8 ana özellik eklenecek. Email için tüm özellikler, SMS için kısmi özellikler (A/B testi hariç) implement edilecek.

## Veritabanı Şeması

### Yeni Tablolar

1. **email_drafts** - Email taslakları

   - id, club_id, subject, message, recipients (JSON), event_id, rsvp_filter, tags (JSON), created_at, updated_at

2. **sms_drafts** - SMS taslakları  

   - id, club_id, message, recipients (JSON), event_id, rsvp_filter, tags (JSON), created_at, updated_at

3. **email_templates** - Email şablonları

   - id, club_id, name, category, subject, message, variables (JSON), is_system (default 0), created_at, updated_at

4. **sms_templates** - SMS şablonları

   - id, club_id, name, category, message, variables (JSON), is_system (default 0), created_at, updated_at

5. **email_scheduled** - Zamanlanmış emailler

   - id, club_id, campaign_id, scheduled_at, timezone, status (pending/sent/cancelled), created_at

6. **sms_scheduled** - Zamanlanmış SMS'ler

   - id, club_id, scheduled_at, timezone, message, recipients (JSON), status, created_at

7. **email_tags** - Email etiketleri

   - id, club_id, name, color, created_at

8. **sms_tags** - SMS etiketleri

   - id, club_id, name, color, created_at

9. **email_campaign_tags** - Email kampanya-etiket ilişkisi

   - campaign_id, tag_id

10. **sms_campaign_tags** - SMS kampanya-etiket ilişkisi

    - campaign_id, tag_id

11. **email_ab_tests** - A/B testleri

    - id, club_id, test_name, variant_a_subject, variant_a_message, variant_b_subject, variant_b_message, variant_a_sender_name, variant_b_sender_name, variant_a_send_time, variant_b_send_time, split_percentage (default 50), status, winner_variant, created_at, completed_at

12. **email_ab_test_results** - A/B test sonuçları

    - id, test_id, variant (a/b), sent_count, opened_count, clicked_count, conversion_count, created_at

13. **email_tracking** - Email takip (açılma, tıklama)

    - id, queue_id, campaign_id, event_type (open/click), event_data (URL for clicks), ip_address, user_agent, created_at

### Güncellenecek Tablolar

1. **email_campaigns** - Yeni kolonlar:

   - priority (urgent/normal/low, default 'normal')
   - scheduled_at (DATETIME, nullable)
   - is_draft (INTEGER, default 0)
   - tags (TEXT, JSON array)
   - ab_test_id (INTEGER, nullable)

2. **email_queue** - Yeni kolonlar:

   - opened_at (DATETIME, nullable)
   - clicked_at (DATETIME, nullable)
   - click_count (INTEGER, default 0)
   - tracking_token (TEXT, unique)

## Özellik Detayları

### 1. Taslak Kaydet

**Email:**

- Form verilerini (konu, mesaj, alıcılar, etkinlik seçimi, RSVP filtresi) `email_drafts` tablosuna kaydet
- AJAX ile otomatik kaydetme (her 30 saniyede bir)
- Manuel kaydetme butonu
- Taslakları listeleme ve yükleme modalı
- Taslak silme özelliği

**SMS:**

- Aynı mantık, `sms_drafts` tablosuna kaydet

**Dosyalar:**

- `templates/functions/communication.php` - `save_email_draft()`, `load_email_draft()`, `list_email_drafts()`, `delete_email_draft()` fonksiyonları
- `templates/template_index.php` - UI butonları ve JavaScript handler'ları

### 2. Şablonları Yönet

**Email:**

- Şablon CRUD işlemleri (Create, Read, Update, Delete)
- Kategori bazlı organizasyon (Hoşgeldin, Etkinlik, Duyuru, vb.)
- Sistem şablonları (varsayılan, silinemez)
- Özel şablonlar (kullanıcı oluşturur)
- Şablon önizleme
- Şablondan email oluşturma

**SMS:**

- Aynı mantık, `sms_templates` tablosu

**Dosyalar:**

- `templates/functions/communication.php` - Şablon CRUD fonksiyonları
- `templates/template_index.php` - Şablon yönetim modalı ve UI

### 3. Raporları Gör

**Email:**

- Kampanya bazlı raporlar:
  - Gönderilen sayısı
  - Başarılı/Başarısız sayısı
  - Açılma oranı (open rate)
  - Tıklama oranı (click rate)
  - Bounce rate
  - Zaman bazlı grafikler (günlük/haftalık/aylık)
- Alıcı bazlı detaylar
- Export özelliği (CSV/PDF)

**SMS:**

- Gönderilen sayısı
- Başarılı/Başarısız sayısı
- Zaman bazlı grafikler

**Dosyalar:**

- `templates/functions/communication.php` - `get_email_campaign_report()`, `get_email_statistics()` fonksiyonları
- `templates/template_index.php` - Rapor görüntüleme sayfası ve grafikler (Chart.js kullanılacak)

### 4. Zamanla

**Email:**

- Tarih ve saat seçici
- Timezone desteği
- Zamanlanmış gönderimleri listeleme
- İptal etme özelliği
- Cron job ile zamanlanmış gönderim işleme (`system/scripts/process_scheduled_emails.php`)

**SMS:**

- Aynı mantık

**Dosyalar:**

- `templates/functions/communication.php` - `schedule_email()`, `cancel_scheduled_email()` fonksiyonları
- `system/scripts/process_scheduled_emails.php` - Zamanlanmış gönderimleri işleyen script
- `templates/template_index.php` - Zamanlama UI

### 5. Acil

**Email:**

- Priority flag'i (`email_campaigns.priority = 'urgent'`)
- Acil gönderimler öncelikli işlenir
- UI'da görsel vurgulama (kırmızı badge)

**SMS:**

- Aynı mantık

**Dosyalar:**

- `templates/functions/communication.php` - Priority kontrolü
- `system/scripts/process_email_queue.php` - Acil gönderimler öncelikli işlenir
- `templates/template_index.php` - Acil checkbox ve UI

### 6. Tekrar Gönder

**Email:**

- Başarısız gönderimleri filtreleme (`email_queue.status = 'failed'`)
- Seçili gönderimleri tekrar kuyruğa ekleme
- Toplu tekrar gönderim
- Maksimum deneme sayısı kontrolü (örn: 3 deneme)

**SMS:**

- Aynı mantık

**Dosyalar:**

- `templates/functions/communication.php` - `retry_failed_emails()` fonksiyonu
- `templates/template_index.php` - Tekrar gönder butonu ve modal

### 7. Etiket Ekle

**Email:**

- Etiket oluşturma (renk seçimi ile)
- Kampanyalara etiket ekleme/çıkarma
- Etiket bazlı filtreleme
- Etiket bazlı raporlama

**SMS:**

- Aynı mantık

**Dosyalar:**

- `templates/functions/communication.php` - Etiket CRUD fonksiyonları
- `templates/template_index.php` - Etiket UI ve filtreleme

### 8. A/B Testi (Sadece Email)

**Email:**

- Test oluşturma:
  - Variant A ve B için farklı konu, içerik, gönderen adı, gönderim zamanı
  - Split percentage (örn: %50-%50)
- Test başlatma
- Sonuçları izleme (açılma, tıklama, conversion)
- Kazanan variant'ı otomatik belirleme
- Kazanan variant'ı tüm alıcılara gönderme

**Dosyalar:**

- `templates/functions/communication.php` - A/B test fonksiyonları
- `templates/template_index.php` - A/B test oluşturma modalı ve sonuç görüntüleme

## Email Tracking (Açılma/Tıklama)

- Tracking pixel (1x1 görsel) email içeriğine eklenir
- Link'lere tracking token eklenir
- `email_tracking` tablosuna event'ler kaydedilir
- API endpoint: `api/track_email.php?token=xxx&type=open/click`

## Cron Jobs

1. `system/scripts/process_scheduled_emails.php` - Zamanlanmış emailleri gönderir
2. `system/scripts/process_scheduled_sms.php` - Zamanlanmış SMS'leri gönderir
3. `system/scripts/process_email_queue.php` - Mevcut, acil gönderimler öncelikli işlenir

## UI/UX Değişiklikleri

### Mail Merkezi (`templates/template_index.php`)

1. **Taslak Kaydet Butonu:**

   - Aktif hale getirilecek
   - Otomatik kaydetme göstergesi
   - Taslakları yükleme dropdown'ı

2. **Şablonları Yönet Butonu:**

   - Modal açılacak
   - Şablon listesi, ekleme, düzenleme, silme

3. **Raporları Gör Butonu:**

   - Rapor sayfasına yönlendirme
   - Kampanya bazlı raporlar

4. **Zamanla Seçeneği:**

   - Tarih/saat picker
   - Timezone seçimi

5. **Acil Checkbox:**

   - Aktif hale getirilecek
   - Görsel vurgulama

6. **Tekrar Gönder Butonu:**

   - Başarısız gönderimler listesi
   - Seçim ve tekrar gönderim

7. **Etiket Ekle:**

   - Etiket seçici dropdown
   - Yeni etiket oluşturma

8. **A/B Testi:**

   - Test oluşturma modalı
   - Sonuç görüntüleme

### SMS Merkezi

Aynı özellikler (A/B testi hariç) SMS için de eklenecek.

## Güvenlik ve Performans

- CSRF koruması tüm form işlemlerinde
- Rate limiting (spam önleme)
- SQL injection koruması (prepared statements)
- XSS koruması (htmlspecialchars)
- Tracking token'ları güvenli (hash kullanımı)
- Batch processing (toplu işlemler)

## Test Senaryoları

1. Taslak kaydetme ve yükleme
2. Şablon oluşturma ve kullanma
3. Zamanlanmış gönderim
4. Acil gönderim önceliği
5. Başarısız gönderimleri tekrar gönderme
6. Etiket ekleme ve filtreleme
7. A/B testi oluşturma ve sonuçları izleme
8. Email tracking (açılma/tıklama)

## Dosya Yapısı

```
templates/functions/communication.php - Tüm backend fonksiyonları
templates/template_index.php - UI ve JavaScript
system/scripts/process_scheduled_emails.php - Zamanlanmış email işleyici
system/scripts/process_scheduled_sms.php - Zamanlanmış SMS işleyici
api/track_email.php - Email tracking endpoint
api/endpoints/email_drafts.php - Taslak API endpoint'leri
api/endpoints/email_templates.php - Şablon API endpoint'leri
api/endpoints/email_reports.php - Rapor API endpoint'leri
```

## İlerleme Sırası

1. Veritabanı şeması oluşturma
2. Taslak Kaydet özelliği
3. Şablonları Yönet özelliği
4. Zamanla özelliği
5. Acil özelliği
6. Tekrar Gönder özelliği
7. Etiket Ekle özelliği
8. Email Tracking (açılma/tıklama)
9. Raporları Gör özelliği
10. A/B Testi özelliği