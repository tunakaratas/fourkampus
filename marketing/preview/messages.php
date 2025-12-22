<?php
// Demo Preview - SMS Messages
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - SMS Messages Preview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 24px;
            min-height: 100vh;
        }
        .messages-container {
            max-width: 1200px;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            font-size: clamp(24px, 4vw, 32px);
            font-weight: 800;
            color: #111827;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .sms-form {
            background: white;
            padding: clamp(20px, 3vw, 32px);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
        }
        .form-group {
            margin-bottom: 24px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #111827;
            font-size: 14px;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        .recipient-selector {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .recipient-option {
            flex: 1;
            min-width: 150px;
        }
        .btn-send {
            background: #6366f1;
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 0.375rem;
            border: 1px solid #6366f1;
            font-weight: 500;
            width: 100%;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-send:hover {
            background: #4f46e5;
        }
        .sms-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .sms-info i {
            color: #3b82f6;
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .sms-info-content {
            flex: 1;
        }
        .sms-info-title {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .sms-info-text {
            font-size: 13px;
            color: #1e3a8a;
            line-height: 1.5;
        }
        .history-section {
            background: white;
            padding: clamp(20px, 3vw, 32px);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
        }
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .history-title {
            font-size: clamp(18px, 2.5vw, 20px);
            font-weight: 700;
            color: #111827;
        }
        .history-item {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-item:hover {
            background: #f9fafb;
        }
        .history-title-item {
            font-weight: 600;
            margin-bottom: 8px;
            color: #111827;
            font-size: 16px;
        }
        .history-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: #6b7280;
            flex-wrap: wrap;
        }
        .history-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .history-meta-item i {
            color: #6366f1;
            font-size: 12px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        .char-count {
            text-align: right;
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        .char-count.warning {
            color: #f59e0b;
        }
        .char-count.error {
            color: #ef4444;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .page-title {
            color: #ffffff;
        }
        body.dark-mode .sms-form {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode label {
            color: #ffffff;
        }
        body.dark-mode input,
        body.dark-mode textarea,
        body.dark-mode select {
            background: #000000;
            border-color: rgba(99, 102, 241, 0.3);
            color: #ffffff;
        }
        body.dark-mode input:focus,
        body.dark-mode textarea:focus,
        body.dark-mode select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        body.dark-mode input::placeholder,
        body.dark-mode textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        body.dark-mode .btn-send {
            background: #8b5cf6;
            border-color: #8b5cf6;
        }
        body.dark-mode .btn-send:hover {
            background: #7c3aed;
        }
        body.dark-mode .sms-info {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
        }
        body.dark-mode .sms-info i {
            color: #8b5cf6;
        }
        body.dark-mode .sms-info-title {
            color: #8b5cf6;
        }
        body.dark-mode .sms-info-text {
            color: #cbd5e1;
        }
        body.dark-mode .history-section {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode .history-title {
            color: #ffffff;
        }
        body.dark-mode .history-item {
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .history-item:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        body.dark-mode .history-title-item {
            color: #ffffff;
        }
        body.dark-mode .history-meta {
            color: #cbd5e1;
        }
        body.dark-mode .history-meta-item i {
            color: #8b5cf6;
        }
        body.dark-mode .status-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        body.dark-mode .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        body.dark-mode .status-failed {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        body.dark-mode .char-count {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="messages-container">
        <div class="page-header">
            <h1 class="page-title">SMS Gönder</h1>
            <span class="premium-badge">
                <i class="fas fa-crown"></i>
                Premium Özellik
            </span>
        </div>

        <div class="sms-info">
            <i class="fas fa-info-circle"></i>
            <div class="sms-info-content">
                <div class="sms-info-title">SMS Gönderimi Hakkında</div>
                <div class="sms-info-text">
                    SMS gönderimi premium pakete dahildir ve ekstra ücretlidir. Her SMS için operatör tarifesine göre ücretlendirme yapılır. 
                    Email bildirimleri ücretsizdir ve sınırsızdır.
                </div>
            </div>
        </div>

        <div class="sms-form">
            <div class="form-group">
                <label for="recipient_type">Alıcı Seçimi</label>
                <div class="recipient-selector">
                    <select id="recipient_type" class="recipient-option">
                        <option value="all">Tüm Üyeler</option>
                        <option value="selected">Seçili Üyeler</option>
                        <option value="group">Grup Seç</option>
                        <option value="event">Etkinlik Katılımcıları</option>
                    </select>
                    <select class="recipient-option">
                        <option value="">Etkinlik Seçin</option>
                        <option value="1">Teknoloji Semineri</option>
                        <option value="2">Networking Etkinliği</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="sms_message">SMS Mesajı</label>
                <textarea id="sms_message" placeholder="Mesajınızı yazın... (Maksimum 160 karakter)">Merhaba! Etkinliğimiz 15 Ocak'ta saat 14:00'te başlıyor. Katılımınızı bekliyoruz.</textarea>
                <div class="char-count">98 / 160 karakter</div>
            </div>

            <div class="form-group">
                <label for="sender_name">Gönderen Adı (Opsiyonel)</label>
                <input type="text" id="sender_name" placeholder="Four Kampüs" value="Four Kampüs">
            </div>

            <button class="btn-send">
                <i class="fas fa-paper-plane"></i>
                SMS Gönder (248 üye)
            </button>
        </div>

        <div class="history-section">
            <div class="history-header">
                <h2 class="history-title">Gönderim Geçmişi</h2>
            </div>

            <div class="history-item">
                <div class="history-title-item">Etkinlik Hatırlatması - Teknoloji Semineri</div>
                <div class="history-meta">
                    <div class="history-meta-item">
                        <i class="fas fa-users"></i>
                        <span>248 alıcı</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>14 Ocak 2025, 10:30</span>
                    </div>
                    <div class="history-meta-item">
                        <span class="status-badge status-success">
                            <i class="fas fa-check-circle"></i>
                            Başarılı
                        </span>
                    </div>
                </div>
            </div>

            <div class="history-item">
                <div class="history-title-item">Yeni Etkinlik Duyurusu</div>
                <div class="history-meta">
                    <div class="history-meta-item">
                        <i class="fas fa-users"></i>
                        <span>248 alıcı</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>12 Ocak 2025, 15:45</span>
                    </div>
                    <div class="history-meta-item">
                        <span class="status-badge status-success">
                            <i class="fas fa-check-circle"></i>
                            Başarılı
                        </span>
                    </div>
                </div>
            </div>

            <div class="history-item">
                <div class="history-title-item">Üyelik Onay Bildirimi</div>
                <div class="history-meta">
                    <div class="history-meta-item">
                        <i class="fas fa-users"></i>
                        <span>15 alıcı</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>10 Ocak 2025, 09:20</span>
                    </div>
                    <div class="history-meta-item">
                        <span class="status-badge status-pending">
                            <i class="fas fa-clock"></i>
                            Gönderiliyor
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const smsTextarea = document.getElementById('sms_message');
        const charCount = document.querySelector('.char-count');
        
        smsTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length + ' / 160 karakter';
            
            if (length > 160) {
                charCount.classList.add('error');
                charCount.classList.remove('warning');
            } else if (length > 140) {
                charCount.classList.add('warning');
                charCount.classList.remove('error');
            } else {
                charCount.classList.remove('warning', 'error');
            }
        });
    </script>
</body>
</html>
