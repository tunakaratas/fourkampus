<?php
/**
 * Event Survey API Endpoint
 * RESTful API for event surveys
 * 
 * GET    /api/event_survey.php?community_id={id}&event_id={id} - Get survey for event
 * POST   /api/event_survey.php?community_id={id}&event_id={id} - Create/Update survey
 * POST   /api/event_survey.php?community_id={id}&event_id={id}&action=submit - Submit survey response
 * GET    /api/event_survey.php?community_id={id}&event_id={id}&action=responses - Get survey responses (admin)
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
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
    // Community ID kontrolü
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
    
    // Event ID kontrolü
    if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
        sendResponse(false, null, null, 'event_id parametresi gerekli');
    }
    
    $event_id = (int)$_GET['event_id'];
    
    // Event var mı kontrol et
    $event_check = $db->prepare("SELECT id, title FROM events WHERE id = ? LIMIT 1");
    if (!$event_check) {
        sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
    }
    $event_check->bindValue(1, $event_id, SQLITE3_INTEGER);
    $event_result = $event_check->execute();
    if (!$event_result) {
        sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
    }
    $event = $event_result->fetchArray(SQLITE3_ASSOC);
    
    if (!$event) {
        sendResponse(false, null, null, 'Etkinlik bulunamadı');
    }
    
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Tabloları oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS event_surveys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL,
        club_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        question_text TEXT NOT NULL,
        question_type TEXT DEFAULT 'multiple_choice',
        display_order INTEGER DEFAULT 0,
        options TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES event_surveys(id) ON DELETE CASCADE
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS survey_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER NOT NULL,
        option_text TEXT NOT NULL,
        display_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        option_id INTEGER,
        response_text TEXT,
        user_email TEXT,
        user_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES event_surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES survey_options(id) ON DELETE CASCADE
    )");
    
    // Eksik kolonları kontrol et ve ekle
    $table_info = $db->query("PRAGMA table_info(survey_responses)");
    if (!$table_info) {
        sendResponse(false, null, null, 'Tablo bilgisi alınamadı: ' . $db->lastErrorMsg());
    }
    $columns = [];
    while ($row = $table_info->fetchArray(SQLITE3_ASSOC)) {
        $columns[$row['name']] = true;
    }
    
    if (!isset($columns['user_email'])) {
        $db->exec("ALTER TABLE survey_responses ADD COLUMN user_email TEXT");
    }
    if (!isset($columns['user_name'])) {
        $db->exec("ALTER TABLE survey_responses ADD COLUMN user_name TEXT");
    }
    
    // GET - Survey'i getir
    if ($method === 'GET' && $action !== 'responses') {
        $currentUser = optionalAuth();
        $user_email = $currentUser['email'] ?? $_GET['user_email'] ?? '';
        
        $survey_query = $db->prepare("SELECT * FROM event_surveys WHERE event_id = ? LIMIT 1");
        if (!$survey_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $survey_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $survey_result = $survey_query->execute();
        if (!$survey_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $survey = $survey_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$survey) {
            sendResponse(true, null, 'Anket bulunamadı');
        }
        
        // Kullanıcının mevcut cevaplarını getir (eğer varsa)
        $user_responses = [];
        if (!empty($user_email)) {
            $responses_query = $db->prepare("
                SELECT question_id, option_id, response_text 
                FROM survey_responses 
                WHERE survey_id = ? AND user_email = ?
            ");
            if (!$responses_query) {
                // Hata durumunda devam et, sadece user_responses boş kalır
                error_log("Survey responses query prepare failed: " . $db->lastErrorMsg());
            } else {
                $responses_query->bindValue(1, $survey['id'], SQLITE3_INTEGER);
                $responses_query->bindValue(2, $user_email, SQLITE3_TEXT);
                $responses_result = $responses_query->execute();
                if (!$responses_result) {
                    // Hata durumunda devam et, sadece user_responses boş kalır
                    error_log("Survey responses query execute failed: " . $db->lastErrorMsg());
                } else {
                    while ($resp_row = $responses_result->fetchArray(SQLITE3_ASSOC)) {
                        if ($resp_row) {
                            $user_responses[(int)$resp_row['question_id']] = [
                                'option_id' => $resp_row['option_id'] ? (int)$resp_row['option_id'] : null,
                                'response_text' => $resp_row['response_text'] ?? null
                            ];
                        }
                    }
                }
            }
        }
        
        // Soruları getir
        $questions_query = $db->prepare("
            SELECT sq.*, 
                   GROUP_CONCAT(so.id || '::' || so.option_text || '::' || so.display_order, '|||') as options_list
            FROM survey_questions sq
            LEFT JOIN survey_options so ON sq.id = so.question_id
            WHERE sq.survey_id = ?
            GROUP BY sq.id
            ORDER BY sq.display_order ASC
        ");
        if (!$questions_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $questions_query->bindValue(1, $survey['id'], SQLITE3_INTEGER);
        $questions_result = $questions_query->execute();
        if (!$questions_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        
        $questions = [];
        while ($q_row = $questions_result->fetchArray(SQLITE3_ASSOC)) {
            if (!$q_row) {
                break; // No more rows
            }
            $options = [];
            if (!empty($q_row['options_list'])) {
                $options_raw = explode('|||', $q_row['options_list']);
                foreach ($options_raw as $opt_str) {
                    $opt_parts = explode('::', $opt_str);
                    if (count($opt_parts) >= 2) {
                        $options[] = [
                            'id' => (int)$opt_parts[0],
                            'text' => $opt_parts[1],
                            'order' => isset($opt_parts[2]) ? (int)$opt_parts[2] : 0
                        ];
                    }
                }
                // Sort by order
                usort($options, function($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
            }
            
            $question_id = (int)$q_row['id'];
            $user_response = $user_responses[$question_id] ?? null;
            
            $questions[] = [
                'id' => $question_id,
                'question_text' => $q_row['question_text'],
                'question_type' => $q_row['question_type'] ?? 'multiple_choice',
                'display_order' => (int)($q_row['display_order'] ?? 0),
                'options' => $options,
                'user_response' => $user_response // Kullanıcının cevabı
            ];
        }
        
        sendResponse(true, [
            'id' => (int)$survey['id'],
            'event_id' => (int)$survey['event_id'],
            'title' => $survey['title'],
            'description' => $survey['description'] ?? null,
            'is_active' => (bool)($survey['is_active'] ?? 1),
            'questions' => $questions,
            'created_at' => $survey['created_at'] ?? null,
            'updated_at' => $survey['updated_at'] ?? null,
            'has_user_response' => !empty($user_responses)
        ]);
    }
    
    // GET - Survey responses (admin only)
    if ($method === 'GET' && $action === 'responses') {
        $currentUser = requireAuth(true);
        
        $survey_query = $db->prepare("SELECT id FROM event_surveys WHERE event_id = ? LIMIT 1");
        $survey_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $survey_result = $survey_query->execute();
        $survey = $survey_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$survey) {
            sendResponse(false, null, null, 'Anket bulunamadı');
        }
        
        $survey_id = $survey['id'];
        
        // Questions with response counts
        $questions_query = $db->prepare("
            SELECT sq.*,
                   COUNT(DISTINCT sr.id) as total_responses
            FROM survey_questions sq
            LEFT JOIN survey_responses sr ON sq.id = sr.question_id
            WHERE sq.survey_id = ?
            GROUP BY sq.id
            ORDER BY sq.display_order ASC
        ");
        $questions_query->bindValue(1, $survey_id, SQLITE3_INTEGER);
        $questions_result = $questions_query->execute();
        
        $questions_data = [];
        while ($q_row = $questions_result->fetchArray(SQLITE3_ASSOC)) {
            if (!$q_row) {
                break; // No more rows
            }
            $question_id = (int)$q_row['id'];
            
            // Options with response counts
            $options_query = $db->prepare("
                SELECT so.*,
                       COUNT(sr.id) as response_count
                FROM survey_options so
                LEFT JOIN survey_responses sr ON so.id = sr.option_id AND sr.question_id = ?
                WHERE so.question_id = ?
                GROUP BY so.id
                ORDER BY so.display_order ASC
            ");
            $options_query->bindValue(1, $question_id, SQLITE3_INTEGER);
            $options_query->bindValue(2, $question_id, SQLITE3_INTEGER);
            $options_result = $options_query->execute();
            if (!$options_result) {
                // Hata durumunda devam et, sadece bu soru için options boş kalır
                error_log("Options query execute failed: " . $db->lastErrorMsg());
                $options_data = [];
            } else {
                $options_data = [];
                while ($opt_row = $options_result->fetchArray(SQLITE3_ASSOC)) {
                    if (!$opt_row) {
                        break; // No more rows
                    }
                    $options_data[] = [
                        'id' => (int)$opt_row['id'],
                        'text' => $opt_row['option_text'],
                        'response_count' => (int)($opt_row['response_count'] ?? 0)
                    ];
                }
            }
            
            $questions_data[] = [
                'id' => $question_id,
                'question_text' => $q_row['question_text'],
                'question_type' => $q_row['question_type'] ?? 'multiple_choice',
                'total_responses' => (int)($q_row['total_responses'] ?? 0),
                'options' => $options_data
            ];
        }
        
        // Total participants
        $participants_query = $db->prepare("
            SELECT COUNT(DISTINCT user_email) as participant_count
            FROM survey_responses
            WHERE survey_id = ?
        ");
        if (!$participants_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $participants_query->bindValue(1, $survey_id, SQLITE3_INTEGER);
        $participants_result = $participants_query->execute();
        if (!$participants_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $participants_row = $participants_result->fetchArray(SQLITE3_ASSOC);
        
        sendResponse(true, [
            'survey_id' => $survey_id,
            'participant_count' => (int)($participants_row['participant_count'] ?? 0),
            'questions' => $questions_data
        ]);
    }
    
    // POST - Create/Update survey
    if ($method === 'POST' && $action !== 'submit') {
        $currentUser = requireAuth(true);
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $questions = $input['questions'] ?? [];
        
        if (empty($title)) {
            sendResponse(false, null, null, 'Anket başlığı gerekli');
        }
        
        // Mevcut anketi kontrol et
        $existing_query = $db->prepare("SELECT id FROM event_surveys WHERE event_id = ? LIMIT 1");
        $existing_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $existing_result = $existing_query->execute();
        $existing = $existing_result->fetchArray(SQLITE3_ASSOC);
        
        if ($existing) {
            // Update
            $update_stmt = $db->prepare("
                UPDATE event_surveys 
                SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $update_stmt->bindValue(1, $title, SQLITE3_TEXT);
            $update_stmt->bindValue(2, $description, SQLITE3_TEXT);
            $update_stmt->bindValue(3, $existing['id'], SQLITE3_INTEGER);
            
            if (!$update_stmt->execute()) {
                sendResponse(false, null, null, 'Anket güncellenemedi: ' . $db->lastErrorMsg());
            }
            
            $survey_id = $existing['id'];
            
            // Eski soruları sil
            $delete_questions = $db->prepare("DELETE FROM survey_questions WHERE survey_id = ?");
            $delete_questions->bindValue(1, $survey_id, SQLITE3_INTEGER);
            $delete_questions->execute();
        } else {
            // Create
            // Event'ten club_id'yi al
            $event_club_query = $db->prepare("SELECT club_id FROM events WHERE id = ? LIMIT 1");
            if (!$event_club_query) {
                sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
            }
            $event_club_query->bindValue(1, $event_id, SQLITE3_INTEGER);
            $event_club_result = $event_club_query->execute();
            if (!$event_club_result) {
                sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
            }
            $event_club = $event_club_result->fetchArray(SQLITE3_ASSOC);
            $club_id = $event_club ? (int)$event_club['club_id'] : 1;
            
            $insert_stmt = $db->prepare("
                INSERT INTO event_surveys (event_id, club_id, title, description) 
                VALUES (?, ?, ?, ?)
            ");
            if (!$insert_stmt) {
                sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
            }
            $insert_stmt->bindValue(1, $event_id, SQLITE3_INTEGER);
            $insert_stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
            $insert_stmt->bindValue(3, $title, SQLITE3_TEXT);
            $insert_stmt->bindValue(4, $description, SQLITE3_TEXT);
            
            if (!$insert_stmt->execute()) {
                sendResponse(false, null, null, 'Anket oluşturulamadı: ' . $db->lastErrorMsg());
            }
            
            $survey_id = $db->lastInsertRowID();
            
            // Event'i has_survey olarak işaretle
            $update_event = $db->prepare("UPDATE events SET has_survey = 1 WHERE id = ?");
            $update_event->bindValue(1, $event_id, SQLITE3_INTEGER);
            $update_event->execute();
        }
        
        // Soruları ekle
        if (!empty($questions) && is_array($questions)) {
            foreach ($questions as $order => $question_data) {
                if (is_array($question_data)) {
                    $question_text = trim($question_data['question_text'] ?? $question_data['text'] ?? '');
                    $question_type = $question_data['question_type'] ?? 'multiple_choice';
                    $options = $question_data['options'] ?? [];
                } else {
                    $question_text = trim($question_data);
                    $question_type = 'multiple_choice';
                    $options = [];
                }
                
                if (empty($question_text)) {
                    continue;
                }
                
                $q_stmt = $db->prepare("
                    INSERT INTO survey_questions (survey_id, question_text, question_type, display_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $q_stmt->bindValue(1, $survey_id, SQLITE3_INTEGER);
                $q_stmt->bindValue(2, $question_text, SQLITE3_TEXT);
                $q_stmt->bindValue(3, $question_type, SQLITE3_TEXT);
                $q_stmt->bindValue(4, (int)$order, SQLITE3_INTEGER);
                
                if (!$q_stmt->execute()) {
                    continue; // Skip on error
                }
                
                $question_id = $db->lastInsertRowID();
                
                // Seçenekleri ekle
                if (!empty($options) && is_array($options)) {
                    foreach ($options as $opt_order => $option_data) {
                        if (is_array($option_data)) {
                            $option_text = trim($option_data['text'] ?? $option_data['option_text'] ?? '');
                        } else {
                            $option_text = trim($option_data);
                        }
                        
                        if (empty($option_text)) {
                            continue;
                        }
                        
                        $opt_stmt = $db->prepare("
                            INSERT INTO survey_options (question_id, option_text, display_order) 
                            VALUES (?, ?, ?)
                        ");
                        $opt_stmt->bindValue(1, $question_id, SQLITE3_INTEGER);
                        $opt_stmt->bindValue(2, $option_text, SQLITE3_TEXT);
                        $opt_stmt->bindValue(3, (int)$opt_order, SQLITE3_INTEGER);
                        $opt_stmt->execute();
                    }
                }
            }
        }
        
        sendResponse(true, ['survey_id' => (int)$survey_id], 'Anket başarıyla kaydedildi');
    }
    
    // POST - Submit survey response
    if ($method === 'POST' && $action === 'submit') {
        $currentUser = optionalAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $user_email = trim($input['user_email'] ?? '');
        $user_name = trim($input['user_name'] ?? '');
        $responses = $input['responses'] ?? [];
        
        if (empty($user_email)) {
            sendResponse(false, null, null, 'E-posta adresi gerekli');
        }
        
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, null, null, 'Geçersiz e-posta adresi');
        }
        
        // Üyelik kontrolü - members tablosundan e-posta ile kontrol et
        $user_email_lower = strtolower($user_email);
        $student_id = trim($currentUser['student_id'] ?? '');
        // Üyelik kontrolü - hem members hem de approved membership_requests
        $is_member = false;
        
        // Önce members tablosunu kontrol et
        $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
        if ($member_check) {
            $member_check->bindValue(1, $user_email_lower, SQLITE3_TEXT);
            $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
            $member_result = $member_check->execute();
            if ($member_result) {
                $member = $member_result->fetchArray(SQLITE3_ASSOC);
                if ($member) {
                    $is_member = true;
                }
            }
        }
        
        // Eğer members tablosunda yoksa, approved membership_requests'i kontrol et
        if (!$is_member) {
            $request_check = $db->prepare("SELECT id, status FROM membership_requests WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) ORDER BY created_at DESC LIMIT 1");
            if ($request_check) {
                $request_check->bindValue(1, $user_email_lower, SQLITE3_TEXT);
                $request_check->bindValue(2, $student_id, SQLITE3_TEXT);
                $request_result = $request_check->execute();
                if ($request_result) {
                    $request = $request_result->fetchArray(SQLITE3_ASSOC);
                    if ($request && ($request['status'] ?? '') === 'approved') {
                        $is_member = true;
                    }
                }
            }
        }
        
        if (!$is_member) {
            sendResponse(false, null, null, 'Bu ankete katılmak için topluluğa üye olmanız gerekiyor');
        }
        
        // Survey'i getir
        $survey_query = $db->prepare("SELECT id FROM event_surveys WHERE event_id = ? AND is_active = 1 LIMIT 1");
        if (!$survey_query) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $survey_query->bindValue(1, $event_id, SQLITE3_INTEGER);
        $survey_result = $survey_query->execute();
        if (!$survey_result) {
            sendResponse(false, null, null, 'SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $survey = $survey_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$survey) {
            sendResponse(false, null, null, 'Aktif anket bulunamadı');
        }
        
        $survey_id = $survey['id'];
        
        // Önceki yanıtları sil (aynı kullanıcı tekrar yanıtlayabilir)
        $delete_old = $db->prepare("DELETE FROM survey_responses WHERE survey_id = ? AND user_email = ?");
        if (!$delete_old) {
            sendResponse(false, null, null, 'SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $delete_old->bindValue(1, $survey_id, SQLITE3_INTEGER);
        $delete_old->bindValue(2, $user_email, SQLITE3_TEXT);
        if (!$delete_old->execute()) {
            sendResponse(false, null, null, 'Eski yanıtlar silinemedi: ' . $db->lastErrorMsg());
        }
        
        // Yanıtları kaydet
        if (!empty($responses) && is_array($responses)) {
            foreach ($responses as $response_data) {
                $question_id = (int)($response_data['question_id'] ?? 0);
                $option_id = isset($response_data['option_id']) ? (int)$response_data['option_id'] : null;
                $response_text = isset($response_data['response_text']) ? trim($response_data['response_text']) : null;
                
                if ($question_id <= 0) {
                    continue;
                }
                
                $resp_stmt = $db->prepare("
                    INSERT INTO survey_responses (survey_id, question_id, option_id, response_text, user_email, user_name) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if (!$resp_stmt) {
                    continue; // Skip on error
                }
                $resp_stmt->bindValue(1, $survey_id, SQLITE3_INTEGER);
                $resp_stmt->bindValue(2, $question_id, SQLITE3_INTEGER);
                $resp_stmt->bindValue(3, $option_id, $option_id ? SQLITE3_INTEGER : SQLITE3_NULL);
                $resp_stmt->bindValue(4, $response_text, $response_text ? SQLITE3_TEXT : SQLITE3_NULL);
                $resp_stmt->bindValue(5, $user_email, SQLITE3_TEXT);
                $resp_stmt->bindValue(6, $user_name, $user_name ? SQLITE3_TEXT : SQLITE3_NULL);
                if (!$resp_stmt->execute()) {
                    continue; // Skip on error
                }
            }
        }
        
        sendResponse(true, null, 'Anket yanıtınız başarıyla kaydedildi');
    }
    
    sendResponse(false, null, null, 'Geçersiz istek');
    
} catch (Exception $e) {
    error_log("Event Survey API Error: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    sendResponse(false, null, null, 'Sunucu hatası: ' . $e->getMessage());
} catch (Error $e) {
    error_log("Event Survey API Fatal Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, null, null, 'Kritik hata: ' . $e->getMessage());
} finally {
    if (isset($db)) {
        $db->close();
    }
}

