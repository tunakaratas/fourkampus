<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - PUBLIC WEB SİTESİ (HERKES ERİŞEBİLİR)
// =================================================================

// Security helper'ı yükle
require_once __DIR__ . '/security_helper.php';

// Güvenli session başlat (security headers dahil)
secure_session_start();

// Production'da hataları gizle
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../system/logs/php_errors.log');

$home_sections = ['communities', 'events', 'market', 'campaigns'];
$home_current_view = $_GET['section'] ?? 'communities';
if (!in_array($home_current_view, $home_sections, true)) {
    $home_current_view = 'communities';
}

// Cache sistemini yükle
require_once __DIR__ . '/../lib/core/Cache.php';
use UniPanel\Core\Cache;

// Cache instance
$publicCache = Cache::getInstance(__DIR__ . '/../system/cache');

// --- AUTHENTICATION İŞLEMLERİ ---
// Not: Login ve register işlemleri artık login.php ve register.php sayfalarında yapılıyor

// --- VERİ ÇEKME FONKSİYONLARI ---

// Kullanıcı bilgilerini getir
function get_user_profile($user_id) {
    $db_path = __DIR__ . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return null;
    }
    
    try {
        // Güvenli database bağlantısı (read-only)
        $db = get_safe_db_connection($db_path, true);
        if (!$db) {
            return null;
        }
        
        $stmt = $db->prepare("SELECT id, email, first_name, last_name, student_id, phone_number, university, department, created_at, last_login FROM system_users WHERE id = ? AND is_active = 1");
        if (!$stmt) {
            $db->close();
            return null;
        }
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if (!$result) {
            $db->close();
            return null;
        }
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        $db->close();
        
        return $user;
    } catch (Exception $e) {
        error_log("get_user_profile error: " . $e->getMessage());
        return null;
    }
}


// Kullanıcı bildirimlerini getir
function get_user_notifications($user_id) {
    $db_path = __DIR__ . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return [];
    }
    
    try {
        $db = get_safe_db_connection($db_path, true);
        if (!$db) {
            return [];
        }
        
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $notifications = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notifications[] = $row;
        }
        
        $db->close();
        return $notifications;
    } catch (Exception $e) {
        return [];
    }
}

