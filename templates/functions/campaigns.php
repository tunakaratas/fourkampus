<?php
/**
 * Campaigns Module - Lazy Loaded
 */

function get_campaigns($db) {
    // Önce tabloyu garantili oluştur
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            offer_text TEXT NOT NULL,
            partner_name TEXT,
            discount_percentage INTEGER,
            image_path TEXT,
            start_date TEXT,
            end_date TEXT,
            campaign_code TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Mevcut tabloda campaign_code kolonu yoksa ekle
        $tableInfo = $db->query("PRAGMA table_info(campaigns)");
        $columns = [];
        while ($row = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
            $columns[$row['name']] = true;
        }
        if (!isset($columns['campaign_code'])) {
            $db->exec("ALTER TABLE campaigns ADD COLUMN campaign_code TEXT");
        }
    } catch (Exception $e) {
        tpl_error_log("Campaigns table creation error in get_campaigns: " . $e->getMessage());
    }
    
    // Şimdi sorguyu çalıştır
    try {
        $stmt = $db->prepare("SELECT * FROM campaigns WHERE club_id = ? ORDER BY created_at DESC");
        if ($stmt === false) {
            return [];
        }
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result === false) {
            return [];
        }
        
        $campaigns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $campaigns[] = $row;
        }
        return $campaigns;
    } catch (Exception $e) {
        tpl_error_log("Get campaigns error: " . $e->getMessage());
        return [];
    }
}


function get_campaign_by_id($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ? AND club_id = ?");
        if ($stmt === false) {
            return null;
        }
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) {
            return null;
        }
        return $result->fetchArray(SQLITE3_ASSOC);
    } catch (Exception $e) {
        tpl_error_log("Get campaign by id error: " . $e->getMessage());
        return null;
    }
}


