<?php
/**
 * Ödeme E-posta Log Kontrol Scripti
 * Son e-posta gönderim loglarını gösterir
 */

$project_root = dirname(__DIR__);
$error_log = ini_get('error_log');

if (!$error_log || !file_exists($error_log)) {
    echo "Error log dosyası bulunamadı: " . ($error_log ?: 'tanımlı değil') . "\n";
    exit(1);
}

echo "=== ÖDEME E-POSTA LOG KONTROLÜ ===\n\n";
echo "Log dosyası: $error_log\n\n";

// Son 100 satırı oku
$lines = file($error_log);
$last_lines = array_slice($lines, -100);

// SMTP ve ödeme e-posta ile ilgili satırları filtrele
$relevant_lines = [];
foreach ($last_lines as $line) {
    if (stripos($line, 'SMTP') !== false || 
        stripos($line, 'payment') !== false || 
        stripos($line, 'ödeme') !== false ||
        stripos($line, 'mail') !== false ||
        stripos($line, 'email') !== false ||
        stripos($line, 'send_smtp_mail') !== false ||
        stripos($line, 'get_email_template') !== false) {
        $relevant_lines[] = trim($line);
    }
}

if (empty($relevant_lines)) {
    echo "Son 100 satırda SMTP veya ödeme e-posta ile ilgili log bulunamadı.\n";
    echo "\nSon 20 satır:\n";
    foreach (array_slice($last_lines, -20) as $line) {
        echo "  " . trim($line) . "\n";
    }
} else {
    echo "Bulunan ilgili loglar (" . count($relevant_lines) . " satır):\n\n";
    foreach ($relevant_lines as $line) {
        echo "  " . $line . "\n";
    }
}

echo "\n=== TAMAMLANDI ===\n";
