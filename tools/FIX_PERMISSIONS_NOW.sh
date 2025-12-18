#!/bin/bash
# İZİN SORUNUNU ÇÖZ - TEK KOMUT
# Kullanım: sudo bash tools/FIX_PERMISSIONS_NOW.sh

cd "$(dirname "$0")/.."

WEB_USER="tunakaratas"
TEMP_DIR="/tmp/unipanel_db_fix"

echo "🔧 İzin sorunlarını çözüyorum..."
echo ""

# Geçici dosyalar var mı kontrol et
if [ ! -f "$TEMP_DIR/aaa.sqlite" ] || [ ! -f "$TEMP_DIR/aabb.sqlite" ]; then
    echo "📝 Geçici veritabanı dosyalarını oluşturuyorum..."
    php tools/create_temp_databases.php
fi

# İzinleri düzelt
for comm_id in "aaa" "aabb"; do
    COMM_DIR="communities/$comm_id"
    DB_FILE="$COMM_DIR/unipanel.sqlite"
    TEMP_FILE="$TEMP_DIR/$comm_id.sqlite"
    
    echo "📝 Düzeltiliyor: $comm_id..."
    
    # Klasör sahibini ve izinlerini değiştir
    chown -R "$WEB_USER" "$COMM_DIR" 2>/dev/null
    chmod -R 755 "$COMM_DIR" 2>/dev/null
    
    # Geçici dosyayı hedefe kopyala
    cp "$TEMP_FILE" "$DB_FILE" 2>/dev/null
    
    # Veritabanı dosyası sahibini ve izinlerini değiştir
    chown "$WEB_USER" "$DB_FILE" 2>/dev/null
    chmod 666 "$DB_FILE" 2>/dev/null
    
    # WAL dosyalarını da düzelt
    [ -f "$DB_FILE-wal" ] && chown "$WEB_USER" "$DB_FILE-wal" && chmod 666 "$DB_FILE-wal" 2>/dev/null
    [ -f "$DB_FILE-shm" ] && chown "$WEB_USER" "$DB_FILE-shm" && chmod 666 "$DB_FILE-shm" 2>/dev/null
    
    echo "   ✅ Tamamlandı"
done

echo ""
echo "✅ İşlem tamamlandı!"
echo ""
echo "Test için: php tools/test_all_communities_membership.php"
