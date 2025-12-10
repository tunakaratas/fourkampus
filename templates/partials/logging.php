<?php
if (!function_exists('mask_sensitive_log_data')) {
    function mask_sensitive_log_data($message): string
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($message)) {
            $message = (string) $message;
        }

        $patterns = [
            '/sk-[A-Za-z0-9]{16,}/' => 'sk-********',
            '/(api[_-]?key\s*[:=]\s*)([A-Za-z0-9\-_]+)/i' => '$1********',
            '/(smtp[_-]?password[^:]*:\s*)([^\s]+)/i' => '$1********',
            '/(smtp[_-]?username[^:]*:\s*)([^\s]+)/i' => '$1********',
            '/(smtp[_-]?host[^:]*:\s*)([^\s]+)/i' => '$1********',
            '/(smtp[_-]?port[^:]*:\s*)([^\s]+)/i' => '$1********',
            '/(auth[_-]?token\s*[:=]\s*)([^\s\'"]+)/i' => '$1********',
            '/(bearer\s+)[A-Za-z0-9\-_\.]+/i' => '$1********',
            '/(password\s*[:=]\s*)([^\s\'"]+)/i' => '$1********',
            '/(secret\s*[:=]\s*)([^\s\'"]+)/i' => '$1********',
            '/(cookie\s*:\s*)([^;]+)/i' => '$1********',
            '/(set-cookie:\s*)([^;]+)/i' => '$1********',
            '/(authorization:\s*[^\r\n]+)/i' => '$1 ********',
            '/(twilio\s+account\s+sid\s*:\s*)([A-Za-z0-9]+)/i' => '$1********',
            '/(twilio\s+auth\s+token\s*:\s*)([A-Za-z0-9]+)/i' => '$1********',
            '/(callback_token\s*=\s*)([^\s&]+)/i' => '$1********',
            '/(sync_token\s*=\s*)([^\s&]+)/i' => '$1********',
            '/(\busername\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
            '/(\bto\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
            '/(\bemail\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
            '/(\brecipient\w*\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
            '/(\bphone(?:_number)?\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
            '/(\bmsisdn\b[^:]*:\s*)([^\s,]+)/i' => '$1********',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $message;
    }
}

if (!function_exists('tpl_log_debug_enabled')) {
    function tpl_log_debug_enabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }

        $flag = getenv('APP_DEBUG_LOGS') ?: ($_SERVER['APP_DEBUG_LOGS'] ?? null);
        if ($flag !== null) {
            $normalized = strtolower(trim((string)$flag));
            $enabled = in_array($normalized, ['1', 'true', 'on', 'yes'], true);
        } else {
            $enabled = defined('APP_ENV') && APP_ENV === 'development';
        }

        return $enabled;
    }
}

if (!function_exists('tpl_rotate_error_log_if_needed')) {
    function tpl_rotate_error_log_if_needed(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $logFile = ini_get('error_log');
        if (!$logFile || !file_exists($logFile)) {
            return;
        }

        $maxBytes = (int)(getenv('TPL_LOG_MAX_BYTES') ?: (5 * 1024 * 1024));
        if (@filesize($logFile) < $maxBytes) {
            return;
        }

        $archive = $logFile . '.' . date('Ymd_His');
        @rename($logFile, $archive);
        @touch($logFile);
        @chmod($logFile, 0600);

        $dir = dirname($logFile);
        $base = basename($logFile);
        $archives = glob($dir . '/' . $base . '.*', GLOB_NOSORT) ?: [];
        rsort($archives);
        $keep = 5;
        foreach (array_slice($archives, $keep) as $old) {
            @unlink($old);
        }
    }
}

if (!function_exists('tpl_error_log')) {
    function tpl_error_log($message, string $level = 'error'): void
    {
        $level = strtolower($level);
        if ($level === 'debug' && !tpl_log_debug_enabled()) {
            return;
        }

        tpl_rotate_error_log_if_needed();
        $prefix = '[' . strtoupper($level) . '] ';
        $final_msg = mask_sensitive_log_data($message);
        error_log($prefix . $final_msg);
        
        // Custom debug log
        $custom_log = __DIR__ . '/../../logs/custom_debug.log';
        $time = date('Y-m-d H:i:s');
        @file_put_contents($custom_log, "[$time] $prefix $final_msg\n", FILE_APPEND);
    }
}

