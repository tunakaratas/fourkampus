<?php
/**
 * SuperAdmin Configuration File
 * All secrets must be provided via environment variables.
 */

// .env dosyasını yükle (varsa)
require_once __DIR__ . '/load_env.php';

function superadmin_config_env(string $key, bool $required = false, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        // Environment variable yoksa, config/credentials.php'den oku
        static $credentials = null;
        if ($credentials === null) {
            $credentials_path = __DIR__ . '/../config/credentials.php';
            if (file_exists($credentials_path)) {
                $credentials = require $credentials_path;
            } else {
                $credentials = [];
            }
        }
        
        // NetGSM için config dosyasından oku
        if (strpos($key, 'NETGSM_') === 0) {
            $config_key = strtolower(str_replace('NETGSM_', '', $key));
            if ($config_key === 'user' && isset($credentials['netgsm']['username'])) {
                return $credentials['netgsm']['username'];
            }
            if ($config_key === 'pass' && isset($credentials['netgsm']['password'])) {
                return $credentials['netgsm']['password'];
            }
            if ($config_key === 'header' && isset($credentials['netgsm']['msgheader'])) {
                return $credentials['netgsm']['msgheader'];
            }
        }
        
        // SUPERADMIN_PHONE için config dosyasından oku
        if ($key === 'SUPERADMIN_PHONE') {
            // Önce credentials.php'den oku (eğer varsa)
            if (isset($credentials['superadmin']['phone_number'])) {
                return $credentials['superadmin']['phone_number'];
            }
            // Varsayılan telefon numarası (kullanıcının telefon numarası)
            return '5428055983';
        }
        
        if ($required) {
            throw new RuntimeException(sprintf('Missing required environment variable: %s', $key));
        }
        return $default;
    }
    return $value;
}

return [
    // NetGSM Credentials
    'netgsm' => [
        'user' => superadmin_config_env('NETGSM_USER', false, ''),
        'pass' => superadmin_config_env('NETGSM_PASS', false, ''),
        'header' => superadmin_config_env('NETGSM_HEADER', false, ''),
    ],

    // Twilio Credentials (Optional)
    'twilio' => [
        'sid' => superadmin_config_env('TWILIO_ACCOUNT_SID', false, ''),
        'token' => superadmin_config_env('TWILIO_AUTH_TOKEN', false, ''),
        'from' => superadmin_config_env('TWILIO_FROM_NUMBER', false, ''),
        'messaging_sid' => superadmin_config_env('TWILIO_MESSAGING_SERVICE_SID', false, ''),
    ],

    // SuperAdmin Settings
    'superadmin' => [
        'phone_number' => superadmin_config_env('SUPERADMIN_PHONE', false, ''),
        'sms_provider' => superadmin_config_env('SUPERADMIN_SMS_PROVIDER', false, 'netgsm'),
    ],

    // Security Settings
    'security' => [
        'allowed_ips' => array_filter(array_map('trim', explode(',', superadmin_config_env('SUPERADMIN_ALLOWED_IPS', false, '127.0.0.1,::1')))),
        'session_lifetime' => (int) superadmin_config_env('SUPERADMIN_SESSION_LIFETIME', false, 60 * 60 * 24 * 7),
        'idle_timeout' => (int) superadmin_config_env('SUPERADMIN_IDLE_TIMEOUT', false, 60 * 60 * 24 * 7),
    ]
];
