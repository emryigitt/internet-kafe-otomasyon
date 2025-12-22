<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';                          // ✅ Rol fonksiyonları
requireRol(['Yönetici', 'Teknisyen']);        // ✅ Sadece Yönetici + Teknisyen erişsin

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Yeni Bilgisayar Ekle";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Formdan gelen değerleri tutmak için
$bilgisayar_adi = $_POST['bilgisayar_adi'] ?? '';
$konum          = $_POST['konum'] ?? '';
$durum          = $_POST['durum'] ?? 'boş';
$ip_adresi      = $_POST['ip_adresi'] ?? '';
$aciklama       = $_POST['aciklama'] ?? '';

// ✅ varsayılan aktif = 1, POST gelince checkbox’a göre ayarla
$aktif_mi = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? (isset($_POST['aktif_mi']) ? 1 : 0)
    : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($bilgisayar_adi) === '') {
        $hata = "Bilgisayar adı boş olamaz.";
    } else {
        try {
            // Bilgisayar adı benzersiz mi kontrol et
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS s
                FROM bilgisayarlar
                WHERE bilgisayar_adi = :adi
            ");
            $stmt->execute([':adi' => $bilgisayar_adi]);
            $varMi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

            if ($varMi > 0) {
                $hata = "Bu bilgisayar adı zaten kayıtlı. Lütfen farklı bir ad kullanın.";
            } else {
                // Kayıt ekle
                $stmt = $conn->prepare("
                    INSERT INTO bilgisayarlar
                        (bilgisayar_adi, konum, durum, ip_adresi, aciklama, aktif_mi)
                    VALUES
                        (:bilgisayar_adi, :konum, :durum, :ip_adresi, :aciklama, :aktif_mi)
                ");

                $stmt->execute([
                    ':bilgisayar_adi' => $bilgisayar_adi,
                    ':konum'          => $konum !== '' ? $konum : null,
                    ':durum'          => $durum !== '' ? $durum : 'boş',
                    ':ip_adresi'      => $ip_adresi !== '' ? $ip_adresi : null,
                    ':aciklama'       => $aciklama !== '' ? $aciklama : null,
                    ':aktif_mi'       => $aktif_mi ? 1 : 0,
                ]);

                // ✅ Başarılıysa direkt dashboard’a dön (kartı anında gör)
                header("Location: dashboard.php?pc_ekleme=ok");
                exit;
            }
        } catch (PDOException $e) {
            $hata = "Kayıt eklenirken bir hata oluştu.";
            // $hata .= " Detay: " . $e->getMessage();
        }
    }
}
?>

<h2 class="mb-4">Yeni Bilgisayar Ekle</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Bilgisayar Adı (ör: PC-01)</label>
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
                        <option value="<?= $d; ?>" <?= $durum === $d ? 'selected' : ''; ?>>
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

            <button type="submit" class="btn btn-primary w-100">Bilgisayarı Kaydet</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>


