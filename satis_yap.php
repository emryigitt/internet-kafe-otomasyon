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

$pageTitle = "Yeni Satış";
require 'includes/header.php';

$hata = null;
$basari = null;

// Aktif müşteriler
try {
    $stmt = $conn->query("
        SELECT musteri_id, ad, soyad
        FROM musteriler
        WHERE aktif_mi = 1
        ORDER BY ad, soyad
    ");
    $musteriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $musteriler = [];
}

// Satılabilir ürünler (aktif ve stok > 0)
try {
    $stmt = $conn->query("
        SELECT urun_id, urun_adi, birim_fiyati, stok_miktari
        FROM urunler
        WHERE aktif_mi = 1 AND stok_miktari > 0
        ORDER BY urun_adi
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $urunler = [];
}

if (!empty($_POST)) {
    $musteri_id = !empty($_POST['musteri_id']) ? (int)$_POST['musteri_id'] : null;
    $urun_id    = (int)$_POST['urun_id'];
    $adet       = (int)$_POST['adet'];

    if (!$urun_id || $adet <= 0) {
        $hata = "Ürün seçmeli ve adet pozitif olmalıdır.";
    } else {
        try {
            // Ürün bilgilerini tekrar kontrol et (fiyat + stok)
            $stmt = $conn->prepare("
                SELECT urun_adi, birim_fiyati, stok_miktari
                FROM urunler
                WHERE urun_id = :id AND aktif_mi = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $urun_id]);
            $urun = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$urun) {
                $hata = "Seçilen ürün bulunamadı veya pasif.";
            } elseif ((int)$urun['stok_miktari'] < $adet) {
                $hata = "Yeterli stok yok. Mevcut stok: " . (int)$urun['stok_miktari'];
            } else {
                $birim_fiyat  = (float)$urun['birim_fiyati'];
                $toplam_tutar = $birim_fiyat * $adet;

                // Satış, stok ve ödeme işlemlerini tek transaction içinde yapalım
                $conn->beginTransaction();

                // 1) satislar tablosuna ekle
                $stmt = $conn->prepare("
                    INSERT INTO satislar (urun_id, oturum_id, musteri_id, kullanici_id, adet, birim_fiyat, toplam_tutar)
                    VALUES (:urun_id, :oturum_id, :musteri_id, :kullanici_id, :adet, :birim_fiyat, :toplam_tutar)
                ");
                $stmt->execute([
                    ':urun_id'      => $urun_id,
                    ':oturum_id'    => null, // şimdilik oturuma bağlamıyoruz
                    ':musteri_id'   => $musteri_id,
                    ':kullanici_id' => $_SESSION['kullanici_id'],
                    ':adet'         => $adet,
                    ':birim_fiyat'  => $birim_fiyat,
                    ':toplam_tutar' => $toplam_tutar
                ]);

                // 2) stok düş (urunler tablosu) - güvenli
                $stmt = $conn->prepare("
                    UPDATE urunler
                    SET stok_miktari = stok_miktari - :adet,
                        guncellenme_tarihi = NOW()
                    WHERE urun_id = :urun_id
                      AND stok_miktari >= :adet
                ");
                $stmt->execute([
                    ':adet'    => $adet,
                    ':urun_id' => $urun_id
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Stok güncellenemedi (yeterli stok yok veya ürün bulunamadı).");
                }

                // 3) stok_hareketleri tablosuna kayıt (kullanici_id eklendi)
                $stmt = $conn->prepare("
                    INSERT INTO stok_hareketleri (urun_id, miktar, hareket_turu, aciklama, kullanici_id)
                    VALUES (:urun_id, :miktar, 'cikis', :aciklama, :kullanici_id)
                ");
                $aciklama_stok = "Satış: " . $urun['urun_adi'] . " x " . $adet;
                $stmt->execute([
                    ':urun_id'      => $urun_id,
                    ':miktar'       => $adet,
                    ':aciklama'     => $aciklama_stok,
                    ':kullanici_id' => $_SESSION['kullanici_id']
                ]);

                // 4) odemeler tablosuna kayıt
                $stmt = $conn->prepare("
                    INSERT INTO odemeler (musteri_id, kullanici_id, tutar, odeme_turu, aciklama)
                    VALUES (:musteri_id, :kullanici_id, :tutar, :odeme_turu, :aciklama)
                ");
                $aciklama_odeme = "Ürün satışı: " . $urun['urun_adi'] . " x " . $adet;
                $stmt->execute([
                    ':musteri_id'   => $musteri_id,
                    ':kullanici_id' => $_SESSION['kullanici_id'],
                    ':tutar'        => $toplam_tutar,
                    ':odeme_turu'   => 'nakit',
                    ':aciklama'     => $aciklama_odeme
                ]);

                $conn->commit();

                $basari = "Satış başarıyla gerçekleştirildi.";
            }

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $hata = "Satış sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<h2 class="mb-4">Yeni Satış Yap</h2>

<div class="card bg-dark text-light shadow-lg border-0">
    <div class="card-body">

        <?php if ($hata): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($basari); ?></div>
            <a href="satislar.php" class="btn btn-success btn-sm mt-2">Satış Listesine Dön</a>
        <?php elseif (empty($urunler)): ?>
            <div class="alert alert-warning">
                Satılabilir ürün bulunmuyor (stok = 0 veya tüm ürünler pasif).
            </div>
            <a href="urunler.php" class="btn btn-secondary btn-sm mt-2">Ürünleri Yönet</a>
        <?php else: ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Müşteri (opsiyonel)</label>
                <select name="musteri_id" class="form-select">
                    <option value="">Misafir (kayıtsız)</option>
                    <?php foreach ($musteriler as $m): ?>
                        <option value="<?= $m['musteri_id']; ?>">
                            <?= htmlspecialchars($m['ad'] . ' ' . $m['soyad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Ürün</label>
                <select name="urun_id" class="form-select" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ($urunler as $u): ?>
                        <option value="<?= $u['urun_id']; ?>">
                            <?= htmlspecialchars($u['urun_adi']); ?>
                            (Stok: <?= (int)$u['stok_miktari']; ?>,
                             Fiyat: <?= number_format($u['birim_fiyati'], 2, ',', '.'); ?> TL)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Adet</label>
                <input type="number" name="adet" class="form-control" value="1" min="1" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Satışı Tamamla</button>
        </form>

        <?php endif; ?>

    </div>
</div>

<?php require 'includes/footer.php'; ?>



