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

  // ── DATA TRANSAKSI + HPP per transaksi ──────────────────────────────────
  $q = mysqli_query($koneksi, "
      SELECT t.*, u.nama as kasir_nama
      FROM transaksi t JOIN users u ON t.kasir_id = u.id
      WHERE DATE(t.created_at) BETWEEN '$f_dari' AND '$f_sampai'
      ORDER BY t.created_at DESC
  ");
  $rows       = [];
  $total_trx  = 0;
  $total_item = 0;
  $total_omzet = 0;   // total pendapatan (harga jual)
  $total_hpp   = 0;   // total HPP (harga beli × qty)

  while ($t = mysqli_fetch_assoc($q)) {
      $total_trx++;
      $total_item  += $t['total_item'];
      $total_omzet += $t['total_nilai'];

      // Ambil detail + harga_beli dari tabel produk untuk hitung HPP
      $q_detail = mysqli_query($koneksi, "
          SELECT dt.*, p.nama_produk, p.no_batch, p.harga_beli,
                (dt.qty * p.harga_beli) AS hpp_item
          FROM detail_transaksi dt
          JOIN produk p ON dt.produk_id = p.id
          WHERE dt.transaksi_id = {$t['id']}
          ORDER BY dt.id ASC
      ");
      $items    = [];
      $hpp_trx  = 0;
      while ($it = mysqli_fetch_assoc($q_detail)) {
          $hpp_trx += $it['hpp_item'];
          $items[]  = $it;
      }
      $t['items']    = $items;
      $t['hpp']      = $hpp_trx;
      $t['laba']     = $t['total_nilai'] - $hpp_trx;
      $t['margin']   = $t['total_nilai'] > 0
                      ? ($t['laba'] / $t['total_nilai']) * 100
                      : 0;
      $total_hpp    += $hpp_trx;
      $rows[]        = $t;
  }

  $total_laba  = $total_omzet - $total_hpp;
  $margin_total = $total_omzet > 0 ? ($total_laba / $total_omzet) * 100 : 0;
  $rata2       = $total_trx > 0 ? $total_omzet / $total_trx : 0;

  // Produk terlaris pada rentang ini (top 5 by qty) + HPP & laba per produk
  $q_top = mysqli_query($koneksi, "
      SELECT p.nama_produk,
            SUM(dt.qty)                        AS total_qty,
            SUM(dt.subtotal)                   AS total_omzet,
            SUM(dt.qty * p.harga_beli)         AS total_hpp,
            SUM(dt.subtotal - dt.qty*p.harga_beli) AS total_laba
      FROM detail_transaksi dt
      JOIN transaksi t  ON dt.transaksi_id = t.id
      JOIN produk    p  ON dt.produk_id    = p.id
      WHERE DATE(t.created_at) BETWEEN '$f_dari' AND '$f_sampai'
      GROUP BY p.nama_produk
      ORDER BY total_qty DESC
      LIMIT 5
  ");
  $top_produk = [];
  while ($tp = mysqli_fetch_assoc($q_top)) $top_produk[] = $tp;

  // ── EXPORT EXCEL — sekarang termasuk HPP & Laba ───────────────────────────
  if (isset($_GET['export']) && $_GET['export'] === 'excel') {
      header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
      header("Content-Disposition: attachment; filename=Laporan_Transaksi_" . date('Ymd_His') . ".xls");
      header("Pragma: no-cache");
      header("Expires: 0");
      echo "\xEF\xBB\xBF";
      echo "<table border='1'>";
      echo "<tr><th colspan='9' style='font-size:14px;text-align:center'>LAPORAN TRANSAKSI & LABA KOTOR — " . htmlspecialchars($NAMA_TOKO) . "</th></tr>";
      echo "<tr><th colspan='9' style='text-align:center'>Periode: " . tgl_indo($f_dari) . " s/d " . tgl_indo($f_sampai) . " | Dicetak: " . tgl_indo($tgl) . "</th></tr>";
      echo "<tr><th colspan='9'></th></tr>";
      echo "<tr>
              <th>No</th><th>Kode Transaksi</th><th>Tanggal</th><th>Kasir</th>
              <th>Jumlah Item</th>
              <th>Omzet (Rp)</th>
              <th>HPP (Rp)</th>
              <th>Laba Kotor (Rp)</th>
              <th>Margin (%)</th>
            </tr>";
      $no = 1;
      foreach ($rows as $t) {
          $margin_baris = $t['total_nilai'] > 0
              ? number_format(($t['laba']/$t['total_nilai'])*100, 1) . '%'
              : '0%';
          echo "<tr>";
          echo "<td>" . $no++ . "</td>";
          echo "<td>" . htmlspecialchars($t['kode_transaksi']) . "</td>";
          echo "<td>" . date('d-m-Y H:i', strtotime($t['created_at'])) . "</td>";
          echo "<td>" . htmlspecialchars($t['kasir_nama']) . "</td>";
          echo "<td>" . $t['total_item'] . "</td>";
          echo "<td>" . number_format($t['total_nilai'],0,',','.') . "</td>";
          echo "<td>" . number_format($t['hpp'],0,',','.') . "</td>";
          echo "<td>" . number_format($t['laba'],0,',','.') . "</td>";
          echo "<td>" . $margin_baris . "</td>";
          echo "</tr>";
      }
      echo "<tr style='font-weight:bold;background:#e8f5e9'>
              <td colspan='5'>TOTAL (" . $total_trx . " transaksi)</td>
              <td>" . number_format($total_omzet,0,',','.') . "</td>
              <td>" . number_format($total_hpp,0,',','.') . "</td>
              <td>" . number_format($total_laba,0,',','.') . "</td>
              <td>" . number_format($margin_total,1) . "%</td>
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
      .detail-row td { background:#fafbfc; }
      .btn-toggle .bi { transition:.2s; }
      .btn-toggle[aria-expanded="true"] .bi { transform:rotate(90deg); }

      /* Stat cards */
      .stat-card { border-radius:12px; padding:16px 18px; }
      .stat-card .val { font-size:1.25rem; font-weight:700; line-height:1.2; }
      .stat-card .lbl { font-size:.75rem; opacity:.75; margin-top:2px; }

      /* HPP summary box (di bawah stat cards) */
      .hpp-box { border-radius:10px; padding:14px 18px; border:1.5px solid; }
      .hpp-box .hpp-lbl { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
      .hpp-box .hpp-val { font-size:1.2rem; font-weight:700; margin-top:4px; }

      /* Margin pill di tabel */
      .margin-pill { font-size:.72rem; font-weight:700; padding:2px 8px;
                    border-radius:20px; white-space:nowrap; }
      .margin-hi  { background:#d1fae5; color:#065f46; }
      .margin-mid { background:#fef9c3; color:#713f12; }
      .margin-lo  { background:#fee2e2; color:#991b1b; }

      /* Laba baris detail */
      .laba-detail { background:#f0fdf4; font-size:.78rem; }

      .print-header { display:none; }
      @media print {
          body { background:#fff; }
          .no-print { display:none !important; }
          .print-header { display:block !important; text-align:center; margin-bottom:14px; }
          .card { box-shadow:none; border:none; }
          .card-body { padding:0 !important; }
          table { font-size:10px; }
          .collapse { display:block !important; }
          .btn-toggle { display:none !important; }
          .hpp-box { border:1px solid #ccc !important; }
      }
    </style>
  </head>
  <body>
  <div class="container-fluid px-4 py-3" style="max-width:1280px">

    <!-- Header cetak -->
    <div class="print-header">
      <h4 class="fw-bold mb-0"><?= htmlspecialchars($NAMA_TOKO) ?></h4>
      <?php if ($ALAMAT_TOKO): ?><div class="small"><?= htmlspecialchars($ALAMAT_TOKO) ?></div><?php endif; ?>
      <hr>
      <h5 class="fw-bold mb-0">LAPORAN TRANSAKSI & LABA KOTOR</h5>
      <div class="small text-muted">Periode: <?= tgl_indo($f_dari) ?> s/d <?= tgl_indo($f_sampai) ?> | Dicetak: <?= tgl_indo($tgl) ?></div>
    </div>

    <!-- Header halaman -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
      <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Laporan Transaksi</h4>
        <small class="text-muted">Riwayat Barang Keluar · HPP · Laba Kotor</small>
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

    <!-- ── RINGKASAN ATAS (4 stat cards) ─────────────────────────────────── -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-card text-white" style="background:linear-gradient(135deg,#0f3460,#16213e)">
          <div class="lbl"><i class="bi bi-receipt me-1"></i>Total Transaksi</div>
          <div class="val"><?= number_format($total_trx) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card text-white" style="background:linear-gradient(135deg,#0ea5e9,#0369a1)">
          <div class="lbl"><i class="bi bi-box me-1"></i>Total Item Terjual</div>
          <div class="val"><?= number_format($total_item) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card text-white" style="background:linear-gradient(135deg,#10b981,#047857)">
          <div class="lbl"><i class="bi bi-cash-stack me-1"></i>Total Omzet</div>
          <div class="val" style="font-size:1rem"><?= rupiah($total_omzet) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card text-white" style="background:linear-gradient(135deg,#6366f1,#4338ca)">
          <div class="lbl"><i class="bi bi-graph-up-arrow me-1"></i>Rata-rata / Transaksi</div>
          <div class="val" style="font-size:1rem"><?= rupiah($rata2) ?></div>
        </div>
      </div>
    </div>

    <!-- ── HPP & LABA KOTOR (ringkasan utama baru) ───────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="hpp-box h-100" style="border-color:#0ea5e9;background:#f0f9ff">
          <div class="hpp-lbl text-primary"><i class="bi bi-tag me-1"></i>Total Omzet (Pendapatan)</div>
          <div class="hpp-val text-primary"><?= rupiah($total_omzet) ?></div>
          <div class="small text-muted mt-1">Total nilai penjualan (harga jual × qty)</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="hpp-box h-100" style="border-color:#f59e0b;background:#fffbeb">
          <div class="hpp-lbl text-warning"><i class="bi bi-box-arrow-in-down me-1"></i>Total HPP (Harga Pokok Penjualan)</div>
          <div class="hpp-val text-warning"><?= rupiah($total_hpp) ?></div>
          <div class="small text-muted mt-1">Total modal (harga beli × qty terjual)</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="hpp-box h-100" style="border-color:<?= $total_laba >= 0 ? '#10b981' : '#ef4444' ?>;background:<?= $total_laba >= 0 ? '#f0fdf4' : '#fef2f2' ?>">
          <div class="hpp-lbl" style="color:<?= $total_laba >= 0 ? '#065f46' : '#991b1b' ?>">
            <i class="bi bi-graph-up me-1"></i>Laba Kotor (Gross Profit)
          </div>
          <div class="hpp-val" style="color:<?= $total_laba >= 0 ? '#059669' : '#dc2626' ?>">
            <?= ($total_laba >= 0 ? '' : '') . rupiah(abs($total_laba)) ?>
          </div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <span class="small text-muted">Margin:</span>
            <?php
              $mc = $margin_total >= 20 ? 'margin-hi' : ($margin_total >= 10 ? 'margin-mid' : 'margin-lo');
            ?>
            <span class="margin-pill <?= $mc ?>"><?= number_format($margin_total, 1) ?>%</span>
            <span class="small text-muted">dari omzet</span>
          </div>
          <div class="small text-muted mt-1">Omzet − HPP</div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Tabel transaksi (kiri) -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header bg-white py-3 no-print">
            <h6 class="mb-0 fw-semibold">
              <i class="bi bi-list-ul me-1 text-primary"></i>Daftar Transaksi
              <span class="badge bg-secondary ms-1"><?= $total_trx ?></span>
            </h6>
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
                    <th class="text-end">Omzet</th>
                    <th class="text-end">HPP</th>
                    <th class="text-end">Laba Kotor</th>
                    <th class="text-center no-print">Margin</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="9" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox me-1"></i>Tidak ada transaksi pada periode ini.
                  </td></tr>
                <?php else: foreach ($rows as $i => $t):
                  $mc_row = $t['margin'] >= 20 ? 'margin-hi' : ($t['margin'] >= 10 ? 'margin-mid' : 'margin-lo');
                ?>
                  <tr>
                    <td class="ps-3 no-print">
                      <button class="btn btn-sm btn-link p-0 btn-toggle" type="button"
                              data-bs-toggle="collapse" data-bs-target="#det<?= $t['id'] ?>"
                              aria-expanded="false">
                        <i class="bi bi-chevron-right"></i>
                      </button>
                    </td>
                    <td><code class="small"><?= htmlspecialchars($t['kode_transaksi']) ?></code></td>
                    <td class="small"><?= date('d-m-Y H:i', strtotime($t['created_at'])) ?></td>
                    <td class="small"><?= htmlspecialchars($t['kasir_nama']) ?></td>
                    <td class="text-center"><span class="badge bg-secondary"><?= $t['total_item'] ?></span></td>
                    <td class="text-end small fw-semibold"><?= rupiah($t['total_nilai']) ?></td>
                    <td class="text-end small text-warning-emphasis"><?= rupiah($t['hpp']) ?></td>
                    <td class="text-end small fw-bold <?= $t['laba'] >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= rupiah($t['laba']) ?>
                    </td>
                    <td class="text-center no-print">
                      <span class="margin-pill <?= $mc_row ?>"><?= number_format($t['margin'], 1) ?>%</span>
                    </td>
                  </tr>
                  <!-- Detail baris collapse -->
                  <tr class="collapse" id="det<?= $t['id'] ?>">
                    <td></td>
                    <td colspan="8" class="pb-3 ps-2">
                      <?php if (!empty($t['keterangan'])): ?>
                        <div class="small text-muted mb-2">
                          <i class="bi bi-card-text me-1"></i>Keterangan: <?= htmlspecialchars($t['keterangan']) ?>
                        </div>
                      <?php endif; ?>
                      <table class="table table-sm mb-2 bg-white border">
                        <thead>
                          <tr class="small text-muted table-light">
                            <th>Produk</th><th>No. Batch</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end">HPP Satuan</th>
                            <th class="text-end">Subtotal Jual</th>
                            <th class="text-end">Subtotal HPP</th>
                            <th class="text-end">Laba Item</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php
                          $sub_jual = 0; $sub_hpp_trx = 0;
                          foreach ($t['items'] as $it):
                            $sub_jual     += $it['subtotal'];
                            $sub_hpp_trx  += $it['hpp_item'];
                            $laba_item     = $it['subtotal'] - $it['hpp_item'];
                        ?>
                          <tr>
                            <td class="small fw-semibold"><?= htmlspecialchars($it['nama_produk']) ?></td>
                            <td class="small"><span class="badge bg-dark"><?= htmlspecialchars($it['no_batch']) ?></span></td>
                            <td class="text-center small"><?= $it['qty'] ?></td>
                            <td class="text-end small"><?= rupiah($it['harga_satuan']) ?></td>
                            <td class="text-end small text-warning-emphasis"><?= rupiah($it['harga_beli']) ?></td>
                            <td class="text-end small fw-semibold"><?= rupiah($it['subtotal']) ?></td>
                            <td class="text-end small text-warning-emphasis"><?= rupiah($it['hpp_item']) ?></td>
                            <td class="text-end small fw-bold <?= $laba_item >= 0 ? 'text-success' : 'text-danger' ?>">
                              <?= rupiah($laba_item) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="laba-detail fw-semibold">
                          <tr>
                            <td colspan="5" class="text-end small">Subtotal transaksi ini:</td>
                            <td class="text-end small"><?= rupiah($sub_jual) ?></td>
                            <td class="text-end small text-warning-emphasis"><?= rupiah($sub_hpp_trx) ?></td>
                            <td class="text-end small <?= ($sub_jual - $sub_hpp_trx) >= 0 ? 'text-success' : 'text-danger' ?>">
                              <?= rupiah($sub_jual - $sub_hpp_trx) ?>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                      <div class="text-end no-print">
                        <a href="struk.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-printer me-1"></i>Cetak Struk
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-success fw-bold">
                  <tr>
                    <td colspan="4" class="text-end ps-3">TOTAL (<?= $total_trx ?> transaksi)</td>
                    <td class="text-center"><?= $total_item ?></td>
                    <td class="text-end"><?= rupiah($total_omzet) ?></td>
                    <td class="text-end text-warning-emphasis"><?= rupiah($total_hpp) ?></td>
                    <td class="text-end text-success"><?= rupiah($total_laba) ?></td>
                    <td class="text-center no-print">
                      <?php $mc_tot = $margin_total >= 20 ? 'margin-hi' : ($margin_total >= 10 ? 'margin-mid' : 'margin-lo'); ?>
                      <span class="margin-pill <?= $mc_tot ?>"><?= number_format($margin_total, 1) ?>%</span>
                    </td>
                  </tr>
                </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel kanan -->
      <div class="col-lg-4 d-flex flex-column gap-3">

        <!-- Produk terlaris + laba per produk -->
        <div class="card">
          <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy me-1 text-warning"></i>Produk Terlaris (Top 5)</h6>
            <small class="text-muted">Qty · Omzet · HPP · Laba Kotor</small>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Produk</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Laba Kotor</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($top_produk)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
                <?php else: foreach ($top_produk as $rank => $tp):
                  $margin_tp = $tp['total_omzet'] > 0
                      ? ($tp['total_laba'] / $tp['total_omzet']) * 100 : 0;
                  $mc_tp = $margin_tp >= 20 ? 'margin-hi' : ($margin_tp >= 10 ? 'margin-mid' : 'margin-lo');
                ?>
                  <tr>
                    <td class="small">
                      <span class="badge bg-<?= $rank===0?'warning text-dark':'light text-dark border' ?> me-1">#<?= $rank+1 ?></span>
                      <?= htmlspecialchars($tp['nama_produk']) ?>
                      <div class="text-muted" style="font-size:.7rem">
                        Omzet <?= rupiah($tp['total_omzet']) ?> · HPP <?= rupiah($tp['total_hpp']) ?>
                      </div>
                    </td>
                    <td class="text-center small fw-semibold"><?= $tp['total_qty'] ?></td>
                    <td class="text-end small">
                      <div class="fw-bold text-success"><?= rupiah($tp['total_laba']) ?></div>
                      <span class="margin-pill <?= $mc_tp ?>"><?= number_format($margin_tp,1) ?>%</span>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mini ringkasan laba kotor -->
        <div class="card">
          <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart me-1 text-success"></i>Struktur Pendapatan</h6>
          </div>
          <div class="card-body">
            <?php
              $pct_hpp  = $total_omzet > 0 ? ($total_hpp  / $total_omzet) * 100 : 0;
              $pct_laba = $total_omzet > 0 ? ($total_laba / $total_omzet) * 100 : 0;
            ?>
            <!-- Progress bar -->
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">HPP</span>
                <span class="fw-semibold"><?= number_format($pct_hpp, 1) ?>%</span>
              </div>
              <div class="progress" style="height:10px;border-radius:6px">
                <div class="progress-bar bg-warning" style="width:<?= min(100,$pct_hpp) ?>%"></div>
              </div>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Laba Kotor</span>
                <span class="fw-semibold text-success"><?= number_format($pct_laba, 1) ?>%</span>
              </div>
              <div class="progress" style="height:10px;border-radius:6px">
                <div class="progress-bar bg-success" style="width:<?= min(100,max(0,$pct_laba)) ?>%"></div>
              </div>
            </div>
            <hr class="my-2">
            <div class="row g-2 text-center">
              <div class="col-6">
                <div class="small text-muted">Omzet</div>
                <div class="fw-bold small"><?= rupiah($total_omzet) ?></div>
              </div>
              <div class="col-6">
                <div class="small text-muted">HPP</div>
                <div class="fw-bold small text-warning"><?= rupiah($total_hpp) ?></div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Laba Kotor</div>
                <div class="fw-bold text-success"><?= rupiah($total_laba) ?></div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /col-lg-4 -->
    </div><!-- /row -->

    <!-- Tanda tangan (khusus print) -->
    <div class="print-header mt-5">
      <div class="row">
        <div class="col-6 text-center">
          <div>Dibuat oleh,</div>
          <div style="height:70px"></div>
          <div>( __________________________ )</div>
        </div>
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