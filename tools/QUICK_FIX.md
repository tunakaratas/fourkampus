# 🚀 Hızlı İzin Düzeltme

## Tek Komutla Çözüm

```bash
sudo bash tools/FIX_PERMISSIONS_NOW.sh
```

Bu komut:
1. ✅ Geçici veritabanı dosyalarını oluşturur (eğer yoksa)
2. ✅ `aaa` ve `aabb` topluluklarının klasör sahibini değiştirir
3. ✅ Veritabanı dosyalarını kopyalar ve izinlerini düzeltir
4. ✅ Tüm WAL dosyalarını düzeltir

## Alternatif: Manuel Komutlar

```bash
# 1. Geçici dosyaları oluştur (zaten oluşturuldu)
php tools/create_temp_databases.php

# 2. Dosyaları kopyala ve izinleri düzelt
sudo cp /tmp/unipanel_db_fix/aaa.sqlite communities/aaa/unipanel.sqlite
sudo cp /tmp/unipanel_db_fix/aabb.sqlite communities/aabb/unipanel.sqlite
sudo chown -R tunakaratas communities/aaa communities/aabb
sudo chmod -R 755 communities/aaa communities/aabb
sudo chmod 666 communities/aaa/unipanel.sqlite communities/aabb/unipanel.sqlite
```

## Test

```bash
php tools/test_all_communities_membership.php
```

Tüm topluluklar için ✅ görmelisiniz!
