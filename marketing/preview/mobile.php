<?php
// Demo Preview - Mobile View
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniPanel - Mobile Preview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --mobile-primary-gradient: <?php echo $primaryGradient; ?>;
            --mobile-accent-color: <?php echo $accentColor; ?>;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--mobile-primary-gradient);
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .mobile-frame {
            width: 375px;
            max-width: 100%;
            background: #000;
            border-radius: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 8px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
            padding: 8px;
        }
        .mobile-screen {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            height: 812px;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .mobile-notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 30px;
            background: #000;
            border-radius: 0 0 20px 20px;
            z-index: 10;
        }
        .mobile-status-bar {
            height: 44px;
            background: var(--mobile-primary-gradient);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        .status-left {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .status-right {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .mobile-header {
            background: var(--mobile-primary-gradient);
            color: white;
            padding: 16px 20px 20px;
            position: relative;
        }
        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        .mobile-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .mobile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .mobile-content {
            flex: 1;
            overflow-y: auto;
            background: #f8fafc;
            padding: 20px;
            -webkit-overflow-scrolling: touch;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 18px;
        }
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-title span {
            font-size: 14px;
            font-weight: 500;
            color: var(--mobile-accent-color);
        }
        .event-card-mobile {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .event-card-mobile:active {
            transform: scale(0.98);
        }
        .event-image-mobile {
            width: 100%;
            height: 160px;
            background: var(--mobile-primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .event-image-mobile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.2;
        }
        .event-image-mobile i {
            font-size: 3rem;
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }
        .event-image-mobile.gradient-2 {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .event-image-mobile.gradient-3 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .event-content-mobile {
            padding: 16px;
        }
        .event-title-mobile {
            font-weight: 700;
            margin-bottom: 12px;
            color: #111827;
            font-size: 18px;
            line-height: 1.4;
            letter-spacing: -0.01em;
        }
        .event-meta-mobile {
            font-size: 14px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .event-meta-mobile i {
            font-size: 14px;
            color: var(--mobile-accent-color);
            width: 18px;
            flex-shrink: 0;
        }
        .event-footer-mobile {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .event-badge-mobile {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .event-participants-mobile {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
        }
        .event-participants-mobile i {
            color: var(--mobile-accent-color);
            font-size: 14px;
        }
        .mobile-nav {
            display: flex;
            justify-content: space-around;
            padding: 12px 0 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
            position: relative;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: #9ca3af;
            font-size: 11px;
            font-weight: 600;
            transition: color 0.2s;
            cursor: pointer;
            flex: 1;
        }
        .nav-item.active {
            color: var(--mobile-accent-color);
        }
        .nav-item i {
            font-size: 22px;
            transition: transform 0.2s;
        }
        .nav-item.active i {
            transform: scale(1.1);
        }
        .quick-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .quick-action-btn {
            flex: 1;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .quick-action-btn:active {
            transform: scale(0.95);
            background: #f8fafc;
        }
        .quick-action-btn i {
            font-size: 20px;
            color: var(--mobile-accent-color);
        }
        .quick-action-btn span {
            font-size: 12px;
            font-weight: 600;
            color: #111827;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .event-card-mobile {
            animation: slideIn 0.3s ease-out;
        }
        .event-card-mobile:nth-child(2) {
            animation-delay: 0.1s;
        }
        .event-card-mobile:nth-child(3) {
            animation-delay: 0.2s;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .mobile-frame {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 8px rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .mobile-screen {
            background: #0a0a0a;
        }
        body.dark-mode .mobile-content {
            background: #000000;
        }
        body.dark-mode .stat-card {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .stat-value {
            color: #ffffff;
        }
        body.dark-mode .stat-label {
            color: #94a3b8;
        }
        body.dark-mode .section-title {
            color: #ffffff;
        }
        body.dark-mode .section-title span {
            color: #8b5cf6;
        }
        body.dark-mode .event-card-mobile {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .event-title-mobile {
            color: #ffffff;
        }
        body.dark-mode .event-meta-mobile {
            color: #cbd5e1;
        }
        body.dark-mode .event-meta-mobile i {
            color: #8b5cf6;
        }
        body.dark-mode .event-footer-mobile {
            border-top-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .event-participants-mobile {
            color: #cbd5e1;
        }
        body.dark-mode .event-participants-mobile i {
            color: #8b5cf6;
        }
        body.dark-mode .mobile-nav {
            background: #0a0a0a;
            border-top-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .nav-item {
            color: #94a3b8;
        }
        body.dark-mode .nav-item.active {
            color: #8b5cf6;
        }
        body.dark-mode .quick-action-btn {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .quick-action-btn:active {
            background: rgba(99, 102, 241, 0.1);
        }
        body.dark-mode .quick-action-btn i {
            color: #8b5cf6;
        }
        body.dark-mode .quick-action-btn span {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="mobile-frame">
        <div class="mobile-screen">
        <div class="mobile-notch"></div>
            
            <!-- Status Bar -->
            <div class="mobile-status-bar">
                <div class="status-left">
                    <span>9:41</span>
                </div>
                <div class="status-right">
                    <i class="fas fa-signal"></i>
                    <i class="fas fa-wifi"></i>
                    <i class="fas fa-battery-three-quarters"></i>
                </div>
            </div>
            
            <!-- Header -->
        <div class="mobile-header">
                <div class="mobile-header-content">
            <div class="mobile-title">UniPanel</div>
                    <div class="mobile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
        </div>
            
            <!-- Content -->
        <div class="mobile-content">
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span>Yeni</span>
                    </div>
                    <div class="quick-action-btn">
                        <i class="fas fa-search"></i>
                        <span>Ara</span>
                    </div>
                    <div class="quick-action-btn">
                        <i class="fas fa-bell"></i>
                        <span>Bildirim</span>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value">12</div>
                        <div class="stat-label">Etkinlik</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value">248</div>
                        <div class="stat-label">Üye</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-value">5</div>
                        <div class="stat-label">Bildirim</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-poll"></i>
                        </div>
                        <div class="stat-value">8</div>
                        <div class="stat-label">Anket</div>
                    </div>
                </div>
                
                <!-- Events Section -->
                <div class="section-title">
                    <span>Yaklaşan Etkinlikler</span>
                    <span>Tümünü Gör</span>
                </div>
                
                <!-- Event Card 1 -->
                <div class="event-card-mobile">
                    <div class="event-image-mobile">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="event-content-mobile">
                        <div class="event-title-mobile">Teknoloji Semineri</div>
                <div class="event-meta-mobile">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Konferans Salonu</span>
                </div>
                        <div class="event-meta-mobile">
                            <i class="fas fa-clock"></i>
                            <span>15 Ocak 2025, 14:00</span>
                        </div>
                        <div class="event-footer-mobile">
                <span class="event-badge-mobile">
                    <i class="fas fa-check-circle"></i>
                    Yaklaşan
                </span>
                            <div class="event-participants-mobile">
                                <i class="fas fa-users"></i>
                                <span>89</span>
                            </div>
                        </div>
            </div>
                </div>
                
                <!-- Event Card 2 -->
                <div class="event-card-mobile">
                    <div class="event-image-mobile gradient-2">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="event-content-mobile">
                        <div class="event-title-mobile">Networking Etkinliği</div>
                <div class="event-meta-mobile">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Kafeterya</span>
                        </div>
                        <div class="event-meta-mobile">
                            <i class="fas fa-clock"></i>
                            <span>18 Ocak 2025, 18:00</span>
                        </div>
                        <div class="event-footer-mobile">
                            <span class="event-badge-mobile">
                                <i class="fas fa-check-circle"></i>
                                Yaklaşan
                            </span>
                            <div class="event-participants-mobile">
                                <i class="fas fa-users"></i>
                                <span>124</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Event Card 3 -->
                <div class="event-card-mobile">
                    <div class="event-image-mobile gradient-3">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <div class="event-content-mobile">
                        <div class="event-title-mobile">Web Geliştirme Workshop</div>
                        <div class="event-meta-mobile">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Bilgisayar Lab</span>
                        </div>
                        <div class="event-meta-mobile">
                            <i class="fas fa-clock"></i>
                            <span>22 Ocak 2025, 10:00</span>
                        </div>
                        <div class="event-footer-mobile">
                <span class="event-badge-mobile">
                    <i class="fas fa-check-circle"></i>
                    Yaklaşan
                </span>
                            <div class="event-participants-mobile">
                                <i class="fas fa-users"></i>
                                <span>56</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Navigation -->
        <div class="mobile-nav">
            <div class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Ana Sayfa</span>
                </div>
                <div class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Etkinlikler</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-users"></i>
                <span>Üyeler</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-bell"></i>
                <span>Bildirimler</span>
            </div>
            <div class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
