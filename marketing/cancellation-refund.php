<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İptal ve İade Koşulları - Four Kampüs</title>
    <meta name="description" content="Four Kampüs iptal ve iade koşulları. Ürün iade süreci ve koşulları hakkında detaylı bilgiler.">
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
        
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
        }
        
        .info-box p {
            margin: 0;
            color: #0c4a6e;
        }
        
        .step-list {
            counter-reset: step-counter;
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            counter-increment: step-counter;
            margin-bottom: 1.5rem;
            padding-left: 3rem;
            position: relative;
        }
        
        .step-list li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
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
            <h1><i class="fas fa-undo-alt"></i> İptal ve İade Koşulları</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Genel Hükümler</h2>
                <p>
                    Bu İptal ve İade Koşulları, 6502 sayılı Tüketicinin Korunması Hakkında Kanun 
                    ve Mesafeli Sözleşmeler Yönetmeliği uyarınca hazırlanmıştır. Four Kampüs platformu 
                    üzerinden yapılan satın alma işlemlerinde geçerlidir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Cayma Hakkı</h2>
                <h3>2.1. Cayma Hakkı Süresi</h3>
                <p>
                    Alıcı, ürün/hizmetin teslim edildiği tarihten itibaren <strong>14 gün</strong> 
                    içinde cayma hakkını kullanabilir. Bu süre, 6502 sayılı Tüketicinin Korunması 
                    Hakkında Kanun'un 15. maddesi uyarınca tanınmıştır.
                </p>
                
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Önemli:</strong> Cayma hakkı süresi, ürün/hizmetin teslim 
                        edildiği tarihten itibaren başlar. Süre, hafta sonu ve resmi tatil 
                        günleri dahil olmak üzere 14 gündür.
                    </p>
                </div>
                
                <h3>2.2. Cayma Hakkının Kullanılamayacağı Durumlar</h3>
                <p>
                    Aşağıdaki durumlarda cayma hakkı kullanılamaz:
                </p>
                <ul>
                    <li>
                        <strong>Dijital İçerikler:</strong> Alıcı'nın onayı ile anında teslim 
                        edilen dijital içerikler (yazılım, abonelik, platform erişimi vb.)
                    </li>
                    <li>
                        <strong>Kişiselleştirilmiş Ürünler:</strong> Alıcı'nın özel talebi ile 
                        hazırlanan, kişiselleştirilmiş ürünler
                    </li>
                    <li>
                        <strong>Tamamlanmış Hizmetler:</strong> Hizmetin tamamen ifa edilmesi 
                        durumunda (abonelik süresi dolmuşsa)
                    </li>
                    <li>
                        <strong>Hızlı Bozulan Ürünler:</strong> Hızlı bozulan veya son kullanma 
                        tarihi geçen ürünler (varsa)
                    </li>
                </ul>
                
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Uyarı:</strong> Dijital hizmetler (abonelik, platform erişimi) 
                        için cayma hakkı, hizmetin kullanılmaya başlanması durumunda kullanılamaz.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>3. İptal İşlemi</h2>
                <h3>3.1. İptal Talebi Nasıl Yapılır?</h3>
                <p>
                    Cayma hakkını kullanmak isteyen Alıcı, aşağıdaki yollardan biriyle bildirimde bulunabilir:
                </p>
                <ul>
                    <li><strong>E-posta:</strong> info@fourkampus.com.tr</li>
                    <li><strong>Telefon:</strong> +90 533 544 59 83</li>
                    <li><strong>Platform:</strong> Üyelik paneli üzerinden iletişim formu</li>
                </ul>
                
                <h3>3.2. İptal Bildiriminde Bulunması Gereken Bilgiler</h3>
                <ul>
                    <li>Sipariş numarası</li>
                    <li>Alıcı adı ve soyadı</li>
                    <li>İletişim bilgileri (telefon, e-posta)</li>
                    <li>İptal nedeni (isteğe bağlı)</li>
                </ul>
                
                <h3>3.3. İptal İşlem Süreci</h3>
                <ol class="step-list">
                    <li>
                        <strong>İptal Talebi:</strong> Alıcı, yukarıdaki yollardan biriyle 
                        iptal talebini bildirir.
                    </li>
                    <li>
                        <strong>Onay:</strong> Satıcı, iptal talebini en geç 2 iş günü içinde 
                        onaylar ve Alıcı'ya bildirir.
                    </li>
                    <li>
                        <strong>İade İşlemi:</strong> Fiziksel ürünler için (varsa) iade 
                        süreci başlatılır.
                    </li>
                    <li>
                        <strong>Ödeme İadesi:</strong> Ödeme, aşağıdaki koşullara göre iade edilir.
                    </li>
                </ol>
            </div>
            
            <div class="legal-section">
                <h2>4. İade İşlemleri</h2>
                <h3>4.1. İade Koşulları</h3>
                <p>
                    İade edilecek ürün/hizmet aşağıdaki koşulları sağlamalıdır:
                </p>
                <ul>
                    <li>Ürün/hizmet kullanılmamış olmalıdır</li>
                    <li>Orijinal ambalajında olmalıdır (fiziksel ürünler için)</li>
                    <li>Eksiksiz ve hasarsız olmalıdır</li>
                    <li>İade süresi içinde talep edilmelidir (14 gün)</li>
                </ul>
                
                <h3>4.2. İade Süreci</h3>
                <ol class="step-list">
                    <li>
                        <strong>İade Talebi:</strong> Alıcı, iptal talebi ile birlikte 
                        iade talebini bildirir.
                    </li>
                    <li>
                        <strong>Kargo Bilgisi:</strong> Fiziksel ürünler için (varsa) 
                        kargo bilgileri Alıcı'ya gönderilir.
                    </li>
                    <li>
                        <strong>Ürün Kontrolü:</strong> Satıcı, iade edilen ürünü kontrol eder.
                    </li>
                    <li>
                        <strong>Onay:</strong> Ürün koşulları sağlıyorsa, iade onaylanır.
                    </li>
                </ol>
                
                <h3>4.3. İade Kargo Ücreti</h3>
                <ul>
                    <li>
                        <strong>Ürün Hatası:</strong> Ürün hatası veya eksikliği durumunda, 
                        kargo ücreti Satıcı tarafından karşılanır.
                    </li>
                    <li>
                        <strong>Alıcı Kaynaklı:</strong> Alıcı'nın cayma hakkını kullanması 
                        durumunda, kargo ücreti Alıcı'ya aittir (fiziksel ürünler için).
                    </li>
                    <li>
                        <strong>Dijital Hizmetler:</strong> Dijital hizmetler için kargo 
                        ücreti söz konusu değildir.
                    </li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Ödeme İadesi</h2>
                <h3>5.1. İade Süresi</h3>
                <p>
                    Ödeme iadesi, iade onayından sonra <strong>14 iş günü</strong> içinde 
                    yapılır. İade süresi, banka işlem sürelerine bağlı olarak değişebilir.
                </p>
                
                <h3>5.2. İade Yöntemi</h3>
                <ul>
                    <li>
                        <strong>Kredi/Banka Kartı:</strong> Ödeme, aynı kart hesabına iade edilir.
                    </li>
                    <li>
                        <strong>Havale/EFT:</strong> Ödeme, aynı banka hesabına iade edilir.
                    </li>
                    <li>
                        <strong>Diğer Yöntemler:</strong> Ödeme yöntemine göre iade edilir.
                    </li>
                </ul>
                
                <h3>5.3. İade Tutarı</h3>
                <p>
                    İade tutarı, ödenen toplam tutardır. Ancak aşağıdaki durumlarda kesinti yapılabilir:
                </p>
                <ul>
                    <li>Ürün/hizmetin kullanılması durumunda değer kaybı</li>
                    <li>Kargo ücreti (Alıcı kaynaklı iade durumunda)</li>
                    <li>İşlem ücretleri (varsa)</li>
                </ul>
                
                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Bilgi:</strong> İade tutarı, ödeme yöntemine göre 14 iş günü 
                        içinde hesabınıza yansır. Banka işlem süreleri farklılık gösterebilir.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>6. Abonelik İptali</h2>
                <h3>6.1. Abonelik Planları</h3>
                <ul>
                    <li><strong>Profesyonel Plan:</strong> ₺250/ay</li>
                    <li><strong>Business Plan:</strong> ₺500/ay</li>
                </ul>
                
                <h3>6.2. Abonelik İptal Süreci</h3>
                <p>
                    Aboneliğinizi iptal etmek için:
                </p>
                <ul>
                    <li>Üyelik paneli üzerinden "Aboneliği İptal Et" seçeneğini kullanın</li>
                    <li>E-posta ile info@fourkampus.com.tr adresine bildirimde bulunun</li>
                    <li>Telefon ile +90 533 544 59 83 numarasını arayın</li>
                </ul>
                
                <h3>6.3. Abonelik İptal Koşulları</h3>
                <ul>
                    <li>
                        <strong>Dönem İçi İptal:</strong> Abonelik dönemi içinde iptal edilirse, 
                        kalan süre için ödeme iadesi yapılmaz. Hizmet, dönem sonuna kadar devam eder.
                    </li>
                    <li>
                        <strong>Otomatik Yenileme:</strong> İptal edilen abonelik, bir sonraki 
                        dönem için otomatik olarak yenilenmez.
                    </li>
                    <li>
                        <strong>Veri Saklama:</strong> İptal sonrası verileriniz 30 gün süreyle 
                        saklanır. Bu süre içinde verilerinizi yedekleyebilirsiniz.
                    </li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>7. Topluluk Market Ürünleri İadesi</h2>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Önemli:</strong> Platform üzerindeki topluluklar tarafından satılan fiziksel ürünlerin 
                        iadesi, ilgili toplulukların belirleyeceği koşullara tabidir. Four Kampüs, bu ürünler 
                        için aracı platform konumundadır.
                    </p>
                </div>
                <p>
                    Topluluk market ürünleri için iade talepleri doğrudan ilgili topluluk yöneticileri ile 
                    iletişime geçilerek yapılmalıdır. Topluluklar, kendi iade politikalarını belirlemekle 
                    yükümlüdür.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>8. İtiraz ve Şikayet</h2>
                <h3>8.1. İtiraz Hakkı</h3>
                <p>
                    İade/iptal işlemi ile ilgili itirazlarınızı aşağıdaki yollardan bildirebilirsiniz:
                </p>
                <ul>
                    <li>E-posta: info@fourkampus.com.tr</li>
                    <li>Telefon: +90 533 544 59 83</li>
                    <li>Platform üzerinden iletişim formu</li>
                </ul>
                
                <h3>8.2. Tüketici Hakem Heyeti</h3>
                <p>
                    Uyuşmazlıkların çözülememesi durumunda, Tüketici Hakem Heyetleri ve 
                    Tüketici Mahkemeleri'ne başvurabilirsiniz. İstanbul Tüketici Hakem Heyeti 
                    ve Tüketici Mahkemeleri yetkilidir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>9. Özel Durumlar</h2>
                <h3>9.1. Kampanya ve İndirimli Ürünler</h3>
                <p>
                    Kampanya ve indirimli ürünler için de aynı iptal ve iade koşulları geçerlidir. 
                    İndirimli fiyat üzerinden iade yapılır.
                </p>
                
                <h3>9.2. Hediye Ürünler</h3>
                <p>
                    Hediye olarak gönderilen ürünler için de aynı iptal ve iade koşulları geçerlidir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>10. İletişim</h2>
                <p>
                    İptal ve iade işlemleri hakkında sorularınız için:
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

