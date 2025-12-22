<?php
/**
 * Direct SMS API - Robust SMS Sending System
 * 
 * Özellikler:
 * - Çoklu telefon numarası desteği
 * - Kapsamlı hata yönetimi
 * - Otomatik telefon formatı düzeltme
 * - Retry mekanizması
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
 * Telefon numarasını normalize et
 */
function normalizePhoneNumber($phone) {
    // Sadece rakamları al
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Boş ise null döndür
    if (empty($phone)) {
        return null;
    }
    
    // Formatları düzelt
    if (strlen($phone) === 10 && $phone[0] === '5') {
        // 5XX XXX XX XX -> 905XXXXXXXXX
        $phone = '90' . $phone;
    } elseif (strlen($phone) === 11 && $phone[0] === '0') {
        // 05XX XXX XX XX -> 905XXXXXXXXX
        $phone = '9' . $phone;
    } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '90') {
        // Zaten doğru format
    } elseif (strlen($phone) === 13 && substr($phone, 0, 3) === '+90') {
        // +90 prefix varsa kaldır
        $phone = substr($phone, 1);
    }
    
    // Geçerli Türkiye numarası mı?
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '90') {
        return $phone;
    }
    
    return null;
}

/**
 * SMS gönder (retry mekanizmasıyla)
 */
function sendSms($username, $password, $header, $phone, $message, $maxRetries = 2) {
    $url = 'http://api.netgsm.com.tr/sms/send/get?' . http_build_query([
        'usercode' => $username,
        'password' => $password,
        'gsmno' => $phone,
        'message' => $message,
        'msgheader' => $header ?: '8503022568',
        'language' => 'TR'
    ]);
    
    $lastError = null;
    
    for ($retry = 0; $retry < $maxRetries; $retry++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        // cURL hatası yoksa döngüden çık
        if ($curlErrno === 0) {
            break;
        }
        
        $lastError = $curlError;
        
        // Son deneme değilse bekle ve tekrar dene
        if ($retry < $maxRetries - 1) {
            usleep(500000); // 0.5 saniye bekle
        }
    }
    
    if ($curlErrno !== 0) {
        return ['success' => false, 'error' => 'Bağlantı hatası: ' . $lastError];
    }
    
    $response = trim($response);
    
    // Yanıtı kontrol et
    if (strpos($response, '00') === 0) {
        return [
            'success' => true,
            'campaign_id' => trim(substr($response, 3))
        ];
    } else {
        $errorCodes = [
            '20' => 'Mesaj metni boş',
            '30' => 'Geçersiz kullanıcı adı veya şifre',
            '40' => 'Mesaj başlığı tanımsız',
            '50' => 'Geçersiz telefon numarası',
            '51' => 'Telefon numarası engelli',
            '60' => 'Abone değilsiniz',
            '70' => 'Hatalı parametre',
            '80' => 'Tarih hatalı',
            '85' => 'Mükerrer gönderim'
        ];
        $code = substr($response, 0, 2);
        return [
            'success' => false,
            'error' => $errorCodes[$code] ?? "NetGSM hatası (Kod: $code)"
        ];
    }
}

try {
    // POST kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }
    
    // SuperAdmin config'i yükle
    $configPath = __DIR__ . '/../superadmin/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Yapılandırma dosyası bulunamadı');
    }
    require_once $configPath;
    
    if (!function_exists('superadmin_config_env')) {
        throw new Exception('Yapılandırma fonksiyonu bulunamadı');
    }
    
    $username = superadmin_config_env('NETGSM_USER');
    $password = superadmin_config_env('NETGSM_PASS'); 
    $header = superadmin_config_env('NETGSM_HEADER');
    
    // Validasyon - Kimlik bilgileri
    if (empty($username) || empty($password)) {
        throw new Exception('NetGSM ayarları eksik. Lütfen SuperAdmin panelinden yapılandırın.');
    }
    
    // Header'ı düzelt (başında 0 varsa kaldır)
    if (!empty($header) && $header[0] === '0') {
        $header = substr($header, 1);
    }
    
    // Parametreleri al
    $rawPhones = $_POST['selected_phones'] ?? $_POST['phone'] ?? [];
    $message = trim($_POST['sms_body'] ?? $_POST['message'] ?? '');
    
    // Mesaj validasyonu
    if (empty($message)) {
        throw new Exception('Mesaj içeriği boş olamaz');
    }
    
    // Telefon numaralarını işle
    $phones = [];
    if (is_array($rawPhones)) {
        foreach ($rawPhones as $phone) {
            $normalized = normalizePhoneNumber($phone);
            if ($normalized) {
                $phones[] = $normalized;
            }
        }
    } else {
        // Virgülle ayrılmış string olabilir
        $phoneList = explode(',', $rawPhones);
        foreach ($phoneList as $phone) {
            $normalized = normalizePhoneNumber(trim($phone));
            if ($normalized) {
                $phones[] = $normalized;
            }
        }
    }
    
    // Unique telefon numaraları
    $phones = array_unique($phones);
    
    if (empty($phones)) {
        throw new Exception('Geçerli telefon numarası girilmedi');
    }
    
    // SMS gönder
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($phones as $phone) {
        $result = sendSms($username, $password, $header, $phone, $message);
        $results[] = [
            'phone' => $phone,
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'campaign_id' => $result['campaign_id'] ?? null
        ];
        
        if ($result['success']) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    // Sonuç mesajı
    $totalCount = count($phones);
    
    if ($successCount === $totalCount) {
        // Tüm SMS'ler başarılı
        $message = $totalCount === 1 
            ? 'SMS başarıyla gönderildi! ✓'
            : "$successCount SMS başarıyla gönderildi! ✓";
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'details' => [
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => 0
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($successCount > 0) {
        // Bazı SMS'ler başarılı, bazıları başarısız
        echo json_encode([
            'success' => true,
            'message' => "$successCount SMS gönderildi, $failCount başarısız oldu.",
            'details' => [
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => $failCount,
                'results' => $results
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Tüm SMS'ler başarısız
        $firstError = $results[0]['error'] ?? 'Bilinmeyen hata';
        echo json_encode([
            'success' => false,
            'message' => "SMS gönderilemedi: $firstError",
            'details' => [
                'total' => $totalCount,
                'success' => 0,
                'failed' => $failCount,
                'results' => $results
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
