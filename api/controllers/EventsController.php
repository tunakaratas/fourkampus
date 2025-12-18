<?php
/**
 * Events Controller
 * 
 * Etkinlik işlemleri
 */

require_once __DIR__ . '/../router.php';
require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../services/EventsService.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Sanitizer.php';

class EventsController {
    
    /**
     * GET /api/v1/events
     * Tüm etkinlikleri listele
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
        if (isset($_GET['community_id'])) {
            $filters['community_id'] = Sanitizer::input($_GET['community_id'], 'string');
        }
        if (isset($_GET['university_id'])) {
            $filters['university_id'] = Sanitizer::input($_GET['university_id'], 'string');
        } elseif (isset($_GET['university'])) {
            $filters['university_id'] = Sanitizer::input($_GET['university'], 'string');
        }
        
        $events = EventsService::getAll($filters);
        
        APIResponse::success($events);
    }
    
    /**
     * GET /api/v1/events/{id}
     * Etkinlik detayı
     * Query params: community_id (required)
     */
    public function show($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            APIResponse::methodNotAllowed();
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            APIResponse::error('Etkinlik ID gerekli');
        }
        
        $communityId = $_GET['community_id'] ?? null;
        if (!$communityId) {
            APIResponse::error('Topluluk ID gerekli (community_id parametresi)');
        }
        
        // Rate limiting
        if (!checkRateLimit(200, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429);
        }
        
        $event = EventsService::getById($id, $communityId);
        
        if (!$event) {
            APIResponse::error('Etkinlik bulunamadı', 404);
        }
        
        APIResponse::success($event);
    }
    
    /**
     * POST /api/v1/events/{id}/rsvp
     * Etkinliğe katılım
     * Query params: community_id (required)
     */
    public function rsvp($params = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            APIResponse::methodNotAllowed();
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            APIResponse::error('Etkinlik ID gerekli');
        }
        
        $communityId = $_GET['community_id'] ?? null;
        if (!$communityId) {
            APIResponse::error('Topluluk ID gerekli (community_id parametresi)');
        }
        
        $user = $GLOBALS['currentUser'] ?? null;
        if (!$user) {
            APIResponse::unauthorized();
        }
        
        // Rate limiting
        if (!checkRateLimit(10, 60)) {
            APIResponse::error('Çok fazla istek. Lütfen daha sonra tekrar deneyin.', 429);
        }
        
        $fullName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
        $result = EventsService::rsvp(
            $id,
            $communityId,
            $user['id'] ?? null,
            $user['email'],
            trim($fullName)
        );
        
        if (!$result['success']) {
            APIResponse::error($result['message']);
        }
        
        APIResponse::success(null, $result['message']);
    }
}
