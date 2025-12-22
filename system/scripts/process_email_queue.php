<?php
/**
 * Email Queue Processor
 * Arka planda email kuyruğunu işleyen worker script
 * 
 * Kullanım: php process_email_queue.php <db_path> <club_id>
 */

require_once __DIR__ . '/security_helper.php';

// CLI'den çalıştırılıyor mu kontrol et
if (php_sapi_name() !== 'cli' && !isset($_GET['run_as_worker'])) {
    // Web'den erişim için authentication kontrolü
    $auth_token = $_GET['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $valid_token = getenv('SYSTEM_SCRIPT_TOKEN') ?: 'change_this_token_in_production';
    
    if ($auth_token !== $valid_token) {
        http_response_code(403);
        die('Unauthorized');
    }
}

// Parametreleri al
$db_path = $argv[1] ?? $_GET['db_path'] ?? null;
$club_id = (int)($argv[2] ?? $_GET['club_id'] ?? 1);

// Path sanitization
if ($db_path) {
    $sanitized_db_path = sanitizePath($db_path);
    if (!$sanitized_db_path || !file_exists($sanitized_db_path)) {
        secureLog("Email Queue Processor: Database path not found or invalid: $db_path", 'error');
        exit(1);
    }
    $db_path = $sanitized_db_path;
} else {
    secureLog("Email Queue Processor: Database path not provided", 'error');
    exit(1);
}

// Bootstrap
require_once dirname(__DIR__, 2) . '/lib/core/Database.php';
require_once dirname(__DIR__, 2) . '/lib/core/ErrorHandler.php';

use UniPanel\Core\Database;
use UniPanel\Core\ErrorHandler;

// Database bağlantısı
try {
    $database = Database::getInstance($db_path);
    $db = $database->getDb();
} catch (Exception $e) {
    error_log("Email Queue Processor: Database connection failed: " . $e->getMessage());
    exit(1);
}

// SMTP ayarlarını al
function get_smtp_settings($db, $club_id) {
    $settings = [];
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE club_id = ? AND setting_key LIKE 'smtp_%'");
    $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Fallback SMTP ayarları - Environment variable veya config dosyasından al
    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        require_once __DIR__ . '/security_helper.php';
        
        $settings['smtp_username'] = getCredential('SMTP_USERNAME', '');
        $settings['smtp_password'] = getCredential('SMTP_PASSWORD', '');
        $settings['smtp_host'] = getCredential('SMTP_HOST', 'ms8.guzel.net.tr');
        $settings['smtp_port'] = getCredential('SMTP_PORT', '587');
        $settings['smtp_secure'] = getCredential('SMTP_SECURE', 'tls');
        
        // Hala boşsa hata ver
        if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
            secureLog('SMTP credentials not found in database, environment, or config file', 'error');
            return [];
        }
    }
    
    return $settings;
}

