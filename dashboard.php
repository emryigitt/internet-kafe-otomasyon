<?php
require 'config.php';
require 'db.php';
session_start();

if (empty($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

require 'yetki.php';

$rolAdi  = $_SESSION['rol_adi'] ?? 'Personel';
$isAdmin = ($rolAdi === 'Yönetici');

// Yetki kısayolları
$canPcView   = can('pc.view');
$canSession  = can('session.manage');
$canSale     = can('sale.create');
$canMaint    = can('maintenance.manage');
$canPcStatus = can('pc.status.view');

// Sayfa başlığı
$pageTitle = "Yönetim Paneli";
require 'includes/header.php';

// Başlangıç değerleri
$acikOturum       = 0;
$aktifMusteri     = 0;
$aktifUrun        = 0;
$toplamCiro       = 0.0;
$bugunCiro        = 0.0;
$toplamSatisAdedi = 0;
$bakimSayisi      = 0;
$oturumGeliri     = 0.0;
$urunGeliri       = 0.0;

$toplamBilgisayar = 0;
$aktifBilgisayar  = 0;

$pcListe = [];
$musteriler = [];
$tarifeler  = [];
$urunlerModal = [];

// ✅ Konuma göre gruplama
$pcGruplu = [
    'Salon 1' => [],
    'Salon 2' => [],
    'Üst Kat' => [],
    'VIP Oda' => []
];

// ✅ Konum normalizasyonu
function normalizeKonum(?string $k): string {
    $k = trim((string)$k);
    if ($k === '') return '';
    $k = mb_strtolower($k, 'UTF-8');
    $k = str_replace(['ı','İ','ö','Ö','ü','Ü','ş','Ş','ğ','Ğ','ç','Ç'], ['i','i','o','o','u','u','s','s','g','g','c','c'], $k);
    $k = preg_replace('/\s+/', ' ', $k);
    return $k;
}

// ✅ Tarife adından "vip" yakalamak için
function isVipTarifeAdi(?string $ad): bool {
    $ad = mb_strtolower(trim((string)$ad), 'UTF-8');
    $ad = str_replace(['İ','I','ı'], ['i','i','i'], $ad);
    return strpos($ad, 'vip') !== false;
}

try {
    // İstatistikleri sadece Yönetici görsün
    if ($isAdmin) {
        $stmt = $conn->query("SELECT COUNT(*) AS s FROM oturumlar WHERE durum = 'acik'");
        $acikOturum = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

        $stmt = $conn->query("SELECT COUNT(*) AS s FROM musteriler WHERE aktif_mi = 1");
        $aktifMusteri = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

        $stmt = $conn->query("SELECT COUNT(*) AS s FROM urunler WHERE aktif_mi = 1");
        $aktifUrun = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

        $stmt = $conn->query("SELECT COALESCE(SUM(tutar),0) AS toplam FROM odemeler");
        $toplamCiro = (float)$stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

        $stmt = $conn->query("
            SELECT COALESCE(SUM(tutar),0) AS toplam
            FROM odemeler
            WHERE DATE(odeme_tarihi) = CURDATE()
        ");
        $bugunCiro = (float)$stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

        $stmt = $conn->query("SELECT COALESCE(SUM(adet),0) AS toplam_adet FROM satislar");
        $toplamSatisAdedi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['toplam_adet'];

        $stmt = $conn->query("SELECT COUNT(*) AS s FROM bilgisayar_bakim_kayitlari");
        $bakimSayisi = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

        $stmt = $conn->query("
            SELECT COALESCE(SUM(tutar),0) AS toplam
            FROM odemeler
            WHERE aciklama LIKE '% Oturum ücreti (%'
               OR aciklama LIKE 'Oturum ücreti (%'
        ");
        $oturumGeliri = (float)$stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

        $stmt = $conn->query("
            SELECT COALESCE(SUM(tutar),0) AS toplam
            FROM odemeler
            WHERE aciklama LIKE '% Ürün:%'
               OR aciklama LIKE 'Ürün satışı:%'
        ");
        $urunGeliri = (float)$stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

        $stmt = $conn->query("SELECT COUNT(*) AS s FROM bilgisayarlar");
        $toplamBilgisayar = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];

        $stmt = $conn->query("SELECT COUNT(*) AS s FROM bilgisayarlar WHERE aktif_mi = 1");
        $aktifBilgisayar = (int)$stmt->fetch(PDO::FETCH_ASSOC)['s'];
    }

    // PC listesi: pc.view olan herkes görsün
    if ($canPcView) {
        $stmt = $conn->query("
            SELECT 
                b.bilgisayar_id,
                b.bilgisayar_adi,
                b.konum,
                b.durum,
                b.aktif_mi,

                o.oturum_id,
                o.musteri_id,
                o.tarife_id,
                o.baslangic_zamani,

                m.ad AS musteri_ad,
                m.soyad AS musteri_soyad,

                t.tarife_adi,
                t.saat_ucreti,

                COALESCE((
                    SELECT SUM(s.toplam_tutar)
                    FROM satislar s
                    WHERE s.oturum_id = o.oturum_id
                ), 0) AS urun_toplam

            FROM bilgisayarlar b
            LEFT JOIN oturumlar o 
                ON o.bilgisayar_id = b.bilgisayar_id
               AND o.durum = 'acik'
            LEFT JOIN musteriler m 
                ON m.musteri_id = o.musteri_id
            LEFT JOIN tarifeler t
                ON t.tarife_id = o.tarife_id
            WHERE b.aktif_mi = 1
            ORDER BY 
                CAST(SUBSTRING(b.bilgisayar_adi, LOCATE('-', b.bilgisayar_adi) + 1) AS UNSIGNED) ASC,
                b.bilgisayar_adi ASC
        ");
        $pcListe = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pcListe as $pc) {
            $nk = normalizeKonum($pc['konum'] ?? '');

            if ($nk === 'salon 1' || $nk === 'salon1') $pcGruplu['Salon 1'][] = $pc;
            elseif ($nk === 'salon 2' || $nk === 'salon2') $pcGruplu['Salon 2'][] = $pc;
            elseif ($nk === 'ust kat' || $nk === 'ustkat' || $nk === 'ust') $pcGruplu['Üst Kat'][] = $pc;
            elseif ($nk === 'vip oda' || $nk === 'vip') $pcGruplu['VIP Oda'][] = $pc;
        }
    }

    // Oturum başlatma gerekiyorsa müşteri+tarife listele
    if ($canSession) {
        $stmt = $conn->query("
            SELECT musteri_id, ad, soyad
            FROM musteriler
            WHERE aktif_mi = 1
            ORDER BY ad, soyad
        ");
        $musteriler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("
            SELECT tarife_id, tarife_adi, saat_ucreti
            FROM tarifeler
            WHERE aktif_mi = 1
            ORDER BY tarife_adi
        ");
        $tarifeler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ürün satışı gerekiyorsa ürünleri getir
    if ($canSale) {
        $stmt = $conn->query("
            SELECT urun_id, urun_adi, birim_fiyati, stok_miktari
            FROM urunler
            WHERE aktif_mi = 1 AND stok_miktari > 0
            ORDER BY urun_adi
        ");
        $urunlerModal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    // dashboard devam etsin
}
?>

<style>
.pc-card { cursor: pointer; transition: transform .14s ease, box-shadow .14s ease; }
.pc-card:hover { transform: translateY(-2px); }

.pc-title { font-weight: 800; font-size: 1.06rem; letter-spacing: .2px; }
.pc-sub { color: rgba(229,231,235,.76); font-size: .9rem; }
.pc-metric { color: rgba(229,231,235,.88); font-size: .92rem; }
.badge-soft { background: rgba(255,255,255,.08); }

/* ✅ GÜNCELLENDİ: daha canlı/modern ikon alanı */
.pc-monitor{
    width:44px; height:44px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;

    background:
      radial-gradient(120% 120% at 25% 20%, rgba(59,130,246,.40) 0%, rgba(59,130,246,0) 55%),
      radial-gradient(120% 120% at 80% 85%, rgba(168,85,247,.35) 0%, rgba(168,85,247,0) 58%),
      rgba(255,255,255,.06);

    border: 1px solid rgba(255,255,255,.10);
    box-shadow:
      0 10px 26px rgba(0,0,0,.35),
      inset 0 0 0 1px rgba(255,255,255,.04);
}
.pc-monitor svg{
    filter: drop-shadow(0 6px 10px rgba(0,0,0,.25));
}

/* ✅ YENİ: PC kartlarının modern/canlı görünümü (duruma göre renk) */
.pc-shell{
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.10);
  background:
    radial-gradient(120% 140% at 15% 10%, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 55%),
    rgba(255,255,255,.045);
  box-shadow: 0 18px 45px rgba(0,0,0,.45);
  transition: transform .14s ease, box-shadow .14s ease, border-color .14s ease;
}
.pc-shell:hover{
  transform: translateY(-2px);
  box-shadow: 0 22px 60px rgba(0,0,0,.52);
  border-color: rgba(255,255,255,.16);
}

/* üstte ince “glow” şerit */
.pc-shell::before{
  content:"";
  position:absolute;
  left:-40px;
  right:-40px;
  top:-60px;
  height:140px;
  opacity:.92;
  pointer-events:none;
  transform: rotate(-6deg);
}

/* Durum temaları */
.pc-empty::before{
  background: radial-gradient(60% 100% at 30% 40%,
    rgba(34,197,94,.55) 0%,
    rgba(34,197,94,0) 70%);
}
.pc-full::before{
  background: radial-gradient(60% 100% at 30% 40%,
    rgba(56,189,248,.55) 0%,
    rgba(56,189,248,0) 70%);
}
.pc-maint::before{
  background: radial-gradient(60% 100% at 30% 40%,
    rgba(251,191,36,.55) 0%,
    rgba(251,191,36,0) 70%);
}
.pc-off::before{
  background: radial-gradient(60% 100% at 30% 40%,
    rgba(239,68,68,.55) 0%,
    rgba(239,68,68,0) 70%);
}

/* Kart içindeki body biraz daha “cam” dursun */
.pc-shell .card-body{
  background:
    radial-gradient(120% 140% at 100% 0%,
      rgba(168,85,247,.10) 0%,
      rgba(168,85,247,0) 60%),
    rgba(255,255,255,.02);
  backdrop-filter: blur(8px);
}

/* Aksiyon barı */
.pc-actions{
  padding: 10px 12px 12px 12px;
  background: rgba(255,255,255,.04);
  border-top: 1px solid rgba(255,255,255,.10);
  border-bottom-left-radius: 18px;
  border-bottom-right-radius: 18px;
  min-height: 108px;
  display:flex;
  align-items: center;
}
.pc-actions .btn{
  border-radius: 12px;
  padding: 8px 10px;
  font-weight: 600;
  font-size: .92rem;
  line-height: 1.2;
}

/* butonlar */
.btn-pc-primary{ background: rgba(37, 99, 235, .92) !important; border: 1px solid rgba(37, 99, 235, .92) !important; }
.btn-pc-primary:hover{ background: rgba(29, 78, 216, 1) !important; border-color: rgba(29, 78, 216, 1) !important; }
.btn-pc-danger{ background: rgba(220, 38, 38, .92) !important; border: 1px solid rgba(220, 38, 38, .92) !important; }
.btn-pc-danger:hover{ background: rgba(185, 28, 28, 1) !important; border-color: rgba(185, 28, 28, 1) !important; }
.btn-pc-success{ background: rgba(22, 163, 74, .92) !important; border: 1px solid rgba(22, 163, 74, .92) !important; }
.btn-pc-success:hover{ background: rgba(21, 128, 61, 1) !important; border-color: rgba(21, 128, 61, 1) !important; }
.btn-pc-disabled{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  color: rgba(255,255,255,.70) !important;
}

.section-sep{
    height: 1px;
    background: rgba(255,255,255,.12);
    margin: 22px 0 26px 0;
    border-radius: 999px;
}
.loc-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 12px;
  margin-top: 14px;
}
.loc-head h5{ margin:0; font-weight: 800; letter-spacing: .2px; }
.loc-pill{
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  padding: 6px 10px;
  border-radius: 999px;
  font-size: .85rem;
  color: rgba(255,255,255,.85);
}

/* Üst kısayollar + hızlı yönetim */
.top-shortcuts{ display:flex; gap: 12px; align-items: stretch; flex-wrap: wrap; }
.shortcut-card{
  border: 0; border-radius: 18px;
  background: rgba(255,255,255,.06);
  backdrop-filter: blur(6px);
  transition: transform .14s ease, box-shadow .14s ease, background .14s ease;
  min-width: 200px;
  flex: 1 1 220px;
  cursor: pointer;
}
.shortcut-card:hover{
  transform: translateY(-2px);
  box-shadow: 0 14px 34px rgba(0,0,0,.35);
  background: rgba(255,255,255,.085);
}
.shortcut-card .inner{
  padding: 16px 16px;
  display:flex;
  align-items:center;
  justify-content: space-between;
  gap: 12px;
}
.shortcut-left{ display:flex; align-items:center; gap: 12px; }
.shortcut-ico{
  width: 56px; height: 56px;
  border-radius: 16px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,.10);
  font-size: 26px;
}
.shortcut-title{ font-weight: 800; font-size: 1.05rem; letter-spacing: .2px; }
.shortcut-sub{ color: rgba(229,231,235,.78); font-size: .92rem; margin-top: 2px; }
.shortcut-arrow{ opacity: .8; font-size: 22px; }

.pc-modal, .pc-modal * { color: rgba(255,255,255,.92) !important; }
.pc-modal .text-muted { color: rgba(255,255,255,.70) !important; }
.pc-modal .form-select, .pc-modal .form-control {
    background: rgba(255,255,255,.06) !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,.14) !important;
}
.pc-modal option { background: #111 !important; color: #fff !important; }
.pc-modal .alert { background: rgba(255,255,255,.06) !important; border: 0 !important; }
.pc-modal .btn-close { filter: invert(1) grayscale(100%); }

.sum-card{
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.10);
  border-radius: 14px;
  padding: 12px 12px;
}
.sum-label{ opacity:.78; font-size:.85rem; }
.sum-value{ font-weight: 900; font-size: 1.15rem; }

.quick-grid-title { font-weight: 800; font-size: 1.15rem; letter-spacing: .2px; }
.quick-grid-sub { color: rgba(229,231,235,.80); font-size: .98rem; margin-top: 4px; }
.quick-card {
  cursor: pointer;
  border: 0;
  border-radius: 18px;
  background: rgba(255,255,255,.06);
  backdrop-filter: blur(6px);
  transition: transform .14s ease, box-shadow .14s ease, background .14s ease;
  height: 100%;
  min-height: 120px;
}
.quick-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 34px rgba(0,0,0,.35);
  background: rgba(255,255,255,.085);
}
.quick-card .card-body{ padding: 18px 18px; }
.quick-icon {
  width: 64px; height: 64px;
  border-radius: 16px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,.10);
  font-size: 28px;
}
.quick-arrow { opacity: .8; font-size: 22px; }

/* ✅ DÜZELTME: İstatistik modal yazıları koyu kalmasın */
#istatistikModal,
#istatistikModal .modal-content,
#istatistikModal .modal-body,
#istatistikModal .card,
#istatistikModal .card-body {
  color: rgba(255,255,255,.92) !important;
}
#istatistikModal .text-muted,
#istatistikModal .small.text-muted,
#istatistikModal .small {
  color: rgba(255,255,255,.72) !important;
}
</style>

<h2 class="mb-4">Yönetim Paneli</h2>

<?php if (!$canPcView): ?>
  <div class="alert alert-danger">Bu ekrana erişim yetkin yok.</div>
<?php else: ?>

  <!-- ✅ ÜST KISAYOLLAR -->
  <?php if ($isAdmin || $canMaint): ?>
    <div class="d-flex justify-content-between align-items-stretch gap-3 mb-3 flex-wrap">
      <div class="top-shortcuts flex-grow-1">

        <?php if ($isAdmin): ?>
          <div class="shortcut-card" onclick="window.location.href='bilgisayarlar.php'">
            <div class="inner">
              <div class="shortcut-left">
                <div class="shortcut-ico">🖥️</div>
                <div>
                  <div class="shortcut-title">Bilgisayarlar</div>
                  <div class="shortcut-sub">Bilgisayar Listesi ve Durum</div>
                </div>
              </div>
              <div class="shortcut-arrow">›</div>
            </div>
          </div>

          <div class="shortcut-card" onclick="window.location.href='odemeler.php'">
            <div class="inner">
              <div class="shortcut-left">
                <div class="shortcut-ico">💳</div>
                <div>
                  <div class="shortcut-title">Ödemeler</div>
                  <div class="shortcut-sub">Tüm ödemeler</div>
                </div>
              </div>
              <div class="shortcut-arrow">›</div>
            </div>
          </div>

          <div class="shortcut-card" onclick="window.location.href='urunler.php'">
            <div class="inner">
              <div class="shortcut-left">
                <div class="shortcut-ico">🧃</div>
                <div>
                  <div class="shortcut-title">Ürünler</div>
                  <div class="shortcut-sub">Stok ve Fiyat</div>
                </div>
              </div>
              <div class="shortcut-arrow">›</div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($canMaint && !$isAdmin): ?>
          <div class="shortcut-card" onclick="window.location.href='bakim_kayitlari.php'">
            <div class="inner">
              <div class="shortcut-left">
                <div class="shortcut-ico">🛠️</div>
                <div>
                  <div class="shortcut-title">Bakım Kayıtları</div>
                  <div class="shortcut-sub">Bakım geçmişi</div>
                </div>
              </div>
              <div class="shortcut-arrow">›</div>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>

  <?php $konumSiralama = ['Salon 1','Salon 2','Üst Kat','VIP Oda']; ?>

  <!-- ✅ PC KARTLARI -->
  <?php foreach ($konumSiralama as $konumBaslik): ?>
    <?php if (empty($pcGruplu[$konumBaslik])) continue; ?>

    <div class="loc-head">
      <h5><?= htmlspecialchars($konumBaslik); ?></h5>
      <div class="loc-pill"><?= count($pcGruplu[$konumBaslik]); ?> bilgisayar</div>
    </div>
    <div class="section-sep" style="margin:12px 0 16px 0;"></div>

    <div class="row g-3 mb-4">
      <?php foreach ($pcGruplu[$konumBaslik] as $pc): ?>
        <?php
          $durum = $pc['durum'] ?? 'boş';

          $badgeClass = 'bg-secondary';
          if ($durum === 'boş') $badgeClass = 'bg-success';
          elseif ($durum === 'dolu') $badgeClass = 'bg-info';
          elseif ($durum === 'bakımda') $badgeClass = 'bg-warning';
          elseif ($durum === 'kapalı') $badgeClass = 'bg-danger';

          // ✅ Kart tema class
          $pcThemeClass = 'pc-empty';
          if ($durum === 'boş') $pcThemeClass = 'pc-empty';
          elseif ($durum === 'dolu') $pcThemeClass = 'pc-full';
          elseif ($durum === 'bakımda') $pcThemeClass = 'pc-maint';
          elseif ($durum === 'kapalı') $pcThemeClass = 'pc-off';

          $oturumAcikMi = !empty($pc['oturum_id']);

          $musteriYazi = 'Misafir';
          if (!empty($pc['musteri_id'])) {
            $tmp = trim(($pc['musteri_ad'] ?? '') . ' ' . ($pc['musteri_soyad'] ?? ''));
            $musteriYazi = $tmp !== '' ? $tmp : 'Misafir';
          }

          $dakika = 0;
          $oturumUcreti = 0.0;
          if ($oturumAcikMi && !empty($pc['baslangic_zamani'])) {
            $bas = new DateTime($pc['baslangic_zamani']);
            $now = new DateTime();
            $diffSeconds = max(0, $now->getTimestamp() - $bas->getTimestamp());
            $dakika = max(1, (int) floor($diffSeconds / 60));

            $saatUcreti = (float)($pc['saat_ucreti'] ?? 0);
            $acilis = 20.0;
            $oturumUcreti = round($acilis + (($dakika / 60) * $saatUcreti), 2);
          }

          $urunToplam = (float)($pc['urun_toplam'] ?? 0);

          $modalId = 'pcModal_' . (int)$pc['bilgisayar_id'];
          $urunModalId   = 'urunSatisModal_' . (int)$pc['bilgisayar_id'];
          $bitirModalId  = 'oturumBitirModal_' . (int)$pc['bilgisayar_id'];
          $baslatModalId = 'oturumBaslatModal_' . (int)$pc['bilgisayar_id'];

          $hourly = (float)($pc['saat_ucreti'] ?? 0);
          $openFee = 20.0;

          $nkPcKonum = normalizeKonum($pc['konum'] ?? '');
          $isVipRoom = ($nkPcKonum === 'vip oda' || $nkPcKonum === 'vip');

          $tarifeListe = [];
          if ($canSession) {
            foreach ($tarifeler as $t) {
              $vipMi = isVipTarifeAdi($t['tarife_adi'] ?? '');
              if ($isVipRoom) { if ($vipMi) $tarifeListe[] = $t; }
              else { if (!$vipMi) $tarifeListe[] = $t; }
            }
          }
        ?>

        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
          <div class="card text-light border-0 h-100 d-flex flex-column pc-shell <?= $pcThemeClass; ?>">

            <div class="pc-card flex-grow-1" data-bs-toggle="modal" data-bs-target="#<?= $modalId; ?>">
              <div class="card-body">

                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="pc-monitor">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <defs>
                        <linearGradient id="pcG" x1="3" y1="5" x2="21" y2="19" gradientUnits="userSpaceOnUse">
                          <stop stop-color="#60A5FA"/>
                          <stop offset="0.55" stop-color="#A78BFA"/>
                          <stop offset="1" stop-color="#34D399"/>
                        </linearGradient>
                        <linearGradient id="pcScreen" x1="6" y1="7" x2="18" y2="15" gradientUnits="userSpaceOnUse">
                          <stop stop-color="rgba(255,255,255,0.20)"/>
                          <stop offset="1" stop-color="rgba(255,255,255,0.02)"/>
                        </linearGradient>
                      </defs>

                      <rect x="4.5" y="6" width="15" height="10" rx="2.3" stroke="url(#pcG)" stroke-width="1.8"/>
                      <rect x="6.2" y="7.7" width="11.6" height="6.6" rx="1.6" fill="url(#pcScreen)"/>

                      <path d="M9.2 19.2H14.8" stroke="url(#pcG)" stroke-width="1.9" stroke-linecap="round"/>
                      <path d="M10.6 16.2L9.6 19.2" stroke="url(#pcG)" stroke-width="1.9" stroke-linecap="round"/>
                      <path d="M13.4 16.2L14.4 19.2" stroke="url(#pcG)" stroke-width="1.9" stroke-linecap="round"/>

                      <circle cx="18.1" cy="15.2" r="0.8" fill="#34D399" opacity="0.9"/>
                    </svg>
                  </div>

                  <div class="flex-grow-1">
                    <div class="pc-title"><?= htmlspecialchars($pc['bilgisayar_adi']); ?></div>
                    <div class="pc-sub"><?= htmlspecialchars($pc['konum'] ?: 'Konum: -'); ?></div>
                  </div>

                  <?php if ($canPcStatus): ?>
                    <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars(ucfirst($durum)); ?></span>
                  <?php endif; ?>
                </div>

                <?php if ($oturumAcikMi): ?>
                  <div class="pc-metric mb-1">Müşteri: <b><?= htmlspecialchars($musteriYazi); ?></b></div>
                  <div class="pc-metric mb-1">Tarife: <b><?= htmlspecialchars($pc['tarife_adi'] ?? '-'); ?></b></div>

                  <div class="pc-metric mb-1">
                    Süre:
                    <b><span class="js-live-min" data-start="<?= htmlspecialchars($pc['baslangic_zamani']); ?>"><?= (int)$dakika; ?> dk</span></b>
                  </div>

                  <div class="pc-metric mb-1">
                    Ürünler: <b><?= number_format((float)$urunToplam, 2, ',', '.'); ?> TL</b>
                  </div>

                  <div class="pc-metric">
                    Oturum Ücreti:
                    <b>
                      <span class="js-live-fee"
                            data-start="<?= htmlspecialchars($pc['baslangic_zamani']); ?>"
                            data-hourly="<?= htmlspecialchars($hourly); ?>"
                            data-openfee="<?= htmlspecialchars($openFee); ?>">
                        <?= number_format((float)$oturumUcreti, 2, ',', '.'); ?> TL
                      </span>
                    </b>
                  </div>
                <?php else: ?>
                  <div class="pc-metric mb-2">Müşteri: <b>-</b></div>
                  <div class="pc-metric mb-2">Tarife: <b>-</b></div>
                  <div class="pc-metric">Durum: <b><?= $durum === 'bakımda' ? 'Bakımda' : 'Oturum Kapalı'; ?></b></div>
                <?php endif; ?>

              </div>
            </div>

            <div class="pc-actions">
              <div class="d-grid gap-2 w-100">

                <?php if ($oturumAcikMi): ?>

                  <?php if ($canSale): ?>
                    <button type="button" class="btn btn-pc-primary"
                            data-bs-toggle="modal" data-bs-target="#<?= $urunModalId; ?>"
                            onclick="event.stopPropagation();">
                      🧾 Ürün Satışı
                    </button>
                  <?php endif; ?>

                  <?php if ($canSession): ?>
                    <button type="button" class="btn btn-pc-danger js-open-finish"
                            data-bs-toggle="modal" data-bs-target="#<?= $bitirModalId; ?>"
                            data-oturum-id="<?= (int)$pc['oturum_id']; ?>"
                            data-return="dashboard.php"
                            data-start="<?= htmlspecialchars($pc['baslangic_zamani']); ?>"
                            data-hourly="<?= htmlspecialchars($hourly); ?>"
                            data-openfee="<?= htmlspecialchars($openFee); ?>"
                            data-urun="<?= htmlspecialchars($urunToplam); ?>"
                            data-pc="<?= htmlspecialchars($pc['bilgisayar_adi']); ?>"
                            onclick="event.stopPropagation();">
                      ⛔ Oturumu Bitir
                    </button>
                  <?php endif; ?>

                  <?php if (!$canSale && !$canSession): ?>
                    <button type="button" class="btn btn-pc-disabled" disabled onclick="event.stopPropagation();">
                      Yetkin yok
                    </button>
                  <?php endif; ?>

                <?php else: ?>

                  <?php if ($durum === 'bakımda'): ?>
                    <button type="button" class="btn btn-pc-disabled" disabled onclick="event.stopPropagation();">
                      🛠️ Bakımda
                    </button>
                  <?php else: ?>
                    <?php if ($canSession): ?>
                      <button type="button" class="btn btn-pc-success"
                              data-bs-toggle="modal" data-bs-target="#<?= $baslatModalId; ?>"
                              onclick="event.stopPropagation();">
                        ▶︎ Oturum Başlat
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-pc-disabled" disabled onclick="event.stopPropagation();">
                        Oturum başlatma yetkin yok
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>

                <?php endif; ?>

              </div>
            </div>
          </div>
        </div>

        <!-- ✅ PC Modal -->
        <div class="modal fade" id="<?= $modalId; ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-0 shadow-lg pc-modal" style="border-radius: 1rem;">
              <div class="modal-header border-0">
                <h5 class="modal-title"><?= htmlspecialchars($pc['bilgisayar_adi']); ?> — Yönetim</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="badge badge-soft">Konum: <?= htmlspecialchars($pc['konum'] ?: '-'); ?></span>
                  <?php if ($canPcStatus): ?>
                    <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars(ucfirst($durum)); ?></span>
                  <?php endif; ?>
                </div>

                <?php if (!$oturumAcikMi): ?>

                  <?php if ($durum === 'bakımda'): ?>

                    <div class="alert alert-warning">
                      Bu bilgisayar şu anda <b>bakımda</b>.
                    </div>

                    <?php if ($canMaint): ?>
                      <h6 class="mb-2">Bakımı Tamamla</h6>

                      <form method="POST" action="bakim_tamamla.php" class="row g-2">
                        <input type="hidden" name="bilgisayar_id" value="<?= (int)$pc['bilgisayar_id']; ?>">
                        <input type="hidden" name="return" value="dashboard.php">

                        <div class="col-12">
                          <textarea name="aciklama" class="form-control" rows="2"
                                    placeholder="Bakım tamamlandı notu (opsiyonel)"></textarea>
                        </div>

                        <div class="col-12">
                          <button type="submit" class="btn btn-success w-100"
                                  onclick="return confirm('Bakımı tamamlayıp bilgisayarı BOŞ duruma almak istiyor musunuz?');">
                            Bakımı Tamamla
                          </button>
                        </div>
                      </form>
                    <?php else: ?>
                      <div class="alert alert-secondary">Bakımı tamamlamak için yetkin yok.</div>
                    <?php endif; ?>

                  <?php else: ?>
                    <div class="alert alert-secondary">
                      Bu bilgisayarda açık oturum yok.
                      <?php if ($canSession): ?>
                        <div class="small text-muted mt-1">Oturumu başlatmak için kartın altındaki <b>“Oturum Başlat”</b> butonunu kullan.</div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                <?php else: ?>

                  <div class="alert alert-secondary">
                    <div><b>Müşteri:</b> <?= htmlspecialchars($musteriYazi); ?></div>
                    <div><b>Tarife:</b> <?= htmlspecialchars($pc['tarife_adi'] ?? '-'); ?></div>

                    <div>
                      <b>Süre:</b>
                      <span class="js-live-min" data-start="<?= htmlspecialchars($pc['baslangic_zamani']); ?>"><?= (int)$dakika; ?> dk</span>
                    </div>

                    <div><b>Ürünler:</b> <?= number_format((float)$urunToplam, 2, ',', '.'); ?> TL</div>

                    <div>
                      <b>Oturum Ücreti:</b>
                      <span class="js-live-fee"
                            data-start="<?= htmlspecialchars($pc['baslangic_zamani']); ?>"
                            data-hourly="<?= htmlspecialchars($hourly); ?>"
                            data-openfee="<?= htmlspecialchars($openFee); ?>">
                        <?= number_format((float)$oturumUcreti, 2, ',', '.'); ?> TL
                      </span>
                    </div>

                    <div class="small text-muted mt-1">Not: Oturum ücreti 20 TL açılış dahil hesaplanır.</div>
                  </div>

                <?php endif; ?>
              </div>

              <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Kapat</button>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$oturumAcikMi && $durum !== 'bakımda' && $canSession): ?>
          <!-- ✅ OTURUM BAŞLAT MODALI -->
          <div class="modal fade" id="<?= $baslatModalId; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content bg-dark text-light border-0 shadow-lg pc-modal" style="border-radius: 1rem;">
                <div class="modal-header border-0">
                  <h5 class="modal-title">Oturum Başlat — <?= htmlspecialchars($pc['bilgisayar_adi']); ?></h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="alert alert-secondary">
                    <div><b>Konum:</b> <?= htmlspecialchars($pc['konum'] ?: '-'); ?></div>
                    <div class="small text-muted mt-1">Hızlı başlatma ekranı</div>
                  </div>

                  <form method="POST" action="oturum_baslat.php">
                    <input type="hidden" name="bilgisayar_id" value="<?= (int)$pc['bilgisayar_id']; ?>">

                    <div class="mb-3">
                      <label class="form-label">Müşteri (opsiyonel)</label>
                      <select name="musteri_id" class="form-select">
                        <option value="">Misafir (kayıtsız)</option>
                        <?php foreach ($musteriler as $m): ?>
                          <option value="<?= (int)$m['musteri_id']; ?>"><?= htmlspecialchars($m['ad'].' '.$m['soyad']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Tarife<?= $isVipRoom ? ' (VIP)' : ''; ?></label>
                      <select name="tarife_id" class="form-select" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($tarifeListe as $t): ?>
                          <option value="<?= (int)$t['tarife_id']; ?>">
                            <?= htmlspecialchars($t['tarife_adi']); ?>
                            (<?= number_format((float)$t['saat_ucreti'], 2, ',', '.'); ?> TL/saat)
                          </option>
                        <?php endforeach; ?>
                      </select>

                      <?php if ($isVipRoom && empty($tarifeListe)): ?>
                        <div class="alert alert-warning mt-2">
                          VIP Oda için "VIP" içeren aktif tarife bulunamadı.
                        </div>
                      <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-success w-100">▶︎ Oturum Başlat</button>
                  </form>
                </div>

                <div class="modal-footer border-0">
                  <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Kapat</button>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($oturumAcikMi && $canSale): ?>
          <!-- ✅ ÜRÜN SATIŞI MODALI -->
          <div class="modal fade" id="<?= $urunModalId; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content bg-dark text-light border-0 shadow-lg pc-modal" style="border-radius: 1rem;">
                <div class="modal-header border-0">
                  <h5 class="modal-title">Ürün Satışı — <?= htmlspecialchars($pc['bilgisayar_adi']); ?></h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="alert alert-secondary">
                    <div><b>Oturum:</b> #<?= (int)$pc['oturum_id']; ?></div>
                    <div><b>Müşteri:</b> <?= htmlspecialchars($musteriYazi); ?></div>
                  </div>

                  <form method="POST" action="satis_oturum_ekle.php" class="row g-2">
                    <input type="hidden" name="oturum_id" value="<?= (int)$pc['oturum_id']; ?>">
                    <input type="hidden" name="bilgisayar_id" value="<?= (int)$pc['bilgisayar_id']; ?>">

                    <div class="col-12">
                      <label class="form-label">Ürün</label>
                      <select name="urun_id" class="form-select" required>
                        <option value="">Ürün seçiniz...</option>
                        <?php foreach ($urunlerModal as $u): ?>
                          <option value="<?= (int)$u['urun_id']; ?>">
                            <?= htmlspecialchars($u['urun_adi']); ?>
                            (Stok: <?= (int)$u['stok_miktari']; ?>,
                            <?= number_format((float)$u['birim_fiyati'], 2, ',', '.'); ?> TL)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Adet</label>
                      <input type="number" name="adet" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="col-12 mt-2">
                      <button type="submit" class="btn btn-primary w-100">➕ Satışı Ekle</button>
                    </div>
                  </form>
                </div>

                <div class="modal-footer border-0">
                  <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Kapat</button>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($oturumAcikMi && $canSession): ?>
          <!-- ✅ OTURUM BİTİR ÖZET MODALI -->
          <div class="modal fade" id="<?= $bitirModalId; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content bg-dark text-light border-0 shadow-lg pc-modal" style="border-radius: 1rem;">
                <div class="modal-header border-0">
                  <h5 class="modal-title">Oturumu Bitir — <?= htmlspecialchars($pc['bilgisayar_adi']); ?></h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="alert alert-secondary mb-3">
                    <div><b>Müşteri:</b> <?= htmlspecialchars($musteriYazi); ?></div>
                    <div><b>Tarife:</b> <?= htmlspecialchars($pc['tarife_adi'] ?? '-'); ?></div>
                    <div class="small text-muted mt-1">Aşağıdaki özet canlı hesaplanır.</div>
                  </div>

                  <div class="row g-2">
                    <div class="col-6">
                      <div class="sum-card">
                        <div class="sum-label">Ürün Satışı</div>
                        <div class="sum-value js-sum-urun"><?= number_format((float)$urunToplam, 2, ',', '.'); ?> TL</div>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="sum-card">
                        <div class="sum-label">Oturum Ücreti</div>
                        <div class="sum-value js-sum-oturum"><?= number_format((float)$oturumUcreti, 2, ',', '.'); ?> TL</div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="sum-card" style="border-color: rgba(255,255,255,.22);">
                        <div class="sum-label">Toplam</div>
                        <div class="sum-value js-sum-toplam"><?= number_format((float)($urunToplam + $oturumUcreti), 2, ',', '.'); ?> TL</div>
                      </div>
                    </div>
                  </div>

                  <div class="small text-muted mt-2">
                    Not: Oturum ücreti açılış (<?= number_format((float)$openFee, 2, ',', '.'); ?> TL) + geçen süreye göre hesaplanır.
                  </div>
                </div>

                <div class="modal-footer border-0 d-grid gap-2">
                  <a class="btn btn-danger"
                     href="oturum_bitir.php?id=<?= (int)$pc['oturum_id']; ?>&return=dashboard.php"
                     onclick="return confirm('Oturumu bitirmek istediğinizden emin misiniz?');">
                    ✅ Ödeme Al ve Oturumu Bitir
                  </a>
                  <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Vazgeç</button>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- ✅ HIZLI YÖNETİM -->
  <?php if ($isAdmin || $canMaint): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Hızlı Yönetim</h4>
      <span class="text-muted small">Modüllere hızlı erişim</span>
    </div>

    <div class="row g-3 mb-4">
      <?php
        $kartlar = [];

        if ($isAdmin) {
          $kartlar[] = ['title'=>'İstatistikler','sub'=>'Bugün / toplam özet','icon'=>'📈','href'=>'#','modal'=>true];
          $kartlar[] = ['title'=>'Oturumlar','sub'=>'Başlat / bitir / liste','icon'=>'⏱️','href'=>'oturumlar.php','modal'=>false];
          $kartlar[] = ['title'=>'Müşteriler','sub'=>'Kayıt ve Yönetim','icon'=>'👥','href'=>'musteriler.php','modal'=>false];
          $kartlar[] = ['title'=>'Ürün Satışları','sub'=>'Satış kayıtları','icon'=>'🧾','href'=>'satislar.php','modal'=>false];
          $kartlar[] = ['title'=>'Bakım Kayıtları','sub'=>'Bakım geçmişi','icon'=>'🛠️','href'=>'bakim_kayitlari.php','modal'=>false];
          $kartlar[] = ['title'=>'Çalışanlar','sub'=>'Yetki ve Kullanıcılar','icon'=>'🧑‍💼','href'=>'calisanlar.php','modal'=>false];
          $kartlar[] = ['title'=>'Tarifeler','sub'=>'Saatlik ücretler','icon'=>'📊','href'=>'tarifeler.php','modal'=>false];
        } else {
          $kartlar[] = ['title'=>'Bakım Kayıtları','sub'=>'Bakım geçmişi','icon'=>'🛠️','href'=>'bakim_kayitlari.php','modal'=>false];
        }

        foreach ($kartlar as $k) {
          $cardAttrs = $k['modal']
            ? 'data-bs-toggle="modal" data-bs-target="#istatistikModal"'
            : 'onclick="window.location.href=\''.htmlspecialchars($k['href'], ENT_QUOTES).'\'"';

          echo '
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card quick-card text-light" '.$cardAttrs.'>
              <div class="card-body d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                  <div class="quick-icon">'.$k['icon'].'</div>
                  <div>
                    <div class="quick-grid-title">'.htmlspecialchars($k['title']).'</div>
                    <div class="quick-grid-sub">'.htmlspecialchars($k['sub']).'</div>
                  </div>
                </div>
                <div class="quick-arrow">›</div>
              </div>
            </div>
          </div>';
        }
      ?>
    </div>
  <?php endif; ?>

  <!-- ✅ İSTATİSTİK MODAL -->
  <?php if ($isAdmin): ?>
  <div class="modal fade" id="istatistikModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-0 shadow-lg" style="border-radius: 1rem;">
        <div class="modal-header border-0">
          <h5 class="modal-title">İstatistikler — Özet</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Açık Oturumlar</div>
                  <div class="fs-3 fw-bold"><?= (int)$acikOturum; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Aktif Müşteriler</div>
                  <div class="fs-3 fw-bold"><?= (int)$aktifMusteri; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Aktif Ürünler</div>
                  <div class="fs-3 fw-bold"><?= (int)$aktifUrun; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Toplam Bilgisayar</div>
                  <div class="fs-3 fw-bold"><?= (int)$toplamBilgisayar; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Aktif Bilgisayar</div>
                  <div class="fs-3 fw-bold"><?= (int)$aktifBilgisayar; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Bakım Kayıtları</div>
                  <div class="fs-3 fw-bold"><?= (int)$bakimSayisi; ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Toplam Ciro</div>
                  <div class="fs-3 fw-bold"><?= number_format((float)$toplamCiro, 2, ',', '.'); ?> TL</div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Bugünkü Ciro</div>
                  <div class="fs-3 fw-bold"><?= number_format((float)$bugunCiro, 2, ',', '.'); ?> TL</div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Oturum Geliri</div>
                  <div class="fs-3 fw-bold"><?= number_format((float)$oturumGeliri, 2, ',', '.'); ?> TL</div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card bg-black bg-opacity-25 border-0 h-100" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Ürün Satış Geliri</div>
                  <div class="fs-3 fw-bold"><?= number_format((float)$urunGeliri, 2, ',', '.'); ?> TL</div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card bg-black bg-opacity-25 border-0" style="border-radius: 14px;">
                <div class="card-body">
                  <div class="small text-muted">Toplam Ürün Satış Adedi</div>
                  <div class="fs-3 fw-bold"><?= (int)$toplamSatisAdedi; ?></div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Kapat</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>

<script>
(function () {
  function parseMySQLDateTime(dt) {
    if (!dt) return null;
    return new Date(dt.replace(' ', 'T'));
  }

  function formatTLNumber(x) {
    return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(x);
  }

  function formatTL(x) {
    return formatTLNumber(x) + ' TL';
  }

  function calcMinutes(start) {
    const diffMs = Date.now() - start.getTime();
    const m = Math.floor(diffMs / 60000);
    return Math.max(1, m);
  }

  function calcSessionFee(start, hourly, openFee) {
    const minutes = calcMinutes(start);
    const sessionFee = openFee + (minutes / 60) * hourly;
    return Math.round(sessionFee * 100) / 100;
  }

  function updateLive() {
    document.querySelectorAll('.js-live-min').forEach(el => {
      const start = parseMySQLDateTime(el.dataset.start);
      if (!start || isNaN(start.getTime())) return;
      el.textContent = calcMinutes(start) + ' dk';
    });

    document.querySelectorAll('.js-live-fee').forEach(el => {
      const start = parseMySQLDateTime(el.dataset.start);
      if (!start || isNaN(start.getTime())) return;

      const hourly = parseFloat(el.dataset.hourly || '0');
      const openFee = parseFloat(el.dataset.openfee || '0');

      const sessionFee = calcSessionFee(start, hourly, openFee);
      el.textContent = formatTL(sessionFee);
    });
  }

  updateLive();
  setInterval(updateLive, 1000);

  document.querySelectorAll('.modal').forEach(modalEl => {
    modalEl.addEventListener('show.bs.modal', function (ev) {
      const trigger = ev.relatedTarget;
      if (!trigger || !trigger.classList.contains('js-open-finish')) return;

      const startStr = trigger.dataset.start;
      const hourly = parseFloat(trigger.dataset.hourly || '0');
      const openFee = parseFloat(trigger.dataset.openfee || '0');
      const urun = parseFloat(trigger.dataset.urun || '0');

      const start = parseMySQLDateTime(startStr);
      if (!start || isNaN(start.getTime())) return;

      const oturum = calcSessionFee(start, hourly, openFee);
      const toplam = Math.round((urun + oturum) * 100) / 100;

      const urunEl = modalEl.querySelector('.js-sum-urun');
      const oturumEl = modalEl.querySelector('.js-sum-oturum');
      const toplamEl = modalEl.querySelector('.js-sum-toplam');

      if (urunEl) urunEl.textContent = formatTL(urun);
      if (oturumEl) oturumEl.textContent = formatTL(oturum);
      if (toplamEl) toplamEl.textContent = formatTL(toplam);
    });
  });

})();
</script>

<?php require 'includes/footer.php'; ?>



















