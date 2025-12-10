<?php
/**
 * Mobil API - RSVP Endpoint
 * GET /api/rsvp.php?community_id={id}&event_id={id} - Etkinlik RSVP durumunu getir
 * POST /api/rsvp.php - RSVP kaydı oluştur/güncelle
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

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

// Authentication zorunlu (RSVP işlemleri için)
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
    if (!isset($_GET['community_id']) || empty($_GET['community_id'])) {
        sendResponse(false, null, null, 'community_id parametresi gerekli');
    }
    
    $community_id = sanitizeCommunityId($_GET['community_id']);
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // POST isteği - RSVP kaydı oluştur/güncelle
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['event_id']) || !isset($input['member_name']) || !isset($input['status'])) {
            sendResponse(false, null, null, 'event_id, member_name ve status parametreleri gerekli');
        }
        
        $event_id = (int)$input['event_id'];
        $member_name = sanitizeInput(trim($input['member_name']), 'string');
        $status = sanitizeInput($input['status'], 'string'); // 'attending' veya 'not_attending'
        $member_email = isset($input['member_email']) ? sanitizeInput(trim($input['member_email']), 'email') : null;
        $member_phone = isset($input['member_phone']) ? sanitizeInput(trim($input['member_phone']), 'string') : null;
        
        // Input validation
        if (strlen($member_name) > 200) {
            sendResponse(false, null, null, 'İsim çok uzun (maksimum 200 karakter)');
        }
        
        if (!in_array($status, ['attending', 'not_attending'])) {
            sendResponse(false, null, null, 'Geçersiz durum değeri');
        }
        
        if (!empty($member_email) && !validateEmail($member_email)) {
            sendResponse(false, null, null, 'Geçersiz email formatı');
        }
        
        if (!empty($member_phone) && !validatePhone($member_phone)) {
            sendResponse(false, null, null, 'Geçersiz telefon numarası formatı');
        }
        
        // event_rsvp tablosunu oluştur veya güncelle
        $db->exec("CREATE TABLE IF NOT EXISTS event_rsvp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            club_id INTEGER,
            member_name TEXT NOT NULL,
            member_email TEXT,
            member_phone TEXT,
            rsvp_status TEXT DEFAULT 'attending',
            status TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Mevcut tabloyu kontrol et ve eksik kolonları ekle
        $tableInfo = $db->query("PRAGMA table_info(event_rsvp)");
        $columns = [];
        while ($row = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
            $columns[$row['name']] = true;
        }
        
        // status kolonu yoksa ekle (rsvp_status varsa status'e kopyala)
        if (!isset($columns['status']) && isset($columns['rsvp_status'])) {
            // rsvp_status kolonunu status olarak kullan
            $statusColumn = 'rsvp_status';
        } else if (!isset($columns['status'])) {
            // status kolonu yoksa ekle
            $db->exec("ALTER TABLE event_rsvp ADD COLUMN status TEXT");
            $statusColumn = 'status';
        } else {
            $statusColumn = 'status';
        }
        
        // rsvp_status kolonu yoksa ekle
        if (!isset($columns['rsvp_status'])) {
            $db->exec("ALTER TABLE event_rsvp ADD COLUMN rsvp_status TEXT DEFAULT 'attending'");
        }
        
        // updated_at kolonu yoksa ekle
        if (!isset($columns['updated_at'])) {
            $db->exec("ALTER TABLE event_rsvp ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
        
        // club_id kolonu yoksa ekle
        if (!isset($columns['club_id'])) {
            $db->exec("ALTER TABLE event_rsvp ADD COLUMN club_id INTEGER");
        }
        
        // Mevcut RSVP kaydını kontrol et - email veya telefon ile eşleştir
        $check_query = null;
        $existing = null;
        
        // Önce email ile kontrol et
        if (!empty($member_email)) {
            $check_query = $db->prepare("SELECT id FROM event_rsvp WHERE event_id = ? AND member_email = ?");
            if ($check_query !== false) {
                $check_query->bindValue(1, $event_id, SQLITE3_INTEGER);
                $check_query->bindValue(2, $member_email, SQLITE3_TEXT);
                $result = $check_query->execute();
                if ($result !== false) {
                    $existing = $result->fetchArray(SQLITE3_ASSOC);
                }
            }
        }
        
        // Email ile bulunamazsa telefon ile kontrol et
        if (!$existing && !empty($member_phone)) {
            $check_query = $db->prepare("SELECT id FROM event_rsvp WHERE event_id = ? AND member_phone = ?");
            if ($check_query !== false) {
                $check_query->bindValue(1, $event_id, SQLITE3_INTEGER);
                $check_query->bindValue(2, $member_phone, SQLITE3_TEXT);
                $result = $check_query->execute();
                if ($result !== false) {
                    $existing = $result->fetchArray(SQLITE3_ASSOC);
                }
            }
        }
        
        // Hala bulunamazsa isim ile kontrol et (son çare)
        if (!$existing) {
            $check_query = $db->prepare("SELECT id FROM event_rsvp WHERE event_id = ? AND member_name = ?");
            if ($check_query === false) {
                sendResponse(false, null, null, 'Veritabanı hatası: ' . $db->lastErrorMsg());
            }
            $check_query->bindValue(1, $event_id, SQLITE3_INTEGER);
            $check_query->bindValue(2, $member_name, SQLITE3_TEXT);
            $result = $check_query->execute();
            if ($result === false) {
                sendResponse(false, null, null, 'Veritabanı hatası: ' . $db->lastErrorMsg());
            }
            $existing = $result->fetchArray(SQLITE3_ASSOC);
        }
        
        if ($existing) {
            // Güncelle - hem status hem rsvp_status kolonlarını güncelle
            $update_query = $db->prepare("UPDATE event_rsvp SET status = ?, rsvp_status = ?, member_email = ?, member_phone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($update_query === false) {
                sendResponse(false, null, null, 'Veritabanı hatası: ' . $db->lastErrorMsg());
            }
            $update_query->bindValue(1, $status, SQLITE3_TEXT);
            $update_query->bindValue(2, $status, SQLITE3_TEXT); // rsvp_status'e de yaz
            $update_query->bindValue(3, $member_email, SQLITE3_TEXT);
            $update_query->bindValue(4, $member_phone, SQLITE3_TEXT);
            $update_query->bindValue(5, $existing['id'], SQLITE3_INTEGER);
            if ($update_query->execute() === false) {
                sendResponse(false, null, null, 'Güncelleme hatası: ' . $db->lastErrorMsg());
            }
            
            // RSVP güncellendikten sonra güncel istatistikleri döndür
            $stats_query = $db->prepare("SELECT 
                COUNT(CASE WHEN (status = 'attending' OR rsvp_status = 'attending') THEN 1 END) as attending_count,
                COUNT(CASE WHEN (status = 'not_attending' OR rsvp_status = 'not_attending') THEN 1 END) as not_attending_count,
                COUNT(*) as total_count
                FROM event_rsvp WHERE event_id = ?");
            $stats_query->bindValue(1, $event_id, SQLITE3_INTEGER);
            $stats_result = $stats_query->execute();
            $stats_row = $stats_result->fetchArray(SQLITE3_ASSOC);
            
            sendResponse(true, [
                'id' => (int)$existing['id'],
                'statistics' => [
                    'attending_count' => (int)($stats_row['attending_count'] ?? 0),
                    'not_attending_count' => (int)($stats_row['not_attending_count'] ?? 0),
                    'total_count' => (int)($stats_row['total_count'] ?? 0)
                ]
            ], 'RSVP güncellendi');
        } else {
            // Yeni kayıt - hem status hem rsvp_status kolonlarına yaz
            $insert_query = $db->prepare("INSERT INTO event_rsvp (event_id, club_id, member_name, member_email, member_phone, status, rsvp_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            if ($insert_query === false) {
                sendResponse(false, null, null, 'Veritabanı hatası: ' . $db->lastErrorMsg());
            }
            $insert_query->bindValue(1, $event_id, SQLITE3_INTEGER);
            $insert_query->bindValue(2, 1, SQLITE3_INTEGER); // club_id
            $insert_query->bindValue(3, $member_name, SQLITE3_TEXT);
            $insert_query->bindValue(4, $member_email, SQLITE3_TEXT);
            $insert_query->bindValue(5, $member_phone, SQLITE3_TEXT);
            $insert_query->bindValue(6, $status, SQLITE3_TEXT);
            $insert_query->bindValue(7, $status, SQLITE3_TEXT); // rsvp_status'e de yaz
            if ($insert_query->execute() === false) {
                sendResponse(false, null, null, 'Kayıt hatası: ' . $db->lastErrorMsg());
            }
            
            $rsvp_id = $db->lastInsertRowID();
            
            // RSVP kaydedildikten sonra güncel istatistikleri döndür
            $stats_query = $db->prepare("SELECT 
                COUNT(CASE WHEN (status = 'attending' OR rsvp_status = 'attending') THEN 1 END) as attending_count,
                COUNT(CASE WHEN (status = 'not_attending' OR rsvp_status = 'not_attending') THEN 1 END) as not_attending_count,
                COUNT(*) as total_count
                FROM event_rsvp WHERE event_id = ?");
            $stats_query->bindValue(1, $event_id, SQLITE3_INTEGER);
            $stats_result = $stats_query->execute();
            $stats_row = $stats_result->fetchArray(SQLITE3_ASSOC);
            
            sendResponse(true, [
                'id' => (int)$rsvp_id,
                'statistics' => [
                    'attending_count' => (int)($stats_row['attending_count'] ?? 0),
                    'not_attending_count' => (int)($stats_row['not_attending_count'] ?? 0),
                    'total_count' => (int)($stats_row['total_count'] ?? 0)
                ]
            ], 'RSVP kaydedildi');
        }
    }
    
    // GET isteği - RSVP durumunu getir
    if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
        sendResponse(false, null, null, 'event_id parametresi gerekli');
    }
    
    $event_id = (int)$_GET['event_id'];
    
    // RSVP kayıtlarını çek
    $query = $db->prepare("SELECT * FROM event_rsvp WHERE event_id = ? ORDER BY created_at DESC");
    $query->bindValue(1, $event_id, SQLITE3_INTEGER);
    $result = $query->execute();
    
    $rsvps = [];
    $attending_count = 0;
    $not_attending_count = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // status veya rsvp_status kolonunu kullan
        $rsvpStatus = $row['status'] ?? $row['rsvp_status'] ?? 'not_attending';
        
        if ($rsvpStatus === 'attending') {
            $attending_count++;
        } else {
            $not_attending_count++;
        }
        
        $rsvps[] = [
            'id' => (int)$row['id'],
            'event_id' => (int)$row['event_id'],
            'member_name' => $row['member_name'] ?? '',
            'member_email' => $row['member_email'] ?? null,
            'member_phone' => $row['member_phone'] ?? null,
            'status' => $rsvpStatus,
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
        ];
    }
    
    $db->close();
    
    sendResponse(true, [
        'event_id' => $event_id,
        'rsvps' => $rsvps,
        'statistics' => [
            'attending_count' => $attending_count,
            'not_attending_count' => $not_attending_count,
            'total_count' => count($rsvps)
        ]
    ]);
    
} catch (Exception $e) {
    // Hata logla
    error_log("RSVP Error: " . $e->getMessage());
    error_log("RSVP Stack Trace: " . $e->getTraceAsString());
    
    // Response oluştur
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    
    // HTTP status code ayarla
    http_response_code(500);
    
    // Response gönder
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
} catch (Error $e) {
    // Fatal error yakala
    error_log("RSVP Fatal Error: " . $e->getMessage());
    error_log("RSVP Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    sendResponse(false, null, null, 'Sunucu hatası: ' . $e->getMessage());
}

