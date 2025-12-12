<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - KULÜP YÖNETİM PANELİ - GİRİŞ SAYFASI
// =================================================================

// Global exception handler - EN BAŞTA (require'lardan önce)
set_exception_handler(function($exception) {
    // Basit logging (helper'lar yüklenmeden önce)
    $log_file = __DIR__ . '/../system/logs/php_errors.log';
    @error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine(), 3, $log_file);
    
    // Output buffer'ı temizle
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // JSON response (AJAX istekleri için)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Sunucu hatası oluştu. Lütfen tekrar deneyin.']);
        exit;
    }
    
    // HTML response (normal istekler için)
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Hata</title></head><body>';
    echo '<h1>Sunucu Hatası</h1>';
    echo '<p>Giriş işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.</p>';
    echo '<p><a href="login.php">Giriş sayfasına dön</a></p>';
    echo '</body></html>';
    exit;
});

// Error handler - EN BAŞTA
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $log_file = __DIR__ . '/../system/logs/php_errors.log';
    @error_log("PHP Error [$errno]: $errstr in $errfile on line $errline", 3, $log_file);
    
    // Fatal error'ları exception'a çevir
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    
    return false;
});

const SESSION_PERSISTENT_LIFETIME = 60 * 60 * 24 * 7; // 7 gün



