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

$pageTitle = "Ödemeler";
require 'includes/header.php';

try {
    $stmt = $conn->query("
        SELECT 
            o.odeme_id,
            o.tutar,
            o.odeme_turu,
            o.aciklama,
            o.odeme_tarihi,
            b.bilgisayar_adi
        FROM odemeler o
        LEFT JOIN bilgisayarlar b ON o.bilgisayar_id = b.bilgisayar_id
        ORDER BY o.odeme_id DESC
    ");
    $odemeler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $odemeler = [];
}
?>

<h2 class="mb-4">Ödemeler</h2>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">

        <?php if (empty($odemeler)): ?>
            <p>Henüz ödeme bulunmuyor.</p>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bilgisayar</th>
                        <th>Tutar</th>
                        <th>Tür</th>
                        <th>Açıklama</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($odemeler as $o): ?>
                        <tr>
                            <td><?= (int)$o['odeme_id']; ?></td>

                            <td>
                                <?= htmlspecialchars($o['bilgisayar_adi'] ?? '-'); ?>
                            </td>

                            <td><?= number_format((float)$o['tutar'], 2, ',', '.'); ?> TL</td>

                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($o['odeme_turu']); ?></span>
                            </td>

                            <td><?= htmlspecialchars($o['aciklama'] ?: "-"); ?></td>

                            <td><?= htmlspecialchars($o['odeme_tarihi']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>


