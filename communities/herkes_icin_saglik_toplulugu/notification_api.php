<?php
// =================================================================
// BİLDİRİM API - TOPLULUK BİLDİRİM SİSTEMİ
// =================================================================

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı yolu
const DB_PATH = 'unipanel.sqlite';
const CLUB_ID = 1;

// Veritabanı bağlantısı
function get_db() {
    static $db = null;
    if ($db === null) {
        if (!file_exists(DB_PATH)) {
            die(json_encode(['error' => 'Veritabanı bulunamadı']));
        }
        try {
            $db = new SQLite3(DB_PATH);
            $db->enableExceptions(true);
        } catch (Exception $e) {
            die(json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]));
        }
    }
    return $db;
}

// JSON header
header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_notifications':
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM notifications WHERE club_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            $notifications = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $notifications[] = $row;
            }
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;
            
        case 'get_notification_count':
            $db = get_db();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE club_id = ? AND is_read = 0");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'get_latest_notification':
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM notifications WHERE club_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $notification = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($notification) {
                echo json_encode(['success' => true, 'notification' => $notification]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bildirim bulunamadı']);
            }
            break;
            
        case 'mark_as_read':
            $notification_id = $_POST['notification_id'] ?? '';
            if (empty($notification_id)) {
                echo json_encode(['success' => false, 'error' => 'Bildirim ID gerekli']);
                break;
            }
            
            $db = get_db();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND club_id = ?");
            $stmt->bindValue(1, $notification_id, SQLITE3_INTEGER);
            $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_notification':
            $notification_id = $_POST['notification_id'] ?? '';
            if (empty($notification_id)) {
                echo json_encode(['success' => false, 'error' => 'Bildirim ID gerekli']);
                break;
            }
            
            $db = get_db();
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND club_id = ?");
            $stmt->bindValue(1, $notification_id, SQLITE3_INTEGER);
            $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $db = get_db();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_all':
            $db = get_db();
            $stmt = $db->prepare("DELETE FROM notifications WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Bildirim API hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()]);
}
?>
