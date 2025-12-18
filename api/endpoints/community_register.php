<?php
/**
 * Marketing API - Community Registration Endpoint
 * POST /api/community_register.php - Topluluk kayıt işlemi
 */

// Error reporting (geçici - debug için)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../bootstrap/community_stubs.php';

use function UniPanel\Community\sync_community_stubs;

// Rate limiting basit kontrolü
$rate_limit_file = sys_get_temp_dir() . '/unipanel_register_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rate_limit_data = @json_decode(@file_get_contents($rate_limit_file), true) ?: ['count' => 0, 'time' => 0];
$current_time = time();

if ($rate_limit_data['time'] < $current_time - 3600) {
    $rate_limit_data = ['count' => 0, 'time' => $current_time];
}

if ($rate_limit_data['count'] >= 5) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla kayıt denemesi. Lütfen 1 saat sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rate_limit_data['count']++;
$rate_limit_data['time'] = $current_time;
@file_put_contents($rate_limit_file, json_encode($rate_limit_data));

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Yardımcı fonksiyonlar
function cleanCommunityName($name) {
    $name = trim($name);
    $name = preg_replace('/\s*topluluğu\s*/i', ' ', $name);
    $name = preg_replace('/\s*topluluk\s*/i', ' ', $name);
    $name = trim($name);
    return $name;
}

function formatFolderName($name) {
    $name = cleanCommunityName($name);
    $turkish_chars = ['Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', 'ç', 'ğ', 'ı', 'ö', 'ş', 'ü'];
    $english_chars = ['C', 'G', 'I', 'O', 'S', 'U', 'c', 'g', 'i', 'o', 's', 'u'];
    $name = str_replace($turkish_chars, $english_chars, $name);
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_');
    return $name;
}

