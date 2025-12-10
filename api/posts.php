<?php
/**
 * Mobil API - Posts Endpoint (Instagram benzeri Feed)
 * GET /api/posts.php - Tüm postları getir (feed)
 * GET /api/posts.php?post_id={id}&action=comments - Post yorumlarını getir
 * POST /api/posts.php?post_id={id} - Post beğen
 * POST /api/posts.php?action=comment - Yorum ekle
 */

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/auth_middleware.php';

// Rate limiting
if (!checkRateLimit(100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => null,
        'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
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
    $communities_dir = __DIR__ . '/../communities';
    
    // Comments action
    if (isset($_GET['action']) && $_GET['action'] === 'comments') {
        $currentUser = requireAuth(true);
        
        if (!isset($_GET['post_id']) || empty($_GET['post_id'])) {
            sendResponse(false, null, null, 'post_id parametresi gerekli');
        }
        
        $post_id = (int)$_GET['post_id'];
        
        // Post'un hangi topluluğa ait olduğunu bul
        $post = findPostInAllCommunities($post_id);
        if (!$post) {
            sendResponse(false, null, null, 'Post bulunamadı');
        }
        
        $db = new SQLite3($post['db_path']);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Yorumları çek
        $query = $db->prepare("SELECT * FROM post_comments WHERE post_id = ? ORDER BY created_at DESC");
        $query->bindValue(1, $post_id, SQLITE3_INTEGER);
        $result = $query->execute();
        
        $comments = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Kullanıcı bilgilerini al
            $user = getUserInfo($row['user_id']);
            
            // Beğeni sayısını al
            $likeCount = getCommentLikeCount($db, $row['id']);
            $isLiked = isCommentLiked($db, $row['id'], $currentUser['id']);
            
            $comments[] = [
                'id' => (int)$row['id'],
                'post_id' => (int)$row['post_id'],
                'user_id' => (int)$row['user_id'],
                'user_name' => $user['name'] ?? 'Kullanıcı',
                'user_avatar' => $user['avatar'] ?? null,
                'content' => sanitizeInput($row['content'] ?? '', 'string'), // XSS koruması
                'like_count' => $likeCount,
                'is_liked' => $isLiked,
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                'replies' => null // Şimdilik reply yok
            ];
        }
        
        $db->close();
        sendResponse(true, $comments);
    }
    
    // Add comment action
    if (isset($_GET['action']) && $_GET['action'] === 'comment') {
        $currentUser = requireAuth(true);
        
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['post_id']) || !isset($input['content']) || empty(trim($input['content']))) {
            sendResponse(false, null, null, 'post_id ve content parametreleri gerekli');
        }
        
        $post_id = (int)$input['post_id'];
        $content = sanitizeInput(trim($input['content']), 'string'); // XSS koruması
        
        // Post'u bul
        $post = findPostInAllCommunities($post_id);
        if (!$post) {
            sendResponse(false, null, null, 'Post bulunamadı');
        }
        
        $db = new SQLite3($post['db_path']);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Yorum tablosunu oluştur (yoksa)
        ensurePostCommentsTable($db);
        
        // Yorum ekle
        $insert = $db->prepare("INSERT INTO post_comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $insert->bindValue(1, $post_id, SQLITE3_INTEGER);
        $insert->bindValue(2, $currentUser['id'], SQLITE3_INTEGER);
        $insert->bindValue(3, $content, SQLITE3_TEXT);
        $insert->execute();
        
        $comment_id = $db->lastInsertRowID();
        
        // Kullanıcı bilgilerini al
        $user = getUserInfo($currentUser['id']);
        
        // Post'un comment_count'unu güncelle
        updatePostCommentCount($db, $post_id);
        
        $db->close();
        
        sendResponse(true, [
            'id' => (int)$comment_id,
            'post_id' => $post_id,
            'user_id' => (int)$currentUser['id'],
            'user_name' => sanitizeInput($user['name'] ?? 'Kullanıcı', 'string'),
            'user_avatar' => $user['avatar'] ?? null,
            'content' => sanitizeInput($content, 'string'), // XSS koruması
            'like_count' => 0,
            'is_liked' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'replies' => null
        ]);
    }
    
    // Like/Unlike action
    if (isset($_GET['post_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $currentUser = requireAuth(true);
        
        // CSRF koruması
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            if (!verifyCSRFToken($csrfToken)) {
                sendResponse(false, null, null, 'CSRF token geçersiz');
            }
        }
        
        $post_id = (int)$_GET['post_id'];
        
        // Post'u bul
        $post = findPostInAllCommunities($post_id);
        if (!$post) {
            sendResponse(false, null, null, 'Post bulunamadı');
        }
        
        $db = new SQLite3($post['db_path']);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Post likes tablosunu oluştur
        ensurePostLikesTable($db);
        
        // Mevcut beğeniyi kontrol et
        $check = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $check->bindValue(1, $post_id, SQLITE3_INTEGER);
        $check->bindValue(2, $currentUser['id'], SQLITE3_INTEGER);
        $existing = $check->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($existing) {
            // Unlike
            $delete = $db->prepare("DELETE FROM post_likes WHERE id = ?");
            $delete->bindValue(1, $existing['id'], SQLITE3_INTEGER);
            $delete->execute();
            $isLiked = false;
        } else {
            // Like
            $insert = $db->prepare("INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $insert->bindValue(1, $post_id, SQLITE3_INTEGER);
            $insert->bindValue(2, $currentUser['id'], SQLITE3_INTEGER);
            $insert->execute();
            $isLiked = true;
        }
        
        // Like count'u güncelle
        $likeCount = getPostLikeCount($db, $post_id);
        updatePostLikeCount($db, $post_id, $likeCount);
        
        // Post'u tekrar getir
        $updatedPost = getPostById($db, $post_id, $currentUser['id'], $post['community_id']);
        
        $db->close();
        sendResponse(true, $updatedPost);
    }
    
    // GET - Tüm postları getir (Feed)
    $currentUser = optionalAuth();
    $user_id = $currentUser ? $currentUser['id'] : null;
    
    $allPosts = [];
    
    // Tüm toplulukları tara
    if (is_dir($communities_dir)) {
        $communities = scandir($communities_dir);
        foreach ($communities as $community) {
            if ($community === '.' || $community === '..') continue;
            
            $db_path = $communities_dir . '/' . $community . '/unipanel.sqlite';
            if (!file_exists($db_path)) continue;
            
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode = WAL');
            
            // Post tablosunu oluştur (yoksa)
            ensurePostsTable($db);
            
            // Bu topluluğun postlarını getir
            $query = $db->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT 50");
            $result = $query->execute();
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $likeCount = getPostLikeCount($db, $row['id']);
                $commentCount = getPostCommentCount($db, $row['id']);
                $isLiked = $user_id ? isPostLiked($db, $row['id'], $user_id) : false;
                
                // Topluluk bilgilerini al
                $communityInfo = getCommunityInfo($community);
                
                $allPosts[] = [
                    'id' => (int)$row['id'],
                    'type' => $row['type'] ?? 'general',
                    'community_id' => $community,
                    'community_name' => $communityInfo['name'] ?? $community,
                    'community_logo' => $communityInfo['logo'] ?? null,
                    'author_id' => isset($row['author_id']) ? (int)$row['author_id'] : null,
        'author_name' => sanitizeInput($row['author_name'] ?? null, 'string'),
        'content' => sanitizeInput($row['content'] ?? '', 'string'), // XSS koruması
                    'images' => !empty($row['images']) ? json_decode($row['images'], true) : [],
                    'video' => $row['video'] ?? null,
                    'event_id' => isset($row['event_id']) ? (int)$row['event_id'] : null,
                    'campaign_id' => isset($row['campaign_id']) ? (int)$row['campaign_id'] : null,
                    'like_count' => $likeCount,
                    'comment_count' => $commentCount,
                    'is_liked' => $isLiked,
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $row['updated_at'] ?? null
                ];
            }
            
            $db->close();
        }
    }
    
    // Tarihe göre sırala (en yeni önce)
    usort($allPosts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    sendResponse(true, array_slice($allPosts, 0, 100)); // En fazla 100 post
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}

// Helper Functions
function findPostInAllCommunities($post_id) {
    $communities_dir = __DIR__ . '/../communities';
    if (!is_dir($communities_dir)) return null;
    
    $communities = scandir($communities_dir);
    foreach ($communities as $community) {
        if ($community === '.' || $community === '..') continue;
        
        $db_path = $communities_dir . '/' . $community . '/unipanel.sqlite';
        if (!file_exists($db_path)) continue;
        
        $db = new SQLite3($db_path);
        $db->exec('PRAGMA journal_mode = WAL');
        
        ensurePostsTable($db);
        
        $query = $db->prepare("SELECT * FROM posts WHERE id = ?");
        $query->bindValue(1, $post_id, SQLITE3_INTEGER);
        $result = $query->execute();
        $post = $result->fetchArray(SQLITE3_ASSOC);
        
        $db->close();
        
        if ($post) {
            return array_merge($post, ['db_path' => $db_path, 'community_id' => $community]);
        }
    }
    
    return null;
}

function ensurePostsTable($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT DEFAULT 'general',
        community_id TEXT,
        author_id INTEGER,
        author_name TEXT,
        content TEXT,
        images TEXT,
        video TEXT,
        event_id INTEGER,
        campaign_id INTEGER,
        like_count INTEGER DEFAULT 0,
        comment_count INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT
    )");
}

