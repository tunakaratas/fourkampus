<?php
/**
 * Otomatik Template Senkronizasyon Sistemi
 * Bu script template dosyalarındaki değişiklikleri izler ve otomatik senkronize eder
 */

require_once __DIR__ . '/security_helper.php';

// CLI kontrolü
requireCLI();

// Yapılandırma
$templates_dir = sanitizePath(__DIR__ . '/../../templates/');
$sync_script = sanitizePath(__DIR__ . '/sync_templates.php');
$log_file = sanitizePath(__DIR__ . '/../logs/auto_sync.log');
$cache_file = sanitizePath(__DIR__ . '/../logs/template_cache.json');

if (!$templates_dir || !$sync_script) {
    die("Error: Invalid paths\n");
}

// Log klasörünü oluştur
if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

/**
 * Log mesajı yaz
 */
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

/**
 * Template dosyalarının hash'lerini hesapla
 */
function get_template_hashes($templates_dir) {
    $hashes = [];
    $files = ['template_index.php', 'template_login.php', 'template_loading.php'];
    
    foreach ($files as $file) {
        // Path sanitization
        $filepath = realpath($templates_dir . '/' . basename($file));
        if ($filepath && file_exists($filepath) && strpos($filepath, $templates_dir) === 0) {
            $hashes[$file] = md5_file($filepath);
        }
    }
    
    return $hashes;
}

/**
 * Cache'i oku
 */
function read_cache($cache_file) {
    if (file_exists($cache_file)) {
        $content = file_get_contents($cache_file);
        return json_decode($content, true) ?: [];
    }
    return [];
}

/**
 * Cache'i yaz
 */
function write_cache($cache_file, $data) {
    file_put_contents($cache_file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Senkronizasyon script'ini çalıştır - Güvenli exec
 */
function run_sync($sync_script) {
    // Path sanitization
    $sanitized_script = sanitizePath($sync_script);
    if (!$sanitized_script) {
        return ['success' => false, 'error' => 'Invalid script path'];
    }
    
    // PHP binary path
    $php_binary = escapeshellarg(PHP_BINARY);
    $script_path = escapeshellarg($sanitized_script);
    
    $output = [];
    $return_var = 0;
    
    // Güvenli exec - sadece PHP binary ve script path'i kullan
    exec("$php_binary $script_path 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        return ['success' => true, 'output' => implode("\n", $output)];
    } else {
        return ['success' => false, 'error' => implode("\n", $output)];
    }
}

// Ana işlem
write_log("=== Otomatik Senkronizasyon Başlatıldı ===");

// Mevcut hash'leri al
$current_hashes = get_template_hashes($templates_dir);

// Önceki hash'leri oku
$cached_hashes = read_cache($cache_file);

// Değişiklikleri kontrol et
$changed_files = [];
foreach ($current_hashes as $file => $hash) {
    if (!isset($cached_hashes[$file]) || $cached_hashes[$file] !== $hash) {
        $changed_files[] = $file;
    }
}

if (empty($changed_files)) {
    write_log("Değişiklik tespit edilmedi. Senkronizasyon gerekmiyor.");
} else {
    write_log("Değişen dosyalar: " . implode(', ', $changed_files));
    write_log("Senkronizasyon başlatılıyor...");
    
    $result = run_sync($sync_script);
    
    if ($result['success']) {
        write_log("✓ Senkronizasyon başarılı!");
        write_log("Çıktı: " . $result['output']);
        
        // Cache'i güncelle
        write_cache($cache_file, $current_hashes);
        write_log("✓ Cache güncellendi.");
    } else {
        write_log("✗ Senkronizasyon hatası: " . $result['error']);
    }
}

write_log("=== Otomatik Senkronizasyon Tamamlandı ===\n");

