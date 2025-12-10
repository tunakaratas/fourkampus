<?php
/**
 * HOSTING OTOMATIK YEDEKLEME SİSTEMİ
 * Hosting ortamında çalışacak yedekleme sistemi
 */

// Hata raporlamayı kapat (hosting için)
error_reporting(0);
ini_set('display_errors', 0);

// Yedek klasörü
$backup_dir = __DIR__ . '/backups';
$date = date('Y-m-d_H-i-s');

// Yedeklenecek dosyalar
$files_to_backup = [
    'superadmin/index.php',
    'communities/template_index.php',
    'communities/template_login.php',
    'unipanel.sqlite'
];

// Eski yedekleri sil (7 günden eski olanları)
$old_backups = glob($backup_dir . '/*.zip');
foreach ($old_backups as $old_backup) {
    if (file_exists($old_backup) && (time() - filemtime($old_backup)) > (7 * 24 * 60 * 60)) {
        unlink($old_backup);
    }
}

// Yeni yedek oluştur
$backup_file = $backup_dir . '/unipanel_backup_' . $date . '.zip';

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    
    if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
        foreach ($files_to_backup as $file) {
            $full_path = __DIR__ . '/' . $file;
            if (file_exists($full_path)) {
                $zip->addFile($full_path, $file);
            }
        }
        
        // Topluluk veritabanlarını da yedekle
        $communities = glob(__DIR__ . '/communities/*/unipanel.sqlite');
        foreach ($communities as $community_db) {
            $relative_path = str_replace(__DIR__ . '/', '', $community_db);
            $zip->addFile($community_db, $relative_path);
        }
        
        $zip->close();
        
        // Log dosyasına yaz
        $log_entry = date('Y-m-d H:i:s') . " - Yedek oluşturuldu: " . basename($backup_file) . " (" . round(filesize($backup_file) / 1024, 2) . " KB)\n";
        file_put_contents($backup_dir . '/backup.log', $log_entry, FILE_APPEND);
    }
} else {
    // ZipArchive yoksa basit kopyalama
    $backup_file = $backup_dir . '/unipanel_backup_' . $date . '.tar.gz';
    $command = "cd " . __DIR__ . " && tar -czf " . $backup_file . " superadmin/ communities/ unipanel.sqlite";
    exec($command);
}

// E-posta bildirimi (opsiyonel) - Environment variable'dan al
$admin_email = getenv('BACKUP_ADMIN_EMAIL') ?: '';
if (!empty($admin_email) && filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    $subject = "UniPanel Yedekleme - " . date('Y-m-d H:i:s');
    $message = "UniPanel sistemi başarıyla yedeklendi.\n";
    $message .= "Yedek dosyası: " . basename($backup_file) . "\n";
    $message .= "Boyut: " . round(filesize($backup_file) / 1024, 2) . " KB\n";
    $message .= "Tarih: " . date('Y-m-d H:i:s');
    
    mail($admin_email, $subject, $message);
}

echo "Yedekleme tamamlandı: " . date('Y-m-d H:i:s');
?>
