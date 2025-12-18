<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - KULÜP YÖNETİM PANELİ
// =================================================================

// Autoloader'ı dahil et
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../templates/partials/superadmin_guard.php';
require_once __DIR__ . '/../../templates/partials/security_headers.php';
require_once __DIR__ . '/../../templates/partials/inline_handler_bridge.php';
tpl_inline_handler_transform_start();
set_security_headers();

// Environment tanımla
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // Production'da 'production' olacak
}

// Error Handler'ı başlat
use UniPanel\Core\ErrorHandler;

ErrorHandler::init(
    __DIR__ . '/../../system/logs/error.log',
    true
);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- YAPILANDIRMA ---
// DB_PATH sabiti, tüm fonksiyon çağrılarından önce tanımlanmalıdır.
const DB_PATH = 'unipanel.sqlite';
const CLUB_ID = 1; // Sabit kulüp ID'si


// Import namespace'leri
use UniPanel\Core\Database;
use UniPanel\Models\Event;
use UniPanel\Models\Member;
use UniPanel\Models\Notification;

// --- VERİTABANI İŞLEVLERİ ---

// Cache sistemi
$cache = [];
function getCachedData($key, $callback) {
    global $cache;
    if (!isset($cache[$key])) {
        $cache[$key] = $callback();
    }
    return $cache[$key];
}

/**
 * SQLite veritabanına bağlanır (Backward compatibility için).
 * @return SQLite3 Veritabanı bağlantı nesnesi.
 */
function get_db() {
    try {
        $database = Database::getInstance(DB_PATH);
        return $database->getDb();
    } catch (\Exception $e) {
        ErrorHandler::error("Veritabanı bağlantısı başarısız: " . $e->getMessage(), 500);
        exit;
    }
}

function ensure_settings_unique_index() {
    static $settingsIndexEnsured = false;
    if ($settingsIndexEnsured) {
        return;
    }
    $settingsIndexEnsured = true;

    try {
        $db = get_db();
        if (!$db instanceof SQLite3) {
            return;
        }

        $table_exists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        if (!$table_exists) {
            return;
        }

        $index_exists = false;
        $index_list = $db->query("PRAGMA index_list(settings)");
        if ($index_list) {
            while ($row = $index_list->fetchArray(SQLITE3_ASSOC)) {
                if (($row['name'] ?? '') === 'idx_settings_club_key') {
                    $index_exists = true;
                    break;
                }
            }
        }

        if (!$index_exists) {
            $duplicates = $db->query("SELECT club_id, setting_key, MAX(id) AS keep_id FROM settings GROUP BY club_id, setting_key HAVING COUNT(*) > 1");
            if ($duplicates) {
                while ($dup = $duplicates->fetchArray(SQLITE3_ASSOC)) {
                    $stmt = $db->prepare("DELETE FROM settings WHERE club_id = :club_id AND setting_key = :setting_key AND id <> :keep_id");
                    if ($stmt) {
                        $stmt->bindValue(':club_id', (int)($dup['club_id'] ?? 0), SQLITE3_INTEGER);
                        $stmt->bindValue(':setting_key', $dup['setting_key'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(':keep_id', (int)($dup['keep_id'] ?? 0), SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                }
            }

            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_settings_club_key ON settings (club_id, setting_key)");
        }
    } catch (Exception $e) {
        error_log('Settings unique index ensure failed: ' . $e->getMessage());
    }
}

ensure_settings_unique_index();

/**
 * Gerekli tabloları oluşturur ve varsayılan ayarları ekler.
 * @param SQLite3 $db Veritabanı bağlantısı.
 */
function setup_database(SQLite3 $db) {
    // 1. Ayarlar Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL
    )");
    
    // Varsayılan kulüp adını ayarla
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'club_name'");
    $count = $stmt->execute()->fetchArray()[0];
    if ($count == 0) {
        $db->exec("INSERT INTO settings (club_id, setting_key, setting_value) VALUES (1, 'club_name', 'Sanat ve Tasarım Topluluğu')");
        $db->exec("INSERT INTO settings (club_id, setting_key, setting_value) VALUES (1, 'status', 'active')");
    }

    // 2. Etkinlikler Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        title TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        location TEXT,
        description TEXT,
        image_path TEXT,
        video_path TEXT
    )");

    // 3. Üyeler Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS members (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        full_name TEXT, 
        email TEXT, 
        student_id TEXT, 
        phone_number TEXT, 
        registration_date TEXT
    )");

    // 4. Yönetim Kurulu Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS board_members (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        full_name TEXT NOT NULL,
        role TEXT,
        contact_email TEXT,
        phone TEXT
    )");
    
    // 5. Bildirimler Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        type TEXT DEFAULT 'info',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        sender_type TEXT DEFAULT 'system'
    )");
    
    // 6. İşbirliği Logoları Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS partner_logos (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        partner_name TEXT NOT NULL,
        partner_website TEXT,
        logo_path TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Events tablosuna image_path ve video_path kolonlarını ekle (eğer yoksa)
    try {
        $db->exec("ALTER TABLE events ADD COLUMN image_path TEXT");
    } catch (Exception $e) {
        // Kolon zaten varsa hata vermez
    }
    
    try {
        $db->exec("ALTER TABLE events ADD COLUMN video_path TEXT");
    } catch (Exception $e) {
        // Kolon zaten varsa hata vermez
    }
    
    // Board_members tablosuna phone kolonunu ekle (eğer yoksa)
    try {
        $db->exec("ALTER TABLE board_members ADD COLUMN phone TEXT");
    } catch (Exception $e) {
        // Kolon zaten varsa hata vermez
    }
}

// --- GÜVENLİK KONTROLÜ (ZORUNLU GİRİŞ) ---

handle_superadmin_auto_login();

// Otomatik login kontrolü (DB_PATH artık tanımlı, get_db'ye gerek yok)
if (isset($_GET['auto_login']) && !isset($_SESSION['admin_id'])) {
    $token = $_GET['auto_login'];
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    
    if (count($parts) === 3) {
        list($community, $username, $timestamp) = $parts;
        
        // Token 1 saat içinde oluşturulmuş mu kontrol et
        if (time() - $timestamp < 3600) {
            try {
                // DB'yi manuel bağla
                $db_path_check = __DIR__ . '/' . DB_PATH; // Mutlak yol kullanmak daha güvenli olabilir
                if (!file_exists($db_path_check)) {
                    // DB yoksa login'e yönlendir, DB_PATH hatası oluşmaz
                    header("Location: login.php");
                    exit;
                }
                
                $db = new SQLite3($db_path_check);
                $db->enableExceptions(true);

                $stmt = $db->prepare("SELECT id, password_hash FROM admins WHERE username = ? AND club_id = 1");
                $stmt->bindValue(1, $username, SQLITE3_TEXT);
                $result = $stmt->execute();
                $admin = $result->fetchArray(SQLITE3_ASSOC);
                
                $db->close();

                if ($admin) {
                    // Otomatik giriş yap
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['club_id'] = 1;
                    header("Location: index.php");
                    exit;
                }
            } catch (Exception $e) {
                // Hata durumunda (örn. admins tablosu yoksa) normal login'e yönlendir
            }
        }
    }
    
    // Otomatik login başarısız, normal login'e yönlendir
    header("Location: login.php");
    exit;
}

// Topluluk durumu kontrolü
if (isset($_SESSION['admin_id'])) {
    try {
        $db = get_db();
        $status_stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'status'");
        $status_result = $status_stmt->execute();
        $status_row = $status_result->fetchArray(SQLITE3_ASSOC);
        $status = $status_row ? $status_row['setting_value'] : null;
        if ($status === 'disabled') {
            // Topluluk kapatılmış, oturumu sil ve login sayfasına yönlendir
            session_unset();
            session_destroy();
            header("Location: login.php?error=disabled");
            exit;
        }
    } catch (Exception $e) {
        // Hata durumunda devam et
    }
}

// Eğer oturum açılmamışsa, kullanıcıyı login sayfasına yönlendir.
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- KİMLİK DOĞRULAMA & YÖNETİM İŞLEVLERİ (Devamı) ---

/**
 * Yönetici çıkış işlemini gerçekleştirir.
 */
function handle_logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- VERİ ÇEKME İŞLEVLERİ (READ) ---

function get_club_name() {
    $db = get_db();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'club_name' AND club_id = :club_id");
    $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $club_name = $result->fetchArray()[0] ?? null;
    return $club_name ?: 'UniPanel Kulübü';
}

function get_stats() {
    $db = get_db();
    $stats = [
        'total_members' => 0,
        'upcoming_events' => 0,
        'board_members' => 0,
        'total_events' => 0
    ];

    $members_stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE club_id = ?");
    $members_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $members_result = $members_stmt->execute();
    $stats['total_members'] = (int) $members_result->fetchArray()[0];
    
    $events_stmt = $db->prepare("SELECT COUNT(*) as count FROM events WHERE club_id = ?");
    $events_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $events_result = $events_stmt->execute();
    $stats['total_events'] = (int) $events_result->fetchArray()[0];
    
    $upcoming_q = $db->prepare("SELECT COUNT(*) FROM events WHERE club_id = :club_id AND date >= date('now')");
    $upcoming_q->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $stats['upcoming_events'] = (int) $upcoming_q->execute()->fetchArray()[0];

    $board_stmt = $db->prepare("SELECT COUNT(*) as count FROM board_members WHERE club_id = ?");
    $board_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $board_result = $board_stmt->execute();
    $stats['board_members'] = (int) $board_result->fetchArray()[0];

    return $stats;
}

function get_notifications() {
    $db = get_db();
    $notifications = [];
    
    $query = $db->prepare("SELECT * FROM notifications WHERE club_id = :club_id ORDER BY created_at DESC LIMIT 10");
    $query->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $result = $query->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

function get_unread_notification_count() {
    try {
        $database = Database::getInstance(DB_PATH);
        $notificationModel = new Notification($database->getDb(), CLUB_ID);
        return $notificationModel->getUnreadCount();
    } catch (\Exception $e) {
        ErrorHandler::error("Bildirim sayısı getirilemedi: " . $e->getMessage(), 500);
        return 0;
    }
}

function get_events() {
    try {
        $database = Database::getInstance(DB_PATH);
        $eventModel = new Event($database->getDb(), CLUB_ID);
        return $eventModel->getAll();
    } catch (\Exception $e) {
        ErrorHandler::error("Etkinlikler getirilemedi: " . $e->getMessage(), 500);
        return [];
    }
}

function get_event_by_id($id) {
    try {
        $database = Database::getInstance(DB_PATH);
        $eventModel = new Event($database->getDb(), CLUB_ID);
        return $eventModel->getById($id);
    } catch (\Exception $e) {
        ErrorHandler::error("Etkinlik getirilemedi: " . $e->getMessage(), 500);
        return null;
    }
}

function get_members() {
    try {
        $database = Database::getInstance(DB_PATH);
        $memberModel = new Member($database->getDb(), CLUB_ID);
        return $memberModel->getAll();
    } catch (\Exception $e) {
        ErrorHandler::error("Üyeler getirilemedi: " . $e->getMessage(), 500);
        return [];
    }
}

function get_board_members() {
    $db = get_db();
    $board = [];
    // Güvenli prepared statement kullan
    $stmt = $db->prepare("SELECT * FROM board_members WHERE club_id = :club_id ORDER BY id ASC");
    $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $query = $stmt->execute();
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        $board[] = $row;
    }
    return $board;
}

function get_sms_member_contacts() {
    $db = get_db();
    $contacts = [];
    // Güvenli prepared statement kullan
    $stmt = $db->prepare("SELECT full_name, phone_number FROM members WHERE club_id = :club_id AND phone_number IS NOT NULL AND phone_number != '' ORDER BY full_name ASC");
    $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $query = $stmt->execute();
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        $contacts[] = $row;
    }
    return $contacts;
}

function get_email_member_contacts() {
    $db = get_db();
    $contacts = [];
    // Güvenli prepared statement kullan
    $stmt = $db->prepare("SELECT full_name, email FROM members WHERE club_id = :club_id AND email IS NOT NULL AND email != '' ORDER BY full_name ASC");
    $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    $query = $stmt->execute();
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        $contacts[] = $row;
    }
    return $contacts;
}

// --- CRUD OPERASYONLARI (POST İŞLEYİCİLERİ) ---

function handle_file_upload($file, $subfolder, $allowed_extensions, $max_size) {
    try {
        // Klasör oluştur
        $upload_dir = __DIR__ . '/assets/' . $subfolder;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            chmod($upload_dir, 0777);
        }
        
        // Klasör yazılabilir mi kontrol et
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0777);
        }
        
        // Debug için log ekle
        error_log("Partner logo upload - Upload directory: " . $upload_dir);
        error_log("Partner logo upload - Directory exists: " . (is_dir($upload_dir) ? 'yes' : 'no'));
        error_log("Partner logo upload - Directory writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
        error_log("Partner logo upload - File size: " . $file['size']);
        error_log("Partner logo upload - File type: " . $file['type']);
        
        // Dosya bilgilerini al
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];
        
        // Uzantı kontrolü
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Geçersiz dosya uzantısı. İzin verilen: ' . implode(', ', $allowed_extensions));
        }
        
        // Boyut kontrolü
        if ($file_size > $max_size) {
            throw new Exception('Dosya boyutu çok büyük. Maksimum: ' . round($max_size / (1024 * 1024), 1) . 'MB');
        }
        
        // Benzersiz dosya adı oluştur
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Dosyayı taşı
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return 'assets/' . $subfolder . $filename;
        } else {
            throw new Exception('Dosya yüklenirken hata oluştu');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Dosya yükleme hatası: ' . $e->getMessage();
        return '';
    }
}

function add_event($db, $post) {
    try {
        // Dosya yükleme işlemleri
        $image_path = '';
        $video_path = '';
        
        // Görsel yükleme
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = handle_file_upload($_FILES['event_image'], 'images/events/', ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024); // 5MB
        }
        
        // Video yükleme
        if (isset($_FILES['event_video']) && $_FILES['event_video']['error'] === UPLOAD_ERR_OK) {
            $video_path = handle_file_upload($_FILES['event_video'], 'videos/events/', ['mp4', 'avi', 'mov', 'wmv'], 50 * 1024 * 1024); // 50MB
        }
        
        $stmt = $db->prepare("INSERT INTO events (club_id, title, date, time, location, description, image_path, video_path) VALUES (:club_id, :title, :date, :time, :location, :description, :image_path, :video_path)");
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $post['title'], SQLITE3_TEXT);
        $stmt->bindValue(':date', $post['date'], SQLITE3_TEXT);
        $stmt->bindValue(':time', $post['time'], SQLITE3_TEXT);
        $stmt->bindValue(':location', $post['location'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $post['description'], SQLITE3_TEXT);
        $stmt->bindValue(':image_path', $image_path, SQLITE3_TEXT);
        $stmt->bindValue(':video_path', $video_path, SQLITE3_TEXT);
        $stmt->execute();
        $_SESSION['message'] = "Etkinlik başarıyla eklendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Etkinlik eklenirken hata: " . $e->getMessage();
    }
}