function ensurePostLikesTable($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER,
        user_id INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(post_id, user_id)
    )");
}

function ensurePostCommentsTable($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS post_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER,
        user_id INTEGER,
        content TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
}

function getPostLikeCount($db, $post_id) {
    ensurePostLikesTable($db);
    $query = $db->prepare("SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?");
    $query->bindValue(1, $post_id, SQLITE3_INTEGER);
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return (int)($row['count'] ?? 0);
}

function getPostCommentCount($db, $post_id) {
    ensurePostCommentsTable($db);
    $query = $db->prepare("SELECT COUNT(*) as count FROM post_comments WHERE post_id = ?");
    $query->bindValue(1, $post_id, SQLITE3_INTEGER);
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return (int)($row['count'] ?? 0);
}

function isPostLiked($db, $post_id, $user_id) {
    ensurePostLikesTable($db);
    $query = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $query->bindValue(1, $post_id, SQLITE3_INTEGER);
    $query->bindValue(2, $user_id, SQLITE3_INTEGER);
    $result = $query->execute();
    return $result->fetchArray(SQLITE3_ASSOC) !== false;
}

function isCommentLiked($db, $comment_id, $user_id) {
    // Şimdilik false döndür, sonra comment likes tablosu eklenebilir
    return false;
}

