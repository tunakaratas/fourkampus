<?php
/**
 * Lazy Loading API - Events
 * AJAX endpoint for loading events with pagination
 */

header('Content-Type: application/json; charset=utf-8');

// Session başlat (Auth için)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$community_id = isset($_GET['community_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['community_id']) : '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if (empty($community_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Community ID required']);
    exit;
}

try {
    // Database yolunu belirle
    $db_path = __DIR__ . '/../communities/' . $community_id . '/unipanel.sqlite';
    if (!file_exists($db_path)) {
        throw new Exception("Database not found for community: $community_id");
    }

    $db = new \SQLite3($db_path);
    $db->busyTimeout(5000);
    
    // Toplam sayı
    $total_count = $db->querySingle("SELECT COUNT(*) FROM events") ?: 0;
    
    // Etkinlikleri çek
    $sql = "SELECT e.*, 
            (SELECT COUNT(*) FROM event_rsvp r WHERE r.event_id = e.id) as rsvp_count
            FROM events e
            WHERE e.club_id = 1
            ORDER BY e.date DESC, e.time DESC, e.id DESC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    $events = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $events[] = $row;
    }
    
    $has_more = ($offset + count($events)) < $total_count;
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'has_more' => $has_more,
        'total' => $total_count,
        'offset' => $offset,
        'limit' => $limit
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
