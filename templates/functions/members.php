<?php

if (!function_exists('tpl_validate_string')) {
    require_once __DIR__ . '/validation.php';
}
/**
 * Members Module - Lazy Loaded
 * Üye yönetimi ile ilgili tüm fonksiyonlar
 */

// Import namespace'leri
use UniPanel\Core\Database;
use UniPanel\Core\ErrorHandler;
use UniPanel\Models\Member;

function get_members() {
    $cache = get_cache();
    
    // Cache key'ini topluluk bazlı yap (DB_PATH'den hash oluştur)
    $cache_key = 'members_list_' . md5(DB_PATH);
    
    // Try to get from cache (15 minutes TTL)
    return $cache->remember($cache_key, 900, function() {
    try {
        $database = Database::getInstance(DB_PATH);
        $memberModel = new Member($database->getDb(), CLUB_ID);
        return $memberModel->getAll();
    } catch (\Exception $e) {
        ErrorHandler::error("Üyeler getirilemedi: " . $e->getMessage(), 500);
        return [];
    }
    });
}


function get_membership_requests($status = 'pending') {
    $db = get_db();
    ensure_membership_requests_table($db);

    $sql = "SELECT * FROM membership_requests WHERE club_id = :club_id";
    if ($status && $status !== 'all') {
        $sql .= " AND status = :status";
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
    if ($status && $status !== 'all') {
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    }

    $result = $stmt->execute();
    $requests = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $requests[] = $row;
    }
    return $requests;
}


function get_board_members() {
    $cache = get_cache();
    
    // Cache key'ini topluluk bazlı yap (DB_PATH'den hash oluştur)
    $cache_key = 'board_members_list_' . md5(DB_PATH);
    
    // Try to get from cache (15 minutes TTL)
    return $cache->remember($cache_key, 900, function() {
        $db = get_db();
        $board = [];
        
        // Önce board_members tablosunun var olup olmadığını kontrol et
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='board_members'");
        if (!$table_check || !$table_check->fetchArray()) {
            return $board;
        }
        
        // Güvenli prepared statement kullan
        $stmt = @$db->prepare("SELECT * FROM board_members WHERE club_id = :club_id ORDER BY id ASC");
        if (!$stmt) {
            return $board;
        }
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $query = $stmt->execute();
        if (!$query) {
            return $board;
        }
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $board[] = $row;
        }
        return $board;
    });
}


function get_sms_member_contacts() {
    // Cache'i tamamen devre dışı bırak - her zaman güncel veriyi göster
    // Üye eklendiğinde/güncellendiğinde/silindiğinde cache temizleniyor ama yine de direkt çağır
    return get_sms_member_contacts_direct();
}

function get_sms_member_contacts_direct() {
    try {
        $db = get_db();
        $contacts = [];
        
        // Önce members tablosunun var olup olmadığını kontrol et
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        if (!$table_check || !$table_check->fetchArray()) {
            error_log("get_sms_member_contacts_direct: members tablosu bulunamadı");
            return $contacts;
        }
        
        // Telefon numarası olan tüm üyeleri getir (boş string kontrolü de eklendi)
        $stmt = @$db->prepare("SELECT id, full_name, phone_number FROM members WHERE club_id = ? AND phone_number IS NOT NULL AND phone_number != '' AND TRIM(phone_number) != '' ORDER BY full_name ASC");
        if (!$stmt) {
            error_log("get_sms_member_contacts_direct: SQL hazırlanamadı - " . $db->lastErrorMsg());
            return $contacts;
        }
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $query = $stmt->execute();
        if (!$query) {
            error_log("get_sms_member_contacts_direct: SQL çalıştırılamadı - " . $db->lastErrorMsg());
            return $contacts;
        }
        
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            // Telefon numarasını temizle ve kontrol et
            $phone = trim((string)($row['phone_number'] ?? ''));
            if (!empty($phone)) {
                $contacts[] = [
                    'id' => $row['id'] ?? null,
                    'full_name' => trim((string)($row['full_name'] ?? 'Adsız Üye')),
                    'phone_number' => $phone
                ];
            }
        }
        
        // Result'ı kapat (memory temizliği)
        $query->finalize();
        
        error_log("get_sms_member_contacts_direct: " . count($contacts) . " üye bulundu (club_id: " . CLUB_ID . ")");
        
        return $contacts;
    } catch (Exception $e) {
        error_log("get_sms_member_contacts_direct error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        return [];
    } catch (Error $e) {
        error_log("get_sms_member_contacts_direct fatal error: " . $e->getMessage());
        return [];
    }
}


