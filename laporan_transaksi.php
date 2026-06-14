<?php
include 'koneksi.php';
cek_login();

$tgl = date('Y-m-d');

// ── FILTER TANGGAL (default: awal bulan ini s/d hari ini) ─────────────────
$f_dari   = $_GET['dari']   ?? date('Y-m-01');
$f_sampai = $_GET['sampai'] ?? $tgl;

// Validasi sederhana
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_dari))   $f_dari   = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_sampai)) $f_sampai = $tgl;
if ($f_dari > $f_sampai) { $tmp = $f_dari; $f_dari = $f_sampai; $f_sampai = $tmp; }

// ── DATA TRANSAKSI ──────────────────────────────────────────────────────
$q = mysqli_query($koneksi, "
    SELECT t.*, u.nama as kasir_nama
    FROM transaksi t JOIN users u ON t.kasir_id = u.id
    WHERE DATE(t.created_at) BETWEEN '$f_dari' AND '$f_sampai'
    ORDER BY t.created_at DESC
");
$rows = [];
$total_trx = 0; $total_item = 0; $total_nilai = 0;
while ($t = mysqli_fetch_assoc($q)) {
    $total_trx++;
    $total_item  += $t['total_item'];
    $total_nilai += $t['total_nilai'];

    // Ambil detail item per transaksi (untuk tampilan & cetak)
    $q_detail = mysqli_query($koneksi, "
        SELECT dt.*, p.nama_produk, p.no_batch
        FROM detail_transaksi dt JOIN produk p ON dt.produk_id = p.id
        WHERE dt.transaksi_id = {$t['id']}
        ORDER BY dt.id ASC
    ");
    $items = [];
    while ($it = mysqli_fetch_assoc($q_detail)) $items[] = $it;
    $t['items'] = $items;

    $rows[] = $t;
}
$rata2 = $total_trx > 0 ? $total_nilai / $total_trx : 0;

// Produk terlaris pada rentang ini (top 5 by qty)
$q_top = mysqli_query($koneksi, "
    SELECT p.nama_produk, SUM(dt.qty) as total_qty, SUM(dt.subtotal) as total_nilai
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    WHERE DATE(t.created_at) BETWEEN '$f_dari' AND '$f_sampai'
    GROUP BY p.nama_produk
    ORDER BY total_qty DESC
    LIMIT 5
");
$top_produk = [];
while ($tp = mysqli_fetch_assoc($q_top)) $top_produk[] = $tp;

// ── EXPORT EXCEL (.xls) — level transaksi ─────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=Laporan_Transaksi_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr><th colspan='6' style='font-size:14px;text-align:center'>LAPORAN TRANSAKSI BARANG KELUAR — " . htmlspecialchars($NAMA_TOKO) . "</th></tr>";
    echo "<tr><th colspan='6' style='text-align:center'>Periode: " . tgl_indo($f_dari) . " s/d " . tgl_indo($f_sampai) . "</th></tr>";
    echo "<tr><th colspan='6'></th></tr>";
    echo "<tr><th>No</th><th>Kode Transaksi</th><th>Tanggal</th><th>Kasir</th><th>Jumlah Item</th><th>Total Nilai (Rp)</th></tr>";
    $no = 1;
    foreach ($rows as $t) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($t['kode_transaksi']) . "</td>";
        echo "<td>" . date('d-m-Y H:i', strtotime($t['created_at'])) . "</td>";
        echo "<td>" . htmlspecialchars($t['kasir_nama']) . "</td>";
        echo "<td>" . $t['total_item'] . "</td>";
        echo "<td>" . number_format($t['total_nilai'],0,',','.') . "</td>";
        echo "</tr>";
    }
    echo "<tr style='font-weight:bold;background:#f0f0f0'>
            <td colspan='4'>TOTAL (" . $total_trx . " transaksi)</td>
            <td>$total_item</td>
            <td>" . number_format($total_nilai,0,',','.') . "</td>
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
  <title>Laporan Transaksi – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
    .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
    .table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .summary-box { border-radius:10px; padding:14px; text-align:center; height:100%; }
    .detail-row td { background:#fafbfc; }
    .btn-toggle .bi { transition:.2s; }
    .btn-toggle[aria-expanded="true"] .bi { transform:rotate(90deg); }

    .print-header { display:none; }
    @media print {
        body { background:#fff; }
        .no-print { display:none !important; }
        .print-header { display:block !important; text-align:center; margin-bottom:14px; }
        .card { box-shadow:none; border:none; }
        .card-body { padding:0 !important; }
        table { font-size:11px; }
        .collapse { display:block !important; }
        .btn-toggle { display:none !important; }
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
    <h5 class="fw-bold mb-0">LAPORAN TRANSAKSI BARANG KELUAR</h5>
    <div class="small text-muted">Periode: <?= tgl_indo($f_dari) ?> s/d <?= tgl_indo($f_sampai) ?></div>
  </div>

  <!-- Header halaman -->
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Laporan Transaksi</h4>
      <small class="text-muted">Laporan › Riwayat Barang Keluar</small>
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

  <!-- Filter tanggal -->
  <div class="card mb-3 no-print">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-auto">
          <label class="form-label small mb-0 me-1">Dari</label>
          <input type="date" name="dari" class="form-control form-control-sm" value="<?= $f_dari ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small mb-0 me-1">Sampai</label>
          <input type="date" name="sampai" class="form-control form-control-sm" value="<?= $f_sampai ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="laporan_transaksi.php" class="btn btn-secondary btn-sm">Bulan Ini</a>
        </div>
        <div class="col-auto ms-auto">
          <div class="btn-group btn-group-sm" role="group">
            <a class="btn btn-outline-secondary" href="?dari=<?= date('Y-m-d') ?>&sampai=<?= date('Y-m-d') ?>">Hari Ini</a>
            <a class="btn btn-outline-secondary" href="?dari=<?= date('Y-m-d', strtotime('-7 days')) ?>&sampai=<?= date('Y-m-d') ?>">7 Hari</a>
            <a class="btn btn-outline-secondary" href="?dari=<?= date('Y-01-01') ?>&sampai=<?= date('Y-m-d') ?>">Tahun Ini</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Ringkasan -->
  <div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
      <div class="summary-box bg-primary bg-opacity-10">
        <div class="fs-4 fw-bold text-primary"><?= $total_trx ?></div>
        <div class="text-muted small">Total Transaksi</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="summary-box bg-info bg-opacity-10">
        <div class="fs-4 fw-bold text-info"><?= $total_item ?></div>
        <div class="text-muted small">Total Item Terjual</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="summary-box bg-success bg-opacity-10">
        <div class="fs-5 fw-bold text-success"><?= rupiah($total_nilai) ?></div>
        <div class="text-muted small">Total Nilai Penjualan</div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="summary-box bg-secondary bg-opacity-10">
        <div class="fs-5 fw-bold text-secondary"><?= rupiah($rata2) ?></div>
        <div class="text-muted small">Rata-rata / Transaksi</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Tabel transaksi -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-white py-3 no-print">
          <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-1 text-primary"></i>Daftar Transaksi</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-dark">
                <tr>
                  <th class="ps-3 no-print" style="width:30px"></th>
                  <th>Kode</th>
                  <th>Tanggal</th>
                  <th>Kasir</th>
                  <th class="text-center">Item</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">
                  <i class="bi bi-inbox me-1"></i>Tidak ada transaksi pada periode ini.
                </td></tr>
              <?php else: foreach ($rows as $i => $t): ?>
                <tr>
                  <td class="ps-3 no-print">
                    <button class="btn btn-sm btn-link p-0 btn-toggle" type="button"
                            data-bs-toggle="collapse" data-bs-target="#det<?= $t['id'] ?>"
                            aria-expanded="false">
                      <i class="bi bi-chevron-right"></i>
                    </button>
                  </td>
                  <td><code><?= htmlspecialchars($t['kode_transaksi']) ?></code></td>
                  <td><?= date('d-m-Y H:i', strtotime($t['created_at'])) ?></td>
                  <td><?= htmlspecialchars($t['kasir_nama']) ?></td>
                  <td class="text-center"><span class="badge bg-secondary"><?= $t['total_item'] ?></span></td>
                  <td class="text-end fw-semibold"><?= rupiah($t['total_nilai']) ?></td>
                </tr>
                <tr class="collapse detail-row" id="det<?= $t['id'] ?>">
                  <td></td>
                  <td colspan="5" class="pb-3">
                    <?php if (!empty($t['keterangan'])): ?>
                      <div class="small text-muted mb-2">
                        <i class="bi bi-card-text me-1"></i>Keterangan: <?= htmlspecialchars($t['keterangan']) ?>
                      </div>
                    <?php endif; ?>
                    <table class="table table-sm mb-0 bg-white">
                      <thead>
                        <tr class="small text-muted">
                          <th>Produk</th><th>No. Batch</th>
                          <th class="text-center">Qty</th>
                          <th class="text-end">Harga</th>
                          <th class="text-end">Subtotal</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php foreach ($t['items'] as $it): ?>
                        <tr>
                          <td class="small fw-semibold"><?= htmlspecialchars($it['nama_produk']) ?></td>
                          <td class="small"><span class="badge bg-dark"><?= htmlspecialchars($it['no_batch']) ?></span></td>
                          <td class="text-center small"><?= $it['qty'] ?></td>
                          <td class="text-end small"><?= rupiah($it['harga_satuan']) ?></td>
                          <td class="text-end small fw-semibold"><?= rupiah($it['subtotal']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                    <div class="text-end mt-2 no-print">
                      <a href="struk.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-printer me-1"></i>Cetak Struk
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
              <?php if (!empty($rows)): ?>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td colspan="4" class="text-end">TOTAL</td>
                  <td class="text-center"><?= $total_item ?></td>
                  <td class="text-end"><?= rupiah($total_nilai) ?></td>
                </tr>
              </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Produk terlaris -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy me-1 text-warning"></i>Produk Terlaris (Top 5)</h6>
          <small class="text-muted">Periode terpilih</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr><th>Produk</th><th class="text-center">Qty</th><th class="text-end">Nilai</th></tr>
              </thead>
              <tbody>
              <?php if (empty($top_produk)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
              <?php else: foreach ($top_produk as $rank => $tp): ?>
                <tr>
                  <td class="small">
                    <span class="badge bg-<?= $rank===0?'warning text-dark':'light text-dark border' ?> me-1">#<?= $rank+1 ?></span>
                    <?= htmlspecialchars($tp['nama_produk']) ?>
                  </td>
                  <td class="text-center small fw-semibold"><?= $tp['total_qty'] ?></td>
                  <td class="text-end small"><?= rupiah($tp['total_nilai']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
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