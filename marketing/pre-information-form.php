<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ön Bilgilendirme Formu - Four Kampüs</title>
    <meta name="description" content="Four Kampüs ön bilgilendirme formu. Ürün, fiyat ve teslimat hakkında yasal bilgiler.">
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
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: #f8fafc;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .info-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-table tr:last-child {
            border-bottom: none;
        }
        
        .info-table td {
            padding: 1rem 1.5rem;
            vertical-align: top;
        }
        
        .info-table td:first-child {
            font-weight: 600;
            color: #0f172a;
            width: 40%;
        }
        
        .info-table td:last-child {
            color: #475569;
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
        
        .contact-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-top: 2rem;
        }
        
        .contact-info p {
            margin: 0.5rem 0;
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
            
            .info-table {
                font-size: 0.875rem;
            }
            
            .info-table td {
                padding: 0.75rem 1rem;
                display: block;
            }
            
            .info-table td:first-child {
                width: 100%;
                margin-bottom: 0.5rem;
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
            <h1><i class="fas fa-info-circle"></i> Ön Bilgilendirme Formu</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>Genel Bilgiler</h2>
                <p>
                    Bu form, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli 
                    Sözleşmeler Yönetmeliği uyarınca, satın alma işlemi öncesinde tüketiciyi 
                    bilgilendirmek amacıyla hazırlanmıştır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>Satıcı Bilgileri</h2>
                <table class="info-table">
                    <tr>
                        <td>Şirket Adı</td>
                        <td>Four Kampüs</td>
                    </tr>
                    <tr>
                        <td>E-posta</td>
                        <td>info@fourkampus.com.tr</td>
                    </tr>
                    <tr>
                        <td>Telefon</td>
                        <td>+90 533 544 59 83</td>
                    </tr>
                    <tr>
                        <td>Çalışma Saatleri</td>
                        <td>Pazartesi - Cuma: 09:00 - 18:00</td>
                    </tr>
                    <tr>
                        <td>Adres</td>
                        <td>Türkiye</td>
                    </tr>
                </table>
            </div>
            
            <div class="legal-section">
                <h2>Ürün/Hizmet Bilgileri</h2>
                <h3>1. Profesyonel Plan Abonelik Hizmeti</h3>
                <table class="info-table">
                    <tr>
                        <td>Ürün/Hizmet Adı</td>
                        <td>Four Kampüs Profesyonel Plan</td>
                    </tr>
                    <tr>
                        <td>Açıklama</td>
                        <td>Topluluk yönetim platformu, email hosting ve temel özelliklere erişim</td>
                    </tr>
                    <tr>
                        <td>Fiyat</td>
                        <td>₺250/ay (KDV dahil)</td>
                    </tr>
                    <tr>
                        <td>Ödeme Yöntemleri</td>
                        <td>Kredi kartı, banka kartı, havale/EFT</td>
                    </tr>
                    <tr>
                        <td>Teslimat Süresi</td>
                        <td>Anında (dijital hizmet)</td>
                    </tr>
                    <tr>
                        <td>Teslimat Yöntemi</td>
                        <td>E-posta ile erişim bilgileri gönderilir</td>
                    </tr>
                </table>
                
                <h3>2. Business Plan Abonelik Hizmeti</h3>
                <table class="info-table">
                    <tr>
                        <td>Ürün/Hizmet Adı</td>
                        <td>Four Kampüs Business Plan</td>
                    </tr>
                    <tr>
                        <td>Açıklama</td>
                        <td>Gelişmiş topluluk yönetim platformu, öncelikli destek ve tüm özelliklere erişim</td>
                    </tr>
                    <tr>
                        <td>Fiyat</td>
                        <td>₺500/ay (KDV dahil)</td>
                    </tr>
                    <tr>
                        <td>Ödeme Yöntemleri</td>
                        <td>Kredi kartı, banka kartı, havale/EFT</td>
                    </tr>
                    <tr>
                        <td>Teslimat Süresi</td>
                        <td>Anında (dijital hizmet)</td>
                    </tr>
                    <tr>
                        <td>Teslimat Yöntemi</td>
                        <td>E-posta ile erişim bilgileri gönderilir</td>
                    </tr>
                </table>
                
                <h3>3. Topluluk Market Ürünleri</h3>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Not:</strong> Platform üzerindeki topluluklar tarafından satışa sunulan fiziksel ürünler, 
                        ilgili toplulukların sorumluluğundadır. Bu ürünlerin satışı, teslimatı, kalitesi ve iadesi 
                        topluluk yöneticileri tarafından yönetilir. Four Kampüs, bu ürünler için aracı konumundadır.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>Fiyat Bilgileri</h2>
                <h3>Fiyatlandırma</h3>
                <ul>
                    <li>Tüm fiyatlar Türk Lirası (₺) cinsindendir</li>
                    <li>Fiyatlar KDV dahildir</li>
                    <li>Fiyatlar, sipariş verildiği andaki geçerli fiyatlardır</li>
                    <li>Fiyat değişiklikleri önceden bildirilir</li>
                </ul>
                
                <h3>Ek Ücretler</h3>
                <ul>
                    <li>Teslimat ücreti: Yok (dijital hizmet)</li>
                    <li>İşlem ücreti: Ödeme yöntemine göre değişebilir</li>
                    <li>Ekstra özellikler: Ayrıca fiyatlandırılır</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>Teslimat Bilgileri</h2>
                <h3>Teslimat Süresi</h3>
                <ul>
                    <li><strong>Dijital Hizmetler:</strong> Sipariş onayından hemen sonra (anında)</li>
                    <li><strong>Fiziksel Ürünler:</strong> 3-7 iş günü (varsa)</li>
                </ul>
                
                <h3>Teslimat Yöntemi</h3>
                <ul>
                    <li>Dijital hizmetler için e-posta ile erişim bilgileri</li>
                    <li>Fiziksel ürünler için kargo (varsa)</li>
                    <li>Teslimat adresi sipariş sırasında belirtilir</li>
                </ul>
                
                <h3>Teslimat Adresi</h3>
                <p>
                    Alıcı, sipariş sırasında doğru ve güncel adres bilgilerini vermekle 
                    yükümlüdür. Yanlış adres bilgisi nedeniyle oluşan gecikmelerden 
                    Satıcı sorumlu değildir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>Ödeme Bilgileri</h2>
                <h3>Ödeme Yöntemleri</h3>
                <ul>
                    <li>Kredi kartı (Visa, Mastercard, Troy)</li>
                    <li>Banka kartı</li>
                    <li>Havale/EFT (önceden bildirim ile)</li>
                    <li>Güvenli ödeme altyapısı</li>
                </ul>
                
                <h3>Ödeme Güvenliği</h3>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-shield-alt"></i> 
                        <strong>Güvenlik:</strong> Tüm ödeme işlemleri SSL sertifikası ile 
                        şifrelenir. Kredi kartı bilgileri saklanmaz. Güvenli ödeme altyapısı kullanılır.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>Cayma Hakkı</h2>
                <h3>Cayma Hakkı Süresi</h3>
                <p>
                    Alıcı, ürün/hizmetin teslim edildiği tarihten itibaren <strong>14 gün</strong> 
                    içinde cayma hakkını kullanabilir.
                </p>
                
                <h3>Cayma Hakkının Kullanılamayacağı Durumlar</h3>
                <ul>
                    <li>Alıcı'nın onayı ile anında teslim edilen dijital içerikler</li>
                    <li>Alıcı'nın özel talebi ile hazırlanan kişiselleştirilmiş ürünler</li>
                    <li>Hizmetin tamamen ifa edilmesi durumunda</li>
                </ul>
                
                <h3>Cayma Bildirimi</h3>
                <p>
                    Cayma hakkını kullanmak için:
                </p>
                <ul>
                    <li>E-posta: info@fourkampus.com.tr</li>
                    <li>Telefon: +90 533 544 59 83</li>
                    <li>Platform üzerinden iletişim formu</li>
                </ul>
                <p>
                    Detaylı bilgi için 
                    <a href="cancellation-refund.php" style="color: #6366f1; font-weight: 600;">İptal ve İade Koşulları</a> 
                    sayfasını inceleyiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>Garanti ve Sorumluluk</h2>
                <h3>Garanti</h3>
                <p>
                    Satılan ürün/hizmetin sözleşmede belirtilen özelliklere uygun olması garanti edilir. 
                    Garanti süresi, ürün/hizmet türüne göre değişiklik gösterebilir.
                </p>
                
                <h3>Sorumluluk</h3>
                <p>
                    Satıcı, ürün/hizmetin sözleşmede belirtilen özelliklere uygun olmasından sorumludur. 
                    Alıcı'nın hatalı kullanımından kaynaklanan sorunlardan Satıcı sorumlu değildir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>Kişisel Verilerin Korunması</h2>
                <p>
                    Alıcı'nın kişisel verileri (isim, adres, telefon, e-posta vb.), 6698 sayılı 
                    KVKK kapsamında işlenir ve korunur. Detaylı bilgi için 
                    <a href="privacy-policy.php" style="color: #6366f1; font-weight: 600;">Gizlilik Politikamız</a> 
                    ve KVKK Aydınlatma Metni'ni inceleyiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>Uyuşmazlıkların Çözümü</h2>
                <p>
                    Uyuşmazlıklar öncelikle dostane çözüm yolları ile çözülmeye çalışılır. 
                    Çözülemezse, Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir. 
                    İstanbul Tüketici Hakem Heyeti ve Tüketici Mahkemeleri yetkilidir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>İletişim</h2>
                <p>
                    Ön bilgilendirme formu hakkında sorularınız için:
                </p>
                <div class="contact-info">
                    <p><strong>E-posta:</strong> info@fourkampus.com.tr</p>
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
