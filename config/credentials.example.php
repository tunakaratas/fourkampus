<?php
/**
 * Credentials Configuration (EXAMPLE)
 * 
 * IMPORTANT: Copy this file to credentials.php and fill in your actual values.
 * The credentials.php file is excluded from git for security.
 */

return [
    'smtp' => [
        'host' => 'mail.example.com',        // SMTP sunucu adresi
        'port' => 587,                        // SMTP port (genellikle 587 veya 465)
        'username' => 'user@example.com',     // SMTP kullanıcı adı
        'password' => 'your-password-here',   // SMTP şifresi
        'from_email' => 'noreply@example.com', // Gönderen e-posta
        'from_name' => 'Four Kampüs',            // Gönderen adı
        'encryption' => 'tls'                 // 'tls' veya 'ssl'
    ],
    
    'netgsm' => [
        'username' => '8503022568',           // NetGSM kullanıcı adı (Abone No)
        'password' => 'your-netgsm-password', // NetGSM şifresi
        'msgheader' => '8503022568'           // Mesaj başlığı (genellikle kullanıcı adı ile aynı)
    ],
    
    // AI Özellikleri için Groq API (Ücretsiz)
    // API key almak için: https://console.groq.com/keys
    'groq' => [
        'api_key' => 'your-groq-api-key-here', // Groq API key
        'model' => 'llama-3.3-70b-versatile'   // Kullanılacak model (varsayılan: llama-3.3-70b-versatile)
    ]
];
