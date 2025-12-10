<?php
declare(strict_types=1);

$communityBasePath = $communityBasePath ?? ($GLOBALS['communityBasePath'] ?? null);
$communityView = $communityView ?? 'index';

if (!isset($communityBasePath) || !is_dir($communityBasePath)) {
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
    if ($scriptFilename) {
        $scriptDir = realpath(dirname($scriptFilename));
        if ($scriptDir) {
            $normalized = str_replace('\\', '/', $scriptDir);
            $needle = '/communities/';
            $pos = strpos($normalized, $needle);
            if ($pos !== false) {
                $after = substr($normalized, $pos + strlen($needle));
                $parts = explode('/', $after);
                if (!empty($parts[0])) {
                    $candidate = dirname(__DIR__) . '/communities/' . $parts[0];
                    if (is_dir($candidate)) {
                        $communityBasePath = $candidate;
                    }
                }
            }
        }
    }
}

if (!isset($communityBasePath) || !is_dir($communityBasePath)) {
    throw new RuntimeException('community_entry.php: Topluluk klasörü belirlenemedi.');
}

$communityBasePath = realpath($communityBasePath);

$GLOBALS['communityBasePath'] = $communityBasePath;
$_SERVER['UNIPANEL_COMMUNITY_PATH'] = $communityBasePath;

if (!defined('COMMUNITY_BASE_PATH')) {
    define('COMMUNITY_BASE_PATH', $communityBasePath);
}

if (!defined('COMMUNITY_ID')) {
    define('COMMUNITY_ID', basename($communityBasePath));
}

define('UNIPANEL_COMMUNITY_VIEW', $communityView);

$viewMap = [
    'index' => dirname(__DIR__) . '/templates/template_index.php',
    'login' => dirname(__DIR__) . '/templates/template_login.php',
    'loading' => dirname(__DIR__) . '/templates/template_loading.php',
    'public_index' => dirname(__DIR__) . '/templates/template_public_index.php',
];

if (!array_key_exists($communityView, $viewMap)) {
    throw new InvalidArgumentException('Geçersiz communityView: ' . $communityView);
}

require $viewMap[$communityView];

