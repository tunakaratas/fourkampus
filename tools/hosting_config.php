<?php
/**
 * Hosting Ortamı İçin PHP Ayarları
 * Bu dosyayı hosting'e yükleyip index.php dosyasının başına include edin
 */

// Hosting ortamı için PHP ayarları
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');
ini_set('max_file_uploads', 20);

// Hata raporlama
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Dosya yükleme fonksiyonu
function create_upload_directories() {
    $directories = [
        'assets',
        'assets/images',
        'assets/images/events',
        'assets/images/partner-logos',
        'assets/videos',
        'assets/videos/events'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                error_log("Created directory: $dir");
            } else {
                error_log("Failed to create directory: $dir");
            }
        }
        
        // İzinleri düzelt
        if (is_dir($dir)) {
            chmod($dir, 0755);
            if (!is_writable($dir)) {
                chmod($dir, 0777);
            }
        }
    }
}

// Klasörleri oluştur
create_upload_directories();

// Hosting ortamı kontrolü
function is_hosting_environment() {
    // Hosting ortamı belirleme
    $hosting_indicators = [
        'cpanel',
        'plesk',
        'hostinger',
        'godaddy',
        'bluehost',
        'siteground',
        'shared hosting',
        'virtual host'
    ];
    
    $server_info = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    $document_root = strtolower($_SERVER['DOCUMENT_ROOT'] ?? '');
    
    foreach ($hosting_indicators as $indicator) {
        if (strpos($server_info, $indicator) !== false || strpos($document_root, $indicator) !== false) {
            return true;
        }
    }
    
    return false;
}

// Hosting ortamı ise özel ayarlar
if (is_hosting_environment()) {
    error_log("Hosting environment detected");
    
    // Dosya yükleme limitlerini kontrol et
    $upload_max = ini_get('upload_max_filesize');
    $post_max = ini_get('post_max_size');
    
    error_log("Upload max filesize: $upload_max");
    error_log("Post max size: $post_max");
    
    // Eğer limitler düşükse uyarı ver
    if (return_bytes($upload_max) < (10 * 1024 * 1024)) {
        error_log("WARNING: upload_max_filesize is too low: $upload_max");
    }
    
    if (return_bytes($post_max) < (10 * 1024 * 1024)) {
        error_log("WARNING: post_max_size is too low: $post_max");
    }
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

// Hosting ortamı için özel dosya yükleme fonksiyonu
function handle_file_upload_hosting_safe($file, $subfolder, $allowed_extensions, $max_size) {
    try {
        // Hosting ortamı için güvenli dosya yükleme
        $upload_dir = __DIR__ . '/assets/' . $subfolder;
        
        // Klasör oluştur
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Klasör oluşturulamadı: ' . $upload_dir);
            }
        }
        
        // İzinleri düzelt
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0755);
            if (!is_writable($upload_dir)) {
                chmod($upload_dir, 0777);
                if (!is_writable($upload_dir)) {
                    throw new Exception('Klasör yazılabilir değil: ' . $upload_dir);
                }
            }
        }
        
        // Dosya bilgilerini al
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];
        
        // Uzantı kontrolü
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Geçersiz dosya uzantısı. İzin verilen: ' . implode(', ', $allowed_extensions));
        }
        
        // Boyut kontrolü
        if ($file_size > $max_size) {
            throw new Exception('Dosya boyutu çok büyük. Maksimum: ' . round($max_size / (1024 * 1024), 1) . 'MB');
        }
        
        // Benzersiz dosya adı oluştur
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . '/' . $filename;
        
        // Dosyayı taşı
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Dosya izinlerini düzelt
            chmod($file_path, 0644);
            return 'assets/' . $subfolder . $filename;
        } else {
            throw new Exception('Dosya yüklenirken hata oluştu');
        }
    } catch (Exception $e) {
        error_log("Hosting file upload error: " . $e->getMessage());
        $_SESSION['error'] = 'Dosya yükleme hatası: ' . $e->getMessage();
        return '';
    }
}

echo "<!-- Hosting PHP ayarları yüklendi -->\n";
?>
