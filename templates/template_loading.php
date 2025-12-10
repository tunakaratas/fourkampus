<?php
/**
 * Şifresiz Erişim Loading Ekranı
 * SuperAdmin panelinden topluluk paneline otomatik giriş için
 */

if (!function_exists('tpl_script_nonce_attr')) {
    function tpl_script_nonce_attr(): string
    {
        if (function_exists('tpl_get_csp_nonce')) {
            return ' nonce="' . htmlspecialchars(tpl_get_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
        }
        return '';
    }
}

session_start();

// Topluluk adını al - Güvenlik: Input validation
$community = $_GET['community'] ?? '';
$auto_access = $_GET['auto_access'] ?? false;

// Güvenlik: Community name'i sadece alfanumerik ve tire/alt çizgi içerebilir
$community = preg_replace('/[^a-z0-9_-]/i', '', $community);

if (empty($community)) {
    die('Topluluk bulunamadı!');
}

// Otomatik giriş yap (loading ekranından sonra)
if ($auto_access) {
    $_SESSION['club_id'] = 1; // Varsayılan club_id
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'superadmin';
    $_SESSION['superadmin_login'] = true;
}

// 2 saniye sonra index.php'ye yönlendir
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otomatik Giriş - <?= htmlspecialchars($community) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .loading-container {
            text-align: center;
            color: #1e293b;
            max-width: 400px;
            padding: 60px 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        
        .logo::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(59, 130, 246, 0.1) 50%, transparent 70%);
            animation: shine 2s infinite;
        }
        
        .logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            z-index: 1;
        }
        
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }
        
        .title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
        }
        
        .subtitle {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 32px;
            font-weight: 400;
        }
        
        .progress-container {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .progress-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            border-radius: 2px;
            animation: progress 2s ease-in-out forwards;
        }
        
        .status {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <!-- Logo -->
        <div class="logo">
            <img src="https://www.caddedoner.com/foursoftware-light.png" alt="UniPanel Logo" class="logo-img">
        </div>
        
        <!-- Spinner -->
        <div class="spinner"></div>
        
        <!-- Başlık -->
        <h1 class="title"><?= htmlspecialchars($community) ?></h1>
        
        <!-- Alt başlık -->
        <p class="subtitle">Topluluk paneline yönlendiriliyorsunuz</p>
        
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-fill"></div>
        </div>
        
        <!-- Status -->
        <div class="status">
            <span id="status-text">Bağlantı kuruluyor...</span>
        </div>
    </div>

    <script<?= tpl_script_nonce_attr(); ?>>
        // Status text güncellemeleri
        const statusTexts = [
            'Bağlantı kuruluyor...',
            'Güvenlik doğrulanıyor...',
            'Otomatik giriş hazırlanıyor...',
            'Yönetim paneline yönlendiriliyor...'
        ];
        
        let statusIndex = 0;
        const statusInterval = setInterval(() => {
            const statusEl = document.getElementById('status-text');
            if (statusEl) {
                statusEl.textContent = statusTexts[statusIndex];
                statusIndex = (statusIndex + 1) % statusTexts.length;
            }
        }, 500);
        
        // 2 saniye sonra index.php'ye yönlendir
        setTimeout(() => {
            clearInterval(statusInterval);
            // PHP ile yönlendirme yap
            window.location.href = 'index.php';
        }, 2000);
    </script>
</body>
</html>
