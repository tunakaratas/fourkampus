<?php
/**
 * Destek Talebi Yönetim Fonksiyonları
 */

/**
 * Ticket tablosunu oluştur
 */
function ensure_support_tickets_table($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            community_id TEXT NOT NULL,
            community_name TEXT,
            ticket_number TEXT UNIQUE NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT DEFAULT 'open',
            priority TEXT DEFAULT 'normal',
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            resolved_by TEXT
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            reply_type TEXT DEFAULT 'reply',
            message TEXT NOT NULL,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
        )");
        
        // Index'ler
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ticket_community ON support_tickets(community_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ticket_status ON support_tickets(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ticket_number ON support_tickets(ticket_number)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_reply_ticket ON support_ticket_replies(ticket_id)");
    } catch (Exception $e) {
        tpl_error_log("Support tickets table creation error: " . $e->getMessage());
    }
}

/**
 * Destek talebi sekmesi görünümü
 */
function render_support_view($db) {
    // SSS (Sık Sorulan Sorular) listesi
    $faq_items = [
        [
            'question' => 'Abonelik paketimi nasıl yükseltebilirim?',
            'answer' => 'Abonelik Yönetimi sekmesinden mevcut paketinizi görüntüleyebilir ve daha yüksek bir pakete geçiş yapabilirsiniz. Profesyonel veya Business paketlerinden birini seçerek ödeme yapabilirsiniz.'
        ],
        [
            'question' => 'SMS gönderiminde sorun yaşıyorum',
            'answer' => 'SMS gönderiminde sorun yaşıyorsanız, öncelikle SMS Merkezi ayarlarınızı kontrol edin. NetGSM veya Twilio ayarlarınızın doğru yapılandırıldığından emin olun. Sorun devam ederse destek ekibimizle iletişime geçin.'
        ],
        [
            'question' => 'Mail gönderiminde hata alıyorum',
            'answer' => 'Mail gönderiminde sorun yaşıyorsanız, SMTP ayarlarınızı kontrol edin. Mail Merkezi sekmesinden SMTP ayarlarınızı test edebilir ve gerekirse güncelleyebilirsiniz.'
        ],
        [
            'question' => 'Etkinlik oluştururken hata alıyorum',
            'answer' => 'Etkinlik oluştururken hata alıyorsanız, lütfen tüm zorunlu alanların doldurulduğundan emin olun. Tarih ve saat bilgilerinin doğru formatta olduğunu kontrol edin.'
        ],
        [
            'question' => 'Üye eklerken hata alıyorum',
            'answer' => 'Üye eklerken hata alıyorsanız, öğrenci numarası, e-posta ve telefon numarasının benzersiz olduğundan emin olun. Aynı bilgilere sahip bir üye zaten sistemde kayıtlı olabilir.'
        ],
        [
            'question' => 'Ödeme işlemim tamamlanmadı',
            'answer' => 'Ödeme işleminiz tamamlanmadıysa, lütfen ödeme geçmişinizi kontrol edin. Sorun devam ederse, ödeme sağlayıcınızla (Iyzico) iletişime geçin veya destek ekibimizden yardım alın.'
        ],
        [
            'question' => 'Şifremi unuttum, nasıl sıfırlayabilirim?',
            'answer' => 'Şifrenizi sıfırlamak için giriş sayfasındaki "Şifremi Unuttum" linkini kullanabilirsiniz. E-posta adresinize şifre sıfırlama bağlantısı gönderilecektir.'
        ],
        [
            'question' => 'Raporlar sekmesinde veri göremiyorum',
            'answer' => 'Raporlar sekmesinde veri göremiyorsanız, öncelikle sistemde yeterli veri olduğundan emin olun. Etkinlikler, üyeler ve diğer verilerin düzgün şekilde kaydedildiğini kontrol edin.'
        ]
    ];
    
    // Ticket gönderme işlemi
    $ticket_success = null;
    $ticket_error = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_support_ticket') {
        ensure_support_tickets_table($db);
        
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            $ticket_error = 'Konu ve mesaj alanları zorunludur';
        } elseif (strlen($subject) > 200) {
            $ticket_error = 'Konu en fazla 200 karakter olabilir';
        } elseif (strlen($message) > 5000) {
            $ticket_error = 'Mesaj en fazla 5000 karakter olabilir';
        } else {
            try {
                // Community bilgilerini al
                $community_id = defined('CLUB_ID') ? CLUB_ID : (defined('COMMUNITY_ID') ? COMMUNITY_ID : 'unknown');
                $community_name = '';
                
                try {
                    $settings_stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'club_name' AND club_id = ? LIMIT 1");
                    if ($settings_stmt) {
                        $settings_stmt->bindValue(1, $community_id, SQLITE3_INTEGER);
                        $settings_result = $settings_stmt->execute();
                        if ($settings_result) {
                            $settings_row = $settings_result->fetchArray(SQLITE3_ASSOC);
                            if ($settings_row && !empty($settings_row['setting_value'])) {
                                $community_name = $settings_row['setting_value'];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Sessizce devam et
                }
                
                // Ticket numarası oluştur
                $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $community_id . rand()), 0, 8));
                
                // Ticket kaydet
                $stmt = $db->prepare("INSERT INTO support_tickets (community_id, community_name, ticket_number, subject, message, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bindValue(1, $community_id, SQLITE3_TEXT);
                $stmt->bindValue(2, $community_name, SQLITE3_TEXT);
                $stmt->bindValue(3, $ticket_number, SQLITE3_TEXT);
                $stmt->bindValue(4, $subject, SQLITE3_TEXT);
                $stmt->bindValue(5, $message, SQLITE3_TEXT);
                $stmt->bindValue(6, $_SESSION['admin_username'] ?? 'Admin', SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $ticket_success = 'Destek talebiniz başarıyla oluşturuldu. Ticket numaranız: <strong>' . htmlspecialchars($ticket_number) . '</strong>';
                } else {
                    $ticket_error = 'Destek talebi oluşturulurken bir hata oluştu';
                }
            } catch (Exception $e) {
                tpl_error_log("Create support ticket error: " . $e->getMessage());
                $ticket_error = 'Bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
    ?>
    
    <div class="support-view max-w-6xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Destek Talebi</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Sorununuzu bize iletin, size yardımcı olalım</p>
            </div>
            
            <?php if ($ticket_success): ?>
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <p class="text-sm text-green-800 dark:text-green-200"><?= $ticket_success ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($ticket_error): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm text-red-800 dark:text-red-200"><?= htmlspecialchars($ticket_error) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- SSS Bölümü -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Sık Sorulan Sorular</h3>
                    <div class="space-y-3 max-h-[600px] overflow-y-auto">
                        <?php foreach ($faq_items as $index => $faq): ?>
                            <div class="faq-item border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="selectFAQ(<?= $index ?>)">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                                            <?= htmlspecialchars($faq['question']) ?>
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 hidden faq-answer">
                                            <?= htmlspecialchars($faq['answer']) ?>
                                        </p>
                                    </div>
                                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-2 faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Destek Talebi Formu -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Destek Talebi Oluştur</h3>
                    <form method="POST" action="?view=support" id="supportTicketForm" class="space-y-4">
                        <input type="hidden" name="action" value="create_support_ticket">
                        <?= csrf_token_field() ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Konu <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subject" id="ticket-subject" required maxlength="200" 
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500"
                                   placeholder="Destek talebinizin konusunu yazın">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Mesaj <span class="text-red-500">*</span>
                            </label>
                            <textarea name="message" id="ticket-message" required rows="8" maxlength="5000"
                                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
                                      placeholder="Sorununuzu veya talebinizi detaylı bir şekilde açıklayın..."></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <span id="char-count">0</span> / 5000 karakter
                            </p>
                        </div>
                        
                        <button type="submit" 
                                class="w-full px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Gönder
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!function_exists('tpl_script_nonce_attr')) {
        function tpl_script_nonce_attr(): string
        {
            if (function_exists('tpl_get_csp_nonce')) {
                return ' nonce="' . htmlspecialchars(tpl_get_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
            }
            return '';
        }
    } ?>
    <script<?= tpl_script_nonce_attr(); ?>>
    document.addEventListener('DOMContentLoaded', function() {
        // Karakter sayacı
        const messageField = document.getElementById('ticket-message');
        const charCount = document.getElementById('char-count');
        
        if (messageField && charCount) {
            messageField.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
        
        // FAQ açma/kapama
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            item.addEventListener('click', function() {
                const answer = this.querySelector('.faq-answer');
                const icon = this.querySelector('.faq-icon');
                
                if (answer && icon) {
                    const isHidden = answer.classList.contains('hidden');
                    
                    // Tüm FAQ'ları kapat
                    faqItems.forEach(faq => {
                        faq.querySelector('.faq-answer')?.classList.add('hidden');
                        faq.querySelector('.faq-icon')?.classList.remove('rotate-180');
                    });
                    
                    // Tıklanan FAQ'ı aç
                    if (isHidden) {
                        answer.classList.remove('hidden');
                        icon.classList.add('rotate-180');
                    }
                }
            });
        });
    });
    
    // SSS seçildiğinde formu doldur
    function selectFAQ(index) {
        const faqItems = <?= json_encode($faq_items) ?>;
        const faq = faqItems[index];
        
        if (faq) {
            const subjectField = document.getElementById('ticket-subject');
            const messageField = document.getElementById('ticket-message');
            const charCount = document.getElementById('char-count');
            
            if (subjectField) {
                subjectField.value = faq.question;
            }
            
            if (messageField) {
                messageField.value = faq.answer;
                if (charCount) {
                    charCount.textContent = faq.answer.length;
                }
            }
            
            // Forma scroll yap
            document.getElementById('supportTicketForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    </script>
    
    <style>
    .faq-item {
        transition: all 0.2s;
    }
    .faq-item:hover {
        background-color: rgba(139, 92, 246, 0.05);
    }
    .faq-icon {
        transition: transform 0.2s;
    }
    .faq-icon.rotate-180 {
        transform: rotate(180deg);
    }
    </style>
    
    <?php
}

