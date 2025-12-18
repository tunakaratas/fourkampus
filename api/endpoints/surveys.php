<?php
/**
 * Mobil API - Surveys Endpoint
 * GET /api/surveys.php?community_id={id} - Topluluğa ait anketleri listele
 * GET /api/surveys.php?community_id={id}&event_id={id} - Belirli bir etkinliğe ait anket
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    
    // Surveys tablosunun var olup olmadığını kontrol et
    $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='surveys'");
    if (!$table_check || !$table_check->fetchArray()) {
        // Tablo yoksa event_surveys tablosunu kontrol et
        $event_surveys_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_surveys'");
        if (!$event_surveys_check || !$event_surveys_check->fetchArray()) {
            // Hiçbir anket tablosu yoksa boş array döndür
            $db->close();
            sendResponse(true, []);
        } else {
            // event_surveys tablosunu kullan
            $use_event_surveys = true;
        }
    } else {
        $use_event_surveys = false;
    }
    
    // Event ID varsa sadece o etkinliğin anketini getir
    if (isset($_GET['event_id']) && !empty($_GET['event_id'])) {
        $event_id = (int)$_GET['event_id'];
        if (isset($use_event_surveys) && $use_event_surveys) {
            $query = $db->prepare("SELECT * FROM event_surveys WHERE event_id = ? ORDER BY created_at DESC LIMIT 1");
        } else {
            $query = $db->prepare("SELECT * FROM surveys WHERE event_id = ? ORDER BY created_at DESC LIMIT 1");
        }
        if (!$query) {
            $db->close();
            sendResponse(true, []);
        }
        $query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $result = $query->execute();
    } else {
        // Tüm anketleri getir
        if (isset($use_event_surveys) && $use_event_surveys) {
            $query = $db->prepare("SELECT * FROM event_surveys ORDER BY created_at DESC");
        } else {
            $query = $db->prepare("SELECT * FROM surveys ORDER BY created_at DESC");
        }
        if (!$query) {
            $db->close();
            sendResponse(true, []);
        }
        $result = $query->execute();
    }
    
    if (!$result) {
        $db->close();
        sendResponse(true, []);
    }
    
    $surveys = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Soruları çek
        $questions_query = $db->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY question_order ASC");
        $questions_query->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $questions_result = $questions_query->execute();
        
        $questions = [];
        while ($q_row = $questions_result->fetchArray(SQLITE3_ASSOC)) {
            $questions[] = [
                'id' => (int)$q_row['id'],
                'question_text' => $q_row['question_text'] ?? '',
                'question_type' => $q_row['question_type'] ?? 'text',
                'question_order' => (int)($q_row['question_order'] ?? 0),
                'options' => !empty($q_row['options']) ? json_decode($q_row['options'], true) : null
            ];
        }
        
        $surveys[] = [
            'id' => (int)$row['id'],
            'event_id' => isset($row['event_id']) ? (int)$row['event_id'] : null,
            'title' => $row['title'] ?? '',
            'description' => $row['description'] ?? null,
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'questions' => $questions
        ];
    }
    
    $db->close();
    sendResponse(true, $surveys);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

