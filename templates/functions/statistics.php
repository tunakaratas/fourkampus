<?php
/**
 * Statistics Module - Lazy Loaded
 */

// Import namespace'leri
use UniPanel\Core\Database;
use UniPanel\Core\ErrorHandler;

function stats_events_table_exists(SQLite3 $db) {
    static $hasEvents = null;
    if ($hasEvents === null) {
        $hasEvents = (bool) @$db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
    }
    return $hasEvents;
}

function get_event_attendance_monthly() {
    $db = get_db();
    
    $events_table_exists = stats_events_table_exists($db);
    if ($events_table_exists) {
        load_module('events');
        ensure_events_table_columns($db);
        ensure_event_rsvp_table($db);
    }
    
    $data = [];
    
    // Son 12 ay için aylık katılım verileri
    for ($i = 11; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        
        $count = 0;
        if ($events_table_exists) {
            $stmt = @$db->prepare("SELECT COUNT(DISTINCT er.id) as count FROM event_rsvp er 
                                  INNER JOIN events e ON er.event_id = e.id 
                                  WHERE e.club_id = ? AND er.rsvp_status = 'attending' 
                                  AND e.date >= ? AND e.date <= ?");
            if ($stmt) {
                $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                $stmt->bindValue(2, $month_start, SQLITE3_TEXT);
                $stmt->bindValue(3, $month_end, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $count = (int)($row['count'] ?? 0);
                }
            }
        }
        
        $data[] = [
            'month' => $month_label,
            'count' => $count
        ];
    }
    
    return $data;
}


function get_event_attendance_yearly() {
    $db = get_db();
    
    $events_table_exists = stats_events_table_exists($db);
    if ($events_table_exists) {
        load_module("events");
        ensure_events_table_columns($db);
        ensure_event_rsvp_table($db);
    }
    
    $data = [];
    
    // Son 5 yıl için yıllık katılım verileri
    for ($i = 4; $i >= 0; $i--) {
        $year = date('Y', strtotime("-$i years"));
        $year_start = "$year-01-01";
        $year_end = "$year-12-31";
        
        $count = 0;
        if ($events_table_exists) {
            $stmt = @$db->prepare("SELECT COUNT(DISTINCT er.id) as count FROM event_rsvp er 
                                  INNER JOIN events e ON er.event_id = e.id 
                                  WHERE e.club_id = ? AND er.rsvp_status = 'attending' 
                                  AND e.date >= ? AND e.date <= ?");
            if ($stmt) {
                $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                $stmt->bindValue(2, $year_start, SQLITE3_TEXT);
                $stmt->bindValue(3, $year_end, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $count = (int)($row['count'] ?? 0);
                }
            }
        }
        
        $data[] = [
            'year' => $year,
            'count' => $count
        ];
    }
    
    return $data;
}


function get_member_growth() {
    $db = get_db();
    $data = [];
    
    // Son 12 ay için aylık üye büyümesi
    for ($i = 11; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM members 
                              WHERE club_id = ? AND registration_date >= ? AND registration_date <= ?");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, $month_start, SQLITE3_TEXT);
        $stmt->bindValue(3, $month_end, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        $data[] = [
            'month' => $month_label,
            'count' => (int)($row['count'] ?? 0)
        ];
    }
    
    return $data;
}


function get_event_category_distribution() {
    $db = get_db();
    $events_table_exists = stats_events_table_exists($db);
    if (!$events_table_exists) {
        return [];
    }
    
    // Events tablosuna eksik kolonları ekle (category dahil)
    load_module("events"); ensure_events_table_columns($db);
    
    // events tablosuna category kolonu yoksa ekle
    try {
        // Önce kolonun varlığını kontrol et
        $table_info = $db->query("PRAGMA table_info(events)");
        $has_category = false;
        while ($row = $table_info->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'category') {
                $has_category = true;
                break;
            }
        }
        
        // Kolon yoksa ekle
        if (!$has_category) {
            $db->exec("ALTER TABLE events ADD COLUMN category TEXT DEFAULT 'Genel'");
        }
    } catch (Exception $e) {
        // Hata durumunda devam et
    }
    
    $data = [];
    
    // Category kolonunun varlığını tekrar kontrol et
    $table_info = $db->query("PRAGMA table_info(events)");
    $has_category = false;
    while ($row = $table_info->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'category') {
            $has_category = true;
            break;
        }
    }
    
    if ($has_category) {
        $stmt = $db->prepare("SELECT category, COUNT(*) as count FROM events 
                              WHERE club_id = ? AND (category IS NOT NULL AND category != '') 
                              GROUP BY category ORDER BY count DESC");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = [
                'category' => $row['category'] ?: 'Genel',
                'count' => (int)$row['count']
            ];
        }
    }
    
    // Eğer kategori yoksa veya data boşsa, tüm etkinlikleri "Genel" olarak göster
    if (empty($data)) {
        $stmt = @$db->prepare("SELECT COUNT(*) as count FROM events WHERE club_id = ?");
        if ($stmt) {
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result) {
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if ($row && $row['count'] > 0) {
                    $data[] = [
                        'category' => 'Genel',
                        'count' => (int)$row['count']
                    ];
                }
            }
        }
    }
    
    return $data;
}


