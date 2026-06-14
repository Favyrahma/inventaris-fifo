<?php
include 'koneksi.php';
cek_login();

$tgl = date('Y-m-d');

// ── FILTER ───────────────────────────────────────────────────────────────
$f_kategori = intval($_GET['kategori'] ?? 0);
$f_status   = $_GET['status'] ?? '';

$where = "WHERE 1=1";
if ($f_kategori > 0) $where .= " AND p.kategori_id = $f_kategori";
if ($f_status === 'aman')     $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') > $THRESHOLD_KUNING";
if ($f_status === 'warning')  $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') BETWEEN " . ($THRESHOLD_MERAH+1) . " AND $THRESHOLD_KUNING";
if ($f_status === 'kritis')   $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') BETWEEN 0 AND $THRESHOLD_MERAH";
if ($f_status === 'expired')  $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') < 0";

// ── DATA ─────────────────────────────────────────────────────────────────
$q = mysqli_query($koneksi, "
    SELECT p.*, k.nama_kategori, DATEDIFF(p.tgl_expired,'$tgl') as sisa_hari
    FROM produk p JOIN kategori k ON p.kategori_id = k.id
    $where
    ORDER BY p.tgl_expired ASC
");
$rows = [];
$total_qty = 0; $total_nilai_beli = 0; $total_nilai_jual = 0;
while ($d = mysqli_fetch_assoc($q)) {
    $d['nilai_beli'] = $d['stok'] * $d['harga_beli'];
    $d['nilai_jual'] = $d['stok'] * $d['harga_jual'];
    $total_qty        += $d['stok'];
    $total_nilai_beli += $d['nilai_beli'];
    $total_nilai_jual += $d['nilai_jual'];
    $rows[] = $d;
}

$q_kat = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori");
$kategori_list = [];
while ($k = mysqli_fetch_assoc($q_kat)) $kategori_list[] = $k;

$status_label = [
    ''        => 'Semua Status',
    'aman'    => 'Aman',
    'warning' => 'Peringatan',
    'kritis'  => 'Kritis',
    'expired' => 'Sudah Kedaluwarsa',
];

// ── EXPORT EXCEL (.xls) ──────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=Laporan_Stok_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF"; // BOM UTF-8 agar karakter Rp/° terbaca benar di Excel
    echo "<table border='1'>";
    echo "<tr><th colspan='11' style='font-size:14px;text-align:center'>LAPORAN STOK PRODUK — " . htmlspecialchars($NAMA_TOKO) . "</th></tr>";
    echo "<tr><th colspan='11' style='text-align:center'>Dicetak: " . tgl_indo($tgl) . " | Filter Kategori: " .
         ($f_kategori ? htmlspecialchars(array_values(array_filter($kategori_list, fn($k)=>$k['id']==$f_kategori))[0]['nama_kategori'] ?? '-') : 'Semua') .
         " | Status: " . $status_label[$f_status] . "</th></tr>";
    echo "<tr>
            <th>No</th><th>No. Batch</th><th>Nama Produk</th><th>Kategori</th>
            <th>Tgl Masuk</th><th>Tgl Expired</th><th>Sisa Hari</th><th>Status</th>
            <th>Stok</th><th>Nilai Beli (Rp)</th><th>Nilai Jual (Rp)</th>
          </tr>";
    $no = 1;
    foreach ($rows as $d) {
        $lbl = getStatus($d['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING)['label'];
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($d['no_batch']) . "</td>";
        echo "<td>" . htmlspecialchars($d['nama_produk']) . "</td>";
        echo "<td>" . htmlspecialchars($d['nama_kategori']) . "</td>";
        echo "<td>" . tgl_indo($d['tgl_masuk']) . "</td>";
        echo "<td>" . tgl_indo($d['tgl_expired']) . "</td>";
        echo "<td>" . $d['sisa_hari'] . "</td>";
        echo "<td>" . $lbl . "</td>";
        echo "<td>" . $d['stok'] . "</td>";
        echo "<td>" . number_format($d['nilai_beli'],0,',','.') . "</td>";
        echo "<td>" . number_format($d['nilai_jual'],0,',','.') . "</td>";
        echo "</tr>";
    }
    echo "<tr style='font-weight:bold;background:#f0f0f0'>
            <td colspan='8'>TOTAL</td>
            <td>$total_qty</td>
            <td>" . number_format($total_nilai_beli,0,',','.') . "</td>
            <td>" . number_format($total_nilai_jual,0,',','.') . "</td>
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
  <title>Laporan Stok – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
    .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
    .table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .summary-box { border-radius:10px; padding:14px; text-align:center; }

    /* ── PRINT STYLES ───────────────────────────────────────────────── */
    .print-header { display:none; }
    @media print {
        body { background:#fff; }
        .no-print { display:none !important; }
        .print-header { display:block !important; text-align:center; margin-bottom:14px; }
        .card { box-shadow:none; border:none; }
        .card-body { padding:0 !important; }
        table { font-size:12px; }
    }
  </style>
</head>
<body>
<div class="container-fluid px-4 py-3" style="max-width:1200px">

  <!-- Header cetak (hanya tampil saat print) -->
  <div class="print-header">
    <h4 class="fw-bold mb-0"><?= htmlspecialchars($NAMA_TOKO) ?></h4>
    <?php if ($ALAMAT_TOKO): ?><div class="small"><?= htmlspecialchars($ALAMAT_TOKO) ?></div><?php endif; ?>
    <?php if ($HP_TOKO): ?><div class="small">Telp/WA: <?= htmlspecialchars($HP_TOKO) ?></div><?php endif; ?>
    <hr>
    <h5 class="fw-bold mb-0">LAPORAN STOK PRODUK</h5>
    <div class="small text-muted">Dicetak: <?= tgl_indo($tgl) ?> &nbsp;|&nbsp; Status: <?= $status_label[$f_status] ?></div>
  </div>

  <!-- Header halaman -->
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-archive me-2 text-primary"></i>Laporan Stok Produk</h4>
      <small class="text-muted">Laporan › Stok per Batch</small>
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
        <div class="col-md-4">
          <select name="kategori" class="form-select form-select-sm">
            <option value="0">Semua Kategori</option>
            <?php foreach ($kategori_list as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $f_kategori==$k['id']?'selected':'' ?>>
                <?= htmlspecialchars($k['nama_kategori']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <select name="status" class="form-select form-select-sm">
            <?php foreach ($status_label as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $f_status===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="laporan_stok.php" class="btn btn-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Ringkasan -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="summary-box bg-primary bg-opacity-10">
        <div class="fs-4 fw-bold text-primary"><?= count($rows) ?> batch</div>
        <div class="text-muted small">Total Batch / <?= $total_qty ?> unit</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="summary-box bg-secondary bg-opacity-10">
        <div class="fs-4 fw-bold text-secondary"><?= rupiah($total_nilai_beli) ?></div>
        <div class="text-muted small">Total Nilai Stok (Harga Beli)</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="summary-box bg-success bg-opacity-10">
        <div class="fs-4 fw-bold text-success"><?= rupiah($total_nilai_jual) ?></div>
        <div class="text-muted small">Total Nilai Stok (Harga Jual)</div>
      </div>
    </div>
  </div>

  <!-- Tabel -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th class="ps-3">No</th>
              <th>No. Batch</th>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th>Tgl Masuk</th>
              <th>Tgl Expired</th>
              <th class="text-center">Sisa Hari</th>
              <th class="text-center">Status</th>
              <th class="text-center">Stok</th>
              <th class="text-end">Nilai Beli</th>
              <th class="text-end">Nilai Jual</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="11" class="text-center py-4 text-muted">
              <i class="bi bi-inbox me-1"></i>Tidak ada data untuk filter ini.
            </td></tr>
          <?php else: $no=1; foreach ($rows as $d):
              $s = getStatus($d['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING);
          ?>
            <tr class="<?= $s['row'] ?>">
              <td class="ps-3"><?= $no++ ?></td>
              <td><span class="badge bg-dark"><?= htmlspecialchars($d['no_batch']) ?></span></td>
              <td class="fw-semibold"><?= htmlspecialchars($d['nama_produk']) ?></td>
              <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
              <td><?= tgl_indo($d['tgl_masuk']) ?></td>
              <td><?= tgl_indo($d['tgl_expired']) ?></td>
              <td class="text-center">
                <?= $d['sisa_hari']>=0 ? $d['sisa_hari'].' hari' : '<span class="text-danger">-'.abs($d['sisa_hari']).'</span>' ?>
              </td>
              <td class="text-center"><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
              <td class="text-center">
                <span class="badge <?= $d['stok']<=5?'bg-danger':'bg-primary' ?>"><?= $d['stok'] ?></span>
              </td>
              <td class="text-end"><?= rupiah($d['nilai_beli']) ?></td>
              <td class="text-end"><?= rupiah($d['nilai_jual']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <?php if (!empty($rows)): ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="8" class="text-end">TOTAL</td>
              <td class="text-center"><?= $total_qty ?></td>
              <td class="text-end"><?= rupiah($total_nilai_beli) ?></td>
              <td class="text-end"><?= rupiah($total_nilai_jual) ?></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

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