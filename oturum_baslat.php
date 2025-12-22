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

$musteri_id    = !empty($_POST['musteri_id']) ? (int)$_POST['musteri_id'] : null;
$bilgisayar_id = (int)($_POST['bilgisayar_id'] ?? 0);
$tarife_id     = (int)($_POST['tarife_id'] ?? 0);

if (!$bilgisayar_id || !$tarife_id) {
    header("Location: dashboard.php");
    exit;
}

// ✅ konum normalize
function normalizeKonumLocal(?string $k): string {
    $k = trim((string)$k);
    if ($k === '') return '';
    $k = mb_strtolower($k, 'UTF-8');
    $k = str_replace(['ı','İ','ö','Ö','ü','Ü','ş','Ş','ğ','Ğ','ç','Ç'], ['i','i','o','o','u','u','s','s','g','g','c','c'], $k);
    $k = preg_replace('/\s+/', ' ', $k);
    return $k;
}

// ✅ tarife adı vip mi?
function isVipTarifeAdiLocal(?string $ad): bool {
    $ad = mb_strtolower(trim((string)$ad), 'UTF-8');
    $ad = str_replace(['İ','I','ı'], ['i','i','i'], $ad);
    return strpos($ad, 'vip') !== false;
}

try {
    // bilgisayar durumu + konum + aktif mi
    $stmt = $conn->prepare("SELECT durum, aktif_mi, konum FROM bilgisayarlar WHERE bilgisayar_id = :id LIMIT 1");
    $stmt->execute([':id' => $bilgisayar_id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pc || (int)$pc['aktif_mi'] !== 1 || ($pc['durum'] ?? '') !== 'boş') {
        header("Location: dashboard.php");
        exit;
    }

    $pcKonumNorm = normalizeKonumLocal($pc['konum'] ?? '');
    $isVipRoom = ($pcKonumNorm === 'vip oda' || $pcKonumNorm === 'vip');

    // tarife gerçekten var mı + adı
    $stmt = $conn->prepare("SELECT tarife_id, tarife_adi FROM tarifeler WHERE tarife_id = :tid AND aktif_mi = 1 LIMIT 1");
    $stmt->execute([':tid' => $tarife_id]);
    $tarife = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarife) {
        header("Location: dashboard.php");
        exit;
    }

    $isVipTarife = isVipTarifeAdiLocal($tarife['tarife_adi'] ?? '');

    // ✅ VIP Oda -> sadece VIP tarife
    if ($isVipRoom && !$isVipTarife) {
        header("Location: dashboard.php");
        exit;
    }

    // ✅ VIP değil -> VIP tarife seçilemesin
    if (!$isVipRoom && $isVipTarife) {
        header("Location: dashboard.php");
        exit;
    }

    $conn->beginTransaction();

    // oturum aç
    $stmt = $conn->prepare("
        INSERT INTO oturumlar (musteri_id, bilgisayar_id, tarife_id, baslangic_zamani, durum)
        VALUES (:musteri_id, :bilgisayar_id, :tarife_id, NOW(), 'acik')
    ");
    $stmt->execute([
        ':musteri_id'    => $musteri_id,
        ':bilgisayar_id' => $bilgisayar_id,
        ':tarife_id'     => $tarife_id
    ]);

    // pc dolu yap
    $stmt = $conn->prepare("
        UPDATE bilgisayarlar
        SET durum = 'dolu'
        WHERE bilgisayar_id = :bilgisayar_id
    ");
    $stmt->execute([':bilgisayar_id' => $bilgisayar_id]);

    $conn->commit();

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
}

header("Location: dashboard.php");
exit;


