<?php
// Demo Preview - Surveys
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniPanel - Surveys Preview</title>
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
        .surveys-container {
            max-width: 100%;
            width: 100%;
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
            word-wrap: break-word;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-add {
            background: #6366f1;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #6366f1;
            font-weight: 500;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        .btn-add:hover {
            background: #4f46e5;
        }
        .surveys-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
            gap: 20px;
            width: 100%;
        }
        .survey-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
            border-top: 4px solid #6366f1;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .survey-card.success {
            border-top-color: #10b981;
        }
        .survey-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .survey-title {
            font-size: clamp(16px, 2.5vw, 20px);
            font-weight: 700;
            margin-bottom: 8px;
            color: #111827;
            word-wrap: break-word;
            overflow-wrap: break-word;
            flex: 1;
            min-width: 0;
        }
        .survey-meta {
            font-size: 13px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .survey-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .survey-meta-item i {
            font-size: 12px;
            color: #6366f1;
            flex-shrink: 0;
        }
        .survey-meta-item span {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .survey-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        .survey-card.success .survey-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .survey-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            margin-top: 16px;
        }
        .stat {
            text-align: center;
            min-width: 0;
        }
        .stat-value {
            font-size: clamp(20px, 3vw, 28px);
            font-weight: 800;
            color: #6366f1;
            line-height: 1;
            margin-bottom: 6px;
            word-wrap: break-word;
        }
        .survey-card.success .stat-value {
            color: #10b981;
        }
        .stat-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            word-wrap: break-word;
        }
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 12px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }
        .survey-card.success .progress-fill {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
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
        body.dark-mode .survey-card {
            background: #0a0a0a;
            border-top-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode .survey-card.success {
            border-top-color: rgba(16, 185, 129, 0.5);
        }
        body.dark-mode .survey-title {
            color: #ffffff;
        }
        body.dark-mode .survey-meta {
            color: #cbd5e1;
        }
        body.dark-mode .survey-meta-item i {
            color: #8b5cf6;
        }
        body.dark-mode .survey-stats {
            border-top-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .stat-value {
            color: #8b5cf6;
        }
        body.dark-mode .survey-card.success .stat-value {
            color: #10b981;
        }
        body.dark-mode .stat-label {
            color: #94a3b8;
        }
        body.dark-mode .progress-bar {
            background: rgba(99, 102, 241, 0.2);
        }
    </style>
</head>
<body>
    <div class="surveys-container">
        <div class="page-header">
            <h1 class="page-title">Anketler</h1>
            <button class="btn-add">
                <i class="fas fa-plus"></i>
                Yeni Anket
            </button>
        </div>
        
        <div class="surveys-grid">
            <div class="survey-card">
                <div class="survey-header">
                    <div style="flex: 1;">
                        <div class="survey-title">Etkinlik Memnuniyet Anketi</div>
                        <div class="survey-meta">
                            <div class="survey-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Teknoloji Semineri</span>
                            </div>
                            <div class="survey-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>15 Ocak 2025</span>
                            </div>
                        </div>
                    </div>
                    <div class="survey-icon">
                        <i class="fas fa-poll"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 75%;"></div>
                </div>
                <div class="survey-stats">
                    <div class="stat">
                        <div class="stat-value">89</div>
                        <div class="stat-label">Katılım</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">4.7</div>
                        <div class="stat-label">Ortalama</div>
                    </div>
                </div>
            </div>
            <div class="survey-card success">
                <div class="survey-header">
                    <div style="flex: 1;">
                        <div class="survey-title">Yeni Dönem Planlama</div>
                        <div class="survey-meta">
                            <div class="survey-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Genel</span>
                            </div>
                            <div class="survey-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>10 Ocak 2025</span>
                            </div>
                        </div>
                    </div>
                    <div class="survey-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 90%;"></div>
                </div>
                <div class="survey-stats">
                    <div class="stat">
                        <div class="stat-value">156</div>
                        <div class="stat-label">Katılım</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">4.2</div>
                        <div class="stat-label">Ortalama</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
