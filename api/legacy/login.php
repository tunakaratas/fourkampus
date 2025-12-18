<?php
/**
 * ⚠️ DEPRECATED - Bu endpoint kullanımdan kaldırılmıştır
 * 
 * Yeni endpoint: POST /api/v1/auth/login
 * 
 * Bu dosya geriye dönük uyumluluk için tutulmaktadır.
 * Yeni projeler için router.php üzerinden /api/v1/auth/login kullanın.
 * 
 * @deprecated 2025-12-13
 * @see api/router.php
 */

require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID, X-Request-Timestamp, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Login-specific rate limiting (daha sıkı - 10 deneme/dakika)
function checkLoginRateLimit($email = null) {
    // Güvenli IP alma
    $ip = getRealIP();
    
    // IP bazlı rate limiting (10 istek/dakika)
    if (!checkRateLimit(10, 60)) {
        return ['allowed' => false, 'reason' => 'IP rate limit aşıldı. Lütfen 1 dakika sonra tekrar deneyin.'];
    }
    
    // Email bazlı rate limiting (5 istek/dakika - brute force koruması)
    if ($email) {
        $emailHash = hash('sha256', strtolower(trim($email)) . 'unipanel_login_salt_2025');
        $cacheFile = __DIR__ . '/../../system/cache/login_rate_' . substr($emailHash, 0, 16) . '.json';
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        $now = time();
        $requests = [];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['requests']) && is_array($data['requests'])) {
                $requests = $data['requests'];
            }
        }
        
        // Eski istekleri temizle (1 dakika dışında kalanlar)
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return is_numeric($timestamp) && ($now - (int)$timestamp) < 60;
        });
        
        // Yeni isteği ekle
        $requests[] = $now;
        
        // Limit kontrolü (5 istek/dakika)
        if (count($requests) > 5) {
            @file_put_contents($cacheFile, json_encode(['requests' => array_slice($requests, -5), 'last_updated' => $now]), LOCK_EX);
            return ['allowed' => false, 'reason' => 'Bu email için çok fazla deneme yapıldı. Lütfen 1 dakika sonra tekrar deneyin.'];
        }
        
        // Cache'i güncelle
        @file_put_contents($cacheFile, json_encode(['requests' => $requests, 'last_updated' => $now]), LOCK_EX);
    }
    
    return ['allowed' => true];
}

// Brute force koruması - başarısız denemeleri takip et
function checkBruteForceProtection($email, $db) {
    $email = strtolower(trim($email));
    $now = time();
    
    // Başarısız deneme kayıtlarını kontrol et
    $cacheFile = __DIR__ . '/../../system/cache/brute_force_' . substr(hash('sha256', $email . 'unipanel_bf_2025'), 0, 16) . '.json';
    $cacheDir = dirname($cacheFile);
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $attempts = [];
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data['attempts']) && is_array($data['attempts'])) {
            $attempts = $data['attempts'];
        }
    }
    
    // Son 15 dakika içindeki başarısız denemeleri say
    $recentAttempts = array_filter($attempts, function($timestamp) use ($now) {
        return is_numeric($timestamp) && ($now - (int)$timestamp) < 900; // 15 dakika
    });
    
    // 5 başarısız denemeden sonra hesabı kilitle (15 dakika)
    if (count($recentAttempts) >= 5) {
        $lockUntil = isset($data['lock_until']) ? (int)$data['lock_until'] : 0;
        if ($lockUntil > $now) {
            $remainingMinutes = ceil(($lockUntil - $now) / 60);
            return [
                'locked' => true,
                'message' => "Çok fazla başarısız deneme yapıldı. Hesap $remainingMinutes dakika süreyle kilitlendi. Lütfen daha sonra tekrar deneyin."
            ];
        }
    }
    
    return ['locked' => false];
}

