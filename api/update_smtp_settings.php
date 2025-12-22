<?php
/**
 * Tüm toplulukların SMTP ayarlarını günceller
 * 
 * Kullanım: php api/update_smtp_settings.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<pre>\n";
echo "========================================\n";
echo "  SMTP Ayarlarını Topluluk DB'lerine Kaydet\n";
echo "========================================\n\n";

// Doğru SMTP ayarları
$smtp_settings = [
    'smtp_host' => 'mail.guzel.net.tr',
    'smtp_port' => '465',
    'smtp_secure' => 'ssl',
    'smtp_username' => 'admin@fourkampus.com.tr',
    'smtp_password' => '123a123s123.D',
    'smtp_from_email' => 'admin@fourkampus.com.tr',
    'smtp_from_name' => 'Four Kampüs'
];

echo "Kaydedilecek SMTP ayarları:\n";
foreach ($smtp_settings as $key => $value) {
    if ($key === 'smtp_password') {
        echo "  $key: ********\n";
    } else {
        echo "  $key: $value\n";
    }
}
echo "\n";

// Communities dizini
$communities_dir = __DIR__ . '/../communities';
$updated = 0;
$failed = 0;

if (!is_dir($communities_dir)) {
    echo "❌ Communities dizini bulunamadı!\n";
    echo "</pre>";
    exit(1);
}

$dirs = scandir($communities_dir);
foreach ($dirs as $dir) {
    if ($dir === '.' || $dir === '..' || $dir === 'public' || $dir === '.DS_Store') {
        continue;
    }
    
    $db_path = $communities_dir . '/' . $dir . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        continue;
    }
    
    try {
        $db = new SQLite3($db_path);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Settings tablosu var mı kontrol et
        $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        if (!$table_check->fetchArray()) {
            // Settings tablosu yok, oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INTEGER, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL)");
        }
        
        // Her SMTP ayarını kaydet
        foreach ($smtp_settings as $key => $value) {
            // Önce mevcut ayarı sil
            $stmt = $db->prepare('DELETE FROM settings WHERE club_id = 1 AND setting_key = :key');
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->execute();
            
            // Yeni ayarı ekle
            $stmt = $db->prepare('INSERT INTO settings (club_id, setting_key, setting_value) VALUES (1, :key, :value)');
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        $db->close();
        echo "✅ $dir: SMTP ayarları güncellendi\n";
        $updated++;
        
    } catch (Exception $e) {
        echo "❌ $dir: HATA - " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n========================================\n";
echo "Toplam: " . ($updated + $failed) . " topluluk\n";
echo "Başarılı: $updated\n";
echo "Başarısız: $failed\n";
echo "========================================\n";

// Şimdi SMTP'yi test et
echo "\n🔄 SMTP bağlantısı test ediliyor...\n\n";

$host = $smtp_settings['smtp_host'];
$port = (int)$smtp_settings['smtp_port'];
$username = $smtp_settings['smtp_username'];
$password = $smtp_settings['smtp_password'];

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
]);

$fp = @stream_socket_client(
    'ssl://' . $host . ':' . $port, 
    $errno, 
    $errstr, 
    30, 
    STREAM_CLIENT_CONNECT, 
    $context
);

if (!$fp) {
    echo "❌ Bağlantı kurulamadı: $errstr\n";
} else {
    stream_set_timeout($fp, 30);
    
    $read = function() use ($fp) {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };
    
    $write = function($cmd) use ($fp) {
        fputs($fp, $cmd . "\r\n");
    };
    
    $banner = $read();
    $write('EHLO localhost');
    $ehlo = $read();
    
    if (strpos($ehlo, '250') === 0) {
        $write('AUTH LOGIN');
        $auth1 = $read();
        $write(base64_encode($username));
        $auth2 = $read();
        $write(base64_encode($password));
        $authResp = $read();
        
        if (strpos($authResp, '235') === 0) {
            echo "✅ SMTP kimlik doğrulama BAŞARILI!\n";
            echo "   Artık mail gönderimi çalışacaktır.\n";
        } else {
            echo "❌ Kimlik doğrulama başarısız: " . trim($authResp) . "\n";
        }
    } else {
        echo "❌ EHLO başarısız: " . trim($ehlo) . "\n";
    }
    
    $write('QUIT');
    fclose($fp);
}

echo "</pre>\n";
