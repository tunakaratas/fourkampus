<?php
/**
 * Abonelik Limit Kontrol Helper Fonksiyonları
 * Toplulukların paket limitlerine göre işlem yapmasını sağlar
 */

use UniPanel\Payment\SubscriptionManager;

/**
 * Mevcut topluluğun abonelik limitlerini kontrol et
 * 
 * @param string $limitType Limit tipi (max_members, max_events_per_month, has_financial, vb.)
 * @param int|null $currentValue Mevcut değer (opsiyonel)
 * @return array Limit bilgisi
 */
function check_subscription_limit($limitType, $currentValue = null) {
    if (!defined('COMMUNITY_ID') || !COMMUNITY_ID) {
        // Community ID yoksa varsayılan olarak standart limitleri döndür
        $defaultLimits = SubscriptionManager::getPackageLimits('standard');
        $limit = $defaultLimits[$limitType] ?? -1;
        return [
            'allowed' => $limit === -1 || ($currentValue !== null && $currentValue < $limit),
            'limit' => $limit,
            'current' => $currentValue,
            'remaining' => $limit === -1 ? -1 : max(0, $limit - ($currentValue ?? 0))
        ];
    }
    
    try {
        $db = get_db();
        $subscriptionManager = new SubscriptionManager($db, COMMUNITY_ID);
        return $subscriptionManager->checkLimit($limitType, $currentValue);
    } catch (Exception $e) {
        error_log("Subscription limit check error: " . $e->getMessage());
        // Hata durumunda varsayılan olarak standart limitleri döndür
        $defaultLimits = SubscriptionManager::getPackageLimits('standard');
        $limit = $defaultLimits[$limitType] ?? -1;
        return [
            'allowed' => false,
            'limit' => $limit,
            'current' => $currentValue,
            'remaining' => 0
        ];
    }
}

/**
 * Özellik erişim kontrolü
 * 
 * @param string $feature Özellik adı (financial, sms, email, api)
 * @return bool Özellik mevcut mu?
 */
