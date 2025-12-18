#!/bin/bash
# Manuel İzin Düzeltme Scripti
# Bu script'i sudo ile çalıştırın: sudo ./fix_permissions_manual.sh

echo "🔧 Topluluk klasör ve veritabanı izinlerini düzeltiyorum..."

cd "$(dirname "$0")/.."

# Tüm topluluk klasörlerini düzelt
for dir in communities/*/; do
    if [ -d "$dir" ]; then
        comm_id=$(basename "$dir")
        echo "📝 Düzeltiliyor: $comm_id..."
        
        # Klasör izinlerini düzelt
        chmod -R 755 "$dir"
        
        # Veritabanı dosyası varsa izinlerini düzelt
        if [ -f "$dir/unipanel.sqlite" ]; then
            chmod 666 "$dir/unipanel.sqlite"
            echo "   ✅ Veritabanı izinleri düzeltildi"
        fi
    fi
done

echo "✅ İşlem tamamlandı!"
