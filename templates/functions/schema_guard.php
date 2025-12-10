<?php

/**
 * Core schema guard – kritik tabloların varlığını garanti eder ve sağlık raporu döndürür.
 */

if (!function_exists('tpl_schema_require')) {
    function tpl_schema_require(string $relativePath, string $label): void
    {
        $path = __DIR__ . '/' . ltrim($relativePath, '/');
        if (function_exists('tpl_safe_require')) {
            tpl_safe_require($path, $label);
            return;
        }
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

tpl_schema_require('migrations/runner.php', 'migration runner');

if (!function_exists('tpl_ensure_core_tables')) {
    function tpl_ensure_core_tables(): array
    {
        static $status = null;
        if ($status !== null) {
            return $status;
        }

        $status = [
            'success' => true,
            'issues' => [],
            'domains' => [
                'finance' => ['ok' => true, 'message' => ''],
                'email' => ['ok' => true, 'message' => ''],
                'support' => ['ok' => true, 'message' => ''],
                'rate_limits' => ['ok' => true, 'message' => ''],
            ],
        ];

        try {
            $db = get_db();
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['issues'][] = 'Veritabanı erişimi başarısız: ' . $e->getMessage();
            foreach ($status['domains'] as &$domain) {
                $domain['ok'] = false;
                $domain['message'] = 'Veritabanı erişilemedi.';
            }
            return $status;
        }

        try {
            tpl_run_migrations($db);
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['issues'][] = 'Migration çalıştırılamadı: ' . $e->getMessage();
        }

        // Email & SMS tabloları
        try {
            if (function_exists('ensure_email_tables')) {
                ensure_email_tables($db);
            }
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['domains']['email']['ok'] = false;
            $status['domains']['email']['message'] = 'Email/SMS tabloları oluşturulamadı: ' . $e->getMessage();
            $status['issues'][] = $status['domains']['email']['message'];
        }

        // Finans tabloları
        try {
            tpl_schema_require('financial.php', 'financial functions');
            if (function_exists('financial_require_tables')) {
                financial_require_tables($db);
            }
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['domains']['finance']['ok'] = false;
            $status['domains']['finance']['message'] = 'Finans tabloları oluşturulamadı: ' . $e->getMessage();
            $status['issues'][] = $status['domains']['finance']['message'];
        }

        // Destek tabloları
        try {
            tpl_schema_require('support.php', 'support functions');
            if (function_exists('ensure_support_tickets_table')) {
                ensure_support_tickets_table($db);
            }
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['domains']['support']['ok'] = false;
            $status['domains']['support']['message'] = 'Destek tabloları oluşturulamadı: ' . $e->getMessage();
            $status['issues'][] = $status['domains']['support']['message'];
        }

        // Rate limit tablosu (genel amaçlı)
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                action_type TEXT NOT NULL,
                action_count INTEGER DEFAULT 0,
                hour_timestamp TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\Throwable $e) {
            $status['success'] = false;
            $status['domains']['rate_limits']['ok'] = false;
            $status['domains']['rate_limits']['message'] = 'Rate limit tablosu oluşturulamadı: ' . $e->getMessage();
            $status['issues'][] = $status['domains']['rate_limits']['message'];
        }

        return $status;
    }
}

