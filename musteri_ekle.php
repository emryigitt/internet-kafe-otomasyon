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

$pageTitle = "Yeni Müşteri Ekle";
require 'includes/header.php';

$hata = null;
$basari = null;

if (!empty($_POST)) {
    $ad      = trim($_POST['ad']);
    $soyad   = trim($_POST['soyad']);
    $telefon = trim($_POST['telefon']);
    $email   = trim($_POST['email']);

    if ($ad === "" || $soyad === "") {
        $hata = "Ad ve soyad boş bırakılamaz.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO musteriler (ad, soyad, telefon, email)
                VALUES (:ad, :soyad, :telefon, :email)
            ");
            $stmt->execute([
                ':ad'      => $ad,
                ':soyad'   => $soyad,
                ':telefon' => $telefon ?: null,
                ':email'   => $email ?: null
            ]);

            $basari = "Müşteri başarıyla eklendi.";
        } catch (PDOException $e) {
            $hata = "Kayıt sırasında bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Yeni Müşteri Ekle</h2>

<div class="card bg-dark text-light shadow-lg border-0">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="musteriler.php" class="btn btn-success btn-sm mt-2">Müşteri Listesine Dön</a>
        <?php else: ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Ad</label>
                <input type="text" name="ad" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Soyad</label>
                <input type="text" name="soyad" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Telefon</label>
                <input type="text" name="telefon" class="form-control" placeholder="(opsiyonel)">
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="(opsiyonel)">
            </div>

            <button type="submit" class="btn btn-primary w-100">Müşteri Ekle</button>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
