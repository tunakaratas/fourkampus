<?php
// =================================================================
// TEMPLATE SENKRONİZASYON SİSTEMİ
// =================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../bootstrap/community_stubs.php';

use function UniPanel\Community\sync_community_stubs;

/**
 * Template stub dosyalarını tüm topluluklara senkronize eder
 */
function syncTemplates() {
    $communitiesDir = __DIR__ . '/../../communities';
    
    if (!is_dir($communitiesDir)) {
        return ['success' => false, 'message' => 'Communities dizini bulunamadı'];
    }
    
    $syncedFiles = [];
    $errors = [];
    
    $communities = array_filter(scandir($communitiesDir), function($item) use ($communitiesDir) {
        return is_dir($communitiesDir . '/' . $item) &&
               !in_array($item, ['.', '..', 'assets', 'public']) &&
               !str_starts_with($item, '.');
    });
    
    foreach ($communities as $community) {
        $communityPath = $communitiesDir . '/' . $community;
        $result = sync_community_stubs($communityPath);
        
        $relative = static function ($paths) use ($communitiesDir) {
            return array_map(function ($path) use ($communitiesDir) {
                return ltrim(str_replace($communitiesDir . '/', '', $path), '/');
            }, $paths);
        };
        
        if ($result['success']) {
            $syncedFiles = array_merge($syncedFiles, $relative($result['written']));
        } else {
            $syncedFiles = array_merge($syncedFiles, $relative($result['written']));
            $errors = array_merge($errors, $relative($result['errors']));
        }
    }
    
    return [
        'success' => empty($errors),
        'synced' => $syncedFiles,
        'errors' => $errors,
        'total_communities' => count($communities)
    ];
}

/**
 * Belirli bir topluluğa template dosyalarını kopyalar
 */
function syncToCommunity($communityName) {
    require_once __DIR__ . '/security_helper.php';
    
    // Community name sanitization
    $sanitized_name = sanitizeCommunityName($communityName);
    if (!$sanitized_name) {
        return ['success' => false, 'message' => 'Geçersiz topluluk adı: ' . $communityName];
    }
    
    $communitiesDir = __DIR__ . '/../../communities';
    $communityPath = sanitizePath($communitiesDir . '/' . $sanitized_name);
    
    if (!$communityPath || !is_dir($communityPath)) {
        return ['success' => false, 'message' => 'Topluluk bulunamadı: ' . $sanitized_name];
    }
    
    $result = sync_community_stubs($communityPath);
    $synced = array_map(function ($path) use ($communityPath) {
        return ltrim(str_replace($communityPath . '/', '', $path), '/');
    }, $result['written']);
    $errors = array_map(function ($path) use ($communityPath) {
        return ltrim(str_replace($communityPath . '/', '', $path), '/');
    }, $result['errors']);
    
    return [
        'success' => $result['success'],
        'synced' => $synced,
        'errors' => $errors
    ];
}

/**
 * Yeni topluluk oluşturulduğunda template dosyalarını kopyalar
 */
function setupNewCommunity($communityName) {
    require_once __DIR__ . '/security_helper.php';
    
    // Community name sanitization
    $sanitized_name = sanitizeCommunityName($communityName);
    if (!$sanitized_name) {
        return ['success' => false, 'message' => 'Geçersiz topluluk adı: ' . $communityName];
    }
    
    $communitiesDir = __DIR__ . '/../../communities';
    $communityPath = $communitiesDir . '/' . $sanitized_name;
    
    // Path sanitization
    $realpath = realpath($communitiesDir);
    if (!$realpath) {
        return ['success' => false, 'message' => 'Communities dizini bulunamadı'];
    }
    
    $communityPath = $realpath . '/' . $sanitized_name;
    
    // Path traversal kontrolü
    if (strpos($communityPath, $realpath) !== 0) {
        return ['success' => false, 'message' => 'Geçersiz topluluk yolu'];
    }
    
    // Topluluk dizinini oluştur (güvenli izinlerle)
    if (!is_dir($communityPath)) {
        mkdir($communityPath, 0755, true);
    }
    
    $result = sync_community_stubs($communityPath);
    $synced = array_map(function ($path) use ($communityPath) {
        return ltrim(str_replace($communityPath . '/', '', $path), '/');
    }, $result['written']);
    $errors = array_map(function ($path) use ($communityPath) {
        return ltrim(str_replace($communityPath . '/', '', $path), '/');
    }, $result['errors']);
    
    // Veritabanı dosyasını kopyala (varsa)
    $dbSource = $communitiesDir . '/Ünigfb_topluluğu/unipanel.sqlite';
    if (file_exists($dbSource)) {
        if (copy($dbSource, $communityPath . '/unipanel.sqlite')) {
            $synced[] = 'unipanel.sqlite';
        } else {
            $errors[] = 'unipanel.sqlite kopyalanamadı';
        }
    }
    
    return [
        'success' => $result['success'] && empty($errors),
        'synced' => $synced,
        'errors' => $errors
    ];
}

// CLI veya web arayüzü için
if (php_sapi_name() === 'cli') {
    // Komut satırından çalıştırıldığında
    $result = syncTemplates();
    echo json_encode($result, JSON_PRETTY_PRINT);
} elseif (isset($_GET['action'])) {
    // Web arayüzünden çalıştırıldığında - Authentication gerekli
    require_once __DIR__ . '/security_helper.php';
    
    // Basit authentication kontrolü (token veya session)
    $auth_token = $_GET['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $valid_token = getenv('SYSTEM_SCRIPT_TOKEN') ?: 'change_this_token_in_production';
    
    if ($auth_token !== $valid_token) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $action = $_GET['action'] ?? 'sync_all';
    
    switch ($action) {
        case 'sync_all':
            $result = syncTemplates();
            break;
        case 'sync_community':
            $community = $_GET['community'] ?? '';
            if ($community) {
                $result = syncToCommunity($community);
            } else {
                $result = ['success' => false, 'message' => 'Topluluk adı belirtilmedi'];
            }
            break;
        case 'setup_new':
            $community = $_GET['community'] ?? '';
            if ($community) {
                $result = setupNewCommunity($community);
            } else {
                $result = ['success' => false, 'message' => 'Topluluk adı belirtilmedi'];
            }
            break;
        default:
            $result = ['success' => false, 'message' => 'Geçersiz işlem'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
// Template'den çağrıldığında hiçbir output vermez
?>
