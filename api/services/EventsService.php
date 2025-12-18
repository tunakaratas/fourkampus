<?php
/**
 * Events Service
 * 
 * Etkinlik işlemleri business logic
 */

require_once __DIR__ . '/../connection_pool.php';
require_once __DIR__ . '/../security_helper.php';

class EventsService {
    
    /**
     * Normalize university ID
     */
    private static function normalizeUniversityId($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = str_replace([' ', '-', '_'], '', $normalized);
        return $normalized;
    }
    
    /**
     * Get all events
     */
    public static function getAll($filters = []) {
        $communities_dir = __DIR__ . '/../../communities';
        $community_id = $filters['community_id'] ?? null;
        $university_id = $filters['university_id'] ?? null;
        
        $events = [];
        
        if ($community_id) {
            // Tek topluluk için
            $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
            if (file_exists($db_path)) {
                $events = self::getEventsFromCommunity($db_path, $community_id);
            }
        } else {
            // Tüm topluluklar için
            $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
            if ($community_folders === false) {
                $community_folders = [];
            }
            
            $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
            
            foreach ($community_folders as $folder_path) {
                $cid = basename($folder_path);
                if (in_array($cid, $excluded_dirs)) {
                    continue;
                }
                
                $db_path = $folder_path . '/unipanel.sqlite';
                if (!file_exists($db_path)) {
                    continue;
                }
                
                // Üniversite filtresi
                if ($university_id) {
                    try {
                        $connResult = ConnectionPool::getConnection($db_path, true);
                        if (!$connResult) {
                            continue;
                        }
                        $db = $connResult['db'];
                        $poolId = $connResult['pool_id'];
                        
                        $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
                        $settings = [];
                        if ($settings_query) {
                            while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                                $settings[$row['setting_key']] = $row['setting_value'];
                            }
                        }
                        
                        $community_university_name = $settings['university'] ?? $settings['organization'] ?? '';
                        $community_university_id = self::normalizeUniversityId($community_university_name);
                        
                        ConnectionPool::releaseConnection($db_path, $poolId, true);
                        
                        if ($community_university_id === '' || $community_university_id !== $university_id) {
                            continue;
                        }
                    } catch (Exception $e) {
                        if (isset($poolId)) {
                            ConnectionPool::releaseConnection($db_path, $poolId, true);
                        }
                        continue;
                    }
                }
                
                $communityEvents = self::getEventsFromCommunity($db_path, $cid);
                $events = array_merge($events, $communityEvents);
            }
        }
        
        // Tarihe göre sırala
        usort($events, function($a, $b) {
            $dateA = ($a['date'] ?? '') . ' ' . ($a['time'] ?? '');
            $dateB = ($b['date'] ?? '') . ' ' . ($b['time'] ?? '');
            return strcmp($dateB, $dateA); // En yeni önce
        });
        
        return $events;
    }
    
    /**
     * Get events from a community
     */
    private static function getEventsFromCommunity($db_path, $community_id) {
        $events = [];
        
        try {
            $connResult = ConnectionPool::getConnection($db_path, true);
            if (!$connResult) {
                return $events;
            }
            $db = $connResult['db'];
            $poolId = $connResult['pool_id'];
            
            $stmt = $db->prepare("SELECT * FROM events WHERE club_id = 1 ORDER BY date DESC, time DESC");
            $result = $stmt->execute();
            
            if ($result) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $row['community_id'] = $community_id;
                    $events[] = $row;
                }
            }
            
            ConnectionPool::releaseConnection($db_path, $poolId, true);
        } catch (Exception $e) {
            if (isset($poolId)) {
                ConnectionPool::releaseConnection($db_path, $poolId, true);
            }
            error_log("Events Service error: " . $e->getMessage());
        }
        
        return $events;
    }
    
    /**
     * Get event by ID
     */
    public static function getById($eventId, $communityId) {
        try {
            $communityId = sanitizeCommunityId($communityId);
        } catch (Exception $e) {
            return null;
        }
        
        $db_path = __DIR__ . '/../../communities/' . $communityId . '/unipanel.sqlite';
        
        if (!file_exists($db_path)) {
            return null;
        }
        
        try {
            $connResult = ConnectionPool::getConnection($db_path, true);
            if (!$connResult) {
                return null;
            }
            $db = $connResult['db'];
            $poolId = $connResult['pool_id'];
            
            $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND club_id = 1");
            $stmt->bindValue(1, (int)$eventId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if (!$result) {
                ConnectionPool::releaseConnection($db_path, $poolId, true);
                return null;
            }
            
            $event = $result->fetchArray(SQLITE3_ASSOC);
            ConnectionPool::releaseConnection($db_path, $poolId, true);
            
            if ($event) {
                $event['community_id'] = $communityId;
            }
            
            return $event;
        } catch (Exception $e) {
            if (isset($poolId)) {
                ConnectionPool::releaseConnection($db_path, $poolId, true);
            }
            error_log("Events Service error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * RSVP to event
     */
    public static function rsvp($eventId, $communityId, $userId, $userEmail, $userName) {
        try {
            $communityId = sanitizeCommunityId($communityId);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Geçersiz topluluk ID'];
        }
        
        $db_path = __DIR__ . '/../../communities/' . $communityId . '/unipanel.sqlite';
        
        if (!file_exists($db_path)) {
            return ['success' => false, 'message' => 'Topluluk bulunamadı'];
        }
        
        try {
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode = WAL');
            
            // Event var mı?
            $event = self::getById($eventId, $communityId);
            if (!$event) {
                $db->close();
                return ['success' => false, 'message' => 'Etkinlik bulunamadı'];
            }
            
            // RSVP tablosunu oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS event_rsvps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                user_id INTEGER,
                email TEXT NOT NULL,
                name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(event_id, email)
            )");
            
            // Zaten RSVP var mı?
            $checkStmt = $db->prepare("SELECT id FROM event_rsvps WHERE event_id = ? AND email = ?");
            $checkStmt->bindValue(1, (int)$eventId, SQLITE3_INTEGER);
            $checkStmt->bindValue(2, $userEmail, SQLITE3_TEXT);
            $result = $checkStmt->execute();
            
            if ($result && $result->fetchArray()) {
                $db->close();
                return ['success' => false, 'message' => 'Zaten bu etkinliğe katılım kaydınız var'];
            }
            
            // RSVP ekle
            $insertStmt = $db->prepare("INSERT INTO event_rsvps (event_id, club_id, user_id, email, name) VALUES (?, 1, ?, ?, ?)");
            $insertStmt->bindValue(1, (int)$eventId, SQLITE3_INTEGER);
            $insertStmt->bindValue(2, $userId ? (int)$userId : null, SQLITE3_INTEGER);
            $insertStmt->bindValue(3, $userEmail, SQLITE3_TEXT);
            $insertStmt->bindValue(4, $userName, SQLITE3_TEXT);
            
            if (!$insertStmt->execute()) {
                $db->close();
                return ['success' => false, 'message' => 'RSVP kaydı başarısız'];
            }
            
            $db->close();
            return ['success' => true, 'message' => 'Etkinliğe katılım kaydınız alındı'];
        } catch (Exception $e) {
            if (isset($db)) {
                $db->close();
            }
            error_log("RSVP error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Bir hata oluştu'];
        }
    }
}
