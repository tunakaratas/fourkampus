<?php
/**
 * Lazy Loading API - Events
 * AJAX endpoint for loading events with pagination
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// JSON header
header('Content-Type: application/json; charset=utf-8');

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
// Güvenlik kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

    // Club ID'yi al
    $club_id = isset($_SESSION['club_id']) ? (int)$_SESSION['club_id'] : 1;
    
    // Parametreleri al
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 30;
    
    // Veritabanı yolunu session'dan al
    $db_path = isset($_SESSION['db_path']) ? $_SESSION['db_path'] : null;
    
    // Eğer session'da yoksa, referrer'dan veya fallback yöntemle bul
    if (!$db_path || !file_exists($db_path)) {
        // Referrer'dan community path'i çıkar
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referrer && preg_match('#/communities/([^/]+)#', $referrer, $matches)) {
            $community_slug = $matches[1];
            $candidate_path = __DIR__ . '/../communities/' . $community_slug . '/unipanel.sqlite';
            if (file_exists($candidate_path) && is_readable($candidate_path)) {
                $db_path = realpath($candidate_path);
            }
        }
        
        // Hala bulunamadıysa, tüm veritabanlarını tara ve en çok etkinliğe sahip olanı bul
        if (!$db_path || !file_exists($db_path)) {
            $communities_dir = __DIR__ . '/../communities/';
            $max_events = 0;
            $best_db = null;
            
            if (is_dir($communities_dir)) {
                $folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
                if ($folders) {
                    foreach ($folders as $folder) {
                        $test_db = realpath($folder . '/unipanel.sqlite');
                        if ($test_db && file_exists($test_db) && is_readable($test_db)) {
                            try {
                                $test_conn = new SQLite3($test_db, SQLITE3_OPEN_READONLY);
                                $count = (int)($test_conn->querySingle("SELECT COUNT(*) FROM events WHERE club_id = $club_id") ?: 0);
                                $test_conn->close();
                                
                                if ($count > $max_events) {
                                    $max_events = $count;
                                    $best_db = $test_db;
                                }
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }
                }
            }
            
            if ($best_db) {
                $db_path = $best_db;
            }
        }
    }
    
    if (!$db_path || !file_exists($db_path)) {
        throw new Exception('Veritabanı dosyası bulunamadı. Session: ' . (isset($_SESSION['db_path']) ? $_SESSION['db_path'] : 'yok'));
    }
    
    // Veritabanı bağlantısı
    $db = new SQLite3($db_path, SQLITE3_OPEN_READWRITE);
    if (!$db) {
        throw new Exception('Veritabanı açılamadı: ' . $db_path);
    }
    $db->busyTimeout(5000);
    
    // Toplam sayı
    $total_count = (int)($db->querySingle("SELECT COUNT(*) FROM events WHERE club_id = $club_id") ?: 0);
    
    // Etkinlikleri çek
    $sql = "SELECT id, club_id, title, date, time, location, description, category, status, priority, featured, image_path, created_at 
            FROM events 
            WHERE club_id = $club_id 
            ORDER BY date DESC, id DESC 
            LIMIT $limit OFFSET $offset";
    
    $result = $db->query($sql);
    
    if (!$result) {
        $error = $db->lastErrorMsg();
        $db->close();
        throw new Exception("SQL query failed: $error");
    }
    
    $events = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $events[] = $row;
    }
    
    $db->close();
    
    $has_more = ($offset + $limit) < $total_count;
    
    // Response
    $response = [
        'success' => true,
        'events' => $events,
        'has_more' => $has_more,
        'total' => $total_count,
        'offset' => $offset,
        'limit' => $limit
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("load_events.php error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    error_log("load_events.php fatal error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
