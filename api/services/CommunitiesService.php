<?php
/**
 * Communities Service
 * 
 * Topluluk işlemleri business logic
 */

require_once __DIR__ . '/../connection_pool.php';
require_once __DIR__ . '/../security_helper.php';

class CommunitiesService {
    
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
     * Get requested university ID from request
     */
    private static function getRequestedUniversityId() {
        $raw = '';
        
        if (isset($_GET['university_id'])) {
            $raw = (string)$_GET['university_id'];
            if (strpos($raw, '%') !== false) {
                $raw = urldecode($raw);
                if (strpos($raw, '%') !== false) {
                    $raw = urldecode($raw);
                }
            }
        } elseif (isset($_GET['university'])) {
            $raw = (string)$_GET['university'];
            if (strpos($raw, '%') !== false) {
                $raw = urldecode($raw);
                if (strpos($raw, '%') !== false) {
                    $raw = urldecode($raw);
                }
            }
        }
        
        $raw = trim($raw);
        if ($raw === '' || $raw === 'all') {
            return '';
        }
        
        return self::normalizeUniversityId($raw);
    }
    
    /**
     * Get all communities
     */
    public static function getAll($filters = []) {
        $communities_dir = __DIR__ . '/../../communities';
        $requested_university_id = $filters['university_id'] ?? self::getRequestedUniversityId();
        
        // Cache kontrolü
        require_once __DIR__ . '/../../lib/core/Cache.php';
        
        $cache = \UniPanel\Core\Cache::getInstance(__DIR__ . '/../../system/cache');
        $cacheKey = 'all_communities_list_v3_' . md5($requested_university_id);
        
        if ($cache && $requested_university_id === '') {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
        if ($community_folders === false) {
            $community_folders = [];
        }
        
        $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
        $communities = [];
        
        foreach ($community_folders as $folder_path) {
            $community_id = basename($folder_path);
            if (in_array($community_id, $excluded_dirs)) {
                continue;
            }
            
            $db_path = $folder_path . '/unipanel.sqlite';
            if (!file_exists($db_path)) {
                continue;
            }
            
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
                
                // Üniversite filtresi
                if ($requested_university_id !== '') {
                    $community_university_name = $settings['university'] ?? $settings['organization'] ?? '';
                    $community_university_id = self::normalizeUniversityId($community_university_name);
                    
                    if ($community_university_id === '' || $community_university_id !== $requested_university_id) {
                        ConnectionPool::releaseConnection($db_path, $poolId, true);
                        continue;
                    }
                }
                
                // Üye sayısı
                $member_count = 0;
                $member_result = $db->querySingle("SELECT COUNT(*) FROM members WHERE club_id = 1");
                if ($member_result !== false) $member_count = (int)$member_result;
                
                // Etkinlik sayısı
                $event_count = 0;
                $event_result = $db->querySingle("SELECT COUNT(*) FROM events WHERE club_id = 1");
                if ($event_result !== false) $event_count = (int)$event_result;
                
                // Kampanya sayısı
                $campaign_count = 0;
                $db->exec("CREATE TABLE IF NOT EXISTS campaigns (id INTEGER PRIMARY KEY, club_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT, offer_text TEXT NOT NULL, partner_name TEXT, discount_percentage INTEGER, image_path TEXT, start_date TEXT, end_date TEXT, is_active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $campaign_result = $db->querySingle("SELECT COUNT(*) FROM campaigns WHERE club_id = 1 AND is_active = 1");
                if ($campaign_result !== false) $campaign_count = (int)$campaign_result;
                
                // Base URL
                $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'foursoftware.com.tr');
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $baseUrl = $protocol . '://' . $host;
                
                // QR kod
                $qr_deep_link = 'unifour://community/' . urlencode($community_id);
                $qr_code_url = $baseUrl . '/api/qr_code.php?type=community&id=' . urlencode($community_id);
                
                $communities[] = [
                    'id' => $community_id,
                    'name' => $settings['club_name'] ?? ucwords(str_replace('_', ' ', $community_id)),
                    'description' => $settings['club_description'] ?? '',
                    'member_count' => (int)$member_count,
                    'event_count' => (int)$event_count,
                    'campaign_count' => (int)$campaign_count,
                    'qr_deep_link' => $qr_deep_link,
                    'qr_code_url' => $qr_code_url
                ];
                
                ConnectionPool::releaseConnection($db_path, $poolId, true);
            } catch (Exception $e) {
                if (isset($poolId)) {
                    ConnectionPool::releaseConnection($db_path, $poolId, true);
                }
                error_log("Communities Service error: " . $e->getMessage());
                continue;
            }
        }
        
        // Cache'e kaydet (sadece filtre yoksa)
        if ($cache && $requested_university_id === '') {
            $cache->set($cacheKey, $communities, 30);
        }
        
        return $communities;
    }
    
