# 💳 Abonelik Sistemi - Iyzico Entegrasyonu

## 📋 Genel Bakış

Her topluluk için aylık 250₺ abonelik sistemi. Iyzico ödeme altyapısı kullanılarak ödeme alınır.

## 🎯 Özellikler

- ✅ Aylık 250₺ abonelik ücreti
- ✅ 1 ay kullanım hakkı
- ✅ Iyzico ödeme entegrasyonu
- ✅ Abonelik durumu takibi
- ✅ Kalan gün gösterimi

## 📁 Dosya Yapısı

```
lib/payment/
├── SubscriptionManager.php    # Abonelik yönetim sınıfı
├── IyzicoHelper.php           # Iyzico ödeme helper
└── README.md                  # Bu dosya

templates/
├── functions/
│   └── subscription.php       # Abonelik görünümü
└── payment_callback.php       # Ödeme callback handler
```

## 🔧 Kullanım

### 1. Abonelik Sekmesi

Topluluk admin panelinde **"Abonelik"** sekmesi eklendi. Bu sekmede:
- Abonelik durumu görüntülenir
- Ödeme yapılabilir
- Kalan gün sayısı gösterilir

### 2. Ödeme İşlemi

1. Abonelik sekmesine gidin
2. "Ödeme Yap (250₺)" butonuna tıklayın
3. Iyzico ödeme sayfasına yönlendirilirsiniz
4. Ödeme tamamlandıktan sonra otomatik olarak geri dönülür

### 3. Veritabanı

Abonelik bilgileri her topluluğun kendi veritabanında `subscriptions` tablosunda saklanır:

```sql
CREATE TABLE subscriptions (
    id INTEGER PRIMARY KEY,
    community_id TEXT,
    payment_id TEXT,
    payment_status TEXT,
    amount REAL,
    start_date DATETIME,
    end_date DATETIME,
    is_active INTEGER
)
```

## ⚙️ Iyzico Konfigürasyonu

### Test Ortamı

`lib/payment/IyzicoHelper.php` dosyasında test API anahtarlarını ayarlayın:

```php
$this->apiKey = 'sandbox-xxxxx';
$this->secretKey = 'sandbox-xxxxx';
```

### Production Ortamı

Production için Iyzico'dan alınan gerçek API anahtarlarını kullanın:

```php
define('IYZICO_LIVE_API_KEY', 'xxxxx');
define('IYZICO_LIVE_SECRET_KEY', 'xxxxx');
```

## 📝 Sonraki Adımlar

1. **Iyzico SDK Kurulumu**: `composer require iyzico/iyzipay-php`
2. **API Anahtarları**: Iyzico panelinden API anahtarlarını alın
3. **Test Ödemesi**: Test ortamında ödeme akışını test edin
4. **Production**: Production API anahtarlarını ayarlayın

## 🔒 Güvenlik

- Tüm ödeme işlemleri Iyzico üzerinden yapılır
- Ödeme bilgileri sistemde saklanmaz
- Abonelik durumu veritabanında şifrelenmiş olarak tutulur

## 📞 Destek

Sorularınız için proje dokümantasyonuna bakın: `docs/IYZICO_INTEGRATION_GUIDE.md`

