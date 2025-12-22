<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KVKK Aydınlatma Metni - Four Kampüs</title>
    <meta name="description" content="Four Kampüs KVKK aydınlatma metni. Kişisel verilerinizin işlenmesi hakkında bilgilendirme.">
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
            <h1><i class="fas fa-shield-alt"></i> KVKK Aydınlatma Metni</h1>
            <p>6698 Sayılı Kişisel Verilerin Korunması Kanunu Uyarınca</p>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Veri Sorumlusu</h2>
                <p>
                    <strong>Four Kampüs</strong> olarak, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") 
                    kapsamında veri sorumlusu sıfatıyla, kişisel verilerinizin işlenmesi hakkında 
                    sizleri bilgilendirmek isteriz.
                </p>
                <div class="highlight-box">
                    <p><strong>İletişim:</strong></p>
                    <p>E-posta: info@fourkampus.com.tr</p>
                    <p>Telefon: +90 533 544 59 83</p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>2. İşlenen Kişisel Veriler</h2>
                <h3>2.1. Kimlik Bilgileri</h3>
                <ul>
                    <li>Ad, soyad</li>
                    <li>E-posta adresi</li>
                    <li>Telefon numarası</li>
                    <li>Doğum tarihi (gerekirse)</li>
                </ul>
                
                <h3>2.2. İletişim Bilgileri</h3>
                <ul>
                    <li>E-posta adresi</li>
                    <li>Telefon numarası</li>
                    <li>Adres bilgileri (teslimat için)</li>
                </ul>
                
                <h3>2.3. İşlem Bilgileri</h3>
                <ul>
                    <li>Sipariş bilgileri</li>
                    <li>Ödeme bilgileri (şifrelenmiş)</li>
                    <li>Fatura bilgileri</li>
                </ul>
                
                <h3>2.4. Platform Kullanım Bilgileri</h3>
                <ul>
                    <li>IP adresi</li>
                    <li>Çerez bilgileri</li>
                    <li>Kullanım logları</li>
                    <li>Cihaz bilgileri</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Kişisel Verilerin İşlenme Amaçları</h2>
                <p>Kişisel verileriniz aşağıdaki amaçlarla işlenmektedir:</p>
                <ul>
                    <li>Platform hizmetlerinin sunulması</li>
                    <li>Üyelik işlemlerinin yönetilmesi</li>
                    <li>Sipariş ve ödeme işlemlerinin gerçekleştirilmesi</li>
                    <li>Müşteri hizmetleri ve destek sağlanması</li>
                    <li>Yasal yükümlülüklerin yerine getirilmesi</li>
                    <li>Güvenlik ve dolandırıcılık önleme</li>
                    <li>İstatistiksel analizler ve raporlama</li>
                    <li>Pazarlama ve tanıtım faaliyetleri (izin verilmesi halinde)</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>4. Kişisel Verilerin İşlenme Hukuki Sebepleri</h2>
                <p>KVKK Madde 5 ve 6 uyarınca, kişisel verileriniz aşağıdaki hukuki sebeplere dayanarak işlenmektedir:</p>
                <ul>
                    <li><strong>KVKK Madde 5/2-a:</strong> Açık rıza</li>
                    <li><strong>KVKK Madde 5/2-c:</strong> Sözleşmenin kurulması veya ifasıyla doğrudan doğruya ilgili olması</li>
                    <li><strong>KVKK Madde 5/2-e:</strong> Veri sorumlusunun hukuki yükümlülüğünü yerine getirebilmesi</li>
                    <li><strong>KVKK Madde 5/2-f:</strong> İlgili kişinin temel hak ve özgürlüklerine zarar vermemek kaydıyla veri sorumlusunun meşru menfaatleri</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Kişisel Verilerin Aktarılması</h2>
                <h3>5.1. Üçüncü Kişilere Aktarım</h3>
                <p>
                    Kişisel verileriniz, yukarıda belirtilen amaçların gerçekleştirilmesi için 
                    aşağıdaki üçüncü kişilere aktarılabilir:
                </p>
                <ul>
                    <li>Ödeme hizmet sağlayıcıları (İyzico vb.)</li>
                    <li>Kargo ve teslimat şirketleri</li>
                    <li>Bulut depolama hizmet sağlayıcıları</li>
                    <li>Yasal danışmanlar ve muhasebe hizmetleri</li>
                    <li>Yasal zorunluluklar gereği yetkili kamu kurumları</li>
                </ul>
                
                <h3>5.2. Yurtdışına Aktarım</h3>
                <p>
                    Kişisel verileriniz, KVKK ve ilgili mevzuat uyarınca, yeterli koruma seviyesine 
                    sahip ülkelere veya yeterli koruma garantisi veren sözleşmelerle aktarılabilir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>6. Kişisel Verilerin Saklanma Süresi</h2>
                <p>
                    Kişisel verileriniz, işlenme amacının gerektirdiği süre boyunca ve yasal 
                    saklama süreleri dahilinde saklanmaktadır. Bu süreler:
                </p>
                <ul>
                    <li><strong>Üyelik bilgileri:</strong> Hesap aktif olduğu sürece</li>
                    <li><strong>Sipariş bilgileri:</strong> 10 yıl (yasal saklama süresi)</li>
                    <li><strong>Fatura bilgileri:</strong> 10 yıl</li>
                    <li><strong>İletişim kayıtları:</strong> 2 yıl</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>7. KVKK Kapsamındaki Haklarınız</h2>
                <p>KVKK Madde 11 uyarınca aşağıdaki haklara sahipsiniz:</p>
                <ul>
                    <li><strong>Bilgi talep etme:</strong> Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
                    <li><strong>Erişim:</strong> İşlenen kişisel verileriniz hakkında bilgi talep etme</li>
                    <li><strong>Düzeltme:</strong> Yanlış veya eksik verilerin düzeltilmesini isteme</li>
                    <li><strong>Silme:</strong> Kişisel verilerinizin silinmesini isteme</li>
                    <li><strong>İtiraz:</strong> İşlenmesine itiraz etme</li>
                    <li><strong>Taşınabilirlik:</strong> Verilerinizin başka bir veri sorumlusuna aktarılmasını isteme</li>
                    <li><strong>Rıza geri çekme:</strong> Rızaya dayalı işlemlerde rızanızı geri çekme</li>
                </ul>
                
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Haklarınızı kullanmak için:</strong> info@fourkampus.com.tr adresine e-posta gönderebilir 
                        veya +90 533 544 59 83 numaralı telefonu arayabilirsiniz.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>8. Güvenlik</h2>
                <p>
                    Kişisel verilerinizin güvenliği için teknik ve idari tedbirler alınmaktadır:
                </p>
                <ul>
                    <li>SSL şifreleme</li>
                    <li>Güvenli sunucu altyapısı</li>
                    <li>Düzenli güvenlik denetimleri</li>
                    <li>Erişim kontrolü ve yetkilendirme</li>
                    <li>Yedekleme ve felaket kurtarma planları</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>9. Şikayet Hakkı</h2>
                <p>
                    Kişisel verilerinizin işlenmesi ile ilgili şikayetlerinizi Kişisel Verileri 
                    Koruma Kurumu'na (KVKK) iletebilirsiniz:
                </p>
                <div class="highlight-box">
                    <p><strong>KVKK İletişim:</strong></p>
                    <p><strong>Adres:</strong> Nasuh Akar Mah. 1407. Sok. No:4 06520 Balgat/Ankara</p>
                    <p><strong>Telefon:</strong> +90 312 216 50 00</p>
                    <p><strong>Web:</strong> www.kvkk.gov.tr</p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>10. Değişiklikler</h2>
                <p>
                    Bu aydınlatma metni, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler e-posta ile bildirilir ve güncel metin 
                    her zaman bu sayfada yayınlanır.
                </p>
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

