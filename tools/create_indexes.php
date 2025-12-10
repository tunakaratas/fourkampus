<?php
/**
 * Database Index Oluşturma Scripti
 * Performans optimizasyonu için index'leri ekler
 */

require_once __DIR__ . '/../bootstrap/community_entry.php';

// SQLite3 constants
if (!defined('SQLITE3_INTEGER')) {
    define('SQLITE3_INTEGER', 1);
    define('SQLITE3_TEXT', 3);
    define('SQLITE3_REAL', 2);
    define('SQLITE3_BLOB', 4);
    define('SQLITE3_NULL', 5);
    define('SQLITE3_ASSOC', 1);
}

function createIndexes($db, $club_id) {
    $indexes = [
        // Events tablosu için index'ler
        "CREATE INDEX IF NOT EXISTS idx_events_club_date ON events(club_id, date DESC)",
        "CREATE INDEX IF NOT EXISTS idx_events_club_id ON events(club_id)",
        "CREATE INDEX IF NOT EXISTS idx_events_date ON events(date DESC)",
        "CREATE INDEX IF NOT EXISTS idx_events_status ON events(status)",
        
        // Members tablosu için index'ler
        "CREATE INDEX IF NOT EXISTS idx_members_club_reg ON members(club_id, registration_date DESC)",
        "CREATE INDEX IF NOT EXISTS idx_members_club_id ON members(club_id)",
        "CREATE INDEX IF NOT EXISTS idx_members_reg_date ON members(registration_date DESC)",
        "CREATE INDEX IF NOT EXISTS idx_members_email ON members(email)",
        
        // Board members tablosu için index'ler
        "CREATE INDEX IF NOT EXISTS idx_board_club_id ON board_members(club_id)",
        "CREATE INDEX IF NOT EXISTS idx_board_position ON board_members(position)",
        
        // Products tablosu için index'ler
        "CREATE INDEX IF NOT EXISTS idx_products_club_created ON products(club_id, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_products_club_id ON products(club_id)",
        "CREATE INDEX IF NOT EXISTS idx_products_created ON products(created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_products_status ON products(status)",
        "CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)",
        
        // Campaigns tablosu için index'ler
        "CREATE INDEX IF NOT EXISTS idx_campaigns_club_created ON campaigns(club_id, created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_club_id ON campaigns(club_id)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_created ON campaigns(created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status)",
    ];
    
    $created = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($indexes as $sql) {
        try {
            $result = @$db->exec($sql);
            if ($result !== false) {
                $created++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            $errors[] = $sql . " - " . $e->getMessage();
        }
    }
    
    return [
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

// Tek topluluk için çalıştır
if (defined('CLUB_ID') && defined('DB_PATH')) {
    try {
        $db = get_db();
        $result = createIndexes($db, CLUB_ID);
        
        echo "✅ Index'ler oluşturuldu!\n";
        echo "Oluşturulan: {$result['created']}\n";
        echo "Atlanan: {$result['skipped']}\n";
        
        if (!empty($result['errors'])) {
            echo "Hatalar:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Hata: " . $e->getMessage() . "\n";
    }
} else {
    // Tüm topluluklar için çalıştır
    $communities_dir = __DIR__ . '/../communities';
    $communities = glob($communities_dir . '/*', GLOB_ONLYDIR);
    
    $total_created = 0;
    $total_skipped = 0;
    $total_errors = 0;
    
    foreach ($communities as $community_path) {
        $community_name = basename($community_path);
        $db_path = $community_path . '/database.db';
        
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            $db = new SQLite3($db_path);
            $db->busyTimeout(5000);
            
            // Club ID'yi path'ten çıkar
            $club_id = 1; // Varsayılan, gerçekte settings'ten alınmalı
            
            $result = createIndexes($db, $club_id);
            $total_created += $result['created'];
            $total_skipped += $result['skipped'];
            $total_errors += count($result['errors']);
            
            $db->close();
            
            echo "✅ $community_name: {$result['created']} index oluşturuldu\n";
        } catch (Exception $e) {
            echo "❌ $community_name: " . $e->getMessage() . "\n";
            $total_errors++;
        }
    }
    
    echo "\n📊 Özet:\n";
    echo "Toplam oluşturulan: $total_created\n";
    echo "Toplam atlanan: $total_skipped\n";
    echo "Toplam hata: $total_errors\n";
}

