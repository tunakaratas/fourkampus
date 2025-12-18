# Four Kampüs Brand Dönüşüm Planı

## 📋 Genel Bakış
Bu plan, frontend dosyalarındaki tüm eski brand isimlerini "Four Kampüs"e çevirmek için hazırlanmıştır. Backend/API dosyalarındaki deep link'ler ve teknik referanslar (riskli olduğu için) bu planın dışında bırakılmıştır.

---

## 🎯 Kapsam

### ✅ Değiştirilecek Dosyalar (Frontend)
1. **templates/template_index.php** - Ana admin panel template'i
2. **templates/template_login.php** - Giriş sayfası template'i
3. **templates/template_market.php** - Market sayfası template'i
4. **public/index.php** - Public frontend sayfası
5. **Swift iOS Projesi** (`unipanel_swift/`) - iOS mobil uygulama

### ⚠️ Değiştirilmeyecek Dosyalar (Backend/API - Riskli)
- `api/communities.php` - Deep link'ler (`unifour://`)
- `api/events.php` - Deep link'ler
- `api/endpoints/*` - Deep link'ler
- `api/calendar.php` - Deep link'ler
- `api/qr_code.php` - Deep link'ler
- `api/services/CommunitiesService.php` - Deep link'ler
- `templates/functions/events.php` - Deep link'ler
- `qr-redirect.php` - Deep link'ler
- `templates/functions/communication.php` - Email header'ları (kullanıcı isteği üzerine değiştirilmeyecek)

**Not:** Deep link'ler (`unifour://`) mobil uygulama entegrasyonu için kritik olduğundan değiştirilmeyecektir.

---

## 📝 Detaylı Değişiklik Listesi

### 1. templates/template_index.php

#### 1.1. Sidebar Logo Yorumu (Satır ~7579)
- **Mevcut:** `<!-- Ana Logo ve Four Kampüs İsmi -->`
- **Yeni:** `<!-- Ana Logo ve Four Kampüs İsmi -->`

#### 1.2. Copyright Footer (Satır ~7635)
- **Mevcut:** `© 2025 Four Kampüs - Four Software tarafından`
- **Yeni:** `© 2025 Four Kampüs - Four Software tarafından`

#### 1.3. SMS Template Butonu (Satır ~9767)
- **Mevcut:** `<span class="mail-template-title">Four Kampüs</span>`
- **Yeni:** `<span class="mail-template-title">Four Kampüs</span>`

#### 1.4. Email Template Butonu (Satır ~10092)
- **Mevcut:** `<span class="mail-template-title">Four Kampüs</span>`
- **Yeni:** `<span class="mail-template-title">Four Kampüs</span>`

#### 1.5. SMTP Default Email (Satır ~9920)
- **Mevcut:** `'info@unifour.com'`
- **Yeni:** `'info@fourkampus.com'` veya `'info@foursoftware.com'` (karar verilmeli)
- **Not:** Bu teknik bir ayar, email adresi değişikliği gerekebilir.

#### 1.6. Mesafeli Satış Sözleşmesi Bölümü (Satır ~13000-13148)
- **Satır ~13000:** `Satıcı: Four Kampüs` → `Satıcı: Four Kampüs`
- **Satır ~13001:** `info@fourkampus.com` → `info@fourkampus.com` veya `info@foursoftware.com`
- **Satır ~13003:** `Four Kampüs platformu` → `Four Kampüs platformu` (2 kez)
- **Satır ~13008:** `Four Kampüs platformu` → `Four Kampüs platformu`
- **Satır ~13056:** `info@fourkampus.com` → `info@fourkampus.com` veya `info@foursoftware.com`
- **Satır ~13081:** `Four Kampüs` → `Four Kampüs`
- **Satır ~13085:** `info@fourkampus.com` → `info@fourkampus.com` veya `info@foursoftware.com`
- **Satır ~13100:** `Four Kampüs Profesyonel Plan` → `Four Kampüs Profesyonel Plan`
- **Satır ~13131:** `info@fourkampus.com` → `info@fourkampus.com` veya `info@foursoftware.com`
- **Satır ~13148:** `Four Kampüs platformu` → `Four Kampüs platformu`

