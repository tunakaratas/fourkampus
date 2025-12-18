#!/bin/bash
# İzin Sorunlarını Tamamen Düzelt - SUDO GEREKLİ
# Kullanım: sudo ./fix_permissions_complete.sh

echo "🔧 Topluluk izin sorunlarını tamamen düzeltiyorum..."
echo "⚠️  Bu script sudo yetkisi gerektirir!"
echo ""

cd "$(dirname "$0")/.."

# Web sunucusu kullanıcısını belirle
WEB_USER="tunakaratas"

# Sorunlu topluluklar
PROBLEMATIC=("aaa" "aabb")

# Geçici dizinde dosyalar oluşturuldu mu kontrol et
TEMP_DIR="/tmp/unipanel_db_fix"

if [ ! -d "$TEMP_DIR" ] || [ ! -f "$TEMP_DIR/aaa.sqlite" ] || [ ! -f "$TEMP_DIR/aabb.sqlite" ]; then
    echo "❌ Geçici dizinde dosyalar bulunamadı!"
    echo "   Önce şu komutu çalıştırın:"
    echo "   php tools/create_temp_databases.php"
    exit 1
fi

for comm_id in "${PROBLEMATIC[@]}"; do
    COMM_DIR="communities/$comm_id"
    DB_FILE="$COMM_DIR/unipanel.sqlite"
    TEMP_FILE="$TEMP_DIR/$comm_id.sqlite"
    
    if [ ! -d "$COMM_DIR" ]; then
        echo "❌ Klasör bulunamadı: $COMM_DIR"
        continue
    fi
    
    echo "📝 Düzeltiliyor: $comm_id..."
    
    # Klasör sahibini ve izinlerini değiştir
    echo "   🔧 Klasör sahibi ve izinleri..."
    chown -R "$WEB_USER" "$COMM_DIR"
    chmod -R 755 "$COMM_DIR"
    
    # Geçici dosyayı hedefe kopyala
    echo "   📋 Veritabanı dosyası kopyalanıyor..."
    cp "$TEMP_FILE" "$DB_FILE"
    
    # Veritabanı dosyası sahibini ve izinlerini değiştir
    chown "$WEB_USER" "$DB_FILE"
    chmod 666 "$DB_FILE"
    
    # WAL dosyalarını da düzelt (varsa)
    if [ -f "$DB_FILE-wal" ]; then
        chown "$WEB_USER" "$DB_FILE-wal"
        chmod 666 "$DB_FILE-wal"
    fi
    
    if [ -f "$DB_FILE-shm" ]; then
        chown "$WEB_USER" "$DB_FILE-shm"
        chmod 666 "$DB_FILE-shm"
    fi
    
    # Son kontrol
    if [ -f "$DB_FILE" ]; then
        if [ -r "$DB_FILE" ] && [ -w "$DB_FILE" ]; then
            OWNER=$(stat -f "%Su" "$DB_FILE" 2>/dev/null || echo "N/A")
            PERMS=$(stat -f "%OLp" "$DB_FILE" 2>/dev/null || echo "N/A")
            echo "   ✅ Başarılı - Sahip: $OWNER, İzinler: $PERMS"
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
