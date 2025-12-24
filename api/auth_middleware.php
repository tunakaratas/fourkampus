<?php
/**
 * API Authentication Middleware
 * Token tabanlı authentication kontrolü
 */

require_once __DIR__ . '/security_helper.php';

/**
 * getallheaders() fallback - Bazı sunucularda mevcut değil
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header_name] = $value;
            }
        }
        // HTTP_AUTHORIZATION özel durumu (Apache mod_rewrite ile)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        return $headers;
    }
}

/**
 * Authorization header'dan token'ı al
 */
function getAuthToken() {
    $headers = getallheaders();
    
    // Authorization header'ı kontrol et
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        // "Bearer TOKEN" formatından token'ı çıkar
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (!empty($token)) {
                return $token;
            }
        }
    }
    
    // Alternatif olarak direkt Authorization header'ı kontrol et (case-insensitive)
    if (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (!empty($token)) {
                return $token;
            }
        }
    }
    
    // HTTP_AUTHORIZATION server variable kontrolü (bazı sunucularda gerekli)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (!empty($token)) {
                return $token;
            }
        }
    }
    
    // REDIRECT_HTTP_AUTHORIZATION kontrolü (Apache mod_rewrite ile)
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (!empty($token)) {
                return $token;
            }
        }
    }
    
    return null;
}

/**
 * Token'ı doğrula ve kullanıcı bilgilerini döndür
 * @param string|null $token
 * @return array|null Kullanıcı bilgileri veya null
 */
function validateToken($token) {
    if (empty($token)) {
        return null;
    }
    
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        secureLog("validateToken: Database dosyası bulunamadı", 'error');
        return null;
    }
    
    try {
        $db = new SQLite3($system_db_path);
        @$db->exec('PRAGMA journal_mode = DELETE');
        
        // Token'ı hash'le ve kontrol et
        $token_hash = hash('sha256', $token);
        
        // Önce token_hash ile kontrol et, yoksa eski token ile (backward compatibility)
        $stmt = $db->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, t.id as token_id, t.expires_at, t.revoked_at, t.token as plain_token
            FROM api_tokens t
            JOIN system_users u ON t.user_id = u.id
            WHERE (t.token_hash = ? OR t.token = ?)
              AND u.is_active = 1
        ");
        $stmt->bindValue(1, $token_hash, SQLITE3_TEXT);
        $stmt->bindValue(2, $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user) {
            // Token durumunu kontrol et
            $expiresAt = $user['expires_at'] ?? null;
            $revokedAt = $user['revoked_at'] ?? null;
            
            // Expiration kontrolü
            if ($expiresAt && strtotime($expiresAt) <= time()) {
                $db->close();
                return null;
            }
            
            // Revoked kontrolü
            if ($revokedAt) {
                $db->close();
                return null;
            }
            
            // Eğer token_hash yoksa ve plain_token varsa, token_hash'i güncelle (migration)
            if (empty($user['token_hash']) && !empty($user['plain_token']) && $user['plain_token'] === $token) {
                try {
                    $update_hash = $db->prepare("UPDATE api_tokens SET token_hash = ? WHERE id = ?");
                    $update_hash->bindValue(1, $token_hash, SQLITE3_TEXT);
                    $update_hash->bindValue(2, $user['token_id'], SQLITE3_INTEGER);
                    @$update_hash->execute();
                } catch (Exception $e) {
                    secureLog("validateToken: Token hash güncelleme hatası: " . $e->getMessage(), 'warning');
                }
            }
            
            // Token kullanım zamanını güncelle
            try {
                $update_stmt = $db->prepare("UPDATE api_tokens SET last_used_at = datetime('now') WHERE id = ?");
                $update_stmt->bindValue(1, $user['token_id'], SQLITE3_INTEGER);
                @$update_stmt->execute();
            } catch (Exception $e) {
                secureLog("validateToken: Token güncelleme hatası: " . $e->getMessage(), 'warning');
            }
            
            $db->close();
            
            return [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'is_active' => (bool)$user['is_active']
            ];
        }
        
        $db->close();
        return null;
    } catch (Exception $e) {
        secureLog("validateToken: Hata: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Authentication gerektiren endpoint'ler için middleware
 * @param bool $requireAuth - true ise zorunlu, false ise opsiyonel
 * @return array|null Kullanıcı bilgileri veya null
 */
function requireAuth($requireAuth = true) {
    $token = getAuthToken();
    $user = validateToken($token);
    
    if ($requireAuth && !$user) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => null,
            'error' => 'Yetkilendirme gerekli. Lütfen giriş yapın.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return $user;
}

/**
 * Opsiyonel authentication - token varsa kullanıcıyı döndür, yoksa null
 * @return array|null
 */
function optionalAuth() {
    return requireAuth(false);
}

/**
 * Rate limiting için basit kontrol (IP bazlı)
 * 10k kullanıcı için optimize edildi
 * @param int $maxRequests - Maksimum istek sayısı (default: 200/dakika - 10k kullanıcı için yeterli)
 * @param int $timeWindow - Zaman penceresi (saniye)
 * @return bool
 */
function checkRateLimit($maxRequests = 200, $timeWindow = 60) {
    try {
        // Güvenli IP adresini al
        $ip = getRealIP();
        
        // Cache dosyası yolu (güvenli hash)
        $ipHash = hash('sha256', $ip . 'unipanel_salt_2025');
        $cacheFile = __DIR__ . '/../system/cache/rate_limit_' . substr($ipHash, 0, 16) . '.json';
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            if (!@mkdir($cacheDir, 0755, true)) {
                // Klasör oluşturulamıyorsa - rate limiting'i atla, isteğe devam et
                return true;
            }
        }
        
        // Yazma izni yoksa atla
        if (!is_writable($cacheDir)) {
            return true;
        }
        
        $now = time();
        $requests = [];
        
        if (file_exists($cacheFile) && is_readable($cacheFile)) {
            $data = @json_decode(@file_get_contents($cacheFile), true);
            if ($data && isset($data['requests']) && is_array($data['requests'])) {
                $requests = $data['requests'];
            }
        }
        
        // Eski istekleri temizle (timeWindow dışında kalanlar)
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return is_numeric($timestamp) && ($now - (int)$timestamp) < $timeWindow;
        });
        
        // Yeni isteği ekle
        $requests[] = $now;
        
        // Limit kontrolü
        if (count($requests) > $maxRequests) {
            // Limit aşıldı - dosyayı güncelle
            @file_put_contents($cacheFile, json_encode(['requests' => array_slice($requests, -$maxRequests), 'last_updated' => $now]), LOCK_EX);
            return false;
        }
        
        // Cache'i güncelle (atomic write)
        @file_put_contents($cacheFile, json_encode(['requests' => $requests, 'last_updated' => $now]), LOCK_EX);
        return true;
    } catch (Exception $e) {
        // Rate limiting başarısız olursa, isteğe izin ver (fail-open)
        return true;
    } catch (Error $e) {
        // PHP Error durumunda da izin ver
        return true;
    }
}

