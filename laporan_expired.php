<?php
include 'koneksi.php';
cek_login();

$tgl = date('Y-m-d');

// ── FILTER ───────────────────────────────────────────────────────────────
// all     = semua yang berisiko (kritis + peringatan + sudah expired)
// risiko  = kritis + sudah expired (yang butuh tindakan segera)
// expired = sudah lewat tanggal kedaluwarsa
$f_filter = $_GET['filter'] ?? 'all';

$where_map = [
    'all'     => "DATEDIFF(p.tgl_expired,'$tgl') <= $THRESHOLD_KUNING",
    'risiko'  => "DATEDIFF(p.tgl_expired,'$tgl') <= $THRESHOLD_MERAH",
    'expired' => "p.tgl_expired < '$tgl'",
];
$where_cond = $where_map[$f_filter] ?? $where_map['all'];

// ── DATA ─────────────────────────────────────────────────────────────────
$q = mysqli_query($koneksi, "
    SELECT p.*, k.nama_kategori, DATEDIFF(p.tgl_expired,'$tgl') as sisa_hari
    FROM produk p JOIN kategori k ON p.kategori_id = k.id
    WHERE $where_cond AND p.stok > 0
    ORDER BY p.tgl_expired ASC
");
$rows = [];
$rugi_expired = 0; $rugi_kritis = 0; $rugi_warning = 0;
$qty_expired = 0; $qty_kritis = 0; $qty_warning = 0;

while ($d = mysqli_fetch_assoc($q)) {
    $nilai = $d['stok'] * $d['harga_beli'];
    $d['nilai_rugi'] = $nilai;
    $s = getStatus($d['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING);
    $d['status'] = $s;

    if ($d['sisa_hari'] < 0) {
        $rugi_expired += $nilai; $qty_expired += $d['stok'];
    } elseif ($d['sisa_hari'] <= $THRESHOLD_MERAH) {
        $rugi_kritis += $nilai; $qty_kritis += $d['stok'];
    } else {
        $rugi_warning += $nilai; $qty_warning += $d['stok'];
    }
    $rows[] = $d;
}

$total_potensi_rugi = $rugi_expired + $rugi_kritis; // fokus: yang butuh tindakan segera
$total_semua_rugi   = $rugi_expired + $rugi_kritis + $rugi_warning;

$filter_label = [
    'all'     => 'Semua Berisiko (Peringatan, Kritis, Expired)',
    'risiko'  => 'Risiko Segera (Kritis + Expired)',
    'expired' => 'Sudah Kedaluwarsa Saja',
];

// ── EXPORT EXCEL (.xls) ──────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=Laporan_Kedaluwarsa_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr><th colspan='10' style='font-size:14px;text-align:center'>LAPORAN PRODUK MENDEKATI / SUDAH KEDALUWARSA — " . htmlspecialchars($NAMA_TOKO) . "</th></tr>";
    echo "<tr><th colspan='10' style='text-align:center'>Dicetak: " . tgl_indo($tgl) . " | Filter: " . $filter_label[$f_filter] . "</th></tr>";
    echo "<tr><th colspan='10'></th></tr>";
    echo "<tr><th colspan='2'>Estimasi Kerugian Sudah Terjadi (Expired)</th><th colspan='8'>" . number_format($rugi_expired,0,',','.') . "</th></tr>";
    echo "<tr><th colspan='2'>Estimasi Potensi Kerugian (Kritis, &le; $THRESHOLD_MERAH hari)</th><th colspan='8'>" . number_format($rugi_kritis,0,',','.') . "</th></tr>";
    echo "<tr><th colspan='2'>Perlu Diawasi (Peringatan)</th><th colspan='8'>" . number_format($rugi_warning,0,',','.') . "</th></tr>";
    echo "<tr><th colspan='2'>TOTAL POTENSI RUGI (Expired + Kritis)</th><th colspan='8'>" . number_format($total_potensi_rugi,0,',','.') . "</th></tr>";
    echo "<tr><th colspan='10'></th></tr>";
    echo "<tr>
            <th>No</th><th>No. Batch</th><th>Nama Produk</th><th>Kategori</th>
            <th>Stok</th><th>Harga Beli</th><th>Tgl Expired</th><th>Sisa Hari</th>
            <th>Status</th><th>Estimasi Nilai Rugi (Rp)</th>
          </tr>";
    $no = 1;
    foreach ($rows as $d) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($d['no_batch']) . "</td>";
        echo "<td>" . htmlspecialchars($d['nama_produk']) . "</td>";
        echo "<td>" . htmlspecialchars($d['nama_kategori']) . "</td>";
        echo "<td>" . $d['stok'] . "</td>";
        echo "<td>" . number_format($d['harga_beli'],0,',','.') . "</td>";
        echo "<td>" . tgl_indo($d['tgl_expired']) . "</td>";
        echo "<td>" . $d['sisa_hari'] . "</td>";
        echo "<td>" . $d['status']['label'] . "</td>";
        echo "<td>" . number_format($d['nilai_rugi'],0,',','.') . "</td>";
        echo "</tr>";
    }
    echo "<tr style='font-weight:bold;background:#f0f0f0'>
            <td colspan='9'>TOTAL ESTIMASI NILAI RUGI</td>
            <td>" . number_format($total_semua_rugi,0,',','.') . "</td>
          </tr>";
    echo "</table>";
    exit;
}

include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Laporan Kedaluwarsa – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
    .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
    .table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .summary-box { border-radius:10px; padding:16px; text-align:center; height:100%; }
    .summary-box .fs-val { font-size:1.5rem; font-weight:700; }
    .total-box { border-radius:12px; padding:20px; background:linear-gradient(135deg,#7c2d12,#991b1b); color:#fff; }

    .print-header { display:none; }
    @media print {
        body { background:#fff; }
        .no-print { display:none !important; }
        .print-header { display:block !important; text-align:center; margin-bottom:14px; }
        .card { box-shadow:none; border:none; }
        .card-body { padding:0 !important; }
        table { font-size:12px; }
        .total-box { background:#991b1b !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    }
  </style>
</head>
<body>
<div class="container-fluid px-4 py-3" style="max-width:1200px">

  <!-- Header cetak -->
  <div class="print-header">
    <h4 class="fw-bold mb-0"><?= htmlspecialchars($NAMA_TOKO) ?></h4>
    <?php if ($ALAMAT_TOKO): ?><div class="small"><?= htmlspecialchars($ALAMAT_TOKO) ?></div><?php endif; ?>
    <hr>
    <h5 class="fw-bold mb-0">LAPORAN PRODUK MENDEKATI / SUDAH KEDALUWARSA</h5>
    <div class="small text-muted">Dicetak: <?= tgl_indo($tgl) ?> &nbsp;|&nbsp; Filter: <?= $filter_label[$f_filter] ?></div>
  </div>

  <!-- Header halaman -->
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Laporan Kedaluwarsa & Estimasi Kerugian</h4>
      <small class="text-muted">Laporan › Early Warning System</small>
    </div>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
        <i class="bi bi-printer me-1"></i>Cetak / PDF
      </button>
      <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'excel'])) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
      </a>
    </div>
  </div>

  <!-- Filter -->
  <div class="card mb-3 no-print">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-6">
          <select name="filter" class="form-select form-select-sm">
            <?php foreach ($filter_label as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $f_filter===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="laporan_expired.php" class="btn btn-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Ringkasan Estimasi Kerugian -->
  <div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
      <div class="summary-box bg-secondary bg-opacity-10">
        <div class="fs-val text-secondary"><?= rupiah($rugi_expired) ?></div>
        <div class="text-muted small mt-1">Sudah Kedaluwarsa</div>
        <div class="text-muted small"><?= $qty_expired ?> unit</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="summary-box bg-danger bg-opacity-10">
        <div class="fs-val text-danger"><?= rupiah($rugi_kritis) ?></div>
        <div class="text-muted small mt-1">Kritis (≤ <?= $THRESHOLD_MERAH ?> hari)</div>
        <div class="text-muted small"><?= $qty_kritis ?> unit</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="summary-box bg-warning bg-opacity-10">
        <div class="fs-val text-warning"><?= rupiah($rugi_warning) ?></div>
        <div class="text-muted small mt-1">Peringatan (≤ <?= $THRESHOLD_KUNING ?> hari)</div>
        <div class="text-muted small"><?= $qty_warning ?> unit</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="total-box">
        <div class="fs-val"><?= rupiah($total_potensi_rugi) ?></div>
        <div class="small mt-1 opacity-90"><i class="bi bi-cash-coin me-1"></i>TOTAL POTENSI RUGI</div>
        <div class="small opacity-75">Expired + Kritis</div>
      </div>
    </div>
  </div>

  <p class="text-muted small no-print">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Potensi Rugi</strong> dihitung dari <code>stok &times; harga beli</code> untuk batch yang sudah kedaluwarsa
    atau berstatus kritis (sisa ≤ <?= $THRESHOLD_MERAH ?> hari). Kolom <strong>Peringatan</strong> bersifat informatif
    untuk perencanaan penjualan, belum dihitung sebagai kerugian.
  </p>

  <!-- Tabel -->
  <div class="card">
    <div class="card-header bg-dark text-white py-2 px-3 no-print">
      <span class="fw-semibold"><i class="bi bi-arrow-down-up me-2"></i>Urutan Prioritas FIFO (Expired Terdekat Duluan)</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th class="ps-3">No</th>
              <th>No. Batch</th>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th class="text-center">Stok</th>
              <th class="text-end">Harga Beli</th>
              <th>Tgl Expired</th>
              <th class="text-center">Sisa Hari</th>
              <th class="text-center">Status</th>
              <th class="text-end">Estimasi Nilai Rugi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">
              <i class="bi bi-emoji-smile me-1"></i>Tidak ada produk pada kategori risiko ini. Aman!
            </td></tr>
          <?php else: $no=1; foreach ($rows as $d): $s = $d['status']; ?>
            <tr class="<?= $s['row'] ?>">
              <td class="ps-3"><?= $no++ ?></td>
              <td><span class="badge bg-dark"><?= htmlspecialchars($d['no_batch']) ?></span></td>
              <td class="fw-semibold"><?= htmlspecialchars($d['nama_produk']) ?></td>
              <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
              <td class="text-center">
                <span class="badge <?= $d['stok']<=5?'bg-danger':'bg-primary' ?>"><?= $d['stok'] ?></span>
              </td>
              <td class="text-end"><?= rupiah($d['harga_beli']) ?></td>
              <td><?= tgl_indo($d['tgl_expired']) ?></td>
              <td class="text-center">
                <?= $d['sisa_hari']>=0 ? $d['sisa_hari'].' hari' : '<span class="text-danger fw-bold">Lewat '.abs($d['sisa_hari']).' hari</span>' ?>
              </td>
              <td class="text-center"><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
              <td class="text-end fw-semibold"><?= rupiah($d['nilai_rugi']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <?php if (!empty($rows)): ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="9" class="text-end">TOTAL ESTIMASI NILAI RUGI</td>
              <td class="text-end"><?= rupiah($total_semua_rugi) ?></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

  <!-- Rekomendasi tindakan (khusus print juga tampil) -->
  <?php if ($qty_expired > 0 || $qty_kritis > 0): ?>
  <div class="alert alert-danger mt-3">
    <i class="bi bi-lightbulb me-1"></i>
    <strong>Rekomendasi tindakan:</strong>
    <?php if ($qty_expired > 0): ?>
      Segera tarik <?= $qty_expired ?> unit produk yang sudah kedaluwarsa dari rak penjualan.
    <?php endif; ?>
    <?php if ($qty_kritis > 0): ?>
      Prioritaskan penjualan/promosi untuk <?= $qty_kritis ?> unit produk berstatus kritis sebelum melewati tanggal kedaluwarsa.
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Tanda tangan (khusus print) -->
  <div class="print-header mt-5">
    <div class="row">
      <div class="col-6"></div>
      <div class="col-6 text-center">
        <div>Mengetahui,</div>
        <div style="height:70px"></div>
        <div>( __________________________ )</div>
      </div>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>