<?php
/**
 * Mobil API - Membership Status Endpoint
 * GET /api/membership_status.php?community_id={id} - Kullanıcının topluluk üyelik durumunu kontrol et
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/connection_pool.php';

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
        
        $community_id = sanitizeCommunityId($post_data['community_id'] ?? $_GET['community_id'] ?? '');
        
        if (empty($community_id)) {
            sendResponse(false, null, null, 'community_id parametresi gerekli');
        }
        
        $communities_dir = __DIR__ . '/../communities';
        $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
        
        if (!file_exists($db_path)) {
            sendResponse(false, null, null, 'Topluluk bulunamadı');
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
            ConnectionPool::releaseConnection($db_path, $poolId, false);
            if ($status === 'pending') {
                sendResponse(false, null, null, 'Üyelik başvurunuz zaten inceleniyor.');
            } else {
                sendResponse(false, null, null, 'Daha önce bir başvuru yapmışsınız.');
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
    
    $community_id = sanitizeCommunityId($_GET['community_id']);
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
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
        // Bağlantıyı pool'a geri ver
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(true, [
            'status' => $status,
            'is_member' => false,
            'is_pending' => $status === 'pending',
            'request_id' => (string)($request['id'] ?? ''),
            'created_at' => $request['created_at'] ?? null
        ], $status === 'pending' ? 'Üyelik başvurunuz inceleniyor.' : ($status === 'approved' ? 'Üyelik başvurunuz onaylandı.' : 'Üyelik başvurunuz reddedildi.'));
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

