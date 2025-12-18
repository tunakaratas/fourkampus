# Register 2FA Deployment Guide

## Production Sunucusuna Yüklenecek Dosyalar

Aşağıdaki dosyaları production sunucusuna yükleyin:

1. `/api/register_2fa.php`
2. `/api/endpoints/register_2fa.php`
3. `/api/router.php` (güncellenmiş)
4. `/api/.htaccess` (güncellenmiş)
5. `/api/index.php` (güncellenmiş)

## Dosya Yükleme Komutları

```bash
# Production sunucusuna bağlan
ssh user@foursoftware.com.tr

# Dosyaları yükle
scp /Applications/XAMPP/xamppfiles/htdocs/unipanel/api/register_2fa.php user@foursoftware.com.tr:/path/to/unipanel/api/
scp /Applications/XAMPP/xamppfiles/htdocs/unipanel/api/endpoints/register_2fa.php user@foursoftware.com.tr:/path/to/unipanel/api/endpoints/
scp /Applications/XAMPP/xamppfiles/htdocs/unipanel/api/router.php user@foursoftware.com.tr:/path/to/unipanel/api/
scp /Applications/XAMPP/xamppfiles/htdocs/unipanel/api/.htaccess user@foursoftware.com.tr:/path/to/unipanel/api/
scp /Applications/XAMPP/xamppfiles/htdocs/unipanel/api/index.php user@foursoftware.com.tr:/path/to/unipanel/api/
```

## Test

Production'da test etmek için:

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

- Dosyalar yüklendikten sonra `.htaccess` kurallarının çalıştığından emin olun
- Apache mod_rewrite modülünün aktif olduğundan emin olun
- Dosya izinlerini kontrol edin (644 veya 755)

