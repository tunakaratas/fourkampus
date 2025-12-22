<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanım Koşulları - Four Kampüs</title>
    <meta name="description" content="Four Kampüs kullanım koşulları. Platform kullanımı, haklar ve yükümlülükler hakkında detaylı bilgiler.">
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
            <h1><i class="fas fa-file-contract"></i> Kullanım Koşulları</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Genel Hükümler</h2>
                <p>
                    Bu Kullanım Koşulları, <strong>Four Kampüs</strong> platformunu kullanırken geçerlidir. 
                    Platformu kullanarak bu koşulları kabul etmiş sayılırsınız. Eğer bu koşulları 
                    kabul etmiyorsanız, lütfen platformu kullanmayın.
                </p>
                <p>
                    Four Kampüs, üniversite toplulukları için topluluk yönetim sistemi hizmeti sunmaktadır. 
                    Platform, web tabanlı yönetim paneli ve mobil uygulamalar aracılığıyla hizmet vermektedir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>2. Hizmet Tanımı</h2>
                <p>Four Kampüs platformu aşağıdaki hizmetleri sunmaktadır:</p>
                <ul>
                    <li><strong>Topluluk Yönetim Paneli:</strong> Etkinlik, üye, bildirim ve anket yönetimi</li>
                    <li><strong>Email Hizmetleri:</strong> Topluluk için ücretsiz email adresleri (SMS hariç)</li>
                    <li><strong>Mobil Uygulamalar:</strong> iOS (SwiftUI) ve Android (Jetpack Compose) uygulamaları</li>
                    <li><strong>Gerçek Zamanlı Senkronizasyon:</strong> Tüm platformlar arasında 0.4 saniye senkron</li>
                    <li><strong>Bildirim Sistemi:</strong> Push, Email ve SMS bildirimleri</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Kayıt ve Hesap Oluşturma</h2>
                <h3>3.1. Kayıt Süreci</h3>
                <ul>
                    <li>Topluluk kayıt talebi marketing sayfasından yapılır</li>
                    <li>Talep superadmin onayından sonra aktif hale gelir</li>
                    <li>Onay süreci genellikle 24 saat içinde tamamlanır</li>
                    <li>Onay sonrası topluluk klasörü, veritabanı ve admin kullanıcısı oluşturulur</li>
                </ul>
                
                <h3>3.2. Hesap Sorumluluğu</h3>
                <ul>
                    <li>Hesap bilgilerinizin güvenliğinden siz sorumlusunuz</li>
                    <li>Şifrenizi kimseyle paylaşmayın</li>
                    <li>Şüpheli aktivite fark ederseniz derhal bildirin</li>
                    <li>Hesabınız üzerinden yapılan tüm işlemlerden sorumlusunuz</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>4. Fiyatlandırma ve Ödeme</h2>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-gift"></i> 
                        <strong>İlk Yıl Ücretsiz:</strong> Tüm topluluklar ilk yıl tamamen ücretsiz hizmet alır. 
                        Email adresleri ve tüm özellikler dahildir.
                    </p>
                </div>
                <h3>4.1. Ücretsiz Dönem</h3>
                <ul>
                    <li>İlk 12 ay tamamen ücretsiz</li>
                    <li>Ücretsiz email adresleri (SMS hariç)</li>
                    <li>Tüm platform özelliklerine erişim</li>
                    <li>7/24 teknik destek</li>
                </ul>
                
                <h3>4.2. Ücretli Dönem</h3>
                <ul>
                    <li>İlk yıl sonrası fiyatlandırma bilgisi önceden bildirilir</li>
                    <li>Ödeme işlemleri güvenli ödeme sistemleri üzerinden yapılır</li>
                    <li>Fiyat değişiklikleri 30 gün önceden bildirilir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>5. Kullanıcı Yükümlülükleri</h2>
                <h3>5.1. Yasaklanan Faaliyetler</h3>
                <p>Aşağıdaki faaliyetler kesinlikle yasaktır:</p>
                <ul>
                    <li>Yasa dışı içerik paylaşımı</li>
                    <li>Spam, phishing veya dolandırıcılık faaliyetleri</li>
                    <li>Başkalarının kişisel bilgilerini izinsiz kullanma</li>
                    <li>Platform güvenliğini tehdit eden aktiviteler</li>
                    <li>Telif hakkı ihlali</li>
                    <li>Nefret söylemi, ayrımcılık veya taciz</li>
                    <li>Otomatik bot veya script kullanımı (izin verilen API'ler hariç)</li>
                    <li>Hesap paylaşımı veya ticari amaçlı kullanım (izin verilmedikçe)</li>
                </ul>
                
                <h3>5.2. İçerik Sorumluluğu</h3>
                <ul>
                    <li>Yüklediğiniz tüm içeriklerden (etkinlik, görsel, video, metin) siz sorumlusunuz</li>
                    <li>Telif hakkı ihlali yapmamalısınız</li>
                    <li>İçerikleriniz KVKK ve yasal mevzuata uygun olmalıdır</li>
                    <li>Üçüncü kişilerin haklarını ihlal eden içerik yüklememelisiniz</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>6. Hizmet Kullanımı ve Limitler</h2>
                <h3>6.1. Kullanım Limitleri</h3>
                <ul>
                    <li><strong>Depolama:</strong> Makul kullanım prensibi geçerlidir</li>
                    <li><strong>API Kullanımı:</strong> Rate limiting uygulanır (spam önleme)</li>
                    <li><strong>Email Gönderimi:</strong> Günlük limitler uygulanabilir</li>
                    <li><strong>Dosya Yükleme:</strong> Dosya boyutu limitleri geçerlidir</li>
                </ul>
                
                <h3>6.2. Hizmet Kesintileri</h3>
                <ul>
                    <li>Planlı bakım işlemleri önceden duyurulur</li>
                    <li>Acil durumlarda kesintiler olabilir</li>
                    <li>Kesintilerden kaynaklanan zararlardan sorumluluk kabul edilmez</li>
                    <li>Maksimum uptime hedefi %99.5'tir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>7. Fikri Mülkiyet Hakları</h2>
                <h3>7.1. Platform Hakları</h3>
                <p>
                    Four Kampüs platformu, yazılım, tasarım, logo ve marka hakları Four Kampüs'e aittir. 
                    Platform içeriği telif hakkı koruması altındadır.
                </p>
                
                <h3>7.2. Kullanıcı İçerik Hakları</h3>
                <p>
                    Yüklediğiniz içeriklerin (etkinlik, görsel, video vb.) hakları size aittir. 
                    Ancak, platform hizmetlerinin sunulması için gerekli lisansı vermiş olursunuz.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>8. Veri Güvenliği ve Gizlilik</h2>
                <p>
                    Veri güvenliği ve gizlilik konuları detaylı olarak 
                    <a href="privacy-policy.php" style="color: #6366f1; font-weight: 600;">Gizlilik Politikası</a> 
                    sayfasında açıklanmıştır.
                </p>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-shield-alt"></i> 
                        <strong>Güvenlik Önlemleri:</strong> Verileriniz TLS 1.3 ile şifrelenir, 
                        SOC 2 uyumlu altyapıda saklanır ve düzenli yedeklenir.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>9. Hesap İptali ve Fesih</h2>
                <h3>9.1. Kullanıcı Tarafından İptal</h3>
                <ul>
                    <li>Hesabınızı istediğiniz zaman iptal edebilirsiniz</li>
                    <li>İptal talebi superadmin panelinden yapılır</li>
                    <li>İptal sonrası 30 gün içinde verileriniz silinir</li>
                    <li>Yedekleme için veriler geçici olarak saklanabilir</li>
                </ul>
                
                <h3>9.2. Platform Tarafından Fesih</h3>
                <p>Aşağıdaki durumlarda hesabınız feshedilebilir:</p>
                <ul>
                    <li>Kullanım koşullarının ihlali</li>
                    <li>Yasa dışı faaliyetler</li>
                    <li>Ödeme yükümlülüklerinin yerine getirilmemesi (ücretli dönemde)</li>
                    <li>Platform güvenliğini tehdit eden aktiviteler</li>
                </ul>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Uyarı:</strong> Hesap feshi durumunda verileriniz 30 gün içinde silinir. 
                        Bu süre içinde verilerinizi yedekleyebilirsiniz.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>10. Sorumluluk Reddi</h2>
                <h3>10.1. Hizmet Sorumluluğu</h3>
                <p>
                    Four Kampüs, platform hizmetlerini "olduğu gibi" sunar. Aşağıdaki durumlarda 
                    sorumluluk kabul edilmez:
                </p>
                <ul>
                    <li>Hizmet kesintileri veya teknik sorunlar</li>
                    <li>Veri kaybı (yedekleme kullanıcı sorumluluğundadır)</li>
                    <li>Üçüncü taraf hizmetlerinden kaynaklanan sorunlar</li>
                    <li>Kullanıcı hatasından kaynaklanan veri kayıpları</li>
                    <li>İnternet bağlantı sorunları</li>
                </ul>
                
                <h3>10.2. İçerik Sorumluluğu</h3>
                <p>
                    Platform üzerinden paylaşılan içeriklerden (etkinlik, görsel, video, metin) 
                    kullanıcılar sorumludur. Four Kampüs, kullanıcı içeriklerinden sorumlu değildir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>11. Garanti ve Destek</h2>
                <h3>11.1. Hizmet Garantisi</h3>
                <ul>
                    <li>Platform %99.5 uptime hedefi ile çalışır</li>
                    <li>Kritik hatalar 24 saat içinde düzeltilir</li>
                    <li>Yeni özellikler düzenli olarak eklenir</li>
                    <li>Güvenlik güncellemeleri anında uygulanır</li>
                </ul>
                
                <h3>11.2. Teknik Destek</h3>
                <ul>
                    <li><strong>E-posta:</strong> info@fourkampus.com.tr</li>
                    <li><strong>Telefon:</strong> +90 533 544 59 83</li>
                    <li><strong>Çalışma Saatleri:</strong> Pazartesi - Cuma: 09:00 - 18:00</li>
                    <li><strong>Yanıt Süresi:</strong> 24 saat içinde (iş günleri)</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>12. Değişiklikler ve Güncellemeler</h2>
                <p>
                    Bu Kullanım Koşulları, yasal değişiklikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler:
                </p>
                <ul>
                    <li>E-posta ile bildirilir</li>
                    <li>Platform üzerinden duyurulur</li>
                    <li>30 gün önceden bildirilir (mümkün olduğunda)</li>
                </ul>
                <p>
                    Değişikliklerden sonra platformu kullanmaya devam etmeniz, yeni koşulları 
                    kabul ettiğiniz anlamına gelir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>13. Uygulanacak Hukuk ve Uyuşmazlık Çözümü</h2>
                <h3>13.1. Uygulanacak Hukuk</h3>
                <p>
                    Bu Kullanım Koşulları, Türkiye Cumhuriyeti yasalarına tabidir.
                </p>
                
                <h3>13.2. Uyuşmazlık Çözümü</h3>
                <ul>
                    <li>Öncelikle dostane çözüm yolları denenir</li>
                    <li>Uyuşmazlıklar önce müzakere ile çözülmeye çalışılır</li>
                    <li>Çözülemezse, Türkiye Cumhuriyeti mahkemeleri yetkilidir</li>
                    <li>İstanbul mahkemeleri ve icra daireleri yetkilidir</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>14. Çeşitli Hükümler</h2>
                <h3>14.1. Bölünebilirlik</h3>
                <p>
                    Bu koşulların herhangi bir maddesi geçersiz sayılırsa, diğer maddeler geçerliliğini 
                    korumaya devam eder.
                </p>
                
                <h3>14.2. Tam Anlaşma</h3>
                <p>
                    Bu Kullanım Koşulları, platform kullanımı ile ilgili tüm anlaşmaları içerir ve 
                    önceki tüm anlaşmaların yerine geçer.
                </p>
                
                <h3>14.3. Devir</h3>
                <p>
                    Bu koşullar, Four Kampüs'ün izni olmadan devredilemez. Four Kampüs, haklarını ve 
                    yükümlülüklerini üçüncü taraflara devredebilir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>15. İletişim</h2>
                <p>
                    Kullanım koşulları hakkında sorularınız için bizimle iletişime geçebilirsiniz:
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
