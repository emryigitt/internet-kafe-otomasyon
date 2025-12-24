# İnternet Kafe Otomasyon Sistemi
Ders: Veritabanı Yönetim Sistemleri / Proje

Bu proje, bir internet kafenin günlük operasyonlarını (bilgisayar yönetimi, oturum yönetimi, ürün satışı, ödeme kayıtları, bakım süreçleri ve kullanıcı/rol yetkilendirme) tek bir web paneli üzerinden yönetmek amacıyla geliştirilmiş bir **web tabanlı otomasyon sistemidir**.

## Özellikler
- **Bilgisayar Yönetimi:** Bilgisayar ekleme/düzenleme/silme, konuma göre listeleme (Salon 1, Salon 2, Üst Kat, VIP)
- **Oturum Yönetimi:** Oturum başlatma/bitirme, süre takibi, anlık ücret hesaplama (açılış ücreti + dakika bazlı)
- **Ürün Satışı:** Oturuma ürün ekleme, stok kontrolü
- **Ödeme Yönetimi:** Oturum + ürün toplamının hesaplanması ve ödeme kaydı
- **Bakım Modülü:** Bilgisayar bakımda işaretleme, bakım kayıtları ve bakım tamamlama
- **Yetkilendirme (RBAC):** Rol tabanlı erişim (Yönetici / Personel / Kasiyer / Teknisyen)

## Kullanılan Teknolojiler
- **Backend:** PHP (PDO)
- **Veritabanı:** MySQL
- **Frontend:** HTML, CSS, Bootstrap 5, JavaScript
- **Diğer:** Rol & yetki kontrolü (custom `yetki.php`)

## Kurulum (Local)
1. Bu repoyu indir:
   - GitHub → **Code** → **Download ZIP** (veya `git clone`)
2. Veritabanını oluştur:
   - `database/` klasöründeki `.sql` dosyasını phpMyAdmin / MySQL Workbench üzerinden içe aktar.
3. Ayar dosyaları:
   - `config.php` ve `db.php` dosyalarını kendi ortamına göre düzenle.
   - Güvenlik için örnek şablon dosyaları kullanılabilir:
     - `config.example.php`
     - `db.example.php`
4. Projeyi çalıştır:
   - XAMPP/WAMP ile `htdocs` altına koyup tarayıcıdan aç.

## Örnek Giriş Bilgileri
> (Varsa örnek admin kullanıcı bilgilerini yaz. Yoksa bu bölümü kaldır.)
- Kullanıcı: `admin`
- Şifre: `admin123`

## Klasör Yapısı (Özet)
- `includes/` → header/footer gibi ortak parçalar
- `database/` → SQL script
- `*.php` → modül sayfaları (dashboard, oturumlar, satışlar, ödemeler vb.)

## Notlar
- VIP Oda bilgisayarlarında, sadece adı içinde **“VIP”** geçen tarifeler listelenir.
- Oturum ücreti: **Açılış Ücreti + (Dakika/60 × Saatlik Ücret)** şeklinde hesaplanır.

## Lisans
Bu proje eğitim amaçlı geliştirilmiştir.

