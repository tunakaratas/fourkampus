<?php
/**
 * Events Helper Functions
 * Güvenli veritabanı işlemleri için yardımcı fonksiyonlar
 */

if (!function_exists('safe_prepare')) {
    /**
     * Güvenli prepare() çağrısı - false dönerse exception fırlatır
     */
    function safe_prepare($db, $sql, $error_context = '') {
        // Veritabanı kontrolü
        if (!$db || !is_object($db)) {
            $error_msg = "Veritabanı bağlantısı geçersiz";
            error_log("[ERROR] safe_prepare: $error_msg | Context: $error_context");
            throw new Exception("Veritabanı bağlantısı geçersiz" . ($error_context ? " ($error_context)" : ''));
        }
        
        // prepare() çağrısını try-catch ile koru
        try {
            $stmt = $db->prepare($sql);
        } catch (Exception $e) {
            $error_msg = $db->lastErrorMsg();
            $log_msg = "prepare() exception. Context: $error_context | SQL: " . substr($sql, 0, 200) . "... | Exception: " . $e->getMessage() . " | DB Error: $error_msg";
            error_log("[ERROR] safe_prepare: $log_msg");
            if (function_exists('tpl_error_log')) {
                tpl_error_log($log_msg);
            }
            throw new Exception("Veritabanı sorgusu hazırlanamadı: " . ($error_msg ?: $e->getMessage()) . ($error_context ? " ($error_context)" : ''));
        }
        
        // prepare() false dönerse
        if (!$stmt || $stmt === false) {
            $error_msg = $db->lastErrorMsg();
            $log_msg = "prepare() false döndü. Context: $error_context | SQL: " . substr($sql, 0, 200) . "... | DB Error: $error_msg";
            error_log("[ERROR] safe_prepare: $log_msg");
            if (function_exists('tpl_error_log')) {
                tpl_error_log($log_msg);
            }
            throw new Exception("Veritabanı sorgusu hazırlanamadı: " . ($error_msg ?: 'Bilinmeyen hata') . ($error_context ? " ($error_context)" : ''));
        }
        
        return $stmt;
    }
}

if (!function_exists('safe_execute')) {
    /**
     * Güvenli execute() çağrısı - false dönerse exception fırlatır
     */
    function safe_execute($stmt, $db, $error_context = '') {
        // Statement kontrolü
        if (!$stmt || $stmt === false || !is_object($stmt)) {
            $error_msg = "Statement geçersiz";
            error_log("[ERROR] safe_execute: $error_msg | Context: $error_context");
            throw new Exception("Veritabanı statement'ı geçersiz" . ($error_context ? " ($error_context)" : ''));
        }
        
        // execute() çağrısını try-catch ile koru
        try {
            $result = $stmt->execute();
        } catch (Exception $e) {
            $error_msg = $db ? $db->lastErrorMsg() : 'DB bağlantısı yok';
            $log_msg = "execute() exception. Context: $error_context | Exception: " . $e->getMessage() . " | DB Error: $error_msg";
            error_log("[ERROR] safe_execute: $log_msg");
            if (function_exists('tpl_error_log')) {
                tpl_error_log($log_msg);
            }
            throw new Exception("Veritabanı sorgusu çalıştırılamadı: " . ($error_msg ?: $e->getMessage()) . ($error_context ? " ($error_context)" : ''));
        }
        
        // execute() false dönerse
        if (!$result || $result === false) {
            $error_msg = $db ? $db->lastErrorMsg() : 'DB bağlantısı yok';
            $log_msg = "execute() false döndü. Context: $error_context | DB Error: $error_msg";
            error_log("[ERROR] safe_execute: $log_msg");
            if (function_exists('tpl_error_log')) {
                tpl_error_log($log_msg);
            }
            throw new Exception("Veritabanı sorgusu çalıştırılamadı: " . ($error_msg ?: 'Bilinmeyen hata') . ($error_context ? " ($error_context)" : ''));
        }
        
        return $result;
    }
}

