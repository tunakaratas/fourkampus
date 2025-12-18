<?php
/**
 * Test kullanıcısını tüm topluluklara üye yapma scripti
 * Email: 2511505042@ogr.bandirma.edu.tr
 */

require_once __DIR__ . '/../api/connection_pool.php';

$email = '2511505042@ogr.bandirma.edu.tr';
$student_id = '2511505042';
$full_name = 'Test User';

// Tüm toplulukları bul
$communities_dir = __DIR__ . '/../communities/';
$community_folders = glob($communities_dir . '/*', GLOB_ONLYDIR);
$communities = [];

foreach ($community_folders as $folder) {
    $name = basename($folder);
    if (!in_array($name, ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'])) {
        $communities[] = $name;
    }
}

$added_count = 0;
$skipped_count = 0;
$error_count = 0;

echo "🔍 Topluluklar bulundu: " . count($communities) . "\n\n";

foreach ($communities as $community) {
    $db_path = __DIR__ . '/../communities/' . $community . '/unipanel.sqlite';
    
    if (!file_exists($db_path)) {
        echo "⚠️  $community: Veritabanı dosyası bulunamadı\n";
        $error_count++;
        continue;
    }
    
    // Connection pool kullan
    $connResult = ConnectionPool::getConnection($db_path, false);
    if (!$connResult) {
        echo "❌ $community: Veritabanı bağlantısı kurulamadı\n";
        $error_count++;
        continue;
    }
    
    $db = $connResult['db'];
    $poolId = $connResult['pool_id'];
    
    // Members tablosunun yapısını kontrol et
    $table_info = $db->query("PRAGMA table_info(members)");
    $columns = [];
    if ($table_info) {
        while ($row = $table_info->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
    }
    
    // Zaten üye mi kontrol et
    $check = $db->prepare("SELECT id FROM members WHERE club_id = 1 AND (LOWER(email) = LOWER(?) OR (student_id != '' AND student_id = ?)) LIMIT 1");
    if (!$check) {
        echo "❌ $community: Üyelik kontrolü hazırlanamadı - " . $db->lastErrorMsg() . "\n";
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        $error_count++;
        continue;
    }
    
    $check->bindValue(1, $email, SQLITE3_TEXT);
    $check->bindValue(2, $student_id, SQLITE3_TEXT);
    $result = $check->execute();
    
    if ($result && $result->fetchArray()) {
        echo "⏭️  $community: Zaten üye\n";
        $skipped_count++;
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        continue;
    }
    
    // Kolonları dinamik olarak oluştur
    $insert_cols = ['club_id', 'full_name', 'email', 'student_id'];
    $insert_vals = ['1', '?', '?', '?'];
    $bind_values = [$full_name, $email, $student_id];
    
    if (in_array('phone_number', $columns)) {
        $insert_cols[] = 'phone_number';
        $insert_vals[] = '?';
        $bind_values[] = '';
    }
    if (in_array('department', $columns)) {
        $insert_cols[] = 'department';
        $insert_vals[] = '?';
        $bind_values[] = 'Bilgisayar Mühendisliği';
    }
    if (in_array('registration_date', $columns)) {
        $insert_cols[] = 'registration_date';
        $insert_vals[] = '?';
        $bind_values[] = date('Y-m-d');
    }
    
    $sql = 'INSERT INTO members (' . implode(', ', $insert_cols) . ') VALUES (' . implode(', ', $insert_vals) . ')';
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        echo "❌ $community: INSERT hazırlanamadı - " . $db->lastErrorMsg() . "\n";
        ConnectionPool::releaseConnection($db_path, $poolId, false);
        $error_count++;
        continue;
    }
    
    $bind_index = 1;
    foreach ($bind_values as $value) {
        $stmt->bindValue($bind_index++, $value, SQLITE3_TEXT);
    }
    
    if ($stmt->execute()) {
        $added_count++;
        echo "✅ $community: Üye eklendi\n";
    } else {
        echo "❌ $community: Üye eklenemedi - " . $db->lastErrorMsg() . "\n";
        $error_count++;
    }
    
    ConnectionPool::releaseConnection($db_path, $poolId, false);
}

echo "\n📊 Özet:\n";
echo "✅ Eklenen: $added_count\n";
echo "⏭️  Zaten üye: $skipped_count\n";
echo "❌ Hata: $error_count\n";
echo "\n✅ İşlem tamamlandı!\n";

