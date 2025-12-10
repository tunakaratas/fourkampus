<?php
namespace UniPanel\Core;

/**
 * Query Optimizer
 * Prepared statement caching ve query optimizasyonları
 */
class QueryOptimizer {
    private static $preparedStatements = [];
    private static $queryCache = [];
    
    /**
     * Prepared statement'ı cache'le ve tekrar kullan
     */
    public static function getPreparedStatement($db, $sql, $key = null) {
        if ($key === null) {
            $key = md5($sql);
        }
        
        // Cache'den kontrol et
        if (isset(self::$preparedStatements[$key])) {
            return self::$preparedStatements[$key];
        }
        
        // Yeni prepared statement oluştur
        $stmt = $db->prepare($sql);
        if ($stmt) {
            self::$preparedStatements[$key] = $stmt;
        }
        
        return $stmt;
    }
    
    /**
     * Query result'ı cache'le (kısa süreli)
     */
    public static function cacheQueryResult($sql, $params, $result, $ttl = 60) {
        $key = md5($sql . serialize($params));
        self::$queryCache[$key] = [
            'result' => $result,
            'expires' => time() + $ttl
        ];
    }
    
    /**
     * Cache'lenmiş query result'ı al
     */
    public static function getCachedQueryResult($sql, $params) {
        $key = md5($sql . serialize($params));
        
        if (isset(self::$queryCache[$key])) {
            $cached = self::$queryCache[$key];
            if (time() < $cached['expires']) {
                return $cached['result'];
            } else {
                unset(self::$queryCache[$key]);
            }
        }
        
        return null;
    }
    
    /**
     * Cache'i temizle
     */
    public static function clearCache() {
        self::$preparedStatements = [];
        self::$queryCache = [];
    }
    
    /**
     * Expired cache'leri temizle
     */
    public static function cleanupExpiredCache() {
        $now = time();
        foreach (self::$queryCache as $key => $cached) {
            if ($now >= $cached['expires']) {
                unset(self::$queryCache[$key]);
            }
        }
    }
}

