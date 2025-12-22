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

$pageTitle = "Tarife Düzenle";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Mevcut tarifeyi çek
try {
    $stmt = $conn->prepare("
        SELECT tarife_id, tarife_adi, saat_ucreti, aciklama, aktif_mi
        FROM tarifeler
        WHERE tarife_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $tarife_id]);
    $tarife = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarife) {
        header("Location: tarifeler.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: tarifeler.php");
    exit;
}

// Form değerleri (ilk açılışta DB'den, POST'ta kullanıcıdan)
$tarife_adi  = $_POST['tarife_adi']  ?? $tarife['tarife_adi'];
$saat_ucreti = $_POST['saat_ucreti'] ?? $tarife['saat_ucreti'];
$aciklama    = $_POST['aciklama']    ?? ($tarife['aciklama'] ?? '');
$aktif_mi    = isset($_POST['aktif_mi']) ? 1 : (int)$tarife['aktif_mi'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($tarife_adi) === '' || trim($saat_ucreti) === '') {
        $hata = "Tarife adı ve saatlik ücret zorunludur.";
    } elseif (!is_numeric(str_replace(',', '.', $saat_ucreti))) {
        $hata = "Saatlik ücret sayısal olmalıdır.";
    } else {
        $saat_ucreti_float = (float) str_replace(',', '.', $saat_ucreti);

        try {
            $stmt = $conn->prepare("
                UPDATE tarifeler
                SET tarife_adi = :tarife_adi,
                    saat_ucreti = :saat_ucreti,
                    aciklama = :aciklama,
                    aktif_mi = :aktif_mi
                WHERE tarife_id = :id
            ");
            $stmt->execute([
                ':tarife_adi'  => $tarife_adi,
                ':saat_ucreti' => $saat_ucreti_float,
                ':aciklama'    => $aciklama !== '' ? $aciklama : null,
                ':aktif_mi'    => $aktif_mi ? 1 : 0,
                ':id'          => $tarife_id
            ]);

            $basari = "Tarife başarıyla güncellendi.";

        } catch (PDOException $e) {
            $hata = "Tarife güncellenirken bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Tarife Düzenle</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="tarifeler.php" class="btn btn-success btn-sm mt-2">Tarife Listesine Dön</a>
        <?php endif; ?>

        <form method="POST" class="mt-3">
            <div class="mb-3">
                <label class="form-label">Tarife Adı</label>
                <input type="text"
                       name="tarife_adi"
                       class="form-control"
                       required
                       value="<?= htmlspecialchars($tarife_adi); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Saatlik Ücret (TL)</label>
                <input type="text"
                       name="saat_ucreti"
                       class="form-control"
                       required
                       value="<?= htmlspecialchars($saat_ucreti); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama"
                          class="form-control"
                          rows="3"><?= htmlspecialchars($aciklama); ?></textarea>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input"
                       type="checkbox"
                       name="aktif_mi"
                       id="aktifMiCheck"
                       value="1"
                       <?= $aktif_mi ? 'checked' : ''; ?>>
                <label class="form-check-label" for="aktifMiCheck">
                    Aktif
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Güncelle</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
