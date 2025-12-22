<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici', 'Personel', 'Kasiyer', 'Teknisyen']);

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$oturum_id     = isset($_POST['oturum_id']) ? (int)$_POST['oturum_id'] : 0;
$bilgisayar_id = isset($_POST['bilgisayar_id']) ? (int)$_POST['bilgisayar_id'] : 0;
$urun_id       = isset($_POST['urun_id']) ? (int)$_POST['urun_id'] : 0;
$adet          = isset($_POST['adet']) ? (int)$_POST['adet'] : 0;

if ($oturum_id <= 0 || $bilgisayar_id <= 0 || $urun_id <= 0 || $adet <= 0) {
    header("Location: dashboard.php");
    exit;
}

try {
    // 1) Oturum gerçekten açık mı ve bu bilgisayara mı ait?
    $stmt = $conn->prepare("
        SELECT o.oturum_id, o.musteri_id, o.durum, b.bilgisayar_adi
        FROM oturumlar o
        INNER JOIN bilgisayarlar b ON b.bilgisayar_id = o.bilgisayar_id
        WHERE o.oturum_id = :oturum_id
          AND o.bilgisayar_id = :bilgisayar_id
        LIMIT 1
    ");
    $stmt->execute([
        ':oturum_id' => $oturum_id,
        ':bilgisayar_id' => $bilgisayar_id
    ]);
    $oturum = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oturum || $oturum['durum'] !== 'acik') {
        header("Location: dashboard.php");
        exit;
    }

    // 2) Ürün kontrolü (aktif + stok yeterli)
    $stmt = $conn->prepare("
        SELECT urun_adi, birim_fiyati, stok_miktari
        FROM urunler
        WHERE urun_id = :id AND aktif_mi = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $urun_id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        throw new Exception("Ürün bulunamadı/pasif.");
    }
    if ((int)$urun['stok_miktari'] < $adet) {
        throw new Exception("Yeterli stok yok. Mevcut stok: " . (int)$urun['stok_miktari']);
    }

    $birim_fiyat  = (float)$urun['birim_fiyati'];
    $toplam_tutar = $birim_fiyat * $adet;

    $conn->beginTransaction();

    // 3) satislar'a yaz (oturuma bağlı!)
    $stmt = $conn->prepare("
        INSERT INTO satislar (urun_id, oturum_id, musteri_id, kullanici_id, adet, birim_fiyat, toplam_tutar)
        VALUES (:urun_id, :oturum_id, :musteri_id, :kullanici_id, :adet, :birim_fiyat, :toplam_tutar)
    ");
    $stmt->execute([
        ':urun_id'      => $urun_id,
        ':oturum_id'    => $oturum_id,
        ':musteri_id'   => !empty($oturum['musteri_id']) ? (int)$oturum['musteri_id'] : null,
        ':kullanici_id' => (int)$_SESSION['kullanici_id'],
        ':adet'         => $adet,
        ':birim_fiyat'  => $birim_fiyat,
        ':toplam_tutar' => $toplam_tutar
    ]);

    // 4) stok düş
    $stmt = $conn->prepare("
        UPDATE urunler
        SET stok_miktari = stok_miktari - :adet,
            guncellenme_tarihi = NOW()
        WHERE urun_id = :urun_id
          AND stok_miktari >= :adet
    ");
    $stmt->execute([
        ':adet' => $adet,
        ':urun_id' => $urun_id
    ]);
    if ($stmt->rowCount() === 0) {
        throw new Exception("Stok güncellenemedi.");
    }

    // 5) stok_hareketleri
    $stmt = $conn->prepare("
        INSERT INTO stok_hareketleri (urun_id, miktar, hareket_turu, aciklama, kullanici_id)
        VALUES (:urun_id, :miktar, 'cikis', :aciklama, :kullanici_id)
    ");
    $stmt->execute([
        ':urun_id'      => $urun_id,
        ':miktar'       => $adet,
        ':aciklama'     => "Oturum Satışı: " . $urun['urun_adi'] . " x " . $adet,
        ':kullanici_id' => (int)$_SESSION['kullanici_id']
    ]);

    // 6) odemeler’e ayrı satır at: "PC-01 Ürün satışı: KOLA x 1"
    $pcAdi = $oturum['bilgisayar_adi'];
    $stmt = $conn->prepare("
        INSERT INTO odemeler (musteri_id, bilgisayar_id, oturum_id, kullanici_id, tutar, odeme_turu, aciklama)
        VALUES (:musteri_id, :bilgisayar_id, :oturum_id, :kullanici_id, :tutar, :odeme_turu, :aciklama)
    ");
    $stmt->execute([
        ':musteri_id'   => !empty($oturum['musteri_id']) ? (int)$oturum['musteri_id'] : null,
        ':bilgisayar_id'=> $bilgisayar_id,
        ':oturum_id'    => $oturum_id,
        ':kullanici_id' => (int)$_SESSION['kullanici_id'],
        ':tutar'        => $toplam_tutar,
        ':odeme_turu'   => 'nakit',
        ':aciklama'     => $pcAdi . " Ürün: " . $urun['urun_adi'] . " x " . $adet
    ]);

    $conn->commit();

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    // İstersen session flash mesaj yaparız; şimdilik dashboard'a dön
}

header("Location: dashboard.php");
exit;
