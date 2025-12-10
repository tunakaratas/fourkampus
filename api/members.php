<?php
/**
 * Mobil API - Members Endpoint
 * GET /api/members.php?community_id={id} - Topluluğa ait üyeleri listele
 */

require_once __DIR__ . '/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/connection_pool.php';

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

// Authentication zorunlu (üye listesi hassas bilgi)
$currentUser = requireAuth(true);

function sendResponse($success, $data = null, $message = null, $error = null) {
    // JSON encoding hatalarını yakala
    $json = json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    if ($json === false) {
        // JSON encoding hatası
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => null,
            'error' => 'JSON encoding hatası: ' . json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    echo $json;
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
    
    // Connection Pool kullan (10k kullanıcı için kritik)
    $connResult = ConnectionPool::getConnection($db_path, true);
    if (!$connResult) {
        sendResponse(false, null, null, 'Veritabanı bağlantı hatası');
    }
    
    $db = $connResult['db'];
    $poolId = $connResult['pool_id'];
    
    // Tablo var mı kontrol et
    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
    if (!$table_check || !$table_check->fetchArray()) {
        // Tablo yoksa boş array döndür
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(true, []);
    }
    
    // Üyeleri çek (banned olmayanlar)
    $members = [];
    try {
        $query = $db->prepare("SELECT id, full_name, email, student_id, phone_number, registration_date FROM members WHERE club_id = 1 AND (is_banned IS NULL OR is_banned = 0) ORDER BY registration_date DESC");
        if (!$query) {
            throw new Exception("Query prepare failed");
        }
        
        $result = $query->execute();
        if (!$result) {
            throw new Exception("Query execute failed");
        }
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Null değerleri güvenli şekilde handle et - JSON encoding için
            $member = [
                'id' => isset($row['id']) && $row['id'] !== null ? (int)$row['id'] : 0,
                'full_name' => isset($row['full_name']) && $row['full_name'] !== null && trim($row['full_name']) !== '' ? trim((string)$row['full_name']) : 'İsimsiz Üye',
                'registration_date' => isset($row['registration_date']) && $row['registration_date'] !== null && trim($row['registration_date']) !== '' ? trim((string)$row['registration_date']) : date('Y-m-d')
            ];
            
            // Optional alanlar - sadece varsa ekle
            if (isset($row['email']) && $row['email'] !== null && trim($row['email']) !== '') {
                $member['email'] = trim((string)$row['email']);
            }
            
            if (isset($row['student_id']) && $row['student_id'] !== null && trim($row['student_id']) !== '') {
                $member['student_id'] = trim((string)$row['student_id']);
            }
            
            if (isset($row['phone_number']) && $row['phone_number'] !== null && trim($row['phone_number']) !== '') {
                $member['phone_number'] = trim((string)$row['phone_number']);
            }
            
            $members[] = $member;
        }
    } catch (Exception $e) {
        // Hata olsa bile boş array döndür (çökme koruması)
        error_log("Members query error: " . $e->getMessage());
        $members = [];
    }
    
    // Bağlantıyı pool'a geri ver
    ConnectionPool::releaseConnection($db_path, $poolId, true);
    sendResponse(true, $members);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

