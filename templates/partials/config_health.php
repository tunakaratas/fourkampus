<?php

if (!function_exists('tpl_health_flag_file')) {
    function tpl_health_flag_file(): string
    {
        $dir = sys_get_temp_dir() . '/unipanel_flags';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/config_health.json';
    }
}

if (!function_exists('tpl_should_log_config_issue')) {
    function tpl_should_log_config_issue(string $key, int $ttlSeconds = 900): bool
    {
        $file = tpl_health_flag_file();
        $flags = [];
        if (file_exists($file)) {
            $json = @file_get_contents($file);
            $flags = json_decode($json, true) ?: [];
        }

        $now = time();
        if (isset($flags[$key]) && ($now - $flags[$key]) < $ttlSeconds) {
            return false;
        }

        $flags[$key] = $now;
        @file_put_contents($file, json_encode($flags));
        return true;
    }
}

if (!function_exists('tpl_get_comm_config_health')) {
    function tpl_get_comm_config_health(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [
            'smtp' => [
                'configured' => true,
                'issues' => [],
            ],
            'sms' => [
                'configured' => true,
                'issues' => [],
            ],
        ];

        $smtp_username = get_setting('smtp_username', '');
        $smtp_password = get_setting('smtp_password', '');
        $smtp_host = get_setting('smtp_host', '');
        $smtp_port = get_setting('smtp_port', '');

        if (!$smtp_username) {
            $cache['smtp']['configured'] = false;
            $cache['smtp']['issues'][] = 'SMTP kullanıcı adı girilmemiş.';
        }
        if (!$smtp_password) {
            $cache['smtp']['configured'] = false;
            $cache['smtp']['issues'][] = 'SMTP şifresi girilmemiş.';
        }
        if (!$smtp_host || !$smtp_port) {
            $cache['smtp']['configured'] = false;
            $cache['smtp']['issues'][] = 'SMTP sunucu adresi/portu eksik.';
        }

        if (!$cache['smtp']['configured'] && tpl_should_log_config_issue('smtp_missing')) {
            tpl_error_log('SMTP config eksik: host=' . ($smtp_host ?: 'EMPTY') . ', port=' . ($smtp_port ?: 'EMPTY') . ', username=' . ($smtp_username ? 'SET' : 'EMPTY'), 'warning');
        }

        // SMS entegrasyonu kontrolü - Sadece Business paket için gerekli
        // Business paket değilse SMS entegrasyonu uyarısı gösterme
        $isBusinessPackage = false;
        try {
            if (function_exists('has_subscription_feature')) {
                require_once __DIR__ . '/../../lib/general/subscription_helper.php';
            }
            if (defined('COMMUNITY_ID') && COMMUNITY_ID) {
                require_once __DIR__ . '/../../lib/payment/SubscriptionManager.php';
                $db = get_db();
                $subscriptionManager = new \UniPanel\Payment\SubscriptionManager($db, COMMUNITY_ID);
                $currentTier = $subscriptionManager->getCurrentTier();
                $isBusinessPackage = (strtolower($currentTier) === 'business');
            }
        } catch (Exception $e) {
            // Hata durumunda sessizce devam et
        }
        
        // Sadece Business paket için SMS kontrolü yap
        if ($isBusinessPackage) {
            $sms_provider = get_setting('sms_provider', 'netgsm');
            if ($sms_provider === 'netgsm') {
                $netgsm_username = get_setting('netgsm_username', '');
                $netgsm_password = get_setting('netgsm_password', '');
                if (!$netgsm_username) {
                    $cache['sms']['configured'] = false;
                    $cache['sms']['issues'][] = 'NetGSM kullanıcı adı girilmemiş.';
                }
                if (!$netgsm_password) {
                    $cache['sms']['configured'] = false;
                    $cache['sms']['issues'][] = 'NetGSM şifresi girilmemiş.';
                }
            } else {
                $twilio_sid = get_setting('twilio_account_sid', '');
                $twilio_token = get_setting('twilio_auth_token', '');
                if (!$twilio_sid || !$twilio_token) {
                    $cache['sms']['configured'] = false;
                    $cache['sms']['issues'][] = 'Twilio SID/Token eksik.';
                }
            }

            if (!$cache['sms']['configured'] && tpl_should_log_config_issue('sms_missing')) {
                tpl_error_log('SMS config eksik: provider=' . $sms_provider, 'warning');
            }
        } else {
            // Business paket değilse SMS entegrasyonu her zaman configured olarak işaretle (uyarı gösterme)
            $cache['sms']['configured'] = true;
            $cache['sms']['issues'] = [];
        }

        return $cache;
    }
}

