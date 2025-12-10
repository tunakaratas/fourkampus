<?php
namespace UniPanel\Core;

/**
 * Simple File-Based Cache System
 * 
 * @author UniPanel
 * @version 1.0
 */
class Cache {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour default
    
    private function __construct($cacheDir = null) {
        if ($cacheDir === null) {
            // Default cache directory
            $this->cacheDir = dirname(__DIR__, 2) . '/system/cache/';
        } else {
            $this->cacheDir = rtrim($cacheDir, '/') . '/';
        }
        
        // Create cache directory if not exists
        if (!is_dir($this->cacheDir)) {
            $old_umask = umask(0);
            mkdir($this->cacheDir, 0777, true);
            umask($old_umask);
        }
        
        // Ensure directory is writable
        if (!is_writable($this->cacheDir)) {
            @chmod($this->cacheDir, 0777);
        }
        
        // Auto cleanup on initialization (5% chance)
        if (rand(1, 100) <= 5) {
            $this->cleanup();
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance($cacheDir = null) {
        if (self::$instance === null) {
            self::$instance = new self($cacheDir);
        }
        return self::$instance;
    }
    
    /**
     * Get item from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Returns cached value or null if not found/expired
     */
    public function get($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = @unserialize(file_get_contents($filename));
        
        if ($data === false) {
            // Invalid cache file
            @unlink($filename);
            return null;
        }
        
        // Check if expired
        if (time() > $data['expires']) {
            @unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Store item in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 3600)
     * @return bool Success status
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }
        
        $filename = $this->getFilename($key);
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $serialized = serialize($data);
        
        // Atomic write
        $tempFile = $filename . '.tmp';
        if (file_put_contents($tempFile, $serialized, LOCK_EX) !== false) {
            return rename($tempFile, $filename);
        }
        
        return false;
    }
    
    /**
     * Delete item from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        $filename = $this->getFilename($key);
        
        if (file_exists($filename)) {
            return @unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Check if item exists in cache and is not expired
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Clear all cache
     * 
     * @return int Number of files deleted
     */
    public function clear() {
        $count = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Clean up expired cache files
     * 
     * @return int Number of expired files deleted
     */
    public function cleanup() {
        $count = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        if ($files) {
            foreach ($files as $file) {
                $data = @unserialize(file_get_contents($file));
                
                if ($data === false || time() > $data['expires']) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;
        
        if ($files) {
            foreach ($files as $file) {
                $totalSize += filesize($file);
                
                $data = @unserialize(file_get_contents($file));
                
                if ($data === false || time() > $data['expires']) {
                    $expiredCount++;
                } else {
                    $validCount++;
                }
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $validCount,
            'expired_files' => $expiredCount,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'cache_dir' => $this->cacheDir
        ];
    }
    
    /**
     * Remember pattern - get from cache or execute callback and cache result
     * 
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute if cache miss
     * @return mixed
     */
    public function remember($key, $ttl, callable $callback) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Get cache filename for key
     * 
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    private function getFilename($key) {
        // Create safe filename
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $hash = md5($key);
        
        return $this->cacheDir . $safeKey . '_' . $hash . '.cache';
    }
    
    /**
     * Set default TTL
     * 
     * @param int $seconds
     */
    public function setDefaultTTL($seconds) {
        $this->defaultTTL = $seconds;
    }
    
    /**
     * Get default TTL
     * 
     * @return int
     */
    public function getDefaultTTL() {
        return $this->defaultTTL;
    }
}