function get_email_member_contacts() {
    // Cache'i tamamen devre dışı bırak - her zaman güncel veriyi göster
    // Üye eklendiğinde/güncellendiğinde/silindiğinde cache temizleniyor ama yine de direkt çağır
    return get_email_member_contacts_direct();
}

function get_email_member_contacts_direct() {
    try {
        $db = get_db();
        $contacts = [];
        
        // Önce members tablosunun var olup olmadığını kontrol et
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        if (!$table_check || !$table_check->fetchArray()) {
            error_log("get_email_member_contacts_direct: members tablosu bulunamadı");
            return $contacts;
        }
        
        // Email adresi olan tüm üyeleri getir (boş string kontrolü de eklendi)
        $stmt = @$db->prepare("SELECT id, full_name, email FROM members WHERE club_id = ? AND email IS NOT NULL AND email != '' AND TRIM(email) != '' ORDER BY full_name ASC");
        if (!$stmt) {
            error_log("get_email_member_contacts_direct: SQL hazırlanamadı - " . $db->lastErrorMsg());
            return $contacts;
        }
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $query = $stmt->execute();
        if (!$query) {
            error_log("get_email_member_contacts_direct: SQL çalıştırılamadı - " . $db->lastErrorMsg());
            return $contacts;
        }
        
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            // Email adresini temizle ve kontrol et
            $email = trim((string)($row['email'] ?? ''));
            if (!empty($email)) {
                $contacts[] = [
                    'id' => $row['id'] ?? null,
                    'full_name' => trim((string)($row['full_name'] ?? 'Adsız Üye')),
                    'email' => $email
                ];
            }
        }
        
        // Result'ı kapat (memory temizliği)
        $query->finalize();
        
        error_log("get_email_member_contacts_direct: " . count($contacts) . " üye bulundu (club_id: " . CLUB_ID . ")");
        
        return $contacts;
    } catch (Exception $e) {
        error_log("get_email_member_contacts_direct error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        return [];
    } catch (Error $e) {
        error_log("get_email_member_contacts_direct fatal error: " . $e->getMessage());
        return [];
    }
}

// --- CRUD OPERASYONLARI (POST İŞLEYİCİLERİ) ---


