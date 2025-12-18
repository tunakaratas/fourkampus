<?php
/**
 * Sipariş Onayı ve E-posta Bildirimi API
 * POST /api/order_confirmation.php - Sipariş onayı ve e-posta gönderimi
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendOrderConfirmationEmail($orderData) {
    try {
        // Communication modülünü yükle
        $communicationPath = __DIR__ . '/../templates/functions/communication.php';
        if (file_exists($communicationPath)) {
            require_once $communicationPath;
        }
        
        $customerEmail = $orderData['customer']['email'] ?? '';
        $customerName = $orderData['customer']['name'] ?? 'Müşteri';
        $orderNumber = $orderData['order_number'] ?? 'Bilinmiyor';
        $orderDate = date('d.m.Y H:i');
        $totalAmount = number_format($orderData['total'] ?? 0, 2, ',', '.');
        
        // E-posta içeriği
        $subject = "Sipariş Onayı - #{$orderNumber}";
        
        $message = "
Merhaba {$customerName},

Siparişiniz başarıyla alındı!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SİPARİŞ BİLGİLERİ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Sipariş No: {$orderNumber}
Sipariş Tarihi: {$orderDate}
Toplam Tutar: {$totalAmount} ₺

ÜRÜNLER:
";
        
        foreach ($orderData['items'] ?? [] as $item) {
            $itemName = $item['name'] ?? 'Ürün';
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = number_format($item['unit_total'] ?? 0, 2, ',', '.');
            $lineTotal = number_format(($item['unit_total'] ?? 0) * $quantity, 2, ',', '.');
            
            $message .= "• {$itemName} - {$quantity} adet × {$unitPrice} ₺ = {$lineTotal} ₺\n";
        }
        
        $message .= "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TESLİMAT BİLGİLERİ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Ad Soyad: {$orderData['customer']['name']}
E-posta: {$orderData['customer']['email']}
Telefon: {$orderData['customer']['phone']}

TESLİMAT TİPİ: STANT TESLİMATI
• Ürünler topluluk stantlarından elden teslim edilecektir.
• Stant konumu ve teslimat tarihi topluluk tarafından belirlenir.
• Topluluk size stant konumu ve teslimat tarihi hakkında bilgi verecektir.

ÖNEMLİ NOT:
Four Kampüs sadece bir aracı platformdur. Teslimat sorumluluğu tamamen 
topluluğa aittir. Ürünlerle ilgili sorularınız için lütfen ilgili 
toplulukla iletişime geçiniz.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
YASAL BİLGİLER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• İade ve iptal koşulları için: https://foursoftware.net/marketing/cancellation-refund.php
• Stant teslimat sözleşmesi: https://foursoftware.net/marketing/stand-delivery-contract.php
• Tüketici hakları: https://foursoftware.net/marketing/consumer-rights.php

Topluluk size stant konumu ve teslimat tarihi hakkında bilgi verecektir.

Teşekkürler,
Four Kampüs Ekibi
";
        
        // SMTP ayarlarını al
        $smtp_username = '';
        $smtp_password = '';
        $smtp_host = 'ms7.guzel.net.tr';
        $smtp_port = 587;
        $smtp_secure = 'tls';
        
        if (function_exists('get_smtp_credential')) {
            $smtp_username = get_smtp_credential('username') ?? '';
            $smtp_password = get_smtp_credential('password') ?? '';
            $smtp_host = get_smtp_credential('host', 'ms7.guzel.net.tr');
            $smtp_port = (int)(get_smtp_credential('port', 587));
            $smtp_secure = get_smtp_credential('encryption', 'tls');
        }
        
        // E-posta gönder
        if (!empty($smtp_username) && !empty($smtp_password) && function_exists('send_smtp_mail')) {
            return @send_smtp_mail(
                $customerEmail,
                $subject,
                $message,
                'Four Kampüs',
                $smtp_username,
                [
                    'host' => $smtp_host,
                    'port' => $smtp_port,
                    'secure' => $smtp_secure,
                    'username' => $smtp_username,
                    'password' => $smtp_password,
                ]
            );
        } else {
            // Fallback: PHP mail()
            $headers = "From: Four Kampüs <noreply@fourkampus.com.tr>\r\n";
            $headers .= "Reply-To: info@fourkampus.com.tr\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            return @mail($customerEmail, $subject, $message, $headers);
        }
    } catch (Exception $e) {
        error_log("Order confirmation email error: " . $e->getMessage());
        return false;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz istek yöntemi.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz JSON verisi.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Gerekli alanları kontrol et
    $requiredFields = ['order_number', 'customer', 'items', 'total'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Eksik alan: {$field}"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Sipariş numarası oluştur (yoksa)
    $orderNumber = $input['order_number'] ?? 'ORD-' . strtoupper(substr(uniqid(), -8));
    
    // Sipariş verilerini hazırla
    $orderData = [
        'order_number' => $orderNumber,
        'customer' => $input['customer'],
        'items' => $input['items'],
        'total' => (float)($input['total'] ?? 0),
        'order_date' => date('Y-m-d H:i:s')
    ];
    
    // E-posta gönder
    $emailSent = sendOrderConfirmationEmail($orderData);
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Sipariş onaylandı ve e-posta gönderildi.',
        'order' => [
            'order_number' => $orderNumber,
            'email_sent' => $emailSent
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("order_confirmation.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sipariş onayı sırasında bir hata oluştu.'
    ], JSON_UNESCAPED_UNICODE);
}

