# Swift iOS Projesi - Four Kampüs Dönüşüm Rehberi

## 📋 Genel Bakış
Bu rehber, Swift iOS projesinde (`unipanel_swift/`) "UniFour" ve "unifour" referanslarını "Four Kampüs"e çevirmek için hazırlanmıştır.

**Önemli:** Deep link handler'lar (`unifour://` protokolü) **DEĞİŞTİRİLMEMELİ** - backend ile uyumlu kalması gerekiyor.

---

## 🔍 Yapılacak Değişiklikler

### 1. Info.plist ve InfoPlist.strings

**Dosyalar:**
- `Info.plist`
- `InfoPlist.strings` (tüm diller için: `tr.lproj/InfoPlist.strings`, `en.lproj/InfoPlist.strings`, vs.)

**Değiştirilecek Alanlar:**
- `CFBundleDisplayName`: `UniFour` → `Four Kampüs`
- `CFBundleName`: `UniFour` → `Four Kampüs`
- `CFBundleExecutable`: Genellikle teknik olduğundan değiştirilmeyebilir

**Örnek:**
```xml
<!-- Önce -->
<key>CFBundleDisplayName</key>
<string>UniFour</string>

<!-- Sonra -->
<key>CFBundleDisplayName</key>
<string>Four Kampüs</string>
```

---

### 2. Localization Strings (Localizable.strings)

**Dosyalar:**
- `tr.lproj/Localizable.strings`
- `en.lproj/Localizable.strings`
- Diğer dil dosyaları (varsa)

**Değiştirilecek Metinler:**
- `"UniFour"` → `"Four Kampüs"`
- `"UniFour'a"` → `"Four Kampüs'e"`
- `"UniFour'a hoş geldiniz"` → `"Four Kampüs'e hoş geldiniz"`
- `"UniFour'dan bildirim"` → `"Four Kampüs'ten bildirim"`
- Tüm kullanıcıya görünen metinlerdeki "UniFour" referansları

**Örnek:**
```swift
// Önce
"welcome_message" = "UniFour'a hoş geldiniz!";

// Sonra
"welcome_message" = "Four Kampüs'e hoş geldiniz!";
```

**Komut:**
```bash
# Tüm localization dosyalarında arama yap
grep -r "UniFour" unipanel_swift/*.lproj/
```

---

### 3. Swift Kaynak Kodları (.swift)

**Dosyalar:**
- Tüm `.swift` dosyaları

**Değiştirilecek:**
- Hardcoded string literal'lar: `"UniFour"` → `"Four Kampüs"`
- Alert mesajları
- Error mesajları
- Debug mesajları (kullanıcıya görünmeyenler hariç)

**⚠️ DEĞİŞTİRİLMEYECEK:**
- Deep link handler kodları (`unifour://` protokolü)
- API endpoint'leri
- Bundle identifier'lar (teknik referanslar)

**Komut:**
```bash
# Tüm Swift dosyalarında arama yap
grep -r "UniFour" unipanel_swift/ --include="*.swift"
grep -r "unifour" unipanel_swift/ --include="*.swift" -i
```

**Örnek Değişiklikler:**
```swift
// Önce
let appName = "UniFour"
let welcomeMessage = "UniFour'a hoş geldiniz!"

// Sonra
let appName = "Four Kampüs"
let welcomeMessage = "Four Kampüs'e hoş geldiniz!"
```

---

### 4. App İkonları ve Splash Screen

**Dosyalar:**
- `Assets.xcassets/AppIcon.appiconset/` (app icon dosyaları)
- `LaunchScreen.storyboard` veya `LaunchScreen.xib`
- `Assets.xcassets/LaunchImage.imageset/` (varsa)

**Kontrol Edilecek:**
- App icon'da "UniFour" yazısı varsa, görsel olarak güncellenmeli
- Splash screen'deki "UniFour" metinleri → `"Four Kampüs"`

**Not:** Görsel dosyalar manuel olarak düzenlenmelidir.

---

### 5. About ve Settings Ekranları

**Dosyalar:**
- `AboutViewController.swift`
- `SettingsViewController.swift`
- İlgili storyboard/xib dosyaları

**Değiştirilecek:**
- `"© 2025 UniFour"` → `"© 2025 Four Kampüs"`
- `"UniFour tarafından geliştirilmiştir"` → `"Four Kampüs tarafından geliştirilmiştir"`
- Ayarlar ekranındaki brand referansları

**Örnek:**
```swift
// Önce
copyrightLabel.text = "© 2025 UniFour - Tüm hakları saklıdır"

// Sonra
copyrightLabel.text = "© 2025 Four Kampüs - Tüm hakları saklıdır"
```

---

### 6. Push Notification Messages

**Dosyalar:**
- Push notification handler dosyaları
- Notification payload oluşturan kodlar

