<?php
/**
 * Market API v2 - Products Endpoint
 * 
 * GET /api/v2/market/products.php - List products with filters
 * GET /api/v2/market/products.php?id={id}&community_id={id} - Single product
 * 
 * Query Parameters:
 * - category: Filter by category
 * - community_id: Filter by community
 * - university_id: Filter by university
 * - min_price: Minimum price filter
 * - max_price: Maximum price filter
 * - in_stock: Only show in-stock items (1/0)
 * - search: Search term
 * - sort: Sorting option (price_asc, price_desc, newest, name_asc, name_desc, bestselling)
 * - limit: Number of items per page (default: 20, max: 100)
 * - offset: Offset for pagination
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load dependencies
require_once __DIR__ . '/../../security_helper.php';
require_once __DIR__ . '/../../auth_middleware.php';
require_once __DIR__ . '/../../../lib/autoload.php';
require_once __DIR__ . '/../../../api/university_helper.php';

// CORS handling
if (function_exists('setSecureCORS')) {
    setSecureCORS();
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Rate limiting
if (function_exists('checkRateLimit') && !checkRateLimit(100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Çok fazla istek. Lütfen bir dakika bekleyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Sadece GET metodu desteklenmektedir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get optional authenticated user
$currentUser = function_exists('optionalAuth') ? optionalAuth() : null;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get base URL for image URLs
 */
function getBaseUrl(): string {
    static $baseUrl = null;
    if ($baseUrl === null) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    return $baseUrl;
}

/**
 * normalizeUniversityName kaldırıldı, university_helper.php'deki kullanılacak
 */

/**
 * Calculate commission and total price
 */
function calculatePricing(float $basePrice, float $commissionRate = 8.0): array {
    // Iyzico commission: 2.99% + 0.25 TL
    $iyzicoRate = 2.99;
    $iyzicoFixed = 0.25;
    $iyzicoCommission = ($basePrice * $iyzicoRate / 100) + $iyzicoFixed;
    
    // Platform commission
    $platformCommission = $basePrice * $commissionRate / 100;
    
    // Total price
    $totalPrice = $basePrice + $iyzicoCommission + $platformCommission;
    
    return [
        'base_price' => round($basePrice, 2),
        'iyzico_commission' => round($iyzicoCommission, 2),
        'platform_commission' => round($platformCommission, 2),
        'total_price' => round($totalPrice, 2),
        'commission_rate' => $commissionRate
    ];
}

/**
 * Build absolute URL for product images
 */
function buildImageUrl(string $imagePath, string $communitySlug): string {
    if (empty($imagePath)) {
        return '';
    }
    
    // Already absolute URL
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    $baseUrl = getBaseUrl();
    
    // Use product_media.php endpoint for secure serving
    return $baseUrl . '/api/product_media.php?file=' . urlencode(basename($imagePath)) 
           . '&community_id=' . urlencode($communitySlug);
}

/**
 * Get community database connection
 */
function getCommunityDb(string $communitySlug): ?SQLite3 {
    // Validate community slug
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communitySlug)) {
        return null;
    }
    
    $dbPath = realpath(__DIR__ . '/../../../communities/' . $communitySlug . '/unipanel.sqlite');
    if (!$dbPath || !file_exists($dbPath)) {
        return null;
    }
    
    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $db->busyTimeout(5000);
        return $db;
    } catch (Exception $e) {
        error_log("Market Products API: DB connection failed for {$communitySlug}: " . $e->getMessage());
        return null;
    }
}

/**
 * Get community settings (name, university, etc.)
 */
function getCommunitySettings(SQLite3 $db, string $communitySlug): array {
    $settings = [
        'name' => ucwords(str_replace('_', ' ', $communitySlug)),
        'university' => null
    ];
    
    try {
        $query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
        if ($query) {
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                if ($row['setting_key'] === 'club_name' && !empty($row['setting_value'])) {
                    $settings['name'] = $row['setting_value'];
                }
                if ($row['setting_key'] === 'university' && !empty($row['setting_value'])) {
                    $settings['university'] = $row['setting_value'];
                }
            }
        }
    } catch (Exception $e) {
        // Use defaults
    }
    
    return $settings;
}

/**
 * Get all community slugs
 */
