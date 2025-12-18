<?php
/**
 * Mobil API - Saved Campaigns Endpoint
 * POST /api/saved_campaigns.php - Kampanya kaydetme durumunu toggle et
 */

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

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

// Rate limiting
if (!checkRateLimit(30, 60)) {
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    // CSRF koruması
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            sendResponse(false, null, null, 'CSRF token geçersiz');
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['campaign_id']) || empty($input['campaign_id'])) {
        sendResponse(false, null, null, 'campaign_id parametresi gerekli');
    }
    
    $campaign_id = basename($input['campaign_id']);
    $user_id = $currentUser['id'];
    
    // Kaydedilen kampanyalar için veritabanı tablosu oluştur (eğer yoksa)
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        sendResponse(false, null, null, 'Veritabanı dosyası bulunamadı');
    }
    
    $db = new SQLite3($system_db_path);
    @$db->exec('PRAGMA journal_mode = DELETE');
    
    // Kaydedilen kampanyalar tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS user_saved_campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        campaign_id TEXT NOT NULL,
        community_id TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, campaign_id)
    )");
    
    // Community ID'yi al (campaign_id'den parse et veya input'tan al)
    $community_id = $input['community_id'] ?? '';
    
    // Mevcut kayıt durumunu kontrol et
    $check_stmt = $db->prepare("SELECT id FROM user_saved_campaigns WHERE user_id = ? AND campaign_id = ?");
    $check_stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $check_stmt->bindValue(2, $campaign_id, SQLITE3_TEXT);
    $result = $check_stmt->execute();
    $existing = $result->fetchArray();
    
    if ($existing) {
        // Kayıttan çıkar
        $delete_stmt = $db->prepare("DELETE FROM user_saved_campaigns WHERE user_id = ? AND campaign_id = ?");
        $delete_stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $delete_stmt->bindValue(2, $campaign_id, SQLITE3_TEXT);
        $delete_stmt->execute();
        $isSaved = false;
    } else {
        // Kaydet
        $insert_stmt = $db->prepare("INSERT INTO user_saved_campaigns (user_id, campaign_id, community_id) VALUES (?, ?, ?)");
        $insert_stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $insert_stmt->bindValue(2, $campaign_id, SQLITE3_TEXT);
        $insert_stmt->bindValue(3, $community_id, SQLITE3_TEXT);
        $insert_stmt->execute();
        $isSaved = true;
    }
    
    $db->close();
    
    sendResponse(true, ['isSaved' => $isSaved], null, null);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