// Kullanıcı bilgilerini güncelle
function update_user_profile($user_id, $data) {
    $db_path = __DIR__ . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return ['success' => false, 'message' => 'Veritabanı bulunamadı'];
    }
    
    try {
        // Güvenli database bağlantısı
        $db = get_safe_db_connection($db_path, false);
        if (!$db) {
            return ['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.'];
        }
        
        // Input sanitization
        if (isset($data['email'])) {
            $data['email'] = sanitize_input($data['email'], 'email');
        }
        if (isset($data['first_name'])) {
            $data['first_name'] = sanitize_input($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $data['last_name'] = sanitize_input($data['last_name']);
        }
        if (isset($data['student_id'])) {
            $data['student_id'] = sanitize_input($data['student_id']);
        }
        if (isset($data['phone_number'])) {
            $phone_validation = validate_phone($data['phone_number']);
            if ($phone_validation['valid']) {
                $data['phone_number'] = $phone_validation['normalized'];
            } else {
                $db->close();
                return ['success' => false, 'message' => $phone_validation['message']];
            }
        }
        
        // Email kontrolü (başka bir kullanıcıda var mı?)
        if (!empty($data['email'])) {
            // Email validation
            $email_validation = validate_email($data['email']);
            if (!$email_validation['valid']) {
                $db->close();
                return ['success' => false, 'message' => $email_validation['message']];
            }
            
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE email = ? AND id != ?");
            if ($check_stmt) {
                $check_stmt->bindValue(1, $data['email'], SQLITE3_TEXT);
                $check_stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                $check_result = $check_stmt->execute();
                if ($check_result && $check_result->fetchArray()) {
                    $db->close();
                    return ['success' => false, 'message' => 'Bu email adresi zaten kullanılıyor'];
                }
            }
        }
        
        // Student ID kontrolü (varsa)
        if (!empty($data['student_id'])) {
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE student_id = ? AND id != ?");
            if ($check_stmt) {
                $check_stmt->bindValue(1, $data['student_id'], SQLITE3_TEXT);
                $check_stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                $check_result = $check_stmt->execute();
                if ($check_result && $check_result->fetchArray()) {
                    $db->close();
                    return ['success' => false, 'message' => 'Bu öğrenci numarası zaten kullanılıyor'];
                }
            }
        }
        
        // Phone kontrolü (varsa)
        if (!empty($data['phone_number'])) {
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE phone_number = ? AND id != ?");
            if ($check_stmt) {
                $check_stmt->bindValue(1, $data['phone_number'], SQLITE3_TEXT);
                $check_stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                $check_result = $check_stmt->execute();
                if ($check_result && $check_result->fetchArray()) {
                    $db->close();
                    return ['success' => false, 'message' => 'Bu telefon numarası zaten kullanılıyor'];
                }
            }
        }
        
        // Güncelleme sorgusu
        $update_fields = [];
        $update_values = [];
        
        if (isset($data['first_name'])) {
            $update_fields[] = "first_name = ?";
            $update_values[] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $update_fields[] = "last_name = ?";
            $update_values[] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $update_fields[] = "email = ?";
            $update_values[] = $data['email'];
        }
        if (isset($data['student_id'])) {
            $update_fields[] = "student_id = ?";
            $update_values[] = $data['student_id'];
        }
        if (isset($data['phone_number'])) {
            $update_fields[] = "phone_number = ?";
            $update_values[] = $data['phone_number'];
        }
        if (isset($data['university'])) {
            $update_fields[] = "university = ?";
            $update_values[] = $data['university'];
        }
        if (isset($data['department'])) {
            $update_fields[] = "department = ?";
            $update_values[] = $data['department'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            // Password strength kontrolü
            $password_validation = validate_password_strength($data['password']);
            if (!$password_validation['valid']) {
                $db->close();
                return ['success' => false, 'message' => $password_validation['message']];
            }
            $update_fields[] = "password_hash = ?";
            $update_values[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($update_fields)) {
            $db->close();
            return ['success' => false, 'message' => 'Güncellenecek alan bulunamadı'];
        }
        
        $update_values[] = $user_id;
        $sql = "UPDATE system_users SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $update_stmt = $db->prepare($sql);
        
        $i = 1;
        foreach ($update_values as $value) {
            $update_stmt->bindValue($i, $value, SQLITE3_TEXT);
            $i++;
        }
        
        $update_stmt->execute();
        $db->close();
        
        return ['success' => true, 'message' => 'Profil başarıyla güncellendi'];
    } catch (Exception $e) {
        // Production'da hassas bilgi sızıntısını önle
        $error_message = handleError('Profil güncelleme hatası', $e);
        return ['success' => false, 'message' => $error_message];
    }
}

// Tüm toplulukları getir (Cache'lenmiş - 10 dakika TTL)
function get_all_communities($useCache = true) {
    global $publicCache;
    
    // Cache key
    $cacheKey = 'all_communities_list_v2';
    
    // Cache'den al (10 dakika = 600 saniye)
    if ($useCache && $publicCache) {
        $cached = $publicCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }
    
    // Cache miss - veritabanlarından çek
    $communities_dir = __DIR__ . '/../communities';
    $communities = [];
    
    if (!is_dir($communities_dir)) {
        return [];
    }
    
    $dirs = scandir($communities_dir);
    $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    
    foreach ($dirs as $dir) {
        if (in_array($dir, $excluded_dirs) || !is_dir($communities_dir . '/' . $dir)) {
            continue;
        }
        
        $db_path = $communities_dir . '/' . $dir . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            // Güvenli database bağlantısı (read-only)
            $community_db = get_safe_db_connection($db_path, true);
            if (!$community_db) {
                continue;
            }
            
            // Topluluk bilgilerini al
            $settings = [];
            try {
                // Önce settings tablosunun var olup olmadığını kontrol et
                $table_check = @$community_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
                if ($table_check && $table_check->fetchArray()) {
                    // Önce club_id kolonu olmadan dene
                    $settings_query = @$community_db->query("SELECT setting_key, setting_value FROM settings");
                    if (!$settings_query) {
                        // club_id ile dene
                        $settings_query = @$community_db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                    }
                    if ($settings_query) {
                        while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                            $settings[$row['setting_key']] = $row['setting_value'];
                        }
                    }
                }
            } catch (Exception $e) {
                // Settings tablosu yoksa boş array kullan
                $settings = [];
            }
            
            // İstatistikler
            $member_count = 0;
            $event_count = 0;
            $campaign_count = 0;
            
            // Exceptions'ı geçici olarak kapat
            $old_exceptions = $community_db->enableExceptions(false);
            
            // Members tablosunu kontrol et
            $members_table_check = @$community_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
            if ($members_table_check && $members_table_check->fetchArray()) {
                $member_result = @$community_db->querySingle("SELECT COUNT(*) FROM members WHERE club_id = 1");
                if ($member_result !== false) {
                    $member_count = (int)$member_result;
                }
            }
            
            // Events tablosunu kontrol et
            $events_table_check = @$community_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
            if ($events_table_check && $events_table_check->fetchArray()) {
                $event_result = @$community_db->querySingle("SELECT COUNT(*) FROM events WHERE club_id = 1");
                if ($event_result !== false) {
                    $event_count = (int)$event_result;
                }
            }
            
            // Kampanyalar tablosunu kontrol et (readonly olduğu için oluşturma)
            $campaigns_table_check = @$community_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='campaigns'");
            if ($campaigns_table_check && $campaigns_table_check->fetchArray()) {
                $campaign_result = @$community_db->querySingle("SELECT COUNT(*) FROM campaigns WHERE club_id = 1 AND is_active = 1");
                if ($campaign_result !== false) {
                    $campaign_count = (int)$campaign_result;
                }
            }
            
            // Exceptions'ı geri aç
            $community_db->enableExceptions($old_exceptions);
            
            $communities[] = [
                'id' => $dir,
                'name' => $settings['club_name'] ?? ucwords(str_replace('_', ' ', $dir)),
                'description' => $settings['club_description'] ?? '',
                'member_count' => (int)$member_count,
                'event_count' => (int)$event_count,
                'campaign_count' => (int)$campaign_count
            ];
            
            $community_db->close();
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Cache'e kaydet (10 dakika)
    if ($publicCache) {
        $publicCache->set($cacheKey, $communities, 600);
    }
    
    return $communities;
}

function market_normalize_text($text) {
    if (!is_string($text)) {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return trim(mb_strtolower($text, 'UTF-8'));
    }
    return trim(strtolower($text));
}

function market_calculate_commissions($price, $commission_rate = 8.0) {
    $price = (float)$price;
    $commission_rate = (float)$commission_rate;
    
    $iyzico_rate = 2.99;
    $iyzico_fixed = 0.25;
    
    $iyzico = ($price * $iyzico_rate / 100) + $iyzico_fixed;
    $platform = $price * $commission_rate / 100;
    $total = $price + $iyzico + $platform;
    
    return [
        'iyzico' => round($iyzico, 2),
        'platform' => round($platform, 2),
        'total' => round($total, 2)
    ];
}

function get_public_market_products($limit = 36) {
    global $publicCache;
    
    $limit = max(1, (int)$limit);
    $cacheKey = "public_market_products_v2_{$limit}"; // v2: image_url düzeltmesi için cache key değiştirildi
    
    if ($publicCache) {
        $cached = $publicCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $communities_dir = __DIR__ . '/../communities';
    if (!is_dir($communities_dir)) {
        return [
            'products' => [],
            'categories' => [],
            'total_products' => 0,
            'community_count' => 0
        ];
    }
    
    $excludedDirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
    if ($community_folders === false) {
        $community_folders = [];
    }
    
    $products = [];
    $categories = [];
    $communitiesWithProducts = [];
    
    foreach ($community_folders as $folder) {
        $dirName = basename($folder);
        if (in_array($dirName, $excludedDirs, true)) {
            continue;
        }
        
        $db_path = $folder . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            // Güvenli database bağlantısı (read-only)
            $db = get_safe_db_connection($db_path, true);
            if (!$db) {
                continue;
            }
        } catch (Exception $e) {
            continue;
        }
        
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
        if (!$table_check || !$table_check->fetchArray()) {
            $db->close();
            continue;
        }
        
        $settings = [];
        try {
            // Önce settings tablosunun var olup olmadığını kontrol et
            $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            if ($table_check && $table_check->fetchArray()) {
                // Önce club_id kolonu olmadan dene
                $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings");
                if (!$settings_query) {
                    // club_id ile dene
                    $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                }
                if ($settings_query) {
                    while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // Settings tablosu yoksa boş array kullan
            $settings = [];
        }
        $community_name = $settings['club_name'] ?? ucwords(str_replace('_', ' ', $dirName));
        $community_color = $settings['primary_color'] ?? '#6366f1';
        
        $product_query = $db->query("SELECT id, name, description, price, stock, category, status, image_path, commission_rate, iyzico_commission, platform_commission, total_price, created_at FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT 30");
        if (!$product_query) {
            $db->close();
            continue;
        }
        
        while ($row = $product_query->fetchArray(SQLITE3_ASSOC)) {
            if (empty($row['name'])) {
                continue;
            }
            
            $category = $row['category'] ?: 'Genel';
            $price = (float)($row['price'] ?? 0);
            $commission_rate = isset($row['commission_rate']) ? (float)$row['commission_rate'] : 8.0;
            
            $iyzico_commission = isset($row['iyzico_commission']) ? (float)$row['iyzico_commission'] : null;
            $platform_commission = isset($row['platform_commission']) ? (float)$row['platform_commission'] : null;
            $total_price = isset($row['total_price']) ? (float)$row['total_price'] : null;
            
            if ($iyzico_commission === null || $platform_commission === null || $total_price === null) {
                $commissionData = market_calculate_commissions($price, $commission_rate);
                $iyzico_commission = $commissionData['iyzico'];
                $platform_commission = $commissionData['platform'];
                $total_price = $commissionData['total'];
            }
            
            $image_url = null;
            if (!empty($row['image_path'])) {
                // image_path formatı: assets/images/products/product_xxx.jpg
                // Oluşturulacak URL: /fourkampus/communities/{dirName}/assets/images/products/product_xxx.jpg
                $image_path = ltrim($row['image_path'], '/');
                $image_url = build_public_asset_url('communities/' . $dirName . '/' . $image_path);
            }
            
            $products[] = [
                'key' => $dirName . '-' . $row['id'],
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'category' => $category,
                'category_slug' => market_normalize_text($category),
                'price' => $price,
                'stock' => (int)($row['stock'] ?? 0),
                'commission_rate' => $commission_rate,
                'iyzico_commission' => $iyzico_commission,
                'platform_commission' => $platform_commission,
                'total_price' => $total_price,
                'image_url' => $image_url,
                'community_slug' => $dirName,
                'community_name' => $community_name,
                'community_color' => $community_color,
                'created_at' => $row['created_at'] ?? null,
                'search_index' => market_normalize_text(
                    ($row['name'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . $category . ' ' . $community_name
                ),
            ];
            
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            $communitiesWithProducts[$dirName] = true;
            
            // Removed early break to ensure global sorting works correctly
            // if (count($products) >= $limit) {
            //    break 2;
            // }
        }
        
        $db->close();
    }
    
    usort($products, function($a, $b) {
        $timeA = $a['created_at'] ? strtotime($a['created_at']) : 0;
        $timeB = $b['created_at'] ? strtotime($b['created_at']) : 0;
        return $timeB <=> $timeA;
    });
    
    arsort($categories);
    $categoryNames = array_keys($categories);
    
    $total_found = count($products);
    $products = array_slice($products, 0, $limit);
    
    $result = [
        'products' => $products,
        'categories' => $categoryNames,
        'total_products' => $total_found,
        'community_count' => count($communitiesWithProducts),
    ];
    
    if ($publicCache) {
        $publicCache->set($cacheKey, $result, 300);
    }
    
    return $result;
}

function get_public_base_url() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Script'in çalıştığı dizini bul (public klasörü)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // /fourkampus/public/index.php -> /fourkampus
    // /public/index.php -> root
    if (strpos($scriptPath, '/fourkampus/public') !== false) {
        $basePath = '/fourkampus';
    } elseif (strpos($scriptPath, '/public') !== false) {
        // /public/index.php -> base path'i bul
        $basePath = str_replace('/public', '', dirname($scriptPath));
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

function build_public_asset_url($path) {
    if (empty($path)) {
        return null;
    }
    $normalized = '/' . ltrim($path, '/');
    return get_public_base_url() . $normalized;
}

function get_public_events($limit = 8) {
    global $publicCache;
    
    $limit = max(1, (int)$limit);
    $cacheKey = "public_events_v1_{$limit}";
    
    if ($publicCache) {
        $cached = $publicCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $communities_dir = __DIR__ . '/../communities';
    $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR) ?: [];
    
    $events = [];
    $communities = [];
    $now = time();
    
    foreach ($community_folders as $folder) {
        $dirName = basename($folder);
        if (!is_dir($folder)) {
            continue;
        }
        $db_path = $folder . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            // Güvenli database bağlantısı (read-only)
            $db = get_safe_db_connection($db_path, true);
            if (!$db) {
                continue;
            }
        } catch (Exception $e) {
            continue;
        }
        
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
        if (!$table_check || !$table_check->fetchArray()) {
            $db->close();
            continue;
        }
        
        $settings = [];
        try {
            // Önce settings tablosunun var olup olmadığını kontrol et
            $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            if ($table_check && $table_check->fetchArray()) {
                // Önce club_id kolonu olmadan dene
                $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings");
                if (!$settings_query) {
                    // club_id ile dene
                    $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                }
                if ($settings_query) {
                    while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // Settings tablosu yoksa boş array kullan
            $settings = [];
        }
        $community_name = $settings['club_name'] ?? ucwords(str_replace('_', ' ', $dirName));
        $community_color = $settings['primary_color'] ?? '#6366f1';
        
        $hasStartDatetime = false;
        $hasStatusColumn = false;
        $tableInfo = $db->query("PRAGMA table_info(events)");
        if ($tableInfo) {
            while ($col = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
                $colName = $col['name'] ?? '';
                if ($colName === 'start_datetime') {
                    $hasStartDatetime = true;
                }
                if ($colName === 'status') {
                    $hasStatusColumn = true;
                }
            }
        }
        
        $selectColumns = [
            'id',
            'title',
            'description',
            'date',
            'time',
            'location',
            $hasStatusColumn ? 'status' : "'planlanıyor' AS status",
            'image_path'
        ];
        
        $eventSelect = "SELECT " . implode(', ', $selectColumns);
        $eventSelect .= $hasStartDatetime ? ", start_datetime" : ", NULL as start_datetime";
        $eventSelect .= " FROM events WHERE club_id = 1 ORDER BY date DESC LIMIT 50";
        
        $result = $db->query($eventSelect);
        if (!$result) {
            $db->close();
            continue;
        }
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $startDateTime = $row['start_datetime'] ?? null;
            if (!$startDateTime && !empty($row['date'])) {
                $startDateTime = $row['date'] . ' ' . (!empty($row['time']) ? $row['time'] : '00:00');
            }
            $timestamp = $startDateTime ? strtotime($startDateTime) : null;
            if ($timestamp && $timestamp < ($now - 60 * 60 * 24 * 2)) {
                continue;
            }
            
            $displayDate = $timestamp ? date('d M Y', $timestamp) : ($row['date'] ?? 'Tarih bekleniyor');
            $displayTime = $timestamp ? date('H:i', $timestamp) : ($row['time'] ?? '');
            
            $image_url = null;
            if (!empty($row['image_path'])) {
                $image_url = build_public_asset_url('communities/' . $dirName . '/' . ltrim($row['image_path'], '/'));
            }
            
            $events[] = [
                'key' => $dirName . '-' . $row['id'],
                'id' => (int)$row['id'],
                'title' => $row['title'] ?? 'Etkinlik',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? 'Belirlenecek',
                'status' => $row['status'] ?? 'planlanıyor',
                'datetime' => $timestamp,
                'date_label' => $displayDate,
                'time_label' => $displayTime,
                'image_url' => $image_url,
                'community_name' => $community_name,
                'community_slug' => $dirName,
                'community_color' => $community_color
            ];
            $communities[$dirName] = true;
        }
        
        $db->close();
    }
    
    usort($events, function ($a, $b) {
        $aTime = $a['datetime'] ?? 0;
        $bTime = $b['datetime'] ?? 0;
        if ($aTime === $bTime) {
            return 0;
        }
        if ($aTime === 0) {
            return 1;
        }
        if ($bTime === 0) {
            return -1;
        }
        return $aTime <=> $bTime;
    });
    
    $events = array_slice($events, 0, $limit);
    
    $result = [
        'events' => $events,
        'total' => count($events),
        'community_count' => count($communities)
    ];
    
    if ($publicCache) {
        $publicCache->set($cacheKey, $result, 300);
    }
    
    return $result;
}

function get_public_campaigns($limit = 6) {
    global $publicCache;
    
    $limit = max(1, (int)$limit);
    $cacheKey = "public_campaigns_v1_{$limit}";
    
    if ($publicCache) {
        $cached = $publicCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $communities_dir = __DIR__ . '/../communities';
    $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR) ?: [];
    
    $campaigns = [];
    $communities = [];
    
    foreach ($community_folders as $folder) {
        $dirName = basename($folder);
        $db_path = $folder . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            // Güvenli database bağlantısı (read-only)
            $db = get_safe_db_connection($db_path, true);
            if (!$db) {
                continue;
            }
        } catch (Exception $e) {
            continue;
        }
        
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='campaigns'");
        if (!$table_check || !$table_check->fetchArray()) {
            $db->close();
            continue;
        }
        
        $settings = [];
        try {
            // Önce settings tablosunun var olup olmadığını kontrol et
            $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            if ($table_check && $table_check->fetchArray()) {
                // Önce club_id kolonu olmadan dene
                $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings");
                if (!$settings_query) {
                    // club_id ile dene
                    $settings_query = @$db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                }
                if ($settings_query) {
                    while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // Settings tablosu yoksa boş array kullan
            $settings = [];
        }
        $community_name = $settings['club_name'] ?? ucwords(str_replace('_', ' ', $dirName));
        $community_color = $settings['primary_color'] ?? '#8b5cf6';
        
        $result = $db->query("SELECT id, title, description, offer_text, partner_name, discount_percentage, image_path, start_date, end_date, is_active FROM campaigns WHERE club_id = 1 AND is_active = 1 ORDER BY start_date DESC LIMIT 30");
        if (!$result) {
            $db->close();
            continue;
        }
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $image_url = null;
            if (!empty($row['image_path'])) {
                $image_url = build_public_asset_url('communities/' . $dirName . '/' . ltrim($row['image_path'], '/'));
            }
            $endTimestamp = $row['end_date'] ? strtotime($row['end_date']) : null;
            $campaigns[] = [
                'key' => $dirName . '-' . $row['id'],
                'id' => (int)$row['id'],
                'title' => $row['title'] ?? 'Kampanya',
                'offer_text' => $row['offer_text'] ?? '',
                'description' => $row['description'] ?? '',
                'partner_name' => $row['partner_name'] ?? '',
                'discount_percentage' => $row['discount_percentage'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'is_expired' => $endTimestamp ? $endTimestamp < time() : false,
                'image_url' => $image_url,
                'community_name' => $community_name,
                'community_slug' => $dirName,
                'community_color' => $community_color
            ];
            $communities[$dirName] = true;
        }
        
        $db->close();
    }
    
    usort($campaigns, function ($a, $b) {
        $aTime = $a['start_date'] ? strtotime($a['start_date']) : 0;
        $bTime = $b['start_date'] ? strtotime($b['start_date']) : 0;
        return $bTime <=> $aTime;
    });
    
    $campaigns = array_slice($campaigns, 0, $limit);
    
    $result = [
        'campaigns' => $campaigns,
        'total' => count($campaigns),
        'community_count' => count($communities)
    ];
    
    if ($publicCache) {
        $publicCache->set($cacheKey, $result, 300);
    }
    
    return $result;
}

// Market verilerini önceden yükle
$market_data = get_public_market_products(42);
$market_products = $market_data['products'];
$market_categories = $market_data['categories'];
$market_total_products = $market_data['total_products'];
$market_total_communities = $market_data['community_count'];

$public_events_data = get_public_events(9);
$public_events = $public_events_data['events'];
$public_events_total = $public_events_data['total'];
$public_events_community_count = $public_events_data['community_count'];

$public_campaigns_data = get_public_campaigns(6);
$public_campaigns = $public_campaigns_data['campaigns'];
$public_campaigns_total = $public_campaigns_data['total'];
$public_campaigns_community_count = $public_campaigns_data['community_count'];

// Topluluk listesi cache'ini temizle (yeni topluluk eklendiğinde/güncellendiğinde çağrılmalı)
function clear_communities_cache() {
    global $publicCache;
    if ($publicCache) {
        $publicCache->delete('all_communities_list_v2');
    }
}

// Seçili topluluğun veritabanından veri çek
function get_community_data($community_id) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return null;
    }
    
    $db_path = __DIR__ . '/../communities/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return null;
    }
    
    try {
        // Güvenli database bağlantısı
        $db = get_safe_db_connection($db_path, false);
        if (!$db) {
            return null;
        }
        
        // Exceptions'ı geçici olarak kapat
        $old_exceptions = $db->enableExceptions(false);
        
        // Topluluk bilgileri
        $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
        $settings = [];
        if ($settings_query) {
            while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        // Etkinlikler
        $events = [];
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            date TEXT,
            time TEXT,
            location TEXT,
            image_path TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            tags TEXT
        )");
        $events_stmt = $db->prepare("SELECT * FROM events WHERE club_id = 1 ORDER BY date DESC, time DESC LIMIT 20");
        if ($events_stmt) {
            $events_result = $events_stmt->execute();
            if ($events_result) {
                while ($row = $events_result->fetchArray(SQLITE3_ASSOC)) {
                    $events[] = $row;
                }
            }
        }
        
        // Üyeler
        $members = [];
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            student_id TEXT,
            department TEXT,
            role TEXT DEFAULT 'member',
            status TEXT DEFAULT 'active',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $members_stmt = $db->prepare("SELECT full_name FROM members WHERE club_id = 1 AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
        if ($members_stmt) {
            $members_result = $members_stmt->execute();
            if ($members_result) {
                while ($row = $members_result->fetchArray(SQLITE3_ASSOC)) {
                    $members[] = $row;
                }
            }
        }
        
        // Kampanyalar
        $campaigns = [];
        // Tabloyu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            offer_text TEXT NOT NULL,
            partner_name TEXT,
            discount_percentage INTEGER,
            image_path TEXT,
            start_date TEXT,
            end_date TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $campaigns_stmt = $db->prepare("SELECT * FROM campaigns WHERE club_id = 1 AND is_active = 1 ORDER BY created_at DESC");
        if ($campaigns_stmt) {
            $campaigns_result = $campaigns_stmt->execute();
            if ($campaigns_result) {
                while ($row = $campaigns_result->fetchArray(SQLITE3_ASSOC)) {
                    $campaigns[] = $row;
                }
            }
        }
        
        // Yönetim Kurulu
        $board = [];
        $db->exec("CREATE TABLE IF NOT EXISTS board_members (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            image_path TEXT,
            display_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $board_stmt = $db->prepare("SELECT full_name, role FROM board_members WHERE club_id = 1 ORDER BY id ASC");
        if ($board_stmt) {
            $board_result = $board_stmt->execute();
            if ($board_result) {
                while ($row = $board_result->fetchArray(SQLITE3_ASSOC)) {
                    $board[] = $row;
                }
            }
        }
        
        // Exceptions'ı geri aç
        $db->enableExceptions($old_exceptions);
        
        $db->close();
        
        return [
            'name' => $settings['club_name'] ?? ucwords(str_replace('_', ' ', $community_id)),
            'description' => $settings['club_description'] ?? '',
            'events' => $events,
            'members' => $members,
            'campaigns' => $campaigns,
            'board' => $board
        ];
    } catch (Exception $e) {
        return null;
    }
}

// Etkinlik medyasını getir
function get_event_media($event_id, $community_id) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return ['images' => [], 'videos' => []];
    }
    
    // Event ID sanitization
    $event_id = filter_var($event_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($event_id === false) {
        return ['images' => [], 'videos' => []];
    }
    
    $media = ['images' => [], 'videos' => []];
    $db_path = __DIR__ . '/../communities/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return $media;
    }
    
    try {
        // Güvenli database bağlantısı
        $event_db = get_safe_db_connection($db_path, false);
        if (!$event_db) {
            return ['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı'];
        }
        
        // Fotoğraflar
        try {
            $images_stmt = $event_db->prepare("SELECT id, image_path FROM event_images WHERE event_id = ? AND club_id = 1 ORDER BY uploaded_at DESC");
            $images_stmt->bindValue(1, $event_id, SQLITE3_INTEGER);
            $images_result = $images_stmt->execute();
            while ($row = $images_result->fetchArray(SQLITE3_ASSOC)) {
                $media['images'][] = $row;
            }
        } catch (Exception $e) {}
        
        // Videolar
        try {
            $videos_stmt = $event_db->prepare("SELECT id, video_path FROM event_videos WHERE event_id = ? AND club_id = 1 ORDER BY uploaded_at DESC");
            $videos_stmt->bindValue(1, $event_id, SQLITE3_INTEGER);
            $videos_result = $videos_stmt->execute();
            while ($row = $videos_result->fetchArray(SQLITE3_ASSOC)) {
                $media['videos'][] = $row;
            }
        } catch (Exception $e) {}
        
        $event_db->close();
    } catch (Exception $e) {}
    
    return $media;
}

// --- VERİ ÇEKME ---
$all_communities = get_all_communities();

// Genel istatistikler (arama filtresinden önce hesapla)
$total_communities_all = count($all_communities);
$total_members_all = array_sum(array_column($all_communities, 'member_count'));
$total_events_all = array_sum(array_column($all_communities, 'event_count'));
$total_campaigns_all = array_sum(array_column($all_communities, 'campaign_count'));

// Arama ve sıralama parametreleri - Input sanitization
$search_query = sanitize_input(trim($_GET['search'] ?? ''), 'string');
// Search query uzunluk kontrolü (XSS ve DoS koruması)
if (strlen($search_query) > 100) {
    $search_query = substr($search_query, 0, 100);
}

// Sort by sanitization - Sadece izin verilen değerler
$allowed_sorts = ['name', 'members', 'events', 'campaigns'];
$sort_by = sanitize_input($_GET['sort'] ?? 'name', 'string');
if (!in_array($sort_by, $allowed_sorts, true)) {
    $sort_by = 'name';
}

// Arama filtresi
if ($search_query) {
    $all_communities = array_filter($all_communities, function($community) use ($search_query) {
        return stripos($community['name'], $search_query) !== false || 
               stripos($community['description'] ?? '', $search_query) !== false;
    });
    $all_communities = array_values($all_communities); // Re-index array
}

// Sıralama
if ($sort_by === 'members') {
    usort($all_communities, function($a, $b) {
        return $b['member_count'] - $a['member_count'];
    });
} elseif ($sort_by === 'events') {
    usort($all_communities, function($a, $b) {
        return $b['event_count'] - $a['event_count'];
    });
} elseif ($sort_by === 'campaigns') {
    usort($all_communities, function($a, $b) {
        return $b['campaign_count'] - $a['campaign_count'];
    });
} else {
    // Varsayılan: isme göre alfabetik
    usort($all_communities, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}

// Seçili topluluk - Path traversal koruması ile sanitize et
$selected_community = null;
if (isset($_GET['community'])) {
    $selected_community = sanitizeCommunityId($_GET['community']);
} elseif (isset($_POST['community'])) {
    $selected_community = sanitizeCommunityId($_POST['community']);
}

$community_data = null;

// View sanitization - Sadece izin verilen view'lar
$allowed_views = ['overview', 'events', 'members', 'market', 'campaigns', 'event_detail', 'product_detail'];
$current_view = sanitize_input($_GET['view'] ?? 'overview', 'string');
if (!in_array($current_view, $allowed_views, true)) {
    $current_view = 'overview';
}

$event_detail = null;
$event_media = null;

if ($selected_community && in_array($selected_community, array_column($all_communities, 'id'))) {
    $community_data = get_community_data($selected_community);
    
    if ($current_view === 'event_detail' && isset($_GET['event_id'])) {
        $event_id = (int)$_GET['event_id'];
        $event_detail = get_event_detail_data($selected_community, $event_id);
        if (!$event_detail) {
        foreach ($community_data['events'] as $event) {
            if ($event['id'] == $event_id) {
                $event_detail = $event;
                break;
            }
            }
        }
        if ($event_detail) {
            $event_media = get_event_media($event_id, $selected_community);
        }
    }
}

// Kullanıcı çıkışı
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Kullanıcı bilgilerini kontrol et (genel sistem girişi)
$user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$unread_notifications_count = 0;
if ($user_logged_in) {
    $user_id = $_SESSION['user_id'];
    $all_notifications = get_user_notifications($user_id);
    foreach ($all_notifications as $n) {
        if (!$n['is_read']) $unread_notifications_count++;
    }
}
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;

// Profil güncelleme
$profile_update_success = null;
$profile_update_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && $user_logged_in && $user_id) {
    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $update_error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        log_security_event('csrf_failure', ['page' => 'update_profile', 'user_id' => $user_id]);
    } else {
        // Input sanitization
        $update_data = [
            'first_name' => sanitize_input($_POST['first_name'] ?? ''),
            'last_name' => sanitize_input($_POST['last_name'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? '', 'email'),
            'student_id' => sanitize_input($_POST['student_id'] ?? ''),
            'phone_number' => sanitize_input($_POST['phone_number'] ?? ''),
            'university' => sanitize_input($_POST['university'] ?? ''),
            'department' => sanitize_input($_POST['department'] ?? '')
        ];
        
        // Validation
        if (empty($update_data['first_name']) || strlen($update_data['first_name']) < 2 || strlen($update_data['first_name']) > 50) {
            $profile_update_error = "Ad en az 2, en fazla 50 karakter olmalıdır.";
        } elseif (empty($update_data['last_name']) || strlen($update_data['last_name']) < 2 || strlen($update_data['last_name']) > 50) {
            $profile_update_error = "Soyad en az 2, en fazla 50 karakter olmalıdır.";
        } else {
            // Şifre güncelleme (opsiyonel)
            if (!empty($_POST['password']) && !empty($_POST['password_confirm'])) {
                if ($_POST['password'] === $_POST['password_confirm']) {
                    $password_validation = validate_password_strength($_POST['password']);
                    if (!$password_validation['valid']) {
                        $profile_update_error = $password_validation['message'];
                    } else {
                        $update_data['password'] = $_POST['password'];
                    }
                } else {
                    $profile_update_error = "Şifreler eşleşmiyor.";
                }
            }
            
            if (!$profile_update_error) {
                $result = update_user_profile($user_id, $update_data);
                if ($result['success']) {
                    $profile_update_success = $result['message'];
                    // Session'ı güncelle
                    $_SESSION['user_name'] = $update_data['first_name'] . ' ' . $update_data['last_name'];
                    $_SESSION['user_email'] = $update_data['email'];
                    $_SESSION['user_first_name'] = $update_data['first_name'];
                    $_SESSION['user_last_name'] = $update_data['last_name'];
                    $user_name = $_SESSION['user_name'];
                    $user_email = $_SESSION['user_email'];
                    log_security_event('profile_updated', ['user_id' => $user_id]);
                } else {
                    $profile_update_error = $result['message'];
                }
            }
        }
    }
}

// Profil bilgilerini getir
$user_profile = null;
if ($user_logged_in && $user_id) {
    $user_profile = get_user_profile($user_id);
}

$membership_status = ['status' => 'none'];
if ($selected_community && $user_logged_in && $user_profile) {
    $membership_status = get_membership_status($selected_community, $user_profile);
}

if (!isset($_SESSION['community_memberships'])) {
    $_SESSION['community_memberships'] = [];
}

if ($selected_community) {
    $membership_state_for_session = $membership_status['status'] ?? 'none';
    $_SESSION['community_memberships'][$selected_community] = $membership_state_for_session;
}

$is_member_of_selected = ($membership_status['status'] ?? '') === 'member';
$membership_pending_for_selected = ($membership_status['status'] ?? '') === 'pending';
$is_guest = !$user_logged_in;

if (!function_exists('render_membership_info_banner')) {
    function render_membership_info_banner($selected_community, $membership_pending, $current_view = 'overview', $extra_params = []) {
        $query_params = array_merge(['community' => $selected_community, 'view' => $current_view], $extra_params);
        $action = '?' . http_build_query($query_params);
        ob_start();
        ?>
        <div class="mb-6 rounded-xl border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/70 dark:bg-indigo-500/10 p-4 sm:p-5 shadow-inner">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-indigo-900 dark:text-indigo-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/80 text-indigo-600 dark:text-indigo-200 flex items-center justify-center">
                        <i class="fas <?= $membership_pending ? 'fa-hourglass-half' : 'fa-user-plus' ?>"></i>
                    </div>
                    <div class="text-left">
                        <?php if ($membership_pending): ?>
                            <h3 class="text-sm font-semibold">Üyelik başvurunuz inceleniyor.</h3>
                            <p class="text-xs text-indigo-800/80 dark:text-indigo-200/80">Onaylandığında topluluğun tüm özelliklerine erişebileceksiniz.</p>
                        <?php else: ?>
                            <h3 class="text-sm font-semibold">Topluluğa katılarak daha fazla özelliğe erişebilirsiniz.</h3>
                            <p class="text-xs text-indigo-800/80 dark:text-indigo-200/80">Üyelik onayı sonrasında etkinlik katılımı ve bildirim gibi özellikler açılacak.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$membership_pending): ?>
                <form method="POST" action="<?= htmlspecialchars($action) ?>" class="flex-shrink-0">
                    <input type="hidden" name="action" value="join_community">
                    <?= csrf_token_field() ?>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        <i class="fas fa-user-plus"></i>
                        Üyelik Başvurusu Gönder
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// RSVP ve üyelik işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $selected_community) {
    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $rsvp_error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        log_security_event('csrf_failure', ['page' => 'public_action', 'action' => $_POST['action'] ?? 'unknown']);
    } else {
        $action = $_POST['action'];

        if ($action === 'submit_rsvp') {
        if (!$user_logged_in || !$user_profile) {
            $rsvp_error = "Katılım talebi için önce giriş yapmalısınız.";
        } else {
        // Event ID sanitization - Sadece pozitif integer
        $event_id = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($event_id === false) {
            $event_id = 0;
        }
            // RSVP status sanitization - Sadece izin verilen değerler
            $allowed_statuses = ['attending', 'not_attending', 'maybe'];
            $requested_status = sanitize_input($_POST['rsvp_status'] ?? 'attending', 'string');
            if (!in_array($requested_status, $allowed_statuses, true)) {
                $requested_status = 'attending';
            }

            $membership_status = get_membership_status($selected_community, $user_profile);
            if ($membership_status['status'] !== 'member') {
                $rsvp_error = "Etkinliğe katılmak için önce topluluğa üye olmalısınız.";
            } elseif ($event_id <= 0) {
                $rsvp_error = "Geçersiz etkinlik isteği.";
        } else {
                $result = upsert_event_rsvp($selected_community, $event_id, $user_profile, $requested_status);
                if ($result['success']) {
                    $rsvp_success = $result['message'];
                } else {
                    $rsvp_error = $result['message'];
                }
            }
        }
    } elseif ($action === 'join_community') {
        if (!$user_logged_in || !$user_profile) {
            $membership_error = "Üyelik başvurusu göndermek için giriş yapmalısınız.";
        } else {
            $result = submit_membership_request($selected_community, $user_profile);
            if ($result['success']) {
                $membership_success = $result['message'];
            } else {
                $membership_error = $result['message'];
            }

            if ($selected_community && $user_logged_in && $user_profile) {
                $membership_status = get_membership_status($selected_community, $user_profile);
                $is_member_of_selected = ($membership_status['status'] ?? '') === 'member';
                $membership_pending_for_selected = ($membership_status['status'] ?? '') === 'pending';
                $_SESSION['community_memberships'][$selected_community] = $membership_status['status'] ?? 'none';
            }
        }
    } elseif ($action === 'submit_survey_response') {
        if (!$user_logged_in || !$user_profile) {
            $survey_error = "Ankete oy vermek için önce giriş yapmalısınız.";
        } else {
            $membership_status = get_membership_status($selected_community, $user_profile);
            if ($membership_status['status'] !== 'member') {
                $survey_error = "Ankete oy vermek için önce topluluğa üye olmalısınız.";
            } else {
                $survey_id = (int)($_POST['survey_id'] ?? 0);
                $event_id = (int)($_POST['event_id'] ?? 0);
                $responses = $_POST['responses'] ?? [];

                if ($survey_id <= 0 || empty($responses)) {
                    $survey_error = "Geçersiz anket isteği.";
                } else {
                    $email = trim($user_profile['email'] ?? '');
                    $member_id = get_member_id_by_email($selected_community, $email);
                    
                    if (!$member_id) {
                        $survey_error = "Üyelik bilgileriniz bulunamadı.";
                    } else {
                        $result = submit_survey_response($selected_community, $survey_id, $member_id, $responses);
                        if ($result['success']) {
                            $survey_success = $result['message'];
                            // Sayfayı yenilemek için redirect
                            header("Location: ?community=" . urlencode($selected_community) . "&view=event_detail&event_id=" . $event_id);
                            exit;
                        } else {
                            $survey_error = $result['message'];
                        }
                    }
                }
            }
        }
        }
    }
}

function open_community_db($community_id) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return null;
    }
    
    $db_path = __DIR__ . '/../communities/' . $community_id . '/unipanel.sqlite';
    if (!file_exists($db_path)) {
        return null;
    }

    try {
        // Güvenli database bağlantısı
        $db = get_safe_db_connection($db_path, false);
        if (!$db) {
            return null;
        }
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

function ensure_event_rsvp_table_public(SQLite3 $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS event_rsvp (
                    id INTEGER PRIMARY KEY,
                    event_id INTEGER NOT NULL,
                    club_id INTEGER NOT NULL,
                    member_name TEXT NOT NULL,
                    member_email TEXT NOT NULL,
                    member_phone TEXT,
                    rsvp_status TEXT DEFAULT 'attending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(event_id, club_id, member_email)
    )");
}

function ensure_membership_requests_table(SQLite3 $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS membership_requests (
        id INTEGER PRIMARY KEY,
        club_id INTEGER NOT NULL,
        user_id INTEGER,
        full_name TEXT,
        email TEXT,
        phone TEXT,
        student_id TEXT,
        department TEXT,
        status TEXT DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        additional_data TEXT,
        UNIQUE(club_id, email)
    )");
}

function get_membership_status($community_id, $user_profile) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return ['status' => 'none'];
    }
    
    $db = open_community_db($community_id);
    if (!$db || empty($user_profile)) {
        return ['status' => 'none'];
    }

    $email = strtolower(trim($user_profile['email'] ?? $user_profile['email'] ?? ''));
    $student_id = trim($user_profile['student_id'] ?? '');

    try {
        $member_stmt = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(:email) OR (student_id != '' AND student_id = :student_id)) LIMIT 1");
        $member_stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $member_stmt->bindValue(':student_id', $student_id, SQLITE3_TEXT);
        $member_result = $member_stmt->execute();
        if ($member_result && $member_result->fetchArray(SQLITE3_ASSOC)) {
            $db->close();
            return ['status' => 'member'];
        }

        ensure_membership_requests_table($db);
        $request_stmt = $db->prepare("SELECT * FROM membership_requests WHERE club_id = 1 AND (user_id = :user_id OR LOWER(email) = LOWER(:email)) ORDER BY created_at DESC LIMIT 1");
        $request_stmt->bindValue(':user_id', $user_profile['id'] ?? 0, SQLITE3_INTEGER);
        $request_stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $request_result = $request_stmt->execute();
        $request = $request_result ? $request_result->fetchArray(SQLITE3_ASSOC) : null;
        if ($request) {
            $db->close();
            return ['status' => $request['status'] ?? 'pending', 'request' => $request];
        }

        $db->close();
    } catch (Exception $e) {
        if ($db) {
            $db->close();
        }
    }

    return ['status' => 'none'];
}

function submit_membership_request($community_id, $user_profile) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return ['success' => false, 'message' => 'Geçersiz topluluk ID'];
    }
    
    $db = open_community_db($community_id);
    if (!$db || empty($user_profile)) {
        return ['success' => false, 'message' => 'Topluluğun veritabanına erişilemedi.'];
    }

    try {
        ensure_membership_requests_table($db);

        $status = get_membership_status($community_id, $user_profile);
        if ($status['status'] === 'member') {
            $db->close();
            return ['success' => false, 'message' => 'Zaten topluluğun üyesisiniz.'];
        }
        if ($status['status'] === 'pending') {
            $db->close();
            return ['success' => false, 'message' => 'Üyelik başvurunuz zaten inceleniyor.'];
        }

        $full_name = trim(($user_profile['first_name'] ?? '') . ' ' . ($user_profile['last_name'] ?? ''));
        $email = trim($user_profile['email'] ?? '');
        $phone = trim($user_profile['phone_number'] ?? '');
        $student_id = trim($user_profile['student_id'] ?? '');
        $department = trim($user_profile['department'] ?? '');

        $stmt = $db->prepare("INSERT INTO membership_requests (club_id, user_id, full_name, email, phone, student_id, department, additional_data) VALUES (1, :user_id, :full_name, :email, :phone, :student_id, :department, :additional_data)");
        $stmt->bindValue(':user_id', $user_profile['id'] ?? null, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $stmt->bindValue(':student_id', $student_id, SQLITE3_TEXT);
        $stmt->bindValue(':department', $department, SQLITE3_TEXT);
        $stmt->bindValue(':additional_data', json_encode($user_profile, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();

        $db->close();
        return ['success' => true, 'message' => 'Üyelik başvurunuz alındı. Onaylandığında bilgilendirileceksiniz.'];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => 'Üyelik başvurusu kaydedilemedi: ' . $e->getMessage()];
    }
}

function get_user_rsvp_status($community_id, $event_id, $user_email) {
    $db = open_community_db($community_id);
    if (!$db) {
        return null;
    }

    try {
        ensure_event_rsvp_table_public($db);
        $stmt = $db->prepare("SELECT * FROM event_rsvp WHERE event_id = :event_id AND club_id = 1 AND LOWER(member_email) = LOWER(:email) LIMIT 1");
        $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
        $stmt->bindValue(':email', $user_email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        $db->close();
        return $row ?: null;
    } catch (Exception $e) {
        $db->close();
        return null;
    }
}

function upsert_event_rsvp($community_id, $event_id, $user_profile, $status = 'attending') {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return ['success' => false, 'message' => 'Geçersiz topluluk ID'];
    }
    
    // Event ID sanitization
    $event_id = filter_var($event_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($event_id === false) {
        return ['success' => false, 'message' => 'Geçersiz etkinlik ID'];
    }
    
    // Status sanitization
    $allowed_statuses = ['attending', 'not_attending', 'maybe'];
    $status = sanitize_input($status, 'string');
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'attending';
    }
    
    $db = open_community_db($community_id);
    if (!$db) {
        return ['success' => false, 'message' => 'Topluluk veritabanına bağlanırken hata oluştu.'];
    }

    try {
        ensure_event_rsvp_table_public($db);

        $full_name = trim(($user_profile['first_name'] ?? '') . ' ' . ($user_profile['last_name'] ?? ''));
        if (empty($full_name)) {
            $full_name = trim($user_profile['full_name'] ?? '');
        }
        $email = trim($user_profile['email'] ?? '');
        $phone = trim($user_profile['phone_number'] ?? '');

        $existing = get_user_rsvp_status($community_id, $event_id, $email);
                
                if ($existing) {
            $stmt = $db->prepare("UPDATE event_rsvp SET rsvp_status = :status, member_name = :name, member_phone = :phone, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':name', $full_name, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':id', $existing['id'], SQLITE3_INTEGER);
            $stmt->execute();
                } else {
            $stmt = $db->prepare("INSERT INTO event_rsvp (event_id, club_id, member_name, member_email, member_phone, rsvp_status) VALUES (:event_id, 1, :name, :email, :phone, :status)");
            $stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
            $stmt->bindValue(':name', $full_name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->execute();
        }

        $db->close();
        return ['success' => true, 'message' => $status === 'attending' ? 'Katılımınız kaydedildi!' : 'Katılamama tercihiniz güncellendi.'];
    } catch (Exception $e) {
        $db->close();
        return ['success' => false, 'message' => 'Katılım kaydedilemedi: ' . $e->getMessage()];
    }
}

function get_member_id_by_email($community_id, $email) {
    $db = open_community_db($community_id);
    if (!$db || empty($email)) {
        return null;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->bindValue(':email', trim($email), SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        $db->close();
        return $row ? (int)$row['id'] : null;
    } catch (Exception $e) {
        if ($db) {
            $db->close();
        }
        return null;
    }
}

function has_user_voted_in_survey($community_id, $survey_id, $member_id) {
    if (!$member_id) {
        return false;
    }

    $db = open_community_db($community_id);
    if (!$db) {
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = :survey_id AND member_id = :member_id LIMIT 1");
        $stmt->bindValue(':survey_id', $survey_id, SQLITE3_INTEGER);
        $stmt->bindValue(':member_id', $member_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        $db->close();
        return $row && (int)$row['count'] > 0;
    } catch (Exception $e) {
        if ($db) {
            $db->close();
        }
        return false;
    }
}

function submit_survey_response($community_id, $survey_id, $member_id, $responses) {
    $db = open_community_db($community_id);
    if (!$db || !$member_id || empty($responses)) {
        return ['success' => false, 'message' => 'Geçersiz istek.'];
    }

    try {
        // Kullanıcının daha önce oy verip vermediğini kontrol et
        if (has_user_voted_in_survey($community_id, $survey_id, $member_id)) {
            $db->close();
            return ['success' => false, 'message' => 'Bu ankete zaten oy verdiniz.'];
        }

        // Her soru için cevabı kaydet
        foreach ($responses as $question_id => $option_id) {
            if (empty($option_id)) {
                continue;
            }

            $stmt = $db->prepare("INSERT INTO survey_responses (survey_id, question_id, member_id, option_id, submitted_at) VALUES (:survey_id, :question_id, :member_id, :option_id, datetime('now'))");
            $stmt->bindValue(':survey_id', $survey_id, SQLITE3_INTEGER);
            $stmt->bindValue(':question_id', (int)$question_id, SQLITE3_INTEGER);
            $stmt->bindValue(':member_id', $member_id, SQLITE3_INTEGER);
            $stmt->bindValue(':option_id', (int)$option_id, SQLITE3_INTEGER);
            $stmt->execute();
        }

        $db->close();
        return ['success' => true, 'message' => 'Oyunuz başarıyla kaydedildi.'];
    } catch (Exception $e) {
        if ($db) {
            $db->close();
        }
        return ['success' => false, 'message' => 'Oylama kaydedilemedi: ' . $e->getMessage()];
    }
}

function get_event_survey_data(SQLite3 $db, $event_id) {
    try {
        $survey_stmt = $db->prepare("SELECT * FROM event_surveys WHERE event_id = :event_id AND club_id = 1 AND is_active = 1 LIMIT 1");
        $survey_stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
        $survey_result = $survey_stmt->execute();
        $survey = $survey_result ? $survey_result->fetchArray(SQLITE3_ASSOC) : null;
        if (!$survey) {
            return null;
        }

        $survey['questions'] = [];
        $questions_stmt = $db->prepare("SELECT * FROM survey_questions WHERE survey_id = :survey_id ORDER BY display_order ASC, id ASC");
        $questions_stmt->bindValue(':survey_id', $survey['id'], SQLITE3_INTEGER);
        $questions_result = $questions_stmt->execute();

        while ($question = $questions_result->fetchArray(SQLITE3_ASSOC)) {
            $question_data = $question;
            $question_data['options'] = [];
            $question_data['total_responses'] = 0;

            $options_stmt = $db->prepare("SELECT so.*, COUNT(sr.id) as response_count
                FROM survey_options so
                LEFT JOIN survey_responses sr ON sr.option_id = so.id
                    AND sr.question_id = so.question_id
                WHERE so.question_id = :question_id
                GROUP BY so.id
                ORDER BY so.display_order ASC, so.id ASC");
            $options_stmt->bindValue(':question_id', $question['id'], SQLITE3_INTEGER);
            $options_result = $options_stmt->execute();

            while ($option = $options_result->fetchArray(SQLITE3_ASSOC)) {
                $option['response_count'] = (int)($option['response_count'] ?? 0);
                $question_data['total_responses'] += $option['response_count'];
                $question_data['options'][] = $option;
            }

            $survey['questions'][] = $question_data;
        }

        return $survey;
    } catch (Exception $e) {
        return null;
    }
}

function get_event_detail_data($community_id, $event_id) {
    // Community ID sanitization - Path traversal koruması
    $community_id = sanitizeCommunityId($community_id);
    if (!$community_id) {
        return null;
    }
    
    // Event ID sanitization
    $event_id = filter_var($event_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($event_id === false) {
        return null;
    }
    
    $db = open_community_db($community_id);
    if (!$db) {
        return null;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = :id AND club_id = 1 LIMIT 1");
        $stmt->bindValue(':id', $event_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $event = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;

        if (!$event) {
            $db->close();
            return null;
        }

        $event['tags_list'] = [];
        if (!empty($event['tags'])) {
            $event['tags_list'] = array_filter(array_map('trim', explode(',', $event['tags'])));
        }

        $attendance_stmt = $db->prepare("SELECT rsvp_status, COUNT(*) as total FROM event_rsvp WHERE event_id = :event_id AND club_id = 1 GROUP BY rsvp_status");
        $attendance_stmt->bindValue(':event_id', $event_id, SQLITE3_INTEGER);
        $attendance_result = $attendance_stmt->execute();
        $attendance = ['attending' => 0, 'not_attending' => 0];
        while ($row = $attendance_result->fetchArray(SQLITE3_ASSOC)) {
            $attendance[$row['rsvp_status']] = (int)$row['total'];
        }
        $event['attendance'] = $attendance;

        $event['survey'] = get_event_survey_data($db, $event_id);

        $db->close();
        return $event;
    } catch (Exception $e) {
        $db->close();
        return null;
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - Topluluk Portalı</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?php include __DIR__ . '/../templates/partials/tailwind_cdn_loader.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --accent-color: #ec4899;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --bg-dark: #0f172a;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-glow: 0 0 0 1px rgba(99, 102, 241, 0.1), 0 4px 12px rgba(99, 102, 241, 0.15);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-accent: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;
            --spacing-2xl: 4rem;
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.625rem;
            --radius-xl: 0.75rem;
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            font-weight: 400;
            color: var(--text-primary);
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: var(--bg-secondary);
            padding-bottom: 80px;
            overflow-x: hidden;
            font-feature-settings: 'kern' 1, 'liga' 1, 'calt' 1;
            text-rendering: optimizeLegibility;
        }
        
        @supports (-webkit-touch-callout: none) {
            body {
                padding-bottom: calc(80px + env(safe-area-inset-bottom));
            }
        }
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .card-modern {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
            border-radius: var(--radius-md);
        }
        .card-modern:active {
            transform: scale(0.98);
            transition: transform var(--transition-fast);
        }
        @media (hover: hover) {
            .card-modern:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
                border-color: var(--primary-color);
            }
        }
        .btn-primary {
            background: var(--primary-color);
            transition: all var(--transition-base);
            font-weight: 600;
            letter-spacing: -0.01em;
            border-radius: var(--radius-md);
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }
        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
            border-radius: var(--radius-md);
        }
        @media (hover: hover) {
            .stat-card:hover {
                transform: translateY(-1px);
                box-shadow: var(--shadow-md);
                border-color: var(--primary-color);
            }
        }
        .nav-link {
            position: relative;
            transition: all var(--transition-base);
            font-weight: 500;
            letter-spacing: -0.01em;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            border-radius: 2px;
            transition: width var(--transition-base);
        }
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }
        .nav-link.active {
            color: var(--text-primary);
            font-weight: 600;
            background: var(--bg-tertiary);
        }
        /* Bottom Navigation Bar (Mobile) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            z-index: 100;
            padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
        }
        .bottom-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 8px 4px;
            color: var(--text-light);
            text-decoration: none;
            transition: all var(--transition-base);
            font-size: 10px;
            font-weight: 500;
            -webkit-tap-highlight-color: transparent;
            border-radius: var(--radius-sm);
            position: relative;
        }
        .bottom-nav-item:active {
            transform: scale(0.95);
            background: rgba(99, 102, 241, 0.05);
        }
        .bottom-nav-item.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        .bottom-nav-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 0 0 2px 2px;
        }
        .bottom-nav-item i {
            font-size: 20px;
            transition: all var(--transition-base);
        }
        .bottom-nav-item.active i {
            transform: scale(1.15);
        }
        /* Native-like touch targets */
        button, a, input, select, textarea {
            min-height: 44px;
            min-width: 44px;
        }
        /* Smooth scrolling - already in html selector above */
        /* iOS safe area support */
        @supports (padding: max(0px)) {
            .bottom-nav {
                padding-bottom: max(8px, env(safe-area-inset-bottom));
            }
        }
        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            animation: slideInRight 0.3s ease-out, fadeOut 0.3s ease-in 2.7s forwards;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }
        .toast.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .toast.info {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(400px);
            }
        }
        /* Loading Spinner */
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Password Strength Indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        .password-strength.weak {
            background: linear-gradient(90deg, #ef4444 0%, #fca5a5 100%);
            width: 33%;
        }
        .password-strength.medium {
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
            width: 66%;
        }
        .password-strength.strong {
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
            width: 100%;
        }
        /* Form Input Focus Animation */
        .form-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        /* Modal Animation */
        .modal-content {
            animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        /* Success Check Animation */
        .success-check {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .success-check i {
            color: white;
            font-size: 28px;
            animation: checkMark 0.5s cubic-bezier(0.4, 0, 0.2, 1) 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        @keyframes checkMark {
            from {
                transform: scale(0) rotate(-45deg);
            }
            to {
                transform: scale(1) rotate(0deg);
            }
        }
        /* Input Icon */
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.2s;
        }
        .input-wrapper:focus-within .input-icon {
            color: #6366f1;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input {
            padding-left: 44px;
        }
        /* Float Animation */
        @keyframes float {
            0%, 100% { 
                transform: translate(0, 0) rotate(0deg); 
            }
            33% { 
                transform: translate(30px, -30px) rotate(120deg); 
            }
            66% { 
                transform: translate(-20px, 20px) rotate(240deg); 
            }
        }
        /* Fade In Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        .market-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 1.75rem;
            box-shadow: var(--shadow-lg);
        }
        .market-chip {
            border-radius: 999px;
            border: 1px solid var(--border-color);
            padding: 0.45rem 1.1rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--bg-primary);
            transition: all var(--transition-base);
            cursor: pointer;
            white-space: nowrap;
        }
        .market-chip:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .market-chip.active {
            background: var(--primary-color);
            color: #fff;
            border-color: transparent;
            box-shadow: var(--shadow-md);
        }
        .market-card {
            border: 1px solid var(--border-color);
            border-radius: 1.5rem;
            background: var(--bg-primary);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all var(--transition-base);
        }
        .market-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        .market-image {
            border-radius: 1.25rem;
            overflow: hidden;
            position: relative;
            background: var(--bg-tertiary);
        }
        .market-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .market-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.92);
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }
        .market-empty {
            border: 1px dashed var(--border-color);
            border-radius: 1.5rem;
            background: var(--bg-secondary);
        }
        .market-modal-overlay {
            background: rgba(15, 23, 42, 0.75);
        }
    </style>
    <?php
        $market_payload = json_encode(
            [
                'products' => $market_products,
                'total_products' => $market_total_products,
                'community_count' => $market_total_communities
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        $market_categories_payload = json_encode(
            $market_categories,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
    ?>
    <script>
        window.MARKET_DATA = <?= $market_payload ?: 'null' ?>;
        window.MARKET_CATEGORIES = <?= $market_categories_payload ?: '[]' ?>;
        window.MARKET_PRODUCT_MAP = {};
        if (window.MARKET_DATA && Array.isArray(window.MARKET_DATA.products)) {
            window.MARKET_DATA.products.forEach(function(product) {
                if (product && product.key) {
                    window.MARKET_PRODUCT_MAP[product.key] = product;
                }
            });
        }
    </script>
</head>
<body class="min-h-screen antialiased" style="background: var(--bg-secondary);">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 safe-area-top" style="background: var(--bg-primary); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-18">
                <!-- Logo ve Başlık -->
                <div class="flex items-center gap-4 sm:gap-6 flex-1">
                    <a href="?" class="flex items-center gap-2 sm:gap-3 group flex-shrink-0 transition-all" style="letter-spacing: -0.02em;">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 flex items-center justify-center transition-all group-hover:scale-105 group-active:scale-95">
                            <i class="fas fa-graduation-cap text-lg sm:text-xl" style="color: var(--primary-color);"></i>
                        </div>
                        <div>
                            <div class="text-lg sm:text-xl lg:text-2xl font-extrabold leading-none transition-colors" style="color: var(--text-primary);">
                                Four Kampüs
                            </div>
                            <p class="text-xs font-normal hidden sm:block mt-0.5" style="color: var(--text-secondary);">Topluluk Portalı</p>
                        </div>
                    </a>
                    
                    <!-- Navigation Desktop -->
                    <?php if ($selected_community && $community_data): ?>
                    <nav class="hidden lg:flex items-center gap-2 ml-8">
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=overview" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $current_view === 'overview' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Genel Bakış
                        </a>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=events" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $current_view === 'events' || $current_view === 'event_detail' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Etkinlikler
                        </a>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=members" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $current_view === 'members' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Üyeler
                        </a>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=campaigns" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $current_view === 'campaigns' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Kampanyalar
                        </a>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=board" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $current_view === 'board' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Yönetim
                        </a>
                    </nav>
                    <?php endif; ?>
                </div>
                
                <!-- Sağ Taraf: Kullanıcı Menüsü / Giriş Butonu -->
                <div class="flex items-center gap-3">
                    <?php if ($selected_community && $community_data): ?>
                        <!-- Topluluk Sayfasında -->
                        <?php if ($user_logged_in): ?>
                            <!-- Giriş Yapmış Kullanıcı -->
                            <div class="hidden lg:flex items-center">
                                <!-- Bildirimler -->
                                <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=notifications" class="relative p-2 mr-2 text-gray-600 hover:text-indigo-600 transition-colors">
                                    <i class="fas fa-bell text-xl"></i>
                                    <?php if ($unread_notifications_count > 0): ?>
                                    <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"><?= $unread_notifications_count ?></span>
                                    <?php endif; ?>
                                </a>
                                <!-- Profil Kutusu -->
                                <div class="relative z-50">
                                    <button id="profile-btn" class="flex items-center space-x-2 p-1.5 pr-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-200 pointer-events-auto" style="background: var(--bg-primary); border: 1px solid var(--border-color);">
                                        <div class="w-9 h-9 rounded-md bg-white dark:bg-gray-800 flex items-center justify-center border border-gray-300 dark:border-gray-600">
                                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user_name) ?></span>
                                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <!-- Mobil: Bildirimler -->
                            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=notifications" class="lg:hidden relative p-2.5 mr-1 text-gray-600 hover:text-indigo-600 transition-colors">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unread_notifications_count > 0): ?>
                                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"><?= $unread_notifications_count ?></span>
                                <?php endif; ?>
                            </a>
                            <!-- Mobil: Kullanıcı Butonu -->
                            <div class="lg:hidden relative">
                                <button id="profile-btn-mobile" class="p-2.5 transition-colors" style="color: var(--primary-color); border-radius: var(--radius-sm);" onmouseover="this.style.background='var(--bg-tertiary)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-user-circle text-xl"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Giriş Yapmamış Kullanıcı -->
                            <div class="hidden lg:flex items-center">
                                <a href="login.php" class="btn-primary px-4 py-2 text-white text-sm font-medium active:scale-95 inline-flex items-center">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                                </a>
                            </div>
                            <!-- Mobil: Giriş Butonu -->
                            <div class="lg:hidden">
                                <a href="login.php" class="p-2.5 transition-colors inline-block" style="color: var(--primary-color); border-radius: var(--radius-sm);" onmouseover="this.style.background='var(--bg-tertiary)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-sign-in-alt text-xl"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Ana Sayfada (Topluluk Listesi) -->
                        <nav class="hidden lg:flex items-center gap-2 ml-8">
                            <a href="?section=communities" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $home_current_view === 'communities' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                                <i class="fas fa-users w-4 h-4 mr-2"></i>
                                Topluluklar
                            </a>
                            <a href="?section=events" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $home_current_view === 'events' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                                <i class="fas fa-calendar-alt w-4 h-4 mr-2"></i>
                                Etkinlikler
                            </a>
                            <a href="?section=market" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $home_current_view === 'market' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                                <i class="fas fa-store w-4 h-4 mr-2"></i>
                                Market
                            </a>
                            <a href="?section=campaigns" class="group flex items-center px-4 py-2.5 text-sm font-medium transition-all duration-200 rounded-lg <?= $home_current_view === 'campaigns' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                                <i class="fas fa-bullhorn w-4 h-4 mr-2"></i>
                                Kampanyalar
                            </a>
                        </nav>
                        <?php if ($user_logged_in): ?>
                            <!-- Giriş Yapmış Kullanıcı -->
                            <div class="hidden lg:flex items-center">
                                <!-- Bildirimler -->
                                <a href="?view=notifications" class="relative p-2 mr-2 text-gray-600 hover:text-indigo-600 transition-colors">
                                    <i class="fas fa-bell text-xl"></i>
                                    <?php if ($unread_notifications_count > 0): ?>
                                    <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"><?= $unread_notifications_count ?></span>
                                    <?php endif; ?>
                                </a>
                                <!-- Profil Kutusu -->
                                <div class="relative z-50">
                                    <button id="profile-btn" class="flex items-center space-x-2 p-1.5 pr-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-200 pointer-events-auto" style="background: var(--bg-primary); border: 1px solid var(--border-color);">
                                        <div class="w-9 h-9 rounded-md bg-white dark:bg-gray-800 flex items-center justify-center border border-gray-300 dark:border-gray-600">
                                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);"><?= htmlspecialchars($user_name ?? 'Kullanıcı') ?></span>
                                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <!-- Mobil: Bildirimler -->
                            <a href="?view=notifications" class="lg:hidden relative p-2.5 mr-1 text-gray-600 hover:text-indigo-600 transition-colors">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unread_notifications_count > 0): ?>
                                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"><?= $unread_notifications_count ?></span>
                                <?php endif; ?>
                            </a>
                            <!-- Mobil: Kullanıcı Butonu -->
                            <div class="lg:hidden relative">
                                <button id="profile-btn-mobile" class="p-2.5 transition-colors" style="color: var(--primary-color); border-radius: var(--radius-sm);" onmouseover="this.style.background='var(--bg-tertiary)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-user-circle text-xl"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Giriş Yapmamış Kullanıcı -->
                            <div class="hidden lg:flex items-center">
                                <a href="login.php" class="btn-primary px-4 py-2 text-white text-sm font-medium active:scale-95 inline-flex items-center">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                                </a>
                            </div>
                            <!-- Mobil: Giriş Butonu -->
                            <div class="lg:hidden">
                                <a href="login.php" class="p-2.5 transition-colors inline-block" style="color: var(--primary-color); border-radius: var(--radius-sm);" onmouseover="this.style.background='var(--bg-tertiary)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-sign-in-alt text-xl"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-12 pb-20 lg:pb-12">
        
        <!-- Mesajlar -->
        <?php if (isset($rsvp_success)): ?>
            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 text-green-800 dark:text-green-300 px-5 py-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($rsvp_success) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($rsvp_error)): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-300 px-5 py-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($rsvp_error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($membership_success)): ?>
            <div class="mb-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 text-indigo-800 dark:text-indigo-300 px-5 py-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($membership_success) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($membership_error)): ?>
            <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 px-5 py-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($membership_error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($current_view === 'profile'): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Profil bilgilerinizi görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 bg-gray-50 hover:bg-gray-100 text-gray-900 rounded-xl font-semibold transition-all active:scale-95 border border-gray-200 inline-block">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif (!$user_profile): ?>
                <!-- Profil Bulunamadı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-user-slash text-gray-400 text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Profil Bulunamadı</h2>
                        <p class="text-gray-600 mb-8">Profil bilgileriniz yüklenemedi.</p>
                        <a href="?" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition-all active:scale-95 inline-block">
                            Ana Sayfaya Dön
                        </a>
                    </div>
                </div>
            <?php else: ?>
            <!-- Modern Profil Sayfası -->
            <div class="max-w-6xl mx-auto space-y-6">
                <!-- Mesajlar -->
                <?php if ($profile_update_success): ?>
                    <div class="mb-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 text-green-800 dark:text-green-300 px-5 py-4 rounded-xl shadow-lg animate-fade-in">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="font-semibold"><?= htmlspecialchars($profile_update_success) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($profile_update_error): ?>
                    <div class="mb-4 bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-l-4 border-red-500 text-red-800 dark:text-red-300 px-5 py-4 rounded-xl shadow-lg animate-fade-in">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                            <span class="font-semibold"><?= htmlspecialchars($profile_update_error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Profil Header -->
                <div class="relative overflow-hidden rounded-3xl p-8 sm:p-12 mb-6 shadow-xl" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <!-- Background Shapes -->
                    <div class="absolute inset-0 overflow-hidden">
                        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white rounded-full opacity-10" style="animation: float 12s infinite;"></div>
                        <div class="absolute -bottom-40 -left-40 w-60 h-60 bg-white rounded-full opacity-10" style="animation: float 12s infinite; animation-delay: 3s;"></div>
                        <div class="absolute top-1/2 right-1/4 w-40 h-40 bg-white rounded-full opacity-10" style="animation: float 12s infinite; animation-delay: 6s;"></div>
                    </div>
                    
                    <!-- Background Overlay -->
                    <div class="absolute inset-0" style="background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%); pointer-events: none;"></div>
                    
                    <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-end gap-6">
                        <!-- Avatar -->
                        <div class="relative">
                            <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-white/20 backdrop-blur-sm border-4 border-white/30 flex items-center justify-center shadow-2xl">
                                <i class="fas fa-user text-white text-4xl sm:text-5xl"></i>
                    </div>
                            <button class="absolute bottom-0 right-0 w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                                <i class="fas fa-camera text-indigo-600 text-sm"></i>
                            </button>
                    </div>
                        
                        <!-- Kullanıcı Bilgileri -->
                        <div class="flex-1 text-center sm:text-left">
                            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">
                                <?= htmlspecialchars(($user_profile['first_name'] ?? '') . ' ' . ($user_profile['last_name'] ?? '')) ?>
                            </h1>
                            <p class="text-white/80 text-sm sm:text-base mb-3">
                                <?= htmlspecialchars($user_profile['email'] ?? '') ?>
                            </p>
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-4 text-white/90 text-xs sm:text-sm">
                                <?php if ($user_profile['university']): ?>
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-university"></i>
                                        <span><?= htmlspecialchars($user_profile['university']) ?></span>
                    </div>
                                <?php endif; ?>
                                <?php if ($user_profile['department']): ?>
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span><?= htmlspecialchars($user_profile['department']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profil Formu -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl overflow-hidden border border-gray-100 dark:border-gray-700">
                    <form method="POST" action="?view=profile" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <input type="hidden" name="action" value="update_profile">
                        <?= csrf_token_field() ?>
                        
                        <!-- Kişisel Bilgiler -->
                        <div class="p-6 sm:p-8 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors duration-200">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-user text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Kişisel Bilgiler</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Adınızı ve soyadınızı güncelleyin</p>
                                </div>
                            </div>
                            
                            <div class="space-y-5">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Ad</label>
                                        <input type="text" name="first_name" required 
                                               value="<?= htmlspecialchars($user_profile['first_name'] ?? '') ?>"
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                    </div>
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Soyad</label>
                                        <input type="text" name="last_name" required 
                                               value="<?= htmlspecialchars($user_profile['last_name'] ?? '') ?>"
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- İletişim Bilgileri -->
                        <div class="p-6 sm:p-8 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors duration-200">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-envelope text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">İletişim</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Email ve telefon bilgileriniz</p>
                                </div>
                            </div>
                            
                            <div class="space-y-5">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Email Adresi</label>
                                    <input type="email" name="email" required 
                                           value="<?= htmlspecialchars($user_profile['email'] ?? '') ?>"
                                           class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Telefon Numarası</label>
                                    <input type="tel" name="phone_number" 
                                           value="<?= htmlspecialchars($user_profile['phone_number'] ?? '') ?>"
                                           class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm"
                                           placeholder="05XX XXX XX XX">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Üniversite Bilgileri -->
                        <div class="p-6 sm:p-8 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors duration-200">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-graduation-cap text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Üniversite</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Eğitim bilgileriniz</p>
                                </div>
                            </div>
                            
                            <div class="space-y-5">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Okul Numarası</label>
                                        <input type="text" name="student_id" 
                                               value="<?= htmlspecialchars($user_profile['student_id'] ?? '') ?>"
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                    </div>
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Üniversite</label>
                                        <input type="text" name="university" 
                                               value="<?= htmlspecialchars($user_profile['university'] ?? '') ?>"
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Bölüm</label>
                                    <input type="text" name="department" 
                                           value="<?= htmlspecialchars($user_profile['department'] ?? '') ?>"
                                           class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Güvenlik -->
                        <div class="p-6 sm:p-8 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors duration-200">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-lock text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Güvenlik</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Şifrenizi güvenli bir şekilde değiştirin</p>
                                </div>
                            </div>
                            
                            <div class="space-y-5">
                                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                                    <p class="text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Şifrenizi değiştirmek istemiyorsanız bu alanları boş bırakın.</span>
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Yeni Şifre</label>
                                        <input type="password" name="password" 
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm"
                                               placeholder="En az 6 karakter">
                                    </div>
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2.5">Yeni Şifre Tekrar</label>
                                        <input type="password" name="password_confirm" 
                                               class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-gray-900 dark:text-white placeholder-gray-400 hover:border-gray-300 dark:hover:border-gray-500 group-hover:shadow-sm"
                                               placeholder="Şifrenizi tekrar girin">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hesap Bilgileri -->
                        <div class="p-6 sm:p-8 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900/50 dark:to-gray-800/50">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-info-circle text-white text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Hesap Bilgileri</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Hesap geçmişiniz ve istatistikler</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow duration-200 border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg flex-shrink-0">
                                            <i class="fas fa-calendar text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Kayıt Tarihi</p>
                                            <p class="text-base font-bold text-gray-900 dark:text-white">
                                                <?= $user_profile['created_at'] ? date('d.m.Y', strtotime($user_profile['created_at'])) : 'Bilinmiyor' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow duration-200 border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center shadow-lg flex-shrink-0">
                                            <i class="fas fa-clock text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Son Giriş</p>
                                            <p class="text-base font-bold text-gray-900 dark:text-white">
                                                <?= $user_profile['last_login'] ? date('d.m.Y H:i', strtotime($user_profile['last_login'])) : 'Hiç giriş yapılmamış' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Butonlar -->
                        <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
                            <button type="submit" class="flex-1 sm:flex-none px-8 py-4 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 hover:from-indigo-700 hover:via-purple-700 hover:to-pink-700 text-white font-bold rounded-xl transition-all duration-200 active:scale-95 shadow-xl shadow-indigo-500/30 hover:shadow-2xl hover:shadow-indigo-500/40 flex items-center justify-center gap-2 group">
                                <i class="fas fa-save group-hover:scale-110 transition-transform"></i>
                                <span>Değişiklikleri Kaydet</span>
                            </button>
                            <a href="?" class="flex-1 sm:flex-none px-8 py-4 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-semibold rounded-xl transition-all duration-200 active:scale-95 text-center flex items-center justify-center gap-2 border-2 border-transparent hover:border-gray-300 dark:hover:border-gray-500">
                                <i class="fas fa-times"></i>
                                <span>İptal</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        
        <?php elseif (!$selected_community || !$community_data): ?>
            <!-- Topluluk Listesi (Ana Sayfa) - Herkese Açık -->
            <div class="space-y-6 sm:space-y-8 lg:space-y-12">
                <?php
                $home_nav_items = [
                ];
                ?>
                <div class="flex flex-wrap gap-2 sm:gap-3 py-2">
                    <?php foreach ($home_nav_items as $key => $item): ?>
                        <a href="?section=<?= $key ?>" class="inline-flex items-center px-4 py-2.5 rounded-2xl border text-sm font-semibold transition-all duration-200 <?= $home_current_view === $key ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-500/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:text-gray-900' ?>">
                            <i class="<?= $item['icon'] ?> text-sm mr-2"></i>
                            <?= $item['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($home_current_view === 'events'): ?>
                <section id="events" class="market-section px-5 sm:px-8 py-6 sm:py-8 lg:py-10 mb-8 sm:mb-12 scroll-mt-24">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-gray-400 mb-2">UniEvents</p>
                            <h2 class="text-3xl sm:text-4xl font-black text-gray-900 mb-3 leading-tight">Topluluk Etkinlikleri</h2>
                            <p class="text-sm sm:text-base text-gray-500 max-w-2xl">Tüm toplulukların yaklaşan etkinlikleri tek listede. Hemen keşfedin, katılımınızı planlayın.</p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                            <div class="p-4 rounded-2xl bg-blue-50 border border-blue-100 text-center">
                                <p class="text-3xl font-extrabold text-blue-600 leading-tight"><?= number_format($public_events_total) ?></p>
                                <p class="text-xs font-semibold text-blue-900/70 uppercase tracking-wide">Etkinlik</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-sky-50 border border-sky-100 text-center">
                                <p class="text-3xl font-extrabold text-sky-600 leading-tight"><?= number_format($public_events_community_count) ?></p>
                                <p class="text-xs font-semibold text-sky-900/70 uppercase tracking-wide">Topluluk</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-cyan-50 border border-cyan-100 text-center hidden sm:block">
                                <p class="text-3xl font-extrabold text-cyan-600 leading-tight"><?= number_format(max($public_events_total, 0)) ?></p>
                                <p class="text-xs font-semibold text-cyan-900/70 uppercase tracking-wide">Planlanıyor</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($public_events)): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mt-6">
                        <?php foreach ($public_events as $event): ?>
                        <div class="market-card">
                            <div class="p-5 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div class="text-left">
                                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Tarih</p>
                                        <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($event['date_label']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Saat</p>
                                        <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($event['time_label'] ?: 'TBA') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 p-5 space-y-3 flex flex-col">
                                <div class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold w-max"><?= htmlspecialchars($event['community_name']) ?></div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 leading-tight line-clamp-2"><?= htmlspecialchars($event['title']) ?></h3>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($event['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <i class="fas fa-map-marker-alt mr-1 text-indigo-500"></i>
                                    <?= htmlspecialchars($event['location']) ?>
                                </div>
                                <div class="mt-auto flex gap-2">
                                    <a href="?community=<?= htmlspecialchars($event['community_slug']) ?>&view=events" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-all">
                                        Topluluk
                                    </a>
                                    <a href="?community=<?= htmlspecialchars($event['community_slug']) ?>&view=events" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white text-sm font-semibold shadow-sm hover:shadow-lg transition-all">
                                        Detay
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="market-empty mt-6 p-8 text-center">
                        <div class="max-w-md mx-auto">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white flex items-center justify-center text-gray-400 shadow-inner">
                                <i class="fas fa-calendar-xmark text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Yaklaşan etkinlik bulunamadı</h3>
                            <p class="text-sm text-gray-500">Topluluklarımız yeni etkinliklerini planlıyor. Lütfen daha sonra tekrar kontrol edin.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                
                <?php if ($home_current_view === 'market'): ?>
                <section id="market" class="market-section px-5 sm:px-8 py-6 sm:py-8 lg:py-10 mb-8 sm:mb-12">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-gray-400 mb-2">UniMarket</p>
                            <h2 class="text-3xl sm:text-4xl font-black text-gray-900 mb-3 leading-tight">Topluluk Marketi</h2>
                            <p class="text-sm sm:text-base text-gray-500 max-w-2xl">Swift uygulamasındaki modern market deneyimini artık web üzerinde de keşfedebilirsiniz. Tüm toplulukların ürünleri tek bir yerde.</p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                            <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-100 text-center">
                                <p class="text-3xl font-extrabold text-indigo-600 leading-tight"><?= number_format($market_total_products) ?></p>
                                <p class="text-xs font-semibold text-indigo-900/70 uppercase tracking-wide">Ürün</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-violet-50 border border-violet-100 text-center">
                                <p class="text-3xl font-extrabold text-violet-600 leading-tight"><?= number_format($market_total_communities) ?></p>
                                <p class="text-xs font-semibold text-violet-900/70 uppercase tracking-wide">Topluluk</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-purple-50 border border-purple-100 text-center hidden sm:block">
                                <p class="text-3xl font-extrabold text-purple-600 leading-tight"><?= number_format(count($market_categories)) ?></p>
                                <p class="text-xs font-semibold text-purple-900/70 uppercase tracking-wide">Kategori</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-4 flex items-center text-gray-400 pointer-events-none">
                                <i class="fas fa-search"></i>
                            </span>
                            <input 
                                type="text" 
                                id="market-search" 
                                placeholder="Ürün, kategori veya topluluk ara..."
                                class="w-full pl-12 pr-4 py-3 rounded-2xl border border-gray-200 text-sm sm:text-base focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 outline-none transition-all"
                                style="background: var(--bg-secondary);"
                            >
                            <span class="absolute inset-y-0 right-4 flex items-center text-xs font-semibold text-gray-400" id="market-result-count-label">
                                Toplam <span id="market-result-count" class="mx-1 text-indigo-600 font-bold"><?= count($market_products) ?></span>ürün
                            </span>
                        </div>
                        <button type="button" id="market-cart-button" class="flex items-center justify-center gap-3 px-5 py-3 rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-700 font-semibold text-sm sm:text-base hover:bg-indigo-100 transition-all shadow-sm relative">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-cart-shopping text-lg"></i>
                                Sepet
                            </span>
                            <span id="market-cart-count" class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-white text-indigo-600 border border-indigo-100">0</span>
                        </button>
                    </div>
                    
                    <?php if (!empty($market_categories)): ?>
                    <div class="mt-5 overflow-x-auto">
                        <div class="flex items-center gap-3 min-w-full pb-1" id="market-category-wrapper">
                            <button type="button" class="market-chip active" data-market-category-btn data-category="">Tümü</button>
                            <?php foreach ($market_categories as $category): ?>
                                <button type="button" class="market-chip" data-market-category-btn data-category="<?= htmlspecialchars(market_normalize_text($category)) ?>">
                                    <?= htmlspecialchars($category) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div id="market-empty" class="market-empty mt-6 p-8 text-center <?= !empty($market_products) ? 'hidden' : '' ?>">
                        <div class="max-w-md mx-auto">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white flex items-center justify-center text-gray-400 shadow-inner">
                                <i class="fas fa-box-open text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Ürün bulunamadı</h3>
                            <p class="text-sm text-gray-500">Aramanızı değiştirin veya tüm kategorileri görüntüleyin.</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($market_products)): ?>
                    <div id="market-products" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6 mt-6">
                        <?php foreach ($market_products as $product): 
                            $category_slug = htmlspecialchars($product['category_slug'] ?? market_normalize_text($product['category']));
                            $search_index = htmlspecialchars($product['search_index'] ?? '', ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="market-card" 
                             data-market-card 
                             data-product-key="<?= htmlspecialchars($product['key']) ?>"
                             data-category="<?= $category_slug ?>"
                             data-search="<?= $search_index ?>">
                            <div class="market-image aspect-[4/3]">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-sm text-gray-400 bg-gradient-to-br from-gray-100 to-gray-200">
                                        Görsel yakında
                                    </div>
                                <?php endif; ?>
                                <span class="market-badge"><?= htmlspecialchars($product['category']) ?></span>
                                <button type="button" class="absolute top-3 right-3 bg-white/90 text-gray-700 rounded-full p-2 shadow-md hover:text-indigo-600 transition-all" data-open-product="<?= htmlspecialchars($product['key']) ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="flex flex-col flex-1 p-5 gap-3">
                                <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    <span style="color: <?= htmlspecialchars($product['community_color']) ?>;"><?= htmlspecialchars($product['community_name']) ?></span>
                                    <span>Stok: <?= max(0, $product['stock']) ?></span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 leading-tight line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                                    <?php if (!empty($product['description'])): ?>
                                        <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-end justify-between gap-4 mt-auto">
                                    <div>
                                        <div class="text-[1.8rem] font-black text-gray-900 leading-none"><?= number_format($product['price'], 2, ',', '.') ?> ₺</div>
                                        <p class="text-xs text-gray-500 mt-1">Toplam: <?= number_format($product['total_price'], 2, ',', '.') ?> ₺</p>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <button type="button" data-add-cart="<?= htmlspecialchars($product['key']) ?>" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-all whitespace-nowrap">
                                            Sepete Ekle
                                        </button>
                                        <button type="button" data-open-product="<?= htmlspecialchars($product['key']) ?>" class="px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white text-sm font-semibold shadow-sm hover:shadow-lg transition-all whitespace-nowrap">
                                            İncele
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                
                <?php if ($home_current_view === 'campaigns'): ?>
                <section id="campaigns" class="market-section px-5 sm:px-8 py-6 sm:py-8 lg:py-10 mb-8 sm:mb-12 scroll-mt-24">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-gray-400 mb-2">UniDeals</p>
                            <h2 class="text-3xl sm:text-4xl font-black text-gray-900 mb-3 leading-tight">Topluluk Kampanyaları</h2>
                            <p class="text-sm sm:text-base text-gray-500 max-w-2xl">Toplulukların özel indirimleri ve iş birlikleri. Avantajlardan yararlanmak için kampanyaları inceleyin.</p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                            <div class="p-4 rounded-2xl bg-pink-50 border border-pink-100 text-center">
                                <p class="text-3xl font-extrabold text-pink-600 leading-tight"><?= number_format($public_campaigns_total) ?></p>
                                <p class="text-xs font-semibold text-pink-900/70 uppercase tracking-wide">Kampanya</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 text-center">
                                <p class="text-3xl font-extrabold text-rose-600 leading-tight"><?= number_format($public_campaigns_community_count) ?></p>
                                <p class="text-xs font-semibold text-rose-900/70 uppercase tracking-wide">Topluluk</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-orange-50 border border-orange-100 text-center hidden sm:block">
                                <p class="text-3xl font-extrabold text-orange-600 leading-tight"><?= number_format($public_campaigns_total) ?></p>
                                <p class="text-xs font-semibold text-orange-900/70 uppercase tracking-wide">Aktif</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($public_campaigns)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mt-6">
                        <?php foreach ($public_campaigns as $campaign): ?>
                        <div class="market-card <?= !empty($campaign['is_expired']) ? 'opacity-70' : '' ?>">
                            <div class="market-image aspect-[4/3]">
                                <?php if (!empty($campaign['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($campaign['image_url']) ?>" alt="<?= htmlspecialchars($campaign['title']) ?>">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-sm text-gray-400 bg-gradient-to-br from-gray-100 to-gray-200">
                                        Görsel yakında
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($campaign['discount_percentage'])): ?>
                                    <span class="market-badge">%<?= htmlspecialchars($campaign['discount_percentage']) ?> İndirim</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col flex-1 p-5 gap-3">
                                <div class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold w-max"><?= htmlspecialchars($campaign['community_name']) ?></div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 leading-tight line-clamp-2"><?= htmlspecialchars($campaign['title']) ?></h3>
                                    <?php if (!empty($campaign['offer_text'])): ?>
                                        <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($campaign['offer_text']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($campaign['partner_name'])): ?>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Partner: <?= htmlspecialchars($campaign['partner_name']) ?></p>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500 space-y-1">
                                    <?php if (!empty($campaign['start_date'])): ?>
                                        <p><i class="fas fa-play mr-1 text-green-500"></i><?= htmlspecialchars(date('d.m.Y', strtotime($campaign['start_date']))) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($campaign['end_date'])): ?>
                                        <p><i class="fas fa-flag-checkered mr-1 text-red-500"></i><?= htmlspecialchars(date('d.m.Y', strtotime($campaign['end_date']))) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-auto flex gap-2">
                                    <a href="?community=<?= htmlspecialchars($campaign['community_slug']) ?>&view=campaigns" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300 transition-all">
                                        Topluluk
                                    </a>
                                    <a href="?community=<?= htmlspecialchars($campaign['community_slug']) ?>&view=campaigns" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gradient-to-r from-pink-500 to-rose-500 text-white text-sm font-semibold shadow-sm hover:shadow-lg transition-all">
                                        İncele
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="market-empty mt-6 p-8 text-center">
                        <div class="max-w-md mx-auto">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white flex items-center justify-center text-gray-400 shadow-inner">
                                <i class="fas fa-bullhorn text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Kampanya bulunamadı</h3>
                            <p class="text-sm text-gray-500">Topluluk kampanyaları kısa süre sonra burada listelenecek.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                
                <?php if ($home_current_view === 'communities'): ?>
                <!-- Arama ve Sıralama -->
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 max-w-4xl mx-auto">
                    <!-- Arama Kutusu -->
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-search" style="color: var(--text-light);"></i>
                        </div>
                        <input 
                            type="text" 
                            id="search-input"
                            value="<?= htmlspecialchars($search_query) ?>"
                            placeholder="Topluluk ara..." 
                            class="w-full pl-11 pr-4 py-3 text-sm sm:text-base transition-all"
                            style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: var(--radius-md);"
                            onfocus="this.style.borderColor='var(--primary-color)'; this.style.boxShadow='0 0 0 3px rgba(99, 102, 241, 0.1)'; this.style.background='var(--bg-secondary)'"
                            onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'; this.style.background='var(--bg-primary)'"
                            onkeyup="handleSearch(event)"
                        >
                        <?php if ($search_query): ?>
                            <button onclick="clearSearch()" class="absolute inset-y-0 right-0 pr-4 flex items-center transition-colors" style="color: var(--text-light);" onmouseover="this.style.color='var(--text-secondary)'" onmouseout="this.style.color='var(--text-light)'">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sıralama -->
                    <div class="relative">
                        <select 
                            id="sort-select"
                            onchange="handleSort(event)"
                            class="w-full sm:w-auto pl-4 pr-10 py-3 text-sm sm:text-base appearance-none cursor-pointer transition-all"
                            style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: var(--radius-md);"
                            onfocus="this.style.borderColor='var(--primary-color)'; this.style.boxShadow='0 0 0 3px rgba(99, 102, 241, 0.1)'; this.style.background='var(--bg-secondary)'"
                            onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'; this.style.background='var(--bg-primary)'"
                        >
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>İsme Göre</option>
                            <option value="members" <?= $sort_by === 'members' ? 'selected' : '' ?>>Üye Sayısına Göre</option>
                            <option value="events" <?= $sort_by === 'events' ? 'selected' : '' ?>>Etkinlik Sayısına Göre</option>
                            <option value="campaigns" <?= $sort_by === 'campaigns' ? 'selected' : '' ?>>Kampanya Sayısına Göre</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Arama Sonuçları Bilgisi -->
                <?php if ($search_query): ?>
                    <div class="text-center text-sm font-medium" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span><?= count($all_communities) ?> topluluk bulundu</span>
                    </div>
                <?php endif; ?>
                
                <!-- Topluluk Listesi -->
                <?php if (empty($all_communities)): ?>
                    <div class="text-center py-12 sm:py-16">
                        <div class="card-modern p-8 sm:p-12 max-w-md mx-auto">
                            <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4" style="background: var(--bg-tertiary); border-radius: var(--radius-md);">
                                <i class="fas fa-search text-2xl" style="color: var(--text-light);"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Topluluk bulunamadı</h3>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);"><?= $search_query ? 'Arama kriterlerinize uygun topluluk bulunamadı.' : 'Henüz topluluk eklenmemiş.' ?></p>
                            <?php if ($search_query): ?>
                                <button onclick="clearSearch()" class="btn-primary px-4 py-2 text-white text-sm font-medium active:scale-95">
                                    Aramayı Temizle
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6">
                    <?php foreach ($all_communities as $community): ?>
                        <a href="?community=<?= htmlspecialchars($community['id']) ?>&view=overview" class="group block">
                            <div class="card-modern overflow-hidden h-full transition-all duration-300 active:scale-[0.98]" style="border: 1px solid var(--border-color);">
                                <!-- Header with Icon -->
                                <div class="relative p-5 sm:p-6" style="background: var(--primary-color);">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center mb-3 shadow-lg" style="background: rgba(255, 255, 255, 0.15); border-radius: var(--radius-sm); border: 1px solid rgba(255, 255, 255, 0.2);">
                                                <i class="fas fa-users text-white text-xl sm:text-2xl"></i>
                                            </div>
                                            <h3 class="text-lg sm:text-xl font-bold text-white leading-tight line-clamp-2 group-hover:text-white/90 transition-colors">
                                                <?= htmlspecialchars($community['name']) ?>
                                            </h3>
                                        </div>
                                        <div class="ml-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <i class="fas fa-arrow-right text-white/60 text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="p-5 sm:p-6 bg-white">
                                    <!-- Stats Grid -->
                                    <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-4">
                                        <div class="text-center">
                                            <div class="flex items-center justify-center mb-1.5">
                                                <i class="fas fa-user-friends text-sm sm:text-base mr-1.5" style="color: var(--primary-color);"></i>
                                                <span class="text-xl sm:text-2xl font-bold" style="color: var(--text-primary);"><?= $community['member_count'] ?></span>
                                            </div>
                                            <p class="text-[10px] sm:text-xs font-medium" style="color: var(--text-secondary);">Üye</p>
                                        </div>
                                        <div class="text-center">
                                            <div class="flex items-center justify-center mb-1.5">
                                                <i class="fas fa-calendar-alt text-sm sm:text-base mr-1.5" style="color: var(--primary-color);"></i>
                                                <span class="text-xl sm:text-2xl font-bold" style="color: var(--text-primary);"><?= $community['event_count'] ?></span>
                                            </div>
                                            <p class="text-[10px] sm:text-xs font-medium" style="color: var(--text-secondary);">Etkinlik</p>
                                        </div>
                                        <div class="text-center">
                                            <div class="flex items-center justify-center mb-1.5">
                                                <i class="fas fa-tags text-sm sm:text-base mr-1.5" style="color: var(--primary-color);"></i>
                                                <span class="text-xl sm:text-2xl font-bold" style="color: var(--text-primary);"><?= $community['campaign_count'] ?></span>
                                            </div>
                                            <p class="text-[10px] sm:text-xs font-medium" style="color: var(--text-secondary);">Kampanya</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="pt-4" style="border-top: 1px solid var(--border-color);">
                                        <div class="flex items-center justify-between text-sm font-semibold transition-colors" style="color: var(--primary-color);" onmouseover="this.style.color='var(--primary-dark)'" onmouseout="this.style.color='var(--primary-color)'">
                                            <span>Detayları görüntüle</span>
                                            <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($current_view === 'notifications'): ?>
            <?php if (!$user_logged_in): ?>
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Bildirimlerinizi görmek için lütfen giriş yapın.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php 
                $notifications = get_user_notifications($user_id);
                ?>
                <div class="max-w-3xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1">Bildirimler</h1>
                            <p class="text-gray-500">Son aktiviteler ve güncellemeler</p>
                        </div>
                        <?php if (!empty($notifications)): ?>
                        <button onclick="markAllNotificationsRead()" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline">
                            Tümünü Okundu İşaretle
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="card-modern rounded-3xl p-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-bell-slash text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Bildiriminiz Yok</h3>
                            <p class="text-gray-500">Şu anda görüntülenecek yeni bildiriminiz bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="card-modern p-4 sm:p-5 rounded-2xl transition-all hover:shadow-md <?= $notification['is_read'] ? 'opacity-75' : 'border-l-4 border-l-indigo-500' ?>">
                                    <div class="flex gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $notification['type'] === 'success' ? 'bg-green-100 text-green-600' : ($notification['type'] === 'warning' ? 'bg-yellow-100 text-yellow-600' : ($notification['type'] === 'error' ? 'bg-red-100 text-red-600' : 'bg-indigo-100 text-indigo-600')) ?>">
                                                <i class="fas <?= $notification['type'] === 'success' ? 'fa-check' : ($notification['type'] === 'warning' ? 'fa-exclamation' : ($notification['type'] === 'error' ? 'fa-times' : 'fa-info')) ?>"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-base font-semibold text-gray-900 leading-tight mb-1">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                </h4>
                                                <span class="text-xs text-gray-400 whitespace-nowrap">
                                                    <?= date('d.m H:i', strtotime($notification['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 leading-relaxed mb-2">
                                                <?= htmlspecialchars($notification['message']) ?>
                                            </p>
                                            <?php if (!empty($notification['link_url'])): ?>
                                                <a href="<?= htmlspecialchars($notification['link_url']) ?>" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                                    <?= htmlspecialchars($notification['link_text'] ?: 'Görüntüle') ?>
                                                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                function markAllNotificationsRead() {
                    fetch('api/notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_read',
                            mark_all: true
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        }
                    });
                }
                </script>
            <?php endif; ?>

        <?php elseif ($current_view === 'overview'): ?>
            <div class="space-y-0">
                <!-- Hero Section -->
                <div class="relative -mx-4 sm:-mx-6 lg:-mx-8 mb-8 sm:mb-12 overflow-hidden rounded-3xl shadow-2xl" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <!-- Background Shapes -->
                    <div class="absolute inset-0 overflow-hidden">
                        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white rounded-full opacity-10" style="animation: float 12s infinite;"></div>
                        <div class="absolute -bottom-40 -left-40 w-60 h-60 bg-white rounded-full opacity-10" style="animation: float 12s infinite; animation-delay: 3s;"></div>
                        <div class="absolute top-1/2 right-1/4 w-40 h-40 bg-white rounded-full opacity-10" style="animation: float 12s infinite; animation-delay: 6s;"></div>
                    </div>
                    
                    <!-- Background Overlay -->
                    <div class="absolute inset-0" style="background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%); pointer-events: none;"></div>
                    
                    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
                        <div class="max-w-4xl">
                            <!-- Topluluk İkonu ve İsmi -->
                            <div class="flex items-center gap-4 sm:gap-6 mb-6 sm:mb-8">
                                <div class="w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center bg-white/20 backdrop-blur-sm rounded-xl border-4 border-white/30 shadow-2xl">
                                    <i class="fas fa-users text-white text-2xl sm:text-3xl"></i>
                                </div>
                                <div class="flex-1">
                                    <h1 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black mb-2 sm:mb-3 text-white leading-tight" style="letter-spacing: -0.03em; text-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);">
                                        <?= htmlspecialchars($community_data['name']) ?>
                                    </h1>
                                    <?php if ($community_data['description']): ?>
                                        <p class="text-base sm:text-lg lg:text-xl text-white/90 leading-relaxed max-w-2xl" style="opacity: 0.95; letter-spacing: -0.01em;">
                                            <?= htmlspecialchars($community_data['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <!-- QR Kod Butonu -->
                                <button onclick="showQRCode('community', '<?= htmlspecialchars($selected_community) ?>', '<?= htmlspecialchars($community_data['name']) ?>')" class="w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center bg-white/20 backdrop-blur-sm rounded-xl border-2 border-white/30 shadow-lg hover:bg-white/30 hover:shadow-xl transition-all text-white hover:scale-110">
                                    <i class="fas fa-qrcode text-xl sm:text-2xl"></i>
                                </button>
                            </div>
                            
                            <?php if ($is_guest): ?>
                            <div class="inline-flex items-center gap-3 px-4 py-2.5 bg-white/15 backdrop-blur-sm border border-white/20 rounded-full text-white text-sm font-medium">
                                <i class="fas fa-info-circle text-white/80"></i>
                                <span>Detaylı içerikler için giriş yapın veya kayıt olun.</span>
                            </div>
                            <?php elseif (!$is_member_of_selected): ?>
                            <div class="inline-flex items-center gap-3 px-4 py-2.5 bg-white/15 backdrop-blur-sm border border-white/20 rounded-full text-white text-sm font-medium">
                                <?php if ($membership_pending_for_selected): ?>
                                    <i class="fas fa-hourglass-half text-white/80"></i>
                                    <span>Üyelik başvurunuz yönetici onayını bekliyor.</span>
                                <?php else: ?>
                                    <i class="fas fa-user-plus text-white/80"></i>
                                    <form method="POST" action="?community=<?= htmlspecialchars($selected_community) ?>&view=<?= htmlspecialchars($current_view) ?>" class="flex items-center gap-2">
                                        <input type="hidden" name="action" value="join_community">
                                        <?= csrf_token_field() ?>
                                        <button type="submit" class="text-white font-semibold underline decoration-white/60 decoration-2 underline-offset-4 hover:decoration-white">Topluluğa Katıl</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- İstatistikler - Hero İçinde -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 lg:gap-6 mt-8 sm:mt-10">
                                <div class="text-center bg-white/20 backdrop-blur-sm rounded-xl border border-white/30 shadow-lg hover:shadow-xl transition-all p-4 hover:bg-white/25">
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <i class="fas fa-user-friends text-white text-sm sm:text-base"></i>
                                        <div class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white"><?= count($community_data['members']) ?></div>
                                    </div>
                                    <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-white/90">Üye</div>
                                </div>
                                <div class="text-center bg-white/20 backdrop-blur-sm rounded-xl border border-white/30 shadow-lg hover:shadow-xl transition-all p-4 hover:bg-white/25">
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <i class="fas fa-calendar-alt text-white text-sm sm:text-base"></i>
                                        <div class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white"><?= count($community_data['events']) ?></div>
                                    </div>
                                    <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-white/90">Etkinlik</div>
                                </div>
                                <div class="text-center bg-white/20 backdrop-blur-sm rounded-xl border border-white/30 shadow-lg hover:shadow-xl transition-all p-4 hover:bg-white/25">
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <i class="fas fa-tags text-white text-sm sm:text-base"></i>
                                        <div class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white"><?= count($community_data['campaigns']) ?></div>
                                    </div>
                                    <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-white/90">Kampanya</div>
                                </div>
                                <div class="text-center bg-white/20 backdrop-blur-sm rounded-xl border border-white/30 shadow-lg hover:shadow-xl transition-all p-4 hover:bg-white/25">
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <i class="fas fa-user-shield text-white text-sm sm:text-base"></i>
                                        <div class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white"><?= count($community_data['board']) ?></div>
                                    </div>
                                    <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-white/90">Yönetim</div>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10 sm:space-y-12 lg:space-y-16">
                    <!-- Son Etkinlikler -->
                    <?php if (!empty($community_data['events'])): ?>
                    <div>
                        <div class="flex items-center justify-between mb-6 sm:mb-8">
                            <div>
                                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.02em;">Etkinlikler</h2>
                                <p class="text-sm sm:text-base" style="color: var(--text-secondary);">Yaklaşan ve geçmiş etkinlikler</p>
                            </div>
                        <?php if ($is_guest): ?>
                        <a href="login.php" class="hidden sm:flex items-center gap-2 px-4 py-2.5 font-medium text-white hover:text-white border border-transparent rounded-lg bg-indigo-600 hover:bg-indigo-700 transition-all duration-200 shadow-sm hover:shadow">
                            Giriş Yap
                            <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                        <?php else: ?>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=events" class="hidden sm:flex items-center gap-2 px-4 py-2.5 font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200 shadow-sm hover:shadow">
                                Tümünü gör
                                <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                        </div>
                    <?php if ($is_guest): ?>
                    <div class="card-modern p-8 sm:p-12 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 flex items-center justify-center rounded-2xl" style="background: var(--bg-tertiary);">
                            <i class="fas fa-lock text-2xl sm:text-3xl" style="color: var(--primary-color);"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Etkinlikleri görüntülemek için giriş yapın</h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">Topluluğun etkinlik takvimine erişmek için hesabınızla giriş yapmanız gerekiyor.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="btn-primary px-6 py-3 text-white text-sm font-semibold active:scale-95 inline-flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 text-sm font-semibold transition-all active:scale-95 inline-flex items-center justify-center" style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md);" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-tertiary)'">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6">
                            <?php foreach (array_slice($community_data['events'], 0, 6) as $event): ?>
                            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=event_detail&event_id=<?= $event['id'] ?>" class="card-modern overflow-hidden group active:scale-[0.98] block h-full">
                                <div class="relative h-48 sm:h-56 lg:h-64 overflow-hidden" style="background: var(--bg-tertiary);">
                                    <?php if (!empty($event['image_path'])): ?>
                                        <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($event['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($event['title']) ?>" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                             onerror="this.style.display='none';">
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800">
                                        <i class="fas fa-calendar-alt text-slate-400 dark:text-slate-500 text-5xl sm:text-6xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    <div class="absolute top-4 right-4 px-3 py-2" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: var(--radius-sm);">
                                        <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary);">
                                            <?= date('M', strtotime($event['date'])) ?>
                                        </div>
                                        <div class="text-lg sm:text-xl font-bold leading-none" style="color: var(--text-primary);">
                                            <?= date('d', strtotime($event['date'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <h3 class="text-lg sm:text-xl font-bold mb-3 line-clamp-2 text-slate-900 dark:text-white group-hover:text-slate-700 dark:group-hover:text-slate-200 transition-colors">
                                        <?= htmlspecialchars($event['title']) ?>
                                    </h3>
                                    <div class="flex items-center gap-4 text-xs sm:text-sm mb-4" style="color: var(--text-secondary);">
                                        <div class="flex items-center gap-1.5">
                                            <i class="far fa-clock"></i>
                                            <span><?= date('d.m.Y', strtotime($event['date'])) ?> • <?= htmlspecialchars($event['time']) ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
                                            <span>Detayları gör</span>
                                            <i class="fas fa-arrow-right ml-2 text-xs group-hover:translate-x-1 transition-transform"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="card-modern p-16 text-center">
                    <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4" style="background: var(--bg-tertiary); border-radius: var(--radius-md);">
                        <i class="fas fa-calendar-alt text-2xl" style="color: var(--text-light);"></i>
                    </div>
                    <p class="text-base font-semibold mb-1" style="color: var(--text-primary);">Henüz etkinlik eklenmemiş</p>
                    <p class="text-sm" style="color: var(--text-secondary);">Yakında yeni etkinlikler eklenecektir.</p>
                    </div>
                    <?php endif; ?>
                
                    <!-- Aktif Kampanyalar -->
                    <?php if (!empty($community_data['campaigns'])): ?>
                    <div>
                        <div class="flex items-center justify-between mb-6 sm:mb-8">
                            <div>
                                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.02em;">Kampanyalar</h2>
                                <p class="text-sm sm:text-base" style="color: var(--text-secondary);">Özel fırsatlar ve indirimler</p>
                            </div>
                        <?php if ($is_guest): ?>
                        <a href="login.php" class="hidden sm:flex items-center gap-2 px-4 py-2.5 font-medium text-white hover:text-white border border-transparent rounded-lg bg-indigo-600 hover:bg-indigo-700 transition-all duration-200 shadow-sm hover:shadow">
                            Giriş Yap
                            <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                        <?php else: ?>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=campaigns" class="hidden sm:flex items-center gap-2 px-4 py-2.5 font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200 shadow-sm hover:shadow">
                                Tümünü gör
                                <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                        </div>
                    <?php if ($is_guest): ?>
                    <div class="card-modern p-8 sm:p-12 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 flex items-center justify-center rounded-2xl" style="background: var(--bg-tertiary);">
                            <i class="fas fa-lock text-2xl sm:text-3xl" style="color: var(--primary-color);"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Kampanyaları görmek için giriş yapın</h3>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">Topluluğun sunduğu özel indirimler ve fırsatlar üyeler için görüntülenebilir.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="btn-primary px-6 py-3 text-white text-sm font-semibold active:scale-95 inline-flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 text-sm font-semibold transition-all active:scale-95 inline-flex items-center justify-center" style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md);" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-tertiary)'">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6">
                            <?php foreach (array_slice($community_data['campaigns'], 0, 6) as $campaign): ?>
                            <?php $is_expired = $campaign['end_date'] && strtotime($campaign['end_date']) < time(); ?>
                                <div class="card-modern overflow-hidden <?= $is_expired ? 'opacity-60' : '' ?> active:scale-[0.98] group h-full flex flex-col">
                                    <div class="relative h-48 sm:h-56 lg:h-64 overflow-hidden" style="background: var(--bg-tertiary);">
                                        <?php if ($campaign['image_path']): ?>
                                            <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($campaign['image_path']) ?>" alt="<?= htmlspecialchars($campaign['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                        <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800">
                                            <i class="fas fa-tags text-slate-400 dark:text-slate-500 text-5xl sm:text-6xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($campaign['discount_percentage']): ?>
                                            <div class="absolute top-4 left-4">
                                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-bold" style="background: rgba(239, 68, 68, 0.95); color: white; border-radius: var(--radius-sm); box-shadow: var(--shadow-md);">
                                                    %<?= htmlspecialchars($campaign['discount_percentage']) ?> İndirim
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-5 sm:p-6 flex-1 flex flex-col">
                                        <h3 class="text-lg sm:text-xl font-bold mb-3 line-clamp-2" style="color: var(--text-primary);">
                                            <?= htmlspecialchars($campaign['title']) ?>
                                        </h3>
                                        <p class="text-sm sm:text-base line-clamp-3 leading-relaxed flex-1" style="color: var(--text-secondary);">
                                            <?= htmlspecialchars($campaign['offer_text']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!$is_guest): ?>
                <div class="card-modern rounded-3xl p-12 sm:p-16 text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-tags text-gray-400 text-2xl sm:text-3xl"></i>
            </div>
                    <p class="text-base font-bold text-gray-900 mb-1">Henüz kampanya yok</p>
                    <p class="text-sm text-gray-500">Yakında kampanyalar eklenecektir.</p>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($current_view === 'events'): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern p-8 sm:p-12">
                        <div class="w-20 h-20 flex items-center justify-center mx-auto mb-6" style="background: var(--primary-color); border-radius: var(--radius-md);">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold mb-3" style="color: var(--text-primary); letter-spacing: -0.02em;">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="mb-8" style="color: var(--text-secondary);">Etkinlikleri görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="btn-primary px-6 py-3 text-white font-semibold active:scale-95 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 font-semibold transition-all active:scale-95 inline-block" style="background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md);" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-tertiary)'">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!$is_member_of_selected): ?>
                    <?= render_membership_info_banner($selected_community, $membership_pending_for_selected, 'events') ?>
                <?php endif; ?>
            <!-- Etkinlikler Listesi -->
            <div class="space-y-6 sm:space-y-8">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold mb-2 tracking-tight" style="color: var(--text-primary); letter-spacing: -0.02em;">Etkinlikler</h1>
                    <p class="text-sm sm:text-base" style="color: var(--text-secondary);"><?= htmlspecialchars($community_data['name']) ?> topluluğunun düzenlediği tüm etkinlikler</p>
                </div>
                
                <?php if (empty($community_data['events'])): ?>
                    <div class="card-modern p-16 text-center">
                        <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4" style="background: var(--bg-tertiary); border-radius: var(--radius-sm);">
                            <i class="fas fa-calendar-alt text-2xl" style="color: var(--text-light);"></i>
                        </div>
                        <p class="text-base font-semibold mb-1" style="color: var(--text-primary);">Henüz etkinlik eklenmemiş</p>
                        <p class="text-sm" style="color: var(--text-secondary);">Yakında yeni etkinlikler eklenecektir.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                        <?php foreach ($community_data['events'] as $event): ?>
                        <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=event_detail&event_id=<?= $event['id'] ?>" class="card-modern overflow-hidden group active:scale-[0.98]">
                            <?php 
                            $event_media_preview = get_event_media($event['id'], $selected_community);
                            $first_image = !empty($event_media_preview['images']) ? $event_media_preview['images'][0] : null;
                            ?>
                            <div class="relative h-44 sm:h-48 overflow-hidden" style="background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--border-color) 100%);">
                                <?php if ($first_image): ?>
                                    <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($first_image['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($event['title']) ?>" 
                                         class="w-full h-full object-cover group-active:scale-105 transition-transform duration-300"
                                         onerror="this.style.display='none';">
                                <?php elseif (!empty($event['image_path'])): ?>
                                    <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($event['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($event['title']) ?>" 
                                         class="w-full h-full object-cover group-active:scale-105 transition-transform duration-300"
                                         onerror="this.style.display='none';">
                                <?php endif; ?>
                                
                                <div class="absolute top-3 right-3 sm:top-4 sm:right-4 px-2.5 py-1.5 sm:px-3 sm:py-2 shadow-sm" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: var(--radius-sm);">
                                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary);">
                                        <?= date('M', strtotime($event['date'])) ?>
                                    </div>
                                    <div class="text-lg sm:text-xl font-bold leading-none" style="color: var(--text-primary);">
                                        <?= date('d', strtotime($event['date'])) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 sm:p-6">
                                <h3 class="text-base sm:text-lg font-bold mb-2 sm:mb-3 line-clamp-2 transition-colors" style="color: var(--text-primary);" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-primary)'">
                                    <?= htmlspecialchars($event['title']) ?>
                                </h3>
                                <div class="space-y-1.5 sm:space-y-2 mb-3 sm:mb-4">
                                    <div class="flex items-center text-xs sm:text-sm" style="color: var(--text-secondary);">
                                        <i class="far fa-clock mr-2 text-xs"></i>
                                        <span><?= date('d.m.Y', strtotime($event['date'])) ?> • <?= htmlspecialchars($event['time']) ?></span>
                                    </div>
                                    <?php if (!empty($event['location'])): ?>
                                    <div class="flex items-center text-xs sm:text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-2 text-xs"></i>
                                        <span class="truncate"><?= htmlspecialchars($event['location']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center text-xs sm:text-sm font-semibold transition-colors" style="color: var(--primary-color);" onmouseover="this.style.color='var(--primary-dark)'" onmouseout="this.style.color='var(--primary-color)'">
                                    <span>Detayları görüntüle</span>
                                    <i class="fas fa-arrow-right ml-2 text-xs group-active:translate-x-1 transition-transform"></i>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>

        <?php elseif ($current_view === 'event_detail' && $event_detail): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Etkinlik detaylarını görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 bg-gray-50 hover:bg-gray-100 text-gray-900 rounded-xl font-semibold transition-all active:scale-95 border border-gray-200 inline-block">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <?php
                $email_for_rsvp = '';
                if ($user_profile) {
                    $email_for_rsvp = trim($user_profile['email'] ?? $user_email ?? '');
                }
                $current_rsvp = null;
                if ($email_for_rsvp !== '') {
                    $current_rsvp = get_user_rsvp_status($selected_community, $event_detail['id'], $email_for_rsvp);
                }
                $event_tags = $event_detail['tags_list'] ?? [];
                $attendance = $event_detail['attendance'] ?? ['attending' => 0, 'not_attending' => 0];
                $total_attending = (int)($attendance['attending'] ?? 0);
                $total_not_attending = (int)($attendance['not_attending'] ?? 0);
                $total_responses = $total_attending + $total_not_attending;
                $meta_items = [];

                if (!empty($event_detail['category'])) {
                    $meta_items[] = ['icon' => 'fa-layer-group', 'label' => 'Kategori', 'value' => $event_detail['category'], 'raw' => false];
                }
                if (!empty($event_detail['status'])) {
                    $meta_items[] = ['icon' => 'fa-flag-checkered', 'label' => 'Durum', 'value' => $event_detail['status'], 'raw' => false];
                }
                if (!empty($event_detail['priority'])) {
                    $meta_items[] = ['icon' => 'fa-bolt', 'label' => 'Öncelik', 'value' => $event_detail['priority'], 'raw' => false];
                }
                if (!empty($event_detail['capacity'])) {
                    $meta_items[] = ['icon' => 'fa-users', 'label' => 'Kontenjan', 'value' => number_format((int)$event_detail['capacity']), 'raw' => false];
                }
                if (isset($event_detail['registration_required']) && $event_detail['registration_required'] !== '') {
                    $meta_items[] = [
                        'icon' => 'fa-id-card',
                        'label' => 'Kayıt Zorunluluğu',
                        'value' => (int)$event_detail['registration_required'] === 1 ? 'Zorunlu' : 'Opsiyonel',
                        'raw' => false
                    ];
                }
                if (!empty($event_detail['registration_deadline'])) {
                    $meta_items[] = ['icon' => 'fa-hourglass-half', 'label' => 'Son Kayıt', 'value' => $event_detail['registration_deadline'], 'raw' => false];
                }
                if (!empty($event_detail['start_datetime'])) {
                    $meta_items[] = ['icon' => 'fa-play-circle', 'label' => 'Başlangıç', 'value' => $event_detail['start_datetime'], 'raw' => false];
                }
                if (!empty($event_detail['end_datetime'])) {
                    $meta_items[] = ['icon' => 'fa-stop-circle', 'label' => 'Bitiş', 'value' => $event_detail['end_datetime'], 'raw' => false];
                }
                if (!empty($event_detail['organizer'])) {
                    $meta_items[] = ['icon' => 'fa-user-tie', 'label' => 'Organizatör', 'value' => $event_detail['organizer'], 'raw' => false];
                }
                if (!empty($event_detail['contact_email'])) {
                    $meta_items[] = ['icon' => 'fa-envelope', 'label' => 'İletişim E-posta', 'value' => $event_detail['contact_email'], 'raw' => false];
                }
                if (!empty($event_detail['contact_phone'])) {
                    $meta_items[] = ['icon' => 'fa-phone', 'label' => 'İletişim Telefon', 'value' => $event_detail['contact_phone'], 'raw' => false];
                }
                if (!empty($event_detail['cost'])) {
                    $meta_items[] = ['icon' => 'fa-ticket-alt', 'label' => 'Katılım Ücreti', 'value' => $event_detail['cost'], 'raw' => false];
                }
                if (!empty($event_detail['external_link'])) {
                    $safe_link = htmlspecialchars($event_detail['external_link'], ENT_QUOTES, 'UTF-8');
                    $meta_items[] = [
                        'icon' => 'fa-link',
                        'label' => 'Dış Bağlantı',
                        'value' => '<a href="' . $safe_link . '" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800">' . $safe_link . '</a>',
                        'raw' => true
                    ];
                }
                if (!empty($event_detail['visibility'])) {
                    $meta_items[] = ['icon' => 'fa-eye', 'label' => 'Görünürlük', 'value' => $event_detail['visibility'], 'raw' => false];
                }
                if (!empty($event_detail['max_attendees'])) {
                    $meta_items[] = ['icon' => 'fa-user-plus', 'label' => 'Maksimum Katılımcı', 'value' => number_format((int)$event_detail['max_attendees']), 'raw' => false];
                }
                if (!empty($event_detail['min_attendees'])) {
                    $meta_items[] = ['icon' => 'fa-user-check', 'label' => 'Minimum Katılımcı', 'value' => number_format((int)$event_detail['min_attendees']), 'raw' => false];
                }

                $attending_active = $current_rsvp && ($current_rsvp['rsvp_status'] ?? '') === 'attending';
                $not_attending_active = $current_rsvp && ($current_rsvp['rsvp_status'] ?? '') === 'not_attending';
                $attending_button_state = $attending_active
                    ? 'bg-green-600 text-white border-green-600 shadow-lg shadow-green-500/30'
                    : 'bg-white text-green-700 border-green-400 hover:bg-green-100 hover:border-green-500';
                $not_attending_button_state = $not_attending_active
                    ? 'bg-slate-700 text-white border-slate-700 shadow-lg shadow-slate-500/30'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100 hover:border-slate-400';
            ?>
            <!-- Etkinlik Detay Sayfası -->
            <div class="space-y-6">
                <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=events" class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 font-medium text-sm transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Geri Dön
                </a>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 card-shadow">
                    <div class="p-8 border-b border-gray-200 dark:border-gray-700">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4"><?= htmlspecialchars($event_detail['title']) ?></h1>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <?= date('d.m.Y', strtotime($event_detail['date'])) ?>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?= htmlspecialchars($event_detail['time']) ?>
                            </div>
                            <?php if (!empty($event_detail['location'])): ?>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <?= htmlspecialchars($event_detail['location']) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($event_detail['image_path'])): ?>
                        <div class="mt-6">
                            <div class="relative overflow-hidden rounded-2xl shadow-lg ring-1 ring-black/5">
                                <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($event_detail['image_path']) ?>" alt="<?= htmlspecialchars($event_detail['title']) ?>" class="w-full h-64 sm:h-80 object-cover">
                    </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($event_detail['video_path'])): ?>
                        <div class="mt-6">
                            <div class="relative overflow-hidden rounded-2xl shadow-lg ring-1 ring-black/5 bg-black">
                                <video controls class="w-full h-64 sm:h-80">
                                    <source src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($event_detail['video_path']) ?>" type="video/mp4">
                                    <source src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($event_detail['video_path']) ?>" type="video/quicktime">
                                    Tarayıcınız video oynatmayı desteklemiyor.
                                </video>
                                </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($meta_items)): ?>
                        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($meta_items as $item): ?>
                            <div class="flex items-start gap-3 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 flex items-center justify-center flex-shrink-0">
                                    <i class="fas <?= $item['icon'] ?>"></i>
                                    </div>
                                <div class="space-y-1">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= htmlspecialchars($item['label']) ?></div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        <?php if (!empty($item['raw'])): ?>
                                            <?= $item['value'] ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string)$item['value']) ?>
                                        <?php endif; ?>
                                    </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($event_tags)): ?>
                        <div class="mt-6 flex flex-wrap gap-2">
                            <?php foreach ($event_tags as $tag): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-500/30">
                                <i class="fas fa-hashtag mr-1"></i><?= htmlspecialchars($tag) ?>
                            </span>
                            <?php endforeach; ?>
                                </div>
                        <?php endif; ?>

                    </div>
                    
                    <div class="p-8 space-y-8">
                        <?php if (!$is_member_of_selected): ?>
                            <?= render_membership_info_banner($selected_community, $membership_pending_for_selected, 'event_detail', ['event_id' => $event_detail['id']]) ?>
                        <?php else: ?>
                        <div class="rounded-xl border border-green-200 dark:border-green-600/30 bg-green-50/80 dark:bg-green-500/10 p-6 space-y-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full <?= $attending_active ? 'bg-green-500 text-white' : 'bg-green-500/20 text-green-600 dark:text-green-300' ?> flex items-center justify-center">
                                    <i class="fas <?= $attending_active ? 'fa-check-circle' : 'fa-clipboard-check' ?>"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-green-900 dark:text-green-100">
                                        <?php if ($attending_active): ?>
                                            Katılımınız Onaylandı
                                        <?php elseif ($not_attending_active): ?>
                                            Katılmayacağınızı Belirttiniz
                                        <?php else: ?>
                                            Katılım Durumunuzu Seçin
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-sm text-green-800/80 dark:text-green-200/80">
                                        Tek tıkla katılım durumunuzu güncelleyebilirsiniz. Organizasyon ekibi anlık olarak bilgilenecek.
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <form method="POST" action="?community=<?= htmlspecialchars($selected_community) ?>&view=event_detail&event_id=<?= $event_detail['id'] ?>" class="flex-1">
                                    <input type="hidden" name="action" value="submit_rsvp">
                                    <?= csrf_token_field() ?>
                                    <input type="hidden" name="event_id" value="<?= $event_detail['id'] ?>">
                                    <input type="hidden" name="rsvp_status" value="attending">
                                    <button type="submit" class="flex w-full items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 font-semibold text-sm transition-all <?= $attending_button_state ?>">
                                        <i class="fas fa-check"></i>
                                        Etkinliğe Katılacağım
                                    </button>
                                </form>
                                <form method="POST" action="?community=<?= htmlspecialchars($selected_community) ?>&view=event_detail&event_id=<?= $event_detail['id'] ?>" class="flex-1">
                                    <input type="hidden" name="action" value="submit_rsvp">
                                    <?= csrf_token_field() ?>
                                    <input type="hidden" name="event_id" value="<?= $event_detail['id'] ?>">
                                    <input type="hidden" name="rsvp_status" value="not_attending">
                                    <button type="submit" class="flex w-full items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 font-semibold text-sm transition-all <?= $not_attending_button_state ?>">
                                        <i class="fas fa-times"></i>
                                        Katılamayacağım
                                </button>
                            </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="rounded-xl border border-green-200 bg-white dark:bg-slate-900/40 p-4 shadow-inner">
                                <div class="text-xs font-semibold uppercase tracking-wide text-green-600">Katılacak</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-2xl font-bold text-slate-900 dark:text-white"><?= $total_attending ?></span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= $total_responses > 0 ? round(($total_attending / $total_responses) * 100) : 0 ?>%</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white dark:bg-slate-900/40 p-4 shadow-inner">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-600">Katılmayacak</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-2xl font-bold text-slate-900 dark:text-white"><?= $total_not_attending ?></span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= $total_responses > 0 ? round(($total_not_attending / $total_responses) * 100) : 0 ?>%</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-indigo-200 bg-white dark:bg-slate-900/40 p-4 shadow-inner">
                                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Toplam Yanıt</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-2xl font-bold text-slate-900 dark:text-white"><?= $total_responses ?></span>
                                    <?php if (!empty($event_detail['capacity'])): ?>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Kontenjan: <?= number_format((int)$event_detail['capacity']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($event_detail['description'])): ?>
                        <div class="space-y-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Etkinlik Açıklaması</h3>
                            <div class="bg-gray-50 dark:bg-gray-700/30 p-5 rounded-lg border border-gray-200 dark:border-gray-600">
                                <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-wrap">
                                    <?= nl2br(htmlspecialchars($event_detail['description'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($event_detail['survey'])): ?>
                        <?php 
                        $survey = $event_detail['survey'];
                        $user_has_voted = false;
                        $can_vote = false;
                        
                        if ($is_member_of_selected && $user_profile) {
                            $email = trim($user_profile['email'] ?? '');
                            $member_id = get_member_id_by_email($selected_community, $email);
                            if ($member_id) {
                                $user_has_voted = has_user_voted_in_survey($selected_community, $survey['id'], $member_id);
                                $can_vote = !$user_has_voted;
                            }
                        }
                        ?>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <?= htmlspecialchars($survey['title'] ?? 'Etkinlik Anketi') ?>
                                </h3>
                                <?php if (!empty($survey['description'])): ?>
                                <span class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($survey['description']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($survey_error)): ?>
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-300 px-5 py-4 rounded-lg shadow-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle mr-3"></i>
                                    <span class="font-medium"><?= htmlspecialchars($survey_error) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($can_vote): ?>
                            <!-- Oylama Formu -->
                            <form method="POST" action="?community=<?= htmlspecialchars($selected_community) ?>&view=event_detail&event_id=<?= $event_detail['id'] ?>" class="space-y-6 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-200 dark:border-indigo-700 p-6">
                                <input type="hidden" name="action" value="submit_survey_response">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                <input type="hidden" name="event_id" value="<?= $event_detail['id'] ?>">
                                
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-full bg-indigo-500 text-white flex items-center justify-center">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-semibold text-indigo-900 dark:text-indigo-100">Ankete Oy Verin</h4>
                                        <p class="text-sm text-indigo-700 dark:text-indigo-300">Lütfen aşağıdaki soruları cevaplayın.</p>
                                    </div>
                                </div>
                                
                                <?php foreach ($survey['questions'] as $index => $question): ?>
                                <div class="space-y-3 rounded-xl border border-indigo-200 dark:border-indigo-700 bg-white dark:bg-slate-800/60 p-5">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-1">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400 mb-1">Soru <?= $index + 1 ?></div>
                                            <h4 class="text-base font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($question['question_text'] ?? '') ?></h4>
                                        </div>
                                    </div>
                                    <?php if (!empty($question['options'])): ?>
                                    <div class="space-y-2 mt-4">
                                        <?php foreach ($question['options'] as $option): ?>
                                        <label class="flex items-center p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                                            <input type="radio" name="responses[<?= $question['id'] ?>]" value="<?= $option['id'] ?>" required class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600">
                                            <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($option['option_text'] ?? '') ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-sm transition-all">
                                        <i class="fas fa-paper-plane mr-2"></i>Oyu Gönder
                                    </button>
                                </div>
                            </form>
                            <?php elseif ($user_has_voted): ?>
                            <!-- Oy Verildi Mesajı -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-5 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-semibold text-green-900 dark:text-green-100">Oyunuz Kaydedildi</h4>
                                        <p class="text-sm text-green-700 dark:text-green-300">Bu ankete zaten oy verdiniz. Sonuçlar aşağıda görüntülenmektedir.</p>
                                    </div>
                                </div>
                            </div>
                            <?php elseif (!$is_member_of_selected): ?>
                            <!-- Üye Olmayanlar İçin Mesaj -->
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl p-5 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-yellow-500 text-white flex items-center justify-center">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-base font-semibold text-yellow-900 dark:text-yellow-100">Ankete Oy Vermek İçin Üye Olun</h4>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300">Bu ankete oy verebilmek için önce topluluğa üye olmanız gerekmektedir.</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Anket Sonuçları -->
                            <div class="space-y-6">
                                <?php foreach ($survey['questions'] as $index => $question): ?>
                                <?php $question_total = (int)($question['total_responses'] ?? 0); ?>
                                <div class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Soru <?= $index + 1 ?></div>
                                            <h4 class="text-base font-semibold text-slate-900 dark:text-white mt-1"><?= htmlspecialchars($question['question_text'] ?? '') ?></h4>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Toplam Yanıt: <?= $question_total ?></div>
                                    </div>
                                    <?php if (!empty($question['options'])): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($question['options'] as $option): ?>
                                        <?php
                                            $option_count = (int)($option['response_count'] ?? 0);
                                            $percentage = $question_total > 0 ? round(($option_count / $question_total) * 100) : 0;
                                        ?>
                                        <div class="space-y-1.5">
                                            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                                                <span class="font-medium"><?= htmlspecialchars($option['option_text'] ?? '') ?></span>
                                                <span><?= $percentage ?>% (<?= $option_count ?>)</span>
                                            </div>
                                            <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                                <div class="h-full rounded-full bg-indigo-500" style="width: <?= $percentage ?>%;"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event_media && !empty($event_media['images'])): ?>
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Etkinlik Fotoğrafları</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <?php foreach ($event_media['images'] as $image): ?>
                                <div class="relative group cursor-pointer rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700" onclick="openImageModal('../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($image['image_path']) ?>')">
                                    <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($image['image_path']) ?>" 
                                         alt="Etkinlik Fotoğrafı" 
                                         class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
                                         onerror="this.style.display='none';">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($event_media['videos'])): ?>
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Etkinlik Videoları</h3>
                            <div class="space-y-4">
                                <?php foreach ($event_media['videos'] as $video): ?>
                                <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
                                    <video controls class="w-full">
                                        <source src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($video['video_path']) ?>" type="video/mp4">
                                        <source src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($video['video_path']) ?>" type="video/quicktime">
                                        Tarayıcınız video oynatmayı desteklemiyor.
                                    </video>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            </div>

        <?php elseif ($current_view === 'campaigns'): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Kampanyaları görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 bg-gray-50 hover:bg-gray-100 text-gray-900 rounded-xl font-semibold transition-all active:scale-95 border border-gray-200 inline-block">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!$is_member_of_selected): ?>
                    <?= render_membership_info_banner($selected_community, $membership_pending_for_selected, 'campaigns') ?>
                <?php endif; ?>
            <!-- Kampanyalar -->
            <div class="space-y-4 sm:space-y-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-1 sm:mb-2 tracking-tight">Kampanyalar</h1>
                    <p class="text-sm sm:text-base text-gray-600"><?= htmlspecialchars($community_data['name']) ?> topluluğunun özel kampanyaları</p>
                </div>
                
                <?php if (empty($community_data['campaigns'])): ?>
                    <div class="card-modern rounded-3xl p-12 sm:p-16 text-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-tags text-gray-400 text-2xl sm:text-3xl"></i>
                        </div>
                        <p class="text-base font-bold text-gray-900 mb-1">Henüz kampanya yok</p>
                        <p class="text-sm text-gray-500">Yakında kampanyalar eklenecektir.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                        <?php foreach ($community_data['campaigns'] as $campaign): ?>
                            <?php
                            $is_expired = $campaign['end_date'] && strtotime($campaign['end_date']) < time();
                            ?>
                            <div class="card-modern rounded-3xl overflow-hidden <?= $is_expired ? 'opacity-60' : '' ?> active:scale-[0.98]">
                                <?php if ($campaign['image_path']): ?>
                                    <div class="h-40 sm:h-48 overflow-hidden" style="background: var(--bg-tertiary);">
                                        <img src="../communities/<?= htmlspecialchars($selected_community) ?>/<?= htmlspecialchars($campaign['image_path']) ?>" alt="<?= htmlspecialchars($campaign['title']) ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="h-40 sm:h-48 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] flex items-center justify-center">
                                        <i class="fas fa-tags text-white/50 text-4xl sm:text-5xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="p-4 sm:p-6">
                                    <div class="flex items-start justify-between mb-2">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 line-clamp-2 flex-1">
                                            <?= htmlspecialchars($campaign['title']) ?>
                                        </h3>
                                        <?php if ($is_expired): ?>
                                            <span class="ml-2 px-2 py-1 text-[10px] font-bold rounded-full bg-gray-100 text-gray-700 flex-shrink-0">
                                                Süresi Doldu
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($campaign['partner_name']): ?>
                                        <p class="text-xs sm:text-sm text-gray-600 mb-2">
                                            <span class="font-semibold">Partner:</span> <?= htmlspecialchars($campaign['partner_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($campaign['discount_percentage']): ?>
                                        <div class="mb-2 sm:mb-3">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                                %<?= htmlspecialchars($campaign['discount_percentage']) ?> İndirim
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-xs sm:text-sm text-gray-600 mb-2 sm:mb-3 line-clamp-2 leading-relaxed">
                                        <?= htmlspecialchars($campaign['offer_text']) ?>
                                    </p>
                                    
                                    <?php if ($campaign['description']): ?>
                                        <p class="text-[10px] sm:text-xs text-gray-500 line-clamp-2">
                                            <?= htmlspecialchars($campaign['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>

        <?php elseif ($current_view === 'members'): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Üye listesini görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 bg-gray-50 hover:bg-gray-100 text-gray-900 rounded-xl font-semibold transition-all active:scale-95 border border-gray-200 inline-block">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
    <?php if (!$is_member_of_selected): ?>
        <?= render_membership_info_banner($selected_community, $membership_pending_for_selected, 'members') ?>
    <?php endif; ?>
            <!-- Üyeler Listesi -->
            <div class="space-y-4 sm:space-y-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-1 sm:mb-2 tracking-tight">Üye Listesi</h1>
                    <p class="text-sm sm:text-base text-gray-600"><?= htmlspecialchars($community_data['name']) ?> topluluğunun tüm üyeleri</p>
                </div>
                
                <?php if (empty($community_data['members'])): ?>
                    <div class="card-modern rounded-3xl p-12 sm:p-16 text-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users text-gray-400 text-2xl sm:text-3xl"></i>
                        </div>
                        <p class="text-base font-bold text-gray-900 mb-1">Henüz üye kaydı bulunmuyor</p>
                        <p class="text-sm text-gray-500">Yakında üyeler eklenecektir.</p>
                    </div>
                <?php else: ?>
                    <div class="card-modern rounded-3xl overflow-hidden">
                        <div class="p-4 sm:p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                                <?php foreach ($community_data['members'] as $member): ?>
                                <div class="flex items-center p-3 sm:p-3.5 bg-gray-50 rounded-2xl active:bg-gray-100 transition-colors">
                                    <div class="w-10 h-10 sm:w-11 sm:h-11 bg-[#6366f1] rounded-xl flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-user text-white text-sm sm:text-base"></i>
                                    </div>
                                    <span class="text-sm sm:text-base font-semibold text-gray-900 truncate"><?= htmlspecialchars($member['full_name']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>

        <?php elseif ($current_view === 'board'): ?>
            <?php if (!$user_logged_in): ?>
                <!-- Login Gerekli Ekranı -->
                <div class="max-w-2xl mx-auto text-center py-12 sm:py-16">
                    <div class="card-modern rounded-3xl p-8 sm:p-12">
                        <div class="w-20 h-20 bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Devam Etmek İçin Giriş Yapın</h2>
                        <p class="text-gray-600 mb-8">Yönetim kurulunu görmek için lütfen giriş yapın veya kayıt olun.</p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="login.php" class="px-6 py-3 bg-gradient-to-r from-[#6366f1] to-[#8b5cf6] hover:from-[#4f46e5] hover:to-[#7c3aed] text-white rounded-xl font-semibold transition-all active:scale-95 shadow-lg shadow-[#6366f1]/25 inline-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Giriş Yap
                            </a>
                            <a href="register.php" class="px-6 py-3 bg-gray-50 hover:bg-gray-100 text-gray-900 rounded-xl font-semibold transition-all active:scale-95 border border-gray-200 inline-block">
                                <i class="fas fa-user-plus mr-2"></i>Kayıt Ol
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
    <?php if (!$is_member_of_selected): ?>
        <?= render_membership_info_banner($selected_community, $membership_pending_for_selected, 'board') ?>
    <?php endif; ?>
            <!-- Yönetim Kurulu -->
            <div class="space-y-4 sm:space-y-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-1 sm:mb-2 tracking-tight">Yönetim Kurulu</h1>
                    <p class="text-sm sm:text-base text-gray-600"><?= htmlspecialchars($community_data['name']) ?> topluluğunu yöneten ekip</p>
                </div>
                
                <?php if (empty($community_data['board'])): ?>
                    <div class="card-modern rounded-3xl p-12 sm:p-16 text-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-shield text-gray-400 text-2xl sm:text-3xl"></i>
                        </div>
                        <p class="text-base font-bold text-gray-900 mb-1">Yönetim kurulu bilgisi bulunmuyor</p>
                        <p class="text-sm text-gray-500">Yakında yönetim kurulu bilgileri eklenecektir.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-5">
                        <?php foreach ($community_data['board'] as $member): ?>
                        <div class="card-modern rounded-3xl p-5 sm:p-6 text-center active:scale-[0.98]">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-[#6366f1] rounded-2xl mx-auto mb-3 sm:mb-4 flex items-center justify-center">
                                <i class="fas fa-user text-white text-xl sm:text-2xl"></i>
                            </div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2">
                                <?= htmlspecialchars($member['full_name']) ?>
                            </h3>
                            <?php if (!empty($member['role'])): ?>
                                <div class="inline-flex items-center px-3 py-1 bg-[#6366f1]/10 text-[#6366f1] rounded-full text-xs font-bold border border-[#6366f1]/20">
                                    <?= htmlspecialchars($member['role']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation Bar (Mobile) -->
    <?php if ($selected_community && $community_data): ?>
    <nav class="bottom-nav lg:hidden">
        <div class="flex items-center justify-around">
            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=overview" class="bottom-nav-item <?= $current_view === 'overview' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Ana Sayfa</span>
            </a>
            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=events" class="bottom-nav-item <?= $current_view === 'events' || $current_view === 'event_detail' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Etkinlikler</span>
            </a>
            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=members" class="bottom-nav-item <?= $current_view === 'members' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Üyeler</span>
            </a>
            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=campaigns" class="bottom-nav-item <?= $current_view === 'campaigns' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span>Kampanyalar</span>
            </a>
            <a href="?community=<?= htmlspecialchars($selected_community) ?>&view=board" class="bottom-nav-item <?= $current_view === 'board' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i>
                <span>Yönetim</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Market Ürün Detay Modal -->
    <div id="market-product-modal" class="market-modal-overlay fixed inset-0 hidden z-50 items-center justify-center px-4 py-8">
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6 sm:p-8">
            <button type="button" id="market-modal-close" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center shadow-md transition-all">
                <i class="fas fa-times"></i>
            </button>
            <div class="grid lg:grid-cols-2 gap-6 lg:gap-8">
                <div>
                    <div class="market-image aspect-[4/3]" id="market-modal-image-wrapper">
                        <img id="market-modal-image" src="" alt="Ürün görseli">
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600">
                        <div class="p-4 rounded-2xl bg-gray-50">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Topluluk</p>
                            <p class="font-bold text-gray-900" id="market-modal-community"></p>
                        </div>
                        <div class="p-4 rounded-2xl bg-gray-50">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Stok</p>
                            <p class="font-bold text-gray-900" id="market-modal-stock"></p>
                        </div>
                    </div>
                </div>
                <div class="space-y-4 flex flex-col">
                    <div>
                        <span id="market-modal-category" class="inline-flex items-center px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold border border-indigo-100"></span>
                        <h3 id="market-modal-title" class="text-2xl sm:text-3xl font-black text-gray-900 mt-3 leading-tight"></h3>
                        <p id="market-modal-description" class="text-sm text-gray-600 mt-2"></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Ürün Fiyatı</p>
                                <p id="market-modal-price" class="text-3xl font-black text-gray-900">-</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Toplam Tutar</p>
                                <p id="market-modal-total" class="text-xl font-bold text-indigo-600">-</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs text-gray-600">
                            <div class="p-3 rounded-xl bg-white border border-gray-100">
                                <p class="font-semibold text-gray-500 mb-1">İyzico Komisyonu</p>
                                <p id="market-modal-iyzico" class="text-base font-bold text-gray-900">-</p>
                            </div>
                            <div class="p-3 rounded-xl bg-white border border-gray-100">
                                <p class="font-semibold text-gray-500 mb-1">Platform Komisyonu</p>
                                <p id="market-modal-platform" class="text-base font-bold text-gray-900">-</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-auto flex flex-col sm:flex-row gap-3">
                        <button type="button" id="market-modal-add-cart" class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-gray-200 text-gray-700 font-semibold gap-2 hover:border-gray-300 transition-all">
                            <i class="fas fa-cart-plus"></i>
                            Sepete Ekle
                        </button>
                        <a href="#" target="_blank" rel="noopener" id="market-modal-community-link" class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-semibold shadow-lg hover:shadow-xl transition-all gap-2">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                            Topluluk Sayfası
                        </a>
                        <button type="button" id="market-modal-share" class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-gray-200 text-gray-700 font-semibold gap-2 hover:border-gray-300 transition-all">
                            <i class="fas fa-share-nodes"></i>
                            Paylaş
                        </button>
                    </div>
                </div>
            </div>
        </div>
    
    <!-- Market Sepet Modal -->
    <div id="market-cart-modal" class="market-modal-overlay fixed inset-0 hidden z-50 items-center justify-center px-4 py-8">
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6 sm:p-8">
            <button type="button" id="market-cart-close" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center shadow-md transition-all">
                <i class="fas fa-times"></i>
            </button>
            <div class="grid lg:grid-cols-2 gap-6 lg:gap-8">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] font-semibold text-gray-400">Sepet</p>
                        <h2 class="text-2xl font-black text-gray-900">Ürünleriniz</h2>
                    </div>
                    <div id="market-cart-empty" class="market-empty p-6 text-center hidden">
                        <div class="max-w-sm mx-auto">
                            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white flex items-center justify-center text-gray-400 shadow-inner">
                                <i class="fas fa-cart-arrow-down text-2xl"></i>
                            </div>
                            <p class="text-sm text-gray-500">Sepetiniz boş. Ürün eklemek için markete göz atın.</p>
                        </div>
                    </div>
                    <div id="market-cart-items" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1"></div>
                </div>
                <div class="space-y-5">
                    <div class="p-5 rounded-2xl bg-gray-50 border border-gray-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-500">Ürün Tutarı</span>
                            <span id="market-cart-subtotal" class="text-lg font-bold text-gray-900">0,00 ₺</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-500">Komisyonlar</span>
                            <span id="market-cart-commissions" class="text-lg font-bold text-gray-900">0,00 ₺</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3 flex items-center justify-between">
                            <span class="text-base font-bold text-gray-900">Ödenecek Toplam</span>
                            <span id="market-cart-total" class="text-2xl font-black text-indigo-600">0,00 ₺</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ad Soyad</label>
                                <input type="text" id="market-customer-name" class="mt-1 w-full px-4 py-3 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="Adınız Soyadınız">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">E-posta</label>
                                <input type="email" id="market-customer-email" class="mt-1 w-full px-4 py-3 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="mail@ornek.com">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Telefon</label>
                                <input type="tel" id="market-customer-phone" class="mt-1 w-full px-4 py-3 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="05XX XXX XX XX">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Şehir</label>
                                <input type="text" id="market-customer-city" class="mt-1 w-full px-4 py-3 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="Şehir">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Adres</label>
                            <textarea id="market-customer-address" rows="3" class="mt-1 w-full px-4 py-3 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="Teslimat/iletişim adresiniz"></textarea>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button type="button" id="market-cart-submit" class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-semibold shadow-lg hover:shadow-xl transition-all gap-2">
                                <i class="fas fa-credit-card"></i>
                                Ödeme Yap
                            </button>
                            <button type="button" id="market-cart-clear" class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-2xl border border-gray-200 text-gray-600 font-semibold gap-2 hover:border-gray-300 transition-all">
                                <i class="fas fa-trash"></i>
                                Sepeti Temizle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fotoğraf Modal (Sadece bu modal kaldı) -->
    <div id="imageModal" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center hidden backdrop-blur-md">
        <div class="relative max-w-7xl max-h-[90vh] w-full h-full flex items-center justify-center p-4">
            <button onclick="closeImageModal()" class="absolute top-6 right-6 bg-gray-900/50 hover:bg-gray-900/70 backdrop-blur-sm text-white rounded-lg p-2 transition-all z-10 border border-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img id="modalImage" src="" alt="Büyük Görsel" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
        </div>
    </div>

    <!-- Profil Dropdown -->
    <?php if ($user_logged_in): ?>
    <div id="profile-dropdown" class="fixed w-64 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 hidden overflow-hidden" style="display: none;">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center border border-gray-200 dark:border-gray-600">
                        <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($user_name ?? 'Kullanıcı') ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user_email ?? '') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Menu Items -->
        <div class="py-2">
            <a href="?view=profile" onclick="closeProfileDropdown(); return true;" class="group flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200">
                <div class="w-8 h-8 mr-3 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center group-hover:bg-gray-200 dark:group-hover:bg-gray-600 transition-colors duration-200">
                    <svg class="w-4 h-4 text-gray-600 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <span class="font-medium">Profilim</span>
            </a>
            
            <!-- Çıkış Yap -->
            <div class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2">
                <a href="?logout=1" class="w-full group flex items-center px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200 rounded-lg">
                    <div class="w-8 h-8 mr-3 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center group-hover:bg-red-200 dark:group-hover:bg-red-900/50 transition-colors duration-200">
                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </div>
                    <span class="font-medium">Çıkış Yap</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Profil dropdown başlatma
        function initProfileDropdown() {
            const profileBtn = document.getElementById('profile-btn');
            const profileBtnMobile = document.getElementById('profile-btn-mobile');
            const profileDropdown = document.getElementById('profile-dropdown');
            
            function toggleProfileDropdown(e) {
                if (e) e.stopPropagation();
                
                if (profileDropdown) {
                    const isHidden = profileDropdown.classList.contains('hidden') || profileDropdown.style.display === 'none';
                    if (isHidden) {
                        // Pozisyonu hesapla ve ayarla
                        const btn = profileBtn || profileBtnMobile;
                        if (btn) {
                            const rect = btn.getBoundingClientRect();
                            profileDropdown.style.top = (rect.bottom + 12) + 'px';
                            profileDropdown.style.right = (window.innerWidth - rect.right) + 'px';
                        }
                        profileDropdown.classList.remove('hidden');
                        profileDropdown.style.display = 'block';
                    } else {
                        profileDropdown.classList.add('hidden');
                        profileDropdown.style.display = 'none';
                    }
                }
            }
            
            if (profileBtn) {
                profileBtn.addEventListener('click', toggleProfileDropdown);
            }
            
            if (profileBtnMobile) {
                profileBtnMobile.addEventListener('click', toggleProfileDropdown);
            }
            
            // Dışarı tıklayınca kapat
            document.addEventListener('click', function(e) {
                if (profileDropdown && 
                    !profileBtn?.contains(e.target) && 
                    !profileBtnMobile?.contains(e.target) && 
                    !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                    profileDropdown.style.display = 'none';
                }
            });
        }
        
        // Profil dropdown'ı kapat
        function closeProfileDropdown() {
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileDropdown) {
                profileDropdown.classList.add('hidden');
                profileDropdown.style.display = 'none';
            }
        }
        
        // Sayfa yüklendiğinde profil dropdown'ı başlat
        document.addEventListener('DOMContentLoaded', function() {
            initProfileDropdown();
            initMarketSection();
        });
        
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');
            const closeIcon = document.getElementById('close-icon');
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                menuIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        }
        
        function openImageModal(imagePath) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            if (modalImage) modalImage.src = imagePath;
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }
        }
        
        const MARKET_CART_STORAGE_KEY = 'fourkampus_market_cart';
        let marketCartInitialized = false;
        let marketCartState = { items: [] };
        
        function formatMarketPrice(value) {
            const numberValue = Number(value || 0);
            return numberValue.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
        }
        
        function loadMarketCartState() {
            try {
                const stored = localStorage.getItem(MARKET_CART_STORAGE_KEY);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (parsed && Array.isArray(parsed.items)) {
                        marketCartState = { items: parsed.items.map(item => ({
                            key: item.key,
                            quantity: Math.max(1, parseInt(item.quantity, 10) || 1)
                        })) };
                        return;
                    }
                }
            } catch (err) {}
            marketCartState = { items: [] };
        }
        
        function saveMarketCartState() {
            try {
                localStorage.setItem(MARKET_CART_STORAGE_KEY, JSON.stringify(marketCartState));
            } catch (err) {}
        }
        
        function updateMarketCartCount() {
            const countEl = document.getElementById('market-cart-count');
            if (!countEl) return;
            const total = marketCartState.items.reduce((sum, item) => sum + item.quantity, 0);
            countEl.textContent = total;
        }
        
        function getMarketProduct(key) {
            if (!key || !window.MARKET_PRODUCT_MAP) {
                return null;
            }
            return window.MARKET_PRODUCT_MAP[key] || null;
        }
        
        function computeMarketCartTotals() {
            let subtotal = 0;
            let commission = 0;
            marketCartState.items.forEach(item => {
                const product = getMarketProduct(item.key);
                if (!product) return;
                subtotal += (product.price || 0) * item.quantity;
                const lineCommission = (product.total_price - product.price) * item.quantity;
                commission += lineCommission;
            });
            return {
                subtotal,
                commission,
                total: subtotal + commission
            };
        }
        
        function renderMarketCart() {
            const itemsWrapper = document.getElementById('market-cart-items');
            const emptyState = document.getElementById('market-cart-empty');
            if (!itemsWrapper || !emptyState) return;
            
            itemsWrapper.innerHTML = '';
            if (!marketCartState.items.length) {
                emptyState.classList.remove('hidden');
                return;
            }
            emptyState.classList.add('hidden');
            
            marketCartState.items.forEach(item => {
                const product = getMarketProduct(item.key);
                if (!product) {
                    return;
                }
                const lineTotal = (product.total_price || 0) * item.quantity;
                const element = document.createElement('div');
                element.className = 'p-4 rounded-2xl border border-gray-100 bg-white shadow-sm flex gap-4';
                element.dataset.cartKey = item.key;
                element.innerHTML = `
                    <div class="flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">${product.community_name || '-'}</p>
                        <h4 class="text-base font-bold text-gray-900">${product.name}</h4>
                        <p class="text-xs text-gray-500">${product.category || 'Genel'}</p>
                        <p class="text-sm font-semibold text-gray-900 mt-2">${formatMarketPrice(product.total_price)} <span class="text-xs text-gray-500">/ adet</span></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <div class="flex items-center gap-2">
                            <button type="button" class="w-8 h-8 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" data-cart-action="decrement"><i class="fas fa-minus"></i></button>
                            <input type="text" readonly class="w-12 text-center font-semibold text-gray-900 bg-gray-50 rounded-lg border border-gray-100" value="${item.quantity}">
                            <button type="button" class="w-8 h-8 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200" data-cart-action="increment"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="text-sm font-bold text-gray-900">${formatMarketPrice(lineTotal)}</div>
                        <button type="button" class="text-xs text-red-500 font-semibold hover:underline" data-cart-action="remove">Kaldır</button>
                    </div>
                `;
                itemsWrapper.appendChild(element);
            });
            
            const totals = computeMarketCartTotals();
            const subtotalEl = document.getElementById('market-cart-subtotal');
            const commissionEl = document.getElementById('market-cart-commissions');
            const totalEl = document.getElementById('market-cart-total');
            if (subtotalEl) subtotalEl.textContent = formatMarketPrice(totals.subtotal);
            if (commissionEl) commissionEl.textContent = formatMarketPrice(totals.commission);
            if (totalEl) totalEl.textContent = formatMarketPrice(totals.total);
        }
        
        function addProductToCart(productKey, quantity = 1) {
            const product = getMarketProduct(productKey);
            if (!product) {
                showMarketToast('Ürün bulunamadı.', 'error');
                return;
            }
            const existing = marketCartState.items.find(item => item.key === productKey);
            if (existing) {
                existing.quantity += quantity;
            } else {
                marketCartState.items.push({ key: productKey, quantity: quantity });
            }
            saveMarketCartState();
            updateMarketCartCount();
            renderMarketCart();
            showMarketToast('Ürün sepetinize eklendi ✅');
        }
        
        function updateCartItemQuantity(productKey, delta) {
            const item = marketCartState.items.find(entry => entry.key === productKey);
            if (!item) return;
            item.quantity += delta;
            if (item.quantity <= 0) {
                marketCartState.items = marketCartState.items.filter(entry => entry.key !== productKey);
            }
            saveMarketCartState();
            updateMarketCartCount();
            renderMarketCart();
        }
        
        function removeCartItem(productKey) {
            marketCartState.items = marketCartState.items.filter(entry => entry.key !== productKey);
            saveMarketCartState();
            updateMarketCartCount();
            renderMarketCart();
        }
        
        function clearMarketCart() {
            marketCartState.items = [];
            saveMarketCartState();
            updateMarketCartCount();
            renderMarketCart();
        }
        
        function openMarketCartModal() {
            const modal = document.getElementById('market-cart-modal');
            if (!modal) return;
            renderMarketCart();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMarketCartModal() {
            const modal = document.getElementById('market-cart-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }
        
        function submitMarketCheckout(buttonEl) {
            if (!marketCartState.items.length) {
                showMarketToast('Sepetiniz boş.', 'error');
                return;
            }
            const name = (document.getElementById('market-customer-name')?.value || '').trim();
            const email = (document.getElementById('market-customer-email')?.value || '').trim();
            const phone = (document.getElementById('market-customer-phone')?.value || '').trim();
            const city = (document.getElementById('market-customer-city')?.value || '').trim();
            const address = (document.getElementById('market-customer-address')?.value || '').trim();
            
            if (!name || !email || !phone || !city || !address) {
                showMarketToast('Lütfen iletişim bilgilerini doldurun.', 'error');
                return;
            }
            
            const payload = {
                items: marketCartState.items,
                customer: { name, email, phone, city, address }
            };
            
            if (buttonEl) {
                buttonEl.disabled = true;
                buttonEl.classList.add('opacity-70');
                buttonEl.innerHTML = '<span class="spinner"></span> Yönlendiriliyor...';
            }
            
            fetch('/api/market_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Ödeme başlatılamadı.');
                }
                showMarketToast('Ödeme sayfasına yönlendiriliyorsunuz ✅');
                if (data.payment_form && data.payment_form.payment_form_url) {
                    window.open(data.payment_form.payment_form_url, '_blank');
                }
                closeMarketCartModal();
            })
            .catch(err => {
                showMarketToast(err.message || 'Ödeme başlatılamadı.', 'error');
            })
            .finally(() => {
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.classList.remove('opacity-70');
                    buttonEl.innerHTML = '<i class="fas fa-credit-card"></i> Ödeme Yap';
                }
            });
        }
        
        function ensureMarketCartSetup() {
            if (marketCartInitialized) return;
            loadMarketCartState();
            updateMarketCartCount();
            renderMarketCart();
            marketCartInitialized = true;
            
            const cartBtn = document.getElementById('market-cart-button');
            if (cartBtn) {
                cartBtn.addEventListener('click', () => openMarketCartModal());
            }
            const cartClose = document.getElementById('market-cart-close');
            if (cartClose) {
                cartClose.addEventListener('click', () => closeMarketCartModal());
            }
            const cartOverlay = document.getElementById('market-cart-modal');
            if (cartOverlay) {
                cartOverlay.addEventListener('click', event => {
                    if (event.target === cartOverlay) {
                        closeMarketCartModal();
                    }
                });
            }
            const cartClear = document.getElementById('market-cart-clear');
            if (cartClear) {
                cartClear.addEventListener('click', () => {
                    clearMarketCart();
                    showMarketToast('Sepet temizlendi.');
                });
            }
            const cartSubmit = document.getElementById('market-cart-submit');
            if (cartSubmit) {
                cartSubmit.addEventListener('click', () => submitMarketCheckout(cartSubmit));
            }
            const cartItemsWrapper = document.getElementById('market-cart-items');
            if (cartItemsWrapper) {
                cartItemsWrapper.addEventListener('click', event => {
                    const actionBtn = event.target.closest('[data-cart-action]');
                    if (!actionBtn) return;
                    const action = actionBtn.dataset.cartAction;
                    const parent = actionBtn.closest('[data-cart-key]');
                    if (!parent) return;
                    const key = parent.dataset.cartKey;
                    if (!key) return;
                    if (action === 'increment') {
                        updateCartItemQuantity(key, 1);
                    } else if (action === 'decrement') {
                        updateCartItemQuantity(key, -1);
                    } else if (action === 'remove') {
                        removeCartItem(key);
                    }
                });
            }
        }
        
        function openMarketModal(product) {
            const modal = document.getElementById('market-product-modal');
            if (!modal || !product) return;
            
            const imageEl = document.getElementById('market-modal-image');
            const imageWrapper = document.getElementById('market-modal-image-wrapper');
            if (imageEl && imageWrapper) {
                if (product.image_url) {
                    imageEl.src = product.image_url;
                    imageEl.alt = product.name || 'Ürün görseli';
                    imageEl.classList.remove('hidden');
                } else {
                    imageEl.src = '';
                    imageEl.alt = '';
                    imageEl.classList.add('hidden');
                }
            }
            
            const categoryEl = document.getElementById('market-modal-category');
            if (categoryEl) {
                categoryEl.textContent = product.category || 'Genel';
            }
            const titleEl = document.getElementById('market-modal-title');
            if (titleEl) {
                titleEl.textContent = product.name || '';
            }
            const descEl = document.getElementById('market-modal-description');
            if (descEl) {
                descEl.textContent = product.description || 'Bu ürün için açıklama eklenmemiş.';
            }
            const communityEl = document.getElementById('market-modal-community');
            if (communityEl) {
                communityEl.textContent = product.community_name || '-';
            }
            const stockEl = document.getElementById('market-modal-stock');
            if (stockEl) {
                stockEl.textContent = (product.stock ?? 0) + ' adet';
            }
            const priceEl = document.getElementById('market-modal-price');
            if (priceEl) {
                priceEl.textContent = formatMarketPrice(product.price);
            }
            const totalEl = document.getElementById('market-modal-total');
            if (totalEl) {
                totalEl.textContent = formatMarketPrice(product.total_price);
            }
            const iyzicoEl = document.getElementById('market-modal-iyzico');
            if (iyzicoEl) {
                iyzicoEl.textContent = formatMarketPrice(product.iyzico_commission);
            }
            const platformEl = document.getElementById('market-modal-platform');
            if (platformEl) {
                platformEl.textContent = formatMarketPrice(product.platform_commission);
            }
            const communityLink = document.getElementById('market-modal-community-link');
            if (communityLink) {
                communityLink.href = '?community=' + encodeURIComponent(product.community_slug) + '&view=overview';
            }
            
            modal.dataset.activeProductKey = product.key || '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMarketModal() {
            const modal = document.getElementById('market-product-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.dataset.activeProductKey = '';
                document.body.style.overflow = '';
            }
        }
        
        function showMarketToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3200);
        }
        
        function handleMarketShare() {
            const modal = document.getElementById('market-product-modal');
            if (!modal) return;
            const productKey = modal.dataset.activeProductKey;
            const products = (window.MARKET_DATA && window.MARKET_DATA.products) ? window.MARKET_DATA.products : [];
            const product = products.find(item => item.key === productKey);
            if (!product) return;
            
            const shareUrl = `${window.location.origin}/?community=${encodeURIComponent(product.community_slug)}&view=overview`;
            const shareText = `${product.name} - ${shareUrl}`;
            
            if (navigator.share) {
                navigator.share({
                    title: product.name,
                    text: product.description || product.name,
                    url: shareUrl
                }).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(shareText).then(() => {
                    showMarketToast('Bağlantı panoya kopyalandı ✅');
                }).catch(() => {
                    showMarketToast('Paylaşmak için bağlantıyı kopyalayamadık 😔', 'error');
                });
            } else {
                showMarketToast('Paylaşım bağlantısı: ' + shareUrl);
            }
        }
        
        function initMarketSection() {
            ensureMarketCartSetup();
            
            const marketData = window.MARKET_DATA || {};
            const products = Array.isArray(marketData.products) ? marketData.products : [];
            if (!products.length) {
                return;
            }
            
            const productsWrapper = document.getElementById('market-products');
            if (!productsWrapper) {
                return;
            }
            
            renderMarketCart();
            
            const searchInput = document.getElementById('market-search');
            const cards = Array.from(productsWrapper.querySelectorAll('[data-market-card]'));
            const emptyState = document.getElementById('market-empty');
            const resultCount = document.getElementById('market-result-count');
            const categoryButtons = document.querySelectorAll('[data-market-category-btn]');
            const shareButton = document.getElementById('market-modal-share');
            const modalClose = document.getElementById('market-modal-close');
            const modalOverlay = document.getElementById('market-product-modal');
            const modalAddCart = document.getElementById('market-modal-add-cart');
            
            if (shareButton) {
                shareButton.addEventListener('click', handleMarketShare);
            }
            if (modalClose) {
                modalClose.addEventListener('click', closeMarketModal);
            }
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(event) {
                    if (event.target === modalOverlay) {
                        closeMarketModal();
                    }
                });
            }
            if (modalAddCart) {
                modalAddCart.addEventListener('click', function() {
                    const modal = document.getElementById('market-product-modal');
                    const productKey = modal ? modal.dataset.activeProductKey : null;
                    if (productKey) {
                        addProductToCart(productKey);
                    }
                });
            }
            
            let selectedCategory = '';
            
            function updateState(visibleCount) {
                if (resultCount) {
                    resultCount.textContent = visibleCount;
                }
                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount > 0);
                }
            }
            
            function applyFilters() {
                const query = market_normalize_query(searchInput ? searchInput.value : '');
                let visible = 0;
                cards.forEach(card => {
                    const matchesCategory = !selectedCategory || card.dataset.category === selectedCategory;
                    const searchField = card.dataset.search || '';
                    const matchesSearch = !query || searchField.includes(query);
                    const shouldShow = matchesCategory && matchesSearch;
                    card.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        visible++;
                    }
                });
                updateState(visible);
            }
            
            function market_normalize_query(value) {
                if (!value) return '';
                return String(value).toLocaleLowerCase('tr-TR');
            }
            
            categoryButtons.forEach(button => {
                button.addEventListener('click', () => {
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    selectedCategory = button.dataset.category || '';
                    applyFilters();
                });
            });
            
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    applyFilters();
                });
            }
            
            productsWrapper.addEventListener('click', event => {
                const addBtn = event.target.closest('[data-add-cart]');
                if (addBtn) {
                    addProductToCart(addBtn.dataset.addCart);
                    return;
                }
                const trigger = event.target.closest('[data-open-product]');
                if (!trigger) return;
                const key = trigger.dataset.openProduct;
                const product = products.find(item => item.key === key);
                if (product) {
                    openMarketModal(product);
                }
            });
            
            applyFilters();
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeMarketModal();
                const menu = document.getElementById('mobile-menu');
                if (menu && !menu.classList.contains('hidden')) {
                    toggleMobileMenu();
                }
            }
        });
        
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('imageModal');
            if (modal && e.target === modal && !e.target.closest('img') && !e.target.closest('button')) {
                closeImageModal();
            }
        });
        
        // QR Kod Fonksiyonları
        function showQRCode(type, id, title, communityId = null) {
            const modal = document.getElementById('qrCodeModal');
            const qrImage = document.getElementById('qrCodeImage');
            const qrTitle = document.getElementById('qrCodeTitle');
            const qrContent = document.getElementById('qrCodeContent');
            
            if (!modal || !qrImage || !qrTitle || !qrContent) return;
            
            qrTitle.textContent = title + ' QR Kodu';
            qrContent.textContent = 'Yükleniyor...';
            qrImage.src = '';
            qrImage.style.display = 'none';
            
            // QR kod URL'i oluştur - API path'i düzelt
            let apiPath = window.location.pathname.includes('/public/') ? '../api/qr_code.php' : 'api/qr_code.php';
            let qrUrl = apiPath + '?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&size=300';
            if (type === 'event' && communityId) {
                qrUrl += '&community_id=' + encodeURIComponent(communityId);
            }
            
            // QR kod içeriği
            let qrContentText = '';
            if (type === 'community') {
                qrContentText = 'fourkampus://community/' + id;
            } else if (type === 'event') {
                qrContentText = 'fourkampus://event/' + (communityId || id) + '/' + id;
            }
            qrContent.textContent = qrContentText;
            
            // QR kod görselini yükle
            qrImage.onload = function() {
                qrImage.style.display = 'block';
            };
            qrImage.onerror = function() {
                qrImage.style.display = 'none';
                qrContent.textContent = 'QR kod yüklenemedi. Lütfen tekrar deneyin.';
            };
            qrImage.src = qrUrl;
            
            modal.classList.remove('hidden');
        }
        
        function closeQRCodeModal() {
            const modal = document.getElementById('qrCodeModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        // QR kod modal'ını kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQRCodeModal();
            }
        });
    </script>
    
    <!-- QR Kod Modal -->
    <div id="qrCodeModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full mx-4 p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <h3 id="qrCodeTitle" class="text-xl font-bold text-gray-900 dark:text-white">QR Kod</h3>
                <button onclick="closeQRCodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="text-center space-y-4">
                <div class="flex justify-center">
                    <img id="qrCodeImage" src="" alt="QR Code" class="w-64 h-64 bg-white p-4 rounded-xl shadow-lg" style="display: none;">
                </div>
                <p id="qrCodeContent" class="text-sm text-gray-600 dark:text-gray-400 font-mono break-all px-4"></p>
                <button onclick="closeQRCodeModal()" class="w-full px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl">
                    Kapat
                </button>
            </div>
        </div>
    </div>

</body>
</html>

