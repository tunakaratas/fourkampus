<?php
/**
 * Hosting PHP Ayarları Kontrol Sayfası
 * Bu dosyayı hosting'e yükleyip çalıştırarak PHP ayarlarını kontrol edin
 */

echo "<h1>PHP Ayarları Kontrolü</h1>";

echo "<h2>Dosya Yükleme Ayarları</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Ayar</th><th>Değer</th><th>Durum</th></tr>";

$upload_settings = [
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'max_file_uploads' => ini_get('max_file_uploads')
];

foreach ($upload_settings as $setting => $value) {
    $status = '';
    if ($setting === 'file_uploads') {
        $status = $value ? '✅ Aktif' : '❌ Pasif';
    } elseif ($setting === 'upload_max_filesize') {
        $bytes = return_bytes($value);
        $status = $bytes >= (50 * 1024 * 1024) ? '✅ Yeterli (50MB+)' : '⚠️ Düşük (' . $value . ')';
    } elseif ($setting === 'post_max_size') {
        $bytes = return_bytes($value);
        $status = $bytes >= (50 * 1024 * 1024) ? '✅ Yeterli (50MB+)' : '⚠️ Düşük (' . $value . ')';
    } elseif ($setting === 'max_execution_time') {
        $status = $value >= 300 ? '✅ Yeterli (5dk+)' : '⚠️ Düşük (' . $value . 'sn)';
    } elseif ($setting === 'memory_limit') {
        $bytes = return_bytes($value);
        $status = $bytes >= (128 * 1024 * 1024) ? '✅ Yeterli (128MB+)' : '⚠️ Düşük (' . $value . ')';
    }
    
    echo "<tr><td>$setting</td><td>$value</td><td>$status</td></tr>";
}

echo "</table>";

echo "<h2>Klasör İzinleri</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Klasör</th><th>Var mı?</th><th>Yazılabilir mi?</th><th>İzinler</th></tr>";

$folders_to_check = [
    'assets',
    'assets/images',
    'assets/images/events',
    'assets/videos',
    'assets/videos/events'
];

foreach ($folders_to_check as $folder) {
    $exists = is_dir($folder);
    $writable = $exists ? is_writable($folder) : false;
    $perms = $exists ? substr(sprintf('%o', fileperms($folder)), -4) : 'N/A';
    
    $exists_status = $exists ? '✅ Var' : '❌ Yok';
    $writable_status = $writable ? '✅ Evet' : '❌ Hayır';
    
    echo "<tr><td>$folder</td><td>$exists_status</td><td>$writable_status</td><td>$perms</td></tr>";
}

echo "</table>";

echo "<h2>Test Dosya Yükleme</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    echo "<h3>Yüklenen Dosya Bilgileri:</h3>";
    echo "<ul>";
    echo "<li>Dosya Adı: " . htmlspecialchars($file['name']) . "</li>";
    echo "<li>Dosya Boyutu: " . number_format($file['size']) . " bytes</li>";
    echo "<li>MIME Type: " . htmlspecialchars($file['type']) . "</li>";
    echo "<li>Hata Kodu: " . $file['error'] . "</li>";
    echo "</ul>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'assets/images/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'test_' . time() . '_' . $file['name'];
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo "<p style='color: green;'>✅ Dosya başarıyla yüklendi: $upload_path</p>";
        } else {
            echo "<p style='color: red;'>❌ Dosya yüklenemedi. move_uploaded_file() başarısız.</p>";
        }
    } else {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu upload_max_filesize limitini aşıyor',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu MAX_FILE_SIZE limitini aşıyor',
            UPLOAD_ERR_PARTIAL => 'Dosya sadece kısmen yüklendi',
            UPLOAD_ERR_NO_FILE => 'Hiç dosya yüklenmedi',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör eksik',
            UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
            UPLOAD_ERR_EXTENSION => 'Dosya yükleme bir uzantı tarafından durduruldu'
        ];
        
        $error_msg = $error_messages[$file['error']] ?? 'Bilinmeyen hata';
        echo "<p style='color: red;'>❌ Dosya yükleme hatası: $error_msg</p>";
    }
}

echo "<form method='POST' enctype='multipart/form-data'>";
echo "<p>Test için bir dosya yükleyin:</p>";
echo "<input type='file' name='test_file' required>";
echo "<br><br>";
echo "<input type='submit' value='Test Et'>";
echo "</form>";

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
?>
