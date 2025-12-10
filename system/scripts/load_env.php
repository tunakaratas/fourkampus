<?php
/**
 * Environment Variables Loader
 * Bu dosyayı script'lerin başına require edin
 * 
 * Kullanım: require_once __DIR__ . '/load_env.php';
 */

$env_file = __DIR__ . '/../../.env';

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Comment satırlarını atla
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        // Boş satırları atla
        if (empty($line)) {
            continue;
        }
        
        // KEY=VALUE formatını parse et
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Environment variable'ı ayarla (eğer zaten ayarlanmamışsa)
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

