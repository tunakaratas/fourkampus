<?php

/**
 * Community Verification Helper Functions
 */

if (!function_exists('verification_require_table')) {
    function verification_require_table(): SQLite3
    {
        $db = get_db();
        $db->exec("CREATE TABLE IF NOT EXISTS community_verifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            community_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            document_path TEXT,
            notes TEXT,
            admin_notes TEXT,
            reviewed_by INTEGER,
            reviewer_name TEXT,
            reviewed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_verifications_community ON community_verifications(community_id)");

        return $db;
    }
}

if (!function_exists('verification_storage_dir')) {
    function verification_storage_dir(): string
    {
        $dir = community_path('storage/verification');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('verification_upload_document')) {
    function verification_upload_document(array $file): array
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Belge yüklenemedi.'];
        }

        $allowedMime = ['application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mime, $allowedMime, true)) {
            return ['success' => false, 'error' => 'Sadece PDF belgeleri yükleyebilirsiniz.'];
        }

        $storageDir = verification_storage_dir();
        $filename = 'verification_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $targetPath = rtrim($storageDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Belge kaydedilemedi.'];
        }

        @chmod($targetPath, 0640);

        // Döndürülen yol, topluluk köküne göre relatif olsun
        $relativePath = 'storage/verification/' . $filename;

        return [
            'success' => true,
            'path' => $relativePath
        ];
    }
}

if (!function_exists('verification_get_latest_request')) {
    function verification_get_latest_request(?string $communityId = null): ?array
    {
        $db = verification_require_table();
        $communityId = $communityId ?: (defined('COMMUNITY_ID') ? COMMUNITY_ID : (defined('CLUB_ID') ? CLUB_ID : ''));
        if ($communityId === '') {
            return null;
        }

        $stmt = $db->prepare("SELECT * FROM community_verifications WHERE community_id = :cid ORDER BY created_at DESC LIMIT 1");
        $stmt->bindValue(':cid', $communityId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result ? $result->fetchArray(SQLITE3_ASSOC) ?: null : null;
    }
}

if (!function_exists('verification_get_requests')) {
    function verification_get_requests(?string $communityId = null, int $limit = 10): array
    {
        $db = verification_require_table();
        $communityId = $communityId ?: (defined('COMMUNITY_ID') ? COMMUNITY_ID : (defined('CLUB_ID') ? CLUB_ID : ''));
        if ($communityId === '') {
            return [];
        }

        $stmt = $db->prepare("SELECT * FROM community_verifications WHERE community_id = :cid ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue(':cid', $communityId, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $requests = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $requests[] = $row;
            }
        }
        return $requests;
    }
}

if (!function_exists('verification_get_all_requests')) {
    function verification_get_all_requests(?string $status = null, int $limit = 100): array
    {
        $db = verification_require_table();
        $sql = "SELECT * FROM community_verifications";
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $requests = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $requests[] = $row;
            }
        }
        return $requests;
    }
}

if (!function_exists('verification_is_verified')) {
    function verification_is_verified(?string $communityId = null): bool
    {
        $latest = verification_get_latest_request($communityId);
        return $latest && $latest['status'] === 'approved';
    }
}

if (!function_exists('verification_create_request')) {
    function verification_create_request(string $documentPath, string $notes = ''): bool
    {
        $db = verification_require_table();
        $communityId = defined('COMMUNITY_ID') ? COMMUNITY_ID : (defined('CLUB_ID') ? CLUB_ID : '');
        if ($communityId === '') {
            return false;
        }

        $stmt = $db->prepare("INSERT INTO community_verifications (community_id, status, document_path, notes) VALUES (:cid, 'pending', :path, :notes)");
        $stmt->bindValue(':cid', $communityId, SQLITE3_TEXT);
        $stmt->bindValue(':path', $documentPath, SQLITE3_TEXT);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }
}

if (!function_exists('verification_update_status')) {
    function verification_update_status(int $requestId, string $status, ?int $reviewerId = null, ?string $reviewerName = null, ?string $adminNotes = null): bool
    {
        $allowed = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $db = verification_require_table();
        $stmt = $db->prepare("UPDATE community_verifications 
            SET status = :status,
                admin_notes = :notes,
                reviewed_by = :reviewer_id,
                reviewer_name = :reviewer_name,
                reviewed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':notes', $adminNotes, SQLITE3_TEXT);
        $stmt->bindValue(':reviewer_id', $reviewerId, SQLITE3_INTEGER);
        $stmt->bindValue(':reviewer_name', $reviewerName, SQLITE3_TEXT);
        $stmt->bindValue(':id', $requestId, SQLITE3_INTEGER);

        $updated = $stmt->execute() !== false;
        if ($updated) {
            $request = verification_get_request_by_id($requestId);
            if ($request) {
                // Her durum değişikliğinde SMS gönder
                verification_notify_status_change($request, $status, $adminNotes);
            }
        }
        return $updated;
    }
}

