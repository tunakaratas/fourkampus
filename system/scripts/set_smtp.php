<?php
// CLI: Gmail SMTP ayarlarını tüm topluluk veritabanlarına uygular
// Kullanım: php system/scripts/set_smtp.php

require_once __DIR__ . '/security_helper.php';

// CLI kontrolü
requireCLI();

error_reporting(E_ALL);
ini_set('display_errors', isProduction() ? 0 : 1);

$root = realpath(__DIR__ . '/..' . '/..');
$communitiesDir = $root . '/communities';

// Credentials environment variable veya config dosyasından al
$gmailUsername = getCredential('SMTP_USERNAME');
$gmailAppPassword = getCredential('SMTP_PASSWORD');

if (empty($gmailUsername) || empty($gmailAppPassword)) {
    fwrite(STDERR, "Error: SMTP_USERNAME and SMTP_PASSWORD must be set\n");
    fwrite(STDERR, "Set them in .env file or config/credentials.php\n");
    fwrite(STDERR, "Usage: SMTP_USERNAME=user@example.com SMTP_PASSWORD=password php set_smtp.php\n");
    exit(1);
}

function setSmtp($dbPath, $clubId, $username, $password) {
    if (!file_exists($dbPath)) {
        echo "skip (db yok): $dbPath\n";
        return;
    }
    $db = new SQLite3($dbPath);
    $db->exec('PRAGMA journal_mode = WAL');
    $pairs = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'smtp_username' => $username,
        'smtp_password' => $password,
        'smtp_from_email' => $username,
        'smtp_from_name' => 'Topluluk',
    ];
    foreach ($pairs as $key => $value) {
        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, :k, :v)');
        $stmt->bindValue(':club_id', $clubId, SQLITE3_INTEGER);
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $stmt->bindValue(':v', $value, SQLITE3_TEXT);
        $stmt->execute();
    }
    echo "ok: $dbPath\n";
}

if (!is_dir($communitiesDir)) {
    fwrite(STDERR, "Communities klasörü bulunamadı: $communitiesDir\n");
    exit(1);
}

$dirs = scandir($communitiesDir);
foreach ($dirs as $d) {
    if ($d === '.' || $d === '..' || $d === 'public') continue;
    $dbPath = $communitiesDir . '/' . $d . '/unipanel.sqlite';
    setSmtp($dbPath, 1, $gmailUsername, $gmailAppPassword);
}

echo "Tamamlandi.\n";


