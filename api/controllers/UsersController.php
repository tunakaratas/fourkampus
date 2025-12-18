<?php
/**
 * Users Controller
 * 
 * Kullanıcı işlemleri
 */

require_once __DIR__ . '/../router.php';
require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';

class UsersController {
    
    /**
     * GET /api/v1/users/me
     * Mevcut kullanıcı bilgileri
     */
    public function me($params = []) {
        $user = $GLOBALS['currentUser'] ?? null;
        if (!$user) {
            APIResponse::unauthorized();
        }
        
        // Veritabanından güncel bilgileri al
        $system_db_path = __DIR__ . '/../../public/unipanel.sqlite';
        
        if (!file_exists($system_db_path)) {
            APIResponse::error('Veritabanı dosyası bulunamadı', 500);
        }
        
        $db = new SQLite3($system_db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        $stmt = $db->prepare("SELECT id, email, first_name, last_name, phone_number, university, department, student_id, email_verified, phone_verified, is_active, created_at, last_login FROM system_users WHERE id = ?");
        $stmt->bindValue(1, $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if (!$result) {
            $db->close();
            APIResponse::error('Veritabanı hatası', 500);
        }
        
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        $db->close();
        
        if (!$userData) {
            APIResponse::error('Kullanıcı bulunamadı', 404);
        }
        
        APIResponse::success($userData);
    }
    
    /**
     * PUT /api/v1/users/me
     * Kullanıcı bilgilerini güncelle
     */
    public function update($params = []) {
        $user = $GLOBALS['currentUser'] ?? null;
        if (!$user) {
            APIResponse::unauthorized();
        }
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'PUT') {
            APIResponse::methodNotAllowed();
        }
        
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput ?: '{}', true);
        
        if (!is_array($input)) {
            APIResponse::error('Geçersiz istek formatı');
        }
        
        $system_db_path = __DIR__ . '/../../public/unipanel.sqlite';
        
        if (!file_exists($system_db_path)) {
            APIResponse::error('Veritabanı dosyası bulunamadı', 500);
        }
        
        $db = new SQLite3($system_db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        $updates = [];
        $values = [];
        
        // Güncellenebilir alanlar
        $allowedFields = ['first_name', 'last_name', 'phone_number', 'university', 'department', 'student_id'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = sanitizeInput(trim($input[$field]), 'string');
            }
        }
        
        if (empty($updates)) {
            $db->close();
            APIResponse::error('Güncellenecek alan yok');
        }
        
        $values[] = $user['id'];
        $sql = "UPDATE system_users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        foreach ($values as $index => $value) {
            $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
        }
        
        if (!$stmt->execute()) {
            $db->close();
            APIResponse::error('Güncelleme başarısız', 500);
        }
        
        $db->close();
        APIResponse::success(null, 'Profil güncellendi');
    }
}