// send_smtp_mail fonksiyonunu dahil et (template_index.php'den)
// Basit bir send_smtp_mail implementasyonu
function send_smtp_mail($to, $subject, $message, $from_name, $from_email, $config = []) {
    try {
        $host = $config['host'] ?? $config['smtp_host'] ?? 'ms8.guzel.net.tr';
        $port = (int)($config['port'] ?? $config['smtp_port'] ?? 587);
        $secure = strtolower($config['secure'] ?? $config['smtp_secure'] ?? 'tls');
        $username = $config['username'] ?? $config['smtp_username'] ?? '';
        $password = $config['password'] ?? $config['smtp_password'] ?? '';

        if (!$host || !$port || !$username || !$password) {
            return false;
        }

        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $timeout = 30;

        // SSL verification - Production'da açık olmalı
        $ssl_verify = !isProduction(); // Development'ta kapalı, production'da açık
        $fp = @stream_socket_client(($transport ?: '') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, stream_context_create([
            'ssl' => [
                'verify_peer' => $ssl_verify,
                'verify_peer_name' => $ssl_verify,
                'allow_self_signed' => !$ssl_verify,
            ],
        ]));

        if (!$fp) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }

        // SMTP handshake
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '220') {
            fclose($fp);
            return false;
        }

        // EHLO
        fputs($fp, "EHLO $host\r\n");
        $response = '';
        while ($line = fgets($fp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }

        // STARTTLS
        if ($secure === 'tls') {
            fputs($fp, "STARTTLS\r\n");
            $response = fgets($fp, 515);
            if (substr($response, 0, 3) !== '220') {
                fclose($fp);
                return false;
            }
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($fp, "EHLO $host\r\n");
            $response = '';
            while ($line = fgets($fp, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        // AUTH LOGIN
        fputs($fp, "AUTH LOGIN\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($fp);
            return false;
        }

        fputs($fp, base64_encode($username) . "\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($fp);
            return false;
        }

        fputs($fp, base64_encode($password) . "\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '235') {
            fclose($fp);
            return false;
        }

        // MAIL FROM her zaman sunucu kullanıcı adı ile aynı olmalı (Guzel Hosting vb. için)
        $envelopeFrom = $username;
        $write('MAIL FROM:<' . $envelopeFrom . '>');
        $response = $read();
        if (substr($response, 0, 3) !== '250') {
            $write('RSET');
            $read();
            fclose($fp);
            return false;
        }

        // RCPT TO
        $write('RCPT TO:<' . $to . '>');
        $response = $read();
        if (substr($response, 0, 3) !== '250') {
            $write('RSET');
            $read();
            fclose($fp);
            return false;
        }

        // DATA
        fputs($fp, "DATA\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '354') {
            fclose($fp);
            return false;
        }

        // Email headers ve body
        $encodedFromName = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
        $headers = "From: $encodedFromName <$envelopeFrom>\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $host . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Four Kampüs Email Processor\r\n";
        $headers .= "\r\n";

        fputs($fp, $headers . $message . "\r\n.\r\n");
        $response = fgets($fp, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($fp);
            return false;
        }

        // QUIT
        fputs($fp, "QUIT\r\n");
        fclose($fp);
        return true;

    } catch (Exception $e) {
        error_log("send_smtp_mail error: " . $e->getMessage());
        return false;
    }
}

// Rate limiting kontrolü
function check_rate_limit_queue($db, $club_id, $limit = 150) {
    $hour = date('Y-m-d H:00:00');
    $stmt = $db->prepare("SELECT action_count FROM rate_limits WHERE club_id = ? AND action_type = 'email' AND hour_timestamp = ?");
    $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $hour, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    $count = $row ? (int)$row['action_count'] : 0;
    return $count < $limit;
}

function increment_rate_limit_queue($db, $club_id) {
    $hour = date('Y-m-d H:00:00');
    // Önce var mı kontrol et
    $stmt = $db->prepare("SELECT action_count FROM rate_limits WHERE club_id = ? AND action_type = 'email' AND hour_timestamp = ?");
    $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $hour, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        // Güncelle
        $stmt = $db->prepare("UPDATE rate_limits SET action_count = action_count + 1 WHERE club_id = ? AND action_type = 'email' AND hour_timestamp = ?");
        $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $hour, SQLITE3_TEXT);
        $stmt->execute();
    } else {
        // Yeni ekle
        $stmt = $db->prepare("INSERT INTO rate_limits (club_id, action_type, action_count, hour_timestamp) VALUES (?, 'email', 1, ?)");
        $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $hour, SQLITE3_TEXT);
        $stmt->execute();
    }
}

