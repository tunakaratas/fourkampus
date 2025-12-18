<?php
/**
 * 30 Topluluk Oluşturma Script'i
 * Sunucuya 30 adet topluluk ekler
 */

// CLI veya web'den çalışabilir
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Yol tanımlamaları
define('BASE_PATH', dirname(__DIR__));
define('COMMUNITIES_DIR', BASE_PATH . '/communities/');
define('SUPERADMIN_DIR_PERMS', 0755);
define('SUPERADMIN_PUBLIC_DIR_PERMS', 0755);

// HTML başlığı (web için)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

// Gerekli dosyaları dahil et
require_once BASE_PATH . '/bootstrap/community_stubs.php';
require_once BASE_PATH . '/lib/general/input_validator.php';
require_once BASE_PATH . '/config/credentials.php';

// Namespace kullanımı
use function UniPanel\Community\sync_community_stubs;

// Helper fonksiyonlar
function cleanCommunityName($name) {
    $name = trim($name);
    $name = preg_replace('/\s+topluluğu\s*$/i', '', $name);
    $name = preg_replace('/\s+topluluk\s*$/i', '', $name);
    return trim($name);
}

function formatFolderName($name) {
    $name = cleanCommunityName($name);
    $turkish_chars = ['Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', 'ç', 'ğ', 'ı', 'ö', 'ş', 'ü'];
    $english_chars = ['C', 'G', 'I', 'O', 'S', 'U', 'c', 'g', 'i', 'o', 's', 'u'];
    $name = str_replace($turkish_chars, $english_chars, $name);
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_');
    return $name;
}

function generateCommunityCode($source_name) {
    $source_name = cleanCommunityName($source_name);
    $turkish_chars = ['Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', 'ç', 'ğ', 'ı', 'ö', 'ş', 'ü'];
    $english_chars = ['C', 'G', 'I', 'O', 'S', 'U', 'c', 'g', 'i', 'o', 's', 'u'];
    $name = str_replace($turkish_chars, $english_chars, $source_name);
    $name = preg_replace('/[^A-Za-z]/', '', $name);
    $name = strtoupper($name);
    $code = substr($name, 0, 3);
    if (strlen($code) < 3) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        while (strlen($code) < 3) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
    }
    $code .= rand(0, 9);
    return $code;
}

// Topluluk isimleri
$communityNames = [
    'Yazılım Geliştirme',
    'Mobil Uygulama',
    'Web Tasarım',
    'Veri Bilimi',
    'Yapay Zeka',
    'Siber Güvenlik',
    'Blockchain',
    'Oyun Geliştirme',
    'Robotik',
    'Elektronik',
    'Makine Mühendisliği',
    'Endüstri Mühendisliği',
    'İnşaat Mühendisliği',
    'Mimarlık',
    'İç Mimarlık',
    'Grafik Tasarım',
    'Fotoğrafçılık',
    'Sinema',
    'Tiyatro',
    'Müzik',
    'Dans',
    'Spor',
    'Futbol',
    'Basketbol',
    'Voleybol',
    'Satranç',
    'Kitap',
    'Dil',
    'Girişimcilik',
    'Sosyal Sorumluluk'
];

$universities = [
    'İstanbul Üniversitesi',
    'Boğaziçi Üniversitesi',
    'Orta Doğu Teknik Üniversitesi',
    'Sabancı Üniversitesi',
    'Koç Üniversitesi',
    'Bilkent Üniversitesi',
    'Hacettepe Üniversitesi',
    'Ankara Üniversitesi',
    'İstanbul Teknik Üniversitesi',
    'Yıldız Teknik Üniversitesi'
];

// İstatistikler
$createdCommunities = 0;
$errors = [];

// COMMUNITIES_DIR oluştur
if (!is_dir(COMMUNITIES_DIR)) {
    @mkdir(COMMUNITIES_DIR, SUPERADMIN_DIR_PERMS, true);
    @chmod(COMMUNITIES_DIR, SUPERADMIN_DIR_PERMS);
}

echo "🚀 30 Topluluk Oluşturma Başlıyor...\n\n";

// SMTP ayarlarını al
$smtpSettings = $credentials['smtp'] ?? [];

