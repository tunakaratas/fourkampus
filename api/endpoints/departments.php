<?php
/**
 * Mobil API - Departments Endpoint
 * GET /api/departments.php - Tüm bölümleri listele
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';

header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = null, $message = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Türkiye'deki yaygın üniversite bölümleri
    $departments = [
        // Mühendislik
        'Bilgisayar Mühendisliği',
        'Yazılım Mühendisliği',
        'Elektrik-Elektronik Mühendisliği',
        'Endüstri Mühendisliği',
        'Makine Mühendisliği',
        'İnşaat Mühendisliği',
        'Kimya Mühendisliği',
        'Gıda Mühendisliği',
        'Çevre Mühendisliği',
        'Biyomedikal Mühendisliği',
        'Mekatronik Mühendisliği',
        'Otomotiv Mühendisliği',
        'Enerji Sistemleri Mühendisliği',
        'Petrol ve Doğalgaz Mühendisliği',
        'Harita Mühendisliği',
        'Jeoloji Mühendisliği',
        'Maden Mühendisliği',
        'Metalurji ve Malzeme Mühendisliği',
        'Tekstil Mühendisliği',
        'Orman Mühendisliği',
        'Ziraat Mühendisliği',
        
        // Fen Bilimleri
        'Matematik',
        'Fizik',
        'Kimya',
        'Biyoloji',
        'İstatistik',
        'Astronomi ve Uzay Bilimleri',
        'Moleküler Biyoloji ve Genetik',
        'Biyokimya',
        'Biyofizik',
        
        // Sağlık Bilimleri
        'Tıp',
        'Diş Hekimliği',
        'Eczacılık',
        'Veteriner Hekimliği',
        'Hemşirelik',
        'Beslenme ve Diyetetik',
        'Fizyoterapi ve Rehabilitasyon',
        'Sağlık Yönetimi',
        'Odyoloji',
        'Dil ve Konuşma Terapisi',
        'Ergoterapi',
        
        // İktisadi ve İdari Bilimler
        'İşletme',
        'İktisat',
        'Siyaset Bilimi ve Kamu Yönetimi',
        'Uluslararası İlişkiler',
        'Maliye',
        'Ekonometri',
        'Çalışma Ekonomisi ve Endüstri İlişkileri',
        'Turizm İşletmeciliği',
        'İnsan Kaynakları Yönetimi',
        'Lojistik Yönetimi',
        
        // Hukuk
        'Hukuk',
        
        // Eğitim
        'Sınıf Öğretmenliği',
        'Okul Öncesi Öğretmenliği',
        'Matematik Öğretmenliği',
        'Fen Bilgisi Öğretmenliği',
        'Türkçe Öğretmenliği',
        'İngilizce Öğretmenliği',
        'Tarih Öğretmenliği',
        'Coğrafya Öğretmenliği',
        'Beden Eğitimi ve Spor Öğretmenliği',
        'Müzik Öğretmenliği',
        'Resim Öğretmenliği',
        'Rehberlik ve Psikolojik Danışmanlık',
        
        // İletişim
        'Halkla İlişkiler ve Tanıtım',
        'Radyo, Televizyon ve Sinema',
        'Gazetecilik',
        'Medya ve İletişim',
        'Reklamcılık',
        'Dijital Medya',
        
        // Güzel Sanatlar
        'Grafik Tasarım',
        'Endüstriyel Tasarım',
        'İç Mimarlık',
        'Mimarlık',
        'Şehir ve Bölge Planlama',
        'Görsel İletişim Tasarımı',
        'Animasyon',
        'Sinema ve Televizyon',
        'Müzik',
        'Tiyatro',
        'Dans',
        
        // Sosyal Bilimler
        'Psikoloji',
        'Sosyoloji',
        'Felsefe',
        'Tarih',
        'Coğrafya',
        'Arkeoloji',
        'Sanat Tarihi',
        'Antropoloji',
        'Sosyal Hizmet',
        
        // Dil ve Edebiyat
        'Türk Dili ve Edebiyatı',
        'İngiliz Dili ve Edebiyatı',
        'Alman Dili ve Edebiyatı',
        'Fransız Dili ve Edebiyatı',
        'İspanyol Dili ve Edebiyatı',
        'Rus Dili ve Edebiyatı',
        'Çeviribilim',
        'Mütercim-Tercümanlık',
        
        // Spor Bilimleri
        'Spor Bilimleri',
        'Antrenörlük Eğitimi',
        'Rekreasyon',
        
        // Diğer
        'Güvenlik Bilimleri',
        'İlahiyat',
        'Konservatuvar',
        'Diğer'
    ];
    
    // Alfabetik sırala
    sort($departments);
    
    // Array'i API formatına çevir
    $departmentsList = [];
    foreach ($departments as $index => $dept) {
        $departmentsList[] = [
            'id' => (string)($index + 1),
            'name' => $dept
        ];
    }
    
    sendResponse(true, $departmentsList);
    
} catch (Exception $e) {
    $response = sendSecureErrorResponse('İşlem sırasında bir hata oluştu', $e);
    sendResponse($response['success'], $response['data'], $response['message'], $response['error']);
}
