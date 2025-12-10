<?php
/**
 * ACİL SQLITE İZİN DÜZELTME
 * Hosting'de SQLite readonly hatası için acil çözüm
 */

echo "<h1>🚨 Acil SQLite İzin Düzeltme</h1>";

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SQLite dosyalarını bul ve düzelt
function emergencyFixSQLite() {
    $fixed = 0;
    $errors = 0;
    
    echo "<h2>1. Tüm SQLite Dosyalarını Bulma</h2>";
    
    // Recursive olarak tüm SQLite dosyalarını bul
    $sqlite_files = [];
    findSQLiteFiles('.', $sqlite_files);
    
    echo "<p>Bulunan SQLite dosyaları: " . count($sqlite_files) . "</p>";
    
    foreach ($sqlite_files as $file) {
        echo "<h3>Düzeltiliyor: $file</h3>";
        
        // Dosya izinlerini düzelt
        if (chmod($file, 0666)) {
            echo "✅ İzin düzeltildi (666)<br>";
            $fixed++;
        } else {
            echo "❌ İzin düzeltilemedi<br>";
            $errors++;
        }
        
        // Klasör izinlerini de düzelt
        $dir = dirname($file);
        if (chmod($dir, 0755)) {
            echo "✅ Klasör izni düzeltildi: $dir<br>";
        } else {
            echo "❌ Klasör izni düzeltilemedi: $dir<br>";
        }
        
        // SQLite dosyasını test et
        testSQLiteWrite($file);
    }
    
    return ['fixed' => $fixed, 'errors' => $errors];
}

// Recursive olarak SQLite dosyalarını bul
function findSQLiteFiles($dir, &$files) {
    if (!is_dir($dir)) return;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            findSQLiteFiles($path, $files);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'sqlite') {
            $files[] = $path;
        }
    }
}

// SQLite dosyasını test et
function testSQLiteWrite($file) {
    echo "<h4>Test: $file</h4>";
    
    try {
        $db = new SQLite3($file);
        
        // Test tablosu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS write_test (id INTEGER PRIMARY KEY, data TEXT)");
        
        // Test verisi ekle
        $stmt = $db->prepare("INSERT INTO write_test (data) VALUES (?)");
        $stmt->bindValue(1, 'test_' . time());
        $result = $stmt->execute();
        
        if ($result) {
            echo "✅ Yazma testi başarılı<br>";
            
            // Test verisini sil
            $db->exec("DELETE FROM write_test");
            $db->exec("DROP TABLE write_test");
            
        } else {
            echo "❌ Yazma testi başarısız<br>";
        }
        
        $db->close();
        
    } catch (Exception $e) {
        echo "❌ SQLite hatası: " . $e->getMessage() . "<br>";
    }
}

// Klasör izinlerini toplu düzelt
function fixAllDirectories() {
    echo "<h2>2. Tüm Klasör İzinlerini Düzeltme</h2>";
    
    $dirs = [
        '.',
        'communities',
        'system',
        'system/logs',
        'system/scripts',
        'system/config',
        'assets',
        'assets/images',
        'assets/css',
        'assets/js',
        'templates',
        'superadmin',
        'docs'
    ];
    
    foreach ($dirs as $dir) {
        if (file_exists($dir)) {
            if (chmod($dir, 0755)) {
                echo "✅ $dir - Klasör izni düzeltildi<br>";
            } else {
                echo "❌ $dir - Klasör izni düzeltilemedi<br>";
            }
        }
    }
}

// .htaccess dosyasını güncelle
function createSQLiteHtaccess() {
    echo "<h2>3. .htaccess Güncelleme</h2>";
    
    $htaccess = "# UniPanel - SQLite İzin Düzeltme
RewriteEngine On

# PHP ayarları
php_flag display_errors On
php_value error_reporting E_ALL

# SQLite dosyaları için özel izinler
<Files \"*.sqlite\">
    Order allow,deny
    Allow from all
    Require all granted
</Files>

# Log dosyaları için özel izinler  
<Files \"*.log\">
    Order allow,deny
    Allow from all
    Require all granted
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

# SQLite için özel ayarlar
php_value sqlite3.extension_dir \".\"
";

    if (file_put_contents('.htaccess', $htaccess)) {
        echo "✅ .htaccess dosyası oluşturuldu<br>";
        chmod('.htaccess', 0644);
    } else {
        echo "❌ .htaccess dosyası oluşturulamadı<br>";
    }
}

// Ana işlemleri çalıştır
echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
echo "<h3>⚠️ Acil SQLite İzin Düzeltme Başlıyor...</h3>";
echo "</div>";

$result = emergencyFixSQLite();
fixAllDirectories();
createSQLiteHtaccess();

echo "<h2>4. Sonuç</h2>";
echo "<p><strong>Düzeltilen dosya sayısı:</strong> " . $result['fixed'] . "</p>";
echo "<p><strong>Hata sayısı:</strong> " . $result['errors'] . "</p>";

if ($result['fixed'] > 0) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>✅ Başarılı!</h3>";
    echo "<p>SQLite dosyalarının izinleri düzeltildi. Artık yazma işlemleri çalışmalı.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>❌ Hata!</h3>";
    echo "<p>SQLite dosyalarının izinleri düzeltilemedi. Hosting sağlayıcınızla iletişime geçin.</p>";
    echo "</div>";
}

echo "<h2>5. Test Etme</h2>";
echo "<p>Aşağıdaki linkleri test edin:</p>";
echo "<ul>";
echo "<li><a href='superadmin/login.php' target='_blank'>SuperAdmin Giriş</a></li>";
echo "<li><a href='hosting_test.php' target='_blank'>Hosting Test</a></li>";
echo "</ul>";

echo "<h2>6. Hosting Sağlayıcısına Bildirilecek Bilgiler</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 4px;'>";
echo "<p><strong>Hata:</strong> SQLite3Stmt::execute(): Unable to execute statement: attempt to write a readonly database</p>";
echo "<p><strong>Çözüm:</strong> SQLite dosyalarının yazma izinlerinin 666 olması gerekiyor</p>";
echo "<p><strong>Dosyalar:</strong> communities/*/unipanel.sqlite</p>";
echo "<p><strong>İzin:</strong> chmod 666 *.sqlite</p>";
echo "</div>";

echo "<hr>";
echo "<p><em>Acil SQLite düzeltme script'i çalıştırıldı: " . date('Y-m-d H:i:s') . "</em></p>";
?>
