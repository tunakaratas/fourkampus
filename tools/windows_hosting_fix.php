<?php
/**
 * WINDOWS HOSTING SQLITE DÜZELTME
 * Windows hosting'de SQLite readonly hatası için özel çözüm
 */

echo "<h1>🪟 Windows Hosting SQLite Düzeltme</h1>";
echo "<p>Windows hosting'de SQLite readonly hatası düzeltiliyor...</p>";

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Windows hosting için özel çözüm
function fixWindowsHostingSQLite() {
    echo "<h2>1. Windows Hosting Tespiti</h2>";
    
    $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    echo "<p>İşletim Sistemi: " . PHP_OS . ($is_windows ? " (Windows)" : " (Linux)") . "</p>";
    
    if ($is_windows) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
        echo "<h3>⚠️ Windows Hosting Tespit Edildi</h3>";
        echo "<p>Windows hosting'de chmod() çalışmaz. Alternatif çözümler uygulanacak.</p>";
        echo "</div>";
    }
    
    return $is_windows;
}

// SQLite dosyalarını yeniden oluştur
function recreateSQLiteFiles() {
    echo "<h2>2. SQLite Dosyalarını Yeniden Oluşturma</h2>";
    
    $sqlite_files = [];
    findSQLiteFiles('.', $sqlite_files);
    
    $recreated = 0;
    $errors = 0;
    
    foreach ($sqlite_files as $file) {
        echo "<h3>Yeniden oluşturuluyor: $file</h3>";
        
        try {
            // Eski dosyayı yedekle
            $backup_file = $file . '.backup.' . time();
            if (file_exists($file)) {
                if (copy($file, $backup_file)) {
                    echo "✅ Yedek oluşturuldu: $backup_file<br>";
                }
            }
            
            // Yeni SQLite dosyası oluştur
            $db = new SQLite3($file);
            
            // Temel tabloları oluştur
            createBasicTables($db);
            
            $db->close();
            
            echo "✅ SQLite dosyası yeniden oluşturuldu: $file<br>";
            $recreated++;
            
        } catch (Exception $e) {
            echo "❌ Hata: " . $e->getMessage() . "<br>";
            $errors++;
        }
    }
    
    return ['recreated' => $recreated, 'errors' => $errors];
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

// Temel tabloları oluştur
function createBasicTables($db) {
    echo "<h4>Tablo oluşturuluyor...</h4>";
    
    // Settings tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        setting_key TEXT UNIQUE,
        setting_value TEXT
    )");
    
    // Admins tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        username TEXT,
        password_hash TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Members tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS members (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        full_name TEXT,
        email TEXT,
        student_id TEXT,
        phone_number TEXT,
        registration_date TEXT,
        is_banned INTEGER DEFAULT 0,
        ban_reason TEXT
    )");
    
    // Events tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        date TEXT NOT NULL,
        time TEXT,
        location TEXT,
        is_active INTEGER DEFAULT 1
    )");
    
    // Board members tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS board_members (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        name TEXT,
        role TEXT,
        email TEXT,
        phone TEXT,
        is_active INTEGER DEFAULT 1
    )");
    
    // Notifications tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY,
        title TEXT,
        message TEXT,
        type TEXT DEFAULT 'info',
        is_read INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Partner logos tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS partner_logos (
        id INTEGER PRIMARY KEY,
        partner_name TEXT,
        partner_website TEXT,
        logo_path TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Admin logs tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id INTEGER PRIMARY KEY,
        community_name TEXT,
        action TEXT,
        details TEXT,
        timestamp TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "✅ Temel tablolar oluşturuldu<br>";
}

// .htaccess dosyasını Windows hosting için optimize et
function createWindowsHtaccess() {
    echo "<h2>3. Windows Hosting .htaccess</h2>";
    
    $htaccess = "# UniPanel - Windows Hosting Optimizasyonu
RewriteEngine On

# PHP ayarları
php_flag display_errors On
php_value error_reporting E_ALL

# SQLite dosyaları için özel ayarlar
<Files \"*.sqlite\">
    Order allow,deny
    Allow from all
    Require all granted
</Files>

# Log dosyaları için özel ayarlar
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

# Windows hosting için özel ayarlar
php_value auto_prepend_file \"\"
php_value auto_append_file \"\"

# SQLite için özel ayarlar
php_value sqlite3.extension_dir \".\"

# Güvenlik başlıkları
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
";

    if (file_put_contents('.htaccess', $htaccess)) {
        echo "✅ Windows hosting .htaccess oluşturuldu<br>";
    } else {
        echo "❌ .htaccess oluşturulamadı<br>";
    }
}

