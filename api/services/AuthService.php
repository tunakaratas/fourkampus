<?php
/**
 * Auth Service
 * 
 * Authentication business logic
 */

class AuthService {
    private static function getSystemDbPath() {
        return __DIR__ . '/../../public/unipanel.sqlite';
    }
    
    private static function ensureSystemDb() {
        $db_path = self::getSystemDbPath();
        $db_dir = dirname($db_path);
        if (!is_dir($db_dir)) {
            @mkdir($db_dir, 0755, true);
        }
        if (!file_exists($db_path)) {
            @touch($db_path);
            @chmod($db_path, 0666);
        }
        return $db_path;
    }
    
    private static function ensureEmailVerificationTable(SQLite3 $db) {
        @$db->exec("CREATE TABLE IF NOT EXISTS email_verification_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            code TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used INTEGER DEFAULT 0,
            verified INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // verified kolonu yoksa ekle (migration)
        try {
            @$db->exec("ALTER TABLE email_verification_codes ADD COLUMN verified INTEGER DEFAULT 0");
        } catch (Exception $e) {
            // ignore
        }
    }
    
    /**
     * Generate verification code
     */
    public static function generateCode() {
        return str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Save verification code
     */
    public static function saveVerificationCode($email, $code) {
        $db_path = self::ensureSystemDb();
        $db = new SQLite3($db_path);
        @$db->exec('PRAGMA journal_mode = WAL');
        
        self::ensureEmailVerificationTable($db);
        
        // Eski/bitmiş kodları temizle
        @$db->exec("DELETE FROM email_verification_codes WHERE expires_at < datetime('now') OR used = 1");
        
        // E-posta zaten doğrulanmış mı kontrol et (son 10 dakika içinde)
        $verified_check = $db->prepare("SELECT id FROM email_verification_codes WHERE email = ? AND verified = 1 AND created_at > datetime('now', '-10 minutes') ORDER BY created_at DESC LIMIT 1");
        if ($verified_check) {
            $verified_check->bindValue(1, $email, SQLITE3_TEXT);
            $verified_res = $verified_check->execute();
            $verified_row = $verified_res ? $verified_res->fetchArray(SQLITE3_ASSOC) : null;
            if ($verified_res) {
                $verified_res->finalize();
            }
            $verified_check->close();
            if ($verified_row) {
                // E-posta zaten doğrulanmış, tekrar kod göndermeye gerek yok
                $db->close();
                return false; // false döndür ama özel bir mesaj için AuthService'e ek kontrol eklenebilir
            }
        }
        
        // Aynı email için son 1 dakika içinde tekrar gönderimi engelle
        $check = $db->prepare("SELECT id FROM email_verification_codes WHERE email = ? AND created_at > datetime('now', '-1 minute') ORDER BY created_at DESC LIMIT 1");
        if ($check) {
            $check->bindValue(1, $email, SQLITE3_TEXT);
            $res = $check->execute();
            $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
            if ($res) {
                $res->finalize();
            }
            $check->close();
            if ($row) {
                $db->close();
                return false;
            }
        }
        
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $db->prepare("INSERT INTO email_verification_codes (email, code, expires_at, used, verified) VALUES (?, ?, ?, 0, 0)");
        if (!$stmt) {
            $db->close();
            return false;
        }
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->bindValue(2, $code, SQLITE3_TEXT);
        $stmt->bindValue(3, $expires_at, SQLITE3_TEXT);
        $ok = (bool)$stmt->execute();
        $stmt->close();
        $db->close();
        
        return $ok;
    }
    
    /**
     * Verify code
     */
    public static function verifyCode($email, $code) {
        $db_path = self::ensureSystemDb();
        if (!file_exists($db_path)) {
            return ['valid' => false, 'message' => 'Kod bulunamadı'];
        }
        
        $db = new SQLite3($db_path);
        @$db->exec('PRAGMA journal_mode = WAL');
        self::ensureEmailVerificationTable($db);
        
        $stmt = $db->prepare("SELECT id, expires_at, used FROM email_verification_codes WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1");
        if (!$stmt) {
            $db->close();
            return ['valid' => false, 'message' => 'Kod bulunamadı'];
        }
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->bindValue(2, $code, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($res) {
            $res->finalize();
        }
        $stmt->close();
        
        if (!$row) {
            $db->close();
            return ['valid' => false, 'message' => 'Kod bulunamadı'];
        }
        
        if ((int)$row['used'] === 1) {
            $db->close();
            return ['valid' => false, 'message' => 'Kod daha önce kullanılmış'];
        }
        
        if (strtotime($row['expires_at']) < time()) {
            $db->close();
            return ['valid' => false, 'message' => 'Kod süresi dolmuş'];
        }
        
        // verified=1
        $upd = $db->prepare("UPDATE email_verification_codes SET verified = 1 WHERE id = ?");
        if ($upd) {
            $upd->bindValue(1, (int)$row['id'], SQLITE3_INTEGER);
            @$upd->execute();
            $upd->close();
        }
        
        $db->close();
        return ['valid' => true, 'message' => 'Kod doğrulandı'];
    }
    
    /**
     * Check if email is verified
     */
    public static function isEmailVerified($email) {
        $db_path = self::getSystemDbPath();
        if (!file_exists($db_path)) {
            return false;
        }
        
        $db = new SQLite3($db_path);
        @$db->exec('PRAGMA journal_mode = WAL');
        self::ensureEmailVerificationTable($db);
        
        $stmt = $db->prepare("SELECT verified, used, expires_at FROM email_verification_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        if (!$stmt) {
            $db->close();
            return false;
        }
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($res) {
            $res->finalize();
        }
        $stmt->close();
        $db->close();
        
        if (!$row) {
            return false;
        }
        
        if ((int)($row['used'] ?? 0) === 1) {
            return false;
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return false;
        }
        
        return (int)($row['verified'] ?? 0) === 1;
    }
    
    /**
     * Remove verification code
     */
    public static function removeCode($email) {
        $db_path = self::getSystemDbPath();
        if (!file_exists($db_path)) {
            return;
        }
        $db = new SQLite3($db_path);
        @$db->exec('PRAGMA journal_mode = WAL');
        self::ensureEmailVerificationTable($db);
        $stmt = $db->prepare("DELETE FROM email_verification_codes WHERE email = ?");
        if ($stmt) {
            $stmt->bindValue(1, $email, SQLITE3_TEXT);
            @$stmt->execute();
            $stmt->close();
        }
        $db->close();
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $code) {
        try {
            $smtp = [];
            $credPath = __DIR__ . '/../../config/credentials.php';
            if (file_exists($credPath)) {
                $credentials = require $credPath;
            $smtp = $credentials['smtp'] ?? [];
            }
            
            $communicationPath = __DIR__ . '/../../templates/functions/communication.php';
            if (file_exists($communicationPath)) {
                require_once $communicationPath;
            }
            
            $subject = 'Four Kampüs - Doğrulama Kodu';
            $htmlBody = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #667eea;'>Four Kampüs</h2>
                    <p>Doğrulama kodunuz:</p>
                    <div style='background-color: #f8f9fa; border: 2px solid #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <h1 style='color: #667eea; font-size: 36px; margin: 0; letter-spacing: 4px;'>$code</h1>
                    </div>
                    <p>Kod 1 saat geçerlidir.</p>
                </div>
            </body>
            </html>
            ";
            
            $from_name = $smtp['from_name'] ?? 'Four Kampüs';
            $from_email = $smtp['from_email'] ?? ($smtp['username'] ?? 'admin@foursoftware.com.tr');
            
            if (function_exists('send_smtp_mail')) {
                $smtpConfig = [
                    'host' => $smtp['host'] ?? '',
                    'port' => (int)($smtp['port'] ?? 587),
                    'secure' => $smtp['encryption'] ?? 'tls',
                    'username' => $smtp['username'] ?? '',
                    'password' => $smtp['password'] ?? '',
                ];
                
                // SMTP creds yoksa fallback mail()
                if (!empty($smtpConfig['host']) && !empty($smtpConfig['username']) && !empty($smtpConfig['password'])) {
                return send_smtp_mail($email, $subject, $htmlBody, $from_name, $from_email, $smtpConfig);
                }
            } else {
                // fallthrough
            }
            
            $headers = "From: {$from_name} <{$from_email}>\r\n";
            $headers .= "Reply-To: {$from_email}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            return @mail($email, $subject, $htmlBody, $headers);
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create API token for user (same format as login)
     */
    public static function createApiToken($userId) {
        $db_path = self::ensureSystemDb();
        $db = new SQLite3($db_path, SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000);
        @$db->exec('PRAGMA journal_mode = WAL');
        @$db->exec('PRAGMA synchronous = NORMAL');
        
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
            $db->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_expires_at ON api_tokens(expires_at)");
        }
        
        // token_hash kolonu yoksa ekle (migration)
        try {
            $db->exec("ALTER TABLE api_tokens ADD COLUMN token_hash TEXT");
        } catch (Exception $e) {
            // ignore
        }
        
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        $created_at = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("INSERT INTO api_tokens (user_id, token, token_hash, expires_at, created_at, last_used_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
        if (!$stmt) {
            $db->close();
            throw new Exception('Token oluşturulamadı');
        }
        $stmt->bindValue(1, (int)$userId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $token, SQLITE3_TEXT);
        $stmt->bindValue(3, $token_hash, SQLITE3_TEXT);
        $stmt->bindValue(4, $expires_at, SQLITE3_TEXT);
        $stmt->bindValue(5, $created_at, SQLITE3_TEXT);
        if (!$stmt->execute()) {
            $stmt->close();
            $db->close();
            throw new Exception('Token oluşturulamadı');
        }
        
        $stmt->close();
        $db->close();
        return $token;
    }
    
    /**
     * Create user account
     */
    public static function createUser($email, $password, $firstName, $lastName) {
        $db_path = self::ensureSystemDb();
        
        $db = new SQLite3($db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Tablo oluştur
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
        
        // phone_verified kolonu kontrolü
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
        
        // Şifre hashle
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        if ($password_hash === false) {
            $db->close();
            throw new Exception('Şifre işleme hatası');
        }
        
        // Kullanıcı oluştur
        $stmt = $db->prepare("INSERT INTO system_users (email, password_hash, first_name, last_name, email_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->bindValue(2, $password_hash, SQLITE3_TEXT);
        $stmt->bindValue(3, $firstName, SQLITE3_TEXT);
        $stmt->bindValue(4, $lastName, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            $error = $db->lastErrorMsg();
            $db->close();
            if (strpos($error, 'UNIQUE') !== false) {
                throw new Exception('Bu e-posta adresi zaten kayıtlı');
            }
            throw new Exception('Kayıt başarısız');
        }
        
        $user_id = $db->lastInsertRowID();
        $stmt->close();
        $db->close();
        
        return $user_id;
    }
}