    /**
     * Get community by ID
     */
    public static function getById($communityId) {
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
            
            $settings_query = $db->query("SELECT setting_key, setting_value FROM settings WHERE club_id = 1");
            $settings = [];
            if ($settings_query) {
                while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            // Events
            $events = [];
            $events_stmt = $db->prepare("SELECT * FROM events WHERE club_id = 1 ORDER BY date DESC, time DESC LIMIT 20");
            if ($events_stmt) {
                $events_result = $events_stmt->execute();
                if ($events_result) {
                    while ($row = $events_result->fetchArray(SQLITE3_ASSOC)) {
                        $events[] = $row;
                    }
                }
            }
            
            // Members
            $members = [];
            $members_stmt = $db->prepare("SELECT full_name FROM members WHERE club_id = 1 AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
            if ($members_stmt) {
                $members_result = $members_stmt->execute();
                if ($members_result) {
                    while ($row = $members_result->fetchArray(SQLITE3_ASSOC)) {
                        $members[] = $row;
                    }
                }
            }
            
            // Campaigns
            $campaigns = [];
            $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                offer_text TEXT NOT NULL,
                partner_name TEXT,
                discount_percentage INTEGER,
                image_path TEXT,
                start_date TEXT,
                end_date TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $campaigns_stmt = $db->prepare("SELECT * FROM campaigns WHERE club_id = 1 AND is_active = 1 ORDER BY created_at DESC");
            if ($campaigns_stmt) {
                $campaigns_result = $campaigns_stmt->execute();
                if ($campaigns_result) {
                    while ($row = $campaigns_result->fetchArray(SQLITE3_ASSOC)) {
                        $campaigns[] = $row;
                    }
                }
            }
            
            // Board
            $board = [];
            $board_stmt = $db->prepare("SELECT full_name, role FROM board_members WHERE club_id = 1 ORDER BY id ASC");
            if ($board_stmt) {
                $board_result = $board_stmt->execute();
                if ($board_result) {
                    while ($row = $board_result->fetchArray(SQLITE3_ASSOC)) {
                        $board[] = $row;
                    }
                }
            }
            
            ConnectionPool::releaseConnection($db_path, $poolId, true);
            
            // Base URL
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'foursoftware.com.tr');
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $baseUrl = $protocol . '://' . $host;
            
            $image_url = null;
            if (!empty($settings['club_image'])) {
                $image_url = '/communities/' . $communityId . '/' . $settings['club_image'];
            }
            
            $logo_path = null;
            if (!empty($settings['club_logo'])) {
                $logo_path = '/communities/' . $communityId . '/' . $settings['club_logo'];
            }
            
            return [
                'id' => $communityId,
                'name' => $settings['club_name'] ?? ucwords(str_replace('_', ' ', $communityId)),
                'description' => $settings['club_description'] ?? '',
                'image_url' => $image_url,
                'logo_url' => $logo_path,
                'member_count' => count($members),
                'event_count' => count($events),
                'campaign_count' => count($campaigns),
                'board_member_count' => count($board),
                'events' => $events,
                'members' => $members,
                'campaigns' => $campaigns,
                'board' => $board,
                'qr_deep_link' => 'unifour://community/' . urlencode($communityId),
                'qr_code_url' => $baseUrl . '/api/qr_code.php?type=community&id=' . urlencode($communityId)
            ];
        } catch (Exception $e) {
            if (isset($poolId)) {
                ConnectionPool::releaseConnection($db_path, $poolId, true);
            }
            error_log("Communities Service error: " . $e->getMessage());
            return null;
        }
    }
}
