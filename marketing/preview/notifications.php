<?php
// Demo Preview - Notifications
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniPanel - Notifications Preview</title>
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
            padding: 20px;
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
        }
        .notifications-container {
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .page-title {
            font-size: clamp(24px, 4vw, 32px);
            font-weight: 800;
            color: #111827;
            margin-bottom: 24px;
            word-wrap: break-word;
        }
        .notification-form {
            background: white;
            padding: clamp(20px, 3vw, 32px);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
            width: 100%;
            box-sizing: border-box;
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
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #6366f1;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
            max-width: 100%;
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
        }
        .btn-send:hover {
            background: #4f46e5;
        }
        .history-section {
            background: white;
            padding: clamp(20px, 3vw, 32px);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            box-sizing: border-box;
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
            word-wrap: break-word;
        }
        .history-item {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
            width: 100%;
            box-sizing: border-box;
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
            font-size: clamp(14px, 2vw, 16px);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .history-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #6b7280;
            flex-wrap: wrap;
        }
        .history-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .history-meta-item i {
            color: #6366f1;
            font-size: 12px;
            flex-shrink: 0;
        }
        .history-meta-item span {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .badge-success {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .page-title {
            color: #ffffff;
        }
        body.dark-mode .notification-form {
            background: #0a0a0a;
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
        body.dark-mode .history-section {
            background: #0a0a0a;
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
        body.dark-mode .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="notifications-container">
        <h1 class="page-title">Bildirimler</h1>
        
        <div class="notification-form">
            <div class="form-group">
                <label>Alıcılar</label>
                <select>
                    <option>Tüm Üyeler</option>
                    <option>Seçili Üyeler</option>
                </select>
            </div>
            <div class="form-group">
                <label>Gönderim Tipi</label>
                <select>
                    <option>Email</option>
                    <option>SMS</option>
                    <option>Email + SMS</option>
                </select>
            </div>
            <div class="form-group">
                <label>Konu</label>
                <input type="text" value="Yeni Etkinlik Duyurusu">
            </div>
            <div class="form-group">
                <label>Mesaj</label>
                <textarea>Merhaba, yaklaşan etkinliğimiz hakkında bilgilendirmek istiyoruz. Detaylar için etkinlik sayfasını ziyaret edebilirsiniz.</textarea>
            </div>
            <button class="btn-send">
                <i class="fas fa-paper-plane"></i>
                Bildirim Gönder
            </button>
        </div>

        <div class="history-section">
            <div class="history-header">
                <h2 class="history-title">Gönderim Geçmişi</h2>
            </div>
            <div class="history-item">
                <div class="history-title-item">Etkinlik Duyurusu</div>
                <div class="history-meta">
                    <div class="history-meta-item">
                        <i class="fas fa-users"></i>
                        <span>247 üyeye gönderildi</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-envelope"></i>
                        <span>Email</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>10 Ocak 2025</span>
                    </div>
                    <span class="badge-success">
                        <i class="fas fa-check-circle"></i>
                        Başarılı
                    </span>
                </div>
            </div>
            <div class="history-item">
                <div class="history-title-item">Toplantı Hatırlatması</div>
                <div class="history-meta">
                    <div class="history-meta-item">
                        <i class="fas fa-users"></i>
                        <span>12 üyeye gönderildi</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-sms"></i>
                        <span>SMS</span>
                    </div>
                    <div class="history-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>8 Ocak 2025</span>
                    </div>
                    <span class="badge-success">
                        <i class="fas fa-check-circle"></i>
                        Başarılı
                    </span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
