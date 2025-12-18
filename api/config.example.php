<?php
/**
 * API Configuration Example
 * 
 * Bu dosyayı kopyalayıp config.php olarak kaydedin ve değerleri doldurun.
 */

return [
    // API Settings
    'api' => [
        'version' => 'v1',
        'base_url' => 'https://foursoftware.com.tr/api',
        'timezone' => 'Europe/Istanbul',
    ],
    
    // Security
    'security' => [
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'window_seconds' => 60,
        ],
        'cors' => [
            'allowed_origins' => [
                'https://foursoftware.com.tr',
                'https://www.foursoftware.com.tr',
                'https://community.foursoftware.net',
                'https://app.foursoftware.net',
            ],
        ],
    ],
    
    // Database
    'database' => [
        'system_db_path' => __DIR__ . '/../../public/unipanel.sqlite',
        'connection_pool' => [
            'enabled' => true,
            'max_connections' => 10,
        ],
    ],
    
    // Cache
    'cache' => [
        'enabled' => true,
        'path' => __DIR__ . '/../../system/cache',
        'ttl' => 3600, // 1 hour
    ],
    
    // Email
    'email' => [
        'verification_code_ttl' => 3600, // 1 hour
        'from_name' => 'Four Kampüs',
        'from_email' => 'noreply@foursoftware.com.tr',
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'path' => __DIR__ . '/../../system/logs',
    ],
];
