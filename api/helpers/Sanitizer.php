<?php
/**
 * Sanitizer Class
 * 
 * Merkezi input sanitization işlemleri
 */

class Sanitizer {
    
    /**
     * Sanitize input
     */
    public static function input($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::input($item, $type);
            }, $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        $input = trim($input);
        
        switch ($type) {
            case 'string':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'int':
                return (int)$input;
            case 'float':
                return (float)$input;
            case 'raw':
                return $input; // Şifre gibi ham kalması gerekenler
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Sanitize community ID
     */
    public static function communityId($id) {
        if (empty($id)) {
            throw new Exception('Topluluk ID boş olamaz');
        }
        
        $id = basename($id);
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new Exception('Geçersiz topluluk ID formatı');
        }
        
        if (strpos($id, '..') !== false || strpos($id, '/') !== false || strpos($id, '\\') !== false) {
            throw new Exception('Geçersiz topluluk ID - path traversal tespit edildi');
        }
        
        return $id;
    }
    
    /**
     * Sanitize array of inputs
     */
    public static function array($data, $rules) {
        $sanitized = [];
        
        foreach ($rules as $field => $type) {
            if (isset($data[$field])) {
                $sanitized[$field] = self::input($data[$field], $type);
            }
        }
        
        return $sanitized;
    }
}
