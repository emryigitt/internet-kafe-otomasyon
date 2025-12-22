<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici', 'Personel', 'Kasiyer']);


if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: musteriler.php");
    exit;
}

$id = intval($_GET['id']);

try {
    $stmt = $conn->prepare("DELETE FROM musteriler WHERE musteri_id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    // İstersen hata loglayabilirsin
}

header("Location: musteriler.php");
exit;
