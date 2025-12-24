<?php
/**
 * Market API v2 - Payment Callback
 * 
 * Handles Iyzico payment callback to update order status
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Load dependencies  
require_once __DIR__ . '/../../security_helper.php';
require_once __DIR__ . '/../../../lib/autoload.php';
require_once __DIR__ . '/../../../lib/payment/IyzicoHelper.php';

use UniPanel\Payment\IyzicoHelper;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get orders database
 */
function getOrdersDb(): ?SQLite3 {
    $dbPath = __DIR__ . '/../../../storage/orders/orders.sqlite';
    if (!file_exists($dbPath)) {
        return null;
    }
    
    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000);
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get community database connection
 */
function getCommunityDb(string $communitySlug): ?SQLite3 {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $communitySlug)) {
        return null;
    }
    
    $dbPath = realpath(__DIR__ . '/../../../communities/' . $communitySlug . '/unipanel.sqlite');
    if (!$dbPath || !file_exists($dbPath)) {
        return null;
    }
    
    try {
        $db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE);
        $db->busyTimeout(5000);
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Decrease product stock
 */
function decreaseStock(string $communitySlug, int $productId, int $quantity): bool {
    $db = getCommunityDb($communitySlug);
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("UPDATE products SET stock = stock - ?, sold_count = COALESCE(sold_count, 0) + ? WHERE id = ? AND club_id = 1 AND stock >= ?");
        $stmt->bindValue(1, $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(2, $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(3, $productId, SQLITE3_INTEGER);
        $stmt->bindValue(4, $quantity, SQLITE3_INTEGER);
        $stmt->execute();
        $success = $db->changes() > 0;
        $db->close();
        return $success;
    } catch (Exception $e) {
        $db->close();
        return false;
    }
}

/**
 * Get base URL
 */
function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

// ============================================================================
// Main Logic
// ============================================================================

try {
    $orderNumber = $_GET['order'] ?? null;
    
    if (!$orderNumber) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Sipariş numarası eksik.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Sanitize order number
    if (!preg_match('/^FK[0-9]{8}[A-Z0-9]{6}$/', $orderNumber)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Geçersiz sipariş numarası.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get payment result from Iyzico
    $iyzicoHelper = new IyzicoHelper();
    $token = $_POST['token'] ?? null;
    
    if (!$token) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Ödeme token eksik.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Retrieve payment result
    $paymentResult = $iyzicoHelper->retrievePaymentResult($token);
    
    if (!$paymentResult) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Ödeme sonucu alınamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $ordersDb = getOrdersDb();
    if (!$ordersDb) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Veritabanı hatası.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get order
    $orderStmt = $ordersDb->prepare("SELECT * FROM orders WHERE order_number = ?");
    $orderStmt->bindValue(1, $orderNumber, SQLITE3_TEXT);
    $orderResult = $orderStmt->execute();
    $order = $orderResult ? $orderResult->fetchArray(SQLITE3_ASSOC) : null;
    
    if (!$order) {
        $ordersDb->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Sipariş bulunamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check if payment was successful
    $paymentStatus = $paymentResult->getStatus();
    $paymentId = $paymentResult->getPaymentId();
    
    if ($paymentStatus === 'success') {
        // Update order status
        $updateStmt = $ordersDb->prepare("
            UPDATE orders 
            SET status = 'confirmed', payment_status = 'paid', payment_id = ?, paid_at = datetime('now'), updated_at = datetime('now')
            WHERE order_number = ?
        ");
        $updateStmt->bindValue(1, $paymentId, SQLITE3_TEXT);
        $updateStmt->bindValue(2, $orderNumber, SQLITE3_TEXT);
        $updateStmt->execute();
        
        // Get order items and decrease stock
        $itemsStmt = $ordersDb->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->bindValue(1, $order['id'], SQLITE3_INTEGER);
        $itemsResult = $itemsStmt->execute();
        
        while ($item = $itemsResult->fetchArray(SQLITE3_ASSOC)) {
            decreaseStock($item['community_id'], $item['product_id'], $item['quantity']);
        }
        
        $ordersDb->close();
        
        // Redirect to success page
        $successUrl = getBaseUrl() . '/order-success.html?order=' . urlencode($orderNumber);
        header('Location: ' . $successUrl);
        exit;
        
    } else {
        // Payment failed
        $errorMessage = $paymentResult->getErrorMessage() ?? 'Bilinmeyen hata';
        
        $updateStmt = $ordersDb->prepare("
            UPDATE orders 
            SET status = 'cancelled', payment_status = 'failed', notes = ?, updated_at = datetime('now')
            WHERE order_number = ?
        ");
        $updateStmt->bindValue(1, $errorMessage, SQLITE3_TEXT);
        $updateStmt->bindValue(2, $orderNumber, SQLITE3_TEXT);
        $updateStmt->execute();
        
        $ordersDb->close();
        
        // Redirect to failure page
        $failUrl = getBaseUrl() . '/order-failed.html?order=' . urlencode($orderNumber) . '&error=' . urlencode($errorMessage);
        header('Location: ' . $failUrl);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Market Payment Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ödeme işlemi sırasında bir hata oluştu.'
    ], JSON_UNESCAPED_UNICODE);
}
