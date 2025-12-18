<?php
header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/payment/IyzicoHelper.php';
require_once __DIR__ . '/auth_middleware.php';

use UniPanel\Payment\IyzicoHelper;

function build_base_url_market() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . '://' . $host;
    return $base;
}

function parse_product_key($key) {
    if (!$key) {
        return null;
    }
    $parts = explode('-', $key);
    if (count($parts) < 2) {
        return null;
    }
    $productId = array_pop($parts);
    if (!ctype_digit($productId)) {
        return null;
    }
    $communitySlug = implode('-', $parts);
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communitySlug)) {
        return null;
    }
    return [$communitySlug, (int)$productId];
}

function load_market_product($communitySlug, $productId) {
    $dbPath = realpath(__DIR__ . '/../communities/' . $communitySlug . '/unipanel.sqlite');
    if (!$dbPath || !file_exists($dbPath)) {
        return null;
    }
    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    } catch (Exception $e) {
        return null;
    }
    
    $settings = [];
    $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
    if ($settings_query) {
        while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    $community_name = $settings['club_name'] ?? ucwords(str_replace('_', ' ', $communitySlug));
    
    $stmt = $db->prepare("SELECT id, name, description, price, stock, category, image_path, commission_rate, total_price, iyzico_commission, platform_commission FROM products WHERE id = ? AND club_id = 1 AND status = 'active' LIMIT 1");
    if (!$stmt) {
        $db->close();
        return null;
    }
    $stmt->bindValue(1, $productId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $product = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
    $db->close();
    
    if (!$product) {
        return null;
    }
    
    $price = isset($product['price']) ? (float)$product['price'] : 0;
    $commission_rate = isset($product['commission_rate']) ? (float)$product['commission_rate'] : 8.0;
    $total_price = isset($product['total_price']) ? (float)$product['total_price'] : 0;
    $iyzico_commission = isset($product['iyzico_commission']) ? (float)$product['iyzico_commission'] : null;
    $platform_commission = isset($product['platform_commission']) ? (float)$product['platform_commission'] : null;
    
    if (!$total_price || $iyzico_commission === null || $platform_commission === null) {
        $iyzico_rate = 2.99;
        $iyzico_fixed = 0.25;
        $iyzico_commission = ($price * $iyzico_rate / 100) + $iyzico_fixed;
        $platform_commission = $price * $commission_rate / 100;
        $total_price = $price + $iyzico_commission + $platform_commission;
    }
    
    return [
        'id' => (int)$product['id'],
        'name' => $product['name'] ?? 'Ürün',
        'category' => $product['category'] ?? 'Genel',
        'price' => $price,
        'total_price' => $total_price,
        'iyzico_commission' => $iyzico_commission,
        'platform_commission' => $platform_commission,
        'community_slug' => $communitySlug,
        'community_name' => $community_name,
        'stock' => isset($product['stock']) ? (int)$product['stock'] : 0
    ];
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
    
    // Authentication kontrolü (opsiyonel - ödeme için gerekli olabilir)
    $currentUser = optionalAuth();
    
    // CSRF koruması (web formları için)
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'CSRF token geçersiz.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
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
    
    $items = $input['items'] ?? [];
    $customer = $input['customer'] ?? [];
    
    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Sepet boş.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $requiredFields = ['name', 'email', 'phone', 'city', 'address'];
    foreach ($requiredFields as $field) {
        if (empty($customer[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Lütfen iletişim bilgilerinizi eksiksiz doldurun.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Input validation ve sanitization
    $customer['name'] = sanitizeInput(trim($customer['name']), 'string');
    $customer['email'] = sanitizeInput(trim($customer['email']), 'email');
    $customer['phone'] = sanitizeInput(trim($customer['phone']), 'string');
    $customer['city'] = sanitizeInput(trim($customer['city']), 'string');
    $customer['address'] = sanitizeInput(trim($customer['address']), 'string');
    
    // Email validation
    if (!validateEmail($customer['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz email formatı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Phone validation (opsiyonel - farklı formatlar olabilir)
    if (strlen($customer['phone']) > 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Telefon numarası çok uzun.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $cartItems = [];
    $subtotal = 0;
    $commissionTotal = 0;
    $communities = [];
    
    foreach ($items as $item) {
        $key = $item['key'] ?? null;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        if ($quantity < 1) {
            $quantity = 1;
        }
        $parsed = parse_product_key($key);
        if (!$parsed) {
            continue;
        }
        [$communitySlug, $productId] = $parsed;
        
        // Community slug'ı sanitize et
        try {
            $communitySlug = sanitizeCommunityId($communitySlug);
        } catch (Exception $e) {
            continue; // Geçersiz community ID'yi atla
        }
        $product = load_market_product($communitySlug, $productId);
        if (!$product) {
            continue;
        }
        $lineSubtotal = $product['price'] * $quantity;
        $lineTotal = $product['total_price'] * $quantity;
        $lineCommission = ($product['total_price'] - $product['price']) * $quantity;
        
        $subtotal += $lineSubtotal;
        $commissionTotal += $lineCommission;
        $communities[$communitySlug] = true;
        
        $cartItems[] = [
            'key' => $key,
            'product_id' => $product['id'],
            'community_slug' => $communitySlug,
            'community_name' => $product['community_name'],
            'name' => $product['name'],
            'quantity' => $quantity,
            'unit_price' => $product['price'],
            'unit_total' => $product['total_price'],
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal
        ];
    }
    
    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Sepetteki ürünler bulunamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $grandTotal = $subtotal + $commissionTotal;
    
    $conversationId = 'MARKET-' . time() . '-' . uniqid();
    $callbackUrl = build_base_url_market() . '/templates/payment_callback.php?type=market&order=' . urlencode($conversationId);
    
    $iyzicoHelper = new IyzicoHelper();
    $paymentForm = $iyzicoHelper->createPaymentForm([
        'conversation_id' => $conversationId,
        'price' => round($grandTotal, 2),
        'callback_url' => $callbackUrl
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ödeme sayfasına yönlendiriliyorsunuz.',
        'order' => [
            'reference' => $conversationId,
            'subtotal' => round($subtotal, 2),
            'commission_total' => round($commissionTotal, 2),
            'total' => round($grandTotal, 2),
            'items' => $cartItems,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'city' => $customer['city']
            ]
        ],
        'payment_form' => $paymentForm
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('Ödeme işlemi sırasında bir hata oluştu', $e);
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

