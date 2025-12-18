<?php
/**
 * Event RSVP API Endpoint
 * RESTful API for event RSVP management
 * 
 * GET    /api/event_rsvp.php?community_id={id}&event_id={id} - Get user's RSVP status
 * POST   /api/event_rsvp.php?community_id={id}&event_id={id} - Create/Update RSVP
 * DELETE /api/event_rsvp.php?community_id={id}&event_id={id} - Cancel RSVP
 * GET    /api/event_rsvp.php?community_id={id}&event_id={id}&action=list - Get RSVP list (admin)
 */

// Session başlat (eğer başlatılmamışsa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    // Community ID kontrolü
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
    $db->busyTimeout(5000); // 5 saniye timeout
    
    // Event ID kontrolü
    if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
        sendResponse(false, null, null, 'event_id parametresi gerekli');
    }
    
    $event_id = (int)$_GET['event_id'];
    
    // Event var mı kontrol et
    $event_check = $db->prepare("SELECT id, title, date, time, location FROM events WHERE id = ? LIMIT 1");
    if (!$event_check) {
        sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
    }
    $event_check->bindValue(1, $event_id, SQLITE3_INTEGER);
    $event_result = $event_check->execute();
    if (!$event_result) {
        sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
    }
    $event = $event_result->fetchArray(SQLITE3_ASSOC);
    
    if (!$event) {
        sendResponse(false, null, null, 'Etkinlik bulunamadı');
    }
    
    // RSVP tablosunu oluştur
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
    
    // Eksik kolonları kontrol et ve ekle
    $table_info = $db->query("PRAGMA table_info(event_rsvp)");
    $columns = [];
    while ($row = $table_info->fetchArray(SQLITE3_ASSOC)) {
        $columns[$row['name']] = true;
    }
    
    if (!isset($columns['status'])) {
        $db->exec("ALTER TABLE event_rsvp ADD COLUMN status TEXT");
    }
    if (!isset($columns['rsvp_status'])) {
        $db->exec("ALTER TABLE event_rsvp ADD COLUMN rsvp_status TEXT DEFAULT 'attending'");
    }
    if (!isset($columns['updated_at'])) {
        $db->exec("ALTER TABLE event_rsvp ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    }
    if (!isset($columns['club_id'])) {
        $db->exec("ALTER TABLE event_rsvp ADD COLUMN club_id INTEGER");
    }
    
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET - User's RSVP status
    if ($method === 'GET' && $action !== 'list') {
        $currentUser = optionalAuth();
        $user_email = $currentUser['email'] ?? $_GET['user_email'] ?? '';
        
        if (empty($user_email)) {
            sendResponse(false, null, null, 'E-posta adresi gerekli');
        }
        
        $rsvp_query = $db->prepare("
            SELECT * FROM event_rsvp 
            WHERE event_id = ? AND member_email = ? 
            LIMIT 1
        ");
        if (!$rsvp_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $rsvp_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $rsvp_query->bindValue(2, $user_email, SQLITE3_TEXT);
        $rsvp_result = $rsvp_query->execute();
        if (!$rsvp_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $rsvp = $rsvp_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$rsvp) {
            sendResponse(true, [
                'has_rsvp' => false,
                'status' => null,
                'event_id' => $event_id
            ]);
        }
        
        $status = $rsvp['status'] ?? $rsvp['rsvp_status'] ?? 'not_attending';
        
        sendResponse(true, [
            'has_rsvp' => true,
            'id' => (int)$rsvp['id'],
            'status' => $status,
            'member_name' => $rsvp['member_name'] ?? '',
            'member_email' => $rsvp['member_email'] ?? '',
            'member_phone' => $rsvp['member_phone'] ?? null,
            'created_at' => $rsvp['created_at'] ?? null,
            'updated_at' => $rsvp['updated_at'] ?? null,
            'event_id' => $event_id
        ]);
    }
    
    // GET - RSVP list (admin)
    if ($method === 'GET' && $action === 'list') {
        // Session kontrolü - admin panelinden çağrı yapılıyorsa session'ı kontrol et
        if (isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
            // Session'dan admin bilgisi var, devam et
            $currentUser = ['id' => $_SESSION['admin_id'], 'email' => $_SESSION['admin_email'] ?? ''];
        } else {
            // Session yoksa token kontrolü yap
            $currentUser = requireAuth(true);
        }
        
        $rsvp_query = $db->prepare("
            SELECT * FROM event_rsvp 
            WHERE event_id = ? 
            ORDER BY created_at DESC
        ");
        if (!$rsvp_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $rsvp_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $rsvp_result = $rsvp_query->execute();
        if (!$rsvp_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        
        $rsvps = [];
        $attending_count = 0;
        $not_attending_count = 0;
        
        while ($row = $rsvp_result->fetchArray(SQLITE3_ASSOC)) {
            $status = $row['status'] ?? $row['rsvp_status'] ?? 'not_attending';
            
            if ($status === 'attending') {
                $attending_count++;
            } else {
                $not_attending_count++;
            }
            
            $rsvps[] = [
                'id' => (int)$row['id'],
                'member_name' => $row['member_name'] ?? '',
                'member_email' => $row['member_email'] ?? '',
                'member_phone' => $row['member_phone'] ?? null,
                'status' => $status,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null
            ];
        }
        
        sendResponse(true, [
            'event_id' => $event_id,
            'rsvps' => $rsvps,
            'statistics' => [
                'attending_count' => $attending_count,
                'not_attending_count' => $not_attending_count,
                'total_count' => count($rsvps)
            ]
        ]);
    }
    
    // POST - Create/Update RSVP
    if ($method === 'POST') {
        $currentUser = optionalAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $member_name = trim($input['member_name'] ?? '');
        $member_email = trim($input['member_email'] ?? $currentUser['email'] ?? '');
        $member_phone = isset($input['member_phone']) ? trim($input['member_phone']) : null;
        $status = trim($input['status'] ?? 'attending');
        
        if (empty($member_name)) {
            sendResponse(false, null, null, 'İsim gerekli');
        }
        
        if (empty($member_email)) {
            sendResponse(false, null, null, 'E-posta adresi gerekli');
        }
        
        if (!filter_var($member_email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, null, null, 'Geçersiz e-posta adresi');
        }
        
        if (!in_array($status, ['attending', 'not_attending'])) {
            sendResponse(false, null, null, 'Geçersiz durum. "attending" veya "not_attending" olmalı');
        }
        
        // Üyelik kontrolü - members tablosundan e-posta ile kontrol et
        $member_email_lower = strtolower($member_email);
        $student_id = trim($currentUser['student_id'] ?? '');
        // Üyelik kontrolü - hem members hem de approved membership_requests
        $is_member = false;
        
        // Önce members tablosunu kontrol et
        $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
        if ($member_check) {
            $member_check->bindValue(1, $member_email_lower, SQLITE3_TEXT);
            $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
            $member_result = $member_check->execute();
            if ($member_result) {
                $member = $member_result->fetchArray(SQLITE3_ASSOC);
                if ($member) {
                    $is_member = true;
                }
            }
        }
        
        // Eğer members tablosunda yoksa, approved membership_requests'i kontrol et
        if (!$is_member) {
            $request_check = $db->prepare("SELECT id, status FROM membership_requests WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) ORDER BY created_at DESC LIMIT 1");
            if ($request_check) {
                $request_check->bindValue(1, $member_email_lower, SQLITE3_TEXT);
                $request_check->bindValue(2, $student_id, SQLITE3_TEXT);
                $request_result = $request_check->execute();
                if ($request_result) {
                    $request = $request_result->fetchArray(SQLITE3_ASSOC);
                    if ($request && ($request['status'] ?? '') === 'approved') {
                        $is_member = true;
                    }
                }
            }
        }
        
        if (!$is_member) {
            sendResponse(false, null, null, 'Etkinliğe katılım durumunu belirtmek için topluluğa üye olmanız gerekiyor');
        }
        
        // Mevcut RSVP'yi kontrol et
        $existing_query = $db->prepare("
            SELECT id FROM event_rsvp 
            WHERE event_id = ? AND member_email = ?
            LIMIT 1
        ");
        if (!$existing_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $existing_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $existing_query->bindValue(2, $member_email, SQLITE3_TEXT);
        $existing_result = $existing_query->execute();
        if (!$existing_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $existing = $existing_result->fetchArray(SQLITE3_ASSOC);
        
        if ($existing) {
            // Update
            $update_stmt = $db->prepare("
                UPDATE event_rsvp 
                SET member_name = ?, 
                    member_phone = ?, 
                    status = ?, 
                    rsvp_status = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $update_stmt->bindValue(1, $member_name, SQLITE3_TEXT);
            $update_stmt->bindValue(2, $member_phone, $member_phone ? SQLITE3_TEXT : SQLITE3_NULL);
            $update_stmt->bindValue(3, $status, SQLITE3_TEXT);
            $update_stmt->bindValue(4, $status, SQLITE3_TEXT);
            $update_stmt->bindValue(5, $existing['id'], SQLITE3_INTEGER);
            
            if (!$update_stmt->execute()) {
                sendResponse(false, null, null, 'RSVP güncellenemedi: ' . $db->lastErrorMsg());
            }
            
            $rsvp_id = $existing['id'];
            $action_type = 'updated';
        } else {
            // Create
            $insert_stmt = $db->prepare("
                INSERT INTO event_rsvp (event_id, club_id, member_name, member_email, member_phone, status, rsvp_status) 
                VALUES (?, 1, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->bindValue(1, $event_id, SQLITE3_INTEGER);
            $insert_stmt->bindValue(2, $member_name, SQLITE3_TEXT);
            $insert_stmt->bindValue(3, $member_email, SQLITE3_TEXT);
            $insert_stmt->bindValue(4, $member_phone, $member_phone ? SQLITE3_TEXT : SQLITE3_NULL);
            $insert_stmt->bindValue(5, $status, SQLITE3_TEXT);
            $insert_stmt->bindValue(6, $status, SQLITE3_TEXT);
            
            if (!$insert_stmt->execute()) {
                sendResponse(false, null, null, 'RSVP kaydedilemedi: ' . $db->lastErrorMsg());
            }
            
            $rsvp_id = $db->lastInsertRowID();
            $action_type = 'created';
        }
        
        // Statistics
        $stats_query = $db->prepare("
            SELECT 
                COUNT(CASE WHEN (status = 'attending' OR rsvp_status = 'attending') THEN 1 END) as attending_count,
                COUNT(CASE WHEN (status = 'not_attending' OR rsvp_status = 'not_attending') THEN 1 END) as not_attending_count,
                COUNT(*) as total_count
            FROM event_rsvp 
            WHERE event_id = ?
        ");
        if (!$stats_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $stats_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $stats_result = $stats_query->execute();
        if (!$stats_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $stats_row = $stats_result->fetchArray(SQLITE3_ASSOC);
        
        sendResponse(true, [
            'id' => (int)$rsvp_id,
            'status' => $status,
            'action' => $action_type,
            'statistics' => [
                'attending_count' => (int)($stats_row['attending_count'] ?? 0),
                'not_attending_count' => (int)($stats_row['not_attending_count'] ?? 0),
                'total_count' => (int)($stats_row['total_count'] ?? 0)
            ]
        ], $action_type === 'created' ? 'RSVP kaydedildi' : 'RSVP güncellendi');
    }
    
    // DELETE - Cancel RSVP
    if ($method === 'DELETE') {
        $currentUser = optionalAuth();
        
        $user_email = $currentUser['email'] ?? $_GET['user_email'] ?? '';
        
        if (empty($user_email)) {
            sendResponse(false, null, null, 'E-posta adresi gerekli');
        }
        
        $delete_stmt = $db->prepare("
            DELETE FROM event_rsvp 
            WHERE event_id = ? AND member_email = ?
        ");
        $delete_stmt->bindValue(1, $event_id, SQLITE3_INTEGER);
        $delete_stmt->bindValue(2, $user_email, SQLITE3_TEXT);
        
        if (!$delete_stmt->execute()) {
            sendResponse(false, null, null, 'RSVP silinemedi: ' . $db->lastErrorMsg());
        }
        
        // Statistics
        $stats_query = $db->prepare("
            SELECT 
                COUNT(CASE WHEN (status = 'attending' OR rsvp_status = 'attending') THEN 1 END) as attending_count,
                COUNT(CASE WHEN (status = 'not_attending' OR rsvp_status = 'not_attending') THEN 1 END) as not_attending_count,
                COUNT(*) as total_count
            FROM event_rsvp 
            WHERE event_id = ?
        ");
        if (!$stats_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $stats_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $stats_result = $stats_query->execute();
        if (!$stats_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $stats_row = $stats_result->fetchArray(SQLITE3_ASSOC);
        
        sendResponse(true, [
            'statistics' => [
                'attending_count' => (int)($stats_row['attending_count'] ?? 0),
                'not_attending_count' => (int)($stats_row['not_attending_count'] ?? 0),
                'total_count' => (int)($stats_row['total_count'] ?? 0)
            ]
        ], 'RSVP iptal edildi');
    }
    
    sendResponse(false, null, null, 'Geçersiz istek');
    
} catch (Exception $e) {
    error_log("Event RSVP API Error: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    sendResponse(false, null, null, 'Sunucu hatası: ' . $e->getMessage());
} catch (Error $e) {
    error_log("Event RSVP API Fatal Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, null, null, 'Kritik hata: ' . $e->getMessage());
} finally {
    if (isset($db)) {
        $db->close();
    }
}

