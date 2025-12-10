<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çerez Politikası - UniFour</title>
    <meta name="description" content="UniFour çerez politikası. Web sitesinde kullanılan çerezler hakkında bilgilendirme.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .legal-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 8rem 2rem 4rem;
            line-height: 1.8;
            color: #334155;
        }
        
        .legal-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .legal-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .legal-header p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .legal-content {
            background: white;
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .legal-section {
            margin-bottom: 2.5rem;
        }
        
        .legal-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .legal-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .legal-section p {
            margin-bottom: 1rem;
            color: #475569;
        }
        
        .legal-section ul, .legal-section ol {
            margin: 1rem 0;
            padding-left: 2rem;
        }
        
        .legal-section li {
            margin-bottom: 0.75rem;
            color: #475569;
        }
        
        .legal-section strong {
            color: #0f172a;
            font-weight: 600;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
            border-left: 4px solid #6366f1;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
        }
        
        .highlight-box p {
            margin: 0;
            color: #1e293b;
            font-weight: 500;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #4f46e5;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        table th, table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table th {
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
        }
        
        @media (max-width: 768px) {
            .legal-page {
                padding: 6rem 1rem 2rem;
            }
            
            .legal-content {
                padding: 2rem 1.5rem;
            }
            
            .legal-header h1 {
                font-size: 2rem;
            }
            
            table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="legal-page">
        <a href="index.html" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>
        
        <div class="legal-header">
            <h1><i class="fas fa-cookie-bite"></i> Çerez Politikası</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Çerez Nedir?</h2>
                <p>
                    Çerezler (Cookies), web sitelerini ziyaret ettiğinizde tarayıcınız tarafından 
                    cihazınıza kaydedilen küçük metin dosyalarıdır. Bu dosyalar, web sitesinin 
                    düzgün çalışmasını sağlar ve kullanıcı deneyimini iyileştirir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Çerezlerin Kullanım Amacı</h2>
                <p>UniFour olarak çerezleri aşağıdaki amaçlarla kullanmaktayız:</p>
                <ul>
                    <li>Web sitesinin temel işlevlerini sağlamak</li>
                    <li>Kullanıcı oturumlarını yönetmek</li>
                    <li>Kullanıcı tercihlerini hatırlamak</li>
                    <li>Güvenlik ve dolandırıcılık önleme</li>
                    <li>Site performansını analiz etmek</li>
                    <li>Kullanıcı deneyimini iyileştirmek</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Kullanılan Çerez Türleri</h2>
                <h3>3.1. Zorunlu Çerezler</h3>
                <p>
                    Bu çerezler web sitesinin çalışması için gereklidir ve devre dışı bırakılamaz.
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Çerez Adı</th>
                            <th>Amaç</th>
                            <th>Süre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PHPSESSID</td>
                            <td>Oturum yönetimi</td>
                            <td>Oturum süresi</td>
                        </tr>
                        <tr>
                            <td>csrf_token</td>
                            <td>Güvenlik (CSRF koruması)</td>
                            <td>Oturum süresi</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>3.2. Performans Çerezleri</h3>
                <p>
                    Bu çerezler, web sitesinin performansını analiz etmek için kullanılır.
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Çerez Adı</th>
                            <th>Amaç</th>
                            <th>Süre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>_ga</td>
                            <td>Google Analytics - Kullanıcı analizi</td>
                            <td>2 yıl</td>
                        </tr>
                        <tr>
                            <td>_gid</td>
                            <td>Google Analytics - Kullanıcı analizi</td>
                            <td>24 saat</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>3.3. İşlevsellik Çerezleri</h3>
                <p>
                    Bu çerezler, kullanıcı tercihlerini hatırlamak için kullanılır.
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Çerez Adı</th>
                            <th>Amaç</th>
                            <th>Süre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>theme_preference</td>
                            <td>Karanlık/Aydınlık tema tercihi</td>
                            <td>1 yıl</td>
                        </tr>
                        <tr>
                            <td>language</td>
                            <td>Dil tercihi</td>
                            <td>1 yıl</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="legal-section">
                <h2>4. Üçüncü Taraf Çerezler</h2>
                <p>
                    Bazı çerezler, hizmetlerimizi iyileştirmek için üçüncü taraf hizmet sağlayıcılar 
                    tarafından yerleştirilebilir:
                </p>
                <ul>
                    <li><strong>Google Analytics:</strong> Site kullanım analizi</li>
                    <li><strong>Ödeme Sağlayıcıları:</strong> Güvenli ödeme işlemleri</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Çerezleri Yönetme</h2>
                <h3>5.1. Tarayıcı Ayarları</h3>
                <p>
                    Çerezleri tarayıcı ayarlarınızdan yönetebilirsiniz. Ancak bazı çerezler 
                    devre dışı bırakıldığında web sitesi düzgün çalışmayabilir.
                </p>
                <ul>
                    <li><strong>Chrome:</strong> Ayarlar > Gizlilik ve güvenlik > Çerezler</li>
                    <li><strong>Firefox:</strong> Seçenekler > Gizlilik ve Güvenlik > Çerezler</li>
                    <li><strong>Safari:</strong> Tercihler > Gizlilik > Çerezler</li>
                    <li><strong>Edge:</strong> Ayarlar > Gizlilik > Çerezler</li>
                </ul>
                
                <h3>5.2. Çerez Onayı</h3>
                <p>
                    İlk ziyaretinizde çerez kullanımı hakkında bilgilendirilir ve onayınız alınır. 
                    Onayınızı istediğiniz zaman değiştirebilirsiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>6. Çerezlerin Güvenliği</h2>
                <p>
                    Çerezlerimiz güvenli bir şekilde saklanır ve üçüncü kişilerle paylaşılmaz. 
                    Hassas bilgiler (şifre, kredi kartı bilgileri vb.) çerezlerde saklanmaz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>7. Değişiklikler</h2>
                <p>
                    Bu çerez politikası, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler e-posta ile bildirilir ve güncel politika 
                    her zaman bu sayfada yayınlanır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>8. İletişim</h2>
                <p>
                    Çerez politikası hakkında sorularınız için:
                </p>
                <div class="highlight-box">
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
            <a href="index.html" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Ana Sayfaya Dön
            </a>
        </div>
    </div>
</body>
</html>

