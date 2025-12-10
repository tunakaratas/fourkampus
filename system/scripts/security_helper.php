<?php
/**
 * System Scripts Security Helper
 * Güvenlik fonksiyonları
 */

// Environment variables'ı yükle
require_once __DIR__ . '/load_env.php';

/**
 * CLI kontrolü - Script sadece CLI'den çalıştırılabilir
 */
function requireCLI() {
    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        die('This script can only be run from command line');
    }
}

/**
 * Path sanitization - Path traversal koruması
 */
function sanitizePath($path) {
    if (empty($path)) {
        return null;
    }
    
    // Realpath kullanarak canonical path al
    $realpath = realpath($path);
    if (!$realpath) {
        return null;
    }
    
    // Root directory'yi belirle
    $root = realpath(__DIR__ . '/../..');
    
    // Path root içinde mi kontrol et
    if (strpos($realpath, $root) !== 0) {
        return null;
    }
    
    return $realpath;
}

/**
 * Community name sanitization
 */
function sanitizeCommunityName($name) {
    if (empty($name)) {
        return null;
    }
    
    // Sadece alfanumerik, alt çizgi ve tire karakterlerine izin ver
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        return null;
    }
    
    // Path traversal karakterlerini kontrol et
    if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
        return null;
    }
    
    return $name;
}

/**
 * Environment variable'dan credential al
 */
function getCredential($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        // Config dosyasından da okuyabilir
        $config_path = __DIR__ . '/../../config/credentials.php';
        if (file_exists($config_path)) {
            $config = require $config_path;
            // SMTP için
            if (strpos($key, 'SMTP_') === 0) {
                $config_key = strtolower(str_replace('SMTP_', '', $key));
                if (isset($config['smtp'][$config_key])) {
                    return $config['smtp'][$config_key];
                }
            }
            // NetGSM için
            if (strpos($key, 'NETGSM_') === 0) {
                $config_key = strtolower(str_replace('NETGSM_', '', $key));
                if (isset($config['netgsm'][$config_key])) {
                    return $config['netgsm'][$config_key];
                }
            }
        }
        return $default;
    }
    return $value;
}

/**
 * Güvenli error logging
 */
function secureLog($message, $level = 'info') {
    $logFile = __DIR__ . '/../logs/system_scripts.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Production environment kontrolü
 */
function isProduction() {
    $env = getenv('APP_ENV') ?: getenv('ENVIRONMENT');
    return $env === 'production' || $env === 'prod';
}

/**
 * Güvenli error handling
 */
function handleError($message, $exception = null) {
    if (isProduction()) {
        secureLog("Error: {$message}", 'error');
        if ($exception) {
            secureLog("Exception: " . $exception->getMessage(), 'error');
        }
        echo "An error occurred. Check logs for details.\n";
    } else {
        echo "Error: {$message}\n";
        if ($exception) {
            echo "Exception: " . $exception->getMessage() . "\n";
        }
    }
}

