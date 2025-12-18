#!/bin/bash

# Proje dizinine git
cd /Applications/XAMPP/xamppfiles/htdocs/unipanel

# Değişiklik var mı kontrol et
if [[ -n $(git status -s) ]]; then
    # Tarih ve saat bilgisini al
    TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")
    
    # Değişiklikleri ekle ve commit yap
    git add .
    git commit -m "Auto-commit: $TIMESTAMP"
    
    # GitHub'a gönder
    git push origin main
    
    echo "[$TIMESTAMP] Değişiklikler başarıyla GitHub'a gönderildi."
else
    echo "$(date "+%Y-%m-%d %H:%M:%S") Değişiklik bulunamadı, işlem atlandı."
fi
