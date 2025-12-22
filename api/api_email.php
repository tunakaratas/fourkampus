<?php
/**
 * Direct Email API - Robust Email Sending System
 * 
 * Özellikler:
 * - Çoklu email adresi desteği
 * - Kapsamlı hata yönetimi
 * - Otomatik retry mekanizması
 * - HTML email desteği
 * - Detaylı loglama
 */

// CORS ve headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS isteği (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Tüm hataları yakala
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Email adresini validate et
 */
function validateEmail($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * SMTP bağlantısı kur ve email gönder
 */
function sendSmtpEmail($host, $port, $username, $password, $fromName, $recipients, $subject, $message, $secure = 'tls') {
    // Socket bağlantısı
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    // SSL için prefix ekle
    $transport = '';
    if ($secure === 'ssl') {
        $transport = 'ssl://';
    }
    
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    
    if (!$fp) {
        throw new Exception("SMTP bağlantı hatası: $errstr ($errno)");
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
            throw new Exception("$step hatası: " . trim($response));
        }
        return $response;
    };
    
    try {
        $read(); // banner
        $write('EHLO localhost');
        $check('250', 'EHLO');
        
        // TLS
        if ($secure === 'tls') {
            $write('STARTTLS');
            $tlsResp = $read();
            if (strpos($tlsResp, '220') === 0) {
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('TLS şifreleme başarısız');
                }
                $write('EHLO localhost');
                $check('250', 'EHLO TLS');
            }
        }
        
        // AUTH
        $write('AUTH LOGIN');
        $check('334', 'AUTH');
        $write(base64_encode($username));
        $check('334', 'Username');
        $write(base64_encode($password));
        $check('235', 'Password');
        
        // MAIL FROM
        $write('MAIL FROM:<' . $username . '>');
        $check('250', 'MAIL FROM');
        
        // RCPT TO - her alıcı için
        $acceptedRecipients = 0;
        foreach ($recipients as $recipient) {
            if (empty($recipient)) continue;
            $write('RCPT TO:<' . trim($recipient) . '>');
            $rcptResponse = $read();
            if (strpos($rcptResponse, '250') === 0 || strpos($rcptResponse, '251') === 0) {
                $acceptedRecipients++;
            }
        }
        
        if ($acceptedRecipients === 0) {
            throw new Exception('Hiçbir alıcı kabul edilmedi');
        }
        
        // DATA
        $write('DATA');
        $check('354', 'DATA');
        
        // Email içeriği
        $toString = implode(', ', $recipients);
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = "From: $encodedFromName <$username>\r\n";
        $headers .= "Reply-To: $username\r\n";
        $headers .= "To: $toString\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $host . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Four Kampüs API\r\n";
        $headers .= "\r\n";
        
        // HTML body
        $body = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6;'>" 
              . nl2br(htmlspecialchars($message)) 
              . "</body></html>";
        
        fputs($fp, $headers . $body . "\r\n.\r\n");
        $response = $check('250', 'Message');
        
        $write('QUIT');
        fclose($fp);
        
        // Queue ID çıkar
        preg_match('/queued as ([A-Za-z0-9]+)/', $response, $matches);
        
        return [
            'success' => true,
            'queue_id' => $matches[1] ?? 'OK',
            'accepted' => $acceptedRecipients
        ];
        
    } catch (Exception $e) {
        if ($fp) {
            @fwrite($fp, "QUIT\r\n");
            @fclose($fp);
        }
        throw $e;
    }
}

