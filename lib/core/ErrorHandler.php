<?php
/**
 * Error Handler Sınıfı
 * Hata yönetimi ve loglama
 */

namespace UniPanel\Core;

class ErrorHandler {
    private static $logPath = null;
    private static $initialized = false;
    
    /**
     * Error Handler'ı başlat
     * 
     * @param string $logPath Log dosyası yolu
     * @param bool $displayErrors Hataları ekranda göster
     */
    public static function init($logPath = null, $displayErrors = false) {
        if (self::$initialized) {
            return;
        }
        
        self::$logPath = $logPath;
        
        // Her zaman error reporting açık olsun
        error_reporting(E_ALL);
        ini_set('display_errors', $displayErrors ? 1 : 0);
        ini_set('display_startup_errors', $displayErrors ? 1 : 0);
        
        // Hata yakalama (sadece fatal error'lar için)
        // set_error_handler ve set_exception_handler sadece kritik hatalar için
        // Normal PHP hatalarının gösterilmesi için kapatıyoruz
        
        self::$initialized = true;
    }
    
    /**
     * Hata yakalama
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED'
        ];
        
        $errorType = $errorTypes[$errno] ?? 'UNKNOWN ERROR';
        
        $message = sprintf(
            "[%s] %s in %s on line %d",
            $errorType,
            $errstr,
            $errfile,
            $errline
        );
        
        self::log($message);
        
        return false; // PHP'nin standart hata handler'ını da çalıştır
    }
    
    /**
     * Exception yakalama
     */
    public static function handleException($exception) {
        $message = sprintf(
            "[EXCEPTION] %s in %s on line %d\nStack Trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        self::log($message);
    }
    
    /**
     * Hata logla
     */
    public static function error($message, $code = 500) {
        $logMessage = sprintf(
            "[ERROR %d] %s - %s",
            $code,
            date('Y-m-d H:i:s'),
            $message
        );
        
        self::log($logMessage);
        
        if (http_response_code() === false || http_response_code() === 200) {
            http_response_code($code);
        }
    }
    
    /**
     * Log dosyasına yaz
     */
    private static function log($message) {
        if (self::$logPath) {
            $logDir = dirname(self::$logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            @file_put_contents(
                self::$logPath,
                $message . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
        
        // Ayrıca PHP error log'una da yaz
        error_log($message);
    }
}

