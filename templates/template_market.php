<?php
/**
 * Market Template - Ürün Yönetimi
 * Toplulukların ürünlerini ekleyip yönetebileceği sayfa
 */
require_once __DIR__ . '/partials/storage.php';
if (!function_exists('tpl_script_nonce_attr')) {
    require_once __DIR__ . '/partials/security_headers.php';
}
require_once __DIR__ . '/partials/inline_handler_bridge.php';
require_once __DIR__ . '/partials/schema_bootstrap.php';
tpl_inline_handler_transform_start();

// SQLite3 sabitlerini tanımla (eğer tanımlı değillerse)
if (!defined('SQLITE3_INTEGER')) define('SQLITE3_INTEGER', 1);
if (!defined('SQLITE3_TEXT')) define('SQLITE3_TEXT', 3);
if (!defined('SQLITE3_REAL')) define('SQLITE3_REAL', 2);

// Veritabanı bağlantısı
$db = get_db();

// Sabit komisyon oranı (değiştirilemez) - %5
define('FIXED_COMMISSION_RATE', 5.0);

if (!function_exists('tpl_script_nonce_attr')) {
    function tpl_script_nonce_attr(): string
    {
        if (function_exists('tpl_get_csp_nonce')) {
            return ' nonce="' . htmlspecialchars(tpl_get_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
        }
        return '';
    }
}

if (!defined('PRODUCT_STORAGE_PREFIX')) {
    define('PRODUCT_STORAGE_PREFIX', 'secure://products/');
}

if (!defined('PRODUCT_MAX_UPLOAD_BYTES')) {
    define('PRODUCT_MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2MB
}

if (!function_exists('product_storage_directory')) {
    function product_storage_directory(): string
    {
        $dir = tpl_get_product_storage_base_dir();
        protect_private_directory($dir);
        return $dir;
    }
}

if (!function_exists('protect_private_directory')) {
    /**
     * Upload klasörünü doğrudan HTTP erişimine kapatır.
     */
    function protect_private_directory(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
        @chmod($htaccess, 0640);

        $index = $dir . 'index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, "<!-- Access denied -->");
        }
        @chmod($index, 0640);
    }
}

if (!function_exists('format_product_image_url')) {
    /**
     * Görsel path'ini URL'ye çevirir
     * JSON array veya tek string destekler
     * @param string|array|null $path Görsel path'i veya path array'i
     * @return string|null İlk görselin URL'si
     */
    function format_product_image_url($path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // JSON array olarak saklanmış olabilir
        if (is_string($path) && (strpos($path, '[') === 0 || strpos($path, '"') === 0)) {
            $decoded = json_decode($path, true);
            if (is_array($decoded) && !empty($decoded)) {
                $path = $decoded[0]; // İlk görseli al
            }
        }
        
        // Array ise ilk elemanı al
        if (is_array($path)) {
            if (empty($path)) {
                return null;
            }
            $path = $path[0];
        }

        if (empty($path)) {
            return null;
        }

        // secure://products/ prefix'i kontrolü
        if (strpos($path, PRODUCT_STORAGE_PREFIX) === 0) {
            $file = substr($path, strlen(PRODUCT_STORAGE_PREFIX));
            // Çift slash'ları temizle
            $file = str_replace('//', '/', $file);
            return 'index.php?action=product_media&file=' . rawurlencode($file);
        }

        return $path;
    }
}

if (!function_exists('delete_product_media')) {
    /**
     * Tek bir görsel dosyasını siler
     * @param string|array|null $path Görsel path'i veya path array'i
     */
    function delete_product_media($path): void
    {
        if (!$path) {
            return;
        }

        // Eğer array ise, her birini sil
        if (is_array($path)) {
            foreach ($path as $single_path) {
                delete_product_media($single_path);
            }
            return;
        }

        // Tek string path
        if (strpos($path, PRODUCT_STORAGE_PREFIX) === 0) {
            $file = substr($path, strlen(PRODUCT_STORAGE_PREFIX));
            if (function_exists('tpl_product_storage_path')) {
                $target = tpl_product_storage_path($file);
            } else {
                $target = product_storage_directory() . $file;
            }
        } else {
            $target = community_path($path);
        }

        if (is_file($target)) {
            @unlink($target);
        }
    }
}

if (!function_exists('process_product_image_upload')) {
    function process_product_image_upload(array $file): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Görsel yüklenemedi. Lütfen tekrar deneyin.');
        }

        $size = $file['size'] ?? 0;
        if ($size > PRODUCT_MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Görsel boyutu 2MB sınırını aşıyor.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (empty($tmpName) || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Geçersiz yükleme isteği.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Yalnızca JPG, PNG, GIF veya WebP formatı desteklenir.');
        }

        $dir = product_storage_directory();
        $filename = 'product_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $destination = $dir . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Görsel diske kaydedilemedi.');
        }

        @chmod($destination, 0640);
        return PRODUCT_STORAGE_PREFIX . $filename;
    }
}

if (!function_exists('process_product_images_upload')) {
    /**
     * Çoklu görsel yükleme fonksiyonu
     * @param array $files $_FILES['images'] array'i
     * @return array Yüklenen görsellerin path'leri
     */
    function process_product_images_upload(array $files): array
    {
        $uploaded_paths = [];
        
        // Debug: Gelen dosya yapısını logla
        error_log("process_product_images_upload called. Files structure: " . print_r($files, true));
        
        // Eğer tek dosya yüklenmişse (eski format veya tek dosya seçilmişse)
        if (isset($files['error']) && !is_array($files['error'])) {
            // Tek dosya yükleme
            if ($files['error'] !== UPLOAD_ERR_NO_FILE) {
                $single_path = process_product_image_upload($files);
                if ($single_path) {
                    $uploaded_paths[] = $single_path;
                }
            }
            return $uploaded_paths;
        }
        
        // Çoklu dosya yükleme kontrolü
        if (!isset($files['error']) || !is_array($files['error'])) {
            error_log("process_product_images_upload: No valid files array structure found.");
            return [];
        }
        
        $file_count = count($files['error']);
        error_log("process_product_images_upload: Processing $file_count files.");
        
        // Maksimum 10 görsel
        if ($file_count > 10) {
            throw new RuntimeException('Maksimum 10 görsel yükleyebilirsiniz.');
        }
        
        // Her dosyayı işle
        for ($i = 0; $i < $file_count; $i++) {
            // Dosya bilgilerini al
            $file = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0
            ];
            
            // Hata kontrolü
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                error_log("process_product_images_upload: File $i skipped (UPLOAD_ERR_NO_FILE)");
                continue;
            }
            
            // Upload hata kodlarını kontrol et
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'Dosya php.ini\'deki maksimum boyutu aşıyor.',
                    UPLOAD_ERR_FORM_SIZE => 'Dosya form\'daki maksimum boyutu aşıyor.',
                    UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                    UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
                    UPLOAD_ERR_EXTENSION => 'Bir PHP uzantısı dosya yüklemeyi durdurdu.',
                ];
                $error_msg = $error_messages[$file['error']] ?? 'Bilinmeyen hata kodu: ' . $file['error'];
                error_log("process_product_images_upload: File $i upload error: $error_msg");
                continue;
            }
            
            // Dosyayı yükle
            try {
                $path = process_product_image_upload($file);
                if ($path) {
                    $uploaded_paths[] = $path;
                    error_log("process_product_images_upload: File $i uploaded successfully: $path");
                } else {
                    error_log("process_product_images_upload: File $i returned null path");
                }
            } catch (RuntimeException $e) {
                // Bir görsel yüklenemezse diğerlerini yüklemeye devam et
                error_log("process_product_images_upload: File $i upload failed: " . $e->getMessage());
            } catch (Exception $e) {
                error_log("process_product_images_upload: File $i exception: " . $e->getMessage());
            }
        }
        
        error_log("process_product_images_upload: Total uploaded: " . count($uploaded_paths) . " files");
        return $uploaded_paths;
    }
}

// Komisyon hesaplama fonksiyonları
// NOT: İyzico komisyonu artık hesaplanmıyor ve gösterilmiyor
function calculateIyzicoCommission($price) {
    // İyzico komisyonu gizlendi - artık hesaplanmıyor
    return 0;
}

function calculatePlatformCommission($price, $commission_rate = 5.0) {
    // Platform komisyonu: Fiyatın içinden alınır (üstüne eklenmez)
    // Kullanıcı 1500 girerse, komisyon = 1500 * 0.05 = 75
    $platform_commission = $price * $commission_rate / 100;
    return round($platform_commission, 2);
}

function calculateTotalPrice($price, $commission_rate = 5.0) {
    // Toplam fiyat = girilen fiyat (komisyon içinde)
    // Kullanıcı 1500 girerse, toplam = 1500
    return round($price, 2);
}

