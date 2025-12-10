<?php
/**
 * Shared helpers for secure storage directories.
 */
if (!function_exists('tpl_get_product_storage_base_dir')) {
    function tpl_get_product_storage_base_dir(): string
    {
        static $baseDir = null;
        if ($baseDir !== null) {
            return $baseDir;
        }

        $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : realpath(__DIR__ . '/..');
        $slug = defined('COMMUNITY_ID')
            ? COMMUNITY_ID
            : (defined('DB_PATH') ? md5(DB_PATH) : 'default');

        $path = rtrim($projectRoot . '/storage/private_uploads/' . $slug . '/products', '/') . '/';
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }

        $guards = [
            '.htaccess' => "Require all denied\n",
            'index.html' => "<!-- Access denied -->"
        ];

        foreach ($guards as $file => $content) {
            $fullPath = $path . $file;
            if (!file_exists($fullPath)) {
                @file_put_contents($fullPath, $content);
                @chmod($fullPath, 0600);
            }
        }

        return $baseDir = $path;
    }
}

if (!function_exists('tpl_product_storage_path')) {
    function tpl_product_storage_path(string $relativePath = ''): string
    {
        return tpl_get_product_storage_base_dir() . ltrim($relativePath, '/');
    }
}

