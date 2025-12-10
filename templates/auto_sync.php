<?php
/**
 * Otomatik Template Senkronizasyon Script'i
 * Bu script template dosyalarındaki değişiklikleri otomatik olarak tüm topluluklara uygular
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/partials/logging.php';
require_once __DIR__ . '/partials/security_headers.php';
require_once __DIR__ . '/partials/path_guard.php';

set_security_headers();

// Template dosyalarının yolları
$template_files = [
    'index.php' => __DIR__ . '/template_index.php',
    'login.php' => __DIR__ . '/template_login.php',
    'loading.php' => __DIR__ . '/template_loading.php'
];

// Communities klasörü yolu
$communities_dir = __DIR__ . '/../communities/';

// Log dosyası
$log_file = __DIR__ . '/../system/logs/template_sync.log';

// Log dizinini oluştur
if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

/**
 * Log yazma fonksiyonu
 */
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    if (function_exists('mask_sensitive_log_data')) {
        $message = mask_sensitive_log_data($message);
    }
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Web erişimleri için doğrulama
 */
function ensureAutoSyncAuthorized(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Güvenlik: Ortam değişkeni yoksa varsayılan olarak sadece localhost'a izin ver
    $allowedIpsEnv = getenv('TEMPLATE_SYNC_ALLOWED_IPS');
    $allowedIps = ($allowedIpsEnv !== false && $allowedIpsEnv !== '') ? $allowedIpsEnv : '127.0.0.1,::1';
    
    $allowed = array_filter(array_map('trim', explode(',', $allowedIps)));
    if ($clientIp === '' || !in_array($clientIp, $allowed, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'IP reddedildi']);
        exit;
    }

    $expectedToken = getenv('TEMPLATE_SYNC_TOKEN');
    // Güvenlik: Token tanımlı değilse erişimi tamamen kapat
    if ($expectedToken === false || $expectedToken === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Sistem yapılandırma hatası: Token tanımlanmamış']);
        exit;
    }

    $providedToken = $_GET['sync_token'] ?? '';
    if (!hash_equals($expectedToken, (string)$providedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }
}

ensureAutoSyncAuthorized();

/**
 * MD5 hash hesaplama fonksiyonu
 */
function getFileHash($file_path) {
    if (!file_exists($file_path)) {
        return null;
    }
    return md5_file($file_path);
}

/**
 * Template dosyasını topluluk klasörüne kopyalama
 */
function syncTemplateToCommunity($template_path, $community_path, $filename) {
    if (!file_exists($template_path)) {
        writeLog("HATA: Template dosyası bulunamadı: $template_path");
        return false;
    }
    
    if (!is_dir($community_path)) {
        writeLog("HATA: Topluluk klasörü bulunamadı: $community_path");
        return false;
    }
    
    $target_path = $community_path . '/' . $filename;
    
    // Dosyayı kopyala
    if (copy($template_path, $target_path)) {
        writeLog("BAŞARILI: $filename -> " . basename($community_path));
        return true;
    } else {
        writeLog("HATA: $filename kopyalanamadı -> " . basename($community_path));
        return false;
    }
}

/**
 * Logo dosyasını topluluk klasörüne kopyalama
 */
function syncLogoToCommunity($community_path) {
    $source_logo = __DIR__ . '/../assets/images/logo_tr.png';
    $target_assets_dir = $community_path . '/assets/images/';
    $target_logo = $target_assets_dir . 'logo_tr.png';
    
    // Kaynak logo dosyası var mı kontrol et
    if (!file_exists($source_logo)) {
        writeLog("UYARI: Kaynak logo dosyası bulunamadı: $source_logo");
        return false;
    }
    
    // Hedef assets/images klasörünü oluştur
    if (!is_dir($target_assets_dir)) {
        if (!mkdir($target_assets_dir, 0755, true)) {
            writeLog("HATA: Assets/images klasörü oluşturulamadı: $target_assets_dir");
            return false;
        }
    }
    
    // Partner-logos klasörünü oluştur
    $partner_logos_dir = $community_path . '/assets/images/partner-logos/';
    if (!is_dir($partner_logos_dir)) {
        if (mkdir($partner_logos_dir, 0755, true)) {
            // Güvenlik: Klasör izinleri 0755 (rwxr-xr-x)
            chmod($partner_logos_dir, 0755);
            writeLog("BAŞARILI: Partner-logos klasörü oluşturuldu: $partner_logos_dir");
        } else {
            writeLog("HATA: Partner-logos klasörü oluşturulamadı: $partner_logos_dir");
        }
    }
    
    // Events klasörlerini oluştur
    $events_images_dir = $community_path . '/assets/images/events/';
    $events_videos_dir = $community_path . '/assets/videos/events/';
    if (!is_dir($events_images_dir)) {
        if (mkdir($events_images_dir, 0755, true)) {
            // Güvenlik: Klasör izinleri 0755 (rwxr-xr-x)
            chmod($events_images_dir, 0755);
            writeLog("BAŞARILI: Events images klasörü oluşturuldu: $events_images_dir");
        } else {
            writeLog("HATA: Events images klasörü oluşturulamadı: $events_images_dir");
        }
    }
    if (!is_dir($events_videos_dir)) {
        if (mkdir($events_videos_dir, 0755, true)) {
            // Güvenlik: Klasör izinleri 0755 (rwxr-xr-x)
            chmod($events_videos_dir, 0755);
            writeLog("BAŞARILI: Events videos klasörü oluşturuldu: $events_videos_dir");
        } else {
            writeLog("HATA: Events videos klasörü oluşturulamadı: $events_videos_dir");
        }
    }
    
    // Logo dosyasını kopyala
    if (copy($source_logo, $target_logo)) {
        writeLog("BAŞARILI: Logo kopyalandı -> " . basename($community_path));
        return true;
    } else {
        writeLog("HATA: Logo kopyalanamadı -> " . basename($community_path));
        return false;
    }
}

