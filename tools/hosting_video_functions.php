<?php
/**
 * Hosting Ortamı İçin Özel Video Yükleme Fonksiyonu
 */

function handle_video_upload_hosting($file, $subfolder, $allowed_extensions, $max_size) {
    try {
        // Hosting ortamı için özel video yükleme
        $upload_dir = __DIR__ . '/assets/' . $subfolder;
        
        // Klasör oluştur
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Video klasörü oluşturulamadı: ' . $upload_dir);
            }
        }
        
        // İzinleri düzelt
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0755);
            if (!is_writable($upload_dir)) {
                chmod($upload_dir, 0777);
                if (!is_writable($upload_dir)) {
                    throw new Exception('Video klasörü yazılabilir değil: ' . $upload_dir);
                }
            }
        }
        
        // Dosya bilgilerini al
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];
        $file_name = $file['name'];
        $file_type = $file['type'];
        
        // Video dosya türü kontrolü
        $video_mime_types = [
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'application/octet-stream' // Bazı hosting'ler bu şekilde gönderir
        ];
        
        if (!in_array($file_type, $video_mime_types) && !in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Geçersiz video dosya türü. MIME: ' . $file_type . ', Uzantı: ' . $file_extension);
        }
        
        // Uzantı kontrolü
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Geçersiz video uzantısı. İzin verilen: ' . implode(', ', $allowed_extensions) . ' (Yüklenen: ' . $file_extension . ')');
        }
        
        // Boyut kontrolü
        if ($file_size > $max_size) {
            throw new Exception('Video dosya boyutu çok büyük. Maksimum: ' . round($max_size / (1024 * 1024), 1) . 'MB, Yüklenen: ' . round($file_size / (1024 * 1024), 1) . 'MB');
        }
        
        // Dosya boyutu 0 kontrolü
        if ($file_size == 0) {
            throw new Exception('Video dosya boyutu 0 bytes. Dosya boş olabilir.');
        }
        
        // Disk alanı kontrolü
        $free_space = disk_free_space($upload_dir);
        if ($free_space && $free_space < ($file_size * 2)) {
            throw new Exception('Yetersiz disk alanı. Gerekli: ' . round($file_size / (1024 * 1024), 1) . 'MB, Mevcut: ' . round($free_space / (1024 * 1024), 1) . 'MB');
        }
        
        // Benzersiz dosya adı oluştur
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . '/' . $filename;
        
        // Dosyayı taşı
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Dosyanın gerçekten oluştuğunu kontrol et
            if (!file_exists($file_path)) {
                throw new Exception('Video dosyası oluşturulamadı - file_exists() false döndü');
            }
            
            // Dosya boyutunu kontrol et
            $actual_size = filesize($file_path);
            if ($actual_size != $file_size) {
                throw new Exception('Video dosya boyutu uyuşmuyor. Beklenen: ' . $file_size . ', Gerçek: ' . $actual_size);
            }
            
            // Dosya izinlerini düzelt
            chmod($file_path, 0644);
            
            return 'assets/' . $subfolder . $filename;
        } else {
            // Detaylı hata mesajı
            $error_info = [
                'tmp_name' => $file['tmp_name'],
                'target_path' => $file_path,
                'tmp_exists' => file_exists($file['tmp_name']),
                'target_dir_exists' => is_dir($upload_dir),
                'target_dir_writable' => is_writable($upload_dir),
                'target_dir_perms' => substr(sprintf('%o', fileperms($upload_dir)), -4),
                'free_space' => disk_free_space($upload_dir)
            ];
            
            throw new Exception('move_uploaded_file() başarısız. Detaylar: ' . json_encode($error_info));
        }
    } catch (Exception $e) {
        error_log("Hosting video upload error: " . $e->getMessage());
        $_SESSION['error'] = 'Video yükleme hatası: ' . $e->getMessage();
        return '';
    }
}