function add_campaign($db, $data) {
    // Paket limit kontrolü - Kampanya için Professional paketi gerekli
    if (!function_exists('require_subscription_feature')) {
        require_once __DIR__ . '/../../lib/general/subscription_guard.php';
    }
    
    // Mevcut aktif kampanya sayısını hesapla
    $currentCount = null;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM campaigns WHERE club_id = ? AND is_active = 1");
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $currentCount = (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        $currentCount = 0;
    }
    
    if (!require_subscription_feature('max_campaigns', null, $currentCount + 1)) {
        // Sayfa gösterildi ve çıkış yapıldı
        return;
    }
    
    // Tabloyu garantili oluştur
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            offer_text TEXT NOT NULL,
            partner_name TEXT,
            discount_percentage INTEGER,
            image_path TEXT,
            start_date TEXT,
            end_date TEXT,
            campaign_code TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Mevcut tabloda eksik kolonları ekle
        $tableInfo = $db->query("PRAGMA table_info(campaigns)");
        $columns = [];
        while ($row = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
            $columns[$row['name']] = true;
        }
        if (!isset($columns['campaign_code'])) {
            $db->exec("ALTER TABLE campaigns ADD COLUMN campaign_code TEXT");
        }
        if (!isset($columns['requires_membership'])) {
            $db->exec("ALTER TABLE campaigns ADD COLUMN requires_membership INTEGER DEFAULT 0");
        }
        if (!isset($columns['requirements'])) {
            $db->exec("ALTER TABLE campaigns ADD COLUMN requirements TEXT");
        }
    } catch (Exception $e) {
        tpl_error_log("Campaigns table creation error in add_campaign: " . $e->getMessage());
    }
    
    // Görsel yükleme
    $image_path = null;
    if (isset($_FILES['campaign_image']) && $_FILES['campaign_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = community_path('assets/images/campaigns');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['campaign_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Güvenlik: MIME type kontrolü
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['campaign_image']['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (in_array($file_extension, $allowed_extensions) && in_array($mime_type, $allowed_mimes)) {
            // Güvenlik: Resim dosyası gerçekten geçerli bir resim mi kontrol et
            $image_info = @getimagesize($_FILES['campaign_image']['tmp_name']);
            if ($image_info !== false) {
                $file_name = 'campaign_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['campaign_image']['tmp_name'], $file_path)) {
                    $image_path = 'assets/images/campaigns/' . $file_name;
                }
            }
        }
    }
    
    // Requirements JSON'a çevir
    $requirements_json = null;
    // Önce requirements_json'dan kontrol et (form submit'ten geliyor)
    if (!empty($data['requirements_json'])) {
        $requirements_json = $data['requirements_json'];
    } elseif (!empty($data['requirements']) && is_array($data['requirements'])) {
        $requirements = [];
        foreach ($data['requirements'] as $req) {
            if (!empty($req['type']) && !empty($req['description'])) {
                $requirements[] = [
                    'id' => $req['id'] ?? uniqid(),
                    'type' => $req['type'],
                    'description' => $req['description']
                ];
            }
        }
        if (!empty($requirements)) {
            $requirements_json = json_encode($requirements, JSON_UNESCAPED_UNICODE);
        }
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO campaigns (club_id, title, description, offer_text, partner_name, discount_percentage, image_path, start_date, end_date, campaign_code, is_active, requires_membership, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $_SESSION['error'] = 'Kampanya eklenirken hata oluştu!';
            header("Location: index.php?view=campaigns");
            exit;
        }
        $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
        $stmt->bindValue(2, $data['title'], SQLITE3_TEXT);
        $stmt->bindValue(3, $data['description'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(4, $data['offer_text'], SQLITE3_TEXT);
        $stmt->bindValue(5, $data['partner_name'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(6, !empty($data['discount_percentage']) ? (int)$data['discount_percentage'] : null, SQLITE3_INTEGER);
        $stmt->bindValue(7, $image_path, SQLITE3_TEXT);
        $stmt->bindValue(8, $data['start_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(9, $data['end_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(10, !empty($data['campaign_code']) ? trim($data['campaign_code']) : null, SQLITE3_TEXT);
        $stmt->bindValue(11, isset($data['is_active']) ? (int)$data['is_active'] : 1, SQLITE3_INTEGER);
        $stmt->bindValue(12, isset($data['requires_membership']) ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(13, $requirements_json, SQLITE3_TEXT);
        $stmt->execute();
        
        $_SESSION['message'] = 'Kampanya başarıyla eklendi!';
        header("Location: index.php?view=campaigns");
        exit;
    } catch (Exception $e) {
        tpl_error_log("Add campaign error: " . $e->getMessage());
        $_SESSION['error'] = 'Kampanya eklenirken hata oluştu: ' . $e->getMessage();
        header("Location: index.php?view=campaigns");
        exit;
    }
}


function update_campaign($db, $data) {
    $id = (int)$data['id'];
    
    // Mevcut kampanyayı al
    $current_campaign = get_campaign_by_id($db, $id);
    if (!$current_campaign) {
        $_SESSION['error'] = 'Kampanya bulunamadı!';
        header("Location: index.php?view=campaigns");
        exit;
    }
    
    // Görsel yükleme (yeni görsel yüklenmişse)
    $image_path = $current_campaign['image_path'];
    if (isset($_FILES['campaign_image']) && $_FILES['campaign_image']['error'] === UPLOAD_ERR_OK) {
        // Eski görseli sil
        if ($image_path && file_exists(community_path($image_path))) {
            @unlink(community_path($image_path));
        }
        
        $upload_dir = community_path('assets/images/campaigns');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['campaign_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Güvenlik: MIME type kontrolü
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['campaign_image']['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (in_array($file_extension, $allowed_extensions) && in_array($mime_type, $allowed_mimes)) {
            // Güvenlik: Resim dosyası gerçekten geçerli bir resim mi kontrol et
            $image_info = @getimagesize($_FILES['campaign_image']['tmp_name']);
            if ($image_info !== false) {
                $file_name = 'campaign_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['campaign_image']['tmp_name'], $file_path)) {
                    $image_path = 'assets/images/campaigns/' . $file_name;
                }
            }
        }
    }
    
    // Requirements JSON'a çevir
    $requirements_json = null;
    // Önce requirements_json'dan kontrol et (form submit'ten geliyor)
    if (!empty($data['requirements_json'])) {
        $requirements_json = $data['requirements_json'];
    } elseif (!empty($data['requirements']) && is_array($data['requirements'])) {
        $requirements = [];
        foreach ($data['requirements'] as $req) {
            if (!empty($req['type']) && !empty($req['description'])) {
                $requirements[] = [
                    'id' => $req['id'] ?? uniqid(),
                    'type' => $req['type'],
                    'description' => $req['description']
                ];
            }
        }
        if (!empty($requirements)) {
            $requirements_json = json_encode($requirements, JSON_UNESCAPED_UNICODE);
        }
    }
    
    try {
        $stmt = $db->prepare("UPDATE campaigns SET title = ?, description = ?, offer_text = ?, partner_name = ?, discount_percentage = ?, image_path = ?, start_date = ?, end_date = ?, campaign_code = ?, is_active = ?, requires_membership = ?, requirements = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND club_id = ?");
        if ($stmt === false) {
            $_SESSION['error'] = 'Kampanya güncellenirken hata oluştu!';
            header("Location: index.php?view=campaigns");
            exit;
        }
        $stmt->bindValue(1, $data['title'], SQLITE3_TEXT);
        $stmt->bindValue(2, $data['description'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(3, $data['offer_text'], SQLITE3_TEXT);
        $stmt->bindValue(4, $data['partner_name'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(5, !empty($data['discount_percentage']) ? (int)$data['discount_percentage'] : null, SQLITE3_INTEGER);
        $stmt->bindValue(6, $image_path, SQLITE3_TEXT);
        $stmt->bindValue(7, $data['start_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(8, $data['end_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(9, !empty($data['campaign_code']) ? trim($data['campaign_code']) : null, SQLITE3_TEXT);
        $stmt->bindValue(10, isset($data['is_active']) ? (int)$data['is_active'] : 1, SQLITE3_INTEGER);
        $stmt->bindValue(11, isset($data['requires_membership']) ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(12, $requirements_json, SQLITE3_TEXT);
        $stmt->bindValue(13, $id, SQLITE3_INTEGER);
        $stmt->bindValue(14, CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        
        $_SESSION['message'] = 'Kampanya başarıyla güncellendi!';
        header("Location: index.php?view=campaigns");
        exit;
    } catch (Exception $e) {
        tpl_error_log("Update campaign error: " . $e->getMessage());
        $_SESSION['error'] = 'Kampanya güncellenirken hata oluştu: ' . $e->getMessage();
        header("Location: index.php?view=campaigns");
        exit;
    }
}


function delete_campaign($db, $id) {
    try {
        // Kampanyayı al ve görseli sil
        $campaign = get_campaign_by_id($db, $id);
        if ($campaign && $campaign['image_path'] && file_exists(community_path($campaign['image_path']))) {
            @unlink(community_path($campaign['image_path']));
        }
        
        $stmt = $db->prepare("DELETE FROM campaigns WHERE id = ? AND club_id = ?");
        if ($stmt === false) {
            $_SESSION['error'] = 'Kampanya silinirken hata oluştu!';
            header("Location: index.php?view=campaigns");
            exit;
        }
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        
        $_SESSION['message'] = 'Kampanya başarıyla silindi!';
        header("Location: index.php?view=campaigns");
        exit;
    } catch (Exception $e) {
        tpl_error_log("Delete campaign error: " . $e->getMessage());
        $_SESSION['error'] = 'Kampanya silinirken hata oluştu: ' . $e->getMessage();
        header("Location: index.php?view=campaigns");
        exit;
    }
}


function toggle_campaign_status($db, $id) {
    try {
        $stmt = $db->prepare("UPDATE campaigns SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND club_id = ?");
        if ($stmt === false) {
            $_SESSION['error'] = 'Kampanya durumu güncellenirken hata oluştu!';
            header("Location: index.php?view=campaigns");
            exit;
        }
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->bindValue(2, CLUB_ID, SQLITE3_INTEGER);
        $stmt->execute();
        
        $_SESSION['message'] = 'Kampanya durumu güncellendi!';
        header("Location: index.php?view=campaigns");
        exit;
    } catch (Exception $e) {
        tpl_error_log("Toggle campaign status error: " . $e->getMessage());
        $_SESSION['error'] = 'Kampanya durumu güncellenirken hata oluştu: ' . $e->getMessage();
        header("Location: index.php?view=campaigns");
        exit;
    }
}

// --- TOPLULUK LİSTELEME FONKSİYONU ---


