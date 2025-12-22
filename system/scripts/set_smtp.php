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
$smtpUsername = getCredential('SMTP_USERNAME');
$smtpPassword = getCredential('SMTP_PASSWORD');
$smtpHost = getCredential('SMTP_HOST', 'ms8.guzel.net.tr');

if (empty($smtpUsername) || empty($smtpPassword)) {
    fwrite(STDERR, "Error: SMTP_USERNAME and SMTP_PASSWORD must be set\n");
    fwrite(STDERR, "Set them in .env file or config/credentials.php\n");
    fwrite(STDERR, "Usage: SMTP_USERNAME=user@example.com SMTP_PASSWORD=password php set_smtp.php\n");
    exit(1);
}

function setSmtp($dbPath, $clubId, $username, $password, $host) {
    if (!file_exists($dbPath)) {
        echo "skip (db yok): $dbPath\n";
        return;
    }
    try {
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL');
        $pairs = [
            'smtp_host' => $host,
            'smtp_port' => '587',
            'smtp_secure' => 'tls',
            'smtp_username' => $username,
            'smtp_password' => $password,
            'smtp_from_email' => $username,
            'smtp_from_name' => 'Four Kampüs',
        ];
        foreach ($pairs as $key => $value) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, :k, :v)');
            $stmt->bindValue(':club_id', $clubId, SQLITE3_INTEGER);
            $stmt->bindValue(':k', $key, SQLITE3_TEXT);
            $stmt->bindValue(':v', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
        $db->close();
        echo "ok: $dbPath\n";
    } catch (Throwable $e) {
        echo "error (acilmadi): $dbPath - " . $e->getMessage() . "\n";
    }
}

if (!is_dir($communitiesDir)) {
    fwrite(STDERR, "Communities klasörü bulunamadı: $communitiesDir\n");
    exit(1);
}

$dirs = scandir($communitiesDir);
foreach ($dirs as $d) {
    if ($d === '.' || $d === '..' || $d === 'public' || $d === '.DS_Store') continue;
    $fullPath = $communitiesDir . '/' . $d;
    if (!is_dir($fullPath)) continue;
    $dbPath = $fullPath . '/unipanel.sqlite';
    setSmtp($dbPath, 1, $smtpUsername, $smtpPassword, $smtpHost);
}

echo "Tamamlandi.\n";


