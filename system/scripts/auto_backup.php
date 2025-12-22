<?php
/**
 * Otomatik Yedekleme Script'i
 * Cron job ile çalıştırılabilir
 * 
 * Kullanım:
 * php auto_backup.php [community_path]
 * 
 * Örnek cron job (her gün saat 02:00'de):
 * 0 2 * * * /usr/bin/php /path/to/unipanel/system/scripts/auto_backup.php
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Autoload
require_once __DIR__ . '/../../lib/autoload.php';

require_once __DIR__ . '/security_helper.php';

// Community path argümanı
$community_path = $argv[1] ?? null;

if (!$community_path) {
    // Tüm toplulukları yedekle
    $communities_dir = __DIR__ . '/../../communities';
    if (is_dir($communities_dir)) {
        $communities = glob($communities_dir . '/*', GLOB_ONLYDIR);
        foreach ($communities as $community_dir) {
            $sanitized_path = sanitizePath($community_dir);
            if ($sanitized_path) {
                $community_name = basename($sanitized_path);
                process_community_backup($sanitized_path);
            }
        }
    }
} else {
    // Belirli bir topluluğu yedekle - Path sanitization
    $sanitized_path = sanitizePath($community_path);
    if ($sanitized_path && is_dir($sanitized_path)) {
        process_community_backup($sanitized_path);
    } else {
        handleError("Community path not found or invalid: $community_path");
        exit(1);
    }
}

function process_community_backup($community_path) {
    try {
        $index_file = $community_path . '/index.php';
        if (!file_exists($index_file)) {
            return;
        }
        
        // Community'nin template_index.php'yi include etmesi gerekiyor
        // Bu yüzden direkt backup fonksiyonunu çağırmak yerine
        // index.php'yi include edip fonksiyonu çağıracağız
        
        // DB_PATH ve CLUB_ID'yi belirle
        $db_file = $community_path . '/unipanel.sqlite';
        if (!file_exists($db_file)) {
            error_log("Database not found for: $community_path");
            return;
        }
        
        // Backup klasörü oluştur
        $backup_dir = $community_path . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Ayarları kontrol et
        $db = new SQLite3($db_file);
        $club_id_result = $db->querySingle("SELECT club_id FROM settings WHERE club_id IS NOT NULL AND club_id > 0 LIMIT 1");
        $club_id = $club_id_result ? (int)$club_id_result : 1;
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_backup_enabled' AND club_id = :club_id");
        $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $auto_backup_enabled = ($row && $row['setting_value'] === '1');
        
        if (!$auto_backup_enabled) {
            $db->close();
            return; // Otomatik yedekleme kapalı
        }
        
        // Son yedekleme zamanını kontrol et
        $frequency = 'daily'; // Varsayılan
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_backup_frequency' AND club_id = :club_id");
        $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $frequency = $row['setting_value'];
        }
        
        // Son yedekleme zamanını kontrol et
        $last_backup_file = null;
        $backup_files = glob($backup_dir . '/backup_*.sqlite');
        if (!empty($backup_files)) {
            usort($backup_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $last_backup_file = $backup_files[0];
        }
        
        // Sıklık kontrolü
        $should_backup = false;
        if (!$last_backup_file) {
            $should_backup = true; // İlk yedekleme
        } else {
            $last_backup_time = filemtime($last_backup_file);
            $time_diff = time() - $last_backup_time;
            
            switch ($frequency) {
                case 'hourly':
                    $should_backup = ($time_diff >= 3600); // 1 saat
                    break;
                case 'daily':
                    $should_backup = ($time_diff >= 86400); // 24 saat
                    break;
                case 'weekly':
                    $should_backup = ($time_diff >= 604800); // 7 gün
                    break;
                case 'monthly':
                    $should_backup = ($time_diff >= 2592000); // 30 gün
                    break;
                default:
                    $should_backup = ($time_diff >= 86400); // Varsayılan: günlük
            }
        }
        
        if (!$should_backup) {
            $db->close();
            return; // Henüz yedekleme zamanı gelmedi
        }
        
        // Yedekleme yap
        $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . $club_id . '.sqlite';
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        if (copy($db_file, $backup_path)) {
            // Eski backupları temizle (30 günden eski)
            $cutoff_time = time() - (30 * 24 * 60 * 60);
            foreach ($backup_files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    unlink($file);
                }
            }
            
            $backup_size = round(filesize($backup_path) / 1024, 2);
            error_log("Auto backup created: $backup_filename ($backup_size KB) for " . basename($community_path));
            
            // Bildirim gönder
            $notify_email = '';
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_backup_notify_email' AND club_id = :club_id");
            $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && filter_var($row['setting_value'], FILTER_VALIDATE_EMAIL)) {
                $notify_email = $row['setting_value'];
            }
            
            if ($notify_email) {
                send_backup_notification_email($db, $club_id, $notify_email, $backup_filename, $backup_size, $community_path);
            }
            
            auto_backup_record_result($db, $club_id, 'success', '');
        } else {
            error_log("Failed to create backup for: " . basename($community_path));
            auto_backup_record_result($db, $club_id, 'failed', 'Backup dosyası kopyalanamadı');
        }
        
        $db->close();
        
    } catch (Exception $e) {
        handleError("Auto backup error for " . basename($community_path), $e);
        if (isset($db) && $db instanceof SQLite3) {
            auto_backup_record_result($db, $club_id ?? 1, 'failed', isProduction() ? 'Backup failed' : $e->getMessage());
        }
    }
}

function send_backup_notification_email($db, $club_id, $to_email, $backup_file, $backup_size, $community_path) {
    try {
        $community_name = basename($community_path);
        // SMTP ayarlarını al
        $smtp_settings = [];
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE club_id = :club_id AND setting_key LIKE 'smtp_%'");
        $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $smtp_settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Club name
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE club_id = :club_id AND setting_key = 'club_name'");
        $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $club_name = $row ? $row['setting_value'] : $community_name;
        
        if (empty($smtp_settings['smtp_username']) || empty($smtp_settings['smtp_password'])) {
            return false; // SMTP ayarları yok
        }
        
        $subject = '[' . $club_name . '] Otomatik Yedekleme Tamamlandı';
        $message = '<html><body>';
        $message .= '<h2>Veritabanı Yedekleme Bildirimi</h2>';
        $message .= '<p>Merhaba,</p>';
        $message .= '<p><strong>' . htmlspecialchars($club_name) . '</strong> topluluğunun veritabanı yedeği otomatik olarak oluşturuldu.</p>';
        $message .= '<div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p><strong>Yedek Dosyası:</strong> ' . htmlspecialchars($backup_file) . '</p>';
        $message .= '<p><strong>Dosya Boyutu:</strong> ' . htmlspecialchars($backup_size) . ' KB</p>';
        $message .= '<p><strong>Oluşturulma Tarihi:</strong> ' . date('d.m.Y H:i:s') . '</p>';
        $message .= '</div>';
        $message .= '<p>Bu yedek 30 gün boyunca saklanacaktır.</p>';
        $message .= '<p>İyi çalışmalar,<br>UniPanel Otomatik Yedekleme Sistemi</p>';
        $message .= '</body></html>';
        
        $from_email = $smtp_settings['smtp_from_email'] ?? $smtp_settings['smtp_username'];
        $from_name = $smtp_settings['smtp_from_name'] ?? $club_name;
        
        return auto_backup_send_mail($to_email, $subject, $message, $from_name, $from_email, [
            'host' => $smtp_settings['smtp_host'] ?? 'smtp.gmail.com',
            'port' => (int)($smtp_settings['smtp_port'] ?? 587),
            'secure' => strtolower($smtp_settings['smtp_secure'] ?? 'tls'),
            'username' => $smtp_settings['smtp_username'],
            'password' => $smtp_settings['smtp_password'],
        ]);
    } catch (Exception $e) {
        error_log("Backup notification email error: " . $e->getMessage());
        return false;
    }
}

function auto_backup_send_mail($to, $subject, $message, $from_name, $from_email, $config = []) {
    try {
        $host = $config['host'] ?? 'ms8.guzel.net.tr';
        $port = (int)($config['port'] ?? 587);
        $secure = strtolower($config['secure'] ?? 'tls');
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (!$host || !$port || !$username || !$password) {
            return false;
        }

        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $timeout = 30;

        // SSL verification - Production'da açık olmalı
        $ssl_verify = !isProduction(); // Development'ta kapalı, production'da açık
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $ssl_verify,
                'verify_peer_name' => $ssl_verify,
                'allow_self_signed' => !$ssl_verify,
            ],
        ]);

        $fp = @stream_socket_client(($transport ?: '') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$fp) {
            error_log("auto_backup_send_mail: SMTP bağlantısı kurulamadı: $errstr ($errno)");
            return false;
        }

        $read = function() use ($fp) {
            $data = '';
            while ($str = fgets($fp, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === ' ') {
                    break;
                }
            }
            return $data;
        };

        $write = function($cmd) use ($fp) {
            fputs($fp, $cmd . "\r\n");
        };

        $read(); // banner
        $write('EHLO localhost');
        $ehlo = $read();
        if (strpos($ehlo, '250') !== 0) {
            fclose($fp);
            return false;
        }

        if ($secure === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $resp = $read();
            if (strpos($resp, '220') !== 0) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write('EHLO localhost');
            $ehlo = $read();
            if (strpos($ehlo, '250') !== 0) {
                fclose($fp);
                return false;
            }
        }

        $write('AUTH LOGIN');
        if (strpos($read(), '334') !== 0) {
            fclose($fp);
            return false;
        }

        $write(base64_encode($username));
        if (strpos($read(), '334') !== 0) {
            fclose($fp);
            return false;
        }

        $write(base64_encode($password));
        if (strpos($read(), '235') !== 0) {
            fclose($fp);
            return false;
        }

        $envelopeFrom = $username;
        $write('MAIL FROM:<' . $envelopeFrom . '>');
        $mf = $read();
        if (strpos($mf, '250') !== 0) {
            $write('RSET');
            $read();
            fclose($fp);
            return false;
        }

        $write('RCPT TO:<' . $to . '>');
        $rc = $read();
        if (strpos($rc, '250') !== 0 && strpos($rc, '251') !== 0) {
            $write('RSET');
            $read();
            fclose($fp);
            return false;
        }

        $write('DATA');
        $dt = $read();
        if (strpos($dt, '354') !== 0) {
            $write('RSET');
            $read();
            fclose($fp);
            return false;
        }

        $encodedFromName = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
        $headers = "From: $encodedFromName <$envelopeFrom>\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $host . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Four Kampüs Backup System\r\n";
        $headers .= "\r\n";

        fputs($fp, $headers . $message . "\r\n.\r\n");
        if (strpos($read(), '250') !== 0) {
            fclose($fp);
            return false;
        }

        $write('QUIT');
        fclose($fp);
        return true;
    } catch (Exception $e) {
        error_log("auto_backup_send_mail hata: " . $e->getMessage());
        return false;
    }
}

function auto_backup_record_result(SQLite3 $db, int $club_id, string $status, string $message = ''): void {
    $now = date('Y-m-d H:i:s');
    auto_backup_save_setting($db, $club_id, 'auto_backup_last_run', $now);
    auto_backup_save_setting($db, $club_id, 'auto_backup_last_status', $status);
    auto_backup_save_setting($db, $club_id, 'auto_backup_last_error', $status === 'success' ? '' : $message);
}

function auto_backup_save_setting(SQLite3 $db, int $club_id, string $key, string $value): void {
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (club_id, setting_key, setting_value) VALUES (:club_id, :key, :value)");
    $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    $stmt->execute();
}