function add_member($db, $post) {
    try {
        // Paket limit kontrolü
        if (!function_exists('require_subscription_feature')) {
            require_once __DIR__ . '/../../lib/general/subscription_guard.php';
        }
        
        // Mevcut üye sayısını hesapla
        $currentCount = null;
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
        
        if (!require_subscription_feature('max_members', null, $currentCount + 1)) {
            // Sayfa gösterildi ve çıkış yapıldı
            return;
        }
        
        // Members tablosunu oluştur (yoksa)
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            student_id TEXT,
            phone_number TEXT,
            registration_date TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        try {
            $first_name = tpl_validate_string($post['first_name'] ?? '', [
                'field' => 'Ad',
                'min' => 2,
                'max' => 100,
            ]);
            $last_name = tpl_validate_string($post['last_name'] ?? '', [
                'field' => 'Soyad',
                'min' => 2,
                'max' => 100,
            ]);
            $student_id = tpl_validate_string($post['student_id'] ?? '', [
                'field' => 'Öğrenci numarası',
                'min' => 3,
                'max' => 30,
                'pattern' => '/^[0-9]+$/',
                'pattern_message' => 'Öğrenci numarası sadece rakamlardan oluşmalıdır!',
            ]);
            $raw_phone = preg_replace('/\s+/', '', (string)($post['phone_number'] ?? ''));
            $phone_clean = tpl_validate_phone($raw_phone, [
                'field' => 'Telefon numarası',
                'pattern' => '/^5[0-9]{9}$/',
                'pattern_message' => "Telefon numarası 5 ile başlayan 10 haneli olmalıdır! (Örn: 5551234567)",
            ]);
            $email = tpl_validate_email($post['email'] ?? '', [
                'field' => 'E-posta',
                'allow_empty' => true,
            ]);
        } catch (TplValidationException $validationException) {
            $_SESSION['error'] = $validationException->getMessage();
            return;
        }
        
        $full_name = trim($first_name . ' ' . $last_name);
        
        if (empty($email) && !empty($student_id)) {
            $email = $student_id . '@ogr.bandirma.edu.tr';
        }
        
        // DUPLICATE KONTROLÜ - Gerçek hayat senaryosu: Aynı öğrenci numarası veya email ile kayıt var mı?
        $duplicate_check = @$db->prepare("SELECT id, full_name FROM members WHERE club_id = :club_id AND (student_id = :student_id OR (email IS NOT NULL AND email != '' AND LOWER(email) = LOWER(:email)) OR phone_number = :phone_number) LIMIT 1");
        if ($duplicate_check) {
            $duplicate_check->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $duplicate_check->bindValue(':student_id', $student_id, SQLITE3_TEXT);
            $duplicate_check->bindValue(':email', $email, SQLITE3_TEXT);
            $duplicate_check->bindValue(':phone_number', $phone_clean, SQLITE3_TEXT);
            $duplicate_result = $duplicate_check->execute();
            if ($duplicate_result) {
                $duplicate = $duplicate_result->fetchArray(SQLITE3_ASSOC);
                if ($duplicate) {
                    $_SESSION['error'] = "Bu üye zaten kayıtlı! (Ad: " . ($duplicate['full_name'] ?? 'Bilinmiyor') . ", ID: " . $duplicate['id'] . ")";
                    return;
                }
            }
        }
        
        $stmt = @$db->prepare("INSERT INTO members (club_id, full_name, email, student_id, phone_number, registration_date) VALUES (:club_id, :full_name, :email, :student_id, :phone_number, :registration_date)");
        if (!$stmt) {
            throw new Exception('Members tablosu sorgusu hazırlanamadı.');
        }
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':student_id', $student_id, SQLITE3_TEXT);
        $stmt->bindValue(':phone_number', $phone_clean, SQLITE3_TEXT);
        $stmt->bindValue(':registration_date', date('Y-m-d'), SQLITE3_TEXT);
        
        $execute_result = $stmt->execute();
        if (!$execute_result) {
            $error_msg = $db->lastErrorMsg();
            // Duplicate key hatası kontrolü
            if (strpos($error_msg, 'UNIQUE') !== false || strpos($error_msg, 'duplicate') !== false) {
                $_SESSION['error'] = "Bu üye zaten kayıtlı! (Veritabanı hatası)";
                return;
            }
            throw new Exception('Üye eklenirken veritabanı hatası: ' . $error_msg);
        }
        
        $member_id = $db->lastInsertRowID();
        if (!$member_id) {
            throw new Exception('Üye ID alınamadı.');
        }
        
        // Log kaydet
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'member_add',
                'action_description' => 'Yeni üye eklendi: ' . ($full_name ?: 'N/A'),
                'additional_data' => [
                    'member_id' => $member_id,
                    'full_name' => $full_name,
                    'email' => $email,
                    'student_id' => $student_id,
                    'phone_number' => $phone_clean
                ]
            ]);
        }
        
        // Cache'i temizle
        clear_entity_cache('members');
        
        // SMS ve Email contacts cache'lerini temizle
        try {
            $cache = get_cache();
            if ($cache) {
                $sms_cache_key = 'sms_contacts_' . md5(DB_PATH);
                $email_cache_key = 'email_contacts_' . md5(DB_PATH);
                $cache->delete($sms_cache_key);
                $cache->delete($email_cache_key);
                tpl_error_log('SMS and Email contacts cache cleared after adding member');
            }
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
        }
        
        $_SESSION['message'] = "Üye başarıyla eklendi. 👤";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye eklenirken hata: " . $e->getMessage();
    }
}