#### 1.7. Belge Doğrulama Timeline (Satır ~13412)
- **Mevcut:** `'Four Kampüs güven ekibi belgeyi doğrular.'`
- **Yeni:** `'Four Kampüs güven ekibi belgeyi doğrular.'`

#### 1.8. Email Template İçeriği (Satır ~17930-17941)
- **Satır ~17931:** `'Four Kampüs\'e Hoş Geldin {{member_name}}!'` → `'Four Kampüs\'e Hoş Geldin {{member_name}}!'`
- **Satır ~17933:** `Four Kampüs topluluğuna` → `Four Kampüs topluluğuna`
- **Satır ~17941:** `Four Kampüs Ekibi` → `Four Kampüs Ekibi`

#### 1.9. SMS Template İçeriği (Satır ~18092)
- **Mevcut:** `'Four Kampüs\'e hoş geldin {{member_name}}! ...'`
- **Yeni:** `'Four Kampüs\'e hoş geldin {{member_name}}! ...'`

---

### 2. templates/template_login.php

#### 2.1. SMS Mesajı (Satır ~839)
- **Mevcut:** `"Four Kampüs Güvenli Giriş Kodunuz: %s..."`
- **Yeni:** `"Four Kampüs Güvenli Giriş Kodunuz: %s..."`

#### 2.2. Logo Alt Text (Satır ~2599)
- **Mevcut:** `alt="Four Kampüs Logo"`
- **Yeni:** `alt="Four Kampüs Logo"`

#### 2.3. Copyright Footer (Satır ~2792)
- **Mevcut:** `© 2025 Four Kampüs - Tüm hakları saklıdır`
- **Yeni:** `© 2025 Four Kampüs - Tüm hakları saklıdır`

---

### 3. templates/template_market.php

#### 3.1. Copyright Footer (Satır ~2354)
- **Mevcut:** `© <?= date('Y') ?> <span class="font-semibold text-indigo-600">Four Kampüs</span>`
- **Yeni:** `© <?= date('Y') ?> <span class="font-semibold text-indigo-600">Four Kampüs</span>`

---

### 4. public/index.php

#### 4.1. QR Kod Deep Link'leri (Satır ~5454, 5456)
- **Durum:** Bu deep link'ler (`unifour://`) mobil uygulama entegrasyonu için kritik olduğundan **DEĞİŞTİRİLMEYECEK**
- **Not:** Kullanıcıya bilgi verilecek, backend ile uyumlu kalması gerekiyor.

---

### 5. Swift iOS Projesi (`unipanel_swift/`)

**Not:** Swift projesi workspace'te bulunmuyor (`.gitignore`'da `unipanel_swift/` olarak işaretli), ancak değişiklikler yapılırken bu projede aşağıdaki alanlar kontrol edilmeli ve güncellenmelidir.

#### 5.1. Info.plist ve InfoPlist.strings
- **App Display Name:** `Four Kampüs` → `Four Kampüs`
- **Bundle Display Name:** `Four Kampüs` → `Four Kampüs`
- **Bundle Name:** `Four Kampüs` → `Four Kampüs`
- **Dosyalar:** `Info.plist`, `InfoPlist.strings` (tüm diller için)

#### 5.2. Localization Strings (Localizable.strings)
- Tüm dillerde (`tr.lproj/Localizable.strings`, `en.lproj/Localizable.strings`, vs.) "Four Kampüs" geçen tüm metinler:
  - `"Four Kampüs"` → `"Four Kampüs"`
  - `"Four Kampüs'e"` → `"Four Kampüs'e"`
  - `"Four Kampüs'e hoş geldiniz"` → `"Four Kampüs'e hoş geldiniz"`
  - Diğer tüm kullanıcıya görünen metinler

#### 5.3. Swift Kaynak Kodları
- **Hardcoded Strings:** Swift dosyalarında (`*.swift`) hardcode edilmiş "Four Kampüs" metinleri:
  - String literal'lar: `"Four Kampüs"` → `"Four Kampüs"`
  - Alert mesajları
  - Error mesajları
  - Debug mesajları (kullanıcıya görünmeyenler hariç)
