<?php
require_once __DIR__ . '/security_helper.php';
/**
 * API Index - Endpoint listesi
 * GET /api/ veya GET /api/index.php
 */

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için hemen cevap ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$endpoints = [
    'authentication' => [
        'login' => '/api/login.php',
        'register' => '/api/register.php',
        'user' => '/api/user.php'
    ],
    'communities' => [
        'list' => '/api/communities.php',
        'detail' => '/api/communities.php?id={id}',
        'members' => '/api/members.php?community_id={id}',
        'membership_status' => '/api/membership_status.php?community_id={id}'
    ],
    'events' => [
        'list' => '/api/events.php',
        'detail' => '/api/events.php?id={id}',
        'register' => '/api/rsvp.php?event_id={id}'
    ],
    'campaigns' => [
        'list' => '/api/campaigns.php',
        'detail' => '/api/campaigns.php?id={id}'
    ],
    'posts' => [
        'feed' => '/api/posts.php',
        'comments' => '/api/posts.php?post_id={id}&action=comments'
    ],
    'notifications' => [
        'list' => '/api/notifications.php'
    ],
    'universities' => [
        'list' => '/api/universities.php'
    ]
];

echo json_encode([
    'success' => true,
    'data' => [
        'api_version' => '1.0',
        'base_url' => 'https://community.foursoftware.net/fourkampus/api',
        'endpoints' => $endpoints,
        'documentation' => 'https://community.foursoftware.net/fourkampus/api/README.md'
    ],
    'message' => 'Four Kampüs API v1.0 - Endpoint listesi',
    'error' => null
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