function update_member($db, $post) {
    try {
        // Önce members tablosunun var olup olmadığını kontrol et
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        if (!$table_check || !$table_check->fetchArray()) {
            throw new Exception('Members tablosu bulunamadı.');
        }
        
        // Hem 'id' hem de 'member_id' destekle
        $member_id_raw = $post['member_id'] ?? $post['id'] ?? null;
        try {
            $member_id = tpl_validate_int($member_id_raw, [
                'field' => 'Üye ID',
                'min' => 1,
            ]);
        } catch (TplValidationException $validationException) {
            $_SESSION['error'] = $validationException->getMessage();
            return;
        }
        
        // Önce mevcut üye bilgilerini al
        $current_stmt = @$db->prepare("SELECT full_name, email, student_id, phone_number FROM members WHERE id = :id AND club_id = :club_id");
        if (!$current_stmt) {
            throw new Exception('Üye sorgusu hazırlanamadı.');
        }
        $current_stmt->bindValue(':id', $member_id, SQLITE3_INTEGER);
        $current_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $current_result = $current_stmt->execute();
        if (!$current_result) {
            throw new Exception('Üye sorgusu çalıştırılamadı.');
        }
        $current = $current_result->fetchArray(SQLITE3_ASSOC);
        
        if (!$current) {
            throw new Exception('Üye bulunamadı.');
        }
        
        // POST'tan gelen değerleri al, boşsa mevcut değeri koru
        // Eğer POST'ta alan varsa ve doluysa kullan, yoksa mevcut değeri koru
        $full_name = isset($post['full_name']) && trim($post['full_name']) !== '' ? trim($post['full_name']) : ($current['full_name'] ?? '');
        $email = isset($post['email']) && trim($post['email']) !== '' ? trim($post['email']) : ($current['email'] ?? '');
        $student_id = isset($post['student_id']) && trim($post['student_id']) !== '' ? trim($post['student_id']) : ($current['student_id'] ?? '');
        $phone_number = isset($post['phone_number']) && trim($post['phone_number']) !== '' ? trim($post['phone_number']) : ($current['phone_number'] ?? '');
        $phone_compact = preg_replace('/\s+/', '', (string)$phone_number);
        
        try {
            $full_name = tpl_validate_string($full_name, [
                'field' => 'Ad Soyad',
                'min' => 3,
                'max' => 150,
            ]);
            $email = tpl_validate_email($email, [
                'field' => 'E-posta',
                'allow_empty' => true,
            ]);
            $student_id = tpl_validate_string($student_id, [
                'field' => 'Öğrenci numarası',
                'allow_empty' => true,
                'min' => 3,
                'max' => 30,
                'pattern' => '/^[0-9]+$/',
                'pattern_message' => 'Öğrenci numarası sadece rakamlardan oluşmalıdır!',
            ]);
            $phone_number = tpl_validate_phone($phone_compact, [
                'field' => 'Telefon numarası',
                'allow_empty' => true,
                'pattern' => '/^5[0-9]{9}$/',
                'pattern_message' => 'Telefon numarası 5 ile başlayan 10 haneli olmalıdır!',
            ]);
        } catch (TplValidationException $validationException) {
            $_SESSION['error'] = $validationException->getMessage();
            return;
        }
        
        // DUPLICATE KONTROLÜ - Güncelleme sırasında başka bir üye ile çakışma var mı?
        $duplicate_check = @$db->prepare("SELECT id, full_name FROM members WHERE club_id = :club_id AND id != :member_id AND (student_id = :student_id OR (email IS NOT NULL AND email != '' AND LOWER(email) = LOWER(:email)) OR phone_number = :phone_number) LIMIT 1");
        if ($duplicate_check) {
            $duplicate_check->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $duplicate_check->bindValue(':member_id', $member_id, SQLITE3_INTEGER);
            $duplicate_check->bindValue(':student_id', $student_id, SQLITE3_TEXT);
            $duplicate_check->bindValue(':email', $email, SQLITE3_TEXT);
            $duplicate_check->bindValue(':phone_number', $phone_number, SQLITE3_TEXT);
            $duplicate_result = $duplicate_check->execute();
            if ($duplicate_result) {
                $duplicate = $duplicate_result->fetchArray(SQLITE3_ASSOC);
                if ($duplicate) {
                    throw new Exception('Bu bilgiler başka bir üyede kullanılıyor! (Ad: ' . ($duplicate['full_name'] ?? 'Bilinmiyor') . ', ID: ' . $duplicate['id'] . ')');
                }
            }
        }
        
        // Debug için log (sadece development'ta)
        if (defined('DEBUG') && constant('DEBUG')) {
            tpl_error_log("update_member - ID: $member_id");
            tpl_error_log("update_member - POST full_name: " . ($post['full_name'] ?? 'NULL'));
            tpl_error_log("update_member - Current full_name: " . ($current['full_name'] ?? 'NULL'));
            tpl_error_log("update_member - Final full_name: $full_name");
        }
        
        $stmt = @$db->prepare("UPDATE members SET full_name = :full_name, email = :email, student_id = :student_id, phone_number = :phone_number WHERE id = :id AND club_id = :club_id");
        if (!$stmt) {
            throw new Exception('Update sorgusu hazırlanamadı.');
        }
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':student_id', $student_id, SQLITE3_TEXT);
        $stmt->bindValue(':phone_number', $phone_number, SQLITE3_TEXT);
        $stmt->bindValue(':id', $member_id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception('Güncelleme sorgusu başarısız oldu: ' . $db->lastErrorMsg());
        }
        
        // Güncellemenin başarılı olduğunu doğrula
        $check_stmt = @$db->prepare("SELECT full_name, email, student_id, phone_number FROM members WHERE id = :id AND club_id = :club_id");
        if ($check_stmt) {
            $check_stmt->bindValue(':id', $member_id, SQLITE3_INTEGER);
            $check_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $check_result = $check_stmt->execute();
            if ($check_result) {
                $updated = $check_result->fetchArray(SQLITE3_ASSOC);
            } else {
                $updated = null;
            }
        } else {
            $updated = null;
        }
        
        if (!$updated) {
            throw new Exception('Güncelleme sonrası üye bulunamadı.');
        }
        
        // Log kaydet
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'member_update',
                'action_description' => 'Üye bilgileri güncellendi: ' . ($full_name ?? 'ID: ' . $member_id),
                'additional_data' => [
                    'member_id' => $member_id,
                    'full_name' => $full_name,
                    'email' => $email,
                    'student_id' => $student_id,
                    'phone_number' => $phone_number
                ]
            ]);
        }
        
        // Cache'i temizle
        clear_entity_cache('members');
        
        // SMS ve Email contacts cache'lerini temizle
        try {
            $cache = get_cache();
            if ($cache) {
                $sms_cache_key = 'sms_contacts_' . md5(DB_PATH);
                $email_cache_key = 'email_contacts_' . md5(DB_PATH);
                $cache->delete($sms_cache_key);
                $cache->delete($email_cache_key);
                tpl_error_log('SMS and Email contacts cache cleared after updating member');
            }
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
        }
        
        $_SESSION['message'] = "Üye bilgileri başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye bilgileri güncellenirken hata: " . $e->getMessage();
    }
}


