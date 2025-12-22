<?php
// Demo Preview - Events
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - Events Preview</title>
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
        .events-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #111827;
        }
        .btn-add {
            background: #6366f1;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #6366f1;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        .btn-add:hover {
            background: #4f46e5;
        }
        .btn-add i {
            font-size: 0.875rem;
        }
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .event-card {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.15), 0 4px 8px -2px rgba(0, 0, 0, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
        }
        .event-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .event-image::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.2;
        }
        .event-image i {
            font-size: 3rem;
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }
        .event-card:hover .event-image {
            transform: scale(1.02);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .event-content {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .event-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #111827;
            line-height: 1.4;
            letter-spacing: -0.01em;
        }
        .event-meta {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .event-meta i {
            font-size: 0.875rem;
            color: #6366f1;
            width: 16px;
            flex-shrink: 0;
        }
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .event-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .event-participants {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .event-participants i {
            color: #6366f1;
            font-size: 0.875rem;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .events-container {
            color: #ffffff;
        }
        body.dark-mode .page-title {
            color: #ffffff;
        }
        body.dark-mode .btn-add {
            background: #8b5cf6;
            border-color: #8b5cf6;
        }
        body.dark-mode .btn-add:hover {
            background: #7c3aed;
        }
        body.dark-mode .event-card {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }
        body.dark-mode .event-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 12px 32px -8px rgba(99, 102, 241, 0.3), 0 4px 12px -4px rgba(0, 0, 0, 0.6);
        }
        body.dark-mode .event-title {
            color: #ffffff;
        }
        body.dark-mode .event-meta {
            color: #cbd5e1;
        }
        body.dark-mode .event-meta i {
            color: #8b5cf6;
        }
        body.dark-mode .event-footer {
            border-top-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .event-participants {
            color: #cbd5e1;
        }
        body.dark-mode .event-participants i {
            color: #8b5cf6;
        }
    </style>
</head>
<body>
    <div class="events-container">
        <div class="page-header">
            <h1 class="page-title">Etkinlikler</h1>
            <button class="btn-add">
                <i class="fas fa-plus"></i>
                Yeni Etkinlik
            </button>
        </div>
        
        <div class="events-grid">
            <div class="event-card">
                <div class="event-image">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="event-content">
                    <div class="event-title">Teknoloji Semineri</div>
                    <div class="event-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Konferans Salonu</span>
                    </div>
                    <div class="event-meta">
                        <i class="fas fa-clock"></i>
                        <span>15 Ocak 2025, 14:00</span>
                    </div>
                    <div class="event-footer">
                        <span class="event-badge">
                            <i class="fas fa-check-circle"></i>
                            Yaklaşan
                        </span>
                        <div class="event-participants">
                            <i class="fas fa-users"></i>
                            <span>89 katılımcı</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="event-card">
                <div class="event-image" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="event-content">
                    <div class="event-title">Networking Etkinliği</div>
                    <div class="event-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Kafeterya</span>
                    </div>
                    <div class="event-meta">
                        <i class="fas fa-clock"></i>
                        <span>18 Ocak 2025, 18:00</span>
                    </div>
                    <div class="event-footer">
                        <span class="event-badge">
                            <i class="fas fa-check-circle"></i>
                            Yaklaşan
                        </span>
                        <div class="event-participants">
                            <i class="fas fa-users"></i>
                            <span>124 katılımcı</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="event-card">
                <div class="event-image" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <div class="event-content">
                    <div class="event-title">Web Geliştirme Workshop</div>
                    <div class="event-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Bilgisayar Lab</span>
                    </div>
                    <div class="event-meta">
                        <i class="fas fa-clock"></i>
                        <span>22 Ocak 2025, 10:00</span>
                    </div>
                    <div class="event-footer">
                        <span class="event-badge">
                            <i class="fas fa-check-circle"></i>
                            Yaklaşan
                        </span>
                        <div class="event-participants">
                            <i class="fas fa-users"></i>
                            <span>67 katılımcı</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
