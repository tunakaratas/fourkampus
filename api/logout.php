<?php
/**
 * Mobil API - Logout Endpoint
 * POST /api/logout.php - Token iptal et (çıkış yap)
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
    
    // Kullanıcı authenticated olmalı
    $user = requireAuth(true);
    
    // CSRF koruması
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            sendResponse(false, null, null, 'CSRF token geçersiz');
        }
    }
    
    // Token'ı al
    $token = getAuthToken();
    
    if (!$token) {
        sendResponse(false, null, null, 'Token bulunamadı');
    }
    
    // Token'ı iptal et
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        sendResponse(false, null, null, 'Veritabanı dosyası bulunamadı');
    }
    
    $db = new SQLite3($system_db_path);
    @$db->exec('PRAGMA journal_mode = DELETE');
    
    $stmt = $db->prepare("UPDATE api_tokens SET revoked_at = datetime('now') WHERE token = ?");
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $db->close();
    
    sendResponse(true, null, 'Çıkış başarılı. Token iptal edildi.');
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('Çıkış işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}
