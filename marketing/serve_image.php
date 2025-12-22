<?php
// marketing/serve_image.php

// Basic security checks
if (!isset($_GET['path'])) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$path = $_GET['path'];
// Remove any leading slashes or dots to sanitize
$path = ltrim($path, '/.');
$path = str_replace(['../', '..\\'], '', $path);

// Define allowed base directories (project root/communities and project root/public/communities)
// __DIR__ is .../marketing
$base_dirs = [
    realpath(__DIR__ . '/../communities'),
    realpath(__DIR__ . '/../public/communities')
];

$found_path = null;

foreach ($base_dirs as $base_dir) {
    if (!$base_dir) continue;
    
    // Construct full path
    // The path parameter is expected to be "community_folder/path/to/image.png"
    $try_path = $base_dir . '/' . $path;
    $real_try_path = realpath($try_path);
    
    // Check if file exists and is within the base directory
    if ($real_try_path && file_exists($real_try_path) && strpos($real_try_path, $base_dir) === 0) {
        $found_path = $real_try_path;
        break;
    }
}

if (!$found_path) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Get mime type and serve
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $found_path);
finfo_close($finfo);

// Basic image type validation
if (strpos($mime_type, 'image/') !== 0) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($found_path));
readfile($found_path);
