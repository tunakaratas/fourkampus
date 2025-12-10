<?php
/**
 * Mobil API - Email Verification Code Sending Endpoint
 * POST /api/send_verification_code.php - E-posta doğrulama kodu gönderme
 */

// Error reporting - sadece development için
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production'da hataları gösterme
ini_set('log_errors', 1);

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = null, $message = null, $error = null) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Öğrenci e-postası domain kontrolü - sadece @ogr.bandirma.edu.tr
function isStudentEmail($email) {
    $emailLower = strtolower($email);
    return strpos($emailLower, '@ogr.bandirma.edu.tr') !== false;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        sendResponse(false, null, null, 'Request body boş');
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, null, 'Geçersiz JSON formatı: ' . json_last_error_msg());
    }
    
    if (!isset($input['email']) || empty(trim($input['email']))) {
        sendResponse(false, null, null, 'E-posta adresi gerekli');
    }
    
    $email = sanitizeInput(trim($input['email']), 'email');
    
    // E-posta format kontrolü
    if (!validateEmail($email)) {
        sendResponse(false, null, null, 'Geçersiz e-posta formatı');
    }
    
    // Öğrenci e-postası kontrolü
    if (!isStudentEmail($email)) {
        sendResponse(false, null, null, 'Sadece @ogr.bandirma.edu.tr e-posta adresleri kabul edilir');
    }
    
    // Genel sistem veritabanı yolu
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    // Veritabanı dizinini kontrol et ve oluştur
    $db_dir = dirname($system_db_path);
    if (!is_dir($db_dir)) {
        if (!mkdir($db_dir, 0755, true)) {
            sendResponse(false, null, null, 'Veritabanı dizini oluşturulamadı');
        }
    }
    
    // Veritabanı dosyasını oluştur (yoksa)
    if (!file_exists($system_db_path)) {
        touch($system_db_path);
        chmod($system_db_path, 0666);
    }
    
    $db = new SQLite3($system_db_path);
    if (!$db) {
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
    }
    
    @$db->exec('PRAGMA journal_mode = DELETE');
    
    // Email verification codes tablosunu oluştur
    $createTableResult = @$db->exec("CREATE TABLE IF NOT EXISTS email_verification_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        code TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    if ($createTableResult === false) {
        error_log("Table creation failed: " . $db->lastErrorMsg());
    }
    
    // Eski kodları temizle (24 saatten eski)
    @$db->exec("DELETE FROM email_verification_codes WHERE expires_at < datetime('now') OR used = 1");
    
    // Aynı e-posta için son 1 dakika içinde kod gönderilmiş mi kontrol et
    $check_stmt = $db->prepare("SELECT created_at FROM email_verification_codes WHERE email = ? AND created_at > datetime('now', '-1 minute') ORDER BY created_at DESC LIMIT 1");
    if (!$check_stmt) {
        $db->close();
        sendResponse(false, null, null, 'Veritabanı sorgusu hazırlanamadı: ' . $db->lastErrorMsg());
    }
    
    $check_stmt->bindValue(1, $email, SQLITE3_TEXT);
    $result = $check_stmt->execute();
    if (!$result) {
        $check_stmt->close();
        $db->close();
        sendResponse(false, null, null, 'Veritabanı sorgusu çalıştırılamadı: ' . $db->lastErrorMsg());
    }
    
    if ($result->fetchArray()) {
        $result->finalize();
        $check_stmt->close();
        $db->close();
        sendResponse(false, null, null, 'Kod çok kısa süre önce gönderildi. Lütfen 1 dakika bekleyin.');
    }
    $result->finalize();
    $check_stmt->close();
    
    // 6 haneli rastgele kod oluştur
    $code = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    
    // Kod 10 dakika geçerli
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Kodu veritabanına kaydet
    $insert_stmt = $db->prepare("INSERT INTO email_verification_codes (email, code, expires_at) VALUES (?, ?, ?)");
    if (!$insert_stmt) {
        $db->close();
        sendResponse(false, null, null, 'Insert sorgusu hazırlanamadı: ' . $db->lastErrorMsg());
    }
    
    $insert_stmt->bindValue(1, $email, SQLITE3_TEXT);
    $insert_stmt->bindValue(2, $code, SQLITE3_TEXT);
    $insert_stmt->bindValue(3, $expires_at, SQLITE3_TEXT);
    
    $insertResult = $insert_stmt->execute();
    if (!$insertResult) {
        $insert_stmt->close();
        $db->close();
        sendResponse(false, null, null, 'Kod kaydedilemedi: ' . $db->lastErrorMsg());
    }
    $insert_stmt->close();
    
    // E-posta gönder
    $emailSent = false;
    
    try {
        // Communication modülünü yükle
        $communicationPath = __DIR__ . '/../templates/functions/communication.php';
        if (file_exists($communicationPath)) {
            require_once $communicationPath;
        }
        
        // Lazy loader'ı yükle
        $lazyLoaderPath = __DIR__ . '/../templates/lazy_loader.php';
        if (file_exists($lazyLoaderPath) && function_exists('load_module')) {
            require_once $lazyLoaderPath;
            if (function_exists('load_module')) {
                load_module('communication');
            }
        }
        
        $subject = 'UniFour E-posta Doğrulama Kodu';
        $message = "Merhaba,\n\nE-posta doğrulama kodunuz: {$code}\n\nBu kod 10 dakika geçerlidir.\n\nUniFour Ekibi";
        
        // SMTP ayarlarını al
        $smtp_username = '';
        $smtp_password = '';
        $smtp_host = 'ms7.guzel.net.tr';
        $smtp_port = 587;
        $smtp_secure = 'tls';
        
        if (function_exists('get_smtp_credential')) {
            $smtp_username = get_smtp_credential('username');
            $smtp_password = get_smtp_credential('password');
            $smtp_host = get_smtp_credential('host', 'ms7.guzel.net.tr');
            $smtp_port = (int)get_smtp_credential('port', 587);
            $smtp_secure = get_smtp_credential('encryption', 'tls');
        }
        
        // Eksik fonksiyonları tanımla
        if (!function_exists('tpl_error_log')) {
            function tpl_error_log($message) {
                error_log($message);
            }
        }
        
        if (!function_exists('get_setting')) {
            function get_setting($key, $default = '') {
                return $default;
            }
        }
        
        if (!function_exists('get_db')) {
            function get_db() {
                // API context'inde get_db() gerekmiyor, null döndür
                return null;
            }
        }
        
        // Basit SMTP gönderimi - get_db() gerektirmeyen versiyon
        if (!empty($smtp_username) && !empty($smtp_password) && function_exists('send_smtp_mail')) {
            try {
                // get_db() null döndüğünde hata vermemesi için try-catch içinde çağır
                $emailSent = @send_smtp_mail(
                    $email,
                    $subject,
                    $message,
                    'UniFour',
                    $smtp_username,
                    [
                        'host' => $smtp_host,
                        'port' => $smtp_port,
                        'secure' => $smtp_secure,
                        'username' => $smtp_username,
                        'password' => $smtp_password,
                    ]
                );
            } catch (Throwable $e) {
                error_log("SMTP send error: " . $e->getMessage());
                // Hata olsa bile devam et, kod oluşturuldu
                $emailSent = false;
            }
        } else {
            // Fallback: PHP mail() fonksiyonu
            $headers = "From: UniFour <noreply@unifour.com>\r\n";
            $headers .= "Reply-To: noreply@unifour.com\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $emailSent = @mail($email, $subject, $message, $headers);
        }
    } catch (Exception $emailException) {
        error_log("Email sending error: " . $emailException->getMessage());
        // E-posta gönderilemese bile devam et
    }
    
    $db->close();
    
    // Kod oluşturuldu, e-posta gönderilse de gönderilmese de başarılı say
    if ($emailSent) {
        sendResponse(true, ['email' => $email], 'Doğrulama kodu e-posta adresinize gönderildi.');
    } else {
        // E-posta gönderilemese bile kod oluşturuldu
        sendResponse(true, ['email' => $email], 'Doğrulama kodu oluşturuldu. E-posta gönderiminde sorun olabilir, lütfen spam klasörünüzü kontrol edin.');
    }
    
} catch (Exception $e) {
    error_log("send_verification_code.php fatal error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    sendResponse(false, null, null, 'Doğrulama kodu gönderilirken bir hata oluştu: ' . $e->getMessage());
} catch (Error $e) {
    error_log("send_verification_code.php fatal error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    sendResponse(false, null, null, 'Doğrulama kodu gönderilirken bir hata oluştu: ' . $e->getMessage());
}
