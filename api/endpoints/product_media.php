<?php
/**
 * Public Product Media API Endpoint
 * Mobil uygulamalar için ürün görsellerini serve eder
 * GET /api/product_media.php?file=product_xxx.jpg&community_id=community_folder
 */

// PROJECT_ROOT tanımla (eğer yoksa)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';

// setSecureCORS ve checkRateLimit fonksiyonlarını kontrol et
if (function_exists('setSecureCORS')) {
    setSecureCORS();
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting (eğer fonksiyon varsa)
if (function_exists('checkRateLimit') && !checkRateLimit(200, 60)) {
    http_response_code(429);
    header('Content-Type: text/plain');
    exit('Çok fazla istek. Lütfen daha sonra tekrar deneyin.');
}

$file = $_GET['file'] ?? '';
$community_id = $_GET['community_id'] ?? '';

// Dosya adı validation (güvenlik)
if (empty($file) || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Geçersiz dosya adı');
}

// Community ID validation
if (empty($community_id) || !preg_match('/^[A-Za-z0-9_-]+$/', $community_id)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Geçersiz topluluk ID');
}

// Product storage base directory'i bul
$product_storage_base = null;

// Storage helper fonksiyonlarını yükle
$storage_file = __DIR__ . '/../templates/partials/storage.php';
if (file_exists($storage_file)) {
    require_once $storage_file;
}

// Community ID'yi set et (storage.php fonksiyonu için gerekli)
if (!defined('COMMUNITY_ID')) {
    define('COMMUNITY_ID', $community_id);
}

// Önce template fonksiyonlarını kullanmayı dene
if (function_exists('tpl_get_product_storage_base_dir')) {
    try {
        $product_storage_base = tpl_get_product_storage_base_dir();
    } catch (Exception $e) {
        error_log("Product Media API: tpl_get_product_storage_base_dir hatası: " . $e->getMessage());
        $product_storage_base = null;
    }
}

// Fallback: storage/private_uploads/{community_id}/products/ klasörünü kullan
if (!$product_storage_base || !is_dir($product_storage_base)) {
    $project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : realpath(__DIR__ . '/..');
    if (!$project_root) {
        $project_root = dirname(__DIR__);
    }
    $product_storage_base = rtrim($project_root . '/storage/private_uploads/' . $community_id . '/products', '/') . '/';
    
    if (!is_dir($product_storage_base)) {
        // Klasör yoksa oluştur
        @mkdir($product_storage_base, 0700, true);
    }
}

if (!$product_storage_base || !is_dir($product_storage_base)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Görsel depolama klasörü bulunamadı');
}

// Dosya yolunu oluştur
$file_path = $product_storage_base . $file;
$real_path = realpath($file_path);

// Güvenlik kontrolü: Path traversal koruması
$base_real = realpath($product_storage_base);
if (!$real_path || !$base_real || strpos($real_path, $base_real) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Dosya bulunamadı');
}

// Dosya var mı kontrol et
if (!is_file($real_path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Dosya bulunamadı');
}

// MIME type'ı belirle
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $real_path) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

// İzin verilen MIME type'ları kontrol et
$allowed_mimes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp'
];

if (!in_array($mime, $allowed_mimes)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Geçersiz dosya tipi');
}

// Cache headers
header('Cache-Control: public, max-age=31536000, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($real_path)) . ' GMT');
header('ETag: "' . md5_file($real_path) . '"');

// Content-Type ve Content-Length
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real_path));

// Dosyayı oku ve gönder
readfile($real_path);
exit;

