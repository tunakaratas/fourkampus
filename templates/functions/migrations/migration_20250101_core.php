<?php

return [
    'up' => function (SQLite3 $db) {
        $db->exec("CREATE TABLE IF NOT EXISTS financial_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            color TEXT DEFAULT '#6b7280',
            type TEXT NOT NULL CHECK(type IN ('income','expense')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS financial_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            category_id INTEGER,
            type TEXT NOT NULL CHECK(type IN ('income','expense')),
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            transaction_date TEXT NOT NULL,
            payment_method TEXT,
            reference_number TEXT,
            notes TEXT,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS budget_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            category_id INTEGER,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income','expense')),
            budgeted_amount REAL NOT NULL,
            period_start TEXT NOT NULL,
            period_end TEXT NOT NULL,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            event_id INTEGER,
            member_id INTEGER,
            member_name TEXT,
            member_email TEXT,
            amount REAL NOT NULL,
            payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending','paid','refunded','cancelled')),
            payment_method TEXT,
            payment_date TEXT,
            transaction_id TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS email_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            club_id INTEGER NOT NULL,
            recipient_email TEXT NOT NULL,
            recipient_name TEXT,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            attempts INTEGER DEFAULT 0,
            last_error TEXT,
            sent_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_bounced INTEGER DEFAULT 0,
            bounce_reason TEXT,
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            action_count INTEGER DEFAULT 0,
            hour_timestamp TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $columnExists = function (string $table, string $column) use ($db): bool {
            $info = $db->query("PRAGMA table_info($table)");
            if (!$info) {
                return false;
            }
            while ($row = $info->fetchArray(SQLITE3_ASSOC)) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        };

        $ensureColumn = function (string $table, string $column, string $definition) use ($db, $columnExists): void {
            if (!$columnExists($table, $column)) {
                $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        };

        $ensureColumn('email_campaigns', 'club_id', 'INTEGER DEFAULT 1');
        $ensureColumn('email_campaigns', 'sent_count', 'INTEGER DEFAULT 0');
        $ensureColumn('email_campaigns', 'status', "TEXT DEFAULT 'pending'");
        $ensureColumn('email_campaigns', 'started_at', 'DATETIME');
        $ensureColumn('email_campaigns', 'completed_at', 'DATETIME');

        $ensureColumn('email_queue', 'club_id', 'INTEGER DEFAULT 1');
        $ensureColumn('email_queue', 'attempts', 'INTEGER DEFAULT 0');
        $ensureColumn('email_queue', 'last_error', 'TEXT');
        $ensureColumn('email_queue', 'is_bounced', 'INTEGER DEFAULT 0');
        $ensureColumn('email_queue', 'bounce_reason', 'TEXT');

        $db->exec("CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_email_queue_campaign ON email_queue(campaign_id)");
    },
];

