<?php
/**
 * Mobil API - Leave Community Endpoint
 * DELETE /api/leave_community.php?community_id={id} - Topluluktan ayrıl
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/connection_pool.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(10, 60)) {
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
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        sendResponse(false, null, null, 'Sadece DELETE istekleri kabul edilir');
    }
    
    // CSRF koruması
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            sendResponse(false, null, null, 'CSRF token geçersiz');
        }
    }
    
    // community_id parametresini al (query string veya body'den)
    $raw_input = file_get_contents('php://input');
    $input = [];
    
    if (!empty($raw_input)) {
        $input = json_decode($raw_input, true) ?: [];
    }
    
    $community_id = sanitizeCommunityId($input['community_id'] ?? $_GET['community_id'] ?? '');
    
    if (empty($community_id)) {
        sendResponse(false, null, null, 'community_id parametresi gerekli');
    }
    
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    // Connection Pool kullan
    $connResult = ConnectionPool::getConnection($db_path, false); // DELETE için read-write
    if (!$connResult) {
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
    }
    $db = $connResult['db'];
    $poolId = $connResult['pool_id'];
    
    $user_id = $currentUser['id'];
    $user_email = strtolower(trim($currentUser['email'] ?? ''));
    $student_id = trim($currentUser['student_id'] ?? '');
    
    // Members tablosunun varlığını kontrol et
    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
    $members_table_exists = $table_check && $table_check->fetchArray();
    
    if (!$members_table_exists) {
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        sendResponse(false, null, null, 'Üye tablosu bulunamadı');
    }
    
    // Kullanıcının üye olup olmadığını kontrol et
    $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
    $member_check->bindValue(1, $user_email, SQLITE3_TEXT);
    $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
    $member_result = $member_check->execute();
    $member = $member_result ? $member_result->fetchArray(SQLITE3_ASSOC) : null;
    
    if (!$member) {
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        sendResponse(false, null, null, 'Topluluğun üyesi değilsiniz.');
    }
    
    // Üyeyi sil
    $delete_stmt = $db->prepare("DELETE FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?))");
    $delete_stmt->bindValue(1, $user_email, SQLITE3_TEXT);
    $delete_stmt->bindValue(2, $student_id, SQLITE3_TEXT);
    
    if ($delete_stmt->execute()) {
        // Membership requests tablosundan da sil (eğer varsa)
        try {
            $request_delete = $db->prepare("DELETE FROM membership_requests WHERE club_id = 1 AND (user_id = ? OR LOWER(email) = LOWER(?))");
            $request_delete->bindValue(1, $user_id, SQLITE3_INTEGER);
            $request_delete->bindValue(2, $user_email, SQLITE3_TEXT);
            $request_delete->execute();
        } catch (Exception $e) {
            // Membership requests tablosu yoksa veya hata varsa devam et
            secureLog('leave_community', 'Membership request silme hatası: ' . $e->getMessage());
        }
        
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        sendResponse(true, null, 'Topluluktan başarıyla ayrıldınız.');
    } else {
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        sendResponse(false, null, null, 'Ayrılma işlemi başarısız oldu.');
    }
    
} catch (Exception $e) {
    // Veritabanı bağlantısını pool'a geri ver (eğer açıksa)
    if (isset($db_path) && isset($poolId)) {
        try {
            ConnectionPool::releaseConnection($db_path, $poolId, false);
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
    $response = sendSecureErrorResponse('Ayrılma işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