function generateCommunityCode($source_name) {
    $source_name = cleanCommunityName($source_name);
    $turkish_chars = ['Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', 'ç', 'ğ', 'ı', 'ö', 'ş', 'ü'];
    $english_chars = ['C', 'G', 'I', 'O', 'S', 'U', 'c', 'g', 'i', 'o', 's', 'u'];
    $name = str_replace($turkish_chars, $english_chars, $source_name);
    $name = preg_replace('/[^A-Za-z]/', '', $name);
    $name = strtoupper($name);
    $code = substr($name, 0, 3);
    if (strlen($code) < 3) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        while (strlen($code) < 3) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
    }
    $code .= rand(0, 9);
    return $code;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    // CSRF koruması
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            sendResponse(false, null, null, 'CSRF token geçersiz');
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $community_name = trim($input['community_name'] ?? '');
    $folder_name = trim($input['folder_name'] ?? '');
    $admin_username = trim($input['admin_username'] ?? '');
    $admin_password = trim($input['admin_password'] ?? '');
    $admin_email = trim($input['admin_email'] ?? '');
    $admin_phone = trim($input['admin_phone'] ?? '');
    $selected_university = trim($input['university'] ?? '');
    
    // Validasyon
    if (empty($community_name)) {
        sendResponse(false, null, null, 'Topluluk adı gerekli');
    }
    
    if (empty($admin_username)) {
        sendResponse(false, null, null, 'Admin kullanıcı adı gerekli');
    }
    
    // Güçlü şifre kontrolü
    $passwordValidation = validatePassword($admin_password);
    if (!$passwordValidation['valid']) {
        sendResponse(false, null, null, $passwordValidation['message']);
    }
    
    if (empty($selected_university)) {
        sendResponse(false, null, null, 'Üniversite seçimi gerekli');
    }
    
    if (empty($admin_phone)) {
        sendResponse(false, null, null, 'Başkan telefon numarası gerekli');
    }
    
    // Telefon numarası validasyonu
    $clean_phone = preg_replace('/\s+/', '', $admin_phone);
    if (!validatePhone($clean_phone)) {
        sendResponse(false, null, null, 'Telefon numarası 5 ile başlamalı ve 10 haneli olmalıdır');
    }
    $admin_phone = $clean_phone;
    
    if (!empty($admin_email) && !validateEmail($admin_email)) {
        sendResponse(false, null, null, 'Geçerli bir email adresi giriniz');
    }
    
    // Input sanitization
    $community_name = sanitizeInput($community_name, 'string');
    $admin_username = sanitizeInput($admin_username, 'string');
    $admin_email = !empty($admin_email) ? sanitizeInput($admin_email, 'email') : '';
    $selected_university = sanitizeInput($selected_university, 'string');
    
    // Topluluk adını temizle
    $community_name = cleanCommunityName($community_name);
    
    // Klasör adını oluştur ve güvenli hale getir
    if (empty($folder_name)) {
        $folder_name = formatFolderName($community_name);
    } else {
        $folder_name = formatFolderName($folder_name);
    }
    
    // Path traversal koruması
    try {
        $folder_name = sanitizeCommunityId($folder_name);
    } catch (Exception $e) {
        sendResponse(false, null, null, 'Geçersiz klasör adı: ' . $e->getMessage());
    }
    
    // Superadmin veritabanına talep kaydet
    $superadmin_db = __DIR__ . '/../unipanel.sqlite';
    
    // Veritabanı dosyasını oluştur (yoksa)
    if (!file_exists($superadmin_db)) {
        touch($superadmin_db);
        chmod($superadmin_db, 0666);
    }
    
    $db = new SQLite3($superadmin_db);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Community requests tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS community_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        community_name TEXT NOT NULL,
        folder_name TEXT NOT NULL,
        university TEXT NOT NULL,
        admin_username TEXT NOT NULL,
        admin_password_hash TEXT NOT NULL,
        admin_email TEXT,
        admin_phone TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME,
        processed_by TEXT
    )");
    
    // Eğer admin_phone kolonu yoksa ekle (mevcut tablolar için)
    try {
        $db->exec("ALTER TABLE community_requests ADD COLUMN admin_phone TEXT");
    } catch (Exception $e) {
        // Kolon zaten varsa hata vermez
    }
    
    // Aynı topluluk adı veya klasör adı ile bekleyen talep var mı kontrol et
    $check_stmt = $db->prepare("SELECT id FROM community_requests WHERE (community_name = ? OR folder_name = ?) AND status = 'pending'");
    $check_stmt->bindValue(1, $community_name, SQLITE3_TEXT);
    $check_stmt->bindValue(2, $folder_name, SQLITE3_TEXT);
    $existing = $check_stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($existing) {
        $db->close();
        sendResponse(false, null, null, 'Bu topluluk için zaten bekleyen bir talep var. Lütfen onay bekleyin.');
    }
    
    // Şifreyi hashle
    $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    // Talebi kaydet
    $insert_stmt = $db->prepare("INSERT INTO community_requests (community_name, folder_name, university, admin_username, admin_password_hash, admin_email, admin_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $insert_stmt->bindValue(1, $community_name, SQLITE3_TEXT);
    $insert_stmt->bindValue(2, $folder_name, SQLITE3_TEXT);
    $insert_stmt->bindValue(3, $selected_university, SQLITE3_TEXT);
    $insert_stmt->bindValue(4, $admin_username, SQLITE3_TEXT);
    $insert_stmt->bindValue(5, $admin_password_hash, SQLITE3_TEXT);
    $insert_stmt->bindValue(6, $admin_email, SQLITE3_TEXT);
    $insert_stmt->bindValue(7, $admin_phone, SQLITE3_TEXT);
    $insert_stmt->execute();
    
    $request_id = $db->lastInsertRowID();
    $db->close();
    
    // Topluluk klasörünü ve login sayfasını oluştur
    $communities_dir = realpath(__DIR__ . '/../communities/');
    if (!$communities_dir) {
        sendResponse(false, null, null, 'Communities dizini bulunamadı');
    }
    
    // Path traversal koruması - realpath kullan
    $community_path = realpath($communities_dir . '/' . $folder_name);
    if ($community_path && strpos($community_path, $communities_dir) !== 0) {
        sendResponse(false, null, null, 'Geçersiz klasör yolu');
    }
    
    $community_path = $communities_dir . '/' . $folder_name;
    $create_error = null;
    
    try {
        // Klasör oluştur (güvenli izinlerle)
        if (!is_dir($community_path)) {
            if (!@mkdir($community_path, 0755, true)) {
                throw new Exception('Topluluk klasörü oluşturulamadı');
            }
            @chmod($community_path, 0755); // Güvenli izinler
        }
        
        // Stub dosyalarını oluştur (login.php, index.php, loading.php)
        try {
            $stubResult = sync_community_stubs($community_path);
            
            if (!$stubResult['success']) {
                // Hata olsa bile devam et, sadece logla
                error_log('Community stubs oluşturulurken hata: ' . implode(', ', $stubResult['errors'] ?? []));
            }
        } catch (Exception $stubEx) {
            // sync_community_stubs hatası olsa bile devam et
            error_log('sync_community_stubs hatası: ' . $stubEx->getMessage());
        }
        
    } catch (Exception $e) {
        $create_error = $e->getMessage();
        error_log('Topluluk klasörü oluşturma hatası: ' . $create_error);
        // Hata olsa bile talebi kaydet, superadmin düzeltebilir
    }
    
    sendResponse(true, [
        'request_id' => (int)$request_id,
        'community_name' => $community_name,
        'folder_name' => $folder_name,
        'login_url' => '../communities/' . $folder_name . '/login.php',
        'status' => 'pending',
        'message' => 'Topluluk kayıt talebiniz alındı. Superadmin onayından sonra topluluğunuz oluşturulacaktır.'
    ], 'Topluluk kayıt talebiniz başarıyla gönderildi! Giriş sayfanız hazır. Superadmin onayından sonra topluluğunuz aktif olacaktır.');
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('Kayıt işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

