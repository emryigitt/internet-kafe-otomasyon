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

if (!isset($_GET['id'])) {
    header("Location: bilgisayarlar.php");
    exit;
}

$bilgisayar_id = (int)$_GET['id'];

$pageTitle = "Bilgisayar Düzenle";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Önce mevcut bilgisayarı çek
try {
    $stmt = $conn->prepare("
        SELECT 
            bilgisayar_id,
            bilgisayar_adi,
            konum,
            durum,
            ip_adresi,
            aciklama,
            aktif_mi
        FROM bilgisayarlar
        WHERE bilgisayar_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $bilgisayar_id]);
    $bilgisayar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bilgisayar) {
        // Böyle bir kayıt yoksa listeye dön
        header("Location: bilgisayarlar.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: bilgisayarlar.php");
    exit;
}

// Form değerleri: ilk açılışta DB'den, POST sonrası kullanıcıdan
$bilgisayar_adi = $_POST['bilgisayar_adi'] ?? $bilgisayar['bilgisayar_adi'];
$konum          = $_POST['konum']          ?? ($bilgisayar['konum'] ?? '');
$durum          = $_POST['durum']          ?? ($bilgisayar['durum'] ?? 'boş');
$ip_adresi      = $_POST['ip_adresi']      ?? ($bilgisayar['ip_adresi'] ?? '');
$aciklama       = $_POST['aciklama']       ?? ($bilgisayar['aciklama'] ?? '');
$aktif_mi       = isset($_POST['aktif_mi'])
                    ? 1
                    : (int)$bilgisayar['aktif_mi'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($bilgisayar_adi) === '') {
        $hata = "Bilgisayar adı boş olamaz.";
    } else {
        try {
            // Aynı isimli başka bilgisayar var mı? (kendisi hariç)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS s
                FROM bilgisayarlar
                WHERE bilgisayar_adi = :adi
                  AND bilgisayar_id <> :id
            ");
            $stmt->execute([
                ':adi' => $bilgisayar_adi,
                ':id'  => $bilgisayar_id
            ]);
            $varMi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

            if ($varMi > 0) {
                $hata = "Bu bilgisayar adı başka bir kayıtta kullanılıyor. Lütfen farklı bir ad seçin.";
            } else {
                // Güncelle
                $stmt = $conn->prepare("
                    UPDATE bilgisayarlar
                    SET 
                        bilgisayar_adi   = :bilgisayar_adi,
                        konum            = :konum,
                        durum            = :durum,
                        ip_adresi        = :ip_adresi,
                        aciklama         = :aciklama,
                        aktif_mi         = :aktif_mi,
                        guncellenme_tarihi = NOW()
                    WHERE bilgisayar_id = :id
                ");

                $stmt->execute([
                    ':bilgisayar_adi' => $bilgisayar_adi,
                    ':konum'          => $konum !== '' ? $konum : null,
                    ':durum'          => $durum !== '' ? $durum : 'boş',
                    ':ip_adresi'      => $ip_adresi !== '' ? $ip_adresi : null,
                    ':aciklama'       => $aciklama !== '' ? $aciklama : null,
                    ':aktif_mi'       => $aktif_mi ? 1 : 0,
                    ':id'             => $bilgisayar_id
                ]);

                $basari = "Bilgisayar bilgileri başarıyla güncellendi.";
            }

        } catch (PDOException $e) {
            $hata = "Güncelleme sırasında bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Bilgisayar Düzenle</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="bilgisayarlar.php" class="btn btn-success btn-sm mt-2">Bilgisayar Listesine Dön</a>
        <?php endif; ?>

        <form method="POST" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Bilgisayar Adı</label>
                <input type="text"
                       name="bilgisayar_adi"
                       class="form-control"
                       required
                       value="<?= htmlspecialchars($bilgisayar_adi); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Konum</label>
                <input type="text"
                       name="konum"
                       class="form-control"
                       placeholder="Örn: Salon 1, Üst Kat"
                       value="<?= htmlspecialchars($konum); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Durum</label>
                <select name="durum" class="form-select">
                    <?php
                    $durumlar = ['boş', 'dolu', 'bakımda', 'kapalı'];
                    foreach ($durumlar as $d):
                    ?>
                        <option value="<?= $d; ?>" <?= ($durum === $d) ? 'selected' : ''; ?>>
                            <?= ucfirst($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">IP Adresi</label>
                <input type="text"
                       name="ip_adresi"
                       class="form-control"
                       placeholder="Örn: 192.168.1.10"
                       value="<?= htmlspecialchars($ip_adresi); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama"
                          class="form-control"
                          rows="3"
                          placeholder="Özel donanım, notlar vb."><?= htmlspecialchars($aciklama); ?></textarea>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input"
                       type="checkbox"
                       name="aktif_mi"
                       id="aktifMiCheck"
                       value="1"
                       <?= $aktif_mi ? 'checked' : ''; ?>>
                <label class="form-check-label" for="aktifMiCheck">
                    Aktif (kullanımda)
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
