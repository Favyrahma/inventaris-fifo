    <?php
    include 'koneksi.php';
    cek_login();

    $tgl = date('Y-m-d');

    // ── FILTER ────────────────────────────────────────────────────────────────
    $f_dari    = $_GET['dari']    ?? date('Y-m-01');
    $f_sampai  = $_GET['sampai']  ?? $tgl;
    $f_jenis   = $_GET['jenis']   ?? '';          // masuk | keluar | ''
    $f_produk  = trim($_GET['produk'] ?? '');     // pencarian nama/batch
    $f_per_hal = max(10, min(100, intval($_GET['per_hal'] ?? 25)));
    $f_hal     = max(1, intval($_GET['hal'] ?? 1));

    // Validasi tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_dari))   $f_dari   = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_sampai)) $f_sampai = $tgl;
    if ($f_dari > $f_sampai) { $tmp = $f_dari; $f_dari = $f_sampai; $f_sampai = $tmp; }

    // ── QUERY UTAMA ───────────────────────────────────────────────────────────
    $where = "WHERE DATE(sl.created_at) BETWEEN '$f_dari' AND '$f_sampai'";
    if ($f_jenis === 'masuk' || $f_jenis === 'keluar') {
        $j = mysqli_real_escape_string($koneksi, $f_jenis);
        $where .= " AND sl.jenis = '$j'";
    }
    if ($f_produk !== '') {
        $p = mysqli_real_escape_string($koneksi, $f_produk);
        $where .= " AND (p.nama_produk LIKE '%$p%' OR p.no_batch LIKE '%$p%')";
    }

    // Hitung total baris (untuk paginasi)
    $q_count = mysqli_query($koneksi,
        "SELECT COUNT(*) AS total
        FROM stok_log sl
        JOIN produk p ON sl.produk_id = p.id
        $where");
    $total_rows = (int)(mysqli_fetch_assoc($q_count)['total'] ?? 0);
    $total_hal  = max(1, (int)ceil($total_rows / $f_per_hal));
    $f_hal      = min($f_hal, $total_hal);
    $offset     = ($f_hal - 1) * $f_per_hal;

    // Ringkasan (masuk / keluar / selisih)
    $q_sum = mysqli_query($koneksi,
        "SELECT
            SUM(IF(sl.jenis='masuk',  sl.qty, 0)) AS total_masuk,
            SUM(IF(sl.jenis='keluar', sl.qty, 0)) AS total_keluar,
            COUNT(*) AS total_log
        FROM stok_log sl
        JOIN produk p ON sl.produk_id = p.id
        $where");
    $sum = mysqli_fetch_assoc($q_sum);
    $total_masuk  = (int)($sum['total_masuk']  ?? 0);
    $total_keluar = (int)($sum['total_keluar'] ?? 0);
    $total_log    = (int)($sum['total_log']    ?? 0);
    $selisih      = $total_masuk - $total_keluar;

    // Data halaman ini
    $q = mysqli_query($koneksi,
        "SELECT sl.*, p.nama_produk, p.no_batch, k.nama_kategori
        FROM stok_log sl
        JOIN produk p ON sl.produk_id = p.id
        JOIN kategori k ON p.kategori_id = k.id
        $where
        ORDER BY sl.created_at DESC, sl.id DESC
        LIMIT $f_per_hal OFFSET $offset");
    $rows = [];
    while ($d = mysqli_fetch_assoc($q)) $rows[] = $d;

    // ── EXPORT EXCEL ──────────────────────────────────────────────────────────
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        // Ambil semua baris tanpa limit untuk ekspor
        $q_all = mysqli_query($koneksi,
            "SELECT sl.*, p.nama_produk, p.no_batch, k.nama_kategori
            FROM stok_log sl
            JOIN produk p ON sl.produk_id = p.id
            JOIN kategori k ON p.kategori_id = k.id
            $where
            ORDER BY sl.created_at DESC, sl.id DESC");
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=Riwayat_Stok_" . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        echo "\xEF\xBB\xBF";
        echo "<table border='1'>";
        echo "<tr><th colspan='10' style='font-size:14px;text-align:center'>RIWAYAT LOG STOK — " . htmlspecialchars($NAMA_TOKO) . "</th></tr>";
        echo "<tr><th colspan='10' style='text-align:center'>Periode: " . tgl_indo($f_dari) . " s/d " . tgl_indo($f_sampai) . " | Dicetak: " . tgl_indo($tgl) . "</th></tr>";
        echo "<tr>
                <th>No</th><th>Waktu</th><th>No. Batch</th><th>Nama Produk</th>
                <th>Kategori</th><th>Jenis</th><th>Qty</th>
                <th>Stok Sebelum</th><th>Stok Sesudah</th><th>Keterangan</th>
            </tr>";
        $no = 1;
        while ($d = mysqli_fetch_assoc($q_all)) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . $d['created_at'] . "</td>";
            echo "<td>" . htmlspecialchars($d['no_batch']) . "</td>";
            echo "<td>" . htmlspecialchars($d['nama_produk']) . "</td>";
            echo "<td>" . htmlspecialchars($d['nama_kategori']) . "</td>";
            echo "<td>" . ucfirst($d['jenis']) . "</td>";
            echo "<td>" . $d['qty'] . "</td>";
            echo "<td>" . $d['stok_sebelum'] . "</td>";
            echo "<td>" . $d['stok_sesudah'] . "</td>";
            echo "<td>" . htmlspecialchars($d['keterangan'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "<tr><td colspan='6'><strong>TOTAL</strong></td>
                <td><strong>" . $total_masuk . " masuk / " . $total_keluar . " keluar</strong></td>
                <td colspan='3'></td></tr>";
        echo "</table>";
        exit;
    }

    // ── Bangun URL param (tanpa 'hal' agar paginasi bisa tambah sendiri) ──────
    $url_params = http_build_query(array_filter([
        'dari'    => $f_dari,
        'sampai'  => $f_sampai,
        'jenis'   => $f_jenis,
        'produk'  => $f_produk,
        'per_hal' => $f_per_hal,
    ]));
    $url_base = '?' . $url_params;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Riwayat Stok Log – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
        .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        .table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; background:#f8f9fa; }
        .table td { vertical-align:middle; font-size:.88rem; }

        /* Badge jenis */
        .badge-masuk  { background:#d1fae5; color:#065f46; font-weight:600; }
        .badge-keluar { background:#fee2e2; color:#991b1b; font-weight:600; }

        /* Row highlight */
        .row-masuk  { background:#f0fdf4 !important; }
        .row-keluar { background:#fff5f5 !important; }

        /* Stat card */
        .stat-card { border-radius:12px; padding:16px 20px; color:#fff; }
        .stat-card .val { font-size:1.7rem; font-weight:700; line-height:1.1; }
        .stat-card .lbl { font-size:.8rem; opacity:.85; }

        /* Timeline indicator di kiri */
        .tl-dot { width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
        .tl-dot-masuk  { background:#10b981; }
        .tl-dot-keluar { background:#ef4444; }

        /* Print */
        .print-header { display:none; }
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .print-header { display:block !important; text-align:center; margin-bottom:14px; }
            .card { box-shadow:none; }
            .table th, .table td { font-size:10px; }
            .stat-card { color:#000 !important; background:#f3f4f6 !important; }
        }
    </style>
    </head>
    <body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid px-4 py-2" style="max-width:1280px">

    <!-- Print header -->
    <div class="print-header">
        <h4 class="fw-bold mb-0"><?= htmlspecialchars($NAMA_TOKO) ?></h4>
        <?php if ($ALAMAT_TOKO): ?><div class="small"><?= htmlspecialchars($ALAMAT_TOKO) ?></div><?php endif; ?>
        <hr>
        <h5 class="fw-bold">RIWAYAT LOG STOK</h5>
        <div class="small">Periode: <?= tgl_indo($f_dari) ?> s/d <?= tgl_indo($f_sampai) ?> | Dicetak: <?= tgl_indo($tgl) ?></div>
    </div>

    <!-- Page Title -->
    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Riwayat Stok Log</h4>
        <div class="text-muted small">Jejak audit setiap perubahan stok (barang masuk &amp; keluar)</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
        <a href="<?= $url_base ?>&export=excel" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#0f3460,#16213e)">
            <div class="lbl"><i class="bi bi-list-check me-1"></i>Total Entri Log</div>
            <div class="val"><?= number_format($total_log) ?></div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="lbl"><i class="bi bi-arrow-down-circle me-1"></i>Total Stok Masuk</div>
            <div class="val"><?= number_format($total_masuk) ?></div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
            <div class="lbl"><i class="bi bi-arrow-up-circle me-1"></i>Total Stok Keluar</div>
            <div class="val"><?= number_format($total_keluar) ?></div>
        </div>
        </div>
        <div class="col-6 col-md-3">
        <div class="stat-card" style="background:<?= $selisih >= 0 ? 'linear-gradient(135deg,#6366f1,#4f46e5)' : 'linear-gradient(135deg,#f59e0b,#b45309)' ?>">
            <div class="lbl"><i class="bi bi-calculator me-1"></i>Selisih (Masuk−Keluar)</div>
            <div class="val"><?= ($selisih >= 0 ? '+' : '') . number_format($selisih) ?></div>
        </div>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="card mb-3 no-print">
        <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold mb-1">Dari</label>
            <input type="date" name="dari" class="form-control form-control-sm" value="<?= $f_dari ?>">
            </div>
            <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold mb-1">Sampai</label>
            <input type="date" name="sampai" class="form-control form-control-sm" value="<?= $f_sampai ?>">
            </div>
            <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold mb-1">Jenis</label>
            <select name="jenis" class="form-select form-select-sm">
                <option value="" <?= $f_jenis==='' ? 'selected':'' ?>>Semua Jenis</option>
                <option value="masuk"  <?= $f_jenis==='masuk'  ? 'selected':'' ?>>Stok Masuk</option>
                <option value="keluar" <?= $f_jenis==='keluar' ? 'selected':'' ?>>Stok Keluar</option>
            </select>
            </div>
            <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold mb-1">Cari Produk / No. Batch</label>
            <input type="text" name="produk" class="form-control form-control-sm"
                    placeholder="Ketik nama atau no. batch…" value="<?= htmlspecialchars($f_produk) ?>">
            </div>
            <div class="col-6 col-md-1">
            <label class="form-label small fw-semibold mb-1">Per Hal.</label>
            <select name="per_hal" class="form-select form-select-sm">
                <?php foreach([10,25,50,100] as $opt): ?>
                <option value="<?= $opt ?>" <?= $f_per_hal==$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="riwayat_stok.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i>
            </a>
            </div>
        </form>
        </div>
    </div>

    <!-- TABEL LOG -->
    <div class="card">
        <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-journal-text me-1 text-primary"></i>
            Log Perubahan Stok
            <span class="badge bg-secondary ms-1"><?= number_format($total_rows) ?> entri</span>
        </h6>
        <span class="text-muted small">Hal <?= $f_hal ?> / <?= $total_hal ?></span>
        </div>
        <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
            <thead>
                <tr>
                <th class="ps-3" style="width:42px">#</th>
                <th>Waktu</th>
                <th>No. Batch</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th class="text-center">Jenis</th>
                <th class="text-center">Qty</th>
                <th class="text-center">Stok Sebelum</th>
                <th class="text-center">Stok Sesudah</th>
                <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                <td colspan="10" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-30"></i>
                    Tidak ada data log pada filter yang dipilih.
                </td>
                </tr>
                <?php else: ?>
                <?php $no = $offset + 1; foreach ($rows as $d):
                    $is_masuk = ($d['jenis'] === 'masuk');
                    $selisih_qty = $d['stok_sesudah'] - $d['stok_sebelum'];
                    $waktu = date('d/m/Y H:i', strtotime($d['created_at']));
                ?>
                <tr class="<?= $is_masuk ? 'row-masuk' : 'row-keluar' ?>">
                <td class="ps-3 text-muted small"><?= $no++ ?></td>
                <td class="text-nowrap small">
                    <span class="tl-dot <?= $is_masuk ? 'tl-dot-masuk' : 'tl-dot-keluar' ?> me-1"></span>
                    <?= $waktu ?>
                </td>
                <td><code class="small"><?= htmlspecialchars($d['no_batch']) ?></code></td>
                <td class="fw-semibold"><?= htmlspecialchars($d['nama_produk']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($d['nama_kategori']) ?></td>
                <td class="text-center">
                    <span class="badge <?= $is_masuk ? 'badge-masuk' : 'badge-keluar' ?> rounded-pill px-2 py-1">
                    <i class="bi <?= $is_masuk ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle' ?> me-1"></i>
                    <?= ucfirst($d['jenis']) ?>
                    </span>
                </td>
                <td class="text-center fw-bold <?= $is_masuk ? 'text-success' : 'text-danger' ?>">
                    <?= $is_masuk ? '+' : '-' ?><?= number_format($d['qty']) ?>
                </td>
                <td class="text-center text-muted"><?= number_format($d['stok_sebelum']) ?></td>
                <td class="text-center fw-semibold"><?= number_format($d['stok_sesudah']) ?></td>
                <td class="small text-muted">
                    <?php if ($d['keterangan']): ?>
                    <i class="bi bi-info-circle me-1 opacity-50"></i><?= htmlspecialchars($d['keterangan']) ?>
                    <?php else: ?>
                    <span class="opacity-30">—</span>
                    <?php endif; ?>
                </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot class="table-light">
                <tr>
                <td colspan="6" class="fw-semibold ps-3 text-end">Subtotal halaman ini:</td>
                <td class="text-center fw-bold">
                    <?php
                    $sub_masuk  = array_sum(array_map(fn($r) => $r['jenis']==='masuk'  ? $r['qty'] : 0, $rows));
                    $sub_keluar = array_sum(array_map(fn($r) => $r['jenis']==='keluar' ? $r['qty'] : 0, $rows));
                    echo "<span class='text-success'>+{$sub_masuk}</span> / <span class='text-danger'>-{$sub_keluar}</span>";
                    ?>
                </td>
                <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
            </table>
        </div>
        </div>

        <!-- PAGINASI -->
        <?php if ($total_hal > 1): ?>
        <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2 no-print">
        <div class="text-muted small">
            Menampilkan <?= $offset + 1 ?>–<?= min($offset + $f_per_hal, $total_rows) ?>
            dari <?= number_format($total_rows) ?> entri
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
            <!-- Prev -->
            <li class="page-item <?= $f_hal <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $url_base ?>&hal=<?= $f_hal - 1 ?>">
                <i class="bi bi-chevron-left"></i>
                </a>
            </li>

            <?php
                // Tampilkan maks 7 nomor halaman
                $range = 3;
                $start = max(1, $f_hal - $range);
                $end   = min($total_hal, $f_hal + $range);
                if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= $url_base ?>&hal=1">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $f_hal ? 'active' : '' ?>">
                <a class="page-link" href="<?= $url_base ?>&hal=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($end < $total_hal): ?>
                <?php if ($end < $total_hal - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= $url_base ?>&hal=<?= $total_hal ?>"><?= $total_hal ?></a></li>
            <?php endif; ?>

            <!-- Next -->
            <li class="page-item <?= $f_hal >= $total_hal ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $url_base ?>&hal=<?= $f_hal + 1 ?>">
                <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            </ul>
        </nav>
        </div>
        <?php endif; ?>
    </div><!-- /card -->

    <div class="text-muted small text-center mt-3 no-print">
        Inventaris FIFO — Riwayat Stok Log &mdash; <?= tgl_indo($tgl) ?>
    </div>

    </div><!-- /container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