- **Dosyalar:** Tüm `.swift` dosyaları aranmalı (grep ile `"Four Kampüs"` veya `"fourkampus"`)

#### 5.4. App İkonları ve Splash Screen
- **App Icon:** Eğer app icon'da "Four Kampüs" yazısı varsa, güncellenmeli
- **Splash Screen:** Launch screen'deki "Four Kampüs" metinleri → `"Four Kampüs"`
- **Dosyalar:** `Assets.xcassets/AppIcon.appiconset/`, `LaunchScreen.storyboard` veya `LaunchScreen.xib`

#### 5.5. About ve Settings Ekranları
- **About Screen:** Uygulama hakkında bilgiler, telif hakları
  - `"© 2025 Four Kampüs"` → `"© 2025 Four Kampüs"`
  - `"Four Kampüs tarafından geliştirilmiştir"` → `"Four Kampüs tarafından geliştirilmiştir"`
- **Settings Screen:** Ayarlar ekranındaki brand referansları
- **Dosyalar:** `AboutViewController.swift`, `SettingsViewController.swift` ve benzeri

#### 5.6. Push Notification Messages
- Push bildirimlerinde görünen "Four Kampüs" metinleri:
  - `"Four Kampüs'ten bildirim"` → `"Four Kampüs'ten bildirim"`
- **Not:** Backend'den gelen push notification payload'larında da kontrol edilmeli

#### 5.7. Deep Link Handlers (⚠️ DİKKAT)
- **URL Scheme:** `unifour://` protokolü **DEĞİŞTİRİLMEMELİ** (backend ile uyumlu kalması gerekiyor)
- **Not:** Deep link handler kodları (`AppDelegate.swift`, `SceneDelegate.swift` vb.) içindeki `unifour://` referansları değiştirilmeyecek

#### 5.8. Xcode Proje Ayarları
- **Product Name:** Proje ayarlarında "Four Kampüs" → `"Four Kampüs"` (eğer görünürse)
- **Scheme Names:** Scheme isimleri genellikle teknik olduğundan değiştirilmeyebilir
- **Dosyalar:** `.xcodeproj/project.pbxproj` (dikkatli düzenlenmeli)

#### 5.9. Copyright ve License Dosyaları
- **LICENSE:** Lisans dosyasındaki "Four Kampüs" referansları
- **README.md:** README dosyasındaki brand isimleri
- **Credits.rtf:** Credits dosyasındaki telif hakları bilgileri

---

## 🔄 Değişiklik Stratejisi

### Faz 1: Template Dosyaları (Yüksek Öncelik)
1. ✅ `templates/template_index.php` - En kapsamlı dosya
2. ✅ `templates/template_login.php` - Kullanıcı görünürlüğü yüksek
3. ✅ `templates/template_market.php` - Public görünürlük

### Faz 2: Public Dosyalar
4. ⚠️ `public/index.php` - Deep link'ler değiştirilmeyecek (sadece bilgilendirme)

### Faz 3: Swift iOS Projesi
5. ✅ `unipanel_swift/` - Mobil uygulama brand dönüşümü
   - Info.plist ve localization dosyaları
   - Swift kaynak kodları
   - UI dosyaları ve storyboard'lar
   - App icon ve splash screen

---

## ⚠️ Dikkat Edilmesi Gerekenler

### 1. Email Adresleri
- `info@unifour.com` → `info@fourkampus.com` veya `info@foursoftware.com`?
- **Karar Gerekiyor:** Hangi email adresi kullanılacak?

### 2. Deep Link'ler
- `unifour://` protokolü mobil uygulama için kritik
- Frontend'de görünse bile değiştirilmeyecek
- Backend ile uyumlu kalması gerekiyor
- **Swift projesinde de deep link handler'lar değiştirilmeyecek**

### 3. Telif Hakkı Metinleri
- Tüm copyright metinleri güncellenecek
- "Four Software tarafından" kısmı korunacak