// Ürün işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF token kontrolü
    if (function_exists('verify_csrf_token') && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Güvenlik hatası: Geçersiz token.';
        // Output buffer kontrolü
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Location: index.php?view=market");
        }
        exit;
    }
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_product':
            // Paket limit kontrolü - Market ürün
            if (!function_exists('require_subscription_feature')) {
                require_once __DIR__ . '/../lib/general/subscription_guard.php';
            }
            
            // Mevcut ürün sayısını hesapla
            $currentCount = null;
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE club_id = ?");
                $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $currentCount = (int)($row['count'] ?? 0);
            } catch (Exception $e) {
                $currentCount = 0;
            }
            
            if (!require_subscription_feature('max_products', null, $currentCount + 1)) {
                // Sayfa gösterildi ve çıkış yapıldı
                return;
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
            $category = trim($_POST['category'] ?? 'Genel');
            
            // Yasaklı ürünler kontrolü
            $forbidden_keywords = ['alkol', 'içki', 'bira', 'şarap', 'rakı', 'viski', 'sigara', 'tütün', 'tamak', 'ilaç', 'hap', 'tablet', 'silah', 'bıçak', 'silah', 'uyuşturucu', 'esrar', 'kokain', 'eroin', 'amfetamin'];
            $name_lower = mb_strtolower($name, 'UTF-8');
            $description_lower = mb_strtolower($description ?? '', 'UTF-8');
            $category_lower = mb_strtolower($category, 'UTF-8');
            
            foreach ($forbidden_keywords as $keyword) {
                if (strpos($name_lower, $keyword) !== false || strpos($description_lower, $keyword) !== false || strpos($category_lower, $keyword) !== false) {
                    $_SESSION['error'] = 'Yasaklı ürün kategorisinde ürün satılamaz. Lütfen farklı bir ürün ekleyin.';
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header("Location: index.php?view=market");
                    }
                    exit;
                }
            }
            
            // Sabit komisyon oranı (değiştirilemez) - %5
            $commission_rate = defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0;
            
            // Komisyonları hesapla
            // İyzico komisyonu artık 0 (gizlendi)
            $iyzico_commission = 0;
            $platform_commission = calculatePlatformCommission($price, $commission_rate);
            // Toplam fiyat = girilen fiyat (komisyon içinde)
            $total_price = $price;
            
            if (empty($name)) {
                $_SESSION['error'] = 'Ürün adı gereklidir.';
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            if ($price <= 0) {
                $_SESSION['error'] = 'Geçerli bir fiyat giriniz.';
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            // Çoklu görsel yükleme desteği
            $image_paths = [];
            try {
                // Debug: Dosya yükleme durumunu kontrol et
                $has_images = isset($_FILES['images']) && !empty($_FILES['images']);
                $has_image = isset($_FILES['image']) && !empty($_FILES['image']);
                
                // Önce yeni çoklu görsel formatını dene
                if ($has_images) {
                    // $_FILES['images'] yapısını kontrol et
                    if (is_array($_FILES['images']['error'])) {
                        // Çoklu dosya yükleme
                        $image_paths = process_product_images_upload($_FILES['images']);
                    } elseif ($_FILES['images']['error'] !== UPLOAD_ERR_NO_FILE) {
                        // Tek dosya ama 'images' name'i ile gönderilmiş
                        $single_path = process_product_image_upload($_FILES['images']);
                        if ($single_path) {
                            $image_paths[] = $single_path;
                        }
                    }
                } 
                // Eski tek görsel formatını destekle (geriye dönük uyumluluk)
                elseif ($has_image && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $single_path = process_product_image_upload($_FILES['image']);
                    if ($single_path) {
                        $image_paths[] = $single_path;
                    }
                }
                
                // Debug: Yüklenen görsel sayısını logla
                if (empty($image_paths)) {
                    error_log("Product add: Hiç görsel yüklenmedi. _FILES: " . print_r($_FILES, true));
                } else {
                    error_log("Product add: " . count($image_paths) . " görsel yüklendi.");
                }
            } catch (RuntimeException $e) {
                error_log("Product image upload error: " . $e->getMessage());
                $_SESSION['error'] = $e->getMessage();
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            } catch (Exception $e) {
                error_log("Product image upload exception: " . $e->getMessage());
                $_SESSION['error'] = 'Görsel yüklenirken bir hata oluştu: ' . $e->getMessage();
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            // image_path'i JSON array olarak sakla (geriye dönük uyumluluk için boş string de olabilir)
            $image_path_json = !empty($image_paths) ? json_encode($image_paths) : null;
            
            try {
                // Veritabanı bağlantısını kontrol et
                if (!isset($db) || !$db) {
                    throw new Exception("Veritabanı bağlantısı bulunamadı.");
                }
                
                $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
                
                // Tabloyu kontrol et ve oluştur (zaten CREATE TABLE IF NOT EXISTS ile oluşturuluyor)
                // ensure_products_table($db); // Fonksiyon tanımlı değil, zaten tablo oluşturuluyor
                
                $stmt = $db->prepare("INSERT INTO products (club_id, name, description, price, stock, category, image_path, status, commission_rate, iyzico_commission, platform_commission, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, datetime('now'))");
                
                if (!$stmt) {
                    throw new Exception("SQL hazırlama hatası: " . $db->lastErrorMsg());
                }
                
                $stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
                $stmt->bindValue(2, $name, SQLITE3_TEXT);
                $stmt->bindValue(3, $description, SQLITE3_TEXT);
                $stmt->bindValue(4, $price, SQLITE3_REAL);
                $stmt->bindValue(5, $stock, SQLITE3_INTEGER);
                $stmt->bindValue(6, $category, SQLITE3_TEXT);
                $stmt->bindValue(7, $image_path_json, SQLITE3_TEXT);
                $stmt->bindValue(8, $commission_rate, SQLITE3_REAL);
                $stmt->bindValue(9, $iyzico_commission, SQLITE3_REAL);
                $stmt->bindValue(10, $platform_commission, SQLITE3_REAL);
                $stmt->bindValue(11, $total_price, SQLITE3_REAL);
                
                $result = $stmt->execute();
                if (!$result) {
                    throw new Exception("SQL çalıştırma hatası: " . $db->lastErrorMsg());
                }
                
                $_SESSION['message'] = 'Ürün başarıyla eklendi.';
                
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            } catch (Exception $e) {
                tpl_error_log("Ürün ekleme hatası: " . $e->getMessage());
                tpl_error_log("Stack trace: " . $e->getTraceAsString());
                $_SESSION['error'] = 'Ürün eklenirken bir hata oluştu: ' . htmlspecialchars($e->getMessage());
                
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
        case 'update_product':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
            $category = trim($_POST['category'] ?? 'Genel');
            $status = trim($_POST['status'] ?? 'active');
            
            // Yasaklı ürünler kontrolü
            $forbidden_keywords = ['alkol', 'içki', 'bira', 'şarap', 'rakı', 'viski', 'sigara', 'tütün', 'tamak', 'ilaç', 'hap', 'tablet', 'silah', 'bıçak', 'silah', 'uyuşturucu', 'esrar', 'kokain', 'eroin', 'amfetamin'];
            $name_lower = mb_strtolower($name, 'UTF-8');
            $description_lower = mb_strtolower($description ?? '', 'UTF-8');
            $category_lower = mb_strtolower($category, 'UTF-8');
            
            foreach ($forbidden_keywords as $keyword) {
                if (strpos($name_lower, $keyword) !== false || strpos($description_lower, $keyword) !== false || strpos($category_lower, $keyword) !== false) {
                    $_SESSION['error'] = 'Yasaklı ürün kategorisinde ürün satılamaz. Lütfen farklı bir ürün ekleyin.';
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header("Location: index.php?view=market");
                    }
                    exit;
                }
            }
            
            // Sabit komisyon oranı (değiştirilemez) - %5
            $commission_rate = defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0;
            
            // Komisyonları hesapla
            // İyzico komisyonu artık 0 (gizlendi)
            $iyzico_commission = 0;
            $platform_commission = calculatePlatformCommission($price, $commission_rate);
            // Toplam fiyat = girilen fiyat (komisyon içinde)
            $total_price = $price;
            
            if (empty($name)) {
                $_SESSION['error'] = 'Ürün adı gereklidir.';
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            if ($price <= 0) {
                $_SESSION['error'] = 'Geçerli bir fiyat giriniz.';
                // Output buffer kontrolü
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            // Çoklu görsel yükleme desteği
            $image_paths = null;
            $merge_with_existing = false; // Yeni görselleri eski görsellerle birleştir mi?
            
            try {
                // Debug: Dosya yükleme durumunu kontrol et
                $has_images = isset($_FILES['images']) && !empty($_FILES['images']);
                $has_image = isset($_FILES['image']) && !empty($_FILES['image']);
                
                // Önce yeni çoklu görsel formatını dene
                if ($has_images) {
                    // $_FILES['images'] yapısını kontrol et
                    if (is_array($_FILES['images']['error'])) {
                        // Çoklu dosya yükleme
                        $uploaded_paths = process_product_images_upload($_FILES['images']);
                        if (!empty($uploaded_paths)) {
                            // Mevcut görselleri al ve yeni görsellerle birleştir
                            $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
                            $old_stmt = $db->prepare("SELECT image_path FROM products WHERE id = ? AND club_id = ?");
                            $old_stmt->bindValue(1, $id, SQLITE3_INTEGER);
                            $old_stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
                            $old_result = $old_stmt->execute();
                            if ($old_result) {
                                $old_row = $old_result->fetchArray(SQLITE3_ASSOC);
                                if ($old_row && !empty($old_row['image_path'])) {
                                    // JSON array veya tek string olabilir
                                    $old_paths = json_decode($old_row['image_path'], true);
                                    if (!is_array($old_paths)) {
                                        // Eski format (tek string)
                                        $old_paths = [$old_row['image_path']];
                                    }
                                    // Yeni görselleri eski görsellerle birleştir
                                    $image_paths = array_merge($old_paths, $uploaded_paths);
                                    // Maksimum 10 görsel
                                    if (count($image_paths) > 10) {
                                        $image_paths = array_slice($image_paths, 0, 10);
                                    }
                                    $merge_with_existing = true;
                                } else {
                                    $image_paths = $uploaded_paths;
                                }
                            } else {
                                $image_paths = $uploaded_paths;
                            }
                        }
                    } elseif ($_FILES['images']['error'] !== UPLOAD_ERR_NO_FILE) {
                        // Tek dosya ama 'images' name'i ile gönderilmiş
                        $single_path = process_product_image_upload($_FILES['images']);
                        if ($single_path) {
                            // Mevcut görselleri al ve yeni görseli ekle
                $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
                $old_stmt = $db->prepare("SELECT image_path FROM products WHERE id = ? AND club_id = ?");
                $old_stmt->bindValue(1, $id, SQLITE3_INTEGER);
                $old_stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
                $old_result = $old_stmt->execute();
                            if ($old_result) {
                $old_row = $old_result->fetchArray(SQLITE3_ASSOC);
                if ($old_row && !empty($old_row['image_path'])) {
                                    $old_paths = json_decode($old_row['image_path'], true);
                                    if (!is_array($old_paths)) {
                                        $old_paths = [$old_row['image_path']];
                                    }
                                    $old_paths[] = $single_path;
                                    if (count($old_paths) > 10) {
                                        $old_paths = array_slice($old_paths, 0, 10);
                                    }
                                    $image_paths = $old_paths;
                                    $merge_with_existing = true;
                                } else {
                                    $image_paths = [$single_path];
                                }
                            } else {
                                $image_paths = [$single_path];
                            }
                        }
                    }
                } 
                // Eski tek görsel formatını destekle (geriye dönük uyumluluk)
                elseif ($has_image && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $single_path = process_product_image_upload($_FILES['image']);
                    if ($single_path) {
                        // Mevcut görselleri al ve yeni görseli ekle
                        $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
                        $old_stmt = $db->prepare("SELECT image_path FROM products WHERE id = ? AND club_id = ?");
                        $old_stmt->bindValue(1, $id, SQLITE3_INTEGER);
                        $old_stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
                        $old_result = $old_stmt->execute();
                        if ($old_result) {
                            $old_row = $old_result->fetchArray(SQLITE3_ASSOC);
                            if ($old_row && !empty($old_row['image_path'])) {
                                $old_paths = json_decode($old_row['image_path'], true);
                                if (!is_array($old_paths)) {
                                    $old_paths = [$old_row['image_path']];
                                }
                                $old_paths[] = $single_path;
                                if (count($old_paths) > 10) {
                                    $old_paths = array_slice($old_paths, 0, 10);
                                }
                                $image_paths = $old_paths;
                                $merge_with_existing = true;
                            } else {
                                $image_paths = [$single_path];
                            }
                        } else {
                            $image_paths = [$single_path];
                        }
                    }
                }
                
                // Debug: Yüklenen görsel sayısını logla
                if ($image_paths === null || empty($image_paths)) {
                    error_log("Product update: Hiç görsel yüklenmedi. _FILES: " . print_r($_FILES, true));
                } else {
                    error_log("Product update: " . count($image_paths) . " görsel yüklendi. Merge: " . ($merge_with_existing ? 'yes' : 'no'));
                }
            } catch (RuntimeException $e) {
                error_log("Product image upload error (update): " . $e->getMessage());
                $_SESSION['error'] = $e->getMessage();
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            } catch (Exception $e) {
                error_log("Product image upload exception (update): " . $e->getMessage());
                $_SESSION['error'] = 'Görsel yüklenirken bir hata oluştu: ' . $e->getMessage();
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Location: index.php?view=market");
                }
                exit;
            }
            
            // image_path'i JSON array olarak sakla
            $image_path_json = $image_paths !== null ? json_encode($image_paths) : null;
            
            if ($image_path_json !== null) {
                $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
                $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, image_path = ?, commission_rate = ?, iyzico_commission = ?, platform_commission = ?, total_price = ?, updated_at = datetime('now') WHERE id = ? AND club_id = ?");
                $stmt->bindValue(1, $name, SQLITE3_TEXT);
                $stmt->bindValue(2, $description, SQLITE3_TEXT);
                $stmt->bindValue(3, $price, SQLITE3_REAL);
                $stmt->bindValue(4, $stock, SQLITE3_INTEGER);
                $stmt->bindValue(5, $category, SQLITE3_TEXT);
                $stmt->bindValue(6, $status, SQLITE3_TEXT);
                $stmt->bindValue(7, $image_path_json, SQLITE3_TEXT);
                $stmt->bindValue(8, $commission_rate, SQLITE3_REAL);
                $stmt->bindValue(9, $iyzico_commission, SQLITE3_REAL);
                $stmt->bindValue(10, $platform_commission, SQLITE3_REAL);
                $stmt->bindValue(11, $total_price, SQLITE3_REAL);
                $stmt->bindValue(12, $id, SQLITE3_INTEGER);
                $stmt->bindValue(13, $club_id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, commission_rate = ?, iyzico_commission = ?, platform_commission = ?, total_price = ?, updated_at = datetime('now') WHERE id = ? AND club_id = ?");
                $stmt->bindValue(1, $name, SQLITE3_TEXT);
                $stmt->bindValue(2, $description, SQLITE3_TEXT);
                $stmt->bindValue(3, $price, SQLITE3_REAL);
                $stmt->bindValue(4, $stock, SQLITE3_INTEGER);
                $stmt->bindValue(5, $category, SQLITE3_TEXT);
                $stmt->bindValue(6, $status, SQLITE3_TEXT);
                $stmt->bindValue(7, $commission_rate, SQLITE3_REAL);
                $stmt->bindValue(8, $iyzico_commission, SQLITE3_REAL);
                $stmt->bindValue(9, $platform_commission, SQLITE3_REAL);
                $stmt->bindValue(10, $total_price, SQLITE3_REAL);
                $stmt->bindValue(11, $id, SQLITE3_INTEGER);
                $stmt->bindValue(12, $club_id, SQLITE3_INTEGER);
            }
            $stmt->execute();
            
            $_SESSION['message'] = 'Ürün başarıyla güncellendi.';
            // Output buffer kontrolü
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Location: index.php?view=market");
            }
            exit;
            
        case 'ai_generate_product_description':
            header('Content-Type: application/json');
            
            // CSRF kontrolü
            if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'message' => 'CSRF token doğrulaması başarısız']);
                exit;
            }
            
            // AI Helper'ı yükle
            if (!function_exists('ai_generate_product_description')) {
                $ai_helper_path = defined('PROJECT_ROOT') 
                    ? PROJECT_ROOT . '/lib/ai/AIHelper.php'
                    : dirname(__DIR__) . '/lib/ai/AIHelper.php';
                
                if (file_exists($ai_helper_path)) {
                    require_once $ai_helper_path;
                } else {
                    // Fallback: relative path
                    require_once __DIR__ . '/../../lib/ai/AIHelper.php';
                }
            }
            
            if (!function_exists('ai_generate_product_description')) {
                echo json_encode(['success' => false, 'message' => 'AI Helper bulunamadı']);
                exit;
            }
            
            $product_data = [
                'name' => $_POST['name'] ?? '',
                'price' => $_POST['price'] ?? '',
                'category' => $_POST['category'] ?? 'Genel',
            ];
            
            $description = ai_generate_product_description($product_data);
            
            if ($description === false) {
                echo json_encode(['success' => false, 'message' => 'Ürün açıklaması oluşturulamadı. Lütfen tekrar deneyin.']);
            } else {
                echo json_encode(['success' => true, 'description' => $description]);
            }
            exit;
            
        case 'delete_product':
            $id = (int)($_POST['id'] ?? 0);
            
            $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
            
            // Topluluk klasörünü DB_PATH'den al
            if (defined('DB_PATH') && file_exists(DB_PATH)) {
                $community_base = dirname(DB_PATH);
            } elseif (defined('COMMUNITY_BASE_PATH')) {
                $community_base = COMMUNITY_BASE_PATH;
            } else {
                // Fallback: script'ten topluluk klasörünü bul
                $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
                $normalized = str_replace('\\', '/', $script_dir);
                $needle = '/communities/';
                $pos = strpos($normalized, $needle);
                if ($pos !== false) {
                    $after = substr($normalized, $pos + strlen($needle));
                    $parts = explode('/', $after);
                    $community_base = dirname(__DIR__) . '/communities/' . $parts[0];
                } else {
                    $community_base = dirname(__DIR__) . '/communities/default';
                }
            }
            
            // Görselleri sil - Çoklu görsel desteği
            $img_stmt = $db->prepare("SELECT image_path FROM products WHERE id = ? AND club_id = ?");
            $img_stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $img_stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
            $img_result = $img_stmt->execute();
            $img_row = $img_result->fetchArray(SQLITE3_ASSOC);
            if ($img_row && !empty($img_row['image_path'])) {
                // JSON array veya tek string olabilir
                $image_paths = json_decode($img_row['image_path'], true);
                if (!is_array($image_paths)) {
                    // Eski format (tek string)
                    $image_paths = [$img_row['image_path']];
                }
                foreach ($image_paths as $image_path) {
                    if (!empty($image_path)) {
                        delete_product_media($image_path);
                    }
                }
            }
            
            $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND club_id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $club_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $_SESSION['message'] = 'Ürün başarıyla silindi.';
            // Output buffer kontrolü
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Location: index.php?view=market");
            }
            exit;
    }
}

