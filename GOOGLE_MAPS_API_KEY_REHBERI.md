# Google Maps API Key Alma Rehberi

## 📋 Genel Bilgiler

### Ücretlendirme
- ✅ **Ücretsiz aylık limitler** var (kategoriye göre değişir)
- ✅ **Yeni hesaplar için $300 ücretsiz deneme kredisi** (12 ay)
- ⚠️ **Billing hesabı gerekiyor** ama ücretsiz limitler dahilinde ücretlendirilmez
- 📊 **Kullanım limitleri**: Her API için farklı ücretsiz limitler var

### Hangi API'ler Gerekli?
1. **Maps JavaScript API** - Harita görüntüleme için
2. **Places API** - Konum arama için
3. **Geocoding API** - Adres ↔ Koordinat dönüşümü için

---

## 🚀 Adım Adım API Key Alma

### 1. Google Cloud Console'a Giriş
- https://console.cloud.google.com/ adresine gidin
- Google hesabınızla giriş yapın

### 2. Yeni Proje Oluşturma
1. Üst menüden **"Proje Seç"** dropdown'ına tıklayın
2. **"YENİ PROJE"** butonuna tıklayın
3. Proje adı girin (örn: "Four Kampüs Maps")
4. **"Oluştur"** butonuna tıklayın
5. Proje oluşturulduktan sonra seçili olduğundan emin olun

### 3. Billing Hesabı Ekleme
1. Sol menüden **"Faturalandırma"** (Billing) seçin
2. **"Hesap Bağla"** (Link a billing account) butonuna tıklayın
3. Ödeme bilgilerinizi girin
   - ⚠️ **Not**: Ücretsiz limitler dahilinde ücretlendirilmez
   - 💰 Yeni hesaplar için $300 ücretsiz kredi var

### 4. Gerekli API'leri Etkinleştirme
1. Sol menüden **"API'ler ve Hizmetler"** > **"Kütüphane"** seçin
2. Aşağıdaki API'leri tek tek arayıp **"Etkinleştir"** butonuna tıklayın:
   - **Maps JavaScript API**
   - **Places API**
   - **Geocoding API**

### 5. API Key Oluşturma
1. Sol menüden **"API'ler ve Hizmetler"** > **"Kimlik Bilgileri"** seçin
2. **"KİMLİK BİLGİLERİ OLUŞTUR"** butonuna tıklayın
3. **"API anahtarı"** seçeneğini seçin
4. API key oluşturulacak, **kopyalayın** (daha sonra gösterilmeyecek!)

### 6. API Key Kısıtlama (ÖNERİLİR - Güvenlik)
1. Oluşturduğunuz API key'e tıklayın
2. **"Uygulama kısıtlamaları"** bölümünde:
   - **"HTTP referrers (web sitesi)"** seçin
   - **"Web sitesi kısıtlamaları"** altına şunları ekleyin:
     ```
     yourdomain.com/*
     *.yourdomain.com/*
     localhost:*
     ```
3. **"API kısıtlamaları"** bölümünde:
   - **"Anahtarı şu API'larla sınırla"** seçin
   - Şu API'leri seçin:
     - Maps JavaScript API
     - Places API
     - Geocoding API
4. **"Kaydet"** butonuna tıklayın

---

## 🔧 Projeye API Key Ekleme

### 1. credentials.php Dosyasını Düzenle
`/config/credentials.php` dosyasını açın ve şu kısmı ekleyin:

```php
'google_maps' => [
    'api_key' => 'BURAYA_API_KEY_YAPISTIRIN'
]
```

### 2. Örnek credentials.php
```php
<?php
return [
    'smtp' => [
        // ... SMTP ayarları
    ],
    
    'netgsm' => [
        // ... NetGSM ayarları
    ],
    
    'google_maps' => [
        'api_key' => 'AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
    ]
];
```

---

## 📊 Ücretsiz Kullanım Limitleri (Yaklaşık)

- **Maps JavaScript API**: Aylık 28,000 harita yükleme
- **Places API**: Aylık 17,000 istek
- **Geocoding API**: Aylık 40,000 istek

> ⚠️ **Not**: Limitler değişebilir, güncel bilgi için Google Cloud Console'dan kontrol edin.

---

## 🔒 Güvenlik İpuçları

1. ✅ **API key'i asla public repository'ye commit etmeyin**
2. ✅ **Key'i kısıtlayın** (sadece gerekli domain'lerden erişim)
3. ✅ **Sadece gerekli API'leri etkinleştirin**
4. ✅ **Kullanım limitlerini izleyin** (Google Cloud Console'dan)

---

## ❓ Sorun Giderme

### "This API project is not authorized to use this API"
- İlgili API'yi etkinleştirdiğinizden emin olun
- Billing hesabının bağlı olduğundan emin olun

### "RefererNotAllowedMapError"
- API key kısıtlamalarını kontrol edin
- Domain'in doğru eklendiğinden emin olun

### Harita görünmüyor
- Browser console'da hata var mı kontrol edin
- API key'in doğru eklendiğinden emin olun
- Maps JavaScript API'nin etkin olduğundan emin olun

---

## 📞 Destek

- Google Cloud Console: https://console.cloud.google.com/
- Google Maps Platform Dokümantasyonu: https://developers.google.com/maps
- Fiyatlandırma: https://developers.google.com/maps/billing-and-pricing
