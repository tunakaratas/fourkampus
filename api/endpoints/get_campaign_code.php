<?php
/**
 * Kullanıcıya Özel Kampanya Kodu Alma API
 * Her kullanıcıya kampanya için benzersiz kod oluşturur
 */

// Error reporting açık (development için)
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSON response için display_errors kapalı
ini_set('log_errors', 1);

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Global exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    error_log("Stack trace: " . $exception->getTraceAsString());
    error_log("File: " . $exception->getFile() . " Line: " . $exception->getLine());
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Sunucu hatası: ' . $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

function sendResponse($success, $data = null, $message = null, $error = null) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message
    ];
    
    if ($error) {
        $response['error'] = $error;
    }
    
    // Success durumunda 200, hata durumunda 400 döndür
    // Ama Swift tarafında decode edilebilmesi için her zaman JSON döndür
    http_response_code($success ? 200 : 400);
    
    header('Content-Type: application/json; charset=utf-8');
    
    // JSON encode hatası kontrolü
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log("JSON encode hatası: " . json_last_error_msg());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => null,
            'error' => 'Response oluşturulamadı'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    echo $json;
    exit;
}

try {
    // Auth kontrolü - requireAuth kullan (zorunlu)
    // requireAuth(true) zaten hata durumunda exit yapar, buraya gelirse kullanıcı authenticated
    $currentUser = requireAuth(true);
    
    // requireAuth başarılı olduysa kullanıcı bilgileri var
    if (!$currentUser || !isset($currentUser['id'])) {
        error_log("Get campaign code: requireAuth başarılı ama user_id yok");
        sendResponse(false, null, null, 'Kullanıcı bilgileri alınamadı');
    }
    
    $user_id = (string)$currentUser['id'];
    
    error_log("Get campaign code: User ID: $user_id");
    
    // Campaign ID ve Community ID al
    $campaign_id = isset($_GET['campaign_id']) ? trim($_GET['campaign_id']) : null;
    $community_id_param = isset($_GET['community_id']) ? trim($_GET['community_id']) : null;
    
    error_log("Get campaign code: campaign_id=$campaign_id, community_id=$community_id_param");
    error_log("Get campaign code: GET params: " . print_r($_GET, true));
    
    if (!$campaign_id) {
        sendResponse(false, null, null, 'Kampanya ID gerekli');
    }
    
    // Community ID'yi campaign_id'den çıkar (format: community_id-campaign_id) veya parametre olarak al
    $community_id = null;
    $actual_campaign_id = null;
    
    if ($community_id_param) {
        // Community ID parametre olarak gönderilmişse
        try {
            $community_id = sanitizeCommunityId($community_id_param);
            $actual_campaign_id = (int)$campaign_id;
            error_log("Get campaign code: Using community_id param: $community_id, campaign_id: $actual_campaign_id");
        } catch (Exception $e) {
            error_log("Get campaign code: sanitizeCommunityId error: " . $e->getMessage());
            sendResponse(false, null, null, 'Geçersiz community ID: ' . $e->getMessage());
        }
    } else {
        // Campaign ID format: community_id-campaign_id
        $parts = explode('-', $campaign_id);
        if (count($parts) === 2) {
            try {
                $community_id = sanitizeCommunityId($parts[0]);
                $actual_campaign_id = (int)$parts[1];
                error_log("Get campaign code: Parsed from campaign_id: community_id=$community_id, campaign_id=$actual_campaign_id");
            } catch (Exception $e) {
                error_log("Get campaign code: sanitizeCommunityId error: " . $e->getMessage());
                sendResponse(false, null, null, 'Geçersiz campaign ID formatı: ' . $e->getMessage());
            }
        } else {
            // Sadece campaign_id gönderilmişse, community_id parametre olarak gerekli
            error_log("Get campaign code: campaign_id format invalid, expecting community_id param");
            sendResponse(false, null, null, 'Community ID gerekli (campaign_id parametresi ile birlikte)');
        }
    }
    
    if (!$community_id || !$actual_campaign_id) {
        error_log("Get campaign code: Invalid IDs - community_id=$community_id, campaign_id=$actual_campaign_id");
        sendResponse(false, null, null, 'Geçersiz kampanya veya topluluk ID');
    }
    
    // Community veritabanına bağlan
    // campaigns.php ile aynı yolu kullan: /../communities
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        // Fallback: public/communities dizinini de kontrol et
        $public_communities_dir = __DIR__ . '/../public/communities';
        $public_db_path = $public_communities_dir . '/' . $community_id . '/unipanel.sqlite';
        
        if (file_exists($public_db_path)) {
            $db_path = $public_db_path;
        } else {
            error_log("Get campaign code: Veritabanı bulunamadı - community_id: $community_id");
            error_log("Get campaign code: Denenen yollar:");
            error_log("  1. $communities_dir/$community_id/unipanel.sqlite");
            error_log("  2. $public_communities_dir/$community_id/unipanel.sqlite");
            error_log("Get campaign code: Mevcut dizinler:");
            if (is_dir($communities_dir)) {
                $dirs = glob($communities_dir . '/*', GLOB_ONLYDIR);
                error_log("  communities dizini: " . implode(', ', array_map('basename', $dirs)));
            }
            if (is_dir($public_communities_dir)) {
                $dirs = glob($public_communities_dir . '/*', GLOB_ONLYDIR);
                error_log("  public/communities dizini: " . implode(', ', array_map('basename', $dirs)));
            }
            sendResponse(false, null, null, "Topluluk bulunamadı (ID: $community_id)");
        }
    }
    
    try {
        $db = new SQLite3($db_path);
        if (!$db) {
            throw new Exception('SQLite3 bağlantısı oluşturulamadı');
        }
    } catch (Exception $e) {
        error_log("SQLite3 bağlantı hatası: " . $e->getMessage());
        sendResponse(false, null, null, 'Veritabanı bağlantısı kurulamadı: ' . $e->getMessage());
    }
    
    try {
        @$db->exec('PRAGMA journal_mode = DELETE');
    } catch (Exception $e) {
        error_log("PRAGMA hatası: " . $e->getMessage());
        // PRAGMA hatası kritik değil, devam et
    }
    
    // campaign_user_codes tablosunu oluştur
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS campaign_user_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            user_id TEXT NOT NULL,
            code TEXT NOT NULL UNIQUE,
            qr_code_data TEXT,
            used INTEGER DEFAULT 0,
            used_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
        )");
        
        // Index oluştur
        $db->exec("CREATE INDEX IF NOT EXISTS idx_code ON campaign_user_codes(code)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_campaign_user ON campaign_user_codes(campaign_id, user_id)");
    } catch (Exception $e) {
        error_log("Tablo oluşturma hatası: " . $e->getMessage());
        $db->close();
        sendResponse(false, null, null, 'Tablo oluşturulamadı: ' . $e->getMessage());
    }
    
    // Kampanyayı kontrol et
    try {
        $campaign_stmt = $db->prepare("SELECT id, title, start_date, end_date, is_active, requires_membership FROM campaigns WHERE id = ?");
        if (!$campaign_stmt) {
            throw new Exception('SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $campaign_stmt->bindValue(1, $actual_campaign_id, SQLITE3_INTEGER);
        $campaign_result = $campaign_stmt->execute();
        if (!$campaign_result) {
            throw new Exception('SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $campaign = $campaign_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$campaign) {
            $db->close();
            error_log("Kampanya bulunamadı: campaign_id=$actual_campaign_id, community_id=$community_id");
            sendResponse(false, null, null, "Kampanya bulunamadı (ID: $actual_campaign_id)");
        }
    } catch (Exception $e) {
        error_log("Kampanya sorgusu hatası: " . $e->getMessage());
        $db->close();
        sendResponse(false, null, null, 'Kampanya sorgusu hatası: ' . $e->getMessage());
    }
    
    // Kampanya aktif mi kontrol et
    $now = date('Y-m-d H:i:s');
    if ($campaign['is_active'] != 1) {
        $db->close();
        sendResponse(false, null, null, 'Bu kampanya aktif değil');
    }
    
    if ($campaign['start_date'] > $now || $campaign['end_date'] < $now) {
        $db->close();
        sendResponse(false, null, null, 'Bu kampanya şu anda geçerli değil');
    }
    
    // Üyelik kontrolü - requires_membership varsa ve 1 ise kontrol et
    $requires_membership = isset($campaign['requires_membership']) ? (int)$campaign['requires_membership'] : 0;
    if ($requires_membership == 1) {
        // Üyelik durumunu kontrol et (members tablosundan e-posta ile)
        try {
            $user_email = strtolower(trim($currentUser['email'] ?? ''));
            $student_id = trim($currentUser['student_id'] ?? '');
            
            // Üyelik kontrolü - hem members hem de approved membership_requests
            $is_member = false;
            
            // Önce members tablosunu kontrol et
            $member_check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
            if ($member_check) {
                $member_check->bindValue(1, $user_email, SQLITE3_TEXT);
                $member_check->bindValue(2, $student_id, SQLITE3_TEXT);
                $member_result = $member_check->execute();
                if ($member_result) {
                    $member = $member_result->fetchArray(SQLITE3_ASSOC);
                    if ($member) {
                        $is_member = true;
                    }
                }
            }
            
            // Eğer members tablosunda yoksa, approved membership_requests'i kontrol et
            if (!$is_member) {
                $request_check = $db->prepare("SELECT id, status FROM membership_requests WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) ORDER BY created_at DESC LIMIT 1");
                if ($request_check) {
                    $request_check->bindValue(1, $user_email, SQLITE3_TEXT);
                    $request_check->bindValue(2, $student_id, SQLITE3_TEXT);
                    $request_result = $request_check->execute();
                    if ($request_result) {
                        $request = $request_result->fetchArray(SQLITE3_ASSOC);
                        if ($request && ($request['status'] ?? '') === 'approved') {
                            $is_member = true;
                        }
                    }
                }
            }
            
            if (!$is_member) {
                error_log("Üyelik kontrolü başarısız: email=$user_email, student_id=$student_id - members tablosunda ve approved requests'te bulunamadı");
                $db->close();
                sendResponse(false, null, null, 'Bu kampanyadan yararlanmak için topluluğa üye olmanız gerekiyor');
            }
            error_log("Üyelik kontrolü başarılı: email=$user_email, student_id=$student_id - members tablosunda bulundu");
        } catch (Exception $e) {
            error_log("Üyelik kontrolü hatası: " . $e->getMessage());
            $db->close();
            sendResponse(false, null, null, 'Üyelik kontrolü hatası: ' . $e->getMessage());
        }
    } else {
        error_log("Kampanya üyelik gerektirmiyor: requires_membership=$requires_membership");
    }
    
    // Kullanıcının bu kampanya için zaten bir kodu var mı kontrol et
    try {
        $existing_stmt = $db->prepare("SELECT id, code, qr_code_data, used, used_at FROM campaign_user_codes WHERE campaign_id = ? AND user_id = ?");
        if (!$existing_stmt) {
            throw new Exception('SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $existing_stmt->bindValue(1, $actual_campaign_id, SQLITE3_INTEGER);
        $existing_stmt->bindValue(2, $user_id, SQLITE3_TEXT);
        $existing_result = $existing_stmt->execute();
        if (!$existing_result) {
            throw new Exception('SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
        $existing = $existing_result->fetchArray(SQLITE3_ASSOC);
    } catch (Exception $e) {
        error_log("Mevcut kod sorgusu hatası: " . $e->getMessage());
        $db->close();
        sendResponse(false, null, null, 'Kod sorgusu hatası: ' . $e->getMessage());
    }
    
    if ($existing) {
        // Mevcut kodu döndür
        $db->close();
        sendResponse(true, [
            'code' => $existing['code'],
            'qr_code_data' => $existing['qr_code_data'] ?? null,
            'used' => $existing['used'] == 1,
            'used_at' => $existing['used_at'] ?? null,
            'campaign_id' => $campaign_id,
            'campaign_title' => $campaign['title']
        ], 'Kampanya kodunuz');
    }
    
    // Yeni kod oluştur
    // Format: CAMPAIGN_ID-USER_ID-SHORT_HASH (örn: 123-abc123-A7B9)
    $short_hash = strtoupper(substr(md5($user_id . $actual_campaign_id . time()), 0, 4));
    $code = $actual_campaign_id . '-' . substr($user_id, 0, 6) . '-' . $short_hash;
    
    // QR kod verisi oluştur (JSON formatında)
    $qr_data = [
        'campaign_id' => $campaign_id,
        'code' => $code,
        'user_id' => $user_id,
        'created_at' => $now
    ];
    $qr_code_data = json_encode($qr_data, JSON_UNESCAPED_UNICODE);
    
    // Kodu veritabanına kaydet
    try {
        $insert_stmt = $db->prepare("INSERT INTO campaign_user_codes (campaign_id, user_id, code, qr_code_data) VALUES (?, ?, ?, ?)");
        if (!$insert_stmt) {
            throw new Exception('SQL hazırlama hatası: ' . $db->lastErrorMsg());
        }
        $insert_stmt->bindValue(1, $actual_campaign_id, SQLITE3_INTEGER);
        $insert_stmt->bindValue(2, $user_id, SQLITE3_TEXT);
        $insert_stmt->bindValue(3, $code, SQLITE3_TEXT);
        $insert_stmt->bindValue(4, $qr_code_data, SQLITE3_TEXT);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('SQL çalıştırma hatası: ' . $db->lastErrorMsg());
        }
    } catch (Exception $e) {
        error_log("Kod kaydetme hatası: " . $e->getMessage());
        $db->close();
        sendResponse(false, null, null, 'Kod oluşturulamadı: ' . $e->getMessage());
    }
    
    $db->close();
    
    sendResponse(true, [
        'code' => $code,
        'qr_code_data' => $qr_code_data,
        'used' => false,
        'used_at' => null,
        'campaign_id' => $campaign_id,
        'campaign_title' => $campaign['title']
    ], 'Kampanya kodunuz oluşturuldu');
    
} catch (Exception $e) {
    error_log("Get campaign code error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    // Detaylı hata mesajı (production'da gizlenebilir)
    $errorMessage = 'Kod oluşturma sırasında bir hata oluştu: ' . $e->getMessage();
    
    http_response_code(500);
    sendResponse(false, null, null, $errorMessage);
}

