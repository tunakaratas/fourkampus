#!/bin/bash

# Register 2FA dosyalarını sunucuya yükleme scripti
# Kullanım: ./upload_register_2fa.sh

echo "📤 Register 2FA dosyaları sunucuya yükleniyor..."

# Sunucu bilgileri
SERVER="root@89.252.152.125"
REMOTE_BASE="/var/www/html/unipanel"

# Yüklenecek dosyalar
FILES=(
    "api/register_2fa.php"
    "api/endpoints/register_2fa.php"
    "api/router.php"
    "api/.htaccess"
    "api/index.php"
)

# Dosya var mı kontrol et ve yükle
for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ Hata: $file dosyası bulunamadı!"
        continue
    fi
    
    REMOTE_PATH="$REMOTE_BASE/$file"
    REMOTE_DIR=$(dirname "$REMOTE_PATH")
    
    echo "📝 Yükleniyor: $file"
    echo "🔐 Şifre: 651CceSl"
    echo ""
    
    # Klasörü oluştur ve dosyayı yükle
    cat "$file" | ssh "$SERVER" "mkdir -p $REMOTE_DIR && cat > $REMOTE_PATH && chmod 644 $REMOTE_PATH && echo '✅ $file başarıyla yüklendi!'"
    
    if [ $? -eq 0 ]; then
        echo "✅ $file başarıyla yüklendi!"
    else
        echo "❌ $file yüklenirken hata oluştu!"
    fi
    echo ""
done

echo "✅ Yükleme tamamlandı!"
echo ""
echo "🌐 Test URL: https://foursoftware.com.tr/unipanel/api/register_2fa.php"
echo ""
echo "Test komutu:"
echo "curl -X POST 'https://foursoftware.com.tr/unipanel/api/register_2fa.php' \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"step\":1,\"email\":\"test@example.com\"}'"