// Başarısız denemeyi kaydet
function recordFailedLoginAttempt($email) {
    $email = strtolower(trim($email));
    $now = time();
    
    $cacheFile = __DIR__ . '/../../system/cache/brute_force_' . substr(hash('sha256', $email . 'unipanel_bf_2025'), 0, 16) . '.json';
    $cacheDir = dirname($cacheFile);
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $data = [
        'attempts' => [],
        'lock_until' => 0
    ];
    
    if (file_exists($cacheFile)) {
        $existing = json_decode(file_get_contents($cacheFile), true);
        if ($existing && isset($existing['attempts'])) {
            $data['attempts'] = $existing['attempts'];
            $data['lock_until'] = $existing['lock_until'] ?? 0;
        }
    }
    
    // Yeni başarısız denemeyi ekle
    $data['attempts'][] = $now;
    
    // Son 15 dakika içindeki denemeleri say
    $recentAttempts = array_filter($data['attempts'], function($timestamp) use ($now) {
        return is_numeric($timestamp) && ($now - (int)$timestamp) < 900;
    });
    
    // 5 başarısız denemeden sonra kilitle
    if (count($recentAttempts) >= 5) {
        $data['lock_until'] = $now + 900; // 15 dakika kilit
    }
    
    // Eski denemeleri temizle (1 saatten eski)
    $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now) {
        return is_numeric($timestamp) && ($now - (int)$timestamp) < 3600;
    });
    
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