function has_subscription_feature($feature) {
    if (!defined('COMMUNITY_ID') || !COMMUNITY_ID) {
        return false;
    }
    
    try {
        $db = get_db();
        $subscriptionManager = new SubscriptionManager($db, COMMUNITY_ID);
        return $subscriptionManager->hasFeature($feature);
    } catch (Exception $e) {
        error_log("Subscription feature check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Üye sayısı limit kontrolü
 * 
 * @param int|null $currentCount Mevcut üye sayısı (opsiyonel, verilmezse sadece limit döner)
 * @return array Limit bilgisi
 */
function check_member_limit($currentCount = null) {
    if ($currentCount === null) {
        // Mevcut üye sayısını hesapla
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
    }
    
    return check_subscription_limit('max_members', $currentCount);
}

/**
 * Aylık etkinlik limit kontrolü
 * 
 * @param int|null $currentCount Bu ay oluşturulan etkinlik sayısı (opsiyonel)
 * @return array Limit bilgisi
 */
function check_event_limit($currentCount = null) {
    if ($currentCount === null) {
        // Bu ay oluşturulan etkinlik sayısını hesapla
        try {
            $db = get_db();
            $firstDayOfMonth = date('Y-m-01');
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM events WHERE club_id = ? AND created_at >= ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $stmt->bindValue(2, $firstDayOfMonth, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
    }
    
    return check_subscription_limit('max_events_per_month', $currentCount);
}

/**
 * Yönetim kurulu limit kontrolü
 * 
 * @param int|null $currentCount Mevcut yönetim kurulu üye sayısı (opsiyonel)
 * @return array Limit bilgisi
 */
function check_board_member_limit($currentCount = null) {
    if ($currentCount === null) {
        // Mevcut yönetim kurulu üye sayısını hesapla
        try {
            $db = get_db();
            // Board members genelde members tablosunda role ile belirlenir
            // Veya ayrı bir board_members tablosu olabilir
            // Şimdilik basit bir kontrol yapalım
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE club_id = ? AND (role = 'president' OR role = 'vice_president' OR role = 'secretary' OR role = 'treasurer' OR role = 'board_member')");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
    }
    
    return check_subscription_limit('max_board_members', $currentCount);
}

/**
 * Kampanya limit kontrolü
 * 
 * @param int|null $currentCount Mevcut aktif kampanya sayısı (opsiyonel)
 * @return array Limit bilgisi
 */
function check_campaign_limit($currentCount = null) {
    if ($currentCount === null) {
        // Mevcut aktif kampanya sayısını hesapla
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM campaigns WHERE club_id = ? AND is_active = 1");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
    }
    
    return check_subscription_limit('max_campaigns', $currentCount);
}

/**
 * Ürün limit kontrolü (Market)
 * 
 * @param int|null $currentCount Mevcut ürün sayısı (opsiyonel)
 * @return array Limit bilgisi
 */
function check_product_limit($currentCount = null) {
    if ($currentCount === null) {
        // Mevcut ürün sayısını hesapla
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE club_id = ?");
            $stmt->bindValue(1, CLUB_ID, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentCount = (int)($row['count'] ?? 0);
        } catch (Exception $e) {
            $currentCount = 0;
        }
    }
    
    return check_subscription_limit('max_products', $currentCount);
}

/**
 * Limit aşım mesajı al
 * 
 * @param string $limitType Limit tipi
 * @param array $limitInfo Limit bilgisi (check_subscription_limit'ten dönen)
 * @return string Mesaj
 */
function get_limit_exceeded_message($limitType, $limitInfo) {
    if (!defined('COMMUNITY_ID') || !COMMUNITY_ID) {
        return 'Limit aşıldı. Lütfen paket yükseltin.';
    }
    
    try {
        $db = get_db();
        $subscriptionManager = new SubscriptionManager($db, COMMUNITY_ID);
        return $subscriptionManager->getLimitExceededMessage($limitType, $limitInfo);
    } catch (Exception $e) {
        return 'Limit aşıldı. Lütfen paket yükseltin.';
    }
}

/**
 * SubscriptionManager instance'ını al
 * 
 * @return SubscriptionManager|null
 */
function get_subscription_manager() {
    if (!defined('COMMUNITY_ID') || empty(COMMUNITY_ID)) {
        return null;
    }
    
    try {
        if (!function_exists('get_db')) {
            return null;
        }
        $db = get_db();
        if (!$db) {
            return null;
        }
        return new SubscriptionManager($db, COMMUNITY_ID);
    } catch (Exception $e) {
        error_log("Get subscription manager error: " . $e->getMessage());
        return null;
    } catch (Error $e) {
        error_log("Get subscription manager fatal error: " . $e->getMessage());
        return null;
    }
}

/**
 * SMS kullanım bilgisini al (bu ay)
 * RAPORLAR SEKMESİ İLE AYNI MANTIK: rate_limits tablosundan çekiyoruz
 * 
 * @return array SMS kullanım bilgisi
 */
function get_sms_usage_info() {
    // COMMUNITY_ID veya CLUB_ID kontrolü
    $communityId = null;
    $clubId = null;
    
    if (defined('COMMUNITY_ID') && COMMUNITY_ID) {
        $communityId = COMMUNITY_ID;
    } elseif (defined('CLUB_ID') && CLUB_ID) {
        $clubId = CLUB_ID;
    } else {
        return [
            'current' => 0,
            'limit' => 0,
            'remaining' => 0
        ];
    }
    
    try {
        $db = get_db();
        
        // Rate limits tablosunu oluştur (eğer yoksa)
        // Tabloyu direkt oluştur (ensure_rate_limits_table fonksiyonuna bağımlı olmadan)
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                action_type TEXT NOT NULL,
                action_count INTEGER DEFAULT 0,
                hour_timestamp TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {
            error_log("Rate limits table creation error: " . $e->getMessage());
        }
        
        // RAPORLAR SEKMESİ İLE AYNI MANTIK: rate_limits tablosundan bu ayın SMS sayısını çek
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        
        // Bu ay gönderilen SMS sayısını hesapla (raporlar sekmesi ile aynı)
        // CLUB_ID kullan (rate_limits tablosu club_id kullanıyor)
        $usedClubId = $clubId ?? (defined('CLUB_ID') ? CLUB_ID : null);
        if (!$usedClubId) {
            return [
                'current' => 0,
                'limit' => 0,
                'remaining' => 0
            ];
        }
        
        $stmt = $db->prepare("SELECT SUM(action_count) as total FROM rate_limits 
                              WHERE club_id = ? AND action_type = 'sms' 
                              AND hour_timestamp >= ? AND hour_timestamp <= ?");
        $stmt->bindValue(1, $usedClubId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $month_start, SQLITE3_TEXT);
        $stmt->bindValue(3, $month_end, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $currentUsage = (int)($row['total'] ?? 0);
        
        // Limit bilgisini al (Business plan için 500 SMS + tahsis edilen krediler)
        // COMMUNITY_ID kullan (SubscriptionManager COMMUNITY_ID bekliyor)
        if (!$communityId) {
            return [
                'current' => $currentUsage,
                'limit' => 0,
                'remaining' => 0
            ];
        }
        
        $subscriptionManager = get_subscription_manager();
        if (!$subscriptionManager) {
            return [
                'current' => $currentUsage,
                'limit' => 0,
                'remaining' => 0
            ];
        }
        
        $limits = $subscriptionManager->getCurrentLimits();
        $baseLimit = $limits['max_sms_per_month'] ?? 0; // Business plan için 500 SMS
        
        // Business paketi kontrolü - eğer Business paketi yoksa limit 0
        $currentTier = $subscriptionManager->getCurrentTier();
        if ($currentTier !== 'business') {
            // Business paketi yok - limit 0, sınırsız değil
            error_log("SMS Usage Info: Business paketi yok (tier=$currentTier), limit=0");
            return [
                'current' => $currentUsage,
                'limit' => 0,
                'remaining' => 0,
                'base_limit' => 0,
                'credits' => 0
            ];
        }
        
        // Tahsis edilen SMS kredilerini ekle
        $smsCredits = $subscriptionManager->getTotalSmsCredits();
        $totalLimit = $baseLimit + $smsCredits; // 500 (Business) + tahsis edilen krediler
        
        $remaining = $totalLimit === -1 ? -1 : max(0, $totalLimit - $currentUsage);
        
        error_log("SMS Usage Info (from rate_limits + credits): current=$currentUsage, baseLimit=$baseLimit, credits=$smsCredits, totalLimit=$totalLimit, remaining=$remaining, tier=$currentTier");
        
        return [
            'current' => $currentUsage,
            'limit' => $totalLimit,
            'remaining' => $remaining,
            'base_limit' => $baseLimit,
            'credits' => $smsCredits
        ];
    } catch (Exception $e) {
        error_log("Get SMS usage info error: " . $e->getMessage());
        return [
            'current' => 0,
            'limit' => 0,
            'remaining' => 0
        ];
    }
}

/**
 * Mevcut paket bilgisini al
 * 
 * @return array Paket bilgisi
 */
function get_current_subscription_info() {
    if (!defined('COMMUNITY_ID') || !COMMUNITY_ID) {
        return [
            'tier' => 'standard',
            'tier_label' => 'Standart',
            'limits' => SubscriptionManager::getPackageLimits('standard')
        ];
    }
    
    try {
        $db = get_db();
        $subscriptionManager = new SubscriptionManager($db, COMMUNITY_ID);
        $currentTier = $subscriptionManager->getCurrentTier();
        $limits = $subscriptionManager->getCurrentLimits();
        
        $tierLabels = [
            'standard' => 'Standart',
            'professional' => 'Profesyonel',
            'business' => 'Business'
        ];
        
        return [
            'tier' => $currentTier,
            'tier_label' => $tierLabels[$currentTier] ?? 'Standart',
            'limits' => $limits
        ];
    } catch (Exception $e) {
        error_log("Get subscription info error: " . $e->getMessage());
        return [
            'tier' => 'standard',
            'tier_label' => 'Standart',
            'limits' => SubscriptionManager::getPackageLimits('standard')
        ];
    }
}

/**
 * Paket yetersizliği uyarı ekranı göster
 * 
 * @param string $featureName Özellik adı (örn: "Finans Yönetimi", "SMS Gönderimi")
 * @param string $requiredTier Gerekli paket seviyesi (professional, business)
 * @param string|null $currentTier Mevcut paket seviyesi (opsiyonel)
 * @return string HTML uyarı ekranı
 */
function render_subscription_warning($featureName, $requiredTier, $currentTier = null) {
    if ($currentTier === null) {
        $info = get_current_subscription_info();
        $currentTier = $info['tier'];
    }
    
    $tierLabels = [
        'standard' => 'Standart',
        'professional' => 'Profesyonel',
        'business' => 'Business'
    ];
    
    $requiredTierLabel = $tierLabels[$requiredTier] ?? ucfirst($requiredTier);
    $currentTierLabel = $tierLabels[$currentTier] ?? ucfirst($currentTier);
    
    $upgradeMessage = '';
    if ($requiredTier === 'professional') {
        $upgradeMessage = '<strong>Professional</strong> veya <strong>Business</strong> paketine yükselterek bu özelliğe erişebilirsiniz.';
    } elseif ($requiredTier === 'business') {
        $upgradeMessage = '<strong>Business</strong> paketine yükselterek bu özelliğe erişebilirsiniz.';
    }
    
    return '
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">' . htmlspecialchars($featureName) . ' Mevcut Değil</h2>
            <p class="text-gray-600 mb-6">
                ' . htmlspecialchars($featureName) . ' özelliği <strong>' . htmlspecialchars($currentTierLabel) . '</strong> pakette mevcut değil.
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    ' . $upgradeMessage . '
                </p>
            </div>
            <a href="?view=subscription" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 font-semibold">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Paket Yükselt
            </a>
        </div>
    </div>';
}

/**
 * Limit aşımı uyarı ekranı göster
 * 
 * @param string $limitType Limit tipi (max_members, max_events_per_month, vb.)
 * @param array $limitInfo Limit bilgisi (check_subscription_limit'ten dönen)
 * @return string HTML uyarı ekranı
 */
function render_limit_exceeded_warning($limitType, $limitInfo) {
    $featureNames = [
        'max_members' => 'Üye Ekleme',
        'max_events_per_month' => 'Etkinlik Oluşturma',
        'max_board_members' => 'Yönetim Kurulu Üyesi Ekleme',
        'max_campaigns' => 'Kampanya Oluşturma',
        'max_products' => 'Ürün Ekleme'
    ];
    
    $featureName = $featureNames[$limitType] ?? 'Bu İşlem';
    $tier = ucfirst($limitInfo['tier'] ?? 'Standart');
    $limit = $limitInfo['limit'] ?? 0;
    $current = $limitInfo['current'] ?? 0;
    
    $message = get_limit_exceeded_message($limitType, $limitInfo);
    
    return '
    <div class="bg-white rounded-xl shadow-sm border border-yellow-200 p-8">
        <div class="text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">' . htmlspecialchars($featureName) . ' Limit Aşıldı</h2>
            <p class="text-gray-600 mb-4">
                ' . htmlspecialchars($message) . '
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                    Mevcut planınız (<strong>' . htmlspecialchars($tier) . '</strong>) için limit: <strong>' . ($limit === -1 ? 'Sınırsız' : $limit) . '</strong><br>
                    Mevcut kullanımınız: <strong>' . $current . '</strong>
                </p>
            </div>
            <a href="?view=subscription" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 font-semibold">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Paket Yükselt
            </a>
        </div>
    </div>';
}

