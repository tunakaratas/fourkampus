<?php
namespace UniPanel\Models;

/**
 * Event Model
 * Etkinlik yönetimi için model sınıfı
 */
class Event {
    private $db;
    private $clubId;
    
    public function __construct($db, $clubId = 1) {
        $this->db = $db;
        $this->clubId = $clubId;
    }
    
    /**
     * Tüm etkinlikleri getir
     * 
     * @return array
     */
    public function getAll() {
        try {
            // Önce events tablosunun var olup olmadığını kontrol et
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }
            
            $stmt = @$this->db->prepare("
                SELECT * FROM events 
                WHERE club_id = ? 
                ORDER BY id DESC
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result) {
                return [];
            }
            
            $events = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $events[] = $row;
            }
            
            return $events;
        } catch (\Exception $e) {
            error_log("Event::getAll() error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Etkinlikleri sayfalı getir (Lazy Loading için)
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPaginated($limit = 20, $offset = 0) {
        try {
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }
            
            $stmt = @$this->db->prepare("
                SELECT * FROM events 
                WHERE club_id = ? 
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            ");
            if (!$stmt) return [];
            
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $stmt->bindValue(2, (int)$limit, SQLITE3_INTEGER);
            $stmt->bindValue(3, (int)$offset, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            if (!$result) return [];
            
            $events = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $events[] = $row;
            }
            return $events;
        } catch (\Exception $e) {
            error_log("Event::getPaginated() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ID'ye göre etkinlik getir
     * 
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM events 
                WHERE id = ? AND club_id = ?
                LIMIT 1
            ");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            return $result->fetchArray(SQLITE3_ASSOC) ?: null;
        } catch (\Exception $e) {
            error_log("Event::getById() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Yeni etkinlik oluştur
     * 
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO events (
                    club_id, title, description, date, time, end_date, end_time,
                    location, image_path, video_path, category, status, organizer,
                    contact_email, contact_phone, capacity, cost, currency,
                    registration_required, max_attendees, min_attendees,
                    online_link, has_survey, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $data['title'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(3, $data['description'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(4, $data['date'] ?? date('Y-m-d'), SQLITE3_TEXT);
            $stmt->bindValue(5, $data['time'] ?? '00:00', SQLITE3_TEXT);
            $stmt->bindValue(6, $data['end_date'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(7, $data['end_time'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(8, $data['location'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(9, $data['image_path'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(10, $data['video_path'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(11, $data['category'] ?? 'Genel', SQLITE3_TEXT);
            $stmt->bindValue(12, $data['status'] ?? 'active', SQLITE3_TEXT);
            $stmt->bindValue(13, $data['organizer'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(14, $data['contact_email'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(15, $data['contact_phone'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(16, $data['capacity'] ?? null, SQLITE3_INTEGER);
            $stmt->bindValue(17, $data['cost'] ?? null, SQLITE3_REAL);
            $stmt->bindValue(18, $data['currency'] ?? 'TRY', SQLITE3_TEXT);
            $stmt->bindValue(19, isset($data['registration_required']) ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(20, $data['max_attendees'] ?? null, SQLITE3_INTEGER);
            $stmt->bindValue(21, $data['min_attendees'] ?? null, SQLITE3_INTEGER);
            $stmt->bindValue(22, $data['online_link'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(23, isset($data['has_survey']) ? 1 : 0, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertRowID();
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Event::create() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Etkinlik güncelle
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            $paramIndex = 1;
            
            $allowedFields = [
                'title', 'description', 'date', 'time', 'end_date', 'end_time',
                'location', 'image_path', 'video_path', 'category', 'status',
                'organizer', 'contact_email', 'contact_phone', 'capacity',
                'cost', 'currency', 'registration_required', 'max_attendees',
                'min_attendees', 'online_link', 'has_survey'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    if ($field === 'registration_required' || $field === 'has_survey') {
                        $values[] = $data[$field] ? 1 : 0;
                    } else {
                        $values[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ? AND club_id = ?";
            $stmt = $this->db->prepare($sql);
            
            foreach ($values as $value) {
                $stmt->bindValue($paramIndex++, $value, is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_REAL : SQLITE3_TEXT));
            }
            
            $stmt->bindValue($paramIndex++, $id, SQLITE3_INTEGER);
            $stmt->bindValue($paramIndex++, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Event::update() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Etkinlik sil
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM events WHERE id = ? AND club_id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Event::delete() error: " . $e->getMessage());
            return false;
        }
    }
}