function add_event_with_video_hosting($db, $post) {
    try {
        // Dosya yükleme işlemleri
        $image_path = '';
        $video_path = '';
        $upload_errors = [];
        
        // Görsel yükleme
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = handle_file_upload_hosting_safe($_FILES['event_image'], 'images/events/', ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024); // 5MB
            if (empty($image_path)) {
                $upload_errors[] = 'Görsel yüklenemedi';
            }
        } elseif (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Görsel dosya boyutu çok büyük (upload_max_filesize limiti)',
                UPLOAD_ERR_FORM_SIZE => 'Görsel dosya boyutu çok büyük (MAX_FILE_SIZE limiti)',
                UPLOAD_ERR_PARTIAL => 'Görsel dosya sadece kısmen yüklendi',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör eksik',
                UPLOAD_ERR_CANT_WRITE => 'Görsel dosya yazılamadı',
                UPLOAD_ERR_EXTENSION => 'Görsel dosya yükleme bir uzantı tarafından durduruldu'
            ];
            $upload_errors[] = $error_messages[$_FILES['event_image']['error']] ?? 'Görsel yükleme hatası';
        }
        
        // Video yükleme - Özel hosting fonksiyonu
        if (isset($_FILES['event_video']) && $_FILES['event_video']['error'] === UPLOAD_ERR_OK) {
            $video_path = handle_video_upload_hosting($_FILES['event_video'], 'videos/events/', ['mp4', 'avi', 'mov', 'wmv'], 50 * 1024 * 1024); // 50MB
            if (empty($video_path)) {
                $upload_errors[] = 'Video yüklenemedi';
            }
        } elseif (isset($_FILES['event_video']) && $_FILES['event_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Video dosya boyutu çok büyük (upload_max_filesize limiti) - Hosting sağlayıcınızla iletişime geçin',
                UPLOAD_ERR_FORM_SIZE => 'Video dosya boyutu çok büyük (MAX_FILE_SIZE limiti) - Form limitini artırın',
                UPLOAD_ERR_PARTIAL => 'Video dosya sadece kısmen yüklendi - İnternet bağlantınızı kontrol edin',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör eksik - Hosting sağlayıcınızla iletişime geçin',
                UPLOAD_ERR_CANT_WRITE => 'Video dosya yazılamadı - Klasör izinlerini kontrol edin',
                UPLOAD_ERR_EXTENSION => 'Video dosya yükleme bir uzantı tarafından durduruldu - Güvenlik yazılımını kontrol edin'
            ];
            $upload_errors[] = $error_messages[$_FILES['event_video']['error']] ?? 'Video yükleme hatası';
        }
        
        // Dosya yükleme hatalarını kontrol et
        if (!empty($upload_errors)) {
            $_SESSION['error'] = "Dosya yükleme hataları: " . implode(', ', $upload_errors);
            return;
        }
        
        // Etkinliği veritabanına ekle
        $stmt = $db->prepare("INSERT INTO events (club_id, title, date, time, location, description, image_path, video_path) VALUES (:club_id, :title, :date, :time, :location, :description, :image_path, :video_path)");
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $post['title'], SQLITE3_TEXT);
        $stmt->bindValue(':date', $post['date'], SQLITE3_TEXT);
        $stmt->bindValue(':time', $post['time'], SQLITE3_TEXT);
        $stmt->bindValue(':location', $post['location'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $post['description'], SQLITE3_TEXT);
        $stmt->bindValue(':image_path', $image_path, SQLITE3_TEXT);
        $stmt->bindValue(':video_path', $video_path, SQLITE3_TEXT);
        $stmt->execute();
        
        $_SESSION['message'] = "Etkinlik başarıyla eklendi. 🎉";
        if (!empty($image_path)) {
            $_SESSION['message'] .= " Görsel yüklendi.";
        }
        if (!empty($video_path)) {
            $_SESSION['message'] .= " Video yüklendi.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Etkinlik eklenirken hata: " . $e->getMessage();
    }
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
?>
