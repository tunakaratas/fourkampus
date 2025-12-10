<?php
/**
 * HOSTING BACKUP SİSTEMİ
 * Otomatik yedekleme ve temizlik sistemi
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Backup klasörü oluştur
$backup_dir = 'backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Backup fonksiyonu
function createBackup($name = 'manual') {
    global $backup_dir;
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_name = "backup_{$name}_{$timestamp}";
    $backup_path = $backup_dir . '/' . $backup_name;
    
    // Backup klasörü oluştur
    if (!file_exists($backup_path)) {
        mkdir($backup_path, 0755, true);
    }
    
    // Kopyalanacak klasörler
    $folders_to_backup = [
        'communities',
        'templates',
        'superadmin',
        'system',
        'assets'
    ];
    
    $backup_files = [
        'index.php',
        'login.php',
        '.htaccess'
    ];
    
    $success = true;
    $backup_log = [];
    
    // Klasörleri kopyala
    foreach ($folders_to_backup as $folder) {
        if (file_exists($folder)) {
            $dest = $backup_path . '/' . $folder;
            if (recursiveCopy($folder, $dest)) {
                $backup_log[] = "✅ Klasör kopyalandı: $folder";
            } else {
                $backup_log[] = "❌ Klasör kopyalanamadı: $folder";
                $success = false;
            }
        }
    }
    
    // Dosyaları kopyala
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $dest = $backup_path . '/' . $file;
            if (copy($file, $dest)) {
                $backup_log[] = "✅ Dosya kopyalandı: $file";
            } else {
                $backup_log[] = "❌ Dosya kopyalanamadı: $file";
                $success = false;
            }
        }
    }
    
    // Backup bilgilerini kaydet
    $backup_info = [
        'name' => $backup_name,
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $name,
        'success' => $success,
        'log' => $backup_log
    ];
    
    file_put_contents($backup_path . '/backup_info.json', json_encode($backup_info, JSON_PRETTY_PRINT));
    
    return $backup_info;
}

// Recursive copy fonksiyonu
function recursiveCopy($src, $dst) {
    if (!file_exists($src)) return false;
    
    if (is_dir($src)) {
        if (!file_exists($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (!recursiveCopy($src . '/' . $file, $dst . '/' . $file)) {
                    return false;
                }
            }
        }
    } else {
        return copy($src, $dst);
    }
    
    return true;
}

// Eski backup'ları temizle (7 günden eski)
function cleanOldBackups() {
    global $backup_dir;
    
    if (!file_exists($backup_dir)) return;
    
    $files = scandir($backup_dir);
    $deleted_count = 0;
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $backup_dir . '/' . $file;
            $file_time = filemtime($file_path);
            
            // 7 günden eski dosyaları sil
            if (time() - $file_time > 7 * 24 * 3600) {
                if (is_dir($file_path)) {
                    recursiveDelete($file_path);
                } else {
                    unlink($file_path);
                }
                $deleted_count++;
            }
        }
    }
    
    return $deleted_count;
}

// Recursive delete fonksiyonu
function recursiveDelete($dir) {
    if (!file_exists($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                recursiveDelete($file_path);
            } else {
                unlink($file_path);
            }
        }
    }
    rmdir($dir);
}

// Backup listesi
function getBackupList() {
    global $backup_dir;
    
    if (!file_exists($backup_dir)) return [];
    
    $backups = [];
    $files = scandir($backup_dir);
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && is_dir($backup_dir . '/' . $file)) {
            $info_file = $backup_dir . '/' . $file . '/backup_info.json';
            if (file_exists($info_file)) {
                $info = json_decode(file_get_contents($info_file), true);
                $backups[] = $info;
            }
        }
    }
    
    // Tarihe göre sırala (yeni önce)
    usort($backups, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return $backups;
}

// Restore fonksiyonu
function restoreBackup($backup_name) {
    global $backup_dir;
    
    $backup_path = $backup_dir . '/' . $backup_name;
    
    if (!file_exists($backup_path)) {
        return ['success' => false, 'message' => 'Backup bulunamadı'];
    }
    
    $success = true;
    $restore_log = [];
    
    // Communities klasörünü restore et
    if (file_exists($backup_path . '/communities')) {
        if (recursiveCopy($backup_path . '/communities', 'communities')) {
            $restore_log[] = "✅ Communities restore edildi";
        } else {
            $restore_log[] = "❌ Communities restore edilemedi";
            $success = false;
        }
    }
    
    // Templates klasörünü restore et
    if (file_exists($backup_path . '/templates')) {
        if (recursiveCopy($backup_path . '/templates', 'templates')) {
            $restore_log[] = "✅ Templates restore edildi";
        } else {
            $restore_log[] = "❌ Templates restore edilemedi";
            $success = false;
        }
    }
    
    return [
        'success' => $success,
        'log' => $restore_log
    ];
}

// İşlemleri yap
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_backup'])) {
        $backup_info = createBackup('manual');
        $message = $backup_info['success'] ? 'Backup başarıyla oluşturuldu!' : 'Backup oluşturulamadı!';
    }
    
    if (isset($_POST['clean_old'])) {
        $deleted_count = cleanOldBackups();
        $message = "$deleted_count eski backup silindi.";
    }
    
    if (isset($_POST['restore_backup'])) {
        $backup_name = $_POST['backup_name'] ?? '';
        if ($backup_name) {
            $result = restoreBackup($backup_name);
            $message = $result['success'] ? 'Backup restore edildi!' : 'Backup restore edilemedi!';
        }
    }
}

$backups = getBackupList();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosting Backup Sistemi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .backup-item { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #3498db; }
        .backup-item.success { border-left-color: #27ae60; }
        .backup-item.error { border-left-color: #e74c3c; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Hosting Backup Sistemi</h1>
            <p>Otomatik yedekleme ve restore sistemi</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div style="margin: 20px 0;">
            <form method="post" style="display: inline;">
                <button type="submit" name="create_backup" class="btn btn-success">📦 Yeni Backup Oluştur</button>
            </form>
            
            <form method="post" style="display: inline;">
                <button type="submit" name="clean_old" class="btn btn-danger" onclick="return confirm('7 günden eski backup\'lar silinecek. Emin misiniz?')">🗑️ Eski Backup'ları Temizle</button>
            </form>
        </div>
        
        <h2>📋 Mevcut Backup'lar</h2>
        
        <?php if (empty($backups)): ?>
            <p>Henüz backup bulunmuyor.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Backup Adı</th>
                        <th>Tarih</th>
                        <th>Tür</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?= htmlspecialchars($backup['name']) ?></td>
                            <td><?= htmlspecialchars($backup['timestamp']) ?></td>
                            <td><?= htmlspecialchars($backup['type']) ?></td>
                            <td>
                                <?php if ($backup['success']): ?>
                                    <span style="color: green;">✅ Başarılı</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Hatalı</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="backup_name" value="<?= htmlspecialchars($backup['name']) ?>">
                                    <button type="submit" name="restore_backup" class="btn" onclick="return confirm('Bu backup restore edilecek. Emin misiniz?')">🔄 Restore</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 4px;">
            <h3>ℹ️ Bilgiler</h3>
            <ul>
                <li><strong>Backup Konumu:</strong> <?= realpath($backup_dir) ?></li>
                <li><strong>Toplam Backup:</strong> <?= count($backups) ?></li>
                <li><strong>Otomatik Temizlik:</strong> 7 günden eski backup'lar otomatik silinir</li>
                <li><strong>Backup İçeriği:</strong> Communities, Templates, SuperAdmin, System, Assets</li>
            </ul>
        </div>
    </div>
</body>
</html>
