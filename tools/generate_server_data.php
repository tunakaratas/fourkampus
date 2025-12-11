<?php
/**
 * Sunucuya Veri Yükleme Script'i
 * Tüm topluluklara üye, etkinlik ve kampanya verileri ekler
 * Her birinden 30 adet ekler
 */

// CLI veya web'den çalışabilir
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Yol tanımlamaları
define('BASE_PATH', dirname(__DIR__));
define('COMMUNITIES_DIR', BASE_PATH . '/communities/');

// HTML başlığı (web için)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

// İsim listeleri
$firstNames = ['Ahmet', 'Mehmet', 'Ali', 'Ayşe', 'Fatma', 'Zeynep', 'Mustafa', 'Emre', 'Can', 'Burak', 'Deniz', 'Elif', 'Gizem', 'Hakan', 'İrem', 'Kemal', 'Leyla', 'Murat', 'Nazlı', 'Okan', 'Pınar', 'Rıza', 'Selin', 'Tolga', 'Umut', 'Veli', 'Yasin', 'Zehra'];
$lastNames = ['Yılmaz', 'Kaya', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir', 'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Öz', 'Avcı', 'Erdoğan', 'Yavuz', 'Aksoy', 'Bulut', 'Güneş'];

$eventTitles = [
    'Teknoloji Konferansı',
    'Yazılım Geliştirme Workshop',
    'Networking Etkinliği',
    'Kariyer Günleri',
    'Hackathon Yarışması',
    'Sosyal Sorumluluk Projesi',
    'Kültür Gezisi',
    'Spor Turnuvası',
    'Müzik Konseri',
    'Tiyatro Gösterisi',
    'Film Gösterimi',
    'Kitap Okuma Etkinliği',
    'Seminer',
    'Panel Tartışması',
    'Eğitim Atölyesi'
];

$campaignTitles = [
    'Öğrenci İndirimi',
    'Erken Kayıt Fırsatı',
    'Özel Kampanya',
    'Yıl Sonu İndirimi',
    'Üyelere Özel',
    'Sezon Açılışı',
    'Öğrenci Dostu Fiyat',
    'Toplu Alım İndirimi',
    'Referans Bonusu',
    'Sadakat Programı'
];

// İstatistikler
$processedCommunities = 0;
$addedMembers = 0;
$addedEvents = 0;
$addedCampaigns = 0;
$errors = [];

// Toplulukları bul
$communities = [];
if (is_dir(COMMUNITIES_DIR)) {
    $dirs = scandir(COMMUNITIES_DIR);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..' || $dir === 'index.php' || $dir === '.htaccess') {
            continue;
        }
        $communityPath = COMMUNITIES_DIR . $dir;
        if (is_dir($communityPath)) {
            $dbPath = $communityPath . '/unipanel.sqlite';
            if (file_exists($dbPath)) {
                $communities[] = $communityPath;
            }
        }
    }
}

if (empty($communities)) {
    echo "❌ Hiç topluluk bulunamadı!\n";
    exit(1);
}

echo "📊 Toplam " . count($communities) . " topluluk bulundu.\n";
echo "🚀 Veri yükleme başlıyor...\n\n";

