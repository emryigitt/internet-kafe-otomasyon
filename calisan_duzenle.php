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
    header("Location: calisanlar.php");
    exit;
}

$kullanici_id = (int)$_GET['id'];

$pageTitle = "Çalışan Düzenle";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Roller
try {
    $stmt = $conn->query("SELECT rol_id, rol_adi FROM roller ORDER BY rol_id ASC");
    $roller = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roller = [];
}

// Mevcut çalışan
try {
    $stmt = $conn->prepare("
        SELECT kullanici_id, kullanici_adi, sifre, ad, soyad, email, rol_id, aktif_mi
        FROM kullanicilar
        WHERE kullanici_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $kullanici_id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        header("Location: calisanlar.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: calisanlar.php");
    exit;
}

// Form değerleri
$kullanici_adi = $_POST['kullanici_adi'] ?? $c['kullanici_adi'];
$ad            = $_POST['ad'] ?? $c['ad'];
$soyad         = $_POST['soyad'] ?? $c['soyad'];
$email         = $_POST['email'] ?? $c['email'];
$rol_id        = $_POST['rol_id'] ?? $c['rol_id'];
$aktif_mi      = isset($_POST['aktif_mi']) ? 1 : (int)$c['aktif_mi'];
$yeni_sifre    = $_POST['yeni_sifre'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (trim($kullanici_adi) === '' || trim($ad) === '' || trim($soyad) === '' || empty($rol_id)) {
        $hata = "Kullanıcı adı, ad, soyad ve rol zorunludur.";
    } else {
        try {
            // Aynı kullanıcı adı başka birinde var mı? (kendisi hariç)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS s
                FROM kullanicilar
                WHERE kullanici_adi = :kullanici_adi
                  AND kullanici_id <> :id
            ");
            $stmt->execute([
                ':kullanici_adi' => $kullanici_adi,
                ':id'            => $kullanici_id
            ]);
            $varMi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

            if ($varMi > 0) {
                $hata = "Bu kullanıcı adı başka bir çalışan tarafından kullanılıyor.";
            } else {
                // Şifre değişecek mi?
                if (trim($yeni_sifre) !== '') {
                    $sqlSifre = ", sifre = :sifre";
                } else {
                    $sqlSifre = "";
                }

                $sql = "
                    UPDATE kullanicilar
                    SET 
                        kullanici_adi = :kullanici_adi,
                        ad            = :ad,
                        soyad         = :soyad,
                        email         = :email,
                        rol_id        = :rol_id,
                        aktif_mi      = :aktif_mi
                        $sqlSifre
                    WHERE kullanici_id = :id
                ";

                $stmt = $conn->prepare($sql);

                $params = [
                    ':kullanici_adi' => $kullanici_adi,
                    ':ad'            => $ad,
                    ':soyad'         => $soyad,
                    ':email'         => $email !== '' ? $email : null,
                    ':rol_id'        => (int)$rol_id,
                    ':aktif_mi'      => $aktif_mi ? 1 : 0,
                    ':id'            => $kullanici_id
                ];

                if (trim($yeni_sifre) !== '') {
                    $params[':sifre'] = $yeni_sifre; // ileride hashlenir
                }

                $stmt->execute($params);

                $basari = "Çalışan bilgileri başarıyla güncellendi.";
            }

        } catch (PDOException $e) {
            $hata = "Güncelleme sırasında bir hata oluştu.";
        }
    }
}
?>

<h2 class="mb-4">Çalışan Düzenle</h2>

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
                Rol listesi bulunamadı. Lütfen roller tablosunu kontrol edin.
            </div>
        <?php else: ?>

        <form method="POST" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" class="form-control"
                       value="<?= htmlspecialchars($kullanici_adi); ?>" required>
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
                <label class="form-label">E-posta (opsiyonel)</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($email); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="rol_id" class="form-select" required>
                    <?php foreach ($roller as $r): ?>
                        <option value="<?= $r['rol_id']; ?>"
                            <?= ($rol_id == $r['rol_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($r['rol_adi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Yeni Şifre (boş bırakırsan değişmez)</label>
                <input type="text" name="yeni_sifre" class="form-control"
                       value="">
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

        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
