<?php
/**
 * Adım Adım Kayıt API
 * Step 1: Email + Password -> Email verification code gönder
 * Step 2: Email verification code doğrula -> Kullanıcı oluştur (sadece email, password, first_name, last_name)
 * Step 3: İsteğe bağlı bilgileri güncelle (university, department, phone_number, student_id)
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $step = $input['step'] ?? '1';
    
    // Step 1: Email + Password -> Verification code gönder
    if ($step === '1') {
        if (!isset($input['email']) || empty(trim($input['email']))) {
            sendResponse(false, null, null, 'Email adresi gerekli');
        }
        
        if (!isset($input['password']) || empty($input['password'])) {
            sendResponse(false, null, null, 'Şifre gerekli');
        }
        
        $email = sanitizeInput(trim($input['email']), 'email');
        $password = $input['password'];
        
        // Şifre validasyonu
        $passwordValidation = validatePassword($password);
        if (!$passwordValidation['valid']) {
            sendResponse(false, null, null, $passwordValidation['message']);
        }
        
        // Email zaten kayıtlı mı kontrol et
        $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
        if (file_exists($system_db_path)) {
            $db = new SQLite3($system_db_path);
            $db->exec('PRAGMA journal_mode = WAL');
            
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE email = ?");
            $check_stmt->bindValue(1, $email, SQLITE3_TEXT);
            $result = $check_stmt->execute();
            if ($result && $result->fetchArray()) {
                $db->close();
                sendResponse(false, null, null, 'Bu e-posta adresi zaten kayıtlı. Lütfen giriş yapın.');
            }
            $db->close();
        }
        
        // Email verification code gönder
        require_once __DIR__ . '/send_verification_code.php';
        
        // send_verification_code.php'yi çağır (session kullanmadan)
        $_POST['email'] = $email;
        $_POST['action'] = 'send_code';
        
        // Code gönder
        $code = generateVerificationCode();
        
        // Code'u geçici olarak kaydet (session yerine dosya veya database)
        $temp_codes_file = __DIR__ . '/../system/temp_verification_codes.json';
        $temp_codes_dir = dirname($temp_codes_file);
        if (!is_dir($temp_codes_dir)) {
            mkdir($temp_codes_dir, 0755, true);
        }
        
        $temp_codes = [];
        if (file_exists($temp_codes_file)) {
            $temp_codes = json_decode(file_get_contents($temp_codes_file), true) ?? [];
        }
        
        // Eski kodları temizle (1 saatten eski)
        $now = time();
        foreach ($temp_codes as $key => $code_data) {
            if ($now - $code_data['timestamp'] > 3600) {
                unset($temp_codes[$key]);
            }
        }
        
        // Yeni kodu ekle
        $temp_codes[$email] = [
            'code' => $code,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'timestamp' => $now
        ];
        
        file_put_contents($temp_codes_file, json_encode($temp_codes, JSON_UNESCAPED_UNICODE));
        
        // Email gönder
        $email_sent = sendVerificationEmail($email, $code);
        
        if ($email_sent) {
            sendResponse(true, ['email' => $email], 'Doğrulama kodu e-posta adresinize gönderildi.');
        } else {
            sendResponse(false, null, null, 'E-posta gönderilemedi. Lütfen tekrar deneyin.');
        }
    }
    
    // Step 2: Email verification code doğrula -> Kullanıcı oluştur
    if ($step === '2') {
        if (!isset($input['email']) || empty(trim($input['email']))) {
            sendResponse(false, null, null, 'Email adresi gerekli');
        }
        
        if (!isset($input['code']) || empty(trim($input['code']))) {
            sendResponse(false, null, null, 'Doğrulama kodu gerekli');
        }
        
        if (!isset($input['first_name']) || empty(trim($input['first_name']))) {
            sendResponse(false, null, null, 'Ad gerekli');
        }
        
        if (!isset($input['last_name']) || empty(trim($input['last_name']))) {
            sendResponse(false, null, null, 'Soyad gerekli');
        }
        
        $email = sanitizeInput(trim($input['email']), 'email');
        $code = trim($input['code']);
        $first_name = sanitizeInput(trim($input['first_name']), 'string');
        $last_name = sanitizeInput(trim($input['last_name']), 'string');
        
        // Geçici kodları kontrol et
        $temp_codes_file = __DIR__ . '/../system/temp_verification_codes.json';
        if (!file_exists($temp_codes_file)) {
            sendResponse(false, null, null, 'Doğrulama kodu bulunamadı. Lütfen tekrar deneyin.');
        }
        
        $temp_codes = json_decode(file_get_contents($temp_codes_file), true) ?? [];
        
        if (!isset($temp_codes[$email])) {
            sendResponse(false, null, null, 'Doğrulama kodu bulunamadı. Lütfen tekrar deneyin.');
        }
        
        $code_data = $temp_codes[$email];
        
        // Kod süresi dolmuş mu kontrol et (1 saat)
        if (time() - $code_data['timestamp'] > 3600) {
            unset($temp_codes[$email]);
            file_put_contents($temp_codes_file, json_encode($temp_codes, JSON_UNESCAPED_UNICODE));
            sendResponse(false, null, null, 'Doğrulama kodu süresi dolmuş. Lütfen yeni kod isteyin.');
        }
        
        // Kod doğru mu kontrol et
        if ($code_data['code'] !== $code) {
            sendResponse(false, null, null, 'Doğrulama kodu hatalı.');
        }
        
        // Kullanıcıyı oluştur
        $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
        $db_dir = dirname($system_db_path);
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
        
        if (!file_exists($system_db_path)) {
            touch($system_db_path);
            chmod($system_db_path, 0666);
        }
        
        $db = new SQLite3($system_db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // system_users tablosunu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS system_users (
            id INTEGER PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            student_id TEXT UNIQUE,
            password_hash TEXT NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            phone_number TEXT,
            university TEXT,
            department TEXT,
            email_verified INTEGER DEFAULT 1,
            phone_verified INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");
        
        // phone_verified kolonu yoksa ekle
        $columns = $db->query("PRAGMA table_info(system_users)");
        $hasPhoneVerified = false;
        while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
            if ($col['name'] === 'phone_verified') {
                $hasPhoneVerified = true;
                break;
            }
        }
        if (!$hasPhoneVerified) {
            $db->exec("ALTER TABLE system_users ADD COLUMN phone_verified INTEGER DEFAULT 0");
        }
        
        // Kullanıcıyı kaydet
        $password_hash = $code_data['password_hash'];
        
        $insert_stmt = $db->prepare("INSERT INTO system_users (email, password_hash, first_name, last_name, email_verified) VALUES (?, ?, ?, ?, 1)");
        $insert_stmt->bindValue(1, $email, SQLITE3_TEXT);
        $insert_stmt->bindValue(2, $password_hash, SQLITE3_TEXT);
        $insert_stmt->bindValue(3, $first_name, SQLITE3_TEXT);
        $insert_stmt->bindValue(4, $last_name, SQLITE3_TEXT);
        
        if (!$insert_stmt->execute()) {
            $error_msg = $db->lastErrorMsg();
            $db->close();
            error_log("Register Step 2 - Insert failed: $error_msg");
            sendResponse(false, null, null, 'Kayıt işlemi başarısız oldu. Lütfen tekrar deneyin.');
        }
        
        $user_id = $db->lastInsertRowID();
        $insert_stmt->close();
        
        // Geçici kodu sil
        unset($temp_codes[$email]);
        file_put_contents($temp_codes_file, json_encode($temp_codes, JSON_UNESCAPED_UNICODE));
        
        // Auth token oluştur ve döndür
        $token = (string)$user_id;
        
        $db->close();
        
        sendResponse(true, [
            'user_id' => $user_id,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'token' => $token
        ], 'Kayıt başarılı!');
    }
    
    // Step 3: İsteğe bağlı bilgileri güncelle
    if ($step === '3') {
        // Authentication gerekli
        require_once __DIR__ . '/auth_middleware.php';
        $currentUser = requireAuth(true);
        
        $university = isset($input['university']) ? sanitizeInput(trim($input['university']), 'string') : null;
        $department = isset($input['department']) ? sanitizeInput(trim($input['department']), 'string') : null;
        $student_id = isset($input['student_id']) ? sanitizeInput(trim($input['student_id']), 'string') : null;
        $phone_number = isset($input['phone_number']) ? sanitizeInput(trim($input['phone_number']), 'string') : null;
        
        $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
        $db = new SQLite3($system_db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Güncelleme sorgusu oluştur
        $updates = [];
        $params = [];
        $param_index = 1;
        
        if ($university !== null) {
            $updates[] = "university = ?";
            $params[] = $university;
        }
        if ($department !== null) {
            $updates[] = "department = ?";
            $params[] = $department;
        }
        if ($student_id !== null) {
            $updates[] = "student_id = ?";
            $params[] = $student_id;
        }
        if ($phone_number !== null) {
            $updates[] = "phone_number = ?";
            $params[] = $phone_number;
        }
        
        if (empty($updates)) {
            $db->close();
            sendResponse(true, null, 'Güncellenecek bilgi bulunamadı.');
        }
        
        $sql = "UPDATE system_users SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $currentUser['id'];
        
        $update_stmt = $db->prepare($sql);
        foreach ($params as $index => $param) {
            $update_stmt->bindValue($index + 1, $param, SQLITE3_TEXT);
        }
        
        if (!$update_stmt->execute()) {
            $error_msg = $db->lastErrorMsg();
            $db->close();
            error_log("Register Step 3 - Update failed: $error_msg");
            sendResponse(false, null, null, 'Güncelleme başarısız oldu.');
        }
        
        $db->close();
        sendResponse(true, null, 'Bilgiler güncellendi.');
    }
    
    sendResponse(false, null, null, 'Geçersiz adım.');
    
} catch (Exception $e) {
    error_log("Register Step Error: " . $e->getMessage());
    sendResponse(false, null, null, 'Bir hata oluştu: ' . $e->getMessage());
}

// Helper function
function generateVerificationCode() {
    return str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($email, $code) {
    try {
        require_once __DIR__ . '/../templates/functions/communication.php';
        return send_email($email, 'UniPanel - E-posta Doğrulama Kodu', "Doğrulama kodunuz: $code\n\nBu kodu kullanarak hesabınızı doğrulayabilirsiniz.");
    } catch (Exception $e) {
        error_log("Email gönderme hatası: " . $e->getMessage());
        return false;
    }
}