function delete_member($db, $id) {
    try {
        // Önce members tablosunun var olup olmadığını kontrol et
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='members'");
        if (!$table_check || !$table_check->fetchArray()) {
            throw new Exception('Members tablosu bulunamadı.');
        }
        
        // Önce üye bilgilerini al (log için)
        $member_stmt = @$db->prepare("SELECT full_name, email, student_id FROM members WHERE id = :id AND club_id = :club_id");
        if (!$member_stmt) {
            throw new Exception('Üye sorgusu hazırlanamadı.');
        }
        $member_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $member_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $member_result = $member_stmt->execute();
        $member = null;
        if ($member_result) {
            $member = $member_result->fetchArray(SQLITE3_ASSOC);
        }
        
        // SOFT DELETE - Gerçek hayat senaryosu: Üye silinmeden önce ilişkili verileri kontrol et
        // Etkinlik RSVP kayıtları, email kampanyaları vb. kontrol edilmeli
        $related_data_check = @$db->prepare("SELECT COUNT(*) as count FROM event_rsvp WHERE member_email = :email OR member_phone = :phone");
        if ($related_data_check && $member) {
            $related_data_check->bindValue(':email', $member['email'] ?? '', SQLITE3_TEXT);
            $related_data_check->bindValue(':phone', $member['phone_number'] ?? '', SQLITE3_TEXT);
            $related_result = $related_data_check->execute();
            if ($related_result) {
                $related_row = $related_result->fetchArray(SQLITE3_ASSOC);
                if ($related_row && $related_row['count'] > 0) {
                    // İlişkili veri var, uyarı ver ama silmeye devam et
                    tpl_error_log("Member deletion warning: Member has " . $related_row['count'] . " related RSVP records");
                }
            }
        }
        
        // HARD DELETE - Üyeyi sil
        $stmt = @$db->prepare("DELETE FROM members WHERE id = :id AND club_id = :club_id");
        if (!$stmt) {
            throw new Exception('Delete sorgusu hazırlanamadı.');
        }
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $delete_result = $stmt->execute();
        
        if (!$delete_result) {
            throw new Exception('Üye silinirken veritabanı hatası: ' . $db->lastErrorMsg());
        }
        
        // Silme işleminin başarılı olduğunu doğrula
        $verify_stmt = @$db->prepare("SELECT id FROM members WHERE id = :id AND club_id = :club_id LIMIT 1");
        if ($verify_stmt) {
            $verify_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $verify_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $verify_result = $verify_stmt->execute();
            if ($verify_result) {
                $still_exists = $verify_result->fetchArray(SQLITE3_ASSOC);
                if ($still_exists) {
                    throw new Exception('Üye silinemedi! (Doğrulama hatası)');
                }
            }
        }
        
        // Log kaydet
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'member_delete',
                'action_description' => 'Üye silindi: ' . ($member ? ($member['full_name'] ?? 'ID: ' . $id) : 'ID: ' . $id),
                'additional_data' => [
                    'member_id' => $id,
                    'full_name' => $member['full_name'] ?? null,
                    'email' => $member['email'] ?? null,
                    'student_id' => $member['student_id'] ?? null
                ]
            ]);
        }
        
        // Cache'i temizle
        clear_entity_cache('members');
        
        // SMS ve Email contacts cache'lerini temizle
        try {
            $cache = get_cache();
            if ($cache) {
                $sms_cache_key = 'sms_contacts_' . md5(DB_PATH);
                $email_cache_key = 'email_contacts_' . md5(DB_PATH);
                $cache->delete($sms_cache_key);
                $cache->delete($email_cache_key);
                tpl_error_log('SMS and Email contacts cache cleared after deleting member');
            }
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
        }
        
        $_SESSION['message'] = "Üye başarıyla silindi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üye silinirken hata: " . $e->getMessage();
    }
}


