<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gizlilik Politikası - Four Kampüs</title>
    <meta name="description" content="Four Kampüs gizlilik politikası. Kişisel verilerinizin korunması ve güvenliği hakkında detaylı bilgiler.">
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
            <h1><i class="fas fa-shield-alt"></i> Gizlilik Politikası</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Genel Bilgiler</h2>
                <p>
                    <strong>Four Kampüs</strong> olarak, kişisel verilerinizin korunmasına büyük önem vermekteyiz. 
                    Bu Gizlilik Politikası, Four Kampüs platformunu kullanırken toplanan, işlenen ve saklanan 
                    kişisel verileriniz hakkında bilgi vermek amacıyla hazırlanmıştır.
                </p>
                <p>
                    Bu politika, 6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) ve Avrupa Birliği 
                    Genel Veri Koruma Tüzüğü (GDPR) kapsamında hazırlanmıştır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Veri Sorumlusu</h2>
                <p>
                    <strong>Four Kampüs</strong> hizmetlerini sunan veri sorumlusu:
                </p>
                <div class="contact-info">
                    <p><strong>Şirket Adı:</strong> Four Kampüs</p>
                    <p><strong>E-posta:</strong> info@fourkampus.com.tr</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>3. Toplanan Kişisel Veriler</h2>
                <h3>3.1. Topluluk Yöneticileri İçin</h3>
                <p>Topluluk kayıt işlemi sırasında aşağıdaki veriler toplanmaktadır:</p>
                <ul>
                    <li><strong>Kimlik Bilgileri:</strong> Ad, soyad, kullanıcı adı</li>
                    <li><strong>İletişim Bilgileri:</strong> E-posta adresi, telefon numarası</li>
                    <li><strong>Topluluk Bilgileri:</strong> Topluluk adı, üniversite bilgisi, klasör adı</li>
                    <li><strong>Güvenlik Bilgileri:</strong> Şifre (hash'lenmiş olarak saklanır)</li>
                    <li><strong>Teknik Bilgiler:</strong> IP adresi, tarayıcı bilgisi, cihaz bilgisi</li>
                </ul>
                
                <h3>3.2. E-Ticaret İşlemleri İçin</h3>
                <p>Satın alma işlemleri sırasında aşağıdaki veriler toplanmaktadır:</p>
                <ul>
                    <li><strong>Kimlik Bilgileri:</strong> Ad, soyad</li>
                    <li><strong>İletişim Bilgileri:</strong> E-posta adresi, telefon numarası</li>
                    <li><strong>Adres Bilgileri:</strong> Teslimat adresi (fiziksel ürünler için)</li>
                    <li><strong>Ödeme Bilgileri:</strong> Ödeme yöntemi, işlem bilgileri (kart bilgileri saklanmaz)</li>
                    <li><strong>Sipariş Bilgileri:</strong> Sipariş numarası, ürün/hizmet bilgileri, tutar</li>
                    <li><strong>Fatura Bilgileri:</strong> Fatura adresi, vergi numarası (varsa)</li>
                </ul>
                
                <h3>3.3. Platform Kullanımı Sırasında</h3>
                <ul>
                    <li>Giriş/çıkış kayıtları ve zaman damgaları</li>
                    <li>Platform içi aktiviteler (etkinlik oluşturma, üye ekleme, bildirim gönderme vb.)</li>
                    <li>Hata logları ve teknik performans verileri</li>
                    <li>Kullanım istatistikleri ve analitik veriler</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>4. Verilerin İşlenme Amaçları</h2>
                <p>Toplanan kişisel veriler aşağıdaki amaçlarla işlenmektedir:</p>
                <ul>
                    <li>Topluluk hesabı oluşturma ve yönetimi</li>
                    <li>Platform hizmetlerinin sunulması ve geliştirilmesi</li>
                    <li>Kullanıcı kimlik doğrulama ve güvenlik</li>
                    <li>Teknik destek ve müşteri hizmetleri</li>
                    <li>E-ticaret işlemlerinin gerçekleştirilmesi (sipariş, ödeme, teslimat)</li>
                    <li>Fatura düzenleme ve muhasebe işlemleri</li>
                    <li>İade ve iptal işlemlerinin yönetimi</li>
                    <li>Yasal yükümlülüklerin yerine getirilmesi</li>
                    <li>Platform güvenliğinin sağlanması ve kötüye kullanımın önlenmesi</li>
                    <li>İstatistiksel analizler ve raporlama</li>
                    <li>Yeni özellikler ve hizmetlerin geliştirilmesi</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Verilerin İşlenme Hukuki Sebepleri</h2>
                <p>Kişisel verileriniz aşağıdaki hukuki sebeplere dayanarak işlenmektedir:</p>
                <ul>
                    <li>KVKK Madde 5/2-c: "Sözleşmenin kurulması veya ifasıyla doğrudan doğruya ilgili olması"</li>
                    <li>KVKK Madde 5/2-e: "Veri sorumlusunun hukuki yükümlülüğünü yerine getirebilmesi"</li>
                    <li>KVKK Madde 5/2-f: "İlgili kişinin temel hak ve özgürlüklerine zarar vermemek kaydıyla veri sorumlusunun meşru menfaatleri"</li>
                    <li>Açık rıza (bildirim gönderimi, pazarlama faaliyetleri için)</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>6. Verilerin Saklanma Süresi</h2>
                <p>
                    Kişisel verileriniz, işlenme amaçlarının gerektirdiği süre boyunca saklanmaktadır. 
                    Genel olarak:
                </p>
                <ul>
                    <li><strong>Aktif Hesaplar:</strong> Hesap aktif olduğu sürece saklanır</li>
                    <li><strong>Silinen Hesaplar:</strong> Hesap silindikten sonra 30 gün içinde tamamen silinir</li>
                    <li><strong>Yasal Yükümlülükler:</strong> Yasal saklama süreleri boyunca saklanır</li>
                    <li><strong>Log Kayıtları:</strong> Güvenlik amaçlı 12 ay saklanır</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>7. Veri Güvenliği</h2>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-shield-alt"></i> 
                        <strong>Güvenlik Önlemleri:</strong> Verileriniz TLS 1.3 protokolü ile şifrelenir 
                        ve SOC 2 uyumlu altyapıda güvenle saklanır.
                    </p>
                </div>
                <h3>7.1. Teknik Önlemler</h3>
                <ul>
                    <li><strong>Şifreleme:</strong> Tüm veriler TLS 1.3 ile şifrelenir</li>
                    <li><strong>Veritabanı Güvenliği:</strong> SQLite veritabanları şifrelenmiş ve yedeklenmiş durumda</li>
                    <li><strong>Şifre Yönetimi:</strong> Şifreler bcrypt algoritması ile hash'lenir (asla düz metin saklanmaz)</li>
                    <li><strong>Erişim Kontrolü:</strong> Rol bazlı erişim kontrolü (RBAC) uygulanır</li>
                    <li><strong>Rate Limiting:</strong> Brute force saldırılarına karşı koruma</li>
                    <li><strong>Session Güvenliği:</strong> Güvenli session yönetimi ve timeout mekanizmaları</li>
                </ul>
                
                <h3>7.2. Fiziksel Önlemler</h3>
                <ul>
                    <li>Sunucu erişim kontrolleri</li>
                    <li>Düzenli güvenlik denetimleri</li>
                    <li>Yedekleme ve felaket kurtarma planları</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>8. Verilerin Paylaşılması</h2>
                <p>
                    Kişisel verileriniz, aşağıdaki durumlar dışında üçüncü kişilerle paylaşılmaz:
                </p>
                <ul>
                    <li><strong>Yasal Yükümlülükler:</strong> Yasal zorunluluklar gereği yetkili kurumlarla paylaşım</li>
                    <li><strong>Hizmet Sağlayıcılar:</strong> Platform hizmetlerinin sunulması için gerekli teknik hizmet sağlayıcılar (hosting, email servisleri vb.)</li>
                    <li><strong>İş Ortakları:</strong> Sadece açık rıza ile ve sınırlı amaçlarla</li>
                </ul>
                <p>
                    <strong>Önemli:</strong> Verileriniz hiçbir zaman reklam veya pazarlama amaçlı üçüncü taraflarla paylaşılmaz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>9. Çerezler (Cookies)</h2>
                <p>
                    Platform, kullanıcı deneyimini iyileştirmek için çerezler kullanmaktadır:
                </p>
                <ul>
                    <li><strong>Zorunlu Çerezler:</strong> Oturum yönetimi ve güvenlik için gerekli</li>
                    <li><strong>Fonksiyonel Çerezler:</strong> Kullanıcı tercihlerinin hatırlanması</li>
                    <li><strong>Analitik Çerezler:</strong> Platform kullanım istatistikleri (anonim)</li>
                </ul>
                <p>
                    Tarayıcı ayarlarınızdan çerezleri yönetebilirsiniz, ancak bu durumda bazı özellikler çalışmayabilir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>10. KVKK Kapsamındaki Haklarınız</h2>
                <p>KVKK Madde 11 uyarınca aşağıdaki haklara sahipsiniz:</p>
                <ul>
                    <li><strong>Bilgi Alma Hakkı:</strong> Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
                    <li><strong>Erişim Hakkı:</strong> İşlenen kişisel verileriniz hakkında bilgi talep etme</li>
                    <li><strong>Düzeltme Hakkı:</strong> Yanlış veya eksik verilerin düzeltilmesini isteme</li>
                    <li><strong>Silme Hakkı:</strong> Kişisel verilerinizin silinmesini isteme</li>
                    <li><strong>İtiraz Hakkı:</strong> Kişisel verilerinizin işlenmesine itiraz etme</li>
                    <li><strong>Veri Taşınabilirliği:</strong> Verilerinizi başka bir sisteme aktarma</li>
                    <li><strong>Rıza Geri Çekme:</strong> Verilen rızayı geri çekme</li>
                </ul>
                <p>
                    Bu haklarınızı kullanmak için <strong>info@fourkampus.com.tr</strong> adresine e-posta gönderebilirsiniz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>11. Çocukların Gizliliği</h2>
                <p>
                    Four Kampüs platformu 18 yaş altındaki kişilerden bilerek kişisel veri toplamamaktadır. 
                    Eğer 18 yaş altında bir kişinin veri topladığımızı fark edersek, bu verileri derhal sileriz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>12. Uluslararası Veri Transferi</h2>
                <p>
                    Verileriniz şu anda Türkiye sınırları içindeki sunucularda saklanmaktadır. 
                    Eğer gelecekte uluslararası veri transferi yapılması gerekirse, GDPR ve KVKK 
                    uyumlu anlaşmalar yapılacak ve kullanıcılar bilgilendirilecektir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>13. Politika Değişiklikleri</h2>
                <p>
                    Bu Gizlilik Politikası, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    güncellenebilir. Önemli değişiklikler e-posta veya platform bildirimi ile 
                    kullanıcılara duyurulur. Güncel politika her zaman bu sayfada yayınlanır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>14. İletişim</h2>
                <p>
                    Gizlilik politikamız hakkında sorularınız veya haklarınızı kullanmak istiyorsanız, 
                    bizimle iletişime geçebilirsiniz:
                </p>
                <div class="contact-info">
                    <p><strong>E-posta:</strong> info@fourkampus.com.tr</p>
                    <p><strong>Telefon:</strong> +90 533 544 59 83</p>
                    <p><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>15. Şikayet Hakkı</h2>
                <p>
                    Kişisel verilerinizin işlenmesi ile ilgili şikayetlerinizi Kişisel Verileri Koruma 
                    Kurumu'na (KVKK) iletebilirsiniz:
                </p>
                <div class="contact-info">
                    <p><strong>Kurum:</strong> Kişisel Verileri Koruma Kurumu</p>
                    <p><strong>Web:</strong> www.kvkk.gov.tr</p>
                    <p><strong>Telefon:</strong> 0850 201 11 35</p>
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

