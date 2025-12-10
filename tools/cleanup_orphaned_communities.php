<?php
/**
 * Cleanup Orphaned Communities
 * Bu script, veritabanında kayıtlı olan ama fiziksel klasörü olmayan toplulukları temizler
 * ve sadece gerçek topluluk klasörlerini gösterir
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PROJECT_ROOT', dirname(__DIR__));
define('COMMUNITIES_DIR', PROJECT_ROOT . '/communities/');
define('CACHE_FILE', PROJECT_ROOT . '/system/cache/communities_list.cache');

echo "=== Orphaned Communities Cleanup ===\n\n";

// 1. Tüm cache dosyalarını sil
$cache_dir = PROJECT_ROOT . '/system/cache/';
$cache_patterns = [
    'communities_list.cache',
    'all_communities_list_*.cache'
];

$deleted_count = 0;
foreach ($cache_patterns as $pattern) {
    $files = glob($cache_dir . $pattern);
    foreach ($files as $file) {
        if (unlink($file)) {
            $deleted_count++;
            echo "✓ Cache dosyası silindi: " . basename($file) . "\n";
        }
    }
}

if ($deleted_count === 0) {
    echo "ℹ Cache dosyası bulunamadı\n";
} else {
    echo "✓ Toplam $deleted_count cache dosyası silindi\n";
}

// 2. Communities klasöründeki gerçek toplulukları listele
$real_communities = [];
if (is_dir(COMMUNITIES_DIR)) {
    $dirs = scandir(COMMUNITIES_DIR);
    $excluded_dirs = ['.', '..', 'assets', 'templates', 'system', 'docs', 'public'];
    
    foreach ($dirs as $dir) {
        if (in_array($dir, $excluded_dirs)) {
            continue;
        }
        
        $full_path = COMMUNITIES_DIR . $dir;
        if (is_dir($full_path)) {
            // unipanel.sqlite dosyası var mı kontrol et
            $db_path = $full_path . '/unipanel.sqlite';
            if (file_exists($db_path)) {
                $real_communities[] = $dir;
                echo "✓ Gerçek topluluk bulundu: $dir\n";
            } else {
                echo "⚠ Klasör var ama veritabanı yok: $dir\n";
            }
        }
    }
}

echo "\n=== Özet ===\n";
echo "Toplam gerçek topluluk sayısı: " . count($real_communities) . "\n";
echo "Topluluklar: " . implode(', ', $real_communities) . "\n\n";

// 3. Public veritabanını kontrol et (eğer varsa)
$public_db_path = PROJECT_ROOT . '/public/unipanel.sqlite';
if (file_exists($public_db_path)) {
    try {
        $db = new SQLite3($public_db_path);
        
        // Communities tablosu var mı kontrol et
        $table_exists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='communities'");
        
        if ($table_exists) {
            echo "=== Veritabanı Temizliği ===\n";
            
            // Tüm kayıtları al
            $result = $db->query("SELECT folder_name, name FROM communities");
            $db_communities = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $db_communities[] = $row['folder_name'];
            }
            
            echo "Veritabanındaki topluluk sayısı: " . count($db_communities) . "\n";
            
            // Fiziksel klasörü olmayan kayıtları bul
            $orphaned = array_diff($db_communities, $real_communities);
            
            if (!empty($orphaned)) {
                echo "\n⚠ Fiziksel klasörü olmayan kayıtlar bulundu:\n";
                foreach ($orphaned as $folder) {
                    echo "  - $folder\n";
                }
                
                echo "\nBu kayıtları silmek ister misiniz? (y/n): ";
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                $answer = trim(strtolower($line));
                fclose($handle);
                
                if ($answer === 'y' || $answer === 'yes') {
                    foreach ($orphaned as $folder) {
                        $stmt = $db->prepare("DELETE FROM communities WHERE folder_name = ?");
                        $stmt->bindValue(1, $folder, SQLITE3_TEXT);
                        $stmt->execute();
                        echo "✓ Silindi: $folder\n";
                    }
                    echo "\n✓ Temizlik tamamlandı!\n";
                } else {
                    echo "\nℹ Temizlik iptal edildi.\n";
                }
            } else {
                echo "✓ Veritabanı temiz, tüm kayıtlar geçerli.\n";
            }
        } else {
            echo "ℹ Veritabanında 'communities' tablosu yok.\n";
        }
        
        $db->close();
    } catch (Exception $e) {
        echo "⚠ Veritabanı hatası: " . $e->getMessage() . "\n";
    }
} else {
    echo "ℹ Public veritabanı bulunamadı: $public_db_path\n";
}

echo "\n=== Tamamlandı ===\n";
echo "SuperAdmin panelini yenileyin, sadece gerçek topluluklar görünecektir.\n";

