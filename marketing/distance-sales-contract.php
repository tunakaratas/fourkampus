<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesafeli Satış Sözleşmesi - UniFour</title>
    <meta name="description" content="UniFour mesafeli satış sözleşmesi. E-ticaret işlemleri için yasal sözleşme metni.">
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
            <h1><i class="fas fa-file-contract"></i> Mesafeli Satış Sözleşmesi</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Taraflar</h2>
                <h3>1.1. Satıcı Bilgileri</h3>
                <div class="contact-info">
                    <p><strong>Şirket Adı:</strong> UniFour</p>
                    <p><strong>E-posta:</strong> info@unifour.com</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
                
                <h3>1.2. Alıcı</h3>
                <p>
                    Bu sözleşme, UniFour platformu üzerinden ürün veya hizmet satın alan 
                    gerçek veya tüzel kişiler (Alıcı) ile UniFour (Satıcı) arasında 
                    6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler 
                    Yönetmeliği hükümlerine göre düzenlenmiştir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Konu</h2>
                <p>
                    Bu sözleşmenin konusu, Alıcı'nın UniFour platformu üzerinden satın aldığı 
                    ürün/hizmetin satışı ve teslimi ile ilgili tarafların hak ve yükümlülükleridir.
                </p>
                <p>
                    <strong>Satılan Ürün/Hizmetler:</strong>
                </p>
                <ul>
                    <li>Profesyonel Plan abonelik hizmeti (₺250/ay)</li>
                    <li>Topluluk yönetim platformu hizmetleri</li>
                    <li>Web sitesi ve email hosting hizmetleri</li>
                    <li>Diğer dijital ürün ve hizmetler</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Sözleşmenin Kurulması</h2>
                <p>
                    Sözleşme, Alıcı'nın platform üzerinden ürün/hizmeti seçmesi, ön bilgilendirme 
                    formunu okuması, ödeme işlemini tamamlaması ve satın alma işlemini onaylaması 
                    ile kurulmuş sayılır.
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
                    Ürün/hizmet fiyatları, platform üzerinde Türk Lirası (₺) cinsinden 
                    gösterilir. Tüm fiyatlar KDV dahildir. Fiyatlar, sipariş verildiği 
                    andaki geçerli fiyatlardır.
                </p>
                
                <h3>4.2. Ödeme Yöntemleri</h3>
                <ul>
                    <li>Kredi kartı (Visa, Mastercard, Troy)</li>
                    <li>Banka kartı</li>
                    <li>Havale/EFT (önceden bildirim ile)</li>
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
                <h3>5.1. Dijital Ürün/Hizmetler</h3>
                <p>
                    Dijital ürün ve hizmetler (abonelik, platform erişimi vb.) sipariş 
                    onayından hemen sonra aktif hale gelir. Erişim bilgileri e-posta ile 
                    Alıcı'ya gönderilir.
                </p>
                
                <h3>5.2. Teslimat Süresi</h3>
                <ul>
                    <li><strong>Dijital Hizmetler:</strong> Anında (sipariş onayından sonra)</li>
                    <li><strong>Fiziksel Ürünler:</strong> 3-7 iş günü (varsa)</li>
                </ul>
                
                <h3>5.3. Teslimat Adresi</h3>
                <p>
                    Alıcı, sipariş sırasında doğru ve güncel adres bilgilerini vermekle 
                    yükümlüdür. Yanlış adres bilgisi nedeniyle oluşan gecikmelerden 
                    Satıcı sorumlu değildir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>6. Cayma Hakkı</h2>
                <h3>6.1. Cayma Hakkı Süresi</h3>
                <p>
                    Alıcı, 6502 sayılı Tüketicinin Korunması Hakkında Kanun'un 15. maddesi 
                    uyarınca, sözleşmeden cayma hakkına sahiptir. Cayma hakkı, ürün/hizmetin 
                    teslim edildiği tarihten itibaren <strong>14 gün</strong> içinde kullanılabilir.
                </p>
                
                <h3>6.2. Cayma Hakkının Kullanılamayacağı Durumlar</h3>
                <p>
                    Aşağıdaki durumlarda cayma hakkı kullanılamaz:
                </p>
                <ul>
                    <li>Alıcı'nın onayı ile anında teslim edilen dijital içerikler</li>
                    <li>Alıcı'nın özel talebi ile hazırlanan kişiselleştirilmiş ürünler</li>
                    <li>Hizmetin tamamen ifa edilmesi durumunda (abonelik süresi dolmuşsa)</li>
                </ul>
                
                <h3>6.3. Cayma Bildirimi</h3>
                <p>
                    Cayma hakkını kullanmak isteyen Alıcı, aşağıdaki yollardan biriyle 
                    bildirimde bulunabilir:
                </p>
                <ul>
                    <li>E-posta: info@unifour.com</li>
                    <li>Telefon: +90 533 544 59 83</li>
                    <li>Platform üzerinden iletişim formu</li>
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
                    <li>İade edilecek ürün/hizmet kullanılmamış olmalıdır</li>
                    <li>İade işlemi 14 iş günü içinde tamamlanır</li>
                    <li>Ödeme, aynı yöntemle iade edilir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>8. Garanti ve Sorumluluk</h2>
                <h3>8.1. Garanti</h3>
                <p>
                    Satıcı, satılan ürün/hizmetin sözleşmede belirtilen özelliklere uygun 
                    olmasını garanti eder. Garanti süresi, ürün/hizmet türüne göre değişiklik 
                    gösterebilir.
                </p>
                
                <h3>8.2. Sorumluluk Sınırlaması</h3>
                <p>
                    Satıcı, aşağıdaki durumlardan sorumlu değildir:
                </p>
                <ul>
                    <li>Alıcı'nın hatalı kullanımından kaynaklanan sorunlar</li>
                    <li>Üçüncü taraf hizmetlerinden kaynaklanan kesintiler</li>
                    <li>İnternet bağlantı sorunları</li>
                    <li>Mücbir sebep durumları</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>9. Fikri Mülkiyet Hakları</h2>
                <p>
                    Satılan ürün/hizmetler üzerindeki tüm fikri mülkiyet hakları Satıcı'ya 
                    aittir. Alıcı, bu hakları ihlal edemez, kopyalayamaz veya üçüncü kişilere 
                    devredemez.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>10. Kişisel Verilerin Korunması</h2>
                <p>
                    Alıcı'nın kişisel verileri, 6698 sayılı KVKK ve 
                    <a href="privacy-policy.php" style="color: #6366f1; font-weight: 600;">Gizlilik Politikamız</a> 
                    kapsamında işlenir ve korunur.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>11. Uyuşmazlıkların Çözümü</h2>
                <h3>11.1. Uygulanacak Hukuk</h3>
                <p>
                    Bu sözleşme, Türkiye Cumhuriyeti yasalarına tabidir.
                </p>
                
                <h3>11.2. Uyuşmazlık Çözümü</h3>
                <ul>
                    <li>Öncelikle dostane çözüm yolları denenir</li>
                    <li>Çözülemezse, Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir</li>
                    <li>İstanbul Tüketici Hakem Heyeti ve Tüketici Mahkemeleri yetkilidir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>12. Sözleşme Değişiklikleri</h2>
                <p>
                    Bu sözleşme, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler e-posta ile bildirilir ve 
                    güncel sözleşme her zaman bu sayfada yayınlanır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>13. İletişim</h2>
                <p>
                    Mesafeli satış sözleşmesi hakkında sorularınız için:
                </p>
                <div class="contact-info">
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

