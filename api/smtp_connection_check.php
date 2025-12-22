<?php
/**
 * SMTP Kimlik Doğrulama Testi
 * 
 * Bu script SMTP bağlantısını test eder ve hatayı detaylı gösterir.
 * 
 * Kullanım: http://localhost/fourkampus/api/smtp_connection_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px;'>\n";
echo "<span style='color:#4ec9b0'>========================================</span>\n";
echo "<span style='color:#4ec9b0'>  SMTP Kimlik Doğrulama Testi</span>\n";
echo "<span style='color:#4ec9b0'>========================================</span>\n\n";

// Credentials dosyasını yükle
$credentialsPath = __DIR__ . '/../config/credentials.php';

if (!file_exists($credentialsPath)) {
    echo "<span style='color:#f14c4c'>❌ HATA: config/credentials.php dosyası bulunamadı!</span>\n";
    echo "   → config/credentials.example.php dosyasını kopyalayıp düzenleyin.\n\n";
    echo "</pre>";
    exit(1);
}

$credentials = require $credentialsPath;
$smtp = $credentials['smtp'] ?? [];

if (empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password'])) {
    echo "<span style='color:#f14c4c'>❌ HATA: SMTP ayarları eksik!</span>\n";
    echo "   Host: " . ($smtp['host'] ?? 'BOŞ') . "\n";
    echo "   Username: " . ($smtp['username'] ?? 'BOŞ') . "\n";
    echo "   Password: " . (!empty($smtp['password']) ? '***SET***' : 'BOŞ') . "\n";
    echo "</pre>";
    exit(1);
}

$host = $smtp['host'];
$port = (int)($smtp['port'] ?? 587);
$username = $smtp['username'];
$password = $smtp['password'];
$encryption = strtolower($smtp['encryption'] ?? 'tls');

echo "<span style='color:#569cd6'>📧 SMTP Ayarları:</span>\n";
echo "   Host: <span style='color:#ce9178'>$host</span>\n";
echo "   Port: <span style='color:#ce9178'>$port</span>\n";
echo "   Username: <span style='color:#ce9178'>$username</span>\n";
echo "   Password: <span style='color:#ce9178'>" . str_repeat('*', min(strlen($password), 8)) . "</span> (" . strlen($password) . " karakter)\n";
echo "   Encryption: <span style='color:#ce9178'>$encryption</span>\n\n";

// Socket bağlantısı
echo "<span style='color:#569cd6'>🔗 SMTP sunucusuna bağlanılıyor...</span>\n";

$transport = $encryption === 'ssl' ? 'ssl://' : '';
$timeout = 30;

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
]);

$fp = @stream_socket_client(
    $transport . $host . ':' . $port, 
    $errno, 
    $errstr, 
    $timeout, 
    STREAM_CLIENT_CONNECT, 
    $context
);

if (!$fp) {
    echo "<span style='color:#f14c4c'>❌ HATA: Bağlantı kurulamadı!</span>\n";
    echo "   Hata: $errstr ($errno)\n";
    echo "</pre>";
    exit(1);
}

echo "   <span style='color:#4ec9b0'>✅ Bağlantı başarılı!</span>\n\n";

stream_set_timeout($fp, $timeout);

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

// Banner oku
echo "<span style='color:#569cd6'>📨 SMTP Banner:</span>\n";
$banner = $read();
echo "   <span style='color:#6a9955'>" . htmlspecialchars(trim($banner)) . "</span>\n\n";

// EHLO gönder
echo "<span style='color:#569cd6'>📨 EHLO gönderiliyor...</span>\n";
$write('EHLO localhost');
$ehlo = $read();
echo "   <span style='color:#6a9955'>" . str_replace("\r\n", "\n   ", htmlspecialchars(trim($ehlo))) . "</span>\n\n";

if (strpos($ehlo, '250') !== 0) {
    echo "<span style='color:#f14c4c'>❌ EHLO başarısız!</span>\n";
    fclose($fp);
    echo "</pre>";
    exit(1);
}
echo "   <span style='color:#4ec9b0'>✅ EHLO başarılı!</span>\n\n";

// STARTTLS
if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
    echo "<span style='color:#569cd6'>🔐 STARTTLS gönderiliyor...</span>\n";
    $write('STARTTLS');
    $starttls = $read();
    echo "   <span style='color:#6a9955'>" . htmlspecialchars(trim($starttls)) . "</span>\n";
    
    if (strpos($starttls, '220') !== 0) {
        echo "<span style='color:#f14c4c'>❌ STARTTLS başarısız!</span>\n";
        fclose($fp);
        echo "</pre>";
        exit(1);
    }
    
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        echo "<span style='color:#f14c4c'>❌ TLS şifreleme açılamadı!</span>\n";
        fclose($fp);
        echo "</pre>";
        exit(1);
    }
    
    echo "   <span style='color:#4ec9b0'>✅ TLS şifreleme aktif!</span>\n\n";
    
    $write('EHLO localhost');
    $ehlo2 = $read();
    echo "   TLS EHLO: <span style='color:#4ec9b0'>✅</span>\n\n";
}

// AUTH LOGIN
echo "<span style='color:#569cd6'>🔑 Kimlik doğrulama başlıyor...</span>\n";
$write('AUTH LOGIN');
$auth1 = $read();
echo "   AUTH LOGIN: <span style='color:#6a9955'>" . htmlspecialchars(trim($auth1)) . "</span>\n";

if (strpos($auth1, '334') !== 0) {
    echo "<span style='color:#f14c4c'>❌ AUTH LOGIN başarısız!</span>\n";
    fclose($fp);
    echo "</pre>";
    exit(1);
}

$write(base64_encode($username));
$auth2 = $read();
echo "   Username: <span style='color:#6a9955'>" . htmlspecialchars(trim($auth2)) . "</span>\n";

$write(base64_encode($password));
$authResp = $read();
echo "   Password: <span style='color:#6a9955'>" . htmlspecialchars(trim($authResp)) . "</span>\n\n";

if (strpos($authResp, '235') === 0) {
    echo "<span style='color:#4ec9b0;font-size:16px'>✅ ✅ ✅ KİMLİK DOĞRULAMA BAŞARILI! ✅ ✅ ✅</span>\n\n";
    echo "   SMTP ayarları doğru çalışıyor.\n";
    echo "   E-posta gönderimi yapılabilir.\n";
} else {
    echo "<span style='color:#f14c4c;font-size:16px'>❌ ❌ ❌ KİMLİK DOĞRULAMA BAŞARISIZ! ❌ ❌ ❌</span>\n\n";
    echo "   <span style='color:#f14c4c'>Hata: " . htmlspecialchars(trim($authResp)) . "</span>\n\n";
    echo "<span style='color:#dcdcaa'>ÇÖZÜM:</span>\n";
    echo "   1. Hosting panelinize (cPanel/DirectAdmin) giriş yapın\n";
    echo "   2. E-posta hesapları bölümüne gidin\n";
    echo "   3. '<span style='color:#ce9178'>$username</span>' hesabının şifresini doğrulayın veya yenileyin\n";
    echo "   4. Yeni şifreyi <span style='color:#ce9178'>config/credentials.php</span> dosyasına kaydedin\n\n";
}

$write('QUIT');
fclose($fp);

echo "\n<span style='color:#4ec9b0'>========================================</span>\n";
echo "</pre>\n";
