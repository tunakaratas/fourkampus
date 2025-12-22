<?php
// Demo Preview - Public Portal
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - Public Portal Preview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            padding: 0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .preview-container {
            max-width: 100%;
            margin: 0;
        }
        /* Header */
        .header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon-box {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        .logo-text {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-color);
            letter-spacing: -0.02em;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        /* Main Content */
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 32px;
        }
        .hero-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 12px;
            letter-spacing: -0.03em;
        }
        .hero-description {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 32px;
        }
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            max-width: 800px;
            margin: 0 auto 32px;
        }
        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }
        /* Search and Sort */
        .search-sort-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .search-box {
            position: relative;
            flex: 1;
        }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        .search-input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .sort-select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .sort-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        /* Communities Grid */
        .communities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .community-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }
        .community-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }
        .card-header {
            background: var(--primary-color);
            padding: 24px;
            color: white;
            position: relative;
        }
        .card-icon-box {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 28px;
        }
        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .card-body {
            padding: 20px;
        }
        .card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .card-stat {
            text-align: center;
        }
        .card-stat-icon {
            font-size: 18px;
            color: var(--primary-color);
            margin-bottom: 6px;
        }
        .card-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .card-stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .card-arrow {
            position: absolute;
            bottom: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.2s;
        }
        .community-card:hover .card-arrow {
            opacity: 1;
        }
        @media (min-width: 640px) {
            .search-sort-container {
                flex-direction: row;
            }
            .stats-grid {
                gap: 16px;
            }
        }
        
        /* Dark Mode */
        body.dark-mode {
            --text-primary: #ffffff;
            --text-secondary: #cbd5e1;
            --text-light: #94a3b8;
            --bg-primary: #0a0a0a;
            --bg-secondary: #000000;
            --bg-tertiary: #111111;
            --border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .header {
            background: #0a0a0a;
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .hero-title {
            color: #ffffff;
        }
        body.dark-mode .hero-description {
            color: #cbd5e1;
        }
        body.dark-mode .stat-card {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .stat-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
        }
        body.dark-mode .stat-number {
            color: #ffffff;
        }
        body.dark-mode .stat-label {
            color: #94a3b8;
        }
        body.dark-mode .search-input {
            background: #000000;
            border-color: rgba(99, 102, 241, 0.3);
            color: #ffffff;
        }
        body.dark-mode .search-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        body.dark-mode .search-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        body.dark-mode .search-icon {
            color: #94a3b8;
        }
        body.dark-mode .sort-select {
            background: #000000;
            border-color: rgba(99, 102, 241, 0.3);
            color: #ffffff;
        }
        body.dark-mode .sort-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        body.dark-mode .community-card {
            background: #0a0a0a;
            border-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode .community-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
        }
        body.dark-mode .card-body {
            background: #0a0a0a;
        }
        body.dark-mode .card-stat-number {
            color: #ffffff;
        }
        body.dark-mode .card-stat-label {
            color: #94a3b8;
        }
        body.dark-mode .card-stat-icon {
            color: #8b5cf6;
        }
        body.dark-mode .btn-primary {
            background: #8b5cf6;
        }
        body.dark-mode .btn-primary:hover {
            background: #7c3aed;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon-box">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <span class="logo-text">Four Kampüs</span>
                </div>
                <button class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Giriş Yap</span>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Hero Section -->
            <div class="hero-section">
                <h1 class="hero-title">Topluluk Portalı</h1>
                <p class="hero-description">
                    Tüm toplulukları keşfedin, etkinliklere katılın ve kampanyalardan haberdar olun
                </p>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Topluluk</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">1,250</div>
                        <div class="stat-label">Toplam Üye</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">85</div>
                        <div class="stat-label">Etkinlik</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24</div>
                        <div class="stat-label">Kampanya</div>
                    </div>
                </div>
            </div>

            <!-- Search and Sort -->
            <div class="search-sort-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Topluluk ara...">
                </div>
                <select class="sort-select">
                    <option>İsme Göre</option>
                    <option>Üye Sayısına Göre</option>
                    <option>Etkinlik Sayısına Göre</option>
                    <option>Kampanya Sayısına Göre</option>
                </select>
            </div>

            <!-- Communities Grid -->
            <div class="communities-grid">
                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-title">Teknoloji Topluluğu</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">125</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">12</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">5</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="card-title">Girişimcilik Kulübü</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">98</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">8</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">3</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div class="card-title">Sanat Topluluğu</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">156</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">15</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">7</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <div class="card-title">Sağlık Topluluğu</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">203</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">18</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">9</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <div class="card-title">Spor Topluluğu</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">187</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">22</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">6</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="community-card">
                    <div class="card-header">
                        <div class="card-icon-box">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="card-title">Edebiyat Kulübü</div>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-stats">
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-stat-number">94</div>
                                <div class="card-stat-label">Üye</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="card-stat-number">9</div>
                                <div class="card-stat-label">Etkinlik</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="card-stat-number">4</div>
                                <div class="card-stat-label">Kampanya</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
