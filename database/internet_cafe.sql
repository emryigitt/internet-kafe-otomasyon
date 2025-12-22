create database internet_kafe;

-- ROLLER TABLOSU VE VERİLERİ
CREATE TABLE roller ( 
    rol_id INT AUTO_INCREMENT PRIMARY KEY,
    rol_adi VARCHAR(50) NOT NULL UNIQUE,
    aciklama VARCHAR(255),
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO roller (rol_adi, aciklama) VALUES
('Yönetici', 'Tüm sistem yetkilerine sahiptir. Kullanıcıları, fiyatları ve raporları yönetebilir.'),
('Personel', 'Müşteri oturumlarını, satışları ve ödemeleri yönetebilir.'),
('Teknisyen', 'Bilgisayar bakım ve teknik destek görevlerini yürütür.'),
('Kasiyer', 'Ürün satışlarını ve ödeme işlemlerini yapar.');

SELECT *FROM roller;

-- KULLANICILAR TABLOSU VE VERİLERİ

CREATE TABLE kullanicilar (
    kullanici_id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre VARCHAR(255) NOT NULL,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    rol_id INT NOT NULL,
    aktif_mi TINYINT NOT NULL DEFAULT 1,
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roller(rol_id)
);

INSERT INTO kullanicilar (kullanici_adi, sifre, ad, soyad, email, rol_id)
VALUES
('admin', '123456', 'Emir', 'Yigit', 'admin@internetcafe.com', 1),    
('personel01', '1234567', 'Ahmet', 'Demir', 'personel01@internetcafe.com', 2), 
('teknik01', '12345678', 'Mehmet', 'Acar', 'teknik01@internetcafe.com', 3),  
('kasa01', '123456789', 'Ayşe', 'Kara', 'kasa01@internetcafe.com', 4);       

SELECT * FROM kullanicilar;

-- MÜŞTERİLER TABLOSU VE VERİLERİ

CREATE TABLE musteriler (
    musteri_id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    telefon VARCHAR(15),
    email VARCHAR(100),
    aktif_mi TINYINT NOT NULL DEFAULT 1,  
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO musteriler (ad, soyad, telefon, email)
VALUES
('Ali', 'Kaya', '05320000001', 'ali.kaya@example.com'),
('Zeynep', 'Çelik', '05320000002', 'zeynep.celik@example.com'),
('Mert', 'Yılmaz', '05320000003', 'mert.yilmaz@example.com'),
('Ayşe', 'Demir', '05320000004', 'ayse.demir@example.com'),
('Burak', 'Acar', NULL, NULL);  

SELECT * FROM musteriler;

-- BİLGİSAYARLAR TABLOSU VE VERİLERİ

CREATE TABLE bilgisayarlar (
    bilgisayar_id INT AUTO_INCREMENT PRIMARY KEY,
    bilgisayar_adi VARCHAR(50) NOT NULL UNIQUE,   
    konum VARCHAR(100),                           
    durum VARCHAR(20) NOT NULL DEFAULT 'boş',     
    ip_adresi VARCHAR(45),                        
    aciklama VARCHAR(255),                       
    aktif_mi TINYINT NOT NULL DEFAULT 1,         
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO bilgisayarlar (bilgisayar_adi, konum, durum, ip_adresi, aciklama)
VALUES
('PC-01', 'Salon 1', 'boş', '192.168.1.10', 'Girişe yakın masa'),
('PC-02', 'Salon 1', 'boş', '192.168.1.11', 'Kulaklık değişmesi gerekiyor'),
('PC-03', 'Salon 2', 'bakımda', '192.168.1.12', 'Format atılacak'),
('PC-04', 'VIP Oda', 'boş', '192.168.1.13', 'Yüksek donanımlı oyun bilgisayarı');

SELECT *FROM bilgisayarlar;


-- TARİFELER TABLOSU VE VERİLERİ

CREATE TABLE tarifeler (
    tarife_id INT AUTO_INCREMENT PRIMARY KEY,
    tarife_adi VARCHAR(50) NOT NULL UNIQUE,        
    saat_ucreti DECIMAL(10,2) NOT NULL,        
    aciklama VARCHAR(255),                      
    aktif_mi TINYINT NOT NULL DEFAULT 1,          
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO tarifeler (tarife_adi, saat_ucreti, aciklama)
VALUES
('Standart', 40.00, 'Genel kullanım için standart saatlik ücret.'),
('Öğrenci', 30.00, 'Öğrenciler için indirimli saatlik ücret.'),
('Gece Tarifesi', 35.00, '22:00 - 06:00 saatleri arası gece indirimi.'),
('VIP Oda', 60.00, 'Yüksek donanımlı özel oda kullanımı.');

SELECT *FROM tarifeler;

-- OTURUMLAR TABLOSU VE VERİLERİ

CREATE TABLE oturumlar (
    oturum_id INT AUTO_INCREMENT PRIMARY KEY,
    musteri_id INT,                           
    bilgisayar_id INT NOT NULL,              
    tarife_id INT NOT NULL,                    
    baslangic_zamani DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bitis_zamani DATETIME,                     
    toplam_sure INT,                          
    toplam_ucret DECIMAL(10,2),           
    durum VARCHAR(20) NOT NULL DEFAULT 'acik', 
	olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(musteri_id),
    FOREIGN KEY (bilgisayar_id) REFERENCES bilgisayarlar(bilgisayar_id),
    FOREIGN KEY (tarife_id) REFERENCES tarifeler(tarife_id)
);

INSERT INTO oturumlar 
(musteri_id, bilgisayar_id, tarife_id, baslangic_zamani, bitis_zamani, toplam_sure, toplam_ucret, durum)
VALUES
(1, 1, 1, '2025-11-22 10:00:00', '2025-11-22 11:00:00', 60, 25.00, 'kapali'),
(2, 2, 2, '2025-11-22 11:15:00', '2025-11-22 12:00:00', 45, 13.50, 'kapali'),
(NULL, 1, 1, '2025-11-22 12:10:00', '2025-11-22 12:40:00', 30, 12.50, 'kapali'),
(3, 4, 4, '2025-11-22 13:00:00', '2025-11-22 14:30:00', 90, 60.00, 'kapali'),
(4, 2, 1, NOW(), NULL, NULL, NULL, 'acik');

SELECT *FROM oturumlar;

-- ÖDEMELER TABLOSU VE VERİLERİ

CREATE TABLE odemeler (
    odeme_id INT AUTO_INCREMENT PRIMARY KEY,
    
    oturum_id INT,               
    musteri_id INT,             
    kullanici_id INT NOT NULL,   
    
    odeme_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tutar DECIMAL(10,2) NOT NULL,
    
    odeme_turu VARCHAR(20) NOT NULL,  
    aciklama VARCHAR(255),          
    
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (oturum_id) REFERENCES oturumlar(oturum_id),
    FOREIGN KEY (musteri_id) REFERENCES musteriler(musteri_id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(kullanici_id)
);

INSERT INTO odemeler (oturum_id, musteri_id, kullanici_id, tutar, odeme_turu, aciklama)
VALUES

(1, 1, 1, 25.00, 'nakit', '1 saat bilgisayar kullanım ücreti'),

(2, 2, 1, 13.50, 'kart', 'Öğrenci indirimli oturum'),

(3, NULL, 1, 12.50, 'nakit', 'Misafir oturum ödemesi'),

(4, 3, 1, 60.00, 'nakit', 'VIP oda 90 dakikalık kullanım'),

(5, 4, 1, 45.00, 'kart', 'Oturum + ek hizmet'),

(NULL, NULL, 1, 20.00, 'nakit', '2 adet kola satışı');

ALTER TABLE odemeler
ADD COLUMN bilgisayar_id INT NULL AFTER oturum_id;

ALTER TABLE odemeler
ADD CONSTRAINT fk_odemeler_bilgisayar
FOREIGN KEY (bilgisayar_id) REFERENCES bilgisayarlar(bilgisayar_id);

UPDATE odemeler o
JOIN oturumlar ot ON ot.oturum_id = o.oturum_id
SET o.bilgisayar_id = ot.bilgisayar_id
WHERE o.oturum_id IS NOT NULL;

SELECT *FROM odemeler;

-- ÜRÜNLER TABLOSU VE VERİLERİ

CREATE TABLE urunler (
    urun_id INT AUTO_INCREMENT PRIMARY KEY,
    urun_adi VARCHAR(50) NOT NULL,       
    kategori VARCHAR(50),                
    birim_fiyati DECIMAL(10,2) NOT NULL,   
    stok_miktari INT NOT NULL DEFAULT 0,  
    aktif_mi TINYINT NOT NULL DEFAULT 1,  
    aciklama VARCHAR(255),              
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO urunler (urun_adi, kategori, birim_fiyati, stok_miktari, aciklama)
VALUES
('Kola 330ml', 'İçecek', 40.00, 50, 'Soğuk içecek'),
('Sıcak Çay', 'İçecek', 10.00, 100, 'Bardak çay'),
('Tost (Kaşarlı)', 'Yiyecek', 50.00, 25, 'Sıcak servis'),
('Cips (60g)', 'Atıştırmalık', 40.00, 40, 'Farklı marka olabilir'),
('Enerji İçeceği', 'İçecek', 35.00, 20, 'Buzlu servis'),
('Su 500ml', 'İçecek', 10.00, 100, 'Pet şişe su');

SELECT *FROM urunler;

-- SATIŞLAR TABLOSU VE VERİLERİ

CREATE TABLE satislar (
    satis_id INT AUTO_INCREMENT PRIMARY KEY,

    urun_id INT NOT NULL,             
    oturum_id INT,                         
    musteri_id INT,                       
    kullanici_id INT NOT NULL,            

    adet INT NOT NULL DEFAULT 1,            
    birim_fiyat DECIMAL(10,2) NOT NULL,  
    toplam_tutar DECIMAL(10,2) NOT NULL,    

    satis_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    aciklama VARCHAR(255),              
    
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (urun_id) REFERENCES urunler(urun_id),
    FOREIGN KEY (oturum_id) REFERENCES oturumlar(oturum_id),
    FOREIGN KEY (musteri_id) REFERENCES musteriler(musteri_id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(kullanici_id)
);

INSERT INTO satislar (urun_id, oturum_id, musteri_id, kullanici_id, adet, birim_fiyat, toplam_tutar, aciklama)
VALUES

(1, 1, 1, 1, 1, 40.00, 40.00, 'Oturum esnasında alındı'),

(3, 2, 2, 1, 1, 30.00, 30.00, 'Öğrenci tarife + tost'),

(2, NULL, NULL, 1, 2, 10.00, 20.00, 'Misafir müşteri çay servisi'),

(5, 4, 3, 1, 1, 35.00, 35.00, 'VIP odada alındı'),

(4, 5, 4, 1, 3, 18.00, 54.00, 'Açık oturumda atıştırmalık'),

(6, NULL, NULL, 1, 1, 8.00, 8.00, 'Kasadan su satışı');

SELECT *FROM satislar;

-- STOK HAREKETLERİ TABLOSU VE VERİLERİ

CREATE TABLE stok_hareketleri (
    hareket_id INT AUTO_INCREMENT PRIMARY KEY,

    urun_id INT NOT NULL,                    
    hareket_turu VARCHAR(20) NOT NULL,     
    miktar INT NOT NULL,                     
    onceki_stok INT,                     
    sonraki_stok INT,                     

    kullanici_id INT NOT NULL,           
    aciklama VARCHAR(255),               

    hareket_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (urun_id) REFERENCES urunler(urun_id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(kullanici_id)
);

INSERT INTO stok_hareketleri 
(urun_id, hareket_turu, miktar, onceki_stok, sonraki_stok, kullanici_id, aciklama)
VALUES
(1, 'giris', 30, 50, 80, 1, 'Tedarikçi: 30 adet kola girişi'),

(2, 'cikis', 2, 100, 98, 1, 'İki adet çay satışı'),

(4, 'cikis', 3, 40, 37, 1, 'Ayşe oturumunda 3 adet cips satışı'),

(5, 'giris', 20, 20, 40, 1, 'Enerji içeceği stok yenileme'),

(6, 'duzeltme', 1, 100, 99, 1, 'Ezilen su şişesi nedeniyle stok düzeltildi'),

(3, 'duzeltme', 5, 25, 30, 1, 'Stok sayımı sonrası ek 5 adet tost eklendi');

SELECT *FROM stok_hareketleri;

-- BİLGİSAYAR BAKIM KAYITLARI TABLOSU VE VERILERI

CREATE TABLE bilgisayar_bakim_kayitlari (
    bakim_id INT AUTO_INCREMENT PRIMARY KEY,

    bilgisayar_id INT NOT NULL,                 
    kullanici_id INT NOT NULL,                
    
    bakim_turu VARCHAR(50) NOT NULL,         
    aciklama VARCHAR(255),                  
    
    bakim_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,  
    
    olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncellenme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (bilgisayar_id) REFERENCES bilgisayarlar(bilgisayar_id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(kullanici_id)
);

INSERT INTO bilgisayar_bakim_kayitlari 
(bilgisayar_id, kullanici_id, bakim_turu, aciklama)
VALUES
(1, 3, 'Donanım Temizliği', 'Fan ve kasa içi toz temizliği yapıldı.'),
(2, 3, 'Yazılım Güncellemesi', 'Ekran kartı ve Windows güncellemeleri uygulandı.'),
(3, 3, 'Format', 'Windows yeniden kuruldu, tüm sürücüler yüklendi.'),
(4, 3, 'Donanım', 'İşlemci termal macunu yenilendi, sıcaklıklar düştü.'),
(2, 3, 'Ağ Sorunu', 'Ethernet bağlantı sorunu çözüldü, kablo değiştirildi.'),
(1, 3, 'Donanım Değişimi', '8GB RAM eklendi; toplam RAM 16GB oldu.');

SELECT *FROM bilgisayar_bakim_kayitlari;
























