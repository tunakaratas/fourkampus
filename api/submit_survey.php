<?php
/**
 * Mobil API - Survey Submission Endpoint
 * POST /api/submit_survey.php - Anket cevaplarını gönder
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication zorunlu
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
    
    if (!isset($_GET['community_id']) || empty($_GET['community_id'])) {
        sendResponse(false, null, null, 'community_id parametresi gerekli');
    }
    
    $community_id = sanitizeCommunityId($_GET['community_id']);
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        sendResponse(false, null, null, 'Topluluk bulunamadı');
    }
    
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['survey_id']) || !isset($input['responses'])) {
        sendResponse(false, null, null, 'survey_id ve responses parametreleri gerekli');
    }
    
    $survey_id = (int)$input['survey_id'];
    $responses = $input['responses']; // {question_id: answer} formatında
    
    if (!is_array($responses) || empty($responses)) {
        sendResponse(false, null, null, 'En az bir cevap gönderilmelidir');
    }
    
    // Survey responses tablosunu oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        user_id TEXT,
        user_email TEXT,
        response_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    )");
    
    // Kullanıcı bilgilerini al
    $user_id = $currentUser['id'] ?? null;
    $user_email = $currentUser['email'] ?? null;
    
    // Mevcut cevapları kontrol et (kullanıcı daha önce cevap vermiş mi?)
    $check_query = $db->prepare("SELECT id FROM survey_responses WHERE survey_id = ? AND (user_id = ? OR user_email = ?) LIMIT 1");
    $check_query->bindValue(1, $survey_id, SQLITE3_INTEGER);
    $check_query->bindValue(2, $user_id, SQLITE3_TEXT);
    $check_query->bindValue(3, $user_email, SQLITE3_TEXT);
    $existing = $check_query->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($existing) {
        // Mevcut cevapları sil
        $delete_query = $db->prepare("DELETE FROM survey_responses WHERE survey_id = ? AND (user_id = ? OR user_email = ?)");
        $delete_query->bindValue(1, $survey_id, SQLITE3_INTEGER);
        $delete_query->bindValue(2, $user_id, SQLITE3_TEXT);
        $delete_query->bindValue(3, $user_email, SQLITE3_TEXT);
        $delete_query->execute();
    }
    
    // Yeni cevapları kaydet
    $insert_query = $db->prepare("INSERT INTO survey_responses (survey_id, question_id, user_id, user_email, response_text) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($responses as $question_id => $response_text) {
        $question_id_int = (int)$question_id;
        $response_text_clean = sanitizeInput(trim($response_text), 'string');
        
        if (empty($response_text_clean)) {
            continue; // Boş cevapları atla
        }
        
        $insert_query->bindValue(1, $survey_id, SQLITE3_INTEGER);
        $insert_query->bindValue(2, $question_id_int, SQLITE3_INTEGER);
        $insert_query->bindValue(3, $user_id, SQLITE3_TEXT);
        $insert_query->bindValue(4, $user_email, SQLITE3_TEXT);
        $insert_query->bindValue(5, $response_text_clean, SQLITE3_TEXT);
        $insert_query->execute();
        $insert_query->reset();
    }
    
    $db->close();
    sendResponse(true, ['survey_id' => $survey_id], 'Anket cevapları başarıyla kaydedildi');
    
} catch (Exception $e) {
    error_log("Survey submission error: " . $e->getMessage());
    if (isset($db)) {
        $db->close();
    }
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