**Değiştirilecek:**
- `"UniFour'dan bildirim"` → `"Four Kampüs'ten bildirim"`
- Bildirim başlıklarında ve içeriklerinde "UniFour" referansları

**Not:** Backend'den gelen push notification payload'larında da kontrol edilmeli.

---

### 7. Deep Link Handlers (⚠️ DİKKAT - DEĞİŞTİRİLMEYECEK)

**Dosyalar:**
- `AppDelegate.swift`
- `SceneDelegate.swift`
- Deep link handler dosyaları

**⚠️ ÖNEMLİ:** `unifour://` protokolü **DEĞİŞTİRİLMEMELİ** - backend ile uyumlu kalması gerekiyor.

**Örnek (DEĞİŞTİRİLMEYECEK):**
```swift
// Bu kod DEĞİŞTİRİLMEMELİ
if url.scheme == "unifour" {
    // Deep link handling
}
```

---

### 8. Xcode Proje Ayarları

**Dosyalar:**
- `.xcodeproj/project.pbxproj` (dikkatli düzenlenmeli)

**Değiştirilecek:**
- Product Name: `UniFour` → `Four Kampüs` (eğer görünürse)

**⚠️ DİKKAT:** Xcode proje dosyası düzenlenirken çok dikkatli olunmalı. Yanlış düzenleme projeyi bozabilir.

**Önerilen Yöntem:**
1. Xcode'da projeyi aç
2. Project Navigator'da projeyi seç
3. Target'ı seç
4. General sekmesinde "Display Name" ve "Product Name" alanlarını kontrol et
5. Xcode üzerinden değiştir (manuel dosya düzenleme yerine)

---

### 9. Copyright ve License Dosyaları

**Dosyalar:**
- `LICENSE`
- `README.md`
- `Credits.rtf` (varsa)

**Değiştirilecek:**
- Lisans dosyasındaki "UniFour" referansları
- README dosyasındaki brand isimleri
- Credits dosyasındaki telif hakları bilgileri

---

## 🔧 Adım Adım Uygulama

### Adım 1: Yedekleme
```bash
cd unipanel_swift/
git status
git add .
git commit -m "Backup before UniFour to Four Kampüs conversion"
```

### Adım 2: Tüm Referansları Bul
```bash
# Tüm "UniFour" referanslarını bul
grep -r "UniFour" . --include="*.swift" --include="*.plist" --include="*.strings" --include="*.md"

# Tüm "unifour" referanslarını bul (case-insensitive)
grep -ri "unifour" . --include="*.swift" --include="*.plist" --include="*.strings"
```

### Adım 3: Deep Link'leri İşaretle
Deep link handler kodlarındaki `unifour://` referanslarını **DEĞİŞTİRMEYECEK** şekilde işaretle veya not al.

### Adım 4: Sistematik Değişiklik
1. Info.plist ve InfoPlist.strings dosyalarını güncelle
2. Localization dosyalarını güncelle
3. Swift kaynak kodlarını güncelle (deep link'ler hariç)
4. UI dosyalarını kontrol et ve güncelle
5. Copyright ve license dosyalarını güncelle

### Adım 5: Test
```bash
# Projeyi derle
xcodebuild -project YourProject.xcodeproj -scheme YourScheme -configuration Debug

# Simulator'da test et
# Tüm ekranları kontrol et
# Push notification'ları test et
```

### Adım 6: Final Kontrol
- [ ] Tüm ekranlarda "Four Kampüs" görünüyor mu?
- [ ] Deep link'ler hala çalışıyor mu? (`unifour://` protokolü)
- [ ] Push notification'lar doğru görünüyor mu?
- [ ] Copyright metinleri güncellendi mi?
- [ ] App display name "Four Kampüs" olarak görünüyor mu?

---

## 📝 Notlar

- Deep link'ler (`unifour://`) **ASLA** değiştirilmemeli
- Email header'ları (`X-Mailer: UniFour`) değiştirilmeyecek (kullanıcı isteği)
- Bundle identifier'lar genellikle teknik olduğundan değiştirilmeyebilir
- Xcode proje dosyası düzenlenirken çok dikkatli olunmalı

---

## ✅ Kontrol Listesi

- [ ] Info.plist ve InfoPlist.strings güncellendi
- [ ] Localization dosyaları (tüm diller) güncellendi
- [ ] Swift kaynak kodları güncellendi (deep link'ler hariç)
- [ ] App icon ve splash screen kontrol edildi
- [ ] About/Settings ekranları güncellendi
- [ ] Push notification strings güncellendi
- [ ] Deep link handler'lar kontrol edildi (değiştirilmedi)
- [ ] Copyright ve license dosyaları güncellendi
- [ ] Proje derlendi ve test edildi
- [ ] Tüm ekranlar kontrol edildi

---

**Hazırlayan:** AI Assistant
**Tarih:** 2025-01-XX
**Durum:** Rehber Hazır - Manuel Uygulama Gerekiyor