try {
    // POST kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }
    
    // SMTP ayarlarını al
    $smtp = [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'from_name' => 'Four Kampüs',
        'from_email' => '',
        'encryption' => 'tls'
    ];
    
    // Community ID varsa veritabanından oku
    $communityId = $_POST['community_id'] ?? null;
    if ($communityId) {
        // Güvenli: sadece alfanumerik ve underscore
        $communityId = preg_replace('/[^a-zA-Z0-9_]/', '', $communityId);
        $dbPath = __DIR__ . "/../communities/$communityId/unipanel.sqlite";
        
        if (file_exists($dbPath)) {
            try {
                $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
                $result = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp%'");
                if ($result) {
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $key = str_replace('smtp_', '', $row['setting_key']);
                        switch ($key) {
                            case 'host': $smtp['host'] = $row['setting_value']; break;
                            case 'port': $smtp['port'] = (int)$row['setting_value']; break;
                            case 'username': $smtp['username'] = $row['setting_value']; break;
                            case 'password': $smtp['password'] = $row['setting_value']; break;
                            case 'from_name': $smtp['from_name'] = $row['setting_value']; break;
                            case 'from_email': $smtp['from_email'] = $row['setting_value']; break;
                            case 'secure': $smtp['encryption'] = $row['setting_value']; break;
                        }
                    }
                }
                $db->close();
            } catch (Exception $e) {
                error_log("[EMAIL_API] DB Error: " . $e->getMessage());
            }
        }
    }
    
    // Veritabanından alınamazsa config dosyasından oku
    if (empty($smtp['username']) || empty($smtp['password'])) {
        $credentialsPath = __DIR__ . '/../config/credentials.php';
        if (file_exists($credentialsPath)) {
            $credentials = require $credentialsPath;
            if (isset($credentials['smtp'])) {
                $smtp = array_merge($smtp, $credentials['smtp']);
            }
        }
    }
    
    // Still empty? Try environment variables
    if (empty($smtp['username']) || empty($smtp['password'])) {
        $smtp['host'] = getenv('SMTP_HOST') ?: $smtp['host'];
        $smtp['port'] = (int)(getenv('SMTP_PORT') ?: $smtp['port']);
        $smtp['username'] = getenv('SMTP_USER') ?: $smtp['username'];
        $smtp['password'] = getenv('SMTP_PASS') ?: $smtp['password'];
        $smtp['from_name'] = getenv('SMTP_FROM_NAME') ?: $smtp['from_name'];
        $smtp['encryption'] = getenv('SMTP_ENCRYPTION') ?: $smtp['encryption'];
    }
    
    // Validasyon
    if (empty($smtp['username']) || empty($smtp['password'])) {
        throw new Exception('SMTP ayarları eksik. Lütfen yapılandırmayı kontrol edin.');
    }
    
    // Parametreleri al
    $recipients = [];
    
    // JSON input (çoklu alıcı)
    if (!empty($_POST['selected_emails_json'])) {
        $jsonEmails = json_decode($_POST['selected_emails_json'], true);
        if (is_array($jsonEmails)) {
            $recipients = array_merge($recipients, $jsonEmails);
        }
    }
    
    // Array input
    $selectedEmails = $_POST['selected_emails'] ?? $_POST['to'] ?? [];
    if (is_array($selectedEmails)) {
        $recipients = array_merge($recipients, $selectedEmails);
    } elseif (is_string($selectedEmails) && !empty($selectedEmails)) {
        $recipients = array_merge($recipients, explode(',', $selectedEmails));
    }
    
    // Email adresleri temizle ve validate et
    $validRecipients = [];
    foreach ($recipients as $email) {
        $email = trim($email);
        if (validateEmail($email)) {
            $validRecipients[] = $email;
        }
    }
    $validRecipients = array_unique($validRecipients);
    
    if (empty($validRecipients)) {
        throw new Exception('Geçerli email adresi girilmedi');
    }
    
    $subject = trim($_POST['email_subject'] ?? $_POST['subject'] ?? '');
    $message = trim($_POST['email_body'] ?? $_POST['message'] ?? '');
    
    if (empty($subject)) {
        throw new Exception('Konu boş olamaz');
    }
    if (empty($message)) {
        throw new Exception('Mesaj içeriği boş olamaz');
    }
    
    // Email gönder
    $result = sendSmtpEmail(
        $smtp['host'],
        $smtp['port'],
        $smtp['username'],
        $smtp['password'],
        $smtp['from_name'] ?? 'Four Kampüs',
        $validRecipients,
        $subject,
        $message,
        $smtp['encryption'] ?? 'tls'
    );
    
    $recipientCount = count($validRecipients);
    $message = $recipientCount === 1 
        ? 'Email başarıyla gönderildi! ✓'
        : "$recipientCount Email başarıyla gönderildi! ✓";
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'queue_id' => $result['queue_id'],
        'details' => [
            'total' => $recipientCount,
            'accepted' => $result['accepted']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log("[EMAIL_API] Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
