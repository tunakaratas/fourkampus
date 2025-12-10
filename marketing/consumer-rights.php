<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tüketici Hakları - UniFour</title>
    <meta name="description" content="UniFour tüketici hakları bildirimi. Tüketicinin hakları ve şikayet başvuru yolları.">
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
        
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
        }
        
        .warning-box p {
            margin: 0;
            color: #92400e;
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
            <h1><i class="fas fa-balance-scale"></i> Tüketici Hakları</h1>
            <p>6502 Sayılı Tüketicinin Korunması Hakkında Kanun</p>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Genel Bilgiler</h2>
                <p>
                    6502 sayılı Tüketicinin Korunması Hakkında Kanun uyarınca, UniFour platformu 
                    üzerinden yapılan alışverişlerde tüketicilerin hakları korunmaktadır. Bu sayfa, 
                    tüketici haklarınız ve bu hakları nasıl kullanabileceğiniz hakkında bilgi vermektedir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Tüketici Hakları</h2>
                <h3>2.1. Bilgi Edinme Hakkı</h3>
                <p>
                    Satın almadan önce ürün/hizmet hakkında tam ve doğru bilgi alma hakkına sahipsiniz:
                </p>
                <ul>
                    <li>Ürün/hizmet özellikleri</li>
                    <li>Fiyat bilgisi (KDV dahil/hariç)</li>
                    <li>Teslimat süresi ve koşulları</li>
                    <li>Garanti bilgileri</li>
                    <li>İade ve iptal koşulları</li>
                    <li>Satıcı bilgileri</li>
                </ul>
                
                <h3>2.2. Cayma Hakkı</h3>
                <p>
                    Mesafeli satış sözleşmelerinde <strong>14 gün</strong> içinde cayma hakkınız bulunmaktadır.
                </p>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Cayma hakkı süresi:</strong> Ürün/hizmetin teslim edildiği tarihten itibaren 14 gün.
                    </p>
                </div>
                
                <h3>2.3. Ayıplı İfa</h3>
                <p>
                    Satın aldığınız ürün/hizmet ayıplı ise (kusurlu, eksik, farklı vb.) aşağıdaki haklara sahipsiniz:
                </p>
                <ul>
                    <li>Ücretsiz tamir talep etme</li>
                    <li>Ücretsiz değiştirme talep etme</li>
                    <li>Fiyat indirimi talep etme</li>
                    <li>Sözleşmeden cayma (iade)</li>
                </ul>
                
                <h3>2.4. Güvenli Ürün Hakkı</h3>
                <p>
                    Satın aldığınız ürünlerin güvenli olması ve zarar vermemesi gerekir. Güvenli olmayan 
                    ürünlerden kaynaklanan zararlardan satıcı sorumludur.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>3. Şikayet ve Başvuru Yolları</h2>
                <h3>3.1. Öncelikli Çözüm Yolu</h3>
                <p>
                    Herhangi bir sorun yaşadığınızda öncelikle bizimle iletişime geçin:
                </p>
                <div class="highlight-box">
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                    <p><strong>Şikayet Formu:</strong> <a href="complaint-form.php" style="color: #6366f1; font-weight: 600;">Şikayet Formu</a></p>
                </div>
                
                <h3>3.2. Tüketici Hakem Heyetleri</h3>
                <p>
                    Sorununuz çözülemezse, bulunduğunuz il veya ilçedeki Tüketici Hakem Heyetlerine başvurabilirsiniz.
                </p>
                <ul>
                    <li>Başvuru ücretsizdir</li>
                    <li>Başvuru için <a href="https://www.tuketici.gov.tr" target="_blank" style="color: #6366f1; font-weight: 600;">www.tuketici.gov.tr</a> adresini kullanabilirsiniz</li>
                    <li>Başvuru süresi: 30 gün içinde</li>
                </ul>
                
                <h3>3.3. Tüketici Mahkemeleri</h3>
                <p>
                    Tüketici Hakem Heyetlerinin kararlarına itiraz edebilir veya doğrudan Tüketici Mahkemelerine başvurabilirsiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>4. Mesafeli Satışlarda Özel Haklar</h2>
                <h3>4.1. Ön Bilgilendirme</h3>
                <p>
                    Satın almadan önce aşağıdaki bilgileri alma hakkınız vardır:
                </p>
                <ul>
                    <li>Satıcı kimlik bilgileri</li>
                    <li>Ürün/hizmet özellikleri ve fiyatı</li>
                    <li>Teslimat koşulları ve süresi</li>
                    <li>Ödeme, teslimat ve fiyat bilgileri</li>
                    <li>Cayma hakkı ve kullanım koşulları</li>
                </ul>
                
                <h3>4.2. Sözleşme Onayı</h3>
                <p>
                    Siparişiniz onaylandığında, sözleşme metni ve ön bilgilendirme formu e-posta ile gönderilir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>5. Garanti Hakları</h2>
                <h3>5.1. Yasal Garanti</h3>
                <p>
                    Satın aldığınız ürünler için 2 yıl yasal garanti süresi bulunmaktadır. Bu süre içinde 
                    ayıplı ürünlerden kaynaklanan sorunlar ücretsiz olarak giderilir.
                </p>
                
                <h3>5.2. Garanti Kapsamı</h3>
                <ul>
                    <li>Üretim hataları</li>
                    <li>Malzeme kusurları</li>
                    <li>Montaj hataları</li>
                    <li>Normal kullanımda ortaya çıkan arızalar</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>6. İade ve İptal</h2>
                <p>
                    İade ve iptal koşulları detaylı olarak 
                    <a href="cancellation-refund.php" style="color: #6366f1; font-weight: 600;">İptal ve İade Koşulları</a> 
                    sayfasında açıklanmıştır.
                </p>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Önemli:</strong> İade talebinizi 14 gün içinde yapmanız gerekmektedir.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>7. Kişisel Verilerin Korunması</h2>
                <p>
                    Kişisel verileriniz, 6698 sayılı KVKK kapsamında korunmaktadır. Detaylı bilgi için 
                    <a href="kvkk-aydinlatma-metni.php" style="color: #6366f1; font-weight: 600;">KVKK Aydınlatma Metni</a>'ni inceleyebilirsiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>8. İletişim ve Destek</h2>
                <p>
                    Tüketici haklarınız hakkında sorularınız için:
                </p>
                <div class="highlight-box">
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                    <p><strong>Şikayet Formu:</strong> <a href="complaint-form.php" style="color: #6366f1; font-weight: 600;">Şikayet Formu</a></p>
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

