<?php
/**
 * Abonelik Yönetim Fonksiyonları
 */

use UniPanel\Payment\SubscriptionManager;
use UniPanel\Payment\IyzicoHelper;

/**
 * Abonelik sekmesi görünümü
 */
function render_subscription_view($db, $communityId) {
    $subscriptionManager = new SubscriptionManager($db, $communityId);
    $subscriptionManager->createSubscriptionTable(); // Bu otomatik olarak standart sürümü aktif eder
    
    // Abonelik durumunu kontrol et ve güncelle (expired abonelikleri işaretle)
    $subscriptionManager->checkAndUpdateSubscriptionStatus();
    
    $subscription = $subscriptionManager->getSubscription();
    $isActive = $subscriptionManager->isActive();
    $remainingDays = $subscriptionManager->getRemainingDays();
    
    // Mevcut sürümü belirle - Standart her zaman aktif
    $currentTier = $subscriptionManager->getCurrentTier();
    
    // Standart abonelik her zaman aktif olmalı
    if ($currentTier === 'standard') {
        $isActive = true; // Standart her zaman aktif
    }
    
    // Paket fiyatlarını al
    $allPackages = SubscriptionManager::getPackagePrices();
    $packagesByTier = SubscriptionManager::getPackagesByTier();
    $isSeptemberPromo = SubscriptionManager::isSeptemberPromotion();
    
    // Ödeme işlemi
    $paymentError = null;
    $paymentSuccess = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_payment') {
        // CSRF Token Kontrolü
        if (!function_exists('verify_csrf_token')) {
            function verify_csrf_token($token) {
                if (empty($token) || !isset($_SESSION['csrf_token'])) {
                    return false;
                }
                return hash_equals($_SESSION['csrf_token'], $token);
            }
        }
        
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            $paymentError = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        } else {
            $selectedPackage = $_POST['package'] ?? 'professional_12';
            
            if (!isset($allPackages[$selectedPackage])) {
                $paymentError = "Geçersiz paket seçimi.";
            } else {
                $package = $allPackages[$selectedPackage];
                
                try {
                    // Standart sürüm kontrolü - Standart her zaman aktif, satın alınamaz
                    if ($package['tier'] === 'standard') {
                        $paymentSuccess = "Standart sürüm zaten aktif! Sınırsız kullanabilirsiniz. Profesyonel veya Business paketlerini satın almak için seçim yapın.";
                        // Sayfayı yenile
                        header("Refresh: 2; url=" . $_SERVER['REQUEST_URI']);
                    } elseif ($package['price'] == 0 && $isSeptemberPromo) {
                        // Eylül promosyonu - Ücretsiz paketler
                        $conversationId = 'PROMO-' . time() . '-' . $communityId . '-' . bin2hex(random_bytes(4));
                        
                        // Abonelik kaydı oluştur (ücretsiz, direkt aktif)
                        $subscriptionId = $subscriptionManager->createSubscription(
                            $conversationId,
                            'success', // Direkt aktif
                            $package['months'] ?? 1,
                            $package['price'],
                            $package['tier']
                        );
                        
                        $paymentSuccess = "Tebrikler! Eylül kampanyası kapsamında aboneliğiniz başarıyla aktif edildi. Pro sürüm özelliklerine erişebilirsiniz.";
                        
                        // Sayfayı yenile
                        header("Refresh: 2; url=" . $_SERVER['REQUEST_URI']);
                    } else {
                        // Normal ödeme işlemi - Profesyonel veya Business paketleri
                        // Standart paket satın alınamaz kontrolü
                        if ($package['tier'] === 'standard') {
                            $paymentError = "Standart paket satın alınamaz! Standart paket zaten aktif. Profesyonel veya Business paketlerini seçebilirsiniz.";
                        } else {
                            $iyzicoHelper = new IyzicoHelper();
                            
                            // Session timeout kontrolü - Güvenlik
                            if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > 1800) {
                                $paymentError = "Oturum süresi doldu. Lütfen tekrar giriş yapın.";
                            } else {
                                $_SESSION['last_activity'] = time();
                                
                                // Callback URL güvenli oluştur - XSS koruması
                                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $callbackPath = dirname(dirname($_SERVER['REQUEST_URI'] ?? ''));
                                $callbackUrl = $protocol . '://' . $host . $callbackPath . '/templates/payment_callback.php?community=' . urlencode($communityId);
                                
                                // Unique conversation ID (çift ödeme koruması) - Güvenli token
                                $conversationId = 'SUB-' . time() . '-' . $communityId . '-' . bin2hex(random_bytes(16));
                                
                                // Payment token oluştur (callback'te doğrulama için)
                                $payment_token = bin2hex(random_bytes(32));
                                
                                $paymentData = [
                                    'conversation_id' => $conversationId,
                                    'price' => $package['price'],
                                    'callback_url' => $callbackUrl
                                ];
                                
                                $paymentForm = $iyzicoHelper->createPaymentForm($paymentData);
                                
                                // Abonelik kaydı oluştur (pending) - Güvenlik: Payment token ile
                                try {
                                    $subscriptionId = $subscriptionManager->createSubscription(
                                        $paymentForm['conversation_id'],
                                        'pending',
                                        $package['months'] ?? 1,
                                        $package['price'],
                                        $package['tier']
                                    );
                                    
                                    // Payment token'ı session'a kaydet (callback'te doğrulama için)
                                    // Güvenlik: Token'ı sadece session'da sakla, cookie'de değil
                                    $_SESSION['pending_subscription_token'] = $payment_token;
                                    $_SESSION['pending_subscription_id'] = $subscriptionId;
                                    $_SESSION['pending_payment_id'] = $paymentForm['conversation_id'];
                                    
                                    // Ödeme sayfasına yönlendir
                                    $paymentSuccess = "Ödeme işlemi başlatıldı. Iyzico ödeme sayfasına yönlendiriliyorsunuz...";
                                    
                                    // Iyzico ödeme formunu göster veya yönlendir
                                    // TODO: Iyzico SDK entegrasyonu tamamlandığında burada ödeme formu gösterilecek
                                    
                                } catch (\Exception $e) {
                                    tpl_error_log("Subscription creation error: " . $e->getMessage());
                                    $paymentError = "Abonelik oluşturulurken hata: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    tpl_error_log("Subscription payment error: " . $e->getMessage());
                    $paymentError = "Ödeme işlemi başlatılırken bir hata oluştu: " . $e->getMessage();
                }
            }
        }
    }
    
    ?>
    <div class="subscription-view">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Abonelik Yönetimi</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Topluluğunuzun abonelik durumunu yönetin</p>
                </div>
            </div>
            
            <?php if ($paymentError): ?>
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm text-red-800 dark:text-red-200"><?= htmlspecialchars($paymentError) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($paymentSuccess): ?>
                <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <p class="text-sm text-green-800 dark:text-green-200"><?= htmlspecialchars($paymentSuccess) ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Mevcut Abonelik Durumu -->
            <?php if ($subscription): ?>
                <?php
                $currentTier = $subscriptionManager->getCurrentTier();
                $tierLabels = [
                    'standard' => 'Standart',
                    'professional' => 'Profesyonel',
                    'business' => 'Business'
                ];
                $tierColors = [
                    'standard' => [
                        'bg' => 'bg-gray-50',
                        'border' => 'border-gray-200',
                        'text' => 'text-gray-700',
                        'badge' => 'bg-gray-100 text-gray-800',
                        'icon' => 'text-gray-500'
                    ],
                    'professional' => [
                        'bg' => 'bg-blue-50',
                        'border' => 'border-blue-200',
                        'text' => 'text-blue-700',
                        'badge' => 'bg-blue-100 text-blue-800',
                        'icon' => 'text-blue-500'
                    ],
                    'business' => [
                        'bg' => 'bg-purple-50',
                        'border' => 'border-purple-200',
                        'text' => 'text-purple-700',
                        'badge' => 'bg-purple-100 text-purple-800',
                        'icon' => 'text-purple-500'
                    ]
                ];
                $colors = $tierColors[$currentTier] ?? $tierColors['standard'];
                ?>
                <div class="mb-6">
                    <div class="bg-white rounded-xl shadow-sm border-2 <?= $colors['border'] ?> overflow-hidden">
                        <!-- Üst Başlık -->
                        <div class="<?= $colors['bg'] ?> px-6 py-4 border-b <?= $colors['border'] ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 <?= $colors['bg'] ?> rounded-lg flex items-center justify-center border-2 <?= $colors['border'] ?>">
                                        <?php if ($currentTier === 'standard'): ?>
                                            <svg class="w-6 h-6 <?= $colors['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        <?php elseif ($currentTier === 'professional'): ?>
                                            <svg class="w-6 h-6 <?= $colors['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        <?php else: ?>
                                            <svg class="w-6 h-6 <?= $colors['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">
                                            <?= $tierLabels[$currentTier] ?? 'Standart' ?> Plan
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-0.5">Aktif Abonelik</p>
                                    </div>
                                </div>
                                <?php if ($isActive): ?>
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800 border border-green-200">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                        Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                        <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                                        Süresi Dolmuş
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Abonelik Detayları -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Plan Bilgisi -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Plan Tipi</span>
                                    </div>
                                    <p class="text-lg font-bold text-gray-900">
                                        <?= $tierLabels[$currentTier] ?? 'Standart' ?>
                                    </p>
                                    <?php if ($currentTier === 'standard'): ?>
                                        <p class="text-xs text-green-600 font-semibold mt-1">Sınırsız Kullanım</p>
                                    <?php elseif ($currentTier === 'professional'): ?>
                                        <p class="text-xs text-blue-600 font-semibold mt-1">250₺/ay</p>
                                    <?php else: ?>
                                        <p class="text-xs text-purple-600 font-semibold mt-1">500₺/ay + 500 SMS</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Süre Bilgisi -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            <?php if ($currentTier === 'standard'): ?>
                                                Durum
                                            <?php else: ?>
                                                Kalan Süre
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                        <?php if ($currentTier === 'standard'): ?>
                                        <p class="text-lg font-bold text-green-600">Sınırsız</p>
                                        <p class="text-xs text-gray-500 mt-1">Her zaman aktif</p>
                                    <?php elseif ($isActive && $remainingDays > 0): ?>
                                        <p class="text-lg font-bold text-blue-600"><?= $remainingDays ?> Gün</p>
                                        <?php if ($subscription['end_date']): ?>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= date('d.m.Y', strtotime($subscription['end_date'])) ?> tarihine kadar
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-lg font-bold text-red-600">Süresi Doldu</p>
                                        <p class="text-xs text-gray-500 mt-1">Yenileme gerekli</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Tarih Bilgisi -->
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            <?php if ($currentTier === 'standard'): ?>
                                                Başlangıç
                                            <?php else: ?>
                                                Bitiş Tarihi
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($currentTier === 'standard' && $subscription['start_date']): ?>
                                        <p class="text-lg font-bold text-gray-900">
                                            <?= date('d.m.Y', strtotime($subscription['start_date'])) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Aktif edildi</p>
                                    <?php elseif ($subscription['end_date']): ?>
                                        <p class="text-lg font-bold text-gray-900">
                                            <?= date('d.m.Y', strtotime($subscription['end_date'])) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php
                                            $endDate = strtotime($subscription['end_date']);
                                            $now = time();
                                            if ($endDate > $now) {
                                                $daysLeft = ceil(($endDate - $now) / 86400);
                                                echo $daysLeft . ' gün sonra';
                                            } else {
                                                echo 'Süresi doldu';
                                            }
                                            ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-lg font-bold text-gray-900">-</p>
                                        <p class="text-xs text-gray-500 mt-1">Bilgi yok</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Ödeme Durumu -->
                            <?php if ($subscription['amount'] > 0): ?>
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Ödeme Durumu:</span>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $subscription['payment_status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst($subscription['payment_status']) ?>
                                            </span>
                                    </div>
                                    <?php if ($subscription['amount'] > 0): ?>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="text-sm text-gray-600">Ödenen Tutar:</span>
                                            <span class="text-sm font-bold text-gray-900"><?= number_format($subscription['amount'], 2, ',', '.') ?> ₺</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-6">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                                    Abonelik Bulunmuyor
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Standart sürümü ücretsiz kullanabilir veya Professional/Business paketlerinden birini seçebilirsiniz.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Paket Seçimi ve Ödeme -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                <?php if ($isSeptemberPromo): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-green-400 dark:border-green-600 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <h4 class="text-lg font-bold text-green-800 dark:text-green-200 mb-1">🎉 Eylül Özel Kampanya!</h4>
                                    <p class="text-sm text-green-700 dark:text-green-300">
                                        <strong>Tüm paketler bu ay ücretsiz!</strong> Pro sürüm özelliklerine ücretsiz erişim kazanın. Kampanya sadece Eylül ayı boyunca geçerlidir.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="subscription-form">
                        <input type="hidden" name="action" value="create_payment">
                        <input type="hidden" name="current_view" value="subscription">
                        <input type="hidden" name="csrf_token" value="<?php 
                            if (!function_exists('generate_csrf_token')) {
                                function generate_csrf_token() {
                                    if (!isset($_SESSION['csrf_token'])) {
                                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                    }
                                    return $_SESSION['csrf_token'];
                                }
                            }
                            echo generate_csrf_token(); 
                        ?>">
                        <input type="hidden" name="package" id="selected-package" value="professional_12" required>
                        
                        <!-- Sürüm Seçimi -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Sürüm Seçin</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="tier-selection">
                                <?php 
                                $tiers = [
                    'standard' => ['label' => 'Standart', 'monthly' => 0, 'unlimited' => true, 'color_classes' => [
                        'border' => 'peer-checked:border-gray-500 hover:border-gray-300 dark:hover:border-gray-600',
                        'bg' => 'peer-checked:bg-gray-50 dark:peer-checked:bg-gray-900/20',
                        'text' => 'text-gray-600 dark:text-gray-400'
                    ]],
                    'professional' => ['label' => 'Profesyonel', 'monthly' => 250, 'color_classes' => [
                        'border' => 'peer-checked:border-blue-500 hover:border-blue-300 dark:hover:border-blue-600',
                        'bg' => 'peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20',
                        'text' => 'text-blue-600 dark:text-blue-400'
                    ]],
                    'business' => ['label' => 'Business', 'monthly' => 500, 'monthly_sms_gift' => 500, 'color_classes' => [
                        'border' => 'peer-checked:border-purple-500 hover:border-purple-300 dark:hover:border-purple-600',
                        'bg' => 'peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/20',
                        'text' => 'text-purple-600 dark:text-purple-400'
                    ]]
                ];
                                foreach ($tiers as $tierKey => $tierInfo): 
                                ?>
                                    <label class="tier-option block" data-tier="<?= $tierKey ?>">
                                        <input type="radio" name="tier" value="<?= $tierKey ?>" class="peer hidden" <?= $tierKey === 'professional' ? 'checked' : '' ?>>
                                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-6 cursor-pointer transition-all duration-200 <?= $tierInfo['color_classes']['border'] ?> <?= $tierInfo['color_classes']['bg'] ?> peer-checked:shadow-lg h-full flex flex-col min-h-[500px]">
                                            <div class="text-center flex-grow flex flex-col">
                                                <h4 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                                                    <?= htmlspecialchars($tierInfo['label']) ?>
                                                </h4>
                                                <div class="mb-4">
                                                    <?php if (isset($tierInfo['unlimited']) && $tierInfo['unlimited']): ?>
                                                        <span class="text-2xl font-bold text-green-600 dark:text-green-400 block">
                                                            ÜCRETSİZ
                                                        </span>
                                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Sınırsız</p>
                                                    <?php elseif (isset($tierInfo['monthly_sms_gift'])): ?>
                                                        <span class="text-2xl font-bold text-purple-600 dark:text-purple-400 block">
                                                            <?= number_format($tierInfo['monthly'], 0, ',', '.') ?>₺
                                                        </span>
                                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">/ ay</p>
                                                        <p class="text-xs text-green-600 dark:text-green-400 font-semibold mt-1">
                                                            +<?= $tierInfo['monthly_sms_gift'] ?> SMS/ay hediye
                                                        </p>
                                                    <?php else: ?>
                                                        <span class="text-2xl font-bold <?= $tierInfo['color_classes']['text'] ?> block">
                                                            <?= number_format($tierInfo['monthly'], 0, ',', '.') ?>₺
                                                        </span>
                                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">/ ay</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-auto">
                                                    <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 text-left">Paket İçeriği:</h5>
                                                    <div class="text-left space-y-2 max-h-[300px] overflow-y-auto">
                                                        <?php 
                                                        $samplePackage = $packagesByTier[$tierKey][0] ?? null;
                                                        if ($samplePackage && isset($samplePackage['features'])): 
                                                            foreach ($samplePackage['features'] as $feature): 
                                                        ?>
                                                            <p class="text-xs text-gray-600 dark:text-gray-400 flex items-start">
                                                                <span class="mr-2 mt-0.5 text-purple-600 dark:text-purple-400 flex-shrink-0">✓</span>
                                                                <span class="leading-relaxed"><?= htmlspecialchars($feature) ?></span>
                                                            </p>
                                                        <?php 
                                                            endforeach;
                                                        endif; 
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Süre Seçimi -->
                        <div class="mb-6" id="duration-selection" style="display: none;">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Süre Seçin</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <?php foreach ([1, 6, 12] as $months): ?>
                                    <label class="duration-option block" data-months="<?= $months ?>">
                                        <input type="radio" name="duration" value="<?= $months ?>" class="peer hidden" <?= $months === 12 ? 'checked' : '' ?>>
                                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-5 cursor-pointer transition-all duration-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 peer-checked:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-600 h-full">
                                            <div class="text-center">
                                                <h5 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                                    <?= $months ?> Aylık
                                                </h5>
                                                <div id="price-<?= $months ?>" class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mb-1">
                                                    Hesaplanıyor...
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="discount-<?= $months ?>"></p>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- SMS Ek Paketleri (Business aboneleri için) -->
                        <div class="mb-6" id="sms-addon-selection" style="display: none;">
                            <div class="bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-500 p-4 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold text-purple-900 dark:text-purple-100">Business aboneliğinize SMS kredisi ekleyin</h4>
                                        <p class="text-sm text-purple-700 dark:text-purple-300">Her ay 500 SMS hediye gelir. Daha fazla SMS için ek paket satın alabilirsiniz.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">SMS Ek Paketleri (Opsiyonel)</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <?php 
                                $addonPackages = $packagesByTier['business_addon'] ?? [];
                                foreach ($addonPackages as $package): 
                                ?>
                                    <label class="sms-addon-option block cursor-pointer">
                                        <input type="radio" name="sms_addon" value="<?= number_format($package['sms_credits'], 0, '', '') ?>" class="peer hidden" data-price="<?= $package['price'] ?>" data-package-key="business_sms_addon_<?= number_format($package['sms_credits'], 0, '', '') ?>">
                                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 transition-all duration-200 peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/20 peer-checked:shadow-lg hover:border-purple-300 dark:hover:border-purple-600 h-full flex flex-col">
                                            <div class="text-center flex-grow">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                                    <?= number_format($package['sms_credits'], 0, ',', '.') ?> SMS
                                                </div>
                                                <div class="text-xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                                                    <?= number_format($package['price'], 0, ',', '.') ?>₺
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                    <?php if ($package['commission_rate'] == 7): ?>
                                                        <span class="text-green-600 dark:text-green-400 font-semibold">%7 komisyon</span>
                                                    <?php else: ?>
                                                        NetGSM + %<?= $package['commission_rate'] ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                    <?= number_format($package['price'] / $package['sms_credits'], 4, ',', '.') ?>₺/SMS
                                                </div>
                                                <?php if (!empty($package['badge'])): ?>
                                                    <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
                                                        <?= $package['badge'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                </svg>
                                SMS kredileri 1 ay boyunca geçerlidir. İsterseniz aboneliğinizi SMS paketi eklemeden de kullanabilirsiniz (aylık 500 SMS hediye).
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                                <div class="flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    <span id="payment-button-text">Ödeme Yap</span>
                                </div>
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!function_exists('tpl_script_nonce_attr')) {
                        function tpl_script_nonce_attr(): string
                        {
                            if (function_exists('tpl_get_csp_nonce')) {
                                return ' nonce="' . htmlspecialchars(tpl_get_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
                            }
                            return '';
                        }
                    } ?>
                    <script<?= tpl_script_nonce_attr(); ?>>
                    document.addEventListener('DOMContentLoaded', function() {
                        const form = document.getElementById('subscription-form');
                        const tierRadios = form.querySelectorAll('input[name="tier"]');
                        const durationRadios = form.querySelectorAll('input[name="duration"]');
                        const smsAddonRadios = form.querySelectorAll('input[name="sms_addon"]');
                        const selectedPackageInput = document.getElementById('selected-package');
                        const buttonText = document.getElementById('payment-button-text');
                        const durationSelection = document.getElementById('duration-selection');
                        const smsAddonSelection = document.getElementById('sms-addon-selection');
                        const allPackages = <?= json_encode($allPackages) ?>;
                        
                        function updatePackageSelection() {
                            const selectedTier = form.querySelector('input[name="tier"]:checked')?.value;
                            const selectedDuration = form.querySelector('input[name="duration"]:checked')?.value;
                            const selectedSmsAddon = form.querySelector('input[name="sms_addon"]:checked')?.value;
                            
                            // Standart sürüm - Butonu gizle (zaten aktif)
                            if (selectedTier === 'standard') {
                                durationSelection.style.display = 'none';
                                smsAddonSelection.style.display = 'none';
                                selectedPackageInput.value = 'standard_1';
                                // Standart zaten aktif olduğu için butonu gizle
                                const submitButton = form.querySelector('button[type="submit"]');
                                if (submitButton) {
                                    submitButton.style.display = 'none';
                                }
                                return;
                            }
                            
                            // Standart dışındaki tier'larda butonu göster
                            const submitButton = form.querySelector('button[type="submit"]');
                            if (submitButton) {
                                submitButton.style.display = 'block';
                            }
                            
                            // Professional sürüm
                            if (selectedTier === 'professional') {
                                durationSelection.style.display = 'block';
                                smsAddonSelection.style.display = 'none';
                                
                                if (selectedDuration) {
                                    const packageKey = selectedTier + '_' + selectedDuration;
                                    selectedPackageInput.value = packageKey;
                                    
                                    if (allPackages[packageKey]) {
                                        const pkg = allPackages[packageKey];
                                        if (pkg.price == 0) {
                                            buttonText.textContent = 'Ücretsiz Aktif Et';
                                        } else {
                                            buttonText.textContent = 'Ödeme Yap (' + pkg.price.toLocaleString('tr-TR') + '₺)';
                                        }
                                    }
                                    
                                    // Fiyatları güncelle
                                    [1, 6, 12].forEach(months => {
                                        const packageKeyForMonths = selectedTier + '_' + months;
                                        if (allPackages[packageKeyForMonths]) {
                                            const pkg = allPackages[packageKeyForMonths];
                                            const priceEl = document.getElementById('price-' + months);
                                            const discountEl = document.getElementById('discount-' + months);
                                            
                                            if (pkg.price == 0) {
                                                priceEl.textContent = 'ÜCRETSİZ';
                                                priceEl.className = 'text-lg font-bold text-green-600 dark:text-green-400';
                                            } else {
                                                priceEl.textContent = pkg.price.toLocaleString('tr-TR') + '₺';
                                                priceEl.className = 'text-lg font-bold text-indigo-600 dark:text-indigo-400';
                                                
                                                if (pkg.discount > 0) {
                                                    discountEl.textContent = '%' + pkg.discount + ' indirim';
                                                    discountEl.className = 'text-xs text-green-600 dark:text-green-400 mt-1';
                                                } else {
                                                    discountEl.textContent = '';
                                                }
                                            }
                                        }
                                    });
                                }
                                return;
                            }
                            
                            // Business sürüm - süre seçimi + opsiyonel SMS addon
                            if (selectedTier === 'business') {
                                durationSelection.style.display = 'block';
                                smsAddonSelection.style.display = 'block';
                                
                                // Fiyat güncelleme fonksiyonu
                                function updateBusinessPrice() {
                                    if (!selectedDuration) return;
                                    
                                    const packageKey = 'business_' + selectedDuration;
                                    let totalPrice = 0;
                                    let packageValue = packageKey;
                                    
                                    // Business abonelik fiyatı
                                    if (allPackages[packageKey]) {
                                        const pkg = allPackages[packageKey];
                                        totalPrice = pkg.price;
                                        
                                        // SMS addon fiyatını ekle - Tıklanınca anlık güncelle
                                        const selectedSmsAddonRadio = form.querySelector('input[name="sms_addon"]:checked');
                                        if (selectedSmsAddonRadio) {
                                            const addonPrice = parseFloat(selectedSmsAddonRadio.getAttribute('data-price')) || 0;
                                            const addonKey = selectedSmsAddonRadio.getAttribute('data-package-key');
                                            
                                            if (addonPrice > 0) {
                                                totalPrice += addonPrice;
                                                packageValue = packageKey + '_addon_' + selectedSmsAddonRadio.value;
                                            }
                                        }
                                        
                                        selectedPackageInput.value = packageValue;
                                        buttonText.textContent = 'Ödeme Yap (' + totalPrice.toLocaleString('tr-TR') + '₺)';
                                    }
                                }
                                
                                // İlk yüklemede fiyatı güncelle
                                updateBusinessPrice();
                                
                                // Süre değiştiğinde fiyatı güncelle
                                if (selectedDuration) {
                                    const packageKey = 'business_' + selectedDuration;
                                    
                                    // Fiyatları güncelle
                                    [1, 6, 12].forEach(months => {
                                        const packageKeyForMonths = 'business_' + months;
                                        if (allPackages[packageKeyForMonths]) {
                                            const pkg = allPackages[packageKeyForMonths];
                                            const priceEl = document.getElementById('price-' + months);
                                            const discountEl = document.getElementById('discount-' + months);
                                            
                                            priceEl.textContent = pkg.price.toLocaleString('tr-TR') + '₺';
                                            priceEl.className = 'text-lg font-bold text-indigo-600 dark:text-indigo-400';
                                            
                                            if (pkg.discount > 0) {
                                                discountEl.textContent = '%' + pkg.discount + ' indirim';
                                                discountEl.className = 'text-xs text-green-600 dark:text-green-400 mt-1';
                                            } else {
                                                discountEl.textContent = '';
                                            }
                                        }
                                    });
                                    
                                    // SMS addon seçildiğinde fiyatı güncelle
                                    updateBusinessPrice();
                                }
                            }
                        }
                        
                        tierRadios.forEach(radio => {
                            radio.addEventListener('change', updatePackageSelection);
                        });
                        
                        durationRadios.forEach(radio => {
                            radio.addEventListener('change', updatePackageSelection);
                        });
                        
                        smsAddonRadios.forEach(radio => {
                            radio.addEventListener('change', function() {
                                // SMS addon değiştiğinde fiyatı anlık güncelle
                                const selectedTier = form.querySelector('input[name="tier"]:checked')?.value;
                                if (selectedTier === 'business') {
                                    updatePackageSelection();
                                }
                            });
                        });
                        
                        // İlk yüklemede güncelle
                        updatePackageSelection();
                    });
                    </script>
            </div>
        </div>
    </div>
    <?php
}

