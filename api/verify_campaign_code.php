<?php
/**
 * Kampanya Kodu Doğrulama API
 * Dükkanda kampanya kodunu doğrulamak için kullanılır
 */

require_once __DIR__ . '/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = null, $message = null, $error = null) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message
    ];
    
    if ($error) {
        $response['error'] = $error;
    }
    
    http_response_code($success ? 200 : 400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['code']) || empty(trim($input['code']))) {
        sendResponse(false, null, null, 'Kampanya kodu gerekli');
    }
    
    if (!isset($input['campaign_id']) || empty(trim($input['campaign_id']))) {
        sendResponse(false, null, null, 'Kampanya ID gerekli');
    }
    
    $code = sanitizeInput(trim($input['code']), 'string');
    $campaign_id = sanitizeInput(trim($input['campaign_id']), 'string');
    
    // Community ID'yi campaign_id'den çıkar (format: community_id-campaign_id)
    $parts = explode('-', $campaign_id);
    if (count($parts) !== 2) {
        sendResponse(false, null, null, 'Geçersiz kampanya ID formatı');
    }
    
    try {
        $community_id = sanitizeCommunityId($parts[0]);
    } catch (Exception $e) {
        sendResponse(false, null, null, 'Geçersiz community ID: ' . $e->getMessage());
    }
    $actual_campaign_id = (int)$parts[1];
    
    // Community veritabanına bağlan
    $communities_dir = __DIR__ . '/../public/communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    $db = new SQLite3($db_path);
    if (!$db) {
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
    }
    
    @$db->exec('PRAGMA journal_mode = DELETE');
    
    // campaign_user_codes tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS campaign_user_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        code TEXT NOT NULL UNIQUE,
        qr_code_data TEXT,
        used INTEGER DEFAULT 0,
        used_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
    )");
    
    // Index oluştur
    $db->exec("CREATE INDEX IF NOT EXISTS idx_code ON campaign_user_codes(code)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_campaign_user ON campaign_user_codes(campaign_id, user_id)");
    
    // Kampanyayı kontrol et
    $campaign_stmt = $db->prepare("SELECT id, title, start_date, end_date, is_active, requires_membership FROM campaigns WHERE id = ?");
    $campaign_stmt->bindValue(1, $actual_campaign_id, SQLITE3_INTEGER);
    $campaign_result = $campaign_stmt->execute();
    $campaign = $campaign_result->fetchArray(SQLITE3_ASSOC);
    
    if (!$campaign) {
        $db->close();
        sendResponse(false, null, null, 'Kampanya bulunamadı');
    }
    
    // Kampanya aktif mi kontrol et
    $now = date('Y-m-d H:i:s');
    if ($campaign['is_active'] != 1) {
        $db->close();
        sendResponse(false, null, null, 'Bu kampanya aktif değil');
    }
    
    if ($campaign['start_date'] > $now || $campaign['end_date'] < $now) {
        $db->close();
        sendResponse(false, null, null, 'Bu kampanya şu anda geçerli değil');
    }
    
    // Kodu kontrol et
    $code_stmt = $db->prepare("SELECT id, user_id, used, used_at, created_at FROM campaign_user_codes WHERE code = ? AND campaign_id = ?");
    $code_stmt->bindValue(1, $code, SQLITE3_TEXT);
    $code_stmt->bindValue(2, $actual_campaign_id, SQLITE3_INTEGER);
    $code_result = $code_stmt->execute();
    $code_row = $code_result->fetchArray(SQLITE3_ASSOC);
    
    if (!$code_row) {
        $db->close();
        sendResponse(false, null, null, 'Geçersiz kampanya kodu');
    }
    
    // Kod daha önce kullanılmış mı kontrol et
    if ($code_row['used'] == 1) {
        $used_at = $code_row['used_at'];
        $db->close();
        sendResponse(false, null, null, "Bu kod daha önce kullanılmış (Kullanım tarihi: $used_at)");
    }
    
    // Kodu kullanıldı olarak işaretle
    $update_stmt = $db->prepare("UPDATE campaign_user_codes SET used = 1, used_at = ? WHERE id = ?");
    $update_stmt->bindValue(1, $now, SQLITE3_TEXT);
    $update_stmt->bindValue(2, $code_row['id'], SQLITE3_INTEGER);
    $update_stmt->execute();
    
    $db->close();
    
    sendResponse(true, [
        'campaign_id' => $campaign_id,
        'campaign_title' => $campaign['title'],
        'code' => $code,
        'used_at' => $now,
        'user_id' => $code_row['user_id']
    ], 'Kampanya kodu başarıyla doğrulandı ve kullanıldı');
    
} catch (Exception $e) {
    error_log("Campaign code verification error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    http_response_code(500);
    sendResponse(false, null, null, 'Kod doğrulama sırasında bir hata oluştu: ' . $e->getMessage());
}

