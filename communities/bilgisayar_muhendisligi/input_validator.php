<?php
/**
 * Input Validation System
 * Tüm kullanıcı inputları için merkezi doğrulama sistemi
 */

namespace UniPanel\General;

class InputValidator {
    
    /**
     * String sanitize - XSS ve SQL injection koruması
     */
    public static function sanitizeString($value, $allowHTML = false) {
        if ($value === null) {
            return '';
        }
        
        // Trim
        $value = trim($value);
        
        // HTML karakterlerini temizle (XSS koruması)
        if (!$allowHTML) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }
    
    /**
     * Email doğrula ve sanitize et
     */
    public static function validateEmail($email) {
        $email = trim($email);
        
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email boş olamaz'];
        }
        
        // Email format kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Geçersiz email formatı'];
        }
        
        // Email sanitize
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        return ['valid' => true, 'value' => $email];
    }
    
    /**
     * Telefon numarası doğrula
     */
    public static function validatePhone($phone) {
        $phone = trim($phone);
        
        if (empty($phone)) {
            return ['valid' => false, 'error' => 'Telefon numarası boş olamaz'];
        }
        
        // Sadece rakam ve özel karakterler (+, -, boşluk, parantez)
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            return ['valid' => false, 'error' => 'Telefon numarası sadece rakam, +, -, ( ) içerebilir'];
        }
        
        // Minimum uzunluk kontrolü
        $digitsOnly = preg_replace('/[^\d]/', '', $phone);
        if (strlen($digitsOnly) < 10) {
            return ['valid' => false, 'error' => 'Telefon numarası en az 10 haneli olmalıdır'];
        }
        
        return ['valid' => true, 'value' => $phone];
    }
    
    /**
     * Integer doğrula
     */
    public static function validateInt($value, $min = null, $max = null) {
        $value = trim($value);
        
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => 'Geçersiz sayı'];
        }
        
        $intValue = (int)$value;
        
        if ($min !== null && $intValue < $min) {
            return ['valid' => false, 'error' => "Sayı en az $min olmalıdır"];
        }
        
        if ($max !== null && $intValue > $max) {
            return ['valid' => false, 'error' => "Sayı en fazla $max olmalıdır"];
        }
        
        return ['valid' => true, 'value' => $intValue];
    }
    
    /**
     * URL doğrula
     */
    public static function validateURL($url) {
        $url = trim($url);
        
        if (empty($url)) {
            return ['valid' => false, 'error' => 'URL boş olamaz'];
        }
        
        // URL format kontrolü
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Geçersiz URL formatı'];
        }
        
        return ['valid' => true, 'value' => $url];
    }
    
    /**
     * Tarih doğrula (Y-m-d formatı)
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $date = trim($date);
        
        if (empty($date)) {
            return ['valid' => false, 'error' => 'Tarih boş olamaz'];
        }
        
        $d = DateTime::createFromFormat($format, $date);
        
        if (!$d || $d->format($format) !== $date) {
            return ['valid' => false, 'error' => "Geçersiz tarih formatı (örn: 2024-12-31)"];
        }
        
        return ['valid' => true, 'value' => $date];
    }
    
    /**
     * Saat doğrula (H:i formatı)
     */
    public static function validateTime($time) {
        $time = trim($time);
        
        if (empty($time)) {
            return ['valid' => false, 'error' => 'Saat boş olamaz'];
        }
        
        // HH:MM formatı
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return ['valid' => false, 'error' => "Geçersiz saat formatı (örn: 14:30)"];
        }
        
        return ['valid' => true, 'value' => $time];
    }
    
    /**
     * Dosya boyutu doğrula
     */
    public static function validateFileSize($file, $maxSize) {
        if (!isset($file['size'])) {
            return ['valid' => false, 'error' => 'Dosya bilgisi alınamadı'];
        }
        
        $fileSize = $file['size'];
        
        if ($fileSize > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 1);
            $fileSizeMB = round($fileSize / (1024 * 1024), 1);
            return ['valid' => false, 'error' => "Dosya boyutu çok büyük. Maksimum: {$maxSizeMB}MB, Yüklenen: {$fileSizeMB}MB"];
        }
        
        if ($fileSize === 0) {
            return ['valid' => false, 'error' => 'Dosya boş'];
        }
        
        return ['valid' => true, 'value' => $fileSize];
    }
    
    /**
     * Dosya tipi doğrula
     */
    public static function validateFileType($file, $allowedTypes) {
        if (!isset($file['name'])) {
            return ['valid' => false, 'error' => 'Dosya bilgisi alınamadı'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes)) {
            return ['valid' => false, 'error' => "Geçersiz dosya tipi. İzin verilen: " . implode(', ', $allowedTypes)];
        }
        
        return ['valid' => true, 'value' => $extension];
    }
    
    /**
     * POST verisini doğrula
     */
    public static function validatePost($data, $rules) {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required kontrolü
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ($rule['error'] ?? "$field zorunludur");
                continue;
            }
            
            // Boş değer kontrolü (required değilse)
            if (empty($value) && !isset($rule['required'])) {
                continue;
            }
            
            // Tip kontrolü
            $validation = null;
            switch ($rule['type']) {
                case 'email':
                    $validation = self::validateEmail($value);
                    break;
                case 'phone':
                    $validation = self::validatePhone($value);
                    break;
                case 'int':
                    $validation = self::validateInt($value, $rule['min'] ?? null, $rule['max'] ?? null);
                    break;
                case 'url':
                    $validation = self::validateURL($value);
                    break;
                case 'date':
                    $validation = self::validateDate($value);
                    break;
                case 'time':
                    $validation = self::validateTime($value);
                    break;
                case 'string':
                default:
                    $validation = ['valid' => true, 'value' => self::sanitizeString($value)];
                    break;
            }
            
            if (!$validation['valid']) {
                $errors[$field] = $validation['error'];
            } else {
                $validated[$field] = $validation['value'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
    
    /**
     * XSS koruması ile output
     */
    public static function outputSafe($value, $allowHTML = false) {
        return self::sanitizeString($value, $allowHTML);
    }
}

/**
 * Helper fonksiyonları
 */

/**
 * String sanitize
 */
function sanitize_input($value) {
    return InputValidator::sanitizeString($value);
}

/**
 * Email doğrula
 */
function validate_email($email) {
    return InputValidator::validateEmail($email);
}

/**
 * Telefon doğrula
 */
function validate_phone($phone) {
    return InputValidator::validatePhone($phone);
}

/**
 * Integer doğrula
 */
function validate_int($value, $min = null, $max = null) {
    return InputValidator::validateInt($value, $min, $max);
}

/**
 * Güvenli output
 */
function output_safe($value, $allowHTML = false) {
    return InputValidator::outputSafe($value, $allowHTML);
}
?>
