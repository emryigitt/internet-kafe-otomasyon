<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';                          // ✅ rol fonksiyonlarını al
requireRol(['Yönetici', 'Teknisyen']);        // ✅ sadece Yönetici + Teknisyen erişsin

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Bilgisayarlar";
require 'includes/header.php';

// Bilgisayarları çek
try {
    $stmt = $conn->query("
        SELECT 
            bilgisayar_id,
            bilgisayar_adi,
            konum,
            durum,
            ip_adresi,
            aciklama,
            aktif_mi,
            olusturulma_tarihi,
            guncellenme_tarihi
        FROM bilgisayarlar
        ORDER BY bilgisayar_id ASC
    ");
    $bilgisayarlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bilgisayarlar = [];
}
?>

<h2 class="mb-4">Bilgisayar Listesi</h2>

<div class="mb-3 text-end">
    <a href="bilgisayar_ekle.php" class="btn btn-primary btn-sm">Yeni Bilgisayar Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">
        <?php if (empty($bilgisayarlar)): ?>
            <p>Henüz kayıtlı bilgisayar bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bilgisayar Adı</th>
                            <th>Konum</th>
                            <th>Durum</th>
                            <th>IP Adresi</th>
                            <th>Açıklama</th>
                            <th>Oluşturulma</th>
                            <th>Güncellenme</th>
                            <th>Aktiflik</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bilgisayarlar as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['bilgisayar_id']); ?></td>
                                <td><?= htmlspecialchars($b['bilgisayar_adi']); ?></td>
                                <td>
                                    <?php
                                    $konum = $b['konum'] ?? '';
                                    echo $konum !== ''
                                        ? htmlspecialchars($konum)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $durum = $b['durum'] ?? 'boş';
                                    $badgeClass = 'bg-secondary';
                                    if ($durum === 'boş') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($durum === 'dolu') {
                                        $badgeClass = 'bg-info';
                                    } elseif ($durum === 'bakımda') {
                                        $badgeClass = 'bg-warning';
                                    } elseif ($durum === 'kapalı') {
                                        $badgeClass = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>">
                                        <?= htmlspecialchars(ucfirst($durum)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $ip = $b['ip_adresi'] ?? '';
                                    echo $ip !== ''
                                        ? htmlspecialchars($ip)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $acik = $b['aciklama'] ?? '';
                                    echo $acik !== ''
                                        ? htmlspecialchars($acik)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($b['olusturulma_tarihi'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($b['guncellenme_tarihi'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($b['aktif_mi'])): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="bilgisayar_duzenle.php?id=<?= $b['bilgisayar_id']; ?>"
                                       class="btn btn-sm btn-warning">
                                        Düzenle
                                    </a>
                                    <a href="bilgisayar_sil.php?id=<?= $b['bilgisayar_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Bu bilgisayarı silmek istediğinizden emin misiniz?');">
                                        Sil
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

