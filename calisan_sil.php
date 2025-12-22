<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici']);


if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: calisanlar.php");
    exit;
}

$kullanici_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        UPDATE kullanicilar
        SET aktif_mi = 0
        WHERE kullanici_id = :id
    ");
    $stmt->execute([':id' => $kullanici_id]);
} catch (PDOException $e) {
    // İstersen loglayabilirsin
}

header("Location: calisanlar.php");
exit;
