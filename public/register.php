<?php
// =================================================================
// UNI-PANEL (UNIPANEL) - REGISTER SAYFASI (GENEL SİSTEM KAYDI)
// =================================================================

// Security helper'ı yükle
require_once __DIR__ . '/security_helper.php';

// Güvenli session başlat (security headers dahil)
secure_session_start();

// Production'da hataları gizle
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../system/logs/php_errors.log');

// Genel sistem veritabanı yolu
define('SYSTEM_DB_PATH', __DIR__ . '/unipanel.sqlite');

// Register fonksiyonunu tanımla (genel sistem kaydı)
function register_user($email, $password, $first_name, $last_name, $student_id = null, $phone_number = null, $university = null, $department = null) {
    try {
        // Veritabanı dizinini kontrol et ve oluştur
        $db_dir = dirname(SYSTEM_DB_PATH);
        if (!is_dir($db_dir)) {
            if (!mkdir($db_dir, 0755, true)) {
                return ['success' => false, 'message' => 'Veritabanı dizini oluşturulamadı'];
            }
        }
        
        // Veritabanı dosyasını oluştur (yoksa)
        if (!file_exists(SYSTEM_DB_PATH)) {
            touch(SYSTEM_DB_PATH);
            chmod(SYSTEM_DB_PATH, 0666);
        }
        
        // Güvenli database bağlantısı
        $db = get_safe_db_connection(SYSTEM_DB_PATH, false);
        if (!$db) {
            return ['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.'];
        }
        
        // system_users tablosunu oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS system_users (
            id INTEGER PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            student_id TEXT UNIQUE,
            password_hash TEXT NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            phone_number TEXT,
            university TEXT,
            department TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");
        
        // Email kontrolü (duplicate prevention)
        $check_stmt = $db->prepare("SELECT id FROM system_users WHERE email = ?");
        if (!$check_stmt) {
            $db->close();
            return ['success' => false, 'message' => 'Veritabanı hatası'];
        }
        $check_stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $check_stmt->execute();
        if ($result && $result->fetchArray()) {
            $db->close();
            return ['success' => false, 'message' => 'Bu email adresi zaten kayıtlı'];
        }
        
        // Student ID kontrolü (varsa)
        if ($student_id && !empty(trim($student_id))) {
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE student_id = ?");
            if ($check_stmt) {
                $check_stmt->bindValue(1, $student_id, SQLITE3_TEXT);
                $result = $check_stmt->execute();
                if ($result && $result->fetchArray()) {
                    $db->close();
                    return ['success' => false, 'message' => 'Bu öğrenci numarası zaten kayıtlı'];
                }
            }
        }
        
        // Phone kontrolü (varsa)
        if ($phone_number && !empty(trim($phone_number))) {
            $check_stmt = $db->prepare("SELECT id FROM system_users WHERE phone_number = ?");
            if ($check_stmt) {
                $check_stmt->bindValue(1, $phone_number, SQLITE3_TEXT);
                $result = $check_stmt->execute();
                if ($result && $result->fetchArray()) {
                    $db->close();
                    return ['success' => false, 'message' => 'Bu telefon numarası zaten kayıtlı'];
                }
            }
        }
        
        // Şifreyi hashle
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Kullanıcıyı kaydet
        $insert_stmt = $db->prepare("INSERT INTO system_users (email, student_id, password_hash, first_name, last_name, phone_number, university, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bindValue(1, $email, SQLITE3_TEXT);
        $insert_stmt->bindValue(2, $student_id, SQLITE3_TEXT);
        $insert_stmt->bindValue(3, $password_hash, SQLITE3_TEXT);
        $insert_stmt->bindValue(4, $first_name, SQLITE3_TEXT);
        $insert_stmt->bindValue(5, $last_name, SQLITE3_TEXT);
        $insert_stmt->bindValue(6, $phone_number, SQLITE3_TEXT);
        $insert_stmt->bindValue(7, $university, SQLITE3_TEXT);
        $insert_stmt->bindValue(8, $department, SQLITE3_TEXT);
        $insert_stmt->execute();
        
        $user_id = $db->lastInsertRowID();
        $db->close();
        
        return ['success' => true, 'message' => 'Kayıt başarılı!', 'user_id' => $user_id];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Kayıt hatası: ' . $e->getMessage()];
    }
}

$register_error = null;
$register_success = null;

// Üniversite listesi
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
    'Galatasaray Üniversitesi',
    'İstanbul Üniversitesi-Cerrahpaşa',
    'Ankara Yıldırım Beyazıt Üniversitesi',
    'Atılım Üniversitesi',
    'Başkent Üniversitesi',
    'Çankaya Üniversitesi',
    'TED Üniversitesi',
    'TOBB Ekonomi ve Teknoloji Üniversitesi',
    'İzmir Ekonomi Üniversitesi',
    'İzmir Katip Çelebi Üniversitesi',
    'Yaşar Üniversitesi',
    'Manisa Celal Bayar Üniversitesi',
    'Eskişehir Osmangazi Üniversitesi',
    'Eskişehir Teknik Üniversitesi',
    'Afyon Kocatepe Üniversitesi',
    'Kütahya Dumlupınar Üniversitesi',
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
    'Mersin Üniversitesi',
    'Hatay Mustafa Kemal Üniversitesi',
    'Kahramanmaraş Sütçü İmam Üniversitesi',
    'Osmaniye Korkut Ata Üniversitesi',
    'Gaziantep Üniversitesi',
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
    'Erzincan Binali Yıldırım Üniversitesi',
    'Bayburt Üniversitesi',
    'Gümüşhane Üniversitesi',
    'Rize Recep Tayyip Erdoğan Üniversitesi',
    'Artvin Çoruh Üniversitesi',
    'Trabzon Karadeniz Teknik Üniversitesi',
    'Giresun Üniversitesi',
    'Ordu Üniversitesi',
    'Tekirdağ Namık Kemal Üniversitesi',
    'Kırklareli Üniversitesi',
    'Diğer'
];

