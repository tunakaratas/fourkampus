#!/bin/bash

# UniPanel Sunucu Deployment Script
# Kullanım: ./SUNUCU_DEPLOY.sh

echo "🚀 UniPanel Sunucu Deployment Başlatılıyor..."

# Sunucu bilgileri
SERVER="root@89.252.152.125"
GITHUB_REPO="https://github.com/tunakaratas/unipanel.git"
DEPLOY_PATH="/var/www/html/unipanel"

# 1. Sunucuya bağlan ve projeyi çek
echo "📥 GitHub'dan proje çekiliyor..."
ssh $SERVER << 'ENDSSH'
    # Git kurulu mu kontrol et
    if ! command -v git &> /dev/null; then
        echo "Git kurulu değil, kuruluyor..."
        apt-get update && apt-get install -y git
    fi
    
    # Deployment dizini oluştur
    mkdir -p /var/www/html/unipanel
    cd /var/www/html
    
    # Eğer dizin zaten varsa ve git repo ise, pull yap
    if [ -d "unipanel/.git" ]; then
        echo "Mevcut repository güncelleniyor..."
        cd unipanel
        git pull origin main
    else
        # Yeni clone
        if [ -d "unipanel" ]; then
            echo "Mevcut dizin yedekleniyor..."
            mv unipanel unipanel_backup_$(date +%Y%m%d_%H%M%S)
        fi
        echo "Repository clone ediliyor..."
        git clone https://github.com/tunakaratas/unipanel.git
        cd unipanel
    fi
    
    # Dosya izinlerini ayarla
    echo "📁 Dosya izinleri ayarlanıyor..."
    chmod -R 755 storage/
    chmod -R 755 logs/
    chmod -R 755 communities/
    chmod 644 .htaccess
    
    # Storage klasörlerini oluştur
    mkdir -p storage/databases
    mkdir -p storage/uploads
    mkdir -p storage/cache
    chmod -R 755 storage/
    
    # PHP ayarlarını kontrol et
    echo "✅ Deployment tamamlandı!"
    echo "📝 Sonraki adımlar:"
    echo "   1. https://yourdomain.com/superadmin/ adresine gidin"
    echo "   2. Varsayılan giriş: superadmin / SuperAdmin2024!"
    echo "   3. Şifrenizi değiştirin!"
ENDSSH

echo "✅ Deployment script hazır!"
echo "Sunucuya bağlanmak için: ssh root@89.252.152.125"

