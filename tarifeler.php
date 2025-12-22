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

$pageTitle = "Tarifeler";
require 'includes/header.php';

// Tarifeleri çek
try {
    $stmt = $conn->query("
        SELECT 
            tarife_id,
            tarife_adi,
            saat_ucreti,
            aciklama,
            aktif_mi
        FROM tarifeler
        ORDER BY tarife_id ASC
    ");
    $tarifeler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tarifeler = [];
}
?>

<h2 class="mb-4">Tarife Listesi</h2>

<div class="mb-3 text-end">
    <a href="tarife_ekle.php" class="btn btn-primary btn-sm">Yeni Tarife Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">
        <?php if (empty($tarifeler)): ?>
            <p>Henüz kayıtlı tarife bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tarife Adı</th>
                            <th>Saatlik Ücret</th>
                            <th>Açıklama</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarifeler as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['tarife_id']); ?></td>
                                <td><?= htmlspecialchars($t['tarife_adi']); ?></td>
                                <td><?= number_format((float)$t['saat_ucreti'], 2, ',', '.'); ?> TL</td>
                                <td>
                                    <?php
                                    $acik = $t['aciklama'] ?? '';
                                    echo $acik !== ''
                                        ? htmlspecialchars($acik)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($t['aktif_mi'])): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tarife_duzenle.php?id=<?= $t['tarife_id']; ?>"
                                       class="btn btn-sm btn-warning">
                                        Düzenle
                                    </a>
                                    <a href="tarife_sil.php?id=<?= $t['tarife_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Bu tarifeyi pasif yapmak istediğinize emin misiniz?');">
                                        Pasif Yap
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