// send_smtp_mail_batch fonksiyonu (worker script için optimize edilmiş)
function send_smtp_mail_batch($recipients, $subject, $message, $from_name, $from_email, $config = []) {
    $sent_count = 0;
    $failed_count = 0;
    $success_ids = [];
    $failed_ids = [];
    $success_emails = [];
    $failed_emails = [];
    
    $recipient_entries = [];
    foreach ($recipients as $recipient) {
        if (is_array($recipient)) {
            $email = $recipient['email'] ?? $recipient['recipient_email'] ?? null;
            $recipient_id = isset($recipient['id']) ? (int)$recipient['id'] : null;
            $custom_subject = isset($recipient['subject']) && $recipient['subject'] !== '' ? $recipient['subject'] : null;
            $custom_message = isset($recipient['message']) && $recipient['message'] !== '' ? $recipient['message'] : null;
        } else {
            $email = $recipient;
            $recipient_id = null;
            $custom_subject = null;
            $custom_message = null;
        }
        
        $recipient_entries[] = [
            'email' => $email,
            'id' => $recipient_id,
            'subject' => $custom_subject,
            'message' => $custom_message
        ];
    }
    
    if (empty($recipient_entries)) {
        return [
            'sent' => 0,
            'failed' => 0,
            'success_recipients' => [],
            'failed_recipients' => [],
            'success_ids' => [],
            'failed_ids' => [],
        ];
    }
    
    $recipient_total = count($recipient_entries);
    $all_emails = [];
    $all_ids = [];
    foreach ($recipient_entries as $entry) {
        if (!empty($entry['email'])) {
            $all_emails[] = $entry['email'];
        }
        if (!empty($entry['id'])) {
            $all_ids[] = (int)$entry['id'];
        }
    }
    
    $buildEarlyFailure = function() use ($recipient_total, $all_emails, $all_ids) {
        return [
            'sent' => 0,
            'failed' => $recipient_total,
            'success_recipients' => [],
            'failed_recipients' => $all_emails,
            'success_ids' => [],
            'failed_ids' => $all_ids,
        ];
    };
    
    try {
        $host = $config['host'] ?? $config['smtp_host'] ?? 'ms8.guzel.net.tr';
        $port = (int)($config['port'] ?? $config['smtp_port'] ?? 587);
        $secure = strtolower($config['secure'] ?? $config['smtp_secure'] ?? 'tls');
        $username = $config['username'] ?? $config['smtp_username'] ?? '';
        $password = $config['password'] ?? $config['smtp_password'] ?? '';

        if (!$host || !$port || !$username || !$password) {
            error_log('SMTP config eksik (batch): host=' . ($host ?: 'EMPTY') . ', port=' . ($port ?: 'EMPTY'));
            return $buildEarlyFailure();
        }

        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $timeout = 30;

        // SSL verification - Production'da açık olmalı
        $ssl_verify = !isProduction(); // Development'ta kapalı, production'da açık
        $fp = @stream_socket_client(($transport ?: '') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, stream_context_create([
            'ssl' => [
                'verify_peer' => $ssl_verify,
                'verify_peer_name' => $ssl_verify,
                'allow_self_signed' => !$ssl_verify,
            ],
        ]));
        
        if (!$fp) {
            error_log("SMTP bağlanamadı (batch): $errstr ($errno)");
            return $buildEarlyFailure();
        }

        $read = function() use ($fp) {
            $data = '';
            while (!feof($fp)) {
                $str = fgets($fp, 515);
                if ($str === false) break;
                $data .= $str;
                if (strlen($str) >= 4 && substr($str, 3, 1) === ' ') break;
            }
            return $data;
        };

        $write = function($cmd) use ($fp) {
            fputs($fp, $cmd . "\r\n");
        };

        // SMTP handshake
        $read(); // banner
        $write('EHLO localhost');
        $ehlo = $read();
        if (strpos($ehlo, '250') !== 0) {
            error_log('SMTP EHLO başarısız: ' . trim($ehlo));
            fclose($fp);
            return $buildEarlyFailure();
        }

        if ($secure === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $resp = $read();
            if (strpos($resp, '220') !== 0) {
                error_log('STARTTLS başarısız: ' . $resp);
                fclose($fp);
                return $buildEarlyFailure();
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('TLS şifreleme açılamadı');
                fclose($fp);
                return $buildEarlyFailure();
            }
            $write('EHLO localhost');
            $ehlo2 = $read();
            if (strpos($ehlo2, '250') !== 0) {
                error_log('SMTP EHLO (TLS sonrası) başarısız: ' . trim($ehlo2));
                fclose($fp);
                return $buildEarlyFailure();
            }
        }

        // Authentication
        $write('AUTH LOGIN');
        $auth1 = $read();
        if (strpos($auth1, '334') !== 0) {
            error_log('SMTP AUTH aşaması 1 başarısız: ' . trim($auth1));
            fclose($fp);
            return $buildEarlyFailure();
        }
        $write(base64_encode($username));
        $auth2 = $read();
        if (strpos($auth2, '334') !== 0) {
            error_log('SMTP AUTH aşaması 2 başarısız: ' . trim($auth2));
            fclose($fp);
            return $buildEarlyFailure();
        }
        $write(base64_encode($password));
        $authResp = $read();
        if (strpos($authResp, '235') !== 0) {
            error_log('SMTP kimlik doğrulama başarısız: ' . $authResp);
            fclose($fp);
            return $buildEarlyFailure();
        }

        // MAIL FROM her zaman sunucu kullanıcı adı ile aynı olmalı (Guzel Hosting vb. için)
        $envelopeFrom = $username;
        
        foreach ($recipient_entries as $entry) {
            $to = $entry['email'];
            $recipient_id = $entry['id'];
            if (empty($to)) {
                $failed_count++;
                if ($recipient_id) {
                    $failed_ids[] = $recipient_id;
                }
                continue;
            }
            
            $individual_subject = $entry['subject'] ?? $subject;
            $individual_message = $entry['message'] ?? $message;
            $is_html = (strip_tags($individual_message) !== $individual_message);
            $message_content = $is_html ? $individual_message : nl2br(htmlspecialchars($individual_message));
            $html_template = "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>" . $message_content . "</body></html>";

            // SMTP Dot-stuffing ve line normalization
            $html_template = str_replace(["\r\n", "\r", "\n"], "\n", $html_template);
            $lines = explode("\n", $html_template);
            foreach ($lines as &$line) {
                if (strpos($line, '.') === 0) {
                    $line = '.' . $line;
                }
            }
            $html_template = implode("\r\n", $lines);

            try {
                $write('MAIL FROM:<' . $envelopeFrom . '>');
                $mf = $read();
                if (strpos($mf, '250') !== 0) {
                    error_log('MAIL FROM reddedildi: ' . trim($mf) . ' for ' . $to);
                    $write('RSET');
                    $read();
                    $failed_count++;
                    $failed_emails[] = $to;
                    if ($recipient_id) {
                        $failed_ids[] = $recipient_id;
                    }
                    continue;
                }
                
                $write('RCPT TO:<' . $to . '>');
                $rc = $read();
                if (strpos($rc, '250') !== 0 && strpos($rc, '251') !== 0) {
                    error_log('RCPT TO reddedildi: ' . trim($rc) . ' Alıcı: ' . $to);
                    $write('RSET');
                    $read();
                    $failed_count++;
                    $failed_emails[] = $to;
                    if ($recipient_id) {
                        $failed_ids[] = $recipient_id;
                    }
                    continue;
                }
                
                $write('DATA');
                $dt = $read();
                if (strpos($dt, '354') !== 0) {
                    error_log('DATA kabul edilmedi: ' . trim($dt) . ' for ' . $to);
                    $write('RSET');
                    $read();
                    $failed_count++;
                    $failed_emails[] = $to;
                    if ($recipient_id) {
                        $failed_ids[] = $recipient_id;
                    }
                    continue;
                }

                $headers = [];
                $encodedFromName = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
                $headers[] = 'From: ' . sprintf('%s <%s>', $encodedFromName, $envelopeFrom);
                $headers[] = 'Reply-To: ' . $from_email;
                $headers[] = 'To: ' . $to;
                $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($individual_subject) . '?=';
                $headers[] = 'Date: ' . date('r');
                $headers[] = 'Message-ID: <' . md5(uniqid(microtime(), true)) . '@' . $host . '>';
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $headers[] = 'X-Mailer: Four Kampüs Email Processor';

                $data = implode("\r\n", $headers) . "\r\n\r\n" . $html_template . "\r\n.";
                $write($data);
                $resp = $read();
                
                if (strpos($resp, '250') === 0) {
                    $sent_count++;
                    $success_emails[] = $to;
                    if ($recipient_id) {
                        $success_ids[] = $recipient_id;
                    }
                } else {
                    error_log('Mail gönderilemedi: ' . trim($resp) . ' for ' . $to);
                    $failed_count++;
                    $failed_emails[] = $to;
                    if ($recipient_id) {
                        $failed_ids[] = $recipient_id;
                    }
                }
            } catch (Exception $e) {
                error_log('Mail gönderme hatası (batch): ' . $e->getMessage() . ' for ' . $to);
                $failed_count++;
                $failed_emails[] = $to;
                if ($recipient_id) {
                    $failed_ids[] = $recipient_id;
                }
            }
        }
        
        $write('QUIT');
        $read();
        fclose($fp);
        
        return [
            'sent' => $sent_count,
            'failed' => $failed_count,
            'success_recipients' => $success_emails,
            'failed_recipients' => $failed_emails,
            'success_ids' => $success_ids,
            'failed_ids' => $failed_ids,
        ];
        
    } catch (Exception $e) {
        error_log('send_smtp_mail_batch exception: ' . $e->getMessage());
        if (isset($fp) && is_resource($fp)) {
            @fclose($fp);
        }
        
        foreach ($recipient_entries as $entry) {
            $to = $entry['email'];
            $recipient_id = $entry['id'];
            if ($to && !in_array($to, $success_emails, true) && !in_array($to, $failed_emails, true)) {
                $failed_emails[] = $to;
                $failed_count++;
            }
            if ($recipient_id && !in_array($recipient_id, $success_ids, true) && !in_array($recipient_id, $failed_ids, true)) {
                $failed_ids[] = $recipient_id;
            }
        }
        
        return [
            'sent' => $sent_count,
            'failed' => $failed_count,
            'success_recipients' => $success_emails,
            'failed_recipients' => $failed_emails,
            'success_ids' => $success_ids,
            'failed_ids' => $failed_ids,
        ];
    }
}