// Bölüm listesi
$departments = [
    'Bilgisayar Mühendisliği',
    'Yazılım Mühendisliği',
    'Elektrik-Elektronik Mühendisliği',
    'Endüstri Mühendisliği',
    'Makine Mühendisliği',
    'İnşaat Mühendisliği',
    'Kimya Mühendisliği',
    'Gıda Mühendisliği',
    'Çevre Mühendisliği',
    'Mimarlık',
    'Şehir ve Bölge Planlama',
    'İç Mimarlık',
    'Endüstriyel Tasarım',
    'İşletme',
    'İktisat',
    'Siyaset Bilimi ve Kamu Yönetimi',
    'Uluslararası İlişkiler',
    'Hukuk',
    'Tıp',
    'Diş Hekimliği',
    'Eczacılık',
    'Hemşirelik',
    'Sağlık Bilimleri',
    'Eğitim Bilimleri',
    'Türk Dili ve Edebiyatı',
    'İngiliz Dili ve Edebiyatı',
    'Tarih',
    'Felsefe',
    'Psikoloji',
    'Sosyoloji',
    'Matematik',
    'Fizik',
    'Kimya',
    'Biyoloji',
    'Moleküler Biyoloji ve Genetik',
    'Gıda Bilimi ve Teknolojisi',
    'Ziraat Mühendisliği',
    'Veteriner Hekimliği',
    'Güzel Sanatlar',
    'Müzik',
    'Tiyatro',
    'Sinema ve Televizyon',
    'İletişim',
    'Gazetecilik',
    'Radyo, Televizyon ve Sinema',
    'Halkla İlişkiler ve Tanıtım',
    'Reklamcılık',
    'Turizm ve Otel İşletmeciliği',
    'Gastronomi ve Mutfak Sanatları',
    'Spor Bilimleri',
    'Beden Eğitimi ve Spor',
    'Fizyoterapi ve Rehabilitasyon',
    'Beslenme ve Diyetetik',
    'Sosyal Hizmet',
    'Çocuk Gelişimi',
    'Okul Öncesi Öğretmenliği',
    'Sınıf Öğretmenliği',
    'Matematik Öğretmenliği',
    'Fen Bilgisi Öğretmenliği',
    'Türkçe Öğretmenliği',
    'İngilizce Öğretmenliği',
    'Tarih Öğretmenliği',
    'Coğrafya Öğretmenliği',
    'Diğer'
];