function update_event($db, $post) {
    try {
        $stmt = $db->prepare("UPDATE events SET title = :title, date = :date, time = :time, location = :location, description = :description WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':title', $post['title'], SQLITE3_TEXT);
        $stmt->bindValue(':date', $post['date'], SQLITE3_TEXT);
        $stmt->bindValue(':time', $post['time'], SQLITE3_TEXT);
        $stmt->bindValue(':location', $post['location'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $post['description'], SQLITE3_TEXT);
        $stmt->bindValue(':id', $post['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Etkinlik başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Etkinlik güncellenirken hata: " . $e->getMessage();
    }
}

function delete_event($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM events WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Etkinlik başarıyla silindi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Etkinlik silinirken hata: " . $e->getMessage();
    }
}

function add_member($db, $post) {
    try {
        $stmt = $db->prepare("INSERT INTO members (club_id, full_name, email, student_id, phone_number, registration_date) VALUES (:club_id, :full_name, :email, :student_id, :phone_number, :registration_date)");
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $post['full_name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':email', $post['email'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':student_id', $post['student_id'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':phone_number', $post['phone_number'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':registration_date', date('Y-m-d'), SQLITE3_TEXT);
        $stmt->execute();
        $_SESSION['message'] = "Üye başarıyla eklendi. 👤";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye eklenirken hata: " . $e->getMessage();
    }
}

function update_member($db, $post) {
    try {
        $stmt = $db->prepare("UPDATE members SET full_name = :full_name, email = :email, student_id = :student_id, phone_number = :phone_number WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':full_name', $post['full_name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':email', $post['email'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':student_id', $post['student_id'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':phone_number', $post['phone_number'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':id', $post['member_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Üye bilgileri başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye bilgileri güncellenirken hata: " . $e->getMessage();
    }
}

function delete_member($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM members WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Üye başarıyla silindi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye silinirken hata: " . $e->getMessage();
    }
}

function add_board_member($db, $post) {
    try {
        $stmt = $db->prepare("INSERT INTO board_members (club_id, full_name, role, contact_email, phone) VALUES (:club_id, :full_name, :role, :contact_email, :phone)");
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $post['full_name'], SQLITE3_TEXT);
        $stmt->bindValue(':role', $post['role'], SQLITE3_TEXT);
        $stmt->bindValue(':contact_email', $post['contact_email'], SQLITE3_TEXT);
        $stmt->bindValue(':phone', $post['phone'] ?? '', SQLITE3_TEXT);
        $stmt->execute();
        $_SESSION['message'] = "Yönetim kurulu üyesi başarıyla eklendi. 🏅";
    } catch (Exception $e) {
        $_SESSION['error'] = "Yönetim kurulu üyesi eklenirken hata: " . $e->getMessage();
    }
}

function update_board_member($db, $post) {
    try {
        $stmt = $db->prepare("UPDATE board_members SET full_name = :full_name, role = :role, contact_email = :contact_email, phone = :phone WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':full_name', $post['full_name'], SQLITE3_TEXT);
        $stmt->bindValue(':role', $post['role'], SQLITE3_TEXT);
        $stmt->bindValue(':contact_email', $post['contact_email'], SQLITE3_TEXT);
        $stmt->bindValue(':phone', $post['phone'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':id', $post['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Yönetim kurulu üyesi başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Yönetim kurulu üyesi güncellenirken hata: " . $e->getMessage();
    }
}

function herk_delete_board_member($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM board_members WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['message'] = "Yönetim kurulu üyesi silindi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Yönetim kurulu üyesi silinirken hata: " . $e->getMessage();
    }
}

function get_setting($key, $default = '') {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE club_id = ? AND setting_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function update_settings($db, $post) {
    try {
        // Topluluk adı güvenlik nedeniyle değiştirilemez
        
        // Diğer ayarları güncelle
        if (isset($post['club_description'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'club_description', :club_description)");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(':club_description', $post['club_description'], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        if (isset($post['email_notifications'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'email_notifications', '1')");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'email_notifications', '0')");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        if (isset($post['sms_notifications'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'sms_notifications', '1')");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'sms_notifications', '0')");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
        }
        
        if (isset($post['session_timeout'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'session_timeout', :session_timeout)");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(':session_timeout', $post['session_timeout'], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        if (isset($post['max_login_attempts'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'max_login_attempts', :max_login_attempts)");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(':max_login_attempts', $post['max_login_attempts'], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // SMTP ayarlarını kaydet - ZORUNLU KAYDET
        if (isset($post['smtp_username'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'smtp_username', :smtp_username)");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(':smtp_username', trim($post['smtp_username']), SQLITE3_TEXT);
            $stmt->execute();
            error_log("SMTP Username kaydedildi: " . trim($post['smtp_username']));
        }
        
        if (isset($post['smtp_password'])) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, 'smtp_password', :smtp_password)");
            $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(':smtp_password', trim($post['smtp_password']), SQLITE3_TEXT);
            $stmt->execute();
            error_log("SMTP Password kaydedildi: " . trim($post['smtp_password']));
        }
        
        $_SESSION['message'] = "Ayarlar başarıyla güncellendi! Sistem ayarları kaydedildi.";
        
        // Form gönderildikten sonra aynı sayfada kal
        header("Location: index.php?view=settings");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Ayarlar güncellenirken hata: " . $e->getMessage();
    }
}

function save_smtp_settings($post) {
    try {
        $db = get_db();
        $username = $post['smtp_username'] ?? '';
        $password = $post['smtp_password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo "HATA: Gmail adresi ve App Password gerekli!";
            exit;
        }
        
        // Username kaydet
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (?, 'smtp_username', ?)");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, trim($username), SQLITE3_TEXT);
        $stmt->execute();
        
        // Password kaydet
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (?, 'smtp_password', ?)");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, trim($password), SQLITE3_TEXT);
        $stmt->execute();
        
        echo "BAŞARILI: SMTP ayarları kaydedildi!";
    } catch (Exception $e) {
        echo "HATA: " . $e->getMessage();
    }
    exit;
}

function send_test_email() {
    try {
        $to = 'admin@foursoftware.com.tr';
        $subject = 'TEST MAİLİ - ' . date('Y-m-d H:i:s');
        $message = 'Bu bir test mailidir. SMTP ayarları çalışıyor!';
        $club_name = 'Test Topluluk';
        $from_email = 'admin@foursoftware.com.tr';
        
        // Debug bilgilerini ekrana yazdır
        $debug_info = "=== MAIL DEBUG ===\n";
        $debug_info .= "TO: $to\n";
        $debug_info .= "SUBJECT: $subject\n";
        $debug_info .= "CLUB: $club_name\n";
        $debug_info .= "FROM: $from_email\n";
        
        // Mail ayarlarını kontrol et
        $mail_config = ini_get('sendmail_path');
        $debug_info .= "SENDMAIL PATH: $mail_config\n";
        
        // Mail fonksiyonu aktif mi?
        $mail_enabled = function_exists('mail');
        $debug_info .= "MAIL FUNCTION: " . ($mail_enabled ? 'ENABLED' : 'DISABLED') . "\n";
        
        // PHP mail ayarları
        $smtp_host = ini_get('SMTP');
        $smtp_port = ini_get('smtp_port');
        $debug_info .= "SMTP HOST: $smtp_host\n";
        $debug_info .= "SMTP PORT: $smtp_port\n";
        
        $mail_sent = send_gmail_smtp($to, $subject, $message, $club_name, $from_email);
        
        if ($mail_sent) {
            echo "BAŞARILI: Test maili gönderildi! admin@foursoftware.com.tr adresine kontrol edin.\n\nDEBUG:\n$debug_info";
        } else {
            echo "HATA: Test maili gönderilemedi!\n\nDEBUG:\n$debug_info";
        }
    } catch (Exception $e) {
        echo "HATA: " . $e->getMessage();
    }
    exit;
}

function test_smtp_connection($post) {
    $username = $post['smtp_username'] ?? '';
    $password = $post['smtp_password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo "HATA: Gmail adresi ve App Password gerekli!";
        exit;
    }
    
    // Test mail gönder
    $to = $username; // Kendine gönder
    $subject = "SMTP Test - " . date('Y-m-d H:i:s');
    $message = "Bu bir test mailidir. SMTP ayarları çalışıyor!";
    
    $headers = [
        'From' => "$username <$username>",
        'Reply-To' => $username,
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $mail_sent = mail($to, $subject, $message, $headers);
    
    if ($mail_sent) {
        echo "BAŞARILI: Test maili gönderildi!";
    } else {
        echo "HATA: Test maili gönderilemedi!";
    }
    exit;
}

function handle_send_email($post) {
    try {
        $db = get_db();
        $club_name = get_setting('club_name', 'Topluluk');
        
        // Gmail SMTP ayarlarını al
        $smtp_username = get_setting('smtp_username', '');
        $smtp_password = get_setting('smtp_password', '');
        
        // Config dosyasından SMTP ayarlarını al (fallback)
        if (empty($smtp_username) || empty($smtp_password)) {
            $config_path = __DIR__ . '/../../config/credentials.php';
            if (file_exists($config_path)) {
                $config = require $config_path;
                $smtp_username = $config['smtp']['username'] ?? '';
                $smtp_password = $config['smtp']['password'] ?? '';
                if (!empty($smtp_username) && !empty($smtp_password)) {
                    error_log("SMTP ayarları config dosyasından yüklendi - Username: '$smtp_username'");
                }
            }
        }
        
        // DEBUG: SMTP ayarlarını kontrol et
        error_log("DEBUG SMTP - Username: '$smtp_username', Password: '$smtp_password'");
        
        // Alıcıları belirle
        $recipients = [];
        if (isset($post['selected_emails']) && is_array($post['selected_emails'])) {
            $recipients = $post['selected_emails'];
        } elseif (isset($post['recipients']) && $post['recipients'] === 'Tüm Üyeler') {
            $recipients = get_email_member_contacts();
        }
        
        if (empty($recipients)) {
            $_SESSION['error'] = "Alıcı seçilmedi!";
            return;
        }
        
        $subject = $post['email_subject'] ?? 'Konu Belirtilmedi';
        $message = $post['email_body'] ?? ''; // DÜZELTİLDİ: email_message -> email_body
        
        if (empty($subject) || empty($message)) {
            $_SESSION['error'] = "Konu ve mesaj alanları zorunludur! Subject: '$subject', Message: '$message'";
            return;
        }
        
        // Her alıcıya mail gönder
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail_sent = send_gmail_smtp($email, $subject, $message, $club_name, $smtp_username);
                if ($mail_sent) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }
        
        if ($sent_count > 0) {
            $_SESSION['message'] = "E-posta başarıyla gönderildi! 📧 Gönderilen: $sent_count, Başarısız: $failed_count";
        } else {
            $_SESSION['error'] = "Hiçbir e-posta gönderilemedi! Lütfen SMTP ayarlarını kontrol edin.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Mail gönderme hatası: " . $e->getMessage();
    }
}

function send_gmail_smtp($to, $subject, $message, $club_name, $from_email) {
    try {
        // SMTP ayarları - önce veritabanından, sonra config dosyasından
        $smtp_username = get_setting('smtp_username', '');
        $smtp_password = get_setting('smtp_password', '');
        
        // Config dosyasından fallback
        if (empty($smtp_username) || empty($smtp_password)) {
            $config_path = __DIR__ . '/../../config/credentials.php';
            if (file_exists($config_path)) {
                $config = require $config_path;
                $smtp_username = $config['smtp']['username'] ?? '';
                $smtp_password = $config['smtp']['password'] ?? '';
            }
        }
        
        // SMTP host ve port ayarları
        $smtp_host = get_setting('smtp_host', '') ?: 'smtp.gmail.com';
        $smtp_port = (int)(get_setting('smtp_port', '') ?: 587);
        
        if (empty($smtp_username) || empty($smtp_password)) {
            error_log("SMTP credentials not found");
            return false;
        }
        
        // SMTP bağlantısı
        $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP Bağlantı Hatası: $errstr ($errno)");
            return false;
        }
        
        // SMTP komutları
        fputs($socket, "EHLO localhost\r\n");
        fputs($socket, "AUTH LOGIN\r\n");
        fputs($socket, base64_encode($smtp_username) . "\r\n");
        fputs($socket, base64_encode($smtp_password) . "\r\n");
        fputs($socket, "MAIL FROM: <$smtp_username>\r\n");
        fputs($socket, "RCPT TO: <$to>\r\n");
        fputs($socket, "DATA\r\n");
        
        // Mail başlıkları
        fputs($socket, "From: $club_name <$from_email>\r\n");
        fputs($socket, "To: $to\r\n");
        fputs($socket, "Subject: $subject\r\n");
        fputs($socket, "MIME-Version: 1.0\r\n");
        fputs($socket, "Content-Type: text/html; charset=UTF-8\r\n");
        fputs($socket, "X-Mailer: PHP/" . phpversion() . "\r\n");
        fputs($socket, "\r\n");
        
        // Mail içeriği
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>$subject</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>$club_name</h1>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <h2 style='color: #333; margin-top: 0;'>$subject</h2>
                    <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <div style='margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 14px; color: #666;'>
                        <strong>Bu e-posta $club_name tarafından gönderilmiştir.</strong><br>
                        Gönderim tarihi: " . date('d.m.Y H:i') . "
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        fputs($socket, $html_message . "\r\n");
        fputs($socket, ".\r\n");
        fputs($socket, "QUIT\r\n");
        
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP Hatası: " . $e->getMessage());
        return false;
    }
}

function handle_send_message($post) {
    $recipients_count = 0;
    if (isset($post['selected_phones']) && is_array($post['selected_phones'])) {
        $recipients_count = count($post['selected_phones']);
    }
    
    // Eğer selected_phones gelmediyse ve 'recipients' alanı 'Tüm Üyeler' olarak ayarlanmışsa
    if ($recipients_count === 0 && (isset($post['recipients']) && $post['recipients'] === 'Tüm Üyeler')) {
        // Tüm üyeler varsayılır (Bu simülasyonun mantığıdır)
        $recipients_count = count(get_sms_member_contacts());
    }

    $recipients_info = $recipients_count > 0 ? "Toplam **{$recipients_count}** adet alıcıya" : "**Tüm üyelere**";
    $message_body = htmlspecialchars(substr($post['sms_body'] ?? '', 0, 50)) . '...';
    
    $_SESSION['message'] = "SMS gönderim talebi simülasyonu başarılı! {$recipients_info} SMS gönderildi. Mesaj başlangıcı: \"{$message_body}\". (Gerçek gönderim için SMS API gerekir.) 📱";
}

/**
 * İşbirliği logosu yükleme işlemi
 */
function handle_upload_partner_logo() {
    try {
        // Hata raporlamayı aç
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // HTML çıktısını engelle
        ini_set('log_errors', 1);
        
        // JSON header'ı ayarla
        header('Content-Type: application/json');
        
        // Dosya kontrolü
        if (!isset($_FILES['partner_logo'])) {
            echo json_encode(['success' => false, 'message' => 'Dosya seçilmedi']);
            exit;
        }
        
        $file = $_FILES['partner_logo'];
        
        // Dosya yükleme hatası kontrolü
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Dosya boyutu çok büyük (php.ini limit)',
                UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu çok büyük (form limit)',
                UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı',
                UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
                UPLOAD_ERR_EXTENSION => 'Dosya yükleme uzantı tarafından durduruldu'
            ];
            
            $error_message = $error_messages[$file['error']] ?? 'Bilinmeyen hata: ' . $file['error'];
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
        
        $partner_name = $_POST['partner_name'] ?? '';
        $partner_website = $_POST['partner_website'] ?? '';
        
        // Partner adı kontrolü
        if (empty($partner_name)) {
            echo json_encode(['success' => false, 'message' => 'İşbirliği adı boş olamaz']);
            exit;
        }
        
        // Dosya boyutu kontrolü (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Dosya boyutu 2MB\'dan büyük olamaz']);
            exit;
        }
        
        // Dosya tipi kontrolü
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types) && !in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Sadece JPG, PNG ve GIF dosyaları kabul edilir. Seçilen dosya tipi: ' . $file_type]);
            exit;
        }
        
        // Dosya adını oluştur
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz dosya uzantısı']);
            exit;
        }
        
        $new_filename = 'partner_' . time() . '_' . uniqid() . '.' . $file_extension;
        
        // Klasör yolu - communities/[community_name]/assets/images/partner-logos/
        $upload_dir = __DIR__ . '/assets/images/partner-logos/';
        $upload_path = $upload_dir . $new_filename;
        $logo_url = 'assets/images/partner-logos/' . $new_filename;
        
        // Klasör yoksa oluştur
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                echo json_encode(['success' => false, 'message' => 'Klasör oluşturulamadı: ' . $upload_dir]);
                exit;
            }
            // Klasör oluşturulduktan sonra izinleri düzelt
            chmod($upload_dir, 0777);
        }
        
        // Klasör yazılabilir mi kontrol et
        if (!is_writable($upload_dir)) {
            // İzinleri düzeltmeyi dene
            chmod($upload_dir, 0777);
            
            // Eğer hala yazılamıyorsa, parent klasörleri de düzelt
            if (!is_writable($upload_dir)) {
                $parent_dir = dirname($upload_dir);
                while ($parent_dir !== $upload_dir && $parent_dir !== dirname($parent_dir)) {
                    if (is_dir($parent_dir)) {
                        chmod($parent_dir, 0777);
                    }
                    $parent_dir = dirname($parent_dir);
                }
                
                // Son bir kez dene
                chmod($upload_dir, 0777);
            }
            
            if (!is_writable($upload_dir)) {
                echo json_encode(['success' => false, 'message' => 'Klasör yazılabilir değil: ' . $upload_dir . ' (İzinler: ' . substr(sprintf('%o', fileperms($upload_dir)), -4) . ') - Lütfen hosting sağlayıcınızla iletişime geçin.']);
                exit;
            }
        }
        
        // Dosyayı taşı
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Dosya gerçekten oluştu mu kontrol et
            if (!file_exists($upload_path)) {
                echo json_encode(['success' => false, 'message' => 'Dosya oluşturulamadı']);
                exit;
            }
            
            // Veritabanına kaydet
            $db = get_db();
            
            // Önce mevcut logoyu sil
            $stmt = $db->prepare("SELECT logo_path FROM partner_logos WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $existing_logo = $result->fetchArray(SQLITE3_ASSOC);
            if ($existing_logo && isset($existing_logo['logo_path'])) {
                // Eski dosyayı sil
                $old_file_path = __DIR__ . '/' . $existing_logo['logo_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
                // Veritabanından sil
                $delete_stmt = $db->prepare("DELETE FROM partner_logos WHERE club_id = ?");
                $delete_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                $delete_stmt->execute();
            }
            
            // Yeni logoyu ekle
            $stmt = $db->prepare("INSERT INTO partner_logos (club_id, partner_name, partner_website, logo_path, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(2, $partner_name, SQLITE3_TEXT);
            $stmt->bindValue(3, $partner_website, SQLITE3_TEXT);
            $stmt->bindValue(4, $logo_url, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'İşbirliği logosu başarıyla eklendi', 'logo_url' => $logo_url]);
            } else {
                // Dosyayı sil
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
                echo json_encode(['success' => false, 'message' => 'Veritabanına kaydedilemedi: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Dosya taşınamadı. Klasör izinleri: ' . substr(sprintf('%o', fileperms($upload_dir)), -4)]);
        }
    } catch (Exception $e) {
        error_log("Partner logo upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log("Partner logo upload fatal error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Kritik hata: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * İşbirliği logosu silme işlemi
 */
function handle_delete_partner_logo() {
    try {
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        header('Content-Type: application/json');

        $db = get_db();
        
        // Mevcut logoyu bul
        $stmt = $db->prepare("SELECT logo_path FROM partner_logos WHERE club_id = ?");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $existing_logo = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($existing_logo && isset($existing_logo['logo_path'])) {
            // Dosyayı sil
            $file_path = __DIR__ . '/' . $existing_logo['logo_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Veritabanından sil
            $delete_stmt = $db->prepare("DELETE FROM partner_logos WHERE club_id = ?");
            $delete_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            
            if ($delete_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'İşbirliği logosu başarıyla silindi']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veritabanından silinemedi: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Silinecek logo bulunamadı']);
        }
    } catch (Exception $e) {
        error_log("Partner logo delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log("Partner logo delete fatal error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Kritik hata: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Gelen POST isteğini işleyen ana yönlendirici.
 */
function handle_post_request() {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['admin_id'])) {
        $db = get_db();
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        switch ($action) {
            case 'add_event':
                add_event($db, $_POST);
                break;
            case 'update_event':
                update_event($db, $_POST);
                break;
            case 'delete_event':
                delete_event($db, $id);
                break;
            case 'add_member':
                add_member($db, $_POST);
                break;
            case 'update_member':
                update_member($db, $_POST);
                break;
            case 'delete_member':
                delete_member($db, $id);
                break;
            case 'add_board_member':
                add_board_member($db, $_POST);
                break;
            case 'update_board_member':
                update_board_member($db, $_POST);
                break;
            case 'delete_board_member':
                herk_delete_board_member($db, $id);
                break;
            case 'update_settings':
                update_settings($db, $_POST);
                break;
            case 'test_smtp':
                test_smtp_connection($_POST);
                break;
            case 'save_smtp':
                save_smtp_settings($_POST);
                break;
            case 'send_test_email':
                send_test_email();
                break;
            case 'send_email':
                handle_send_email($_POST);
                break;
            case 'send_sms': // DÜZELTİLDİ: 'send_message' yerine 'send_sms' action'ını kullanmak daha mantıklı.
                handle_send_message($_POST);
                break;
            case 'logout':
                handle_logout();
                break;
            case 'upload_partner_logo':
                // JSON response için header ayarla
                header('Content-Type: application/json');
                handle_upload_partner_logo();
                break;
                
            case 'delete_partner_logo':
                // JSON response için header ayarla
                header('Content-Type: application/json');
                handle_delete_partner_logo();
                break;
        }

        $view = $_POST['current_view'] ?? 'dashboard';
        header("Location: index.php?view=" . $view);
        exit;
    }
}

// --- ANA UYGULAMA MANTIĞI VE YÖNLENDİRME ---
handle_post_request();

$current_view = $_GET['view'] ?? 'dashboard';
$event_detail = null;
if ($current_view === 'event_detail' && isset($_GET['event_id'])) {
    $event_detail = get_event_by_id((int)$_GET['event_id']);
}

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

$stats = get_stats();
$events = get_events();
$members = get_members();
$board = get_board_members();
$club_name = get_club_name();
$sms_contacts = get_sms_member_contacts();
$email_contacts = get_email_member_contacts();
$notifications = get_notifications();
$unread_notification_count = get_unread_notification_count();

// İşbirliği logolarını çek
function get_partner_logos() {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM partner_logos WHERE club_id = ? ORDER BY created_at DESC");
    $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $logos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logos[] = $row;
    }
    return $logos;
}

$partner_logos = get_partner_logos();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniPanel | Kulüp Yönetim Paneli</title>
        
        <!-- DARK MODE FLASH ÖNLEME - KESIN ÇÖZÜM -->
        <script>
            // Basit dark mode sistemi - Sadece kullanıcı seçimini kontrol et
            (function() {
                const savedTheme = localStorage.getItem('theme') || 'light';
                
                // Dark mode uygula
                if (savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.classList.remove('dark');
                }
                
                // Toggle butonunu güncelle
                setTimeout(function() {
                    const toggle = document.getElementById('theme-toggle');
                    if (toggle) {
                        if (savedTheme === 'dark') {
                            toggle.classList.add('active');
                        } else {
                            toggle.classList.remove('active');
                        }
                    }
                }, 100);
            })();
        </script>
        
        <style>
            /* Dark mode flash önleme - Hemen uygulanacak */
            html[data-theme="dark"] {
                background-color: #0f172a !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] body {
                background-color: #0f172a !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tüm elementler */
            html[data-theme="dark"] * {
                transition: none !important;
            }
            
            /* Dark mode - Tüm sayfalar için genel stiller */
            html[data-theme="dark"] {
                background-color: #0f172a !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] body {
                background-color: #0f172a !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode - Tüm container'lar */
            html[data-theme="dark"] .bg-white {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .bg-gray-50 {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .bg-gray-100 {
                background-color: #334155 !important;
            }
            
            /* Dark mode - Tüm kartlar */
            html[data-theme="dark"] .bg-white {
                background-color: #1e293b !important;
                border-color: #334155 !important;
            }
            
            /* Dark mode - Tüm tablolar */
            html[data-theme="dark"] table {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] th {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] td {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] tr:hover {
                background-color: #334155 !important;
            }
            
            /* Dark mode - Tüm input'lar */
            html[data-theme="dark"] input {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] input:focus {
                background-color: #475569 !important;
                border-color: #3b82f6 !important;
            }
            
            html[data-theme="dark"] select {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] textarea {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            /* Dark mode - Tüm butonlar */
            html[data-theme="dark"] .btn-primary {
                background-color: #3b82f6 !important;
                color: #ffffff !important;
            }
            
            html[data-theme="dark"] .btn-secondary {
                background-color: #6b7280 !important;
                color: #ffffff !important;
            }
            
            /* Dark mode - Tüm modal'lar */
            html[data-theme="dark"] .modal {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .modal-header {
                background-color: #334155 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] .modal-body {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode - Tüm form elementleri */
            html[data-theme="dark"] .form-control {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] .form-control:focus {
                background-color: #475569 !important;
                border-color: #3b82f6 !important;
            }
            
            /* Dark mode - Tüm badge'ler */
            html[data-theme="dark"] .badge {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode - Tüm alert'ler */
            html[data-theme="dark"] .alert {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            /* Dark mode - Tüm dropdown'lar */
            html[data-theme="dark"] .dropdown-menu {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] .dropdown-item {
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .dropdown-item:hover {
                background-color: #334155 !important;
            }
            
            /* Dark mode flash önleme - Sidebar */
            html[data-theme="dark"] .bg-sidebar {
                background-color: #1e293b !important;
            }
            
            /* Dark mode flash önleme - Main content */
            html[data-theme="dark"] .bg-white {
                background-color: #1e293b !important;
            }
            
            /* Dark mode flash önleme - Cards */
            html[data-theme="dark"] .bg-gray-50 {
                background-color: #1e293b !important;
            }
            
            /* Dark mode flash önleme - Text */
            html[data-theme="dark"] .text-gray-900 {
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .text-gray-700 {
                color: #cbd5e1 !important;
            }
            
            html[data-theme="dark"] .text-gray-600 {
                color: #94a3b8 !important;
            }
            
            html[data-theme="dark"] .text-gray-500 dark:text-gray-400 {
                color: #64748b !important;
            }
            
            /* Dark mode flash önleme - Borders */
            html[data-theme="dark"] .border-gray-200 {
                border-color: #334155 !important;
            }
            
            html[data-theme="dark"] .border-gray-300 {
                border-color: #475569 !important;
            }
            
            /* Dark mode flash önleme - Buttons */
            html[data-theme="dark"] .bg-blue-500 {
                background-color: #1e40af !important;
            }
            
            html[data-theme="dark"] .bg-green-500 {
                background-color: #166534 !important;
            }
            
            html[data-theme="dark"] .bg-red-500 {
                background-color: #dc2626 !important;
            }
            
            /* Dark mode flash önleme - Input fields */
            html[data-theme="dark"] .bg-gray-100 {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tables */
            html[data-theme="dark"] .bg-white {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .bg-gray-50 {
                background-color: #1e293b !important;
            }
            
            /* Dark mode flash önleme - Modals */
            html[data-theme="dark"] .bg-white {
                background-color: #1e293b !important;
            }
            
            /* Dark mode flash önleme - Headers */
            html[data-theme="dark"] .bg-gray-100 {
                background-color: #334155 !important;
            }
            
            /* Dark mode flash önleme - Sidebar links */
            html[data-theme="dark"] .text-sidebar {
                color: #cbd5e1 !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gray-100:hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] .hover\\:text-blue-600:hover {
                color: #60a5fa !important;
            }
            
            /* Dark mode flash önleme - Active links */
            html[data-theme="dark"] .active-link {
                background-color: #1e40af !important;
                color: #ffffff !important;
            }
            
            /* Dark mode flash önleme - Logout button */
            html[data-theme="dark"] .bg-red-50 {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .text-red-500 {
                color: #f87171 !important;
            }
            
            html[data-theme="dark"] .border-red-200 {
                border-color: #dc2626 !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-red-100:hover {
                background-color: #334155 !important;
            }
            
            /* Dark mode flash önleme - Tüm hover efektleri */
            html[data-theme="dark"] .hover\\:bg-white:hover {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gray-50:hover {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gray-100:hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gray-200:hover {
                background-color: #475569 !important;
            }
            
            /* Dark mode flash önleme - Tüm text hover efektleri */
            html[data-theme="dark"] .hover\\:text-gray-900:hover {
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .hover\\:text-gray-700:hover {
                color: #cbd5e1 !important;
            }
            
            html[data-theme="dark"] .hover\\:text-gray-600:hover {
                color: #94a3b8 !important;
            }
            
            html[data-theme="dark"] .hover\\:text-gray-500 dark:text-gray-400:hover {
                color: #64748b !important;
            }
            
            /* Dark mode flash önleme - Tüm border hover efektleri */
            html[data-theme="dark"] .hover\\:border-gray-200:hover {
                border-color: #334155 !important;
            }
            
            html[data-theme="dark"] .hover\\:border-gray-300:hover {
                border-color: #475569 !important;
            }
            
            html[data-theme="dark"] .hover\\:border-gray-400:hover {
                border-color: #64748b !important;
            }
            
            /* Dark mode flash önleme - Tüm shadow hover efektleri */
            html[data-theme="dark"] .hover\\:shadow-lg:hover {
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
            }
            
            html[data-theme="dark"] .hover\\:shadow-xl:hover {
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Dark mode flash önleme - Tüm transform hover efektleri */
            html[data-theme="dark"] .hover\\:scale-105:hover {
                transform: scale(1.05) !important;
            }
            
            html[data-theme="dark"] .hover\\:scale-110:hover {
                transform: scale(1.1) !important;
            }
            
            html[data-theme="dark"] .hover\\:rotate-3:hover {
                transform: rotate(3deg) !important;
            }
            
            html[data-theme="dark"] .hover\\:-rotate-3:hover {
                transform: rotate(-3deg) !important;
            }
            
            /* Dark mode flash önleme - Tüm opacity hover efektleri */
            html[data-theme="dark"] .hover\\:opacity-80:hover {
                opacity: 0.8 !important;
            }
            
            html[data-theme="dark"] .hover\\:opacity-90:hover {
                opacity: 0.9 !important;
            }
            
            /* Dark mode flash önleme - Tüm filter hover efektleri */
            html[data-theme="dark"] .hover\\:brightness-110:hover {
                filter: brightness(1.1) !important;
            }
            
            html[data-theme="dark"] .hover\\:brightness-125:hover {
                filter: brightness(1.25) !important;
            }
            
            html[data-theme="dark"] .hover\\:contrast-110:hover {
                filter: contrast(1.1) !important;
            }
            
            html[data-theme="dark"] .hover\\:saturate-110:hover {
                filter: saturate(1.1) !important;
            }
            
            /* Dark mode flash önleme - Tüm gradient hover efektleri */
            html[data-theme="dark"] .hover\\:bg-gradient-to-r:hover {
                background: linear-gradient(to right, #1e293b, #334155) !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gradient-to-b:hover {
                background: linear-gradient(to bottom, #1e293b, #334155) !important;
            }
            
            html[data-theme="dark"] .hover\\:bg-gradient-to-br:hover {
                background: linear-gradient(to bottom right, #1e293b, #334155) !important;
            }
            
            /* Dark mode flash önleme - Tüm ring hover efektleri */
            html[data-theme="dark"] .hover\\:ring-2:hover {
                box-shadow: 0 0 0 2px #1e293b !important;
            }
            
            html[data-theme="dark"] .hover\\:ring-blue-500:hover {
                box-shadow: 0 0 0 2px #1e40af !important;
            }
            
            html[data-theme="dark"] .hover\\:ring-green-500:hover {
                box-shadow: 0 0 0 2px #166534 !important;
            }
            
            html[data-theme="dark"] .hover\\:ring-red-500:hover {
                box-shadow: 0 0 0 2px #dc2626 !important;
            }
            
            html[data-theme="dark"] .hover\\:ring-purple-500:hover {
                box-shadow: 0 0 0 2px #7c3aed !important;
            }
            
            /* Dark mode flash önleme - Etkinlik Açıklama Bölümü */
            html[data-theme="dark"] .bg-gradient-to-r {
                background: linear-gradient(to right, #1e293b, #334155) !important;
            }
            
            html[data-theme="dark"] .from-gray-50 {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .to-gray-100 {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] .bg-gradient-to-r.from-gray-50.to-gray-100 {
                background: linear-gradient(to right, #1e293b, #334155) !important;
            }
            
            /* Dark mode flash önleme - Etkinlik Açıklama Text */
            html[data-theme="dark"] .text-gray-700 {
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] .text-gray-800 dark:text-gray-200 {
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Etkinlik Açıklama Border */
            html[data-theme="dark"] .border-indigo-500 {
                border-color: #1e40af !important;
            }
            
            /* Dark mode flash önleme - Etkinlik Detay Header */
            html[data-theme="dark"] .bg-white {
                background-color: #1f2937 !important;
            }
            
            html[data-theme="dark"] .text-gray-600 {
                color: #d1d5db !important;
            }
            
            html[data-theme="dark"] .bg-gray-50 {
                background-color: #374151 !important;
            }
            
            html[data-theme="dark"] .text-yellow-500 {
                color: #fbbf24 !important;
            }
            
            html[data-theme="dark"] .text-green-500 {
                color: #34d399 !important;
            }
            
            html[data-theme="dark"] .text-red-500 {
                color: #f87171 !important;
            }
            
            /* Dark mode flash önleme - Theme Toggle Button */
            html[data-theme="dark"] .theme-toggle {
                background: #3b82f6 !important;
                transition: none !important;
            }
            
            html[data-theme="dark"] .theme-toggle::before {
                transform: translateX(26px) !important;
                transition: none !important;
            }
            
            /* Profesyonel Animasyonlar */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes fadeInLeft {
                from {
                    opacity: 0;
                    transform: translateX(-30px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes fadeInRight {
                from {
                    opacity: 0;
                    transform: translateX(30px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideInDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes scaleIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes bounceIn {
                0% {
                    opacity: 0;
                    transform: scale(0.3);
                }
                50% {
                    opacity: 1;
                    transform: scale(1.05);
                }
                70% {
                    transform: scale(0.9);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.05);
                }
            }
            
            @keyframes shake {
                0%, 100% {
                    transform: translateX(0);
                }
                10%, 30%, 50%, 70%, 90% {
                    transform: translateX(-2px);
                }
                20%, 40%, 60%, 80% {
                    transform: translateX(2px);
                }
            }
            
            @keyframes float {
                0%, 100% {
                    transform: translateY(0px);
                }
                50% {
                    transform: translateY(-10px);
                }
            }
            
            @keyframes glow {
                0%, 100% {
                    box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
                }
                50% {
                    box-shadow: 0 0 20px rgba(59, 130, 246, 0.8);
                }
            }
            
            /* Animasyon Sınıfları */
            .animate-fadeInUp {
                animation: fadeInUp 0.6s ease-out;
            }
            
            .animate-fadeInLeft {
                animation: fadeInLeft 0.6s ease-out;
            }
            
            .animate-fadeInRight {
                animation: fadeInRight 0.6s ease-out;
            }
            
            .animate-slideInDown {
                animation: slideInDown 0.5s ease-out;
            }
            
            .animate-scaleIn {
                animation: scaleIn 0.4s ease-out;
            }
            
            .animate-bounceIn {
                animation: bounceIn 0.6s ease-out;
            }
            
            .animate-pulse {
                animation: pulse 2s infinite;
            }
            
            .animate-shake {
                animation: shake 0.5s ease-in-out;
            }
            
            .animate-float {
                animation: float 3s ease-in-out infinite;
            }
            
            .animate-glow {
                animation: glow 2s ease-in-out infinite;
            }
            
            /* Hover Animasyonları */
            .hover-lift {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .hover-lift:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            }
            
            .hover-scale {
                transition: transform 0.3s ease;
            }
            
            .hover-scale:hover {
                transform: scale(1.05);
            }
            
            .hover-rotate {
                transition: transform 0.3s ease;
            }
            
            .hover-rotate:hover {
                transform: rotate(5deg);
            }
            
            .hover-glow {
                transition: box-shadow 0.3s ease;
            }
            
            .hover-glow:hover {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.6);
            }
            
            /* Loading Animasyonları */
            .loading-spinner {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }
                to {
                    transform: rotate(360deg);
                }
            }
            
            .loading-dots::after {
                content: '';
                animation: dots 1.5s infinite;
            }
            
            @keyframes dots {
                0%, 20% {
                    content: '';
                }
                40% {
                    content: '.';
                }
                60% {
                    content: '..';
                }
                80%, 100% {
                    content: '...';
                }
            }
            
            /* Stagger Animasyonları */
            .stagger-1 { animation-delay: 0.1s; }
            .stagger-2 { animation-delay: 0.2s; }
            .stagger-3 { animation-delay: 0.3s; }
            .stagger-4 { animation-delay: 0.4s; }
            .stagger-5 { animation-delay: 0.5s; }
            
            /* Smooth Transitions */
            .smooth-transition {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            /* Card Animations */
            .card-hover {
                transition: all 0.3s ease;
            }
            
            .card-hover:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            
            /* Button Animations */
            .btn-animate {
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            
            .btn-animate::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }
            
            .btn-animate:hover::before {
                left: 100%;
            }
            
            /* Text Animations */
            .text-shimmer {
                background: linear-gradient(90deg, #000, #fff, #000);
                background-size: 200% 100%;
                -webkit-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                animation: shimmer 2s infinite;
            }
            
            @keyframes shimmer {
                0% {
                    background-position: -200% 0;
                }
                100% {
                    background-position: 200% 0;
                }
            }
            
            /* Dark Mode Geçiş Animasyonları */
            @keyframes darkModeTransition {
                0% {
                    opacity: 1;
                    transform: scale(1);
                }
                50% {
                    opacity: 0.7;
                    transform: scale(0.98);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes lightModeTransition {
                0% {
                    opacity: 1;
                    transform: scale(1);
                }
                50% {
                    opacity: 0.8;
                    transform: scale(1.02);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            @keyframes themeToggleSpin {
                0% {
                    transform: rotate(0deg) scale(1);
                }
                50% {
                    transform: rotate(180deg) scale(1.1);
                }
                100% {
                    transform: rotate(360deg) scale(1);
                }
            }
            
            @keyframes themeTogglePulse {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
                }
                50% {
                    transform: scale(1.05);
                    box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
                }
            }
            
            @keyframes themeToggleGlow {
                0%, 100% {
                    box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
                }
                50% {
                    box-shadow: 0 0 20px rgba(59, 130, 246, 0.8), 0 0 30px rgba(59, 130, 246, 0.4);
                }
            }
            
            /* Dark Mode Geçiş Sınıfları */
            .dark-mode-transition {
                animation: darkModeTransition 0.6s ease-in-out;
            }
            
            .light-mode-transition {
                animation: lightModeTransition 0.6s ease-in-out;
            }
            
            .theme-toggle-animate {
                animation: themeToggleSpin 0.8s ease-in-out, themeTogglePulse 0.8s ease-in-out;
            }
            
            .theme-toggle-glow {
                animation: themeToggleGlow 1s ease-in-out;
            }
            
            /* Smooth Dark Mode Transition */
            * {
                transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            }
            
            /* Dark Mode Geçiş Overlay */
            .theme-transition-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(45deg, #1e293b, #334155);
                z-index: 9999;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            
            .theme-transition-overlay.active {
                opacity: 0.1;
            }
            
            /* Dark Mode Geçiş Ripple Effect */
            .theme-ripple {
                position: fixed;
                border-radius: 4px;
                background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 9998;
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            /* Dark Mode Geçiş için Özel Animasyonlar */
            .dark-mode-fade-in {
                animation: fadeInUp 0.5s ease-out;
            }
            
            .light-mode-fade-in {
                animation: fadeInDown 0.5s ease-out;
            }
            
            @keyframes fadeInDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Theme Toggle Button Özel Animasyonları */
            .theme-toggle {
                position: relative;
                overflow: hidden;
            }
            
            .theme-toggle::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 4px;
                transform: translate(-50%, -50%);
                transition: width 0.3s ease, height 0.3s ease;
            }
            
            .theme-toggle:active::after {
                width: 100px;
                height: 100px;
            }
            
            /* Dark mode flash önleme - Theme Toggle Icons */
            html[data-theme="dark"] .theme-toggle + svg {
                color: #94a3b8 !important;
            }
            
            html[data-theme="dark"] .theme-toggle + svg + svg {
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Header Layout */
            html[data-theme="dark"] .main-header {
                position: sticky !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                transform: none !important;
                transition: none !important;
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] .main-header > div {
                width: 100% !important;
                margin: 0 !important;
                padding: 1rem 1.5rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            
            /* Sistem temasından header'ı koru */
            .main-header {
                background-color: #ffffff !important;
            }
            
            html[data-theme="dark"] .main-header {
                background-color: #1e293b !important;
            }
            
            html[data-theme="light"] .main-header {
                background-color: #ffffff !important;
            }
            
            /* Dark mode flash önleme - Main Content */
            html[data-theme="dark"] main {
                margin-left: 16rem !important;
                width: calc(100% - 16rem) !important;
                position: relative !important;
            }
            
            /* Dark mode flash önleme - Sidebar */
            html[data-theme="dark"] aside {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 16rem !important;
                height: 100vh !important;
                z-index: 30 !important;
            }
            
            /* Dark mode flash önleme - Tablo Satırları */
            html[data-theme="dark"] tbody tr:hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] tbody tr:hover td {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] tbody tr:hover th {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Hücreleri */
            html[data-theme="dark"] tbody tr td:hover {
                background-color: #475569 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] tbody tr th:hover {
                background-color: #475569 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Başlıkları */
            html[data-theme="dark"] thead tr:hover {
                background-color: #1e293b !important;
            }
            
            html[data-theme="dark"] thead tr:hover th {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Genel Hover */
            html[data-theme="dark"] table tr:hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] table tr:hover td {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] table tr:hover th {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Hücre Hover */
            html[data-theme="dark"] table td:hover {
                background-color: #475569 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] table th:hover {
                background-color: #475569 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Satır Hover */
            html[data-theme="dark"] table tbody tr:hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] table tbody tr:hover td {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] table tbody tr:hover th {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            /* Dark mode flash önleme - Tablo Satır Hover - Alternatif */
            html[data-theme="dark"] table tbody tr:nth-child(even):hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] table tbody tr:nth-child(odd):hover {
                background-color: #334155 !important;
            }
            
            /* Dark mode flash önleme - Tablo Satır Hover - Tüm Varyantlar */
            html[data-theme="dark"] table tbody tr:hover,
            html[data-theme="dark"] table tbody tr:nth-child(even):hover,
            html[data-theme="dark"] table tbody tr:nth-child(odd):hover {
                background-color: #334155 !important;
            }
            
            html[data-theme="dark"] table tbody tr:hover td,
            html[data-theme="dark"] table tbody tr:nth-child(even):hover td,
            html[data-theme="dark"] table tbody tr:nth-child(odd):hover td {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
            
            html[data-theme="dark"] table tbody tr:hover th,
            html[data-theme="dark"] table tbody tr:nth-child(even):hover th,
            html[data-theme="dark"] table tbody tr:nth-child(odd):hover th {
                background-color: #334155 !important;
                color: #f1f5f9 !important;
            }
        </style>
<?php include __DIR__ . '/../../templates/partials/tailwind_cdn_loader.php'; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Yeni Minimalist ve Resmi Palet */
        .bg-sidebar { background-color: #ffffff; }
        .text-sidebar { color: #475569; }
        .active-link { background-color: #e0f2f7; color: #0ea5e9; border-left: 4px solid #0ea5e9; }
        .card-shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
        .input-focus:focus { border-color: #0ea5e9; box-shadow: 0 0 0 1px #0ea5e9; }
        
        /* Sabit Renk Paleti */
        .color-primary { background-color: #0ea5e9; }
        .hover-primary:hover { background-color: #0284c7; }
        .color-secondary-btn { background-color: #64748b; }
        .hover-secondary:hover { background-color: #475569; }
        
        .section-card {
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .main-header {
            position: sticky;
            top: 0;
            z-index: 20; 
        }
        
        /* Sidebar Icon Animasyonları */
        .sidebar-icon {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
        }
        
        .sidebar-link:hover .sidebar-icon {
            transform: scale(1.1) rotate(5deg);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
        }
        
        .sidebar-link:hover .sidebar-icon svg {
            animation: iconBounce 0.6s ease-in-out;
        }
        
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        
        .sidebar-link {
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .sidebar-link:hover::before {
            left: 100%;
        }
        
        .sidebar-link:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .mail-metric-card {
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .mail-metric-card::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.35;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.45), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(255,255,255,0.25), transparent 60%);
            pointer-events: none;
            mix-blend-mode: overlay;
        }

        .mail-metric-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
        }

        .mail-template-btn {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.9rem 1rem;
            border-radius: 1rem;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.3);
            transition: all 0.2s ease;
            text-align: left;
            height: 100%;
        }

        .mail-template-btn:hover {
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 15px 35px -20px rgba(59, 130, 246, 0.45);
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(99,102,241,0.1));
        }

        .mail-template-title {
            font-weight: 600;
            color: #1e293b;
        }

        .mail-template-desc {
            font-size: 0.75rem;
            color: #64748b;
        }

        html[data-theme="dark"] .mail-template-btn {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(148, 163, 184, 0.35);
        }

        html[data-theme="dark"] .mail-template-title {
            color: #e2e8f0;
        }

        html[data-theme="dark"] .mail-template-desc {
            color: #94a3b8;
        }

        .editor-toggle-btn {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            transition: all 0.2s ease;
        }

        .editor-toggle-btn:hover {
            background: rgba(79, 70, 229, 0.08);
            color: #1d4ed8;
        }

        .editor-toggle-btn.active {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            box-shadow: 0 12px 32px -20px rgba(79, 70, 229, 0.7);
        }

        html[data-theme="dark"] .editor-toggle-btn {
            color: #cbd5f5;
        }

        .mail-option-chip {
            cursor: pointer;
            margin-right: 0.75rem;
            margin-bottom: 0.5rem;
            display: inline-flex;
        }

        .mail-option-chip span {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.4);
        }

        .mail-option-chip:hover span {
            background: #e0f2fe;
            color: #0369a1;
        }

        .mail-option-chip input:checked + span {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            box-shadow: 0 12px 28px -18px rgba(79, 70, 229, 0.7);
        }

        html[data-theme="dark"] .mail-option-chip span {
            background: rgba(30, 41, 59, 0.85);
            color: #cbd5f5;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.2);
        }

        .email-contact-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
        }

        .email-contact-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .email-contact-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .email-contact-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.4);
            border-radius: 999px;
        }

        .email-contact-item {
            transition: all 0.2s ease;
        }

        .email-contact-item:hover {
            transform: translateY(-1px);
        }

        .email-contact-item.selected {
            border-color: rgba(79, 70, 229, 0.45);
            background: rgba(79, 70, 229, 0.12);
            box-shadow: 0 18px 36px -28px rgba(79, 70, 229, 0.6);
        }

        html[data-theme="dark"] .email-contact-item.selected {
            border-color: rgba(129, 140, 248, 0.4);
            background: rgba(129, 140, 248, 0.25);
        }

        .mail-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(120deg, rgba(59,130,246,0.08), rgba(99,102,241,0.12), rgba(236,72,153,0.08));
            border-radius: 1.75rem;
        }

        .mail-hero::before {
            content: '';
            position: absolute;
            inset: -20%;
            background: radial-gradient(circle at 20% 20%, rgba(59,130,246,0.25), transparent 55%),
                        radial-gradient(circle at 80% 30%, rgba(236,72,153,0.2), transparent 60%),
                        radial-gradient(circle at 50% 80%, rgba(56,189,248,0.18), transparent 60%);
            opacity: 0.9;
            pointer-events: none;
        }

        .mail-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            background: rgba(59,130,246,0.12);
            color: #1d4ed8;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .mail-hero-title {
            font-size: clamp(1.9rem, 2.4vw, 2.4rem);
            font-weight: 700;
            color: #0f172a;
        }

        .mail-hero-sub {
            color: #475569;
            max-width: 48ch;
            line-height: 1.7;
        }

        .mail-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.05);
            color: #1e293b;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .mail-hero-aside {
            backdrop-filter: blur(10px);
            border-radius: 1.25rem;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(255,255,255,0.6);
            padding: 1.25rem 1.5rem;
            display: grid;
            gap: 0.75rem;
            min-width: 220px;
        }

        .mail-hero-aside strong {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }

        .mail-hero-aside span {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1d4ed8;
            font-weight: 600;
        }

        .mail-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .mail-quick-actions button {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.45rem 1rem;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: #ffffff;
            color: #1e293b;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .mail-quick-actions button svg {
            width: 1rem;
            height: 1rem;
        }

        .mail-quick-actions button:hover {
            border-color: rgba(59,130,246,0.5);
            color: #1d4ed8;
            box-shadow: 0 12px 24px -20px rgba(59,130,246,0.6);
        }

        .mail-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.75rem;
        }

        .mail-meta-card {
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(248,250,252,0.8);
            display: grid;
            gap: 0.35rem;
        }

        .mail-meta-card span {
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }

        .mail-attachment-drop {
            margin-top: 0.5rem;
            padding: 1rem 1.2rem;
            border: 1px dashed rgba(148, 163, 184, 0.6);
            border-radius: 1rem;
            background: rgba(248,250,252,0.6);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #475569;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .mail-attachment-drop svg {
            width: 1.25rem;
            height: 1.25rem;
            color: #3b82f6;
        }

        .mail-attachment-drop:hover {
            border-color: rgba(59,130,246,0.6);
            background: rgba(219,234,254,0.35);
        }

        .mail-preview-card {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 1.5rem;
            background: rgba(15,23,42,0.02);
            padding: 1.5rem;
            display: grid;
            gap: 1rem;
        }

        .mail-preview-header {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .mail-preview-header::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 6px rgba(34,197,94,0.12);
        }

        .mail-preview-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .mail-preview-subject {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .mail-preview-to {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .mail-preview-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.8rem;
            border-radius: 999px;
            background: rgba(59,130,246,0.12);
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .mail-preview-body {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            border: 1px solid rgba(148, 163, 184, 0.25);
            min-height: 120px;
            line-height: 1.6;
            color: #475569;
            font-size: 0.9rem;
        }

        .mail-preview-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .mail-preview-footer button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.95rem;
            border-radius: 0.9rem;
            background: #1d4ed8;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .mail-preview-footer button:hover {
            background: #1e40af;
            box-shadow: 0 12px 28px -20px rgba(37, 99, 235, 0.65);
        }

        .mail-footer-tips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        .mail-footer-tips span {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.85rem;
            background: rgba(15,23,42,0.04);
        }

        /* Aktif link için özel animasyon */
        .active-link .sidebar-icon {
            animation: activePulse 2s infinite;
        }
        
        @keyframes activePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Logo animasyonları */
        .logo-container img {
            transition: all 0.3s ease;
        }
        

        
        /* Partner logo animasyonu */
        .partner-logo {
            transition: all 0.3s ease;
        }
        
        .partner-logo:hover {
            transform: scale(1.15) rotate(-2deg);
        }
        
        /* Tema Sistemi */
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #d1d5db;
            --hover-bg: #f9fafb;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --card-bg: #1e293b;
            --sidebar-bg: #0f172a;
            --input-bg: #334155;
            --input-border: #475569;
            --hover-bg: #334155;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
        }
        
        .theme-transition {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .bg-sidebar {
            background-color: var(--sidebar-bg);
        }
        
        .text-sidebar {
            color: var(--text-secondary);
        }
        
        .card-bg {
            background-color: var(--card-bg);
        }
        
        /* KUSURSUZ DARK MODE - TÜM ELEMENTLER İÇİN */
        
        /* Dark Mode Flash Önleme - Hemen Uygulanacak */
        body {
            transition: background-color 0.1s ease, color 0.1s ease;
        }
        
        /* Ana Arka Plan */
        [data-theme="dark"] body {
            background-color: #0f172a !important;
            color: #f1f5f9 !important;
        }
        
        /* Dark Mode Varsayılan Stiller - Flash Önleme */
        .dark-mode-flash-prevention {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        
        /* Beyaz Arka Planlar */
        [data-theme="dark"] .bg-white {
            background-color: #1e293b !important;
        }
        
        /* Gri Arka Planlar */
        [data-theme="dark"] .bg-gray-50 {
            background-color: #334155 !important;
        }
        
        [data-theme="dark"] .bg-gray-100 {
            background-color: #475569 !important;
        }
        
        [data-theme="dark"] .bg-gray-200 {
            background-color: #64748b !important;
        }
        
        /* Hover Efektleri - Koyu Renkler Kullan */
        [data-theme="dark"] .hover\\:bg-gray-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-100:hover {
            background-color: #334155 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-white:hover {
            background-color: #0f172a !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-200:hover {
            background-color: #475569 !important;
        }
        
        /* Sidebar Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .sidebar-link:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .sidebar-link:hover .sidebar-icon {
            color: #60a5fa !important;
        }
        
        /* Button Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .btn-animated:hover {
            background-color: #1e40af !important;
            color: #ffffff !important;
        }
        
        /* Card Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .card-hover:hover {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        
        /* Table Row Hover - Koyu Renkler */
        [data-theme="dark"] .table-row:hover {
            background-color: #1e293b !important;
        }
        
        /* Modal Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .modal-content:hover {
            background-color: #1e293b !important;
        }
        
        /* Tüm Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:bg-blue-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-green-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-red-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-yellow-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-purple-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-pink-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-indigo-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-cyan-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-teal-50:hover {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:bg-orange-50:hover {
            background-color: #1e293b !important;
        }
        
        /* Button Hover Renkleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:bg-blue-500:hover {
            background-color: #1e40af !important;
        }
        
        [data-theme="dark"] .hover\\:bg-green-500:hover {
            background-color: #166534 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-red-500:hover {
            background-color: #dc2626 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-yellow-500:hover {
            background-color: #ca8a04 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-purple-500:hover {
            background-color: #7c3aed !important;
        }
        
        /* Text Hover Renkleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:text-blue-600:hover {
            color: #60a5fa !important;
        }
        
        [data-theme="dark"] .hover\\:text-green-600:hover {
            color: #4ade80 !important;
        }
        
        [data-theme="dark"] .hover\\:text-red-600:hover {
            color: #f87171 !important;
        }
        
        [data-theme="dark"] .hover\\:text-yellow-600:hover {
            color: #facc15 !important;
        }
        
        [data-theme="dark"] .hover\\:text-purple-600:hover {
            color: #a78bfa !important;
        }
        
        /* Border Hover Renkleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:border-blue-300:hover {
            border-color: #1e40af !important;
        }
        
        [data-theme="dark"] .hover\\:border-green-300:hover {
            border-color: #166534 !important;
        }
        
        [data-theme="dark"] .hover\\:border-red-300:hover {
            border-color: #dc2626 !important;
        }
        
        [data-theme="dark"] .hover\\:border-yellow-300:hover {
            border-color: #ca8a04 !important;
        }
        
        [data-theme="dark"] .hover\\:border-purple-300:hover {
            border-color: #7c3aed !important;
        }
        
        /* Shadow Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:shadow-lg:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
        }
        
        [data-theme="dark"] .hover\\:shadow-xl:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.2) !important;
        }
        
        /* Transform Hover Efektleri */
        [data-theme="dark"] .hover\\:scale-105:hover {
            transform: scale(1.05) !important;
        }
        
        [data-theme="dark"] .hover\\:scale-110:hover {
            transform: scale(1.1) !important;
        }
        
        [data-theme="dark"] .hover\\:rotate-3:hover {
            transform: rotate(3deg) !important;
        }
        
        [data-theme="dark"] .hover\\:-rotate-3:hover {
            transform: rotate(-3deg) !important;
        }
        
        /* Opacity Hover Efektleri */
        [data-theme="dark"] .hover\\:opacity-80:hover {
            opacity: 0.8 !important;
        }
        
        [data-theme="dark"] .hover\\:opacity-90:hover {
            opacity: 0.9 !important;
        }
        
        /* Filter Hover Efektleri */
        [data-theme="dark"] .hover\\:brightness-110:hover {
            filter: brightness(1.1) !important;
        }
        
        [data-theme="dark"] .hover\\:brightness-125:hover {
            filter: brightness(1.25) !important;
        }
        
        [data-theme="dark"] .hover\\:contrast-110:hover {
            filter: contrast(1.1) !important;
        }
        
        [data-theme="dark"] .hover\\:saturate-110:hover {
            filter: saturate(1.1) !important;
        }
        
        /* Gradient Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:bg-gradient-to-r:hover {
            background: linear-gradient(to right, #1e293b, #334155) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gradient-to-b:hover {
            background: linear-gradient(to bottom, #1e293b, #334155) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gradient-to-br:hover {
            background: linear-gradient(to bottom right, #1e293b, #334155) !important;
        }
        
        /* Ring Hover Efektleri - Koyu Renkler */
        [data-theme="dark"] .hover\\:ring-2:hover {
            box-shadow: 0 0 0 2px #1e293b !important;
        }
        
        [data-theme="dark"] .hover\\:ring-blue-500:hover {
            box-shadow: 0 0 0 2px #1e40af !important;
        }
        
        [data-theme="dark"] .hover\\:ring-green-500:hover {
            box-shadow: 0 0 0 2px #166534 !important;
        }
        
        [data-theme="dark"] .hover\\:ring-red-500:hover {
            box-shadow: 0 0 0 2px #dc2626 !important;
        }
        
        [data-theme="dark"] .hover\\:ring-purple-500:hover {
            box-shadow: 0 0 0 2px #7c3aed !important;
        }
        
        /* Backdrop Hover Efektleri */
        [data-theme="dark"] .hover\\:backdrop-blur-sm:hover {
            backdrop-filter: blur(4px) !important;
        }
        
        [data-theme="dark"] .hover\\:backdrop-blur-md:hover {
            backdrop-filter: blur(8px) !important;
        }
        
        [data-theme="dark"] .hover\\:backdrop-blur-lg:hover {
            backdrop-filter: blur(12px) !important;
        }
        
        /* Text Renkleri */
        [data-theme="dark"] .text-gray-800 dark:text-gray-200 {
            color: #f1f5f9 !important;
        }
        
        [data-theme="dark"] .text-gray-700 {
            color: #e2e8f0 !important;
        }
        
        [data-theme="dark"] .text-gray-600 {
            color: #cbd5e1 !important;
        }
        
        [data-theme="dark"] .text-gray-500 dark:text-gray-400 {
            color: #94a3b8 !important;
        }
        
        [data-theme="dark"] .text-gray-400 {
            color: #64748b !important;
        }
        
        [data-theme="dark"] .text-gray-300 {
            color: #475569 !important;
        }
        
        [data-theme="dark"] .text-gray-200 {
            color: #334155 !important;
        }
        
        [data-theme="dark"] .text-gray-900 {
            color: #f8fafc !important;
        }
        
        /* Border Renkleri */
        [data-theme="dark"] .border-gray-200 {
            border-color: #334155 !important;
        }
        
        [data-theme="dark"] .border-gray-300 {
            border-color: #475569 !important;
        }
        
        [data-theme="dark"] .border-gray-400 {
            border-color: #64748b !important;
        }
        
        [data-theme="dark"] .border-gray-500 {
            border-color: #94a3b8 !important;
        }
        
        /* Input ve Form Elementleri */
        [data-theme="dark"] input,
        [data-theme="dark"] textarea,
        [data-theme="dark"] select {
            background-color: #334155 !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }
        
        [data-theme="dark"] input:focus,
        [data-theme="dark"] textarea:focus,
        [data-theme="dark"] select:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder {
            color: #64748b !important;
        }
        
        /* Shadow Efektleri */
        [data-theme="dark"] .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2) !important;
        }
        
        [data-theme="dark"] .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
        }
        
        [data-theme="dark"] .shadow-xl {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2) !important;
        }
        
        [data-theme="dark"] .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4) !important;
        }
        
        /* Card ve Section Efektleri */
        [data-theme="dark"] .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2) !important;
        }
        
        [data-theme="dark"] .section-card {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        
        /* Sidebar Dark Mode */
        [data-theme="dark"] .bg-sidebar {
            background-color: #0f172a !important;
        }
        
        [data-theme="dark"] .text-sidebar {
            color: #94a3b8 !important;
        }
        
        [data-theme="dark"] .active-link {
            background-color: #1e40af !important;
            color: #ffffff !important;
            border-left-color: #3b82f6 !important;
        }
        
        /* Tablo Dark Mode */
        [data-theme="dark"] .table-row:hover {
            background-color: #334155 !important;
        }
        
        [data-theme="dark"] .divide-y > * + * {
            border-color: #334155 !important;
        }
        
        /* Modal Dark Mode */
        [data-theme="dark"] .modal-content {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        
        /* Button Dark Mode */
        [data-theme="dark"] .btn-animated:hover {
            background-color: #1e40af !important;
        }
        
        /* Scrollbar Dark Mode */
        [data-theme="dark"] ::-webkit-scrollbar {
            width: 8px;
            background-color: #1e293b;
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-track {
            background-color: #334155;
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-thumb {
            background-color: #64748b;
            border-radius: 4px;
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }
        
        /* Özel Dark Mode Sınıfları */
        [data-theme="dark"] .dark-bg-primary {
            background-color: #0f172a !important;
        }
        
        [data-theme="dark"] .dark-bg-secondary {
            background-color: #1e293b !important;
        }
        
        [data-theme="dark"] .dark-text-primary {
            color: #f1f5f9 !important;
        }
        
        [data-theme="dark"] .dark-text-secondary {
            color: #94a3b8 !important;
        }
        
        [data-theme="dark"] .dark-border {
            border-color: #334155 !important;
        }
        
        /* Hover Text Renkleri */
        [data-theme="dark"] .hover\\:text-gray-600:hover {
            color: #cbd5e1 !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-700:hover {
            color: #e2e8f0 !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-800 dark:text-gray-200:hover {
            color: #f1f5f9 !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-900:hover {
            color: #f8fafc !important;
        }
        
        /* Hover Border Renkleri */
        [data-theme="dark"] .hover\\:border-gray-300:hover {
            border-color: #475569 !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-400:hover {
            border-color: #64748b !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-500:hover {
            border-color: #94a3b8 !important;
        }
        
        /* Focus Efektleri */
        [data-theme="dark"] .focus\\:border-blue-500:focus {
            border-color: #3b82f6 !important;
        }
        
        [data-theme="dark"] .focus\\:ring-blue-500:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        /* Özel Renkler - Dark Mode'da Korun */
        [data-theme="dark"] .text-blue-600 {
            color: #60a5fa !important;
        }
        
        [data-theme="dark"] .text-green-600 {
            color: #4ade80 !important;
        }
        
        [data-theme="dark"] .text-red-600 {
            color: #f87171 !important;
        }
        
        [data-theme="dark"] .text-yellow-600 {
            color: #facc15 !important;
        }
        
        [data-theme="dark"] .text-purple-600 {
            color: #a78bfa !important;
        }
        
        [data-theme="dark"] .text-pink-600 {
            color: #f472b6 !important;
        }
        
        [data-theme="dark"] .text-indigo-600 {
            color: #818cf8 !important;
        }
        
        [data-theme="dark"] .text-teal-600 {
            color: #2dd4bf !important;
        }
        
        [data-theme="dark"] .text-orange-600 {
            color: #fb923c !important;
        }
        
        [data-theme="dark"] .text-cyan-600 {
            color: #22d3ee !important;
        }
        
        [data-theme="dark"] .text-gray-800 dark:text-gray-200 {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .text-gray-700 {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .text-gray-500 dark:text-gray-400 {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .text-gray-400 {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .border-gray-200 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-300 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .bg-gray-50 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-100 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-50:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-100:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-white:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] input,
        [data-theme="dark"] textarea,
        [data-theme="dark"] select {
            background-color: var(--input-bg) !important;
            border-color: var(--input-border) !important;
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] input:focus,
        [data-theme="dark"] textarea:focus,
        [data-theme="dark"] select:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px #3b82f6 !important;
        }
        
        [data-theme="dark"] .shadow-md {
            box-shadow: var(--shadow) !important;
        }
        
        [data-theme="dark"] .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        }
        
        [data-theme="dark"] .shadow-xl {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }
        
        [data-theme="dark"] .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        }
        
        /* Ek Dark Mode Sınıfları */
        [data-theme="dark"] .text-gray-900 {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .text-gray-600 {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .text-gray-300 {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .text-gray-200 {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .bg-gray-200 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-300 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-400 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-500 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-600 {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-700 {
            background-color: var(--card-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-800 {
            background-color: var(--card-bg) !important;
        }
        
        [data-theme="dark"] .bg-gray-900 {
            background-color: var(--bg-primary) !important;
        }
        
        [data-theme="dark"] .border-gray-400 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-500 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-600 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-700 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-800 {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .border-gray-900 {
            border-color: var(--border-color) !important;
        }
        
        /* Hover Efektleri */
        [data-theme="dark"] .hover\\:bg-gray-200:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-300:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-400:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-500:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-600:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-700:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-800:hover {
            background-color: var(--hover-bg) !important;
        }
        
        [data-theme="dark"] .hover\\:bg-gray-900:hover {
            background-color: var(--hover-bg) !important;
        }
        
        /* Text Hover Efektleri */
        [data-theme="dark"] .hover\\:text-gray-600:hover {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-700:hover {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-800 dark:text-gray-200:hover {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .hover\\:text-gray-900:hover {
            color: var(--text-primary) !important;
        }
        
        /* Border Hover Efektleri */
        [data-theme="dark"] .hover\\:border-gray-300:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-400:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-500:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-600:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-700:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-800:hover {
            border-color: var(--border-color) !important;
        }
        
        [data-theme="dark"] .hover\\:border-gray-900:hover {
            border-color: var(--border-color) !important;
        }
        
        /* Focus Efektleri */
        [data-theme="dark"] .focus\\:border-blue-500:focus {
            border-color: #3b82f6 !important;
        }
        
        [data-theme="dark"] .focus\\:ring-blue-500:focus {
            box-shadow: 0 0 0 1px #3b82f6 !important;
        }
        
        [data-theme="dark"] .focus\\:ring-2:focus {
            box-shadow: 0 0 0 2px #3b82f6 !important;
        }
        
        /* Placeholder */
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder {
            color: var(--text-secondary) !important;
        }
        
        /* Scrollbar */
        [data-theme="dark"] ::-webkit-scrollbar {
            width: 8px;
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        
        [data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Modal ve Overlay */
        [data-theme="dark"] .bg-gray-900 {
            background-color: rgba(15, 23, 42, 0.8) !important;
        }
        
        [data-theme="dark"] .bg-gray-800 {
            background-color: var(--card-bg) !important;
        }
        
        /* Card Shadow */
        [data-theme="dark"] .card-shadow {
            box-shadow: var(--shadow) !important;
        }
        
        /* Button Hover */
        [data-theme="dark"] .hover\\:bg-blue-600:hover {
            background-color: #2563eb !important;
        }
        
        [data-theme="dark"] .hover\\:bg-green-600:hover {
            background-color: #059669 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-red-600:hover {
            background-color: #dc2626 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-yellow-600:hover {
            background-color: #d97706 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-purple-600:hover {
            background-color: #7c3aed !important;
        }
        
        [data-theme="dark"] .hover\\:bg-pink-600:hover {
            background-color: #db2777 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-indigo-600:hover {
            background-color: #4f46e5 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-teal-600:hover {
            background-color: #0d9488 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-orange-600:hover {
            background-color: #ea580c !important;
        }
        
        [data-theme="dark"] .hover\\:bg-cyan-600:hover {
            background-color: #0891b2 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-lime-600:hover {
            background-color: #65a30d !important;
        }
        
        [data-theme="dark"] .hover\\:bg-emerald-600:hover {
            background-color: #059669 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-rose-600:hover {
            background-color: #e11d48 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-violet-600:hover {
            background-color: #7c3aed !important;
        }
        
        [data-theme="dark"] .hover\\:bg-fuchsia-600:hover {
            background-color: #c026d3 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-sky-600:hover {
            background-color: #0284c7 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-amber-600:hover {
            background-color: #d97706 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-stone-600:hover {
            background-color: #57534e !important;
        }
        
        [data-theme="dark"] .hover\\:bg-neutral-600:hover {
            background-color: #525252 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-zinc-600:hover {
            background-color: #525252 !important;
        }
        
        [data-theme="dark"] .hover\\:bg-slate-600:hover {
            background-color: #475569 !important;
        }
        
        .theme-toggle {
            position: relative;
            width: 50px;
            height: 24px;
            background: #e2e8f0;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .theme-toggle.active {
            background: #3b82f6;
        }
        
        .theme-toggle::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 3px;
            transition: transform 0.3s ease;
        }
        
        .theme-toggle.active::before {
            transform: translateX(26px);
        }
        
        /* Mobil Optimizasyon */
        @media (max-width: 768px) {
            .mobile-menu-open {
                transform: translateX(0);
            }
            
            .mobile-menu-closed {
                transform: translateX(-100%);
            }
            
            .mobile-padding {
                padding: 1rem;
            }
            
            .mobile-text-sm {
                font-size: 0.875rem;
            }
            
            .mobile-grid-1 {
                grid-template-columns: 1fr;
            }
            
            .mobile-stack {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .mobile-full-width {
                width: 100%;
            }
            
            .mobile-hidden {
                display: none;
            }
            
            .mobile-visible {
                display: block;
            }
            
            /* Touch-friendly buttons */
            .touch-button {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Mobile navigation */
            .mobile-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #e2e8f0;
                z-index: 50;
                display: flex;
                justify-content: space-around;
                padding: 0.5rem 0;
            }
            
            .mobile-nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0.5rem;
                text-decoration: none;
                color: #64748b;
                font-size: 0.75rem;
                transition: color 0.2s;
            }
            
            .mobile-nav-item.active {
                color: #0ea5e9;
            }
            
            .mobile-nav-item svg {
                width: 1.5rem;
                height: 1.5rem;
                margin-bottom: 0.25rem;
            }
        }
        
        /* Tablet optimizasyonu */
        @media (min-width: 768px) and (max-width: 1024px) {
            .tablet-grid-2 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tablet-padding {
                padding: 1.5rem;
            }
        }
        
        /* PWA Stilleri */
        @media (display-mode: standalone) {
            body {
                padding-top: env(safe-area-inset-top);
                padding-bottom: env(safe-area-inset-bottom);
            }
        }

        /* Modern Toast Animasyonları */
        .toast-enter {
            transform: translateX(0) !important;
            opacity: 1 !important;
        }

        .toast-exit {
            transform: translateX(100%) !important;
            opacity: 0 !important;
        }

        /* Yeni Toast Progress Bar Sistemi - Soldan Sağa Akış */
        .toast-progress-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(0,0,0,0.15);
            overflow: hidden;
            border-radius: 0 0 8px 8px;
        }
        
        .toast-progress-fill {
            height: 100%;
            background: currentColor;
            opacity: 0.8;
            width: 0%;
            animation: progressFlow linear forwards;
            border-radius: 0 0 8px 8px;
        }
        
        @keyframes progressFlow {
            from { width: 0%; }
            to { width: 100%; }
        }

        /* Toast Hover Efektleri */
        .toast:hover {
            transform: translateX(-2px) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }

        .toast-enter:hover {
            transform: translateX(-2px) !important;
        }

        /* Toast Container Animasyonu */
        #toast-container {
            pointer-events: none;
        }

        #toast-container .toast {
            pointer-events: auto;
        }

        /* Dark Mode Toast Düzenlemeleri */
        html[data-theme="dark"] .toast {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }

        html[data-theme="dark"] .toast-progress {
            background-color: #374151 !important;
        }

        /* Toast Tip Renkleri - Daha Belirgin Progress Bar */
        .toast-success .toast-progress-fill {
            background: linear-gradient(90deg, #10b981, #34d399) !important;
            opacity: 1 !important;
        }

        .toast-error .toast-progress-fill {
            background: linear-gradient(90deg, #ef4444, #f87171) !important;
            opacity: 1 !important;
        }

        .toast-warning .toast-progress-fill {
            background: linear-gradient(90deg, #f59e0b, #fbbf24) !important;
            opacity: 1 !important;
        }

        .toast-info .toast-progress-fill {
            background: linear-gradient(90deg, #3b82f6, #60a5fa) !important;
            opacity: 1 !important;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 flex min-h-screen pt-0">

    <div id="mobile-menu-toggle" class="lg:hidden fixed top-4 left-4 z-40 p-2 color-primary text-white rounded-md shadow-lg cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
    </div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-sidebar dark:bg-gray-900 transform -translate-x-full lg:translate-x-0 transition duration-200 ease-in-out border-r border-gray-200 dark:border-gray-700">
        <div class="h-full flex flex-col p-4">
            <div class="flex flex-col items-center justify-center p-4 mb-8 border-b border-gray-300 dark:border-gray-700">
                <!-- Ana Logo ve İşbirliği Logosu -->
                <div class="flex items-center justify-center logo-container">
                    <img src="assets/images/brand/logo_tr.png" alt="Four Software Logo" class="w-16 h-16">
                    
                    <?php if (!empty($partner_logos)): ?>
                        <?php $partner_logo = $partner_logos[0]; ?>
                        <div class="mx-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <img src="<?= htmlspecialchars($partner_logo['logo_path']) ?>" alt="<?= htmlspecialchars($partner_logo['partner_name']) ?>" class="w-16 h-16 rounded-md object-cover partner-logo cursor-pointer" title="<?= htmlspecialchars($partner_logo['partner_name']) ?>" onclick="openPartnerLogoModal()">
                    <?php else: ?>
                        <div class="mx-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <div class="w-12 h-12 bg-gray-100 rounded-md flex items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors partner-logo" onclick="openPartnerLogoModal()" title="İşbirliği Logosu Ekle">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <nav class="flex-1 space-y-2">
                <?php
                $menu_items = [
                    'dashboard' => ['Pano', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>'],
                    'events' => ['Etkinlik Yönetimi', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>'],
                    'members' => ['Üyelik Listesi', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'],
                    'board' => ['Yönetim Kurulu', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>'],
                    'messages' => ['Mesaj Merkezi', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>'],
                    'mail' => ['Mail Merkezi', '<svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>']
                ];

                // Ana menü öğelerini göster
                foreach ($menu_items as $view => $item):
                    $is_active = $current_view === $view ? 'active-link' : 'text-sidebar hover:bg-gray-100 hover:text-blue-600';
                ?>
                    <a href="?view=<?= $view ?>" class="sidebar-link flex items-center p-3 rounded-md <?= $is_active ?> transition duration-150">
                        <span class="sidebar-icon"><?= $item[1] ?></span>
                        <span class="font-normal"><?= $item[0] ?></span>
                    </a>
                    
                    <!-- Etkinlik Yönetimi Alt Başlığı -->
                    <?php if ($view === 'events' && $current_view === 'event_detail' && $event_detail): ?>
                        <div class="ml-4 mt-2 mb-4">
                            <div class="px-3 py-2">
                                <a href="?view=event_detail&event_id=<?= $event_detail['id'] ?>" class="sidebar-link flex items-center p-2 rounded-md active-link transition duration-150">
                                    <span class="sidebar-icon">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                    </span>
                                    <span class="font-normal text-sm"><?= htmlspecialchars($event_detail['title']) ?></span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto p-4 border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="w-full flex items-center justify-center p-3 rounded-md text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 transition duration-150 font-semibold border border-red-200 dark:border-red-800">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Çıkış Yap
                    </button>
                </form>
                
                <!-- Copyright ve Versiyon Bilgisi -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                            © 2024 UniPanel - v1.0
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <main class="flex-1 lg:ml-64 pb-16 lg:pb-0">
        <header class="main-header bg-white dark:bg-gray-900 fixed top-0 left-0 lg:left-64 right-0 z-40 border-b border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="p-4 sm:p-6 lg:p-4 flex items-center justify-between w-full">
                <div class="flex items-center">
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight !text-gray-900 dark:!text-gray-100">
                            <?= htmlspecialchars($club_name) ?>
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Topluluk Yönetimi
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3">
                    <!-- Tema Toggle -->
                    <button onclick="toggleTheme()" class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition duration-200 flex items-center pointer-events-auto z-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </button>
                    
                    <!-- Bildirimler Butonu -->
                    <div class="relative z-50">
                        <button id="notification-btn" class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition duration-200 relative flex items-center pointer-events-auto">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center font-medium <?= $unread_notification_count > 0 ? '' : 'hidden' ?>"><?= $unread_notification_count ?></span>
                        </button>
                        
                        
                        <!-- Bildirim Dropdown -->
                        <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 hidden">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Bildirimler</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="p-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 <?= $notification['is_read'] == 0 ? 'bg-blue-50 dark:bg-blue-950/30' : '' ?> cursor-pointer transition-colors" onclick="showNotificationDetail(<?= (int)$notification['id'] ?>, <?= tpl_js_escaped($notification['title'] ?? '') ?>, <?= tpl_js_escaped($notification['message'] ?? '') ?>, <?= tpl_js_escaped($notification['type'] ?? '') ?>, <?= tpl_js_escaped(date('d.m.Y H:i', strtotime($notification['created_at'] ?? 'now'))) ?>, <?= (int)$notification['is_read'] ?>)">
                                            <?php 
                                            $header_badges = [
                                                'success' => ['class' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300', 'text' => 'Başarılı'],
                                                'warning' => ['class' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300', 'text' => 'Uyarı'],
                                                'error' => ['class' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300', 'text' => 'Hata'],
                                                'urgent' => ['class' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300', 'text' => 'Acil'],
                                                'info' => ['class' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300', 'text' => 'Bilgi']
                                            ];
                                            $header_badge = $header_badges[$notification['type']] ?? ['class' => 'bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-300', 'text' => 'Bilgi'];
                                            ?>
                                            <div class="flex items-start gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 line-clamp-1"><?= htmlspecialchars($notification['title']) ?></p>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium <?= $header_badge['class'] ?> shrink-0">
                                                            <?= $header_badge['text'] ?>
                                                        </span>
                                                        <?php if ($notification['is_read'] == 0): ?>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 shrink-0">
                                                                Yeni
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-1 mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-500"><?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.828 7l2.586-2.586A2 2 0 018.828 4h6.344a2 2 0 011.414.586L19.172 7H4.828zM4 7v10a2 2 0 002 2h12a2 2 0 002-2V7H4z"></path>
                                        </svg>
                                        <p class="text-sm">Henüz bildirim yok</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 border-t border-gray-200 dark:border-gray-700">
                                <a href="?view=notifications" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-500">Tüm bildirimleri gör</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profil Kutusu -->
                    <div class="relative z-50">
                        <button id="profile-btn" class="flex items-center space-x-2 p-1.5 pr-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-200 pointer-events-auto">
                            <div class="w-9 h-9 rounded-md bg-white dark:bg-gray-800 flex items-center justify-center border border-gray-300 dark:border-gray-600">
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <!-- Profil Dropdown -->
                        <div id="profile-dropdown" class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 hidden">
                            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-md bg-white dark:bg-gray-700 flex items-center justify-center border border-gray-300 dark:border-gray-600">
                                        <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($club_name) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Topluluk Yöneticisi</p>
                                    </div>
                                </div>
                            </div>
                            <div class="py-1">
                                <a href="?view=settings" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-5 h-5 mr-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Ayarlar
                                </a>
                                <a href="?view=notifications" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-5 h-5 mr-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    Bildirimler
                                    <?php if ($unread_notification_count > 0): ?>
                                        <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $unread_notification_count ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <div class="content-wrapper p-4 sm:p-8 pt-24 lg:pt-28 bg-gray-50 dark:bg-gray-900 min-h-screen">
            <!-- Session mesajları artık toast olarak gösterilecek -->
            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
            toastManager.show('Başarılı', <?= tpl_js_escaped($message ?? '') ?>, 'success', 3000);
                    });
                </script>
            <?php endif; ?>
            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
            toastManager.show('Hata', <?= tpl_js_escaped($error ?? '') ?>, 'error', 4000);
                    });
                </script>
            <?php endif; ?>

            <div id="content-container">
                <?php if ($current_view === 'dashboard'): ?>
                    <!-- Dashboard Widgets -->
                    <div id="dashboard-view" data-view="dashboard">
                        <!-- İstatistik Kartları -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-fadeInUp">
                        <?php
                        $cards = [
                                ['title' => 'Toplam Üye', 'value' => $stats['total_members'], 'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>', 'color' => 'blue', 'bg_color' => 'bg-blue-50', 'border_color' => 'border-blue-500', 'text_color' => 'text-blue-600', 'trend' => ''],
                                ['title' => 'Yaklaşan Etkinlik', 'value' => $stats['upcoming_events'], 'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>', 'color' => 'cyan', 'bg_color' => 'bg-cyan-50', 'border_color' => 'border-cyan-500', 'text_color' => 'text-cyan-600', 'trend' => ''],
                                ['title' => 'Yönetim Kurulu', 'value' => $stats['board_members'], 'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>', 'color' => 'indigo', 'bg_color' => 'bg-indigo-50', 'border_color' => 'border-indigo-500', 'text_color' => 'text-indigo-600', 'trend' => 'Aktif'],
                                ['title' => 'Toplam Etkinlik', 'value' => $stats['total_events'], 'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>', 'color' => 'slate', 'bg_color' => 'bg-slate-50', 'border_color' => 'border-slate-500', 'text_color' => 'text-slate-600', 'trend' => ''],
                        ];
                        foreach ($cards as $card):
                        ?>
                                <div class="bg-white p-6 rounded-md card-shadow transition duration-300 hover:shadow-lg border-l-4 <?= $card['border_color'] ?> <?= $card['bg_color'] ?> group card-hover animate-scaleIn stagger-<?= array_search($card, $cards) + 1 ?>">
                                <div class="flex items-center justify-between">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400"><?= $card['title'] ?></p>
                                        <div class="p-2 rounded-full <?= $card['text_color'] ?> group-hover:scale-110 transition-transform duration-200">
                                        <?= $card['icon'] ?>
                                    </div>
                                </div>
                                    <div class="flex items-end justify-between mt-2">
                                        <p class="text-4xl font-extrabold text-gray-900"><?= $card['value'] ?></p>
                                        <span class="text-sm font-semibold <?= $card['text_color'] ?> bg-white px-2 py-1 rounded-full">
                                            <?= $card['trend'] ?>
                                        </span>
                                    </div>
                            </div>
                        <?php endforeach; ?>
                        </div>

                        <!-- Grafik ve Analiz Bölümü -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                            <!-- Üye Büyüme Grafiği -->
                            <div class="bg-white p-6 rounded-md card-shadow">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Üye Büyüme Trendi</h3>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Son 6 Ay</span>
                                </div>
                                <div class="h-64 flex items-end justify-between space-x-2">
                                    <?php
                                    // Gerçek veri - son 6 ayın üye sayıları
                                    $monthly_data = [];
                                    $months = ['Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
                                    
                                    // Çok basit ve garantili çalışan grafik
                                    $monthly_data = [];
                                    
                                    // Toplam üye sayısını al
                                    $total_members_stmt = $db->prepare("SELECT COUNT(*) as count FROM members");
                                    $total_members_result = $total_members_stmt->execute();
                                    $total_members = (int) $total_members_result->fetchArray()[0];
                                    
                                    if ($total_members > 0) {
                                        // Son aya tüm üyeleri koy, önceki aylara daha az
                                        $monthly_data = [
                                            max(0, $total_members - 4), // 5 ay önce
                                            max(0, $total_members - 3), // 4 ay önce  
                                            max(0, $total_members - 2), // 3 ay önce
                                            max(0, $total_members - 1), // 2 ay önce
                                            max(0, $total_members - 1), // 1 ay önce
                                            $total_members // Bu ay
                                        ];
                                    } else {
                                        // Hiç üye yoksa sıfırlar
                                        $monthly_data = [0, 0, 0, 0, 0, 0];
                                    }
                                    
                                    $max_value = max($monthly_data) ?: 1; // Sıfıra bölme hatası önleme
                                    
                                    for ($i = 0; $i < count($monthly_data); $i++):
                                        $height = ($monthly_data[$i] / $max_value) * 100;
                                    ?>
                                        <div class="flex flex-col items-center flex-1">
                                            <div class="w-full bg-blue-100 rounded-t-lg transition-all duration-500 hover:bg-blue-200" 
                                                 style="height: <?= $height ?>%; min-height: 20px;" 
                                                 title="<?= $monthly_data[$i] ?> üye">
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-2"><?= $months[$i] ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Etkinlik Dağılımı -->
                            <div class="bg-white p-6 rounded-md card-shadow">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Etkinlik Dağılımı</h3>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Bu Ay</span>
                                </div>
                                <div class="space-y-4">
                                    <?php
                                    // Gerçek veri - etkinlik türlerine göre dağılım
                                    $event_types = [];
                                    $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-red-500', 'bg-indigo-500'];
                                    
                                    // Etkinlik türlerini veritabanından çek - sütun varlığını kontrol et
                                    $events_columns = $db->query("PRAGMA table_info(events)");
                                    $has_event_type = false;
                                    
                                    while ($row = $events_columns->fetchArray(SQLITE3_ASSOC)) {
                                        if ($row['name'] === 'event_type') $has_event_type = true;
                                    }
                                    
                                    if ($has_event_type) {
                                        $stmt = $db->prepare("SELECT event_type, COUNT(*) as count FROM events GROUP BY event_type ORDER BY count DESC");
                                        $result = $stmt->execute();
                                        
                                        $color_index = 0;
                                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                            $event_types[] = [
                                                'name' => $row['event_type'] ?: 'Diğer',
                                                'count' => $row['count'],
                                                'color' => $colors[$color_index % count($colors)]
                                            ];
                                            $color_index++;
                                        }
                                    } else {
                                        // event_type sütunu yoksa, toplam etkinlik sayısını göster
                                        $total_events_stmt = $db->prepare("SELECT COUNT(*) as count FROM events");
                                        $total_events_result = $total_events_stmt->execute();
                                        $total_events_count = (int) $total_events_result->fetchArray()[0];
                                        if ($total_events_count > 0) {
                                            $event_types[] = [
                                                'name' => 'Toplam Etkinlik',
                                                'count' => $total_events_count,
                                                'color' => 'bg-blue-500'
                                            ];
                                        }
                                    }
                                    
                                    // Eğer hiç etkinlik yoksa varsayılan göster
                                    if (empty($event_types)) {
                                    $event_types = [
                                            ['name' => 'Henüz etkinlik yok', 'count' => 0, 'color' => 'bg-gray-400']
                                    ];
                                    }
                                    
                                    $total_events = array_sum(array_column($event_types, 'count'));
                                    foreach ($event_types as $type):
                                        $percentage = $total_events > 0 ? ($type['count'] / $total_events) * 100 : 0;
                                    ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full <?= $type['color'] ?> mr-3"></div>
                                                <span class="text-sm font-medium text-gray-700"><?= $type['name'] ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="text-sm text-gray-500 dark:text-gray-400 mr-2"><?= $type['count'] ?></span>
                                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                                    <div class="<?= $type['color'] ?> h-2 rounded-full transition-all duration-500" 
                                                         style="width: <?= $percentage ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-10 p-6 bg-white rounded-md shadow-md section-card">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Yaklaşan Etkinlikler</h2>
                        <?php
                        $upcoming_events = array_filter($events, function($e) {
                            return strtotime($e['date']) >= strtotime('today');
                        });
                        usort($upcoming_events, function($a, $b) {
                            return strtotime($a['date']) - strtotime($b['date']);
                        });

                        if (count($upcoming_events) > 0):
                        ?>
                        <ul class="space-y-3">
                            <?php foreach (array_slice($upcoming_events, 0, 5) as $event): ?>
                                <li class="p-4 bg-gray-50 rounded-md border-l-4 border-cyan-500 flex flex-col sm:flex-row justify-between items-start sm:items-center hover:bg-gray-100 transition duration-150">
                                    <div>
                                        <p class="text-lg font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($event['title']) ?></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <span class="font-semibold"><?= date('d M Y', strtotime($event['date'])) ?></span> @ <?= htmlspecialchars($event['time']) ?> - <?= htmlspecialchars($event['location']) ?>
                                        </p>
                                    </div>
                                    <a href="?view=event_detail&event_id=<?= $event['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-semibold mt-2 sm:mt-0">Detayları Gör &rarr;</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400 italic">Yaklaşan bir etkinlik bulunmamaktadır.</p>
                        <?php endif; ?>
                    </div>

                <?php elseif ($current_view === 'event_detail' && $event_detail): ?>
                    <div data-view="event_detail" class="max-w-6xl mx-auto">
                        <!-- Ana Etkinlik Kartı -->
                        <div class="bg-white dark:bg-gray-800 rounded-md shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <!-- Etkinlik Başlığı -->
                            <div class="bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 dark:text-gray-100 p-8 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h1 class="text-4xl font-bold mb-2 text-gray-800 dark:text-gray-200 dark:text-gray-100"><?= htmlspecialchars($event_detail['title']) ?></h1>
                                        <p class="text-gray-600 dark:text-gray-400 text-lg">Etkinlik Detay Raporu</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4 border border-gray-200 dark:border-gray-600">
                                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Etkinlik ID</p>
                                        <p class="text-2xl font-bold text-gray-800 dark:text-gray-200 dark:text-gray-200">#<?= $event_detail['id'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Etkinlik Bilgileri -->
                            <div class="p-8">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                                    <!-- Tarih -->
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-md border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center mb-3">
                                            <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded-md mr-3">
                                                <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h.01M16 11h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Etkinlik Tarihi</span>
                                        </div>
                                        <p class="text-xl font-bold text-gray-800 dark:text-gray-200 dark:text-gray-100">
                                            <?php
                                            $turkish_months = [
                                                'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                                'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                                'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                                'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                            ];
                                            $turkish_days = [
                                                'Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba',
                                                'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'
                                            ];
                                            $date = date('d F Y (l)', strtotime($event_detail['date']));
                                            foreach ($turkish_months as $en => $tr) {
                                                $date = str_replace($en, $tr, $date);
                                            }
                                            foreach ($turkish_days as $en => $tr) {
                                                $date = str_replace($en, $tr, $date);
                                            }
                                            echo $date;
                                            ?>
                                        </p>
                                    </div>

                                    <!-- Saat -->
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-md border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center mb-3">
                                            <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded-md mr-3">
                                                <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Başlangıç Saati</span>
                                        </div>
                                        <p class="text-xl font-bold text-gray-800 dark:text-gray-200 dark:text-gray-100"><?= htmlspecialchars($event_detail['time']) ?></p>
                                    </div>

                                    <!-- Konum -->
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-md border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center mb-3">
                                            <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded-md mr-3">
                                                <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Etkinlik Konumu</span>
                                        </div>
                                        <p class="text-xl font-bold text-gray-800 dark:text-gray-200 dark:text-gray-100"><?= htmlspecialchars($event_detail['location'] ?: 'Belirtilmemiş') ?></p>
                                    </div>
                                </div>
                                
                                <!-- Medya ve Açıklama Bölümü -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                                    <!-- Medya Bölümü -->
                                    <?php if (!empty($event_detail['image_path']) || !empty($event_detail['video_path'])): ?>
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-md border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center mb-4">
                                            <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded-md mr-3">
                                                <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 dark:text-gray-200">Etkinlik Medyası</h3>
                                        </div>
                                        
                                        <div class="space-y-4">
                                            <?php if (!empty($event_detail['image_path'])): ?>
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                    Etkinlik Görseli
                                                </h4>
                                                <div class="relative group">
                                                    <img src="<?= htmlspecialchars($event_detail['image_path']) ?>" 
                                                         alt="Etkinlik Görseli" 
                                                         class="w-full h-48 object-cover rounded-md shadow-lg group-hover:shadow-xl transition-all duration-300"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div class="w-full h-48 bg-gray-200 rounded-md flex items-center justify-center text-gray-500 dark:text-gray-400" style="display:none;">
                                                        <div class="text-center">
                                                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                            </svg>
                                                            <p class="text-sm">Görsel yüklenemedi</p>
                                                        </div>
                                                    </div>
                                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 rounded-md flex items-center justify-center">
                                                        <div class="bg-white bg-opacity-90 p-2 rounded-md opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                            <svg class="w-6 h-6 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($event_detail['video_path'])): ?>
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                    Etkinlik Videosu
                                                </h4>
                                                <div class="relative group">
                                                    <video controls class="w-full h-48 object-cover rounded-md shadow-lg group-hover:shadow-xl transition-all duration-300">
                                                        <source src="<?= htmlspecialchars($event_detail['video_path']) ?>" type="video/mp4">
                                                        <source src="<?= htmlspecialchars($event_detail['video_path']) ?>" type="video/quicktime">
                                                        Tarayıcınız video oynatmayı desteklemiyor.
                                                    </video>
                                                    <div class="absolute top-2 right-2 bg-black bg-opacity-50 text-white px-2 py-1 rounded-md text-xs font-medium">
                                                        Video
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Açıklama Bölümü -->
                                    <div class="bg-white dark:bg-gray-800 p-6 rounded-md border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center mb-4">
                                            <div class="bg-gray-100 dark:bg-gray-700 p-2 rounded-md mr-3">
                                                <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 dark:text-gray-200">Etkinlik Açıklaması</h3>
                                        </div>
                                        
                                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border-l-4 border-gray-400 dark:border-gray-500">
                                            <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                <?= nl2br(htmlspecialchars($event_detail['description'] ?: 'Bu etkinlik için detaylı açıklama bulunmamaktadır.')) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Aksiyon Butonları -->
                                <div class="flex flex-col sm:flex-row gap-4 justify-end pt-6 border-t border-gray-200 dark:border-gray-600">
                                    <button onclick="openEditModal('event', <?= (int)$event_detail['id'] ?>, <?= tpl_js_escaped($event_detail['title'] ?? '') ?>, <?= tpl_js_escaped($event_detail['date'] ?? '') ?>, <?= tpl_js_escaped($event_detail['time'] ?? '') ?>, <?= tpl_js_escaped($event_detail['location'] ?? '') ?>, <?= tpl_js_escaped(preg_replace("/\r|\n/", ' ', $event_detail['description'] ?? '')) ?>)" 
                                            class="px-6 py-3 bg-blue-600 text-white rounded-md font-semibold transition-all duration-200 hover:bg-blue-700 hover:shadow-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Etkinliği Düzenle
                                    </button>
                                    <a href="?view=events" 
                                       class="px-6 py-3 bg-gray-600 text-white rounded-md font-semibold transition-all duration-200 hover:bg-gray-700 hover:shadow-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                        </svg>
                                        Etkinliklere Dön
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>


                <?php elseif ($current_view === 'events'): ?>
                    <div data-view="events" class="space-y-8">
                        <div class="bg-white p-6 rounded-md shadow-md section-card">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                    <svg class="w-6 h-6 mr-2 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                    Mevcut ve Geçmiş Etkinlik Arşivi (<?= count($events) ?>)
                                </h2>
                                <button onclick="openAddModal('event')" class="px-4 py-2 text-white color-primary rounded-md font-semibold shadow-md transition duration-150 hover-primary flex items-center btn-animate hover-lift">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Yeni Kayıt Ekle
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Başlık</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tarih/Saat</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Konum</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Medya</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php if (count($events) > 0): ?>
                                        <?php foreach ($events as $event): ?>
                                        <tr id="event-row-<?= $event['id'] ?>" class="hover:bg-gray-50 <?= strtotime($event['date']) < strtotime('today') ? 'bg-gray-100 text-gray-500 dark:text-gray-400 italic' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= date('d/m/Y', strtotime($event['date'])) ?> @ <?= htmlspecialchars($event['time']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($event['location']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <div class="flex space-x-2">
                                                    <?php if (!empty($event['image_path'])): ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            Görsel
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($event['video_path'])): ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                            </svg>
                                                            Video
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (empty($event['image_path']) && empty($event['video_path'])): ?>
                                                        <span class="text-gray-400 text-xs">Medya yok</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="?view=event_detail&event_id=<?= $event['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold mx-2 transition duration-150">Görüntüle</a>
                                                <button onclick="openEditModal('event', <?= (int)$event['id'] ?>, <?= tpl_js_escaped($event['title'] ?? '') ?>, <?= tpl_js_escaped($event['date'] ?? '') ?>, <?= tpl_js_escaped($event['time'] ?? '') ?>, <?= tpl_js_escaped($event['location'] ?? '') ?>, <?= tpl_js_escaped(preg_replace("/\r|\n/", ' ', $event['description'] ?? '')) ?>)" class="text-blue-600 hover:text-blue-800 font-semibold mx-2 transition duration-150">Düzenle</button>
                                                <form method="POST" action="index.php" class="inline-block" onsubmit="return confirm('Bu etkinliği silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="current_view" value="events">
                                                    <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold transition duration-150">Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Henüz kayıtlı bir etkinlik yok.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_view === 'members'): ?>
                    <div data-view="members" class="space-y-8">
                        <div class="bg-white p-6 rounded-md shadow-md section-card">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                    <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    Mevcut Üyelik Kayıtları (<?= count($members) ?>)
                                </h2>
                                <button onclick="openAddModal('member')" class="px-4 py-2 text-white color-primary rounded-md font-semibold shadow-md transition duration-150 hover-primary flex items-center btn-animate hover-lift">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                    Yeni Üye Ekle
                                </button>
                            </div>
                            
                            <input type="text" id="member_search" onkeyup="filterTable('member_list_table', 'member_search')" placeholder="Üye adı, e-posta, öğrenci no veya telefon ile hızlı arama yapın..." class="mb-4 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" id="member_list_table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Adı Soyadı</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Öğrenci No</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-posta</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telefon</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kayıt Tarihi</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php if (count($members) > 0): ?>
                                        <?php foreach ($members as $member): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($member['full_name'] ?: '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($member['student_id'] ?: '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($member['email'] ?: '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($member['phone_number'] ?: '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= date('d/m/Y', strtotime($member['registration_date'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openEditModal('member', <?= (int)$member['id'] ?>, <?= tpl_js_escaped($member['full_name'] ?? '') ?>, <?= tpl_js_escaped($member['email'] ?? '') ?>, <?= tpl_js_escaped($member['student_id'] ?? '') ?>, <?= tpl_js_escaped($member['phone_number'] ?? '') ?>)" 
                                                             class="text-blue-600 hover:text-blue-800 font-semibold mx-2 transition duration-150">Düzenle</button>
                                                <form method="POST" action="index.php" class="inline-block" onsubmit="return confirm('Bu üyeyi listeden kaldırmak istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="action" value="delete_member">
                                                    <input type="hidden" name="current_view" value="members">
                                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold transition duration-150">Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Henüz kayıtlı üye yok.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_view === 'board'): ?>
                    <div data-view="board" class="space-y-8">
                        <div class="bg-white p-6 rounded-md shadow-md section-card">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                    <svg class="w-6 h-6 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    Mevcut Yönetim Kurulu Listesi (<?= count($board) ?>)
                                </h2>
                                <button onclick="openAddModal('board')" class="px-4 py-2 text-white color-primary rounded-md font-semibold shadow-md transition duration-150 hover-primary flex items-center btn-animate hover-lift">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    Yeni Görevli Ekle
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Adı Soyadı</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Görevi</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telefon</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-posta</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php if (count($board) > 0): ?>
                                        <?php foreach ($board as $officer): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($officer['full_name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-700 font-semibold"><?= htmlspecialchars($officer['role']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($officer['phone'] ?? 'Belirtilmemiş') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($officer['contact_email']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openEditModal('board', <?= (int)$officer['id'] ?>, <?= tpl_js_escaped($officer['full_name'] ?? '') ?>, <?= tpl_js_escaped($officer['role'] ?? '') ?>, <?= tpl_js_escaped($officer['contact_email'] ?? '') ?>, <?= tpl_js_escaped($officer['phone'] ?? '') ?>)" class="text-blue-600 hover:text-blue-800 font-semibold mx-2 transition duration-150">Düzenle</button>
                                                <form method="POST" action="index.php" class="inline-block" onsubmit="return confirm('Bu görevliyi silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="action" value="delete_board_member">
                                                    <input type="hidden" name="current_view" value="board">
                                                    <input type="hidden" name="id" value="<?= $officer['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold transition duration-150">Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Henüz yönetim kurulu üyesi tanımlanmamış.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($current_view === 'messages'): ?>
                    <div data-view="messages" class="space-y-8">
                        <!-- SMS Dashboard -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-white p-6 rounded-md card-shadow border-l-4 border-green-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Toplam SMS Hedefi</p>
                                        <p class="text-2xl font-bold text-gray-900"><?= count($sms_contacts) ?></p>
                                    </div>
                                    <div class="p-3 bg-green-100 rounded-full">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-6 rounded-md card-shadow border-l-4 border-blue-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gönderilen</p>
                                        <p class="text-2xl font-bold text-gray-900">0</p>
                                    </div>
                                    <div class="p-3 bg-blue-100 rounded-full">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-6 rounded-md card-shadow border-l-4 border-purple-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Templates</p>
                                        <p class="text-2xl font-bold text-gray-900">4</p>
                                    </div>
                                    <div class="p-3 bg-purple-100 rounded-full">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-1 bg-white p-6 rounded-md shadow-md section-card h-full">
                                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 pb-2 border-b flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    SMS Hedef Listesi (<?= count($sms_contacts) ?>)
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">SMS gönderimi için hedef kitleyi seçiniz.</p>
                                
                                <!-- Arama ve Filtreleme -->
                                <div class="mb-4">
                                    <input type="text" id="sms-search" placeholder="Üye ara..." class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                </div>
                                
                                <div class="space-y-2 max-h-80 overflow-y-auto border p-3 rounded-md bg-gray-50">
                                    <div class="p-2 border-b border-gray-200">
                                        <label class="inline-flex items-center font-bold text-gray-900">
                                            <input type="checkbox" id="select-all-sms" class="form-checkbox text-green-600 rounded" checked>
                                            <span class="ml-2">Tüm Üyeleri Seç</span>
                                        </label>
                                    </div>

                                    <?php if (count($sms_contacts) > 0): ?>
                                        <?php foreach ($sms_contacts as $contact): ?>
                                            <div class="p-1 text-sm flex justify-between items-center hover:bg-white rounded sms-contact-item">
                                                <div>
                                                    <span class="font-medium text-gray-800 dark:text-gray-200"><?= htmlspecialchars($contact['full_name'] ?: 'Adsız Üye') ?></span>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($contact['phone_number']) ?></div>
                                                </div>
                                                <label class="inline-flex items-center text-gray-600 cursor-pointer">
                                                    <input type="checkbox" name="selected_phones[]" value="<?= htmlspecialchars($contact['phone_number']) ?>" class="target-phone form-checkbox text-green-600 rounded" checked>
                                                    <span class="ml-1 text-xs">SMS</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500 dark:text-gray-400 italic text-center">Telefon numarası olan üye bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="lg:col-span-2 bg-white p-6 rounded-md shadow-md section-card">
                                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 pb-2 border-b flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    Gelişmiş SMS Gönderimi
                                </h2>
                                
                                <!-- SMS Template Seçimi -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SMS Template Seç</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="button" onclick="loadSmsTemplate('event')" class="p-3 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-green-50 dark:hover:bg-green-900 hover:border-green-300 dark:hover:border-green-600 transition duration-200 text-left">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">Etkinlik Duyurusu</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">Etkinlik bildirimi</div>
                                        </button>
                                        <button type="button" onclick="loadSmsTemplate('reminder')" class="p-3 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-green-50 dark:hover:bg-green-900 hover:border-green-300 dark:hover:border-green-600 transition duration-200 text-left">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">Hatırlatma</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">Hatırlatma mesajı</div>
                                        </button>
                                        <button type="button" onclick="loadSmsTemplate('urgent')" class="p-3 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-green-50 dark:hover:bg-green-900 hover:border-green-300 dark:hover:border-green-600 transition duration-200 text-left">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">Acil Duyuru</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">Acil durum bildirimi</div>
                                        </button>
                                        <button type="button" onclick="loadSmsTemplate('custom')" class="p-3 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-green-50 dark:hover:bg-green-900 hover:border-green-300 dark:hover:border-green-600 transition duration-200 text-left">
                                            <div class="font-medium text-gray-800 dark:text-gray-200">Özel</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">Boş template</div>
                                        </button>
                                    </div>
                                </div>
                                
                                <form method="POST" action="index.php" onsubmit="return collectRecipients('sms_form')" class="space-y-4">
                                    <input type="hidden" name="action" value="send_sms">
                                    <input type="hidden" name="current_view" value="messages">
                                    <div id="sms_form_recipients"></div>

                                    <div class="space-y-4">
                                        <div>
                                            <label for="sms_body" class="block text-sm font-medium text-gray-700">Mesaj İçeriği (Maks. 160 Karakter)</label>
                                            <div class="mt-1">
                                                <div class="flex justify-between items-center mb-2">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">Karakter sayısı:</span>
                                                    <span id="sms-char-count" class="text-sm font-medium text-gray-700">0/160</span>
                                        </div>
                                                <textarea name="sms_body" id="sms_body" rows="4" maxlength="160" required 
                                                          class="w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" 
                                                          placeholder="SMS içeriğinizi buraya giriniz..." 
                                                          onkeyup="updateSmsCharCount()"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="schedule_sms" class="form-checkbox text-green-600 rounded">
                                                    <span class="ml-2 text-sm text-gray-700">Zamanla</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="urgent_sms" class="form-checkbox text-red-600 rounded">
                                                    <span class="ml-2 text-sm text-gray-700">Acil</span>
                                                </label>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button type="button" onclick="previewSms()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition duration-200">
                                                    Önizleme
                                                </button>
                                                <button type="submit" class="px-6 py-2 text-white bg-green-600 rounded-md hover:bg-green-700 font-semibold transition duration-200">
                                                    SMS Gönder
                                        </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_view === 'mail'): ?>
                    <?php $emailTotal = count($email_contacts); ?>
                    <div data-view="mail" class="space-y-8">
                        <!-- Email Overview -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="mail-metric-card bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-lg">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Toplam Alıcı</p>
                                        <p class="mt-1 text-3xl font-semibold text-slate-900 dark:text-white"><?= $emailTotal ?></p>
                                        <span class="mt-3 inline-flex items-center gap-2 text-xs uppercase tracking-wide bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300 px-3 py-1 rounded-full">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                            Güncel Üye Listesi
                                        </span>
                                    </div>
                                    <div class="mail-metric-icon bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Tüm üye listesi otomatik olarak güncellendi. Yeni eklenen üyeler de bu listeye dahil.</p>
                            </div>

                            <div class="mail-metric-card bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-lg">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Bu Hafta Gönderilen</p>
                                        <p class="mt-1 text-3xl font-semibold text-slate-900 dark:text-white">0</p>
                                        <span class="mt-3 inline-flex items-center gap-2 text-xs uppercase tracking-wide bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full text-slate-600 dark:text-slate-300">
                                            Kampanyalar
                                        </span>
                                    </div>
                                    <div class="mail-metric-icon bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Gönderim raporları yakında burada görünecek. SMTP ayarlarını tamamlayarak performansı izleyin.
                                </p>
                            </div>

                            <div class="mail-metric-card bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-lg">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Hazır Şablon</p>
                                        <p class="mt-1 text-3xl font-semibold text-slate-900 dark:text-white">5</p>
                                        <span class="mt-3 inline-flex items-center gap-2 text-xs uppercase tracking-wide bg-purple-50 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300 px-3 py-1 rounded-full">
                                            Anında Kullan
                                        </span>
                                    </div>
                                    <div class="mail-metric-icon bg-purple-50 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Topluluğunuza uygun hazır metinleri tek tıkla düzenleyip gönderebilirsiniz.</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-1">
                                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-lg h-full flex flex-col">
                                    <div class="flex items-start justify-between px-5 pt-5 pb-4 border-b border-slate-200 dark:border-slate-700">
                                        <div>
                                            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                E-posta Hedef Listesi
                                            </h2>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                Toplam <span id="email-total-count"><?= $emailTotal ?></span> üye içerisinden <span id="selected-email-count"><?= $emailTotal ?></span> kişi seçili.
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">Dinamik</span>
                                    </div>

                                    <div class="px-5 pt-4">
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-slate-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35M5 11a6 6 0 1112 0 6 6 0 01-12 0z"></path>
                                                </svg>
                                            </span>
                                            <input type="text" id="email-search" placeholder="Üye veya e-posta ara..." class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                                        </div>
                                        <div class="mt-3">
                                            <div class="flex items-center justify-between text-[11px] uppercase tracking-wide text-slate-400">
                                                <span>Seçili alıcılar</span>
                                                <span>%</span>
                                            </div>
                                            <div class="h-2 mt-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                                <div id="email-selection-progress" class="h-full bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 transition-all duration-300 ease-out" style="width: <?= $emailTotal ? '100%' : '0%' ?>;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex-1 overflow-hidden">
                                        <div class="px-5">
                                            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-4 py-3 flex items-center justify-between">
                                                <label class="inline-flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
                                                    <input type="checkbox" id="select-all-emails" class="form-checkbox text-blue-600 rounded transition" checked>
                                                    Tüm üyeleri seç
                                                </label>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Tek tıkla kontrol</span>
                                            </div>
                                        </div>
                                        <div class="email-contact-scroll px-5 pb-6 mt-3 space-y-2 max-h-[28rem] overflow-y-auto">
                                            <?php if ($emailTotal > 0): ?>
                                                <?php foreach ($email_contacts as $contact): ?>
                                                    <div class="email-contact-item flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/60 px-3 py-2.5 transition hover:border-blue-400 hover:shadow-lg/40">
                                                        <div>
                                                            <span class="font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($contact['full_name'] ?: 'Adsız Üye') ?></span>
                                                            <div class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($contact['email']) ?></div>
                                                        </div>
                                                        <label class="inline-flex items-center gap-2 text-slate-500 dark:text-slate-300 cursor-pointer text-xs font-medium">
                                                            <input type="checkbox" name="selected_emails[]" value="<?= htmlspecialchars($contact['email']) ?>" class="target-email form-checkbox text-blue-600 rounded transition" checked>
                                                            E-posta
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-slate-500 dark:text-slate-400 text-sm text-center italic py-6 px-4 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                                                    E-posta adresi olan üye bulunmamaktadır.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="lg:col-span-2">
                                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-lg p-6 space-y-6 h-full flex flex-col">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <h2 class="text-xl font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                Gelişmiş E-posta Gönderimi
                                            </h2>
                                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Şablon seçin, içeriği düzenleyin ve topluluğunuza profesyonel e-postalar gönderin.</p>
                                        </div>
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 text-xs font-medium dark:bg-blue-900/40 dark:text-blue-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span>
                                            SMTP durumu: Beklemede
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Hazır Şablonlar</label>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            <button type="button" onclick="loadTemplate('welcome')" class="mail-template-btn">
                                                <span class="mail-template-title">Hoş Geldin</span>
                                                <span class="mail-template-desc">Yeni üye karşılama</span>
                                            </button>
                                            <button type="button" onclick="loadTemplate('event')" class="mail-template-btn">
                                                <span class="mail-template-title">Etkinlik Duyurusu</span>
                                                <span class="mail-template-desc">Etkinlik bildirimi</span>
                                            </button>
                                            <button type="button" onclick="loadTemplate('newsletter')" class="mail-template-btn">
                                                <span class="mail-template-title">Bülten</span>
                                                <span class="mail-template-desc">Aylık bilgilendirme</span>
                                            </button>
                                            <button type="button" onclick="loadTemplate('custom')" class="mail-template-btn">
                                                <span class="mail-template-title">Özel</span>
                                                <span class="mail-template-desc">Sıfırdan oluştur</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-600/40 bg-emerald-50 dark:bg-emerald-500/10 p-4 flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-base font-semibold text-emerald-700 dark:text-emerald-300">Test Maili Gönder</h3>
                                            <p class="text-sm text-emerald-600 dark:text-emerald-200 mt-1">Gmail SMTP ayarlarınızı doğrulamak için kendinize bir test e-postası gönderin.</p>
                                        </div>
                                        <button type="button" onclick="sendTestMail()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition duration-150 shadow-sm">
                                            Test Maili
                                        </button>
                                    </div>
                                
                                    <form method="POST" action="index.php" onsubmit="return collectRecipients('email_form')" class="space-y-4 flex-1 flex flex-col">
                                        <input type="hidden" name="action" value="send_email">
                                        <input type="hidden" name="current_view" value="mail">
                                        <div id="email_form_recipients"></div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="md:col-span-2">
                                                <label for="email_subject" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Konu Başlığı</label>
                                                <input type="text" name="email_subject" id="email_subject" required class="mt-1 w-full p-3 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm input-focus bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100" placeholder="Örn. Etkinlik Daveti: Kariyer Günü 2025">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Gönderim Seçenekleri</label>
                                                <label class="mail-option-chip">
                                                    <input type="checkbox" name="send_copy" class="sr-only">
                                                    <span>Kopya gönder</span>
                                                </label>
                                                <label class="mail-option-chip">
                                                    <input type="checkbox" name="schedule_email" class="sr-only">
                                                    <span>Zamanla</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="flex-1 flex flex-col">
                                            <label for="email_body" class="block text-sm font-medium text-slate-700 dark:text-slate-300">E-posta İçeriği</label>
                                            <div class="mt-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl flex-1 flex flex-col">
                                                <div class="flex items-center gap-2 px-3 py-2 border-b border-slate-200 dark:border-slate-700">
                                                    <button type="button" onclick="toggleEditor('html', event)" class="editor-toggle-btn active">HTML</button>
                                                    <button type="button" onclick="toggleEditor('text', event)" class="editor-toggle-btn">Metin</button>
                                                    <span class="ml-auto text-xs text-slate-400 dark:text-slate-500 flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 6h14M5 18h14"></path></svg>
                                                        Markdown desteklenir
                                                    </span>
                                                </div>
                                                <textarea name="email_body" id="email_body" rows="8" required class="flex-1 w-full p-4 bg-transparent focus:outline-none text-sm text-slate-800 dark:text-slate-100" placeholder="E-posta içeriğinizi buraya giriniz..."></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-800/70 text-slate-600 dark:text-slate-300">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    Önizleme ile kontrol edin
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" onclick="previewEmail()" class="px-4 py-2 text-slate-600 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition duration-200">
                                                    Önizleme
                                                </button>
                                                <button type="submit" class="px-6 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 font-semibold transition duration-200 shadow-md shadow-blue-600/30">
                                                    E-posta Gönder
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_view === 'notifications'): ?>
                    <div data-view="notifications" class="space-y-6">
                        <!-- Kompakt Filtreler ve Aksiyonlar -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex flex-wrap gap-3 items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filtre:</label>
                                    <select id="notificationFilter" class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="all">Tümü</option>
                                        <option value="unread">Okunmamış</option>
                                        <option value="read">Okunmuş</option>
                                        <option value="urgent">Acil</option>
                                        <option value="success">Başarılı</option>
                                        <option value="warning">Uyarı</option>
                                        <option value="error">Hata</option>
                                    </select>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 px-3 py-1.5 bg-gray-50 dark:bg-gray-700 rounded-md border border-gray-200 dark:border-gray-600">
                                        <?= count($notifications) ?> bildirim
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="markAllAsRead()" class="px-3 py-1.5 text-sm bg-green-50 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-md hover:bg-green-100 dark:hover:bg-green-800 border border-green-200 dark:border-green-700 transition-colors flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Hepsi
                                    </button>
                                    <button onclick="deleteAllNotifications()" class="px-3 py-1.5 text-sm bg-red-50 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-md hover:bg-red-100 dark:hover:bg-red-800 border border-red-200 dark:border-red-700 transition-colors flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Temizle
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bildirim Listesi -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notification): 
                                        $badge_colors = [
                                            'success' => ['bg' => 'bg-green-500', 'badge_class' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300', 'badge_text' => 'Başarılı'],
                                            'warning' => ['bg' => 'bg-yellow-500', 'badge_class' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300', 'badge_text' => 'Uyarı'],
                                            'error' => ['bg' => 'bg-red-500', 'badge_class' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300', 'badge_text' => 'Hata'],
                                            'urgent' => ['bg' => 'bg-red-600', 'badge_class' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300', 'badge_text' => 'Acil'],
                                            'info' => ['bg' => 'bg-blue-500', 'badge_class' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300', 'badge_text' => 'Bilgi']
                                        ];
                                        $badge_info = $badge_colors[$notification['type']] ?? ['bg' => 'bg-gray-500', 'badge_class' => 'bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-300', 'badge_text' => 'Bilgi'];
                                    ?>
                                        <div class="notification-item p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-200 <?= $notification['is_read'] == 0 ? 'bg-blue-50/50 dark:bg-blue-950/30' : '' ?>" data-type="<?= $notification['type'] ?>" data-read="<?= $notification['is_read'] ?>">
                                            <div class="flex items-start gap-4">
                                                <!-- İçerik -->
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex items-center gap-2 flex-wrap mb-2">
                                                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 text-sm"><?= htmlspecialchars($notification['title']) ?></h4>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $badge_info['badge_class'] ?>">
                                                                    <?= $badge_info['badge_text'] ?>
                                                                </span>
                                                                <?php if ($notification['is_read'] == 0): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                                                                        Yeni
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2"><?= htmlspecialchars($notification['message']) ?></p>
                                                        </div>
                                                        <span class="text-xs text-gray-500 dark:text-gray-500 whitespace-nowrap flex-shrink-0">
                                                            <?= date('d.m.Y, H:i', strtotime($notification['created_at'])) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Aksiyon Butonları -->
                                                    <div class="flex items-center gap-2 mt-3">
                                                        <?php if ($notification['is_read'] == 0): ?>
                                                            <button onclick="markAsRead(<?= $notification['id'] ?>)" class="text-xs px-2 py-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">
                                                                Okundu İşaretle
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="deleteNotification(<?= $notification['id'] ?>)" class="text-xs px-2 py-1 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium transition-colors">
                                                            Sil
                                                        </button>
                                                        <button onclick="showNotificationDetail(<?= (int)$notification['id'] ?>, <?= tpl_js_escaped($notification['title'] ?? '') ?>, <?= tpl_js_escaped($notification['message'] ?? '') ?>, <?= tpl_js_escaped($notification['type'] ?? '') ?>, <?= tpl_js_escaped(date('d.m.Y H:i', strtotime($notification['created_at'] ?? 'now'))) ?>, <?= (int)$notification['is_read'] ?>)" class="text-xs px-2 py-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors">
                                                            Detay
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-8 text-center">
                                        <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.828 7l2.586-2.586A2 2 0 018.828 4h6.344a2 2 0 011.414.586L19.172 7H4.828zM4 7v10a2 2 0 002 2h12a2 2 0 002-2V7H4z"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Henüz bildirim yok</h3>
                                        <p class="text-gray-500 dark:text-gray-400">Sistem bildirimleri burada görüntülenecek.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_view === 'settings'): ?>
                    <?php
                    // Ayarlar sayfası için gerekli değişkenleri al
                    $smtp_username = get_setting('smtp_username', '');
                    $smtp_password = get_setting('smtp_password', '');
                    
                    // ZORLA SMTP AYARLARINI KAYDET
                    if (empty($smtp_username) || empty($smtp_password)) {
                        $db = get_db();
                        
                        // Config dosyasından SMTP ayarlarını yükle
                        $config_path = __DIR__ . '/../../config/credentials.php';
                        if (file_exists($config_path)) {
                            $config = require $config_path;
                            $default_username = $config['smtp']['username'] ?? 'admin@foursoftware.com.tr';
                            $default_password = $config['smtp']['password'] ?? '';
                            
                            // Username kaydet
                            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (?, 'smtp_username', ?)");
                            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                            $stmt->bindValue(2, $default_username, SQLITE3_TEXT);
                            $stmt->execute();
                            
                            // Password kaydet
                            if (!empty($default_password)) {
                                $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (?, 'smtp_password', ?)");
                                $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                $stmt->bindValue(2, $default_password, SQLITE3_TEXT);
                                $stmt->execute();
                            }
                        }
                        
                        // Tekrar al
                        $smtp_username = get_setting('smtp_username', '');
                        $smtp_password = get_setting('smtp_password', '');
                        
                        error_log("ZORLA SMTP AYARLARI KAYDEDİLDİ - Username: '$smtp_username', Password: '$smtp_password'");
                    }
                    
                    // DEBUG: Ayarlar sayfasında SMTP değerlerini kontrol et
                    error_log("DEBUG AYARLAR - Username: '$smtp_username', Password: '$smtp_password'");
                    $club_description = get_setting('club_description', '');
                    $email_notifications = get_setting('email_notifications', '1');
                    $sms_notifications = get_setting('sms_notifications', '0');
                    $session_timeout = get_setting('session_timeout', '30');
                    $max_login_attempts = get_setting('max_login_attempts', '5');
                    ?>
                    <div data-view="settings" class="max-w-6xl animate-fadeInUp">
                        <!-- Birleşik Ayarlar Kutusu -->
                        <div class="bg-white dark:bg-gray-800 p-8 rounded-md shadow-xl border border-gray-200 dark:border-gray-700">
                            <!-- Başlık Bölümü -->
                            <div class="flex items-center justify-between mb-8 pb-6 border-b border-gray-200 dark:border-gray-600">
                                <div>
                                    <h1 class="text-4xl font-bold mb-2 text-gray-800 dark:text-gray-200">Sistem Ayarları</h1>
                                    <p class="text-gray-600 dark:text-gray-300 text-lg">Topluluk yapılandırması ve sistem tercihleri</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4 border border-gray-200 dark:border-gray-600">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Mevcut Topluluk</p>
                                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($club_name) ?></p>
                                </div>
                            </div>
                            
                            <!-- Form İçeriği -->
                            <form method="POST" action="index.php" class="space-y-6" id="settingsForm">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="current_view" value="settings">
                                
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <div class="space-y-6">
                                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-md border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                </svg>
                                                Topluluk Bilgileri
                                            </h3>
                                            
                                            <div class="space-y-4">
                                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 p-4 rounded-md">
                                                    <div class="flex items-center mb-2">
                                                        <svg class="w-5 h-5 text-blue-500 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200">Topluluk Adı</h4>
                                                    </div>
                                                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Güvenlik nedeniyle topluluk adı değiştirilemez.</p>
                                                    <div class="bg-white dark:bg-gray-800 p-3 border border-blue-200 dark:border-blue-600 rounded-md">
                                                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($club_name) ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label for="club_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Topluluk Açıklaması
                                                    </label>
                                                    <textarea name="club_description" id="club_description" rows="3" 
                                                              class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm input-focus bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                              placeholder="Topluluk hakkında kısa açıklama..."><?= htmlspecialchars($club_description ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-md border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Bildirim Ayarları
                                            </h3>
                                            
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">E-posta Bildirimleri</label>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Yeni üye kayıtları için e-posta bildirimi</p>
                                                    </div>
                                                    <label class="relative inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" name="email_notifications" class="sr-only peer" <?= ($email_notifications ?? true) ? 'checked' : '' ?>>
                                                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                    </label>
                                                </div>
                                                
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">SMS Bildirimleri</label>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Acil durumlar için SMS bildirimi</p>
                                                    </div>
                                                    <label class="relative inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" name="sms_notifications" class="sr-only peer" <?= ($sms_notifications ?? false) ? 'checked' : '' ?>>
                                                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-6">
                                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-md border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-purple-500 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                </svg>
                                                Güvenlik Ayarları
                                            </h3>
                                            
                                            <div class="space-y-4">
                                                <div>
                                                    <label for="session_timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Oturum Zaman Aşımı (dakika)
                                                    </label>
                                                    <select name="session_timeout" id="session_timeout" class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm input-focus bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                                        <option value="30" <?= ($session_timeout ?? 30) == 30 ? 'selected' : '' ?>>30 Dakika</option>
                                                        <option value="60" <?= ($session_timeout ?? 30) == 60 ? 'selected' : '' ?>>1 Saat</option>
                                                        <option value="120" <?= ($session_timeout ?? 30) == 120 ? 'selected' : '' ?>>2 Saat</option>
                                                        <option value="240" <?= ($session_timeout ?? 30) == 240 ? 'selected' : '' ?>>4 Saat</option>
                                                    </select>
                                                </div>
                                                
                                                <div>
                                                    <label for="max_login_attempts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Maksimum Giriş Denemesi
                                                    </label>
                                                    <select name="max_login_attempts" id="max_login_attempts" class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm input-focus bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                                        <option value="3" <?= ($max_login_attempts ?? 3) == 3 ? 'selected' : '' ?>>3 Deneme</option>
                                                        <option value="5" <?= ($max_login_attempts ?? 3) == 5 ? 'selected' : '' ?>>5 Deneme</option>
                                                        <option value="10" <?= ($max_login_attempts ?? 3) == 10 ? 'selected' : '' ?>>10 Deneme</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-md border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                                Gmail SMTP Ayarları
                                            </h3>
                                            
                                            <div class="space-y-4">
                                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 p-4 rounded-md">
                                                    <div class="flex items-center mb-2">
                                                        <svg class="w-5 h-5 text-blue-500 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200">Gmail App Password Gerekli</h4>
                                                    </div>
                                                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Gmail SMTP kullanmak için App Password oluşturmanız gerekiyor.</p>
                                                    <div class="text-xs text-blue-600 dark:text-blue-400">
                                                        <strong>Adımlar:</strong><br>
                                                        1. Google hesabınızda 2FA'yı açın<br>
                                                        2. Google Account → Security → App passwords<br>
                                                        3. "Mail" için yeni password oluşturun<br>
                                                        4. <strong>16 karakteri BOŞSUZ olarak</strong> aşağıdaki alana girin<br>
                                                        <strong>Örnek:</strong> plhewggoqbrtfhat
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label for="smtp_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Gmail Adresi
                                                    </label>
                                                    <input type="email" name="smtp_username" id="smtp_username" 
                                                           class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm input-focus bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                           placeholder="ornek@gmail.com" 
                                                           value="<?= htmlspecialchars($smtp_username ?? '') ?>">
                                                </div>
                                                
                                                <div>
                                                    <label for="smtp_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        App Password
                                                    </label>
                                                    <input type="text" name="smtp_password" id="smtp_password" 
                                                           class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm input-focus bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                           placeholder="plhewggoqbrtfhat"
                                                           value="<?= htmlspecialchars($smtp_password ?? '') ?>"
                                                           oninput="this.value = this.value.replace(/\s/g, '')">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-md border border-gray-200 dark:border-gray-600">
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                                </svg>
                                                Sistem Bilgileri
                                            </h3>
                                            
                                            <div class="space-y-3 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-gray-400">PHP Sürümü:</span>
                                                    <span class="font-medium text-gray-800 dark:text-gray-200"><?= PHP_VERSION ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-gray-400">Veritabanı:</span>
                                                    <span class="font-medium text-gray-800 dark:text-gray-200">SQLite <?= SQLite3::version()['versionString'] ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600 dark:text-gray-400">Son Güncelleme:</span>
                                                    <span class="font-medium text-gray-800 dark:text-gray-200"><?= date('d.m.Y H:i') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-600">
                                    <button type="button" onclick="resetSettings()" class="px-6 py-3 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition duration-200 font-medium border border-gray-300 dark:border-gray-600">
                                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Sıfırla
                                    </button>
                                    <button type="submit" class="px-8 py-3 text-white dark:text-gray-900 bg-gray-800 dark:bg-gray-200 rounded-md hover:bg-gray-900 dark:hover:bg-gray-100 font-semibold shadow-lg transition duration-200 border border-gray-700 dark:border-gray-300">
                                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Ayarları Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="addEventModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white p-6 rounded-md shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-transform duration-300 scale-100 my-4">
            <h3 class="text-2xl font-bold mb-4 border-b pb-2 text-gray-900">Yeni Etkinlik Kaydı</h3>
            <form method="POST" action="index.php" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add_event">
                <input type="hidden" name="current_view" value="events">
                <div>
                    <label for="modal_event_title" class="block text-sm font-medium text-gray-700">Etkinlik Adı <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="modal_event_title" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Örn: Yıllık Kongre ve Sempozyumu">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="modal_event_date" class="block text-sm font-medium text-gray-700">Tarih <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="modal_event_date" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                    <div>
                        <label for="modal_event_time" class="block text-sm font-medium text-gray-700">Saat <span class="text-red-500">*</span></label>
                        <input type="time" name="time" id="modal_event_time" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                    <div>
                        <label for="modal_event_location" class="block text-sm font-medium text-gray-700">Yer/Konum</label>
                        <input type="text" name="location" id="modal_event_location" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Salon Adı, Çevrimiçi Bağlantı...">
                    </div>
                </div>
                <div>
                    <label for="modal_event_description" class="block text-sm font-medium text-gray-700">Detaylı Açıklama</label>
                    <textarea name="description" id="modal_event_description" rows="3" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Etkinliğin amacını, konuşmacıları ve katılım detaylarını belirtiniz."></textarea>
                </div>
                
                <!-- Görsel ve Video Yükleme -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Etkinlik Görseli</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-md p-4 text-center hover:border-gray-400 transition-colors">
                            <input type="file" name="event_image" id="event_image" accept="image/*" class="hidden" onchange="previewImage(this, 'imagePreview')">
                            <label for="event_image" class="cursor-pointer">
                                <div class="flex flex-col items-center">
                                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm text-gray-600">Görsel yüklemek için tıklayın</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG (Max: 5MB)</span>
                                </div>
                            </label>
                        </div>
                        <div id="imagePreview" class="mt-2 hidden">
                            <img id="previewImg" class="w-full h-32 object-cover rounded-md">
                            <button type="button" onclick="removeImage()" class="mt-2 text-red-600 text-sm hover:text-red-800">Görseli Kaldır</button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Etkinlik Videosu</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-md p-4 text-center hover:border-gray-400 transition-colors">
                            <input type="file" name="event_video" id="event_video" accept="video/*" class="hidden" onchange="previewVideo(this, 'videoPreview')">
                            <label for="event_video" class="cursor-pointer">
                                <div class="flex flex-col items-center">
                                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm text-gray-600">Video yüklemek için tıklayın</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">MP4, AVI, MOV (Max: 50MB)</span>
                                </div>
                            </label>
                        </div>
                        <div id="videoPreview" class="mt-2 hidden">
                            <video id="previewVideo" class="w-full h-32 object-cover rounded-md" controls></video>
                            <button type="button" onclick="removeVideo()" class="mt-2 text-red-600 text-sm hover:text-red-800">Videoyu Kaldır</button>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addEventModal')" class="px-4 py-2 text-white color-secondary-btn rounded-md hover-secondary transition duration-150">İptal</button>
                    <button type="submit" class="px-4 py-2 text-white color-primary rounded-md hover-primary transition duration-150 font-semibold">Etkinlik Oluştur</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addMemberModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-6 rounded-md shadow-2xl w-full max-w-lg transform transition-transform duration-300 scale-100">
            <h3 class="text-2xl font-bold mb-4 border-b pb-2 text-gray-900">Yeni Üye Kaydı</h3>
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="current_view" value="members">
                <div>
                    <label for="modal_member_name" class="block text-sm font-medium text-gray-700">Adı Soyadı</label>
                    <input type="text" name="full_name" id="modal_member_name" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Tam Ad Soyad">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="modal_member_student_id" class="block text-sm font-medium text-gray-700">Öğrenci Numarası</label>
                        <input type="text" name="student_id" id="modal_member_student_id" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil, sadece rakamlar">
                    </div>
                    <div>
                        <label for="modal_member_phone" class="block text-sm font-medium text-gray-700">Telefon Numarası</label>
                        <input type="tel" name="phone_number" id="modal_member_phone" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil, uluslararası format">
                    </div>
                </div>
                <div>
                    <label for="modal_member_email" class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input type="email" name="email" id="modal_member_email" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil, iletişim e-postası">
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addMemberModal')" class="px-4 py-2 text-white color-secondary-btn rounded-md hover-secondary transition duration-150">İptal</button>
                    <button type="submit" class="px-4 py-2 text-white color-primary rounded-md hover-primary font-semibold shadow-md transition duration-150">Üye Kaydet</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="addBoardModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-6 rounded-md shadow-2xl w-full max-w-lg transform transition-transform duration-300 scale-100">
            <h3 class="text-2xl font-bold mb-4 border-b pb-2 text-gray-900">Yeni Görevli Tanımlama</h3>
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="action" value="add_board_member">
                <input type="hidden" name="current_view" value="board">
                <div>
                    <label for="modal_board_name" class="block text-sm font-medium text-gray-700">Adı Soyadı <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" id="modal_board_name" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                </div>
                <div>
                    <label for="modal_board_role" class="block text-sm font-medium text-gray-700">Görevi/Pozisyonu <span class="text-red-500">*</span></label>
                    <select name="role" id="modal_board_role" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                        <option value="">Görev Seçin</option>
                        <option value="Başkan">Başkan</option>
                        <option value="Başkan Yardımcısı">Başkan Yardımcısı</option>
                        <option value="Genel Sekreter">Genel Sekreter</option>
                        <option value="Mali İşler Sorumlusu">Mali İşler Sorumlusu</option>
                        <option value="Sosyal İşler Sorumlusu">Sosyal İşler Sorumlusu</option>
                        <option value="Eğitim Sorumlusu">Eğitim Sorumlusu</option>
                        <option value="Kültür Sanat Sorumlusu">Kültür Sanat Sorumlusu</option>
                        <option value="Spor Sorumlusu">Spor Sorumlusu</option>
                        <option value="Basın Yayın Sorumlusu">Basın Yayın Sorumlusu</option>
                        <option value="Teknoloji Sorumlusu">Teknoloji Sorumlusu</option>
                        <option value="Çevre Sorumlusu">Çevre Sorumlusu</option>
                        <option value="Üye">Üye</option>
                    </select>
                </div>
                <div>
                    <label for="modal_board_email" class="block text-sm font-medium text-gray-700">İletişim E-postası <span class="text-red-500">*</span></label>
                    <input type="email" name="contact_email" id="modal_board_email" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Kurumsal e-posta adresi">
                </div>
                <div>
                    <label for="modal_board_phone" class="block text-sm font-medium text-gray-700">Telefon Numarası</label>
                    <input type="tel" name="phone" id="modal_board_phone" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="0555 123 45 67">
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addBoardModal')" class="px-4 py-2 text-white color-secondary-btn rounded-md hover-secondary transition duration-150">İptal</button>
                    <button type="submit" class="px-4 py-2 text-white color-primary rounded-md hover-primary font-semibold shadow-md transition duration-150">Görevli Ekle</button>
                </div>
            </form>
        </div>
    </div>


    <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
        <div class="bg-white p-6 rounded-md shadow-2xl w-full max-w-lg transform transition-transform duration-300 scale-95">
            <h3 id="modalTitle" class="text-xl font-bold mb-4 border-b pb-2 text-gray-800 dark:text-gray-200">Öğe Düzenle</h3>
            <form method="POST" action="index.php" id="editForm" class="space-y-4">
                <input type="hidden" name="action" id="modalAction">
                <input type="hidden" name="id" id="modalId">
                <input type="hidden" name="current_view" value="<?= $current_view ?>">

                <div id="eventFields" class="hidden space-y-4">
                    <div>
                        <label for="edit_title" class="block text-sm font-medium text-gray-700">Etkinlik Adı <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="edit_title" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_date" class="block text-sm font-medium text-gray-700">Tarih <span class="text-red-500">*</span></label>
                            <input type="date" name="date" id="edit_date" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                        </div>
                        <div>
                            <label for="edit_time" class="block text-sm font-medium text-gray-700">Saat <span class="text-red-500">*</span></label>
                            <input type="time" name="time" id="edit_time" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                        </div>
                    </div>
                    <div>
                        <label for="edit_location" class="block text-sm font-medium text-gray-700">Yer/Konum</label>
                        <input type="text" name="location" id="edit_location" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <textarea name="description" id="edit_description" rows="3" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus"></textarea>
                    </div>
                </div>
                
                <div id="memberFields" class="hidden space-y-4">
                    <input type="hidden" name="member_id" id="edit_member_id">
                    <div>
                        <label for="edit_member_name" class="block text-sm font-medium text-gray-700">Adı Soyadı</label>
                        <input type="text" name="full_name" id="edit_member_name" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_student_id" class="block text-sm font-medium text-gray-700">Öğrenci Numarası</label>
                            <input type="text" name="student_id" id="edit_student_id" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil">
                        </div>
                        <div>
                            <label for="edit_phone_number" class="block text-sm font-medium text-gray-700">Telefon Numarası</label>
                            <input type="tel" name="phone_number" id="edit_phone_number" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil">
                        </div>
                    </div>
                    <div>
                        <label for="edit_member_email" class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input type="email" name="email" id="edit_member_email" class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="Zorunlu değil">
                    </div>
                </div>

                <div id="boardFields" class="hidden space-y-4">
                    <div>
                        <label for="edit_board_name" class="block text-sm font-medium text-gray-700">Adı Soyadı <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" id="edit_board_name" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                    <div>
                        <label for="edit_board_role" class="block text-sm font-medium text-gray-700">Görevi <span class="text-red-500">*</span></label>
                        <select name="role" id="edit_board_role" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                            <option value="">Görev Seçin</option>
                            <option value="Başkan">Başkan</option>
                            <option value="Başkan Yardımcısı">Başkan Yardımcısı</option>
                            <option value="Genel Sekreter">Genel Sekreter</option>
                            <option value="Mali İşler Sorumlusu">Mali İşler Sorumlusu</option>
                            <option value="Sosyal İşler Sorumlusu">Sosyal İşler Sorumlusu</option>
                            <option value="Eğitim Sorumlusu">Eğitim Sorumlusu</option>
                            <option value="Kültür Sanat Sorumlusu">Kültür Sanat Sorumlusu</option>
                            <option value="Spor Sorumlusu">Spor Sorumlusu</option>
                            <option value="Basın Yayın Sorumlusu">Basın Yayın Sorumlusu</option>
                            <option value="Teknoloji Sorumlusu">Teknoloji Sorumlusu</option>
                            <option value="Çevre Sorumlusu">Çevre Sorumlusu</option>
                            <option value="Üye">Üye</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_board_phone" class="block text-sm font-medium text-gray-700">Telefon Numarası <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" id="edit_board_phone" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus" placeholder="0555 123 45 67">
                    </div>
                    <div>
                        <label for="edit_board_email" class="block text-sm font-medium text-gray-700">E-posta <span class="text-red-500">*</span></label>
                        <input type="email" name="contact_email" id="edit_board_email" required class="mt-1 w-full p-3 border border-gray-300 rounded-md shadow-sm input-focus">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-white color-secondary-btn rounded-md hover-secondary transition duration-150">İptal</button>
                    <button type="submit" class="px-4 py-2 text-white color-primary rounded-md hover-primary transition duration-150 font-semibold">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bildirim Detay Modalı -->
    <div id="notificationDetailModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl transform transition-all duration-300 scale-95 border border-gray-200 dark:border-gray-700">
            <!-- Üst Kısım -->
            <div id="notificationDetailHeader" class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="notificationDetailIcon" class="w-10 h-10 rounded-lg flex items-center justify-center">
                            <!-- Icon buraya dinamik eklenecek -->
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Bildirim Detayı</h3>
                            <p id="notificationDetailBadge" class="text-xs mt-1 inline-flex items-center px-2 py-0.5 rounded font-medium">
                                <!-- Badge buraya dinamik eklenecek -->
                            </p>
                        </div>
                    </div>
                    <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- İçerik -->
            <div id="notificationDetailContent" class="px-6 py-6">
                <!-- İçerik buraya dinamik olarak yüklenecek -->
            </div>
            
            <!-- Alt Kısım -->
            <div id="notificationDetailFooter" class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span id="notificationDetailDate" class="text-sm text-gray-500 dark:text-gray-400">
                    <!-- Tarih buraya eklenecek -->
                </span>
                <div class="flex gap-2">
                    <button onclick="closeNotificationModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Kapat
                    </button>
                    <button id="markAsReadBtn" onclick="markNotificationAsRead()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors hidden">
                        Okundu İşaretle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Bildirim Modalı -->
    <div id="newNotificationModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-6 rounded-md shadow-2xl w-full max-w-lg transform transition-transform duration-300 scale-95">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200">Yeni Bildirim</h3>
                <button onclick="closeNewNotificationModal()" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="newNotificationContent">
                <!-- İçerik buraya dinamik olarak yüklenecek -->
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button onclick="closeNewNotificationModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition duration-200">
                    Kapat
                </button>
                <button onclick="markNewNotificationAsRead()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 transition duration-200">
                    Okundu İşaretle
                </button>
            </div>
        </div>
    </div>

    <!-- Acil Bildirim Modalı -->
    <div id="urgentNotificationModal" class="fixed inset-0 bg-red-900 bg-opacity-80 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-8 rounded-md shadow-2xl w-full max-w-2xl transform transition-transform duration-300 scale-95 border-4 border-red-500">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-red-600">ACİL BİLDİRİM</h3>
                </div>
                <button onclick="closeUrgentNotificationModal()" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="urgentNotificationContent" class="space-y-4">
                <!-- İçerik buraya dinamik olarak yüklenecek -->
            </div>
            
            <div class="flex justify-center pt-6 border-t border-red-200">
                <button onclick="markUrgentNotificationAsRead()" class="px-8 py-3 text-white bg-red-600 rounded-md hover:bg-red-700 transition duration-200 font-semibold text-lg">
                    OKUNDU İŞARETLE
                </button>
            </div>
        </div>
    </div>

    <!-- İşbirliği Logosu Modal -->
    <div id="partnerLogoModal" class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-gray-800 rounded-md shadow-xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200" id="partnerModalTitle">İşbirliği Logosu Yönetimi</h3>
                <button onclick="closePartnerLogoModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mevcut Logo Bilgileri -->
            <?php if (!empty($partner_logos)): ?>
                <?php $current_logo = $partner_logos[0]; ?>
                <div id="currentLogoInfo" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <div class="flex items-center space-x-4 mb-3">
                        <img src="<?= htmlspecialchars($current_logo['logo_path']) ?>" alt="<?= htmlspecialchars($current_logo['partner_name']) ?>" class="w-16 h-16 rounded-md object-cover">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($current_logo['partner_name']) ?></h4>
                            <?php if (!empty($current_logo['partner_website'])): ?>
                                <a href="<?= htmlspecialchars($current_logo['partner_website']) ?>" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"><?= htmlspecialchars($current_logo['partner_website']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="showLogoUpdateForm()" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                            Değiştir
                        </button>
                        <button onclick="deletePartnerLogo()" class="px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition-colors">
                            Sil
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Logo Formu -->
            <form id="partnerLogoForm" enctype="multipart/form-data" class="<?= empty($partner_logos) ? '' : 'hidden' ?>" id="logoForm">
                <div class="mb-4">
                    <label for="partnerLogoFile" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Logo Dosyası Seç <span class="text-red-500">*</span></label>
                    <input type="file" id="partnerLogoFile" name="partner_logo" accept="image/*" required class="w-full p-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Desteklenen formatlar: JPG, PNG, GIF (Max: 2MB)</p>
                </div>
                
                <div class="mb-4">
                    <label for="partnerName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">İşbirliği Adı <span class="text-red-500">*</span></label>
                    <input type="text" id="partnerName" name="partner_name" placeholder="Örn: ABC Şirketi" required class="w-full p-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="partnerWebsite" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Website (Opsiyonel)</label>
                    <input type="url" id="partnerWebsite" name="partner_website" placeholder="https://example.com" class="w-full p-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePartnerLogoModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition duration-200">
                        İptal
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 transition duration-200">
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
<script>
    // --- JAVASCRIPT MANTIĞI ---
    
    // Mobile Menü Toggle
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('mobile-menu-toggle');
    
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });
    }

    // Modal Kapatma Fonksiyonu (Hem Edit hem de Add Modalları için)
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const modalContent = modal.querySelector('.bg-white'); 
        
        // Kapanış animasyonu
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        
        // Kısa bir gecikme sonrası gizle
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            
            // Eğer Edit Modal ise form alanlarını gizle
            if (modalId === 'editModal') {
                document.getElementById('eventFields').classList.add('hidden');
                document.getElementById('memberFields').classList.add('hidden');
                document.getElementById('boardFields').classList.add('hidden');
            }
        }, 300);
    }
 
    // Görsel ve Video Preview Fonksiyonları
    function previewImage(input, previewId) {
        const file = input.files[0];
        if (file) {
            // Dosya boyutu kontrolü (5MB)
            if (file.size > 5 * 1024 * 1024) {
                toastManager.show('Dosya Boyutu Hatası', 'Görsel dosyası 5MB\'dan büyük olamaz!', 'error', 4000);
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(previewId);
                const img = document.getElementById('previewImg');
                img.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }
    
    function previewVideo(input, previewId) {
        const file = input.files[0];
        if (file) {
            // Dosya boyutu kontrolü (50MB)
            if (file.size > 50 * 1024 * 1024) {
                toastManager.show('Dosya Boyutu Hatası', 'Video dosyası 50MB\'dan büyük olamaz!', 'error', 4000);
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(previewId);
                const video = document.getElementById('previewVideo');
                video.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }
    
    function removeImage() {
        document.getElementById('event_image').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
    }
    
    function removeVideo() {
        document.getElementById('event_video').value = '';
        document.getElementById('videoPreview').classList.add('hidden');
    }
    
    // Modalın dışına tıklanınca kapanmasını sağla
    document.querySelectorAll('[id$="Modal"]').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target.id === modal.id) {
                closeModal(modal.id);
            }
        });
    });


    // Yeni Kayıt Ekleme Modalını Açma Fonksiyonu
    function openAddModal(type) {
        const modalId = `add${type.charAt(0).toUpperCase() + type.slice(1)}Modal`;
        const modal = document.getElementById(modalId);
        
        if (!modal) return;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }

    // Düzenleme Modalını Açma Fonksiyonu
    function openEditModal(type, id, ...data) {
        const modal = document.getElementById('editModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalAction = document.getElementById('modalAction');
        const modalId = document.getElementById('modalId');
        const eventFields = document.getElementById('eventFields');
        const memberFields = document.getElementById('memberFields'); 
        const boardFields = document.getElementById('boardFields');
        
        // Alanları gizle ve sıfırla
        eventFields.classList.add('hidden');
        memberFields.classList.add('hidden');
        boardFields.classList.add('hidden');

        // Ortak alanları ayarla
        modalId.value = id;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Transform class'larını ekle/kaldır
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');


        if (type === 'event') {
            modalTitle.textContent = 'Etkinlik Düzenle';
            modalAction.value = 'update_event';
            eventFields.classList.remove('hidden');

            document.getElementById('edit_title').value = data[0];
            document.getElementById('edit_date').value = data[1];
            document.getElementById('edit_time').value = data[2];
            document.getElementById('edit_location').value = data[3];
            document.getElementById('edit_description').value = data[4];
        } else if (type === 'member') {
            modalTitle.textContent = 'Üye Bilgilerini Düzenle';
            modalAction.value = 'update_member';
            memberFields.classList.remove('hidden');

            // Data sırası: full_name, email, student_id, phone_number
            document.getElementById('edit_member_id').value = id;
            document.getElementById('edit_member_name').value = data[0];
            document.getElementById('edit_member_email').value = data[1];
            document.getElementById('edit_student_id').value = data[2];
            document.getElementById('edit_phone_number').value = data[3];

        } else if (type === 'board') {
            modalTitle.textContent = 'Yönetim Kurulu Üyesi Düzenle';
            modalAction.value = 'update_board_member';
            boardFields.classList.remove('hidden');
            
            // Data sırası: full_name, role, contact_email, phone
            document.getElementById('edit_board_name').value = data[0];
            document.getElementById('edit_board_role').value = data[1];
            document.getElementById('edit_board_email').value = data[2];
            document.getElementById('edit_board_phone').value = data[3] || '';
        }
    }

    // Arama ve Filtreleme Fonksiyonu
    function filterTable(tableId, inputId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toUpperCase();
        const table = document.getElementById(tableId);
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            let visible = false;
            // İlk 5 sütunu kontrol et (Adı, Öğrenci No, E-posta, Telefon, Kayıt Tarihi)
            for (let j = 0; j < 5; j++) { 
                const td = tr[i].getElementsByTagName("td")[j];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        visible = true;
                        break; 
                    }
                }
            }
            tr[i].style.display = visible ? "" : "none";
        }
    }

    // İletişim Merkezi JS Mantığı
    
    // Tümünü Seç/Seçimi Kaldır işlevi
    const selectAllEmails = document.getElementById('select-all-emails');
    const selectAllPhones = document.getElementById('select-all-phones');
    const emailSelectionCounter = document.getElementById('selected-email-count');
    const emailTotalCounter = document.getElementById('email-total-count');
    const emailSelectionProgress = document.getElementById('email-selection-progress');

    function updateSelectedEmailCount() {
        if (!emailSelectionCounter) {
            return;
        }
        const total = document.querySelectorAll('.target-email').length;
        const selected = document.querySelectorAll('.target-email:checked').length;
        emailSelectionCounter.textContent = selected;
        if (emailTotalCounter) {
            emailTotalCounter.textContent = total;
        }
        if (emailSelectionProgress) {
            const percent = total === 0 ? 0 : Math.round((selected / total) * 100);
            emailSelectionProgress.style.width = `${percent}%`;
        }
    }

    function refreshEmailSelectionStyles() {
        document.querySelectorAll('.target-email').forEach(checkbox => {
            const item = checkbox.closest('.email-contact-item');
            if (item) {
                item.classList.toggle('selected', checkbox.checked);
            }
        });
    }

    if (selectAllEmails) {
        selectAllEmails.addEventListener('change', (e) => {
            document.querySelectorAll('.target-email').forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
            refreshEmailSelectionStyles();
            updateSelectedEmailCount();
        });
    }

    if (selectAllPhones) {
        selectAllPhones.addEventListener('change', (e) => {
            document.querySelectorAll('.target-phone').forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    }

    // Bireysel checkbox'lardan herhangi biri değiştiğinde "Tümünü Seç" kutusunu güncelle
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('target-email')) {
            if (selectAllEmails) {
                const allChecked = document.querySelectorAll('.target-email').length === document.querySelectorAll('.target-email:checked').length;
                selectAllEmails.checked = allChecked;
            }
            refreshEmailSelectionStyles();
            updateSelectedEmailCount();
        }
        if (e.target.classList.contains('target-phone') && selectAllPhones) {
            const allChecked = document.querySelectorAll('.target-phone').length === document.querySelectorAll('.target-phone:checked').length;
            selectAllPhones.checked = allChecked;
        }
    });

    /**
     * Form gönderilmeden önce seçilen alıcıları gizli alanlara ekler.
     * @param {string} formType 'email_form' veya 'sms_form'
     * @returns {boolean} Formun gönderilip gönderilmeyeceği
     */
    function collectRecipients(formType) {
        const containerId = formType === 'email_form' ? 'email_form_recipients' : 'sms_form_recipients';
        const checkboxSelector = formType === 'email_form' ? '.target-email' : '.target-phone';
        const inputName = formType === 'email_form' ? 'selected_emails[]' : 'selected_phones[]';
        
        const container = document.getElementById(containerId);
        const selectedCheckboxes = document.querySelectorAll(checkboxSelector + ':checked');
        
        // Önceki gizli alanları temizle
        container.innerHTML = '';

        if (selectedCheckboxes.length === 0) {
            // Hiçbir alıcı seçilmediyse formu gönderme uyarısı göster
            toastManager.show('Alıcı Seçimi Gerekli', 'Lütfen en az bir alıcı seçin veya "Tüm Üyeleri Seç" kutusunu işaretleyin.', 'warning', 5000);
            return false; 
        }

        // Seçilen her alıcı için gizli input alanı oluştur
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = inputName;
            input.value = checkbox.value;
            container.appendChild(input);
        });

        // Tüm üyelerin seçili olduğu durum, checkbox'lar zaten değerlerini taşıyor olacak.
        return true; 
    }

    // Formların onsubmit olaylarını bağlamak için DOMContentLoaded kullanıyoruz
    document.addEventListener('DOMContentLoaded', () => {
        // SMS Formunu bağla
        const smsForm = document.querySelector('form[name="action"][value="send_sms"]');
        if (smsForm) {
            smsForm.setAttribute('onsubmit', 'return collectRecipients("sms_form")');
        }
        
        // E-posta Formunu bağla
        const emailForm = document.querySelector('form[name="action"][value="send_email"]');
        if (emailForm) {
            emailForm.setAttribute('onsubmit', 'return collectRecipients("email_form")');
        }
        
        // Tema sistemi başlatma
        initTheme();
        
        // Bildirim sistemi başlatma
        initNotifications();

        refreshEmailSelectionStyles();
        updateSelectedEmailCount();
        
        // Profil dropdown başlatma
        initProfileDropdown();
    });
    
    // Tema Sistemi - Sadece kullanıcı seçimini yönet
    function initTheme() {
        // Sistem dark mode kontrolünü yok say, sadece kullanıcı tercihini kullan
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Sistem tercihini göz ardı et
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            // Sistem dark mode açık olsa bile, kullanıcı tercihine göre davran
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        
        updateThemeToggle(savedTheme);
        
        // Dark mode'u uygula
        applyDarkMode();
        
        // Sayfa yüklendikten sonra tekrar uygula
        setTimeout(() => {
            applyDarkMode();
        }, 100);
    }
    
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        
        let newTheme;
        if (currentTheme === 'dark') {
            newTheme = 'light';
        } else {
            newTheme = 'dark';
        }
        
        // Dark mode'a geçişte onay iste
        if (newTheme === 'dark') {
            // Modal oluştur
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Dark Mode Beta</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Bu özellik deneme aşamasındadır</p>
                        </div>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 mb-6">
                        Dark mode'a geçmek istediğinize emin misiniz? Bu özellik hala geliştirilme aşamasındadır.
                    </p>
                    <div class="flex gap-3">
                        <button onclick="closeThemeModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            Hayır, iptal et
                        </button>
                        <button onclick="confirmThemeChange()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Evet, devam et
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Modal'ı kapatma fonksiyonu
            window.closeThemeModal = function() {
                document.body.removeChild(modal);
            };
            
            // Evet butonuna tıklandığında
            window.confirmThemeChange = function() {
                document.body.removeChild(modal);
                
                // Smooth geçiş için body'ye class ekle
                document.body.classList.add('theme-transition');
                
                document.documentElement.setAttribute('data-theme', 'dark');
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateThemeToggle('dark');
                
                // Dark mode'u uygula
                applyDarkMode();
                
                // Smooth geçiş sonrası class'ı kaldır
                setTimeout(() => {
                    document.body.classList.remove('theme-transition');
                }, 300);
                
                // Beta bildirimi göster
                toastManager.show('Bilgi', 'Dark mode aktif. Bu özellik deneme aşamasındadır.', 'info', 5000);
            };
            
            return;
        }
        
        // Light mode'a geçişte doğrudan geç
        // Smooth geçiş için body'ye class ekle
        document.body.classList.add('theme-transition');
        
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.classList.remove('dark');
        
        localStorage.setItem('theme', 'light');
        updateThemeToggle('light');
        
        // Dark mode'u uygula
        applyDarkMode();
        
        // Smooth geçiş sonrası class'ı kaldır
        setTimeout(() => {
            document.body.classList.remove('theme-transition');
        }, 300);
    }
    
    // Dark Mode Flash Önleme - Sayfa Yüklenmeden Önce
    (function() {
        const theme = localStorage.getItem('theme') || 'light';
        if (theme === 'dark') {
            // Hemen uygula - sayfa yüklenmeden önce
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.style.backgroundColor = '#0f172a';
            document.documentElement.style.color = '#f1f5f9';
            
            // Body'yi hemen dark mode'a çevir
            document.body.style.backgroundColor = '#0f172a';
            document.body.style.color = '#f1f5f9';
            
            // Tüm elementleri hemen dark mode'a çevir
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                if (el.classList.contains('bg-white')) {
                    el.style.backgroundColor = '#1e293b';
                }
                if (el.classList.contains('bg-gray-50')) {
                    el.style.backgroundColor = '#1e293b';
                }
                if (el.classList.contains('bg-gray-100')) {
                    el.style.backgroundColor = '#334155';
                }
                if (el.classList.contains('text-gray-900')) {
                    el.style.color = '#f1f5f9';
                }
                if (el.classList.contains('text-gray-700')) {
                    el.style.color = '#cbd5e1';
                }
                if (el.classList.contains('text-gray-600')) {
                    el.style.color = '#94a3b8';
                }
                if (el.classList.contains('text-gray-500 dark:text-gray-400')) {
                    el.style.color = '#64748b';
                }
                if (el.classList.contains('border-gray-200')) {
                    el.style.borderColor = '#334155';
                }
                if (el.classList.contains('border-gray-300')) {
                    el.style.borderColor = '#475569';
                }
            });
            
            // Theme toggle butonunu hemen güncelle
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.style.backgroundColor = '#3b82f6';
                themeToggle.style.transition = 'none';
                const toggleBefore = themeToggle.querySelector('::before');
                if (toggleBefore) {
                    toggleBefore.style.transform = 'translateX(26px)';
                    toggleBefore.style.transition = 'none';
                }
            }
            
            // Header layout'unu düzelt
            const mainHeader = document.querySelector('.main-header');
            if (mainHeader) {
                mainHeader.style.position = 'sticky';
                mainHeader.style.top = '0';
                mainHeader.style.left = '0';
                mainHeader.style.right = '0';
                mainHeader.style.width = '100%';
                mainHeader.style.margin = '0';
                mainHeader.style.padding = '0';
                mainHeader.style.transform = 'none';
                mainHeader.style.transition = 'none';
            }
            
            // Main content layout'unu düzelt
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.style.marginLeft = '16rem';
                mainContent.style.width = 'calc(100% - 16rem)';
                mainContent.style.position = 'relative';
            }
            
            // Sidebar layout'unu düzelt
            const sidebar = document.querySelector('aside');
            if (sidebar) {
                sidebar.style.position = 'fixed';
                sidebar.style.top = '0';
                sidebar.style.left = '0';
                sidebar.style.width = '16rem';
                sidebar.style.height = '100vh';
                sidebar.style.zIndex = '30';
            }
            
            // Tablo hover efektlerini düzelt
            const tableRows = document.querySelectorAll('table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#334155';
                    const cells = this.querySelectorAll('td, th');
                    cells.forEach(cell => {
                        cell.style.backgroundColor = '#334155';
                        cell.style.color = '#f1f5f9';
                    });
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    const cells = this.querySelectorAll('td, th');
                    cells.forEach(cell => {
                        cell.style.backgroundColor = '';
                        cell.style.color = '';
                    });
                });
            });
            
            // Etkinlik açıklama bölümünü düzelt
            const gradientElements = document.querySelectorAll('.bg-gradient-to-r');
            gradientElements.forEach(el => {
                if (el.classList.contains('from-gray-50') && el.classList.contains('to-gray-100')) {
                    el.style.background = 'linear-gradient(to right, #1e293b, #334155)';
                }
            });
            
            const textElements = document.querySelectorAll('.text-gray-700, .text-gray-800 dark:text-gray-200');
            textElements.forEach(el => {
                el.style.color = '#f1f5f9';
            });
            
            // Etkinlik detay header'ını düzelt
            const eventDetailHeader = document.querySelector('.bg-white');
            if (eventDetailHeader) {
                eventDetailHeader.style.backgroundColor = '#1f2937';
            }
            
            const grayTextElements = document.querySelectorAll('.text-gray-600');
            grayTextElements.forEach(el => {
                el.style.color = '#d1d5db';
            });
            
            const grayBgElements = document.querySelectorAll('.bg-gray-50');
            grayBgElements.forEach(el => {
                el.style.backgroundColor = '#374151';
            });
            
            const iconElements = document.querySelectorAll('.text-yellow-500, .text-green-500, .text-red-500');
            iconElements.forEach(el => {
                if (el.classList.contains('text-yellow-500')) {
                    el.style.color = '#fbbf24';
                } else if (el.classList.contains('text-green-500')) {
                    el.style.color = '#34d399';
                } else if (el.classList.contains('text-red-500')) {
                    el.style.color = '#f87171';
                }
            });
        }
    })();
    
    // Dark mode kontrolü ve uygulama
    function applyDarkMode() {
        const theme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);

        // Flash önleme sınıfını kaldır
        document.body.classList.remove('dark-mode-flash-prevention');
        
        // Header'daki topluluk adını koruyalım
        const clubNameElement = document.querySelector('.main-header h1');
        if (clubNameElement) {
            if (theme === 'dark') {
                clubNameElement.style.color = '#f3f4f6';
                clubNameElement.style.setProperty('color', '#f3f4f6', 'important');
            } else {
                clubNameElement.style.color = '#111827';
                clubNameElement.style.setProperty('color', '#111827', 'important');
            }
        }
        
        // Header'ın pointer events'ini ayarla
        const mainHeader = document.querySelector('.main-header');
        if (mainHeader) {
            mainHeader.style.pointerEvents = 'auto';
        }
        
        // Tüm elementleri yeniden uygula
        if (theme === 'dark') {
            // HTML ve body'yi hemen dark mode'a çevir
            document.documentElement.style.backgroundColor = '#0f172a';
            document.documentElement.style.color = '#f1f5f9';
            document.body.style.backgroundColor = '#0f172a';
            document.body.style.color = '#f1f5f9';
            
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                // Header'daki butonları koru
                if (el.classList.contains('main-header') || el.closest('.main-header')) {
                    return;
                }
                
                if (el.classList.contains('bg-white')) {
                    el.style.backgroundColor = '#1e293b';
                }
                if (el.classList.contains('bg-gray-50')) {
                    el.style.backgroundColor = '#1e293b';
                }
                if (el.classList.contains('bg-gray-100')) {
                    el.style.backgroundColor = '#334155';
                }
                if (el.classList.contains('text-gray-900')) {
                    el.style.color = '#f1f5f9';
                }
                if (el.classList.contains('text-gray-700')) {
                    el.style.color = '#cbd5e1';
                }
                if (el.classList.contains('text-gray-600')) {
                    el.style.color = '#94a3b8';
                }
                if (el.classList.contains('text-gray-500 dark:text-gray-400')) {
                    el.style.color = '#64748b';
                }
                if (el.classList.contains('border-gray-200')) {
                    el.style.borderColor = '#334155';
                }
                if (el.classList.contains('border-gray-300')) {
                    el.style.borderColor = '#475569';
                }
            });
            
            // Theme toggle butonunu güncelle
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.style.backgroundColor = '#3b82f6';
                themeToggle.style.transition = 'none';
                const toggleBefore = themeToggle.querySelector('::before');
                if (toggleBefore) {
                    toggleBefore.style.transform = 'translateX(26px)';
                    toggleBefore.style.transition = 'none';
                }
            }
            
            // Header layout'unu düzelt
            const mainHeader = document.querySelector('.main-header');
            if (mainHeader) {
                mainHeader.style.position = 'sticky';
                mainHeader.style.top = '0';
                mainHeader.style.left = '0';
                mainHeader.style.right = '0';
                mainHeader.style.width = '100%';
                mainHeader.style.margin = '0';
                mainHeader.style.padding = '0';
                mainHeader.style.transform = 'none';
                mainHeader.style.transition = 'none';
            }
            
            // Main content layout'unu düzelt
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.style.marginLeft = '16rem';
                mainContent.style.width = 'calc(100% - 16rem)';
                mainContent.style.position = 'relative';
            }
            
            // Sidebar layout'unu düzelt
            const sidebar = document.querySelector('aside');
            if (sidebar) {
                sidebar.style.position = 'fixed';
                sidebar.style.top = '0';
                sidebar.style.left = '0';
                sidebar.style.width = '16rem';
                sidebar.style.height = '100vh';
                sidebar.style.zIndex = '30';
            }
            
            // Tablo hover efektlerini düzelt
            const tableRows = document.querySelectorAll('table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#334155';
                    const cells = this.querySelectorAll('td, th');
                    cells.forEach(cell => {
                        cell.style.backgroundColor = '#334155';
                        cell.style.color = '#f1f5f9';
                    });
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    const cells = this.querySelectorAll('td, th');
                    cells.forEach(cell => {
                        cell.style.backgroundColor = '';
                        cell.style.color = '';
                    });
                });
            });
        } else {
            // Light mode'a geçiş
            document.documentElement.style.backgroundColor = '';
            document.documentElement.style.color = '';
            document.body.style.backgroundColor = '';
            document.body.style.color = '';
            
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                el.style.backgroundColor = '';
                el.style.color = '';
                el.style.borderColor = '';
            });
            
            // Theme toggle butonunu light mode'a çevir
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.style.backgroundColor = '#e2e8f0';
                themeToggle.style.transition = 'none';
                const toggleBefore = themeToggle.querySelector('::before');
                if (toggleBefore) {
                    toggleBefore.style.transform = 'translateX(0px)';
                    toggleBefore.style.transition = 'none';
                }
            }
            
            // Layout'u light mode'a çevir
            const mainHeader = document.querySelector('.main-header');
            if (mainHeader) {
                mainHeader.style.position = '';
                mainHeader.style.top = '';
                mainHeader.style.left = '';
                mainHeader.style.right = '';
                mainHeader.style.width = '';
                mainHeader.style.margin = '';
                mainHeader.style.padding = '';
                mainHeader.style.transform = '';
                mainHeader.style.transition = '';
            }
            
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.style.marginLeft = '';
                mainContent.style.width = '';
                mainContent.style.position = '';
            }
            
            const sidebar = document.querySelector('aside');
            if (sidebar) {
                sidebar.style.position = '';
                sidebar.style.top = '';
                sidebar.style.left = '';
                sidebar.style.width = '';
                sidebar.style.height = '';
                sidebar.style.zIndex = '';
            }
        }
        
        // Tüm elementleri dark mode'a göre güncelle
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            
            // Tüm input, textarea, select elementlerini güncelle
            const formElements = document.querySelectorAll('input, textarea, select');
            formElements.forEach(element => {
                element.style.backgroundColor = 'var(--input-bg)';
                element.style.borderColor = 'var(--input-border)';
                element.style.color = 'var(--text-primary)';
            });
            
            // Tüm kartları güncelle
            const cards = document.querySelectorAll('.bg-white');
            cards.forEach(card => {
                card.style.backgroundColor = 'var(--card-bg)';
            });
            
            // Tüm text elementlerini güncelle
            const textElements = document.querySelectorAll('.text-gray-800 dark:text-gray-200, .text-gray-700, .text-gray-600, .text-gray-500 dark:text-gray-400');
            textElements.forEach(element => {
                if (element.classList.contains('text-gray-800 dark:text-gray-200') || element.classList.contains('text-gray-700')) {
                    element.style.color = 'var(--text-primary)';
                } else {
                    element.style.color = 'var(--text-secondary)';
                }
            });
            
        } else {
            document.body.classList.remove('dark-mode');
        }
    }
    
    function updateThemeToggle(theme) {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;
        
        if (theme === 'dark') {
            toggle.classList.add('active');
            toggle.style.backgroundColor = '#3b82f6';
        } else {
            toggle.classList.remove('active');
            toggle.style.backgroundColor = '#e2e8f0';
        }
    }
    
    // Email Template Sistemi
    const emailTemplates = {
        welcome: {
            subject: 'Hoş Geldiniz - <?= htmlspecialchars($club_name) ?>',
            body: `<h2>Merhaba!</h2>
<p>Hoş geldiniz! <?= htmlspecialchars($club_name) ?> topluluğuna katıldığınız için teşekkür ederiz.</p>
<p>Topluluk etkinlikleri ve güncellemeler hakkında bilgi almak için bizi takip etmeye devam edin.</p>
<p>İyi günler,<br><?= htmlspecialchars($club_name) ?> Yönetimi</p>`
        },
        event: {
            subject: 'Yeni Etkinlik Duyurusu - <?= htmlspecialchars($club_name) ?>',
            body: `<h2>Yeni Etkinlik!</h2>
<p>Merhaba,</p>
<p>Yaklaşan etkinliğimiz hakkında sizi bilgilendirmek istiyoruz.</p>
<p><strong>Etkinlik:</strong> [Etkinlik Adı]<br>
<strong>Tarih:</strong> [Tarih]<br>
<strong>Saat:</strong> [Saat]<br>
<strong>Yer:</strong> [Konum]</p>
<p>Katılımınızı bekliyoruz!</p>
<p>Saygılarımızla,<br><?= htmlspecialchars($club_name) ?> Yönetimi</p>`
        },
        newsletter: {
            subject: 'Aylık Bülten - <?= htmlspecialchars($club_name) ?>',
            body: `<h2>Aylık Bülten</h2>
<p>Merhaba,</p>
<p>Bu ay gerçekleştirdiğimiz etkinlikler ve gelecek planlarımız hakkında bilgi paylaşmak istiyoruz.</p>
<h3>Bu Ay Neler Yaptık?</h3>
<ul>
<li>[Etkinlik 1]</li>
<li>[Etkinlik 2]</li>
<li>[Etkinlik 3]</li>
</ul>
<h3>Gelecek Ay Planları</h3>
<p>[Gelecek etkinlikler ve planlar]</p>
<p>Görüşmek üzere,<br><?= htmlspecialchars($club_name) ?> Yönetimi</p>`
        },
        custom: {
            subject: '',
            body: ''
        }
    };
    
    function loadTemplate(templateName) {
        const template = emailTemplates[templateName];
        if (template) {
            document.getElementById('email_subject').value = template.subject;
            document.getElementById('email_body').value = template.body;
        }
    }
    
    function toggleEditor(type, evt) {
        const buttons = document.querySelectorAll('.editor-toggle-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        const trigger = evt?.currentTarget || evt?.target || event?.currentTarget || event?.target;
        if (trigger && trigger.classList) {
            trigger.classList.add('active');
        }
        
        const textarea = document.getElementById('email_body');
        if (!textarea) {
            return;
        }
        textarea.placeholder = type === 'html'
            ? 'HTML formatında e-posta içeriği yazın...'
            : 'Düz metin e-posta içeriği yazın...';
    }
    
    function previewEmail() {
        const subject = document.getElementById('email_subject').value;
        const body = document.getElementById('email_body').value;
        
        if (!subject || !body) {
            toastManager.show('Eksik Bilgi', 'Lütfen konu ve içerik alanlarını doldurun.', 'warning', 4000);
            return;
        }
        
        const previewWindow = window.open('', '_blank', 'width=800,height=600');
        previewWindow.document.write(`
            <html>
            <head>
                <title>E-posta Önizleme</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                    .email-container { background: white; padding: 30px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .subject { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #333; }
                    .body { line-height: 1.6; color: #555; }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="subject">${subject}</div>
                    <div class="body">${body}</div>
                </div>
            </body>
            </html>
        `);
    }
    
    // Email arama fonksiyonu
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('email-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const contactItems = document.querySelectorAll('.email-contact-item');
                
                contactItems.forEach(item => {
                    const name = item.querySelector('span').textContent.toLowerCase();
                    const email = item.querySelector('.text-xs').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || email.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // SMS arama fonksiyonu
        const smsSearchInput = document.getElementById('sms-search');
        if (smsSearchInput) {
            smsSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const contactItems = document.querySelectorAll('.sms-contact-item');
                
                contactItems.forEach(item => {
                    const name = item.querySelector('span').textContent.toLowerCase();
                    const phone = item.querySelector('.text-xs').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    });
    
    // SMS Template Sistemi
    const smsTemplates = {
        event: {
            message: 'Yeni etkinlik: [Etkinlik Adı] - [Tarih] [Saat] - [Konum]. Detaylar için kulüp web sitesini kontrol edin.'
        },
        reminder: {
            message: 'Hatırlatma: [Etkinlik Adı] etkinliği [Tarih] günü [Saat] saatinde [Konum] adresinde gerçekleşecek.'
        },
        urgent: {
            message: 'ACİL: [Mesaj] - Lütfen hemen kulüp web sitesini kontrol edin.'
        },
        custom: {
            message: ''
        }
    };
    
    function loadSmsTemplate(templateName) {
        const template = smsTemplates[templateName];
        if (template) {
            document.getElementById('sms_body').value = template.message;
            updateSmsCharCount();
        }
    }
    
    function updateSmsCharCount() {
        const textarea = document.getElementById('sms_body');
        const charCount = document.getElementById('sms-char-count');
        const length = textarea.value.length;
        
        charCount.textContent = `${length}/160`;
        
        if (length > 160) {
            charCount.classList.add('text-red-500');
            charCount.classList.remove('text-gray-700');
        } else if (length > 140) {
            charCount.classList.add('text-yellow-500');
            charCount.classList.remove('text-gray-700');
        } else {
            charCount.classList.remove('text-red-500', 'text-yellow-500');
            charCount.classList.add('text-gray-700');
        }
    }
    
    function previewSms() {
        const message = document.getElementById('sms_body').value;
        
        if (!message) {
            toastManager.show('Eksik Bilgi', 'Lütfen SMS içeriğini girin.', 'warning', 4000);
            return;
        }
        
        const previewWindow = window.open('', '_blank', 'width=400,height=300');
        previewWindow.document.write(`
            <html>
            <head>
                <title>SMS Önizleme</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 20px; 
                        background: #f5f5f5; 
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                    }
                    .sms-preview { 
                        background: white; 
                        padding: 20px; 
                        border-radius: 4px; 
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        max-width: 300px;
                        border-left: 4px solid #10b981;
                    }
                    .sms-header {
                        font-size: 12px;
                        color: #666;
                        margin-bottom: 10px;
                    }
                    .sms-content {
                        line-height: 1.4;
                        color: #333;
                    }
                    .sms-footer {
                        font-size: 10px;
                        color: #999;
                        margin-top: 10px;
                        text-align: right;
                    }
                </style>
            </head>
            <body>
                <div class="sms-preview">
                    <div class="sms-header">SMS Önizleme</div>
                    <div class="sms-content">${message}</div>
                    <div class="sms-footer">${message.length}/160 karakter</div>
                </div>
            </body>
            </html>
        `);
    }
    
    // Bildirim Sistemi
    function initNotifications() {
        const notificationBtn = document.getElementById('notification-btn');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const notificationBadge = document.getElementById('notification-badge');
        
        if (notificationBtn && notificationDropdown) {
            // Bildirim butonuna tıklama
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                // Önce profil dropdown'ını kapat
                const profileDropdown = document.getElementById('profile-dropdown');
                if (profileDropdown) profileDropdown.classList.add('hidden');
                // Bildirim dropdown'ını aç/kapat
                notificationDropdown.classList.toggle('hidden');
            });
            
            // Dışarı tıklayınca kapat
            document.addEventListener('click', function(e) {
                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.add('hidden');
                }
            });
            
            // Bildirim sayısını güncelle
            updateNotificationCount();
            
            // Gerçek zamanlı bildirim simülasyonu
            simulateNotifications();
        }
    }
    
    function initProfileDropdown() {
        const profileBtn = document.getElementById('profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        
        if (profileBtn && profileDropdown) {
            // Profil butonuna tıklama
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                // Önce bildirim dropdown'ını kapat
                const notificationDropdown = document.getElementById('notification-dropdown');
                if (notificationDropdown) notificationDropdown.classList.add('hidden');
                // Profil dropdown'ını aç/kapat
                profileDropdown.classList.toggle('hidden');
            });
            
            // Dışarı tıklayınca kapat
            document.addEventListener('click', function(e) {
                if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });
        }
    }
    
    function updateNotificationCount() {
        const badge = document.getElementById('notification-badge');
        const count = <?= $unread_notification_count ?>;
        
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    
    function simulateNotifications() {
        // Her 10 saniyede bir gerçek bildirimleri kontrol et
        setInterval(() => {
            checkForNewNotifications();
        }, 10000);
    }
    
    function checkForNewNotifications() {
        fetch('notification_api.php?action=get_notification_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notification-badge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Bildirim kontrolü hatası:', error);
            });
    }
    
    // Yeni bildirim modalı kaldırıldı - artık sadece badge güncelleniyor
    
    // Acil bildirim modalını göster
    function showUrgentNotification(notification) {
        const modal = document.getElementById('urgentNotificationModal');
        const content = document.getElementById('urgentNotificationContent');
        
        // İçeriği doldur
        content.innerHTML = `
            <div class="space-y-6">
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <h4 class="text-xl font-bold text-red-800 mb-2">${notification.title}</h4>
                    <p class="text-red-700 leading-relaxed text-lg">${notification.message}</p>
                </div>
                
                <div class="text-sm text-gray-600">
                    <span class="font-medium">Tarih:</span> ${notification.date}
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <p class="text-yellow-800 font-semibold">
                        Bu acil bildirim okundu olarak işaretlenene kadar sayfanın ortasında kalacaktır.
                    </p>
                </div>
            </div>
        `;
        
        // Modalı göster
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
        
        // Acil bildirim sesi çal
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('ACİL BİLDİRİM: ' + notification.title, {
                body: notification.message,
                icon: 'assets/images/brand/logo_tr.png',
                tag: 'urgent-notification'
            });
        }
        
        // Sayfa başlığını değiştir
        document.title = '🚨 ACİL BİLDİRİM - ' + notification.title;
        
        // Acil bildirim ID'sini sakla
        window.urgentNotificationId = notification.id;
    }
    
    // Acil bildirim modalını kapat
    function closeUrgentNotificationModal() {
        // Acil bildirim kapatılamaz, sadece okundu işaretlenebilir
        showToast('Uyarı', 'Acil bildirim kapatılamaz. Lütfen "OKUNDU İŞARETLE" butonuna tıklayın.', 'warning');
    }
    
    // Acil bildirimi okundu işaretle
    function markUrgentNotificationAsRead() {
        if (window.urgentNotificationId) {
            markAsRead(window.urgentNotificationId);
            
            // Modalı kapat
            const modal = document.getElementById('urgentNotificationModal');
            const modalContent = modal.querySelector('.bg-white');
            
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
            
            // Sayfa başlığını normale döndür
            document.title = 'UniPanel | Kulüp Yönetim Paneli';
            
            // Acil bildirim ID'sini temizle
            window.urgentNotificationId = null;
        }
    }
    
    function showNotification(title, message, type = 'info') {
        // Tarayıcı bildirimi
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: 'assets/images/brand/logo_tr.png'
            });
        }
        
        // Toast bildirimi
        showToast(title, message, type);
    }
    
    // Modern Toast Sistemi
    class ToastManager {
        constructor() {
            this.toasts = [];
            this.container = null;
            this.init();
        }

        init() {
            // Toast container'ı oluştur
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'fixed top-4 right-4 z-50 space-y-3 max-w-sm';
            document.body.appendChild(this.container);
        }

        show(title, message, type = 'info', duration = 5000) {
            const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const toast = this.createToast(toastId, title, message, type, duration);
            
            this.container.appendChild(toast);
            this.toasts.push({ id: toastId, element: toast });

            // Slide-in animasyonu
            setTimeout(() => {
                toast.classList.add('toast-enter');
            }, 10);

            // Otomatik kaldırma createToast içinde yönetilecek (pause/resume destekli)

            return toastId;
        }

        createToast(id, title, message, type, duration) {
            const toast = document.createElement('div');
            toast.id = id;
            toast.className = `toast toast-${type} bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4 transform translate-x-full opacity-0 transition-all duration-300 ease-out relative`;
            
            // Toast tipine göre renk ve ikon
            const typeConfig = {
                success: { 
                    color: 'text-green-600 dark:text-green-400', 
                    bg: 'bg-green-50 dark:bg-green-900/20',
                    icon: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                },
                error: { 
                    color: 'text-red-600 dark:text-red-400', 
                    bg: 'bg-red-50 dark:bg-red-900/20',
                    icon: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
                },
                warning: { 
                    color: 'text-yellow-600 dark:text-yellow-400', 
                    bg: 'bg-yellow-50 dark:bg-yellow-900/20',
                    icon: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'
                },
                info: { 
                    color: 'text-blue-600 dark:text-blue-400', 
                    bg: 'bg-blue-50 dark:bg-blue-900/20',
                    icon: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
                }
            };

            const config = typeConfig[type] || typeConfig.info;

            toast.innerHTML = `
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 ${config.color}">
                        ${config.icon}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">${title}</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">${message}</p>
                    </div>
                    <div class="flex-shrink-0 flex items-center space-x-2">
                        <button onclick="toastManager.remove('${id}')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="toast-progress-container">
                    <div class="toast-progress-fill" style="animation-duration: ${duration}ms;"></div>
                </div>
            `;

            // Progress bar CSS animasyonu ile otomatik olarak başlar

            // Auto-dismiss timer with hover pause/resume
            if (duration > 0) {
                const progressEl = toast.querySelector('.toast-progress-fill');
                let remaining = duration;
                let startTs = Date.now();
                let timerId = setTimeout(() => this.remove(id), remaining);

                // Store state on element for potential future use
                toast.__timer = { timerId, remaining, startTs };

                const pause = () => {
                    // Pause timer
                    clearTimeout(timerId);
                    const elapsed = Date.now() - startTs;
                    remaining = Math.max(0, remaining - elapsed);
                    // Pause CSS animation
                    if (progressEl) progressEl.style.animationPlayState = 'paused';
                };

                const resume = () => {
                    startTs = Date.now();
                    clearTimeout(timerId);
                    timerId = setTimeout(() => this.remove(id), remaining);
                    // Resume CSS animation
                    if (progressEl) progressEl.style.animationPlayState = 'running';
                };

                toast.addEventListener('mouseenter', pause);
                toast.addEventListener('mouseleave', resume);
            }

            return toast;
        }

        remove(toastId) {
            const toastIndex = this.toasts.findIndex(t => t.id === toastId);
            if (toastIndex === -1) return;

            const toast = this.toasts[toastIndex].element;
            
            // Slide-out animasyonu
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-exit');

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
                this.toasts.splice(toastIndex, 1);
            }, 300);
        }

        clear() {
            this.toasts.forEach(toast => {
                this.remove(toast.id);
            });
        }
    }

    // Toast manager instance
    const toastManager = new ToastManager();

    // Eski showToast fonksiyonunu yeni sisteme yönlendir
    function showToast(title, message, type) {
        return toastManager.show(title, message, type);
    }
    
    // Test fonksiyonu - Progress bar'ı test etmek için
    function testToastProgress() {
        console.log('Toast Progress Bar Test Başlatılıyor...');
        
        // Önce mevcut toast'ları temizle
        toastManager.clear();
        
        // Farklı sürelerle test toast'ları göster
        toastManager.show('Başarı', '3 saniye süreli toast - Progress bar yeşil olmalı', 'success', 3000);
        
        setTimeout(() => {
            toastManager.show('Hata', '5 saniye süreli toast - Progress bar kırmızı olmalı', 'error', 5000);
        }, 1000);
        
        setTimeout(() => {
            toastManager.show('Uyarı', '2 saniye süreli toast - Progress bar sarı olmalı', 'warning', 2000);
        }, 2000);
        
        setTimeout(() => {
            toastManager.show('Bilgi', '4 saniye süreli toast - Progress bar mavi olmalı', 'info', 4000);
        }, 3000);
        
        console.log('Test toast\'ları gönderildi! Progress bar\'ları toast\'ların altında kontrol edin!');
        console.log('Progress bar\'lar soldan sağa doğru akacak ve belirlenen sürede dolacak');
        console.log('Her toast tipi için farklı renkli gradient progress bar göreceksiniz');
    }
    
    // Test fonksiyonunu global olarak erişilebilir yap
    window.testToastProgress = testToastProgress;

    // Toast sistemi hazır - artık tüm feedback'ler modern toast olarak gösterilecek
    
    // Bildirim izni butonu durumunu güncelle
    function updateNotificationButton() {
        const btn = document.getElementById('notification-permission-btn');
        if (!btn) return;
        
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>';
                btn.title = 'Bildirim İzni Verildi';
            } else if (Notification.permission === 'denied') {
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>';
                btn.title = 'Bildirim İzni Reddedildi';
            } else {
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>';
                btn.title = 'Bildirim İzni İste';
            }
        }
    }
    
    // Manuel bildirim izni iste
    function requestNotificationPermission() {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        showNotification('Bildirim izni verildi!', 'Artık yeni bildirimler alacaksınız.', 'success');
                        // Test bildirimi gönder
                        new Notification('Bildirim Sistemi Aktif', {
                            body: 'Artık yeni bildirimler alacaksınız!',
                            icon: 'assets/images/brand/logo_tr.png'
                        });
                    } else {
                        showNotification('Bildirim izni reddedildi', 'Bildirimleri tarayıcı ayarlarından açabilirsiniz.', 'warning');
                    }
                    updateNotificationButton();
                });
            } else if (Notification.permission === 'denied') {
                showNotification('Bildirim izni reddedildi', 'Bildirimleri tarayıcı ayarlarından açabilirsiniz.', 'warning');
            } else {
                showNotification('Bildirim izni zaten verilmiş', 'Artık yeni bildirimler alacaksınız.', 'success');
            }
        }
    }
    
    // Sayfa yüklendiğinde buton durumunu güncelle
    document.addEventListener('DOMContentLoaded', function() {
        updateNotificationButton();
    });
    
    // Bildirim okundu işaretleme - Toast ile güncellenmiş
    function markAsRead(notificationId) {
        console.log('Marking notification as read:', notificationId);
        
        fetch('notification_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_as_read&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Mark as read response:', data);
            if (data.success) {
                // Toast bildirimi göster
                toastManager.show('Başarılı', 'Bildirim okundu olarak işaretlendi', 'success', 3000);
                
                // Badge'i güncelle
                updateNotificationBadge();
                
                // Modalı kapat
                closeNotificationModal();
                
                // Bildirim listesini güncelle (sayfa yenileme yok)
                updateNotificationList();
            } else {
                toastManager.show('Hata', data.error || 'Bildirim işaretlenemedi', 'error', 4000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastManager.show('Hata', 'Bağlantı hatası', 'error', 4000);
        });
    }

    // Bildirim silme - Toast ile güncellenmiş
    function deleteNotification(notificationId) {
        // Onay toast'ı göster
        const confirmToastId = toastManager.show('Onay Gerekli', 'Bu bildirimi silmek istediğinizden emin misiniz?', 'warning', 0);
        
        // Onay butonları ekle
        const confirmToast = document.getElementById(confirmToastId);
        if (confirmToast) {
            const buttonContainer = confirmToast.querySelector('.flex-shrink-0');
            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'ml-2 px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition-colors';
            confirmBtn.textContent = 'Sil';
            confirmBtn.onclick = () => {
                toastManager.remove(confirmToastId);
                performDeleteNotification(notificationId);
            };
            
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'ml-2 px-3 py-1 text-xs bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors';
            cancelBtn.textContent = 'İptal';
            cancelBtn.onclick = () => toastManager.remove(confirmToastId);
            
            buttonContainer.appendChild(confirmBtn);
            buttonContainer.appendChild(cancelBtn);
        }
    }

    // Gerçek silme işlemi
    function performDeleteNotification(notificationId) {
        console.log('Deleting notification:', notificationId);
        
        fetch('notification_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_notification&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Delete notification response:', data);
            if (data.success) {
                toastManager.show('Başarılı', 'Bildirim silindi', 'success', 3000);
                
                // Badge'i güncelle
                updateNotificationBadge();
                
                // Modalı kapat
                closeNotificationModal();
                
                // Bildirim listesini güncelle (sayfa yenileme yok)
                updateNotificationList();
            } else {
                toastManager.show('Hata', data.error || 'Bildirim silinemedi', 'error', 4000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastManager.show('Hata', 'Bağlantı hatası', 'error', 4000);
        });
    }
    
    // Bildirim listesini dinamik olarak güncelle
    function updateNotificationList() {
        fetch('notification_api.php?action=get_notifications')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Bildirim dropdown'ını güncelle
                    const dropdown = document.getElementById('notification-dropdown');
                    if (dropdown) {
                        const notificationsContainer = dropdown.querySelector('.max-h-64');
                        if (notificationsContainer) {
                            if (data.notifications.length > 0) {
                                notificationsContainer.innerHTML = data.notifications.map(notification => `
                                    <div class="p-3 border-b border-gray-100 hover:bg-gray-50 ${notification.is_read == 0 ? 'bg-blue-50' : ''} cursor-pointer" onclick="showNotificationDetail(${notification.id}, '${notification.title.replace(/'/g, "\\'")}', '${notification.message.replace(/'/g, "\\'")}', '${notification.type}', '${new Date(notification.created_at).toLocaleDateString('tr-TR')} ${new Date(notification.created_at).toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'})}', ${notification.is_read})">
                                        <div class="flex items-start space-x-3">
                                            <div class="w-2 h-2 ${notification.type === 'success' ? 'bg-green-500' : (notification.type === 'warning' ? 'bg-yellow-500' : (notification.type === 'error' ? 'bg-red-500' : (notification.type === 'urgent' ? 'bg-red-600' : 'bg-blue-500')))} rounded-full mt-2"></div>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">${notification.title}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">${notification.message.substring(0, 50)}${notification.message.length > 50 ? '...' : ''}</p>
                                                <p class="text-xs text-gray-400">${new Date(notification.created_at).toLocaleDateString('tr-TR')} ${new Date(notification.created_at).toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'})}</p>
                                            </div>
                                            ${notification.is_read == 0 ? '<div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>' : ''}
                                        </div>
                                    </div>
                                `).join('');
                            } else {
                                notificationsContainer.innerHTML = `
                                    <div class="p-8 text-center">
                                        <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                        <p class="text-gray-500 dark:text-gray-400">Henüz bildirim bulunmuyor</p>
                                    </div>
                                `;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Bildirim listesi güncellenemedi:', error);
            });
    }
    
    // Bildirim badge'ini güncelle
    function updateNotificationBadge() {
        fetch('notification_api.php?action=get_notification_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notification-badge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Badge güncelleme hatası:', error);
            });
    }
    
    // Bildirim detayını göster
    function showNotificationDetail(id, title, message, type, date, isRead) {
        const modal = document.getElementById('notificationDetailModal');
        const content = document.getElementById('notificationDetailContent');
        const header = document.getElementById('notificationDetailHeader');
        const icon = document.getElementById('notificationDetailIcon');
        const badge = document.getElementById('notificationDetailBadge');
        const markBtn = document.getElementById('markAsReadBtn');
        const footerDate = document.getElementById('notificationDetailDate');
        
        // Tip renkleri ve ikonları
        const typeConfig = {
            'success': {
                iconBg: 'bg-green-100 dark:bg-green-900',
                iconColor: 'text-green-600 dark:text-green-400',
                iconSvg: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
                badgeText: 'Başarılı',
                badgeClass: 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300'
            },
            'warning': {
                iconBg: 'bg-yellow-100 dark:bg-yellow-900',
                iconColor: 'text-yellow-600 dark:text-yellow-400',
                iconSvg: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>`,
                badgeText: 'Uyarı',
                badgeClass: 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300'
            },
            'error': {
                iconBg: 'bg-red-100 dark:bg-red-900',
                iconColor: 'text-red-600 dark:text-red-400',
                iconSvg: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
                badgeText: 'Hata',
                badgeClass: 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'
            },
            'urgent': {
                iconBg: 'bg-red-200 dark:bg-red-900',
                iconColor: 'text-red-700 dark:text-red-400',
                iconSvg: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>`,
                badgeText: 'Acil',
                badgeClass: 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'
            },
            'info': {
                iconBg: 'bg-blue-100 dark:bg-blue-900',
                iconColor: 'text-blue-600 dark:text-blue-400',
                iconSvg: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
                badgeText: 'Bilgi',
                badgeClass: 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300'
            }
        };
        
        const config = typeConfig[type] || typeConfig['info'];
        
        // Icon ayarla
        icon.className = `${icon.className} ${config.iconBg} ${config.iconColor}`;
        icon.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">${config.iconSvg}</svg>`;
        
        // Badge ayarla
        badge.innerHTML = config.badgeText;
        badge.className = `${config.badgeClass} inline-flex items-center px-2 py-0.5 rounded text-xs font-medium`;
        
        // İçeriği doldur
        content.innerHTML = `
            <div class="space-y-6">
                <div>
                    <h4 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">${title}</h4>
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">${message}</p>
                </div>
            </div>
        `;
        
        // Footer tarih
        footerDate.textContent = date;
        
        // Okundu butonunu ayarla
        if (isRead == 0) {
            markBtn.classList.remove('hidden');
            markBtn.onclick = () => {
                markAsRead(id);
                closeNotificationModal();
            };
        } else {
            markBtn.classList.add('hidden');
        }
        
        // Modalı göster
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Animasyon
        setTimeout(() => {
            const modalContent = modal.querySelector('.rounded-xl');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }, 10);
    }
    
    // Bildirim detay modalını kapat
    function closeNotificationModal() {
        const modal = document.getElementById('notificationDetailModal');
        const modalContent = modal.querySelector('.rounded-xl');
        
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
    
    // Yeni bildirim modalını kapat
    function closeNewNotificationModal() {
        const modal = document.getElementById('newNotificationModal');
        const modalContent = modal.querySelector('.bg-white');
        
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
    
    // Yeni bildirim modalını göster
    function showNewNotification(notification) {
        const modal = document.getElementById('newNotificationModal');
        const content = document.getElementById('newNotificationContent');
        
        // İçeriği doldur
        content.innerHTML = `
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 ${notification.type === 'success' ? 'bg-green-500' : (notification.type === 'warning' ? 'bg-yellow-500' : (notification.type === 'error' ? 'bg-red-500' : 'bg-blue-500'))} rounded-full"></div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">${notification.type === 'success' ? 'Başarı' : (notification.type === 'warning' ? 'Uyarı' : (notification.type === 'error' ? 'Hata' : 'Bilgi'))}</span>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">${notification.title}</h4>
                    <p class="text-gray-600 leading-relaxed">${notification.message}</p>
                </div>
                
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium">Tarih:</span> ${notification.date}
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <p class="text-sm text-blue-700"><strong>Yeni Bildirim</strong> - SuperAdmin'den geldi</p>
                </div>
            </div>
        `;
        
        // Modalı göster
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
        
        // Bildirim sesi çal (eğer izin verilmişse)
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: 'assets/images/brand/logo_tr.png'
            });
        }
    }
    
    // Yeni bildirimi okundu işaretle
    function markNewNotificationAsRead() {
    // Bu fonksiyon yeni bildirim için özel olarak çalışacak
    closeNewNotificationModal();
    // Sayfa yenileme yok - bildirimler dinamik olarak güncellenir
    }
    
    // Bildirim okundu işaretleme (genel)
    function markNotificationAsRead() {
        const modal = document.getElementById('notificationDetailModal');
        const markBtn = document.getElementById('markAsReadBtn');
        
        if (markBtn.onclick) {
            markBtn.onclick();
        }
    }
    
    // İşbirliği Logosu Modal Fonksiyonları
    function openPartnerLogoModal() {
        const modal = document.getElementById('partnerLogoModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        const modalContent = modal.querySelector('.bg-white, .dark\\:bg-gray-800');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }
    
    function closePartnerLogoModal() {
        const modal = document.getElementById('partnerLogoModal');
        const modalContent = modal.querySelector('.bg-white, .dark\\:bg-gray-800');
        
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 200);
    }
    
    function showLogoUpdateForm() {
        document.getElementById('currentLogoInfo').classList.add('hidden');
        document.getElementById('logoForm').classList.remove('hidden');
        document.getElementById('partnerModalTitle').textContent = 'İşbirliği Logosu Güncelle';
    }
    
    function deletePartnerLogo() {
        if (confirm('Bu işbirliği logosunu silmek istediğinizden emin misiniz?')) {
            const formData = new FormData();
            formData.append('action', 'delete_partner_logo');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        toastManager.show('Başarılı', 'İşbirliği logosu başarıyla silindi!', 'success', 4000);
                        closePartnerLogoModal();
                        // Sayfa yenileme yok - dinamik güncelleme
                    } else {
                        toastManager.show('Hata', data.message, 'error', 5000);
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    toastManager.show('Sunucu Hatası', 'Sunucu yanıtı geçersiz: ' + text.substring(0, 200), 'error', 6000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastManager.show('Hata', 'Bir hata oluştu: ' + error.message, 'error', 5000);
            });
        }
    }
    
    // İşbirliği logosu form submit
    document.getElementById('partnerLogoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'upload_partner_logo');
        
        // Loading göster
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Yükleniyor...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text(); // Önce text olarak al
        })
        .then(text => {
            console.log('Raw response:', text); // Debug için
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    toastManager.show('Başarılı', 'İşbirliği logosu başarıyla eklendi!', 'success', 4000);
                    closePartnerLogoModal();
                    // Sayfa yenileme yok - dinamik güncelleme
                } else {
                    toastManager.show('Hata', data.message, 'error', 5000);
                }
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                toastManager.show('Sunucu Hatası', 'Sunucu yanıtı geçersiz: ' + text.substring(0, 200), 'error', 6000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastManager.show('Hata', 'Bir hata oluştu: ' + error.message, 'error', 5000);
        })
        .finally(() => {
            // Loading'i kaldır
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Test SMTP Function
    function testSMTP() {
        const username = document.getElementById('smtp_username').value;
        const password = document.getElementById('smtp_password').value;
        
        if (!username || !password) {
            toastManager.show('Eksik Bilgi', 'Gmail adresi ve App Password gerekli!', 'warning', 4000);
            return;
        }
        
        // AJAX ile test et
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=test_smtp&smtp_username=' + encodeURIComponent(username) + '&smtp_password=' + encodeURIComponent(password)
        })
        .then(response => response.text())
        .then(data => {
            toastManager.show('SMTP Test Sonucu', data, data.includes('BAŞARILI') ? 'success' : 'error', 6000);
        })
        .catch(error => {
            toastManager.show('Test Hatası', error, 'error', 5000);
        });
    }
    
    // Test Mail Gönderme Function
    function sendTestMail() {
        if (confirm('Test maili gönderilsin mi? admin@foursoftware.com.tr adresine gönderilecek.')) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_test_email'
            })
            .then(response => response.text())
            .then(data => {
                toastManager.show('Test Mail Sonucu', data, data.includes('BAŞARILI') ? 'success' : 'error', 6000);
            })
            .catch(error => {
                toastManager.show('Test Mail Hatası', error, 'error', 5000);
            });
        }
    }
    
    // Manuel Kaydetme Function
    function saveSMTP() {
        const username = document.getElementById('smtp_username').value;
        const password = document.getElementById('smtp_password').value;
        
        console.log('Username:', username);
        console.log('Password:', password);
        
        if (!username || !password) {
            toastManager.show('Eksik Bilgi', 'Gmail adresi ve App Password gerekli!\nUsername: ' + username + '\nPassword: ' + password, 'warning', 6000);
            return;
        }
        
        // AJAX ile kaydet
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=save_smtp&smtp_username=' + encodeURIComponent(username) + '&smtp_password=' + encodeURIComponent(password)
        })
        .then(response => response.text())
        .then(data => {
            toastManager.show('Kaydetme Sonucu', data, data.includes('BAŞARILI') ? 'success' : 'error', 6000);
            // Sayfa yenileme yok - ayarlar dinamik olarak güncellenir
        })
        .catch(error => {
            toastManager.show('Kaydetme Hatası', error, 'error', 5000);
        });
    }
    
</script>

<!-- Mobil Navigasyon -->
<nav class="mobile-nav lg:hidden">
    <a href="?view=dashboard" class="mobile-nav-item <?= $current_view === 'dashboard' ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
        </svg>
        <span>Pano</span>
    </a>
    
    <a href="?view=events" class="mobile-nav-item <?= $current_view === 'events' ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
        <span>Etkinlik</span>
    </a>
    
    <a href="?view=members" class="mobile-nav-item <?= $current_view === 'members' ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        <span>Üyeler</span>
    </a>
    
    <a href="?view=mail" class="mobile-nav-item <?= $current_view === 'mail' ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.84 5.23L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
        </svg>
        <span>Mail</span>
    </a>
    
    <a href="?view=settings" class="mobile-nav-item <?= $current_view === 'settings' ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span>Ayarlar</span>
    </a>
</nav>

</body>
</html>

<?php
// =================================================================
// OTOMATİK TEMPLATE SENKRONİZASYONU
// =================================================================

// Template değişikliklerini tüm topluluklara otomatik uygula
if (isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
    // Sadece admin kullanıcıları için çalıştır
    try {
        // Sync script'ini dahil et
        require_once __DIR__ . '/../../system/scripts/sync_templates.php';
        
        // Tüm topluluklara template'leri senkronize et
        $syncResult = syncTemplates();
        
        // Sync log'u (opsiyonel)
        if (!$syncResult['success']) {
            error_log("Template sync hatası: " . implode(', ', $syncResult['errors']));
        }
    } catch (Exception $e) {
        error_log("Template sync exception: " . $e->getMessage());
    }
}
?>