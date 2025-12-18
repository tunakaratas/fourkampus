<?php
/**
 * Topluluk Klasör ve Veritabanı İzinlerini Düzelt
 * Tüm topluluklar için klasör ve veritabanı dosyası izinlerini düzeltir
 */

$communities_dir = __DIR__ . '/../communities';

if (!is_dir($communities_dir)) {
    die("❌ Communities dizini bulunamadı: $communities_dir\n");
}

echo "🔧 Topluluk izinlerini düzeltiyorum...\n\n";

$fixed = 0;
$failed = 0;
$errors = [];

// Tüm topluluk klasörlerini bul
$dirs = glob($communities_dir . '/*', GLOB_ONLYDIR);
foreach ($dirs as $dir) {
    $community_id = basename($dir);
    if ($community_id === '.' || $community_id === '..') {
        continue;
    }
    
    $db_path = $dir . '/unipanel.sqlite';
    
    echo "📝 Düzeltiliyor: {$community_id}...\n";
    
    try {
        // Klasör izinlerini düzelt
        if (!is_writable($dir)) {
            @chmod($dir, 0755);
            if (!is_writable($dir)) {
                throw new Exception("Klasör izinleri düzeltilemedi: $dir");
            }
            echo "   ✅ Klasör izinleri düzeltildi\n";
        }
        
        // Veritabanı dosyası varsa izinlerini düzelt
        if (file_exists($db_path)) {
            if (!is_readable($db_path) || !is_writable($db_path)) {
                @chmod($db_path, 0666);
                if (!is_readable($db_path) || !is_writable($db_path)) {
                    throw new Exception("Veritabanı dosyası izinleri düzeltilemedi: $db_path");
                }
                echo "   ✅ Veritabanı izinleri düzeltildi\n";
            }
        }
        
        $fixed++;
    } catch (Exception $e) {
        echo "   ❌ HATA: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = [
            'community' => $community_id,
            'error' => $e->getMessage()
        ];
    }
}

echo "\n📊 Özet:\n";
echo "   Düzeltilen: {$fixed}\n";
echo "   Başarısız: {$failed}\n";

if (!empty($errors)) {
    echo "\n❌ Hatalar:\n";
    foreach ($errors as $error) {
        echo "   - {$error['community']}: {$error['error']}\n";
    }
}

exit($failed > 0 ? 1 : 0);