// Products tablosunu oluştur (yoksa)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY,
        club_id INTEGER,
        name TEXT NOT NULL,
        description TEXT,
        price REAL DEFAULT 0,
        stock INTEGER DEFAULT 0,
        category TEXT DEFAULT 'Genel',
        image_path TEXT,
        status TEXT DEFAULT 'active',
        commission_rate REAL DEFAULT 8.0,
        iyzico_commission REAL DEFAULT 0,
        platform_commission REAL DEFAULT 0,
        total_price REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Migration: Eksik kolonları ekle
    // Önce mevcut sütunları kontrol et
    $existingColumns = [];
    $result = $db->query("PRAGMA table_info(products)");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existingColumns[] = $row['name'];
    }
    
    $columns_to_add = [
        'commission_rate' => 'REAL DEFAULT 8.0',
        'iyzico_commission' => 'REAL DEFAULT 0',
        'platform_commission' => 'REAL DEFAULT 0',
        'total_price' => 'REAL DEFAULT 0'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        // Sütun zaten varsa ekleme
        if (!in_array($column, $existingColumns)) {
        try {
            $db->exec("ALTER TABLE products ADD COLUMN {$column} {$definition}");
        } catch (Exception $e) {
                // Hata durumunda log'a yaz ama devam et
                tpl_error_log("Kolon eklenirken hata ({$column}): " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    tpl_error_log("Products tablosu oluşturulurken hata: " . $e->getMessage());
}

// Topluluk klasörünü belirle (görsel yolları için)
if (defined('DB_PATH') && file_exists(DB_PATH)) {
    $community_base = dirname(DB_PATH);
    $community_folder = basename($community_base);
} elseif (defined('COMMUNITY_BASE_PATH')) {
    $community_base = COMMUNITY_BASE_PATH;
    $community_folder = basename($community_base);
} else {
    // Fallback: script'ten topluluk klasörünü bul
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $normalized = str_replace('\\', '/', $script_dir);
    $needle = '/communities/';
    $pos = strpos($normalized, $needle);
    if ($pos !== false) {
        $after = substr($normalized, $pos + strlen($needle));
        $parts = explode('/', $after);
        $community_folder = $parts[0];
        $community_base = dirname(__DIR__) . '/communities/' . $community_folder;
    } else {
        $community_folder = 'default';
        $community_base = dirname(__DIR__) . '/communities/default';
    }
}

// Ürünleri getir
$club_id = defined('CLUB_ID') ? CLUB_ID : 1;
// PERFORMANS: Lazy loading için ilk yüklemede sadece ilk 30 ürün
$products_stmt = $db->prepare("SELECT * FROM products WHERE club_id = ? ORDER BY created_at DESC LIMIT 30");
$products_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
$products_result = $products_stmt->execute();
$products = [];
while ($row = $products_result->fetchArray(SQLITE3_ASSOC)) {
    if (!empty($row['image_path'])) {
        // JSON array'i parse et ve image_paths olarak ekle
        $image_paths = json_decode($row['image_path'], true);
        if (!is_array($image_paths)) {
            // Eski format (tek string)
            $image_paths = [$row['image_path']];
        }
        $row['image_paths'] = $image_paths;
        
        // İlk görselin URL'sini oluştur
        $row['image_url'] = format_product_image_url($row['image_path']);
    } else {
        $row['image_paths'] = [];
        $row['image_url'] = null;
    }
    $products[] = $row;
}

// Toplam ürün sayısını kontrol et
$total_products_stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE club_id = ?");
$total_products_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
$total_result = $total_products_stmt->execute();
$total_row = $total_result->fetchArray(SQLITE3_ASSOC);
$total_products = (int)($total_row['total'] ?? 0);
$has_more_products = $total_products > 30;

// Kategorileri getir
$categories_stmt = $db->query("SELECT DISTINCT category FROM products WHERE club_id = " . $club_id . " AND category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
while ($row = $categories_stmt->fetchArray(SQLITE3_ASSOC)) {
    $categories[] = $row['category'];
}
if (empty($categories)) {
    $categories = ['Genel', 'Giyim', 'Aksesuar', 'Kitap', 'Diğer'];
}

// Abonelik bilgilerini ve ürün limitini al
if (!function_exists('get_current_subscription_info')) {
    require_once __DIR__ . '/../lib/general/subscription_helper.php';
}

$subscriptionInfo = function_exists('get_current_subscription_info') ? get_current_subscription_info() : ['tier' => 'standard', 'tier_label' => 'Standart', 'limits' => ['max_products' => 2]];
$subscriptionTier = $subscriptionInfo['tier'] ?? 'standard';
$subscriptionTierLabel = $subscriptionInfo['tier_label'] ?? 'Standart';
$defaultProductLimit = $subscriptionInfo['limits']['max_products'] ?? 2;

$marketLimitInfo = function_exists('check_product_limit')
    ? check_product_limit(count($products))
    : [
        'allowed' => true,
        'limit' => $defaultProductLimit,
        'current' => count($products),
        'remaining' => $defaultProductLimit === -1 ? -1 : max(0, $defaultProductLimit - count($products))
    ];

$maxProductLimit = $marketLimitInfo['limit'] ?? $defaultProductLimit;
$currentProductCount = $marketLimitInfo['current'] ?? count($products);
$marketLimitRemaining = $marketLimitInfo['remaining'];
if ($marketLimitRemaining === null) {
    $marketLimitRemaining = $maxProductLimit === -1 ? -1 : max(0, $maxProductLimit - $currentProductCount);
}
$isStandardPlan = ($subscriptionTier === 'standard');
$standardProductLimitReached = $isStandardPlan && $maxProductLimit !== -1 && $currentProductCount >= $maxProductLimit;
$maxProductLimitLabel = $maxProductLimit === -1 ? 'sınırsız' : $maxProductLimit;
?>

<?php if ($isStandardPlan): ?>
<div class="rounded-2xl border border-amber-200 dark:border-amber-500/40 bg-gradient-to-br from-white via-amber-50 to-orange-50 dark:from-slate-900 dark:via-amber-900/20 dark:to-slate-900 px-5 py-5 mb-6 shadow-lg shadow-amber-100/50 dark:shadow-black/30">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg shadow-orange-500/30">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5l7 3V12c0 4.5-3 8.5-7 9-4-.5-7-4.5-7-9V7.5l7-3z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.5 12.5l2 2 3-3.5"></path>
                </svg>
            </div>
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-amber-300/70 dark:border-amber-400/50 bg-amber-50/80 dark:bg-amber-500/10 text-xs font-semibold text-amber-700 dark:text-amber-200">
                    Standart Paket · Maks. <?= htmlspecialchars((string)$maxProductLimitLabel) ?> ürün
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Market ürün limiti</h3>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    Standart pakette en fazla <?= htmlspecialchars((string)$maxProductLimitLabel) ?> ürünü listelenir. Şu anda <strong><?= $currentProductCount ?></strong> ürününüz var ve <?php if ($maxProductLimit === -1): ?>sınırsız hakka sahipsiniz<?php else: ?><?= max(0, $marketLimitRemaining) ?> slot kaldı<?php endif; ?>. Sınırsız ürün eklemek için Professional veya Business paketine geçin.
                </p>
                <div class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/80 dark:bg-white/5 border border-amber-100/70 dark:border-white/10">
                        <span class="text-amber-500">●</span> Yeni ürünler için Professional
                    </span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/80 dark:bg-white/5 border border-amber-100/70 dark:border-white/10">
                        <span class="text-amber-500">●</span> Ödeme bildirimleri
                    </span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/80 dark:bg-white/5 border border-amber-100/70 dark:border-white/10">
                        <span class="text-amber-500">●</span> Sınırsız stok
                    </span>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <?php if ($standardProductLimitReached): ?>
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200 font-semibold">
                    Limit Doldu (<?= $currentProductCount ?>/<?= htmlspecialchars((string)($maxProductLimit === -1 ? '∞' : $maxProductLimit)) ?>)
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/80 border border-amber-200 text-amber-700 dark:border-amber-500/40 dark:text-amber-100 font-semibold">
                    Kalan Slot: <?= $marketLimitRemaining === -1 ? 'Sınırsız' : $marketLimitRemaining ?>
                </span>
            <?php endif; ?>
            <a href="index.php?view=subscription" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white font-semibold shadow-lg shadow-orange-500/30 hover:scale-[1.01] transition">
                Professional’a Geç
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div data-view="market" class="max-w-full overflow-x-hidden">
    <!-- Tüm İçerik Tek Kart İçinde -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 section-card">
        <!-- Hata ve Başarı Mesajları -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <!-- Başlık -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Market Yönetimi</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Topluluğunuzun ürünlerini yönetin ve satışa sunun</p>
                </div>
            </div>
            <div class="flex flex-col gap-2 items-start">
                <button onclick="window.openAddProductModal()" id="open-product-modal-btn" class="inline-flex items-center px-4 py-2 rounded-lg transition duration-200 font-medium shadow-sm <?= $standardProductLimitReached ? 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-500/40' : 'bg-indigo-600 text-white hover:bg-indigo-700' ?>">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Yeni Ürün Ekle
            </button>
                <?php if ($standardProductLimitReached): ?>
                <p class="text-xs font-semibold text-amber-700 dark:text-amber-200">Standart paketteki 2 ürün sınırına ulaştınız.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- İstatistik Kartları -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Toplam Ürün</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= count($products) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Aktif Ürün</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= count(array_filter($products, fn($p) => $p['status'] === 'active')) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Toplam Stok</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= array_sum(array_column($products, 'stock')) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ürün Listesi -->
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
            <div class="p-6 border-b border-gray-200 dark:border-gray-600">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ürünler</h3>
                    <div class="flex gap-2">
                        <select id="category-filter" onchange="filterProducts()" class="px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="product-search" onkeyup="filterProducts()" placeholder="Ürün ara..." class="px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    </div>
                </div>
            </div>
            
            <div class="p-6">
            <?php if (!empty($products)): ?>
                <div id="products-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-md transition duration-200" 
                             data-category="<?= htmlspecialchars($product['category']) ?>"
                             data-name="<?= htmlspecialchars(strtolower($product['name'])) ?>">
                            <?php 
                            // Çoklu görsel desteği - ilk görseli göster
                            $display_image_url = null;
                            if (!empty($product['image_path'])) {
                                // image_path JSON array olabilir
                                $image_paths = $product['image_paths'] ?? json_decode($product['image_path'], true);
                                if (is_array($image_paths) && !empty($image_paths)) {
                                    $display_image_url = format_product_image_url($image_paths[0]);
                                } else {
                                    $display_image_url = format_product_image_url($product['image_path']);
                                }
                            }
                            if ($display_image_url): ?>
                                <div class="h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                    <img src="<?= htmlspecialchars($display_image_url) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'200\' height=\'200\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'14\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3EGörsel Yüklenemedi%3C/text%3E%3C/svg%3E';">
                                </div>
                            <?php else: ?>
                                <div class="h-48 bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($product['name']) ?></h4>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $product['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' ?>">
                                        <?= $product['status'] === 'active' ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </div>
                                
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2"><?= htmlspecialchars($product['description'] ?: 'Açıklama yok') ?></p>
                                
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <?php 
                                        $total_price = isset($product['total_price']) && $product['total_price'] > 0 
                                            ? $product['total_price'] 
                                            : $product['price'];
                                        ?>
                                        <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">₺<?= number_format($total_price, 2, ',', '.') ?></span>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <div>KDV dahildir</div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">Stok:</span> <?= $product['stock'] ?>
                                    </div>
                                </div>
                                
                                <!-- Yasal Bilgilendirmeler -->
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                    <div class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Stant teslimatı - Topluluk stantından alınacak
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                            </svg>
                                            2 yıl garanti
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            14 gün iade hakkı
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 rounded"><?= htmlspecialchars($product['category']) ?></span>
                                </div>
                                
                                <div class="flex gap-2">
                                    <button onclick="window.openEditProductModal(<?= (int)$product['id'] ?>, <?= tpl_js_escaped($product['name'] ?? '') ?>, <?= tpl_js_escaped($product['description'] ?? '') ?>, <?= (float)$product['price'] ?>, <?= (int)$product['stock'] ?>, <?= tpl_js_escaped($product['category'] ?? '') ?>, <?= tpl_js_escaped($product['status'] ?? '') ?>, <?= tpl_js_escaped(json_encode($product['image_paths'] ?? ($product['image_path'] ? json_decode($product['image_path'], true) ?? [$product['image_path']] : []))) ?>, <?= (float)($product['commission_rate'] ?? 5.0) ?>)" 
                                            class="flex-1 px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 text-sm font-medium">
                                        Düzenle
                                    </button>
                                    <button onclick="window.deleteProduct(<?= (int)$product['id'] ?>, <?= tpl_js_escaped($product['name'] ?? '') ?>)" 
                                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200 text-sm font-medium">
                                        Sil
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Henüz ürün eklenmemiş</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Topluluğunuzun ürünlerini eklemek için "Yeni Ürün Ekle" butonuna tıklayın.</p>
                    <button onclick="window.openAddProductModal()" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        İlk Ürünü Ekle
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Lazy Loading: Daha Fazla Yükle Butonu -->
            <?php if (isset($has_more_products) && $has_more_products): ?>
            <div class="mt-6 text-center" id="loadMoreProductsContainer">
                <button onclick="window.loadMoreProducts()" id="loadMoreProductsBtn" 
                        class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-sm transition duration-200 flex items-center gap-2 mx-auto">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Daha Fazla Ürün Yükle</span>
                </button>
                <div id="productsLoadingSpinner" class="hidden mt-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Yükleniyor...</p>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ürün Ekleme Modal -->
<div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Yeni Ürün Ekle</h3>
                <button onclick="window.closeAddProductModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data" action="index.php?view=market" class="p-6 space-y-4" id="add-product-form">
            <input type="hidden" name="action" value="add_product">
            <?= csrf_token_field() ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ürün Adı *</label>
                <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    Açıklama
                </label>
                <div class="mb-2">
                    <button type="button" onclick="generateProductDescription('add')" 
                            class="px-3 py-2 text-xs font-medium bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-lg transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow-md"
                            id="ai-generate-product-add-btn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>AI ile Oluştur</span>
                    </button>
                </div>
                <textarea name="description" id="add-product-description" rows="4" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fiyat (₺) *</label>
                    <input type="number" name="price" id="add-product-price" step="0.01" min="0" required oninput="calculateCommission('add')" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KDV dahil fiyat giriniz</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stok Miktarı</label>
                    <input type="number" name="stock" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>
            
            <!-- Yasal Bilgilendirme -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">Yasal Bilgilendirme</h4>
                <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1 list-disc list-inside">
                    <li>Ürün fiyatları KDV dahildir.</li>
                    <li>Tüketiciler 14 gün içinde cayma hakkına sahiptir.</li>
                    <li>Ürünler 2 yıl garanti kapsamındadır.</li>
                    <li>Teslimat: Topluluk stantından elden teslim edilecektir.</li>
                    <li>Yasaklı ürünler satılamaz (alkol, tütün, ilaç vb.).</li>
                </ul>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Platform Komisyon Oranı</label>
                <input type="text" value="<?= defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0 ?>%" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 cursor-not-allowed">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sabit komisyon oranı (değiştirilemez)</p>
            </div>
            
            <!-- Komisyon Hesaplama Gösterimi -->
            <div id="add-commission-preview" class="hidden bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Komisyon Hesaplaması</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Satış Fiyatı:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100" id="add-base-price">₺0,00</span>
                    </div>
                    <div class="flex justify-between" id="add-iyzico-row" style="display: none;">
                        <span class="text-gray-600 dark:text-gray-400">İyzico Komisyonu:</span>
                        <span class="font-medium text-orange-600 dark:text-orange-400" id="add-iyzico-commission">₺0,00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Platform Komisyonu (%<?= defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0 ?>):</span>
                        <span class="font-medium text-purple-600 dark:text-purple-400" id="add-platform-commission">₺0,00</span>
                    </div>
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Müşteri Ödeyeceği Toplam:</span>
                        <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400" id="add-total-price">₺0,00</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                        * Komisyon fiyatın içinden alınır (üstüne eklenmez)
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategori</label>
                <input type="text" name="category" value="Genel" list="categories-list" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <datalist id="categories-list">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ürün Görselleri (Maksimum 10)</label>
                <input type="file" name="images[]" accept="image/*" multiple id="add-product-images-input" onchange="previewProductImages('add', this)" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">JPG, PNG, GIF veya WebP formatında (Max: 2MB per görsel, Maksimum 10 görsel)</p>
                <div id="add-product-images-preview" class="mt-3 grid grid-cols-3 gap-2"></div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" onclick="window.closeAddProductModal()" class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition duration-200">
                    İptal
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 font-medium">
                    Ürün Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ürün Düzenleme Modal -->
<div id="editProductModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Ürün Düzenle</h3>
                <button onclick="window.closeEditProductModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data" action="index.php?view=market" class="p-6 space-y-4" id="edit-product-form">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="id" id="edit-product-id">
            <?= csrf_token_field() ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ürün Adı *</label>
                <input type="text" name="name" id="edit-product-name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    Açıklama
                </label>
                <div class="mb-2">
                    <button type="button" onclick="generateProductDescription('edit')" 
                            class="px-3 py-2 text-xs font-medium bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-lg transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow-md"
                            id="ai-generate-product-edit-btn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>AI ile Oluştur</span>
                    </button>
                </div>
                <textarea name="description" id="edit-product-description" rows="4" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fiyat (₺) *</label>
                    <input type="number" name="price" id="edit-product-price" step="0.01" min="0" required oninput="calculateCommission('edit')" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KDV dahil fiyat giriniz</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stok Miktarı</label>
                    <input type="number" name="stock" id="edit-product-stock" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>
            
            <!-- Yasal Bilgilendirme -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">Yasal Bilgilendirme</h4>
                <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1 list-disc list-inside">
                    <li>Ürün fiyatları KDV dahildir.</li>
                    <li>Tüketiciler 14 gün içinde cayma hakkına sahiptir.</li>
                    <li>Ürünler 2 yıl garanti kapsamındadır.</li>
                    <li>Teslimat: Topluluk stantından elden teslim edilecektir.</li>
                    <li>Yasaklı ürünler satılamaz (alkol, tütün, ilaç vb.).</li>
                </ul>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Platform Komisyon Oranı</label>
                <input type="text" value="<?= defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0 ?>%" readonly class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 cursor-not-allowed">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sabit komisyon oranı (değiştirilemez)</p>
            </div>
            
            <!-- Komisyon Hesaplama Gösterimi -->
            <div id="edit-commission-preview" class="hidden bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Komisyon Hesaplaması</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Satış Fiyatı:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100" id="edit-base-price">₺0,00</span>
                    </div>
                    <div class="flex justify-between" id="edit-iyzico-row" style="display: none;">
                        <span class="text-gray-600 dark:text-gray-400">İyzico Komisyonu:</span>
                        <span class="font-medium text-orange-600 dark:text-orange-400" id="edit-iyzico-commission">₺0,00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Platform Komisyonu (%<?= defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0 ?>):</span>
                        <span class="font-medium text-purple-600 dark:text-purple-400" id="edit-platform-commission">₺0,00</span>
                    </div>
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Müşteri Ödeyeceği Toplam:</span>
                        <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400" id="edit-total-price">₺0,00</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                        * Komisyon fiyatın içinden alınır (üstüne eklenmez)
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategori</label>
                <input type="text" name="category" id="edit-product-category" list="categories-list-edit" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <datalist id="categories-list-edit">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Durum</label>
                <select name="status" id="edit-product-status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="active">Aktif</option>
                    <option value="inactive">Pasif</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ürün Görselleri (Maksimum 10)</label>
                <div id="edit-product-images-preview" class="mb-2 grid grid-cols-3 gap-2"></div>
                <input type="file" name="images[]" accept="image/*" multiple id="edit-product-images-input" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" onchange="previewProductImages('edit', this)">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Yeni görseller yüklerseniz eski görseller değiştirilir (Maksimum 10 görsel)</p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" onclick="window.closeEditProductModal()" class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition duration-200">
                    İptal
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 font-medium">
                    Güncelle
                </button>
            </div>
        </form>
    </div>
</div>

<script<?= tpl_script_nonce_attr(); ?>>
window.marketProductLimit = {
    isStandardPlan: <?= $isStandardPlan ? 'true' : 'false' ?>,
    limit: <?= $maxProductLimit === null ? 'null' : (int)$maxProductLimit ?>,
    remaining: <?= $marketLimitRemaining === null ? 'null' : ($marketLimitRemaining === -1 ? '-1' : (int)$marketLimitRemaining) ?>,
    reached: <?= $standardProductLimitReached ? 'true' : 'false' ?>
};

window.showMarketLimitToast = function(limit) {
    const message = limit && limit !== -1
        ? `Standart pakette en fazla ${limit} ürün yayınlayabilirsiniz.`
        : 'Standart paket limitine ulaştınız.';
    if (typeof toastManager !== 'undefined' && typeof toastManager.show === 'function') {
        toastManager.show('Limit Uyarısı', message, 'warning', 4500);
    } else {
        alert(message);
    }
};

// Global scope'a fonksiyonları ekle - window objesine atama
window.openAddProductModal = function() {
    try {
    const limitInfo = window.marketProductLimit || {};
    const limitValue = typeof limitInfo.limit !== 'undefined' ? limitInfo.limit : null;
    const remainingRaw = typeof limitInfo.remaining !== 'undefined' && limitInfo.remaining !== null ? limitInfo.remaining : 0;
    const remaining = Number(remainingRaw);
    if (limitInfo.isStandardPlan && limitValue !== null && limitValue !== -1 && remaining <= 0) {
            if (typeof showMarketLimitToast === 'function') {
        showMarketLimitToast(limitValue);
            }
        const modal = document.getElementById('addProductModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        return;
    }
        const modal = document.getElementById('addProductModal');
        if (!modal) {
            console.error('addProductModal bulunamadı!');
            alert('Modal bulunamadı. Sayfayı yenileyin.');
            return;
        }
        modal.classList.remove('hidden');
    } catch (error) {
        console.error('openAddProductModal hatası:', error);
        alert('Modal açılırken bir hata oluştu: ' + error.message);
    }
};

window.closeAddProductModal = function() {
    try {
        const modal = document.getElementById('addProductModal');
        if (!modal) {
            console.error('addProductModal bulunamadı!');
            return;
        }
        modal.classList.add('hidden');
        
        // Form'u temizle
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        
        // Preview'ı temizle
        const preview = document.getElementById('add-product-images-preview');
        if (preview) {
        preview.innerHTML = '';
    }
    } catch (error) {
        console.error('closeAddProductModal hatası:', error);
    }
};

window.openEditProductModal = function(id, name, description, price, stock, category, status, imagePaths, commissionRate) {
    try {
        const modal = document.getElementById('editProductModal');
        if (!modal) {
            console.error('editProductModal bulunamadı!');
            alert('Modal bulunamadı. Sayfayı yenileyin.');
            return;
        }
        
        const idField = document.getElementById('edit-product-id');
        const nameField = document.getElementById('edit-product-name');
        const descField = document.getElementById('edit-product-description');
        const priceField = document.getElementById('edit-product-price');
        const stockField = document.getElementById('edit-product-stock');
        const categoryField = document.getElementById('edit-product-category');
        const statusField = document.getElementById('edit-product-status');
        const preview = document.getElementById('edit-product-images-preview');
        
        if (!idField || !nameField || !descField || !priceField || !stockField || !categoryField || !statusField || !preview) {
            console.error('Edit modal input alanları bulunamadı!');
            alert('Modal alanları bulunamadı. Sayfayı yenileyin.');
            return;
        }
        
        idField.value = id || '';
        nameField.value = name || '';
        descField.value = description || '';
        priceField.value = price || 0;
        stockField.value = stock || 0;
        categoryField.value = category || '';
        statusField.value = status || 'active';
        
        // Çoklu görsel desteği - imagePaths JSON array veya tek string olabilir
        preview.innerHTML = '';
        if (imagePaths) {
            try {
                // JSON array olarak parse et
                const paths = typeof imagePaths === 'string' ? JSON.parse(imagePaths) : imagePaths;
                if (Array.isArray(paths)) {
                    paths.forEach((path, index) => {
                        if (path) {
                            const imgUrl = path.startsWith('secure://') ? `index.php?action=product_media&file=${encodeURIComponent(path.replace('secure://products/', ''))}` : path;
                            preview.innerHTML += `<div class="relative" data-old="true"><img src="${imgUrl}" alt="${name || 'Ürün'} ${index + 1}" class="w-full h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-600" onerror="this.parentElement.remove()"></div>`;
                        }
                    });
                } else if (typeof paths === 'string') {
                    // Tek string (eski format)
                    const imgUrl = paths.startsWith('secure://') ? `index.php?action=product_media&file=${encodeURIComponent(paths.replace('secure://products/', ''))}` : paths;
                    preview.innerHTML = `<div class="relative" data-old="true"><img src="${imgUrl}" alt="${name || 'Ürün'}" class="w-full h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-600" onerror="this.parentElement.remove()"></div>`;
                }
            } catch (e) {
                // JSON parse hatası - tek string olarak kullan
                const imgUrl = (typeof imagePaths === 'string' ? imagePaths : '').startsWith('secure://') ? `index.php?action=product_media&file=${encodeURIComponent((typeof imagePaths === 'string' ? imagePaths : '').replace('secure://products/', ''))}` : (typeof imagePaths === 'string' ? imagePaths : '');
                if (imgUrl) {
                    preview.innerHTML = `<div class="relative" data-old="true"><img src="${imgUrl}" alt="${name || 'Ürün'}" class="w-full h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-600" onerror="this.parentElement.remove()"></div>`;
                }
            }
    }
    
    // Komisyon hesaplamasını göster
        if (typeof calculateCommission === 'function') {
    calculateCommission('edit');
        }
    
        modal.classList.remove('hidden');
    } catch (error) {
        console.error('openEditProductModal hatası:', error);
        alert('Modal açılırken bir hata oluştu: ' + error.message);
}
};

window.calculateCommission = function(type) {
    const prefix = type === 'add' ? 'add' : 'edit';
    const priceInput = document.getElementById(`${prefix}-product-price`);
    const previewDiv = document.getElementById(`${prefix}-commission-preview`);
    
    if (!priceInput || !previewDiv) return;
    
    const price = parseFloat(priceInput.value) || 0;
    // Sabit komisyon oranı (değiştirilemez)
    const commissionRate = <?= defined('FIXED_COMMISSION_RATE') ? FIXED_COMMISSION_RATE : 5.0 ?>;
    
    if (price <= 0) {
        previewDiv.classList.add('hidden');
        return;
    }
    
    // İyzico komisyonu gizlendi (artık gösterilmiyor)
    const iyzicoCommission = 0;
    
    // Platform komisyonu: Fiyatın içinden alınır (üstüne eklenmez)
    const platformCommission = price * commissionRate / 100;
    
    // Toplam fiyat = girilen fiyat (komisyon içinde)
    const total = price;
    
    // Göster (İyzico satırını gizle)
    const basePriceEl = document.getElementById(`${prefix}-base-price`);
    if (basePriceEl) basePriceEl.textContent = window.formatPrice(price);
    // İyzico satırını gizle
    const iyzicoRow = document.getElementById(`${prefix}-iyzico-row`);
    if (iyzicoRow) iyzicoRow.style.display = 'none';
    const platformCommissionEl = document.getElementById(`${prefix}-platform-commission`);
    if (platformCommissionEl) platformCommissionEl.textContent = window.formatPrice(platformCommission);
    const totalPriceEl = document.getElementById(`${prefix}-total-price`);
    if (totalPriceEl) totalPriceEl.textContent = window.formatPrice(total);
    
    previewDiv.classList.remove('hidden');
};

window.formatPrice = function(amount) {
    return '₺' + amount.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

window.closeEditProductModal = function() {
    try {
        const modal = document.getElementById('editProductModal');
        if (!modal) {
            console.error('editProductModal bulunamadı!');
            return;
        }
        modal.classList.add('hidden');
        
        // Yeni seçilen dosyaların preview'ını temizle (eski görselleri koru)
        const input = document.getElementById('edit-product-images-input');
        if (input) {
            input.value = '';
        }
        
        // Yeni seçilen dosyaların preview'ını kaldır (eski görselleri korumak için)
        const preview = document.getElementById('edit-product-images-preview');
        if (preview) {
            // Sadece yeni eklenen preview'ları kaldır (data-old attribute'u olmayanları)
            const newPreviews = preview.querySelectorAll('.relative:not([data-old])');
            newPreviews.forEach(el => el.remove());
        }
    } catch (error) {
        console.error('closeEditProductModal hatası:', error);
    }
};

window.deleteProduct = function(id, name) {
    // XSS koruması: name'i escape et
    const escapedName = String(name).replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[ch] || ch);
    if (confirm(`"${escapedName}" adlı ürünü silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?view=market';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_product';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        // CSRF token'ı form'dan al veya session'dan
        const existingToken = document.querySelector('input[name="csrf_token"]');
        csrfInput.value = existingToken ? existingToken.value : '';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function filterProducts() {
    const searchTerm = document.getElementById('product-search').value.toLowerCase();
    const categoryFilter = document.getElementById('category-filter').value;
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        const name = card.getAttribute('data-name');
        const category = card.getAttribute('data-category');
        
        const matchesSearch = !searchTerm || name.includes(searchTerm);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

async function generateProductDescription(mode) {
    const btn = document.getElementById(`ai-generate-product-${mode}-btn`);
    let descriptionField, nameField, priceField, categoryField, form;
    
    if (mode === 'add') {
        descriptionField = document.getElementById('add-product-description');
        nameField = document.querySelector('#addProductModal input[name="name"]');
        priceField = document.getElementById('add-product-price');
        categoryField = document.querySelector('#addProductModal input[name="category"]');
        form = document.querySelector('#addProductModal form');
    } else {
        descriptionField = document.getElementById('edit-product-description');
        nameField = document.getElementById('edit-product-name');
        priceField = document.getElementById('edit-product-price');
        categoryField = document.querySelector('#editProductModal input[name="category"]');
        form = document.querySelector('#editProductModal form');
    }
    
    if (!descriptionField) {
        alert('Hata: Açıklama alanı bulunamadı');
        return;
    }
    
    // Form verilerini topla
    const name = nameField?.value || '';
    const price = priceField?.value || '';
    const category = categoryField?.value || 'Genel';
    
    // CSRF token'ı al
    const csrfToken = form?.querySelector('input[name="csrf_token"]')?.value || '';
    
    if (!csrfToken) {
        alert('Güvenlik hatası: CSRF token bulunamadı');
        return;
    }
    
    // Minimum bilgi kontrolü
    if (!name || !price) {
        alert('Lütfen önce ürün adı ve fiyat bilgilerini girin');
        return;
    }
    
    // Butonu devre dışı bırak
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Oluşturuluyor...';
    
    try {
        const response = await fetch('index.php?view=market', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ai_generate_product_description',
                csrf_token: csrfToken,
                name: name,
                price: price,
                category: category,
            }),
        });
        
        const data = await response.json();
        
        if (data.success) {
            descriptionField.value = data.description;
            alert('Ürün açıklaması başarıyla oluşturuldu!');
        } else {
            alert(data.message || 'Ürün açıklaması oluşturulamadı');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Bir hata oluştu: ' + error.message);
    } finally {
        // Butonu tekrar aktif et
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Lazy Loading - Ürünler
window.productsOffset = <?= isset($has_more_products) && $has_more_products ? 30 : 0 ?>;
window.isLoadingProducts = false;

window.loadMoreProducts = function() {
    if (window.isLoadingProducts) return;
    
    window.isLoadingProducts = true;
    const btn = document.getElementById('loadMoreProductsBtn');
    const spinner = document.getElementById('productsLoadingSpinner');
    
    if (btn) btn.style.display = 'none';
    if (spinner) spinner.classList.remove('hidden');
    
    fetch(`../api/load_products.php?offset=${window.productsOffset}&limit=30`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                const container = document.getElementById('products-grid');
                
                data.products.forEach(product => {
                    const card = window.createProductCard(product);
                    container.insertAdjacentHTML('beforeend', card);
                });
                
                window.productsOffset += data.products.length;
                
                if (!data.has_more) {
                    const container = document.getElementById('loadMoreProductsContainer');
                    if (container) container.remove();
                } else {
                    if (btn) btn.style.display = 'flex';
                }
            } else {
                const container = document.getElementById('loadMoreProductsContainer');
                if (container) container.remove();
            }
        })
        .catch(error => {
            console.error('Ürün yükleme hatası:', error);
            alert('Ürünler yüklenirken bir hata oluştu.');
        })
        .finally(() => {
            window.isLoadingProducts = false;
            if (spinner) spinner.classList.add('hidden');
        });
};

window.createProductCard = function(product) {
    const escapeHtml = window.escapeHtml || function(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };
    
    const escapeJs = function(text) {
        if (text == null) return '';
        return String(text).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
    };
    
    const imageUrl = product.image_url || (product.image_path ? `index.php?action=product_media&file=${encodeURIComponent(product.image_path)}` : '');
    const statusClass = product.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    const statusText = product.status === 'active' ? 'Aktif' : 'Pasif';
    
    // Değerleri önceden hazırla
    const productId = parseInt(product.id || 0);
    const productName = escapeJs(product.name || '');
    const productDesc = escapeJs(product.description || '');
    const productPrice = parseFloat(product.price || 0);
    const productStock = parseInt(product.stock || 0);
    const productCategory = escapeJs(product.category || '');
    const productStatus = escapeJs(product.status || 'active');
    // Çoklu görsel desteği
    const productImagePaths = product.image_paths ? (typeof product.image_paths === 'string' ? JSON.parse(product.image_paths) : product.image_paths) : (product.image_path ? (typeof product.image_path === 'string' && product.image_path.startsWith('[') ? JSON.parse(product.image_path) : [product.image_path]) : []);
    const productImageUrl = productImagePaths.length > 0 ? (productImagePaths[0].startsWith('secure://') ? `index.php?action=product_media&file=${encodeURIComponent(productImagePaths[0].replace('secure://products/', ''))}` : productImagePaths[0]) : (product.image_url || '');
    const productImagePathsJson = escapeJs(JSON.stringify(productImagePaths));
    const productCommissionRate = parseFloat(product.commission_rate || 5.0);
    
    return `
        <div class="product-card bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-md transition duration-200" 
             data-category="${escapeHtml(product.category || '')}"
             data-name="${escapeHtml((product.name || '').toLowerCase())}">
            ${imageUrl ? `
                <div class="h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(product.name || '')}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Crect fill=\'%23ddd\' width=\'200\' height=\'200\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'14\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3EGörsel Yüklenemedi%3C/text%3E%3C/svg%3E';">
                </div>
            ` : `
                <div class="h-48 bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            `}
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(product.name || '')}</h4>
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusClass}">${statusText}</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">${escapeHtml(product.description || 'Açıklama yok')}</p>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">₺${parseFloat(product.price || 0).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Stok:</span> ${product.stock || 0}
                    </div>
                </div>
                <div class="flex gap-2">
                    <button data-product-id="${productId}" data-product-name="${escapeHtml(productName)}" data-product-desc="${escapeHtml(productDesc)}" data-product-price="${productPrice}" data-product-stock="${productStock}" data-product-category="${escapeHtml(productCategory)}" data-product-status="${escapeHtml(productStatus)}" data-product-image="${escapeHtml(productImageUrl)}" data-product-image-paths="${escapeHtml(productImagePathsJson)}" data-product-commission="${productCommissionRate}" class="edit-product-btn flex-1 px-3 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors">
                        Düzenle
                    </button>
                    <button data-product-id="${productId}" data-product-name="${escapeHtml(productName)}" class="delete-product-btn px-3 py-2 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            Sil
                        </button>
                </div>
            </div>
        </div>
    `;
};

window.escapeHtml = function(text) {
    if (text == null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
};

// Çoklu görsel preview fonksiyonu
window.previewProductImages = function(mode, input) {
    const previewId = mode === 'add' ? 'add-product-images-preview' : 'edit-product-images-preview';
    const preview = document.getElementById(previewId);
    
    if (!preview || !input || !input.files) {
        return;
    }
    
    // Yeni seçilen dosyalar için preview oluştur (eski görselleri koru)
    const files = Array.from(input.files);
    
    // Maksimum 10 görsel kontrolü (eski + yeni)
    const existingCount = preview.querySelectorAll('.relative[data-old="true"]').length;
    const totalCount = existingCount + files.length;
    
    if (totalCount > 10) {
        const allowedNew = 10 - existingCount;
        if (allowedNew <= 0) {
            alert('Maksimum 10 görsel ekleyebilirsiniz. Lütfen önce mevcut görsellerden bazılarını kaldırın.');
            input.value = '';
            return;
        }
        alert(`Maksimum 10 görsel ekleyebilirsiniz. İlk ${allowedNew} görsel gösterilecek.`);
        files.splice(allowedNew);
    }
    
    // Yeni seçilen dosyalar için preview oluştur
    files.forEach((file, index) => {
        if (!file.type.startsWith('image/')) {
            return;
        }
        
        // Dosya boyutu kontrolü (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert(`"${file.name}" dosyası 2MB'dan büyük. Bu dosya atlanacak.`);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgDiv = document.createElement('div');
            imgDiv.className = 'relative';
            imgDiv.setAttribute('data-file-index', index);
            imgDiv.innerHTML = `
                <img src="${e.target.result}" alt="Yeni görsel ${index + 1}" class="w-full h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-600">
                <button type="button" onclick="removeImagePreview(this, '${mode}')" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600">×</button>
            `;
            preview.appendChild(imgDiv);
        };
        reader.onerror = function() {
            alert(`"${file.name}" dosyası okunamadı.`);
        };
        reader.readAsDataURL(file);
    });
};

// Görsel preview'dan kaldırma fonksiyonu
window.removeImagePreview = function(button, mode) {
    const imgDiv = button.closest('.relative');
    if (!imgDiv) {
        return;
    }
    
    // Eski görsel mi yoksa yeni seçilen dosya mı?
    const isOld = imgDiv.getAttribute('data-old') === 'true';
    
    if (isOld) {
        // Eski görseli kaldır - kullanıcıya bilgi ver
        if (confirm('Bu görseli kaldırmak istediğinize emin misiniz? Ürünü kaydettiğinizde bu görsel silinecek.')) {
            imgDiv.remove();
        }
    } else {
        // Yeni seçilen dosyayı kaldır
        const fileIndex = imgDiv.getAttribute('data-file-index');
        imgDiv.remove();
        
        // Input'tan dosyayı kaldırmak için yeni bir FileList oluştur
        const inputId = mode === 'add' ? 'add-product-images-input' : 'edit-product-images-input';
        const input = document.getElementById(inputId);
        if (input && input.files) {
            const dt = new DataTransfer();
            const files = Array.from(input.files);
            files.forEach((file, index) => {
                if (index.toString() !== fileIndex) {
                    dt.items.add(file);
                }
            });
            input.files = dt.files;
        }
    }
};

// Form submit kontrolü - dosyaların düzgün gönderildiğinden emin ol
document.addEventListener('DOMContentLoaded', function() {
    // Add product form submit kontrolü
    const addForm = document.getElementById('add-product-form') || document.querySelector('#addProductModal form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('add-product-images-input');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                console.log('Add form submitting with', fileInput.files.length, 'files');
                // FormData'yı kontrol et
                const formData = new FormData(addForm);
                const files = formData.getAll('images[]');
                console.log('FormData files count:', files.length);
                if (files.length === 0) {
                    console.warn('No files in FormData!');
                }
            }
        });
    }
    
    // Edit product form submit kontrolü
    const editForm = document.getElementById('edit-product-form') || document.querySelector('#editProductModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('edit-product-images-input');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                console.log('Edit form submitting with', fileInput.files.length, 'files');
                // FormData'yı kontrol et
                const formData = new FormData(editForm);
                const files = formData.getAll('images[]');
                console.log('FormData files count:', files.length);
                if (files.length === 0) {
                    console.warn('No files in FormData!');
                }
            }
        });
    }
    
    // Fonksiyonların tanımlı olduğunu kontrol et
    if (typeof window.openAddProductModal !== 'function') {
        console.error('openAddProductModal fonksiyonu tanımlı değil!');
    }
    if (typeof window.openEditProductModal !== 'function') {
        console.error('openEditProductModal fonksiyonu tanımlı değil!');
    }
    if (typeof window.deleteProduct !== 'function') {
        console.error('deleteProduct fonksiyonu tanımlı değil!');
    }
    if (typeof window.previewProductImages !== 'function') {
        console.error('previewProductImages fonksiyonu tanımlı değil!');
    }
    
    // Dinamik olarak eklenen ürün kartlarındaki butonlara event listener ekle
    document.addEventListener('click', function(e) {
        // Düzenle butonu
        if (e.target.classList.contains('edit-product-btn') || e.target.closest('.edit-product-btn')) {
            const btn = e.target.classList.contains('edit-product-btn') ? e.target : e.target.closest('.edit-product-btn');
            const productId = parseInt(btn.getAttribute('data-product-id') || 0);
            const productName = btn.getAttribute('data-product-name') || '';
            const productDesc = btn.getAttribute('data-product-desc') || '';
            const productPrice = parseFloat(btn.getAttribute('data-product-price') || 0);
            const productStock = parseInt(btn.getAttribute('data-product-stock') || 0);
            const productCategory = btn.getAttribute('data-product-category') || '';
            const productStatus = btn.getAttribute('data-product-status') || 'active';
            // Çoklu görsel desteği - data-product-image-paths attribute'unu kontrol et
            const productImagePaths = btn.getAttribute('data-product-image-paths') || btn.getAttribute('data-product-image') || '[]';
            const productCommission = parseFloat(btn.getAttribute('data-product-commission') || 5.0);
            
            if (typeof window.openEditProductModal === 'function') {
                window.openEditProductModal(productId, productName, productDesc, productPrice, productStock, productCategory, productStatus, productImagePaths, productCommission);
            }
        }
        
        // Sil butonu
        if (e.target.classList.contains('delete-product-btn') || e.target.closest('.delete-product-btn')) {
            const btn = e.target.classList.contains('delete-product-btn') ? e.target : e.target.closest('.delete-product-btn');
            const productId = parseInt(btn.getAttribute('data-product-id') || 0);
            const productName = btn.getAttribute('data-product-name') || '';
            
            if (typeof window.deleteProduct === 'function') {
                window.deleteProduct(productId, productName);
            }
        }
    });
    
    // Butonlara event listener ekle (fallback)
    const addBtn = document.getElementById('open-product-modal-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.openAddProductModal === 'function') {
                window.openAddProductModal();
            } else {
                alert('Modal açma fonksiyonu yüklenemedi. Sayfayı yenileyin.');
            }
        });
    }
});
</script>

