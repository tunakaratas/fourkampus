<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şikayet Formu - Four Kampüs</title>
    <meta name="description" content="Four Kampüs şikayet ve talep formu. Sorunlarınızı bize iletin.">
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .required {
            color: #ef4444;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .success-message {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            color: #065f46;
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
        
        .info-box {
            background: #f8fafc;
            border-left: 4px solid #6366f1;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .info-box p {
            margin: 0;
            color: #475569;
            font-size: 0.9rem;
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
            <h1><i class="fas fa-comment-dots"></i> Şikayet ve Talep Formu</h1>
            <p>Sorunlarınızı bize iletin, en kısa sürede çözüm bulalım</p>
        </div>
        
        <div class="legal-content">
            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i> 
                    <strong>Bilgi:</strong> Şikayet ve talepleriniz 2 iş günü içinde değerlendirilir ve size geri dönüş yapılır. 
                    Acil durumlar için telefon: +90 533 544 59 83
                </p>
            </div>
            
            <form id="complaintForm" method="POST" action="mailto:info@fourkampus.com.tr" enctype="text/plain">
                <div class="form-group">
                    <label for="name">Ad Soyad <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">E-posta Adresi <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                    <small>Size geri dönüş yapabilmemiz için e-posta adresiniz gereklidir.</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Telefon Numarası</label>
                    <input type="tel" id="phone" name="phone" placeholder="05XX XXX XX XX">
                </div>
                
                <div class="form-group">
                    <label for="category">Şikayet/Talep Kategorisi <span class="required">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">Seçiniz...</option>
                        <option value="product">Ürün/Hizmet Sorunu</option>
                        <option value="delivery">Teslimat Sorunu</option>
                        <option value="payment">Ödeme Sorunu</option>
                        <option value="refund">İade/İptal Talebi</option>
                        <option value="account">Hesap Sorunu</option>
                        <option value="technical">Teknik Sorun</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="order_id">Sipariş Numarası (varsa)</label>
                    <input type="text" id="order_id" name="order_id" placeholder="Sipariş numaranızı giriniz">
                    <small>Ürün/hizmet ile ilgili şikayetlerde sipariş numarası gereklidir.</small>
                </div>
                
                <div class="form-group">
                    <label for="subject">Konu <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" required placeholder="Şikayet/talep konusunu kısaca belirtiniz">
                </div>
                
                <div class="form-group">
                    <label for="message">Detaylı Açıklama <span class="required">*</span></label>
                    <textarea id="message" name="message" required placeholder="Şikayet veya talebinizi detaylı olarak açıklayınız..."></textarea>
                    <small>Lütfen sorununuzu mümkün olduğunca detaylı anlatınız. Bu, sorununuzun daha hızlı çözülmesine yardımcı olacaktır.</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" required>
                        <a href="privacy-policy.php" target="_blank" style="color: #6366f1; font-weight: 600;">Gizlilik Politikası</a>'nı okudum ve 
                        kişisel verilerimin işlenmesine onay veriyorum. <span class="required">*</span>
                    </label>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Gönder
                </button>
            </form>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; color: #0f172a;">Alternatif İletişim Yolları</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <p style="margin: 0; font-weight: 600; color: #0f172a;">E-posta</p>
                        <p style="margin: 0.25rem 0 0 0; color: #475569;">info@fourkampus.com.tr</p>
                    </div>
                    <div>
                        <p style="margin: 0; font-weight: 600; color: #0f172a;">Telefon</p>
                        <p style="margin: 0.25rem 0 0 0; color: #475569;">+90 533 544 59 83</p>
                    </div>
                    <div>
                        <p style="margin: 0; font-weight: 600; color: #0f172a;">Çalışma Saatleri</p>
                        <p style="margin: 0.25rem 0 0 0; color: #475569;">Pzt-Cuma: 09:00-18:00</p>
                    </div>
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
    
    <script>
        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const name = formData.get('name');
            const email = formData.get('email');
            const category = formData.get('category');
            const subject = formData.get('subject');
            const message = formData.get('message');
            const orderId = formData.get('order_id');
            const phone = formData.get('phone');
            
            const mailtoLink = `mailto:info@fourkampus.com.tr?subject=${encodeURIComponent('Şikayet/Talep: ' + subject)}&body=${encodeURIComponent(
                'Ad Soyad: ' + name + '\n' +
                'E-posta: ' + email + '\n' +
                'Telefon: ' + (phone || 'Belirtilmedi') + '\n' +
                'Kategori: ' + category + '\n' +
                (orderId ? 'Sipariş No: ' + orderId + '\n' : '') +
                '\n' +
                'Mesaj:\n' + message
            )}`;
            
            window.location.href = mailtoLink;
            
            // Başarı mesajı göster
            const successMsg = document.createElement('div');
            successMsg.className = 'success-message';
            successMsg.innerHTML = '<i class="fas fa-check-circle"></i> Formunuz hazırlandı. E-posta uygulamanız açılacaktır.';
            this.parentNode.insertBefore(successMsg, this);
            
            // Formu temizle
            setTimeout(() => {
                this.reset();
                successMsg.remove();
            }, 5000);
        });
    </script>
</body>
</html>

