<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici', 'Personel', 'Kasiyer']);


if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Ürünler";
require 'includes/header.php';

// Ürünleri çek
try {
    $stmt = $conn->query("
        SELECT urun_id, urun_adi, kategori, birim_fiyati, stok_miktari,
               aciklama, olusturulma_tarihi, guncellenme_tarihi, aktif_mi
        FROM urunler
        ORDER BY urun_id ASC
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $urunler = [];
}
?>

<h2 class="mb-4">Ürün Listesi</h2>

<div class="mb-3 text-end">
    <a href="urun_ekle.php" class="btn btn-primary btn-sm">Yeni Ürün Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">
        <?php if (empty($urunler)): ?>
            <p>Henüz kayıtlı ürün bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Birim Fiyatı</th>
                            <th>Stok</th>
                            <th>Açıklama</th>
                            <th>Oluşturulma</th>
                            <th>Güncellenme</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($urunler as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['urun_id']); ?></td>
                                <td><?= htmlspecialchars($u['urun_adi']); ?></td>
                                <td>
                                    <?php
                                    $kat = $u['kategori'] ?? '';
                                    echo $kat !== ''
                                        ? htmlspecialchars($kat)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $fiyat = (float)$u['birim_fiyati'];
                                    echo number_format($fiyat, 2, ',', '.') . ' TL';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($u['stok_miktari']); ?></td>
                                <td>
                                    <?php
                                    $acik = $u['aciklama'] ?? '';
                                    echo $acik !== ''
                                        ? htmlspecialchars($acik)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($u['olusturulma_tarihi']); ?></td>
                                <td><?= htmlspecialchars($u['guncellenme_tarihi']); ?></td>
                                <td>
                                    <?php if ($u['aktif_mi']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="urun_duzenle.php?id=<?= $u['urun_id']; ?>" class="btn btn-sm btn-warning">
                                        Düzenle
                                    </a>
                                    <a href="urun_sil.php?id=<?= $u['urun_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Bu ürünü silmek istediğinizden emin misiniz?');">
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

<?php
require 'includes/footer.php';