function approve_membership_request($db, $request_id) {
    try {
        ensure_membership_requests_table($db);

        $stmt = $db->prepare("SELECT * FROM membership_requests WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $request = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$request) {
            $_SESSION['error'] = "Üyelik başvurusu bulunamadı.";
            return;
        }

        if ($request['status'] === 'approved') {
            $_SESSION['message'] = "Bu üyelik başvurusu zaten onaylanmış.";
            return;
        }

        // Members tablosunu oluştur (yoksa)
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            student_id TEXT,
            phone_number TEXT,
            registration_date TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Aynı email veya öğrenci numarası ile üye var mı kontrol et
        $check_stmt = @$db->prepare("SELECT id FROM members WHERE club_id = :club_id AND (LOWER(email) = LOWER(:email) OR (student_id != '' AND student_id = :student_id)) LIMIT 1");
        if ($check_stmt) {
            $check_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
            $check_stmt->bindValue(':email', $request['email'] ?? '', SQLITE3_TEXT);
            $check_stmt->bindValue(':student_id', $request['student_id'] ?? '', SQLITE3_TEXT);
            $check_result = $check_stmt->execute();
            if ($check_result) {
                $existing = $check_result->fetchArray(SQLITE3_ASSOC);
                if ($existing) {
                    $_SESSION['error'] = "Bu başvuru için zaten kayıtlı bir üye bulunuyor.";
                    return;
                }
            }
        }

        // Üye kaydını oluştur
        $member_stmt = @$db->prepare("INSERT INTO members (club_id, full_name, email, student_id, phone_number, registration_date) VALUES (:club_id, :full_name, :email, :student_id, :phone_number, :registration_date)");
        if (!$member_stmt) {
            throw new Exception('Members tablosu sorgusu hazırlanamadı.');
        }
        $member_stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $member_stmt->bindValue(':full_name', $request['full_name'] ?? '', SQLITE3_TEXT);
        $member_stmt->bindValue(':email', $request['email'] ?? '', SQLITE3_TEXT);
        $member_stmt->bindValue(':student_id', $request['student_id'] ?? '', SQLITE3_TEXT);
        $member_stmt->bindValue(':phone_number', $request['phone'] ?? '', SQLITE3_TEXT);
        $member_stmt->bindValue(':registration_date', date('Y-m-d'), SQLITE3_TEXT);
        $member_stmt->execute();
        $member_id = $db->lastInsertRowID();

        // Başvuruyu güncelle
        $update_stmt = $db->prepare("UPDATE membership_requests SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update_stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
        $update_stmt->execute();

        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'membership_request_approved',
                'action_description' => 'Üyelik başvurusu onaylandı: ' . ($request['full_name'] ?? 'ID: ' . $request_id),
                'additional_data' => [
                    'request_id' => $request_id,
                    'member_id' => $member_id,
                    'full_name' => $request['full_name'] ?? '',
                    'email' => $request['email'] ?? '',
                    'student_id' => $request['student_id'] ?? ''
                ]
            ]);
        }

        clear_entity_cache('members');
        clear_entity_cache('membership_requests');

        $_SESSION['message'] = "Üyelik başvurusu onaylandı ve üye kaydı oluşturuldu. ✅";
    } catch (Exception $e) {
        $_SESSION['error'] = "Üyelik başvurusu onaylanamadı: " . $e->getMessage();
    }
}


