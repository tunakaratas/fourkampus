<?php
/**
 * SQLITE İZİN DÜZELTME SCRIPT'İ
 * Hosting'de SQLite dosyalarının yazma izinlerini düzeltir
 */

echo "<h1>🔧 SQLite İzin Düzeltme</h1>";
echo "<p>SQLite dosyalarının yazma izinlerini düzeltiyor...</p>";

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SQLite dosyalarını bul ve izinlerini düzelt
function fixSQLitePermissions() {
    $fixed_count = 0;
    $error_count = 0;
    
    echo "<h2>1. Communities Klasöründeki SQLite Dosyaları</h2>";
    
    if (is_dir('communities')) {
        $communities = scandir('communities');
        foreach ($communities as $community) {
            if ($community !== '.' && $community !== '..' && is_dir("communities/$community")) {
                $sqlite_file = "communities/$community/unipanel.sqlite";
                
                if (file_exists($sqlite_file)) {
                    echo "<h3>Topluluk: $community</h3>";
                    
                    // Dosya izinlerini düzelt
                    if (chmod($sqlite_file, 0666)) {
                        echo "✅ $sqlite_file - İzin düzeltildi (666)<br>";
                        $fixed_count++;
                    } else {
                        echo "❌ $sqlite_file - İzin düzeltilemedi<br>";
                        $error_count++;
                    }
                    
                    // Klasör izinlerini de düzelt
                    if (chmod("communities/$community", 0755)) {
                        echo "✅ communities/$community - Klasör izni düzeltildi (755)<br>";
                    } else {
                        echo "❌ communities/$community - Klasör izni düzeltilemedi<br>";
                    }
                    
                    // SQLite dosyasını test et
                    testSQLiteFile($sqlite_file);
                }
            }
        }
    }
    
    echo "<h2>2. Ana SQLite Dosyası</h2>";
    
    $main_sqlite = 'unipanel.sqlite';
    if (file_exists($main_sqlite)) {
        if (chmod($main_sqlite, 0666)) {
            echo "✅ $main_sqlite - İzin düzeltildi (666)<br>";
            $fixed_count++;
        } else {
            echo "❌ $main_sqlite - İzin düzeltilemedi<br>";
            $error_count++;
        }
        
        testSQLiteFile($main_sqlite);
    }
    
    echo "<h2>3. Özet</h2>";
    echo "<p><strong>Düzeltilen dosya sayısı:</strong> $fixed_count</p>";
    echo "<p><strong>Hata sayısı:</strong> $error_count</p>";
    
    return $fixed_count > 0;
}

// SQLite dosyasını test et
function testSQLiteFile($file_path) {
    echo "<h4>Test: $file_path</h4>";
    
    try {
        $db = new SQLite3($file_path);
        
        // Test tablosu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS permission_test (id INTEGER PRIMARY KEY, test TEXT)");
        
        // Test verisi ekle
        $stmt = $db->prepare("INSERT INTO permission_test (test) VALUES (?)");
        $stmt->bindValue(1, 'test_' . time());
        $result = $stmt->execute();
        
        if ($result) {
            echo "✅ Yazma testi başarılı<br>";
            
            // Test verisini sil
            $db->exec("DELETE FROM permission_test");
            $db->exec("DROP TABLE permission_test");
            
        } else {
            echo "❌ Yazma testi başarısız<br>";
        }
        
        $db->close();
        
    } catch (Exception $e) {
        echo "❌ SQLite hatası: " . $e->getMessage() . "<br>";
    }
}

// Klasör izinlerini düzelt
function fixDirectoryPermissions() {
    echo "<h2>4. Klasör İzinlerini Düzeltme</h2>";
    
    $directories = [
        'communities',
        'system',
        'system/logs',
        'assets',
        'assets/images',
        'templates',
        'superadmin'
    ];
    
    foreach ($directories as $dir) {
        if (file_exists($dir)) {
            if (chmod($dir, 0755)) {
                echo "✅ $dir - Klasör izni düzeltildi (755)<br>";
            } else {
                echo "❌ $dir - Klasör izni düzeltilemedi<br>";
            }
        }
    }
}

// Log dosyalarını oluştur ve izinlerini düzelt
function fixLogPermissions() {
    echo "<h2>5. Log Dosyaları İzinleri</h2>";
    
    $log_files = [
        'system/logs/superadmin_login.log',
        'system/logs/key_security.log',
        'system/logs/system.log'
    ];
    
    foreach ($log_files as $log_file) {
        // Log dosyasını oluştur
        if (!file_exists($log_file)) {
            file_put_contents($log_file, '');
        }
        
        if (chmod($log_file, 0666)) {
            echo "✅ $log_file - Log dosyası izni düzeltildi (666)<br>";
        } else {
            echo "❌ $log_file - Log dosyası izni düzeltilemedi<br>";
        }
    }
}

// .htaccess dosyasını güncelle
function updateHtaccess() {
    echo "<h2>6. .htaccess Güncelleme</h2>";
    
    $htaccess_content = "# UniPanel .htaccess - SQLite İzin Düzeltme
RewriteEngine On

# PHP hata raporlamayı aç
php_flag display_errors On
php_value error_reporting E_ALL

# SQLite dosyaları için özel izinler
<Files \"*.sqlite\">
    Order allow,deny
    Allow from all
</Files>

# Log dosyaları için özel izinler
<Files \"*.log\">
    Order allow,deny
    Allow from all
</Files>

# Dosya yükleme limitleri
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
php_value max_input_time 300

# Session ayarları
php_value session.cookie_httponly 1
php_value session.cookie_secure 0
php_value session.use_strict_mode 1

# Güvenlik başlıkları
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
";

    if (file_put_contents('.htaccess', $htaccess_content)) {
        echo "✅ .htaccess dosyası güncellendi<br>";
        chmod('.htaccess', 0644);
    } else {
        echo "❌ .htaccess dosyası güncellenemedi<br>";
    }
}

// Ana işlemleri çalıştır
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";

$success = fixSQLitePermissions();
fixDirectoryPermissions();
fixLogPermissions();
updateHtaccess();

echo "</div>";

if ($success) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>✅ İşlem Tamamlandı!</h3>";
    echo "<p>SQLite dosyalarının izinleri düzeltildi. Şimdi sisteminizi test edebilirsiniz.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>❌ Hata!</h3>";
    echo "<p>SQLite dosyalarının izinleri düzeltilemedi. Hosting sağlayıcınızla iletişime geçin.</p>";
    echo "</div>";
}

echo "<h2>7. Test Etme</h2>";
echo "<p>Aşağıdaki linkleri test edin:</p>";
echo "<ul>";
echo "<li><a href='superadmin/login.php' target='_blank'>SuperAdmin Giriş</a></li>";
echo "<li><a href='hosting_test.php' target='_blank'>Hosting Test</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><em>Bu script hosting ortamında çalıştırıldı: " . date('Y-m-d H:i:s') . "</em></p>";
?>
