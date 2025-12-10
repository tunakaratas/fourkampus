# E-posta Profil Fotoğrafına Logo Ekleme Rehberi

E-posta adresinizin profil fotoğrafına logo eklemek için iki yöntem var:

## Yöntem 1: Gravatar (Önerilen - En Kolay)

Gravatar, e-posta adresine bağlı profil fotoğrafı gösteren bir servistir. Gmail, Outlook ve diğer e-posta istemcileri otomatik olarak Gravatar'dan fotoğrafı çeker.

### Adımlar:

1. **Gravatar.com'a git**: https://gravatar.com
2. **Hesap oluştur**: "Create Your Gravatar" butonuna tıkla
3. **E-posta adresini ekle**: `admin@foursoftware.com.tr` adresini ekle ve doğrula
4. **Logo yükle**: 
   - "Add a new image" butonuna tıkla
   - Logonu yükle (en az 200x200px, kare format önerilir)
   - Logoyu seç ve "Crop Image" ile kare yap
   - "Set as primary" ile birincil yap
5. **Tamamla**: Artık bu e-posta adresinden gönderilen tüm e-postalarda logo görünecek

### Özellikler:
- ✅ Ücretsiz
- ✅ Tüm e-posta istemcilerinde çalışır (Gmail, Outlook, Apple Mail vb.)
- ✅ Otomatik olarak kullanılır
- ✅ Hızlı kurulum (5 dakika)

---

## Yöntem 2: Güzel Hosting E-posta Sunucusu Paneli

Güzel Hosting e-posta sunucusu üzerinden kullanıcı fotoğrafı ayarlanabilir.

### Adımlar:

1. **Güzel Hosting paneli**: https://ms7.guzel.net.tr/iredadmin
2. **Giriş yap**: 
   - Kullanıcı Adı: `admin@foursoftware.com.tr`
   - Şifre: `NokT40a0u7`
3. **Kullanıcıyı bul**: `admin@foursoftware.com.tr` kullanıcısını bul
4. **Profil fotoğrafı ekle**: Kullanıcı ayarlarından profil fotoğrafı yükle
5. **Kaydet**: Değişiklikleri kaydet

### Not:
- Bu yöntem sadece Exchange/ActiveSync kullanan istemcilerde çalışır
- Gmail gibi web tabanlı istemciler Gravatar'ı tercih eder

---

## Yöntem 3: Config Dosyasından Logo URL (Opsiyonel)

Eğer logonu bir web sunucusunda barındırıyorsan, config dosyasına URL ekleyebilirsin:

```php
// config/credentials.php
'smtp' => [
    // ... diğer ayarlar
    'logo_url' => 'https://example.com/logo.png' // Logo URL'si
],
```

**Not**: Bu yöntem çoğu e-posta istemcisi tarafından desteklenmez. Gravatar kullanman önerilir.

---

## Hangi Yöntemi Seçmeliyim?

**Gravatar kullan** çünkü:
- ✅ En yaygın desteklenen yöntem
- ✅ Tüm e-posta istemcilerinde çalışır
- ✅ Ücretsiz ve kolay kurulum
- ✅ Otomatik olarak kullanılır

**Güzel Hosting paneli** sadece Exchange/ActiveSync kullanan istemciler için.

---

## Test Etme

Logo ekledikten sonra:

1. **Gravatar test**: https://gravatar.com/avatar/cbdc2661feae6ca7599c0921ec68e1ec?s=200
   - `admin@foursoftware.com.tr` için MD5 hash: `cbdc2661feae6ca7599c0921ec68e1ec`
   - Bu linke tıklayarak logonun göründüğünü kontrol edebilirsin

2. **E-posta gönder**: Kendine bir test e-postası gönder ve profil fotoğrafını kontrol et

3. **Gmail'de kontrol**: Gmail'de gönderenin profil fotoğrafını kontrol et

---

## Sorun Giderme

**Logo görünmüyor?**
- Gravatar'da logonun "primary" olarak ayarlandığından emin ol
- E-posta adresinin doğru olduğundan emin ol
- Birkaç saat bekle (cache temizlenmesi için)

**Gravatar'da logo yok ama görünüyor?**
- E-posta istemcisi cache'i temizle
- Farklı bir e-posta istemcisinde dene

