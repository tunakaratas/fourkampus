<?php
/**
 * Communities Controller
 * 
 * Topluluk işlemleri
 */

require_once __DIR__ . '/../router.php';
require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../services/CommunitiesService.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Sanitizer.php';

class CommunitiesController {
    
    /**
     * GET /api/v1/communities
     * Tüm toplulukları listele
     */
    public function index($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            APIResponse::methodNotAllowed();
        }
        
        // Rate limiting
        if (!checkRateLimit(200, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429);
        }
        
        // Filters
        $filters = [];
        if (isset($_GET['university_id'])) {
            $filters['university_id'] = Sanitizer::input($_GET['university_id'], 'string');
        } elseif (isset($_GET['university'])) {
            $filters['university_id'] = Sanitizer::input($_GET['university'], 'string');
        }
        
        $communities = CommunitiesService::getAll($filters);
        
        APIResponse::success($communities);
    }
    
    /**
     * GET /api/v1/communities/{id}
     * Topluluk detayı
     */
    public function show($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            APIResponse::methodNotAllowed();
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            APIResponse::error('Topluluk ID gerekli');
        }
        
        // Rate limiting
        if (!checkRateLimit(200, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429);
        }
        
        $community = CommunitiesService::getById($id);
        
        if (!$community) {
            APIResponse::error('Topluluk bulunamadı', 404);
        }
        
        APIResponse::success($community);
    }
    
    /**
     * POST /api/v1/communities/{id}/join
     * Topluluğa katıl
     */
    public function join($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            APIResponse::methodNotAllowed();
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            APIResponse::error('Topluluk ID gerekli');
        }
        
        $user = $GLOBALS['currentUser'] ?? null;
        if (!$user) {
            APIResponse::unauthorized();
        }
        
        // Rate limiting
        if (!checkRateLimit(10, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429);
        }
        
        // Topluluk var mı kontrol et
        $community = CommunitiesService::getById($id);
        if (!$community) {
            APIResponse::error('Topluluk bulunamadı', 404);
        }
        
        // Veritabanına üye ekle
        try {
            $db_path = __DIR__ . '/../../communities/' . $id . '/unipanel.sqlite';
            if (!file_exists($db_path)) {
                APIResponse::error('Topluluk veritabanı bulunamadı', 500);
            }
            
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode = WAL');
            
            // Üye zaten var mı?
            $checkStmt = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND email = ?");
            $checkStmt->bindValue(1, $user['email'], SQLITE3_TEXT);
            $result = $checkStmt->execute();
            
            if ($result && $result->fetchArray()) {
                $db->close();
                APIResponse::error('Zaten bu topluluğun üyesisiniz');
            }
            
            // Üye ekle
            $insertStmt = $db->prepare("INSERT INTO members (club_id, full_name, email, phone_number, created_at) VALUES (1, ?, ?, ?, datetime('now'))");
            $fullName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
            $insertStmt->bindValue(1, trim($fullName), SQLITE3_TEXT);
            $insertStmt->bindValue(2, $user['email'], SQLITE3_TEXT);
            $insertStmt->bindValue(3, $user['phone_number'] ?? null, SQLITE3_TEXT);
            
            if (!$insertStmt->execute()) {
                $db->close();
                APIResponse::error('Üyelik kaydı başarısız', 500);
            }
            
            $db->close();
            APIResponse::success(null, 'Topluluğa başarıyla katıldınız');
        } catch (Exception $e) {
            if (isset($db)) {
                $db->close();
            }
            error_log("Join community error: " . $e->getMessage());
            APIResponse::error('Bir hata oluştu', 500);
        }
    }
}
