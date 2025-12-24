<?php
/**
 * Market API v2 - Orders Endpoint
 * 
 * POST /api/v2/market/orders.php - Create new order
 * GET /api/v2/market/orders.php - List user's orders
 * GET /api/v2/market/orders.php?id={id} - Get order details
 * 
 * POST Body:
 * {
 *   "items": [
 *     {"product_id": 1, "community_id": "slug", "quantity": 1}
 *   ],
 *   "customer": {
 *     "name": "John Doe",
 *     "email": "john@example.com",
 *     "phone": "5551234567"
 *   }
 * }
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load dependencies
require_once __DIR__ . '/../../security_helper.php';
require_once __DIR__ . '/../../auth_middleware.php';
require_once __DIR__ . '/../../../lib/autoload.php';
require_once __DIR__ . '/../../../lib/payment/IyzicoHelper.php';

use UniPanel\Payment\IyzicoHelper;

// CORS handling
if (function_exists('setSecureCORS')) {
    setSecureCORS();
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Rate limiting
if (function_exists('checkRateLimit') && !checkRateLimit(30, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Çok fazla istek. Lütfen bir dakika bekleyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get base URL
 */
function getBaseUrl(): string {
    static $baseUrl = null;
    if ($baseUrl === null) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    return $baseUrl;
}

/**
 * Get community database connection
 */
function getCommunityDb(string $communitySlug, bool $readOnly = true): ?SQLite3 {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communitySlug)) {
        return null;
    }
    
    $dbPath = realpath(__DIR__ . '/../../../communities/' . $communitySlug . '/unipanel.sqlite');
    if (!$dbPath || !file_exists($dbPath)) {
        return null;
    }
    
    try {
        $mode = $readOnly ? SQLITE3_OPEN_READONLY : SQLITE3_OPEN_READWRITE;
        $db = new SQLite3($dbPath, $mode);
        $db->busyTimeout(5000);
        return $db;
    } catch (Exception $e) {
        error_log("Market Orders API: DB connection failed for {$communitySlug}: " . $e->getMessage());
        return null;
    }
}

/**
 * Get community info
 */
function getCommunityInfo(SQLite3 $db, string $slug): array {
    $info = ['name' => ucwords(str_replace('_', ' ', $slug))];
    try {
        $query = $db->query("SELECT setting_value FROM settings WHERE club_id = 1 AND setting_key = 'club_name' LIMIT 1");
        if ($query && $row = $query->fetchArray(SQLITE3_ASSOC)) {
            $info['name'] = $row['setting_value'];
        }
    } catch (Exception $e) {}
    return $info;
}

/**
 * Calculate pricing with commissions
 */
function calculatePricing(float $basePrice, float $commissionRate = 8.0): array {
    $iyzicoRate = 2.99;
    $iyzicoFixed = 0.25;
    $iyzicoCommission = ($basePrice * $iyzicoRate / 100) + $iyzicoFixed;
    $platformCommission = $basePrice * $commissionRate / 100;
    $totalPrice = $basePrice + $iyzicoCommission + $platformCommission;
    
    return [
        'base_price' => round($basePrice, 2),
        'iyzico_commission' => round($iyzicoCommission, 2),
        'platform_commission' => round($platformCommission, 2),
        'total_price' => round($totalPrice, 2)
    ];
}

/**
 * Generate unique order number
 */
function generateOrderNumber(): string {
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    return "FK{$date}{$random}";
}

/**
 * Get main orders database (central orders db)
 */
