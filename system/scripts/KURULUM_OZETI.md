# 🚀 Hızlı Kurulum Özeti

## Ne Yapıldı?

1. ✅ **Twilio kaldırıldı** - Artık sadece NetGSM kullanılıyor
2. ✅ **Environment variables sistemi eklendi** - Şifreler kod içinde değil
3. ✅ **Config dosyası desteği** - `config/credentials.php` dosyasından da okuyabilir

## 📋 Yapmanız Gerekenler

### Seçenek 1: Otomatik Kurulum (Önerilen)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/unipanel
php system/scripts/setup_environment.php
```

Script size sorular soracak, siz cevaplayacaksınız. `.env` dosyası otomatik oluşacak.

### Seçenek 2: Manuel Kurulum

**A) `.env` dosyası oluşturun:**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/unipanel
nano .env
```

Şu içeriği ekleyin:
```env
SMTP_USERNAME=admin@foursoftware.com.tr
SMTP_PASSWORD=plhewggoqbrtfhat
NETGSM_USERNAME=8503022568
NETGSM_PASSWORD=your_netgsm_password
NETGSM_MSGHEADER=8503022568
SYSTEM_SCRIPT_TOKEN=rastgele_güvenli_token
APP_ENV=development
```

**B) VEYA `config/credentials.php` dosyasını kullanın:**

```bash
cp config/credentials.example.php config/credentials.php
nano config/credentials.php
```

İçeriği doldurun (template'te zaten bu dosya kullanılıyor).

## ✅ Hazır!

Artık script'ler otomatik olarak:
1. Önce `.env` dosyasından okur
2. Yoksa `config/credentials.php` dosyasından okur
3. Hiçbiri yoksa hata verir

**Manuel bir şey yapmanıza gerek yok!** Script'ler otomatik çalışır.

## 🔒 Güvenlik

- ✅ Şifreler artık kod içinde değil
- ✅ `.env` ve `config/credentials.php` gitignore'da
- ✅ Twilio kaldırıldı, sadece NetGSM var

## ❓ Sorun mu var?

Detaylı rehber: `system/scripts/ENVIRONMENT_SETUP_REHBERI.md`

