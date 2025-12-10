<?php
/**
 * Mobil API - Board Members Endpoint
 * GET /api/board.php?community_id={id} - Topluluğa ait yönetim kurulu üyelerini listele
 */

require_once __DIR__ . '/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
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

// Authentication zorunlu (yönetim kurulu bilgileri hassas)
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
    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='board_members'");
    if (!$table_check || !$table_check->fetchArray()) {
        // Tablo yoksa boş array döndür
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        sendResponse(true, []);
    }
    
    // Yönetim kurulu üyelerini çek
    $board_members = [];
    try {
        // Önce tablo yapısını kontrol et - hangi kolonlar var?
        $columns_check = $db->query("PRAGMA table_info(board_members)");
        $available_columns = [];
        if ($columns_check) {
            while ($col = $columns_check->fetchArray(SQLITE3_ASSOC)) {
                $available_columns[] = $col['name'];
            }
        }
        
        // Mevcut kolonlara göre SELECT sorgusu oluştur
        $select_columns = ['id', 'full_name', 'role'];
        $optional_columns = ['contact_email', 'phone', 'bio', 'photo_path'];
        
        foreach ($optional_columns as $col) {
            if (in_array($col, $available_columns)) {
                $select_columns[] = $col;
            }
        }
        
        $select_query = "SELECT " . implode(', ', $select_columns) . " FROM board_members WHERE club_id = 1";
        
        // is_active kolonu varsa sadece aktif üyeleri getir
        if (in_array('is_active', $available_columns)) {
            $select_query .= " AND is_active = 1";
        }
        
        $select_query .= " ORDER BY role ASC, id ASC";
        
        $query = $db->prepare($select_query);
        if (!$query) {
            $error_msg = $db->lastErrorMsg();
            error_log("Board members query prepare failed: " . $error_msg);
            throw new Exception("Query prepare failed: " . $error_msg);
        }
        
        $result = $query->execute();
        if (!$result) {
            $error_msg = $db->lastErrorMsg();
            error_log("Board members query execute failed: " . $error_msg);
            throw new Exception("Query execute failed: " . $error_msg);
        }
        
        $row_count = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row_count++;
            
            // Null değerleri güvenli şekilde handle et - JSON encoding için
            $board_member = [
                'id' => isset($row['id']) && $row['id'] !== null && $row['id'] !== 0 ? (int)$row['id'] : null,
                'full_name' => isset($row['full_name']) && $row['full_name'] !== null && trim($row['full_name']) !== '' ? trim((string)$row['full_name']) : null,
                'role' => isset($row['role']) && $row['role'] !== null && trim($row['role']) !== '' ? trim((string)$row['role']) : 'Üye'
            ];
            
            // ID veya full_name yoksa bu kaydı atla
            if (($board_member['id'] === null || $board_member['id'] === 0) && $board_member['full_name'] === null) {
                error_log("Board member skipped: missing id and full_name");
                continue;
            }
            
            // Optional alanlar - sadece varsa ekle
            if (isset($row['contact_email']) && $row['contact_email'] !== null && trim($row['contact_email']) !== '') {
                $board_member['contact_email'] = trim((string)$row['contact_email']);
            }
            
            if (isset($row['phone']) && $row['phone'] !== null && trim($row['phone']) !== '') {
                $board_member['phone'] = trim((string)$row['phone']);
            }
            
            if (isset($row['bio']) && $row['bio'] !== null && trim($row['bio']) !== '') {
                $board_member['bio'] = trim((string)$row['bio']);
            }
            
            if (isset($row['photo_path']) && $row['photo_path'] !== null && trim($row['photo_path']) !== '') {
                $photo_path = trim($row['photo_path']);
                // Eğer zaten tam path değilse, community path'i ekle
                if (substr($photo_path, 0, 1) !== '/' && substr($photo_path, 0, 4) !== 'http') {
                    $board_member['photo_path'] = '/communities/' . $community_id . '/' . $photo_path;
                } else {
                    $board_member['photo_path'] = $photo_path;
                }
            }
            
            $board_members[] = $board_member;
        }
        
        error_log("Board members query successful: $row_count rows found, " . count($board_members) . " valid members");
        
    } catch (Exception $e) {
        // Hata olsa bile boş array döndür (çökme koruması)
        error_log("Board members query error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $board_members = [];
    }
    
    // Bağlantıyı pool'a geri ver
    ConnectionPool::releaseConnection($db_path, $poolId, true);
    sendResponse(true, $board_members);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