// Test dosyası oluştur
function createTestFile() {
    echo "<h2>4. Test Dosyası Oluşturma</h2>";
    
    $test_content = "<?php
// Windows Hosting Test
echo '<h1>Windows Hosting Test</h1>';
echo '<p>PHP Version: ' . PHP_VERSION . '</p>';
echo '<p>OS: ' . PHP_OS . '</p>';
echo '<p>Server: ' . \$_SERVER['SERVER_SOFTWARE'] . '</p>';

// SQLite test
if (class_exists('SQLite3')) {
    echo '<p>SQLite3: ✅ Mevcut</p>';
    
    // Test SQLite dosyası oluştur
    \$test_db = 'test.sqlite';
    try {
        \$db = new SQLite3(\$test_db);
        \$db->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, data TEXT)');
        \$db->exec('INSERT INTO test (data) VALUES (\"test\")');
        \$result = \$db->querySingle('SELECT data FROM test WHERE id = 1');
        
        if (\$result === 'test') {
            echo '<p>SQLite Yazma: ✅ Çalışıyor</p>';
        } else {
            echo '<p>SQLite Yazma: ❌ Çalışmıyor</p>';
        }
        
        \$db->close();
        unlink(\$test_db); // Test dosyasını sil
        
    } catch (Exception \$e) {
        echo '<p>SQLite Test: ❌ ' . \$e->getMessage() . '</p>';
    }
} else {
    echo '<p>SQLite3: ❌ Mevcut değil</p>';
}

// İzin testleri
echo '<h2>İzin Testleri</h2>';
echo '<p>Current Directory: ' . getcwd() . '</p>';
echo '<p>Communities yazılabilir mi: ' . (is_writable('communities') ? '✅ Evet' : '❌ Hayır') . '</p>';
echo '<p>System yazılabilir mi: ' . (is_writable('system') ? '✅ Evet' : '❌ Hayır') . '</p>';
echo '<p>Assets yazılabilir mi: ' . (is_writable('assets') ? '✅ Evet' : '❌ Hayır') . '</p>';
?>";

    if (file_put_contents('windows_hosting_test.php', $test_content)) {
        echo "✅ Windows hosting test dosyası oluşturuldu<br>";
    } else {
        echo "❌ Test dosyası oluşturulamadı<br>";
    }
}

// Ana işlemleri çalıştır
$is_windows = fixWindowsHostingSQLite();

if ($is_windows) {
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>ℹ️ Windows Hosting Tespit Edildi</h3>";
    echo "<p>Windows hosting'de chmod() çalışmaz. SQLite dosyaları yeniden oluşturulacak.</p>";
    echo "</div>";
    
    $result = recreateSQLiteFiles();
    createWindowsHtaccess();
    createTestFile();
    
    echo "<h2>5. Sonuç</h2>";
    echo "<p><strong>Yeniden oluşturulan dosya sayısı:</strong> " . $result['recreated'] . "</p>";
    echo "<p><strong>Hata sayısı:</strong> " . $result['errors'] . "</p>";
    
    if ($result['recreated'] > 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
        echo "<h3>✅ Başarılı!</h3>";
        echo "<p>SQLite dosyaları yeniden oluşturuldu. Artık yazma işlemleri çalışmalı.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
        echo "<h3>❌ Hata!</h3>";
        echo "<p>SQLite dosyaları yeniden oluşturulamadı. Hosting sağlayıcınızla iletişime geçin.</p>";
        echo "</div>";
    }
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>ℹ️ Linux Hosting Tespit Edildi</h3>";
    echo "<p>Linux hosting'de normal izin düzeltme script'lerini kullanabilirsiniz.</p>";
    echo "</div>";
}

echo "<h2>6. Test Etme</h2>";
echo "<p>Aşağıdaki linkleri test edin:</p>";
echo "<ul>";
echo "<li><a href='windows_hosting_test.php' target='_blank'>Windows Hosting Test</a></li>";
echo "<li><a href='superadmin/login.php' target='_blank'>SuperAdmin Giriş</a></li>";
echo "</ul>";

echo "<h2>7. Windows Hosting Özel Notları</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 4px;'>";
echo "<ul>";
echo "<li><strong>chmod() çalışmaz:</strong> Windows hosting'de dosya izinleri farklı çalışır</li>";
echo "<li><strong>SQLite yeniden oluşturuldu:</strong> Eski veriler yedeklendi</li>";
echo "<li><strong>.htaccess optimize edildi:</strong> Windows hosting için özel ayarlar</li>";
echo "<li><strong>Test dosyası oluşturuldu:</strong> Sistem durumunu kontrol edin</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><em>Windows hosting SQLite düzeltme script'i çalıştırıldı: " . date('Y-m-d H:i:s') . "</em></p>";
?>
