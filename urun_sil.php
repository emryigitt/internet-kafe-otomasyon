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
    header("Location: urunler.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("DELETE FROM urunler WHERE urun_id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    // İstersen log yazabilirsin
}

header("Location: urunler.php");
exit;
