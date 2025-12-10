<?php
/**
 * Subscription Guard - Paket Kontrolü ve Yönlendirme
 * Paket yetersizse kullanıcıyı durdurur ve paket yükseltme sayfasına yönlendirir
 */

use UniPanel\Payment\SubscriptionManager;

/**
 * Paket kontrolü yap ve yetersizse paket yükseltme sayfası göster
 * 
 * @param string $requiredFeature Gerekli özellik (financial, sms, email, api) veya limit tipi (max_members, max_events_per_month, vb.)
 * @param string|null $requiredTier Gerekli paket seviyesi (professional, business) - null ise feature'a göre belirlenir
 * @param int|null $currentValue Mevcut kullanım değeri (limit kontrolü için)
 * @return bool Paket yeterliyse true, yetersizse false (ve sayfa gösterilir)
 */
function require_subscription_feature($requiredFeature, $requiredTier = null, $currentValue = null) {
    // Helper dosyasını yükle
    if (!function_exists('has_subscription_feature')) {
        require_once __DIR__ . '/subscription_helper.php';
    }
    
    // Community ID kontrolü
    if (!defined('COMMUNITY_ID') || !COMMUNITY_ID) {
        render_subscription_required_page('Standart', 'Bu özellik için topluluk kimliği gerekli.');
        return false;
    }
    
    try {
        $db = get_db();
        $subscriptionManager = new SubscriptionManager($db, COMMUNITY_ID);
        $currentSubscription = $subscriptionManager->getSubscription();
        $currentTier = $currentSubscription['tier'] ?? 'standard';
        
        // Özellik kontrolü (financial, sms, email, api, reports)
        $featureTierMap = [
            'financial' => 'business', // Sadece Business'ta
            'email' => 'business', // Mail Merkezi sadece Business'ta
            'sms' => 'business',
        'api' => null,
            'reports' => 'professional' // Raporlar Professional'da başlar
        ];
        
        // Eğer requiredTier belirtilmemişse, feature'a göre belirle
        if ($requiredTier === null && isset($featureTierMap[$requiredFeature])) {
            $requiredTier = $featureTierMap[$requiredFeature];
        }
        
        // Limit kontrolü (max_members, max_events_per_month, vb.)
        $limitTierMap = [
            'max_members' => null, // Tüm paketlerde var ama limit farklı
            'max_events_per_month' => null,
            'max_board_members' => null,
        'max_campaigns' => null,
            'max_products' => null
        ];
        
        // Limit kontrolü yapılacaksa
        if (strpos($requiredFeature, 'max_') === 0) {
            // Limit kontrolü için helper fonksiyonları kullan
            $limitInfo = null;
            
            if ($requiredFeature === 'max_members') {
                $limitInfo = check_member_limit($currentValue);
            } elseif ($requiredFeature === 'max_events_per_month') {
                $limitInfo = check_event_limit($currentValue);
            } elseif ($requiredFeature === 'max_board_members') {
                $limitInfo = check_board_member_limit($currentValue);
            } elseif ($requiredFeature === 'max_campaigns') {
                $limitInfo = check_campaign_limit($currentValue);
            } elseif ($requiredFeature === 'max_products') {
                $limitInfo = check_product_limit($currentValue);
            } else {
                $limitInfo = check_subscription_limit($requiredFeature, $currentValue);
            }
            
            if (!$limitInfo['allowed']) {
                $message = get_limit_exceeded_message($requiredFeature, $limitInfo);
                render_subscription_required_page(
                    ucfirst($currentTier),
                    $message,
                    $requiredFeature,
                    $limitInfo
                );
                // View zaten ayarlandı, false döndür
                return false;
            }
            
            return true;
        }
        
        // Özellik kontrolü yapılacaksa
        if ($requiredTier !== null) {
            $tierLevels = ['standard' => 1, 'professional' => 2, 'business' => 3];
            $currentLevel = $tierLevels[$currentTier] ?? 1;
            $requiredLevel = $tierLevels[$requiredTier] ?? 1;
            
            if ($currentLevel < $requiredLevel) {
                $featureNames = [
                    'financial' => 'Finans Yönetimi',
                    'email' => 'Mail Merkezi',
                    'sms' => 'Mesaj Merkezi (SMS)',
                    'api' => 'API Erişimi',
                    'reports' => 'Raporlar ve Analitik'
                ];
                
                $featureName = $featureNames[$requiredFeature] ?? ucfirst($requiredFeature);
                
                render_subscription_required_page(
                    ucfirst($currentTier),
                    "{$featureName} özelliği {$requiredTier} paketinde mevcut.",
                    $requiredFeature,
                    null,
                    $requiredTier
                );
                // View zaten ayarlandı, false döndür
                return false;
            }
        } else {
            // hasFeature kontrolü
            if (!has_subscription_feature($requiredFeature)) {
                $featureNames = [
                    'financial' => 'Finans Yönetimi',
                    'email' => 'Mail Merkezi',
                    'sms' => 'Mesaj Merkezi (SMS)',
                    'api' => 'API Erişimi',
                    'reports' => 'Raporlar ve Analitik'
                ];
                
                $featureName = $featureNames[$requiredFeature] ?? ucfirst($requiredFeature);
                
                render_subscription_required_page(
                    ucfirst($currentTier),
                    "{$featureName} özelliği mevcut paketinizde bulunmamaktadır.",
                    $requiredFeature
                );
                // View zaten ayarlandı, false döndür
                return false;
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Subscription guard error: " . $e->getMessage());
        render_subscription_required_page('Standart', 'Paket kontrolü yapılamadı. Lütfen daha sonra tekrar deneyin.');
        // View zaten ayarlandı, false döndür
        return false;
    }
}

/**
 * Paket yükseltme sayfası içeriğini döndür (template_index.php ile entegre)
 * Global değişkenlere bilgi kaydeder, template_index.php'de gösterilir
 */
function render_subscription_required_page($currentTierLabel, $message, $feature = null, $limitInfo = null, $requiredTier = null) {
    $tierLabels = [
        'standard' => 'Standart',
        'professional' => 'Profesyonel',
        'business' => 'Business'
    ];
    
    $requiredTierLabel = $requiredTier ? ($tierLabels[$requiredTier] ?? ucfirst($requiredTier)) : 'Profesyonel veya Business';
    
    // Limit bilgisi varsa göster
    $limitDetails = '';
    if ($limitInfo !== null) {
        $limit = $limitInfo['limit'] ?? 0;
        $current = $limitInfo['current'] ?? 0;
        $limitDetails = "
            <div class='bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6'>
                <p class='text-sm text-blue-800'>
                    <strong>Mevcut Limit:</strong> " . ($limit === -1 ? 'Sınırsız' : $limit) . "<br>
                    <strong>Mevcut Kullanım:</strong> {$current}
                </p>
            </div>";
    }
    
    // Upgrade mesajı
    $upgradeMessage = '';
    if ($requiredTier === 'professional') {
        $upgradeMessage = '<strong>Professional</strong> veya <strong>Business</strong> paketine yükselterek bu özelliğe erişebilirsiniz.';
    } elseif ($requiredTier === 'business') {
        $upgradeMessage = '<strong>Business</strong> paketine yükselterek bu özelliğe erişebilirsiniz.';
    } else {
        $upgradeMessage = 'Paket yükselterek daha fazla özelliğe erişebilirsiniz.';
    }
    
    // Global değişkene kaydet - template_index.php'de gösterilecek
    global $subscription_required_data;
    $subscription_required_data = [
        'currentTierLabel' => $currentTierLabel,
        'message' => $message,
        'limitDetails' => $limitDetails,
        'upgradeMessage' => $upgradeMessage,
        'requiredTier' => $requiredTier
    ];
    
    // View'ı subscription_required olarak ayarla
    $_GET['view'] = 'subscription_required';
    
    // Global $current_view değişkenini de güncelle (template_index.php'de kullanılıyor)
    global $current_view;
    $current_view = 'subscription_required';
    
    // Template_index.php'nin devam etmesi için false döndür (exit yapma)
    return false;
}

