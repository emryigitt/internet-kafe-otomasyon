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
$pageTitle = "Müşteri Düzenle";

// Mevcut müşteri bilgilerini çek
try {
    $stmt = $conn->prepare("SELECT * FROM musteriler WHERE musteri_id = :id");
    $stmt->execute([':id' => $id]);
    $musteri = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$musteri) {
        header("Location: musteriler.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: musteriler.php");
    exit;
}

$hata = null;
$basari = null;

if (!empty($_POST)) {
    $ad      = trim($_POST['ad']);
    $soyad   = trim($_POST['soyad']);
    $telefon = trim($_POST['telefon']);
    $email   = trim($_POST['email']);
    $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;

    if ($ad === "" || $soyad === "") {
        $hata = "Ad ve soyad boş bırakılamaz.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE musteriler
                SET ad = :ad, soyad = :soyad, telefon = :telefon, email = :email, aktif_mi = :aktif_mi
                WHERE musteri_id = :id
            ");
            $stmt->execute([
                ':ad' => $ad,
                ':soyad' => $soyad,
                ':telefon' => $telefon ?: null,
                ':email' => $email ?: null,
                ':aktif_mi' => $aktif_mi,
                ':id' => $id
            ]);

            $basari = "Müşteri başarıyla güncellendi.";
        } catch (PDOException $e) {
            $hata = "Güncelleme sırasında hata oluştu.";
        }
    }
}

require 'includes/header.php';
?>

<h2 class="mb-4">Müşteri Düzenle (#<?= $id ?>)</h2>

<div class="card bg-dark text-light shadow-lg border-0">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger"><?= $hata ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success"><?= $basari ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Ad</label>
                <input type="text" name="ad" value="<?= htmlspecialchars($musteri['ad']); ?>" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Soyad</label>
                <input type="text" name="soyad" value="<?= htmlspecialchars($musteri['soyad']); ?>" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Telefon</label>
                <input type="text" name="telefon" value="<?= htmlspecialchars($musteri['telefon']); ?>" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($musteri['email']); ?>" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Durum</label><br>
                <input type="checkbox" name="aktif_mi" <?= $musteri['aktif_mi'] ? 'checked' : '' ?>> Aktif
            </div>

            <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>

        <a href="musteriler.php" class="btn btn-secondary btn-sm mt-3">Geri Dön</a>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
