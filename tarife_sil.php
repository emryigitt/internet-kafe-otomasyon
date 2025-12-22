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
    header("Location: tarifeler.php");
    exit;
}

$tarife_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        UPDATE tarifeler
        SET aktif_mi = 0
        WHERE tarife_id = :id
    ");
    $stmt->execute([':id' => $tarife_id]);
} catch (PDOException $e) {
    // istersen loglayabilirsin
}

header("Location: tarifeler.php");
exit;
