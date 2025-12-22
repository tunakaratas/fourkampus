<?php
/**
 * SMTP Test Script
 */

$host = 'mail.guzel.net.tr';
$port = 465;
$username = 'admin@fourkampus.com.tr';
$password = '123a123s123.D';
$to = 'tun4aa@gmail.com';

echo "=== SMTP Test ===\n";
echo "Host: ssl://$host:$port\n";
echo "User: $username\n";
echo "To: $to\n\n";

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
]);

$fp = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

if (!$fp) {
    die("CONNECTION FAILED: $errstr ($errno)\n");
}

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
    echo ">> $cmd\n";
};

echo "<< " . trim($read()) . "\n";

$write('EHLO localhost');
echo "<< " . trim($read()) . "\n";

$write('AUTH LOGIN');
echo "<< " . trim($read()) . "\n";

$write(base64_encode($username));
echo "<< " . trim($read()) . "\n";

$write(base64_encode($password));
$authResp = trim($read());
echo "<< $authResp\n";

if (strpos($authResp, '235') !== 0) {
    die("AUTH FAILED!\n");
}

$write("MAIL FROM:<$username>");
echo "<< " . trim($read()) . "\n";

$write("RCPT TO:<$to>");
echo "<< " . trim($read()) . "\n";

$write('DATA');
echo "<< " . trim($read()) . "\n";

$subject = "SMTP TEST - " . date('Y-m-d H:i:s');
$message = "Bu mail " . date('Y-m-d H:i:s') . " tarihinde SMTP test amaciyla gonderildi.\n\nBu mesaj geldiyse SMTP calisiyor demektir.";

$headers = "From: Four Kampus <$username>\r\n";
$headers .= "To: $to\r\n";
$headers .= "Subject: $subject\r\n";
$headers .= "Date: " . date('r') . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "\r\n";

fputs($fp, $headers . $message . "\r\n.\r\n");
$sendResp = trim($read());
echo "<< $sendResp\n";

$write('QUIT');
fclose($fp);

if (strpos($sendResp, '250') === 0) {
    echo "\n✅ BASARILI! Mail gonderildi: $to\n";
} else {
    echo "\n❌ HATA! Mail gonderilemedi.\n";
}
