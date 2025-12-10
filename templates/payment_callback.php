<?php

use UniPanel\Payment\SubscriptionManager;
use UniPanel\Payment\IyzicoHelper;
/**
 * Iyzico Ödeme Callback Handler
 * Ödeme sonrası iyzico'dan gelen callback'i işler
 */

// Community bootstrap
require_once __DIR__ . '/partials/logging.php';
require_once __DIR__ . '/partials/security_headers.php';
require_once __DIR__ . '/partials/path_guard.php';
require_once __DIR__ . '/partials/schema_bootstrap.php';

$communitySlug = $_GET['community'] ?? '';
$communityBasePath = tpl_resolve_community_path($communitySlug);
if ($communityBasePath === null) {
    tpl_error_log('Payment callback blocked due to invalid community slug: ' . ($communitySlug ?: '[empty]'), 'warning');
    http_response_code(400);
    exit('Invalid community parameter');
}

$communityView = 'index';
require_once __DIR__ . '/../bootstrap/community_entry.php';

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/payment/SubscriptionManager.php';
require_once __DIR__ . '/../lib/payment/IyzicoHelper.php';

session_start();
set_security_headers();

function ensure_payment_callback_authorized(): void
{
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed = getenv('PAYMENT_CALLBACK_ALLOWED_IPS') ?? '195.142.107.0/24,127.0.0.1,::1';
    $authorized = false;
    foreach (array_filter(array_map('trim', explode(',', $allowed))) as $rule) {
        if ($rule === '') {
            continue;
        }
        if (strpos($rule, '/') !== false) {
            [$subnet, $mask] = explode('/', $rule, 2);
            if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $mask = (int)$mask;
                $ipLong = ip2long($clientIp);
                $subnetLong = ip2long($subnet);
                $maskLong = -1 << (32 - $mask);
                if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                    $authorized = true;
                    break;
                }
            }
        } elseif ($clientIp === $rule) {
            $authorized = true;
            break;
        }
    }

    $expectedToken = getenv('PAYMENT_CALLBACK_TOKEN') ?: '';
    $providedToken = $_GET['callback_token'] ?? ($_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '');
    $tokenOk = $expectedToken !== '' && hash_equals($expectedToken, (string)$providedToken);

    if (!$authorized || !$tokenOk) {
        tpl_error_log('Payment callback blocked: ip=' . $clientIp, 'warning');
        http_response_code(403);
        exit('Unauthorized callback');
    }
}

ensure_payment_callback_authorized();

$communityId = defined('COMMUNITY_ID') ? COMMUNITY_ID : basename(COMMUNITY_BASE_PATH);
$db = get_db();

$subscriptionManager = new SubscriptionManager($db, $communityId);
$iyzicoHelper = new IyzicoHelper();

// CSRF Token Kontrolü (Callback için)
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Iyzico'dan gelen token - Güvenlik: Input validation
$token = isset($_GET['token']) ? trim($_GET['token']) : null;
$paymentId = isset($_GET['paymentId']) ? trim($_GET['paymentId']) : null;
$payment_token = isset($_GET['payment_token']) ? trim($_GET['payment_token']) : null;

// Input sanitization - XSS koruması
$token = $token ? htmlspecialchars($token, ENT_QUOTES, 'UTF-8') : null;
$paymentId = $paymentId ? htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8') : null;
$payment_token = $payment_token ? htmlspecialchars($payment_token, ENT_QUOTES, 'UTF-8') : null;

// Güvenlik: IP ve User-Agent kontrolü
$current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// Rate limiting - Callback spam koruması
$rate_limit_key = "callback_{$communityId}_{$current_ip}";
$current_hour = date('Y-m-d H:00:00');
$rate_check = $db->prepare("SELECT action_count FROM subscription_rate_limits WHERE community_id = ? AND action_type = ? AND hour_timestamp = ?");
$rate_check->bindValue(1, $communityId, SQLITE3_TEXT);
$rate_check->bindValue(2, $rate_limit_key, SQLITE3_TEXT);
$rate_check->bindValue(3, $current_hour, SQLITE3_TEXT);
$rate_result = $rate_check->execute();
$rate_row = $rate_result->fetchArray(SQLITE3_ASSOC);
$callback_attempts = $rate_row ? (int)$rate_row['action_count'] : 0;

// Maksimum 20 callback/saat (spam koruması)
if ($callback_attempts >= 20) {
    tpl_error_log("Payment callback rate limit exceeded: {$rate_limit_key} - {$callback_attempts} attempts");
    $_SESSION['error'] = 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.';
    header('Location: index.php?view=subscription&error=1');
    exit;
}

// Rate limit sayacını artır
if ($rate_row) {
    $update_rate = $db->prepare("UPDATE subscription_rate_limits SET action_count = action_count + 1 WHERE community_id = ? AND action_type = ? AND hour_timestamp = ?");
    $update_rate->bindValue(1, $communityId, SQLITE3_TEXT);
    $update_rate->bindValue(2, $rate_limit_key, SQLITE3_TEXT);
    $update_rate->bindValue(3, $current_hour, SQLITE3_TEXT);
    $update_rate->execute();
} else {
    $insert_rate = $db->prepare("INSERT INTO subscription_rate_limits (community_id, ip_address, action_type, action_count, hour_timestamp) VALUES (?, ?, ?, 1, ?)");
    $insert_rate->bindValue(1, $communityId, SQLITE3_TEXT);
    $insert_rate->bindValue(2, $current_ip, SQLITE3_TEXT);
    $insert_rate->bindValue(3, $rate_limit_key, SQLITE3_TEXT);
    $insert_rate->bindValue(4, $current_hour, SQLITE3_TEXT);
    $insert_rate->execute();
}

