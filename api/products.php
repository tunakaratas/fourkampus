<?php
/**
 * Mobil API - Products Endpoint
 * GET /api/products.php - Tüm ürünleri listele
 * GET /api/products.php?community_id={id} - Topluluğa ait ürünleri listele
 * GET /api/products.php?id={id} - Tek bir ürün detayı
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/connection_pool.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting (200 istek/dakika - 10k kullanıcı için optimize edildi)
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

// Kullanıcı bilgilerini al (opsiyonel - giriş yapmışsa)
$currentUser = optionalAuth();

// Public index.php'deki fonksiyonları kullanmak için - Güvenli session ayarlarıyla
configureSecureSession();

require_once __DIR__ . '/../lib/core/Cache.php';
use UniPanel\Core\Cache;

$publicCache = Cache::getInstance(__DIR__ . '/../system/cache');

/**
 * University filter helpers (shared behavior with api/communities.php and api/universities.php)
 */
function normalize_university_id($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    // Türkçe karakter desteği için mb_strtolower kullan
    $normalized = mb_strtolower($value, 'UTF-8');
    // Boşluk, tire ve alt çizgi karakterlerini kaldır
    $normalized = str_replace([' ', '-', '_'], '', $normalized);
    return $normalized;
}

function get_requested_university_id() {
    // Accept both university_id (preferred) and university (name) for compatibility.
    $raw = '';
    if (isset($_GET['university_id'])) {
        $raw = (string)$_GET['university_id'];
        // URL decode - Swift'ten gelen encoded değeri decode et
        $raw = urldecode($raw);
    } elseif (isset($_GET['university'])) {
        $raw = (string)$_GET['university'];
        $raw = urldecode($raw);
    }

    $raw = trim($raw);
    if ($raw === '' || $raw === 'all') {
        return '';
    }

    $raw = basename($raw);
    if (strpos($raw, '..') !== false || strpos($raw, '/') !== false || strpos($raw, '\\') !== false) {
        return '';
    }

    return normalize_university_id($raw);
}

function build_base_url() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Script'in çalıştığı dizini bul (api klasörü)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // /unipanel/api/products.php ise /unipanel'e çevir
    if (strpos($scriptPath, '/unipanel/api') !== false) {
        $basePath = '/unipanel';
    } elseif (strpos($scriptPath, '/api') !== false) {
        // /api/products.php -> base path'i bul
        $basePath = str_replace('/api', '', dirname($scriptPath));
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }
    } else {
        // Script path'inden base path'i çıkar
        $basePath = dirname($scriptPath);
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }
    }
    
    $base = $scheme . '://' . $host . $basePath;
    return $base;
}

function build_absolute_url($path) {
    if (empty($path)) {
        return null;
    }
    $normalized = '/' . ltrim($path, '/');
    return build_base_url() . $normalized;
}

// Veritabanı bağlantısı
function get_community_db($community_id) {
    $communities_dir = __DIR__ . '/../communities/';
    $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
    if ($community_folders === false) {
        $community_folders = [];
    }
    
    $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    
    foreach ($community_folders as $folder) {
        $community_folder_id = basename($folder);
        if (in_array($community_folder_id, $excluded_dirs)) {
            continue;
        }
        
        $db_path = $folder . '/unipanel.sqlite';
        if (file_exists($db_path)) {
            try {
                // Connection pool kullan (10k kullanıcı için kritik)
                // NOT: Bazı DB'ler WAL/shm nedeniyle READONLY modda açılamıyor.
                // Üniversite filtresi / listeleme için RW açıp sadece SELECT yapıyoruz.
                $connResult = ConnectionPool::getConnection($db_path, false);
                if (!$connResult) {
                    continue;
                }
                $db = $connResult['db'];
                $poolId = $connResult['pool_id'];
                
                // Bu topluluğun ID'sini kontrol et
                $stmt = $db->prepare("SELECT id FROM clubs WHERE id = ? LIMIT 1");
                $stmt->bindValue(1, $community_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result && $result->fetchArray()) {
                    // Pool ID'yi sakla, çağıran fonksiyon release edecek
                    return [
                        'db' => $db,
                        'pool_id' => $poolId,
                        'db_path' => $db_path
                    ];
                }
                // Eşleşme bulunamadı, bağlantıyı pool'a geri ver
                ConnectionPool::releaseConnection($db_path, $poolId, false);
            } catch (Exception $e) {
                // Hata durumunda bağlantıyı release et
                if (isset($poolId)) {
                    ConnectionPool::releaseConnection($db_path, $poolId, false);
                }
                error_log("find_community_db_by_id error: " . $e->getMessage());
                continue;
            }
        }
    }
    return null;
}

