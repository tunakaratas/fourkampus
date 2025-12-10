<?php
if (!function_exists('tpl_safe_error')) {
    /**
     * Logs a detailed error while showing a generic message to the user.
     */
    function tpl_safe_error(
        string $userMessage,
        string $logMessage,
        string $logLevel = 'error'
    ): void {
        tpl_error_log($logMessage, $logLevel);
        if (!headers_sent()) {
            $_SESSION['error'] = $userMessage;
        }
    }
}

