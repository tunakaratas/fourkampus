<?php
/**
 * Groq API Helper - Ücretsiz AI API
 * DeepSeek yerine Groq kullanmak için alternatif
 */

/**
 * Groq API ile metin oluştur
 */
function call_groq_api($api_key, $model, $system_prompt, $user_prompt, $max_tokens = 2000) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    
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
        'max_tokens' => (int)$max_tokens
    ];
    
    $json_data = json_encode($data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Groq API: JSON encode error: " . json_last_error_msg());
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // 90 saniye timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    if ($curl_errno !== 0) {
        error_log("Groq API CURL Error #$curl_errno: " . $curl_error);
        return false;
    }
    
    if ($http_code !== 200) {
        error_log("Groq API Error: HTTP $http_code");
        error_log("Groq API Response: " . substr($response, 0, 1000));
        return false;
    }
    
    if (empty($response)) {
        error_log("Groq API: Boş yanıt");
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Groq API JSON Parse Error: " . json_last_error_msg());
        return false;
    }
    
    if (isset($result['error'])) {
        error_log("Groq API Error Response: " . print_r($result['error'], true));
        return false;
    }
    
    if (!isset($result['choices'][0]['message']['content'])) {
        error_log("Groq API: Geçersiz yanıt yapısı");
        return false;
    }
    
    return trim($result['choices'][0]['message']['content']);
}

