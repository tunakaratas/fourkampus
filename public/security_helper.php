<?php
/**
 * Public Security Helper Functions
 * Gerçek hayat senaryolarına uygun güvenlik fonksiyonları
 */

/**
 * CSRF Token oluştur ve session'a kaydet
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token doğrula
 */
function verify_csrf_token($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF Token field HTML
 */
function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Güvenli IP adresi alma - IP spoofing koruması
 */
function getRealIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // IP adresini doğrula
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'unknown';
    }
    
    // Güvenilir proxy'lerden gelen header'ları kontrol et
    $trustedProxies = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8'
    ];
    
    $isTrustedProxy = false;
    foreach ($trustedProxies as $proxy) {
        list($subnet, $mask) = explode('/', $proxy);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        $ipLong = ip2long($ip);
        if ($ipLong !== false && ($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
            $isTrustedProxy = true;
            break;
        }
    }
    
    // Sadece güvenilir proxy'lerden gelen header'ları kullan
    if ($isTrustedProxy) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $forwarded = trim($forwarded[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $realIp = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }
    }
    
    return $ip;
}

/**
 * Rate Limiting - IP bazlı
 */
function check_rate_limit($action, $max_attempts = 5, $time_window = 300) {
    $ip = getRealIP();
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $rate_data = $_SESSION[$key];
    
    // Zaman penceresi dolmuş mu kontrol et
    if (time() - $rate_data['first_attempt'] > $time_window) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        $rate_data = $_SESSION[$key];
    }
    
    // Limit aşıldı mı?
    if ($rate_data['count'] >= $max_attempts) {
        $remaining = $time_window - (time() - $rate_data['first_attempt']);
        return [
            'allowed' => false,
            'remaining' => $remaining,
            'message' => "Çok fazla deneme yaptınız. Lütfen {$remaining} saniye sonra tekrar deneyin."
        ];
    }
    
    // Deneme sayısını artır
    $_SESSION[$key]['count']++;
    
    return ['allowed' => true, 'remaining' => 0];
}

/**
 * Email validation (güçlendirilmiş)
 */
function validate_email($email) {
    if (empty($email)) {
        return ['valid' => false, 'message' => 'Email adresi boş olamaz'];
    }
    
    // Temel format kontrolü
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Geçersiz email formatı'];
    }
    
    // Uzunluk kontrolü
    if (strlen($email) > 255) {
        return ['valid' => false, 'message' => 'Email adresi çok uzun'];
    }
    
    // Disposable email kontrolü (opsiyonel - performans için)
    $disposable_domains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
    $domain = substr(strrchr($email, "@"), 1);
    if (in_array(strtolower($domain), $disposable_domains)) {
        return ['valid' => false, 'message' => 'Geçici email adresleri kabul edilmez'];
    }
    
    return ['valid' => true];
}

/**
 * Phone validation (Türkiye)
 */
function validate_phone($phone) {
    if (empty($phone)) {
        return ['valid' => false, 'message' => 'Telefon numarası boş olamaz'];
    }
    
    // Sadece rakamlar
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Türkiye telefon formatı: 5XXXXXXXXX (10 haneli, 5 ile başlar)
    if (strlen($phone) !== 10 || !preg_match('/^5[0-9]{9}$/', $phone)) {
        return ['valid' => false, 'message' => 'Telefon numarası 5 ile başlayan 10 haneli olmalıdır (örn: 5551234567)'];
    }
    
    return ['valid' => true, 'normalized' => $phone];
}

/**
 * Password strength kontrolü
 */
function validate_password_strength($password) {
    if (empty($password)) {
        return ['valid' => false, 'strength' => 'weak', 'message' => 'Şifre boş olamaz'];
    }
    
    if (strlen($password) < 8) {
        return ['valid' => false, 'strength' => 'weak', 'message' => 'Şifre en az 8 karakter olmalıdır'];
    }
    
    $strength = 0;
    $messages = [];
    
    // Uzunluk kontrolü
    if (strlen($password) >= 8) $strength++;
    if (strlen($password) >= 12) $strength++;
    
    // Karakter çeşitliliği
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
    
    // Zayıf şifre kontrolü
    $common_passwords = ['12345678', 'password', 'qwerty123', '123456789'];
    if (in_array(strtolower($password), $common_passwords)) {
        return ['valid' => false, 'strength' => 'weak', 'message' => 'Bu şifre çok yaygın kullanılıyor, lütfen daha güçlü bir şifre seçin'];
    }
    
    if ($strength < 4) {
        return ['valid' => false, 'strength' => 'weak', 'message' => 'Şifre çok zayıf. Büyük harf, küçük harf, rakam ve özel karakter içermelidir'];
    }
    
    if ($strength < 5) {
        return ['valid' => true, 'strength' => 'medium', 'message' => 'Şifre orta güçte'];
    }
    
    return ['valid' => true, 'strength' => 'strong', 'message' => 'Şifre güçlü'];
}

/**
 * Input sanitization (XSS koruması)
 */
function sanitize_input($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitize_input($item, $type);
        }, $input);
    }
    
    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * SQLite database bağlantısı (güvenli, retry mekanizmalı)
 */
