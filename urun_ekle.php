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

$pageTitle = "Yeni Ürün Ekle";
require 'includes/header.php';

$hata = null;
$basari = null;

if (!empty($_POST)) {
    $urun_adi     = trim($_POST['urun_adi']);
    $kategori     = trim($_POST['kategori']);
    $birim_fiyati = trim($_POST['birim_fiyati']);
    $stok_miktari = trim($_POST['stok_miktari']);
    $aciklama     = trim($_POST['aciklama']);
    $aktif_mi     = isset($_POST['aktif_mi']) ? 1 : 0;

    if ($urun_adi === "" || $birim_fiyati === "") {
        $hata = "Ürün adı ve birim fiyatı zorunludur.";
    } elseif (!is_numeric($birim_fiyati)) {
        $hata = "Birim fiyatı sayısal olmalıdır.";
    } elseif ($stok_miktari !== "" && !ctype_digit($stok_miktari)) {
        $hata = "Stok miktarı tam sayı olmalıdır.";
    } else {
        $stok_miktari = ($stok_miktari === "") ? 0 : (int)$stok_miktari;

        try {
            $stmt = $conn->prepare("
                INSERT INTO urunler (urun_adi, kategori, birim_fiyati, stok_miktari, aciklama, aktif_mi)
                VALUES (:urun_adi, :kategori, :birim_fiyati, :stok_miktari, :aciklama, :aktif_mi)
            ");
            $stmt->execute([
                ':urun_adi'     => $urun_adi,
                ':kategori'     => $kategori ?: null,
                ':birim_fiyati' => $birim_fiyati,
                ':stok_miktari' => $stok_miktari,
                ':aciklama'     => $aciklama ?: null,
                ':aktif_mi'     => $aktif_mi
            ]);

            $basari = "Ürün başarıyla eklendi.";
        } catch (PDOException $e) {
            $hata = "Kayıt sırasında bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Yeni Ürün Ekle</h2>

<div class="card bg-dark text-light shadow-lg border-0">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="urunler.php" class="btn btn-success btn-sm mt-2">Ürün Listesine Dön</a>
        <?php else: ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Ürün Adı</label>
                <input type="text" name="urun_adi" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Kategori</label>
                <input type="text" name="kategori" class="form-control" placeholder="İçecek, Yiyecek, Atıştırmalık... (opsiyonel)">
            </div>

            <div class="mb-3">
                <label class="form-label">Birim Fiyatı (TL)</label>
                <input type="text" name="birim_fiyati" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Stok Miktarı</label>
                <input type="number" name="stok_miktari" class="form-control" value="0" min="0">
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama" class="form-control" rows="2" placeholder="Opsiyonel"></textarea>
            </div>

            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" name="aktif_mi" id="aktif_mi" checked>
                <label class="form-check-label" for="aktif_mi">
                    Ürün aktif olsun
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Ürün Ekle</button>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
