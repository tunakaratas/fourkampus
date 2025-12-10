<?php
/**
 * Database Sınıfı
 * SQLite veritabanı yönetimi - Singleton pattern
 */

namespace UniPanel\Core;

class Database {
    private static $instances = [];
    private $db;
    private $dbPath;
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct($dbPath) {
        $this->dbPath = $dbPath;
        $this->connect();
    }
    
    /**
     * Get instance (Singleton pattern)
     * 
     * @param string $dbPath Veritabanı dosya yolu
     * @return Database
     */
    public static function getInstance($dbPath) {
        // Normalize path
        $normalizedPath = realpath($dbPath) ?: $dbPath;
        
        // Her farklı path için ayrı instance
        if (!isset(self::$instances[$normalizedPath])) {
            self::$instances[$normalizedPath] = new self($normalizedPath);
        }
        
        return self::$instances[$normalizedPath];
    }
    
    /**
     * Veritabanına bağlan
     */
    private function connect() {
        try {
            // Veritabanı dizinini oluştur
            $dbDir = dirname($this->dbPath);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }
            
            // Veritabanı dosyasını oluştur (yoksa)
            if (!file_exists($this->dbPath)) {
                @touch($this->dbPath);
                @chmod($this->dbPath, 0666);
            }
            
            $this->db = new \SQLite3($this->dbPath);
            
            if (!$this->db) {
                throw new \Exception("SQLite3 bağlantısı kurulamadı: " . \SQLite3::lastErrorMsg());
            }
            
            // Journal mode ayarla
            // WAL mode: Daha iyi concurrent access, ama lock sorunlarına dikkat
            // DELETE mode: Daha güvenli, ama daha yavaş
            // Production'da WAL, development'ta DELETE kullanılabilir
            $journal_mode = getenv('SQLITE_JOURNAL_MODE') ?: 'DELETE';
            @$this->db->exec("PRAGMA journal_mode = $journal_mode");
            
            // Foreign keys aktif et
            @$this->db->exec('PRAGMA foreign_keys = ON');
            
            // Busy timeout ayarla (5 saniye)
            $this->db->busyTimeout(5000);
            
            // Performance optimizasyonları
            @$this->db->exec('PRAGMA synchronous = NORMAL'); // WAL mode ile uyumlu
            @$this->db->exec('PRAGMA cache_size = -20000'); // 20MB cache (artırıldı)
            @$this->db->exec('PRAGMA temp_store = MEMORY'); // Temp tabloları memory'de tut
            @$this->db->exec('PRAGMA mmap_size = 268435456'); // 256MB memory-mapped I/O
            @$this->db->exec('PRAGMA page_size = 4096'); // 4KB page size (optimal)
            @$this->db->exec('PRAGMA optimize'); // Query planner'ı optimize et
            
        } catch (\Exception $e) {
            throw new \Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }
    
    /**
     * SQLite3 instance'ını döndür
     * 
     * @return SQLite3
     */
    public function getDb() {
        return $this->db;
    }
    
    /**
     * Veritabanı yolunu döndür
     * 
     * @return string
     */
    public function getPath() {
        return $this->dbPath;
    }
    
    /**
     * Bağlantıyı kapat
     */
    public function close() {
        if ($this->db) {
            $this->db->close();
        }
    }
    
    /**
     * Clone'u engelle (Singleton pattern)
     */
    private function __clone() {}
    
    /**
     * Unserialize'ı engelle (Singleton pattern)
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}

