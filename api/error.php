<?php
require_once __DIR__ . '/security_helper.php';
/**
 * API Error Handler
 * 404 ve diğer hatalar için JSON response
 */

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

http_response_code(404);

echo json_encode([
    'success' => false,
    'data' => null,
    'message' => null,
    'error' => 'API endpoint bulunamadı. Lütfen doğru endpoint kullanın.'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