// Kullanıcı kaydı
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // CSRF Token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $register_error = "Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.";
        log_security_event('csrf_failure', ['page' => 'register']);
    } else {
        // Rate limiting kontrolü (kayıt spam koruması)
        $rate_check = check_rate_limit('register', 3, 600); // 10 dakikada max 3 kayıt
        if (!$rate_check['allowed']) {
            $register_error = $rate_check['message'];
            log_security_event('rate_limit_exceeded', ['action' => 'register', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } else {
            // Input sanitization
            $email = sanitize_input($_POST['email'] ?? '', 'email');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            $first_name = sanitize_input($_POST['first_name'] ?? '');
            $last_name = sanitize_input($_POST['last_name'] ?? '');
            $student_id = sanitize_input($_POST['student_id'] ?? '');
            $phone_number = sanitize_input($_POST['phone_number'] ?? '');
            $university = sanitize_input($_POST['university'] ?? '');
            $department = sanitize_input($_POST['department'] ?? '');
            
            // Şartlar kontrolü - Sadece üyelik için gerekli olanlar
            $terms_accepted = isset($_POST['terms']) && $_POST['terms'] === '1';
            $privacy_accepted = isset($_POST['privacy']) && $_POST['privacy'] === '1';
            $data_processing_accepted = isset($_POST['data_processing']) && $_POST['data_processing'] === '1';
            $notifications_accepted = isset($_POST['notifications']) && $_POST['notifications'] === '1';
            
            if (!$terms_accepted || !$privacy_accepted || !$data_processing_accepted || !$notifications_accepted) {
                $register_error = "Lütfen tüm zorunlu şartları kabul edin.";
            } elseif (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $register_error = "Lütfen tüm zorunlu alanları doldurun.";
            } elseif (strlen($first_name) < 2 || strlen($first_name) > 50) {
                $register_error = "Ad en az 2, en fazla 50 karakter olmalıdır.";
            } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
                $register_error = "Soyad en az 2, en fazla 50 karakter olmalıdır.";
            } else {
                // Email validation
                $email_validation = validate_email($email);
                if (!$email_validation['valid']) {
                    $register_error = $email_validation['message'];
                } elseif ($password !== $password_confirm) {
                    $register_error = "Şifreler eşleşmiyor.";
                } else {
                    // Password strength kontrolü
                    $password_validation = validate_password_strength($password);
                    if (!$password_validation['valid']) {
                        $register_error = $password_validation['message'];
                    } else {
                        // Phone validation (varsa)
                        if (!empty($phone_number)) {
                            $phone_validation = validate_phone($phone_number);
                            if (!$phone_validation['valid']) {
                                $register_error = $phone_validation['message'];
                            } else {
                                $phone_number = $phone_validation['normalized'];
                            }
                        }
                        
                        // Student ID validation (varsa)
                        if (!empty($student_id)) {
                            $student_id = preg_replace('/[^0-9]/', '', $student_id);
                            if (strlen($student_id) < 5 || strlen($student_id) > 20) {
                                $register_error = "Öğrenci numarası 5-20 karakter arasında olmalıdır.";
                            }
                        }
                        
                        if (empty($register_error)) {
                            $result = register_user($email, $password, $first_name, $last_name, $student_id ?: null, $phone_number ?: null, $university ?: null, $department ?: null);
                            if ($result['success']) {
                                $register_success = "Kayıt başarılı! Giriş yapabilirsiniz.";
                                log_security_event('user_registered', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                                // 3 saniye sonra login sayfasına yönlendir
                                header("Refresh: 3; url=login.php");
                            } else {
                                $register_error = $result['message'];
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - UniPanel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include __DIR__ . '/../templates/partials/tailwind_cdn_loader.php'; ?>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.1), 0 2px 4px -1px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 40%, #ffffff 100%);
            min-height: 100vh;
            padding: clamp(2rem, 6vw, 5rem);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            transition: color var(--transition-base);
            z-index: 1;
        }
        
        .input-wrapper input:focus ~ .input-icon,
        .input-wrapper input:not(:placeholder-shown) ~ .input-icon {
            color: #6366f1;
        }
        
        .input-wrapper input {
            padding-left: 44px;
            transition: all var(--transition-base);
        }
        
        .card-container {
            position: relative;
            z-index: 1;
        }
        
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .form-input {
            transition: all var(--transition-base);
            background: var(--bg-primary);
        }
        
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            background: #6366f1;
            transition: all var(--transition-base);
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .link-primary {
            color: #6366f1;
        }
        
        .link-primary:hover {
            color: #4f46e5;
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
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .required-star {
            color: #ef4444;
            font-weight: 600;
        }
        
        .searchable-select {
            position: relative;
            width: 100%;
        }
        
        .searchable-select .searchable-input {
            padding-right: 3.5rem;
            background-color: var(--bg-primary);
        }
        
        .searchable-select .searchable-toggle {
            position: absolute;
            top: 0.65rem;
            right: 1rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0.25rem;
            transition: color var(--transition-base);
            z-index: 12;
        }
        
        .searchable-select .searchable-toggle:hover {
            color: var(--text-primary);
        }
        
        .searchable-dropdown {
            position: absolute;
            top: calc(100% + 0.75rem);
            left: 0;
            width: 100%;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.15);
            max-height: 230px;
            overflow-y: auto;
            padding: 0.5rem;
            z-index: 20;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
        }
        
        .searchable-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .searchable-option {
            width: 100%;
            text-align: left;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.9rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background var(--transition-base), color var(--transition-base);
        }
        
        .searchable-option:hover {
            background: rgba(99, 102, 241, 0.08);
            color: var(--text-primary);
        }
        
        .searchable-option.highlight {
            background: rgba(99, 102, 241, 0.18);
            color: var(--text-primary);
        }
        
        .searchable-option.active {
            background: rgba(99, 102, 241, 0.12);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .searchable-option.hidden {
            display: none;
        }

        .auth-page {
            position: relative;
            min-height: calc(100vh - clamp(2rem, 6vw, 5rem) * 2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .auth-blur {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.12), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(129, 140, 248, 0.18), transparent 60%);
            z-index: -2;
            filter: blur(0px);
            display: none;
        }

        .auth-card {
            position: relative;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            overflow: hidden;
            backdrop-filter: none;
            background: transparent;
            z-index: 1;
        }

        @media (min-width: 900px) {
            .auth-card {
                grid-template-columns: minmax(0, 1.05fr) minmax(0, 1.15fr);
            }
        }

        .auth-card-media {
            position: relative;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: clamp(2.5rem, 5vw, 3.75rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.5rem, 3vw, 2.5rem);
            overflow: hidden;
        }

        .auth-card-media::before,
        .auth-card-media::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            opacity: 0.6;
            z-index: 0;
        }

        .auth-card-media::before {
            width: clamp(240px, 42vw, 340px);
            height: clamp(240px, 42vw, 340px);
            background: rgba(255, 255, 255, 0.12);
            top: clamp(-160px, -12vw, -90px);
            right: clamp(-140px, -8vw, -60px);
        }

        .auth-card-media::after {
            width: clamp(180px, 32vw, 280px);
            height: clamp(180px, 32vw, 280px);
            background: rgba(255, 255, 255, 0.08);
            bottom: clamp(-140px, -8vw, -80px);
            left: clamp(-160px, -9vw, -80px);
        }

        .auth-media-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: clamp(1.25rem, 2.5vw, 1.75rem);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-brand-icon {
            width: clamp(60px, 10vw, 72px);
            height: clamp(60px, 10vw, 72px);
            border-radius: 20px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.16);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .auth-brand-icon i {
            font-size: clamp(1.8rem, 3vw, 2.2rem);
        }

        .auth-brand h1 {
            font-size: clamp(1.8rem, 3vw, 2.3rem);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .auth-brand p {
            color: rgba(255, 255, 255, 0.78);
            font-weight: 500;
        }

        .auth-headline {
            font-size: clamp(2rem, 3.6vw, 2.85rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.045em;
        }

        .auth-subheadline {
            font-size: clamp(1rem, 2vw, 1.125rem);
            color: rgba(255, 255, 255, 0.78);
            max-width: 34rem;
            line-height: 1.65;
        }

        .auth-benefits {
            list-style: none;
            display: grid;
            gap: 0.9rem;
            padding: 0;
            margin: 0;
        }

        .auth-benefits li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.975rem;
            color: rgba(255, 255, 255, 0.88);
            font-weight: 500;
        }

        .auth-benefits i {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.16);
        }

        .auth-card-form {
            position: relative;
            background: transparent;
            padding: clamp(2.5rem, 5vw, 4rem);
            display: flex;
            flex-direction: column;
            gap: clamp(1.5rem, 3vw, 2.3rem);
            overflow-y: auto;
        }

        .auth-card-form::-webkit-scrollbar {
            width: 8px;
        }

        .auth-card-form::-webkit-scrollbar-track {
            background: rgba(226, 232, 240, 0.4);
            border-radius: 999px;
        }

        .auth-card-form::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
            border-radius: 999px;
        }

        .auth-form-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .auth-form-header h2 {
            font-size: clamp(1.75rem, 2.8vw, 2.1rem);
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.035em;
        }

        .auth-form-header p {
            color: var(--text-secondary);
            font-size: 0.975rem;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .auth-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.1rem;
        }

        .auth-form-grid .full-span {
            grid-column: span 1;
        }

        @media (min-width: 640px) {
            .auth-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .auth-form-grid .full-span {
                grid-column: span 2;
            }
        }

        .input-wrapper input {
            padding-left: 48px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            border: none;
            box-shadow: 0 10px 30px -12px rgba(79, 70, 229, 0.6);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-1px);
        }

        .auth-footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.92rem;
            color: var(--text-secondary);
        }

        .auth-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        @media (max-width: 899px) {
            body {
                padding: clamp(1.5rem, 6vw, 3rem);
            }

            .auth-card {
                transform: translateX(0);
            }

            .auth-card-media {
                min-height: 280px;
            }

            .auth-card-form {
                max-height: none;
            }
        }

        @media (min-width: 1024px) {
            body {
                padding: 0;
            }

            .auth-page {
                max-width: none;
                min-height: 100vh;
                align-items: stretch;
                justify-content: stretch;
            }

            .auth-card {
                min-height: 100vh;
                grid-template-columns: minmax(0, 0.75fr) minmax(0, 1fr);
                transform: none;
            }

            .auth-card-media,
            .auth-card-form {
                height: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page fade-in-up">
        <div class="auth-blur"></div>
        <div class="auth-card">
            <div class="auth-card-media">
                <div class="auth-media-content">
                    <div class="auth-brand">
                        <div class="auth-brand-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div>
                            <h1>UniPanel</h1>
                            <p>Topluluk Portalı</p>
                        </div>
                    </div>
                    <div>
                        <h2 class="auth-headline">Topluluğunuz için güçlü bir başlangıç yapın</h2>
                        <p class="auth-subheadline">Etkinliklerden kampanyalara, duyurulardan üye yönetimine kadar tüm süreçlerinizi tek panelden yönetin. Saniyeler içinde kaydolun, topluluğunuzu profesyonelce yönetin.</p>
                    </div>
                    <ul class="auth-benefits">
                        <li><i class="fas fa-check"></i> Gelişmiş etkinlik ve kampanya planlama</li>
                        <li><i class="fas fa-check"></i> Üyelerle güçlü iletişim ve bildirim altyapısı</li>
                        <li><i class="fas fa-check"></i> Analitik raporlar ve karar destek araçları</li>
                    </ul>
                </div>
            </div>
            <div class="auth-card-form">
                <div class="auth-form-header">
                    <h2>Hesap Oluştur</h2>
                    <p>UniPanel'e katılın ve topluluğunuzu yönetmeye hemen başlayın.</p>
                </div>

                <?php if ($register_error): ?>
                    <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                        <span class="font-medium"><?= htmlspecialchars($register_error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($register_success): ?>
                    <div class="p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl text-sm flex items-start gap-3" style="animation: fadeInUp 0.4s ease-out;">
                        <i class="fas fa-check-circle mt-0.5 flex-shrink-0"></i>
                        <div>
                            <span class="font-medium"><?= htmlspecialchars($register_success) ?></span>
                            <p class="text-xs mt-1 opacity-80">3 saniye sonra giriş sayfasına yönlendirileceksiniz...</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="action" value="register">
                    <?= csrf_token_field() ?>

                    <div class="auth-form-grid">
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Ad <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="first_name" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="Adınız"
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Soyad <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="last_name" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="Soyadınız"
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="full-span">
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Email Adresi <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="email" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="ornek@email.com"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Okul Numarası <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" name="student_id" required
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="123456789"
                                       value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Telefon Numarası <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="tel" name="phone_number" required
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="05XX XXX XX XX"
                                       value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Okuduğu Üniversite <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-university input-icon"></i>
                                <div class="searchable-select" data-dropdown="university">
                                    <?php $selectedUniversity = $_POST['university'] ?? ''; ?>
                                    <input type="hidden" name="university" id="university-value" value="<?= htmlspecialchars($selectedUniversity) ?>">
                                    <input type="text"
                                           class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium searchable-input"
                                           style="border-color: var(--border-color); color: var(--text-primary);"
                                           placeholder="Üniversite ara veya seç"
                                           value="<?= htmlspecialchars($selectedUniversity) ?>"
                                           autocomplete="off"
                                           data-placeholder="Üniversite ara veya seç">
                                    <button type="button" class="searchable-toggle" aria-label="Üniversite listesini aç">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="searchable-dropdown">
                                        <?php foreach ($universities as $uni): ?>
                                            <?php
                                                $isSelected = isset($_POST['university']) && $_POST['university'] === $uni;
                                                $searchValue = function_exists('mb_strtolower') ? mb_strtolower($uni, 'UTF-8') : strtolower($uni);
                                            ?>
                                            <button type="button"
                                                    class="searchable-option <?= $isSelected ? 'active' : '' ?>"
                                                    data-value="<?= htmlspecialchars($uni) ?>"
                                                    data-search="<?= htmlspecialchars($searchValue) ?>">
                                                <?= htmlspecialchars($uni) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Bölüm <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap input-icon"></i>
                                <div class="searchable-select" data-dropdown="department">
                                    <?php $selectedDepartment = $_POST['department'] ?? ''; ?>
                                    <input type="hidden" name="department" id="department-value" value="<?= htmlspecialchars($selectedDepartment) ?>">
                                    <input type="text"
                                           class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium searchable-input"
                                           style="border-color: var(--border-color); color: var(--text-primary);"
                                           placeholder="Bölüm ara veya seç"
                                           value="<?= htmlspecialchars($selectedDepartment) ?>"
                                           autocomplete="off"
                                           data-placeholder="Bölüm ara veya seç">
                                    <button type="button" class="searchable-toggle" aria-label="Bölüm listesini aç">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="searchable-dropdown">
                                        <?php foreach ($departments as $dept): ?>
                                            <?php
                                                $isSelected = isset($_POST['department']) && $_POST['department'] === $dept;
                                                $searchValue = function_exists('mb_strtolower') ? mb_strtolower($dept, 'UTF-8') : strtolower($dept);
                                            ?>
                                            <button type="button"
                                                    class="searchable-option <?= $isSelected ? 'active' : '' ?>"
                                                    data-value="<?= htmlspecialchars($dept) ?>"
                                                    data-search="<?= htmlspecialchars($searchValue) ?>">
                                                <?= htmlspecialchars($dept) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Şifre <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password" required minlength="6"
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="En az 6 karakter">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Şifre Tekrar <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password_confirm" required minlength="6"
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="Şifrenizi tekrar girin">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="terms" value="1" required id="terms" class="mt-1 w-4 h-4 rounded border-2 cursor-pointer flex-shrink-0" 
                                   style="border-color: var(--border-color); accent-color: #6366f1;">
                            <label for="terms" class="text-xs font-medium cursor-pointer" style="color: var(--text-secondary); line-height: 1.6;">
                                <a href="../marketing/terms-of-service.php" target="_blank" class="link-primary font-semibold hover:underline">Kullanım Koşullarını</a> okudum ve kabul ediyorum. <span class="required-star">*</span>
                            </label>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="privacy" value="1" required id="privacy" class="mt-1 w-4 h-4 rounded border-2 cursor-pointer flex-shrink-0" 
                                   style="border-color: var(--border-color); accent-color: #6366f1;">
                            <label for="privacy" class="text-xs font-medium cursor-pointer" style="color: var(--text-secondary); line-height: 1.6;">
                                <a href="../marketing/privacy-policy.php" target="_blank" class="link-primary font-semibold hover:underline">Gizlilik Politikasını</a> okudum ve kabul ediyorum. <span class="required-star">*</span>
                            </label>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="data_processing" value="1" required id="data_processing" class="mt-1 w-4 h-4 rounded border-2 cursor-pointer flex-shrink-0" 
                                   style="border-color: var(--border-color); accent-color: #6366f1;">
                            <label for="data_processing" class="text-xs font-medium cursor-pointer" style="color: var(--text-secondary); line-height: 1.6;">
                                Kişisel verilerimin işlenmesine ve saklanmasına izin veriyorum. <span class="required-star">*</span>
                            </label>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="notifications" value="1" required id="notifications" class="mt-1 w-4 h-4 rounded border-2 cursor-pointer flex-shrink-0" 
                                   style="border-color: var(--border-color); accent-color: #6366f1;">
                            <label for="notifications" class="text-xs font-medium cursor-pointer" style="color: var(--text-secondary); line-height: 1.6;">
                                E-posta ve SMS ile bildirim ve duyurular almak istiyorum. <span class="required-star">*</span>
                            </label>
                        </div>
                    </div>
                    
                    <script>
                        (function() {
                            const initSearchableSelect = (dropdown) => {
                                const hiddenInput = dropdown.querySelector('input[type="hidden"]');
                                const searchInput = dropdown.querySelector('.searchable-input');
                                const toggleButton = dropdown.querySelector('.searchable-toggle');
                                const dropdownMenu = dropdown.querySelector('.searchable-dropdown');
                                const options = Array.from(dropdown.querySelectorAll('.searchable-option'));
                                const placeholder = searchInput.dataset.placeholder || '';
                                
                                const openDropdown = () => {
                                    dropdownMenu.classList.add('active');
                                };
                                
                                const closeDropdown = () => {
                                    dropdownMenu.classList.remove('active');
                                };
                                
                                const restoreSelectedValue = () => {
                                    if (hiddenInput.value) {
                                        searchInput.value = hiddenInput.value;
                                    } else {
                                        searchInput.value = '';
                                        searchInput.setAttribute('placeholder', placeholder);
                                    }
                                    filterOptions('');
                                };
                                
                                const filterOptions = (term) => {
                                    const normalizedTerm = term.toLowerCase().trim();
                                    let visibleCount = 0;
                                    options.forEach(option => {
                                        const searchable = option.dataset.search || option.textContent.toLowerCase();
                                        const matches = !normalizedTerm || searchable.includes(normalizedTerm);
                                        option.classList.toggle('hidden', !matches);
                                        if (matches) {
                                            visibleCount++;
                                        }
                                    });
                                    dropdownMenu.dataset.empty = visibleCount === 0 ? 'true' : 'false';
                                };
                                
                                const selectOption = (option) => {
                                    const value = option.dataset.value || '';
                                    hiddenInput.value = value;
                                    searchInput.value = value;
                                    options.forEach(opt => opt.classList.remove('active'));
                                    option.classList.add('active');
                                    closeDropdown();
                                };
                                
                                // Event bindings
                                searchInput.addEventListener('focus', () => {
                                    openDropdown();
                                    searchInput.select();
                                });
                                
                                searchInput.addEventListener('input', (event) => {
                                    const term = event.target.value;
                                    filterOptions(term);
                                    if (!term.trim()) {
                                        hiddenInput.value = '';
                                        options.forEach(opt => opt.classList.remove('active'));
                                    }
                                });
                                
                                searchInput.addEventListener('keydown', (event) => {
                                    const visibleOptions = options.filter(opt => !opt.classList.contains('hidden'));
                                    const activeIndex = visibleOptions.findIndex(opt => opt.classList.contains('highlight'));
                                    
                                    if (event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        const nextIndex = activeIndex + 1 < visibleOptions.length ? activeIndex + 1 : 0;
                                        visibleOptions.forEach(opt => opt.classList.remove('highlight'));
                                        if (visibleOptions[nextIndex]) {
                                            visibleOptions[nextIndex].classList.add('highlight');
                                            visibleOptions[nextIndex].scrollIntoView({ block: 'nearest' });
                                        }
                                    }
                                    
                                    if (event.key === 'ArrowUp') {
                                        event.preventDefault();
                                        const prevIndex = activeIndex > 0 ? activeIndex - 1 : visibleOptions.length - 1;
                                        visibleOptions.forEach(opt => opt.classList.remove('highlight'));
                                        if (visibleOptions[prevIndex]) {
                                            visibleOptions[prevIndex].classList.add('highlight');
                                            visibleOptions[prevIndex].scrollIntoView({ block: 'nearest' });
                                        }
                                    }
                                    
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        const highlighted = dropdown.querySelector('.searchable-option.highlight:not(.hidden)');
                                        if (highlighted) {
                                            selectOption(highlighted);
                                        } else if (visibleOptions.length > 0) {
                                            selectOption(visibleOptions[0]);
                                        }
                                    }
                                });
                                
                                searchInput.addEventListener('blur', () => {
                                    setTimeout(() => {
                                        if (!dropdown.contains(document.activeElement)) {
                                            closeDropdown();
                                            restoreSelectedValue();
                                            options.forEach(opt => opt.classList.remove('highlight'));
                                        }
                                    }, 150);
                                });
                                
                                toggleButton.addEventListener('click', (event) => {
                                    event.stopPropagation();
                                    dropdownMenu.classList.toggle('active');
                                    if (dropdownMenu.classList.contains('active')) {
                                        searchInput.focus();
                                    } else {
                                        searchInput.blur();
                                    }
                                });
                                
                                options.forEach(option => {
                                    option.addEventListener('click', () => selectOption(option));
                                });
                                
                                document.addEventListener('click', (event) => {
                                    if (!dropdown.contains(event.target)) {
                                        closeDropdown();
                                        restoreSelectedValue();
                                        options.forEach(opt => opt.classList.remove('highlight'));
                                    }
                                });
                                
                                // Initialize with existing value
                                if (hiddenInput.value) {
                                    const existing = options.find(opt => opt.dataset.value === hiddenInput.value);
                                    if (existing) {
                                        existing.classList.add('active');
                                        searchInput.value = hiddenInput.value;
                                    }
                                }
                            };
                            
                            document.querySelectorAll('.searchable-select').forEach(initSearchableSelect);
                            
                            // Form gönderilmeden önce şartlar ve seçim kontrolü
                            document.querySelector('form').addEventListener('submit', function(e) {
                                const terms = document.getElementById('terms').checked;
                                const privacy = document.getElementById('privacy').checked;
                                const dataProcessing = document.getElementById('data_processing').checked;
                                const notifications = document.getElementById('notifications').checked;
                                const universityValue = document.getElementById('university-value').value.trim();
                                const departmentValue = document.getElementById('department-value').value.trim();
                                
                                if (!terms || !privacy || !dataProcessing || !notifications) {
                                    e.preventDefault();
                                    alert('Lütfen tüm zorunlu şartları kabul edin.');
                                    return false;
                                }
                                
                                if (!universityValue || !departmentValue) {
                                    e.preventDefault();
                                    alert('Lütfen üniversite ve bölüm seçimini tamamlayın.');
                                    return false;
                                }
                            });
                        })();
                    </script>

                    <button type="submit" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base" style="letter-spacing: -0.01em;">
                        Hesap Oluştur
                    </button>
                </form>

                <div class="auth-footer-links">
                    <span>Zaten hesabınız var mı?</span>
                    <a href="login.php" class="link-primary">
                        <span>Giriş Yap</span>
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="index.php" class="link-primary" style="color: var(--text-light);">
                        <i class="fas fa-arrow-left"></i>
                        <span>Ana sayfaya dön</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

