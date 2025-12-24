import SwiftUI

// MARK: - Legal Document View
struct LegalDocumentView: View {
    let title: String
    let content: String
    @Environment(\.presentationMode) var presentationMode
    
    var body: some View {
        NavigationView {
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    Text(LocalizedStringKey(content))
                        .font(.system(size: 15))
                        .foregroundColor(.primary)
                        .padding(.bottom, 20)
                }
                .padding(20)
            }
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Kapat") {
                        presentationMode.wrappedValue.dismiss()
                    }
                }
            }
            .background(Color(UIColor.systemBackground))
        }
    }
}

// MARK: - Legal Document Model
struct LegalDocument: Identifiable {
    let id = UUID()
    let title: String
    let content: String
}

// MARK: - Legal Content
struct LegalContent {
    static let privacyPolicy = """
Gizlilik Politikası
Son Güncelleme: 24 Aralık 2025

Four Kampüs uygulaması, kişisel verilerinizi korumaya önem verir. Bu metin, verilerinizin nasıl işlendiğini açıklar.

1. Toplanan Bilgiler
Uygulamayı kullanırken aşağıdaki bilgileri toplayabiliriz:
*   Kimlik Bilgileri: Ad, soyad, öğrenci numarası, bölüm bilgisi (Üyeliğinizi doğrulamak için).
*   İletişim Bilgileri: E-posta adresi, telefon numarası.
*   Etkinlik Verileri: Katıldığınız etkinlikler, üye olduğunuz topluluklar.

2. İzinler ve Kullanım Amaçları
Uygulama, size daha iyi hizmet verebilmek için bazı cihaz izinlerine ihtiyaç duyar:
*   Kamera: Sadece etkinlik girişlerinde veya yoklamalarda QR kod okutmak için kullanılır. Görüntü kaydedilmez.
*   Takvim: Kayıt olduğunuz etkinlikleri cihaz takviminize eklemek isterseniz istenir.

3. Veri Güvenliği
Verileriniz güvenli sunucularda saklanır ve sadece hizmetin sağlanması amacıyla kullanılır. Üçüncü taraflarla (yasal zorunluluklar dışında) izinsiz paylaşılmaz.

4. Hesabınızı Silme
Uygulama içindeki "Ayarlar" menüsünden hesabınızı ve tüm verilerinizi kalıcı olarak silebilirsiniz.

İletişim
Verilerinizle ilgili sorularınız için: destek@fourkampus.com.tr
"""

    static let termsOfUse = """
Kullanım Koşulları
Son Güncelleme: 24 Aralık 2025

Four Kampüs, üniversite öğrencileri için geliştirilmiş bir topluluk ve etkinlik platformudur. Üye olarak aşağıdaki koşulları kabul etmiş sayılırsınız.

1. Üyelik ve Güvenlik
*   Üyelik bilgilerinizi doğru girmelisiniz.
*   Hesabınızın güvenliğinden siz sorumlusunuz. Şifrenizi kimseyle paylaşmayın.
*   Başkası adına hesap açamazsınız.

2. Topluluk ve Etkinlik Katılımı
*   Platform üzerinden üniversite topluluklarına katılabilir ve etkinliklerine kayıt olabilirsiniz.
*   Katıldığınız etkinliklerde ve topluluk içi iletişimde genel ahlak kurallarına ve saygı çerçevesine uymalısınız.
*   Diğer üyeleri rahatsız edici mesajlar göndermek yasaktır.

3. Market Alışverişleri
*   Platform üzerinden satın aldığınız ürünler, ilgili üniversite toplulukları tarafından satılmaktadır.
*   Four Kampüs güvenli ödeme altyapısı sağlar ancak ürünün tedariği ve teslimatı ilgili topluluğun sorumluluğundadır.
*   Satın alma işlemlerinde iade/doğişim talepleri için ilgili topluluk ile iletişime geçilmelidir.

4. Yasaklı Davranışlar
*   Platformu yasadışı amaçlar için kullanmak.
*   Sisteme veya diğer kullanıcılara zarar verecek eylemlerde bulunmak.
*   Telif hakkı içeren materyalleri izinsiz paylaşmak.

5. Yaptırımlar
Kurallara uymayan üyelerin hesapları geçici veya kalıcı olarak kapatılabilir.

İletişim
Destek ve sorularınız için: destek@fourkampus.com.tr
"""
}
