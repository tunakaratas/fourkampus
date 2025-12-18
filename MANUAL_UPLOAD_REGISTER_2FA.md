# Register 2FA Manuel Yükleme Talimatları

## Sorun
Production sunucusunda `register_2fa.php` dosyası bulunamıyor (404 hatası).

## Çözüm
Aşağıdaki dosyaları production sunucusuna yükleyin:

### 1. FTP/cPanel ile Yükleme

1. FTP client ile sunucuya bağlanın (FileZilla, Cyberduck, vb.)
2. Aşağıdaki dosyaları yükleyin:

```
/api/register_2fa.php
/api/endpoints/register_2fa.php
/api/router.php (güncellenmiş)
/api/.htaccess (güncellenmiş)
/api/index.php (güncellenmiş)
/.htaccess (güncellenmiş - ana klasör)
```

### 2. Dosya Yolları

Production sunucusunda dosyalar şu konumlarda olmalı:
- `/var/www/html/unipanel/api/register_2fa.php`
- `/var/www/html/unipanel/api/endpoints/register_2fa.php`
- `/var/www/html/unipanel/api/router.php`
- `/var/www/html/unipanel/api/.htaccess`
- `/var/www/html/unipanel/api/index.php`
- `/var/www/html/unipanel/.htaccess`

### 3. Dosya İzinleri

Dosyalar yüklendikten sonra izinleri kontrol edin:
```bash
chmod 644 /var/www/html/unipanel/api/register_2fa.php
chmod 644 /var/www/html/unipanel/api/endpoints/register_2fa.php
chmod 644 /var/www/html/unipanel/api/router.php
chmod 644 /var/www/html/unipanel/api/.htaccess
chmod 644 /var/www/html/unipanel/api/index.php
chmod 644 /var/www/html/unipanel/.htaccess
```

### 4. Test

Yükleme sonrası test edin:
```bash
curl -X POST "https://foursoftware.com.tr/unipanel/api/register_2fa.php" \
  -H "Content-Type: application/json" \
  -d '{"step":1,"email":"test@example.com"}'
```

Başarılı yanıt:
```json
{
  "success": true,
  "data": {
    "email": "test@example.com"
  },
  "message": "Doğrulama kodu e-posta adresinize gönderildi",
  "error": null
}
```

## Notlar

- Apache `mod_rewrite` modülünün aktif olduğundan emin olun
- `.htaccess` dosyalarının çalıştığından emin olun
- Dosya yollarının doğru olduğundan emin olun

