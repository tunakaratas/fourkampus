#!/bin/bash
# İzin Sorunlarını Düzelt - SUDO GEREKLİ
# Kullanım: sudo ./fix_permissions_final.sh

echo "🔧 Topluluk izin sorunlarını düzeltiyorum..."
echo "⚠️  Bu script sudo yetkisi gerektirir!"
echo ""

cd "$(dirname "$0")/.."

# Web sunucusu kullanıcısını belirle
WEB_USER="tunakaratas"

# Sorunlu topluluklar
PROBLEMATIC=("aaa" "aabb")

for comm_id in "${PROBLEMATIC[@]}"; do
    COMM_DIR="communities/$comm_id"
    DB_FILE="$COMM_DIR/unipanel.sqlite"
    
    if [ ! -d "$COMM_DIR" ]; then
        echo "❌ Klasör bulunamadı: $COMM_DIR"
        continue
    fi
    
    echo "📝 Düzeltiliyor: $comm_id..."
    
    # Klasör sahibini ve izinlerini değiştir
    chown -R "$WEB_USER" "$COMM_DIR"
    chmod -R 755 "$COMM_DIR"
    
    # Veritabanı dosyası varsa sahibini ve izinlerini değiştir
    if [ -f "$DB_FILE" ]; then
        chown "$WEB_USER" "$DB_FILE"
        chmod 666 "$DB_FILE"
        
        # WAL dosyalarını da düzelt
        if [ -f "$DB_FILE-wal" ]; then
            chown "$WEB_USER" "$DB_FILE-wal"
            chmod 666 "$DB_FILE-wal"
        fi
        
        if [ -f "$DB_FILE-shm" ]; then
            chown "$WEB_USER" "$DB_FILE-shm"
            chmod 666 "$DB_FILE-shm"
        fi
        
        echo "   ✅ Veritabanı izinleri düzeltildi"
    else
        echo "   ⚠️  Veritabanı dosyası bulunamadı"
    fi
    
    # Son kontrol
    if [ -f "$DB_FILE" ]; then
        if [ -r "$DB_FILE" ] && [ -w "$DB_FILE" ]; then
            echo "   ✅ Başarılı - Dosya okunabilir ve yazılabilir"
        else
            echo "   ⚠️  Dosya hala erişilemiyor"
        fi
    fi
    
    echo ""
done

echo "✅ İşlem tamamlandı!"
echo ""
echo "Test için çalıştırın:"
echo "  php tools/test_all_communities_membership.php"
