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
$pageTitle = "Ürün Düzenle";

// Ürünü çek
try {
    $stmt = $conn->prepare("SELECT * FROM urunler WHERE urun_id = :id");
    $stmt->execute([':id' => $id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        header("Location: urunler.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: urunler.php");
    exit;
}

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
                UPDATE urunler
                SET urun_adi = :urun_adi,
                    kategori = :kategori,
                    birim_fiyati = :birim_fiyati,
                    stok_miktari = :stok_miktari,
                    aciklama = :aciklama,
                    aktif_mi = :aktif_mi
                WHERE urun_id = :id
            ");
            $stmt->execute([
                ':urun_adi'     => $urun_adi,
                ':kategori'     => $kategori ?: null,
                ':birim_fiyati' => $birim_fiyati,
                ':stok_miktari' => $stok_miktari,
                ':aciklama'     => $aciklama ?: null,
                ':aktif_mi'     => $aktif_mi,
                ':id'           => $id
            ]);

            $basari = "Ürün başarıyla güncellendi.";
            // Son görüntülenen değerleri güncelleyelim
            $urun['urun_adi']     = $urun_adi;
            $urun['kategori']     = $kategori;
            $urun['birim_fiyati'] = $birim_fiyati;
            $urun['stok_miktari'] = $stok_miktari;
            $urun['aciklama']     = $aciklama;
            $urun['aktif_mi']     = $aktif_mi;
        } catch (PDOException $e) {
            $hata = "Güncelleme sırasında bir hata oluştu.";
        }
    }
}

require 'includes/header.php';
?>

<h2 class="mb-4">Ürün Düzenle (#<?= $id ?>)</h2>

<div class="card bg-dark text-light shadow-lg border-0">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Ürün Adı</label>
                <input type="text" name="urun_adi" class="form-control"
                       value="<?= htmlspecialchars($urun['urun_adi']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Kategori</label>
                <input type="text" name="kategori" class="form-control"
                       value="<?= htmlspecialchars($urun['kategori'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Birim Fiyatı (TL)</label>
                <input type="text" name="birim_fiyati" class="form-control"
                       value="<?= htmlspecialchars($urun['birim_fiyati']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Stok Miktarı</label>
                <input type="number" name="stok_miktari" class="form-control"
                       value="<?= htmlspecialchars($urun['stok_miktari']); ?>" min="0">
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama" class="form-control" rows="2"><?= htmlspecialchars($urun['aciklama'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" name="aktif_mi" id="aktif_mi"
                       <?= $urun['aktif_mi'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="aktif_mi">
                    Ürün aktif
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>

        <a href="urunler.php" class="btn btn-secondary btn-sm mt-3">Ürün Listesine Dön</a>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
