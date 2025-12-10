<?php
/**
 * Local Development Permission Fix Script
 * Bu script XAMPP local development ortamında SQLite veritabanı dosyalarının
 * yazma izinlerini düzeltir.
 */

echo "XAMPP Local Development Permission Fix\n";
echo "=====================================\n\n";

$base_path = __DIR__;
$communities_path = $base_path . '/communities';

// Ana SQLite dosyasını düzelt
$main_db = $base_path . '/unipanel.sqlite';
if (file_exists($main_db)) {
    chmod($main_db, 0666);
    echo "✓ Ana veritabanı izinleri düzeltildi: unipanel.sqlite\n";
} else {
    echo "⚠ Ana veritabanı bulunamadı: unipanel.sqlite\n";
}

// Communities klasöründeki tüm SQLite dosyalarını düzelt
if (is_dir($communities_path)) {
    $communities = scandir($communities_path);
    
    foreach ($communities as $community) {
        if ($community === '.' || $community === '..' || $community === 'assets') {
            continue;
        }
        
        $community_path = $communities_path . '/' . $community;
        $db_file = $community_path . '/unipanel.sqlite';
        
        if (file_exists($db_file)) {
            chmod($db_file, 0666);
            chmod($community_path, 0777);
            echo "✓ Topluluk veritabanı izinleri düzeltildi: {$community}/unipanel.sqlite\n";
        }
        
        // PHP dosyalarının izinlerini düzelt
        $php_files = ['index.php', 'login.php', 'loading.php', 'notification_api.php'];
        foreach ($php_files as $php_file) {
            $php_path = $community_path . '/' . $php_file;
            if (file_exists($php_path)) {
                chmod($php_path, 0666);
            }
        }
    }
} else {
    echo "⚠ Communities klasörü bulunamadı\n";
}

echo "\n✅ Tüm izinler düzeltildi!\n";
echo "Artık SQLite veritabanı ve template sync yazma hataları almamalısınız.\n";
echo "\nDüzeltilen dosyalar:\n";
echo "- SQLite veritabanı dosyaları (unipanel.sqlite)\n";
echo "- PHP template dosyaları (index.php, login.php, loading.php, notification_api.php)\n";
echo "- Communities klasörleri\n";
?>
