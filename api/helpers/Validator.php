<?php
/**
 * Validator Class
 * 
 * Merkezi validation işlemleri
 */

class Validator {
    
    /**
     * Email validation
     */
    public static function email($email) {
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email adresi boş olamaz'];
        }
        
        $email = trim($email);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Geçersiz email formatı'];
        }
        
        if (strlen($email) > 255) {
            return ['valid' => false, 'message' => 'Email adresi çok uzun (maksimum 255 karakter)'];
        }
        
        if (preg_match('/[<>"\']/', $email)) {
            return ['valid' => false, 'message' => 'Email adresinde geçersiz karakterler var'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Password validation
     */
    public static function password($password, $minLength = 8, $maxLength = 128) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'Şifre boş olamaz'];
        }
        
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'message' => "Şifre en az {$minLength} karakter olmalıdır"];
        }
        
        if (strlen($password) > $maxLength) {
            return ['valid' => false, 'message' => "Şifre çok uzun (maksimum {$maxLength} karakter)"];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Phone validation (Türkiye formatı)
     */
    public static function phone($phone) {
        if (empty($phone)) {
            return ['valid' => false, 'message' => 'Telefon numarası boş olamaz'];
        }
        
        $phone = preg_replace('/\s+/', '', $phone);
        
        // Türkiye telefon formatı: 5 ile başlayan 10 haneli
        if (!preg_match('/^5[0-9]{9}$/', $phone)) {
            return ['valid' => false, 'message' => 'Geçersiz telefon numarası formatı (5XXXXXXXXX)'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Required field validation
     */
    public static function required($value, $fieldName = 'Alan') {
        if (empty($value) && $value !== '0') {
            return ['valid' => false, 'message' => "{$fieldName} zorunludur"];
        }
        
        return ['valid' => true];
    }
    
    /**
     * String length validation
     */
    public static function length($value, $min = null, $max = null, $fieldName = 'Alan') {
        $len = mb_strlen($value, 'UTF-8');
        
        if ($min !== null && $len < $min) {
            return ['valid' => false, 'message' => "{$fieldName} en az {$min} karakter olmalıdır"];
        }
        
        if ($max !== null && $len > $max) {
            return ['valid' => false, 'message' => "{$fieldName} en fazla {$max} karakter olabilir"];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Integer validation
     */
    public static function integer($value, $min = null, $max = null, $fieldName = 'Değer') {
        if (!is_numeric($value)) {
            return ['valid' => false, 'message' => "{$fieldName} sayı olmalıdır"];
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            return ['valid' => false, 'message' => "{$fieldName} en az {$min} olmalıdır"];
        }
        
        if ($max !== null && $int > $max) {
            return ['valid' => false, 'message' => "{$fieldName} en fazla {$max} olabilir"];
        }
        
        return ['valid' => true];
    }
    
    /**
     * URL validation
     */
    public static function url($url) {
        if (empty($url)) {
            return ['valid' => false, 'message' => 'URL boş olamaz'];
        }
        
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return ['valid' => false, 'message' => 'Geçersiz URL formatı'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Multiple validations
     */
    public static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $ruleName = is_array($rule) ? $rule[0] : $rule;
                $params = is_array($rule) ? array_slice($rule, 1) : [];
                
                $result = self::applyRule($ruleName, $value, $params, $field);
                
                if (!$result['valid']) {
                    $errors[$field] = $result['message'];
                    break; // İlk hatada dur
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Apply validation rule
     */
    private static function applyRule($rule, $value, $params, $fieldName) {
        $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
        
        switch ($rule) {
            case 'required':
                return self::required($value, $fieldLabel);
            case 'email':
                return self::email($value);
            case 'password':
                $min = $params[0] ?? 8;
                $max = $params[1] ?? 128;
                return self::password($value, $min, $max);
            case 'phone':
                return self::phone($value);
            case 'url':
                return self::url($value);
            case 'length':
                $min = $params[0] ?? null;
                $max = $params[1] ?? null;
                return self::length($value, $min, $max, $fieldLabel);
            case 'integer':
                $min = $params[0] ?? null;
                $max = $params[1] ?? null;
                return self::integer($value, $min, $max, $fieldLabel);
            default:
                return ['valid' => true];
        }
    }
}
