<?php
/**
 * Mobil API - Verified Communities Endpoint
 * GET /api/verified_communities.php
 *
 * Tüm toplulukların doğrulama durumunu listeler. Sadece "approved" olanlar döner.
 * Çıktı, mobil (Swift / Kotlin) uygulamalarında "mavi tik" verisini göstermek için kullanılabilir.
 */

require_once __DIR__ . '/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/connection_pool.php';
require_once __DIR__ . '/auth_middleware.php';

// İsteğe bağlı: public endpoint olduğu için "guest" olarak doğrulayabiliriz (gerekirse token zorunlu hale getirilebilir)
checkRateLimit(60, 60); // dakikada 60 istek

function sendResponse($success, $data = null, $message = null, $error = null, int $statusCode = 200)
{
    http_response_code($statusCode);
    $json = json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => null,
            'error' => 'JSON encoding hatası: ' . json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo $json;
    exit;
}

function buildBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return rtrim($scheme . '://' . $host . $scriptDir, '/');
}

try {
    $communitiesDir = realpath(__DIR__ . '/../communities');
    if ($communitiesDir === false || !is_dir($communitiesDir)) {
        sendResponse(true, []); // topluluk yoksa boş array dön
    }

    $entries = scandir($communitiesDir);
    if ($entries === false) {
        sendResponse(false, null, null, 'Topluluk dizini okunamadı', 500);
    }

    $verifiedList = [];
    $baseUrl = buildBaseUrl();

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $communityPath = $communitiesDir . '/' . $entry;
        if (!is_dir($communityPath)) {
            continue;
        }

        $dbPath = $communityPath . '/unipanel.sqlite';
        if (!file_exists($dbPath)) {
            continue;
        }

        try {
            $connResult = ConnectionPool::getConnection($dbPath, false);
            if (!$connResult) {
                continue;
            }
            $db = $connResult['db'];
            $poolId = $connResult['pool_id'];

            $hasTable = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='community_verifications'");
            if (!$hasTable) {
                ConnectionPool::releaseConnection($dbPath, $poolId, false);
                continue;
            }

            $verificationStmt = $db->prepare("SELECT status, document_path, admin_notes, created_at, reviewed_at, updated_at 
                                              FROM community_verifications 
                                              ORDER BY created_at DESC LIMIT 1");
            $verificationResult = $verificationStmt ? $verificationStmt->execute() : false;
            $latestVerification = $verificationResult ? $verificationResult->fetchArray(SQLITE3_ASSOC) : null;

            if (!$latestVerification || ($latestVerification['status'] ?? '') !== 'approved') {
                ConnectionPool::releaseConnection($dbPath, $poolId, false);
                continue;
            }

            $clubNameStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'club_name' AND club_id = 1 LIMIT 1");
            $clubNameResult = $clubNameStmt ? $clubNameStmt->execute() : false;
            $clubRow = $clubNameResult ? $clubNameResult->fetchArray(SQLITE3_ASSOC) : null;
            $clubName = $clubRow && !empty($clubRow['setting_value']) ? $clubRow['setting_value'] : $entry;

            $documentPath = $latestVerification['document_path'] ?? '';
            $relativeDocumentPath = $documentPath ? 'communities/' . $entry . '/' . ltrim($documentPath, '/') : null;
            $documentUrl = $relativeDocumentPath ? $baseUrl . '/../' . $relativeDocumentPath : null;

            $verifiedList[] = [
                'community_id' => $entry,
                'community_name' => $clubName,
                'verified' => true,
                'document_path' => $documentPath,
                'document_url' => $documentUrl,
                'admin_notes' => $latestVerification['admin_notes'] ?? null,
                'reviewed_at' => $latestVerification['reviewed_at'] ?? $latestVerification['updated_at'] ?? $latestVerification['created_at'] ?? null,
                'updated_at' => $latestVerification['updated_at'] ?? $latestVerification['created_at'] ?? null
            ];

            ConnectionPool::releaseConnection($dbPath, $poolId, false);
        } catch (Exception $e) {
            if (isset($dbPath, $poolId)) {
                ConnectionPool::releaseConnection($dbPath, $poolId ?? null, false);
            }
            continue;
        }
    }

    sendResponse(true, [
        'count' => count($verifiedList),
        'items' => $verifiedList
    ]);
} catch (Exception $e) {
    sendResponse(false, null, null, 'Sunucu hatası: ' . $e->getMessage(), 500);
}