function getAllCommunitySlugs(): array {
    $communitiesDir = realpath(__DIR__ . '/../../../communities');
    if (!$communitiesDir || !is_dir($communitiesDir)) {
        return [];
    }
    
    $slugs = [];
    $iterator = new DirectoryIterator($communitiesDir);
    foreach ($iterator as $item) {
        if ($item->isDot() || !$item->isDir()) {
            continue;
        }
        $slug = $item->getFilename();
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            $dbPath = $item->getPathname() . '/unipanel.sqlite';
            if (file_exists($dbPath)) {
                $slugs[] = $slug;
            }
        }
    }
    
    return $slugs;
}

// ============================================================================
// Main Logic
// ============================================================================

try {
    // Parse query parameters
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $communityId = isset($_GET['community_id']) ? trim($_GET['community_id']) : null;
    $universityId = isset($_GET['university_id']) ? trim($_GET['university_id']) : null;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $inStock = isset($_GET['in_stock']) ? ($_GET['in_stock'] === '1' || $_GET['in_stock'] === 'true') : false;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    
    // Validate sort option
    $validSorts = ['price_asc', 'price_desc', 'newest', 'name_asc', 'name_desc', 'bestselling'];
    if (!in_array($sort, $validSorts)) {
        $sort = 'newest';
    }
    
    // ========================================================================
    // Single Product Request
    // ========================================================================
    if ($productId !== null && $communityId !== null) {
        // Sanitize community ID
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communityId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Geçersiz topluluk ID.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db = getCommunityDb($communityId);
        if (!$db) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Topluluk bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $communitySettings = getCommunitySettings($db, $communityId);
        
        $stmt = $db->prepare("
            SELECT id, name, description, price, stock, category, image_path, 
                   commission_rate, status, created_at
            FROM products 
            WHERE id = ? AND club_id = 1 AND status = 'active'
            LIMIT 1
        ");
        $stmt->bindValue(1, $productId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $product = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        $db->close();
        
        if (!$product) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Ürün bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Build product response
        $price = (float)($product['price'] ?? 0);
        $commissionRate = (float)($product['commission_rate'] ?? 8.0);
        $pricing = calculatePricing($price, $commissionRate);
        
        $imageUrl = buildImageUrl($product['image_path'] ?? '', $communityId);
        
        $productResponse = [
            'id' => (int)$product['id'],
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'category' => $product['category'] ?? 'Genel',
            'price' => $pricing['base_price'],
            'total_price' => $pricing['total_price'],
            'iyzico_commission' => $pricing['iyzico_commission'],
            'platform_commission' => $pricing['platform_commission'],
            'commission_rate' => $pricing['commission_rate'],
            'stock' => (int)($product['stock'] ?? 0),
            'status' => $product['status'] ?? 'active',
            'image_url' => $imageUrl,
            'image_urls' => $imageUrl ? [$imageUrl] : [],
            'community_id' => $communityId,
            'community_name' => $communitySettings['name'],
            'university' => $communitySettings['university'],
            'created_at' => $product['created_at'] ?? null
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $productResponse
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ========================================================================
    // Product List Request
    // ========================================================================
    
    $allProducts = [];
    $allCategories = [];
    $minPriceFound = PHP_FLOAT_MAX;
    $maxPriceFound = 0;
    
    // Determine which communities to query
    if ($communityId !== null && !empty($communityId)) {
        $communityIds = [$communityId];
    } else {
        $communityIds = getAllCommunitySlugs();
    }
    
    // Query each community database
    foreach ($communityIds as $slug) {
        $db = getCommunityDb($slug);
        if (!$db) {
            continue;
        }
        
        $communitySettings = getCommunitySettings($db, $slug);
        
        // University filter (client-side comparison)
        if ($universityId !== null && !empty($universityId) && $universityId !== 'all') {
            if ($communitySettings['university'] === null) {
                $db->close();
                continue;
            }
            $normalizedUniId = normalizeUniversityName($universityId);
            $normalizedCommunityUni = normalizeUniversityName($communitySettings['university']);
            if ($normalizedCommunityUni !== $normalizedUniId) {
                $db->close();
                continue;
            }
        }
        
        // Check if products table exists
        $tableCheck = @$db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
        if (!$tableCheck) {
            $db->close();
            continue;
        }
        
        // Get available columns
        $columns = [];
        $res = @$db->query("PRAGMA table_info(products)");
        if ($res) {
            while ($col = $res->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $col['name'];
            }
        }
        
        // Build robust query
        $selectCols = "id, name, description, price, stock, category, image_path, commission_rate, status, created_at";
        if (in_array('sold_count', $columns)) $selectCols .= ", sold_count";
        else $selectCols .= ", 0 as sold_count";
        
        if (in_array('rating', $columns)) $selectCols .= ", rating";
        else $selectCols .= ", NULL as rating";

        $sql = "SELECT $selectCols FROM products WHERE club_id = 1 AND status = 'active'";
        $params = [];
        
        // Category filter
        if ($category !== null && !empty($category)) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        // Stock filter
        if ($inStock) {
            $sql .= " AND stock > 0";
        }
        
        // Search filter
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }
        
        try {
            $stmt = @$db->prepare($sql);
            if ($stmt) {
                foreach ($params as $i => $param) {
                    $stmt->bindValue($i + 1, $param, SQLITE3_TEXT);
                }
                $result = @$stmt->execute();
                
                if ($result) {
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $price = (float)($row['price'] ?? 0);
                    $commissionRate = (float)($row['commission_rate'] ?? 8.0);
                    $pricing = calculatePricing($price, $commissionRate);
                    
                    // Price filters (after commission calculation)
                    $totalPrice = $pricing['total_price'];
                    
                    if ($minPrice !== null && $totalPrice < $minPrice) {
                        continue;
                    }
                    if ($maxPrice !== null && $totalPrice > $maxPrice) {
                        continue;
                    }
                    
                    // Track min/max prices and categories
                    $minPriceFound = min($minPriceFound, $totalPrice);
                    $maxPriceFound = max($maxPriceFound, $totalPrice);
                    
                    $productCategory = $row['category'] ?? 'Genel';
                    if (!in_array($productCategory, $allCategories)) {
                        $allCategories[] = $productCategory;
                    }
                    
                    $imageUrl = buildImageUrl($row['image_path'] ?? '', $slug);
                    
                    $allProducts[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'] ?? '',
                        'description' => $row['description'] ?? '',
                        'category' => $productCategory,
                        'price' => $pricing['base_price'],
                        'total_price' => $pricing['total_price'],
                        'iyzico_commission' => $pricing['iyzico_commission'],
                        'platform_commission' => $pricing['platform_commission'],
                        'commission_rate' => $pricing['commission_rate'],
                        'stock' => (int)($row['stock'] ?? 0),
                        'image_url' => $imageUrl,
                        'image_urls' => $imageUrl ? [$imageUrl] : [],
                        'community_id' => $slug,
                        'community_name' => $communitySettings['name'],
                        'university' => $communitySettings['university'],
                        'sold_count' => (int)($row['sold_count'] ?? 0),
                        'rating' => $row['rating'] !== null ? (float)$row['rating'] : null,
                        'created_at' => $row['created_at'] ?? null
                    ];
                }
            }
        }
    } catch (Exception $e) {
            error_log("Market Products API: Query error for {$slug}: " . $e->getMessage());
        }
        
        $db->close();
    }
    
    // Sort products
    usort($allProducts, function($a, $b) use ($sort) {
        switch ($sort) {
            case 'price_asc':
                return $a['total_price'] <=> $b['total_price'];
            case 'price_desc':
                return $b['total_price'] <=> $a['total_price'];
            case 'name_asc':
                return strcasecmp($a['name'], $b['name']);
            case 'name_desc':
                return strcasecmp($b['name'], $a['name']);
            case 'bestselling':
                return ($b['sold_count'] ?? 0) <=> ($a['sold_count'] ?? 0);
            case 'newest':
            default:
                // Sort by created_at desc
                $aTime = strtotime($a['created_at'] ?? '1970-01-01');
                $bTime = strtotime($b['created_at'] ?? '1970-01-01');
                return $bTime <=> $aTime;
        }
    });
    
    // Pagination
    $totalProducts = count($allProducts);
    $paginatedProducts = array_slice($allProducts, $offset, $limit);
    $hasMore = ($offset + $limit) < $totalProducts;
    
    // Sort categories alphabetically
    sort($allCategories);
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'products' => $paginatedProducts,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $totalProducts,
                'has_more' => $hasMore
            ],
            'filters' => [
                'categories' => $allCategories,
                'price_range' => [
                    'min' => $totalProducts > 0 ? round($minPriceFound, 2) : 0,
                    'max' => $totalProducts > 0 ? round($maxPriceFound, 2) : 0
                ]
            ]
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Market Products API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
}
