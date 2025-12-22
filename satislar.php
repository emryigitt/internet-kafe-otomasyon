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

$pageTitle = "Satışlar";
require 'includes/header.php';

try {
    $stmt = $conn->query("
        SELECT
            s.satis_id,
            s.adet,
            s.birim_fiyat,
            s.toplam_tutar,
            s.satis_tarihi,
            u.urun_adi,
            b.bilgisayar_adi
        FROM satislar s
        INNER JOIN urunler u ON s.urun_id = u.urun_id
        LEFT JOIN oturumlar o ON s.oturum_id = o.oturum_id
        LEFT JOIN bilgisayarlar b ON o.bilgisayar_id = b.bilgisayar_id
        ORDER BY s.satis_id ASC
    ");
    $satislar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $satislar = [];
}
?>

<h2 class="mb-4">Satışlar</h2>

<div class="mb-3 text-end">
    <a href="satis_yap.php" class="btn btn-primary btn-sm">Yeni Satış Yap</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if (empty($satislar)): ?>
            <p>Henüz satış kaydı bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bilgisayar</th>
                            <th>Ürün</th>
                            <th>Adet</th>
                            <th>Birim Fiyat</th>
                            <th>Toplam Tutar</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($satislar as $s): ?>
                            <tr>
                                <td><?= (int)$s['satis_id']; ?></td>
                                <td><?= htmlspecialchars($s['bilgisayar_adi'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($s['urun_adi']); ?></td>
                                <td><?= (int)$s['adet']; ?></td>
                                <td><?= number_format((float)$s['birim_fiyat'], 2, ',', '.'); ?> TL</td>
                                <td><?= number_format((float)$s['toplam_tutar'], 2, ',', '.'); ?> TL</td>
                                <td><?= htmlspecialchars($s['satis_tarihi']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>