function get_most_active_members() {
    $db = get_db();
    $events_table_exists = stats_events_table_exists($db);
    if (!$events_table_exists) {
        return [];
    }
    
    // Events tablosuna eksik kolonları ekle
    load_module("events"); ensure_events_table_columns($db);
    
    // Event RSVP tablosunu oluştur
    ensure_event_rsvp_table($db);
    
    $data = [];
    
    // SQLite için optimize edilmiş sorgu - subquery ile
    $stmt = @$db->prepare("SELECT m.full_name, m.email, 
                          (SELECT COUNT(*) FROM event_rsvp er 
                           INNER JOIN events e ON er.event_id = e.id 
                           WHERE er.member_email = m.email 
                           AND er.rsvp_status = 'attending' 
                           AND e.club_id = ?) as attendance_count
                          FROM members m 
                          WHERE m.club_id = ?
                          ORDER BY attendance_count DESC
                          LIMIT 10");
    if (!$stmt) {
        return [];
    }
    $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $attendance_count = (int)$row['attendance_count'];
        // Sadece katılımı olan üyeleri ekle
        if ($attendance_count > 0) {
            $data[] = [
                'name' => $row['full_name'],
                'email' => $row['email'],
                'attendance_count' => $attendance_count
            ];
        }
    }
    
    return $data;
}


function get_event_success_rates() {
    $db = get_db();
    $events_table_exists = stats_events_table_exists($db);
    if (!$events_table_exists) {
        return [];
    }
    
    // Events tablosuna eksik kolonları ekle
    load_module("events"); ensure_events_table_columns($db);
    
    // Event RSVP tablosunu oluştur
    ensure_event_rsvp_table($db);
    
    $data = [];
    
    // Etkinlik başarı oranları (katılım oranına göre)
    $stmt = @$db->prepare("SELECT e.id, e.title, e.date, 
                          COUNT(DISTINCT er.id) as attending_count,
                          COALESCE(e.max_attendees, 0) as max_attendees,
                          CASE 
                              WHEN COALESCE(e.max_attendees, 0) > 0 
                              THEN ROUND((COUNT(DISTINCT er.id) * 100.0 / e.max_attendees), 1)
                              ELSE 0
                          END as success_rate
                          FROM events e
                          LEFT JOIN event_rsvp er ON e.id = er.event_id AND er.rsvp_status = 'attending'
                          WHERE e.club_id = ? AND e.date < date('now')
                          GROUP BY e.id, e.title, e.date, e.max_attendees
                          ORDER BY e.date DESC
                          LIMIT 20");
    if (!$stmt) {
        return [];
    }
    $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = [
            'title' => $row['title'],
            'date' => $row['date'],
            'attending_count' => (int)$row['attending_count'],
            'max_attendees' => (int)$row['max_attendees'],
            'success_rate' => (float)$row['success_rate']
        ];
    }
    
    return $data;
}


function get_email_sms_statistics() {
    $db = get_db();
    
    // Email tablolarını oluştur
    ensure_email_tables($db);
    
    // Rate limits tablosunu oluştur
    ensure_rate_limits_table($db);
    
    $data = [
        'email' => [
            'total_campaigns' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'monthly_sent' => []
        ],
        'sms' => [
            'total_sent' => 0,
            'monthly_sent' => []
        ]
    ];
    
    // Email istatistikleri
    $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(total_recipients) as total, 
                          SUM(sent_count) as sent, SUM(failed_count) as failed 
                          FROM email_campaigns WHERE club_id = ?");
    $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        $data['email']['total_campaigns'] = (int)$row['count'];
        $data['email']['total_sent'] = (int)($row['sent'] ?? 0);
        $data['email']['total_failed'] = (int)($row['failed'] ?? 0);
    }
    
    // Aylık email gönderim istatistikleri
    for ($i = 11; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        
        $stmt = $db->prepare("SELECT SUM(sent_count) as sent FROM email_campaigns 
                              WHERE club_id = ? AND created_at >= ? AND created_at <= ?");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, $month_start, SQLITE3_TEXT);
        $stmt->bindValue(3, $month_end, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        $data['email']['monthly_sent'][] = [
            'month' => $month_label,
            'count' => (int)($row['sent'] ?? 0)
        ];
    }
    
    // SMS istatistikleri (rate_limits tablosundan)
    $stmt = $db->prepare("SELECT SUM(action_count) as total FROM rate_limits 
                          WHERE club_id = ? AND action_type = 'sms'");
    $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $data['sms']['total_sent'] = (int)($row['total'] ?? 0);
    
    // Aylık SMS gönderim istatistikleri
    for ($i = 11; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        
        $stmt = $db->prepare("SELECT SUM(action_count) as sent FROM rate_limits 
                              WHERE club_id = ? AND action_type = 'sms' 
                              AND hour_timestamp >= ? AND hour_timestamp <= ?");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, $month_start, SQLITE3_TEXT);
        $stmt->bindValue(3, $month_end, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        $data['sms']['monthly_sent'][] = [
            'month' => $month_label,
            'count' => (int)($row['sent'] ?? 0)
        ];
    }
    
    return $data;
}

// Export/Import Fonksiyonları

