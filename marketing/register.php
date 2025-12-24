<?php require_once __DIR__ . '/../api/university_helper.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topluluk Kayıt - Four Kampüs</title>
    <meta name="description" content="Topluluğunuzu Four Kampüs'e kaydedin. Bandırma 17 Eylül Üniversitesi topluluklarına Profesyonel Plan 6 ay ücretsiz.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include __DIR__ . '/../templates/partials/tailwind_cdn_loader.php'; ?>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-light: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.1), 0 2px 4px -1px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 40%, #ffffff 100%);
            min-height: 100vh;
            padding: clamp(2rem, 6vw, 5rem);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            transition: color var(--transition-base);
            z-index: 1;
        }
        
        .input-wrapper input:focus ~ .input-icon,
        .input-wrapper input:not(:placeholder-shown) ~ .input-icon,
        .input-wrapper select:focus ~ .input-icon,
        .input-wrapper select:not([value=""]) ~ .input-icon {
            color: #6366f1;
        }
        
        .input-wrapper input,
        .input-wrapper select {
            padding-left: 44px;
            transition: all var(--transition-base);
        }
        
        .input-wrapper input:focus,
        .input-wrapper select:focus {
            padding-left: 44px;
        }
        
        .card-container {
            position: relative;
            z-index: 1;
        }
        
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .form-input,
        .form-select {
            transition: all var(--transition-base);
            background: var(--bg-primary);
        }
        
        .form-input:focus,
        .form-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            background: #6366f1;
            transition: all var(--transition-base);
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .link-primary {
            color: #6366f1;
        }
        
        .link-primary:hover {
            color: #4f46e5;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .auth-page {
            position: relative;
            min-height: calc(100vh - clamp(2rem, 6vw, 5rem) * 2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .auth-blur {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.12), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(129, 140, 248, 0.18), transparent 60%);
            z-index: -2;
            filter: blur(0px);
            display: none;
        }

        .auth-card {
            position: relative;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            overflow: hidden;
            backdrop-filter: none;
            background: transparent;
            z-index: 1;
        }

        @media (min-width: 900px) {
            .auth-card {
                grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            }
        }

        @media (min-width: 1024px) {
            body {
                padding: 0;
                overflow: hidden;
            }

            .auth-page {
                max-width: none;
                height: 100vh;
                min-height: 100vh;
                align-items: stretch;
                justify-content: stretch;
                overflow: hidden;
            }

            .auth-card {
                height: 100vh;
                min-height: 100vh;
                grid-template-columns: minmax(0, 0.75fr) minmax(0, 1fr);
            }

            .auth-card-media {
                height: 100%;
                overflow: hidden;
            }

            .auth-card-form {
                height: 100%;
                overflow-y: auto;
                justify-content: flex-start;
                padding-top: 2rem;
                padding-bottom: 5rem;
            }
        }

        .auth-card-media {
            position: relative;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: clamp(2.5rem, 5vw, 3.75rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.5rem, 3vw, 2.5rem);
            overflow: hidden;
        }

        .auth-card-media::before,
        .auth-card-media::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            filter: blur(0);
            opacity: 0.65;
            z-index: 0;
        }

        .auth-card-media::before {
            width: clamp(220px, 40vw, 320px);
            height: clamp(220px, 40vw, 320px);
            background: rgba(255, 255, 255, 0.12);
            top: clamp(-140px, -10vw, -80px);
            right: clamp(-120px, -8vw, -70px);
        }

        .auth-card-media::after {
            width: clamp(160px, 30vw, 280px);
            height: clamp(160px, 30vw, 260px);
            background: rgba(255, 255, 255, 0.08);
            bottom: clamp(-120px, -8vw, -70px);
            left: clamp(-120px, -8vw, -70px);
        }

        .auth-media-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: clamp(1.25rem, 2.5vw, 1.75rem);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-brand-logo {
            width: clamp(60px, 10vw, 72px);
            height: clamp(60px, 10vw, 72px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .auth-brand h1 {
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .auth-brand p {
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
        }

        .auth-headline {
            font-size: clamp(2rem, 3.6vw, 2.75rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.045em;
        }

        .auth-subheadline {
            font-size: clamp(1rem, 2vw, 1.125rem);
            color: rgba(255, 255, 255, 0.8);
            max-width: 32rem;
            line-height: 1.65;
        }

        .auth-benefits {
            list-style: none;
            display: grid;
            gap: 0.9rem;
            padding: 0;
            margin: 0;
        }

        .auth-benefits li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.975rem;
            color: rgba(255, 255, 255, 0.88);
            font-weight: 500;
        }

        .auth-benefits i {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.15);
        }

        .auth-card-form {
            position: relative;
            background: transparent;
            padding: clamp(2.5rem, 5vw, 4rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.75rem, 3vw, 2.5rem);
        }

        .auth-form-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .auth-form-header h2 {
            font-size: clamp(1.75rem, 2.8vw, 2.1rem);
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.035em;
        }

        .auth-form-header p {
            color: var(--text-secondary);
            font-size: 0.975rem;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.4rem;
        }

        .input-wrapper input,
        .input-wrapper select {
            padding-left: 48px;
        }

        .form-help-text {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        .auth-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            border: none;
            box-shadow: 0 10px 25px -10px rgba(79, 70, 229, 0.65);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .auth-footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.92rem;
            color: var(--text-secondary);
        }

        .auth-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            animation: fadeInUp 0.4s ease-out;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .success-message {
            text-align: center;
            padding: 2rem 0;
        }

        .success-message h2 {
            color: #10b981;
            font-size: clamp(1.5rem, 2.5vw, 1.875rem);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .success-message p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 0.975rem;
        }

        .success-info-box {
            background: #f1f5f9;
            padding: 1.5rem;
            border-radius: 1rem;
            margin: 1.5rem 0;
            text-align: left;
            border: 1px solid var(--border-color);
        }

        .success-info-box p {
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .success-info-box p:last-child {
            margin-bottom: 0;
        }

        .success-info-box strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .success-info-box a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }

        .success-info-box a:hover {
            text-decoration: underline;
        }

        @media (max-width: 899px) {
            body {
                padding: clamp(1.5rem, 6vw, 3rem);
            }

            .auth-card {
                transform: translateX(0);
            }

            .auth-card-media {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page fade-in-up">
        <div class="auth-blur"></div>
        <div class="auth-card">
            <div class="auth-card-media">
                <div class="auth-media-content">
                    <div class="auth-brand">
                        <div class="auth-brand-logo">
                            <img src="assets/images/brand/nobackground_logo.png" alt="Four Kampüs" class="auth-logo-img">
                        </div>
                        <div>
                            <h1>Four Kampüs</h1>
                            <p>Topluluk Yönetimi</p>
                        </div>
                    </div>
                    <div>
                        <h2 class="auth-headline">Topluluğunuzu kaydedin ve yönetmeye başlayın</h2>
                        <p class="auth-subheadline">Four Kampüs ile topluluğunuzu profesyonelce yönetin. Etkinlik planlama, üye takibi, bildirimler ve daha fazlası tek platformda. Bandırma 17 Eylül Üniversitesi topluluklarına Profesyonel Plan 6 ay ücretsiz.</p>
                    </div>
                    <ul class="auth-benefits">
                        <li><i class="fas fa-check"></i> Bandırma 17 Eylül Üniversitesi topluluklarına Profesyonel Plan 6 ay ücretsiz</li>
                        <li><i class="fas fa-check"></i> Ücretsiz email adresleri</li>
                        <li><i class="fas fa-check"></i> 7/24 teknik destek</li>
                    </ul>
                </div>
            </div>
            <div class="auth-card-form">
                <div class="auth-form-header">
                    <h2>Topluluk Kayıt</h2>
                    <p>Topluluğunuzu Four Kampüs'e kaydedin ve yönetmeye başlayın.</p>
                </div>

                <div id="alertContainer"></div>

                <div id="registerForm">
                    <form method="POST" action="" class="auth-form" id="communityRegisterForm">
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Topluluk Adı *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-users input-icon"></i>
                                <input type="text" id="community_name" name="community_name" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="Örn: Bilgisayar Mühendisliği Topluluğu">
                            </div>
                            <p class="form-help-text">Topluluğunuzun tam adını girin</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Üniversite *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-university input-icon"></i>
                                <select id="university" name="university" required 
                                        class="form-select w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                        style="border-color: var(--border-color); color: var(--text-primary);">
                                    <option value="">Üniversite seçiniz</option>
                                    <?php 
                                    $universities = getUniversityList();
                                    sort($universities);
                                    foreach ($universities as $uni): 
                                    ?>
                                        <option value="<?= $uni ?>"><?= $uni ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Admin Kullanıcı Adı *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="admin_username" name="admin_username" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="admin">
                            </div>
                            <p class="form-help-text">Topluluk yönetim paneline giriş için kullanıcı adı</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Admin Şifresi *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="admin_password" name="admin_password" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="••••••••"
                                       minlength="6">
                            </div>
                            <p class="form-help-text">En az 6 karakter olmalıdır</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Yetkili Telefon Numarası *</label>
                            <div class="input-wrapper">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="tel" id="admin_phone" name="admin_phone" required 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="5XX XXX XX XX"
                                       pattern="^5[0-9]{9}$"
                                       title="5 ile başlayan 10 haneli numara giriniz">
                            </div>
                            <p class="form-help-text">Giriş doğrulama kodları için telefon numarası (5 ile başlamalı, 10 haneli)</p>
                        </div>


                        <div>
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">Admin Email (Opsiyonel)</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" id="admin_email" name="admin_email" 
                                       class="form-input w-full px-4 py-3.5 pl-12 border-2 rounded-2xl outline-none font-medium"
                                       style="border-color: var(--border-color); color: var(--text-primary);"
                                       placeholder="admin@example.com">
                            </div>
                            <p class="form-help-text">Bildirimler için email adresi</p>
                        </div>


                        <!-- Yasal Onay Kutuları -->
                        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color);">
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; letter-spacing: -0.01em;">
                                <i class="fas fa-shield-alt" style="color: #6366f1; margin-right: 0.5rem;"></i>
                                Yasal Onaylar *
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 0.875rem;">
                                <!-- Gizlilik Politikası -->
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.875rem; line-height: 1.5;">
                                    <input type="checkbox" id="privacy_policy" name="privacy_policy" required 
                                           style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; flex-shrink: 0;">
                                    <span style="color: var(--text-primary);">
                                        <a href="privacy-policy.php" target="_blank" style="color: #6366f1; font-weight: 600; text-decoration: underline;">Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.
                                    </span>
                                </label>

                                <!-- KVKK Aydınlatma Metni -->
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.875rem; line-height: 1.5;">
                                    <input type="checkbox" id="kvkk" name="kvkk" required 
                                           style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; flex-shrink: 0;">
                                    <span style="color: var(--text-primary);">
                                        <a href="privacy-policy.php#kvkk" target="_blank" style="color: #6366f1; font-weight: 600; text-decoration: underline;">KVKK Aydınlatma Metni</a>'ni okudum ve kişisel verilerimin işlenmesine onay veriyorum.
                                    </span>
                                </label>

                                <!-- Kullanım Koşulları -->
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.875rem; line-height: 1.5;">
                                    <input type="checkbox" id="terms_of_service" name="terms_of_service" required 
                                           style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; flex-shrink: 0;">
                                    <span style="color: var(--text-primary);">
                                        <a href="terms-of-service.php" target="_blank" style="color: #6366f1; font-weight: 600; text-decoration: underline;">Kullanım Koşulları</a>'nı okudum ve kabul ediyorum.
                                    </span>
                                </label>

                                <!-- Mesafeli Satış Sözleşmesi -->
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.875rem; line-height: 1.5;">
                                    <input type="checkbox" id="distance_sales" name="distance_sales" required 
                                           style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; flex-shrink: 0;">
                                    <span style="color: var(--text-primary);">
                                        <a href="distance-sales-contract.php" target="_blank" style="color: #6366f1; font-weight: 600; text-decoration: underline;">Mesafeli Satış Sözleşmesi</a>'ni okudum ve kabul ediyorum.
                                    </span>
                                </label>

                                <!-- Ön Bilgilendirme Formu -->
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; font-size: 0.875rem; line-height: 1.5;">
                                    <input type="checkbox" id="pre_information" name="pre_information" required 
                                           style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; flex-shrink: 0;">
                                    <span style="color: var(--text-primary);">
                                        <a href="pre-information-form.php" target="_blank" style="color: #6366f1; font-weight: 600; text-decoration: underline;">Ön Bilgilendirme Formu</a>'nu okudum ve kabul ediyorum.
                                    </span>
                                </label>
                            </div>
                            <p style="margin-top: 1rem; font-size: 0.75rem; color: var(--text-light); line-height: 1.5;">
                                <i class="fas fa-info-circle" style="margin-right: 0.375rem;"></i>
                                Tüm yasal belgeleri okumanız ve onaylamanız zorunludur. Onay vermeden kayıt işlemi tamamlanamaz.
                            </p>
                        </div>

                        <button type="submit" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base" style="letter-spacing: -0.01em;" id="submitBtn">
                            <span>Kayıt Ol</span>
                        </button>
                    </form>
                </div>

                <div id="successMessage" style="display: none;">
                    <div class="success-message">
                        <h2><i class="fas fa-check-circle"></i> Talep Gönderildi!</h2>
                        <p>Topluluk kayıt talebiniz başarıyla gönderildi. Superadmin onayından sonra topluluğunuz oluşturulacaktır.</p>
                        <div class="success-info-box" id="successInfo"></div>
                        <div id="loginLinkContainer" style="margin-top: 1.5rem;"></div>
                        <div style="margin-top: 1.5rem; text-align: center;">
                            <button onclick="clearStoredRequest()" class="link-primary" style="color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; background: none; border: none; cursor: pointer; font-weight: 500; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                                <i class="fas fa-redo"></i> Yeni Talep Oluştur
                            </button>
                        </div>
                    </div>
                </div>

                <div class="auth-footer-links">
                    <div class="auth-divider">Zaten hesabınız var mı?</div>
                    <a href="index.html" class="link-primary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Ana sayfaya dön</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('communityRegisterForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        const registerForm = document.getElementById('registerForm');
        const successMessage = document.getElementById('successMessage');
        const successInfo = document.getElementById('successInfo');
        const loginLinkContainer = document.getElementById('loginLinkContainer');
        
        // localStorage key
        const STORAGE_KEY = 'unipanel_registration_request';
        
        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function displaySuccessMessage(requestData) {
            registerForm.style.display = 'none';
            successMessage.style.display = 'block';
            
            const loginUrl = requestData.login_url || '../communities/' + requestData.folder_name + '/login.php';
            
            // Durum renkleri
            const statusConfig = {
                'pending': { color: '#f59e0b', text: 'Beklemede', icon: 'clock' },
                'approved': { color: '#10b981', text: 'Onaylandı', icon: 'check-circle' },
                'rejected': { color: '#ef4444', text: 'Reddedildi', icon: 'times-circle' }
            };
            
            const status = requestData.status || 'pending';
            const statusInfo = statusConfig[status] || statusConfig.pending;
            
            successInfo.innerHTML = `
                <p><strong>Topluluk Adı:</strong> ${requestData.community_name}</p>
                <p><strong>Talep ID:</strong> ${requestData.request_id}</p>
                <p><strong>Durum:</strong> <span style="color: ${statusInfo.color}; font-weight: 600;"><i class="fas fa-${statusInfo.icon}"></i> ${statusInfo.text}</span></p>
                <p><strong>Giriş URL:</strong> <a href="${loginUrl}" target="_blank" style="color: #6366f1; text-decoration: underline;">${loginUrl}</a></p>
                <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 0.875rem;">
                    ${status === 'pending' ? 'Topluluk kayıt talebiniz başarıyla gönderildi. Superadmin onayından sonra topluluğunuz oluşturulacak ve size bilgi verilecektir.' : 
                      status === 'approved' ? 'Topluluk kayıt talebiniz onaylandı! Artık giriş yapabilirsiniz.' : 
                      'Topluluk kayıt talebiniz reddedilmiştir.'}
                </p>
            `;
            
            if (status === 'approved') {
                loginLinkContainer.innerHTML = `
                    <a href="${loginUrl}" target="_blank" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base inline-flex items-center justify-center gap-2" style="letter-spacing: -0.01em; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Giriş Yap</span>
                    </a>
                `;
            } else if (status === 'pending') {
                loginLinkContainer.innerHTML = `
                    <a href="${loginUrl}" target="_blank" class="btn-primary w-full py-3.5 text-white rounded-xl font-semibold text-base inline-flex items-center justify-center gap-2" style="letter-spacing: -0.01em; text-decoration: none; opacity: 0.8;">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Giriş Sayfasına Git</span>
                    </a>
                    <p style="margin-top: 0.75rem; text-align: center; color: #f59e0b; font-size: 0.875rem; font-weight: 500;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Not:</strong> Topluluğunuz henüz onaylanmadı. Giriş yapmaya çalışırsanız beklemek gerektiğine dair bilgi göreceksiniz.
                    </p>
                `;
            } else {
                loginLinkContainer.innerHTML = `
                    <p style="text-align: center; color: #ef4444; font-size: 0.875rem; font-weight: 500;">
                        <i class="fas fa-info-circle"></i> 
                        Yeni bir talep oluşturmak için sayfayı yenileyin.
                    </p>
                `;
            }
        }
        
        // Sayfa yüklendiğinde localStorage'dan veriyi kontrol et
        function loadStoredRequest() {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const requestData = JSON.parse(stored);
                    displaySuccessMessage(requestData);
                    
                    // Durum kontrolü için API'ye istek at (opsiyonel - arka planda)
                    if (requestData.request_id && requestData.status === 'pending') {
                        checkRequestStatus(requestData.request_id);
                    }
                }
            } catch (e) {
                console.error('localStorage okuma hatası:', e);
            }
        }
        
        // Talep durumunu kontrol et (arka planda)
        async function checkRequestStatus(requestId) {
            try {
                // Not: Bu endpoint henüz yok, sadece örnek
                // İsterseniz API'ye durum kontrol endpoint'i ekleyebiliriz
                // Şimdilik localStorage'daki veriyi kullanıyoruz
            } catch (e) {
                // Sessizce hata yoksay
            }
        }
        
        // localStorage'dan veriyi temizle (global function - onclick için)
        window.clearStoredRequest = function() {
            try {
                localStorage.removeItem(STORAGE_KEY);
                location.reload();
            } catch (e) {
                console.error('localStorage temizleme hatası:', e);
            }
        };
        
        // Sayfa yüklendiğinde kontrol et
        loadStoredRequest();
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = {
                community_name: formData.get('community_name'),
                university: formData.get('university'),
                admin_username: formData.get('admin_username'),
                admin_password: formData.get('admin_password'),
                admin_email: formData.get('admin_email'),
                admin_phone: formData.get('admin_phone')
            };
            
            if (!data.community_name || !data.university || !data.admin_username || !data.admin_password || !data.admin_phone) {
                showAlert('Lütfen tüm zorunlu alanları doldurun');
                return;
            }
            
            // Telefon numarası validasyonu
            const phonePattern = /^5[0-9]{9}$/;
            const cleanPhone = data.admin_phone.replace(/\s/g, '');
            if (!phonePattern.test(cleanPhone)) {
                showAlert('Telefon numarası 5 ile başlamalı ve 10 haneli olmalıdır');
                return;
            }
            
            if (data.admin_password.length < 6) {
                showAlert('Şifre en az 6 karakter olmalıdır');
                return;
            }
            
            // Yasal onay kontrolleri
            const privacyPolicy = document.getElementById('privacy_policy');
            const kvkk = document.getElementById('kvkk');
            const termsOfService = document.getElementById('terms_of_service');
            const distanceSales = document.getElementById('distance_sales');
            const preInformation = document.getElementById('pre_information');
            
            if (!privacyPolicy.checked || !kvkk.checked || !termsOfService.checked || !distanceSales.checked || !preInformation.checked) {
                showAlert('Lütfen tüm yasal onayları kabul edin');
                window.scrollTo({ top: document.getElementById('privacy_policy').offsetTop - 100, behavior: 'smooth' });
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kayıt yapılıyor...';
            
            try {
                const response = await fetch('api/community_register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Veriyi localStorage'a kaydet
                    const requestData = {
                        request_id: result.data.request_id,
                        community_name: result.data.community_name,
                        folder_name: result.data.folder_name,
                        login_url: result.data.login_url || '../communities/' + result.data.folder_name + '/login.php',
                        status: result.data.status || 'pending',
                        timestamp: new Date().toISOString()
                    };
                    
                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(requestData));
                    } catch (e) {
                        console.error('localStorage kayıt hatası:', e);
                    }
                    
                    // Başarı mesajını göster
                    displaySuccessMessage(requestData);
                } else {
                    showAlert(result.error || 'Bir hata oluştu');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Kayıt Ol</span>';
                }
            } catch (error) {
                showAlert('Bağlantı hatası: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Kayıt Ol</span>';
            }
        });
    </script>
</body>
</html>
