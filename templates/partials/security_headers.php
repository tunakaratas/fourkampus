<?php
/**
 * Security Headers Helper
 * Merkezi güvenlik header'ları yönetimi
 */

if (!function_exists('tpl_inline_handler_transform_enabled')) {
    function tpl_inline_handler_transform_enabled(): bool
    {
        $flag = getenv('TPL_INLINE_HANDLER_TRANSFORM') ?: ($_SERVER['TPL_INLINE_HANDLER_TRANSFORM'] ?? null);
        if ($flag === null) {
            return false;
        }

        $flag = strtolower(trim((string)$flag));
        return in_array($flag, ['1', 'true', 'on', 'yes'], true);
    }
}

if (!function_exists('tpl_get_csp_nonce')) {
    function tpl_get_csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return $nonce;
    }
}

if (!function_exists('tpl_script_nonce_attr')) {
    function tpl_script_nonce_attr(): string
    {
        $nonce = tpl_get_csp_nonce();
        return ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('tpl_style_nonce_attr')) {
    function tpl_style_nonce_attr(): string
    {
        $nonce = tpl_get_csp_nonce();
        return ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('set_security_headers')) {
    function set_security_headers(): void
    {
        // Headers zaten gönderilmişse işlem yapma
        if (headers_sent()) {
            return;
        }

        // X-Frame-Options: Clickjacking koruması
        header('X-Frame-Options: DENY');

        // X-Content-Type-Options: MIME type sniffing koruması
        header('X-Content-Type-Options: nosniff');

        // X-XSS-Protection: XSS koruması (eski tarayıcılar için)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer-Policy: Referrer bilgisi kontrolü
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions-Policy: Özellik izinleri kontrolü
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $nonce = tpl_get_csp_nonce();
        $scriptCdn = "https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://translate.google.com https://translate.googleapis.com https://translate-pa.googleapis.com";
        $styleCdn = "https://cdn.tailwindcss.com https://fonts.googleapis.com https://www.gstatic.com https://cdnjs.cloudflare.com";
        $fontCdn = "https://fonts.gstatic.com https://cdnjs.cloudflare.com";
        $connectCdn = "https://cdn.jsdelivr.net https://translate.google.com https://translate.googleapis.com https://translate-pa.googleapis.com";

        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' {$scriptCdn}; ";
        $csp .= "style-src 'self' 'unsafe-inline' {$styleCdn}; ";
        $csp .= "font-src 'self' {$fontCdn}; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self' {$connectCdn}; ";
        $csp .= "frame-ancestors 'none';";
        header("Content-Security-Policy: $csp");
    }
}

