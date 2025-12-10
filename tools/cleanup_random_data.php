<?php
/**
 * Rastgele Veri Temizleme Script'i
 * generate_random_data.php ile oluşturulan tüm verileri temizler
 */

// Güvenlik kontrolü - sadece localhost'tan çalışsın
if (php_sapi_name() !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== 'localhost' && $host !== '127.0.0.1' && strpos($host, 'localhost') === false) {
        die('Bu script sadece localhost\'ta çalışabilir!');
    }
}

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Yol tanımlamaları
define('BASE_PATH', dirname(__DIR__));
define('COMMUNITIES_DIR', BASE_PATH . '/communities/');

// HTML başlığı
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastgele Veri Temizleme</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 10px;
        }
        .warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning strong {
            color: #d97706;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .log-item {
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .log-item.info {
            background: #e0f2fe;
            color: #0369a1;
        }
        .log-item.success {
            background: #d1fae5;
            color: #047857;
        }
        .log-item.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        button {
            background: #ef4444;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #dc2626;
        }
        button.secondary {
            background: #6b7280;
        }
        button.secondary:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Rastgele Veri Temizleme</h1>
        
        <?php
        // Onay kontrolü
        $confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
        $deleteCommunities = isset($_GET['delete_communities']) && $_GET['delete_communities'] === 'yes';
        
        if (!$confirmed) {
            ?>
            <div class="warning">
                <strong>⚠️ UYARI!</strong>
                <p>Bu script, <code>generate_random_data.php</code> ile oluşturulan tüm verileri silecektir:</p>
                <ul>
                    <li>✅ Tüm etkinlikler (events)</li>
                    <li>✅ Tüm ürünler (products)</li>
                    <li>✅ Tüm üyeler (members)</li>
                    <li>✅ Tüm yönetim kurulu üyeleri (board_members)</li>
                    <li>✅ Tüm etkinlik kayıtları (event_rsvp)</li>
                    <?php if ($deleteCommunities): ?>
                    <li>✅ <strong>Rastgele oluşturulan topluluklar (tamamen silinecek!)</strong></li>
                    <?php endif; ?>
                </ul>
                <p><strong>Bu işlem geri alınamaz!</strong></p>
            </div>
            
            <div class="info">
                <p><strong>Seçenekler:</strong></p>
                <form method="GET" style="margin-top: 15px;">
                    <label style="display: block; margin: 10px 0;">
                        <input type="checkbox" name="delete_communities" value="yes" style="margin-right: 8px;">
                        Rastgele oluşturulan toplulukları da sil (tamamen kaldır)
                    </label>
                    <button type="submit" name="confirm" value="yes" style="background: #ef4444;">
                        Evet, Tüm Verileri Sil
                    </button>
                    <button type="button" onclick="window.location.href='generate_random_data.php'" class="secondary">
                        İptal
                    </button>
                </form>
            </div>
            <?php
            exit;
        }
        
        // Veritabanı bağlantı fonksiyonu
        function getDB($dbPath) {
            $retries = 5;
            for ($i = 0; $i < $retries; $i++) {
                try {
                    $db = new SQLite3($dbPath);
                    $db->busyTimeout(5000);
                    $db->exec('PRAGMA journal_mode = WAL');
                    return $db;
                } catch (Exception $e) {
                    if ($i < $retries - 1) {
                        usleep(100000 * ($i + 1)); // 100ms, 200ms, 300ms...
                        continue;
                    }
                    throw $e;
                }
            }
            return false;
        }
        
        // İstatistikler
        $totalCommunities = 0;
        $processedCommunities = 0;
        $deletedEvents = 0;
        $deletedProducts = 0;
        $deletedMembers = 0;
        $deletedBoardMembers = 0;
        $deletedRSVPs = 0;
        $deletedCommunities = 0;
        $errors = [];
        
        echo "<div class='info'>";
        echo "<p><strong>🔄 Temizleme işlemi başlatılıyor...</strong></p>";
        echo "</div>";
        
        echo "<div class='log' style='max-height: 600px; overflow-y: auto; background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        
        // Tüm toplulukları bul
        $communities = glob(COMMUNITIES_DIR . '*', GLOB_ONLYDIR);
        $totalCommunities = count($communities);
        
        echo "<div class='log-item info'>📁 Toplam <strong>$totalCommunities</strong> topluluk bulundu.</div>";
        
        foreach ($communities as $communityPath) {
            $communityName = basename($communityPath);
            $dbPath = $communityPath . '/unipanel.sqlite';
            
            // Veritabanı yoksa atla
            if (!file_exists($dbPath)) {
                continue;
            }
            
            $processedCommunities++;
            
            try {
                // Veritabanı lock kontrolü
                $db = getDB($dbPath);
                if (!$db) {
                    $errors[] = "$communityName: Veritabanı açılamadı";
                    continue;
                }
                
                echo "<div class='log-item info'>🔄 İşleniyor: <strong>$communityName</strong></div>";
                
                // Events sil
                $result = $db->query("SELECT COUNT(*) as count FROM events");
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $eventCount = $row['count'] ?? 0;
                
                if ($eventCount > 0) {
                    $db->exec("DELETE FROM events");
                    $deletedEvents += $eventCount;
                    echo "<div class='log-item success'>  ✓ $eventCount etkinlik silindi</div>";
                }
                
                // Event RSVP sil
                $result = $db->query("SELECT COUNT(*) as count FROM event_rsvp");
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $rsvpCount = $row['count'] ?? 0;
                
                if ($rsvpCount > 0) {
                    $db->exec("DELETE FROM event_rsvp");
                    $deletedRSVPs += $rsvpCount;
                    echo "<div class='log-item success'>  ✓ $rsvpCount etkinlik kaydı silindi</div>";
                }
                
                // Products sil
                $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
                if ($tableCheck && $tableCheck->fetchArray()) {
                    $result = $db->query("SELECT COUNT(*) as count FROM products");
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $productCount = $row['count'] ?? 0;
                    
                    if ($productCount > 0) {
                        $db->exec("DELETE FROM products");
                        $deletedProducts += $productCount;
                        echo "<div class='log-item success'>  ✓ $productCount ürün silindi</div>";
                    }
                }
                
                // Members sil
                $result = $db->query("SELECT COUNT(*) as count FROM members");
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $memberCount = $row['count'] ?? 0;
                
                if ($memberCount > 0) {
                    $db->exec("DELETE FROM members");
                    $deletedMembers += $memberCount;
                    echo "<div class='log-item success'>  ✓ $memberCount üye silindi</div>";
                }
                
                // Board Members sil
                $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='board_members'");
                if ($tableCheck && $tableCheck->fetchArray()) {
                    $result = $db->query("SELECT COUNT(*) as count FROM board_members");
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $boardCount = $row['count'] ?? 0;
                    
                    if ($boardCount > 0) {
                        $db->exec("DELETE FROM board_members");
                        $deletedBoardMembers += $boardCount;
                        echo "<div class='log-item success'>  ✓ $boardCount yönetim kurulu üyesi silindi</div>";
                    }
                }
                
                // WAL checkpoint
                $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                $db->close();
                
                // Rastgele oluşturulan toplulukları sil (eğer seçildiyse)
                if ($deleteCommunities) {
                    // Rastgele oluşturulan toplulukları tespit et (sayısal suffix ile)
                    if (preg_match('/_\d{4}$/', $communityName)) {
                        // Klasörü tamamen sil
                        if (is_dir($communityPath)) {
                            // Recursive delete
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($communityPath, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::CHILD_FIRST
                            );
                            
                            foreach ($files as $fileinfo) {
                                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                                @$todo($fileinfo->getRealPath());
                            }
                            @rmdir($communityPath);
                            
                            $deletedCommunities++;
                            echo "<div class='log-item success'>  ✓ Topluluk klasörü silindi</div>";
                        }
                    }
                }
                
            } catch (Exception $e) {
                $errors[] = "$communityName: " . $e->getMessage();
                echo "<div class='log-item error'>  ✗ Hata: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        echo "</div>";
        
        // Özet
        echo "<div class='stats'>";
        echo "<div class='stat-card'>";
        echo "<h3>" . number_format($deletedEvents) . "</h3>";
        echo "<p>Etkinlik Silindi</p>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h3>" . number_format($deletedProducts) . "</h3>";
        echo "<p>Ürün Silindi</p>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h3>" . number_format($deletedMembers) . "</h3>";
        echo "<p>Üye Silindi</p>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h3>" . number_format($deletedBoardMembers) . "</h3>";
        echo "<p>Yönetim Kurulu Üyesi Silindi</p>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h3>" . number_format($deletedRSVPs) . "</h3>";
        echo "<p>Etkinlik Kaydı Silindi</p>";
        echo "</div>";
        
        if ($deleteCommunities) {
            echo "<div class='stat-card'>";
            echo "<h3>" . number_format($deletedCommunities) . "</h3>";
            echo "<p>Topluluk Silindi</p>";
            echo "</div>";
        }
        echo "</div>";
        
        if (!empty($errors)) {
            echo "<div class='error'>";
            echo "<strong>⚠️ Hatalar:</strong>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<strong>✅ Temizleme işlemi tamamlandı!</strong>";
            echo "<p>Toplam <strong>$processedCommunities</strong> topluluk işlendi.</p>";
            echo "</div>";
        }
        
        echo "<div style='margin-top: 30px;'>";
        echo "<a href='generate_random_data.php' style='display: inline-block; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 8px; margin-right: 10px;'>Yeni Veri Oluştur</a>";
        echo "<a href='cleanup_random_data.php' style='display: inline-block; padding: 12px 24px; background: #6b7280; color: white; text-decoration: none; border-radius: 8px;'>Tekrar Temizle</a>";
        echo "</div>";
        ?>
    </div>
</body>
</html>

