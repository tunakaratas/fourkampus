<?php
/**
 * Auth Controller
 * 
 * Authentication işlemleri: register, login, logout
 */

require_once __DIR__ . '/../router.php';
require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Sanitizer.php';

class AuthController {
    
    /**
     * POST /api/v1/auth/login
     */
    public function login($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            APIResponse::methodNotAllowed();
        }
        
        require_once __DIR__ . '/../auth_middleware.php';
        
        // Rate limiting kontrolü
        $realIP = getRealIP();
        if (!checkRateLimit(10, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen 1 dakika sonra tekrar deneyin.', 429);
        }
        
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput ?: '{}', true);
        
        if (!is_array($input)) {
            APIResponse::error('Geçersiz JSON formatı');
        }
        
        if (empty($input['email']) || empty($input['password'])) {
            APIResponse::error('Email ve şifre gerekli');
        }
        
        $email = Sanitizer::input(trim($input['email']), 'email');
        $password = Sanitizer::input($input['password'], 'raw');
        
        // Email validation
        $emailValidation = Validator::email($email);
        if (!$emailValidation['valid']) {
            APIResponse::error($emailValidation['message']);
        }
        
        // Veritabanı bağlantısı
        $system_db_path = __DIR__ . '/../../public/unipanel.sqlite';
        
        if (!file_exists($system_db_path)) {
            APIResponse::error('Veritabanı dosyası bulunamadı', 500);
        }
        
        try {
            $db = new SQLite3($system_db_path, SQLITE3_OPEN_READWRITE);
            $db->busyTimeout(5000);
            @$db->exec('PRAGMA journal_mode = WAL');
            @$db->exec('PRAGMA synchronous = NORMAL');
            @$db->exec('PRAGMA cache_size = -64000');
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            APIResponse::error('Veritabanı bağlantı hatası', 500);
        }
        
        // Brute force koruması
        $bruteForceCheck = checkBruteForceProtection($email, $db);
        if ($bruteForceCheck['locked']) {
            $db->close();
            APIResponse::error($bruteForceCheck['message'], 429);
        }
        
        // Kullanıcıyı bul
        $stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name, is_active, failed_login_attempts FROM system_users WHERE LOWER(email) = LOWER(?)");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if (!$result) {
            $db->close();
            APIResponse::error('Veritabanı hatası', 500);
        }
        
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            // Brute force kaydı güncelle
            $this->recordFailedLogin($email, $db);
            $db->close();
            APIResponse::error('Email veya şifre hatalı', 401);
        }
        
        // Şifre kontrolü
        if (!password_verify($password, $user['password_hash'])) {
            // Başarısız giriş kaydı
            $this->recordFailedLogin($email, $db);
            $db->close();
            APIResponse::error('Email veya şifre hatalı', 401);
        }
        
        // Kullanıcı aktif mi?
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            $db->close();
            APIResponse::error('Hesabınız deaktif edilmiş', 403);
        }
        
        // Başarılı giriş - failed_login_attempts sıfırla
        $updateStmt = $db->prepare("UPDATE system_users SET failed_login_attempts = 0, last_login = datetime('now') WHERE id = ?");
        $updateStmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $updateStmt->execute();
        
        // Token oluştur (güvenli random)
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        $created_at = date('Y-m-d H:i:s');
        
        // api_tokens tablosunu kontrol et ve oluştur
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='api_tokens'");
        if (!$table_check || !$table_check->fetchArray()) {
            $db->exec("CREATE TABLE IF NOT EXISTS api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                token_hash TEXT,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_user_id ON api_tokens(user_id)");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_token ON api_tokens(token)");
        }
        
        // token_hash kolonu yoksa ekle
        try {
            $db->exec("ALTER TABLE api_tokens ADD COLUMN token_hash TEXT");
        } catch (Exception $e) {
            // Kolon zaten varsa hata vermez
        }
        
        // Eski token'ları kontrol et (max 5 aktif token)
        $checkTokens = $db->prepare("SELECT COUNT(*) as count FROM api_tokens WHERE user_id = ? AND expires_at > datetime('now') AND revoked_at IS NULL");
        $checkTokens->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $tokenCountResult = $checkTokens->execute();
        $tokenCount = $tokenCountResult->fetchArray(SQLITE3_ASSOC);
        
        if ($tokenCount && (int)$tokenCount['count'] >= 5) {
            $revokeOld = $db->prepare("UPDATE api_tokens SET revoked_at = datetime('now') WHERE user_id = ? AND expires_at > datetime('now') AND revoked_at IS NULL ORDER BY created_at ASC LIMIT 1");
            $revokeOld->bindValue(1, $user['id'], SQLITE3_INTEGER);
            @$revokeOld->execute();
        }
        
        // Yeni token'ı ekle
        $token_stmt = $db->prepare("INSERT INTO api_tokens (user_id, token, token_hash, expires_at, created_at, last_used_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
        $token_stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $token_stmt->bindValue(2, $token, SQLITE3_TEXT);
        $token_stmt->bindValue(3, $token_hash, SQLITE3_TEXT);
        $token_stmt->bindValue(4, $expires_at, SQLITE3_TEXT);
        $token_stmt->bindValue(5, $created_at, SQLITE3_TEXT);
        
        if (!$token_stmt->execute()) {
            $db->close();
            APIResponse::error('Token oluşturulamadı', 500);
        }
        
        $db->close();
        
        // Kullanıcı bilgilerini döndür
        APIResponse::success([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'token' => $token
        ], 'Giriş başarılı');
    }
    
    private function recordFailedLogin($email, $db) {
        $stmt = $db->prepare("UPDATE system_users SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1 WHERE LOWER(email) = LOWER(?)");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    /**
     * POST /api/v1/auth/logout
     */
    public function logout($params = []) {
        // Logout logic
        APIResponse::success(null, 'Çıkış yapıldı');
    }
    
    /**
     * GET /api/v1/auth/me
     */
    public function me($params = []) {
        $user = $GLOBALS['currentUser'] ?? null;
        if (!$user) {
            APIResponse::unauthorized();
        }
        
        APIResponse::success($user);
    }
    
    // register_2fa metodu kaldırıldı - kayıt altyapısı kaldırıldı
    
}
