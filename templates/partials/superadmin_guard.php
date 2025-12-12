<?php
require_once __DIR__ . '/logging.php';
/**
 * SuperAdmin auto login helper & guard.
 * Enforces token-based access before allowing the legacy auto-login shortcut.
 */

if (!function_exists('superadmin_env_flag_true')) {
    function superadmin_env_flag_true($value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }
}

if (!function_exists('superadmin_auto_login_enabled')) {
    function superadmin_auto_login_enabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return $enabled;
        }

        // Localhost için otomatik enable et
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocalhost = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost'], true) || 
                       strpos($remoteIp, '192.168.') === 0 || 
                       strpos($remoteIp, '10.') === 0;
        
        if ($isLocalhost) {
            $enabled = true;
            return $enabled;
        }

        $flag = getenv('ENABLE_SUPERADMIN_LOGIN') ?? ($_SERVER['ENABLE_SUPERADMIN_LOGIN'] ?? null);
        $enabled = superadmin_env_flag_true($flag);

        return $enabled;
    }
}

if (!function_exists('superadmin_trusted_ip')) {
    function superadmin_trusted_ip(): bool
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remoteIp === '') {
            return false;
        }

        // Localhost için otomatik izin ver
        $isLocalhost = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost'], true) || 
                       strpos($remoteIp, '192.168.') === 0 || 
                       strpos($remoteIp, '10.') === 0;
        
        if ($isLocalhost) {
            return true;
        }

        $allowed = getenv('SUPERADMIN_ALLOWED_IPS') ?? ($_SERVER['SUPERADMIN_ALLOWED_IPS'] ?? '127.0.0.1,::1');
        $allowedList = array_filter(array_map('trim', explode(',', $allowed)));

        return in_array($remoteIp, $allowedList, true);
    }
}

if (!function_exists('superadmin_auto_login_expected_token')) {
    function superadmin_auto_login_expected_token(): ?string
    {
        static $tokenLoaded = false;
        static $token = null;

        if ($tokenLoaded) {
            return $token;
        }

        $envToken = getenv('SUPERADMIN_LOGIN_TOKEN') ?? ($_SERVER['SUPERADMIN_LOGIN_TOKEN'] ?? null);
        if (is_string($envToken)) {
            $envToken = trim($envToken);
        }

        $token = $envToken ?: null;
        $tokenLoaded = true;

        return $token;
    }
}

if (!function_exists('superadmin_is_auto_login_allowed')) {
    function superadmin_is_auto_login_allowed($providedToken): bool
    {
        if (!superadmin_auto_login_enabled()) {
            return false;
        }

        $expected = superadmin_auto_login_expected_token();
        if (!$expected) {
            return false;
        }

        if (!is_string($providedToken) || $providedToken === '') {
            return false;
        }

        return hash_equals($expected, (string)$providedToken);
    }
}

