<?php
/**
 * Paket Bilgileri API
 * Marketing sayfası için paket bilgilerini döndürür
 */

// Hızlı yanıt için output buffering ve timeout ayarları
set_time_limit(3); // Maksimum 3 saniye
ini_set('max_execution_time', 3);

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function sendResponse($success, $data = null, $message = null, $error = null) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // SubscriptionManager'ı yükle
    $subscriptionManagerPath = __DIR__ . '/../lib/payment/SubscriptionManager.php';
    if (!file_exists($subscriptionManagerPath)) {
        throw new Exception('SubscriptionManager file not found');
    }
    
    require_once $subscriptionManagerPath;
    
    if (!class_exists('UniPanel\Payment\SubscriptionManager')) {
        throw new Exception('SubscriptionManager class not found');
    }
    
    // Paket bilgilerini al (hızlı static metodlar)
    $allPackages = \UniPanel\Payment\SubscriptionManager::getPackagePrices();
    $packagesByTier = \UniPanel\Payment\SubscriptionManager::getPackagesByTier();
    $isSeptemberPromo = \UniPanel\Payment\SubscriptionManager::isSeptemberPromotion();
    
    // Her tier için 12 aylık paketi al (marketing için)
    $packages = [
        'standard' => null,
        'professional' => null,
        'business' => null
    ];
    
    // Standart paket (her zaman ücretsiz)
    if (isset($packagesByTier['standard'][0])) {
        $packages['standard'] = $packagesByTier['standard'][0];
    }
    
    // Profesyonel paket (12 aylık)
    foreach ($packagesByTier['professional'] as $pkg) {
        if ($pkg['months'] == 12) {
            $packages['professional'] = $pkg;
            break;
        }
    }
    // 12 aylık yoksa ilkini al
    if (!$packages['professional'] && isset($packagesByTier['professional'][0])) {
        $packages['professional'] = $packagesByTier['professional'][0];
    }
    
    // Business paket (12 aylık)
    foreach ($packagesByTier['business'] as $pkg) {
        if ($pkg['months'] == 12) {
            $packages['business'] = $pkg;
            break;
        }
    }
    // 12 aylık yoksa ilkini al
    if (!$packages['business'] && isset($packagesByTier['business'][0])) {
        $packages['business'] = $packagesByTier['business'][0];
    }
    
    sendResponse(true, [
        'packages' => $packages,
        'is_september_promo' => $isSeptemberPromo,
        'promo_message' => $isSeptemberPromo ? 'Eylül ayı özel kampanyası: Tüm paketler bu ay ücretsiz!' : null
    ], 'Paket bilgileri başarıyla yüklendi');
    
} catch (Exception $e) {
    error_log("Packages API error: " . $e->getMessage());
    sendResponse(false, null, null, 'Paket bilgileri yüklenirken bir hata oluştu: ' . $e->getMessage());
}

