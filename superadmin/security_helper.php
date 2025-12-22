<?php
/**
 * Security Helper Functions
 * Provides CSRF protection and XSS escaping.
 */

if (session_status() === PHP_SESSION_NONE) {
    // SuperAdmin paneli için özel session adı kullan
    session_name('FK_SUPERADMIN');
    session_start();
}

/**
 * Generates a CSRF token and stores it in the session.
 * @return string The generated token.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies the CSRF token from the request.
 * @param string|null $token The token to verify (usually from $_POST['csrf_token']).
 * @return bool True if valid, false otherwise.
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escapes output for HTML context to prevent XSS.
 * @param string|null $string The string to escape.
 * @return string The escaped string.
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Regenerates the session ID to prevent session fixation.
 * Should be called on successful login.
 */
function regenerate_session_secure(): void {
    session_regenerate_id(true);
}

/**
 * Returns a hidden input field with CSRF token.
 * @return string HTML input field with CSRF token.
 */
function get_csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
