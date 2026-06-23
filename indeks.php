<?php
include 'koneksi.php';
cek_login();
$tgl = date('Y-m-d');
// ── Kartu statistik ────────────────────────────────────────────────────────
$r_kritis   = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM produk
WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN 0 AND $THRESHOLD_MERAH AND stok > 0"
));
$r_warning  = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM produk
WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN " . ($THRESHOLD_MERAH + 1) . " AND $THRESHOLD_KUNING AND stok > 0"
));
$r_aman     = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM produk
WHERE DATEDIFF(tgl_expired,'$tgl') > $THRESHOLD_KUNING AND stok > 0"
));
$r_expired  = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM produk
WHERE tgl_expired < '$tgl' AND stok > 0"
));
$r_produk   = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM produk"
));
$r_trx_hari = mysqli_fetch_assoc(mysqli_query(
  $koneksi,
  "SELECT COUNT(*) as t FROM transaksi WHERE DATE(created_at) = '$tgl'"
));
// ── Grafik 7 hari terakhir ─────────────────────────────────────────────────
$grafik_labels = [];
$grafik_data   = [];
for ($i = 6; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $grafik_labels[] = "'" . date('d/m', strtotime($d)) . "'";
  $q = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(dt.qty), 0) as t
FROM detail_transaksi dt
JOIN transaksi tr ON dt.transaksi_id = tr.id
WHERE DATE(tr.created_at) = '$d'"
  ));
  $grafik_data[] = $q['t'];
}
// ── Grafik nilai stok per kategori ────────────────────────────────────────
$q_nilai_kat = mysqli_query(
  $koneksi,
  "SELECT k.nama_kategori,
COALESCE(SUM(p.stok * p.harga_beli), 0) AS nilai_stok,
COUNT(p.id) AS jumlah_batch
FROM kategori k
LEFT JOIN produk p ON p.kategori_id = k.id AND p.stok > 0
GROUP BY k.id, k.nama_kategori
ORDER BY nilai_stok DESC"
);
$kat_labels  = [];
$kat_nilai   = [];
$kat_batch   = [];
while ($row = mysqli_fetch_assoc($q_nilai_kat)) {
  $kat_labels[] = "'" . addslashes($row['nama_kategori']) . "'";
  $kat_nilai[]  = (float)$row['nilai_stok'];
  $kat_batch[]  = (int)$row['jumlah_batch'];
}
// ── Tabel FIFO ────────────────────────────────────────────────────────────
$q_fifo = mysqli_query(
  $koneksi,
  "SELECT p.*, k.nama_kategori,
DATEDIFF(p.tgl_expired, '$tgl') as sisa_hari
FROM produk p
JOIN kategori k ON p.kategori_id = k.id
WHERE p.stok > 0
ORDER BY p.tgl_expired ASC"
);
// URL untuk tiap kartu
$url_kritis   = "laporan_expired.php?filter=kritis";
$url_warning  = "laporan_expired.php?filter=warning";
$url_aman     = "produk.php?status=aman";
$url_expired  = "laporan_expired.php?filter=expired";
$url_produk   = "laporan_stok.php";
$url_trx      = "laporan_transaksi.php?dari=$tgl&sampai=$tgl";
?>
<?php include 'includes/navbar.php'; ?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body {
      background: #f0f2f5;
      font-family: 'Segoe UI', sans-serif;
    }

    /* ── Kartu statistik ── */
    .stat-card {
      border: 0;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
      transition: transform .18s, box-shadow .18s;
      text-decoration: none !important;
      color: inherit !important;
      display: block;
      cursor: pointer;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, .12);
    }

    .stat-card:active {
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(0, 0, 0, .1);
    }

    .stat-card.c-danger {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-warning {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-success {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-secondary {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-primary {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-info {
      border-bottom: 3px solid transparent;
    }

    .stat-card.c-danger:hover {
      border-bottom-color: #dc3545;
    }

    .stat-card.c-warning:hover {
      border-bottom-color: #ffc107;
    }

    .stat-card.c-success:hover {
      border-bottom-color: #198754;
    }

    .stat-card.c-secondary:hover {
      border-bottom-color: #6c757d;
    }

    .stat-card.c-primary:hover {
      border-bottom-color: #0d6efd;
    }

    .stat-card.c-info:hover {
      border-bottom-color: #0dcaf0;
    }

    .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
    }

    .stat-card .click-hint {
      font-size: 9px;
      color: #aaa;
      margin-top: 5px;
      display: flex;
      align-items: center;
      gap: 3px;
      opacity: 0;
      transition: opacity .18s;
      white-space: nowrap;
    }

    .stat-card:hover .click-hint {
      opacity: 1;
    }

    .stat-label {
      font-size: 11px;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .stat-value {
      font-size: 1.4rem;
      line-height: 1;
    }

    /* ── Tabel FIFO ── */
    .table-fifo {
      font-size: 0.85rem;
    }

    .table-fifo th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      padding: 0.6rem 0.5rem;
      font-weight: 600;
    }

    .table-fifo td {
      padding: 0.5rem 0.5rem;
      vertical-align: middle;
    }

    .table-fifo td strong {
      font-size: 0.85rem;
    }

    .table-fifo td small {
      font-size: 0.75rem;
    }

    .badge-ews {
      font-size: 0.72rem;
      padding: 0.3em 0.6em;
      border-radius: 5px;
      font-weight: 500;
    }

    .table-fifo .badge {
      font-size: 0.75rem;
      padding: 0.3em 0.6em;
    }

    .chart-card {
      border: 0;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    /* ── Row grafik sejajar ── */
    .row-charts {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 0;
    }

    .row-charts>.col-chart-left {
      flex: 1 1 60%;
      min-width: 0;
    }

    .row-charts>.col-chart-right {
      flex: 1 1 35%;
      min-width: 0;
    }

    .row-charts .chart-card {
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .row-charts .chart-card .chart-body {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .chart-canvas-wrap {
      position: relative;
      flex: 1 1 auto;
      min-height: 260px;
    }

    .chart-canvas-wrap canvas {
      position: absolute;
      inset: 0;
      width: 100% !important;
      height: 100% !important;
    }

    .ews-legend-wrap {
      flex-shrink: 0;
    }

    .hover-legend:hover {
      background: #f0f2f5;
    }

    .row-nilai-stok {
      margin-top: 0;
    }

    @media (max-width: 991.98px) {

      .row-charts>.col-chart-left,
      .row-charts>.col-chart-right {
        flex: 1 1 100%;
      }

      .chart-canvas-wrap {
        min-height: 220px;
      }
    }
  </style>
</head>

<body>
  <div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="fw-bold mb-0">Dashboard EWS</h5>
        <small class="text-muted">Hari ini: <?= tgl_indo($tgl) ?></small>
      </div>
      <a href="tambah.php" class="btn btn-dark btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Input Batch
      </a>
    </div>
    <!-- ── Kartu Statistik ── -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_kritis ?>" class="stat-card card c-danger h-100 p-2" title="Lihat produk kritis">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-danger bg-opacity-15 text-danger"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-danger"><?= $r_kritis['t'] ?></div>
              <div class="text-muted stat-label">Kritis</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_warning ?>" class="stat-card card c-warning h-100 p-2" title="Lihat produk peringatan">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-warning bg-opacity-15 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-warning"><?= $r_warning['t'] ?></div>
              <div class="text-muted stat-label">Peringatan</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_aman ?>" class="stat-card card c-success h-100 p-2" title="Lihat produk aman">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-success bg-opacity-15 text-success"><i class="bi bi-check-circle-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-success"><?= $r_aman['t'] ?></div>
              <div class="text-muted stat-label">Aman</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_expired ?>" class="stat-card card c-secondary h-100 p-2" title="Lihat produk sudah kedaluwarsa">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-secondary bg-opacity-15 text-secondary"><i class="bi bi-x-circle-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-secondary"><?= $r_expired['t'] ?></div>
              <div class="text-muted stat-label">Expired</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_produk ?>" class="stat-card card c-primary h-100 p-2" title="Lihat laporan stok seluruh produk">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-primary bg-opacity-15 text-primary"><i class="bi bi-box-seam-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-primary"><?= $r_produk['t'] ?></div>
              <div class="text-muted stat-label">Produk</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="<?= $url_trx ?>" class="stat-card card c-info h-100 p-2" title="Lihat transaksi hari ini">
          <div class="d-flex align-items-center gap-2">
            <div class="stat-icon bg-info bg-opacity-15 text-info"><i class="bi bi-cart-check-fill"></i></div>
            <div>
              <div class="fw-bold stat-value text-info"><?= $r_trx_hari['t'] ?></div>
              <div class="text-muted stat-label">Trx Hari Ini</div>
            </div>
          </div>
          <div class="click-hint"><i class="bi bi-arrow-right-circle"></i> Lihat detail</div>
        </a>
      </div>
    </div><!-- end row kartu -->

    <!-- ══ Grafik: Bar 7 Hari + Donut EWS (sejajar, tinggi sama rata) ══ -->
    <div class="row-charts mb-3">
      <!-- Kiri: Pengeluaran 7 Hari -->
      <div class="col-chart-left">
        <div class="card chart-card p-3">
          <h6 class="fw-semibold mb-3">
            <i class="bi bi-bar-chart-line me-2"></i>Pengeluaran Barang 7 Hari Terakhir
          </h6>
          <div class="chart-body">
            <div class="chart-canvas-wrap">
              <canvas id="chartTrx"></canvas>
            </div>
          </div>
        </div>
      </div>
      <!-- Kanan: Distribusi EWS -->
      <div class="col-chart-right">
        <div class="card chart-card p-3">
          <h6 class="fw-semibold mb-3">
            <i class="bi bi-pie-chart me-2"></i>Distribusi Status EWS
          </h6>
          <div class="chart-body">
            <div class="chart-canvas-wrap" style="min-height:180px;">
              <canvas id="chartEWS"></canvas>
            </div>
          </div>
          <div class="ews-legend-wrap mt-3">
            <a href="<?= $url_kritis ?>" class="d-flex justify-content-between align-items-center small text-decoration-none text-muted mb-2 p-1 rounded hover-legend">
              <span><span class="badge bg-danger me-1">&nbsp;</span>Kritis</span>
              <strong class="text-danger"><?= $r_kritis['t'] ?> batch</strong>
            </a>
            <a href="<?= $url_warning ?>" class="d-flex justify-content-between align-items-center small text-decoration-none text-muted mb-2 p-1 rounded hover-legend">
              <span><span class="badge bg-warning me-1">&nbsp;</span>Peringatan</span>
              <strong class="text-warning"><?= $r_warning['t'] ?> batch</strong>
            </a>
            <a href="<?= $url_aman ?>" class="d-flex justify-content-between align-items-center small text-decoration-none text-muted mb-2 p-1 rounded hover-legend">
              <span><span class="badge bg-success me-1">&nbsp;</span>Aman</span>
              <strong class="text-success"><?= $r_aman['t'] ?> batch</strong>
            </a>
            <a href="<?= $url_expired ?>" class="d-flex justify-content-between align-items-center small text-decoration-none text-muted p-1 rounded hover-legend">
              <span><span class="badge bg-secondary me-1">&nbsp;</span>Expired</span>
              <strong class="text-secondary"><?= $r_expired['t'] ?> batch</strong>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Grafik Nilai Stok per Kategori (langsung di bawah, tanpa celah) ══ -->
    <div class="row-nilai-stok mb-3">
      <div class="card chart-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-layers-half me-2 text-primary"></i>Nilai Stok per Kategori
            <span class="badge bg-primary bg-opacity-10 text-primary fw-normal ms-2" style="font-size:.75rem">Rp · Harga Beli × Stok</span>
          </h6>
          <a href="laporan_stok.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-right me-1"></i>Detail Stok
          </a>
        </div>
        <div style="position:relative; height:<?= max(160, count($kat_nilai) * 44) ?>px;">
          <canvas id="chartNilaiKat"></canvas>
        </div>
      </div>
    </div>

    <!-- Tabel FIFO -->
    <div class="card chart-card">
      <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-semibold" style="font-size: 0.9rem;">
          <i class="bi bi-arrow-down-up me-2"></i>Urutan Pengeluaran FIFO (Expired Terdekat Duluan)
        </span>
        <a href="produk.php" class="btn btn-outline-light btn-sm">Lihat Semua</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-fifo">
            <thead class="table-light">
              <tr>
                <th class="ps-3">No</th>
                <th>No. Batch</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th class="text-center">Stok Sisa</th>
                <th>Tgl Masuk</th>
                <th>Tgl Expired</th>
                <th>Sisa Hari</th>
                <th class="text-center">Status EWS</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 1;
              while ($d = mysqli_fetch_assoc($q_fifo)) :
                $sisa = $d['sisa_hari'];
                [$bgc, $tc, $lbl] = status_expired($sisa, $THRESHOLD_MERAH, $THRESHOLD_KUNING);
              ?>
                <tr>
                  <td class="ps-3 text-muted"><?= $no++ ?></td>
                  <td><span class="badge bg-dark fw-normal"><?= htmlspecialchars($d['no_batch']) ?></span></td>
                  <td>
                    <strong><?= htmlspecialchars($d['nama_produk']) ?></strong>
                    <?php if (!empty($d['keterangan'])) : ?>
                      <br><small class="text-muted"><?= htmlspecialchars($d['keterangan']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
                  <td class="text-center">
                    <span class="badge <?= $d['stok'] <= 5 ? 'bg-danger' : 'bg-primary' ?>">
                      <?= $d['stok'] ?> unit
                    </span>
                  </td>
                  <td><?= tgl_indo($d['tgl_masuk']) ?></td>
                  <td><?= tgl_indo($d['tgl_expired']) ?></td>
                  <td>
                    <?php if ($sisa >= 0) : ?>
                      <strong><?= $sisa ?></strong> hari
                    <?php else : ?>
                      <span class="text-danger">Lewat <?= abs($sisa) ?> hari</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <span class="badge badge-ews <?= $bgc ?> <?= $tc ?>"><?= $lbl ?></span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- end container -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    // ── Bar chart 7 hari ──────────────────────────────────────────────────────
    new Chart(document.getElementById('chartTrx'), {
      type: 'bar',
      data: {
        labels: [<?= implode(',', $grafik_labels) ?>],
        datasets: [{
          label: 'Item Keluar',
          data: [<?= implode(',', $grafik_data) ?>],
          backgroundColor: 'rgba(33,37,41,.7)',
          borderRadius: 6,
          hoverBackgroundColor: '#0d6efd'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ' ' + ctx.parsed.y + ' item keluar'
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        },
        onClick: function() {
          window.location.href = '<?= $url_trx ?>';
        },
        onHover: function(event, elements) {
          event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        }
      }
    });
    // ── Donat chart EWS ──────────────────────────────────────────────────────
    const donatLinks = [
      '<?= $url_kritis ?>',
      '<?= $url_warning ?>',
      '<?= $url_aman ?>',
      '<?= $url_expired ?>'
    ];
    const ewsChart = new Chart(document.getElementById('chartEWS'), {
      type: 'doughnut',
      data: {
        labels: ['Kritis', 'Peringatan', 'Aman', 'Expired'],
        datasets: [{
          data: [<?= $r_kritis['t'] ?>, <?= $r_warning['t'] ?>, <?= $r_aman['t'] ?>, <?= $r_expired['t'] ?>],
          backgroundColor: ['#dc3545', '#ffc107', '#198754', '#6c757d'],
          hoverBackgroundColor: ['#b02a37', '#cc9a06', '#146c43', '#565e64'],
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' batch — klik untuk detail'
            }
          }
        },
        cutout: '65%',
        onClick: function(event, elements) {
          if (elements.length > 0) {
            window.location.href = donatLinks[elements[0].index];
          }
        },
        onHover: function(event, elements) {
          event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        }
      }
    });
  </script>
  <script>
    // ── Horizontal Bar: Nilai Stok per Kategori ──────────────────────────────
    (function() {
      const katLabels = [<?= implode(',', $kat_labels) ?>];
      const katNilai = [<?= implode(',', $kat_nilai) ?>];
      const katBatch = [<?= implode(',', $kat_batch) ?>];
      const palette = [
        '#0d6efd', '#3d8bfd', '#6ea8fe', '#0dcaf0', '#6f42c1',
        '#9163de', '#198754', '#20c997', '#fd7e14', '#dc3545'
      ];
      const bgColors = katLabels.map((_, i) => palette[i % palette.length]);

      function fmtRp(val) {
        if (val >= 1e9) return 'Rp ' + (val / 1e9).toFixed(1) + ' M';
        if (val >= 1e6) return 'Rp ' + (val / 1e6).toFixed(1) + ' Jt';
        if (val >= 1e3) return 'Rp ' + (val / 1e3).toFixed(0) + ' Rb';
        return 'Rp ' + val.toFixed(0);
      }
      new Chart(document.getElementById('chartNilaiKat'), {
        type: 'bar',
        data: {
          labels: katLabels,
          datasets: [{
            label: 'Nilai Stok',
            data: katNilai,
            backgroundColor: bgColors.map(c => c + 'cc'),
            borderColor: bgColors,
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
            barThickness: 28
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: ctx => {
                  const i = ctx.dataIndex;
                  return [
                    '  Nilai: ' + new Intl.NumberFormat('id-ID', {
                      style: 'currency',
                      currency: 'IDR',
                      maximumFractionDigits: 0
                    }).format(ctx.parsed.x),
                    '  Batch aktif: ' + katBatch[i] + ' batch'
                  ];
                }
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: {
                callback: val => fmtRp(val),
                font: {
                  size: 11
                }
              },
              grid: {
                color: '#e9ecef'
              }
            },
            y: {
              ticks: {
                font: {
                  size: 12
                },
                color: '#343a40'
              },
              grid: {
                display: false
              }
            }
          },
          onClick: function() {
            window.location.href = '<?= $url_produk ?>';
          },
          onHover: function(event, elements) {
            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
          }
        },
        plugins: [{
          id: 'valueLabels',
          afterDatasetsDraw(chart) {
            const {
              ctx
            } = chart;
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
              const val = katNilai[i];
              if (val <= 0) return;
              const xPos = bar.x + 8;
              const yPos = bar.y;
              ctx.save();
              ctx.fillStyle = '#343a40';
              ctx.font = 'bold 11px Segoe UI, sans-serif';
              ctx.textAlign = 'left';
              ctx.textBaseline = 'middle';
              ctx.fillText(fmtRp(val), xPos, yPos);
              ctx.restore();
            });
          }
        }]
      });
    })();
  </script>

</html>