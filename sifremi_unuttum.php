<?php
require 'config.php';
require 'db.php';
session_start();

$pageTitle = "Şifremi Unuttum";
require 'includes/header.php';

$hata   = null;
$basari = null;

// Step 1 alanları
$kullanici_adi = $_POST['kullanici_adi'] ?? '';
$eposta        = $_POST['eposta'] ?? '';

// Step 2 alanları
$yeni_sifre        = $_POST['yeni_sifre'] ?? '';
$yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';

/**
 * Akış:
 * - step=1: kullanıcı adı + e-posta doğrula
 * - step=2: yeni şifreyi belirle
 */
$step = $_POST['step'] ?? '1';

// Step 2 için doğrulanan kullanıcı id'sini session'da tutacağız
if (!isset($_SESSION['sifre_sifirlama_user_id'])) {
    $_SESSION['sifre_sifirlama_user_id'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ STEP 1: Kullanıcı adı + e-posta doğrulama
    if ($step === '1') {

        if (trim($kullanici_adi) === '') {
            $hata = "Kullanıcı adı boş olamaz.";
        } elseif (trim($eposta) === '') {
            $hata = "E-posta adresi boş olamaz.";
        } else {
            try {
                // ✅ DB kolon adı: email
                $stmt = $conn->prepare("
                    SELECT kullanici_id
                    FROM kullanicilar
                    WHERE kullanici_adi = :kullanici_adi
                      AND email = :eposta
                      AND aktif_mi = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    ':kullanici_adi' => $kullanici_adi,
                    ':eposta'        => $eposta
                ]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $hata = "Kullanıcı adı ile e-posta eşleşmedi veya hesap aktif değil.";
                    $_SESSION['sifre_sifirlama_user_id'] = null;
                } else {
                    // Doğrulandı -> Step 2
                    $_SESSION['sifre_sifirlama_user_id'] = (int)$user['kullanici_id'];
                    $step = '2';

                    $yeni_sifre = '';
                    $yeni_sifre_tekrar = '';
                }

            } catch (PDOException $e) {
                $hata = "Doğrulama sırasında bir hata oluştu.";
            }
        }
    }

    // ✅ STEP 2: Yeni şifre belirleme
    if ($step === '2' && !$basari) {

        $userId = (int)($_SESSION['sifre_sifirlama_user_id'] ?? 0);

        if ($userId <= 0) {
            $hata = "Şifre yenileme oturumu geçersiz. Lütfen tekrar deneyin.";
            $step = '1';
        } else {
            if (trim($yeni_sifre) === '') {
                $hata = "Yeni şifre boş olamaz.";
            } elseif (strlen($yeni_sifre) < 6) {
                $hata = "Yeni şifre en az 6 karakter olmalıdır.";
            } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
                $hata = "Yeni şifre ve tekrarı aynı olmalıdır.";
            } else {
                try {
                    // ✅ Hash'leyerek kaydet
                    $hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("
                        UPDATE kullanicilar
                        SET sifre = :sifre
                        WHERE kullanici_id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':sifre' => $hash,
                        ':id'    => $userId
                    ]);

                    $basari = "Şifreniz başarıyla değiştirildi. Giriş yapabilirsiniz.";

                    // Session temizle
                    $_SESSION['sifre_sifirlama_user_id'] = null;

                    // Form temizle
                    $kullanici_adi = '';
                    $eposta = '';
                    $yeni_sifre = '';
                    $yeni_sifre_tekrar = '';
                    $step = '1';

                } catch (PDOException $e) {
                    $hata = "Şifre güncellenirken bir hata oluştu.";
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card bg-dark text-light shadow-lg border-0">
            <div class="card-body">

                <h3 class="card-title text-center mb-3">Şifremi Unuttum</h3>

                <?php if ($hata): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($hata); ?>
                    </div>
                <?php endif; ?>

                <?php if ($basari): ?>
                    <div class="alert alert-success py-2">
                        <?= htmlspecialchars($basari); ?>
                    </div>
                    <a href="login.php" class="btn btn-success btn-sm w-100 mt-2">
                        Giriş Yap
                    </a>

                <?php else: ?>

                    <?php if ($step === '1'): ?>
                        <!-- ✅ STEP 1 -->
                        <form method="POST">
                            <input type="hidden" name="step" value="1">

                            <div class="mb-3">
                                <label class="form-label">Kullanıcı Adı</label>
                                <input type="text"
                                       name="kullanici_adi"
                                       class="form-control"
                                       required
                                       value="<?= htmlspecialchars($kullanici_adi); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-posta</label>
                                <input type="email"
                                       name="eposta"
                                       class="form-control"
                                       required
                                       value="<?= htmlspecialchars($eposta); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Doğrula
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- ✅ STEP 2 -->
                        <form method="POST">
                            <input type="hidden" name="step" value="2">

                            <div class="mb-3">
                                <label class="form-label">Yeni Şifre</label>
                                <input type="password"
                                       name="yeni_sifre"
                                       class="form-control"
                                       required>
                                <div class="form-text text-light" style="opacity:.75;">
                                    En az 6 karakter önerilir.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password"
                                       name="yeni_sifre_tekrar"
                                       class="form-control"
                                       required>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                Şifreyi Onayla
                            </button>

                            <div class="text-center mt-3">
                                <a href="sifremi_unuttum.php" class="btn btn-outline-light btn-sm">
                                    Geri dön
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-outline-light btn-sm">
                            Giriş ekranına dön
                        </a>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>


