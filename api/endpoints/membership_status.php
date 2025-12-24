<?php
/**
 * Mobil API - Membership Status Endpoint
 * GET /api/membership_status.php?community_id={id} - Kullanıcının topluluk üyelik durumunu kontrol et
 */

require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../connection_pool.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(60, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Authentication zorunlu
$currentUser = requireAuth(true);

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // POST isteği - Katılma isteği gönder
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        // POST body'den veya query string'den community_id al
        $raw_input = file_get_contents('php://input');
        $post_data = [];
        
        // Form-urlencoded ise parse et
        if (!empty($raw_input)) {
            parse_str($raw_input, $post_data);
        }
        
        // POST array'ini de kontrol et
        $post_data = array_merge($post_data, $_POST);
        
        $raw_community_id = $post_data['community_id'] ?? $_GET['community_id'] ?? '';
        if (empty($raw_community_id)) {
            sendResponse(false, null, null, 'community_id parametresi gerekli');
        }
        
        try {
            $community_id = sanitizeCommunityId($raw_community_id);
        } catch (Exception $e) {
            sendResponse(false, null, null, 'Geçersiz topluluk ID: ' . $e->getMessage());
        }
        
        $communities_dir = __DIR__ . '/../communities';
        $community_path = $communities_dir . '/' . $community_id;
        $db_path = $community_path . '/unipanel.sqlite';
        
        // Topluluk klasörü yoksa hata ver
        if (!is_dir($community_path)) {
            sendResponse(false, null, null, 'Topluluk bulunamadı');
        }
        
        // Veritabanı dosyası varsa izinlerini kontrol et ve düzelt
        if (file_exists($db_path)) {
            if (!is_readable($db_path) || !is_writable($db_path)) {
                @chmod($db_path, 0666);
                // Hala okunamıyor/yazılamıyorsa klasör izinlerini kontrol et
                if (!is_readable($db_path) || !is_writable($db_path)) {
                    if (!is_writable($community_path)) {
                        @chmod($community_path, 0755);
                    }
                    @chmod($db_path, 0666);
                }
                // Hala okunamıyor/yazılamıyorsa hata ver
                if (!is_readable($db_path) || !is_writable($db_path)) {
                    secureLog('membership_status', "Veritabanı dosyası okunamıyor/yazılamıyor (POST): $db_path (perms: " . substr(sprintf('%o', fileperms($db_path)), -4) . ")");
                    sendResponse(false, null, null, 'Veritabanı dosyasına erişilemiyor. Lütfen sistem yöneticisine başvurun.');
                }
            }
        }
        
        // Veritabanı dosyası yoksa oluştur
        if (!file_exists($db_path)) {
            try {
                // Klasör yazılabilir mi kontrol et
                if (!is_writable($community_path)) {
                    @chmod($community_path, 0755);
                    if (!is_writable($community_path)) {
                        secureLog('membership_status', "Klasör yazılabilir değil (POST): $community_path (perms: " . substr(sprintf('%o', fileperms($community_path)), -4) . ")");
                        sendResponse(false, null, null, 'Topluluk klasörü yazılabilir değil. Lütfen sistem yöneticisine başvurun.');
                    }
                }
            
            // Veritabanı oluştur
            $db = new SQLite3($db_path);
            if (!$db) {
                throw new Exception("SQLite3 bağlantısı kurulamadı");
            }
            
            $db->busyTimeout(5000);
            @$db->exec('PRAGMA journal_mode = WAL');
            @chmod($db_path, 0666);
            
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
            
            // Dosyanın gerçekten oluşturulduğunu kontrol et
            if (!file_exists($db_path)) {
                throw new Exception("Veritabanı dosyası oluşturulamadı");
            }
            
            secureLog('membership_status', "Veritabanı otomatik oluşturuldu (POST): $community_id");
        } catch (Exception $e) {
            secureLog('membership_status', 'Veritabanı oluşturma hatası (POST): ' . $e->getMessage() . ' - Path: ' . $db_path);
            // Dosya oluşturulduysa sil
            if (file_exists($db_path)) {
                @unlink($db_path);
            }
            sendResponse(false, null, null, 'Veritabanı oluşturulamadı: ' . $e->getMessage());
        }
    }
        
        // Connection Pool kullan (10k kullanıcı için kritik)
        $connResult = ConnectionPool::getConnection($db_path, false); // POST için read-write
        if (!$connResult) {
            sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
        }
        $db = $connResult['db'];
        $poolId = $connResult['pool_id'];
        
        // Membership requests tablosunu oluştur
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS membership_requests (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                user_id INTEGER,
                full_name TEXT,
                email TEXT,
                phone TEXT,
                student_id TEXT,
                department TEXT,
                status TEXT DEFAULT 'pending',
                admin_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                additional_data TEXT,
                UNIQUE(club_id, email)
            )");
        } catch (Exception $e) {
            // Tablo oluşturma hatası - devam et
            secureLog('membership_status', 'Tablo oluşturma hatası: ' . $e->getMessage());
        }
        
        // $currentUser kontrolü
        if (!$currentUser || !isset($currentUser['id'])) {
            ConnectionPool::releaseConnection($db_path, $poolId, false);
            sendResponse(false, null, null, 'Kullanıcı bilgisi alınamadı');
        }
        
        $user_id = $currentUser['id'];
        $user_email = strtolower(trim($currentUser['email'] ?? ''));
        $student_id = trim($currentUser['student_id'] ?? '');
        
        // Members tablosunun varlığını kontrol et
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        $members_table_exists = $table_check && $table_check->fetchArray();
        
        $member = null;
        if ($members_table_exists) {
            // Önce üye mi kontrol et
            $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
            if ($member_check) {
                $member_check->bindValue(1, $user_email, SQLITE3_TEXT);
                $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
                $member_result = $member_check->execute();
                if ($member_result) {
                    $member = $member_result->fetchArray(SQLITE3_ASSOC);
                }
            }
        }
        
        if ($member) {
            ConnectionPool::releaseConnection($db_path, $poolId, false);
            sendResponse(false, null, null, 'Zaten topluluğun üyesisiniz.');
        }
        
        // Mevcut başvuru kontrol et
        $existing_request = null;
        $request_check = $db->prepare("SELECT id, status FROM membership_requests WHERE club_id = 1 AND (user_id = ? OR LOWER(email) = LOWER(?)) ORDER BY created_at DESC LIMIT 1");
        if ($request_check) {
            $request_check->bindValue(1, $user_id, SQLITE3_INTEGER);
            $request_check->bindValue(2, $user_email, SQLITE3_TEXT);
            $request_result = $request_check->execute();
            if ($request_result) {
                $existing_request = $request_result->fetchArray(SQLITE3_ASSOC);
            }
        }
        
        if ($existing_request) {
            $status = $existing_request['status'] ?? 'pending';
            if ($status === 'pending') {
                ConnectionPool::releaseConnection($db_path, $poolId, false);
                sendResponse(false, null, null, 'Üyelik başvurunuz zaten inceleniyor.');
            } elseif ($status === 'approved') {
                ConnectionPool::releaseConnection($db_path, $poolId, false);
                sendResponse(false, null, null, 'Zaten topluluğun üyesisiniz.');
            } else {
                // Rejected veya diğer durumlar - önceki başvuruyu sil ve yeni başvuru yap
                try {
                    $delete_old = $db->prepare("DELETE FROM membership_requests WHERE club_id = 1 AND (user_id = ? OR LOWER(email) = LOWER(?))");
                    $delete_old->bindValue(1, $user_id, SQLITE3_INTEGER);
                    $delete_old->bindValue(2, $user_email, SQLITE3_TEXT);
                    $delete_old->execute();
                } catch (Exception $e) {
                    secureLog('membership_status', 'Eski başvuru silme hatası: ' . $e->getMessage());
                }
                // Devam et - yeni başvuru oluşturacak
            }
        }
        
        // Yeni başvuru oluştur
        $full_name = sanitizeInput(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')), 'string');
        $phone = sanitizeInput(trim($currentUser['phone_number'] ?? ''), 'string');
        $department = sanitizeInput(trim($currentUser['department'] ?? ''), 'string');
        
        // Hassas bilgileri JSON'a eklemeden önce sanitize et
        $sanitizedUser = $currentUser;
        if (isset($sanitizedUser['email'])) {
            $sanitizedUser['email'] = sanitizeInput($sanitizedUser['email'], 'email');
        }
        $additional_data = json_encode($sanitizedUser, JSON_UNESCAPED_UNICODE);
        
        $stmt = $db->prepare("INSERT INTO membership_requests (club_id, user_id, full_name, email, phone, student_id, department, additional_data) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $full_name, SQLITE3_TEXT);
        $stmt->bindValue(3, $user_email, SQLITE3_TEXT);
        $stmt->bindValue(4, $phone, SQLITE3_TEXT);
        $stmt->bindValue(5, $student_id, SQLITE3_TEXT);
        $stmt->bindValue(6, $department, SQLITE3_TEXT);
        $stmt->bindValue(7, $additional_data, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            $request_id = $db->lastInsertRowID();
            ConnectionPool::releaseConnection($db_path, $poolId, false);
            sendResponse(true, [
                'message' => 'Üyelik başvurunuz alındı. Onaylandığında bilgilendirileceksiniz.',
                'status' => 'pending',
                'request_id' => (string)$request_id
            ], 'Üyelik başvurunuz başarıyla gönderildi.');
        } else {
            ConnectionPool::releaseConnection($db_path, $poolId, false);
            sendResponse(false, null, null, 'Başvuru kaydedilemedi.');
        }
    }
    
    // GET isteği - Üyelik durumunu kontrol et
    if (!isset($_GET['community_id']) || empty($_GET['community_id'])) {
        sendResponse(false, null, null, 'community_id parametresi gerekli');
    }
    
    try {
        $community_id = sanitizeCommunityId($_GET['community_id']);
    } catch (Exception $e) {
        sendResponse(false, null, null, 'Geçersiz topluluk ID: ' . $e->getMessage());
    }
    $communities_dir = __DIR__ . '/../communities';
    $community_path = $communities_dir . '/' . $community_id;
    $db_path = $community_path . '/unipanel.sqlite';
    
    // Topluluk klasörü yoksa hata ver
    if (!is_dir($community_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    // Veritabanı dosyası varsa izinlerini kontrol et ve düzelt
    if (file_exists($db_path)) {
        if (!is_readable($db_path)) {
            @chmod($db_path, 0666);
            // Hala okunamıyorsa klasör izinlerini kontrol et
            if (!is_readable($db_path)) {
                if (!is_writable($community_path)) {
                    @chmod($community_path, 0755);
                }
                @chmod($db_path, 0666);
            }
            // Hala okunamıyorsa hata ver
            if (!is_readable($db_path)) {
                secureLog('membership_status', "Veritabanı dosyası okunamıyor: $db_path (perms: " . substr(sprintf('%o', fileperms($db_path)), -4) . ")");
                sendResponse(false, null, null, 'Veritabanı dosyasına erişilemiyor. Lütfen sistem yöneticisine başvurun.');
            }
        }
    }
    
    // Veritabanı dosyası yoksa oluştur
    if (!file_exists($db_path)) {
        try {
            // Klasör yazılabilir mi kontrol et
            if (!is_writable($community_path)) {
                @chmod($community_path, 0755);
                if (!is_writable($community_path)) {
                    secureLog('membership_status', "Klasör yazılabilir değil: $community_path (perms: " . substr(sprintf('%o', fileperms($community_path)), -4) . ")");
                    sendResponse(false, null, null, 'Topluluk klasörü yazılabilir değil. Lütfen sistem yöneticisine başvurun.');
                }
            }
            
            // Veritabanı oluştur
            $db = new SQLite3($db_path);
            if (!$db) {
                throw new Exception("SQLite3 bağlantısı kurulamadı");
            }
            
            $db->busyTimeout(5000);
            @$db->exec('PRAGMA journal_mode = WAL');
            @chmod($db_path, 0666);
            
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
            
            // Dosyanın gerçekten oluşturulduğunu kontrol et
            if (!file_exists($db_path)) {
                throw new Exception("Veritabanı dosyası oluşturulamadı");
            }
            
            secureLog('membership_status', "Veritabanı otomatik oluşturuldu: $community_id");
        } catch (Exception $e) {
            secureLog('membership_status', 'Veritabanı oluşturma hatası: ' . $e->getMessage() . ' - Path: ' . $db_path);
            // Dosya oluşturulduysa sil
            if (file_exists($db_path)) {
                @unlink($db_path);
            }
            sendResponse(false, null, null, 'Veritabanı oluşturulamadı: ' . $e->getMessage());
        }
    }
    
    // Connection Pool kullan (10k kullanıcı için kritik)
    $connResult = ConnectionPool::getConnection($db_path, true);
    if (!$connResult) {
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
    }
    $db = $connResult['db'];
    $poolId = $connResult['pool_id'];
    
    // Membership requests tablosunu oluştur - Hata kontrolü ile
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS membership_requests (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            user_id INTEGER,
            full_name TEXT,
            email TEXT,
            phone TEXT,
            student_id TEXT,
            department TEXT,
            status TEXT DEFAULT 'pending',
            admin_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            additional_data TEXT,
            UNIQUE(club_id, email)
        )");
    } catch (Exception $e) {
        // Tablo oluşturma hatası - devam et
        secureLog('membership_status', 'Tablo oluşturma hatası: ' . $e->getMessage());
    }
    
    // $currentUser kontrolü
    if (!$currentUser || !isset($currentUser['id'])) {
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(false, null, null, 'Kullanıcı bilgisi alınamadı');
    }
    
    $user_id = $currentUser['id'];
    $user_email = strtolower(trim($currentUser['email'] ?? ''));
    $student_id = trim($currentUser['student_id'] ?? '');
    
    // Members tablosunun varlığını kontrol et - Hata kontrolü ile
    $members_table_exists = false;
    try {
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        $members_table_exists = $table_check && $table_check->fetchArray();
    } catch (Exception $e) {
        secureLog('membership_status', 'Tablo kontrolü hatası: ' . $e->getMessage());
    }
    
    $member = null;
    if ($members_table_exists) {
        // Önce üye mi kontrol et (members tablosunda user_id yok, sadece email ve student_id var)
        try {
            $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
            if ($member_check) {
                $member_check->bindValue(1, $user_email, SQLITE3_TEXT);
                $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
                $member_result = $member_check->execute();
                if ($member_result) {
                    $member = $member_result->fetchArray(SQLITE3_ASSOC);
                }
            }
        } catch (Exception $e) {
            secureLog('membership_status', 'Member kontrolü hatası: ' . $e->getMessage());
        }
    }
    
    if ($member) {
        // Bağlantıyı pool'a geri ver
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(true, [
            'status' => 'member',
            'is_member' => true,
            'is_pending' => false
        ], 'Topluluğun üyesisiniz.');
    }
    
    // Membership request kontrol et - Hata kontrolü ile
    $request = null;
    try {
        $request_check = $db->prepare("SELECT id, status, created_at FROM membership_requests WHERE club_id = 1 AND (user_id = ? OR LOWER(email) = LOWER(?)) ORDER BY created_at DESC LIMIT 1");
        if ($request_check) {
            $request_check->bindValue(1, $user_id, SQLITE3_INTEGER);
            $request_check->bindValue(2, $user_email, SQLITE3_TEXT);
            $request_result = $request_check->execute();
            if ($request_result) {
                $request = $request_result->fetchArray(SQLITE3_ASSOC);
            }
        }
    } catch (Exception $e) {
        secureLog('membership_status', 'Request kontrolü hatası: ' . $e->getMessage());
    }
    
    if ($request) {
        $status = $request['status'] ?? 'pending';
        // Approved ise member olarak döndür (mobil uygulama uyumluluğu için)
        $response_status = ($status === 'approved') ? 'member' : $status;
        // Bağlantıyı pool'a geri ver
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(true, [
            'status' => $response_status,
            'is_member' => $status === 'approved', // Approved ise üye sayıl
            'is_pending' => $status === 'pending',
            'request_id' => (string)($request['id'] ?? ''),
            'created_at' => $request['created_at'] ?? null
        ], $status === 'pending' ? 'Üyelik başvurunuz inceleniyor.' : ($status === 'approved' ? 'Üyelik başvurunuz onaylandı. Artık topluluğun üyesisiniz!' : 'Üyelik başvurunuz reddedildi.'));
    }
    
    // Hiçbir durum yok
    // Bağlantıyı pool'a geri ver
    ConnectionPool::releaseConnection($db_path, $poolId, true);
    sendResponse(true, [
        'status' => 'none',
        'is_member' => false,
        'is_pending' => false
    ], 'Topluluğa üye değilsiniz.');
    
} catch (Exception $e) {
    // Veritabanı bağlantısını pool'a geri ver (eğer açıksa)
    if (isset($db_path) && isset($poolId)) {
        try {
            ConnectionPool::releaseConnection($db_path, $poolId, true);
        } catch (Exception $closeError) {
            // Kapatma hatası - görmezden gel
        }
    } elseif (isset($db) && $db) {
        try {
            $db->close();
        } catch (Exception $closeError) {
            // Kapatma hatası - görmezden gel
        }
    }
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

