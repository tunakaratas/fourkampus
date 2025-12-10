<?php
/**
 * Community Hosting Fallback Endpoint
 * API'den veri çekilemediğinde community hosting'inden veri çeker
 * GET /api/communities_fallback.php?id={community_id}
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/connection_pool.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(200, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Community ID kontrolü
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        sendResponse(false, null, null, 'Topluluk ID\'si gerekli');
        exit;
    }
    
    $community_id = sanitizeCommunityId($_GET['id']);
    
    // get_community_data fonksiyonunu kullan (communities.php'den)
    require_once __DIR__ . '/communities.php';
    
    // Community data'yı çek
    $community_data = get_community_data($community_id);
    
    if ($community_data === null) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
        exit;
    }
    
    // Community hosting'inden veri çekildi, formatla ve döndür
    $db_path = __DIR__ . '/../communities/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk veritabanı bulunamadı');
        exit;
    }
    
    try {
        // Connection pool kullan
        $connResult = ConnectionPool::getConnection($db_path, true);
        if (!$connResult) {
            sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı.');
            exit;
        }
        $db = $connResult['db'];
        $poolId = $connResult['pool_id'];
        
        // Settings'den topluluk bilgilerini al
        $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
        $settings = [];
        while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // İstatistikler
        $member_count = count($community_data['members'] ?? []);
        $event_count = count($community_data['events'] ?? []);
        $campaign_count = count($community_data['campaigns'] ?? []);
        $board_count = count($community_data['board'] ?? []);
        
        // Logo path
        $logo_path = null;
        if (!empty($settings['club_logo'])) {
            $logo_path = '/communities/' . $community_id . '/' . $settings['club_logo'];
        }
        
        // Image URL
        $image_url = null;
        if (!empty($settings['club_image'])) {
            $image_url = '/communities/' . $community_id . '/' . $settings['club_image'];
        }
        
        // Kategorileri array olarak al
        $categories = [];
        if (!empty($settings['club_category'])) {
            $decoded = json_decode($settings['club_category'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $categories = array_filter($decoded, function($cat) {
                    return $cat !== 'other' && !empty($cat);
                });
            } else {
                $cats = explode(',', $settings['club_category']);
                foreach ($cats as $cat) {
                    $cat = trim($cat);
                    if ($cat !== 'other' && !empty($cat)) {
                        $categories[] = $cat;
                    }
                }
            }
        }
        // Max 3 kategori
        $categories = array_slice($categories, 0, 3);
        
        // Bağlantıyı pool'a geri ver
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        
        $community = [
            'id' => $community_id,
            'name' => $community_data['name'] ?? $community_id,
            'description' => $community_data['description'] ?? null,
            'short_description' => null,
            'member_count' => $member_count,
            'event_count' => $event_count,
            'campaign_count' => $campaign_count,
            'board_member_count' => $board_count,
            'image_url' => $image_url,
            'logo_path' => $logo_path,
            'categories' => $categories,
            'tags' => !empty($settings['club_tags']) ? explode(',', $settings['club_tags']) : [],
            'is_verified' => isset($settings['is_verified']) ? (bool)$settings['is_verified'] : false,
            'created_at' => $settings['created_at'] ?? date('Y-m-d H:i:s'),
            'contact_email' => $settings['contact_email'] ?? null,
            'website' => $settings['website'] ?? null,
            'social_links' => !empty($settings['social_links']) ? json_decode($settings['social_links'], true) : null,
            'status' => 'active'
        ];
        
        sendResponse(true, $community);
        
    } catch (Exception $e) {
        // Hata durumunda bağlantıyı release et
        if (isset($poolId) && isset($db_path)) {
            ConnectionPool::releaseConnection($db_path, $poolId, false);
        }
        $response = sendSecureErrorResponse('Topluluk verisi alınırken hata oluştu', $e);
        sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
    }
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İstek işlenirken bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