// Ana işlem döngüsü - BATCH PROCESSING ile optimize edildi
$max_emails_per_run = 100; // Her çalıştırmada maksimum gönderilecek mail sayısı (artırıldı)
$batch_size = 20; // Her batch'te kaç mail gönderilecek (tek SMTP bağlantısı ile)
$processed = 0;
$smtp_settings = get_smtp_settings($db, $club_id);

// Bekleyen kampanyaları başlat
$stmt = $db->prepare("UPDATE email_campaigns SET status = 'processing', started_at = datetime('now') WHERE club_id = ? AND status = 'pending' AND id IN (SELECT DISTINCT campaign_id FROM email_queue WHERE status = 'pending' AND club_id = ? LIMIT 1)");
$stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
$stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
$stmt->execute();

// BATCH PROCESSING: Aynı kampanyadan mailleri grupla ve tek bağlantı ile gönder
while ($processed < $max_emails_per_run) {
    // Rate limit kontrolü
    if (!check_rate_limit_queue($db, $club_id, 150)) {
        error_log("Email Queue Processor: Rate limit reached for club $club_id");
        break;
    }
    
    // Bir kampanya al (aynı subject/message ile)
    $stmt = $db->prepare("SELECT campaign_id, subject, message, from_name, from_email FROM email_queue WHERE club_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
    $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $first_email = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$first_email) {
        // Kuyrukta mail yok, kampanyaları tamamla olarak işaretle
        $stmt = $db->prepare("UPDATE email_campaigns SET status = 'completed', completed_at = datetime('now') WHERE club_id = ? AND status = 'processing' AND id NOT IN (SELECT DISTINCT campaign_id FROM email_queue WHERE status = 'pending' AND club_id = ?)");
        $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
        $stmt->execute();
        break;
    }
    
    $campaign_id = $first_email['campaign_id'];
    $subject = $first_email['subject'];
    $message = $first_email['message'];
    $from_name = $first_email['from_name'];
    $from_email = $first_email['from_email'];
    
    // Aynı kampanyadan batch_size kadar mail al
    $stmt = $db->prepare("SELECT id, recipient_email, subject, message, recipient_name FROM email_queue WHERE club_id = ? AND campaign_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT ?");
    $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $campaign_id, SQLITE3_INTEGER);
    $stmt->bindValue(3, $batch_size, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $batch_emails = [];
    $email_ids = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $batch_emails[] = [
            'id' => $row['id'],
            'email' => $row['recipient_email'],
            'subject' => $row['subject'],
            'message' => $row['message'],
            'recipient_name' => $row['recipient_name']
        ];
        $email_ids[] = $row['id'];
    }
    
    if (empty($batch_emails)) {
        break;
    }
    
    // Status'u 'sending' yap
    $placeholders = implode(',', array_fill(0, count($email_ids), '?'));
    $stmt = $db->prepare("UPDATE email_queue SET status = 'sending', attempts = attempts + 1 WHERE id IN ($placeholders)");
    for ($i = 0; $i < count($email_ids); $i++) {
        $stmt->bindValue($i + 1, $email_ids[$i], SQLITE3_INTEGER);
    }
    $stmt->execute();
    
    // BATCH GÖNDERİM: Tek SMTP bağlantısı ile tüm mailleri gönder
    $result = send_smtp_mail_batch(
        $batch_emails,
        $subject,
        $message,
        $from_name,
        $from_email,
        [
            'host' => $smtp_settings['smtp_host'] ?? 'smtp.gmail.com',
            'port' => (int)($smtp_settings['smtp_port'] ?? 587),
            'secure' => strtolower($smtp_settings['smtp_secure'] ?? 'tls'),
            'username' => $smtp_settings['smtp_username'],
            'password' => $smtp_settings['smtp_password'],
        ]
    );
    
    $success_ids = $result['success_ids'] ?? [];
    $failed_ids = $result['failed_ids'] ?? [];
    $sent_count = count($success_ids);
    $failed_count = count($failed_ids);
    
    // Başarılı mailleri işaretle
    if ($sent_count > 0) {
        $placeholders = implode(',', array_fill(0, count($success_ids), '?'));
        $stmt = $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = datetime('now') WHERE id IN ($placeholders)");
        for ($i = 0; $i < count($success_ids); $i++) {
            $stmt->bindValue($i + 1, $success_ids[$i], SQLITE3_INTEGER);
        }
        $stmt->execute();
        
        // Kampanya sayacını güncelle
        $stmt = $db->prepare("UPDATE email_campaigns SET sent_count = sent_count + ? WHERE id = ?");
        $stmt->bindValue(1, $sent_count, SQLITE3_INTEGER);
        $stmt->bindValue(2, $campaign_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Rate limit sayacını artır
        for ($i = 0; $i < $sent_count; $i++) {
            increment_rate_limit_queue($db, $club_id);
        }
        
        $processed += $sent_count;
    }
    
    // Başarısız mailleri işaretle
    if ($failed_count > 0) {
        foreach ($failed_ids as $email_id) {
            // Attempt sayısını kontrol et
            $stmt = $db->prepare("SELECT attempts FROM email_queue WHERE id = ?");
            $stmt->bindValue(1, $email_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $attempts = $row ? (int)$row['attempts'] : 0;
            
            if ($attempts >= 3) {
                // 3 denemeden sonra failed olarak işaretle
                $stmt = $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
                $stmt->bindValue(1, "SMTP gönderim hatası", SQLITE3_TEXT);
                $stmt->bindValue(2, $email_id, SQLITE3_INTEGER);
                $stmt->execute();
                
                // Kampanya failed sayacını güncelle
                $stmt = $db->prepare("UPDATE email_campaigns SET failed_count = failed_count + 1 WHERE id = ?");
                $stmt->bindValue(1, $campaign_id, SQLITE3_INTEGER);
                $stmt->execute();
            } else {
                // Tekrar dene
                $stmt = $db->prepare("UPDATE email_queue SET status = 'pending', error_message = ? WHERE id = ?");
                $stmt->bindValue(1, "SMTP gönderim hatası", SQLITE3_TEXT);
                $stmt->bindValue(2, $email_id, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
    }
    
    // Çok kısa bir bekleme (SMTP server'a yük bindirmemek için)
    usleep(50000); // 0.05 saniye (yarıya indirildi)
}

// Eğer web'den çağrıldıysa JSON döndür
if (isset($_GET['run_as_worker'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'message' => "$processed e-posta işlendi"
    ]);
}

exit(0);