function getOrdersDb(): ?SQLite3 {
    $dbPath = __DIR__ . '/../../../storage/orders/orders.sqlite';
    $dbDir = dirname($dbPath);
    
    // Create directory if not exists
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->busyTimeout(5000);
        
        // Create tables if not exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                user_id VARCHAR(100),
                user_email VARCHAR(255) NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                user_phone VARCHAR(50),
                
                subtotal DECIMAL(10,2) NOT NULL,
                commission DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                
                status VARCHAR(50) DEFAULT 'pending',
                payment_status VARCHAR(50) DEFAULT 'pending',
                payment_id VARCHAR(255),
                payment_method VARCHAR(50) DEFAULT 'iyzico',
                
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME
            );
            
            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                community_id VARCHAR(100) NOT NULL,
                community_name VARCHAR(255),
                product_name VARCHAR(255) NOT NULL,
                product_category VARCHAR(100),
                quantity INTEGER NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                unit_total DECIMAL(10,2) NOT NULL,
                line_subtotal DECIMAL(10,2) NOT NULL,
                line_total DECIMAL(10,2) NOT NULL,
                
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );
            
            CREATE INDEX IF NOT EXISTS idx_orders_user_email ON orders(user_email);
            CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id);
            CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
            CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);
            CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
        ");
        
        return $db;
    } catch (Exception $e) {
        error_log("Market Orders API: Orders DB error: " . $e->getMessage());
        return null;
    }
}

/**
 * Load product from community database
 */
