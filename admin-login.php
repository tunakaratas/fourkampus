<?php
// Admin Panel Giriş - Üniversite Seçimi ve Topluluk Listesi
session_start();

// Üniversiteye göre toplulukları getir
function getCommunitiesByUniversity($university) {
    $communities = [];
    $communities_dir = __DIR__ . '/communities';
    
    if (!is_dir($communities_dir) || empty($university)) {
        return [];
    }
    
    $dirs = scandir($communities_dir);
    $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    
    foreach ($dirs as $dir) {
        if (in_array($dir, $excluded_dirs) || !is_dir($communities_dir . '/' . $dir)) {
            continue;
        }
        
        $db_path = $communities_dir . '/' . $dir . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            $db = new SQLite3($db_path);
            $stored_university = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'university'");
            $club_name = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'club_name'") ?: ucwords(str_replace('_', ' ', $dir));
            $community_code = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'community_code'") ?: '';
            $db->close();
            
            if ($stored_university === $university) {
                $communities[] = [
                    'folder' => $dir,
                    'name' => $club_name,
                    'code' => $community_code,
                    'university' => $stored_university
                ];
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    // İsme göre sırala
    usort($communities, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $communities;
}

// Koda göre topluluk ara (tüm üniversitelerde)
function searchCommunityByCode($code) {
    $communities = [];
    $communities_dir = __DIR__ . '/communities';
    
    if (!is_dir($communities_dir) || empty($code)) {
        return [];
    }
    
    $code = strtoupper(trim($code));
    $dirs = scandir($communities_dir);
    $excluded_dirs = ['.', '..', 'assets', 'public', 'templates', 'system', 'docs'];
    
    foreach ($dirs as $dir) {
        if (in_array($dir, $excluded_dirs) || !is_dir($communities_dir . '/' . $dir)) {
            continue;
        }
        
        $db_path = $communities_dir . '/' . $dir . '/unipanel.sqlite';
        if (!file_exists($db_path)) {
            continue;
        }
        
        try {
            $db = new SQLite3($db_path);
            $stored_code = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'community_code'");
            $club_name = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'club_name'") ?: ucwords(str_replace('_', ' ', $dir));
            $stored_university = $db->querySingle("SELECT setting_value FROM settings WHERE setting_key = 'university'") ?: '';
            $db->close();
            
            if ($stored_code === $code) {
                $communities[] = [
                    'folder' => $dir,
                    'name' => $club_name,
                    'code' => $stored_code,
                    'university' => $stored_university
                ];
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return $communities;
}

// Türkiye'deki üniversiteler listesi
$universities = [
    'Bandırma 17 Eylül Üniversitesi',
    'İstanbul Üniversitesi',
    'Ankara Üniversitesi',
    'Hacettepe Üniversitesi',
    'Boğaziçi Üniversitesi',
    'Orta Doğu Teknik Üniversitesi',
    'İstanbul Teknik Üniversitesi',
    'Gazi Üniversitesi',
    'Ege Üniversitesi',
    'Dokuz Eylül Üniversitesi',
    'Marmara Üniversitesi',
    'Yıldız Teknik Üniversitesi',
    'Anadolu Üniversitesi',
    'Selçuk Üniversitesi',
    'Akdeniz Üniversitesi',
    'Çukurova Üniversitesi',
    'Erciyes Üniversitesi',
    'Uludağ Üniversitesi',
    'Atatürk Üniversitesi',
    'Ondokuz Mayıs Üniversitesi',
    'Karadeniz Teknik Üniversitesi',
    'Pamukkale Üniversitesi',
    'Süleyman Demirel Üniversitesi',
    'Kocaeli Üniversitesi',
    'Sakarya Üniversitesi',
    'Trakya Üniversitesi',
    'Çanakkale Onsekiz Mart Üniversitesi',
    'Balıkesir Üniversitesi',
    'Adnan Menderes Üniversitesi',
    'Muğla Sıtkı Koçman Üniversitesi',
    'Bursa Teknik Üniversitesi',
    'İzmir Yüksek Teknoloji Enstitüsü',
    'Gebze Teknik Üniversitesi',
    'Sabancı Üniversitesi',
    'Koç Üniversitesi',
    'Bilkent Üniversitesi',
    'Özyeğin Üniversitesi',
    'Bahçeşehir Üniversitesi',
    'İstanbul Bilgi Üniversitesi',
    'İstanbul Kültür Üniversitesi',
    'Yeditepe Üniversitesi',
    'Maltepe Üniversitesi',
    'Kadir Has Üniversitesi',
    'İstanbul Aydın Üniversitesi',
    'Altınbaş Üniversitesi',
    'İstanbul Medipol Üniversitesi',
    'Acıbadem Üniversitesi',
    'Bezmialem Vakıf Üniversitesi',
    'İstanbul Şehir Üniversitesi',
    'Nişantaşı Üniversitesi',
    'İstanbul Okan Üniversitesi',
    'İstanbul Gelişim Üniversitesi',
    'İstanbul Esenyurt Üniversitesi',
    'İstanbul Kent Üniversitesi',
    'İstanbul Rumeli Üniversitesi',
    'İstanbul Yeni Yüzyıl Üniversitesi',
    'İstanbul Sabahattin Zaim Üniversitesi',
    'İstanbul Ticaret Üniversitesi',
    'İstanbul Üniversitesi-Cerrahpaşa',
    'Ankara Yıldırım Beyazıt Üniversitesi',
    'Atılım Üniversitesi',
    'Başkent Üniversitesi',
    'Çankaya Üniversitesi',
    'Hacettepe Üniversitesi',
    'Orta Doğu Teknik Üniversitesi',
    'TED Üniversitesi',
    'TOBB Ekonomi ve Teknoloji Üniversitesi',
    'Ufuk Üniversitesi',
    'Yüksek İhtisas Üniversitesi',
    'Ankara Bilim Üniversitesi',
    'Ankara Hacı Bayram Veli Üniversitesi',
    'Ankara Medipol Üniversitesi',
    'Ankara Sosyal Bilimler Üniversitesi',
    'Ankara Üniversitesi',
    'Gazi Üniversitesi',
    'Hacettepe Üniversitesi',
    'Lokman Hekim Üniversitesi',
    'Ostim Teknik Üniversitesi',
    'Türk Hava Kurumu Üniversitesi',
    'Yıldırım Beyazıt Üniversitesi',
    'İzmir Ekonomi Üniversitesi',
    'İzmir Katip Çelebi Üniversitesi',
    'İzmir Yüksek Teknoloji Enstitüsü',
    'Dokuz Eylül Üniversitesi',
    'Ege Üniversitesi',
    'Yaşar Üniversitesi',
    'İzmir Bakırçay Üniversitesi',
    'İzmir Demokrasi Üniversitesi',
    'İzmir Kâtip Çelebi Üniversitesi',
    'İzmir Tınaztepe Üniversitesi',
    'İzmir Üniversitesi',
    'İzmir Yüksek Teknoloji Enstitüsü',
    'Manisa Celal Bayar Üniversitesi',
    'Pamukkale Üniversitesi',
    'Uşak Üniversitesi',
    'Aydın Adnan Menderes Üniversitesi',
    'Muğla Sıtkı Koçman Üniversitesi',
    'Denizli Pamukkale Üniversitesi',
    'Afyon Kocatepe Üniversitesi',
    'Kütahya Dumlupınar Üniversitesi',
    'Eskişehir Osmangazi Üniversitesi',
    'Eskişehir Teknik Üniversitesi',
    'Anadolu Üniversitesi',
    'Bilecik Şeyh Edebali Üniversitesi',
    'Bolu Abant İzzet Baysal Üniversitesi',
    'Düzce Üniversitesi',
    'Karabük Üniversitesi',
    'Zonguldak Bülent Ecevit Üniversitesi',
    'Bartın Üniversitesi',
    'Kastamonu Üniversitesi',
    'Sinop Üniversitesi',
    'Amasya Üniversitesi',
    'Çorum Hitit Üniversitesi',
    'Tokat Gaziosmanpaşa Üniversitesi',
    'Sivas Cumhuriyet Üniversitesi',
    'Yozgat Bozok Üniversitesi',
    'Kırıkkale Üniversitesi',
    'Nevşehir Hacı Bektaş Veli Üniversitesi',
    'Niğde Ömer Halisdemir Üniversitesi',
    'Aksaray Üniversitesi',
    'Kırşehir Ahi Evran Üniversitesi',
    'Kayseri Erciyes Üniversitesi',
    'Kayseri Abdullah Gül Üniversitesi',
    'Kayseri Melikşah Üniversitesi',
    'Nevşehir Hacı Bektaş Veli Üniversitesi',
    'Adana Çukurova Üniversitesi',
    'Mersin Üniversitesi',
    'Hatay Mustafa Kemal Üniversitesi',
    'Kahramanmaraş Sütçü İmam Üniversitesi',
    'Osmaniye Korkut Ata Üniversitesi',
    'Gaziantep Üniversitesi',
    'Gaziantep İslam Bilim ve Teknoloji Üniversitesi',
    'Şanlıurfa Harran Üniversitesi',
    'Diyarbakır Dicle Üniversitesi',
    'Mardin Artuklu Üniversitesi',
    'Batman Üniversitesi',
    'Siirt Üniversitesi',
    'Şırnak Üniversitesi',
    'Hakkari Üniversitesi',
    'Van Yüzüncü Yıl Üniversitesi',
    'Muş Alparslan Üniversitesi',
    'Bitlis Eren Üniversitesi',
    'Ağrı İbrahim Çeçen Üniversitesi',
    'Iğdır Üniversitesi',
    'Ardahan Üniversitesi',
    'Kars Kafkas Üniversitesi',
    'Erzurum Atatürk Üniversitesi',
    'Erzincan Binali Yıldırım Üniversitesi',
    'Bayburt Üniversitesi',
    'Gümüşhane Üniversitesi',
    'Rize Recep Tayyip Erdoğan Üniversitesi',
    'Artvin Çoruh Üniversitesi',
    'Trabzon Karadeniz Teknik Üniversitesi',
    'Giresun Üniversitesi',
    'Ordu Üniversitesi',
    'Samsun Ondokuz Mayıs Üniversitesi',
    'Amasya Üniversitesi',
    'Çorum Hitit Üniversitesi',
    'Kastamonu Üniversitesi',
    'Sinop Üniversitesi',
    'Bolu Abant İzzet Baysal Üniversitesi',
    'Düzce Üniversitesi',
    'Zonguldak Bülent Ecevit Üniversitesi',
    'Karabük Üniversitesi',
    'Bartın Üniversitesi',
    'Sakarya Üniversitesi',
    'Kocaeli Üniversitesi',
    'Bursa Uludağ Üniversitesi',
    'Bursa Teknik Üniversitesi',
    'Balıkesir Üniversitesi',
    'Çanakkale Onsekiz Mart Üniversitesi',
    'Edirne Trakya Üniversitesi',
    'Tekirdağ Namık Kemal Üniversitesi',
    'Kırklareli Üniversitesi',
    'İstanbul Üniversitesi',
    'İstanbul Teknik Üniversitesi',
    'Boğaziçi Üniversitesi',
    'Yıldız Teknik Üniversitesi',
    'Marmara Üniversitesi',
    'Galatasaray Üniversitesi',
    'İstanbul Üniversitesi-Cerrahpaşa',
    'İstanbul Medeniyet Üniversitesi',
    'İstanbul Sabahattin Zaim Üniversitesi',
    'İstanbul Ticaret Üniversitesi',
    'İstanbul Arel Üniversitesi',
    'İstanbul Aydın Üniversitesi',
    'İstanbul Bilgi Üniversitesi',
    'İstanbul Esenyurt Üniversitesi',
    'İstanbul Gelişim Üniversitesi',
    'İstanbul Kültür Üniversitesi',
    'İstanbul Medipol Üniversitesi',
    'İstanbul Okan Üniversitesi',
    'İstanbul Rumeli Üniversitesi',
    'İstanbul Şehir Üniversitesi',
    'İstanbul Yeni Yüzyıl Üniversitesi',
    'Kadir Has Üniversitesi',
    'Maltepe Üniversitesi',
    'Nişantaşı Üniversitesi',
    'Özyeğin Üniversitesi',
    'Sabancı Üniversitesi',
    'Yeditepe Üniversitesi',
    'Acıbadem Üniversitesi',
    'Altınbaş Üniversitesi',
    'Bahçeşehir Üniversitesi',
    'Bezmialem Vakıf Üniversitesi',
    'Beykent Üniversitesi',
    'Biruni Üniversitesi',
    'Doğuş Üniversitesi',
    'Fatih Sultan Mehmet Vakıf Üniversitesi',
    'Haliç Üniversitesi',
    'Işık Üniversitesi',
    'İbn Haldun Üniversitesi',
    'İstanbul 29 Mayıs Üniversitesi',
    'İstanbul Atlas Üniversitesi',
    'İstanbul Ayvansaray Üniversitesi',
    'İstanbul Esenyurt Üniversitesi',
    'İstanbul Gedik Üniversitesi',
    'İstanbul Kavram Meslek Yüksekokulu',
    'İstanbul Kent Üniversitesi',
    'İstanbul Nisantasi Üniversitesi',
    'İstanbul Sağlık ve Teknoloji Üniversitesi',
    'İstanbul Ticaret Üniversitesi',
    'İstanbul Topkapı Üniversitesi',
    'İstanbul Üniversitesi',
    'Koç Üniversitesi',
    'MEF Üniversitesi',
    'Piri Reis Üniversitesi',
    'Üsküdar Üniversitesi',
    'Yeditepe Üniversitesi',
    'Zaim Üniversitesi',
    'Diğer'
];

$error = '';
$selected_university = $_GET['university'] ?? $_POST['university'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$code_search = trim(strtoupper($_GET['code'] ?? ''));
$communities = [];
$search_mode = 'university'; // 'university' veya 'code'

// Kod ile arama yapılıyorsa
if (!empty($code_search)) {
    $search_mode = 'code';
    $communities = searchCommunityByCode($code_search);
} elseif (!empty($selected_university)) {
    // Üniversite seçilmişse
    $search_mode = 'university';
    $communities = getCommunitiesByUniversity($selected_university);
    
    // Arama filtresi (sadece isim/kod araması)
    if (!empty($search_query)) {
        $search_query_lower = mb_strtolower($search_query, 'UTF-8');
        $communities = array_filter($communities, function($community) use ($search_query_lower) {
            return strpos(mb_strtolower($community['name'], 'UTF-8'), $search_query_lower) !== false ||
                   strpos(mb_strtolower($community['code'], 'UTF-8'), $search_query_lower) !== false;
        });
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel Giriş - UniPanel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
            <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --error-color: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            padding: 0;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .hero-header {
            background: linear-gradient(135deg, #eef2ff, #f8fafc);
            border-bottom: 1px solid var(--border-color);
            padding: 36px 0 48px;
            position: relative;
            overflow: hidden;
        }
        .hero-header::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            right: -80px;
            top: -80px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25), transparent 60%);
            pointer-events: none;
        }
        .hero-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }
        .hero-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            flex-wrap: wrap;
        }
        .hero-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .hero-logo {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25);
            font-size: 22px;
        }
        .hero-brand-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .hero-brand-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .hero-links {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .hero-link {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 0;
            transition: color 0.2s;
        }
        .hero-link:hover {
            color: var(--primary-color);
        }
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            padding: 12px 20px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .action-button i {
            font-size: 14px;
        }
        .action-button.ghost {
            background: rgba(99, 102, 241, 0.08);
            color: var(--primary-color);
            border-color: rgba(99, 102, 241, 0.15);
        }
        .action-button.ghost:hover {
            background: rgba(99, 102, 241, 0.15);
        }
        .hero-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 32px;
            padding-top: 32px;
            flex-wrap: wrap;
        }
        .hero-text {
            flex: 1;
            min-width: 260px;
        }
        .hero-eyebrow {
            display: inline-block;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.16);
            border-radius: 999px;
            margin-bottom: 12px;
        }
        .hero-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .hero-description {
            font-size: 15px;
            color: var(--text-secondary);
            max-width: 460px;
        }
        .hero-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 20px 24px;
            min-width: 280px;
            max-width: 360px;
            box-shadow: var(--shadow-md);
        }
        .hero-card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .hero-card-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .hero-card-list li {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .hero-card-list li i {
            color: var(--primary-color);
            font-size: 13px;
        }
        .top-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 0;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .top-header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .back-button:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
            border-color: var(--border-color);
        }
        .back-button i {
            font-size: 12px;
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #8b5cf6 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header-section {
            text-align: center;
            margin-bottom: 48px;
        }
        .header-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .header-subtitle {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        .filter-section {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 28px;
            margin-bottom: 32px;
            border: 1px solid var(--border-color);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .communities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .community-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .community-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .community-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        .community-card:hover::before {
            transform: scaleX(1);
        }
        .community-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #8b5cf6 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 16px;
        }
        .community-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .community-code {
            font-size: 12px;
            color: var(--text-secondary);
            font-family: 'Courier New', monospace;
            background: var(--bg-secondary);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        .empty-state-icon {
            font-size: 64px;
            color: var(--text-light);
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state-text {
            font-size: 16px;
            color: var(--text-secondary);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.2s;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error-color);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .error-message i {
            font-size: 16px;
        }
        .btn-primary {
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .footer-section {
            margin-top: 60px;
            padding-top: 40px;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .footer-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        .footer-link:hover {
            color: var(--primary-color);
        }
        .form-input select {
            cursor: pointer;
        }
        .relative {
            position: relative;
        }
        .absolute {
            position: absolute;
        }
        .search-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 16px;
        }
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        .stats-text {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .stats-count {
            font-weight: 600;
            color: var(--primary-color);
        }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .breadcrumb a:hover {
            color: var(--primary-color);
        }
        .breadcrumb-separator {
            color: var(--text-light);
        }
        .search-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        .search-tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: -2px;
        }
        .search-tab:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
        }
        .search-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        .search-tab i {
            font-size: 14px;
        }
        .community-university {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .community-university i {
            font-size: 10px;
        }
        @media (max-width: 768px) {
            .communities-grid {
                grid-template-columns: 1fr;
            }
            .header-title {
                font-size: 28px;
            }
            .page-container {
                padding: 24px 16px;
            }
            .hero-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .hero-links {
                width: 100%;
                padding-top: 4px;
            }
            .hero-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .hero-body {
                flex-direction: column;
                align-items: flex-start;
            }
            .hero-card {
                width: 100%;
            }
            .hero-title {
                font-size: 28px;
            }
        }
    </style>
    <script>
        // Arama modu değiştirme
        function switchSearchMode(mode) {
            const universityForm = document.getElementById('universityForm');
            const codeForm = document.getElementById('codeForm');
            const tabUniversity = document.getElementById('tab-university');
            const tabCode = document.getElementById('tab-code');
            
            if (mode === 'university') {
                universityForm.style.display = 'block';
                codeForm.style.display = 'none';
                tabUniversity.classList.add('active');
                tabCode.classList.remove('active');
            } else {
                universityForm.style.display = 'none';
                codeForm.style.display = 'block';
                tabUniversity.classList.remove('active');
                tabCode.classList.add('active');
                document.getElementById('code').focus();
            }
        }
        
        // Sayfa yüklendiğinde modu ayarla
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const mode = urlParams.get('mode') || '<?= $search_mode ?>';
            if (mode === 'code') {
                switchSearchMode('code');
            }
        });
        
        function filterCommunities() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            
            const searchQuery = searchInput.value.toLowerCase();
            const communityCards = document.querySelectorAll('.community-card');
            let visibleCount = 0;
            
            communityCards.forEach(card => {
                const name = card.querySelector('.community-name').textContent.toLowerCase();
                const codeElement = card.querySelector('.community-code');
                const code = codeElement ? codeElement.textContent.toLowerCase() : '';
                const matches = name.includes(searchQuery) || code.includes(searchQuery);
                
                card.style.display = matches ? 'block' : 'none';
                if (matches) visibleCount++;
            });
            
            // Sonuç sayısını güncelle
            const countElement = document.querySelector('.stats-count');
            if (countElement) {
                countElement.textContent = visibleCount;
            }
        }
    </script>
</head>
<body>
    <header class="hero-header">
        <div class="hero-shell">
            <div class="hero-bar">
                <div class="hero-brand">
                    <div class="hero-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                    <div>
                        <div class="hero-brand-title">UniPanel</div>
                        <div class="hero-brand-subtitle">Topluluk Yönetim Platformu</div>
            </div>
        </div>
                <div class="hero-links">
                    <a href="marketing/index.html" class="hero-link">Ana Sayfa</a>
                    <a href="public/index.php" class="hero-link">Topluluk Portalı</a>
    </div>
                <div class="hero-actions">
                    <a href="public/register.php" class="action-button ghost">
                        <i class="fas fa-plus"></i>
                        Topluluk Oluştur
                    </a>
        </div>
        </div>
            <div class="hero-body">
                <div class="hero-text">
                    <span class="hero-eyebrow">Admin Panel Girişi</span>
                    <h1 class="hero-title">Profesyonel topluluk yönetimini başlatın.</h1>
                    <p class="hero-description">
                        Üniversitenizi seçip topluluğunuzu hızlıca bulun ya da dört karakterlik kod ile doğrudan giriş yapın.
                    </p>
                </div>
                <div class="hero-card">
                    <p class="hero-card-title">Nasıl Çalışır?</p>
                    <ul class="hero-card-list">
                        <li><i class="fas fa-check"></i> Üniversitenizi listeden seçin.</li>
                        <li><i class="fas fa-check"></i> Topluluğunuzu görüntüleyin.</li>
                        <li><i class="fas fa-check"></i> Panel giriş linkine tıklayın.</li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="page-container">

        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 24px;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Arama Seçenekleri -->
        <div class="filter-section">
            <div class="search-tabs">
                <button type="button" class="search-tab active" onclick="switchSearchMode('university')" id="tab-university">
                    <i class="fas fa-university"></i> Üniversite Seç
                </button>
                <button type="button" class="search-tab" onclick="switchSearchMode('code')" id="tab-code">
                    <i class="fas fa-key"></i> Kod ile Ara
                </button>
            </div>
            
            <!-- Üniversite Seçimi -->
            <form method="GET" action="" id="universityForm" style="display: block;">
                <input type="hidden" name="mode" value="university">
                <div class="form-group">
                    <label for="university" class="form-label">Üniversite Seçin</label>
                    <select 
                        id="university" 
                        name="university" 
                        class="form-input" 
                        onchange="this.form.submit()"
                    >
                        <option value="">Üniversite Seçin</option>
                        <?php foreach ($universities as $uni): ?>
                            <option value="<?= htmlspecialchars($uni) ?>" <?= $selected_university === $uni ? 'selected' : '' ?>>
                                <?= htmlspecialchars($uni) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <!-- Kod ile Arama -->
            <form method="GET" action="" id="codeForm" style="display: none;">
                <input type="hidden" name="mode" value="code">
                <div class="form-group">
                    <label for="code" class="form-label">Topluluk Kodu</label>
                    <div class="search-wrapper">
                        <i class="fas fa-key search-icon"></i>
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            class="form-input"
                            placeholder="Örn: BIL5"
                            value="<?= htmlspecialchars($code_search) ?>"
                            maxlength="4"
                            style="text-transform: uppercase; letter-spacing: 0.3em; text-align: center; font-weight: 600; padding-left: 44px;"
                            onkeyup="if(event.key === 'Enter') this.form.submit()"
                        >
                    </div>
                    <small style="display: block; margin-top: 8px; color: var(--text-secondary); font-size: 12px;">
                        <i class="fas fa-info-circle"></i> 4 karakterlik topluluk kodunu girin
                    </small>
                </div>
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">
                    <i class="fas fa-search"></i> Ara
                </button>
            </form>
        </div>

        <?php if (!empty($communities) || !empty($selected_university) || !empty($code_search)): ?>
            <?php if ($search_mode === 'university' && !empty($selected_university)): ?>
                <!-- Arama ve İstatistik (Sadece üniversite modunda) -->
                <div class="filter-section">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Topluluk ara..." 
                            class="form-input"
                            value="<?= htmlspecialchars($search_query) ?>"
                            onkeyup="filterCommunities()"
                            style="padding-left: 44px;"
                        >
                    </div>
                </div>
            <?php endif; ?>

            <div class="stats-bar">
                <span class="stats-text">
                    <?php if ($search_mode === 'code'): ?>
                        Kod araması: <strong><?= htmlspecialchars($code_search) ?></strong>
                    <?php else: ?>
                        <?php if (!empty($selected_university)): ?>
                            Üniversite: <strong><?= htmlspecialchars($selected_university) ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span style="margin: 0 8px;">•</span>
                    Toplam <span class="stats-count"><?= count($communities) ?></span> topluluk bulundu
                </span>
            </div>

            <!-- Topluluk Kartları -->
            <?php if (empty($communities)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <p class="empty-state-text">
                        <?= !empty($search_query) ? 'Arama sonucu bulunamadı.' : 'Bu üniversitede henüz topluluk bulunmuyor.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="communities-grid">
                    <?php foreach ($communities as $community): ?>
                        <a 
                            href="communities/<?= urlencode($community['folder']) ?>/login.php" 
                            class="community-card"
                        >
                            <div class="community-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="community-name">
                                <?= htmlspecialchars($community['name']) ?>
                            </h3>
                            <?php if (!empty($community['code'])): ?>
                                <div class="community-code">
                                    <?= htmlspecialchars($community['code']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($community['university'])): ?>
                                <div class="community-university">
                                    <i class="fas fa-university"></i>
                                    <?= htmlspecialchars($community['university']) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif (empty($code_search)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-university"></i>
                </div>
                <p class="empty-state-text">Yukarıdan bir üniversite seçin veya topluluk kodu ile arama yapın</p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-section">
            <div class="footer-links">
                <a href="marketing/index.html" class="footer-link">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
                <a href="public/index.php" class="footer-link">
                    <i class="fas fa-users"></i> Topluluk Portalı
                </a>
                <a href="admin-login.php" class="footer-link">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                </a>
            </div>
            <p class="footer-copyright">
                © <?= date('Y') ?> UniPanel. Tüm hakları saklıdır.
            </p>
        </div>
    </div>
</body>
</html>

