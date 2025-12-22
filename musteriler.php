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

$pageTitle = "Müşteriler";
require 'includes/header.php';

// Müşterileri çek
try {
    $stmt = $conn->query("
        SELECT musteri_id, ad, soyad, telefon, email, kayit_tarihi, guncellenme_tarihi, aktif_mi
        FROM musteriler
        ORDER BY musteri_id ASC
    ");
    $musteriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $musteriler = [];
}
?>

<h2 class="mb-4">Müşteri Listesi</h2>

<div class="mb-3 text-end">
    <a href="musteri_ekle.php" class="btn btn-primary btn-sm">Yeni Müşteri Ekle</a>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">
        <?php if (empty($musteriler)): ?>
            <p>Henüz kayıtlı müşteri bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad Soyad</th>
                            <th>Telefon</th>
                            <th>Email</th>
                            <th>Kayıt Tarihi</th>
                            <th>Güncellenme Tarihi</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($musteriler as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['musteri_id']); ?></td>
                                <td><?= htmlspecialchars($m['ad'] . ' ' . $m['soyad']); ?></td>
                                <td>
                                    <?php
                                    $tel = $m['telefon'] ?? '';
                                    echo $tel !== ''
                                        ? htmlspecialchars($tel)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $mail = $m['email'] ?? '';
                                    echo $mail !== ''
                                        ? htmlspecialchars($mail)
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($m['kayit_tarihi']); ?></td>

                                <td><?= htmlspecialchars($m['guncellenme_tarihi']); ?></td>

                                <td>
                                    <?php if ($m['aktif_mi']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="musteri_duzenle.php?id=<?= $m['musteri_id']; ?>" class="btn btn-sm btn-warning">
                                        Düzenle
                                    </a>
                                    <a href="musteri_sil.php?id=<?= $m['musteri_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Bu müşteriyi silmek istediğinizden emin misiniz?');">
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