// Session güvenlik ayarları
if (session_status() === PHP_SESSION_NONE) {
    $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $cookie_params = [
        'lifetime' => SESSION_PERSISTENT_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookie_params);
    } else {
        session_set_cookie_params($cookie_params['lifetime'], $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure'], $cookie_params['httponly']);
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $is_secure ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
    
    // CSRF token'ı önce oluştur (regenerate öncesi)
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Session fixation koruması
    if (!isset($_SESSION['initiated'])) {
        // CSRF token'ı sakla (regenerate sırasında kaybolmasın)
        $csrf_token_backup = $_SESSION['csrf_token'] ?? null;
        
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['login_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // CSRF token'ı geri yükle
        if ($csrf_token_backup !== null) {
            $_SESSION['csrf_token'] = $csrf_token_backup;
        }
    }
    
    // IP ve User-Agent değişikliği kontrolü (Session hijacking koruması)
    if (isset($_SESSION['login_ip']) || isset($_SESSION['login_user_agent'])) {
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Eğer IP veya User-Agent değiştiyse ve kullanıcı giriş yapmışsa, oturumu sonlandır
        if (isset($_SESSION['admin_id'])) {
            if ($_SESSION['login_ip'] !== $current_ip || $_SESSION['login_user_agent'] !== $current_ua) {
                // Kritik değişiklik - oturumu temizle
                session_destroy();
                session_start();
                $_SESSION['security_warning'] = 'Güvenlik nedeniyle oturum sonlandırıldı. Lütfen tekrar giriş yapın.';
                header('Location: login.php');
                exit;
            }
        }
    }
}

// AJAX mail gönderimi kontrolü (Session başladıktan sonra)


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/partials/logging.php';
require_once __DIR__ . '/partials/security_headers.php';
require_once __DIR__ . '/partials/db_security.php';
require_once __DIR__ . '/partials/inline_handler_bridge.php';
require_once __DIR__ . '/partials/schema_bootstrap.php';
tpl_inline_handler_transform_start();



// Security headers'ı ayarla (headers gönderilmeden önce)
set_security_headers();

// Community bootstrap'ı dahil et (topluluk klasörünü belirlemek için)
if (!defined('COMMUNITY_BASE_PATH')) {
    require_once __DIR__ . '/community_bootstrap.php';
}

// --- YAPILANDIRMA ---
// Her topluluk kendi veritabanını kullanmalı
if (defined('COMMUNITY_BASE_PATH')) {
    // Topluluk klasörü belirlenmişse, o klasördeki veritabanını kullan
    $DB_PATH = COMMUNITY_BASE_PATH . '/unipanel.sqlite';
} else {
    // Fallback: Mevcut script'in bulunduğu klasördeki veritabanını kullan
    $DB_PATH = __DIR__ . '/unipanel.sqlite';
    
    // Eğer mevcut klasör communities/ içindeyse, o topluluğun veritabanını kullan
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $normalized = str_replace('\\', '/', $script_dir);
    $needle = '/communities/';
    $pos = strpos($normalized, $needle);
    if ($pos !== false) {
        $after = substr($normalized, $pos + strlen($needle));
        $parts = explode('/', $after);
        if (!empty($parts[0])) {
            $community_path = dirname(__DIR__) . '/communities/' . $parts[0];
            if (is_dir($community_path)) {
                $DB_PATH = $community_path . '/unipanel.sqlite';
            }
        }
    }
}

// Sabit olarak tanımla (geriye dönük uyumluluk için)
if (!defined('DB_PATH')) {
    define('DB_PATH', $DB_PATH);
}
ensure_database_permissions(DB_PATH);

const CLUB_ID = 1;

// AJAX mail gönderimi kontrolü (CLUB_ID tanımlandıktan sonra)
if (isset($_POST['action']) && $_POST['action'] === 'send_login_mail_async') {
    try {
        // Output buffer'ı temizle
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Header'ları ayarla
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'error' => 'CSRF mismatch'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    if (isset($_SESSION['unsent_login_notification'])) {
        $mail_data = $_SESSION['unsent_login_notification'];
        
        // Loglama için
        $log_file = __DIR__ . '/../logs/custom_debug.log';
        $time = date('Y-m-d H:i:s');
        @file_put_contents($log_file, "[$time] [ASYNC_MAIL] Tetiklendi\n", FILE_APPEND);
        
        // Mail gönder
        send_login_notification_email($mail_data);
        
        // Session'dan sil
        unset($_SESSION['unsent_login_notification']);
        
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
        echo json_encode(['success' => false, 'error' => 'No pending mail'], JSON_UNESCAPED_UNICODE);
    exit;
    } catch (Exception $e) {
        // Output buffer'ı temizle
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Header'ları ayarla
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        
        tpl_error_log("Async mail gönderme hatası: " . $e->getMessage());
        tpl_error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Mail gönderilirken bir hata oluştu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
const LOGIN_SMS_COOLDOWN = 60; // seconds between verification SMS sends
const CODE_VERIFICATION_MAX_ATTEMPTS = 5; // Max yanlış kod denemesi
const CODE_VERIFICATION_LOCKOUT_TIME = 300; // 5 dakika lockout
const SESSION_TIMEOUT_EXTEND = 60 * 60 * 24 * 7; // 7 gün - kod ekranında session uzatma

// Güvenlik sabitleri
const MAX_LOGIN_ATTEMPTS_PER_HOUR = 5; // Saatlik maksimum deneme
const MAX_LOGIN_ATTEMPTS_PER_DAY = 15; // Günlük maksimum deneme
const IP_BLOCK_DURATION = 3600 * 24; // IP block süresi (24 saat)
const ACCOUNT_LOCKOUT_DURATION = 3600; // Hesap kilitleme süresi (1 saat)
const PROGRESSIVE_DELAY_BASE = 2; // Progressive delay başlangıç süresi (saniye)
const SUSPICIOUS_ACTIVITY_THRESHOLD = 10; // Şüpheli aktivite eşiği
const MAX_FAILED_ATTEMPTS_PER_USER = 5; // Kullanıcı bazlı maksimum başarısız deneme

function extend_session_cookie($lifetime = SESSION_PERSISTENT_LIFETIME) {
    if (PHP_SAPI === 'cli') {
        return;
    }
    $params = session_get_cookie_params();
    $options = [
        'expires' => time() + $lifetime,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $params['secure'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => $params['samesite'] ?? 'Strict'
    ];
    setcookie(session_name(), session_id(), $options);
}

/**
 * SQLite veritabanı dosyasını temizle ve onar
 * Disk I/O hatalarını önler
 */
function repair_database($db_path) {
    // WAL dosyalarını sil (bunlar bazen bozulabilir)
    $wal_file = $db_path . '-wal';
    $shm_file = $db_path . '-shm';
    
        if (file_exists($wal_file)) {
            @unlink($wal_file);
        }
        if (file_exists($shm_file)) {
            @unlink($shm_file);
        }
    
    // Veritabanı dosyası varsa ve bozuksa onar
    if (file_exists($db_path)) {
        try {
            // Integrity check yap
            $test_db = @new SQLite3($db_path);
            if ($test_db) {
                $integrity = $test_db->querySingle("PRAGMA integrity_check");
                $test_db->close();
                
                // Eğer integrity check başarısızsa, dosyayı yedekle ve yeniden oluştur
                if ($integrity !== 'ok') {
                    // Yedek al
                    $backup_path = $db_path . '.backup.' . date('Y-m-d_His');
                    @copy($db_path, $backup_path);
                    
                    // Bozuk dosyayı sil ve yeniden oluştur
                    @unlink($db_path);
                    @touch($db_path);
                    @chmod($db_path, 0600);
                }
            }
        } catch (Exception $e) {
            // Hata durumunda dosyayı yedekle ve yeniden oluştur
            if (file_exists($db_path)) {
                $backup_path = $db_path . '.backup.' . date('Y-m-d_His');
                @copy($db_path, $backup_path);
                @unlink($db_path);
            }
            @touch($db_path);
            @chmod($db_path, 0600);
        }
    }
    
    // İzinleri tekrar düzelt
    ensure_database_permissions($db_path);
}

// get_db wrapper for compatibility with communication.php
function get_db() {
    if (!defined('DB_PATH')) {
        throw new Exception("DB_PATH defined değil!");
    }
    return get_sqlite_connection(DB_PATH);
}

/**
 * SQLite veritabanı bağlantısı oluştur (izin kontrolü ve hata yönetimi ile)
 * Disk I/O hatalarını önler ve otomatik recovery yapar
 */
function get_sqlite_connection($db_path) {
    // Veritabanı dosyası yoksa ve oluşturulmamışsa hata ver
    if (!file_exists($db_path)) {
        throw new Exception("Veritabanı dosyası bulunamadı: $db_path");
    }
    
    $max_retries = 3;
    $retry_count = 0;
    
    while ($retry_count < $max_retries) {
        try {
            // İzinleri düzelt
            ensure_database_permissions($db_path);
            
            // Veritabanı bağlantısı oluştur
            $db = new SQLite3($db_path);
            $db->enableExceptions(true);
            
            // WAL mode yerine DELETE mode kullan (izin sorunlarını önler)
            // WAL mode bazen readonly hatasına neden olabilir
            @$db->exec('PRAGMA journal_mode = DELETE');
            
            // Busy timeout ayarla (eşzamanlı erişim sorunlarını önler)
            @$db->busyTimeout(5000); // 5 saniye
            
            // Integrity check yap (hızlı)
            $integrity = @$db->querySingle("PRAGMA quick_check");
            if ($integrity !== null && $integrity !== 'ok') {
                $db->close();
                throw new Exception("Veritabanı integrity check başarısız");
            }
            
            return $db;
            
        } catch (Exception $e) {
            $retry_count++;
            
            // Disk I/O error veya integrity hatası varsa, veritabanını onar
            if (strpos($e->getMessage(), 'disk I/O error') !== false || 
                strpos($e->getMessage(), 'database disk image is malformed') !== false ||
                strpos($e->getMessage(), 'integrity') !== false) {
                
                // Veritabanını onar
                repair_database($db_path);
                
                // Kısa bir bekleme (dosya sistemi işlemlerinin tamamlanması için)
                usleep(100000); // 0.1 saniye
                
                // Son deneme
                if ($retry_count >= $max_retries) {
                    // Son deneme: Basit bir bağlantı dene
                    try {
                        $db = new SQLite3($db_path);
                        $db->enableExceptions(true);
                        @$db->exec('PRAGMA journal_mode = DELETE');
                        @$db->busyTimeout(5000);
                        return $db;
                    } catch (Exception $e3) {
                        // Tüm denemeler başarısız - kullanıcıya bilgi ver
                        tpl_error_log("SQLite bağlantı hatası (3 deneme sonrası): " . $e3->getMessage() . " - Dosya: $db_path");
                        throw new Exception("Veritabanı bağlantısı kurulamadı. Lütfen sistem yöneticisine başvurun.");
                    }
                }
                
                continue; // Tekrar dene
            }
            
            // Diğer hatalar için izinleri düzelt ve tekrar dene
            if ($retry_count < $max_retries) {
                ensure_database_permissions($db_path);
                usleep(50000); // 0.05 saniye bekle
                continue;
            }
            
            // Son deneme başarısız
            tpl_error_log("SQLite bağlantı hatası: " . $e->getMessage() . " - Dosya: $db_path");
            throw new Exception("Veritabanı bağlantısı başarısız: " . $e->getMessage());
        }
    }
    
    // Buraya gelmemeli ama yine de güvenlik için
    throw new Exception("Veritabanı bağlantısı kurulamadı");
}

// Topluluk adını al
function get_club_name() {
    try {
        // Önce topluluk veritabanından dene
        if (defined('DB_PATH') && file_exists(DB_PATH)) {
            try {
                $db = get_sqlite_connection(DB_PATH);
                $name = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'club_name'");
                $db->close();
                if ($name) {
                    return $name;
                }
            } catch (Exception $e) {
                // Veritabanı hatası, devam et
            }
        }
        
        // Veritabanı yoksa veya isim bulunamadıysa, superadmin veritabanından dene
        $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
        $normalized = str_replace('\\', '/', $script_dir);
        $needle = '/communities/';
        $pos = strpos($normalized, $needle);
        
        if ($pos !== false) {
            $after = substr($normalized, $pos + strlen($needle));
            $parts = explode('/', $after);
            $folder_name = $parts[0] ?? '';
            
            if (!empty($folder_name)) {
                $superadmin_db = dirname(__DIR__) . '/unipanel.sqlite';
                if (file_exists($superadmin_db)) {
                    $super_db = new SQLite3($superadmin_db);
                    $super_db->exec('PRAGMA journal_mode = WAL');
                    $super_db->exec("CREATE TABLE IF NOT EXISTS community_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, community_name TEXT NOT NULL, folder_name TEXT NOT NULL, university TEXT NOT NULL, admin_username TEXT NOT NULL, admin_password_hash TEXT NOT NULL, admin_email TEXT, status TEXT DEFAULT 'pending', admin_notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME, processed_by TEXT)");
                    
                    $check_stmt = $super_db->prepare("SELECT community_name FROM community_requests WHERE folder_name = ? ORDER BY created_at DESC LIMIT 1");
                    $check_stmt->bindValue(1, $folder_name, SQLITE3_TEXT);
                    $result = $check_stmt->execute();
                    $request = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if ($request && !empty($request['community_name'])) {
                        $super_db->close();
                        return $request['community_name'];
                    }
                    $super_db->close();
                }
            }
        }
        
        return 'UniPanel Kulübü';
    } catch (Exception $e) {
        return 'UniPanel Kulübü';
    }
}

// Partner logolarını al
function get_partner_logos() {
    try {
        // DB_PATH tanımlı değilse veya dosya yoksa boş döndür
        if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
            return [];
        }
        
        $db = get_sqlite_connection(DB_PATH);
        
        // Partner logos tablosunu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS partner_logos (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            partner_name TEXT NOT NULL,
            partner_website TEXT,
            logo_path TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $db->prepare("SELECT * FROM partner_logos WHERE club_id = ? ORDER BY created_at DESC");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $logos = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logos[] = $row;
        }
        
        $db->close();
        return $logos;
    } catch (Exception $e) {
        return [];
    }
}

// Topluluk onay durumunu kontrol et
$community_pending = false;
$community_pending_message = '';
$community_disabled = false;
$community_disabled_message = '';
$folder_name = '';

// DEBUG: Form'un render edildiğinden emin ol
$debug_form_visible = true;

try {
    // Topluluk klasör adını bul
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $normalized = str_replace('\\', '/', $script_dir);
    $needle = '/communities/';
    $pos = strpos($normalized, $needle);
    
    if ($pos !== false) {
        $after = substr($normalized, $pos + strlen($needle));
        $parts = explode('/', $after);
        $folder_name = $parts[0] ?? '';
        
        if (!empty($folder_name)) {
            // Veritabanı dosyası var mı kontrol et
            $db_path = dirname(__DIR__) . '/communities/' . $folder_name . '/unipanel.sqlite';
            
            if (!file_exists($db_path)) {
                // Veritabanı yoksa, superadmin veritabanında talep durumunu kontrol et
                $superadmin_db = dirname(__DIR__) . '/unipanel.sqlite';
                if (file_exists($superadmin_db)) {
                    $super_db = new SQLite3($superadmin_db);
                    $super_db->exec('PRAGMA journal_mode = WAL');
                    $super_db->exec("CREATE TABLE IF NOT EXISTS community_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, community_name TEXT NOT NULL, folder_name TEXT NOT NULL, university TEXT NOT NULL, admin_username TEXT NOT NULL, admin_password_hash TEXT NOT NULL, admin_email TEXT, status TEXT DEFAULT 'pending', admin_notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME, processed_by TEXT)");
                    
                    $check_stmt = $super_db->prepare("SELECT status, community_name, admin_notes FROM community_requests WHERE folder_name = ? ORDER BY created_at DESC LIMIT 1");
                    $check_stmt->bindValue(1, $folder_name, SQLITE3_TEXT);
                    $result = $check_stmt->execute();
                    $request = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if ($request) {
                        if ($request['status'] === 'pending') {
                            $community_pending = true;
                            $community_pending_message = $request['community_name'] . ' topluluğunuz henüz onay bekliyor. Superadmin onayından sonra giriş yapabileceksiniz.';
                        } elseif ($request['status'] === 'rejected') {
                            $community_pending = true;
                            $community_pending_message = $request['community_name'] . ' topluluğunuz için kayıt talebiniz reddedilmiştir. ' . (!empty($request['admin_notes']) ? 'Sebep: ' . htmlspecialchars($request['admin_notes']) : '');
                        }
                    } else {
                        // Talep kaydı yoksa da beklemede olabilir
                        $community_pending = true;
                        $community_pending_message = 'Topluluğunuz henüz oluşturulmamış. Lütfen superadmin onayını bekleyin.';
                    }
                    
                    $super_db->close();
                } else {
                    // Superadmin veritabanı yoksa, veritabanı dosyası da yoksa beklemede
                    $community_pending = true;
                    $community_pending_message = 'Topluluğunuz henüz oluşturulmamış. Lütfen superadmin onayını bekleyin.';
                }
            } else {
                // Veritabanı varsa, topluluk durumunu kontrol et
                try {
                    $db = new SQLite3($db_path);
                    $db->exec('PRAGMA journal_mode = WAL');
                    
                    // Settings tablosundan status değerini al
                    $status = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'status'");
                    if ($status === 'inactive' || $status === 'disabled') {
                        $community_disabled = true;
                        $club_name_temp = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'club_name'") ?: $folder_name;
                        $community_disabled_message = htmlspecialchars($club_name_temp) . ' topluluğu şu anda pasif durumda. Giriş yapabilmek için topluluğun aktif hale getirilmesi gerekmektedir.';
                    }
                    
                    $db->close();
                } catch (Exception $e) {
                    // Hata durumunda devam et
                }
            }
        }
    }
} catch (Exception $e) {
    // Hata durumunda devam et
}

$club_name = get_club_name();
$partner_logos = [];
// Partner logolarını sadece veritabanı varsa yükle
if (!$community_pending && defined('DB_PATH') && file_exists(DB_PATH)) {
    try {
        $partner_logos = get_partner_logos();
    } catch (Exception $e) {
        // Hata durumunda boş array
    }
}

// Eğer zaten giriş yapılmışsa, ana sayfaya yönlendir
if (isset($_SESSION['admin_id'])) {
                // Output buffer'ı temizle ve redirect yap
                if (ob_get_level()) {
                    ob_clean();
                }
    header("Location: index.php");
    exit;
}

// Otomatik giriş (şifresiz erişim) - GÜVENLİK NEDENİYLE KALDIRILDI
// if (isset($_GET['auto_access']) ... ) { ... }

/**
 * Mevcut topluluk için SMS doğrulama kodunun gönderileceği telefon numarasını döndürür.
 * Öncelik: SuperAdmin community_requests.admin_phone > topluluk settings tablosundaki çeşitli alanlar.
 */
function login_get_setting(SQLite3 $db, $key, $default = '', $use_club_id = true) {
    static $meta = [];
    static $cache = [];
    
    $handle = spl_object_hash($db);
    
    if (!isset($meta[$handle])) {
        $meta[$handle] = [
            'table_exists' => false,
            'has_club_id' => false
        ];
        $table_exists = @$db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        if ($table_exists) {
            $meta[$handle]['table_exists'] = true;
            $table_info = @$db->query("PRAGMA table_info(settings)");
            if ($table_info) {
                while ($col = $table_info->fetchArray(SQLITE3_ASSOC)) {
                    if (($col['name'] ?? '') === 'club_id') {
                        $meta[$handle]['has_club_id'] = true;
                        break;
                    }
                }
            }
        }
    }
    
    if (!$meta[$handle]['table_exists']) {
        return $default;
    }
    
    $cache_key = $handle . '|' . ($use_club_id && $meta[$handle]['has_club_id'] ? CLUB_ID : 'global') . '|' . $key;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }
    
    $value = $default;
    try {
        if ($meta[$handle]['has_club_id'] && $use_club_id) {
            $stmt = @$db->prepare("SELECT setting_value FROM settings WHERE club_id = :club AND setting_key = :key ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':club', CLUB_ID, SQLITE3_INTEGER);
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    if ($row && isset($row['setting_value'])) {
                        $value = $row['setting_value'];
                    }
                }
            }
        } else {
            $stmt = @$db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    if ($row && isset($row['setting_value'])) {
                        $value = $row['setting_value'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // ignore
    }
    
    $cache[$cache_key] = $value !== '' ? $value : $default;
    return $cache[$cache_key];
}


function resolve_verification_phone(SQLite3 $db) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    
    $phone_candidates = [];
    
    // Topluluk klasör adını bul
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $normalized = str_replace('\\', '/', $script_dir);
    $needle = '/communities/';
    $pos = strpos($normalized, $needle);
    $folder_name = '';
    
    if ($pos !== false) {
        $after = substr($normalized, $pos + strlen($needle));
        $parts = explode('/', $after);
        $folder_name = $parts[0] ?? '';
    }
    
    if (!empty($folder_name)) {
        $superadmin_db = dirname(__DIR__) . '/unipanel.sqlite';
        if (file_exists($superadmin_db)) {
            try {
                $super_db = new SQLite3($superadmin_db);
                if (!$super_db) {
                    throw new Exception('Superadmin DB bağlantısı oluşturulamadı');
                }
                @$super_db->exec('PRAGMA journal_mode = WAL');
                
                // admin_phone kolonunu garanti et
                try {
                $table_info = @$super_db->query("PRAGMA table_info(community_requests)");
                $has_admin_phone = false;
                if ($table_info) {
                    while ($col = $table_info->fetchArray(SQLITE3_ASSOC)) {
                        if (($col['name'] ?? '') === 'admin_phone') {
                            $has_admin_phone = true;
                            break;
                        }
                    }
                }
                if (!$has_admin_phone) {
                    try {
                        @$super_db->exec("ALTER TABLE community_requests ADD COLUMN admin_phone TEXT");
                    } catch (Exception $e) {
                        // ignore
                    }
                }
                
                $check_stmt = @$super_db->prepare("SELECT admin_phone FROM community_requests WHERE folder_name = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
                if ($check_stmt) {
                        try {
                    $check_stmt->bindValue(1, $folder_name, SQLITE3_TEXT);
                    $result = $check_stmt->execute();
                    if ($result) {
                        $request = $result->fetchArray(SQLITE3_ASSOC);
                        if (!empty($request['admin_phone'])) {
                            $phone_candidates[] = $request['admin_phone'];
                        }
                    }
                        } catch (Exception $e) {
                            // ignore prepare/execute errors
                        }
                    }
                } catch (Exception $e) {
                    // ignore table operations
                }
                
                $super_db->close();
            } catch (Exception $e) {
                // ignore superadmin DB errors
                tpl_error_log("Superadmin DB error in resolve_verification_phone: " . $e->getMessage());
            }
        }
    }
    
    // Topluluk ayarlarından telefon al
    $setting_keys = ['president_phone', 'contact_phone', 'admin_phone', 'admin_phone_number'];
    foreach ($setting_keys as $key) {
        try {
        $val = login_get_setting($db, $key, '');
        if (!empty($val)) {
            $phone_candidates[] = $val;
            }
        } catch (Exception $e) {
            // ignore setting errors
            tpl_error_log("login_get_setting error for $key: " . $e->getMessage());
        }
    }
    
    foreach ($phone_candidates as $raw) {
        $digits = preg_replace('/\D+/', '', (string)$raw);
        if (empty($digits)) {
            continue;
        }
        if (strpos($digits, '0090') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '90') === 0 && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        } elseif ($digits[0] === '0' && strlen($digits) > 10) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $cached = '0' . $digits;
            return $cached;
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $cached = $digits;
            return $cached;
        }
    }
    
    $cached = '';
    return '';
}




function send_login_verification_sms(SQLite3 $db, $admin_phone, $verification_code) {
    try {
        require_once __DIR__ . '/functions/communication.php';
        
        // Static cache - aynı request içinde tekrar kullanılabilir
        static $cached_credentials = null;
        static $functions_loaded = false;
        
        // Fonksiyonları sadece bir kez yükle (superadmin gibi)
        if (!$functions_loaded) {
            if (!function_exists('send_sms_netgsm')) {
                require_once __DIR__ . '/functions/communication.php';
            }
            $functions_loaded = true;
        }
        
        if ($cached_credentials === null) {
            // Öncelik 1: Superadmin config'den oku (superadmin gibi)
            $netgsm_username = '';
            $netgsm_password = '';
            $netgsm_msgheader = '';
            
            $superadminConfigPath = dirname(__DIR__) . '/superadmin/config.php';
            if (file_exists($superadminConfigPath)) {
                try {
                    $superadminConfig = require $superadminConfigPath;
                    if (isset($superadminConfig['netgsm']) && is_array($superadminConfig['netgsm'])) {
                        $netgsmConfig = $superadminConfig['netgsm'];
                        $netgsm_username = trim((string)($netgsmConfig['user'] ?? ''));
                        $netgsm_password = trim((string)($netgsmConfig['pass'] ?? ''));
                        $netgsm_msgheader = trim((string)($netgsmConfig['header'] ?? ''));
                    }
                } catch (Exception $e) {
                    tpl_error_log("Superadmin config read error: " . $e->getMessage());
                }
            }
            
            // Öncelik 2: get_netgsm_credential kullan (fallback)
            if (empty($netgsm_username) || empty($netgsm_password)) {
                try {
                    if (empty($netgsm_username)) {
            $netgsm_username = get_netgsm_credential('username');
                    }
                    if (empty($netgsm_password)) {
            $netgsm_password = get_netgsm_credential('password');
                    }
                    if (empty($netgsm_msgheader)) {
            $netgsm_msgheader = get_netgsm_credential('msgheader');
                    }
                } catch (Exception $e) {
                    tpl_error_log("get_netgsm_credential error: " . $e->getMessage());
                }
            }
            
            // Öncelik 3: Database'den çek (son fallback)
            if (empty($netgsm_username)) {
                try {
                $netgsm_username = login_get_setting($db, 'netgsm_username', '');
                } catch (Exception $e) {
                    // ignore
                }
            }
            if (empty($netgsm_password)) {
                try {
                $netgsm_password = login_get_setting($db, 'netgsm_password', '');
                } catch (Exception $e) {
                    // ignore
                }
            }
            if (empty($netgsm_msgheader)) {
                try {
                $netgsm_msgheader = login_get_setting($db, 'netgsm_msgheader', '');
                } catch (Exception $e) {
                    // ignore
                }
            }
            
            // Cache'e kaydet
            $cached_credentials = [
                'username' => $netgsm_username,
                'password' => $netgsm_password,
                'msgheader' => $netgsm_msgheader
            ];
        } else {
            // Cache'den al
            $netgsm_username = $cached_credentials['username'];
            $netgsm_password = $cached_credentials['password'];
            $netgsm_msgheader = $cached_credentials['msgheader'];
        }
        
        // Hala boşsa hata döndür
        if (empty($netgsm_username) || empty($netgsm_password)) {
            tpl_error_log("NetGSM credentials missing");
            return ['success' => false, 'error' => 'NetGSM ayarları eksik. Lütfen superadmin/config.php veya config/credentials.php dosyasını kontrol edin.'];
        }
        
        // msgheader yoksa username kullan
        if (empty($netgsm_msgheader)) {
            $netgsm_msgheader = $netgsm_username;
        }
        
        // Club name'i al (mesaj için)
        try {
        $club_name = login_get_setting($db, 'club_name', 'UniPanel', false);
        } catch (Exception $e) {
            $club_name = 'UniPanel';
        }
        
        $message = sprintf(
            "UniFour Güvenli Giriş Kodunuz: %s. Bu kod %s hesabınız için olup 10 dakika geçerlidir. Kimseyle paylaşmayın.",
            $verification_code,
            $club_name
        );
        
        // NetGSM ile gönder - superadmin gibi direkt çağır
        $result = send_sms_netgsm($admin_phone, $message, $netgsm_username, $netgsm_password, $netgsm_msgheader);
        
        if (!$result || empty($result['success'])) {
            $error_msg = $result['error'] ?? 'NetGSM SMS gönderimi başarısız oldu.';
            tpl_error_log("NetGSM SMS Error: " . $error_msg);
            return ['success' => false, 'error' => $error_msg];
        }
        
        return $result;
    } catch (Exception $e) {
        tpl_error_log("send_login_verification_sms Exception: " . $e->getMessage());
        tpl_error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'error' => 'SMS gönderiminde hata: ' . $e->getMessage()];
    } catch (Throwable $e) {
        tpl_error_log("send_login_verification_sms Fatal Error: " . $e->getMessage());
        tpl_error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'error' => 'SMS gönderiminde kritik hata: ' . $e->getMessage()];
    }
}


function login_sms_cooldown_remaining() {
    if (!isset($_SESSION['verification_code_last_sent'])) {
        return 0;
    }
    $elapsed = time() - (int)$_SESSION['verification_code_last_sent'];
    $remaining = LOGIN_SMS_COOLDOWN - $elapsed;
    return $remaining > 0 ? $remaining : 0;
}

function generate_login_verification_code(): string {
    return (string)random_int(100000, 999999);
}

function resolve_president_email(SQLite3 $db) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    
    $email_candidates = [];
    
    // Topluluk klasör adını bul
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $normalized = str_replace('\\', '/', $script_dir);
    $needle = '/communities/';
    $pos = strpos($normalized, $needle);
    $folder_name = '';
    
    if ($pos !== false) {
        $after = substr($normalized, $pos + strlen($needle));
        $parts = explode('/', $after);
        $folder_name = $parts[0] ?? '';
    }
    
    if (!empty($folder_name)) {
        $superadmin_db = dirname(__DIR__) . '/unipanel.sqlite';
        if (file_exists($superadmin_db)) {
            try {
                $super_db = new SQLite3($superadmin_db);
                @$super_db->exec('PRAGMA journal_mode = WAL');
                
                // admin_email kolonunu kontrol et
                $table_info = @$super_db->query("PRAGMA table_info(community_requests)");
                $has_admin_email = false;
                if ($table_info) {
                    while ($col = $table_info->fetchArray(SQLITE3_ASSOC)) {
                        if (($col['name'] ?? '') === 'admin_email') {
                            $has_admin_email = true;
                            break;
                        }
                    }
                }
                
                if ($has_admin_email) {
                    $check_stmt = @$super_db->prepare("SELECT admin_email FROM community_requests WHERE folder_name = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
                    if ($check_stmt) {
                        $check_stmt->bindValue(1, $folder_name, SQLITE3_TEXT);
                        $result = $check_stmt->execute();
                        if ($result) {
                            $request = $result->fetchArray(SQLITE3_ASSOC);
                            if (!empty($request['admin_email'])) {
                                $email_candidates[] = $request['admin_email'];
                            }
                        }
                    }
                }
                
                $super_db->close();
            } catch (Exception $e) {
                // ignore
            }
        }
    }
    
    // Topluluk ayarlarından email al
    $setting_keys = ['president_email', 'contact_email', 'admin_email', 'smtp_username'];
    foreach ($setting_keys as $key) {
        $val = login_get_setting($db, $key, '');
        if (!empty($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $email_candidates[] = $val;
        }
    }
    
    foreach ($email_candidates as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $cached = $email;
            return $cached;
        }
    }
    
    $cached = '';
    return '';
}

// Giriş bildirimi mail gönderme fonksiyonu
function send_login_notification_email(array $mail_data): void {
    $log_file = __DIR__ . '/../logs/custom_debug.log';
    $log = function($msg) use ($log_file) {
        $time = date('Y-m-d H:i:s');
        @file_put_contents($log_file, "[$time] $msg\n", FILE_APPEND);
    };

    try {
        // DEBUG: Fonksiyon başladı
        $log('[LOGIN_NOTIFICATION] send_login_notification_email tetiklendi.');

        if (!file_exists(__DIR__ . '/functions/communication.php')) {
            $log('[LOGIN_NOTIFICATION] communication.php bulunamadı!');
            return;
        }
        
        $log('[LOGIN_NOTIFICATION] communication.php dahil ediliyor...');
        require_once __DIR__ . '/functions/communication.php';
        $log('[LOGIN_NOTIFICATION] communication.php dahil edildi.');
        
        if (!class_exists('SQLite3')) {
            $log('[LOGIN_NOTIFICATION] FATAL: SQLite3 sınıfı bulunamadı!');
            return;
        }

        if (!function_exists('get_sqlite_connection')) {
            $log('[LOGIN_NOTIFICATION] FATAL: get_sqlite_connection fonksiyonu tanımlı değil!');
            // Fallback definition check
            return;
        }

        $log('[LOGIN_NOTIFICATION] DB bağlantısı kuruluyor: ' . $mail_data['db_path']);
        $db_settings = get_sqlite_connection($mail_data['db_path']);
        if (!$db_settings) {
            $log('[LOGIN_NOTIFICATION] DB bağlantısı kurulamadı (null döndü)!');
            return;
        }
        $log('[LOGIN_NOTIFICATION] DB bağlantısı başarılı.');
        
        // Başkanın emailini bul
        $president_email = resolve_president_email($db_settings);
        $log('[LOGIN_NOTIFICATION] Bulunan başkan emaili: ' . ($president_email ?: 'YOK'));
        
        // Eğer başkan emaili bulunamazsa, fallback olarak smtp_username kullan (genellikle admin mailidir)
        if (empty($president_email)) {
            $president_email = login_get_setting($db_settings, 'smtp_username', '');
            $log('[LOGIN_NOTIFICATION] Fallback email (smtp_username): ' . ($president_email ?: 'YOK'));
        }
        
        // Hala yoksa logla ve çık
        if (empty($president_email) || !filter_var($president_email, FILTER_VALIDATE_EMAIL)) {
            $log('[LOGIN_NOTIFICATION] Geçerli bir alıcı emaili bulunamadı. İşlem iptal.');
            $db_settings->close();
            return;
        }
        
        $club_name = login_get_setting($db_settings, 'club_name', 'Topluluk');
        $smtp_from_name = login_get_setting($db_settings, 'smtp_from_name', 'UniFour');
        $smtp_from_email = login_get_setting($db_settings, 'smtp_from_email', '');
        $smtp_username = login_get_setting($db_settings, 'smtp_username', '');
        
        if (empty($smtp_from_email)) {
            $smtp_from_email = $smtp_username;
        }
        
        $smtp_host = login_get_setting($db_settings, 'smtp_host', '');
        $smtp_port = login_get_setting($db_settings, 'smtp_port', '587');
        $smtp_secure = login_get_setting($db_settings, 'smtp_secure', 'tls');
        $smtp_password = login_get_setting($db_settings, 'smtp_password', '');
        $db_settings->close();
        
        // SMTP ayarları kontrolü
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            $log('[LOGIN_NOTIFICATION] SMTP ayarları eksik! Host: ' . $smtp_host . ', User: ' . $smtp_username);
            return;
        }
        
        // Mail içeriği hazırla
        $subject = 'Giriş Bildirimi - ' . $club_name;
        $message = "
        <h2>Giriş Bildirimi</h2>
        <p>Merhaba,</p>
        <p><strong>{$club_name}</strong> topluluğunun yönetim paneline giriş yapıldı.</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='margin-top: 0;'>Giriş Detayları:</h3>
            <p><strong>Kullanıcı Adı:</strong> " . htmlspecialchars($mail_data['admin_username'], ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>Giriş Zamanı:</strong> " . htmlspecialchars($mail_data['login_time'], ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>IP Adresi:</strong> " . htmlspecialchars($mail_data['login_ip'], ENT_QUOTES, 'UTF-8') . "</p>
            <p><strong>Cihaz:</strong> " . htmlspecialchars(substr($mail_data['user_agent'], 0, 100), ENT_QUOTES, 'UTF-8') . "</p>
        </div>
        
        <p style='color: #666; font-size: 12px;'>Bu otomatik bir bildirimdir. Eğer bu girişi siz yapmadıysanız, lütfen hemen şifrenizi değiştirin ve güvenlik ekibinize bildirin.</p>
        ";
        
        // Socket timeout ayarla - 5 saniye yapalım (kullanıcıyı bekletmeyelim)
        $old_timeout = @ini_get('default_socket_timeout');
        if ($old_timeout === false) {
            $old_timeout = 60;
        }
        @ini_set('default_socket_timeout', 5);
        
        $log('[LOGIN_NOTIFICATION] Mail gönderimi başlıyor... Alıcı: ' . $president_email . ' (Timeout: 5s)');
        
        // Mail gönder - Hata olsa bile devam etsin diye @ koyuyoruz
        $result = @send_smtp_mail($president_email, $subject, $message, $smtp_from_name, $smtp_from_email, [
            'host' => $smtp_host,
            'port' => (int)$smtp_port,
            'secure' => $smtp_secure,
            'username' => $smtp_username,
            'password' => $smtp_password,
            'timeout' => 5 // Explicit timeout for send_smtp_mail
        ]);
        
        if ($result) {
            $log('[LOGIN_NOTIFICATION] Mail başarıyla gönderildi.');
        } else {
            $log('[LOGIN_NOTIFICATION] Mail gönderimi BAŞARISIZ oldu veya zaman aşımına uğradı.');
        }
        
        // Timeout'u geri yükle
        @ini_set('default_socket_timeout', $old_timeout);
        
    } catch (Exception $e) {
        // Hata logla ama sessizce devam et
        $log('[LOGIN_NOTIFICATION] Mail gönderme hatası (Exception): ' . $e->getMessage());
    } catch (Error $e) {
        $log('[LOGIN_NOTIFICATION] Mail gönderme hatası (Fatal Error): ' . $e->getMessage());
    }
}

// Doğrulama kodu gönderme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_verification_code') {
    try {
        // Output buffer'ı temizle
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Header'ları ayarla
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $db = get_sqlite_connection(DB_PATH);
        $remaining = login_sms_cooldown_remaining();
        if ($remaining > 0) {
            $db->close();
            echo json_encode(['success' => false, 'error' => 'Güvenlik nedeniyle yeni kod göndermeden önce lütfen ' . $remaining . ' saniye bekleyin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $admin_phone = resolve_verification_phone($db);
        
        if (empty($admin_phone)) {
        $db->close();
            echo json_encode(['success' => false, 'error' => 'Telefon numarası bulunamadı. Lütfen başkan telefonunu ayarlardan ekleyin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $verification_code = generate_login_verification_code();
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['verification_code_time'] = time();
        
        $send_result = send_login_verification_sms($db, $admin_phone, $verification_code);
        $db->close();
        
        if (!$send_result || empty($send_result['success'])) {
            $error_msg = $send_result['error'] ?? 'SMS gönderilemedi. Lütfen SMS ayarlarını kontrol edin.';
            echo json_encode(['success' => false, 'error' => $error_msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $_SESSION['verification_code_last_sent'] = time();
        
        echo json_encode(['success' => true, 'message' => 'Doğrulama kodu gönderildi'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        // Output buffer'ı temizle
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Header'ları ayarla
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        
        tpl_error_log("SMS doğrulama kodu gönderme hatası: " . $e->getMessage());
        tpl_error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Doğrulama kodu gönderilirken bir hata oluştu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}


// CSRF Token Fonksiyonları (Eğer tanımlı değilse)
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        // Session başlatılmış mı kontrol et
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        // Session başlatılmış mı kontrol et
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($token)) {
            tpl_error_log("CSRF token verify failed: token is empty");
            return false;
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            tpl_error_log("CSRF token verify failed: session token not set");
            return false;
        }
        
        $result = hash_equals($_SESSION['csrf_token'], $token);
        if (!$result) {
            // Debug için log
            tpl_error_log("CSRF token mismatch: session_token=" . substr($_SESSION['csrf_token'], 0, 10) . "...", "received_token=" . substr($token, 0, 10) . "...");
        }
        return $result;
    }
}

// Doğrulama kodu kontrolü ve giriş
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    // CSRF kontrolü
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        $_SESSION['show_verification'] = true;
    } else {
        $verification_code = trim($_POST['verification_code'] ?? '');
        
        // Session timeout kontrolü - kod ekranında beklerken session uzat
        if (isset($_SESSION['verification_code_time'])) {
            $session_age = time() - $_SESSION['verification_code_time'];
            if ($session_age > 0 && $session_age < SESSION_TIMEOUT_EXTEND) {
                // Session'ı uzat
                $_SESSION['last_activity'] = time();
            }
        }
        
        if (empty($verification_code) || strlen($verification_code) !== 6 || !preg_match('/^\d{6}$/', $verification_code)) {
            $error = "Geçersiz doğrulama kodu! Lütfen 6 haneli sayı girin.";
            $_SESSION['show_verification'] = true;
        } elseif (!isset($_SESSION['verification_code']) || !isset($_SESSION['verification_code_time'])) {
            $error = "Doğrulama kodu bulunamadı. Lütfen tekrar kod isteyin.";
            unset($_SESSION['show_verification']);
        } elseif (time() - $_SESSION['verification_code_time'] > 600) { // 10 dakika
            $error = "Doğrulama kodu süresi doldu. Lütfen yeni kod isteyin.";
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_code_time']);
            unset($_SESSION['verification_code_attempts']);
            unset($_SESSION['verification_code_locked_until']);
            unset($_SESSION['show_verification']);
            unset($_SESSION['pending_admin_id']);
            unset($_SESSION['pending_username']);
            unset($_SESSION['pending_password_verified']);
            unset($_SESSION['pending_remember_me']);
        } elseif (isset($_SESSION['verification_code_locked_until']) && time() < $_SESSION['verification_code_locked_until']) {
            $remaining = $_SESSION['verification_code_locked_until'] - time();
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            $error = "Çok fazla yanlış deneme! Lütfen {$minutes} dakika {$seconds} saniye sonra tekrar deneyin.";
            $_SESSION['show_verification'] = true;
        } elseif ($verification_code !== $_SESSION['verification_code']) {
            // Yanlış kod denemesi - rate limiting
            $attempts = isset($_SESSION['verification_code_attempts']) ? (int)$_SESSION['verification_code_attempts'] : 0;
            $attempts++;
            $_SESSION['verification_code_attempts'] = $attempts;
            
            if ($attempts >= CODE_VERIFICATION_MAX_ATTEMPTS) {
                // Lockout
                $_SESSION['verification_code_locked_until'] = time() + CODE_VERIFICATION_LOCKOUT_TIME;
                $error = "Çok fazla yanlış deneme! Hesap 5 dakika süreyle kilitlendi. Lütfen yeni kod isteyin.";
                // Pending bilgileri temizle, yeni kod isteyecek
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_code_time']);
                unset($_SESSION['verification_code_attempts']);
                unset($_SESSION['pending_admin_id']);
                unset($_SESSION['pending_username']);
                unset($_SESSION['pending_password_verified']);
                unset($_SESSION['pending_remember_me']);
                unset($_SESSION['show_verification']);
            } else {
                $remaining_attempts = CODE_VERIFICATION_MAX_ATTEMPTS - $attempts;
                $error = "Doğrulama kodu hatalı! Kalan deneme hakkı: {$remaining_attempts}";
                $_SESSION['show_verification'] = true;
            }
        } else {
            // Kod doğru, giriş yap
            if (isset($_SESSION['pending_admin_id']) && isset($_SESSION['pending_username']) && isset($_SESSION['pending_password_verified']) && $_SESSION['pending_password_verified']) {
                // IP ve User-Agent kontrolü (son bir kez daha)
                $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $current_ip) {
                    // IP değişti - güvenlik uyarısı ama devam et (VPN kullanımı olabilir)
                    tpl_error_log("Login IP changed: " . $_SESSION['login_ip'] . " -> " . $current_ip);
                }
                
                $_SESSION['admin_id'] = $_SESSION['pending_admin_id'];
                $_SESSION['club_id'] = CLUB_ID;
                $_SESSION['admin_username'] = $_SESSION['pending_username'];
                $_SESSION['login_ip'] = $current_ip;
                $_SESSION['login_user_agent'] = $current_ua;
                $_SESSION['last_activity'] = time();
                
                $shouldRemember = array_key_exists('pending_remember_me', $_SESSION) ? (bool)$_SESSION['pending_remember_me'] : true;
                if ($shouldRemember) {
                    extend_session_cookie();
                }
                
                // Doğrulama kodunu ve pending bilgilerini temizle
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_code_time']);
                unset($_SESSION['verification_code_attempts']);
                unset($_SESSION['verification_code_locked_until']);
                unset($_SESSION['show_verification']);
                unset($_SESSION['pending_admin_id']);
                unset($_SESSION['pending_username']);
                unset($_SESSION['pending_password_verified']);
                unset($_SESSION['pending_remember_me']);
            
            try {
                $db = get_sqlite_connection(DB_PATH);
                
                // Giriş logu
                $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
                    id INTEGER PRIMARY KEY,
                    community_name TEXT,
                    action TEXT,
                    details TEXT,
                    timestamp TEXT DEFAULT CURRENT_TIMESTAMP
                )");
                
                $log_stmt = $db->prepare("INSERT INTO admin_logs (community_name, action, details) VALUES (?, ?, ?)");
                $log_stmt->bindValue(1, (string)CLUB_ID, SQLITE3_TEXT);
                $log_stmt->bindValue(2, 'Giriş Yapıldı', SQLITE3_TEXT);
                $log_stmt->bindValue(3, 'Admin giriş yaptı: ' . htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8'), SQLITE3_TEXT);
                $log_stmt->execute();
                
                $db->close();
            } catch (Exception $e) {
                // Log hatası önemli değil, devam et
            }
            
            // Output buffer'ı temizle (eğer varsa)
            if (function_exists('ob_get_level')) {
                $level = @ob_get_level();
                while ($level > 0) {
                    @ob_end_clean();
                    $new_level = @ob_get_level();
                    if ($new_level >= $level) {
                        break;
                    }
                    $level = $new_level;
                }
            }
            
            // Yönlendirmeyi hemen yap (mail zaten kullanıcı adı/şifre doğru girildiğinde gönderildi)
            if (!headers_sent()) {
                header("Location: index.php");
                
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
            }
            exit;
            } else {
                $error = "Oturum bilgileri bulunamadı. Lütfen tekrar giriş yapın.";
                unset($_SESSION['show_verification']);
            }
        }
    }
}

// Giriş işlemi (kullanıcı adı ve şifre kontrolü)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password']) && !isset($_POST['action'])) {
    // Session'ın aktif olduğundan emin ol
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Tüm output buffer'ları temizle (baştan)
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Error değişkenini başlat
    $error = '';
    
    try {
    // CSRF kontrolü
    $csrf_token = $_POST['csrf_token'] ?? '';
        if (empty($csrf_token)) {
            tpl_error_log("CSRF token boş - POST data: " . print_r(array_keys($_POST), true));
            $error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        } elseif (!verify_csrf_token($csrf_token)) {
            tpl_error_log("CSRF token doğrulama başarısız - POST token: " . substr($csrf_token, 0, 10) . "...");
        $error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Güvenlik: Username length validation
    if (strlen($username) > 255 || strlen($username) < 1) {
        $error = "Geçersiz kullanıcı adı!";
    } else {
        try {
                    if (!defined('DB_PATH') || empty(DB_PATH)) {
                        throw new Exception('DB_PATH tanımlı değil');
                    }
            $db = get_sqlite_connection(DB_PATH);
            
            // Güvenlik: Brute force koruması - Rate limiting
            // Tüm güvenlik tablolarını önceden oluştur (hata kontrolü ile)
            $create_tables = [
                "CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY,
                    club_id INTEGER NOT NULL,
                    action_type TEXT NOT NULL,
                    action_count INTEGER DEFAULT 0,
                    hour_timestamp TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS blocked_ips (
                    id INTEGER PRIMARY KEY,
                    ip_address TEXT NOT NULL,
                    club_id INTEGER NOT NULL,
                    blocked_until INTEGER NOT NULL,
                    reason TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS failed_login_attempts (
                    id INTEGER PRIMARY KEY,
                    username TEXT NOT NULL,
                    club_id INTEGER NOT NULL,
                    ip_address TEXT,
                    user_agent TEXT,
                    attempt_count INTEGER DEFAULT 1,
                    last_attempt INTEGER,
                    locked_until INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS security_logs (
                    id INTEGER PRIMARY KEY,
                    club_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    username TEXT,
                    ip_address TEXT,
                    user_agent TEXT,
                    details TEXT,
                    severity TEXT DEFAULT 'info',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )"
            ];
            
            foreach ($create_tables as $sql) {
                $result = @$db->exec($sql);
                if ($result === false) {
                    $error_msg = $db->lastErrorMsg();
                    tpl_error_log("Table creation failed: " . $error_msg . " - SQL: " . substr($sql, 0, 100));
                    // Devam et, diğer tabloları oluşturmaya çalış
                }
            }
            
                    // IP adresini al ve normalize et
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    // Proxy/load balancer arkasındaysa gerçek IP'yi al
                    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                        $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                        $ip_address = trim($forwarded_ips[0]);
                    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                        $ip_address = $_SERVER['HTTP_X_REAL_IP'];
                    }
                    
                    // IP validation
                    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                        $ip_address = 'unknown';
                    }
                    
                    $current_hour = date('Y-m-d H:00:00');
                    $current_day = date('Y-m-d');
                    $current_time = time();
                    
                    // IP Block kontrolü (tablo zaten oluşturuldu)
                    $ip_block_check = @$db->prepare("SELECT blocked_until, reason FROM blocked_ips WHERE ip_address = ? AND club_id = ? AND blocked_until > ?");
                    if (!$ip_block_check) {
                        // Tablo yoksa oluştur ve tekrar dene
                        @$db->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
                            id INTEGER PRIMARY KEY,
                            ip_address TEXT NOT NULL,
                            club_id INTEGER NOT NULL,
                            blocked_until INTEGER NOT NULL,
                            reason TEXT,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        )");
                        $ip_block_check = @$db->prepare("SELECT blocked_until, reason FROM blocked_ips WHERE ip_address = ? AND club_id = ? AND blocked_until > ?");
                    }
                    
                    if ($ip_block_check) {
                        $ip_block_check->bindValue(1, $ip_address, SQLITE3_TEXT);
                        $ip_block_check->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                        $ip_block_check->bindValue(3, $current_time, SQLITE3_INTEGER);
                        $ip_block_result = @$ip_block_check->execute();
                        $ip_block_row = $ip_block_result ? $ip_block_result->fetchArray(SQLITE3_ASSOC) : null;
                    } else {
                        $ip_block_row = null;
                        tpl_error_log("Failed to prepare blocked_ips query: " . $db->lastErrorMsg());
                    }
                    
                    if ($ip_block_row) {
                        $remaining_time = $ip_block_row['blocked_until'] - $current_time;
                        $remaining_hours = ceil($remaining_time / 3600);
                        $reason = $ip_block_row['reason'] ?? 'Çok fazla başarısız giriş denemesi';
                        
                        // Security log (tablo zaten oluşturuldu)
                        $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?)");
                        $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                        $security_log->bindValue(2, 'blocked_ip_attempt', SQLITE3_TEXT);
                        $security_log->bindValue(3, $ip_address, SQLITE3_TEXT);
                        $security_log->bindValue(4, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                        $security_log->bindValue(5, "Blocked IP attempted login: $reason", SQLITE3_TEXT);
                        $security_log->bindValue(6, 'warning', SQLITE3_TEXT);
                        @$security_log->execute();
                        
                        $db->close();
                        $error = "IP adresiniz güvenlik nedeniyle geçici olarak engellenmiştir. Lütfen $remaining_hours saat sonra tekrar deneyin. Sebep: $reason";
                    } else {
                        // Login attempt kontrolü (IP bazlı - saatlik)
            $rate_check_stmt = @$db->prepare("SELECT id, action_count FROM rate_limits WHERE club_id = ? AND action_type = ? AND hour_timestamp = ?");
                        if (!$rate_check_stmt) {
                            $error_msg = $db->lastErrorMsg();
                            tpl_error_log("Rate limit prepare failed: " . $error_msg);
                            // Tablo yoksa oluştur ve tekrar dene
                            @$db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                                id INTEGER PRIMARY KEY,
                                club_id INTEGER NOT NULL,
                                action_type TEXT NOT NULL,
                                action_count INTEGER DEFAULT 0,
                                hour_timestamp TEXT NOT NULL,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                            )");
                            $rate_check_stmt = @$db->prepare("SELECT id, action_count FROM rate_limits WHERE club_id = ? AND action_type = ? AND hour_timestamp = ?");
                            if (!$rate_check_stmt) {
                                throw new Exception('Rate limit sorgusu hazırlanamadı: ' . $db->lastErrorMsg());
                            }
                        }
            $rate_check_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $rate_check_stmt->bindValue(2, 'login_attempt_' . $ip_address, SQLITE3_TEXT);
            $rate_check_stmt->bindValue(3, $current_hour, SQLITE3_TEXT);
            $rate_result = $rate_check_stmt->execute();
                        if (!$rate_result) {
                            throw new Exception('Rate limit sorgusu çalıştırılamadı: ' . $db->lastErrorMsg());
                        }
            $rate_row = $rate_result->fetchArray(SQLITE3_ASSOC);
                        $attempt_count_hour = $rate_row ? (int)$rate_row['action_count'] : 0;
                        
                        // Günlük deneme kontrolü
                        $daily_check_stmt = $db->prepare("SELECT SUM(action_count) as total FROM rate_limits WHERE club_id = ? AND action_type = ? AND hour_timestamp LIKE ?");
                        $daily_check_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                        $daily_check_stmt->bindValue(2, 'login_attempt_' . $ip_address, SQLITE3_TEXT);
                        $daily_check_stmt->bindValue(3, $current_day . '%', SQLITE3_TEXT);
                        $daily_result = $daily_check_stmt->execute();
                        $daily_row = $daily_result ? $daily_result->fetchArray(SQLITE3_ASSOC) : null;
                        $attempt_count_day = $daily_row && isset($daily_row['total']) ? (int)$daily_row['total'] : 0;
                        
                        // Maksimum deneme sayısı (varsayılan, ayarlardan alınabilir)
                        try {
                            $max_attempts_value = login_get_setting($db, 'max_login_attempts', MAX_LOGIN_ATTEMPTS_PER_HOUR);
                            $max_attempts = (is_numeric($max_attempts_value) && (int)$max_attempts_value > 0) ? (int)$max_attempts_value : MAX_LOGIN_ATTEMPTS_PER_HOUR;
                        } catch (Exception $e) {
                            tpl_error_log("login_get_setting error: " . $e->getMessage());
                            $max_attempts = MAX_LOGIN_ATTEMPTS_PER_HOUR;
                        }
                        
                        // Rate limit kontrolü - Saatlik
                        if ($attempt_count_hour >= $max_attempts) {
                            // Şüpheli aktivite - IP'yi blokla
                            if ($attempt_count_hour >= SUSPICIOUS_ACTIVITY_THRESHOLD) {
                                $block_until = $current_time + IP_BLOCK_DURATION;
                                $block_stmt = $db->prepare("INSERT OR REPLACE INTO blocked_ips (ip_address, club_id, blocked_until, reason) VALUES (?, ?, ?, ?)");
                                $block_stmt->bindValue(1, $ip_address, SQLITE3_TEXT);
                                $block_stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                                $block_stmt->bindValue(3, $block_until, SQLITE3_INTEGER);
                                $block_stmt->bindValue(4, 'Saatlik deneme limiti aşıldı (' . $attempt_count_hour . ' deneme)', SQLITE3_TEXT);
                                @$block_stmt->execute();
                                
                                // Security log
                                $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?)");
                                $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                $security_log->bindValue(2, 'ip_blocked', SQLITE3_TEXT);
                                $security_log->bindValue(3, $ip_address, SQLITE3_TEXT);
                                $security_log->bindValue(4, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                $security_log->bindValue(5, "IP blocked due to excessive login attempts: $attempt_count_hour attempts in 1 hour", SQLITE3_TEXT);
                                $security_log->bindValue(6, 'critical', SQLITE3_TEXT);
                                @$security_log->execute();
                            }
                            
                $error = "Çok fazla başarısız giriş denemesi! Lütfen 1 saat sonra tekrar deneyin.";
                // Rate limit sayacını artır
                if ($rate_row && isset($rate_row['id'])) {
                    $update_rate_stmt = $db->prepare("UPDATE rate_limits SET action_count = action_count + 1 WHERE id = ?");
                    $update_rate_stmt->bindValue(1, $rate_row['id'], SQLITE3_INTEGER);
                    $update_rate_stmt->execute();
                } else {
                    $insert_rate_stmt = $db->prepare("INSERT INTO rate_limits (club_id, action_type, action_count, hour_timestamp) VALUES (?, ?, 1, ?)");
                    $insert_rate_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                    $insert_rate_stmt->bindValue(2, 'login_attempt_' . $ip_address, SQLITE3_TEXT);
                    $insert_rate_stmt->bindValue(3, $current_hour, SQLITE3_TEXT);
                    $insert_rate_stmt->execute();
                }
                $db->close();
                        } elseif ($attempt_count_day >= MAX_LOGIN_ATTEMPTS_PER_DAY) {
                            // Günlük limit aşıldı
                            $error = "Günlük giriş deneme limitiniz doldu! Lütfen yarın tekrar deneyin.";
                            $db->close();
            } else {
                // Veritabanı tablolarını oluştur
                $db->exec("CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY,
                    username TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    club_id INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                
                $db->exec("CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY,
                    setting_key TEXT UNIQUE NOT NULL,
                    setting_value TEXT NOT NULL
                )");
                
                // Varsayılan admin kullanıcısı oluşturma - GÜVENLİK NEDENİYLE KALDIRILDI
                // Admin panelinden manuel oluşturulmalı
                            
                            // Kullanıcı bazlı lockout kontrolü
                            $user_lockout_check = @$db->prepare("SELECT attempt_count, locked_until FROM failed_login_attempts WHERE username = ? AND club_id = ?");
                            if (!$user_lockout_check) {
                                // Tablo yoksa oluştur ve tekrar dene
                                @$db->exec("CREATE TABLE IF NOT EXISTS failed_login_attempts (
                                    id INTEGER PRIMARY KEY,
                                    username TEXT NOT NULL,
                                    club_id INTEGER NOT NULL,
                                    ip_address TEXT,
                                    user_agent TEXT,
                                    attempt_count INTEGER DEFAULT 1,
                                    last_attempt INTEGER,
                                    locked_until INTEGER DEFAULT 0,
                                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                                )");
                                $user_lockout_check = @$db->prepare("SELECT attempt_count, locked_until FROM failed_login_attempts WHERE username = ? AND club_id = ?");
                            }
                            
                            if ($user_lockout_check) {
                                $user_lockout_check->bindValue(1, $username, SQLITE3_TEXT);
                                $user_lockout_check->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                                $user_lockout_result = @$user_lockout_check->execute();
                                $user_lockout_row = $user_lockout_result ? $user_lockout_result->fetchArray(SQLITE3_ASSOC) : null;
                            } else {
                                $user_lockout_row = null;
                                tpl_error_log("Failed to prepare failed_login_attempts query: " . $db->lastErrorMsg());
                            }
                            
                            // Hesap kilitli mi kontrol et
                            if ($user_lockout_row && isset($user_lockout_row['locked_until']) && $user_lockout_row['locked_until'] > $current_time) {
                                $remaining_lockout = $user_lockout_row['locked_until'] - $current_time;
                                $remaining_minutes = ceil($remaining_lockout / 60);
                                
                                // Security log
                                $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, username, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                $security_log->bindValue(2, 'locked_account_attempt', SQLITE3_TEXT);
                                $security_log->bindValue(3, $username, SQLITE3_TEXT);
                                $security_log->bindValue(4, $ip_address, SQLITE3_TEXT);
                                $security_log->bindValue(5, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                $security_log->bindValue(6, "Locked account login attempt", SQLITE3_TEXT);
                                $security_log->bindValue(7, 'warning', SQLITE3_TEXT);
                                @$security_log->execute();
                                
                                $db->close();
                                $error = "Bu hesap güvenlik nedeniyle geçici olarak kilitlenmiştir. Lütfen $remaining_minutes dakika sonra tekrar deneyin.";
                            } else {
                                // Kullanıcı doğrulama - Username enumeration koruması için her zaman sorgu çalıştır
                $stmt = $db->prepare("SELECT id, password_hash FROM admins WHERE username = ? AND club_id = ?");
                                if (!$stmt) {
                                    throw new Exception('Admin sorgusu hazırlanamadı: ' . $db->lastErrorMsg());
                                }
                $stmt->bindValue(1, $username, SQLITE3_TEXT);
                $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                $result = $stmt->execute();
                                if (!$result) {
                                    throw new Exception('Admin sorgusu çalıştırılamadı: ' . $db->lastErrorMsg());
                                }
                $admin = $result->fetchArray(SQLITE3_ASSOC);
                                
                                // Device fingerprint oluştur
                                $device_fingerprint = hash('sha256', 
                                    ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
                                    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') . 
                                    ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') .
                                    $ip_address
                                );
        
                // Güvenlik: Password verification (timing-safe)
                                // Username enumeration koruması: Her durumda aynı süreyi harca
                                $password_valid = false;
                                if ($admin) {
                                    $password_valid = password_verify($password, $admin['password_hash']);
                                }
                                
                                // Progressive delay - Her başarısız denemede artan bekleme
                                $progressive_delay = 0;
                                if ($user_lockout_row && isset($user_lockout_row['attempt_count'])) {
                                    $progressive_delay = min($user_lockout_row['attempt_count'] * PROGRESSIVE_DELAY_BASE, 30); // Max 30 saniye
                                }
                                if ($progressive_delay > 0) {
                                    usleep($progressive_delay * 1000000); // Mikrosaniye cinsinden
                                }
                                
                                if ($admin && $password_valid) {
                                    // Başarılı giriş - tüm güvenlik kayıtlarını temizle
                    if ($rate_row && isset($rate_row['id'])) {
                        $delete_rate_stmt = $db->prepare("DELETE FROM rate_limits WHERE id = ?");
                        $delete_rate_stmt->bindValue(1, $rate_row['id'], SQLITE3_INTEGER);
                        $delete_rate_stmt->execute();
                    }
                                    
                                    // Kullanıcı bazlı başarısız deneme kayıtlarını temizle
                                    $delete_user_attempts = $db->prepare("DELETE FROM failed_login_attempts WHERE username = ? AND club_id = ?");
                                    $delete_user_attempts->bindValue(1, $username, SQLITE3_TEXT);
                                    $delete_user_attempts->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                                    @$delete_user_attempts->execute();
                                    
                                    // Security log - Başarılı giriş
                                    $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, username, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                    $security_log->bindValue(2, 'successful_login', SQLITE3_TEXT);
                                    $security_log->bindValue(3, $username, SQLITE3_TEXT);
                                    $security_log->bindValue(4, $ip_address, SQLITE3_TEXT);
                                    $security_log->bindValue(5, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                    $security_log->bindValue(6, "Successful login attempt - Device: " . substr($device_fingerprint, 0, 16), SQLITE3_TEXT);
                                    $security_log->bindValue(7, 'info', SQLITE3_TEXT);
                                    @$security_log->execute();
                    
                    // Kullanıcı bilgilerini session'a kaydet (henüz giriş yapmadı, doğrulama kodu bekliyor)
                    $_SESSION['pending_admin_id'] = $admin['id'];
                    $_SESSION['pending_username'] = $username;
                    $_SESSION['pending_password_verified'] = true;
                    $_SESSION['pending_remember_me'] = array_key_exists('remember', $_POST) ? (bool)$_POST['remember'] : true;
                    
                    // Mail gönderme işlemini başlat (2FA kodu beklenmeden)
                    $mail_data = [
                        'admin_username' => $username,
                        'login_time' => date('d.m.Y H:i:s'),
                                        'login_ip' => $ip_address,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
                        'db_path' => DB_PATH
                    ];
                    
                    // Mail gönderme işlemini senkron yapma - AJAX ile tetikle
                    $_SESSION['unsent_login_notification'] = $mail_data;
                    
                    // Başkanın telefon numarasını superadmin veritabanından çek
                    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
                    $normalized = str_replace('\\', '/', $script_dir);
                    $needle = '/communities/';
                    $pos = strpos($normalized, $needle);
                    $folder_name = '';
                    
                    if ($pos !== false) {
                        $after = substr($normalized, $pos + strlen($needle));
                        $parts = explode('/', $after);
                        $folder_name = $parts[0] ?? '';
                    }
                    
                                    try {
                    $remaining = login_sms_cooldown_remaining();
                                    } catch (Exception $e) {
                                        tpl_error_log("Cooldown check error: " . $e->getMessage());
                                        $remaining = 0;
                                    }
                                    
                                    try {
                    $admin_phone = resolve_verification_phone($db);
                                    } catch (Exception $e) {
                                        tpl_error_log("Resolve phone error: " . $e->getMessage());
                                        tpl_error_log("Stack trace: " . $e->getTraceAsString());
                                        $admin_phone = '';
                                    }
                    
                    if (empty($admin_phone)) {
                        unset($_SESSION['pending_admin_id'], $_SESSION['pending_username'], $_SESSION['pending_password_verified'], $_SESSION['pending_remember_me']);
                        unset($_SESSION['verification_code'], $_SESSION['verification_code_time'], $_SESSION['show_verification']);
                    $db->close();
                        $error = 'Güvenli giriş için telefon numarası bulunamadı. Lütfen başkan telefonunu ayarlardan ekleyin.';
                    } elseif ($remaining > 0) {
                        unset($_SESSION['pending_admin_id'], $_SESSION['pending_username'], $_SESSION['pending_password_verified'], $_SESSION['pending_remember_me']);
                        unset($_SESSION['verification_code'], $_SESSION['verification_code_time'], $_SESSION['show_verification']);
                        $db->close();
                        $error = 'Güvenlik nedeniyle yeni kod göndermeden önce lütfen ' . $remaining . ' saniye bekleyin.';
                    } else {
                        $verification_code = generate_login_verification_code();
                        $_SESSION['verification_code'] = $verification_code;
                        $_SESSION['verification_code_time'] = time();
                        
                        try {
                                            // SMS göndermeyi dene - timeout'u kısalt
                            $send_result = send_login_verification_sms($db, $admin_phone, $verification_code);
                                            
                            if (!$send_result || empty($send_result['success'])) {
                                $error_message = $send_result['error'] ?? 'SMS gönderilemedi. Lütfen SMS ayarlarını kontrol edin.';
                                tpl_error_log("Login SMS Error: " . $error_message);
                                unset($_SESSION['pending_admin_id'], $_SESSION['pending_username'], $_SESSION['pending_password_verified'], $_SESSION['pending_remember_me']);
                                unset($_SESSION['verification_code'], $_SESSION['verification_code_time'], $_SESSION['show_verification']);
                                $db->close();
                                $error = $error_message;
                            } else {
                                                // SMS başarılı - session değişkenlerini set et
                                $_SESSION['verification_code_last_sent'] = time();
                                $_SESSION['show_verification'] = true;
                                                
                                                // Database'i kapat
                                                $db->close();
                                                
                                                // Tüm output buffer'ları temizle
                                                while (ob_get_level() > 0) {
                                                    @ob_end_clean();
                                                }
                                                
                                                // Redirect yap - session açık kalacak
                                                if (!headers_sent()) {
                                                    header('Location: login.php', true, 302);
                                                    header('Cache-Control: no-cache, no-store, must-revalidate');
                                                    header('Pragma: no-cache');
                                                    header('Expires: 0');
                                                    exit;
                                                } else {
                                                    // Headers zaten gönderilmişse JavaScript ile redirect yap
                                                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><script>window.location.href = "login.php";</script></head><body><p>Yönlendiriliyor...</p></body></html>';
                    exit;
                                                }
                            }
                        } catch (Exception $sms_exception) {
                            tpl_error_log("Login SMS Exception: " . $sms_exception->getMessage());
                            tpl_error_log("Stack trace: " . $sms_exception->getTraceAsString());
                            unset($_SESSION['pending_admin_id'], $_SESSION['pending_username'], $_SESSION['pending_password_verified'], $_SESSION['pending_remember_me']);
                            unset($_SESSION['verification_code'], $_SESSION['verification_code_time'], $_SESSION['show_verification']);
                            $db->close();
                            $error = 'SMS gönderiminde teknik bir hata oluştu: ' . $sms_exception->getMessage();
                        }
                    }
                } else {
                    // Başarısız giriş - rate limit sayacını artır
                    if ($rate_row && isset($rate_row['id'])) {
                        $update_rate_stmt = $db->prepare("UPDATE rate_limits SET action_count = action_count + 1 WHERE id = ?");
                        $update_rate_stmt->bindValue(1, $rate_row['id'], SQLITE3_INTEGER);
                        $update_rate_stmt->execute();
                    } else {
                        $insert_rate_stmt = $db->prepare("INSERT INTO rate_limits (club_id, action_type, action_count, hour_timestamp) VALUES (?, ?, 1, ?)");
                        $insert_rate_stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                        $insert_rate_stmt->bindValue(2, 'login_attempt_' . $ip_address, SQLITE3_TEXT);
                        $insert_rate_stmt->bindValue(3, $current_hour, SQLITE3_TEXT);
                        $insert_rate_stmt->execute();
                    }
                                    
                                    // Kullanıcı bazlı başarısız deneme kaydı
                                    $user_attempt_count = 1;
                                    $locked_until = 0;
                                    
                                    if ($user_lockout_row) {
                                        $user_attempt_count = (int)$user_lockout_row['attempt_count'] + 1;
                                        
                                        // Maksimum deneme sayısına ulaşıldıysa hesabı kilitle
                                        if ($user_attempt_count >= MAX_FAILED_ATTEMPTS_PER_USER) {
                                            $locked_until = $current_time + ACCOUNT_LOCKOUT_DURATION;
                                            
                                            // Security log - Hesap kilitlendi
                                            $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, username, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                            $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                            $security_log->bindValue(2, 'account_locked', SQLITE3_TEXT);
                                            $security_log->bindValue(3, $username, SQLITE3_TEXT);
                                            $security_log->bindValue(4, $ip_address, SQLITE3_TEXT);
                                            $security_log->bindValue(5, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                            $security_log->bindValue(6, "Account locked due to $user_attempt_count failed attempts", SQLITE3_TEXT);
                                            $security_log->bindValue(7, 'critical', SQLITE3_TEXT);
                                            @$security_log->execute();
                                        }
                                        
                                        // Güncelle
                                        $update_user_attempts = $db->prepare("UPDATE failed_login_attempts SET attempt_count = ?, last_attempt = ?, locked_until = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ? AND club_id = ?");
                                        $update_user_attempts->bindValue(1, $user_attempt_count, SQLITE3_INTEGER);
                                        $update_user_attempts->bindValue(2, $current_time, SQLITE3_INTEGER);
                                        $update_user_attempts->bindValue(3, $locked_until, SQLITE3_INTEGER);
                                        $update_user_attempts->bindValue(4, $username, SQLITE3_TEXT);
                                        $update_user_attempts->bindValue(5, CLUB_ID, SQLITE3_INTEGER);
                                        @$update_user_attempts->execute();
                                    } else {
                                        // Yeni kayıt oluştur
                                        $insert_user_attempts = $db->prepare("INSERT INTO failed_login_attempts (username, club_id, ip_address, user_agent, attempt_count, last_attempt, locked_until) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                        $insert_user_attempts->bindValue(1, $username, SQLITE3_TEXT);
                                        $insert_user_attempts->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
                                        $insert_user_attempts->bindValue(3, $ip_address, SQLITE3_TEXT);
                                        $insert_user_attempts->bindValue(4, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                        $insert_user_attempts->bindValue(5, 1, SQLITE3_INTEGER);
                                        $insert_user_attempts->bindValue(6, $current_time, SQLITE3_INTEGER);
                                        $insert_user_attempts->bindValue(7, 0, SQLITE3_INTEGER);
                                        @$insert_user_attempts->execute();
                                    }
                                    
                                    // Security log - Başarısız giriş
                                    $security_log = $db->prepare("INSERT INTO security_logs (club_id, event_type, username, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $security_log->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                                    $security_log->bindValue(2, 'failed_login', SQLITE3_TEXT);
                                    $security_log->bindValue(3, $username, SQLITE3_TEXT);
                                    $security_log->bindValue(4, $ip_address, SQLITE3_TEXT);
                                    $security_log->bindValue(5, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
                                    $security_log->bindValue(6, "Failed login attempt #$user_attempt_count - Device: " . substr($device_fingerprint, 0, 16), SQLITE3_TEXT);
                                    $security_log->bindValue(7, $user_attempt_count >= MAX_FAILED_ATTEMPTS_PER_USER ? 'warning' : 'info', SQLITE3_TEXT);
                                    @$security_log->execute();
                    
                    // Güvenlik: Timing attack koruması - Her durumda aynı süreyi harca
                    // password_verify zaten timing-safe ama ekstra güvenlik için
                                    usleep(random_int(200000, 500000)); // 0.2-0.5 saniye rastgele bekleme
                    
                                    // Username enumeration koruması - Her zaman aynı mesajı göster
                    $error = "Kullanıcı adı veya şifre hatalı!";
                                    
                                    // Hesap kilitlendiyse bilgi ver
                                    if ($locked_until > 0) {
                                        $error .= " Bu hesap güvenlik nedeniyle 1 saat süreyle kilitlenmiştir.";
                }
                                    
                $db->close();
            }
                            }
                        }
                    }
                } catch (Exception $inner_e) {
                    $error_msg = $inner_e->getMessage();
                    $error_file = $inner_e->getFile();
                    $error_line = $inner_e->getLine();
                    
                    tpl_error_log("=== LOGIN INNER ERROR ===");
                    tpl_error_log("Message: " . $error_msg);
                    tpl_error_log("File: " . $error_file);
                    tpl_error_log("Line: " . $error_line);
                    tpl_error_log("Stack trace: " . $inner_e->getTraceAsString());
                    tpl_error_log("POST data: " . print_r(['username' => $username ?? 'NOT SET', 'has_password' => isset($password)], true));
                    tpl_error_log("DB_PATH: " . (defined('DB_PATH') ? DB_PATH : 'NOT DEFINED'));
                    tpl_error_log("========================");
                    
                    if (isset($db) && $db instanceof SQLite3) {
                        try {
                            $db->close();
                        } catch (Exception $close_ex) {
                            // ignore
                        }
                    }
                    
                    // Sadece gerçekten veritabanı hatası varsa recovery dene
                    $is_db_error = (
                        stripos($error_msg, 'database') !== false ||
                        stripos($error_msg, 'SQLite') !== false ||
                        stripos($error_msg, 'DB_PATH') !== false ||
                        stripos($error_msg, 'disk I/O') !== false ||
                        stripos($error_msg, 'integrity') !== false ||
                        stripos($error_msg, 'malformed') !== false ||
                        stripos($error_msg, 'prepare') !== false ||
                        stripos($error_msg, 'execute') !== false ||
                        stripos($error_msg, 'bindValue') !== false
                    );
                    
                    if ($is_db_error) {
                        // Hata durumunda otomatik recovery dene
                        try {
                            repair_database(DB_PATH);
                            ensure_database_permissions(DB_PATH);
                            
                            // Bir kez daha dene
                            $test_db = get_sqlite_connection(DB_PATH);
                            $test_db->close();
                            
                            // Başarılı olduysa, kullanıcıdan tekrar denemesini iste
                            $error = "Veritabanı hatası düzeltildi. Lütfen tekrar giriş yapmayı deneyin.";
                        } catch (Exception $e2) {
                            // Recovery başarısız - kullanıcıya bilgi ver
                            tpl_error_log("SQLite kritik hata: " . $e2->getMessage() . " - Dosya: " . DB_PATH);
                            $error = "Veritabanı hatası oluştu. Sistem yöneticisine başvurun veya sayfayı yenileyip tekrar deneyin.";
                        }
                    } else {
                        // Normal login hatası - kullanıcıya genel mesaj göster
                        // Gerçek hata detayları loglara yazıldı
                        $error = "Giriş işlemi sırasında bir hata oluştu. Lütfen bilgilerinizi kontrol edip tekrar deneyin.";
                        
                        // Development modunda gerçek hatayı da göster (opsiyonel)
                        // $error = "Giriş işlemi sırasında bir hata oluştu: " . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
    } catch (Exception $outer_exception) {
        // Dış seviye exception - tüm login işlemini yakala
        tpl_error_log("Login Outer Exception: " . $outer_exception->getMessage());
        tpl_error_log("Stack trace: " . $outer_exception->getTraceAsString());
        tpl_error_log("File: " . $outer_exception->getFile() . " Line: " . $outer_exception->getLine());
        $error = "Giriş işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş | <?= htmlspecialchars($club_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include __DIR__ . '/partials/tailwind_cdn_loader.php'; ?>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.1), 0 2px 4px -1px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 40%, #ffffff 100%);
            min-height: 100vh;
            padding: clamp(2rem, 6vw, 5rem);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            transition: color var(--transition-base);
            z-index: 1;
        }
        
        .input-wrapper input:focus ~ .input-icon,
        .input-wrapper input:not(:placeholder-shown) ~ .input-icon {
            color: #6366f1;
        }
        
        .input-wrapper input {
            padding-left: 44px;
            transition: all var(--transition-base);
        }
        
        .input-wrapper input:focus {
            padding-left: 44px;
        }
        
        .form-input {
            transition: all var(--transition-base);
            background: var(--bg-primary) !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            height: auto !important;
        }
        
        .form-input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            outline: none !important;
        }
        
        .input-wrapper {
            position: relative;
            display: block !important;
            width: 100% !important;
        }
        
        .input-wrapper input {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            border: none;
            box-shadow: 0 10px 25px -10px rgba(79, 70, 229, 0.65);
            transition: all var(--transition-base);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-1px);
        }
        
        .link-primary {
            color: #6366f1;
        }
        
        .link-primary:hover {
            color: #4f46e5;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .auth-page {
            position: relative;
            min-height: calc(100vh - clamp(2rem, 6vw, 5rem) * 2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .auth-blur {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.12), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(129, 140, 248, 0.18), transparent 60%);
            z-index: -2;
            filter: blur(0px);
            display: none;
        }

        .auth-card {
            position: relative;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            overflow: hidden;
            backdrop-filter: none;
            background: transparent;
            z-index: 1;
        }

        @media (min-width: 900px) {
            .auth-card {
                grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            }
        }

        @media (min-width: 1024px) {
            body {
                padding: 0;
            }

            .auth-page {
                max-width: none;
                min-height: 100vh;
                align-items: stretch;
                justify-content: stretch;
            }

            .auth-card {
                min-height: 100vh;
                grid-template-columns: minmax(0, 0.75fr) minmax(0, 1fr);
            }

            .auth-card-media,
            .auth-card-form {
                height: 100%;
            }
        }

        .auth-card-media {
            position: relative;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: clamp(2.5rem, 5vw, 3.75rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.5rem, 3vw, 2.5rem);
            overflow: hidden;
        }

        .auth-card-media::before,
        .auth-card-media::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            filter: blur(0);
            opacity: 0.65;
            z-index: 0;
        }

        .auth-card-media::before {
            width: clamp(220px, 40vw, 320px);
            height: clamp(220px, 40vw, 320px);
            background: rgba(255, 255, 255, 0.12);
            top: clamp(-140px, -10vw, -80px);
            right: clamp(-120px, -8vw, -70px);
        }

        .auth-card-media::after {
            width: clamp(160px, 30vw, 280px);
            height: clamp(160px, 30vw, 260px);
            background: rgba(255, 255, 255, 0.08);
            bottom: clamp(-120px, -8vw, -70px);
            left: clamp(-120px, -8vw, -70px);
        }

        .auth-media-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: clamp(1.25rem, 2.5vw, 1.75rem);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-brand-logo {
            width: clamp(80px, 12vw, 100px);
            height: clamp(80px, 12vw, 100px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .auth-brand h1 {
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .auth-brand p {
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
        }

        .auth-headline {
            font-size: clamp(2rem, 3.6vw, 2.75rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.045em;
        }

        .auth-subheadline {
            font-size: clamp(1rem, 2vw, 1.125rem);
            color: rgba(255, 255, 255, 0.8);
            max-width: 32rem;
            line-height: 1.65;
        }

        .auth-benefits {
            list-style: none;
            display: grid;
            gap: 0.9rem;
            padding: 0;
            margin: 0;
        }

        .auth-benefits li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.975rem;
            color: rgba(255, 255, 255, 0.88);
            font-weight: 500;
        }

        .auth-benefits i {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.15);
        }

        .auth-card-form {
            position: relative;
            background: transparent;
            padding: clamp(2.5rem, 5vw, 4rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.75rem, 3vw, 2.5rem);
        }

        .auth-form-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .auth-form-header h2 {
            font-size: clamp(1.75rem, 2.8vw, 2.1rem);
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.035em;
        }

        .auth-form-header p {
            color: var(--text-secondary);
            font-size: 0.975rem;
        }

        .auth-form {
            display: flex !important;
            flex-direction: column;
            gap: 1.4rem;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .auth-form * {
            visibility: visible !important;
        }
        
        .auth-form input[type="text"],
        .auth-form input[type="password"] {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            height: auto !important;
            min-height: 48px !important;
        }

        .input-wrapper input {
            padding-left: 48px;
        }

        .auth-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .auth-footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.92rem;
            color: var(--text-secondary);
        }

        .auth-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }


        @media (max-width: 899px) {
            body {
                padding: clamp(1.5rem, 6vw, 3rem);
            }

            .auth-card {
                transform: translateX(0);
            }

            .auth-card-media {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page fade-in-up">
        <div class="auth-blur"></div>
        <div class="auth-card">
            <div class="auth-card-media">
                <div class="auth-media-content">
                    <div class="auth-brand">
                        <div class="auth-brand-logo">
                            <?php 
                            // Logo yolunu hesapla - basit ve güvenilir yol
                            $logo_path = dirname(__DIR__) . '/nobackground_logo.png';
                            
                            // REQUEST_URI'den base path'i çıkar
                            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
                            
                            // /unipanel/ path'ini bul
                            if (preg_match('#(/unipanel/)#', $request_uri, $matches)) {
                                $base_path = $matches[1];
                            } else {
                                // Fallback: script_name'den al
                                $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
                                if (preg_match('#(/unipanel)#', $script_name, $matches)) {
                                    $base_path = $matches[1] . '/';
                                } else {
                                    $base_path = '/unipanel/';
                                }
                            }
                            
                            // Protocol ve host
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            
                            // Logo URL'i oluştur
                            if (file_exists($logo_path)) {
                                $logo_url = $protocol . '://' . $host . $base_path . 'nobackground_logo.png';
                            } else {
                                $logo_url = 'https://www.caddedoner.com/foursoftware-light.png';
                            }
                            ?>
                            <img src="<?= htmlspecialchars($logo_url) ?>" alt="UniFour Logo" class="auth-logo-img" onerror="this.src='https://www.caddedoner.com/foursoftware-light.png'; this.onerror=null;">
                        </div>
                        <div>
                            <h1><?= htmlspecialchars($club_name) ?></h1>
                            <p>Topluluk Yönetim Paneli</p>
                        </div>
                    </div>
                    <div>
                        <h2 class="auth-headline">Güçlü topluluk deneyimini keşfedin</h2>
                        <p class="auth-subheadline">Etkinliklerinizi yönetin, üyelerle iletişim kurun ve kampanyaları tek panelden takip edin. UniPanel ile topluluğunuzu bir üst seviyeye taşıyın.</p>
                    </div>
                    <ul class="auth-benefits">
                        <li><i class="fas fa-check"></i> Anlık bildirim ve duyuru yönetimi</li>
                        <li><i class="fas fa-check"></i> Etkinlik katılım takibi ve raporlama</li>
                        <li><i class="fas fa-check"></i> Üyelerle hızlı, etkili iletişim</li>
                    </ul>
                </div>
            </div>
            <div class="auth-card-form">
                <div class="auth-form-header">
                    <h2>Hoş Geldiniz</h2>
                    <p>Hesabınıza giriş yapın ve <?= htmlspecialchars($club_name) ?> yönetim paneline erişin.</p>
                </div>

                <?php if ($community_pending): ?>
                    <div class="mb-2 p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-clock mt-0.5 flex-shrink-0"></i>
                        <div>
                            <span class="font-semibold block mb-1">Onay Bekleniyor</span>
                            <span class="text-yellow-700"><?= htmlspecialchars($community_pending_message) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($community_disabled): ?>
                    <div class="mb-2 p-4 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-ban mt-0.5 flex-shrink-0"></i>
                        <div>
                            <span class="font-semibold block mb-1">Topluluk Pasif</span>
                            <span class="text-red-700"><?= htmlspecialchars($community_disabled_message) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="mb-2 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                        <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php 
                // Session durumunu kontrol et - session başlatılmışsa kontrol et
                $show_verification = false;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $show_verification = isset($_SESSION['show_verification']) && $_SESSION['show_verification'] === true;
                }
                ?>
                
                <?php if ($show_verification): ?>
                    <!-- Doğrulama Kodu Formu -->
                    <form method="POST" action="" class="auth-form" id="verifyCodeForm" style="display: block !important;">
                        <input type="hidden" name="action" value="verify_code">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div style="display: block !important; margin-bottom: 1.4rem;">
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em; display: block !important;">Doğrulama Kodu</label>
                            <div class="input-wrapper" style="display: block !important; visibility: visible !important; position: relative; width: 100%;">
                                <i class="fas fa-key input-icon" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); z-index: 1; color: var(--text-light);"></i>
                                <input type="text" 
                                       name="verification_code" 
                                       required 
                                       maxlength="6" 
                                       pattern="[0-9]{6}"
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium text-center text-2xl tracking-widest"
                                       style="display: block !important; visibility: visible !important; opacity: 1 !important; width: 100% !important; border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="000000"
                                       autocomplete="off">
                            </div>
                            <p class="text-sm text-gray-500 mt-2">
                                Telefonunuza gönderilen 6 haneli kodu giriniz
                                <?php if (isset($_SESSION['verification_code_time'])): ?>
                                    <span id="codeExpiry" class="block mt-1 text-xs"></span>
                                <?php endif; ?>
                            </p>
                            <?php if (isset($_SESSION['verification_code_attempts']) && $_SESSION['verification_code_attempts'] > 0): ?>
                                <p class="text-sm text-orange-600 mt-1">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Yanlış deneme: <?= $_SESSION['verification_code_attempts'] ?>/<?= CODE_VERIFICATION_MAX_ATTEMPTS ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base" style="letter-spacing: -0.01em; display: block !important;" id="verifyBtn">
                            Doğrula ve Giriş Yap
                        </button>
                        
                        <div class="flex items-center justify-between mt-2" style="display: flex !important;">
                            <button type="button" onclick="resendCode()" id="resendBtn" class="text-gray-600 hover:text-indigo-600 text-sm font-medium">
                                <i class="fas fa-redo"></i> Kodu Tekrar Gönder
                            </button>
                            <span id="resendCooldown" class="text-xs text-gray-400"></span>
                        </div>
                    </form>
                    
                    <?php if (isset($_SESSION['unsent_login_notification'])): ?>
                    <script<?= tpl_script_nonce_attr(); ?>>
                        // Arka planda mail gönderimini tetikle
                        (function() {
                            const formData = new FormData();
                            formData.append('action', 'send_login_mail_async');
                            formData.append('csrf_token', '<?= generate_csrf_token() ?>');
                            
                            fetch('', {
                                method: 'POST',
                                body: formData,
                                keepalive: true // Sayfa değişse bile devam et
                            }).then(() => {
                                console.log('Login notification triggered');
                            }).catch(err => {
                                console.error('Login notification failed trigger', err);
                            });
                        })();
                    </script>
                    <?php 
                        // Bir daha tetiklenmemesi için session'dan silmiyoruz, 
                        // çünkü async istek gelene kadar durmalı. 
                        // Ancak async istekte silinecek.
                        // Veya burada silip JS'e data olarak gömebiliriz ama güvenlik için session daha iyi.
                    ?>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Normal Giriş Formu -->
                    <form method="POST" action="" class="auth-form" id="loginForm" style="display: flex !important; flex-direction: column !important; gap: 1.4rem !important; visibility: visible !important; opacity: 1 !important;" <?= $community_pending ? 'onsubmit="return false;"' : '' ?>>
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- DEBUG: Form görünür mü kontrol -->
                        <div style="display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 1.4rem !important; width: 100% !important;">
                            <label class="block text-sm font-semibold mb-2" style="color: #0f172a !important; letter-spacing: -0.01em; display: block !important; visibility: visible !important; opacity: 1 !important;">Kullanıcı Adı</label>
                            <div class="input-wrapper" style="display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important; width: 100% !important;">
                                <i class="fas fa-user" style="position: absolute !important; left: 16px !important; top: 50% !important; transform: translateY(-50%) !important; z-index: 1 !important; color: #94a3b8 !important; pointer-events: none !important;"></i>
                                <input type="text" 
                                       name="username" 
                                       required 
                                       <?= $community_pending ? 'disabled' : '' ?>
                                       class="form-input"
                                       style="display: block !important; visibility: visible !important; opacity: 1 !important; width: 100% !important; min-height: 48px !important; padding: 14px 16px 14px 48px !important; border: 2px solid #e2e8f0 !important; border-radius: 16px !important; outline: none !important; font-weight: 500 !important; color: #0f172a !important; background: #ffffff !important; box-sizing: border-box !important; <?= $community_pending ? 'opacity: 0.6 !important; cursor: not-allowed;' : '' ?>"
                                       placeholder="Kullanıcı adınızı girin"
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       autocomplete="username">
                            </div>
                        </div>

                        <div style="display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 1.4rem !important; width: 100% !important;">
                            <label class="block text-sm font-semibold mb-2" style="color: #0f172a !important; letter-spacing: -0.01em; display: block !important; visibility: visible !important; opacity: 1 !important;">Şifre</label>
                            <div class="input-wrapper" style="display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important; width: 100% !important;">
                                <i class="fas fa-lock" style="position: absolute !important; left: 16px !important; top: 50% !important; transform: translateY(-50%) !important; z-index: 1 !important; color: #94a3b8 !important; pointer-events: none !important;"></i>
                                <input type="password" 
                                       name="password" 
                                       required 
                                       <?= $community_pending ? 'disabled' : '' ?>
                                       class="form-input"
                                       style="display: block !important; visibility: visible !important; opacity: 1 !important; width: 100% !important; min-height: 48px !important; padding: 14px 16px 14px 48px !important; border: 2px solid #e2e8f0 !important; border-radius: 16px !important; outline: none !important; font-weight: 500 !important; color: #0f172a !important; background: #ffffff !important; box-sizing: border-box !important; <?= $community_pending ? 'opacity: 0.6 !important; cursor: not-allowed;' : '' ?>"
                                       placeholder="••••••••"
                                       autocomplete="current-password">
                            </div>
                        </div>

                        <div class="auth-actions" style="display: flex !important; margin-bottom: 1.4rem;">
                            <label class="flex items-center cursor-pointer" style="display: flex !important;">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-2 cursor-pointer" 
                                       style="border-color: var(--border-color); accent-color: #6366f1; display: block !important;" <?= $community_pending ? 'disabled' : '' ?>>
                            <span class="ml-2 text-sm font-medium" style="color: var(--text-secondary);">Beni hatırla</span>
                        </label>
                    </div>

                        <button type="submit" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base" style="letter-spacing: -0.01em; display: block !important; width: 100% !important; min-height: 48px !important; <?= $community_pending ? 'opacity: 0.6; cursor: not-allowed;' : '' ?>" <?= $community_pending ? 'disabled' : '' ?>>
                        <?= $community_pending ? 'Onay Bekleniyor...' : 'Giriş Yap' ?>
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if ($community_pending): ?>
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold mb-1">Ne yapmalısınız?</p>
                                <p class="text-blue-600">Topluluğunuz onaylandıktan sonra bu sayfadan giriş yapabileceksiniz. Onay süreci genellikle 24 saat içinde tamamlanır.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>


                <div class="text-center mt-4">
                    <p class="text-gray-500 text-xs">© 2025 UniFour - Tüm hakları saklıdır</p>
                </div>
            </div>
        </div>
    </div>

    <script<?= tpl_script_nonce_attr(); ?>>
        // Kod tekrar gönderme fonksiyonu
        function resendCode() {
            const btn = document.getElementById('resendBtn');
            const cooldownEl = document.getElementById('resendCooldown');
            
            if (!btn || btn.disabled) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            const formData = new FormData();
            formData.append('action', 'send_verification_code');
            formData.append('csrf_token', '<?= generate_csrf_token() ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı - cooldown başlat
                    let cooldown = <?= LOGIN_SMS_COOLDOWN ?>;
                    const interval = setInterval(() => {
                        if (cooldownEl && cooldown > 0) {
                            cooldownEl.textContent = `${cooldown} saniye sonra tekrar gönderebilirsiniz`;
                            cooldown--;
                        } else {
                            clearInterval(interval);
                            if (cooldownEl) {
                            cooldownEl.textContent = '';
                            }
                            if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-redo"></i> Kodu Tekrar Gönder';
                            }
                        }
                    }, 1000);
                    
                    // Sayfayı yenile (yeni kod için)
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Hata: ' + (data.error || 'Kod gönderilemedi'));
                    if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> Kodu Tekrar Gönder';
                    }
                }
            })
            .catch(error => {
                alert('Hata: Kod gönderilemedi. Lütfen tekrar deneyin.');
                if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Kodu Tekrar Gönder';
                }
            });
        }
        
        // Kod süresi dolduğunda otomatik kontrol
        <?php if (isset($_SESSION['verification_code_time'])): ?>
        (function() {
            const expiryTime = <?= $_SESSION['verification_code_time'] + 600 ?>;
            const expiryEl = document.getElementById('codeExpiry');
            
            // Element yoksa çalıştırma
            if (!expiryEl) {
                console.warn('codeExpiry element not found');
                return;
            }
            
            function updateExpiry() {
                // Element hala var mı kontrol et
                if (!expiryEl) {
                    return;
                }
                
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTime - now;
                
                if (remaining <= 0) {
                    if (expiryEl) {
                    expiryEl.innerHTML = '<span class="text-red-600 font-semibold">Kod süresi doldu! Yeni kod isteyin.</span>';
                    }
                    // Otomatik olarak yeni kod iste
                    setTimeout(() => {
                        if (confirm('Kod süresi doldu. Yeni kod gönderilsin mi?')) {
                            resendCode();
                        }
                    }, 2000);
                } else {
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    if (expiryEl) {
                    expiryEl.innerHTML = `<span class="text-gray-600">Kalan süre: ${minutes}:${String(seconds).padStart(2, '0')}</span>`;
                    }
                }
            }
            
            updateExpiry();
            const expiryInterval = setInterval(() => {
                // Element hala var mı kontrol et
                if (!document.getElementById('codeExpiry')) {
                    clearInterval(expiryInterval);
                    return;
                }
                updateExpiry();
            }, 1000);
        })();
        <?php endif; ?>
        
        // Cooldown gösterimi
        <?php 
        $cooldown = login_sms_cooldown_remaining();
        if ($cooldown > 0): 
        ?>
        (function() {
            let cooldown = <?= $cooldown ?>;
            const btn = document.getElementById('resendBtn');
            const cooldownEl = document.getElementById('resendCooldown');
            
            if (btn) btn.disabled = true;
            
            const interval = setInterval(() => {
                if (cooldown > 0) {
                    if (cooldownEl) cooldownEl.textContent = `${cooldown} saniye sonra`;
                    cooldown--;
                } else {
                    clearInterval(interval);
                    if (cooldownEl) cooldownEl.textContent = '';
                    if (btn) {
                        btn.disabled = false;
                    }
                }
            }, 1000);
        })();
        <?php endif; ?>
        
        // Form animasyonu
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('scale-105');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('scale-105');
                });
            });
            
            // Doğrulama kodu input'u için otomatik odaklanma
            const verificationInput = document.querySelector('input[name="verification_code"]');
            if (verificationInput) {
                verificationInput.focus();
                verificationInput.addEventListener('input', function() {
                    if (this.value.length === 6) {
                        this.form.submit();
                    }
                });
            }
            
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                button.innerHTML = `
                    <div class="flex items-center justify-center">
                        <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                            ${button.textContent.includes('Doğrula') ? 'Doğrulanıyor...' : 'Giriş yapılıyor...'}
                    </div>
                `;
                button.disabled = true;
                }
            });
        });
    </script>
</body>
</html>