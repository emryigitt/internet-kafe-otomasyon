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

if (!isset($_GET['id'])) {
    header("Location: oturumlar.php");
    exit;
}

$oturum_id = (int)$_GET['id'];
$return = $_GET['return'] ?? 'oturumlar.php';

try {
    $conn->beginTransaction();

    // ✅ Oturumu + tarife + bilgisayar bilgilerini çek (kilitleyerek)
    // ✅ Süreyi MySQL NOW() ile dakika bazında hesapla (timezone sapmasını bitirir)
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            t.saat_ucreti,
            b.bilgisayar_id,
            b.bilgisayar_adi,
            GREATEST(1, TIMESTAMPDIFF(MINUTE, o.baslangic_zamani, NOW())) AS hesap_dakika
        FROM oturumlar o
        INNER JOIN tarifeler t ON o.tarife_id = t.tarife_id
        INNER JOIN bilgisayarlar b ON o.bilgisayar_id = b.bilgisayar_id
        WHERE o.oturum_id = :id AND o.durum = 'acik'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':id' => $oturum_id]);
    $oturum = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oturum) {
        $conn->rollBack();
        header("Location: " . $return);
        exit;
    }

    // ✅ Süre (dakika) ve ücret hesaplama
    $toplam_dakika = (int)($oturum['hesap_dakika'] ?? 1);
    $toplam_dakika = max(1, $toplam_dakika);

    $saat_ucreti     = (float)($oturum['saat_ucreti'] ?? 0);
    $acilis_ucreti   = 20.0;

    // ✅ dakika/60 olmalı
    $kullanim_ucreti = ($toplam_dakika / 60) * $saat_ucreti;
    $toplam_ucret    = round($acilis_ucreti + $kullanim_ucreti, 2);

    // ✅ Oturumu kapat
    $stmt = $conn->prepare("
        UPDATE oturumlar
        SET bitis_zamani = NOW(),
            toplam_sure = :toplam_sure,
            toplam_ucret = :toplam_ucret,
            durum = 'kapali'
        WHERE oturum_id = :id
    ");
    $stmt->execute([
        ':toplam_sure'  => $toplam_dakika,
        ':toplam_ucret' => $toplam_ucret,
        ':id'           => $oturum_id
    ]);

    // ✅ Bilgisayarı boş yap
    $stmt2 = $conn->prepare("
        UPDATE bilgisayarlar
        SET durum = 'boş'
        WHERE bilgisayar_id = :bilgisayar_id
    ");
    $stmt2->execute([':bilgisayar_id' => (int)$oturum['bilgisayar_id']]);

    // ✅ Ödeme kaydı oluştur
    $stmt3 = $conn->prepare("
        INSERT INTO odemeler (oturum_id, bilgisayar_id, musteri_id, kullanici_id, tutar, odeme_turu, aciklama)
        VALUES (:oturum_id, :bilgisayar_id, :musteri_id, :kullanici_id, :tutar, :odeme_turu, :aciklama)
    ");

    $pcAdi = $oturum['bilgisayar_adi'] ?? ('PC-' . (int)$oturum['bilgisayar_id']);
    $aciklama = $pcAdi . " Oturum ücreti (" . $toplam_dakika . " dk, 20 TL açılış dahil)";

    $stmt3->execute([
        ':oturum_id'     => $oturum_id,
        ':bilgisayar_id' => (int)$oturum['bilgisayar_id'],
        ':musteri_id'    => !empty($oturum['musteri_id']) ? (int)$oturum['musteri_id'] : null,
        ':kullanici_id'  => (int)$_SESSION['kullanici_id'],
        ':tutar'         => $toplam_ucret,
        ':odeme_turu'    => 'nakit',
        ':aciklama'      => $aciklama
    ]);

    $conn->commit();

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    // Geliştirme aşamasında görmek istersen:
    // die($e->getMessage());
}

header("Location: " . $return);
exit;






