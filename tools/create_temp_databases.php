<?php
/**
 * Geçici Veritabanı Dosyalarını Oluştur
 * Bu dosyalar daha sonra sudo ile kopyalanacak
 */

$temp_dir = '/tmp/unipanel_db_fix';
$communities = ['aaa', 'aabb'];

if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

echo "📝 Geçici veritabanı dosyalarını oluşturuyorum...\n\n";

foreach ($communities as $comm_id) {
    $db_path = $temp_dir . '/' . $comm_id . '.sqlite';
    
    echo "📝 Oluşturuluyor: $comm_id...\n";
    
    try {
        $db = new SQLite3($db_path);
        if (!$db) {
            throw new Exception("SQLite3 bağlantısı kurulamadı");
        }
        
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode = WAL');
        chmod($db_path, 0666);
        
        // Temel tabloları oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, club_id INTEGER, is_banned INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT, email TEXT, student_id TEXT, phone_number TEXT, registration_date TEXT, is_banned INTEGER DEFAULT 0, ban_reason TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, description TEXT, date TEXT NOT NULL, time TEXT, location TEXT, image_path TEXT, video_path TEXT, category TEXT DEFAULT 'Genel', status TEXT DEFAULT 'planlanıyor', priority TEXT DEFAULT 'normal', capacity INTEGER, registration_required INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1)");
        $db->exec("CREATE TABLE IF NOT EXISTS event_rsvp (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL, member_name TEXT NOT NULL, member_email TEXT NOT NULL, member_phone TEXT, rsvp_status TEXT DEFAULT 'attending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE)");
        $db->exec("CREATE TABLE IF NOT EXISTS membership_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER NOT NULL, user_id INTEGER, full_name TEXT, email TEXT, phone TEXT, student_id TEXT, department TEXT, status TEXT DEFAULT 'pending', admin_notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, additional_data TEXT, UNIQUE(club_id, email))");
        $db->exec("CREATE TABLE IF NOT EXISTS board_members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT NOT NULL, role TEXT NOT NULL, contact_email TEXT, is_active INTEGER DEFAULT 1)");
        $db->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, community_name TEXT, action TEXT, details TEXT, timestamp TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, message TEXT NOT NULL, type TEXT DEFAULT 'info', is_read INTEGER DEFAULT 0, is_urgent INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sender_type TEXT DEFAULT 'superadmin')");
        
        $db->close();
        
        echo "   ✅ Başarılı: $db_path\n";
    } catch (Exception $e) {
        echo "   ❌ HATA: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Tüm dosyalar oluşturuldu!\n";
echo "\nŞimdi şu komutu çalıştırın:\n";
echo "  sudo ./tools/fix_permissions_complete.sh\n";
