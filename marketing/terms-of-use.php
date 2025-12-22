<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanım Şartları - Four Kampüs</title>
    <meta name="description" content="Four Kampüs platform kullanım şartları ve kuralları.">
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
            <h1><i class="fas fa-gavel"></i> Kullanım Şartları</h1>
            <p>Son Güncelleme: <?= date('d.m.Y') ?></p>
        </div>
        
        <div class="legal-content">
            <div class="legal-section">
                <h2>1. Genel Hükümler</h2>
                <p>
                    Bu Kullanım Şartları ("Şartlar"), Four Kampüs platformunu ("Platform") kullanarak 
                    hizmetlerimizden yararlanan tüm kullanıcılar için geçerlidir. Platformu kullanarak, 
                    bu şartları kabul etmiş sayılırsınız.
                </p>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Önemli:</strong> Bu şartları kabul etmeden platformu kullanamazsınız. 
                        Şartları kabul etmiyorsanız, lütfen platformu kullanmayın.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>2. Platform Kullanımı</h2>
                <h3>2.1. Kullanıcı Hesabı</h3>
                <ul>
                    <li>Hesap bilgilerinizi doğru ve güncel tutmakla yükümlüsünüz</li>
                    <li>Hesap güvenliğinden siz sorumlusunuz</li>
                    <li>Şifrenizi kimseyle paylaşmayın</li>
                    <li>Şüpheli aktivite tespit ederseniz derhal bildirin</li>
                </ul>
                
                <h3>2.2. Yasaklanan Faaliyetler</h3>
                <p>Aşağıdaki faaliyetler kesinlikle yasaktır:</p>
                <ul>
                    <li>Yasadışı içerik paylaşımı</li>
                    <li>Başkalarının haklarını ihlal etme</li>
                    <li>Spam, phishing veya dolandırıcılık</li>
                    <li>Platformun güvenliğini tehdit etme</li>
                    <li>Diğer kullanıcıları rahatsız etme veya taciz etme</li>
                    <li>Telif hakkı ihlali</li>
                    <li>Yanlış bilgi paylaşımı</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>3. Topluluk Kuralları</h2>
                <h3>3.1. Topluluk Oluşturma</h3>
                <ul>
                    <li>Topluluk adı ve içeriği yasalara uygun olmalıdır</li>
                    <li>Nefret söylemi, ayrımcılık içeren içerik yasaktır</li>
                    <li>Topluluk yöneticileri içerikten sorumludur</li>
                </ul>
                
                <h3>3.2. Üye Davranışları</h3>
                <ul>
                    <li>Diğer üyelere saygılı olun</li>
                    <li>Spam mesajlar göndermeyin</li>
                    <li>Kişisel bilgileri izinsiz paylaşmayın</li>
                    <li>Topluluk kurallarına uyun</li>
                </ul>
            </div>
            
            <div class="legal-section">
                <h2>4. Market ve Ürün Satışı</h2>
                <div class="highlight-box">
                    <p>
                        <i class="fas fa-info-circle"></i> 
                        <strong>Önemli:</strong> Four Kampüs platformu üzerindeki market bölümünde satılan fiziksel ürünler, 
                        topluluklar tarafından satışa sunulmaktadır. Four Kampüs, bu ürünler için aracı konumundadır.
                    </p>
                </div>
                
                <h3>4.1. Topluluk Satış Kuralları</h3>
                <ul>
                    <li>Yasaklı ürünler satılamaz (alkol, tütün, ilaç, silah vb.)</li>
                    <li>Ürün bilgileri doğru ve eksiksiz olmalıdır</li>
                    <li>Fiyatlar KDV dahil gösterilmelidir</li>
                    <li>Stok durumu güncel tutulmalıdır</li>
                </ul>
                
                <h3>4.2. Topluluk Satıcı Sorumlulukları</h3>
                <ul>
                    <li>Ürünlerin yasal ve güvenli olduğunu garanti etmek</li>
                    <li>Teslimat sürelerine uymak</li>
                    <li>Müşteri şikayetlerini zamanında yanıtlamak</li>
                    <li>İade ve iptal politikalarını belirlemek ve uygulamak</li>
                </ul>
                
                <h3>4.3. Four Kampüs'ün Rolü</h3>
                <p>
                    Four Kampüs, topluluklar tarafından satılan ürünlerin kalitesi, teslimatı veya 
                    iadesi konusunda sorumluluk kabul etmez. Platform, yalnızca satış altyapısı 
                    sağlamaktadır. Sorunlar için doğrudan ilgili topluluk yönetimine başvurulmalıdır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>5. Fikri Mülkiyet Hakları</h2>
                <h3>5.1. Platform İçeriği</h3>
                <p>
                    Platform üzerindeki tüm içerik, tasarım ve yazılım Four Kampüs'e aittir. 
                    İzinsiz kopyalama, dağıtma veya kullanım yasaktır.
                </p>
                
                <h3>5.2. Kullanıcı İçeriği</h3>
                <p>
                    Kullanıcılar, paylaştıkları içeriklerin telif haklarına sahiptir. 
                    Ancak platformda paylaşarak, içeriğin platformda gösterilmesine izin vermiş sayılırlar.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>6. Hizmet Kesintileri ve Değişiklikler</h2>
                <p>
                    Four Kampüs, platformu geliştirmek veya bakım yapmak için hizmeti geçici olarak 
                    kesebilir. Bu durumda kullanıcılar önceden bilgilendirilir.
                </p>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Uyarı:</strong> Mücbir sebep durumlarında (doğal afet, siber saldırı vb.) 
                        hizmet kesintilerinden sorumlu değiliz.
                    </p>
                </div>
            </div>
            
            <div class="legal-section">
                <h2>7. Hesap İptali ve Askıya Alma</h2>
                <h3>7.1. İhlal Durumunda</h3>
                <p>
                    Bu şartları ihlal eden kullanıcıların hesapları uyarı verilmeden askıya alınabilir 
                    veya kalıcı olarak kapatılabilir.
                </p>
                
                <h3>7.2. İptal Hakkı</h3>
                <p>
                    Kullanıcılar istedikleri zaman hesaplarını iptal edebilirler. İptal işlemi 
                    geri alınamaz ve tüm veriler silinir.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>8. Sorumluluk Sınırlaması</h2>
                <p>
                    Four Kampüs, kullanıcıların paylaştığı içeriklerden veya üçüncü taraf hizmetlerinden 
                    kaynaklanan sorunlardan sorumlu değildir. Platform "olduğu gibi" sunulur.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>9. Değişiklikler</h2>
                <p>
                    Bu kullanım şartları, yasal gereklilikler veya hizmet güncellemeleri nedeniyle 
                    değiştirilebilir. Önemli değişiklikler e-posta ile bildirilir ve güncel şartlar 
                    her zaman bu sayfada yayınlanır.
                </p>
            </div>
            
            <div class="legal-section">
                <h2>10. İletişim</h2>
                <p>
                    Kullanım şartları hakkında sorularınız için:
                </p>
                <div class="highlight-box">
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

