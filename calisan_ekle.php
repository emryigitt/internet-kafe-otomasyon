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

$pageTitle = "Yeni Çalışan Ekle";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Roller listesi
try {
    $stmt = $conn->query("SELECT rol_id, rol_adi FROM roller ORDER BY rol_id ASC");
    $roller = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roller = [];
}

$kullanici_adi = $_POST['kullanici_adi'] ?? '';
$sifre         = $_POST['sifre'] ?? '';
$ad            = $_POST['ad'] ?? '';
$soyad         = $_POST['soyad'] ?? '';
$email         = $_POST['email'] ?? '';
$rol_id        = $_POST['rol_id'] ?? '';
$aktif_mi      = isset($_POST['aktif_mi']) ? 1 : 1; // varsayılan aktif

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($kullanici_adi) === '' || trim($sifre) === '' || trim($ad) === '' || trim($soyad) === '' || empty($rol_id)) {
        $hata = "Kullanıcı adı, şifre, ad, soyad ve rol zorunludur.";
    } else {
        try {
            // Kullanıcı adı benzersiz mi?
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS s
                FROM kullanicilar
                WHERE kullanici_adi = :kullanici_adi
            ");
            $stmt->execute([':kullanici_adi' => $kullanici_adi]);
            $varMi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

            if ($varMi > 0) {
                $hata = "Bu kullanıcı adı zaten kullanılıyor.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO kullanicilar
                        (kullanici_adi, sifre, ad, soyad, email, rol_id, aktif_mi)
                    VALUES
                        (:kullanici_adi, :sifre, :ad, :soyad, :email, :rol_id, :aktif_mi)
                ");
                $stmt->execute([
                    ':kullanici_adi' => $kullanici_adi,
                    ':sifre'         => $sifre, // İleride hash'leriz
                    ':ad'            => $ad,
                    ':soyad'         => $soyad,
                    ':email'         => $email !== '' ? $email : null,
                    ':rol_id'        => (int)$rol_id,
                    ':aktif_mi'      => $aktif_mi ? 1 : 0
                ]);

                $basari = "Çalışan başarıyla eklendi.";

                // Formu temizleyelim
                $kullanici_adi = $sifre = $ad = $soyad = $email = '';
                $rol_id        = '';
                $aktif_mi      = 1;
            }

        } catch (PDOException $e) {
            $hata = "Kayıt sırasında bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Yeni Çalışan Ekle</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="calisanlar.php" class="btn btn-success btn-sm mt-2">Çalışan Listesine Dön</a>
        <?php endif; ?>

        <?php if (empty($roller)): ?>
            <div class="alert alert-warning mt-3">
                Henüz rol tanımı bulunmuyor. Önce roller tablosunu kontrol edin.
            </div>
        <?php else: ?>

        <form method="POST" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" class="form-control"
                       value="<?= htmlspecialchars($kullanici_adi); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Şifre</label>
                <input type="text" name="sifre" class="form-control"
                       value="<?= htmlspecialchars($sifre); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Ad</label>
                <input type="text" name="ad" class="form-control"
                       value="<?= htmlspecialchars($ad); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Soyad</label>
                <input type="text" name="soyad" class="form-control"
                       value="<?= htmlspecialchars($soyad); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($email); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="rol_id" class="form-select" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ($roller as $r): ?>
                        <option value="<?= $r['rol_id']; ?>"
                            <?= ($rol_id == $r['rol_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($r['rol_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input"
                       type="checkbox"
                       name="aktif_mi"
                       id="aktifMiCheck"
                       value="1"
                       <?= $aktif_mi ? 'checked' : ''; ?>>
                <label class="form-check-label" for="aktifMiCheck">
                    Aktif (sisteme giriş yapabilsin)
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>

        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
