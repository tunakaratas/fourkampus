# 🚀 Sunucuya GitHub'dan Kurulum

## Adım 1: GitHub Secret'ı Unblock Et

GitHub secret taraması `config/credentials.php` dosyasındaki API anahtarını tespit etti. Push yapabilmek için:

1. Bu URL'ye gidin: https://github.com/tunakaratas/unipanel/security/secret-scanning/unblock-secret/36ePeIeXelSBt2P5RJDvGSuAbG0
2. "Allow secret" butonuna tıklayın
3. Ardından push işlemi başarılı olacak

**VEYA** GitHub'da repository ayarlarından secret scanning'i geçici olarak kapatabilirsiniz.

## Adım 2: Sunucuya Bağlan ve Projeyi Çek

```bash
# Sunucuya SSH ile bağlan
ssh root@89.252.152.125

# Şifre: 651CceSl

# Sunucuda çalıştırılacak komutlar:
cd /var/www/html

# Git kurulu mu kontrol et
git --version || (apt-get update && apt-get install -y git)

# Projeyi clone et
git clone https://github.com/tunakaratas/unipanel.git
cd unipanel

# Dosya izinlerini ayarla
chmod -R 755 storage/
chmod -R 755 logs/
chmod -R 755 communities/
chmod 644 .htaccess

# Storage klasörlerini oluştur
mkdir -p storage/databases
mkdir -p storage/uploads
mkdir -p storage/cache
chmod -R 755 storage/

# Config dosyasını oluştur (credentials.php.example'dan kopyala)
cp config/credentials.example.php config/credentials.php
# Sonra config/credentials.php dosyasını düzenle ve API anahtarlarını ekle

# PHP ayarlarını kontrol et
php -v
php -m | grep sqlite
```

## Adım 3: Apache/Nginx Yapılandırması

### Apache için:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/unipanel
    
    <Directory /var/www/html/unipanel>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx için:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/unipanel;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Adım 4: İlk Giriş

1. Tarayıcıda: `https://yourdomain.com/superadmin/`
2. Varsayılan giriş:
   - **Kullanıcı**: `superadmin`
   - **Şifre**: `SuperAdmin2024!`
3. **İlk girişten sonra mutlaka şifrenizi değiştirin!**

## Güncelleme

Sunucuda güncelleme yapmak için:

```bash
cd /var/www/html/unipanel
git pull origin main
```

## Sorun Giderme

### 500 Internal Server Error
- `.htaccess` dosyasını kontrol edin
- Apache error log: `tail -f /var/log/apache2/error.log`
- PHP error log: `tail -f /var/log/php8.2-fpm.log`

### Veritabanı Hatası
- `storage/databases/` klasörünün yazılabilir olduğundan emin olun
- SQLite3 extension'ının aktif olduğunu kontrol edin: `php -m | grep sqlite`

### Dosya Yükleme Hatası
- `storage/` klasörünün yazılabilir olduğundan emin olun
- PHP `upload_max_filesize` ve `post_max_size` ayarlarını kontrol edin