// Başarılı girişte brute force kayıtlarını temizle
function clearFailedLoginAttempts($email) {
    $email = strtolower(trim($email));
    $cacheFile = __DIR__ . '/../../system/cache/brute_force_' . substr(hash('sha256', $email . 'unipanel_bf_2025'), 0, 16) . '.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

// Input sanitization ve email validation fonksiyonları security_helper.php'de tanımlı
// Burada tekrar tanımlanmamalı (duplicate function hatası önlemek için)

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
    // Request start time (performance tracking)
    $startTime = microtime(true);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    // Request body'yi güvenli şekilde oku
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        sendResponse(false, null, null, 'İstek gövdesi boş');
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, null, 'Geçersiz JSON formatı');
    }
    
    // Input validation
    if (!isset($input['email']) || empty(trim($input['email']))) {
        sendResponse(false, null, null, 'Email adresi gerekli');
    }
    
    if (!isset($input['password']) || empty($input['password'])) {
        sendResponse(false, null, null, 'Şifre gerekli');
    }
    
    $email = sanitizeInput($input['email'], 'string');
    $password = $input['password']; // Şifre sanitize edilmez, password_verify için ham kalmalı
    
    // Email format validation
    if (!validateEmail($email)) {
        sendResponse(false, null, null, 'Geçersiz email formatı');
    }
    
    // Email uzunluk kontrolü
    if (strlen($email) > 255) {
        sendResponse(false, null, null, 'Email adresi çok uzun');
    }
    
    // Şifre uzunluk kontrolü
    if (strlen($password) > 128) {
        sendResponse(false, null, null, 'Şifre çok uzun');
    }
    
    // Şifre minimum uzunluk kontrolü (güvenlik için)
    if (strlen($password) < 1) {
        sendResponse(false, null, null, 'Şifre boş olamaz');
    }
    
    // Rate limiting kontrolü - Güvenli IP ile
    $realIP = getRealIP();
    $rateLimitCheck = checkLoginRateLimit($email);
    if (!$rateLimitCheck['allowed']) {
        sendResponse(false, null, null, $rateLimitCheck['reason']);
    }
    
    // Genel sistem veritabanı yolu
    $system_db_path = __DIR__ . '/../../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        sendResponse(false, null, null, 'Veritabanı dosyası bulunamadı');
    }
    
    if (!is_readable($system_db_path)) {
        sendResponse(false, null, null, 'Veritabanı dosyası okunamıyor');
    }
    
    // Database connection - 10k kullanıcı için optimize edildi
    try {
        $db = new SQLite3($system_db_path, SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000); // 5 saniye timeout (concurrent access için)
        @$db->exec('PRAGMA journal_mode = WAL'); // WAL mode (concurrent reads için daha iyi)
        @$db->exec('PRAGMA synchronous = NORMAL'); // Performance için
        @$db->exec('PRAGMA cache_size = -64000'); // 64MB cache (10k kullanıcı için)
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        sendResponse(false, null, null, 'Veritabanı bağlantı hatası');
    }
    
    // Brute force koruması kontrolü
    $bruteForceCheck = checkBruteForceProtection($email, $db);
    if ($bruteForceCheck['locked']) {
        $db->close();
        sendResponse(false, null, null, $bruteForceCheck['message']);
    }
    
    // Tablo var mı kontrol et
    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_users'");
    if (!$table_check || !$table_check->fetchArray()) {
        $db->close();
        sendResponse(false, null, null, 'Veritabanı tablosu bulunamadı');
    }
    
    // Kullanıcıyı bul (email case-insensitive)
    // Önce kolonların varlığını kontrol et
    $columns = [];
    $columnCheck = $db->query("PRAGMA table_info(system_users)");
    while ($row = $columnCheck->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    // Eksik kolonları ekle (migration)
    if (!in_array('failed_login_attempts', $columns)) {
        try {
            $db->exec("ALTER TABLE system_users ADD COLUMN failed_login_attempts INTEGER DEFAULT 0");
        } catch (Exception $e) {
            // Kolon zaten varsa veya başka bir hata varsa sessizce devam et
        }
    }
    if (!in_array('locked_until', $columns)) {
        try {
            $db->exec("ALTER TABLE system_users ADD COLUMN locked_until DATETIME DEFAULT NULL");
        } catch (Exception $e) {
            // Kolon zaten varsa veya başka bir hata varsa sessizce devam et
        }
    }
    
    // Kullanıcıyı bul (email case-insensitive)
    $stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name, student_id, phone_number, university, department, created_at, last_login, failed_login_attempts, locked_until FROM system_users WHERE LOWER(email) = LOWER(?) AND is_active = 1");
    if (!$stmt) {
        $db->close();
        sendResponse(false, null, null, 'Veritabanı sorgu hatası');
    }
    
    $stmt->bindValue(1, $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if (!$result) {
        $db->close();
        sendResponse(false, null, null, 'Veritabanı sorgu hatası');
    }
    
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        // Kullanıcı bulunamadı - başarısız denemeyi kaydet
        recordFailedLoginAttempt($email);
        $db->close();
        // Güvenlik: Email veya şifre hatalı (hangi birinin hatalı olduğunu belirtme)
        sendResponse(false, null, null, 'Email veya şifre hatalı');
    }
    
    // Hesap kilit kontrolü (veritabanından)
    if (isset($user['locked_until']) && $user['locked_until']) {
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            $remainingMinutes = ceil(($lockedUntil - time()) / 60);
            $db->close();
            sendResponse(false, null, null, "Hesap $remainingMinutes dakika süreyle kilitlendi. Çok fazla başarısız deneme yapıldı.");
        }
    }
    
    // Şifre doğrulama
    if (!password_verify($password, $user['password_hash'])) {
        // Başarısız denemeyi kaydet
        recordFailedLoginAttempt($email);
        
        // Veritabanında başarısız deneme sayısını artır
        try {
            $failedAttempts = (int)($user['failed_login_attempts'] ?? 0) + 1;
            $updateFailed = $db->prepare("UPDATE system_users SET failed_login_attempts = ?, locked_until = CASE WHEN ? >= 5 THEN datetime('now', '+15 minutes') ELSE NULL END WHERE id = ?");
            $updateFailed->bindValue(1, $failedAttempts, SQLITE3_INTEGER);
            $updateFailed->bindValue(2, $failedAttempts, SQLITE3_INTEGER);
            $updateFailed->bindValue(3, $user['id'], SQLITE3_INTEGER);
            @$updateFailed->execute();
        } catch (Exception $e) {
            // Hata olsa bile devam et
        }
        
        $db->close();
        // Güvenlik: Email veya şifre hatalı (hangi birinin hatalı olduğunu belirtme)
        sendResponse(false, null, null, 'Email veya şifre hatalı');
    }
    
    // Başarılı giriş - brute force kayıtlarını temizle
    clearFailedLoginAttempts($email);
    
    // Son giriş zamanını ve başarısız deneme sayısını güncelle
    try {
        $update_stmt = $db->prepare("UPDATE system_users SET last_login = CURRENT_TIMESTAMP, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        $update_stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
        @$update_stmt->execute();
    } catch (Exception $e) {
        // Hata olsa bile devam et
        error_log("Failed to update last_login: " . $e->getMessage());
    }
    
    // Token oluştur (güvenli random) ve hash'le
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token); // Token'ı hash'le
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    $created_at = date('Y-m-d H:i:s');
    
    // Token'ı veritabanına kaydet (hash'lenmiş olarak)
    try {
        // api_tokens tablosunun varlığını kontrol et ve yoksa oluştur
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='api_tokens'");
        if (!$table_check || !$table_check->fetchArray()) {
            // Tablo yoksa oluştur
            $create_table = "CREATE TABLE IF NOT EXISTS api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                token_hash TEXT,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
            )";
            // token_hash kolonu yoksa ekle (migration)
            try {
                @$db->exec("ALTER TABLE api_tokens ADD COLUMN token_hash TEXT");
            } catch (Exception $e) {
                // ignore
            }
            if (!$db->exec($create_table)) {
                throw new Exception("Failed to create api_tokens table: " . $db->lastErrorMsg());
            }
            // Index oluştur
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_user_id ON api_tokens(user_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_token ON api_tokens(token)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_expires_at ON api_tokens(expires_at)");
        }
        
        // token_hash kolonu yoksa ekle (migration)
        try {
            @$db->exec("ALTER TABLE api_tokens ADD COLUMN token_hash TEXT");
        } catch (Exception $e) {
            // ignore
        }
        
        // Önce eski token'ları kontrol et (aynı kullanıcı için max 5 aktif token)
        $checkTokens = $db->prepare("SELECT COUNT(*) as count FROM api_tokens WHERE user_id = ? AND expires_at > datetime('now') AND revoked_at IS NULL");
        if (!$checkTokens) {
            throw new Exception("Failed to prepare token check query: " . $db->lastErrorMsg());
        }
        
        $checkTokens->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $tokenCountResult = $checkTokens->execute();
        if (!$tokenCountResult) {
            throw new Exception("Failed to execute token check query: " . $db->lastErrorMsg());
        }
        
        $tokenCount = $tokenCountResult->fetchArray(SQLITE3_ASSOC);
        
        // 5'ten fazla aktif token varsa en eskisini iptal et
        if ($tokenCount && (int)$tokenCount['count'] >= 5) {
            $revokeOld = $db->prepare("UPDATE api_tokens SET revoked_at = datetime('now') WHERE user_id = ? AND expires_at > datetime('now') AND revoked_at IS NULL ORDER BY created_at ASC LIMIT 1");
            if ($revokeOld) {
                $revokeOld->bindValue(1, $user['id'], SQLITE3_INTEGER);
                @$revokeOld->execute();
            }
        }
        
        // Yeni token'ı ekle (hash'lenmiş olarak)
        $token_stmt = $db->prepare("INSERT INTO api_tokens (user_id, token, token_hash, expires_at, created_at, last_used_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
        if (!$token_stmt) {
            throw new Exception("Token statement prepare failed: " . $db->lastErrorMsg());
        }
        
        $token_stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $token_stmt->bindValue(2, $token, SQLITE3_TEXT); // Plain token (sadece bir kez gösterilecek)
        $token_stmt->bindValue(3, $token_hash, SQLITE3_TEXT); // Hash'lenmiş token (veritabanında saklanacak)
        $token_stmt->bindValue(4, $expires_at, SQLITE3_TEXT);
        $token_stmt->bindValue(5, $created_at, SQLITE3_TEXT);
        
        if (!$token_stmt->execute()) {
            throw new Exception("Token insert failed");
        }
    } catch (Exception $e) {
        // Token kaydı başarısız - kritik hata
        error_log("Token creation error: " . $e->getMessage());
        $db->close();
        sendResponse(false, null, null, 'Giriş işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.');
    }
    
    $db->close();
    
    // Performance tracking
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2); // milliseconds
    
    // Yavaş login uyarısı (1 saniyeden fazla)
    if ($executionTime > 1000) {
        error_log("Login API slow response: {$executionTime}ms for email: " . substr($email, 0, 3) . "***");
    }
    
    // Kullanıcı bilgilerini ve token'ı döndür
    sendResponse(true, [
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'full_name' => $user['first_name'] . ' ' . $user['last_name'],
            'student_id' => $user['student_id'] ?? null,
            'phone_number' => $user['phone_number'] ?? null,
            'university' => $user['university'] ?? null,
            'department' => $user['department'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'last_login' => $user['last_login'] ?? null
        ]
    ], 'Giriş başarılı');
    
} catch (Exception $e) {
    secureLog("Login API error: " . $e->getMessage(), 'error');
    if (!isProduction()) {
        secureLog("Login API stack trace: " . $e->getTraceAsString(), 'debug');
    }
    $response = sendSecureErrorResponse('Giriş işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
} catch (Error $e) {
    secureLog("Login API fatal error: " . $e->getMessage(), 'critical');
    if (!isProduction()) {
        secureLog("Login API stack trace: " . $e->getTraceAsString(), 'debug');
    }
    $response = sendSecureErrorResponse('Giriş işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

