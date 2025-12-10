<?php
/**
 * Environment Variables Loader
 * .env dosyasını yükler ve environment değişkenlerini ayarlar
 * 
 * Kullanım: require_once __DIR__ . '/load_env.php';
 */

if (!function_exists('load_env_file')) {
    function load_env_file(string $envPath): void
    {
        if (!file_exists($envPath)) {
            return; // .env dosyası yoksa sessizce devam et
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Yorum satırlarını atla
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // KEY=VALUE formatını parse et
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Tırnak işaretlerini kaldır
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            // Environment değişkeni zaten tanımlı değilse ayarla
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Proje kök dizinindeki .env dosyasını yükle
$envPath = dirname(__DIR__) . '/.env';
load_env_file($envPath);