<!-- Yasal Bilgiler Footer -->
<div class="mt-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Yasal Bilgiler</h4>
            <ul class="space-y-2 text-xs">
                <li>
                    <a href="https://foursoftware.net/marketing/stand-delivery-contract.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Stant Teslimat Sözleşmesi
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/cancellation-refund.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        İptal & İade Koşulları
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/pre-information-form.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Ön Bilgilendirme Formu
                    </a>
                </li>
            </ul>
        </div>
        
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Gizlilik & Güvenlik</h4>
            <ul class="space-y-2 text-xs">
                <li>
                    <a href="https://foursoftware.net/marketing/privacy-policy.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Gizlilik Politikası
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/kvkk-aydinlatma-metni.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        KVKK Aydınlatma Metni
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/cookie-policy.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Çerez Politikası
                    </a>
                </li>
            </ul>
        </div>
        
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Kullanım</h4>
            <ul class="space-y-2 text-xs">
                <li>
                    <a href="https://foursoftware.net/marketing/terms-of-use.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Kullanım Şartları
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/consumer-rights.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Tüketici Hakları
                    </a>
                </li>
                <li>
                    <a href="https://foursoftware.net/marketing/complaint-form.php" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        Şikayet Formu
                    </a>
                </li>
            </ul>
        </div>
        
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Bilgilendirme</h4>
            <ul class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                <li>• Teslimat: Stant teslimatı - Topluluk stantından alınacak</li>
                <li>• Garanti: 2 yıl</li>
                <li>• İade: 14 gün içinde</li>
                <li>• Ödeme: SSL ile güvenli</li>
            </ul>
        </div>
    </div>
    
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
        <div class="flex flex-col items-center gap-2">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">
                © <?= date('Y') ?> <span class="font-semibold text-indigo-600 dark:text-indigo-400">UniFour</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-500">
                Four Software tarafından geliştirilmiştir
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-600">
                Tüm hakları saklıdır.
        </p>
        </div>
    </div>
</div>

<?php 
if (function_exists('tpl_inline_handler_transform_end')) {
    tpl_inline_handler_transform_end();
} elseif (ob_get_level() > 0) {
    ob_end_flush();
}
?>
