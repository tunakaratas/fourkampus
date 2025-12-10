<?php
/**
 * Mobil API - Member Registration Endpoint
 * POST /api/register.php - Topluluğa üye kaydı
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(30, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Authentication zorunlu (topluluğa üye kaydı için)
$currentUser = requireAuth(true);

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, null, 'Sadece POST istekleri kabul edilir');
    }
    
    // CSRF koruması
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        if (!verifyCSRFToken($csrfToken)) {
            sendResponse(false, null, null, 'CSRF token geçersiz');
        }
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $currentUser['id'];
    
    if (!isset($input['community_id']) || empty($input['community_id'])) {
        sendResponse(false, null, null, 'community_id parametresi gerekli');
    }
    
    if (!isset($input['full_name']) || empty(trim($input['full_name']))) {
        sendResponse(false, null, null, 'Ad Soyad gerekli');
    }
    
    $community_id = sanitizeCommunityId($input['community_id']);
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Membership requests tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS membership_requests (
        id INTEGER PRIMARY KEY,
        club_id INTEGER NOT NULL,
        user_id INTEGER,
        full_name TEXT,
        email TEXT,
        phone TEXT,
        student_id TEXT,
        department TEXT,
        status TEXT DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        additional_data TEXT,
        UNIQUE(club_id, email)
    )");
    
    $full_name = sanitizeInput(trim($input['full_name']), 'string');
    $email = isset($input['email']) ? sanitizeInput(trim($input['email']), 'email') : ($currentUser['email'] ?? null);
    $phone_number = isset($input['phone_number']) ? sanitizeInput(trim($input['phone_number']), 'string') : null;
    $student_id = isset($input['student_id']) ? sanitizeInput(trim($input['student_id']), 'string') : null;
    $department = isset($input['department']) ? sanitizeInput(trim($input['department']), 'string') : null;
    
    // Input validation
    if (strlen($full_name) > 200) {
        $db->close();
        sendResponse(false, null, null, 'Ad Soyad çok uzun (maksimum 200 karakter)');
    }
    
    // E-posta kontrolü (varsa)
    if (!empty($email) && !validateEmail($email)) {
        $db->close();
        sendResponse(false, null, null, 'Geçerli bir e-posta adresi giriniz');
    }
    
    // Telefon numarası kontrolü (varsa)
    if (!empty($phone_number) && !validatePhone($phone_number)) {
        $db->close();
        sendResponse(false, null, null, 'Geçersiz telefon numarası formatı');
    }
    
    // Zaten üye mi kontrol et
    if (!empty($email)) {
        $check_member = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
        $check_member->bindValue(1, $email, SQLITE3_TEXT);
        $check_member->bindValue(2, $student_id, SQLITE3_TEXT);
        $existing_member = $check_member->execute()->fetchArray(SQLITE3_ASSOC);
        if ($existing_member) {
            $db->close();
            sendResponse(false, null, null, 'Zaten topluluğun üyesisiniz.');
        }
    }
    
    // Bekleyen başvuru var mı kontrol et
    if (!empty($email)) {
        $check_request = $db->prepare("SELECT id, status FROM membership_requests WHERE club_id = 1 AND (user_id = ? OR LOWER(email) = LOWER(?)) ORDER BY created_at DESC LIMIT 1");
        $check_request->bindValue(1, $user_id, SQLITE3_INTEGER);
        $check_request->bindValue(2, $email, SQLITE3_TEXT);
        $existing_request = $check_request->execute()->fetchArray(SQLITE3_ASSOC);
        if ($existing_request) {
            if ($existing_request['status'] === 'pending') {
                $db->close();
                sendResponse(false, null, null, 'Üyelik başvurunuz zaten inceleniyor.');
            } elseif ($existing_request['status'] === 'approved') {
                $db->close();
                sendResponse(false, null, null, 'Üyelik başvurunuz zaten onaylanmış.');
            }
        }
    }
    
    // Membership request ekle
    $user_profile = [
        'id' => $user_id,
        'first_name' => $currentUser['first_name'] ?? '',
        'last_name' => $currentUser['last_name'] ?? '',
        'email' => $email,
        'phone_number' => $phone_number,
        'student_id' => $student_id,
        'department' => $department
    ];
    
    $insert_query = $db->prepare("INSERT INTO membership_requests (club_id, user_id, full_name, email, phone, student_id, department, additional_data) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
    $insert_query->bindValue(1, $user_id, SQLITE3_INTEGER);
    $insert_query->bindValue(2, $full_name, SQLITE3_TEXT);
    $insert_query->bindValue(3, $email, SQLITE3_TEXT);
    $insert_query->bindValue(4, $phone_number, SQLITE3_TEXT);
    $insert_query->bindValue(5, $student_id, SQLITE3_TEXT);
    $insert_query->bindValue(6, $department, SQLITE3_TEXT);
    $insert_query->bindValue(7, json_encode($user_profile, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    
    if ($insert_query->execute()) {
        $request_id = $db->lastInsertRowID();
        $db->close();
        // Hem request_id hem de member_id döndür (member_id request_id olarak kullanılabilir)
        sendResponse(true, ['request_id' => (int)$request_id, 'member_id' => (string)$request_id], 'Üyelik başvurunuz alındı. Onaylandığında bilgilendirileceksiniz.');
    } else {
        $db->close();
        sendResponse(false, null, null, 'Başvuru sırasında bir hata oluştu');
    }
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('Kayıt işlemi sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

