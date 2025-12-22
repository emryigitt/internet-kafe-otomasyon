<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici', 'Teknisyen']);

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Yeni Bakım Kaydı";
require 'includes/header.php';

$hata   = null;
$basari = null;

// return parametresi (dashboard'dan geliyorsa geri dönmek için)
$return = $_POST['return'] ?? ($_GET['return'] ?? 'bakim_kayitlari.php');

// Aktif bilgisayarları çek (liste için)
try {
    $stmt = $conn->query("
        SELECT bilgisayar_id, bilgisayar_adi
        FROM bilgisayarlar
        WHERE aktif_mi = 1
        ORDER BY bilgisayar_adi
    ");
    $bilgisayarlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bilgisayarlar = [];
}

// Dashboard’dan GET ile bilgisayar_id gelirse seçili gelsin
$bilgisayar_id = $_POST['bilgisayar_id'] ?? ($_GET['bilgisayar_id'] ?? '');
$bakim_turu    = $_POST['bakim_turu']    ?? '';
$aciklama      = $_POST['aciklama']      ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($bilgisayar_id) || (int)$bilgisayar_id <= 0) {
        $hata = "Lütfen bir bilgisayar seçin.";
    } elseif (trim($bakim_turu) === '') {
        $hata = "Bakım türü boş olamaz.";
    } else {
        try {
            $conn->beginTransaction();

            // PC'yi kilitle
            $stmt = $conn->prepare("
                SELECT bilgisayar_id, bilgisayar_adi, durum, aktif_mi
                FROM bilgisayarlar
                WHERE bilgisayar_id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => (int)$bilgisayar_id]);
            $pc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pc) {
                $conn->rollBack();
                $hata = "Seçilen bilgisayar bulunamadı.";
            } elseif ((int)$pc['aktif_mi'] !== 1) {
                $conn->rollBack();
                $hata = "Seçilen bilgisayar aktif değil.";
            } else {

                // Açık oturum var mı?
                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS s
                    FROM oturumlar
                    WHERE bilgisayar_id = :id AND durum = 'acik'
                ");
                $stmt->execute([':id' => (int)$bilgisayar_id]);
                $acikOturumVar = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'] > 0;

                if ($acikOturumVar) {
                    $conn->rollBack();
                    $hata = "Bu bilgisayarda açık oturum varken bakım başlatılamaz.";
                } else {
                    // Bakım kaydı ekle
                    $stmt = $conn->prepare("
                        INSERT INTO bilgisayar_bakim_kayitlari
                            (bilgisayar_id, kullanici_id, bakim_turu, aciklama)
                        VALUES
                            (:bilgisayar_id, :kullanici_id, :bakim_turu, :aciklama)
                    ");

                    $stmt->execute([
                        ':bilgisayar_id' => (int)$bilgisayar_id,
                        ':kullanici_id'  => (int)$_SESSION['kullanici_id'],
                        ':bakim_turu'    => $bakim_turu,
                        ':aciklama'      => $aciklama !== '' ? $aciklama : null,
                    ]);

                    // ✅ PC durumunu bakımda yap
                    $stmt = $conn->prepare("
                        UPDATE bilgisayarlar
                        SET durum = 'bakımda'
                        WHERE bilgisayar_id = :id
                    ");
                    $stmt->execute([':id' => (int)$bilgisayar_id]);

                    $conn->commit();

                    // Dashboard’dan geldiyse direkt geri dön
                    if (!empty($return)) {
                        header("Location: " . $return);
                        exit;
                    }

                    $basari = "Bakım kaydı başarıyla eklendi.";

                    // Formu temiz göster
                    $bilgisayar_id = '';
                    $bakim_turu    = '';
                    $aciklama      = '';
                }
            }

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $hata = "Bakım kaydı eklenirken bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Yeni Bakım Kaydı Ekle</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="bakim_kayitlari.php" class="btn btn-success btn-sm mt-2">Bakım Kayıtlarına Dön</a>
        <?php endif; ?>

        <?php if (empty($bilgisayarlar)): ?>
            <div class="alert alert-warning mt-3">
                Aktif durumda bilgisayar bulunmuyor. Önce bilgisayar ekleyin veya aktif hale getirin.
            </div>
            <a href="bilgisayarlar.php" class="btn btn-secondary btn-sm mt-2">Bilgisayarları Yönet</a>
        <?php else: ?>

        <form method="POST" class="mt-3">
            <input type="hidden" name="return" value="<?= htmlspecialchars($return); ?>">

            <div class="mb-3">
                <label class="form-label">Bilgisayar</label>
                <select name="bilgisayar_id" class="form-select" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ($bilgisayarlar as $b): ?>
                        <option value="<?= (int)$b['bilgisayar_id']; ?>"
                            <?= ((string)$bilgisayar_id === (string)$b['bilgisayar_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($b['bilgisayar_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Bakım Türü</label>
                <select name="bakim_turu" class="form-select" required>
                    <option value="">Seçiniz...</option>
                    <?php
                    $turler = ['Donanım', 'Yazılım', 'Format', 'Temizlik', 'Diğer'];
                    foreach ($turler as $t):
                    ?>
                        <option value="<?= $t; ?>" <?= ($bakim_turu === $t) ? 'selected' : ''; ?>>
                            <?= $t; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama (opsiyonel)</label>
                <textarea name="aciklama"
                          class="form-control"
                          rows="3"
                          placeholder="Örn: Termal macun yenilendi, fan temizliği yapıldı vb."><?= htmlspecialchars($aciklama); ?></textarea>
            </div>

            <button type="submit" class="btn btn-warning w-100">Bakımı Başlat (PC bakımda olsun)</button>
        </form>

        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>



