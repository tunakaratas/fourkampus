<?php
/**
 * İzin Sorunlarını Zorla Düzelt
 * Dosya sahibini değiştirmeyi ve izinleri düzeltmeyi dener
 */

$communities_dir = __DIR__ . '/../communities';
$problematic = ['aaa', 'aabb'];

echo "🔧 İzin sorunlarını zorla düzeltiyorum...\n\n";

foreach ($problematic as $community_id) {
    $dir = $communities_dir . '/' . $community_id;
    $db_path = $dir . '/unipanel.sqlite';
    
    if (!is_dir($dir)) {
        echo "❌ Klasör bulunamadı: $dir\n";
        continue;
    }
    
    echo "📝 Düzeltiliyor: {$community_id}...\n";
    
    // Klasör izinlerini düzelt
    echo "   🔧 Klasör izinleri...\n";
    @chmod($dir, 0755);
    @chmod($dir, 0777); // Maksimum izin
    
    // Alt klasörleri de düzelt
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @chmod($item->getPathname(), 0755);
            @chmod($item->getPathname(), 0777);
        } else {
            @chmod($item->getPathname(), 0666);
            @chmod($item->getPathname(), 0777);
        }
    }
    
    // Veritabanı dosyası varsa izinlerini düzelt
    if (file_exists($db_path)) {
        echo "   🔧 Veritabanı izinleri...\n";
        @chmod($db_path, 0666);
        @chmod($db_path, 0777);
        
        // Dosya sahibini değiştirmeyi dene (eğer mümkünse)
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            // Root isek sahibi değiştir
            $current_user = posix_getpwuid(posix_geteuid());
            if ($current_user) {
                @chown($db_path, $current_user['name']);
                @chown($dir, $current_user['name']);
            }
        }
        
        // Dosyayı yeniden oluşturmayı dene
        if (!is_readable($db_path) || !is_writable($db_path)) {
            echo "   ⚠️  Dosya hala erişilemiyor, yeniden oluşturuluyor...\n";
            
            // Yedekle
            $backup_path = $db_path . '.backup.' . time();
            if (@copy($db_path, $backup_path)) {
                echo "   ✅ Yedek oluşturuldu\n";
            }
            
            // Sil ve yeniden oluştur
            @unlink($db_path);
            
            try {
                $db = new SQLite3($db_path);
                if (!$db) {
                    throw new Exception("SQLite3 bağlantısı kurulamadı");
                }
                
                $db->busyTimeout(5000);
                @$db->exec('PRAGMA journal_mode = WAL');
                @chmod($db_path, 0666);
                @chmod($db_path, 0777);
                
                // Temel tabloları oluştur
                @$db->exec("CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, club_id INTEGER, is_banned INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
                @$db->exec("CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT, email TEXT, student_id TEXT, phone_number TEXT, registration_date TEXT, is_banned INTEGER DEFAULT 0, ban_reason TEXT)");
                @$db->exec("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, description TEXT, date TEXT NOT NULL, time TEXT, location TEXT, image_path TEXT, video_path TEXT, category TEXT DEFAULT 'Genel', status TEXT DEFAULT 'planlanıyor', priority TEXT DEFAULT 'normal', capacity INTEGER, registration_required INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1)");
                @$db->exec("CREATE TABLE IF NOT EXISTS event_rsvp (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL, member_name TEXT NOT NULL, member_email TEXT NOT NULL, member_phone TEXT, rsvp_status TEXT DEFAULT 'attending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE)");
                @$db->exec("CREATE TABLE IF NOT EXISTS membership_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER NOT NULL, user_id INTEGER, full_name TEXT, email TEXT, phone TEXT, student_id TEXT, department TEXT, status TEXT DEFAULT 'pending', admin_notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, additional_data TEXT, UNIQUE(club_id, email))");
                @$db->exec("CREATE TABLE IF NOT EXISTS board_members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT NOT NULL, role TEXT NOT NULL, contact_email TEXT, is_active INTEGER DEFAULT 1)");
                @$db->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL)");
                @$db->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, community_name TEXT, action TEXT, details TEXT, timestamp TEXT DEFAULT CURRENT_TIMESTAMP)");
                @$db->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, message TEXT NOT NULL, type TEXT DEFAULT 'info', is_read INTEGER DEFAULT 0, is_urgent INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sender_type TEXT DEFAULT 'superadmin')");
                
                $db->close();
                
                echo "   ✅ Veritabanı yeniden oluşturuldu\n";
            } catch (Exception $e) {
                echo "   ❌ HATA: " . $e->getMessage() . "\n";
                // Yedekten geri yükle
                if (file_exists($backup_path)) {
                    @copy($backup_path, $db_path);
                    echo "   ⚠️  Yedekten geri yüklendi\n";
                }
            }
        }
    }
    
    // Son kontrol
    if (file_exists($db_path)) {
        $readable = is_readable($db_path);
        $writable = is_writable($db_path);
        $perms = substr(sprintf('%o', fileperms($db_path)), -4);
        
        echo "   📊 Durum: Okunabilir: " . ($readable ? 'EVET' : 'HAYIR') . ", Yazılabilir: " . ($writable ? 'EVET' : 'HAYIR') . ", İzinler: $perms\n";
        
        if ($readable && $writable) {
            echo "   ✅ Başarılı!\n";
        } else {
            echo "   ⚠️  Hala sorun var - Manuel müdahale gerekebilir\n";
        }
    }
    
    echo "\n";
}

echo "✅ İşlem tamamlandı!\n";
