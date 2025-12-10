<?php
require_once __DIR__ . '/security_helper.php';
/**
 * Reklamlar API Endpoint
 * Swift uygulaması için reklamları sağlar
 */

require_once __DIR__ . '/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../bootstrap/community_stubs.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$superadmin_db = __DIR__ . '/../unipanel.sqlite';

try {
    if (!file_exists($superadmin_db)) {
        throw new Exception("Veritabanı bulunamadı");
    }
    
    $db = new SQLite3($superadmin_db);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Reklamlar tablosunu oluştur (eğer yoksa)
    $db->exec("CREATE TABLE IF NOT EXISTS ads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        image_url TEXT,
        logo_url TEXT,
        call_to_action TEXT DEFAULT 'Keşfet',
        advertiser TEXT NOT NULL,
        rating REAL,
        click_url TEXT,
        status TEXT DEFAULT 'active',
        priority INTEGER DEFAULT 0,
        start_date DATETIME,
        end_date DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    if ($method === 'GET') {
        // Aktif reklamları getir
        // Tarih kontrolünü basitleştir - SQLite datetime fonksiyonunu kullan
        // Tarih formatını normalize et (T'yi boşlukla değiştir)
        $now = date('Y-m-d H:i:s');
        
        // Debug: Tarih kontrolü için log
        error_log("Ads API: Şu anki tarih: " . $now);
        
        // Önce tüm aktif reklamları getir (tarih kontrolü olmadan test için)
        // Sonra PHP tarafında tarih kontrolü yapacağız
        $query = "SELECT * FROM ads 
                  WHERE status = 'active' 
                  ORDER BY priority DESC, created_at DESC";
        
        $result = $db->query($query);
        
        // Debug: Sorgu sonucunu logla
        if (!$result) {
            error_log("Ads API: Sorgu hatası: " . $db->lastErrorMsg());
        }
        
        $ads = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Tarih kontrolünü PHP tarafında yap
            $startDate = $row['start_date'] ?? null;
            $endDate = $row['end_date'] ?? null;
            
            // Tarih kontrolü geçici olarak devre dışı - tüm aktif reklamları göster
            // TODO: Tarih kontrolünü düzgün bir şekilde implement et
            
            // ID'yi string'e çevir (Swift uyumluluğu için)
            $row['id'] = (string)$row['id'];
            
            // Image URL'i düzelt (relative path ise tam URL'e çevir)
            if (!empty($row['image_url'])) {
                // Eğer zaten tam URL ise (http:// veya https:// ile başlıyorsa) değiştirme
                if (strpos($row['image_url'], 'http://') === 0 || strpos($row['image_url'], 'https://') === 0) {
                    // Zaten tam URL, değiştirme
                } elseif (strpos($row['image_url'], '/assets/images/ads/') === 0) {
                    // Local yüklenen fotoğraf - tam URL oluştur
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    // localhost için /unipanel path'ini ekle
                    // iOS Simulator için 127.0.0.1 kullan (localhost yerine)
                    if ($host === 'localhost' || $host === 'localhost:80' || $host === '127.0.0.1') {
                        $host = '127.0.0.1'; // iOS Simulator uyumluluğu için
                        $row['image_url'] = $protocol . '://' . $host . '/unipanel' . $row['image_url'];
                    } else {
                        $row['image_url'] = $protocol . '://' . $host . $row['image_url'];
                    }
                }
            }
            
            // Logo URL'i düzelt (relative path ise tam URL'e çevir)
            if (!empty($row['logo_url'])) {
                // Eğer zaten tam URL ise (http:// veya https:// ile başlıyorsa) değiştirme
                if (strpos($row['logo_url'], 'http://') === 0 || strpos($row['logo_url'], 'https://') === 0) {
                    // Zaten tam URL, değiştirme
                } elseif (strpos($row['logo_url'], '/assets/images/ads/') === 0) {
                    // Local yüklenen logo - tam URL oluştur
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    // localhost için /unipanel path'ini ekle
                    // iOS Simulator için 127.0.0.1 kullan (localhost yerine)
                    if ($host === 'localhost' || $host === 'localhost:80' || $host === '127.0.0.1') {
                        $host = '127.0.0.1'; // iOS Simulator uyumluluğu için
                        $row['logo_url'] = $protocol . '://' . $host . '/unipanel' . $row['logo_url'];
                    } else {
                        $row['logo_url'] = $protocol . '://' . $host . $row['logo_url'];
                    }
                }
            }
            
            $ads[] = $row;
        }
        
        // Debug: Kaç reklam bulunduğunu logla
        error_log("Ads API: Bulunan reklam sayısı: " . count($ads));
        
        echo json_encode([
            'success' => true,
            'data' => $ads,
            'count' => count($ads),
            'message' => null,
            'error' => null
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Method not allowed',
            'error' => 'Only GET method is supported'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    $db->close();
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

