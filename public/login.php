<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - LOGIN SAYFASI (GENEL SİSTEM GİRİŞİ)
// =================================================================

// Security helper'ı yükle
require_once __DIR__ . '/security_helper.php';

// Güvenli session başlat (security headers dahil)
secure_session_start();

// Production'da hataları gizle
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../system/logs/php_errors.log');

// Genel sistem veritabanı yolu
define('SYSTEM_DB_PATH', __DIR__ . '/unipanel.sqlite');

// Login fonksiyonunu tanımla (genel sistem girişi)
function login_user($email, $password) {
    try {
        // Veritabanı dosyasının var olduğunu kontrol et
        if (!file_exists(SYSTEM_DB_PATH)) {
            return ['success' => false, 'message' => 'Veritabanı dosyası bulunamadı. Lütfen önce kayıt olun.'];
        }
        
        // Veritabanı okunabilir mi kontrol et
        if (!is_readable(SYSTEM_DB_PATH)) {
            return ['success' => false, 'message' => 'Veritabanı dosyası okunamıyor'];
        }
        
        // Güvenli database bağlantısı
        $db = get_safe_db_connection(SYSTEM_DB_PATH, false);
        if (!$db) {
            return ['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.'];
        }
        
        // Tablo var mı kontrol et
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_users'");
        if (!$table_check->fetchArray()) {
            $db->close();
            return ['success' => false, 'message' => 'Veritabanı tablosu bulunamadı. Lütfen önce kayıt olun.'];
        }
        
        $stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name, university, department FROM system_users WHERE email = ? AND is_active = 1");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            $db->close();
            return ['success' => false, 'message' => 'Email veya şifre hatalı'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            $db->close();
            return ['success' => false, 'message' => 'Email veya şifre hatalı'];
        }
        
        // Son giriş zamanını güncelle (hata olursa devam et)
        try {
            $update_stmt = $db->prepare("UPDATE system_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
            @$update_stmt->execute();
        } catch (Exception $e) {
            // last_login güncellemesi başarısız olsa bile giriş devam etsin
        }
        
        $db->close();
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                'university' => $user['university'],
                'department' => $user['department']
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Giriş hatası: ' . $e->getMessage()];
    }
}

$login_error = null;

// Kullanıcı girişi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $login_error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        log_security_event('csrf_failure', ['page' => 'login']);
    } else {
        // Input sanitization
        $email = sanitize_input($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $login_error = "Lütfen email ve şifrenizi girin.";
        } else {
            // Email validation
            $email_validation = validate_email($email);
            if (!$email_validation['valid']) {
                $login_error = $email_validation['message'];
            } else {
                // Account lockout kontrolü
                $lockout_check = check_account_lockout($email);
                if ($lockout_check['locked']) {
                    $login_error = $lockout_check['message'];
                    log_security_event('login_attempt_locked', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } else {
                    // Rate limiting kontrolü (IP bazlı)
                    $rate_check = check_rate_limit('login', 5, 300); // 5 dakikada max 5 deneme
                    if (!$rate_check['allowed']) {
                        $login_error = $rate_check['message'];
                        log_security_event('rate_limit_exceeded', ['action' => 'login', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } else {
                        $result = login_user($email, $password);
                        if ($result['success']) {
                            // Başarılı giriş - lockout'u temizle
                            clear_account_lockout($email);
                            
                            // Session regeneration (session fixation koruması)
                            session_regenerate_id(true);
                            
                            $_SESSION['user_logged_in'] = true;
                            $_SESSION['user_id'] = $result['user']['id'];
                            $_SESSION['user_email'] = $result['user']['email'];
                            $_SESSION['user_name'] = $result['user']['full_name'];
                            $_SESSION['user_first_name'] = $result['user']['first_name'];
                            $_SESSION['user_last_name'] = $result['user']['last_name'];
                            $_SESSION['last_activity'] = time();
                            
                            log_security_event('login_success', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                            
                            header('Location: index.php');
                            exit;
                        } else {
                            // Başarısız giriş - lockout kaydet
                            record_failed_attempt($email);
                            $login_error = $result['message'];
                            log_security_event('login_failed', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'reason' => $result['message']]);
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - UniPanel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include __DIR__ . '/../templates/partials/tailwind_cdn_loader.php'; ?>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.1), 0 2px 4px -1px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 40%, #ffffff 100%);
            min-height: 100vh;
            padding: clamp(2rem, 6vw, 5rem);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            transition: color var(--transition-base);
            z-index: 1;
        }
        
        .input-wrapper input:focus ~ .input-icon,
        .input-wrapper input:not(:placeholder-shown) ~ .input-icon {
            color: #6366f1;
        }
        
        .input-wrapper input {
            padding-left: 44px;
            transition: all var(--transition-base);
        }
        
        .input-wrapper input:focus {
            padding-left: 44px;
        }
        
        .card-container {
            position: relative;
            z-index: 1;
        }
        
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .form-input {
            transition: all var(--transition-base);
            background: var(--bg-primary);
        }
        
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            background: #6366f1;
            transition: all var(--transition-base);
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .link-primary {
            color: #6366f1;
        }
        
        .link-primary:hover {
            color: #4f46e5;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .auth-page {
            position: relative;
            min-height: calc(100vh - clamp(2rem, 6vw, 5rem) * 2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .auth-blur {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.12), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(129, 140, 248, 0.18), transparent 60%);
            z-index: -2;
            filter: blur(0px);
            display: none;
        }

        .auth-card {
            position: relative;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            overflow: hidden;
            backdrop-filter: none;
            background: transparent;
            z-index: 1;
        }

        @media (min-width: 900px) {
            .auth-card {
                grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            }
        }

        @media (min-width: 1024px) {
            body {
                padding: 0;
            }

            .auth-page {
                max-width: none;
                min-height: 100vh;
                align-items: stretch;
                justify-content: stretch;
            }

            .auth-card {
                min-height: 100vh;
                grid-template-columns: minmax(0, 0.75fr) minmax(0, 1fr);
            }

            .auth-card-media,
            .auth-card-form {
                height: 100%;
            }
        }

        .auth-card-media {
            position: relative;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: clamp(2.5rem, 5vw, 3.75rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.5rem, 3vw, 2.5rem);
            overflow: hidden;
        }

        .auth-card-media::before,
        .auth-card-media::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            filter: blur(0);
            opacity: 0.65;
            z-index: 0;
        }

        .auth-card-media::before {
            width: clamp(220px, 40vw, 320px);
            height: clamp(220px, 40vw, 320px);
            background: rgba(255, 255, 255, 0.12);
            top: clamp(-140px, -10vw, -80px);
            right: clamp(-120px, -8vw, -70px);
        }

        .auth-card-media::after {
            width: clamp(160px, 30vw, 280px);
            height: clamp(160px, 30vw, 260px);
            background: rgba(255, 255, 255, 0.08);
            bottom: clamp(-120px, -8vw, -70px);
            left: clamp(-120px, -8vw, -70px);
        }

        .auth-media-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: clamp(1.25rem, 2.5vw, 1.75rem);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-brand-icon {
            width: clamp(60px, 10vw, 72px);
            height: clamp(60px, 10vw, 72px);
            border-radius: 20px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.16);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .auth-brand-icon i {
            font-size: clamp(1.8rem, 3vw, 2.2rem);
        }

        .auth-brand h1 {
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .auth-brand p {
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
        }

        .auth-headline {
            font-size: clamp(2rem, 3.6vw, 2.75rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.045em;
        }

        .auth-subheadline {
            font-size: clamp(1rem, 2vw, 1.125rem);
            color: rgba(255, 255, 255, 0.8);
            max-width: 32rem;
            line-height: 1.65;
        }

        .auth-benefits {
            list-style: none;
            display: grid;
            gap: 0.9rem;
            padding: 0;
            margin: 0;
        }

        .auth-benefits li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.975rem;
            color: rgba(255, 255, 255, 0.88);
            font-weight: 500;
        }

        .auth-benefits i {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.15);
        }

        .auth-card-form {
            position: relative;
            background: transparent;
            padding: clamp(2.5rem, 5vw, 4rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.75rem, 3vw, 2.5rem);
        }

        .auth-form-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .auth-form-header h2 {
            font-size: clamp(1.75rem, 2.8vw, 2.1rem);
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.035em;
        }

        .auth-form-header p {
            color: var(--text-secondary);
            font-size: 0.975rem;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.4rem;
        }

        .input-wrapper input {
            padding-left: 48px;
        }

        .auth-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            border: none;
            box-shadow: 0 10px 25px -10px rgba(79, 70, 229, 0.65);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-1px);
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .auth-footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.92rem;
            color: var(--text-secondary);
        }

        .auth-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        @media (max-width: 899px) {
            body {
                padding: clamp(1.5rem, 6vw, 3rem);
            }

            .auth-card {
                transform: translateX(0);
            }

            .auth-card-media {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page fade-in-up">
        <div class="auth-blur"></div>
        <div class="auth-card">
            <div class="auth-card-media">
                <div class="auth-media-content">
                    <div class="auth-brand">
                        <div class="auth-brand-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div>
                            <h1>UniPanel</h1>
                            <p>Topluluk Portalı</p>
                        </div>
                    </div>
                    <div>
                        <h2 class="auth-headline">Güçlü topluluk deneyimini keşfedin</h2>
                        <p class="auth-subheadline">Etkinliklerinizi yönetin, üyelerle iletişim kurun ve kampanyaları tek panelden takip edin. UniPanel ile topluluğunuzu bir üst seviyeye taşıyın.</p>
                    </div>
                    <ul class="auth-benefits">
                        <li><i class="fas fa-check"></i> Anlık bildirim ve duyuru yönetimi</li>
                        <li><i class="fas fa-check"></i> Etkinlik katılım takibi ve raporlama</li>
                        <li><i class="fas fa-check"></i> Üyelerle hızlı, etkili iletişim</li>
                    </ul>
                </div>
            </div>
            <div class="auth-card-form">
                <div class="auth-form-header">
                    <h2>Hoş Geldiniz</h2>
                    <p>Hesabınıza giriş yapın ve UniPanel'in sunduğu tüm yönetim araçlarına erişin.</p>
                </div>

                <?php if ($login_error): ?>
                    <div class="mb-2 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                        <span class="font-medium"><?= htmlspecialchars($login_error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="action" value="login">
                    <?= csrf_token_field() ?>

                    <div>
                        <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Email Adresi</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" required 
                                   class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                   style="border-color: var(--border-color); color: var(--text-primary);"
                                   placeholder="ornek@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Şifre</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" required 
                                   class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                   style="border-color: var(--border-color); color: var(--text-primary);"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <div class="auth-actions">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-2 cursor-pointer" 
                                   style="border-color: var(--border-color); accent-color: #6366f1;">
                            <span class="ml-2 text-sm font-medium" style="color: var(--text-secondary);">Beni hatırla</span>
                        </label>
                        <a href="#" class="text-sm font-semibold link-primary">Şifremi unuttum</a>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base" style="letter-spacing: -0.01em;">
                        Giriş Yap
                    </button>
                </form>

                <div class="auth-footer-links">
                    <div class="auth-divider">Henüz hesabınız yok mu?</div>
                    <a href="register.php" class="link-primary">
                        <span>Kayıt Ol</span>
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="index.php" class="link-primary" style="color: var(--text-light);">
                        <i class="fas fa-arrow-left"></i>
                        <span>Ana sayfaya dön</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