// Tüm topluluk veritabanlarını tara
function find_community_db($community_id) {
    $communities_dir = __DIR__ . '/../communities/';
    
    // Önce folder name'e göre dene (community_id folder name olabilir)
    $folder_path = $communities_dir . $community_id;
    if (is_dir($folder_path)) {
        $db_path = $folder_path . '/unipanel.sqlite';
        if (file_exists($db_path)) {
            try {
                // Connection pool kullan (10k kullanıcı için kritik)
                $connResult = ConnectionPool::getConnection($db_path, false);
                if ($connResult) {
                    return [
                        'db' => $connResult['db'],
                        'folder' => basename($folder_path),
                        'pool_id' => $connResult['pool_id'],
                        'db_path' => $db_path
                    ];
                }
            } catch (Exception $e) {
                error_log("find_community_db error: " . $e->getMessage());
                // Hata durumunda devam et
            }
        }
    }
    
    // Folder name bulunamadıysa, tüm klasörleri tara ve clubs tablosunda ara
    $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
    if ($community_folders === false) {
        $community_folders = [];
    }
    
    $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    
    foreach ($community_folders as $folder) {
        $community_folder_id = basename($folder);
        if (in_array($community_folder_id, $excluded_dirs)) {
            continue;
        }
        
        $db_path = $folder . '/unipanel.sqlite';
        if (file_exists($db_path)) {
            try {
                // Connection pool kullan (10k kullanıcı için kritik)
                $connResult = ConnectionPool::getConnection($db_path, false);
                if (!$connResult) {
                    continue;
                }
                $db = $connResult['db'];
                $poolId = $connResult['pool_id'];
                
                // Bu topluluğun ID'sini kontrol et (clubs tablosu yoksa club_id = 1 kullan)
                $old_exceptions = $db->enableExceptions(false);
                $check_query = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='clubs'");
                $has_clubs_table = $check_query->fetchArray() !== false;
                $db->enableExceptions($old_exceptions);
                
                if ($has_clubs_table) {
                    // community_id integer ise clubs tablosunda ara
                    if (is_numeric($community_id)) {
                        $stmt = $db->prepare("SELECT id FROM clubs WHERE id = ? LIMIT 1");
                        $stmt->bindValue(1, (int)$community_id, SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        if ($result && $result->fetchArray()) {
                            return [
                                'db' => $db,
                                'folder' => basename($folder),
                                'pool_id' => $poolId,
                                'db_path' => $db_path
                            ];
                        }
                    }
                } else {
                    // Clubs tablosu yoksa, club_id = 1 kullan (eski sistem uyumluluğu)
                    // veya folder name ile eşleşiyorsa
                    $folderName = basename($folder);
                    if ($community_id == 1 || $folderName == $community_id) {
                        return [
                            'db' => $db,
                            'folder' => basename($folder),
                            'pool_id' => $poolId,
                            'db_path' => $db_path
                        ];
                    }
                }
                // Bağlantıyı pool'a geri ver (eşleşme bulunamadı)
                ConnectionPool::releaseConnection($db_path, $poolId, false);
            } catch (Exception $e) {
                continue;
            }
        }
    }
    return null;
}

// Komisyon hesaplama fonksiyonları
function calculateIyzicoCommission($price) {
    // İyzico komisyonu: %2.99 + 0.25 TL sabit ücret
    $iyzico_rate = 2.99;
    $iyzico_fixed = 0.25;
    $iyzico_commission = ($price * $iyzico_rate / 100) + $iyzico_fixed;
    return round($iyzico_commission, 2);
}

function calculatePlatformCommission($price, $commission_rate = 8.0) {
    // Platform komisyonu (varsayılan %8, normal oranın altında)
    $platform_commission = $price * $commission_rate / 100;
    return round($platform_commission, 2);
}

function calculateTotalPrice($price, $commission_rate = 8.0) {
    $iyzico_commission = calculateIyzicoCommission($price);
    $platform_commission = calculatePlatformCommission($price, $commission_rate);
    $total = $price + $iyzico_commission + $platform_commission;
    return round($total, 2);
}

function addCommissionInfo(&$product) {
    $price = (float)($product['price'] ?? 0);
    $commission_rate = (float)($product['commission_rate'] ?? 8.0);
    
    $product['iyzico_commission'] = calculateIyzicoCommission($price);
    $product['platform_commission'] = calculatePlatformCommission($price, $commission_rate);
    $product['total_price'] = calculateTotalPrice($price, $commission_rate);
    $product['commission_rate'] = $commission_rate;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    // community_id string olarak gelebilir (folder name), integer'a çevirmeye çalışma
    $community_id = isset($_GET['community_id']) ? sanitizeCommunityId(trim($_GET['community_id'])) : null;
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $requested_university_id = get_requested_university_id();
    
    // Tek bir ürün detayı
    if ($method === 'GET' && $product_id) {
        // community_id string olarak gelebilir
        if (!$community_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'community_id parametresi gereklidir.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db_result = find_community_db($community_id);
        if (!$db_result) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Topluluk bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db = $db_result['db'];
        $community_folder = $db_result['folder'];
        $poolId = $db_result['pool_id'] ?? null;
        
        // products tablosunun var olup olmadığını kontrol et
        $old_exceptions = $db->enableExceptions(false);
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
        $has_products_table = $table_check && $table_check->fetchArray() !== false;
        $db->enableExceptions($old_exceptions);
        
        if (!$has_products_table) {
            // products tablosu yoksa 404 döndür
            if ($poolId && isset($db_result['db_path'])) {
                ConnectionPool::releaseConnection($db_result['db_path'], $poolId, true);
            } else {
                $db->close();
            }
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Ürün bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // club_id her zaman 1 (yeni sistemde)
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND club_id = 1");
        $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $product = $result->fetchArray(SQLITE3_ASSOC);
        
        // Connection pool kullanılıyorsa release et, değilse close
        if ($poolId && isset($db_result['db_path'])) {
            ConnectionPool::releaseConnection($db_result['db_path'], $poolId, true);
        } else {
            $db->close();
        }
        
        if (!$product) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Ürün bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Image path'i düzelt (API'den erişilebilir URL) - Çoklu görsel desteği
        if (!empty($product['image_path']) && $community_folder) {
            // image_path JSON array veya tek string olabilir
            $image_paths = json_decode($product['image_path'], true);
            if (!is_array($image_paths)) {
                // Eski format (tek string)
                $image_paths = [$product['image_path']];
            }
            
            $image_urls = [];
            foreach ($image_paths as $image_path) {
                if (empty($image_path)) continue;
                
                // secure://products/ prefix'i kontrolü (backslash'ları normalize et)
                $normalized_path = str_replace('\\', '/', $image_path);
                if (strpos($normalized_path, 'secure://products/') === 0 || strpos($normalized_path, 'secure:/products/') === 0) {
                    // secure://products/product_xxx.jpg -> /api/product_media.php?file=product_xxx.jpg&community_id=community_folder
                    $file = preg_replace('#^secure:/*products/#', '', $normalized_path);
                    $image_urls[] = build_absolute_url('/api/product_media.php?file=' . rawurlencode($file) . '&community_id=' . rawurlencode($community_folder));
                } else {
                    // Normal path: assets/images/products/product_xxx.jpg
                    $image_path_clean = ltrim($image_path, '/');
                    $image_urls[] = build_absolute_url('/communities/' . $community_folder . '/' . $image_path_clean);
                }
            }
            
            // Çoklu görsel desteği
            $product['image_urls'] = $image_urls;
            // Geriye dönük uyumluluk için ilk görseli image_url olarak kullan
            if (!empty($image_urls[0])) {
                $product['image_url'] = $image_urls[0];
            }
            
            // Debug: Görsel URL'lerini logla
            error_log("Product Detail API Image URLs: " . json_encode($image_urls) . " (paths: {$product['image_path']}, community: {$community_folder})");
        } else {
            // image_path boşsa, dosya sisteminden görseli bul
            $product_id = $product['id'] ?? null;
            if ($product_id && $community_folder) {
                $community_dir = __DIR__ . '/../communities/' . $community_folder;
                $possible_paths = [
                    'assets/images/products/product_' . $product_id . '.jpg',
                    'assets/images/products/product_' . $product_id . '.jpeg',
                    'assets/images/products/product_' . $product_id . '.png',
                    'assets/images/products/product_' . $product_id . '.gif',
                    'assets/images/products/' . $product_id . '.jpg',
                    'assets/images/products/' . $product_id . '.jpeg',
                    'assets/images/products/' . $product_id . '.png',
                    'assets/images/products/' . $product_id . '.gif',
                ];
                
                foreach ($possible_paths as $possible_path) {
                    $full_path = $community_dir . '/' . $possible_path;
                    if (file_exists($full_path)) {
                        $product['image_path'] = $possible_path;
                        $product['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $possible_path);
                        break;
                    }
                }
                
                                // Hala bulunamadıysa, products klasöründeki tüm görselleri tara
                                if (empty($product['image_url'])) {
                                    $products_dir = $community_dir . '/assets/images/products';
                                    if (is_dir($products_dir)) {
                                        $files = glob($products_dir . '/product_' . $product_id . '.*');
                                        if (empty($files)) {
                                            $files = glob($products_dir . '/' . $product_id . '.*');
                                        }
                                        if (!empty($files)) {
                                            $found_file = basename($files[0]);
                                            $relative_path = 'assets/images/products/' . $found_file;
                                            $product['image_path'] = $relative_path;
                                            $product['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $relative_path);
                                        }
                                    }
                                }
                                
                                // Hala bulunamadıysa, assets/images altındaki tüm klasörlerde ara
                                if (empty($product['image_url'])) {
                                    $images_dir = $community_dir . '/assets/images';
                                    if (is_dir($images_dir)) {
                                        // Tüm alt klasörlerde product_ID ile başlayan dosyaları ara
                                        $pattern = $images_dir . '/*/product_' . $product_id . '.*';
                                        $files = glob($pattern);
                                        if (empty($files)) {
                                            $pattern = $images_dir . '/*/' . $product_id . '.*';
                                            $files = glob($pattern);
                                        }
                                        if (!empty($files)) {
                                            $found_file = $files[0];
                                            $relative_path = str_replace($community_dir . '/', '', $found_file);
                                            $product['image_path'] = $relative_path;
                                            $product['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $relative_path);
                                        }
                                    }
                                }
            }
            
            // Debug: Görsel URL'ini logla
            if (!empty($product['image_url'])) {
                error_log("Product Detail API Image URL (found in filesystem): {$product['image_url']} (path: {$product['image_path']}, community: {$community_folder})");
            }
        }
        
        // Komisyon bilgilerini ekle
        addCommissionInfo($product);
        
        echo json_encode([
            'success' => true,
            'data' => $product,
            'message' => null,
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Topluluğa ait ürünleri listele
    if ($method === 'GET' && $community_id) {
        // Cache'i bypass et (her zaman fresh data)
        // $cache_key = "products_community_{$community_id}";
        // $cached = $publicCache->get($cache_key);
        // if ($cached !== false) {
        //     echo json_encode([
        //         'success' => true,
        //         'data' => $cached,
        //         'message' => null,
        //         'error' => null
        //     ], JSON_UNESCAPED_UNICODE);
        //     exit;
        // }
        
        $db_result = find_community_db($community_id);
        if (!$db_result) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Topluluk bulunamadı. community_id: ' . $community_id
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db = $db_result['db'];
        $community_folder = $db_result['folder'];
        $poolId = $db_result['pool_id'] ?? null;
        $dbPath = $db_result['db_path'] ?? null;

        // Üniversite bilgisini al (listeye eklemek için)
        $community_university_name = null;
        try {
            $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
            if ($settings_query) {
                $settings = [];
                while ($rowSetting = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                    $settings[$rowSetting['setting_key']] = $rowSetting['setting_value'];
                }
                $community_university_name = $settings['university'] ?? $settings['organization'] ?? null;
            }
        } catch (Exception $e) {
            $community_university_name = null;
        }
        
        // products tablosunun var olup olmadığını kontrol et
        $old_exceptions = $db->enableExceptions(false);
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
        $has_products_table = $table_check && $table_check->fetchArray() !== false;
        $db->enableExceptions($old_exceptions);
        
        if (!$has_products_table) {
            // products tablosu yoksa boş array döndür
            if ($poolId && $dbPath) {
                ConnectionPool::releaseConnection($dbPath, $poolId, true);
            } else {
                $db->close();
            }
            echo json_encode([
                'success' => true,
                'data' => [],
                'message' => null,
                'error' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // club_id her zaman 1 (yeni sistemde)
        $stmt = $db->prepare("SELECT * FROM products WHERE club_id = 1 AND status = 'active' ORDER BY created_at DESC");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'SQL hazırlama hatası: ' . $db->lastErrorMsg()
            ], JSON_UNESCAPED_UNICODE);
            // Connection pool kullanılıyorsa release et, değilse close
            if ($poolId && $dbPath) {
                ConnectionPool::releaseConnection($dbPath, $poolId, true);
            } else {
                $db->close();
            }
            exit;
        }
        
        $result = $stmt->execute();
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'SQL çalıştırma hatası: ' . $db->lastErrorMsg()
            ], JSON_UNESCAPED_UNICODE);
            // Connection pool kullanılıyorsa release et, değilse close
            if ($poolId && $dbPath) {
                ConnectionPool::releaseConnection($dbPath, $poolId, true);
            } else {
                $db->close();
            }
            exit;
        }
        
        $products = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // ID'yi string'e çevir (Swift için)
            $row['id'] = (string)$row['id'];
            // community_id ekle (folder name)
            $row['community_id'] = $community_folder;
            $row['university'] = $community_university_name;
            
                        // Image path'i düzelt (API'den erişilebilir URL) - Çoklu görsel desteği
                        if (!empty($row['image_path']) && $community_folder) {
                            // image_path JSON array veya tek string olabilir
                            $image_paths = json_decode($row['image_path'], true);
                            if (!is_array($image_paths)) {
                                // Eski format (tek string)
                                $image_paths = [$row['image_path']];
                            }
                            
                            $image_urls = [];
                            foreach ($image_paths as $image_path) {
                                if (empty($image_path)) continue;
                                
                                $normalized_path = str_replace('\\', '/', $image_path);
                                if (strpos($normalized_path, 'secure://products/') === 0 || strpos($normalized_path, 'secure:/products/') === 0) {
                                    $file = preg_replace('#^secure:/*products/#', '', $normalized_path);
                                    $image_urls[] = build_absolute_url('/api/product_media.php?file=' . rawurlencode($file) . '&community_id=' . rawurlencode($community_folder));
                                } else {
                                    $image_path_clean = ltrim($image_path, '/');
                                    $image_urls[] = build_absolute_url('/communities/' . $community_folder . '/' . $image_path_clean);
                                }
                            }
                            
                            // Çoklu görsel desteği
                            $row['image_urls'] = $image_urls;
                            // Geriye dönük uyumluluk için ilk görseli image_url olarak kullan
                            if (!empty($image_urls[0])) {
                                $row['image_url'] = $image_urls[0];
                            }
                        } else {
                // image_path boşsa, dosya sisteminden görseli bul
                $product_id = $row['id'] ?? null;
                // ID'yi string'e çevir (zaten string olabilir)
                $product_id_str = (string)$product_id;
                if ($product_id && $community_folder) {
                    $community_dir = __DIR__ . '/../communities/' . $community_folder;
                    $possible_paths = [
                        'assets/images/products/product_' . $product_id_str . '.jpg',
                        'assets/images/products/product_' . $product_id_str . '.jpeg',
                        'assets/images/products/product_' . $product_id_str . '.png',
                        'assets/images/products/product_' . $product_id_str . '.gif',
                        'assets/images/products/' . $product_id_str . '.jpg',
                        'assets/images/products/' . $product_id_str . '.jpeg',
                        'assets/images/products/' . $product_id_str . '.png',
                        'assets/images/products/' . $product_id_str . '.gif',
                    ];
                    
                    foreach ($possible_paths as $possible_path) {
                        $full_path = $community_dir . '/' . $possible_path;
                        if (file_exists($full_path)) {
                            $row['image_path'] = $possible_path;
                            $row['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $possible_path);
                            error_log("Product image found: {$row['image_url']} (product_id: {$product_id_str})");
                            break;
                        }
                    }
                    
                    // Hala bulunamadıysa, products klasöründeki tüm görselleri tara
                    if (empty($row['image_url'])) {
                        $products_dir = $community_dir . '/assets/images/products';
                        if (is_dir($products_dir)) {
                            $files = glob($products_dir . '/product_' . $product_id_str . '.*');
                            if (empty($files)) {
                                $files = glob($products_dir . '/' . $product_id_str . '.*');
                            }
                            if (!empty($files)) {
                                $found_file = basename($files[0]);
                                $relative_path = 'assets/images/products/' . $found_file;
                                $row['image_path'] = $relative_path;
                                $row['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $relative_path);
                                error_log("Product image found via glob: {$row['image_url']} (product_id: {$product_id_str})");
                            }
                        }
                    }
                    
                    // Hala bulunamadıysa, assets/images altındaki tüm klasörlerde ara
                    if (empty($row['image_url'])) {
                        $images_dir = $community_dir . '/assets/images';
                        if (is_dir($images_dir)) {
                            // Tüm alt klasörlerde product_ID ile başlayan dosyaları ara
                            $pattern = $images_dir . '/*/product_' . $product_id_str . '.*';
                            $files = glob($pattern);
                            if (empty($files)) {
                                $pattern = $images_dir . '/*/' . $product_id_str . '.*';
                                $files = glob($pattern);
                            }
                            if (!empty($files)) {
                                $found_file = $files[0];
                                $relative_path = str_replace($community_dir . '/', '', $found_file);
                                $row['image_path'] = $relative_path;
                                $row['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $relative_path);
                                error_log("Product image found in images subdir: {$row['image_url']} (product_id: {$product_id_str})");
                            }
                        }
                    }
                    
                    // Debug: Eğer hala bulunamadıysa logla
                    if (empty($row['image_url'])) {
                        error_log("Product image NOT found for product_id: {$product_id_str}, community: {$community_folder}");
                    }
                }
                
                // Hala bulunamadıysa null
                if (empty($row['image_url'])) {
                    $row['image_url'] = null;
                }
            }
            
            // Eğer image_path var ama image_url yoksa, tekrar dene
            if (!empty($row['image_path']) && empty($row['image_url']) && !empty($community_folder)) {
                $image_path = $row['image_path'];
                $normalized_path = str_replace('\\', '/', $image_path);
                if (preg_match('#^secure:/*products/#', $normalized_path)) {
                    $file = preg_replace('#^secure:/*products/#', '', $normalized_path);
                    $row['image_url'] = build_absolute_url('/api/product_media.php?file=' . rawurlencode($file) . '&community_id=' . rawurlencode($community_folder));
                }
            }
            
            // Komisyon bilgilerini ekle (eğer yoksa)
            if (!isset($row['commission_rate']) || $row['commission_rate'] === null) {
                addCommissionInfo($row);
            }
            
            $products[] = $row;
        }
        
        // Connection pool kullanılıyorsa release et, değilse close
        if ($poolId && $dbPath) {
            ConnectionPool::releaseConnection($dbPath, $poolId, true);
        } else {
            $db->close();
        }
        
        // Debug: Ürün sayısını logla
        error_log("Products API: " . count($products) . " ürün bulundu (community_id: $community_id, folder: $community_folder)");
        
        // Cache'e kaydetme (bypass edildi)
        // $publicCache->set($cache_key, $products, 300);
        
        echo json_encode([
            'success' => true,
            'data' => $products,
            'message' => null,
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Tüm ürünleri listele (tüm topluluklar) - Pagination ile
    if ($method === 'GET' && !$community_id && !$product_id) {
        // Pagination parametreleri
        $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 20; // Default 20 (optimize edildi), max 200
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        
        $communities_dir = __DIR__ . '/../communities/';
        $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
        if ($community_folders === false) {
            $community_folders = [];
        }
        
        $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
        $all_products = [];
        $total_count = 0; // Toplam ürün sayısı
        
        foreach ($community_folders as $folder) {
            $community_id = basename($folder);
            if (in_array($community_id, $excluded_dirs)) {
                continue;
            }
            $db_path = $folder . '/unipanel.sqlite';
            if (file_exists($db_path)) {
                try {
                    // Connection pool kullan (10k kullanıcı için kritik)
                    $connResult = ConnectionPool::getConnection($db_path, false);
                    if (!$connResult) {
                        continue;
                    }
                    $db = $connResult['db'];
                    $poolId = $connResult['pool_id'];

                    // Üniversite filtresi varsa topluluğu erken ele
                    $community_folder = basename($folder);
                    $community_university_name = null;
                    // Üniversite filtresi (kampanyalar.php'deki sistemle aynı)
                    if ($requested_university_id !== '') {
                        try {
                            $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                            $settings = [];
                            if ($settings_query) {
                                while ($srow = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                                    $settings[$srow['setting_key']] = $srow['setting_value'];
                                }
                            }
                            $community_university_name = $settings['university'] ?? $settings['organization'] ?? '';
                            $community_university_id = normalize_university_id($community_university_name);
                            
                            // Debug log (her zaman - sorun tespiti için)
                            error_log("Products API: Community '{$community_id}' - Requested ID: '{$requested_university_id}', Community Uni Name: '{$community_university_name}' -> Normalized ID: '{$community_university_id}'");
                            
                            // Eğer üniversite eşleşmiyorsa geç (kampanyalar.php'deki sistemle aynı)
                            if ($community_university_id === '' || $community_university_id !== $requested_university_id) {
                                error_log("Products API: Community '{$community_id}' SKIPPED - Üniversite eşleşmedi (Requested: '{$requested_university_id}' vs Community: '{$community_university_id}')");
                                ConnectionPool::releaseConnection($db_path, $poolId, false);
                                continue;
                            }
                            
                            error_log("Products API: Community '{$community_id}' MATCHED - Üniversite filtresi geçti");
                        } catch (Exception $e) {
                            ConnectionPool::releaseConnection($db_path, $poolId, false);
                            continue;
                        }
                    } else {
                        // Filtre yoksa yine de üniversite adını eklemek için bir kere oku (hata olursa null)
                        try {
                            $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                            $settings = [];
                            if ($settings_query) {
                                while ($srow = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                                    $settings[$srow['setting_key']] = $srow['setting_value'];
                                }
                            }
                            $community_university_name = $settings['university'] ?? $settings['organization'] ?? null;
                        } catch (Exception $e) {
                            $community_university_name = null;
                        }
                    }
                    
                    // products tablosunun var olup olmadığını kontrol et
                    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
                    if (!$table_check || !$table_check->fetchArray()) {
                        ConnectionPool::releaseConnection($db_path, $poolId, false);
                        continue;
                    }
                    
                    $result = $db->query("SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC");
                    
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        // ID'yi string'e çevir (Swift için)
                        $row['id'] = (string)$row['id'];
                        // community_id ekle (folder name)
                        $row['community_id'] = $community_folder;
                        $row['university'] = $community_university_name;
                        
                        // Image path'i düzelt (API'den erişilebilir URL) - Çoklu görsel desteği
                        if (!empty($row['image_path']) && !empty($community_folder)) {
                            // image_path JSON array veya tek string olabilir
                            $image_paths = json_decode($row['image_path'], true);
                            if (!is_array($image_paths)) {
                                // Eski format (tek string)
                                $image_paths = [$row['image_path']];
                            }
                            
                            $image_urls = [];
                            foreach ($image_paths as $image_path) {
                                if (empty($image_path)) continue;
                                
                                $normalized_path = str_replace('\\', '/', $image_path);
                                if (preg_match('#^secure:/*products/#', $normalized_path)) {
                                    $file = preg_replace('#^secure:/*products/#', '', $normalized_path);
                                    $image_urls[] = build_absolute_url('/api/product_media.php?file=' . rawurlencode($file) . '&community_id=' . rawurlencode($community_folder));
                                } else {
                                    $image_path_clean = ltrim($image_path, '/');
                                    $image_urls[] = build_absolute_url('/communities/' . $community_folder . '/' . $image_path_clean);
                                }
                            }
                            
                            // Çoklu görsel desteği
                            $row['image_urls'] = $image_urls;
                            // Geriye dönük uyumluluk için ilk görseli image_url olarak kullan
                            if (!empty($image_urls[0])) {
                                $row['image_url'] = $image_urls[0];
                            }
                        } else {
                            // image_path boşsa, dosya sisteminden görseli bul
                            $product_id = $row['id'] ?? null;
                            if ($product_id && $community_folder) {
                                $community_dir = __DIR__ . '/../communities/' . $community_folder;
                                $possible_paths = [
                                    'assets/images/products/product_' . $product_id . '.jpg',
                                    'assets/images/products/product_' . $product_id . '.jpeg',
                                    'assets/images/products/product_' . $product_id . '.png',
                                    'assets/images/products/product_' . $product_id . '.gif',
                                    'assets/images/products/' . $product_id . '.jpg',
                                    'assets/images/products/' . $product_id . '.jpeg',
                                    'assets/images/products/' . $product_id . '.png',
                                    'assets/images/products/' . $product_id . '.gif',
                                ];
                                
                                foreach ($possible_paths as $possible_path) {
                                    $full_path = $community_dir . '/' . $possible_path;
                                    if (file_exists($full_path)) {
                                        $row['image_path'] = $possible_path;
                                        $row['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $possible_path);
                                        break;
                                    }
                                }
                                
                                // Hala bulunamadıysa, products klasöründeki tüm görselleri tara
                                if (empty($row['image_url'])) {
                                    $products_dir = $community_dir . '/assets/images/products';
                                    if (is_dir($products_dir)) {
                                        $files = glob($products_dir . '/product_' . $product_id . '.*');
                                        if (empty($files)) {
                                            $files = glob($products_dir . '/' . $product_id . '.*');
                                        }
                                        if (!empty($files)) {
                                            $found_file = basename($files[0]);
                                            $relative_path = 'assets/images/products/' . $found_file;
                                            $row['image_path'] = $relative_path;
                                            $row['image_url'] = build_absolute_url('/communities/' . $community_folder . '/' . $relative_path);
                                        }
                                    }
                                }
                            }
                            
                            // Hala bulunamadıysa null
                            if (empty($row['image_url'])) {
                                $row['image_url'] = null;
                            }
                        }
                        
                        // Eğer image_path var ama image_url yoksa, tekrar dene
                        if (!empty($row['image_path']) && empty($row['image_url']) && !empty($community_folder)) {
                            $image_path = $row['image_path'];
                            $normalized_path = str_replace('\\', '/', $image_path);
                            if (preg_match('#^secure:/*products/#', $normalized_path)) {
                                $file = preg_replace('#^secure:/*products/#', '', $normalized_path);
                                $row['image_url'] = build_absolute_url('/api/product_media.php?file=' . rawurlencode($file) . '&community_id=' . rawurlencode($community_folder));
                            }
                        }
                        
                        // Komisyon bilgilerini ekle (eğer yoksa)
                        if (!isset($row['commission_rate']) || $row['commission_rate'] === null) {
                            addCommissionInfo($row);
                        }
                        
                        $all_products[] = $row;
                    }
                    
                    // Connection pool'a geri ver
                    ConnectionPool::releaseConnection($db_path, $poolId, false);
                } catch (Exception $e) {
                    error_log("Products API Error (all products): " . $e->getMessage());
                    continue;
                }
            }
        }
        
        // Tüm ürünleri tarihe göre sırala (en yeni önce)
        usort($all_products, function($a, $b) {
            $dateA = $a['created_at'] ?? '';
            $dateB = $b['created_at'] ?? '';
            return strcmp($dateB, $dateA); // Descending order
        });
        
        // Toplam sayı
        $total_count = count($all_products);
        
        // Pagination uygula
        $paginated_products = array_slice($all_products, $offset, $limit);
        
        // Debug: Toplam ürün sayısını logla
        error_log("Products API: {$total_count} ürün bulundu, {$limit} ürün döndürülüyor (offset: {$offset})");
        
        echo json_encode([
            'success' => true,
            'data' => $paginated_products,
            'count' => $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count,
            'message' => null,
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // POST, PUT, DELETE için authentication gerekli
    if (!isset($currentUser) || !$currentUser) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => null,
            'error' => 'Yetkilendirme gerekli.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // POST - Yeni ürün ekle
    if ($method === 'POST') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'data' => null,
                    'message' => null,
                    'error' => 'CSRF token geçersiz.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$community_id) {
            $community_id = isset($input['community_id']) ? sanitizeCommunityId(trim($input['community_id'])) : null;
        }
        
        if (!$community_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'community_id gereklidir.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db_result = find_community_db($community_id);
        if (!$db_result) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Topluluk bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db = $db_result['db'];
        
        $name = sanitizeInput(trim($input['name'] ?? ''), 'string');
        $description = sanitizeInput(trim($input['description'] ?? ''), 'string');
        $price = isset($input['price']) ? (float)sanitizeInput($input['price'], 'float') : 0;
        $stock = isset($input['stock']) ? (int)sanitizeInput($input['stock'], 'int') : 0;
        $category = sanitizeInput(trim($input['category'] ?? ''), 'string');
        $status = sanitizeInput(trim($input['status'] ?? 'active'), 'string');
        
        // Input validation
        if (empty($name) || strlen($name) > 255) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Ürün adı gereklidir ve 255 karakterden uzun olamaz.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($price < 0 || $price > 999999) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Geçersiz fiyat değeri.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($stock < 0 || $stock > 999999) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Geçersiz stok değeri.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Komisyon hesaplamaları
        $commission_rate = 8.0; // Sabit komisyon oranı
        $iyzico_commission = calculateIyzicoCommission($price);
        $platform_commission = calculatePlatformCommission($price, $commission_rate);
        $total_price = calculateTotalPrice($price, $commission_rate);
        
        $stmt = $db->prepare("INSERT INTO products (club_id, name, description, price, stock, category, status, commission_rate, iyzico_commission, platform_commission, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))");
        $stmt->bindValue(1, 1, SQLITE3_INTEGER); // club_id her zaman 1
        $stmt->bindValue(2, $name, SQLITE3_TEXT);
        $stmt->bindValue(3, $description, SQLITE3_TEXT);
        $stmt->bindValue(4, $price, SQLITE3_REAL);
        $stmt->bindValue(5, $stock, SQLITE3_INTEGER);
        $stmt->bindValue(6, $category, SQLITE3_TEXT);
        $stmt->bindValue(7, $status, SQLITE3_TEXT);
        $stmt->bindValue(8, $commission_rate, SQLITE3_REAL);
        $stmt->bindValue(9, $iyzico_commission, SQLITE3_REAL);
        $stmt->bindValue(10, $platform_commission, SQLITE3_REAL);
        $stmt->bindValue(11, $total_price, SQLITE3_REAL);
        $stmt->execute();
        
        $product_id = $db->lastInsertRowID();
        
        // Cache'i temizle
        $publicCache->delete("products_community_{$community_id}");
        $publicCache->delete("products_all");
        
        $db->close();
        
        echo json_encode([
            'success' => true,
            'data' => ['id' => $product_id],
            'message' => 'Ürün başarıyla eklendi.',
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // PUT - Ürün güncelle
    if ($method === 'PUT') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'data' => null,
                    'message' => null,
                    'error' => 'CSRF token geçersiz.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$product_id) {
            $product_id = isset($input['id']) ? (int)$input['id'] : null;
        }
        
        if (!$product_id || !$community_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'id ve community_id gereklidir.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db_result = find_community_db($community_id);
        if (!$db_result) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Topluluk bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db = $db_result['db'];
        
        $name = sanitizeInput(trim($input['name'] ?? ''), 'string');
        $description = sanitizeInput(trim($input['description'] ?? ''), 'string');
        $price = isset($input['price']) ? (float)sanitizeInput($input['price'], 'float') : 0;
        $stock = isset($input['stock']) ? (int)sanitizeInput($input['stock'], 'int') : 0;
        $category = sanitizeInput(trim($input['category'] ?? ''), 'string');
        $status = sanitizeInput(trim($input['status'] ?? 'active'), 'string');
        
        // Input validation
        if (empty($name) || strlen($name) > 255) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Ürün adı gereklidir ve 255 karakterden uzun olamaz.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($price < 0 || $price > 999999) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Geçersiz fiyat değeri.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($stock < 0 || $stock > 999999) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Geçersiz stok değeri.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Komisyon hesaplamaları
        $commission_rate = 8.0; // Sabit komisyon oranı
        $iyzico_commission = calculateIyzicoCommission($price);
        $platform_commission = calculatePlatformCommission($price, $commission_rate);
        $total_price = calculateTotalPrice($price, $commission_rate);
        
        $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, commission_rate = ?, iyzico_commission = ?, platform_commission = ?, total_price = ?, updated_at = datetime('now') WHERE id = ? AND club_id = 1");
        $stmt->bindValue(1, $name, SQLITE3_TEXT);
        $stmt->bindValue(2, $description, SQLITE3_TEXT);
        $stmt->bindValue(3, $price, SQLITE3_REAL);
        $stmt->bindValue(4, $stock, SQLITE3_INTEGER);
        $stmt->bindValue(5, $category, SQLITE3_TEXT);
        $stmt->bindValue(6, $status, SQLITE3_TEXT);
        $stmt->bindValue(7, $commission_rate, SQLITE3_REAL);
        $stmt->bindValue(8, $iyzico_commission, SQLITE3_REAL);
        $stmt->bindValue(9, $platform_commission, SQLITE3_REAL);
        $stmt->bindValue(10, $total_price, SQLITE3_REAL);
        $stmt->bindValue(11, $product_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Cache'i temizle
        $publicCache->delete("products_community_{$community_id}");
        $publicCache->delete("products_all");
        
        $db->close();
        
        echo json_encode([
            'success' => true,
            'data' => ['id' => $product_id],
            'message' => 'Ürün başarıyla güncellendi.',
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // DELETE - Ürün sil
    if ($method === 'DELETE') {
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'data' => null,
                    'message' => null,
                    'error' => 'CSRF token geçersiz.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        if (!$product_id || !$community_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'id ve community_id gereklidir.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db_result = find_community_db($community_id);
        if (!$db_result) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => null,
                'error' => 'Topluluk bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db = $db_result['db'];
        
        // club_id her zaman 1 (yeni sistemde)
        $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND club_id = 1");
        $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Cache'i temizle
        $publicCache->delete("products_community_{$community_id}");
        $publicCache->delete("products_all");
        
        $db->close();
        
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'Ürün başarıyla silindi.',
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Geçersiz HTTP metodu.'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

