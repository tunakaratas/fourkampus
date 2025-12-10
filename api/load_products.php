<?php
/**
 * Lazy Loading API - Products
 * AJAX endpoint for loading products with pagination
 * Optimized with caching
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

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;

try {
    $db = get_db();
    $club_id = CLUB_ID;
    $cache = \UniPanel\Core\Cache::getInstance();
    
    // Cache key oluştur
    $cache_key = "products_list_{$club_id}_{$offset}_{$limit}";
    $cache_ttl = 300; // 5 dakika
    
    // Cache'den dene
    $cached_data = $cache->get($cache_key);
    if ($cached_data !== null) {
        $cached_data['cached'] = true;
        echo json_encode($cached_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Toplam sayı (cache'lenmiş)
    $count_cache_key = "products_count_{$club_id}";
    $total_count = $cache->remember($count_cache_key, $cache_ttl, function() use ($db, $club_id) {
        return $db->querySingle("SELECT COUNT(*) FROM products WHERE club_id = $club_id") ?: 0;
    });
    
    // Ürünleri çek
    $query = $db->prepare("SELECT * FROM products WHERE club_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $query->bindValue(1, $club_id, SQLITE3_INTEGER);
    $query->bindValue(2, $limit, SQLITE3_INTEGER);
    $query->bindValue(3, $offset, SQLITE3_INTEGER);
    
    $result = $query->execute();
    $products = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (!empty($row['image_path']) && function_exists('format_product_image_url')) {
            $row['image_url'] = format_product_image_url($row['image_path']);
        }
        $products[] = $row;
    }
    
    $result->finalize();
    
    $has_more = ($offset + $limit) < $total_count;
    
    $response = [
        'success' => true,
        'products' => $products,
        'has_more' => $has_more,
        'total' => $total_count,
        'offset' => $offset,
        'limit' => $limit,
        'cached' => false
    ];
    
    // Cache'e kaydet
    $cache->set($cache_key, $response, $cache_ttl);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

