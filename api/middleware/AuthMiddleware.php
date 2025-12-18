<?php
/**
 * Authentication Middleware
 * 
 * Korumalı endpoint'ler için authentication kontrolü yapar
 */

require_once __DIR__ . '/../auth_middleware.php';

class AuthMiddleware {
    public function handle() {
        $user = requireAuth(true);
        if (!$user) {
            APIResponse::unauthorized('Yetkilendirme gerekli. Lütfen giriş yapın.');
        }
        
        // User'ı global olarak erişilebilir yap
        $GLOBALS['currentUser'] = $user;
    }
}
