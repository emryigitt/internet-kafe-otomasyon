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

$pageTitle = "Yeni Tarife Ekle";
require 'includes/header.php';

$hata   = null;
$basari = null;

$tarife_adi  = $_POST['tarife_adi']  ?? '';
$saat_ucreti = $_POST['saat_ucreti'] ?? '';
$aciklama    = $_POST['aciklama']    ?? '';
$aktif_mi    = isset($_POST['aktif_mi']) ? 1 : 1; // varsayılan aktif

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($tarife_adi) === '' || trim($saat_ucreti) === '') {
        $hata = "Tarife adı ve saatlik ücret zorunludur.";
    } elseif (!is_numeric(str_replace(',', '.', $saat_ucreti))) {
        $hata = "Saatlik ücret sayısal olmalıdır.";
    } else {
        // Virgüllü gelirse noktaya çevir
        $saat_ucreti_float = (float) str_replace(',', '.', $saat_ucreti);

        try {
            // Aynı isimde tarife var mı kontrol et (opsiyonel)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS s FROM tarifeler WHERE tarife_adi = :adi
            ");
            $stmt->execute([':adi' => $tarife_adi]);
            $varMi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

            if ($varMi > 0) {
                $hata = "Bu isimde bir tarife zaten mevcut.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO tarifeler (tarife_adi, saat_ucreti, aciklama, aktif_mi)
                    VALUES (:tarife_adi, :saat_ucreti, :aciklama, :aktif_mi)
                ");
                $stmt->execute([
                    ':tarife_adi'  => $tarife_adi,
                    ':saat_ucreti' => $saat_ucreti_float,
                    ':aciklama'    => $aciklama !== '' ? $aciklama : null,
                    ':aktif_mi'    => $aktif_mi ? 1 : 0,
                ]);

                $basari = "Tarife başarıyla eklendi.";
                // İstersen direkt listeye yönlendir:
                // header("Location: tarifeler.php?ekleme=ok");
                // exit;
            }

        } catch (PDOException $e) {
            $hata = "Tarife eklenirken bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Yeni Tarife Ekle</h2>

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
                       placeholder="Örn: 25 veya 25.50"
                       value="<?= htmlspecialchars($saat_ucreti); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama"
                          class="form-control"
                          rows="3"
                          placeholder="Örn: Öğrenci tarifesi, gece tarifesi vb."><?= htmlspecialchars($aciklama); ?></textarea>
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

            <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