function reject_membership_request($db, $request_id, $admin_notes = '') {
    try {
        ensure_membership_requests_table($db);

        $stmt = $db->prepare("SELECT * FROM membership_requests WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $request = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$request) {
            $_SESSION['error'] = "Üyelik başvurusu bulunamadı.";
            return;
        }

        if ($request['status'] === 'rejected') {
            $_SESSION['message'] = "Bu üyelik başvurusu zaten reddedilmiş.";
            return;
        }

        $update_stmt = $db->prepare("UPDATE membership_requests SET status = 'rejected', admin_notes = :admin_notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update_stmt->bindValue(':admin_notes', $admin_notes, SQLITE3_TEXT);
        $update_stmt->bindValue(':id', $request_id, SQLITE3_INTEGER);
        $update_stmt->execute();

        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'membership_request_rejected',
                'action_description' => 'Üyelik başvurusu reddedildi: ' . ($request['full_name'] ?? 'ID: ' . $request_id),
                'additional_data' => [
                    'request_id' => $request_id,
                    'full_name' => $request['full_name'] ?? '',
                    'email' => $request['email'] ?? '',
                    'student_id' => $request['student_id'] ?? '',
                    'admin_notes' => $admin_notes
                ]
            ]);
        }

        clear_entity_cache('membership_requests');

        $_SESSION['message'] = "Üyelik başvurusu reddedildi. ❌";
    } catch (Exception $e) {
        // Güvenlik: Sensitive bilgi sızıntısını önle
        tpl_error_log("Reject membership request error: " . $e->getMessage());
        $_SESSION['error'] = "Üyelik başvurusu reddedilemedi";
    }
}


