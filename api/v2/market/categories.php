<?php
/**
 * Market API v2 - Categories Endpoint
 * 
 * GET /api/v2/market/categories.php - List all product categories
 * 
 * Query Parameters:
 * - community_id: Filter by community (optional)
 * - university_id: Filter by university (optional)
 * - include_counts: Include product counts per category (1/0, default: 1)
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load dependencies
require_once __DIR__ . '/../../security_helper.php';
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

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Sadece GET metodu desteklenmektedir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get community database connection
 */
function getCommunityDb(string $communitySlug): ?SQLite3 {
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
        return null;
    }
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

/**
 * Get community university
 */
function getCommunityUniversity(SQLite3 $db): ?string {
    try {
        $query = $db->query("SELECT setting_value FROM settings WHERE club_id = 1 AND setting_key = 'university' LIMIT 1");
        if ($query) {
            $row = $query->fetchArray(SQLITE3_ASSOC);
            return $row['setting_value'] ?? null;
        }
    } catch (Exception $e) {
        // Ignore
    }
    return null;
}

/**
 * normalizeUniversityName kaldırıldı, university_helper.php'deki kullanılacak
 */

/**
 * Get category icon (SF Symbol name)
 */
function getCategoryIcon(string $category): string {
    $icons = [
        'Kıyafet' => 'tshirt.fill',
        'Giyim' => 'tshirt.fill',
        'Aksesuar' => 'watch.face',
        'Kırtasiye' => 'pencil.and.outline',
        'Teknoloji' => 'laptopcomputer',
        'Yiyecek' => 'fork.knife',
        'İçecek' => 'cup.and.saucer.fill',
        'Spor' => 'football.fill',
        'Kozmetik' => 'sparkles',
        'Kitap' => 'book.fill',
        'Oyun' => 'gamecontroller.fill',
        'Müzik' => 'music.note',
        'Sanat' => 'paintbrush.fill',
        'Hobi' => 'target',
        'Aksesuar' => 'face.smiling',
        'Hediyelik' => 'gift.fill',
        'Genel' => 'square.grid.2x2'
    ];
    
    return $icons[$category] ?? 'square.grid.2x2';
}

// ============================================================================
// Main Logic
// ============================================================================

try {
    // Parse parameters
    $communityId = isset($_GET['community_id']) ? trim($_GET['community_id']) : null;
    $universityId = isset($_GET['university_id']) ? trim($_GET['university_id']) : null;
    $includeCounts = !isset($_GET['include_counts']) || $_GET['include_counts'] !== '0';
    
    // Determine which communities to query
    if ($communityId !== null && !empty($communityId)) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communityId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Geçersiz topluluk ID.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $communityIds = [$communityId];
    } else {
        $communityIds = getAllCommunitySlugs();
    }
    
    // Collect categories with counts
    $categoryCounts = [];
    
    foreach ($communityIds as $slug) {
        $db = getCommunityDb($slug);
        if (!$db) {
            continue;
        }
        
        // University filter
        if ($universityId !== null && !empty($universityId) && $universityId !== 'all') {
            $communityUniversity = getCommunityUniversity($db);
            if ($communityUniversity === null) {
                $db->close();
                continue;
            }
            $normalizedUniId = normalizeUniversityName($universityId);
            $normalizedCommunityUni = normalizeUniversityName($communityUniversity);
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

        try {
            $query = @$db->query("
                SELECT category, COUNT(*) as count 
                FROM products 
                WHERE club_id = 1 AND status = 'active' AND stock > 0
                GROUP BY category
            ");
            
            if ($query) {
                while ($row = @$query->fetchArray(SQLITE3_ASSOC)) {
                    $category = $row['category'] ?? 'Genel';
                    if (!isset($categoryCounts[$category])) {
                        $categoryCounts[$category] = 0;
                    }
                    $categoryCounts[$category] += (int)$row['count'];
                }
            }
        } catch (Exception $e) {
            // Continue with other communities
        }
        
        $db->close();
    }
    
    // Build categories array
    $categories = [];
    foreach ($categoryCounts as $name => $count) {
        $categoryData = [
            'name' => $name,
            'icon' => getCategoryIcon($name),
            'slug' => mb_strtolower($name, 'UTF-8')
        ];
        
        if ($includeCounts) {
            $categoryData['product_count'] = $count;
        }
        
        $categories[] = $categoryData;
    }
    
    // Sort by product count (descending), then by name
    usort($categories, function($a, $b) use ($includeCounts) {
        if ($includeCounts) {
            $countDiff = ($b['product_count'] ?? 0) - ($a['product_count'] ?? 0);
            if ($countDiff !== 0) {
                return $countDiff;
            }
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => [
            'categories' => $categories,
            'total' => count($categories)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Market Categories API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
}
