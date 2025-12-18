<?php
namespace UniPanel\Models;

/**
 * Member Model
 * Üye yönetimi için model sınıfı
 */
class Member {
    private $db;
    private $clubId;
    
    public function __construct($db, $clubId = 1) {
        $this->db = $db;
        $this->clubId = $clubId;
    }
    
    /**
     * Tüm üyeleri getir
     * 
     * @return array
     */
    public function getAll() {
        try {
            // Önce members tablosunun var olup olmadığını kontrol et
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }
            
            $stmt = @$this->db->prepare("
                SELECT * FROM members 
                WHERE club_id = ? 
                ORDER BY registration_date DESC, full_name ASC
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if (!$result) {
                return [];
            }
            
            $members = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $members[] = $row;
            }
            
            return $members;
        } catch (\Exception $e) {
            error_log("Member::getAll() error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Üyeleri sayfalı getir (Lazy Loading için)
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPaginated($limit = 50, $offset = 0) {
        try {
            $table_check = @$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
            if (!$table_check || !$table_check->fetchArray()) {
                return [];
            }
            
            $stmt = @$this->db->prepare("
                SELECT * FROM members 
                WHERE club_id = ? 
                ORDER BY registration_date DESC, full_name ASC
                LIMIT ? OFFSET ?
            ");
            if (!$stmt) return [];
            
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $stmt->bindValue(2, (int)$limit, SQLITE3_INTEGER);
            $stmt->bindValue(3, (int)$offset, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            if (!$result) return [];
            
            $members = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $members[] = $row;
            }
            return $members;
        } catch (\Exception $e) {
            error_log("Member::getPaginated() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ID'ye göre üye getir
     * 
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM members 
                WHERE id = ? AND club_id = ?
                LIMIT 1
            ");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            return $result->fetchArray(SQLITE3_ASSOC) ?: null;
        } catch (\Exception $e) {
            error_log("Member::getById() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Yeni üye oluştur
     * 
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO members (
                    club_id, full_name, email, student_id, phone_number, registration_date
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bindValue(1, $this->clubId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $data['full_name'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(3, $data['email'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(4, $data['student_id'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(5, $data['phone_number'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(6, $data['registration_date'] ?? date('Y-m-d'), SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertRowID();
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Member::create() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Üye güncelle
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
                'full_name', 'email', 'student_id', 'phone_number', 'registration_date'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $sql = "UPDATE members SET " . implode(', ', $fields) . " WHERE id = ? AND club_id = ?";
            $stmt = $this->db->prepare($sql);
            
            foreach ($values as $value) {
                $stmt->bindValue($paramIndex++, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
            }
            
            $stmt->bindValue($paramIndex++, $id, SQLITE3_INTEGER);
            $stmt->bindValue($paramIndex++, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Member::update() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Üye sil
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM members WHERE id = ? AND club_id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $this->clubId, SQLITE3_INTEGER);
            
            return $stmt->execute() !== false;
        } catch (\Exception $e) {
            error_log("Member::delete() error: " . $e->getMessage());
            return false;
        }
    }
}