// Her topluluk için veri ekle
foreach ($communities as $communityPath) {
    $communityName = basename($communityPath);
    $dbPath = $communityPath . '/unipanel.sqlite';
    
    if (!file_exists($dbPath)) {
        $errors[] = "$communityName: Veritabanı bulunamadı";
        continue;
    }
    
    $processedCommunities++;
    
    try {
        $db = new SQLite3($dbPath);
        $db->enableExceptions(true);
        
        echo "🔄 İşleniyor: $communityName\n";
        
        $club_id = 1;
        
        // Tabloları oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER,
            full_name TEXT,
            email TEXT,
            student_id TEXT,
            phone_number TEXT,
            registration_date TEXT,
            is_banned INTEGER DEFAULT 0,
            ban_reason TEXT
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            date TEXT NOT NULL,
            time TEXT,
            location TEXT,
            image_path TEXT,
            video_path TEXT,
            category TEXT DEFAULT 'Genel',
            status TEXT DEFAULT 'planlanıyor',
            priority TEXT DEFAULT 'normal',
            capacity INTEGER,
            registration_required INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            organizer TEXT,
            contact_email TEXT,
            contact_phone TEXT,
            tags TEXT,
            registration_deadline TEXT
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            offer_text TEXT NOT NULL,
            partner_name TEXT,
            discount_percentage INTEGER,
            image_path TEXT,
            start_date TEXT,
            end_date TEXT,
            campaign_code TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Üyeler ekle (30 adet)
        $memberStmt = $db->prepare("INSERT INTO members (club_id, full_name, email, student_id, phone_number, registration_date) VALUES (?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < 30; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $fullName = $firstName . ' ' . $lastName;
            $email = strtolower($firstName . '.' . $lastName . rand(100, 999) . '@example.com');
            $studentId = rand(100000, 999999);
            $phone = '05' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(10, 99) . ' ' . rand(10, 99);
            $regDate = date('Y-m-d', strtotime('-' . rand(0, 365) . ' days'));
            
            $memberStmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $memberStmt->bindValue(2, $fullName, SQLITE3_TEXT);
            $memberStmt->bindValue(3, $email, SQLITE3_TEXT);
            $memberStmt->bindValue(4, $studentId, SQLITE3_TEXT);
            $memberStmt->bindValue(5, $phone, SQLITE3_TEXT);
            $memberStmt->bindValue(6, $regDate, SQLITE3_TEXT);
            $memberStmt->execute();
            $addedMembers++;
        }
        echo "  ✓ 30 üye eklendi\n";
        
        // Etkinlikler ekle (30 adet)
        $eventStmt = $db->prepare("INSERT INTO events (club_id, title, description, date, time, location, category, status, organizer, capacity, registration_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < 30; $i++) {
            $title = $eventTitles[array_rand($eventTitles)] . ' ' . ($i + 1);
            $description = 'Bu etkinlik topluluğumuzun düzenlediği önemli bir organizasyondur. Tüm üyelerimiz davetlidir. Detaylı bilgi için iletişime geçebilirsiniz.';
            $date = date('Y-m-d', strtotime('+' . rand(1, 90) . ' days'));
            $time = sprintf('%02d:00', rand(9, 18));
            $locations = ['A101', 'B201', 'Konferans Salonu', 'Spor Salonu', 'Kafeterya', 'Açık Hava', 'Online'];
            $location = $locations[array_rand($locations)];
            $categories = ['Genel', 'Eğitim', 'Sosyal', 'Spor', 'Kültür', 'Teknoloji'];
            $category = $categories[array_rand($categories)];
            $statuses = ['planlanıyor', 'devam ediyor', 'tamamlandı'];
            $status = $statuses[array_rand($statuses)];
            $organizer = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $capacity = rand(20, 200);
            $registrationRequired = rand(0, 1);
            
            $eventStmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $eventStmt->bindValue(2, $title, SQLITE3_TEXT);
            $eventStmt->bindValue(3, $description, SQLITE3_TEXT);
            $eventStmt->bindValue(4, $date, SQLITE3_TEXT);
            $eventStmt->bindValue(5, $time, SQLITE3_TEXT);
            $eventStmt->bindValue(6, $location, SQLITE3_TEXT);
            $eventStmt->bindValue(7, $category, SQLITE3_TEXT);
            $eventStmt->bindValue(8, $status, SQLITE3_TEXT);
            $eventStmt->bindValue(9, $organizer, SQLITE3_TEXT);
            $eventStmt->bindValue(10, $capacity, SQLITE3_INTEGER);
            $eventStmt->bindValue(11, $registrationRequired, SQLITE3_INTEGER);
            $eventStmt->execute();
            $addedEvents++;
        }
        echo "  ✓ 30 etkinlik eklendi\n";
        
        // Kampanyalar ekle (30 adet)
        $campaignStmt = $db->prepare("INSERT INTO campaigns (club_id, title, description, offer_text, partner_name, discount_percentage, start_date, end_date, campaign_code, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < 30; $i++) {
            $title = $campaignTitles[array_rand($campaignTitles)] . ' ' . ($i + 1);
            $description = 'Özel kampanya fırsatı! Kaçırma! Bu kampanya sadece topluluk üyelerimize özeldir.';
            $offerText = '%' . rand(10, 50) . ' indirim fırsatı!';
            $partners = ['ABC Mağaza', 'XYZ Restoran', 'Tech Store', 'Book Shop', 'Sport Center', 'Cafe Central', 'Movie Theater'];
            $partnerName = $partners[array_rand($partners)];
            $discount = rand(10, 50);
            $startDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
            $endDate = date('Y-m-d', strtotime('+' . rand(1, 60) . ' days'));
            $campaignCode = strtoupper(substr($communityName, 0, 3)) . rand(1000, 9999);
            $isActive = rand(0, 1);
            
            $campaignStmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $campaignStmt->bindValue(2, $title, SQLITE3_TEXT);
            $campaignStmt->bindValue(3, $description, SQLITE3_TEXT);
            $campaignStmt->bindValue(4, $offerText, SQLITE3_TEXT);
            $campaignStmt->bindValue(5, $partnerName, SQLITE3_TEXT);
            $campaignStmt->bindValue(6, $discount, SQLITE3_INTEGER);
            $campaignStmt->bindValue(7, $startDate, SQLITE3_TEXT);
            $campaignStmt->bindValue(8, $endDate, SQLITE3_TEXT);
            $campaignStmt->bindValue(9, $campaignCode, SQLITE3_TEXT);
            $campaignStmt->bindValue(10, $isActive, SQLITE3_INTEGER);
            $campaignStmt->execute();
            $addedCampaigns++;
        }
        echo "  ✓ 30 kampanya eklendi\n";
        
        $db->close();
        echo "✅ $communityName tamamlandı\n\n";
        
    } catch (Exception $e) {
        $errors[] = "$communityName: " . $e->getMessage();
        echo "❌ $communityName hatası: " . $e->getMessage() . "\n\n";
        if (isset($db)) {
            $db->close();
        }
    }
}

// Özet
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 ÖZET\n";
echo str_repeat("=", 60) . "\n";
echo "İşlenen Topluluk: $processedCommunities\n";
echo "Eklenen Üye: $addedMembers\n";
echo "Eklenen Etkinlik: $addedEvents\n";
echo "Eklenen Kampanya: $addedCampaigns\n";

if (!empty($errors)) {
    echo "\n❌ Hatalar:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n✅ İşlem tamamlandı!\n";
