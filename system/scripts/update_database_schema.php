<?php
// Yeni tabloları tüm topluluklara ekle
require_once __DIR__ . '/security_helper.php';

// CLI kontrolü
requireCLI();

error_reporting(E_ALL);
ini_set('display_errors', isProduction() ? 0 : 1);

$root = realpath(__DIR__ . '/..' . '/..');
$communitiesDir = $root . '/communities';

function updateDatabaseSchema($dbPath, $clubId) {
    if (!file_exists($dbPath)) {
        echo "skip (db yok): $dbPath\n";
        return;
    }
    
    try {
        $db = new SQLite3($dbPath);
        $db->exec('PRAGMA journal_mode = WAL');
        
        // Rate Limiting Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            action_count INTEGER DEFAULT 0,
            hour_timestamp TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Event RSVP Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS event_rsvp (
            id INTEGER PRIMARY KEY,
            event_id INTEGER NOT NULL,
            club_id INTEGER NOT NULL,
            member_name TEXT NOT NULL,
            member_email TEXT NOT NULL,
            member_phone TEXT,
            rsvp_status TEXT DEFAULT 'attending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
        
        // Email Campaigns Tablosu (Toplu mail kampanyaları)
        $db->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            from_name TEXT,
            from_email TEXT,
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            failed_count INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME
        )");
        
        // Email Queue Tablosu (Mail gönderim kuyruğu)
        $db->exec("CREATE TABLE IF NOT EXISTS email_queue (
            id INTEGER PRIMARY KEY,
            campaign_id INTEGER NOT NULL,
            club_id INTEGER NOT NULL,
            recipient_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            from_name TEXT,
            from_email TEXT,
            status TEXT DEFAULT 'pending',
            attempts INTEGER DEFAULT 0,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME,
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
        )");
        
        // Financial Categories Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS financial_categories (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            description TEXT,
            color TEXT DEFAULT '#3b82f6',
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Financial Transactions Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS financial_transactions (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            category_id INTEGER,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            transaction_date TEXT NOT NULL,
            payment_method TEXT,
            reference_number TEXT,
            notes TEXT,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE SET NULL
        )");
        
        // Budget Plans Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS budget_plans (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            category_id INTEGER,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            budgeted_amount REAL NOT NULL,
            period_start TEXT NOT NULL,
            period_end TEXT NOT NULL,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE SET NULL
        )");
        
        // Payments Tablosu
        $db->exec("CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY,
            club_id INTEGER NOT NULL,
            event_id INTEGER,
            member_id INTEGER,
            member_name TEXT,
            member_email TEXT,
            amount REAL NOT NULL,
            payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending', 'paid', 'refunded', 'cancelled')),
            payment_method TEXT,
            payment_date TEXT,
            transaction_id TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
        )");
        
        // Varsayılan kategorileri ekle (eğer yoksa)
        $stmt = $db->prepare("SELECT COUNT(*) FROM financial_categories WHERE club_id = ?");
        $stmt->bindValue(1, $clubId, SQLITE3_INTEGER);
        $count = $stmt->execute()->fetchArray()[0];
        
        if ($count == 0) {
            // Gelir Kategorileri
            $income_categories = [
                ['Üye Aidatları', '#10b981'],
                ['Etkinlik Gelirleri', '#3b82f6'],
                ['Bağışlar', '#8b5cf6'],
                ['Sponsorluk', '#f59e0b'],
                ['Diğer Gelirler', '#6b7280']
            ];
            
            // Gider Kategorileri
            $expense_categories = [
                ['Etkinlik Giderleri', '#ef4444'],
                ['Ofis Malzemeleri', '#f97316'],
                ['Ulaşım', '#eab308'],
                ['Yemek & İkram', '#ec4899'],
                ['İletişim', '#14b8a6'],
                ['Diğer Giderler', '#6b7280']
            ];
            
            $stmt = $db->prepare("INSERT INTO financial_categories (club_id, name, type, color) VALUES (?, ?, 'income', ?)");
            foreach ($income_categories as $cat) {
                $stmt->bindValue(1, $clubId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $cat[0], SQLITE3_TEXT);
                $stmt->bindValue(3, $cat[1], SQLITE3_TEXT);
                $stmt->execute();
            }
            
            $stmt = $db->prepare("INSERT INTO financial_categories (club_id, name, type, color) VALUES (?, ?, 'expense', ?)");
            foreach ($expense_categories as $cat) {
                $stmt->bindValue(1, $clubId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $cat[0], SQLITE3_TEXT);
                $stmt->bindValue(3, $cat[1], SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        
        // Events tablosuna yeni kolonları ekle
        $new_columns = [
            'category' => 'TEXT',
            'status' => 'TEXT',
            'priority' => 'TEXT',
            'capacity' => 'INTEGER',
            'registration_required' => 'INTEGER',
            'registration_deadline' => 'TEXT',
            'start_datetime' => 'TEXT',
            'end_datetime' => 'TEXT',
            'organizer' => 'TEXT',
            'contact_email' => 'TEXT',
            'contact_phone' => 'TEXT',
            'tags' => 'TEXT',
            'visibility' => 'TEXT',
            'featured' => 'INTEGER',
            'external_link' => 'TEXT',
            'cost' => 'REAL',
            'max_attendees' => 'INTEGER',
            'min_attendees' => 'INTEGER',
            'created_at' => 'DATETIME',
            'updated_at' => 'DATETIME'
        ];
        
        foreach ($new_columns as $column => $definition) {
            try {
                $db->exec("ALTER TABLE events ADD COLUMN $column $definition");
            } catch (Exception $e) {
                // Kolon zaten varsa hata vermez
            }
        }
        
        echo "ok: $dbPath -> Tablolar ve kolonlar eklendi\n";
        $db->close();
    } catch (Exception $e) {
        handleError("Database schema update failed for $dbPath", $e);
    }
}

if (!is_dir($communitiesDir)) {
    fwrite(STDERR, "Communities klasörü bulunamadı: $communitiesDir\n");
    exit(1);
}

$dirs = scandir($communitiesDir);
foreach ($dirs as $d) {
    if ($d === '.' || $d === '..' || $d === 'public') continue;
    $dbPath = $communitiesDir . '/' . $d . '/unipanel.sqlite';
    updateDatabaseSchema($dbPath, 1);
}

echo "Tamamlandı.\n";
?>

