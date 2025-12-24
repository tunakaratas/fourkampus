<?php
/**
 * University Helper
 * Centralized list and normalization functions for universities
 */

/**
 * Get the master list of universities
 */
function getUniversityList() {
    return [
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
        'Gelecek Üniversitesi', // Mismatch fix
        'Diğer'
    ];
}

/**
 * Standardized university normalization (for database matching)
 */
function normalizeUniversityName($name) {
    if ($name === null) return '';
    $name = (string)$name;
    
    // Convert to lowercase using UTF-8 mapping
    $name = mb_strtolower($name, 'UTF-8');
    
    // Explicit mapping for Turkish and special characters
    $map = [
        'ç' => 'c', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ı' => 'i', 'ö' => 'o',
        ' ' => '', '-' => '', '_' => '', '.' => '', ',' => '', '+' => '',
        '(' => '', ')' => '', '[' => '', ']' => '', '{' => '', '}' => ''
    ];
    
    // Replace characters using the map
    $name = strtr($name, $map);
    
    // Remove all remaining non-alphanumeric characters
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    
    return $name;
}

/**
 * Generate a slug/ID for a university name
 */
function getUniversitySlug($name) {
    return normalizeUniversityName($name);
}
