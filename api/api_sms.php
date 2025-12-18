<?php
/**
 * Direct SMS API - Basit ve hızlı SMS gönderimi
 */

// CORS ve headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Tüm hataları yakala
error_reporting(0);
ini_set('display_errors', 0);

try {
    // POST kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }
    
    // SuperAdmin config'i yükle
    require_once __DIR__ . '/superadmin/config.php';
    
    $username = superadmin_config_env('NETGSM_USER');
    $password = superadmin_config_env('NETGSM_PASS'); 
    $header = superadmin_config_env('NETGSM_HEADER');
    
    // Parametreleri al
    $phones = $_POST['selected_phones'] ?? $_POST['phone'] ?? '';
    if (is_array($phones)) {
        $phones = implode(',', $phones);
    }
    $phones = trim($phones);
    
    $message = trim($_POST['sms_body'] ?? $_POST['message'] ?? '');
    
    // Validasyon
    if (empty($phones)) {
        throw new Exception('Telefon numarası girilmedi');
    }
    if (empty($message)) {
        throw new Exception('Mesaj içeriği boş');
    }
    if (empty($username) || empty($password)) {
        throw new Exception('NetGSM ayarları eksik. Lütfen superadmin/config.php dosyasını kontrol edin.');
    }
    
    // Telefon numarasını normalize et
    $phone = preg_replace('/[^0-9]/', '', $phones);
    if (strlen($phone) === 10) {
        $phone = '90' . $phone;
    } elseif (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = '9' . $phone;
    }
    
    // Header'ı düzelt (başında 0 varsa kaldır)
    if (!empty($header) && $header[0] === '0') {
        $header = substr($header, 1);
    }
    
    // NetGSM API çağrısı
    $url = 'http://api.netgsm.com.tr/sms/send/get?' . http_build_query([
        'usercode' => $username,
        'password' => $password,
        'gsmno' => $phone,
        'message' => $message,
        'msgheader' => $header ?: '8503022568',
        'language' => 'TR'
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Bağlantı hatası: ' . $curlError);
    }
    
    // Yanıtı kontrol et
    $response = trim($response);
    
    if (strpos($response, '00') === 0) {
        // Başarılı - 00 ile başlıyorsa
        echo json_encode([
            'success' => true, 
            'message' => 'SMS başarıyla gönderildi! ✓',
            'campaign_id' => trim(substr($response, 3))
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Hata kodu
        $errorCodes = [
            '20' => 'Mesaj metni boş',
            '30' => 'Geçersiz kullanıcı adı veya şifre',
            '40' => 'Mesaj başlığı tanımsız',
            '50' => 'Geçersiz telefon numarası',
            '51' => 'Telefon numarası engelli',
            '70' => 'Hatalı parametre',
            '85' => 'Mükerrer gönderim'
        ];
        $code = substr($response, 0, 2);
        $errorMsg = $errorCodes[$code] ?? "NetGSM API hatası: $response";
        echo json_encode(['success' => false, 'message' => $errorMsg], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
