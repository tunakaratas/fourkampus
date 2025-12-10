<?php
/**
 * Mobil API - Notifications Endpoint
 * GET /api/notifications.php - Kullanıcıya ait bildirimleri listele
 * POST /api/notifications.php?id={id}&action=read - Bildirimi okundu olarak işaretle
 */

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

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

// Authentication zorunlu (bildirimler kullanıcıya özel)
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
    $user_id = $currentUser['id'];
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        sendResponse(false, null, null, 'Veritabanı dosyası bulunamadı');
    }
    
    $db = new SQLite3($system_db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Bildirimler tablosunu oluştur (yoksa)
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        type TEXT DEFAULT 'info',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        link_url TEXT,
        link_text TEXT
    )");
    
    // POST isteği - Bildirimi okundu olarak işaretle
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $notification_id = $input['id'] ?? $_GET['id'] ?? null;
        
        if (!$notification_id) {
            sendResponse(false, null, null, 'Bildirim ID gerekli');
        }
        
        // Sadece kullanıcının kendi bildirimini güncelleyebilir
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bindValue(1, $notification_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result) {
            sendResponse(true, ['id' => $notification_id, 'is_read' => true]);
        } else {
            sendResponse(false, null, null, 'Bildirim güncellenemedi');
        }
    }
    
    // PUT isteği - Tüm bildirimleri okundu olarak işaretle
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        // Sadece kullanıcının kendi bildirimlerini güncelle
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result) {
            sendResponse(true, ['updated' => true]);
        } else {
            sendResponse(false, null, null, 'Bildirimler güncellenemedi');
        }
    }
    
    // GET isteği - Sadece kullanıcının kendi bildirimlerini listele
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    
    $notifications = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notifications[] = [
            'id' => (string)$row['id'],
            'title' => $row['title'] ?? '',
            'message' => $row['message'] ?? '',
            'type' => $row['type'] ?? 'info',
            'is_read' => (bool)($row['is_read'] ?? 0),
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'link_url' => $row['link_url'] ?? null,
            'link_text' => $row['link_text'] ?? null
        ];
    }
    
    $db->close();
    sendResponse(true, $notifications);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

