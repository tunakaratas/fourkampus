<?php
// Demo Preview - Dashboard
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - Dashboard Preview</title>
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
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }
        .page-subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-left: 5px solid #6366f1;
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        .stat-card.success {
            border-left-color: #10b981;
        }
        .stat-card.success::before {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, transparent 100%);
        }
        .stat-card.purple {
            border-left-color: #8b5cf6;
        }
        .stat-card.purple::before {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, transparent 100%);
        }
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        .stat-card.warning::before {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, transparent 100%);
        }
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .stat-card.purple .stat-icon {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .stat-title {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }
        .recent-section {
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }
        .section-link {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .event-item {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }
        .event-item:last-child {
            border-bottom: none;
        }
        .event-item:hover {
            background: #f9fafb;
        }
        .event-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .event-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            color: #6b7280;
        }
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .event-meta-item i {
            font-size: 12px;
            color: #6366f1;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .page-title {
            color: #ffffff;
        }
        body.dark-mode .page-subtitle {
            color: #cbd5e1;
        }
        body.dark-mode .stat-card {
            background: #0a0a0a;
            border-left-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode .stat-card.success {
            border-left-color: rgba(16, 185, 129, 0.5);
        }
        body.dark-mode .stat-card.purple {
            border-left-color: rgba(139, 92, 246, 0.5);
        }
        body.dark-mode .stat-card.warning {
            border-left-color: rgba(245, 158, 11, 0.5);
        }
        body.dark-mode .stat-title {
            color: #94a3b8;
        }
        body.dark-mode .stat-value {
            color: #ffffff;
        }
        body.dark-mode .recent-section {
            background: #0a0a0a;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode .section-title {
            color: #ffffff;
        }
        body.dark-mode .section-link {
            color: #8b5cf6;
        }
        body.dark-mode .event-item {
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .event-item:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        body.dark-mode .event-title {
            color: #ffffff;
        }
        body.dark-mode .event-meta {
            color: #cbd5e1;
        }
        body.dark-mode .event-meta-item i {
            color: #8b5cf6;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="page-header">
            <h1 class="page-title">Kontrol Paneli</h1>
            <p class="page-subtitle">Topluluğunuzun genel görünümü</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Toplam Üye</div>
                        <div class="stat-value">247</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Yaklaşan Etkinlik</div>
                        <div class="stat-value">8</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Yönetim Kurulu</div>
                        <div class="stat-value">12</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Toplam Etkinlik</div>
                        <div class="stat-value">45</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="recent-section">
            <div class="section-header">
                <h2 class="section-title">Yaklaşan Etkinlikler</h2>
                <a href="#" class="section-link">Tümünü Gör <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="event-item">
                <div class="event-title">Teknoloji Semineri</div>
                <div class="event-meta">
                    <div class="event-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>15 Ocak 2025</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>14:00</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Konferans Salonu</span>
                    </div>
                </div>
            </div>
            <div class="event-item">
                <div class="event-title">Networking Etkinliği</div>
                <div class="event-meta">
                    <div class="event-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>18 Ocak 2025</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>18:00</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Kafeterya</span>
                    </div>
                </div>
            </div>
            <div class="event-item">
                <div class="event-title">Workshop: Web Geliştirme</div>
                <div class="event-meta">
                    <div class="event-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>22 Ocak 2025</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>10:00</span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Bilgisayar Lab</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
