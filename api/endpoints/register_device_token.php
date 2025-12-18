<?php
/**
 * Mobil API - Device Token Registration
 * POST /api/register_device_token.php - Cihaz token'ını kaydet (push notification için)
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['device_token']) || empty($input['device_token'])) {
        sendResponse(false, null, null, 'device_token parametresi gerekli');
    }
    
    if (!isset($input['platform']) || !in_array($input['platform'], ['ios', 'android'])) {
        sendResponse(false, null, null, 'platform parametresi gerekli (ios veya android)');
    }
    
    $device_token = sanitizeInput(trim($input['device_token']), 'string');
    $platform = sanitizeInput($input['platform'], 'string');
    $community_id = sanitizeInput($input['community_id'] ?? '', 'string');
    
    // Superadmin veritabanına kaydet
    $superadminDbPath = __DIR__ . '/../unipanel.sqlite';
    
    if (!file_exists($superadminDbPath)) {
        $superadminDbDir = dirname($superadminDbPath);
        if (!is_dir($superadminDbDir)) {
            @mkdir($superadminDbDir, 0755, true);
        }
        @touch($superadminDbPath);
        @chmod($superadminDbPath, 0640);
    }
    
    $db = new SQLite3($superadminDbPath);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Device tokens tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS device_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        user_email TEXT,
        device_token TEXT NOT NULL,
        platform TEXT NOT NULL,
        community_id TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, device_token, platform)
    )");
    
    $user_id = $currentUser['id'] ?? null;
    $user_email = $currentUser['email'] ?? null;
    
    // Mevcut token'ı kontrol et
    $check_query = $db->prepare("SELECT id FROM device_tokens WHERE user_id = ? AND device_token = ? AND platform = ?");
    $check_query->bindValue(1, $user_id, SQLITE3_TEXT);
    $check_query->bindValue(2, $device_token, SQLITE3_TEXT);
    $check_query->bindValue(3, $platform, SQLITE3_TEXT);
    $existing = $check_query->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($existing) {
        // Güncelle
        $update_query = $db->prepare("UPDATE device_tokens SET user_email = ?, community_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update_query->bindValue(1, $user_email, SQLITE3_TEXT);
        $update_query->bindValue(2, $community_id, SQLITE3_TEXT);
        $update_query->bindValue(3, $existing['id'], SQLITE3_INTEGER);
        $update_query->execute();
        sendResponse(true, ['id' => (int)$existing['id']], 'Device token güncellendi');
    } else {
        // Yeni kayıt
        $insert_query = $db->prepare("INSERT INTO device_tokens (user_id, user_email, device_token, platform, community_id) VALUES (?, ?, ?, ?, ?)");
        $insert_query->bindValue(1, $user_id, SQLITE3_TEXT);
        $insert_query->bindValue(2, $user_email, SQLITE3_TEXT);
        $insert_query->bindValue(3, $device_token, SQLITE3_TEXT);
        $insert_query->bindValue(4, $platform, SQLITE3_TEXT);
        $insert_query->bindValue(5, $community_id, SQLITE3_TEXT);
        $insert_query->execute();
        
        $token_id = $db->lastInsertRowID();
        sendResponse(true, ['id' => (int)$token_id], 'Device token kaydedildi');
    }
    
    $db->close();
    
} catch (Exception $e) {
    error_log("Device token registration error: " . $e->getMessage());
    if (isset($db)) {
        $db->close();
    }
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

