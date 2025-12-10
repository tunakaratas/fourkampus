<?php
/**
 * Lazy Loading API - Member Contacts
 * AJAX endpoint for loading member contacts with pagination and search
 * Optimized for messages and mail centers
 */

require_once __DIR__ . '/../bootstrap/community_entry.php';
require_once __DIR__ . '/../lib/core/Cache.php';
require_once __DIR__ . '/../lib/core/ResponseOptimizer.php';

// Response optimizasyonu
\UniPanel\Core\ResponseOptimizer::startCompression();
\UniPanel\Core\ResponseOptimizer::setJsonHeaders(true);

// Güvenlik kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : 'email'; // 'email' or 'sms'
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // Daha fazla item (select box için)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $db = get_db();
    $club_id = CLUB_ID;
    $cache = \UniPanel\Core\Cache::getInstance();
    
    // Cache key oluştur (search dahil)
    $cache_key = "member_contacts_{$type}_{$club_id}_" . md5($search) . "_{$offset}_{$limit}";
    $cache_ttl = 300; // 5 dakika
    
    // Cache'den dene (search yoksa)
    if (empty($search)) {
        $cached_data = $cache->get($cache_key);
        if ($cached_data !== null) {
            $cached_data['cached'] = true;
            echo json_encode($cached_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    // Tablo kontrolü
    $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
    if (!$table_check || !$table_check->fetchArray()) {
        echo json_encode([
            'success' => true,
            'contacts' => [],
            'has_more' => false,
            'total' => 0,
            'offset' => $offset,
            'limit' => $limit,
            'cached' => false
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Query oluştur
    if ($type === 'sms') {
        $whereClause = "club_id = ? AND phone_number IS NOT NULL AND phone_number != ''";
        $selectColumns = "id, full_name, phone_number";
    } else {
        $whereClause = "club_id = ? AND email IS NOT NULL AND email != ''";
        $selectColumns = "id, full_name, email";
    }
    
    // Search ekle
    if (!empty($search)) {
        $whereClause .= " AND (full_name LIKE ? OR " . ($type === 'sms' ? 'phone_number' : 'email') . " LIKE ?)";
    }
    
    // Toplam sayı (cache'lenmiş, search yoksa)
    $count_cache_key = "member_contacts_count_{$type}_{$club_id}";
    $total_count = 0;
    
    if (empty($search)) {
        $total_count = $cache->remember($count_cache_key, $cache_ttl, function() use ($db, $club_id, $type) {
            $where = $type === 'sms' 
                ? "club_id = $club_id AND phone_number IS NOT NULL AND phone_number != ''"
                : "club_id = $club_id AND email IS NOT NULL AND email != ''";
            return $db->querySingle("SELECT COUNT(*) FROM members WHERE $where") ?: 0;
        });
    } else {
        // Search varsa cache kullanma, direkt say
        $countSql = "SELECT COUNT(*) FROM members WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        $countStmt->bindValue(1, $club_id, SQLITE3_INTEGER);
        if (!empty($search)) {
            $searchPattern = '%' . $search . '%';
            $countStmt->bindValue(2, $searchPattern, SQLITE3_TEXT);
            $countStmt->bindValue(3, $searchPattern, SQLITE3_TEXT);
        }
        $total_count = $countStmt->execute()->fetchArray()[0] ?: 0;
        $countStmt->close();
    }
    
    // Contacts çek
    $sql = "SELECT $selectColumns FROM members WHERE $whereClause ORDER BY full_name ASC LIMIT ? OFFSET ?";
    $query = $db->prepare($sql);
    
    $paramIndex = 1;
    $query->bindValue($paramIndex++, $club_id, SQLITE3_INTEGER);
    
    if (!empty($search)) {
        $searchPattern = '%' . $search . '%';
        $query->bindValue($paramIndex++, $searchPattern, SQLITE3_TEXT);
        $query->bindValue($paramIndex++, $searchPattern, SQLITE3_TEXT);
    }
    
    $query->bindValue($paramIndex++, $limit, SQLITE3_INTEGER);
    $query->bindValue($paramIndex++, $offset, SQLITE3_INTEGER);
    
    $result = $query->execute();
    $contacts = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $contacts[] = $row;
    }
    
    $result->finalize();
    
    $has_more = ($offset + $limit) < $total_count;
    
    $response = [
        'success' => true,
        'contacts' => $contacts,
        'has_more' => $has_more,
        'total' => $total_count,
        'offset' => $offset,
        'limit' => $limit,
        'type' => $type,
        'cached' => false
    ];
    
    // Cache'e kaydet (search yoksa)
    if (empty($search)) {
        $cache->set($cache_key, $response, $cache_ttl);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

