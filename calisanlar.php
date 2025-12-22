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

$pageTitle = "Çalışanlar";
require 'includes/header.php';

// Çalışanları roller ile birlikte çek
try {
    $stmt = $conn->query("
        SELECT 
            k.kullanici_id,
            k.kullanici_adi,
            k.ad,
            k.soyad,
            k.email,
            k.aktif_mi,
            k.rol_id,
            r.rol_adi
        FROM kullanicilar k
        INNER JOIN roller r ON k.rol_id = r.rol_id
        ORDER BY k.kullanici_id ASC
    ");
    $calisanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $calisanlar = [];
}
?>

<h2 class="mb-4">Çalışanlar</h2>

<div class="mb-3 text-end">
    <a href="calisan_ekle.php" class="btn btn-primary btn-sm">Yeni Çalışan Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if (empty($calisanlar)): ?>
            <p>Henüz çalışan kaydı bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Rol</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calisanlar as $c): ?>
                            <tr>
                                <td><?= $c['kullanici_id']; ?></td>
                                <td><?= htmlspecialchars($c['kullanici_adi']); ?></td>
                                <td><?= htmlspecialchars($c['ad'] . ' ' . $c['soyad']); ?></td>
                                <td>
                                    <?php if (!empty($c['email'])): ?>
                                        <?= htmlspecialchars($c['email']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($c['rol_adi']); ?></td>
                                <td>
                                    <?php if ($c['aktif_mi']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="calisan_duzenle.php?id=<?= $c['kullanici_id']; ?>" class="btn btn-sm btn-warning">
                                        Düzenle
                                    </a>
                                    <a href="calisan_sil.php?id=<?= $c['kullanici_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Bu çalışanı pasif yapmak istediğinize emin misiniz?');">
                                        Pasifleştir
                                    </a>
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
