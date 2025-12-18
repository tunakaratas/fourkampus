#!/bin/bash

# Header HTML snippet
HEADER_HTML='    <!-- Layered Header -->
    <div class="header-container">
        <!-- Top Layer -->
        <div class="header-top-layer">
            <div class="header-top-content">
                <div class="header-top-left">
                    <a href="https://instagram.com/uni.panel" target="_blank" rel="noopener noreferrer" class="header-top-social-btn">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://x.com/uni.panel" target="_blank" rel="noopener noreferrer" class="header-top-social-btn">
                        <i class="fab fa-x"></i>
                    </a>
                    <a href="https://linkedin.com/uni.panel" target="_blank" rel="noopener noreferrer" class="header-top-social-btn">
                        <i class="fab fa-linkedin"></i>
                    </a>
                </div>
                <div class="header-top-right">
                    <a href="register.php" class="header-top-btn register-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Kayıt Ol</span>
                    </a>
                    <a href="../public/index.php" target="_blank" rel="noopener noreferrer" class="header-top-btn portal-btn">
                        <i class="fas fa-globe"></i>
                        <span>Portal</span>
                    </a>
                    <a href="../admin-login.php" target="_blank" rel="noopener noreferrer" class="header-top-btn admin-btn">
                        <i class="fas fa-shield-alt"></i>
                        <span>Admin Panel</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Bottom Layer -->
        <div class="header-bottom-layer">
            <div class="header-bottom-content">
                <a href="index.html" class="header-logo">
                    <img src="../nobackground_logo.png" alt="Four Kampüs" class="header-logo-img">
                    <span>Four Kampüs</span>
                </a>
                <ul class="header-nav-menu">
                    <li><a href="index.html">Ana Sayfa</a></li>
                    <li><a href="index.html#cozumler">Çözümler</a></li>
                    <li><a href="index.html#ozellikler">Özellikler</a></li>
                    <li><a href="index.html#fiyatlandirma">Fiyatlandırma</a></li>
                    <li><a href="index.html#iletisim">İletişim</a></li>
                </ul>
                <button class="nav-toggle" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>'

echo "✅ Script oluşturuldu. Manuel olarak güncelleme yapılacak."
