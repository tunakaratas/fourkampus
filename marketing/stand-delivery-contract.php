<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stant Teslimat Sözleşmesi - UniFour</title>
    <meta name="description" content="UniFour stant teslimat sözleşmesi. Topluluk stantlarından elden teslimat için yasal sözleşme metni.">
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
        
        .important-box {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
        }
        
        .important-box p {
            margin: 0;
            color: #991b1b;
            font-weight: 600;
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
            <h1><i class="fas fa-store"></i> Stant Teslimat Sözleşmesi</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="important-box">
                <p>
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ÖNEMLİ:</strong> UniFour sadece bir aracı platformdur. Teslimat sorumluluğu tamamen topluluğa aittir. 
                    Ürünlerle ilgili sorularınız için lütfen ilgili toplulukla iletişime geçiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>1. Taraflar</h2>
                <h3>1.1. Platform (Aracı)</h3>
                <div class="contact-info">
                    <p><strong>Platform Adı:</strong> UniFour</p>
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
                
                <h3>1.2. Satıcı (Topluluk)</h3>
                <p>
                    Ürünleri satan taraf, UniFour platformuna kayıtlı topluluklardır. Her topluluk 
                    kendi ürünlerinin satışından ve teslimatından sorumludur.
                </p>
                
                <h3>1.3. Alıcı (Müşteri)</h3>
                <p>
                    Bu sözleşme, UniFour platformu üzerinden topluluk stantlarından ürün satın alan 
                    gerçek veya tüzel kişiler (Alıcı) ile ilgili topluluk (Satıcı) arasında 
                    düzenlenmiştir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Konu</h2>
                <p>
                    Bu sözleşmenin konusu, Alıcı'nın UniFour platformu üzerinden satın aldığı 
                    ürünlerin topluluk stantlarından elden teslim edilmesi ile ilgili tarafların 
                    hak ve yükümlülükleridir.
                </p>
                
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <strong>Teslimat Şekli:</strong> Ürünler kargo ile gönderilmez. Tüm ürünler 
                        topluluk stantlarından elden teslim edilir.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>3. Sözleşmenin Kurulması</h2>
                <p>
                    Sözleşme, Alıcı'nın platform üzerinden ürünü seçmesi, ödeme işlemini tamamlaması 
                    ve satın alma işlemini onaylaması ile kurulmuş sayılır.
                </p>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <strong>Önemli:</strong> Sipariş onayı, Alıcı'ya e-posta ile gönderilir. 
                        Sipariş onayı gönderildiği anda sözleşme kesinleşir.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>4. Fiyat ve Ödeme</h2>
                <h3>4.1. Fiyat</h3>
                <p>
                    Ürün fiyatları, platform üzerinde Türk Lirası (₺) cinsinden gösterilir. 
                    Tüm fiyatlar KDV dahildir. Fiyatlar, sipariş verildiği andaki geçerli fiyatlardır.
                </p>
                
                <h3>4.2. Ödeme Yöntemleri</h3>
                <ul>
                    <li>Kredi kartı (Visa, Mastercard, Troy)</li>
                    <li>Banka kartı</li>
                    <li>Diğer güvenli ödeme yöntemleri</li>
                </ul>
                
                <h3>4.3. Ödeme Güvenliği</h3>
                <p>
                    Tüm ödeme işlemleri SSL sertifikası ile şifrelenir ve güvenli ödeme 
                    altyapısı üzerinden gerçekleştirilir. Kredi kartı bilgileri saklanmaz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>5. Teslimat</h2>
                <h3>5.1. Teslimat Şekli</h3>
                <p>
                    <strong>Tüm ürünler topluluk stantlarından elden teslim edilir.</strong> 
                    Ürünler kargo ile gönderilmez.
                </p>
                
                <h3>5.2. Stant Konumu ve Teslimat Tarihi</h3>
                <ul>
                    <li>Stant konumu ve teslimat tarihi <strong>topluluk tarafından belirlenir</strong></li>
                    <li>Topluluk, stant konumu ve teslimat tarihi hakkında müşteriyi bilgilendirmekle yükümlüdür</li>
                    <li>Müşteri, stant konumuna giderek ürünü elden teslim alır</li>
                    <li>Teslimat sırasında kimlik kontrolü yapılabilir</li>
                </ul>
                
                <h3>5.3. Teslimat Sorumluluğu</h3>
                <div class="important-box">
                    <p>
                        <strong>UniFour'un Sorumluluğu:</strong> UniFour sadece bir aracı platformdur. 
                        Teslimat sorumluluğu tamamen topluluğa aittir. UniFour, teslimat süreci, 
                        stant konumu, teslimat tarihi veya ürün kalitesi konularında sorumluluk kabul etmez.
                    </p>
                </div>
                
                <p>
                    <strong>Topluluğun Sorumluluğu:</strong>
                </p>
                <ul>
                    <li>Ürünleri belirlenen stant konumunda hazır bulundurmak</li>
                    <li>Müşteriyi stant konumu ve teslimat tarihi hakkında bilgilendirmek</li>
                    <li>Ürünlerin kaliteli ve siparişe uygun olmasını sağlamak</li>
                    <li>Teslimat sırasında müşteriye yardımcı olmak</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>6. Cayma Hakkı</h2>
                <h3>6.1. Cayma Hakkı Süresi</h3>
                <p>
                    Alıcı, 6502 sayılı Tüketicinin Korunması Hakkında Kanun'un 15. maddesi 
                    uyarınca, sözleşmeden cayma hakkına sahiptir. Cayma hakkı, ürünün teslim 
                    edildiği tarihten itibaren <strong>14 gün</strong> içinde kullanılabilir.
                </p>
                
                <h3>6.2. Cayma Bildirimi</h3>
                <p>
                    Cayma hakkını kullanmak isteyen Alıcı, aşağıdaki yollardan biriyle 
                    bildirimde bulunabilir:
                </p>
                <ul>
                    <li>E-posta: info@unifour.com</li>
                    <li>Telefon: +90 533 544 59 83</li>
                    <li>Platform üzerinden iletişim formu</li>
                    <li>İlgili toplulukla doğrudan iletişim</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>7. İade ve İptal</h2>
                <h3>7.1. İade Koşulları</h3>
                <p>
                    İade koşulları detaylı olarak 
                    <a href="cancellation-refund.php" style="color: #6366f1; font-weight: 600;">İptal ve İade Koşulları</a> 
                    sayfasında açıklanmıştır.
                </p>
                
                <h3>7.2. İade İşlemleri</h3>
                <ul>
                    <li>İade talebi 14 gün içinde yapılmalıdır</li>
                    <li>İade edilecek ürün kullanılmamış ve ambalajı açılmamış olmalıdır</li>
                    <li>İade işlemi topluluk stantından yapılır</li>
                    <li>Ödeme, aynı yöntemle iade edilir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>8. Garanti ve Sorumluluk</h2>
                <h3>8.1. Garanti</h3>
                <p>
                    Ürün garantisi, topluluk tarafından sağlanır. Garanti süresi ve koşulları 
                    ürün türüne göre değişiklik gösterebilir.
                </p>
                
                <h3>8.2. Sorumluluk Sınırlaması</h3>
                <div class="warning-box">
                    <p>
                        <strong>UniFour'un Sorumluluğu:</strong> UniFour sadece bir aracı platformdur. 
                        Ürün kalitesi, teslimat, garanti ve iade konularında sorumluluk kabul etmez. 
                        Tüm sorumluluk ilgili topluluğa aittir.
                    </p>
                </div>
                
                <p>
                    <strong>Topluluğun Sorumluluğu:</strong>
                </p>
                <ul>
                    <li>Ürünlerin kaliteli ve siparişe uygun olması</li>
                    <li>Stant konumu ve teslimat tarihi bilgilendirmesi</li>
                    <li>Ürün garantisi ve iade işlemleri</li>
                    <li>Müşteri şikayetlerinin çözülmesi</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>9. Kişisel Verilerin Korunması</h2>
                <p>
                    Alıcı'nın kişisel verileri, 6698 sayılı KVKK ve 
                    <a href="privacy-policy.php" style="color: #6366f1; font-weight: 600;">Gizlilik Politikamız</a> 
                    kapsamında işlenir ve korunur.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>10. Uyuşmazlıkların Çözümü</h2>
                <h3>10.1. Uygulanacak Hukuk</h3>
                <p>
                    Bu sözleşme, Türkiye Cumhuriyeti yasalarına tabidir.
                </p>
                
                <h3>10.2. Uyuşmazlık Çözümü</h3>
                <ul>
                    <li>Öncelikle müşteri ile topluluk arasında dostane çözüm yolları denenir</li>
                    <li>Çözülemezse, Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir</li>
                    <li>İstanbul Tüketici Hakem Heyeti ve Tüketici Mahkemeleri yetkilidir</li>
                </ul>
                
                <div class="warning-box">
                    <p>
                        <strong>Not:</strong> UniFour uyuşmazlıkların çözümünde taraf değildir. 
                        Uyuşmazlıklar müşteri ile topluluk arasında çözülür.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>11. Sözleşme Değişiklikleri</h2>
                <p>
                    Bu sözleşme, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler e-posta ile bildirilir ve 
                    güncel sözleşme her zaman bu sayfada yayınlanır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>12. İletişim</h2>
                <p>
                    Stant teslimat sözleşmesi hakkında sorularınız için:
                </p>
                <div class="contact-info">
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
                
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <strong>Önemli:</strong> Ürünlerle ilgili sorularınız için lütfen ilgili 
                        toplulukla iletişime geçiniz. UniFour sadece platform hizmeti sağlar.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

