<?php
require 'config.php';
require 'db.php';
session_start();

$pageTitle = "Giriş Yap";
require 'includes/header.php';

$hata = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $sifre         = trim($_POST['sifre'] ?? '');

    if ($kullanici_adi === '' || $sifre === '') {
        $hata = "Kullanıcı adı ve şifre boş olamaz.";
    } else {
        try {
            // Şifreyi SQL'de kontrol etmiyoruz: kullanıcıyı çekiyoruz.
            $stmt = $conn->prepare("
                SELECT k.*, r.rol_adi
                FROM kullanicilar k
                INNER JOIN roller r ON k.rol_id = r.rol_id
                WHERE k.kullanici_adi = :kullanici_adi
                  AND k.aktif_mi = 1
                LIMIT 1
            ");
            $stmt->execute([':kullanici_adi' => $kullanici_adi]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $girisBasarili = false;

            if ($user && isset($user['sifre'])) {
                $dbSifre = (string)$user['sifre'];

                // 1) Hash'li şifre kontrolü
                if ($dbSifre !== '' && password_verify($sifre, $dbSifre)) {
                    $girisBasarili = true;

                    // Opsiyonel: rehash gerekiyorsa güncelle
                    if (password_needs_rehash($dbSifre, PASSWORD_DEFAULT)) {
                        $newHash = password_hash($sifre, PASSWORD_DEFAULT);
                        $up = $conn->prepare("
                            UPDATE kullanicilar
                            SET sifre = :sifre
                            WHERE kullanici_id = :id
                            LIMIT 1
                        ");
                        $up->execute([
                            ':sifre' => $newHash,
                            ':id'    => (int)$user['kullanici_id']
                        ]);
                    }
                }

                // 2) Eski sistem: düz metin şifre kontrolü (geri uyumluluk)
                if (!$girisBasarili && $dbSifre !== '' && hash_equals($dbSifre, $sifre)) {
                    $girisBasarili = true;

                    // Düz metin ise: ilk başarılı girişte hash'e yükselt
                    $newHash = password_hash($sifre, PASSWORD_DEFAULT);
                    $up = $conn->prepare("
                        UPDATE kullanicilar
                        SET sifre = :sifre
                        WHERE kullanici_id = :id
                        LIMIT 1
                    ");
                    $up->execute([
                        ':sifre' => $newHash,
                        ':id'    => (int)$user['kullanici_id']
                    ]);
                }
            }

            if ($girisBasarili) {
                $_SESSION['kullanici_id']  = $user['kullanici_id'];
                $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
                $_SESSION['rol_id']        = $user['rol_id'];
                $_SESSION['rol_adi']       = $user['rol_adi'];

                header("Location: dashboard.php");
                exit;
            } else {
                $hata = "Kullanıcı adı veya şifre hatalı ya da hesap pasif.";
            }

        } catch (PDOException $e) {
            $hata = "Giriş sırasında bir hata oluştu.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card bg-dark text-light shadow-lg border-0">
            <div class="card-body">
                <h3 class="card-title text-center mb-3">Giriş Yap</h3>

                <?php if ($hata): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($hata); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text"
                               name="kullanici_adi"
                               class="form-control"
                               required
                               value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="sifre" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                </form>

                <div class="text-center mt-3">
                    <a href="sifremi_unuttum.php" class="btn btn-outline-light btn-sm">
                        Şifremi Unuttum
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>