/**
 * Tüm toplulukları senkronize et
 */
function syncAllCommunities() {
    global $template_files, $communities_dir;
    
    $synced_count = 0;
    $error_count = 0;
    $total_communities = 0;
    
    writeLog("=== OTOMATIK TEMPLATE SENKRONIZASYON BAŞLADI ===");
    
    // Communities klasöründeki tüm klasörleri tara
    if (!is_dir($communities_dir)) {
        writeLog("HATA: Communities klasörü bulunamadı: $communities_dir");
        return false;
    }
    
    $communities = array_diff(scandir($communities_dir), ['.', '..']);
    
    foreach ($communities as $community) {
        $community_path = $communities_dir . $community;
        
        // Sadece klasörleri işle
        if (!is_dir($community_path)) {
            continue;
        }
        
        // Sistem klasörlerini atla
        if (in_array($community, ['assets', 'templates', 'system', 'docs'])) {
            continue;
        }
        
        $total_communities++;
        writeLog("Topluluk işleniyor: $community");
        
        // Her template dosyasını senkronize et
        foreach ($template_files as $filename => $template_path) {
            if (syncTemplateToCommunity($template_path, $community_path, $filename)) {
                $synced_count++;
            } else {
                $error_count++;
            }
        }
        
        // Logo dosyasını da senkronize et
        if (syncLogoToCommunity($community_path)) {
            $synced_count++;
        } else {
            $error_count++;
        }
    }
    
    writeLog("=== SENKRONIZASYON TAMAMLANDI ===");
    writeLog("Toplam Topluluk: $total_communities");
    writeLog("Başarılı Kopyalama: $synced_count");
    writeLog("Hata Sayısı: $error_count");
    
    return [
        'success' => $error_count === 0,
        'total_communities' => $total_communities,
        'synced_count' => $synced_count,
        'error_count' => $error_count
    ];
}

/**
 * Template değişikliklerini kontrol et
 */
function checkTemplateChanges() {
    global $template_files;
    
    $changes_detected = false;
    $hash_file = __DIR__ . '/template_hashes.json';
    
    // Mevcut hash'leri yükle
    $current_hashes = [];
    if (file_exists($hash_file)) {
        $current_hashes = json_decode(file_get_contents($hash_file), true) ?: [];
    }
    
    // Her template dosyasının hash'ini kontrol et
    foreach ($template_files as $filename => $template_path) {
        $new_hash = getFileHash($template_path);
        $old_hash = $current_hashes[$filename] ?? null;
        
        if ($new_hash !== $old_hash) {
            writeLog("DEĞİŞİKLİK TESPİT EDİLDİ: $filename");
            $changes_detected = true;
            $current_hashes[$filename] = $new_hash;
        }
    }
    
    // Hash'leri kaydet
    file_put_contents($hash_file, json_encode($current_hashes, JSON_PRETTY_PRINT));
    
    return $changes_detected;
}

// Ana işlem
try {
    // Template değişikliklerini kontrol et
    if (checkTemplateChanges()) {
        writeLog("Template değişiklikleri tespit edildi, senkronizasyon başlatılıyor...");
        
        // Tüm toplulukları senkronize et
        $result = syncAllCommunities();
        
        if ($result['success']) {
            writeLog("✅ Tüm topluluklar başarıyla güncellendi!");
        } else {
            writeLog("⚠️ Bazı topluluklarda hata oluştu!");
        }
        
        // Sonuçları JSON olarak döndür
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        writeLog("Template değişikliği tespit edilmedi.");
        echo json_encode(['message' => 'Template değişikliği tespit edilmedi.', 'success' => true]);
    }
    
} catch (Exception $e) {
    writeLog("KRİTİK HATA: " . $e->getMessage());
    header('Content-Type: application/json');
    // Güvenlik: Sensitive bilgi sızıntısını önle
    tpl_error_log("Auto sync error: " . $e->getMessage());
    echo json_encode(['error' => 'Senkronizasyon hatası oluştu', 'success' => false]);
}
?>
