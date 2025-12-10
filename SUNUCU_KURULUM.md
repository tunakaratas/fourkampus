# 🚀 UniPanel Sunucu Kurulum Talimatları

## 📦 Dosya Yükleme

1. **Zip dosyasını indirin**: `unipanel.zip`
2. **Sunucuya yükleyin**: FTP/cPanel File Manager veya SSH ile
3. **Zip dosyasını çıkarın**: 
   ```bash
   unzip unipanel.zip
   ```

## ⚙️ Sunucu Gereksinimleri

- **PHP**: 8.2 veya üzeri
- **Apache/Nginx**: mod_rewrite aktif olmalı
- **SQLite3**: PHP extension aktif olmalı
- **Dosya İzinleri**: 
  - `storage/` klasörü: `755` veya `775`
  - `logs/` klasörü: `755` veya `775`
  - `communities/` klasörü: `755` veya `775`

## 🔧 Kurulum Adımları

### 1. Dosya İzinlerini Ayarlayın

```bash
chmod -R 755 storage/
chmod -R 755 logs/
chmod -R 755 communities/
chmod 644 .htaccess
```

### 2. Veritabanı Klasörünü Oluşturun

```bash
mkdir -p storage/databases
chmod 755 storage/databases
```

### 3. Apache .htaccess Kontrolü

`.htaccess` dosyasının aktif olduğundan emin olun. Apache'de `mod_rewrite` modülünün aktif olması gerekir.

### 4. PHP Ayarları

`php.ini` dosyasında şu ayarların aktif olduğundan emin olun:
```ini
extension=sqlite3
extension=pdo_sqlite
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
```

### 5. İlk Giriş

1. Tarayıcıda şu adresi açın: `https://yourdomain.com/superadmin/`
2. Varsayılan giriş bilgileri:
   - **Kullanıcı Adı**: `superadmin`
   - **Şifre**: `SuperAdmin2024!`
3. İlk girişten sonra şifrenizi değiştirin!

## 🔒 Güvenlik Kontrolleri

### Sunucu Ayarları

1. **Error Display**: Production'da kapatın
   ```php
   display_errors = Off
   log_errors = On
   ```

2. **SSL Sertifikası**: HTTPS kullanın (Let's Encrypt ücretsiz)

3. **Dosya İzinleri**: Hassas dosyaların erişimini kısıtlayın
   ```bash
   chmod 600 config/*.php
   ```

## 📁 Önemli Klasörler

- `superadmin/` - SuperAdmin paneli
- `communities/` - Topluluk dosyaları (her topluluk için ayrı klasör)
- `storage/` - Yüklenen dosyalar ve veritabanları
- `templates/` - Template dosyaları
- `api/` - API endpoint'leri

## 🔄 Güncelleme

Yeni bir sürüm yüklerken:

1. Mevcut dosyaların yedeğini alın
2. Yeni dosyaları yükleyin
3. `storage/` ve `communities/` klasörlerini koruyun
4. Veritabanı dosyalarını (`*.sqlite`) koruyun

## 🐛 Sorun Giderme

### 500 Internal Server Error
- `.htaccess` dosyasını kontrol edin
- Apache error log'larına bakın
- PHP error log'larına bakın

### Veritabanı Hatası
- `storage/databases/` klasörünün yazılabilir olduğundan emin olun
- SQLite3 extension'ının aktif olduğunu kontrol edin

### Dosya Yükleme Hatası
- `storage/` klasörünün yazılabilir olduğundan emin olun
- PHP `upload_max_filesize` ve `post_max_size` ayarlarını kontrol edin

## 📞 Destek

Sorun yaşarsanız:
1. `logs/` klasöründeki log dosyalarını kontrol edin
2. PHP error log'larını kontrol edin
3. Apache/Nginx error log'larını kontrol edin

---

**Not**: İlk kurulumdan sonra mutlaka varsayılan şifreleri değiştirin!

