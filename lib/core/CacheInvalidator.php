<?php
namespace UniPanel\Core;

/**
 * Cache Invalidation Helper
 * Veri güncellendiğinde ilgili cache'leri temizler
 */
class CacheInvalidator {
    private static $cache = null;
    
    /**
     * Get cache instance
     */
    private static function getCache() {
        if (self::$cache === null) {
            self::$cache = Cache::getInstance();
        }
        return self::$cache;
    }
    
    /**
     * Events cache'ini temizle
     */
    public static function clearEventsCache($club_id) {
        $cache = self::getCache();
        $pattern = "events_*_{$club_id}_*";
        self::clearByPattern($pattern);
        $cache->delete("events_count_{$club_id}");
    }
    
    /**
     * Members cache'ini temizle
     */
    public static function clearMembersCache($club_id) {
        $cache = self::getCache();
        $pattern = "members_*_{$club_id}_*";
        self::clearByPattern($pattern);
        $cache->delete("members_count_{$club_id}");
    }
    
    /**
     * Board cache'ini temizle
     */
    public static function clearBoardCache($club_id) {
        $cache = self::getCache();
        $pattern = "board_*_{$club_id}_*";
        self::clearByPattern($pattern);
        $cache->delete("board_count_{$club_id}");
    }
    
    /**
     * Products cache'ini temizle
     */
    public static function clearProductsCache($club_id) {
        $cache = self::getCache();
        $pattern = "products_*_{$club_id}_*";
        self::clearByPattern($pattern);
        $cache->delete("products_count_{$club_id}");
    }
    
    /**
     * Campaigns cache'ini temizle
     */
    public static function clearCampaignsCache($club_id) {
        $cache = self::getCache();
        $pattern = "campaigns_*_{$club_id}_*";
        self::clearByPattern($pattern);
        $cache->delete("campaigns_count_{$club_id}");
    }
    
    /**
     * Tüm cache'leri temizle (bir topluluk için)
     */
    public static function clearAllCache($club_id) {
        self::clearEventsCache($club_id);
        self::clearMembersCache($club_id);
        self::clearBoardCache($club_id);
        self::clearProductsCache($club_id);
        self::clearCampaignsCache($club_id);
    }
    
    /**
     * Pattern'e göre cache temizle
     */
    private static function clearByPattern($pattern) {
        $cache = self::getCache();
        $cacheDir = $cache->getCacheDir();
        
        if (!$cacheDir || !is_dir($cacheDir)) {
            return;
        }
        
        // Pattern'i regex'e çevir
        $regex = str_replace(['*', '_'], ['.*', '_'], $pattern);
        $regex = '/^' . $regex . '/';
        
        $files = glob($cacheDir . '*.cache');
        if ($files) {
            foreach ($files as $file) {
                $filename = basename($file);
                if (preg_match($regex, $filename)) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * Cache directory'yi al
     */
    private static function getCacheDir() {
        $cache = self::getCache();
        return $cache->getCacheDir();
    }
}