// 30 topluluk oluştur
for ($i = 0; $i < 30; $i++) {
    $communityName = $communityNames[$i] . ' Topluluğu';
    $folderName = formatFolderName($communityNames[$i]) . '_' . ($i + 1);
    $adminUsername = 'admin' . ($i + 1);
    $adminPassword = 'Admin123!';
    $adminPasswordHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $adminEmail = 'admin' . ($i + 1) . '@example.com';
    $university = $universities[array_rand($universities)];
    $communityCode = generateCommunityCode($communityNames[$i]);
    
    $fullPath = COMMUNITIES_DIR . $folderName;
    
    // Klasör zaten varsa atla
    if (is_dir($fullPath)) {
        echo "⏭️  Atlandı: $communityName (zaten mevcut)\n";
        continue;
    }
    
    try {
        // Klasör oluştur
        if (!@mkdir($fullPath, SUPERADMIN_DIR_PERMS, true)) {
            throw new Exception("Klasör oluşturulamadı: $fullPath");
        }
        @chmod($fullPath, SUPERADMIN_DIR_PERMS);
        
        echo "🔄 Oluşturuluyor: $communityName ($folderName)\n";
        
        // Template dosyalarını kopyala
        $stubResult = sync_community_stubs($fullPath);
        
        if (!$stubResult['success']) {
            throw new Exception("Template dosyaları kopyalanamadı: " . implode(', ', $stubResult['errors'] ?? []));
        }
        
        // Input validator ve session security kopyala
        $inputValidator = BASE_PATH . '/lib/general/input_validator.php';
        $sessionSecurity = BASE_PATH . '/lib/general/session_security.php';
        
        if (file_exists($inputValidator)) {
            copy($inputValidator, $fullPath . '/input_validator.php');
        }
        if (file_exists($sessionSecurity)) {
            copy($sessionSecurity, $fullPath . '/session_security.php');
        }
        
        // Public ve assets dizinlerini oluştur
        @mkdir($fullPath . '/public', SUPERADMIN_PUBLIC_DIR_PERMS, true);
        @mkdir($fullPath . '/assets/images', SUPERADMIN_PUBLIC_DIR_PERMS, true);
        @mkdir($fullPath . '/assets/images/partner-logos', SUPERADMIN_PUBLIC_DIR_PERMS, true);
        @mkdir($fullPath . '/assets/images/events', SUPERADMIN_PUBLIC_DIR_PERMS, true);
        @mkdir($fullPath . '/assets/videos/events', SUPERADMIN_PUBLIC_DIR_PERMS, true);
        
        // Logo kopyala (varsa)
        $logoSource = BASE_PATH . '/assets/images/brand/logo_tr.png';
        if (file_exists($logoSource)) {
            @mkdir($fullPath . '/assets/images', SUPERADMIN_PUBLIC_DIR_PERMS, true);
            copy($logoSource, $fullPath . '/assets/images/brand/logo_tr.png');
        }
        
        // Veritabanı oluştur
        $dbPath = $fullPath . '/unipanel.sqlite';
        $communityDb = new SQLite3($dbPath);
        $communityDb->exec('PRAGMA journal_mode = WAL');
        @chmod($dbPath, 0666);
        
        // Tabloları oluştur
        $communityDb->exec("CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, club_id INTEGER, is_banned INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT, email TEXT, student_id TEXT, phone_number TEXT, registration_date TEXT, is_banned INTEGER DEFAULT 0, ban_reason TEXT)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, description TEXT, date TEXT NOT NULL, time TEXT, location TEXT, image_path TEXT, video_path TEXT, category TEXT DEFAULT 'Genel', status TEXT DEFAULT 'planlanıyor', priority TEXT DEFAULT 'normal', capacity INTEGER, registration_required INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, organizer TEXT, contact_email TEXT, contact_phone TEXT, tags TEXT, registration_deadline TEXT)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS event_images (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER, club_id INTEGER, image_path TEXT, uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS event_videos (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER, club_id INTEGER, video_path TEXT, uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS event_rsvp (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, club_id INTEGER NOT NULL, member_name TEXT NOT NULL, member_email TEXT NOT NULL, member_phone TEXT, rsvp_status TEXT DEFAULT 'attending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT, offer_text TEXT NOT NULL, partner_name TEXT, discount_percentage INTEGER, image_path TEXT, start_date TEXT, end_date TEXT, campaign_code TEXT, is_active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, name TEXT NOT NULL, description TEXT, price REAL DEFAULT 0, stock INTEGER DEFAULT 0, category TEXT DEFAULT 'Genel', image_path TEXT, status TEXT DEFAULT 'active', commission_rate REAL DEFAULT 8.0, iyzico_commission REAL DEFAULT 0, platform_commission REAL DEFAULT 0, total_price REAL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS email_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER NOT NULL, subject TEXT NOT NULL, message TEXT NOT NULL, from_name TEXT, from_email TEXT, total_recipients INTEGER DEFAULT 0, sent_count INTEGER DEFAULT 0, failed_count INTEGER DEFAULT 0, status TEXT DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, started_at DATETIME, completed_at DATETIME)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS email_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER NOT NULL, club_id INTEGER NOT NULL, recipient_email TEXT NOT NULL, recipient_name TEXT, subject TEXT NOT NULL, message TEXT NOT NULL, from_name TEXT, from_email TEXT, status TEXT DEFAULT 'pending', attempts INTEGER DEFAULT 0, error_message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME, FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS board_members (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, full_name TEXT NOT NULL, role TEXT NOT NULL, contact_email TEXT, is_active INTEGER DEFAULT 1)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS admin_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, community_name TEXT, action TEXT, details TEXT, timestamp TEXT DEFAULT CURRENT_TIMESTAMP)");
        $communityDb->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, title TEXT NOT NULL, message TEXT NOT NULL, type TEXT DEFAULT 'info', is_read INTEGER DEFAULT 0, is_urgent INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sender_type TEXT DEFAULT 'superadmin')");
        
        // Admin kullanıcısı oluştur
        $stmt = $communityDb->prepare("INSERT INTO admins (username, password_hash, club_id) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $adminUsername, SQLITE3_TEXT);
        $stmt->bindValue(2, $adminPasswordHash, SQLITE3_TEXT);
        $stmt->bindValue(3, 1, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Topluluk ayarları
        $settings = [
            ['club_name', $communityName],
            ['status', 'active'],
            ['trial_start_date', date('Y-m-d')],
            ['university', $university],
            ['community_code', $communityCode],
            ['admin_email', $adminEmail]
        ];
        
        // SMTP ayarlarını ekle
        if (!empty($smtpSettings)) {
            $settings[] = ['smtp_host', $smtpSettings['host'] ?? ''];
            $settings[] = ['smtp_port', $smtpSettings['port'] ?? '587'];
            $settings[] = ['smtp_username', $smtpSettings['username'] ?? ''];
            $settings[] = ['smtp_password', $smtpSettings['password'] ?? ''];
            $settings[] = ['smtp_from_email', $smtpSettings['from_email'] ?? ''];
            $settings[] = ['smtp_from_name', $smtpSettings['from_name'] ?? ''];
            $settings[] = ['smtp_secure', $smtpSettings['encryption'] ?? 'tls'];
        }
        
        foreach ($settings as $setting) {
            $stmt = $communityDb->prepare("INSERT INTO settings (club_id, setting_key, setting_value) VALUES (?, ?, ?)");
            $stmt->bindValue(1, 1, SQLITE3_INTEGER);
            $stmt->bindValue(2, $setting[0], SQLITE3_TEXT);
            $stmt->bindValue(3, $setting[1], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // Hoş geldiniz bildirimi
        try {
            $stmt = $communityDb->prepare("INSERT INTO notifications (club_id, title, message, type, sender_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, 1, SQLITE3_INTEGER);
            $stmt->bindValue(2, 'Hoş Geldiniz!', SQLITE3_TEXT);
            $stmt->bindValue(3, $communityName . ' topluluğuna hoş geldiniz! Sistemi kullanmaya başlamak için sol menüden panoyu kontrol edebilirsiniz.', SQLITE3_TEXT);
            $stmt->bindValue(4, 'success', SQLITE3_TEXT);
            $stmt->bindValue(5, 'system', SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
            // Hata olsa da devam et
        }
        
        $communityDb->close();
        
        echo "  ✓ $communityName oluşturuldu (Kullanıcı: $adminUsername, Şifre: $adminPassword)\n";
        $createdCommunities++;
        
    } catch (Exception $e) {
        $errors[] = "$communityName: " . $e->getMessage();
        echo "  ❌ Hata: " . $e->getMessage() . "\n";
        // Hata durumunda klasörü temizle
        if (is_dir($fullPath)) {
            @rmdir($fullPath);
        }
    }
}

// Özet
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 ÖZET\n";
echo str_repeat("=", 60) . "\n";
echo "Oluşturulan Topluluk: $createdCommunities / 30\n";

if (!empty($errors)) {
    echo "\n❌ Hatalar:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n✅ İşlem tamamlandı!\n";
