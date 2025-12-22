#!/usr/bin/env python3
"""
App Icon Oluşturucu
Mor arkaplan (#6366f1) ve beyaz logo ile 1024x1024 app icon oluşturur
"""

from PIL import Image, ImageEnhance, ImageOps
import os
import sys

def create_app_icon():
    # Dosya yolları
    script_dir = os.path.dirname(os.path.abspath(__file__))
    logo_path = os.path.join(script_dir, '..', '..', 'assets', 'images', 'logo_tr.png')
    output_dir = os.path.join(script_dir, 'unipanel', 'Assets.xcassets', 'AppIcon.appiconset')
    output_path = os.path.join(output_dir, 'AppIcon-1024.png')
    
    # Logo dosyasını kontrol et
    if not os.path.exists(logo_path):
        print(f"❌ Logo dosyası bulunamadı: {logo_path}")
        return False
    
    try:
        # Logo'yu yükle
        print(f"📷 Logo yükleniyor: {logo_path}")
        logo = Image.open(logo_path).convert("RGBA")
        
        # Logo'yu beyaz renge çevir
        print("🎨 Logo beyaz renge çevriliyor...")
        # Logo'yu grayscale'e çevir, sonra beyaz yap
        logo_gray = logo.convert("L")
        # Beyaz logo oluştur (alpha channel'ı koru)
        logo_white = Image.new("RGBA", logo.size, (255, 255, 255, 0))
        # Orijinal alpha channel'ı kullan, ama renkleri beyaz yap
        logo_white_data = []
        logo_data = logo.getdata()
        for pixel in logo_data:
            r, g, b, a = pixel
            # Eğer pixel şeffaf değilse, beyaz yap
            if a > 0:
                logo_white_data.append((255, 255, 255, a))
            else:
                logo_white_data.append((0, 0, 0, 0))
        logo_white.putdata(logo_white_data)
        
        # 1024x1024 mor arkaplan oluştur
        print("🟣 Mor arkaplan oluşturuluyor...")
        size = 1024
        background = Image.new("RGB", (size, size), (99, 102, 241))  # #6366f1 RGB değeri
        
        # Logo'yu ortala ve uygun boyuta getir
        # Logo'yu arkaplanın %70'ine sığdır
        logo_size = int(size * 0.7)
        logo_resized = logo_white.resize((logo_size, logo_size), Image.Resampling.LANCZOS)
        
        # Logo'yu ortala
        x_offset = (size - logo_size) // 2
        y_offset = (size - logo_size) // 2
        
        # Arkaplan'a logo'yu yapıştır
        print("🔧 Logo arkaplan'a ekleniyor...")
        background = background.convert("RGBA")
        background.paste(logo_resized, (x_offset, y_offset), logo_resized)
        background = background.convert("RGB")
        
        # Çıktı klasörünü oluştur
        os.makedirs(output_dir, exist_ok=True)
        
        # Ana app icon'u kaydet
        print(f"💾 App icon kaydediliyor: {output_path}")
        background.save(output_path, "PNG", quality=100)
        
        # Dark mode versiyonu (biraz daha koyu mor)
        dark_output = os.path.join(output_dir, 'AppIcon-1024-dark.png')
        dark_background = Image.new("RGB", (size, size), (76, 58, 237))  # #7c3aed (daha koyu mor)
        dark_background = dark_background.convert("RGBA")
        dark_background.paste(logo_resized, (x_offset, y_offset), logo_resized)
        dark_background = dark_background.convert("RGB")
        dark_background.save(dark_output, "PNG", quality=100)
        print(f"🌙 Dark mode icon kaydedildi: {dark_output}")
        
        # Tinted versiyonu (açık mor)
        tinted_output = os.path.join(output_dir, 'AppIcon-1024-tinted.png')
        tinted_background = Image.new("RGB", (size, size), (139, 92, 246))  # #8b5cf6 (açık mor)
        tinted_background = tinted_background.convert("RGBA")
        tinted_background.paste(logo_resized, (x_offset, y_offset), logo_resized)
        tinted_background = tinted_background.convert("RGB")
        tinted_background.save(tinted_output, "PNG", quality=100)
        print(f"✨ Tinted icon kaydedildi: {tinted_output}")
        
        print(f"\n✅ Tüm app icon'lar başarıyla oluşturuldu!")
        print(f"📁 Konum: {output_dir}")
        print(f"📏 Boyut: {size}x{size} piksel")
        print(f"🎨 Arkaplan: Mor (#6366f1, #7c3aed, #8b5cf6)")
        print(f"🎨 Logo: Beyaz")
        
        return True
        
    except Exception as e:
        print(f"❌ Hata: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    print("🚀 App Icon Oluşturucu Başlatılıyor...\n")
    success = create_app_icon()
    sys.exit(0 if success else 1)

