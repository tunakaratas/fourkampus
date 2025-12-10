<?php
/**
 * Partner Logos API
 * Marketing sayfası için tüm toplulukların partner logolarını döndürür
 */

header('Content-Type: application/json; charset=utf-8');

// CORS headers (gerekirse)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function sendResponse($success, $data = null, $message = null, $error = null) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Tüm toplulukların veritabanı dosyalarını bul
    $communities_dir = __DIR__ . '/../communities';
    $public_communities_dir = __DIR__ . '/../public/communities';
    
    $all_logos = [];
    
    // Her iki dizini de kontrol et
    $dirs_to_check = [];
    if (is_dir($communities_dir)) {
        $dirs_to_check[] = $communities_dir;
    }
    if (is_dir($public_communities_dir)) {
        $dirs_to_check[] = $public_communities_dir;
    }
    
    foreach ($dirs_to_check as $base_dir) {
        $folders = scandir($base_dir);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            
            $db_path = $base_dir . '/' . $folder . '/database.db';
            if (!file_exists($db_path)) continue;
            
            try {
                $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
                $db->busyTimeout(5000);
                
                // Partner logos tablosunu kontrol et
                $table_check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='partner_logos'");
                if (!$table_check->fetchArray()) {
                    $db->close();
                    continue;
                }
                
                // Partner logolarını çek
                $stmt = $db->prepare("SELECT partner_name, partner_website, logo_path FROM partner_logos ORDER BY created_at DESC");
                $result = $stmt->execute();
                
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    // Logo path'i düzelt (relative path'i absolute'e çevir)
                    $logo_path = $row['logo_path'];
                    if (strpos($logo_path, 'http') === 0) {
                        // Zaten URL ise olduğu gibi kullan
                        $row['logo_url'] = $logo_path;
                    } else {
                        // Relative path ise, community path'e göre düzelt
                        $full_path = $base_dir . '/' . $folder . '/' . $logo_path;
                        if (file_exists($full_path)) {
                            // Public URL oluştur
                            $row['logo_url'] = '../communities/' . $folder . '/' . $logo_path;
                        } else {
                            continue; // Dosya yoksa atla
                        }
                    }
                    
                    $all_logos[] = [
                        'partner_name' => $row['partner_name'],
                        'partner_website' => $row['partner_website'] ?? null,
                        'logo_path' => $row['logo_url'] ?? $logo_path,
                        'logo' => $row['logo_url'] ?? $logo_path
                    ];
                }
                
                $db->close();
            } catch (Exception $e) {
                // Bu topluluğun veritabanında hata varsa devam et
                error_log("Partner logos error for {$folder}: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // Logoları karıştır ve döndür (her topluluktan eşit dağılım için)
    shuffle($all_logos);
    
    sendResponse(true, ['logos' => $all_logos], 'Partner logoları başarıyla yüklendi');
    
} catch (Exception $e) {
    error_log("Partner logos API error: " . $e->getMessage());
    sendResponse(false, null, null, 'Partner logoları yüklenirken bir hata oluştu');
}

