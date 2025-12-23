<?php
require 'config.php';
require 'db.php';
session_start();

require 'yetki.php';
requireRol(['Yönetici', 'Personel', 'Kasiyer', 'Teknisyen']);

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Oturumlar";
require 'includes/header.php';

// Flash mesajlar
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Oturumları çek
try {
    $stmt = $conn->query("
        SELECT 
            o.oturum_id,
            o.musteri_id,
            o.bilgisayar_id,
            o.tarife_id,
            o.baslangic_zamani,
            o.bitis_zamani,
            o.toplam_sure,
            o.toplam_ucret,
            o.durum,
            m.ad AS musteri_ad,
            m.soyad AS musteri_soyad,
            b.bilgisayar_adi,
            t.tarife_adi
        FROM oturumlar o
        LEFT JOIN musteriler m ON o.musteri_id = m.musteri_id
        INNER JOIN bilgisayarlar b ON o.bilgisayar_id = b.bilgisayar_id
        INNER JOIN tarifeler t ON o.tarife_id = t.tarife_id
        ORDER BY 
            CASE WHEN o.durum = 'acik' THEN 0 ELSE 1 END,
            o.baslangic_zamani DESC
    ");
    $oturumlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $oturumlar = [];
}

// Modal için datalar
function normalizeKonumLocal(?string $k): string {
    $k = trim((string)$k);
    if ($k === '') return '';
    $k = mb_strtolower($k, 'UTF-8');
    $k = str_replace(['ı','İ','ö','Ö','ü','Ü','ş','Ş','ğ','Ğ','ç','Ç'], ['i','i','o','o','u','u','s','s','g','g','c','c'], $k);
    $k = preg_replace('/\s+/', ' ', $k);
    return $k;
}

function isVipTarifeAdiLocal(?string $ad): bool {
    $ad = mb_strtolower(trim((string)$ad), 'UTF-8');
    $ad = str_replace(['İ','I','ı'], ['i','i','i'], $ad);
    return strpos($ad, 'vip') !== false;
}

try {
    $musteriler = $conn->query("SELECT musteri_id, ad, soyad FROM musteriler ORDER BY ad ASC, soyad ASC")->fetchAll(PDO::FETCH_ASSOC);

    $bosBilgisayarlar = $conn->query("
        SELECT bilgisayar_id, bilgisayar_adi, konum
        FROM bilgisayarlar
        WHERE aktif_mi = 1 AND durum = 'boş'
        ORDER BY bilgisayar_adi ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $aktifTarifeler = $conn->query("
        SELECT tarife_id, tarife_adi
        FROM tarifeler
        WHERE aktif_mi = 1
        ORDER BY tarife_adi ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $musteriler = [];
    $bosBilgisayarlar = [];
    $aktifTarifeler = [];
}
?>

<h2 class="mb-4">Oturumlar</h2>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="mb-3 text-end">
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#yeniOturumModal">
        Yeni Oturum Başlat
    </button>
</div>

<div class="card bg-dark text-light border-0 shadow-lg">
    <div class="card-body">
        <?php if (empty($oturumlar)): ?>
            <p>Henüz kayıtlı oturum bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Müşteri</th>
                            <th>Bilgisayar</th>
                            <th>Tarife</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Süre (dk)</th>
                            <th>Ücret (TL)</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($oturumlar as $o): ?>
                            <tr>
                                <td><?= htmlspecialchars($o['oturum_id']); ?></td>
                                <td>
                                    <?php
                                    if ($o['musteri_id']) {
                                        echo htmlspecialchars(trim(($o['musteri_ad'] ?? '') . ' ' . ($o['musteri_soyad'] ?? '')));
                                    } else {
                                        echo '<span class="text-light">Misafir</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($o['bilgisayar_adi']); ?></td>
                                <td><?= htmlspecialchars($o['tarife_adi']); ?></td>
                                <td><?= htmlspecialchars($o['baslangic_zamani']); ?></td>
                                <td>
                                    <?= !empty($o['bitis_zamani'])
                                        ? htmlspecialchars($o['bitis_zamani'])
                                        : '<span class="text-light">Devam ediyor</span>'; ?>
                                </td>
                                <td><?= $o['toplam_sure'] !== null ? htmlspecialchars($o['toplam_sure']) : '-'; ?></td>
                                <td>
                                    <?php
                                    if ($o['toplam_ucret'] !== null) {
                                        echo number_format((float)$o['toplam_ucret'], 2, ',', '.');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($o['durum'] === 'acik'): ?>
                                        <span class="badge bg-success">Açık</span>
                                    <?php elseif ($o['durum'] === 'kapali'): ?>
                                        <span class="badge bg-secondary">Kapalı</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars($o['durum']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($o['durum'] === 'acik'): ?>
                                        <a href="oturum_bitir.php?id=<?= (int)$o['oturum_id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Bu oturumu bitirmek istediğinizden emin misiniz?');">
                                            Oturumu Bitir
                                        </a>
                                    <?php else: ?>
                                        <span class="text-light">-</span>
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

<!-- ✅ YENİ OTURUM MODAL -->
<div class="modal fade" id="yeniOturumModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-light border-0">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Yeni Oturum Başlat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>

      <form action="oturum_baslat.php" method="POST">
        <div class="modal-body">

          <input type="hidden" name="return_to" value="oturumlar.php">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Müşteri (Opsiyonel)</label>
              <select name="musteri_id" class="form-select">
                <option value="">Misafir (Kayıtsız)</option>
                <?php foreach ($musteriler as $m): ?>
                  <option value="<?= (int)$m['musteri_id'] ?>">
                    <?= htmlspecialchars($m['ad'] . ' ' . $m['soyad']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Bilgisayar</label>
              <select id="bilgisayarSelect" name="bilgisayar_id" class="form-select" required>
                <option value="" selected disabled>Seçiniz</option>
                <?php foreach ($bosBilgisayarlar as $b): ?>
                  <?php
                    $kn = normalizeKonumLocal($b['konum'] ?? '');
                    $isVipPc = ($kn === 'vip oda' || $kn === 'vip') ? 1 : 0;
                  ?>
                  <option value="<?= (int)$b['bilgisayar_id'] ?>" data-isvip="<?= $isVipPc ?>">
                    <?= htmlspecialchars($b['bilgisayar_adi']) ?><?= $isVipPc ? ' (VIP)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-secondary">Sadece boş bilgisayarlar listelenir.</small>
            </div>

            <div class="col-md-4">
              <label class="form-label">Tarife</label>
              <select id="tarifeSelect" name="tarife_id" class="form-select" required>
                <option value="" selected disabled>Seçiniz</option>
                <?php foreach ($aktifTarifeler as $t): ?>
                  <?php $isVipT = isVipTarifeAdiLocal($t['tarife_adi'] ?? '') ? 1 : 0; ?>
                  <option value="<?= (int)$t['tarife_id'] ?>" data-isvip="<?= $isVipT ?>">
                    <?= htmlspecialchars($t['tarife_adi']) ?><?= $isVipT ? ' (VIP)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-secondary">VIP PC’de VIP tarife, normal PC’de normal tarife seçilir.</small>
            </div>
          </div>

        </div>

        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-success">Oturumu Başlat</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const pcSel = document.getElementById('bilgisayarSelect');
  const tarSel = document.getElementById('tarifeSelect');
  if (!pcSel || !tarSel) return;

  function filterTarifeler() {
    const pcOpt = pcSel.options[pcSel.selectedIndex];
    if (!pcOpt) return;

    const isVipPc = pcOpt.getAttribute('data-isvip') === '1';

    for (const opt of tarSel.options) {
      if (!opt.value) continue;
      const isVipT = opt.getAttribute('data-isvip') === '1';
      opt.hidden = (isVipPc ? !isVipT : isVipT);
    }

    const cur = tarSel.options[tarSel.selectedIndex];
    if (cur && cur.value) {
      const curVip = cur.getAttribute('data-isvip') === '1';
      if (isVipPc ? !curVip : curVip) {
        tarSel.value = "";
      }
    }
  }

  pcSel.addEventListener('change', filterTarifeler);
})();
</script>

<?php require 'includes/footer.php'; ?>

