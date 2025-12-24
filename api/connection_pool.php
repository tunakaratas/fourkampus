<?php
/**
 * Database Connection Pool Manager
 * 10k kullanıcı için kritik - SQLite connection pooling
 */

class ConnectionPool {
    private static $pools = [];
    private static $maxConnections = 50; // Her veritabanı için max 50 bağlantı
    private static $connectionTimeout = 30; // 30 saniye timeout
    private static $cleanupInterval = 300; // 5 dakikada bir temizlik
    
    /**
     * Connection pool'dan bağlantı al
     */
    public static function getConnection($dbPath, $readOnly = false) {
        $key = md5($dbPath . ($readOnly ? '_ro' : '_rw'));
        
        // Pool yoksa oluştur
        if (!isset(self::$pools[$key])) {
            self::$pools[$key] = [
                'connections' => [],
                'in_use' => [],
                'last_cleanup' => time()
            ];
        }
        
        $pool = &self::$pools[$key];
        
        // Temizlik zamanı geldi mi?
        if (time() - $pool['last_cleanup'] > self::$cleanupInterval) {
            self::cleanupPool($key);
        }
        
        // Kullanılabilir bağlantı var mı?
        foreach ($pool['connections'] as $id => $conn) {
            if (!isset($pool['in_use'][$id])) {
                // Bağlantı hala geçerli mi?
                try {
                    @$conn->query('SELECT 1');
                } catch (Throwable $e) {
                    // Bağlantı geçersiz, sil
                    unset($pool['connections'][$id]);
                    continue;
                }
                
                // Bağlantıyı kullan
                $pool['in_use'][$id] = time();
                return ['db' => $conn, 'pool_id' => $id];
            }
        }
        
        // Yeni bağlantı oluştur (limit kontrolü)
        if (count($pool['connections']) >= self::$maxConnections) {
            // Limit aşıldı, en eski kullanılmayan bağlantıyı bekle
            $oldest = min($pool['in_use'] ?? [time()]);
            $waitTime = self::$connectionTimeout - (time() - $oldest);
            if ($waitTime > 0 && $waitTime < self::$connectionTimeout) {
                usleep(min($waitTime * 1000000, 5000000)); // Max 5 saniye bekle
            }
            
            // Hala limit aşıldıysa, en eski bağlantıyı kapat
            if (count($pool['connections']) >= self::$maxConnections) {
                $oldestId = array_search(min($pool['in_use'] ?? []), $pool['in_use']);
                if ($oldestId !== false) {
                    @$pool['connections'][$oldestId]->close();
                    unset($pool['connections'][$oldestId]);
                    unset($pool['in_use'][$oldestId]);
                }
            }
        }
        
        // Yeni bağlantı oluştur
        try {
            if ($readOnly && file_exists($dbPath)) {
                $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
            } else {
                $db = new SQLite3($dbPath);
            }
            
            $db->busyTimeout(5000);
            @$db->exec('PRAGMA journal_mode = DELETE');
            @$db->exec('PRAGMA foreign_keys = ON');
            
            $id = uniqid('conn_', true);
            $pool['connections'][$id] = $db;
            $pool['in_use'][$id] = time();
            
            return ['db' => $db, 'pool_id' => $id];
        } catch (Throwable $e) {
            error_log("ConnectionPool: Failed to create connection: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Bağlantıyı pool'a geri ver
     */
    public static function releaseConnection($dbPath, $poolId, $readOnly = false) {
        $key = md5($dbPath . ($readOnly ? '_ro' : '_rw'));
        
        if (!isset(self::$pools[$key]) || !isset(self::$pools[$key]['connections'][$poolId])) {
            return;
        }
        
        // Kullanım işaretini kaldır
        unset(self::$pools[$key]['in_use'][$poolId]);
    }
    
    /**
     * Pool'u temizle (kullanılmayan bağlantıları kapat)
     */
    private static function cleanupPool($key) {
        if (!isset(self::$pools[$key])) {
            return;
        }
        
        $pool = &self::$pools[$key];
        $now = time();
        $toRemove = [];
        
        foreach ($pool['connections'] as $id => $conn) {
            // Kullanılmıyorsa ve timeout geçtiyse kapat
            if (!isset($pool['in_use'][$id])) {
                $toRemove[] = $id;
            } elseif (($now - $pool['in_use'][$id]) > self::$connectionTimeout) {
                // Timeout geçmiş, zorla kapat
                $toRemove[] = $id;
            }
        }
        
        // Kullanılmayan bağlantıları kapat
        foreach ($toRemove as $id) {
            @$pool['connections'][$id]->close();
            unset($pool['connections'][$id]);
            unset($pool['in_use'][$id]);
        }
        
        $pool['last_cleanup'] = $now;
    }
    
    /**
     * Tüm pool'ları temizle
     */
    public static function cleanupAll() {
        foreach (array_keys(self::$pools) as $key) {
            self::cleanupPool($key);
        }
    }
    
    /**
     * Pool istatistikleri
     */
    public static function getStats() {
        $stats = [];
        foreach (self::$pools as $key => $pool) {
            $stats[$key] = [
                'total' => count($pool['connections']),
                'in_use' => count($pool['in_use']),
                'available' => count($pool['connections']) - count($pool['in_use'])
            ];
        }
        return $stats;
    }
}

// Shutdown'da temizlik
register_shutdown_function(function() {
    ConnectionPool::cleanupAll();
});