function get_safe_db_connection($db_path, $read_only = false) {
    $max_retries = 3;
    $retry_delay = 100000; // 100ms (microseconds)
    
    for ($i = 0; $i < $max_retries; $i++) {
        try {
            if ($read_only && file_exists($db_path)) {
                $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
            } else {
                $db = new SQLite3($db_path);
            }
            
            if (!$db) {
                throw new Exception('Database connection failed');
            }
            
            // Busy timeout ayarla (5 saniye)
            $db->busyTimeout(5000);
            
            // Journal mode - DELETE (WAL concurrent read sorunlarına neden olabilir)
            @$db->exec('PRAGMA journal_mode = DELETE');
            
            // Foreign keys
            @$db->exec('PRAGMA foreign_keys = ON');
            
            return $db;
        } catch (Exception $e) {
            if ($i < $max_retries - 1) {
                usleep($retry_delay * ($i + 1)); // Exponential backoff
                continue;
            }
            error_log("Database connection failed after {$max_retries} retries: " . $e->getMessage());
            return null;
        }
    }
    
    return null;
}

/**
 * Session güvenlik ayarları
 */
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Session cookie güvenlik ayarları
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session ID güvenliği
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_lifetime', 0); // Browser kapanınca sil
        
        session_start();
        
        // Session fixation koruması
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = getRealIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        }
        
        // Session hijacking koruması - IP ve User-Agent kontrolü
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== getRealIP()) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = getRealIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            log_security_event('session_hijack_attempt', ['old_ip' => $_SESSION['ip_address'] ?? 'unknown', 'new_ip' => getRealIP()]);
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = getRealIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            log_security_event('session_hijack_attempt', ['reason' => 'user_agent_mismatch']);
        }
        
        // Session timeout (30 dakika)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = getRealIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        }
        
        $_SESSION['last_activity'] = time();
        
        // Security headers ekle
        setSecurityHeaders();
    }
}

/**
 * Security headers ekle
 */
function setSecurityHeaders() {
    // X-Frame-Options - Clickjacking koruması
    if (!headers_sent()) {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HTTPS kullanılıyorsa HSTS ekle
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy (CSP) - Temel koruma
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';");
    }
}

/**
 * Logging (güvenlik olayları)
 */
function log_security_event($event_type, $details = []) {
    $log_file = __DIR__ . '/../system/logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => getRealIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'event_type' => $event_type,
        'details' => $details
    ];
    
    @file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Account lockout kontrolü
 */
function check_account_lockout($identifier, $max_attempts = 5, $lockout_duration = 900) {
    $key = "lockout_{$identifier}";
    
    if (isset($_SESSION[$key])) {
        $lockout_data = $_SESSION[$key];
        
        if ($lockout_data['attempts'] >= $max_attempts) {
            $time_elapsed = time() - $lockout_data['locked_at'];
            
            if ($time_elapsed < $lockout_duration) {
                $remaining = $lockout_duration - $time_elapsed;
                return [
                    'locked' => true,
                    'remaining' => $remaining,
                    'message' => "Hesabınız geçici olarak kilitlendi. Lütfen " . ceil($remaining / 60) . " dakika sonra tekrar deneyin."
                ];
            } else {
                // Lockout süresi dolmuş, sıfırla
                unset($_SESSION[$key]);
            }
        }
    }
    
    return ['locked' => false];
}

/**
 * Account lockout kaydet
 */
function record_failed_attempt($identifier, $max_attempts = 5, $lockout_duration = 900) {
    $key = "lockout_{$identifier}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'locked_at' => 0];
    }
    
    $_SESSION[$key]['attempts']++;
    
    if ($_SESSION[$key]['attempts'] >= $max_attempts) {
        $_SESSION[$key]['locked_at'] = time();
        log_security_event('account_lockout', ['identifier' => $identifier, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    }
}

/**
 * Başarılı giriş sonrası lockout'u sıfırla
 */
function clear_account_lockout($identifier) {
    $key = "lockout_{$identifier}";
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

/**
 * Community ID sanitization - Path traversal koruması
 */
function sanitizeCommunityId($id) {
    if (empty($id)) {
        return null;
    }
    
    // Sadece basename kullan (path traversal koruması)
    $id = basename($id);
    
    // Sadece alfanumerik, alt çizgi ve tire karakterlerine izin ver
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
        return null;
    }
    
    // Path traversal karakterlerini kontrol et
    if (strpos($id, '..') !== false || strpos($id, '/') !== false || strpos($id, '\\') !== false) {
        return null;
    }
    
    return $id;
}

/**
 * Güvenli error handling - Production'da hassas bilgi sızıntısını önle
 */
function handleError($message, $exception = null) {
    $isProduction = defined('APP_ENV') && APP_ENV === 'production';
    
    if ($isProduction) {
        // Production'da genel hata mesajı
        error_log("Error: {$message}");
        if ($exception) {
            error_log("Exception: " . $exception->getMessage());
        }
        return 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    } else {
        // Development'ta detaylı hata
        $error = "Error: {$message}";
        if ($exception) {
            $error .= "\nException: " . $exception->getMessage();
            $error .= "\nTrace: " . $exception->getTraceAsString();
        }
        return $error;
    }
}

