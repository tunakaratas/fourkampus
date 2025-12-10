<?php
namespace UniPanel\Core;

/**
 * Response Optimizer
 * Gzip compression, caching headers, ve diğer response optimizasyonları
 */
class ResponseOptimizer {
    
    /**
     * Gzip compression başlat (eğer destekleniyorsa)
     */
    public static function startCompression() {
        if (!ob_get_level() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
                strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                ob_start('ob_gzhandler');
            } else {
                ob_start();
            }
        }
    }
    
    /**
     * Cache headers ekle
     */
    public static function setCacheHeaders($ttl = 300, $public = false) {
        $cacheControl = $public ? 'public' : 'private';
        header("Cache-Control: {$cacheControl}, max-age={$ttl}, must-revalidate");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
        header("Pragma: cache");
    }
    
    /**
     * No-cache headers ekle
     */
    public static function setNoCacheHeaders() {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
    
    /**
     * JSON response için optimize headers
     */
    public static function setJsonHeaders($cache = false) {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($cache) {
            self::setCacheHeaders(300, false);
        } else {
            self::setNoCacheHeaders();
        }
        
        // CORS headers (gerekirse)
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
            header("Access-Control-Allow-Credentials: true");
        }
    }
    
    /**
     * Static asset headers (CSS, JS, images)
     */
    public static function setStaticAssetHeaders($maxAge = 31536000) { // 1 yıl
        header("Cache-Control: public, max-age={$maxAge}, immutable");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    }
}

