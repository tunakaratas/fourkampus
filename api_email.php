<?php
/**
 * Direct Email API - Basit ve hızlı email gönderimi
 */

header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }
    
    // Credentials yükle
    $credentials = require __DIR__ . '/config/credentials.php';
    $smtp = $credentials['smtp'];
    
    $host = $smtp['host'];
    $port = $smtp['port'];
    $username = $smtp['username'];
    $password = $smtp['password'];
    $secure = $smtp['encryption'] ?? 'tls';
    $from_name = $smtp['from_name'] ?? 'Four Kampüs';
    
    // Parametreleri al
    // Parametreleri al
    $to = [];
    
    // JSON input (çoklu alıcı)
    if (!empty($_POST['selected_emails_json'])) {
        $jsonEmails = json_decode($_POST['selected_emails_json'], true);
        if (is_array($jsonEmails)) {
            $to = array_merge($to, $jsonEmails);
        }
    }
    
    // Array input
    $selectedEmails = $_POST['selected_emails'] ?? $_POST['to'] ?? '';
    if (is_array($selectedEmails)) {
        $to = array_merge($to, $selectedEmails);
    } elseif (is_string($selectedEmails) && !empty($selectedEmails)) {
        $to[] = $selectedEmails;
    }
    
    // Array'i string'e çevir
    $to = implode(',', array_unique($to));
    $to = trim($to);
    
    $subject = trim($_POST['email_subject'] ?? $_POST['subject'] ?? '');
    $message = trim($_POST['email_body'] ?? $_POST['message'] ?? '');
    
    // Validasyon
    if (empty($to)) {
        throw new Exception('Email adresi girilmedi');
    }
    if (empty($subject)) {
        throw new Exception('Konu boş');
    }
    if (empty($message)) {
        throw new Exception('Mesaj içeriği boş');
    }
    if (empty($username) || empty($password)) {
        throw new Exception('SMTP ayarları eksik');
    }
    
    // Alıcıları array olarak sakla (RCPT TO için)
    $recipientsArray = array_map('trim', explode(',', $to));
    
    // Header için string formatı
    $toString = $to;
    
    // Debug log
    error_log("[EMAIL_API] Sending to: " . count($recipientsArray) . " recipients ($toString) Subject: $subject");
    
    // Email gönder
    $fp = @stream_socket_client($host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]));
    
    if (!$fp) {
        throw new Exception("SMTP bağlantı hatası: $errstr");
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
    };
    
    $check = function($expected, $step) use ($read) {
        $response = $read();
        if (strpos($response, $expected) !== 0) {
            error_log("[EMAIL_API] $step Error: $response");
            throw new Exception("$step hatası: " . trim($response));
        }
        return $response;
    };
    
    $read(); // banner
    $write('EHLO localhost');
    $check('250', 'EHLO');
    
    // TLS
    $write('STARTTLS');
    $tlsResp = $read();
    if (strpos($tlsResp, '220') === 0) {
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO localhost');
        $check('250', 'EHLO TLS');
    }
    
    $write('AUTH LOGIN');
    $check('334', 'AUTH');
    $write(base64_encode($username));
    $check('334', 'Username');
    $write(base64_encode($password));
    $check('235', 'Password');
    
    $write('MAIL FROM: <' . $username . '>');
    $check('250', 'MAIL FROM');
    
    // Her alıcı için RCPT TO gönder
    $acceptedRecipients = 0;
    foreach ($recipientsArray as $recipient) {
        if (empty($recipient)) continue;
        $write('RCPT TO: <' . $recipient . '>');
        $rcptResponse = $read(); // 250 veya 251 kabul
        if (strpos($rcptResponse, '250') === 0 || strpos($rcptResponse, '251') === 0) {
            $acceptedRecipients++;
        } else {
            error_log("[EMAIL_API] Failed to add recipient $recipient: $rcptResponse");
        }
    }
    
    if ($acceptedRecipients === 0) {
        throw new Exception("Hiçbir alıcı kabul edilmedi.");
    }
    
    $write('DATA');
    $check('354', 'DATA');
    
    // Email içeriği
    $headers = "From: $from_name <$username>\r\n";
    $headers .= "To: $toString\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "\r\n";
    
    $body = "<html><body style='font-family: Arial, sans-serif;'>" . nl2br(htmlspecialchars($message)) . "</body></html>";
    
    fputs($fp, $headers . $body . "\r\n.\r\n");
    $response = $check('250', 'Message');
    
    $write('QUIT');
    fclose($fp);
    
    // Queue ID çıkar
    preg_match('/queued as ([A-Za-z0-9]+)/', $response, $matches);
    $queueId = $matches[1] ?? 'OK';
    
    error_log("[EMAIL_API] Email sent successfully. Queue ID: $queueId");
    
    echo json_encode([
        'success' => true,
        'message' => "Email başarıyla gönderildi! ✓",
        'queue_id' => $queueId
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log("[EMAIL_API] Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
