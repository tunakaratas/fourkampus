<?php
/**
 * Session Security System
 * Session hijacking, fixation ve timeout koruması
 */

namespace UniPanel\General;

class SessionSecurity {
    
    /**
     * Güvenli session başlat
     */
    public static function startSecure($options = []) {
        // Session configurasyonu
        $defaultOptions = [
            'lifetime' => 60 * 60 * 24 * 7, // 7 gün
            'httponly' => true,
            'secure' => false, // HTTPS kullanıyorsanız true yapın
            'samesite' => 'Strict'
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Session ayarları
        ini_set('session.cookie_lifetime', $options['lifetime']);
        ini_set('session.cookie_httponly', $options['httponly'] ? '1' : '0');
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        } else {
            ini_set('session.cookie_secure', $options['secure'] ? '1' : '0');
        }
        
        // Session başlat
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Session hijacking koruması
        self::regenerateOnDemand();
        
        // Session timeout kontrolü
        self::checkTimeout($options['lifetime']);
        
        // Session validation
        self::validateSession();
    }
    
    /**
     * Session ID'yi yenile (Fixation koruması)
     */
    public static function regenerateOnDemand() {
        // Her 10 request'te bir ID yenile
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif ((time() - $_SESSION['last_regeneration']) > 600) { // 10 dakika
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
        
        // Login sonrası mutlaka yenile
        if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in']) {
            session_regenerate_id(true);
            unset($_SESSION['just_logged_in']);
        }
    }
    
    /**
     * Session timeout kontrolü
     */
    public static function checkTimeout($maxLifetime = 60 * 60 * 24 * 7) {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        } elseif ((time() - $_SESSION['last_activity']) > $maxLifetime) {
            // Session süresi dolmuş
            session_unset();
            session_destroy();
            self::startSecure();
            $_SESSION['timeout_error'] = 'Session süreniz dolmuş. Lütfen tekrar giriş yapın.';
        }
        
        // Activity time'ı güncelle
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Session validation (Hijacking kontrolü)
     */
    public static function validateSession() {
        // IP adresi kontrolü (opsiyonel - dev ortamında sorun çıkarabilir)
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        } else {
            // IP değişti mi kontrol et (sadece production'da)
            $currentIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $sessionIP = $_SESSION['ip_address'];
            
            // Aynı network'te değilse ve production ise
            if ($currentIP !== $sessionIP && getenv('APP_ENV') === 'production') {
                session_unset();
                session_destroy();
                self::startSecure();
                $_SESSION['security_error'] = 'Güvenlik kontrolü başarısız. Lütfen tekrar giriş yapın.';
            }
        }
        
        // User Agent kontrolü
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $userAgent;
        } else {
            // User Agent değişti mi kontrol et
            if ($_SESSION['user_agent'] !== $userAgent && getenv('APP_ENV') === 'production') {
                session_unset();
                session_destroy();
                self::startSecure();
                $_SESSION['security_error'] = 'Güvenlik kontrolü başarısız. Lütfen tekrar giriş yapın.';
            }
        }
        
        // CSRF token session'da yoksa oluştur
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Token yaşam süresi kontrolü (1 saat)
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
    }
    
    /**
     * Session'dan güvenli logout
     */
    public static function logout() {
        // Session'ı temizle
        $_SESSION = [];
        
        // Cookie'i sil
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Session'ı yok et
        session_destroy();
        
        // Yeni session başlat (güvenli)
        self::startSecure();
    }
    
    /**
     * Session'ı güvenli hale getir
     */
    public static function secureSession($userId, $userRole = 'user') {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userRole;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['just_logged_in'] = true;
        
        // IP ve User Agent kaydet
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // ID'yi yenile (Fixation koruması)
        session_regenerate_id(true);
    }
    
    /**
     * Oturum açık mı kontrol et
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Kullanıcı ID'si al
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Kullanıcı rolü al
     */
    public static function getUserRole() {
        return $_SESSION['user_role'] ?? 'guest';
    }
    
    /**
     * Oturum süresi kaldı mı kontrol et
     */
    public static function getSessionTimeLeft($maxLifetime = 60 * 60 * 24 * 7) {
        if (!isset($_SESSION['login_time'])) {
            return 0;
        }
        
        $timeLeft = $maxLifetime - (time() - $_SESSION['login_time']);
        return max(0, $timeLeft);
    }
}

/**
 * Helper fonksiyonlar
 */

/**
 * Güvenli session başlat
 */
function start_secure_session($options = []) {
    SessionSecurity::startSecure($options);
}

/**
 * Güvenli logout
 */
function secure_logout() {
    SessionSecurity::logout();
}

/**
 * Güvenli login (session set)
 */
function secure_login($userId, $userRole = 'user') {
    SessionSecurity::secureSession($userId, $userRole);
}

/**
 * Oturum açık mı?
 */
function is_logged_in() {
    return SessionSecurity::isLoggedIn();
}

/**
 * Session timeout var mı?
 */
function check_session_timeout() {
    SessionSecurity::checkTimeout();
}
?>
