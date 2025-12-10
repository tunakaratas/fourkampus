<?php
namespace UniPanel\Models;

/**
 * Notification Model
 * Bildirim yönetimi için model sınıfı
 */
class Notification {
    private $db;
    private $clubId;
    
    public function __construct($db, $clubId = 1) {
        $this->db = $db;
        $this->clubId = $clubId;
    }
    
    /**
     * Tüm bildirimleri getir
     * 
     * @return array
     */
    public function getAll() {
        try {
            // Check if 'notifications' table exists
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }

            $stmt = @$this->db->prepare("
                SELECT * FROM notifications 
                WHERE club_id = ? 
                ORDER BY created_at DESC
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result) {
                return [];
            }
            
            $notifications = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $notifications[] = $row;
            }
            
            return $notifications;
        } catch (\Exception $e) {
            error_log("Notification::getAll() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Okunmamış bildirimleri getir
     * 
     * @return array
     */
    public function getUnread() {
        try {
            // Check if 'notifications' table exists
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }

            $stmt = @$this->db->prepare("
                SELECT * FROM notifications 
                WHERE club_id = ? AND is_read = 0
                ORDER BY created_at DESC
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result) {
                return [];
            }
            
            $notifications = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $notifications[] = $row;
            }
            
            return $notifications;
        } catch (\Exception $e) {
            error_log("Notification::getUnread() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Okunmamış bildirim sayısını getir
     * 
     * @return int
     */
    public function getUnreadCount() {
        try {
            // Check if 'notifications' table exists
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
            if (!$table_check || !$table_check->fetchArray()) {
                return 0;
            }

            $stmt = @$this->db->prepare("
                SELECT COUNT(*) as count FROM notifications 
                WHERE club_id = ? AND is_read = 0
            ");
            if (!$stmt) {
                return 0;
            }
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result) {
                return 0;
            }
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            return (int)($row['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("Notification::getUnreadCount() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Yeni bildirim oluştur
     * 
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            // Create notifications table if it doesn't exist
            @$this->db->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT,
                type TEXT DEFAULT 'info',
                is_read INTEGER DEFAULT 0,
                is_urgent INTEGER DEFAULT 0,
                sender_type TEXT DEFAULT 'system',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = @$this->db->prepare("
                INSERT INTO notifications (
                    club_id, title, message, type, is_read, sender_type, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            if (!$stmt) {
                return false;
            }
            
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $data['title'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(3, $data['message'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(4, $data['type'] ?? 'info', SQLITE3_TEXT);
            $stmt->bindValue(5, isset($data['is_read']) ? ($data['is_read'] ? 1 : 0) : 0, SQLITE3_INTEGER);
            $stmt->bindValue(6, $data['sender_type'] ?? 'system', SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertRowID();
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Notification::create() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bildirimi okundu olarak işaretle
     * 
     * @param int $id
     * @return bool
     */
    public function markAsRead($id) {
        try {
            // Check if 'notifications' table exists
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
            if (!$table_check || !$table_check->fetchArray()) {
                return false;
            }

            $stmt = @$this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND club_id = ?
            ");
            if (!$stmt) {
                return false;
            }
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Notification::markAsRead() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bildirim sil
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            // Check if 'notifications' table exists
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
            if (!$table_check || !$table_check->fetchArray()) {
                return false;
            }

            $stmt = @$this->db->prepare("DELETE FROM notifications WHERE id = ? AND club_id = ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Notification::delete() error: " . $e->getMessage());
            return false;
        }
    }
}

