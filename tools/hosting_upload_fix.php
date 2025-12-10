<?php
/**
 * Hosting Ortamı İçin Gelişmiş Dosya Yükleme Sistemi
 */

function handle_file_upload_hosting($file, $subfolder, $allowed_extensions, $max_size) {
    try {
        // Hosting ortamı için özel kontroller
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
                    throw new Exception('Klasör yazılabilir değil: ' . $upload_dir . ' (İzinler: ' . substr(sprintf('%o', fileperms($upload_dir)), -4) . ')');
                }
            }
        }
        
        // Dosya bilgilerini al
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];
        $file_name = $file['name'];
        
        // Uzantı kontrolü
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Geçersiz dosya uzantısı. İzin verilen: ' . implode(', ', $allowed_extensions) . ' (Yüklenen: ' . $file_extension . ')');
        }
        
        // Boyut kontrolü
        if ($file_size > $max_size) {
            throw new Exception('Dosya boyutu çok büyük. Maksimum: ' . round($max_size / (1024 * 1024), 1) . 'MB, Yüklenen: ' . round($file_size / (1024 * 1024), 1) . 'MB');
        }
        
        // Dosya boyutu 0 kontrolü
        if ($file_size == 0) {
            throw new Exception('Dosya boyutu 0 bytes. Dosya boş olabilir.');
        }
        
        // Benzersiz dosya adı oluştur
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . '/' . $filename;
        
        // Dosyayı taşı
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Dosyanın gerçekten oluştuğunu kontrol et
            if (!file_exists($file_path)) {
                throw new Exception('Dosya oluşturulamadı - file_exists() false döndü');
            }
            
            // Dosya boyutunu kontrol et
            $actual_size = filesize($file_path);
            if ($actual_size != $file_size) {
                throw new Exception('Dosya boyutu uyuşmuyor. Beklenen: ' . $file_size . ', Gerçek: ' . $actual_size);
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
                'target_dir_perms' => substr(sprintf('%o', fileperms($upload_dir)), -4)
            ];
            
            throw new Exception('move_uploaded_file() başarısız. Detaylar: ' . json_encode($error_info));
        }
    } catch (Exception $e) {
        error_log("Hosting file upload error: " . $e->getMessage());
        $_SESSION['error'] = 'Dosya yükleme hatası: ' . $e->getMessage();
        return '';
    }
}

function add_event_hosting($db, $post) {
    try {
        // Dosya yükleme işlemleri
        $image_path = '';
        $video_path = '';
        $upload_errors = [];
        
        // Görsel yükleme
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = handle_file_upload_hosting($_FILES['event_image'], 'images/events/', ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024); // 5MB
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
        
        // Video yükleme
        if (isset($_FILES['event_video']) && $_FILES['event_video']['error'] === UPLOAD_ERR_OK) {
            $video_path = handle_file_upload_hosting($_FILES['event_video'], 'videos/events/', ['mp4', 'avi', 'mov', 'wmv'], 50 * 1024 * 1024); // 50MB
            if (empty($video_path)) {
                $upload_errors[] = 'Video yüklenemedi';
            }
        } elseif (isset($_FILES['event_video']) && $_FILES['event_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Video dosya boyutu çok büyük (upload_max_filesize limiti)',
                UPLOAD_ERR_FORM_SIZE => 'Video dosya boyutu çok büyük (MAX_FILE_SIZE limiti)',
                UPLOAD_ERR_PARTIAL => 'Video dosya sadece kısmen yüklendi',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör eksik',
                UPLOAD_ERR_CANT_WRITE => 'Video dosya yazılamadı',
                UPLOAD_ERR_EXTENSION => 'Video dosya yükleme bir uzantı tarafından durduruldu'
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
?>
