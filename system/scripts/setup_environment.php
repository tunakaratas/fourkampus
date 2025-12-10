<?php
/**
 * Environment Variables Setup Script
 * Bu script environment variable'ları ayarlar
 * 
 * Kullanım: php setup_environment.php
 */

require_once __DIR__ . '/security_helper.php';

// CLI kontrolü
requireCLI();

echo "=== UniPanel Environment Variables Setup ===\n\n";

// Mevcut .env dosyasını oku (varsa)
$env_file = __DIR__ . '/../../.env';
$env_example = __DIR__ . '/../../.env.example';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Comment satırlarını atla
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value);
    }
}

// Sorular ve varsayılan değerler
$questions = [
    'SMTP_USERNAME' => [
        'question' => 'SMTP Email (Gmail için):',
        'default' => $env_vars['SMTP_USERNAME'] ?? '',
        'required' => false
    ],
    'SMTP_PASSWORD' => [
        'question' => 'SMTP Password (Gmail App Password):',
        'default' => $env_vars['SMTP_PASSWORD'] ?? '',
        'required' => false,
        'hidden' => true
    ],
    'NETGSM_USERNAME' => [
        'question' => 'NetGSM Username (Abone No):',
        'default' => $env_vars['NETGSM_USERNAME'] ?? '',
        'required' => false
    ],
    'NETGSM_PASSWORD' => [
        'question' => 'NetGSM Password:',
        'default' => $env_vars['NETGSM_PASSWORD'] ?? '',
        'required' => false,
        'hidden' => true
    ],
    'NETGSM_MSGHEADER' => [
        'question' => 'NetGSM Message Header:',
        'default' => $env_vars['NETGSM_MSGHEADER'] ?? '',
        'required' => false
    ],
    'SYSTEM_SCRIPT_TOKEN' => [
        'question' => 'System Script Token (web erişimi için):',
        'default' => $env_vars['SYSTEM_SCRIPT_TOKEN'] ?? bin2hex(random_bytes(32)),
        'required' => false
    ],
    'APP_ENV' => [
        'question' => 'Environment (production/development):',
        'default' => $env_vars['APP_ENV'] ?? 'development',
        'required' => false,
        'options' => ['production', 'development']
    ],
    'BACKUP_ADMIN_EMAIL' => [
        'question' => 'Backup Admin Email (opsiyonel):',
        'default' => $env_vars['BACKUP_ADMIN_EMAIL'] ?? '',
        'required' => false
    ]
];

$new_env_vars = [];

echo "Mevcut değerleri korumak için Enter'a basın.\n\n";

foreach ($questions as $key => $config) {
    $question = $config['question'];
    $default = $config['default'];
    $required = $config['required'] ?? false;
    $hidden = $config['hidden'] ?? false;
    $options = $config['options'] ?? null;
    
    while (true) {
        if ($hidden && !empty($default)) {
            echo "$question [*****]: ";
        } else {
            echo "$question" . (!empty($default) ? " [$default]" : "") . ": ";
        }
        
        if ($hidden) {
            // Windows'ta stty yok, alternatif yöntem
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $value = trim(fgets(STDIN));
            } else {
                system('stty -echo');
                $value = trim(fgets(STDIN));
                system('stty echo');
                echo "\n";
            }
        } else {
            $value = trim(fgets(STDIN));
        }
        
        if (empty($value) && !empty($default)) {
            $value = $default;
        }
        
        if (empty($value) && $required) {
            echo "Bu alan zorunludur!\n";
            continue;
        }
        
        if ($options && !in_array($value, $options)) {
            echo "Geçersiz değer! Şunlardan biri olmalı: " . implode(', ', $options) . "\n";
            continue;
        }
        
        $new_env_vars[$key] = $value;
        break;
    }
}

// .env dosyasını oluştur
$env_content = "# UniPanel Environment Variables\n";
$env_content .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
$env_content .= "# DO NOT COMMIT THIS FILE TO VERSION CONTROL\n\n";

foreach ($new_env_vars as $key => $value) {
    if (!empty($value)) {
        $env_content .= "$key=$value\n";
    }
}

// .env.example dosyası oluştur
$env_example_content = "# UniPanel Environment Variables Example\n";
$env_example_content .= "# Copy this file to .env and fill in your values\n";
$env_example_content .= "# IMPORTANT: Never commit .env file to version control!\n\n";

foreach ($questions as $key => $config) {
    $example_value = $config['default'] ?? '';
    if (empty($example_value)) {
        $example_value = 'your_' . strtolower($key);
    }
    $env_example_content .= "$key=$example_value\n";
}

// Dosyaları kaydet
file_put_contents($env_file, $env_content);
file_put_contents($env_example, $env_example_content);

echo "\n✓ .env dosyası oluşturuldu: $env_file\n";
echo "✓ .env.example dosyası oluşturuldu: $env_example_file\n\n";

// Shell script oluştur (Linux/Mac için)
$shell_script = <<<'SHELL'
#!/bin/bash
# UniPanel Environment Variables Loader
# Bu script'i .bashrc veya .zshrc'ye ekleyin:
# source /path/to/unipanel/system/scripts/load_env.sh

ENV_FILE="$(dirname "$0")/../../.env"

if [ -f "$ENV_FILE" ]; then
    export $(cat "$ENV_FILE" | grep -v '^#' | xargs)
    echo "✓ UniPanel environment variables loaded"
else
    echo "⚠ .env file not found: $ENV_FILE"
fi
SHELL;

$shell_script_path = __DIR__ . '/load_env.sh';
file_put_contents($shell_script_path, $shell_script);
chmod($shell_script_path, 0755);

echo "✓ Shell script oluşturuldu: $shell_script_path\n";
echo "\n=== Kurulum Tamamlandı ===\n\n";

echo "Kullanım:\n";
echo "1. Linux/Mac için:\n";
echo "   source system/scripts/load_env.sh\n";
echo "   veya .bashrc/.zshrc'ye ekleyin\n\n";

echo "2. Windows için:\n";
echo "   .env dosyasındaki değerleri manuel olarak environment variable olarak ayarlayın\n\n";

echo "3. PHP script'lerinde kullanım:\n";
echo "   getenv('SMTP_USERNAME') ile değerlere erişebilirsiniz\n\n";

echo "4. Cron job'larda:\n";
echo "   source /path/to/load_env.sh && php script.php\n\n";

