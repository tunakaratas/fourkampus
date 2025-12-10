# Environment Variables Kurulum Rehberi

## 🎯 Ne İşe Yarar?

System script'lerinde kullanılan **şifreler, token'lar ve API key'ler** artık kod içinde değil, **güvenli bir şekilde environment variable olarak** saklanıyor.

## ⚡ Hızlı Kurulum

### 1. Otomatik Kurulum (Önerilen)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/unipanel
php system/scripts/setup_environment.php
```

Bu script size sorular soracak ve `.env` dosyasını oluşturacak.

### 2. Manuel Kurulum

Proje kök dizininde `.env` dosyası oluşturun:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/unipanel
nano .env
```

Şu içeriği ekleyin (kendi değerlerinizle değiştirin):

```env
# SMTP Ayarları (Email göndermek için)
SMTP_USERNAME=admin@foursoftware.com.tr
SMTP_PASSWORD=plhewggoqbrtfhat

# NetGSM Ayarları (SMS göndermek için)
NETGSM_USERNAME=8503022568
NETGSM_PASSWORD=your_netgsm_password
NETGSM_MSGHEADER=8503022568

# System Script Token (Web erişimi için güvenlik)
SYSTEM_SCRIPT_TOKEN=rastgele_güvenli_token_buraya

# Environment (production veya development)
APP_ENV=development

# Backup Admin Email (Opsiyonel)
BACKUP_ADMIN_EMAIL=admin@example.com
```

## 📝 Önemli Notlar

1. **`.env` dosyasını GİT'E EKLEMEYİN!** 
   - Bu dosya hassas bilgiler içerir
   - `.gitignore` dosyasına eklenmiş olmalı

2. **Alternatif: `config/credentials.php` kullanabilirsiniz**
   - Template'te zaten bu dosya kullanılıyor
   - `config/credentials.example.php` dosyasını kopyalayıp doldurun
   - Bu dosya da gitignore'da olmalı

3. **Production'da:**
   - `APP_ENV=production` yapın
   - Tüm token'ları güçlü değerlerle değiştirin

4. **Script'ler otomatik olarak yükler:**
   - Önce `.env` dosyasından okur
   - Yoksa `config/credentials.php` dosyasından okur
   - `load_env.php` dosyası tüm script'lerde otomatik çalışır

## 🔒 Güvenlik

- ✅ Şifreler artık kod içinde değil
- ✅ Her ortam için farklı değerler kullanabilirsiniz
- ✅ `.env` dosyası web'den erişilemez (`.htaccess` ile korumalı)

## ❓ Sorun mu var?

Eğer script'ler çalışmıyorsa:
1. `.env` dosyasının proje kök dizininde olduğundan emin olun
2. Dosya izinlerini kontrol edin: `chmod 600 .env`
3. Environment variable'ları manuel kontrol edin