try {
    if ($token || $paymentId) {
        // 1. Ödeme durumunu kontrol et - Iyzico API'den doğrula
        $paymentStatus = $iyzicoHelper->checkPaymentStatus($paymentId ?? $token);
        
        if (!$paymentStatus || !isset($paymentStatus['payment_id'])) {
            throw new Exception('Ödeme durumu alınamadı. Iyzico API hatası.');
        }
        
        // 2. Abonelik doğrulama - Payment token ile güvenli doğrulama
        $verification = $subscriptionManager->verifySubscription($paymentStatus['payment_id'] ?? $paymentId, $payment_token);
        
        if (!$verification['success']) {
            // Abonelik bulunamadı veya expired
            tpl_error_log("Subscription verification failed: " . ($verification['message'] ?? 'Unknown error') . " - Payment ID: " . ($paymentId ?? 'N/A'));
            $_SESSION['error'] = $verification['message'] ?? 'Abonelik kaydı bulunamadı veya süresi dolmuş.';
            header('Location: index.php?view=subscription&error=1');
            exit;
        }
        
        $subscription = $verification['subscription'];
        
        // 3. Standart abonelik koruması - Standart abonelik callback ile güncellenemez
        if ($subscription['tier'] === 'standard') {
            $_SESSION['message'] = 'Standart abonelik her zaman aktiftir.';
            header('Location: index.php?view=subscription&success=1');
            exit;
        }
        
        // 4. Ödeme durumu kontrolü
        if ($paymentStatus['status'] === 'success' && $paymentStatus['payment_status'] === 'SUCCESS') {
            // Ödeme başarılı - aboneliği güncelle
            if ($subscription) {
                // Çift ödeme koruması - zaten başarılıysa tekrar işleme
                if ($subscription['payment_status'] === 'success') {
                    $_SESSION['message'] = 'Bu ödeme zaten işlenmiş. Aboneliğiniz aktif.';
                    header('Location: index.php?view=subscription&success=1');
                    exit;
                }
                
                // Aboneliği güncelle
                $subscriptionManager->updateSubscription(
                    $subscription['id'],
                    $paymentStatus['payment_id'] ?? $paymentId,
                    'success'
                );
                
                // Business paket kontrolü - NetGSM entegrasyonunu otomatik yap (sessizce, uyarı göstermeden)
                if (strtolower($subscription['tier'] ?? '') === 'business') {
                    try {
                        $subscriptionManager->autoIntegrateNetGSM();
                    } catch (Exception $e) {
                        // Sessizce devam et, hata loglanmaz
                    }
                }
                
                $_SESSION['message'] = 'Ödeme başarıyla tamamlandı! Aboneliğiniz aktif.';
            } else {
                // Yeni abonelik oluştur (bu durum normalde olmamalı - güvenlik uyarısı)
                tpl_error_log("WARNING: Creating new subscription from callback - Payment ID: " . ($paymentStatus['payment_id'] ?? $paymentId));
                $subscriptionManager->createSubscription(
                    $paymentStatus['payment_id'] ?? $paymentId,
                    'success',
                    1, // Varsayılan 1 ay
                    null, // Amount Iyzico'dan gelecek
                    'professional' // Varsayılan tier
                );
                
                $_SESSION['message'] = 'Ödeme başarıyla tamamlandı! Aboneliğiniz başlatıldı.';
            }
            
            header('Location: index.php?view=subscription&success=1');
            exit;
        } else {
            // Ödeme başarısız - aboneliği failed olarak işaretle
            if ($subscription) {
                $subscriptionManager->updateSubscription(
                    $subscription['id'],
                    $paymentStatus['payment_id'] ?? $paymentId,
                    'failed'
                );
            }
            
            $error_message = $paymentStatus['error_message'] ?? 'Ödeme işlemi başarısız oldu.';
            tpl_error_log("Payment failed: " . $error_message . " - Payment ID: " . ($paymentId ?? 'N/A'));
            $_SESSION['error'] = $error_message . ' Lütfen tekrar deneyin.';
            header('Location: index.php?view=subscription&error=1');
            exit;
        }
    } else {
        // Token yok - Güvenlik: Detaylı hata verme
        tpl_error_log("Payment callback: Missing token/paymentId - IP: {$current_ip}, Community: {$communityId}");
        $_SESSION['error'] = 'Geçersiz ödeme bilgisi. Lütfen destek ekibiyle iletişime geçin.';
        header('Location: index.php?view=subscription&error=1');
        exit;
    }
} catch (Exception $e) {
    tpl_error_log("Payment callback error: " . $e->getMessage() . " - Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = 'Ödeme işlemi sırasında bir hata oluştu. Lütfen destek ekibiyle iletişime geçin.';
    header('Location: index.php?view=subscription&error=1');
    exit;
}

