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

$pageTitle = "Bakım Kayıtları";
require 'includes/header.php';

// Bakım kayıtlarını çek
try {
    $stmt = $conn->query("
        SELECT 
            bk.bakim_id,
            bk.bilgisayar_id,
            bk.kullanici_id,
            bk.bakim_turu,
            bk.aciklama,
            bk.bakim_tarihi,
            b.bilgisayar_adi,
            k.kullanici_adi AS kullanici_adi
        FROM bilgisayar_bakim_kayitlari bk
        LEFT JOIN bilgisayarlar b ON bk.bilgisayar_id = b.bilgisayar_id
        LEFT JOIN kullanicilar k ON bk.kullanici_id = k.kullanici_id
        ORDER BY bk.bakim_tarihi DESC, bk.bakim_id DESC
    ");
    $bakimlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bakimlar = [];
}
?>

<h2 class="mb-4">Bakım Kayıtları</h2>

<div class="mb-3 text-end">
    <a href="bakim_ekle.php" class="btn btn-primary btn-sm">Yeni Bakım Kaydı Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if (empty($bakimlar)): ?>
            <p>Henüz bakım kaydı bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bilgisayar</th>
                            <th>Bakım Türü</th>
                            <th>Açıklama</th>
                            <th>Bakım Tarihi</th>
                            <th>İşlemi Yapan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bakimlar as $bk): ?>
                            <tr>
                                <td><?= htmlspecialchars($bk['bakim_id']); ?></td>
                                <td>
                                    <?php if (!empty($bk['bilgisayar_adi'])): ?>
                                        <?= htmlspecialchars($bk['bilgisayar_adi']); ?>
                                    <?php else: ?>
                                        <span class="text-white">[Silinmiş / Bulunamadı]</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bk['bakim_turu']); ?></td>
                                <td>
                                    <?php
                                    $acik = $bk['aciklama'] ?? '';
                                    echo trim($acik) !== ''
                                        ? htmlspecialchars($acik)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($bk['bakim_tarihi']); ?></td>
                                <td>
                                    <?php if (!empty($bk['kullanici_adi'])): ?>
                                        <?= htmlspecialchars($bk['kullanici_adi']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sistem</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>



