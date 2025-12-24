<?php
/**
 * Mobil API - Universities Endpoint
 * GET /api/universities.php - Tüm üniversiteleri listele
 */

require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../university_helper.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $communities_dir = __DIR__ . '/../../communities';
    $universities = [];
    $universityMap = [];
    
    // Master listeyi al
    $masterList = getUniversityList();
    foreach ($masterList as $uniName) {
        $slug = getUniversitySlug($uniName);
        $universityMap[$slug] = [
            'id' => $slug,
            'name' => $uniName,
            'community_count' => 0
        ];
    }
    
    if (is_dir($communities_dir)) {
        $dirs = scandir($communities_dir);
        $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
        
        // Tüm toplulukları tarayarak üniversiteleri bul ve sayıları topla
        foreach ($dirs as $dir) {
            if (in_array($dir, $excluded_dirs) || !is_dir($communities_dir . '/' . $dir)) {
                continue;
            }
            
            $db_path = $communities_dir . '/' . $dir . '/unipanel.sqlite';
            if (!file_exists($db_path)) {
                continue;
            }
            
            try {
                $db = new SQLite3($db_path);
                $db->exec('PRAGMA journal_mode = WAL');
                
                $settings_query = @$db->query("SELECT setting_value FROM settings WHERE setting_key = 'university' AND club_id = 1");
                if ($settings_query) {
                    $row = $settings_query->fetchArray(SQLITE3_ASSOC);
                    $university = $row['setting_value'] ?? null;
                    
                    if ($university) {
                        $slug = getUniversitySlug($university);
                        if (isset($universityMap[$slug])) {
                            $universityMap[$slug]['community_count']++;
                        } else {
                            // Master listede yoksa bile ekle
                            $universityMap[$slug] = [
                                'id' => $slug,
                                'name' => $university,
                                'community_count' => 1
                            ];
                        }
                    }
                }
                
                $db->close();
            } catch (Exception $e) {
                // Hata durumunda devam et
            }
        }
    }
    
    // Map'i array'e çevir
    $universities = array_values($universityMap);
    
    // İsme göre sırala (Tümü hariç)
    usort($universities, function($a, $b) {
        if ($a['name'] === 'Diğer') return 1;
        if ($b['name'] === 'Diğer') return -1;
        return strcmp($a['name'], $b['name']);
    });
    
    // "Tümü" seçeneğini başa ekle
    array_unshift($universities, [
        'id' => 'all',
        'name' => 'Tümü',
        'community_count' => 0
    ]);
    
    sendResponse(true, $universities);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}


