<?php
require 'config.php';
require 'db.php';
session_start();

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

require 'yetki.php';

// ✅ Sadece Yönetici silebilsin
requireRol(['Yönetici']);

$bilgisayar_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return = $_GET['return'] ?? 'bilgisayarlar.php';

// güvenli return (basit whitelist)
$allowedReturns = ['bilgisayarlar.php', 'dashboard.php'];
if (!in_array($return, $allowedReturns, true)) {
    $return = 'bilgisayarlar.php';
}

if ($bilgisayar_id <= 0) {
    header("Location: {$return}?err=invalid_id");
    exit;
}

// ✅ Ayar: soft delete mi hard delete mi?
// true  => aktif_mi = 0 yapar (önerilen)
// false => satırı tamamen siler
$SOFT_DELETE = true;

try {
    $conn->beginTransaction();

    // 1) Bilgisayar var mı?
    $stmt = $conn->prepare("SELECT bilgisayar_id, bilgisayar_adi, aktif_mi FROM bilgisayarlar WHERE bilgisayar_id = ? LIMIT 1");
    $stmt->execute([$bilgisayar_id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pc) {
        $conn->rollBack();
        header("Location: {$return}?err=not_found");
        exit;
    }

    // 2) Açık oturum var mı? (varsa silme)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM oturumlar WHERE bilgisayar_id = ? AND durum = 'acik'");
    $stmt->execute([$bilgisayar_id]);
    $acikOturum = (int)$stmt->fetchColumn();

    if ($acikOturum > 0) {
        $conn->rollBack();
        header("Location: {$return}?err=active_session");
        exit;
    }

    // 3) Soft / Hard delete
    if ($SOFT_DELETE) {
        $stmt = $conn->prepare("UPDATE bilgisayarlar SET aktif_mi = 0 WHERE bilgisayar_id = ?");
        $stmt->execute([$bilgisayar_id]);
    } else {
        // Hard delete: ilişkili kayıtlar varsa FK yüzünden hata alabilirsin.
        // Eğer FK varsa önce bağlı kayıtları temizlemek gerekebilir.
        $stmt = $conn->prepare("DELETE FROM bilgisayarlar WHERE bilgisayar_id = ?");
        $stmt->execute([$bilgisayar_id]);
    }

    $conn->commit();

    header("Location: {$return}?ok=deleted");
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    header("Location: {$return}?err=db");
    exit;
}