function getCommentLikeCount($db, $comment_id) {
    // Şimdilik 0 döndür
    return 0;
}

function updatePostLikeCount($db, $post_id, $count) {
    $update = $db->prepare("UPDATE posts SET like_count = ? WHERE id = ?");
    $update->bindValue(1, $count, SQLITE3_INTEGER);
    $update->bindValue(2, $post_id, SQLITE3_INTEGER);
    $update->execute();
}

function updatePostCommentCount($db, $post_id) {
    $count = getPostCommentCount($db, $post_id);
    $update = $db->prepare("UPDATE posts SET comment_count = ? WHERE id = ?");
    $update->bindValue(1, $count, SQLITE3_INTEGER);
    $update->bindValue(2, $post_id, SQLITE3_INTEGER);
    $update->execute();
}

function getPostById($db, $post_id, $user_id, $community_id) {
    $query = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $query->bindValue(1, $post_id, SQLITE3_INTEGER);
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$row) return null;
    
    $likeCount = getPostLikeCount($db, $post_id);
    $commentCount = getPostCommentCount($db, $post_id);
    $isLiked = isPostLiked($db, $post_id, $user_id);
    
    $communityInfo = getCommunityInfo($community_id);
    
    return [
        'id' => (int)$row['id'],
        'type' => $row['type'] ?? 'general',
        'community_id' => $community_id,
        'community_name' => $communityInfo['name'] ?? $community_id,
        'community_logo' => $communityInfo['logo'] ?? null,
        'author_id' => isset($row['author_id']) ? (int)$row['author_id'] : null,
        'author_name' => sanitizeInput($row['author_name'] ?? null, 'string'),
        'content' => sanitizeInput($row['content'] ?? '', 'string'), // XSS koruması
        'images' => !empty($row['images']) ? json_decode($row['images'], true) : [],
        'video' => $row['video'] ?? null,
        'event_id' => isset($row['event_id']) ? (int)$row['event_id'] : null,
        'campaign_id' => isset($row['campaign_id']) ? (int)$row['campaign_id'] : null,
        'like_count' => $likeCount,
        'comment_count' => $commentCount,
        'is_liked' => $isLiked,
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $row['updated_at'] ?? null
    ];
}

function getCommunityInfo($community_id) {
    $communities_dir = __DIR__ . '/../communities';
    $db_path = $communities_dir . '/' . $community_id . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        return ['name' => $community_id, 'logo' => null];
    }
    
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Communities tablosundan bilgi al (eğer varsa)
    $query = $db->query("SELECT name, logo_path FROM communities LIMIT 1");
    $row = $query->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    return [
        'name' => $row['name'] ?? $community_id,
        'logo' => $row['logo_path'] ?? null
    ];
}

function getUserInfo($user_id) {
    $system_db_path = __DIR__ . '/../public/unipanel.sqlite';
    
    if (!file_exists($system_db_path)) {
        return ['name' => 'Kullanıcı', 'avatar' => null];
    }
    
    $db = new SQLite3($system_db_path);
    $db->exec('PRAGMA journal_mode = WAL');
    
    $query = $db->prepare("SELECT first_name, last_name, profile_image_url FROM system_users WHERE id = ?");
    $query->bindValue(1, $user_id, SQLITE3_INTEGER);
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    if ($row) {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        return [
            'name' => !empty($name) ? $name : 'Kullanıcı',
            'avatar' => $row['profile_image_url'] ?? null
        ];
    }
    
    return ['name' => 'Kullanıcı', 'avatar' => null];
}