function loadProduct(string $communitySlug, int $productId): ?array {
    $db = getCommunityDb($communitySlug);
    if (!$db) {
        return null;
    }
    
    $communityInfo = getCommunityInfo($db, $communitySlug);
    
    $stmt = $db->prepare("
        SELECT id, name, description, price, stock, category, commission_rate, status
        FROM products 
        WHERE id = ? AND club_id = 1 AND status = 'active'
        LIMIT 1
    ");
    $stmt->bindValue(1, $productId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $product = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
    $db->close();
    
    if (!$product) {
        return null;
    }
    
    $product['community_id'] = $communitySlug;
    $product['community_name'] = $communityInfo['name'];
    
    return $product;
}

/**
 * Decrease product stock
 */
function decreaseStock(string $communitySlug, int $productId, int $quantity): bool {
    $db = getCommunityDb($communitySlug, false);
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND club_id = 1 AND stock >= ?");
        $stmt->bindValue(1, $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(2, $productId, SQLITE3_INTEGER);
        $stmt->bindValue(3, $quantity, SQLITE3_INTEGER);
        $stmt->execute();
        $success = $db->changes() > 0;
        $db->close();
        return $success;
    } catch (Exception $e) {
        $db->close();
        return false;
    }
}

// ============================================================================
// Request Handlers
// ============================================================================

/**
 * Handle GET request - List orders or get single order
 */
function handleGetRequest(?array $currentUser): void {
    $orderId = isset($_GET['id']) ? trim($_GET['id']) : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Authentication required for viewing orders
    if (!$currentUser || empty($currentUser['email'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Bu işlem için giriş yapmalısınız.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $ordersDb = getOrdersDb();
    if (!$ordersDb) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Veritabanı hatası.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Single order
    if ($orderId !== null) {
        $stmt = $ordersDb->prepare("
            SELECT * FROM orders WHERE (order_number = ? OR id = ?) AND user_email = ?
        ");
        $stmt->bindValue(1, $orderId, SQLITE3_TEXT);
        $stmt->bindValue(2, (int)$orderId, SQLITE3_INTEGER);
        $stmt->bindValue(3, $currentUser['email'], SQLITE3_TEXT);
        $result = $stmt->execute();
        $order = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        
        if (!$order) {
            $ordersDb->close();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Sipariş bulunamadı.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Get order items
        $itemsStmt = $ordersDb->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->bindValue(1, $order['id'], SQLITE3_INTEGER);
        $itemsResult = $itemsStmt->execute();
        $items = [];
        while ($item = $itemsResult->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $item;
        }
        
        $ordersDb->close();
        
        $order['items'] = $items;
        
        echo json_encode([
            'success' => true,
            'data' => $order
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // List orders
    $countStmt = $ordersDb->prepare("SELECT COUNT(*) as total FROM orders WHERE user_email = ?");
    $countStmt->bindValue(1, $currentUser['email'], SQLITE3_TEXT);
    $countResult = $countStmt->execute();
    $total = $countResult ? (int)$countResult->fetchArray(SQLITE3_ASSOC)['total'] : 0;
    
    $stmt = $ordersDb->prepare("
        SELECT * FROM orders WHERE user_email = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $currentUser['email'], SQLITE3_TEXT);
    $stmt->bindValue(2, $limit, SQLITE3_INTEGER);
    $stmt->bindValue(3, $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $orders = [];
    while ($order = $result->fetchArray(SQLITE3_ASSOC)) {
        // Get items count
        $itemsStmt = $ordersDb->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
        $itemsStmt->bindValue(1, $order['id'], SQLITE3_INTEGER);
        $itemsResult = $itemsStmt->execute();
        $order['items_count'] = $itemsResult ? (int)$itemsResult->fetchArray(SQLITE3_ASSOC)['count'] : 0;
        $orders[] = $order;
    }
    
    $ordersDb->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Handle POST request - Create new order
 */
function handlePostRequest(?array $currentUser): void {
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Geçersiz JSON verisi.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $items = $input['items'] ?? [];
    $customer = $input['customer'] ?? [];
    
    // Validate items
    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Sepet boş.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Validate customer info
    $requiredFields = ['name', 'email', 'phone'];
    foreach ($requiredFields as $field) {
        if (empty($customer[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Lütfen müşteri bilgilerini eksiksiz doldurun.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }
    
    // Sanitize customer data
    $customerName = function_exists('sanitizeInput') ? sanitizeInput(trim($customer['name']), 'string') : trim($customer['name']);
    $customerEmail = filter_var(trim($customer['email']), FILTER_SANITIZE_EMAIL);
    $customerPhone = preg_replace('/[^0-9+]/', '', trim($customer['phone']));
    
    // Validate email
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Geçersiz email formatı.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Process cart items
    $cartItems = [];
    $subtotal = 0;
    $commissionTotal = 0;
    
    foreach ($items as $item) {
        $communityId = $item['community_id'] ?? null;
        $productId = isset($item['product_id']) ? (int)$item['product_id'] : null;
        $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
        
        if (!$communityId || !$productId) {
            continue;
        }
        
        // Sanitize community ID
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communityId)) {
            continue;
        }
        
        // Load product
        $product = loadProduct($communityId, $productId);
        if (!$product) {
            continue;
        }
        
        // Check stock
        if ((int)$product['stock'] < $quantity) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "'{$product['name']}' ürünü için yeterli stok yok."
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $basePrice = (float)$product['price'];
        $commissionRate = (float)($product['commission_rate'] ?? 8.0);
        $pricing = calculatePricing($basePrice, $commissionRate);
        
        $lineSubtotal = $basePrice * $quantity;
        $lineTotal = $pricing['total_price'] * $quantity;
        $lineCommission = ($pricing['total_price'] - $basePrice) * $quantity;
        
        $subtotal += $lineSubtotal;
        $commissionTotal += $lineCommission;
        
        $cartItems[] = [
            'product_id' => $productId,
            'community_id' => $communityId,
            'community_name' => $product['community_name'],
            'product_name' => $product['name'],
            'product_category' => $product['category'] ?? 'Genel',
            'quantity' => $quantity,
            'unit_price' => $basePrice,
            'unit_total' => $pricing['total_price'],
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal
        ];
    }
    
    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Sepetteki ürünler bulunamadı veya stokta yok.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $grandTotal = $subtotal + $commissionTotal;
    $orderNumber = generateOrderNumber();
    
    // Create order in database
    $ordersDb = getOrdersDb();
    if (!$ordersDb) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Veritabanı hatası.'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        $ordersDb->exec('BEGIN TRANSACTION');
        
        // Insert order
        $orderStmt = $ordersDb->prepare("
            INSERT INTO orders (order_number, user_id, user_email, user_name, user_phone, subtotal, commission, total, status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $orderStmt->bindValue(1, $orderNumber, SQLITE3_TEXT);
        $orderStmt->bindValue(2, $currentUser['id'] ?? null, SQLITE3_TEXT);
        $orderStmt->bindValue(3, $customerEmail, SQLITE3_TEXT);
        $orderStmt->bindValue(4, $customerName, SQLITE3_TEXT);
        $orderStmt->bindValue(5, $customerPhone, SQLITE3_TEXT);
        $orderStmt->bindValue(6, round($subtotal, 2), SQLITE3_FLOAT);
        $orderStmt->bindValue(7, round($commissionTotal, 2), SQLITE3_FLOAT);
        $orderStmt->bindValue(8, round($grandTotal, 2), SQLITE3_FLOAT);
        $orderStmt->execute();
        
        $orderId = $ordersDb->lastInsertRowID();
        
        // Insert order items
        foreach ($cartItems as $cartItem) {
            $itemStmt = $ordersDb->prepare("
                INSERT INTO order_items (order_id, product_id, community_id, community_name, product_name, product_category, quantity, unit_price, unit_total, line_subtotal, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $itemStmt->bindValue(1, $orderId, SQLITE3_INTEGER);
            $itemStmt->bindValue(2, $cartItem['product_id'], SQLITE3_INTEGER);
            $itemStmt->bindValue(3, $cartItem['community_id'], SQLITE3_TEXT);
            $itemStmt->bindValue(4, $cartItem['community_name'], SQLITE3_TEXT);
            $itemStmt->bindValue(5, $cartItem['product_name'], SQLITE3_TEXT);
            $itemStmt->bindValue(6, $cartItem['product_category'], SQLITE3_TEXT);
            $itemStmt->bindValue(7, $cartItem['quantity'], SQLITE3_INTEGER);
            $itemStmt->bindValue(8, $cartItem['unit_price'], SQLITE3_FLOAT);
            $itemStmt->bindValue(9, $cartItem['unit_total'], SQLITE3_FLOAT);
            $itemStmt->bindValue(10, $cartItem['line_subtotal'], SQLITE3_FLOAT);
            $itemStmt->bindValue(11, $cartItem['line_total'], SQLITE3_FLOAT);
            $itemStmt->execute();
        }
        
        $ordersDb->exec('COMMIT');
        $ordersDb->close();
        
        // Initialize payment with Iyzico
        $callbackUrl = getBaseUrl() . '/api/v2/market/payment_callback.php?order=' . urlencode($orderNumber);
        
        $paymentForm = null;
        try {
            $iyzicoHelper = new IyzicoHelper();
            $paymentForm = $iyzicoHelper->createPaymentForm([
                'conversation_id' => $orderNumber,
                'price' => round($grandTotal, 2),
                'callback_url' => $callbackUrl
            ]);
        } catch (Exception $e) {
            error_log("Market Orders API: Iyzico error: " . $e->getMessage());
        }
        
        // Response
        echo json_encode([
            'success' => true,
            'message' => 'Sipariş oluşturuldu.',
            'data' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'subtotal' => round($subtotal, 2),
                'commission' => round($commissionTotal, 2),
                'total' => round($grandTotal, 2),
                'items' => $cartItems,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone
                ],
                'payment_form' => $paymentForm
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $ordersDb->exec('ROLLBACK');
        $ordersDb->close();
        
        error_log("Market Orders API: Order creation failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Sipariş oluşturulurken bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ============================================================================
// Main Routing
// ============================================================================

try {
    // Get authenticated user (optional for POST, required for GET)
    $currentUser = function_exists('optionalAuth') ? optionalAuth() : null;
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($currentUser);
            break;
        case 'POST':
            handlePostRequest($currentUser);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Desteklenmeyen HTTP metodu.'
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Market Orders API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
}