function add_board_member($db, $post) {
    try {
        // Paket limit kontrolü
        if (!function_exists('require_subscription_feature')) {
            require_once __DIR__ . '/../../lib/general/subscription_guard.php';
        }
        
        // Mevcut yönetim kurulu üye sayısını hesapla
        $currentCount = null;
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM board_members WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
        
        if (!require_subscription_feature('max_board_members', null, $currentCount + 1)) {
            // Sayfa gösterildi ve çıkış yapıldı
            return;
        }
        
        try {
            $full_name = tpl_validate_string($post['full_name'] ?? '', [
                'field' => 'Ad Soyad',
                'min' => 3,
                'max' => 150,
            ]);
            $role = tpl_validate_string($post['role'] ?? '', [
                'field' => 'Görevi',
                'min' => 2,
                'max' => 120,
            ]);
            $contact_email = tpl_validate_email($post['contact_email'] ?? '', [
                'field' => 'İletişim e-postası',
                'allow_empty' => true,
            ]);
            $phone = tpl_validate_phone(preg_replace('/\s+/', '', (string)($post['phone'] ?? '')), [
                'field' => 'Telefon numarası',
                'allow_empty' => true,
                'pattern' => '/^[0-9+\-]{7,20}$/',
            ]);
        } catch (TplValidationException $validationException) {
            $_SESSION['error'] = $validationException->getMessage();
            return;
        }
        
        $stmt = $db->prepare("INSERT INTO board_members (club_id, full_name, role, contact_email, phone) VALUES (:club_id, :full_name, :role, :contact_email, :phone)");
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->bindValue(':contact_email', $contact_email, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $stmt->execute();
        $board_member_id = $db->lastInsertRowID();
        
        // Log kaydet
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'board_member_add',
                'action_description' => 'Yönetim kurulu üyesi eklendi: ' . ($full_name ?: 'N/A'),
                'additional_data' => [
                    'board_member_id' => $board_member_id,
                    'full_name' => $full_name,
                    'role' => $role,
                    'contact_email' => $contact_email,
                    'phone' => $phone
                ]
            ]);
        }
        
        // Cache'i temizle
        clear_entity_cache('board_members');
        
        $_SESSION['message'] = "Yönetim kurulu üyesi başarıyla eklendi. 🏅";
    } catch (Exception $e) {
        $_SESSION['error'] = "Yönetim kurulu üyesi eklenirken hata: " . $e->getMessage();
    }
}


function update_board_member($db, $post) {
    try {
        try {
            $board_id = tpl_validate_int($post['id'] ?? null, [
                'field' => 'Yönetim kurulu ID',
                'min' => 1,
            ]);
            $full_name = tpl_validate_string($post['full_name'] ?? '', [
                'field' => 'Ad Soyad',
                'min' => 3,
                'max' => 150,
            ]);
            $role = tpl_validate_string($post['role'] ?? '', [
                'field' => 'Görevi',
                'min' => 2,
                'max' => 120,
            ]);
            $contact_email = tpl_validate_email($post['contact_email'] ?? '', [
                'field' => 'İletişim e-postası',
                'allow_empty' => true,
            ]);
            $phone = tpl_validate_phone(preg_replace('/\s+/', '', (string)($post['phone'] ?? '')), [
                'field' => 'Telefon numarası',
                'allow_empty' => true,
                'pattern' => '/^[0-9+\-]{7,20}$/',
            ]);
        } catch (TplValidationException $validationException) {
            $_SESSION['error'] = $validationException->getMessage();
            return;
        }
        
        $stmt = $db->prepare("UPDATE board_members SET full_name = :full_name, role = :role, contact_email = :contact_email, phone = :phone WHERE id = :id AND club_id = :club_id");
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->bindValue(':contact_email', $contact_email, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $stmt->bindValue(':id', $board_id, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Log kaydet
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
            logToSuperAdmin('admin_action', [
                'user_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'action_type' => 'board_member_update',
                'action_description' => 'Yönetim kurulu üyesi güncellendi: ' . ($full_name ?: 'ID: ' . $board_id),
                'additional_data' => [
                    'board_member_id' => $board_id,
                    'full_name' => $full_name,
                    'role' => $role,
                    'contact_email' => $contact_email,
                    'phone' => $phone
                ]
            ]);
        }
        
        // Cache'i temizle
        clear_entity_cache('board_members');
        
        $_SESSION['message'] = "Yönetim kurulu üyesi başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Yönetim kurulu üyesi güncellenirken hata: " . $e->getMessage();
    }
}