### 4. Swift Projesi İçin Özel Notlar
- Proje workspace'te bulunmuyor, ayrı bir dizinde (`unipanel_swift/`)
- Tüm `.swift` dosyalarında grep ile "Four Kampüs" ve "fourkampus" aranmalı
- Localization dosyaları tüm dillerde kontrol edilmeli
- Xcode proje dosyası düzenlenirken dikkatli olunmalı

---

## 📊 İstatistikler

- **Toplam Dosya Sayısı:** 5 ana bölüm (4 PHP dosyası + 1 Swift projesi)
- **Toplam Değişiklik Noktası:** ~25+ nokta (PHP) + Swift projesinde değişken sayıda
- **En Kapsamlı Dosya:** `templates/template_index.php` (~15+ değişiklik)
- **Tahmini Süre:** 
  - PHP dosyaları: 30-45 dakika
  - Swift projesi: 1-2 saat (proje büyüklüğüne bağlı)

---

## ✅ Kontrol Listesi

### Değişiklik Öncesi
- [ ] Git commit yapıldı mı? (Yedekleme)
- [ ] Email adresi kararı verildi mi? (`info@fourkampus.com` vs `info@foursoftware.com`)
- [ ] Deep link'lerin değiştirilmeyeceği onaylandı mı?
- [ ] Swift projesi erişilebilir ve yedeklendi mi?

### Değişiklik Sırası (PHP)
- [ ] `templates/template_index.php` - Tüm değişiklikler yapıldı
- [ ] `templates/template_login.php` - Tüm değişiklikler yapıldı
- [ ] `templates/template_market.php` - Tüm değişiklikler yapıldı
- [ ] `public/index.php` - Deep link'ler kontrol edildi (değiştirilmedi)

### Değişiklik Sırası (Swift iOS)
- [ ] `Info.plist` ve `InfoPlist.strings` - App display name güncellendi
- [ ] `Localizable.strings` (tüm diller) - Localization metinleri güncellendi
- [ ] Swift kaynak kodları (`.swift` dosyaları) - Hardcoded strings güncellendi
- [ ] App icon ve splash screen - Brand görselleri güncellendi
- [ ] About/Settings ekranları - UI metinleri güncellendi
- [ ] Push notification strings - Bildirim metinleri güncellendi
- [ ] Deep link handler'lar kontrol edildi (değiştirilmedi)
- [ ] Copyright ve license dosyaları güncellendi

### Değişiklik Sonrası
- [ ] Tüm PHP dosyaları test edildi
- [ ] Frontend görünümü kontrol edildi
- [ ] Email şablonları test edildi
- [ ] SMS şablonları test edildi
- [ ] Copyright metinleri kontrol edildi
- [ ] Swift uygulaması derlendi ve test edildi
- [ ] Swift uygulamasında tüm ekranlar kontrol edildi
- [ ] Git commit yapıldı

---

## 🚀 Uygulama Adımları

1. **Yedekleme:** Git commit yap (hem PHP hem Swift projesi için)
2. **Email Kararı:** `info@fourkampus.com` veya `info@foursoftware.com` kararı ver
3. **PHP Değişiklikleri:** Template dosyalarını sırayla güncelle
4. **Swift Değişiklikleri:** Swift projesinde sistematik olarak güncelleme yap
5. **Test:** Her dosya ve ekranı test et
6. **Final Kontrol:** Tüm değişiklikleri gözden geçir
7. **Commit:** Değişiklikleri commit et

---

## 📝 Notlar

- Bu plan **frontend** değişikliklerini kapsar (PHP templates ve Swift iOS)
- Backend/API dosyalarındaki deep link'ler değiştirilmeyecek
- Email header'ları (`templates/functions/communication.php`) değiştirilmeyecek (kullanıcı isteği)
- Email adresi değişikliği için karar gerekiyor
- Tüm değişiklikler kullanıcı görünürlüğüne odaklıdır
- Swift projesi için workspace'te bulunmayabilir, ayrı bir dizinde (`unipanel_swift/`) kontrol edilmeli

---

**Plan Tarihi:** 2025-01-XX
**Hazırlayan:** AI Assistant
**Durum:** Hazır - Uygulamaya Geçilebilir
