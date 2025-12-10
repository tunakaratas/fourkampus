<?php
// CLI: Topluluk logosunu settings'e kaydet
require_once __DIR__ . '/security_helper.php';

// CLI kontrolü
requireCLI();

error_reporting(E_ALL);
ini_set('display_errors', isProduction() ? 0 : 1);

$root = realpath(__DIR__ . '/..' . '/..');
$communitiesDir = $root . '/communities';

// Herkes İçin Sağlık Topluluğu için logo path'i
$logo_path = 'assets/images/partner-logos/partner_1761398712_68fccfb83e367.png';

function setClubLogo($dbPath, $clubId, $logoPath) {
    // Path sanitization
    $sanitized_db_path = sanitizePath($dbPath);
    if (!$sanitized_db_path || !file_exists($sanitized_db_path)) {
        echo "skip (db yok veya geçersiz): $dbPath\n";
        return;
    }
    
    // Logo path sanitization
    $db_dir = dirname($sanitized_db_path);
    $fullLogoPath = realpath($db_dir . '/' . $logoPath);
    
    // Path traversal kontrolü
    if (!$fullLogoPath || strpos($fullLogoPath, $db_dir) !== 0) {
        echo "skip (logo dosyası geçersiz veya güvenlik riski): $logoPath\n";
        return;
    }
    
    if (!file_exists($fullLogoPath)) {
        echo "skip (logo dosyası yok): $fullLogoPath\n";
        return;
    }
    
    $dbPath = $sanitized_db_path;
    
    $db = new SQLite3($dbPath);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Önce mevcut club_logo kaydını sil
    $delete = $db->prepare('DELETE FROM settings WHERE club_id = ? AND setting_key = ?');
    $delete->bindValue(1, $clubId, SQLITE3_INTEGER);
    $delete->bindValue(2, 'club_logo', SQLITE3_TEXT);
    $delete->execute();
    
    // Yeni club_logo kaydını ekle
    $stmt = $db->prepare('INSERT INTO settings (club_id, setting_key, setting_value) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $clubId, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'club_logo', SQLITE3_TEXT);
    $stmt->bindValue(3, $logoPath, SQLITE3_TEXT);
    $stmt->execute();
    
    echo "ok: $dbPath -> Logo: $logoPath\n";
    $db->close();
}

if (!is_dir($communitiesDir)) {
    fwrite(STDERR, "Communities klasörü bulunamadı: $communitiesDir\n");
    exit(1);
}

$dirs = scandir($communitiesDir);
foreach ($dirs as $d) {
    if ($d === '.' || $d === '..' || $d === 'public') continue;
    
    $dbPath = $communitiesDir . '/' . $d . '/unipanel.sqlite';
    
    // Sadece "herkes_icin_saglik_toplulugu" klasörü için logo ayarla
    if ($d === 'herkes_icin_saglik_toplulugu') {
        setClubLogo($dbPath, 1, $logo_path);
    }
}

echo "Tamamlandi.\n";
?>

