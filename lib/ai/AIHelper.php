<?php
/**
 * AI Helper - DeepSeek API Entegrasyonu
 * Etkinlik, kampanya ve içerik oluşturma için AI desteği
 */

/**
 * DeepSeek API ile metin oluştur
 * 
 * @param string $prompt Kullanıcı prompt'u
 * @param array $context Ek bağlam bilgileri
 * @param string $type İçerik tipi (event, campaign, product, etc.)
 * @return string|false Oluşturulan metin veya false (hata durumunda)
 */
function ai_generate_text($prompt, $context = [], $type = 'general', $max_tokens = 2000) {
    try {
        // Credentials'ı yükle
        $credentials = load_ai_credentials();
        
        // Groq API kullan (ücretsiz ve sınırsız)
        $api_key = '';
        $model = '';
        
        // Groq API key kontrolü
        if (!empty($credentials['groq']['api_key'])) {
            $api_key = $credentials['groq']['api_key'];
            $model = $credentials['groq']['model'] ?? 'llama-3.3-70b-versatile';
        } else {
            error_log("AI Helper: Groq API key bulunamadı");
            return false;
        }
        
        // API key kontrolü
        if (empty($api_key)) {
            error_log("AI Helper: Groq API key boş");
            return false;
        }
        
        // Groq API helper'ı yükle
        if (!function_exists('call_groq_api')) {
            if (file_exists(__DIR__ . '/AIHelper_Groq.php')) {
                require_once __DIR__ . '/AIHelper_Groq.php';
            } else {
                error_log("AI Helper: AIHelper_Groq.php bulunamadı");
                return false;
            }
        }
        
        // Prompt'u tipine göre zenginleştir
        $system_prompt = get_system_prompt($type);
        $enhanced_prompt = build_enhanced_prompt($prompt, $context, $type);
        
        error_log("AI Helper: Groq API çağrılıyor - Model: $model, Max tokens: $max_tokens");
        
        // Groq API çağrısı
        $response = call_groq_api($api_key, $model, $system_prompt, $enhanced_prompt, $max_tokens);
        
        if ($response === false) {
            error_log("AI Helper: call_groq_api returned false");
            return false;
        }
        
        error_log("AI Helper: API yanıtı alındı, uzunluk: " . strlen($response));
        return $response;
        
    } catch (Exception $e) {
        error_log("AI Helper Error: " . $e->getMessage());
        error_log("AI Helper Stack Trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Etkinlik açıklaması oluştur
 * 
 * @param array $event_data Etkinlik bilgileri (title, date, location, category, etc.)
 * @return string|false Oluşturulan açıklama
 */
function ai_generate_event_description($event_data) {
    try {
        $prompt = "Etkinlik açıklaması oluştur:\n";
        $prompt .= "Başlık: " . ($event_data['title'] ?? '') . "\n";
        $prompt .= "Tarih: " . ($event_data['date'] ?? '') . "\n";
        $prompt .= "Saat: " . ($event_data['time'] ?? '') . "\n";
        $prompt .= "Konum: " . ($event_data['location'] ?? '') . "\n";
        $prompt .= "Kategori: " . ($event_data['category'] ?? 'Genel') . "\n";
        
        if (!empty($event_data['organizer'])) {
            $prompt .= "Organizatör: " . $event_data['organizer'] . "\n";
        }
        
        $prompt .= "\nLütfen çekici, profesyonel ve Türkçe bir etkinlik açıklaması yaz. 150-300 kelime arası olsun.";
        
        $result = ai_generate_text($prompt, $event_data, 'event');
        
        if ($result === false) {
            error_log("AI Helper: ai_generate_text returned false for event description");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_event_description: " . $e->getMessage());
        return false;
    }
}

/**
 * DeepSeek API çağrısı yap
 */
function call_deepseek_api($api_key, $model, $system_prompt, $user_prompt) {
    // DeepSeek API endpoint
    $url = 'https://api.deepseek.com/v1/chat/completions';
    
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    $json_data = json_encode($data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("AI Helper: JSON encode error: " . json_last_error_msg());
        return false;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    if ($curl_errno !== 0) {
        error_log("AI Helper CURL Error #$curl_errno: " . $curl_error);
        return false;
    }
    
    if ($http_code !== 200) {
        error_log("AI Helper API Error: HTTP $http_code");
        error_log("AI Helper API Response: " . substr($response, 0, 1000)); // İlk 1000 karakter
        return false;
    }
    
    if (empty($response)) {
        error_log("AI Helper: Boş API yanıtı");
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("AI Helper JSON Parse Error: " . json_last_error_msg());
        error_log("AI Helper Raw Response: " . substr($response, 0, 1000));
        return false;
    }
    
    // Hata kontrolü (API'den hata döndüyse)
    if (isset($result['error'])) {
        error_log("AI Helper API Error Response: " . print_r($result['error'], true));
        return false;
    }
    
    if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
        error_log("AI Helper: choices array bulunamadı veya boş");
        error_log("AI Helper Response Structure: " . print_r($result, true));
        return false;
    }
    
    if (!isset($result['choices'][0]['message']['content'])) {
        error_log("AI Helper: Geçersiz API yanıtı yapısı");
        error_log("AI Helper Response Structure: " . print_r($result, true));
        return false;
    }
    
    return trim($result['choices'][0]['message']['content']);
}

/**
 * Kampanya metni oluştur
 * 
 * @param array $campaign_data Kampanya bilgileri (title, discount_percentage, partner_name, etc.)
 * @return string|false Oluşturulan kampanya metni
 */
function ai_generate_campaign_text($campaign_data) {
    try {
        $prompt = "Kampanya metni oluştur:\n";
        $prompt .= "Başlık: " . ($campaign_data['title'] ?? '') . "\n";
        
        if (!empty($campaign_data['discount_percentage'])) {
            $prompt .= "İndirim: %" . $campaign_data['discount_percentage'] . "\n";
        }
        
        if (!empty($campaign_data['partner_name'])) {
            $prompt .= "Partner: " . $campaign_data['partner_name'] . "\n";
        }
        
        $prompt .= "\nLütfen çekici, satış odaklı ve Türkçe bir kampanya metni yaz. 100-200 kelime arası olsun. Emoji kullan.";
        
        return ai_generate_text($prompt, $campaign_data, 'campaign');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_campaign_text: " . $e->getMessage());
        return false;
    }
}

/**
 * Ürün açıklaması oluştur
 * 
 * @param array $product_data Ürün bilgileri (name, price, category, etc.)
 * @return string|false Oluşturulan ürün açıklaması
 */
function ai_generate_product_description($product_data) {
    try {
        $prompt = "Ürün açıklaması oluştur:\n";
        $prompt .= "Ürün adı: " . ($product_data['name'] ?? '') . "\n";
        $prompt .= "Fiyat: " . ($product_data['price'] ?? '') . " TL\n";
        $prompt .= "Kategori: " . ($product_data['category'] ?? 'Genel') . "\n";
        
        $prompt .= "\nLütfen detaylı, SEO uyumlu ve Türkçe bir ürün açıklaması yaz. 150-300 kelime arası olsun. Ürün özelliklerini liste halinde belirt.";
        
        return ai_generate_text($prompt, $product_data, 'product');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_product_description: " . $e->getMessage());
        return false;
    }
}

/**
 * Destek yanıtı oluştur
 * 
 * @param string $question Kullanıcı sorusu
 * @param array $context Ek bağlam (ticket history, etc.)
 * @return string|false Oluşturulan yanıt
 */
function ai_generate_support_response($question, $context = []) {
    try {
        $prompt = "Kullanıcı sorusuna profesyonel ve yardımcı bir yanıt ver:\n\n";
        $prompt .= "Soru: " . $question . "\n\n";
        $prompt .= "Lütfen Türkçe, net ve çözüm odaklı bir yanıt yaz. Adım adım açıkla.";
        
        return ai_generate_text($prompt, $context, 'general');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_support_response: " . $e->getMessage());
        return false;
    }
}

/**
 * Email/SMS mesaj önerisi oluştur
 * 
 * @param string $purpose Mesaj amacı (duyuru, hatırlatma, teşekkür, etc.)
 * @param array $context Bağlam bilgileri
 * @return string|false Oluşturulan mesaj
 */
function ai_generate_message($purpose, $context = []) {
    try {
        $prompt = "Mesaj oluştur:\n";
        $prompt .= "Amaç: " . $purpose . "\n";
        
        if (!empty($context['event_title'])) {
            $prompt .= "Etkinlik: " . $context['event_title'] . "\n";
        }
        
        if (!empty($context['tone'])) {
            $prompt .= "Ton: " . $context['tone'] . " (resmi/samimi/eğlenceli)\n";
        }
        
        $prompt .= "\nLütfen kısa, etkili ve Türkçe bir mesaj yaz. ";
        
        if (isset($context['is_sms']) && $context['is_sms']) {
            $prompt .= "SMS için maksimum 160 karakter olsun.";
        } else {
            $prompt .= "Email için 100-200 kelime arası olsun.";
        }
        
        return ai_generate_text($prompt, $context, 'general');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_message: " . $e->getMessage());
        return false;
    }
}

/**
 * Email konu başlığı oluştur
 * 
 * @param string $purpose Email amacı (duyuru, davet, hatırlatma, vb.)
 * @return string|false Email konu başlığı
 */
function ai_generate_email_subject($purpose) {
    try {
        $prompt = "Email için kısa, etkili ve çekici bir konu başlığı oluştur:\n\n";
        $prompt .= "Amaç: " . $purpose . "\n\n";
        $prompt .= "LÜTFEN:\n";
        $prompt .= "- Konu başlığı kısa ve öz olsun (maksimum 60 karakter)\n";
        $prompt .= "- Dikkat çekici ve profesyonel olsun\n";
        $prompt .= "- Türkçe olsun\n";
        $prompt .= "- Sadece konu başlığını ver, başka açıklama ekleme\n";
        
        $result = ai_generate_text($prompt, ['purpose' => $purpose], 'general');
        if ($result === false) return false;
        
        // Sadece ilk satırı al ve temizle
        $lines = explode("\n", trim($result));
        $subject = trim($lines[0]);
        
        // Eğer numaralandırma varsa kaldır (1. 2. gibi)
        $subject = preg_replace('/^\d+[\.\)]\s*/', '', $subject);
        
        return $subject ?: false;
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_email_subject: " . $e->getMessage());
        return false;
    }
}

/**
 * Etkinlik başlık önerileri oluştur
 * 
 * @param array $event_data Etkinlik bilgileri
 * @return array|false Başlık önerileri (3-5 adet)
 */
function ai_generate_event_title_suggestions($event_data) {
    try {
        $prompt = "Etkinlik için 5 farklı, çekici başlık önerisi oluştur:\n\n";
        
        // Eğer mevcut bir başlık varsa, ona göre öneriler yap
        if (!empty($event_data['title']) && trim($event_data['title']) !== '') {
            $prompt .= "MEVCUT BAŞLIK: " . trim($event_data['title']) . "\n\n";
            $prompt .= "Yukarıdaki başlığa benzer, ancak farklı yaklaşımlarla 5 alternatif başlık öner. ";
            $prompt .= "Her başlık aynı konuyu/etkinliği anlatmalı ama farklı bir üslup kullanmalı (ciddi, eğlenceli, merak uyandıran, profesyonel, samimi). ";
            $prompt .= "Mevcut başlığın anlamını ve konusunu koru, sadece ifade şeklini değiştir.\n\n";
        } else {
            $prompt .= "Etkinlik bilgileri:\n";
        }
        
        $prompt .= "Kategori: " . ($event_data['category'] ?? 'Genel') . "\n";
        if (!empty($event_data['location'])) {
            $prompt .= "Lokasyon: " . $event_data['location'] . "\n";
        }
        if (!empty($event_data['date'])) {
            $prompt .= "Tarih: " . $event_data['date'] . "\n";
        }
        
        $prompt .= "\nLÜTFEN:\n";
        $prompt .= "- 5 farklı başlık öner\n";
        $prompt .= "- Her başlık farklı bir yaklaşım kullansın\n";
        $prompt .= "- Başlıklar çekici, etkileyici ve profesyonel olsun\n";
        if (!empty($event_data['title'])) {
            $prompt .= "- Mevcut başlığın konusunu ve anlamını koru\n";
        }
        $prompt .= "- Sadece başlıkları liste halinde ver, numaralandır (1. 2. 3. vs.)\n";
        $prompt .= "- Her başlık tek satırda olsun\n";
        $prompt .= "- Açıklama veya ek bilgi ekleme, sadece başlıkları ver";
        
        $result = ai_generate_text($prompt, $event_data, 'event');
        if ($result === false) return false;
        
        // Başlıkları ayır ve temizle
        $lines = explode("\n", $result);
        $titles = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Numarayı kaldır (1. 2. vs.)
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            $line = trim($line);
            
            // Tire veya nokta ile başlıyorsa temizle
            $line = preg_replace('/^[-•]\s*/', '', $line);
            $line = trim($line);
            
            // Tırnak işaretlerini kaldır
            $line = trim($line, '"\'');
            
            if (!empty($line) && strlen($line) > 5) {
                $titles[] = $line;
                if (count($titles) >= 5) break;
            }
        }
        
        // Eğer başlık bulunamadıysa, tüm metni tek başlık olarak kullan
        if (empty($titles)) {
            $clean_result = trim($result);
            // İlk satırı al
            $first_line = explode("\n", $clean_result)[0];
            $first_line = trim($first_line);
            $first_line = preg_replace('/^\d+[\.\)]\s*/', '', $first_line);
            $first_line = trim($first_line, '"\'');
            if (!empty($first_line) && strlen($first_line) > 5) {
                $titles[] = $first_line;
            }
        }
        
        return !empty($titles) ? $titles : false;
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_event_title_suggestions: " . $e->getMessage());
        return false;
    }
}

/**
 * Etkinlik hashtag önerileri oluştur
 * 
 * @param array $event_data Etkinlik bilgileri
 * @return string|false Hashtag'ler (virgülle ayrılmış)
 */
function ai_generate_event_hashtags($event_data) {
    try {
        $prompt = "Etkinlik için sosyal medya hashtag'leri oluştur:\n";
        $prompt .= "Başlık: " . ($event_data['title'] ?? '') . "\n";
        $prompt .= "Kategori: " . ($event_data['category'] ?? 'Genel') . "\n";
        $prompt .= "Lokasyon: " . ($event_data['location'] ?? '') . "\n";
        $prompt .= "\n10-15 adet popüler ve ilgili hashtag öner. Virgülle ayır, # işareti kullan.";
        
        return ai_generate_text($prompt, $event_data, 'event');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_event_hashtags: " . $e->getMessage());
        return false;
    }
}

/**
 * Sosyal medya paylaşım metni oluştur
 * 
 * @param array $event_data Etkinlik bilgileri
 * @param string $platform Platform (instagram, twitter, facebook)
 * @return string|false Paylaşım metni
 */
function ai_generate_social_media_post($event_data, $platform = 'instagram') {
    try {
        $prompt = "Sosyal medya paylaşım metni oluştur:\n";
        $prompt .= "Platform: " . $platform . "\n";
        $prompt .= "Etkinlik: " . ($event_data['title'] ?? '') . "\n";
        $prompt .= "Tarih: " . ($event_data['date'] ?? '') . "\n";
        $prompt .= "Saat: " . ($event_data['time'] ?? '') . "\n";
        $prompt .= "Lokasyon: " . ($event_data['location'] ?? '') . "\n";
        
        $char_limit = match($platform) {
            'twitter' => 280,
            'instagram' => 2200,
            'facebook' => 5000,
            default => 500
        };
        
        $prompt .= "\n" . $platform . " için uygun uzunlukta (" . $char_limit . " karakter), çekici ve emoji içeren bir paylaşım metni yaz.";
        
        return ai_generate_text($prompt, $event_data, 'event');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_social_media_post: " . $e->getMessage());
        return false;
    }
}

/**
 * Metin iyileştirme/düzeltme
 * 
 * @param string $text İyileştirilecek metin
 * @param string $improvement_type İyileştirme tipi (gramer, akıcılık, kısalık, genişletme)
 * @return string|false İyileştirilmiş metin
 */
function ai_improve_text($text, $improvement_type = 'gramer') {
    try {
        $prompt = "Aşağıdaki metni iyileştir ve geliştir:\n\n";
        $prompt .= "ORİJİNAL METİN:\n" . $text . "\n\n";
        
        $improvements = [
            'gramer' => "Yazım ve dil bilgisi hatalarını düzelt, Türkçe dil kurallarına uygun hale getir. Tüm noktalama işaretlerini doğru kullan. Metni tam ve eksiksiz bir şekilde yeniden yaz, sadece hataları düzeltme, tüm metni iyileştirilmiş haliyle ver.",
            'akıcılık' => "Metni daha akıcı ve okunabilir hale getir. Cümle yapılarını iyileştir, geçişleri daha doğal yap. Paragrafları düzenle. Metni tam olarak yeniden yaz, daha akıcı ve profesyonel bir dil kullan.",
            'kısalık' => "Metni özetle ve kısalt, ancak önemli bilgileri ve anlamı koru. Gereksiz tekrarları kaldır. Daha öz ve etkili bir metin oluştur. Kısa ama anlamlı cümleler kullan.",
            'genişletme' => "Metni genişlet ve detaylandır. Daha açıklayıcı ve bilgilendirici hale getir. Örnekler, açıklamalar ve ek bilgiler ekle. Metni en az 2-3 kat daha uzun ve detaylı yap. Orijinal anlamı koruyarak zenginleştir.",
            'profesyonel' => "Metni daha profesyonel ve resmi bir tona çevir. İş dünyasına uygun bir dil kullan. Daha ciddi ve saygılı bir üslup benimse. Tüm metni profesyonel bir şekilde yeniden yaz.",
            'samimi' => "Metni daha samimi ve dostane bir tona çevir. Daha sıcak ve yakın bir dil kullan. Okuyucuyla daha iyi bağ kuran bir üslup benimse. Tüm metni samimi bir şekilde yeniden yaz."
        ];
        
        $prompt .= "İYİLEŞTİRME TALİMATI:\n";
        $prompt .= $improvements[$improvement_type] ?? $improvements['gramer'];
        $prompt .= "\n\nLÜTFEN:\n";
        $prompt .= "- Tüm metni tam olarak yeniden yaz\n";
        $prompt .= "- Orijinal anlamı ve içeriği koru\n";
        $prompt .= "- İyileştirilmiş metni doğrudan ver, açıklama ekleme\n";
        $prompt .= "- Metin uzunluğunu koru veya talimat gereği değiştir\n";
        $prompt .= "- Türkçe dil kurallarına tam uyum sağla\n\n";
        $prompt .= "İYİLEŞTİRİLMİŞ METİN:";
        
        // Özel max_tokens ayarı - daha uzun metinler için
        $credentials = load_ai_credentials();
        $api_key = $credentials['groq']['api_key'] ?? '';
        $model = $credentials['groq']['model'] ?? 'llama-3.3-70b-versatile';
        
        if (empty($api_key)) {
            error_log("AI Helper: Groq API key bulunamadı");
            return false;
        }
        
        // Metin uzunluğuna göre max_tokens ayarla
        $text_length = strlen($text);
        $max_tokens = max(2000, min(4000, $text_length * 3)); // En az 2000, en fazla 4000
        
        $system_prompt = get_system_prompt('general');
        
        // Groq API çağrısı
        if (file_exists(__DIR__ . '/AIHelper_Groq.php')) {
            require_once __DIR__ . '/AIHelper_Groq.php';
        }
        
        // Groq API'ye özel çağrı (max_tokens ile)
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => $max_tokens
        ];
        
        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AI Helper: JSON encode error: " . json_last_error_msg());
            return false;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Daha uzun timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        
        if ($curl_errno !== 0) {
            error_log("AI Helper CURL Error #$curl_errno: " . $curl_error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log("AI Helper API Error: HTTP $http_code");
            error_log("AI Helper API Response: " . substr($response, 0, 1000));
            return false;
        }
        
        if (empty($response)) {
            error_log("AI Helper: Boş API yanıtı");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AI Helper JSON Parse Error: " . json_last_error_msg());
            return false;
        }
        
        if (isset($result['error'])) {
            error_log("AI Helper API Error Response: " . print_r($result['error'], true));
            return false;
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            error_log("AI Helper: Geçersiz yanıt yapısı");
            return false;
        }
        
        return trim($result['choices'][0]['message']['content']);
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_improve_text: " . $e->getMessage());
        return false;
    }
}

/**
 * Metin çevirisi
 * 
 * @param string $text Çevrilecek metin
 * @param string $target_language Hedef dil (ingilizce, almanca, vs.)
 * @return string|false Çevrilmiş metin
 */
function ai_translate_text($text, $target_language = 'ingilizce') {
    try {
        $prompt = "Aşağıdaki metni " . $target_language . " diline çevir:\n\n";
        $prompt .= $text . "\n\n";
        $prompt .= "Sadece çeviriyi döndür, açıklama ekleme.";
        
        return ai_generate_text($prompt, ['original_text' => $text], 'general');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_translate_text: " . $e->getMessage());
        return false;
    }
}

/**
 * Metin özetleme
 * 
 * @param string $text Özetlenecek metin
 * @param int $max_words Maksimum kelime sayısı
 * @return string|false Özet
 */
function ai_summarize_text($text, $max_words = 100) {
    try {
        $prompt = "Aşağıdaki metni özetle (maksimum " . $max_words . " kelime):\n\n";
        $prompt .= $text . "\n\n";
        $prompt .= "Önemli bilgileri koru, sadece özeti döndür.";
        
        return ai_generate_text($prompt, ['original_text' => $text], 'general');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_summarize_text: " . $e->getMessage());
        return false;
    }
}

/**
 * SEO meta açıklama oluştur
 * 
 * @param string $title Başlık
 * @param string $content İçerik
 * @return string|false Meta açıklama (150-160 karakter)
 */
function ai_generate_seo_meta_description($title, $content = '') {
    try {
        $prompt = "SEO meta açıklaması oluştur:\n";
        $prompt .= "Başlık: " . $title . "\n";
        if (!empty($content)) {
            $prompt .= "İçerik: " . substr($content, 0, 500) . "\n";
        }
        $prompt .= "\n150-160 karakter arası, SEO uyumlu, çekici bir meta açıklama yaz.";
        
        return ai_generate_text($prompt, ['title' => $title], 'product');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_seo_meta_description: " . $e->getMessage());
        return false;
    }
}

/**
 * Email şablonu oluştur
 * 
 * @param string $purpose Amaç (duyuru, hatırlatma, teşekkür, davet)
 * @param array $context Bağlam
 * @return string|false HTML email şablonu
 */
function ai_generate_email_template($purpose, $context = []) {
    try {
        $prompt = "Email şablonu oluştur (HTML formatında):\n";
        $prompt .= "Amaç: " . $purpose . "\n";
        
        if (!empty($context['event_title'])) {
            $prompt .= "Etkinlik: " . $context['event_title'] . "\n";
        }
        
        if (!empty($context['club_name'])) {
            $prompt .= "Topluluk: " . $context['club_name'] . "\n";
        }
        
        if (!empty($context['subject'])) {
            $prompt .= "Konu: " . $context['subject'] . "\n";
        }
        
        $prompt .= "\nProfesyonel, modern ve mobil uyumlu bir HTML email şablonu oluştur. ";
        $prompt .= "Başlık, içerik ve çağrı butonu içersin. Sadece HTML kodunu döndür.";
        
        return ai_generate_text($prompt, $context, 'general');
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_generate_email_template: " . $e->getMessage());
        return false;
    }
}

/**
 * Raporları analiz et ve AI önerileri oluştur
 * 
 * @param object $db Veritabanı bağlantısı
 * @return array|false Analiz sonuçları ve öneriler
 */
function ai_analyze_reports($db) {
    try {
        $club_id = defined('CLUB_ID') ? CLUB_ID : 1;
        
        // Etkinlik istatistikleri
        $events_stmt = @$db->prepare("SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN status = 'tamamlandı' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'planlanıyor' THEN 1 ELSE 0 END) as planned,
            SUM(CASE WHEN date >= date('now') THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN date < date('now') THEN 1 ELSE 0 END) as past
            FROM events WHERE club_id = ?");
        $events_data = ['total' => 0, 'completed' => 0, 'planned' => 0, 'upcoming' => 0, 'past' => 0];
        if ($events_stmt) {
            $events_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $events_result = @$events_stmt->execute();
            if ($events_result) {
                $events_data = $events_result->fetchArray(SQLITE3_ASSOC) ?: $events_data;
            }
        }
        
        // Katılım ve trend istatistikleri
        $recent_events = 0;
        $previous_events = 0;
        $recent_stmt = @$db->prepare("SELECT COUNT(*) as count FROM events 
            WHERE club_id = ? AND date >= date('now', '-3 months') AND date < date('now')");
        if ($recent_stmt) {
            $recent_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $recent_result = @$recent_stmt->execute();
            if ($recent_result) {
                $recent_row = $recent_result->fetchArray(SQLITE3_ASSOC);
                $recent_events = (int)($recent_row['count'] ?? 0);
            }
        }
        $prev_stmt = @$db->prepare("SELECT COUNT(*) as count FROM events 
            WHERE club_id = ? AND date >= date('now', '-6 months') AND date < date('now', '-3 months')");
        if ($prev_stmt) {
            $prev_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $prev_result = @$prev_stmt->execute();
            if ($prev_result) {
                $prev_row = $prev_result->fetchArray(SQLITE3_ASSOC);
                $previous_events = (int)($prev_row['count'] ?? 0);
            }
        }
        $event_trend = $previous_events > 0 ? round((($recent_events - $previous_events) / max($previous_events,1)) * 100, 1) : 0;
        
        // Üye istatistikleri
        $members_stmt = @$db->prepare("SELECT COUNT(*) as total FROM members WHERE club_id = ?");
        $members_data = ['total' => 0];
        if ($members_stmt) {
            $members_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $members_result = @$members_stmt->execute();
            if ($members_result) {
                $members_data = $members_result->fetchArray(SQLITE3_ASSOC) ?: $members_data;
            }
        }
        $new_members_stmt = @$db->prepare("SELECT COUNT(*) as count FROM members 
            WHERE club_id = ? AND created_at >= date('now', '-3 months')");
        $new_members = 0;
        if ($new_members_stmt) {
            $new_members_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $new_members_result = @$new_members_stmt->execute();
            if ($new_members_result) {
                $new_members_row = $new_members_result->fetchArray(SQLITE3_ASSOC);
                $new_members = (int)($new_members_row['count'] ?? 0);
            }
        }
        
        // Kampanya istatistikleri
        $campaigns_stmt = @$db->prepare("SELECT COUNT(*) as total, 
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            FROM campaigns WHERE club_id = ?");
        $campaigns_data = ['total' => 0, 'active' => 0];
        if ($campaigns_stmt) {
            $campaigns_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $campaigns_result = @$campaigns_stmt->execute();
            if ($campaigns_result) {
                $campaigns_data = $campaigns_result->fetchArray(SQLITE3_ASSOC) ?: $campaigns_data;
            }
        }
        
        // Ürün istatistikleri
        $products_stmt = @$db->prepare("SELECT COUNT(*) as total, 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(price) as total_value,
            AVG(CASE WHEN status = 'active' THEN price ELSE NULL END) as avg_price
            FROM products WHERE club_id = ?");
        $products_data = ['total' => 0, 'active' => 0, 'total_value' => 0, 'avg_price' => 0];
        if ($products_stmt) {
            $products_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $products_result = @$products_stmt->execute();
            if ($products_result) {
                $products_data = $products_result->fetchArray(SQLITE3_ASSOC) ?: $products_data;
            }
        }
        
        // Email/SMS istatistikleri
        $email_sms_stmt = @$db->prepare("SELECT 
            SUM(CASE WHEN type = 'email' THEN sent_count ELSE 0 END) as total_emails,
            SUM(CASE WHEN type = 'sms' THEN sent_count ELSE 0 END) as total_sms
            FROM email_sms_logs WHERE club_id = ?");
        $communication_data = ['total_emails' => 0, 'total_sms' => 0];
        if ($email_sms_stmt) {
            $email_sms_stmt->bindValue(1, $club_id, SQLITE3_INTEGER);
            $email_sms_result = @$email_sms_stmt->execute();
            if ($email_sms_result) {
                $communication_data = $email_sms_result->fetchArray(SQLITE3_ASSOC) ?: $communication_data;
            }
        }
        
        // Verileri topla
        $data = [
            'events' => [
                'total' => (int)($events_data['total'] ?? 0),
                'completed' => (int)($events_data['completed'] ?? 0),
                'planned' => (int)($events_data['planned'] ?? 0),
                'upcoming' => (int)($events_data['upcoming'] ?? 0),
                'past' => (int)($events_data['past'] ?? 0),
                'recent_3months' => $recent_events,
                'previous_3months' => $previous_events,
                'trend_percentage' => $event_trend,
            ],
            'members' => [
                'total' => (int)($members_data['total'] ?? 0),
                'new_last_3months' => $new_members,
            ],
            'campaigns' => [
                'total' => (int)($campaigns_data['total'] ?? 0),
                'active' => (int)($campaigns_data['active'] ?? 0),
            ],
            'products' => [
                'total' => (int)($products_data['total'] ?? 0),
                'active' => (int)($products_data['active'] ?? 0),
                'total_value' => round((float)($products_data['total_value'] ?? 0), 2),
                'avg_price' => round((float)($products_data['avg_price'] ?? 0), 2),
            ],
            'communication' => [
                'total_emails' => (int)($communication_data['total_emails'] ?? 0),
                'total_sms' => (int)($communication_data['total_sms'] ?? 0),
            ],
        ];
        
        // Yerel özet ve öneriler oluştur
        $local_summary = build_local_analysis_summary($data);
        $local_insights = build_local_insights($data);
        $local_recommendations = build_local_recommendations($data);
        
        // AI çağrısı (opsiyonel)
        $ai_summary = null;
        $ai_prompt = "Verileri analiz et ve maksimum 3 insight + 3 aksiyon öner. Format: Insight 1..., Insight 2..., Recommendation 1..., Recommendation 2...\n\n";
        $ai_prompt .= "Veriler: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        
        $ai_response = ai_generate_text($ai_prompt, ['data' => $data], 'general', 1200);
        if ($ai_response !== false) {
            $ai_summary = trim($ai_response);
        }
        
        return [
            'summary' => $local_summary,
            'data' => $data,
            'insights' => $local_insights,
            'recommendations' => $local_recommendations,
            'ai_summary' => $ai_summary,
        ];
    } catch (Exception $e) {
        error_log("AI Helper: Exception in ai_analyze_reports: " . $e->getMessage());
        error_log("AI Helper: Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function build_local_analysis_summary($data) {
    $events_total = $data['events']['total'];
    $members_total = $data['members']['total'];
    $new_members = $data['members']['new_last_3months'];
    $campaigns_total = $data['campaigns']['total'];
    $products_total = $data['products']['total'];
    
    $summary = [];
    $summary[] = "Toplam {$events_total} etkinlikten {$data['events']['completed']} tanesi tamamlandı, {$data['events']['planned']} tanesi planlama aşamasında.";
    $summary[] = "Üye havuzu {$members_total} kişiye ulaştı; son 3 ayda {$new_members} yeni üye kazanıldı.";
    $summary[] = "Kampanya envanterinde {$campaigns_total} kayıt var ve {$data['campaigns']['active']} kampanya aktif durumda.";
    $summary[] = "Market bölümünde {$products_total} ürün bulunuyor, toplam portföy değeri yaklaşık " . number_format($data['products']['total_value'], 0, ',', '.') . " TL.";
    
    return implode(" ", $summary);
}

function build_local_insights($data) {
    $insights = [];
    
    if ($data['events']['trend_percentage'] > 10) {
        $insights[] = "Etkinlik üretimi son 3 ayda önceki döneme göre %" . $data['events']['trend_percentage'] . " arttı; bu ivme korunmalı.";
    } elseif ($data['events']['trend_percentage'] < -10) {
        $insights[] = "Etkinlik sayısı önceki döneme göre geriledi (%" . $data['events']['trend_percentage'] . "); içerik çeşitliliği gözden geçirilmeli.";
    } else {
        $insights[] = "Etkinlik temposu stabil seyrediyor; katılım kalitesi artırılabilir.";
    }
    
    if ($data['members']['new_last_3months'] > ($data['members']['total'] * 0.1)) {
        $insights[] = "Üye büyümesi yüksek; onboarding sürecine yatırım yaparak bağlılık arttırılabilir.";
    } else {
        $insights[] = "Üye kazanımı düşük; kampanya ve referans mekanizmaları güçlendirilmeli.";
    }
    
    $active_campaign_ratio = $data['campaigns']['total'] > 0 ? round(($data['campaigns']['active'] / max($data['campaigns']['total'],1)) * 100, 1) : 0;
    $insights[] = "Kampanyaların %" . $active_campaign_ratio . " kadarı aktif durumda; dönüşüm odaklı segmentlere öncelik verilmeli.";
    
    return $insights;
}

function build_local_recommendations($data) {
    $recommendations = [];
    
    if ($data['events']['upcoming'] < 3) {
        $recommendations[] = "Takvimde sadece {$data['events']['upcoming']} yaklaşan etkinlik bulunuyor; en az 6 haftalık plan oluşturun.";
    } else {
        $recommendations[] = "Yaklaşan {$data['events']['upcoming']} etkinlik için kişiselleştirilmiş davet serileri tasarlayın.";
    }
    
    if ($data['members']['new_last_3months'] < 5) {
        $recommendations[] = "Yeni üye kazanımı düşük; mevcut üyeleri teşvik edecek referans kampanyası başlatın.";
    } else {
        $recommendations[] = "Yeni üye akışı güçlü; hoş geldin otomasyonlarını geliştirin ve ilk 30 gün deneyimini ölçün.";
    }
    
    if ($data['campaigns']['active'] == 0 && $data['campaigns']['total'] > 0) {
        $recommendations[] = "Hiç aktif kampanya bulunmuyor; en son başarılı kampanyayı tekrar aktive edin.";
    } else {
        $recommendations[] = "Aktif kampanyalar için performans raporları çıkararak düşük performanslı olanları optimize edin.";
    }
    
    if ($data['products']['total_value'] > 0 && $data['products']['avg_price'] < 100) {
        $recommendations[] = "Ürün portföyü düşük fiyatlı; paket ürünler oluşturarak sepet değerini yükseltin.";
    } else {
        $recommendations[] = "Yüksek değerli ürünleri segment bazlı tekliflerle eşleştirerek gelir çeşitlendirmesi sağlayın.";
    }
    
    return array_slice($recommendations, 0, 4);
}

/**
 * Analiz metninden öngörüleri çıkar
 */
function extract_insights($text) {
    // Basit regex ile öngörüleri bul
    $insights = [];
    if (preg_match_all('/[•\-\*]\s*([^\n]+)/u', $text, $matches)) {
        $insights = array_slice($matches[1], 0, 5);
        $insights = array_map('trim', $insights);
        $insights = array_filter($insights, function($item) {
            return strlen($item) > 10;
        });
    }
    return array_values($insights);
}

/**
 * Analiz metninden önerileri çıkar
 */
function extract_recommendations($text) {
    $recommendations = [];
    
    // Önce numaralı liste formatını dene (1. 2. 3. gibi)
    if (preg_match_all('/\d+[\.\)]\s+([^\n]+(?:\n(?!\d+[\.\)]|\n)[^\n]+)*)/u', $text, $matches)) {
        foreach ($matches[1] as $match) {
            $cleaned = trim($match);
            // Çok kısa veya çok uzun önerileri filtrele
            if (strlen($cleaned) > 20 && strlen($cleaned) < 500) {
                // Sadece öneri içeren satırları al (başlık değil)
                if (!preg_match('/^(?:LÜTFEN|ANALİZ|VERİLER|ETKİNLİK|ÜYE|KAMPANYA|ÜRÜN|İLETİŞİM)/iu', $cleaned)) {
                    $recommendations[] = $cleaned;
                }
            }
        }
    }
    
    // Eğer yeterli öneri bulunamadıysa, "öner" kelimesi içeren cümleleri bul
    if (count($recommendations) < 3 && preg_match_all('/(?:öner|tavsiye|yapılmalı|yapılması|öneri|strateji|yaklaşım|taktik)[:.]?\s*([^\n\.]+(?:\.|$))/iu', $text, $matches)) {
        foreach ($matches[1] as $match) {
            $cleaned = trim($match);
            if (strlen($cleaned) > 30 && strlen($cleaned) < 400) {
                // Zaten eklenmiş mi kontrol et
                $is_duplicate = false;
                foreach ($recommendations as $existing) {
                    if (similar_text($existing, $cleaned) > 80) {
                        $is_duplicate = true;
                        break;
                    }
                }
                if (!$is_duplicate) {
                    $recommendations[] = $cleaned;
                }
            }
        }
    }
    
    // En fazla 7 öneri döndür
    return array_slice(array_values($recommendations), 0, 7);
}

/**
 * AI credentials'ı yükle
 */
function load_ai_credentials() {
    static $credentials = null;
    
    if ($credentials === null) {
        // PROJECT_ROOT tanımlı mı kontrol et
        if (!defined('PROJECT_ROOT')) {
            // Otomatik tespit et
            $current_dir = __DIR__;
            // lib/ai/ klasöründen project root'a çık
            $project_root = dirname(dirname($current_dir));
            define('PROJECT_ROOT', $project_root);
        }
        
        $config_path = PROJECT_ROOT . '/config/credentials.php';
        
        if (file_exists($config_path)) {
            $credentials = require $config_path;
        } else {
            $credentials = [
            'groq' => [
                'api_key' => '',
                'model' => 'llama-3.3-70b-versatile'
            ]
            ];
        }
    }
    
    return $credentials;
}

/**
 * Sistem prompt'unu tipine göre al
 */
function get_system_prompt($type) {
    $prompts = [
        'event' => 'Sen bir etkinlik yönetim sistemi için içerik oluşturan bir asistansın. Türkçe, profesyonel ve çekici etkinlik açıklamaları yazarsın.',
        'campaign' => 'Sen bir kampanya yönetim sistemi için içerik oluşturan bir asistansın. Türkçe, çekici ve satış odaklı kampanya metinleri yazarsın.',
        'product' => 'Sen bir e-ticaret sistemi için ürün açıklamaları yazan bir asistansın. Türkçe, detaylı ve SEO uyumlu ürün açıklamaları yazarsın.',
        'general' => 'Sen yardımcı bir AI asistanısın. Türkçe, profesyonel ve yararlı içerikler üretirsin.'
    ];
    
    return $prompts[$type] ?? $prompts['general'];
}

/**
 * Prompt'u zenginleştir
 */
function build_enhanced_prompt($prompt, $context, $type) {
    if (empty($context)) {
        return $prompt;
    }
    
    $enhanced = $prompt;
    
    // Context'ten önemli bilgileri ekle
    if (isset($context['club_name'])) {
        $enhanced .= "\n\nTopluluk adı: " . $context['club_name'];
    }
    
    return $enhanced;
}

