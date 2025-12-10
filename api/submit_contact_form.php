<?php
/**
 * İletişim Formu API
 * Marketing sayfasındaki iletişim formundan gelen verileri kaydeder
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function sendResponse($success, $data = null, $message = null, $error = null) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Hata raporlamayı aç (debug için)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Hata loglama fonksiyonu
function logError($message) {
    $logFile = __DIR__ . '/../logs/contact_form.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    error_log(date('[Y-m-d H:i:s]') . ' [submit_contact_form.php] ' . $message . PHP_EOL, 3, $logFile);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    logError("Form submission received. POST data: " . print_r($_POST, true));
    
    // Form verilerini al
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $community = trim($_POST['community'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    logError("Parsed data - Name: $name, Email: $email, Phone: $phone, Community: $community, Message length: " . strlen($message));
    
    // Validasyon
    if (empty($name)) {
        sendResponse(false, null, null, 'Ad Soyad gereklidir');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, null, null, 'Geçerli bir email adresi gereklidir');
    }
    
    if (empty($message)) {
        sendResponse(false, null, null, 'Mesaj gereklidir');
    }
    
    // Superadmin veritabanına kaydet
    // SUPERADMIN_DB sabitini kullan (superadmin/index.php'deki ile aynı)
    $superadminDbPath = __DIR__ . '/../unipanel.sqlite';
    
    // Veritabanı dosyasının varlığını kontrol et
    if (!file_exists($superadminDbPath)) {
        // Veritabanı yoksa oluştur
        $superadminDbDir = dirname($superadminDbPath);
        if (!is_dir($superadminDbDir)) {
            @mkdir($superadminDbDir, 0755, true);
        }
        @touch($superadminDbPath);
        @chmod($superadminDbPath, 0640);
    }
    
    $db = new SQLite3($superadminDbPath);
    if (!$db) {
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı');
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Contact submissions tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        community TEXT,
        message TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        status TEXT DEFAULT 'new',
        read_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // IP adresi ve user agent al
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Veriyi kaydet
    $stmt = $db->prepare("INSERT INTO contact_submissions (name, email, phone, community, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $db->close();
        sendResponse(false, null, null, 'Veritabanı hazırlama hatası: ' . $db->lastErrorMsg());
    }
    
    $stmt->bindValue(1, $name, SQLITE3_TEXT);
    $stmt->bindValue(2, $email, SQLITE3_TEXT);
    $stmt->bindValue(3, $phone ?: null, SQLITE3_TEXT);
    $stmt->bindValue(4, $community ?: null, SQLITE3_TEXT);
    $stmt->bindValue(5, $message, SQLITE3_TEXT);
    $stmt->bindValue(6, $ip_address, SQLITE3_TEXT);
    $stmt->bindValue(7, $user_agent, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    if ($result) {
        $insertId = $db->lastInsertRowID();
        logError("Contact form submission saved successfully: ID=$insertId, Name=$name, Email=$email");
        $db->close();
        sendResponse(true, ['id' => $insertId], 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.');
    } else {
        $errorMsg = $db->lastErrorMsg();
        logError("Contact form submission failed: " . $errorMsg);
        $db->close();
        sendResponse(false, null, null, 'Mesaj kaydedilirken bir hata oluştu: ' . $errorMsg);
    }
    
} catch (Exception $e) {
    logError("Contact form API exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    if (isset($db)) {
        $db->close();
    }
    sendResponse(false, null, null, 'Bir hata oluştu: ' . $e->getMessage());
}