if (!function_exists('handle_superadmin_auto_login')) {
    function handle_superadmin_auto_login(): void
    {
        if (!isset($_GET['superadmin_login']) || isset($_SESSION['admin_id'])) {
            return;
        }

        $providedToken = $_GET['superadmin_login'];
        if (!superadmin_is_auto_login_allowed($providedToken) || !superadmin_trusted_ip()) {
            http_response_code(403);
            tpl_error_log(sprintf(
                'Blocked superadmin_login attempt from %s (env=%s)',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                defined('APP_ENV') ? APP_ENV : 'unknown'
            ));
            exit('SuperAdmin auto login is disabled on this environment.');
        }

        try {
            $db = get_db();

            // Varsayılan admin kullanıcısı oluştur
            $existing_admin_stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE club_id = ?");
            $existing_admin_stmt->bindValue(1, 1, SQLITE3_INTEGER);
            $existing_admin = $existing_admin_stmt->execute()->fetchArray()[0];
            if ($existing_admin == 0) {
                // Güvenlik: Environment variable'dan al veya rastgele güçlü şifre oluştur
                $default_password = getenv('DEFAULT_ADMIN_PASSWORD');
                if (empty($default_password)) {
                    // Rastgele güçlü şifre oluştur (32 karakter, alfanumerik + özel karakterler)
                    $default_password = bin2hex(random_bytes(16));
                    tpl_error_log('WARNING: DEFAULT_ADMIN_PASSWORD environment variable not set. Generated random password. Admin must change password on first login.');
                }
                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                $insert_stmt = $db->prepare("INSERT INTO admins (username, password_hash, club_id) VALUES (?, ?, ?)");
                $insert_stmt->bindValue(1, 'admin', SQLITE3_TEXT);
                $insert_stmt->bindValue(2, $hashed_password, SQLITE3_TEXT);
                $insert_stmt->bindValue(3, 1, SQLITE3_INTEGER);
                $insert_stmt->execute();
            }

            // Admin kullanıcısını bul
            $admin_stmt = $db->prepare("SELECT id FROM admins WHERE club_id = 1");
            $admin_result = $admin_stmt->execute();
            $admin = $admin_result->fetchArray(SQLITE3_ASSOC);
            if ($admin) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['club_id'] = 1;
                $_SESSION['is_superadmin'] = true;

                // Giriş logu
                $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
                    id INTEGER PRIMARY KEY,
                    community_name TEXT,
                    action TEXT,
                    details TEXT,
                    timestamp TEXT DEFAULT CURRENT_TIMESTAMP
                )");

                $log_stmt = $db->prepare("INSERT INTO admin_logs (community_name, action, details) VALUES (?, ?, ?)");
                $log_stmt->bindValue(1, '1', SQLITE3_TEXT);
                $log_stmt->bindValue(2, 'SuperAdmin Giriş', SQLITE3_TEXT);
                $log_stmt->bindValue(3, 'SuperAdmin panelinden otomatik giriş yapıldı', SQLITE3_TEXT);
                $log_stmt->execute();
            }

            $db->close();

            $nonceAttr = function_exists('tpl_script_nonce_attr') ? tpl_script_nonce_attr() : '';
            echo "<script{$nonceAttr}>
                document.body.innerHTML = `
                    <div style='position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; z-index: 9999; font-family: Inter, sans-serif;'>
                        <div style='text-align: center; color: white; max-width: 500px; padding: 30px;'>
                            <div style='width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 40px; backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0,0,0,0.1);'>
                                <div style='width: 80px; height: 80px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid white; border-radius: 8px; animation: spin 1s linear infinite;'></div>
                            </div>
                            <h1 style='margin: 0 0 15px; font-size: 32px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);'>SuperAdmin Girişi</h1>
                            <p style='margin: 0 0 40px; font-size: 18px; opacity: 0.9; font-weight: 400;'>Topluluk yönetim paneline yönlendiriliyorsunuz...</p>
                            <div style='width: 100%; height: 6px; background: rgba(255,255,255,0.2); border-radius: 2px; margin-bottom: 30px; overflow: hidden;'>
                                <div style='width: 0%; height: 100%; background: linear-gradient(90deg, #00f5ff, #00d4ff); border-radius: 2px; animation: progress 4s ease-in-out forwards;'></div>
                            </div>
                            <div style='text-align: left; margin-top: 30px;'>
                                <div class='loading-step' style='margin-bottom: 15px; opacity: 0; animation: fadeInUp 0.6s ease forwards;'>
                                    <div style='display: flex; align-items: center; font-size: 16px;'>
                                        <div style='width: 10px; height: 10px; background: #00f5ff; border-radius: 2px; margin-right: 15px; animation: pulse 1.5s infinite;'></div>
                                        <span>SuperAdmin yetkileri doğrulanıyor...</span>
                                    </div>
                                </div>
                                <div class='loading-step' style='margin-bottom: 15px; opacity: 0; animation: fadeInUp 0.6s ease 0.8s forwards;'>
                                    <div style='display: flex; align-items: center; font-size: 16px;'>
                                        <div style='width: 10px; height: 10px; background: #00f5ff; border-radius: 2px; margin-right: 15px; animation: pulse 1.5s infinite 0.8s;'></div>
                                        <span>Topluluk veritabanına bağlanılıyor...</span>
                                    </div>
                                </div>
                                <div class='loading-step' style='margin-bottom: 15px; opacity: 0; animation: fadeInUp 0.6s ease 1.6s forwards;'>
                                    <div style='display: flex; align-items: center; font-size: 16px;'>
                                        <div style='width: 10px; height: 10px; background: #00f5ff; border-radius: 2px; margin-right: 15px; animation: pulse 1.5s infinite 1.6s;'></div>
                                        <span>Otomatik giriş işlemi başlatılıyor...</span>
                                    </div>
                                </div>
                                <div class='loading-step' style='margin-bottom: 15px; opacity: 0; animation: fadeInUp 0.6s ease 2.4s forwards;'>
                                    <div style='display: flex; align-items: center; font-size: 16px;'>
                                        <div style='width: 10px; height: 10px; background: #00f5ff; border-radius: 2px; margin-right: 15px; animation: pulse 1.5s infinite 2.4s;'></div>
                                        <span>Yönetim paneline yönlendiriliyor...</span>
                                    </div>
                                </div>
                            </div>
                            <div style='margin-top: 40px; font-size: 14px; opacity: 0.8;'>
                                <span id='status-text'>Bağlantı kuruluyor...</span>
                            </div>
                        </div>
                    </div>
                    <style>
                        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                        @keyframes progress { 0% { width: 0%; } 100% { width: 100%; } }
                        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
                        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
                    </style>
                `;
                const statusTexts = [
                    'Bağlantı kuruluyor...',
                    'SuperAdmin yetkileri doğrulanıyor...',
                    'Topluluk veritabanına bağlanılıyor...',
                    'Otomatik giriş işlemi başlatılıyor...',
                    'Yönetim paneline yönlendiriliyor...',
                    'Giriş tamamlanıyor...'
                ];
                let statusIndex = 0;
                const statusInterval = setInterval(() => {
                    const statusEl = document.getElementById('status-text');
                    if (statusEl) {
                        statusEl.textContent = statusTexts[statusIndex];
                        statusIndex = (statusIndex + 1) % statusTexts.length;
                    }
                }, 600);
                setTimeout(() => {
                    clearInterval(statusInterval);
                    window.location.href = 'index.php';
                }, 4000);
            </script>";
            exit;
        } catch (Exception $e) {
            tpl_error_log('SuperAdmin auto login error: ' . $e->getMessage());
        }
    }
}


