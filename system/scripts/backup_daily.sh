#!/bin/bash
# UNIPANEL GÜNLÜK YEDEKLEME SCRIPTİ

# Proje dizini
PROJECT_DIR="/Applications/XAMPP/xamppfiles/htdocs/unipanel"
BACKUP_DIR="$PROJECT_DIR/backups"
DATE=$(date +%Y-%m-%d_%H-%M-%S)

# Eski yedekleri sil
echo "Eski yedekler siliniyor..."
rm -f $BACKUP_DIR/*.zip

# Yeni yedek oluştur
echo "Yeni yedek oluşturuluyor..."
cd $PROJECT_DIR

# Önemli dosyaları yedekle
zip -r $BACKUP_DIR/unipanel_backup_$DATE.zip \
    superadmin/index.php \
    communities/template_index.php \
    communities/template_login.php \
    unipanel.sqlite \
    communities/*/unipanel.sqlite \
    communities/*/index.php \
    communities/*/login.php

# Yedek boyutunu göster
BACKUP_SIZE=$(du -h $BACKUP_DIR/unipanel_backup_$DATE.zip | cut -f1)
echo "Yedek oluşturuldu: unipanel_backup_$DATE.zip ($BACKUP_SIZE)"

# Log dosyasına yaz
echo "$(date): Yedek oluşturuldu - $BACKUP_SIZE" >> $BACKUP_DIR/backup.log
