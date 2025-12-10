<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - NOTIFICATIONS API
// =================================================================

require_once __DIR__ . '/../security_helper.php';
secure_session_start();

// Security headers
setSecurityHeaders();

header('Content-Type: application/json; charset=utf-8');

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db_path = __DIR__ . '/../unipanel.sqlite';

if (!file_exists($db_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
    exit;
}

// Güvenli veritabanı bağlantısı
$db = get_safe_db_connection($db_path, false);
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

// İstek metodunu kontrol et
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Bildirimleri getir
    try {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $notifications = [];
        $unread_count = 0;
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notifications[] = $row;
            if ($row['is_read'] == 0) {
                $unread_count++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        // Production'da hassas bilgi sızıntısını önle
        $error_message = handleError('Bildirimler alınamadı', $e);
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
    
} elseif ($method === 'POST') {
    // Bildirimi okundu olarak işaretle
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'mark_read') {
        try {
            if (isset($input['notification_id'])) {
                // Notification ID sanitization
                $notification_id = filter_var($input['notification_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($notification_id === false) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Geçersiz bildirim ID']);
                    $db->close();
                    exit;
                }
                
                // Tekil bildirim
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->bindValue(1, $notification_id, SQLITE3_INTEGER);
                $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                $stmt->execute();
            } elseif (isset($input['mark_all']) && $input['mark_all'] === true) {
                // Tümünü okundu işaretle
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                $stmt->execute();
            }
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(500);
            // Production'da hassas bilgi sızıntısını önle
            $error_message = handleError('İşlem başarısız', $e);
            echo json_encode(['success' => false, 'message' => $error_message]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}

$db->close();