if (!function_exists('verification_get_request_by_id')) {
    function verification_get_request_by_id(int $requestId): ?array
    {
        $db = verification_require_table();
        $stmt = $db->prepare("SELECT * FROM community_verifications WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $requestId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result ? $result->fetchArray(SQLITE3_ASSOC) ?: null : null;
    }
}

if (!function_exists('verification_get_status_meta')) {
    function verification_get_status_meta(string $status): array
    {
        $map = [
            'pending' => [
                'label' => 'Beklemede',
                'color' => 'text-amber-700 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-200',
                'description' => 'Belgeniz inceleniyor. Ortalama yanıt süresi 1-3 iş günü.'
            ],
            'approved' => [
                'label' => 'Onaylandı',
                'color' => 'text-emerald-700 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-200',
                'description' => 'Topluluğunuz doğrulandı. Profilinizde mavi tik görünür.'
            ],
            'rejected' => [
                'label' => 'Reddedildi',
                'color' => 'text-rose-700 bg-rose-50 dark:bg-rose-500/10 dark:text-rose-200',
                'description' => 'Belge doğrulanamadı. Lütfen açıklamayı okuyup yeniden başvurun.'
            ],
        ];

        return $map[$status] ?? [
            'label' => 'Belge yok',
            'color' => 'text-gray-600 bg-gray-50 dark:bg-gray-800/50 dark:text-gray-300',
            'description' => 'Henüz doğrulama talebi oluşturmadınız.'
        ];
    }
}

if (!function_exists('verification_get_status_badge')) {
    function verification_get_status_badge(string $status): string
    {
        $meta = verification_get_status_meta($status);
        return '<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold ' . $meta['color'] . '">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            ' . htmlspecialchars($meta['label']) . '
        </span>';
    }
}

if (!function_exists('verification_notify_status_change')) {
    function verification_notify_status_change(array $request, string $status, ?string $adminNotes = null): void
    {
        if (!function_exists('load_module')) {
            return;
        }
        load_module('communication');

        $phone = verification_resolve_contact_phone();
        if (empty($phone)) {
            tpl_error_log('Verification SMS: Telefon numarası bulunamadı');
            return;
        }

        $clubName = get_club_name();
        $message = '';

        switch ($status) {
            case 'approved':
        $message = "Merhaba! {$clubName} topluluğu doğrulandı. Mavi tik rozetiniz aktif edildi. Tebrikler!";
                break;
            case 'rejected':
                $notesText = !empty($adminNotes) ? " Not: {$adminNotes}" : '';
                $message = "Merhaba! {$clubName} topluluğu doğrulama başvurunuz reddedildi.{$notesText} Lütfen belgelerinizi kontrol edip yeniden başvurun.";
                break;
            case 'pending':
                $message = "Merhaba! {$clubName} topluluğu doğrulama başvurunuz alındı ve inceleme sürecine alındı. Sonuç size bildirilecektir.";
                break;
            default:
                return; // Bilinmeyen durum için SMS gönderme
        }

        if (!empty($message)) {
            $result = send_sms_with_retry_and_failover($phone, $message);
            if ($result['success'] ?? false) {
                tpl_error_log('Verification SMS gönderildi - Durum: ' . $status . ', Telefon: ' . $phone);
            } else {
                tpl_error_log('Verification SMS gönderilemedi - Durum: ' . $status . ', Telefon: ' . $phone . ', Hata: ' . ($result['error'] ?? 'Bilinmeyen hata'));
            }
        }
    }
}

// Geriye dönük uyumluluk için eski fonksiyon
if (!function_exists('verification_notify_approval')) {
    function verification_notify_approval(array $request): void
    {
        verification_notify_status_change($request, 'approved');
    }
}

if (!function_exists('verification_resolve_contact_phone')) {
    function verification_resolve_contact_phone(): ?string
    {
        if (!function_exists('normalize_phone_number')) {
            load_module('communication');
        }

        $candidates = [];
        $db = get_db();
        
        // Öncelik 1: Board members tablosundan başkan telefon numarası
        $table_check = @$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='board_members'");
        if ($table_check && $table_check->fetchArray()) {
            // Önce role kolonunu kontrol et
            $columns_check = @$db->query("PRAGMA table_info(board_members)");
            $has_role = false;
            $has_position = false;
            $has_phone = false;
            if ($columns_check) {
                while ($col = $columns_check->fetchArray(SQLITE3_ASSOC)) {
                    if ($col['name'] === 'role') $has_role = true;
                    if ($col['name'] === 'position') $has_position = true;
                    if ($col['name'] === 'phone') $has_phone = true;
                }
            }
            
            if ($has_phone) {
                // Başkanı bul - role veya position kolonuna göre
                $president_query = null;
                if ($has_role) {
                    $president_query = @$db->prepare("SELECT phone FROM board_members WHERE club_id = :club_id AND (LOWER(role) LIKE '%başkan%' OR LOWER(role) LIKE '%president%' OR role = 'Başkan' OR role = 'President') LIMIT 1");
                } elseif ($has_position) {
                    $president_query = @$db->prepare("SELECT phone FROM board_members WHERE club_id = :club_id AND (LOWER(position) LIKE '%başkan%' OR LOWER(position) LIKE '%president%' OR position = 'Başkan' OR position = 'President') LIMIT 1");
                }
                
                if ($president_query) {
                    $president_query->bindValue(':club_id', defined('CLUB_ID') ? CLUB_ID : 1, SQLITE3_INTEGER);
                    $result = @$president_query->execute();
                    if ($result) {
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        if ($row && !empty($row['phone'])) {
                            $candidates[] = $row['phone'];
                        }
                    }
                }
            }
        }
        
        // Öncelik 2: Settings tablosundan telefon numaraları
        $keys = ['president_phone', 'contact_phone', 'admin_phone', 'admin_phone_number'];
        foreach ($keys as $key) {
            $stmt = @$db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key AND club_id = :club LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':club', defined('CLUB_ID') ? CLUB_ID : 1, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    $value = $result->fetchArray(SQLITE3_ASSOC)['setting_value'] ?? null;
                    if (!empty($value)) {
                        $candidates[] = $value;
                    }
                }
            }
        }

        // Öncelik 3: Superadmin veritabanındaki community_requests tablosu
        $superadminDbPath = dirname(__DIR__, 2) . '/unipanel.sqlite';
        if (file_exists($superadminDbPath)) {
            $superDb = new SQLite3($superadminDbPath);
            $superDb->exec("CREATE TABLE IF NOT EXISTS community_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_name TEXT,
                folder_name TEXT,
                admin_phone TEXT,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = @$superDb->prepare("SELECT admin_phone FROM community_requests WHERE folder_name = :folder AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':folder', defined('COMMUNITY_ID') ? COMMUNITY_ID : '', SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $phone = $result->fetchArray(SQLITE3_ASSOC)['admin_phone'] ?? null;
                    if (!empty($phone)) {
                        $candidates[] = $phone;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = normalize_phone_number((string)$candidate);
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        return null;
    }
}

if (!function_exists('verification_get_status_for_api')) {
    function verification_get_status_for_api(?string $communityId = null): array
    {
        $latest = verification_get_latest_request($communityId);
        if (!$latest) {
            return [
                'status' => 'none',
                'verified' => false,
                'message' => 'Henüz doğrulama talebi bulunmuyor.'
            ];
        }

        $meta = verification_get_status_meta($latest['status']);
        return [
            'status' => $latest['status'],
            'verified' => $latest['status'] === 'approved',
            'label' => $meta['label'],
            'description' => $meta['description'],
            'document_path' => $latest['document_path'],
            'notes' => $latest['notes'],
            'admin_notes' => $latest['admin_notes'],
            'reviewed_at' => $latest['reviewed_at'],
            'updated_at' => $latest['updated_at']
        ];
    }
}

if (!function_exists('verification_get_status_counts')) {
    function verification_get_status_counts(): array
    {
        $db = verification_require_table();
        $counts = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total' => 0
        ];
        $result = $db->query("SELECT status, COUNT(*) as cnt FROM community_verifications GROUP BY status");
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $status = $row['status'] ?? '';
                $cnt = (int)($row['cnt'] ?? 0);
                if (isset($counts[$status])) {
                    $counts[$status] = $cnt;
                }
                $counts['total'] += $cnt;
            }
        }
        return $counts;
    }
}

