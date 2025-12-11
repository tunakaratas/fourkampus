<?php
/**
 * Mobil API - Calendar Integration Endpoint
 * GET /api/calendar.php?event_id={id}&community_id={id} - Etkinlik için .ics dosyası oluştur
 * iOS EventKit ile uyumlu .ics formatında takvim dosyası döndürür
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/connection_pool.php';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="event.ics"');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için hemen cevap ver
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
if (!checkRateLimit(100, 60)) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    $community_id = isset($_GET['community_id']) ? sanitizeCommunityId($_GET['community_id']) : null;
    
    if (!$event_id || !$community_id) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'event_id ve community_id parametreleri gerekli'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Topluluk bulunamadı'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Connection pool kullan
    $connResult = ConnectionPool::getConnection($db_path, true);
    if (!$connResult) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Veritabanı bağlantısı kurulamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = $connResult['db'];
    $poolId = $connResult['pool_id'];
    
    // Etkinlik bilgilerini çek
    $query = $db->prepare("SELECT * FROM events WHERE id = ? AND club_id = 1");
    $query->bindValue(1, $event_id, SQLITE3_INTEGER);
    $result = $query->execute();
    $event = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$event) {
        ConnectionPool::releaseConnection($db_path, $poolId, true);
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Etkinlik bulunamadı'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Topluluk adı
    $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
    $settings = [];
    while ($setting_row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
        $settings[$setting_row['setting_key']] = $setting_row['setting_value'];
    }
    $community_name = $settings['club_name'] ?? $community_id;
    
    // Bağlantıyı pool'a geri ver
    ConnectionPool::releaseConnection($db_path, $poolId, true);
    
    // .ics dosyası oluştur
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    // Tarih ve saat bilgilerini parse et
    $start_date = $event['date'] ?? date('Y-m-d');
    $start_time = $event['time'] ?? '00:00';
    $end_time = $event['end_time'] ?? null;
    $end_datetime = $event['end_datetime'] ?? null;
    
    // Başlangıç datetime
    $start_datetime_str = $start_date . ' ' . $start_time;
    $start_datetime = new DateTime($start_datetime_str);
    
    // Bitiş datetime
    if ($end_datetime) {
        $end_datetime_obj = new DateTime($end_datetime);
    } elseif ($end_time) {
        $end_datetime_obj = new DateTime($start_date . ' ' . $end_time);
    } else {
        // Varsayılan olarak başlangıçtan 2 saat sonra
        $end_datetime_obj = clone $start_datetime;
        $end_datetime_obj->modify('+2 hours');
    }
    
    // ICS formatında tarih (UTC)
    $start_ics = $start_datetime->format('Ymd\THis\Z');
    $end_ics = $end_datetime_obj->format('Ymd\THis\Z');
    
    // UID oluştur (benzersiz olmalı)
    $uid = 'event-' . $event_id . '-' . $community_id . '@' . parse_url($baseUrl, PHP_URL_HOST);
    
    // Etkinlik başlığı
    $title = $event['title'] ?? 'Etkinlik';
    $title = str_replace(["\r\n", "\r", "\n"], ' ', $title);
    $title = str_replace(',', '\\,', $title);
    
    // Açıklama
    $description = $event['description'] ?? '';
    $description = str_replace(["\r\n", "\r"], '\\n', $description);
    $description = str_replace("\n", '\\n', $description);
    $description = str_replace(',', '\\,', $description);
    
    // Konum
    $location = $event['location'] ?? '';
    $location = str_replace(',', '\\,', $location);
    
    // Organizatör
    $organizer = $event['organizer'] ?? $community_name;
    $organizer = str_replace(',', '\\,', $organizer);
    
    // Oluşturulma zamanı
    $created = isset($event['created_at']) ? new DateTime($event['created_at']) : new DateTime();
    $created_ics = $created->format('Ymd\THis\Z');
    
    // .ics dosyası içeriği
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//UniFour//Event Calendar//TR\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "METHOD:PUBLISH\r\n";
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "UID:" . $uid . "\r\n";
    $ics_content .= "DTSTAMP:" . $created_ics . "\r\n";
    $ics_content .= "DTSTART:" . $start_ics . "\r\n";
    $ics_content .= "DTEND:" . $end_ics . "\r\n";
    $ics_content .= "SUMMARY:" . $title . "\r\n";
    
    if (!empty($description)) {
        $ics_content .= "DESCRIPTION:" . $description . "\r\n";
    }
    
    if (!empty($location)) {
        $ics_content .= "LOCATION:" . $location . "\r\n";
    }
    
    $ics_content .= "ORGANIZER;CN=" . $organizer . "\r\n";
    
    // URL (deep link)
    $ics_content .= "URL;VALUE=URI:unifour://event/" . urlencode($community_id) . "/" . urlencode($event_id) . "\r\n";
    
    // Kategori
    if (!empty($event['category'])) {
        $category = str_replace(',', '\\,', $event['category']);
        $ics_content .= "CATEGORIES:" . $category . "\r\n";
    }
    
    // Durum
    $ics_content .= "STATUS:CONFIRMED\r\n";
    
    // Son güncelleme
    $updated = isset($event['updated_at']) ? new DateTime($event['updated_at']) : new DateTime();
    $updated_ics = $updated->format('Ymd\THis\Z');
    $ics_content .= "LAST-MODIFIED:" . $updated_ics . "\r\n";
    
    $ics_content .= "END:VEVENT\r\n";
    $ics_content .= "END:VCALENDAR\r\n";
    
    // .ics dosyasını döndür
    echo $ics_content;
    
} catch (Exception $e) {
    error_log("Calendar API error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    error_log("Calendar API fatal error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
