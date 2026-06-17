<?php
    include 'koneksi.php';
    cek_login();
    $tgl = date('Y-m-d');

    // ── Kartu statistik ────────────────────────────────────────────────────────
    $r_kritis   = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk
        WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN 0 AND $THRESHOLD_MERAH AND stok > 0"));

    $r_warning  = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk
        WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN " . ($THRESHOLD_MERAH + 1) . " AND $THRESHOLD_KUNING AND stok > 0"));

    $r_aman     = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk
        WHERE DATEDIFF(tgl_expired,'$tgl') > $THRESHOLD_KUNING AND stok > 0"));

    $r_expired  = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk
        WHERE tgl_expired < '$tgl' AND stok > 0"));

    $r_produk   = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk"));

    $r_trx_hari = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM transaksi WHERE DATE(created_at) = '$tgl'"));

    // ── Grafik 7 hari terakhir ─────────────────────────────────────────────────
    $grafik_labels = [];
    $grafik_data   = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $grafik_labels[] = "'" . date('d/m', strtotime($d)) . "'";
        $q = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT COALESCE(SUM(dt.qty), 0) as t
            FROM detail_transaksi dt
            JOIN transaksi tr ON dt.transaksi_id = tr.id
            WHERE DATE(tr.created_at) = '$d'"));
        $grafik_data[] = $q['t'];
    }

    // ── Tabel FIFO — FIX: nama variabel wajib $q_fifo ─────────────────────────
    // Kolom yang tersedia di tabel produk: id, no_batch, nama_produk, kategori_id,
    // harga_beli, harga_jual, stok, stok_awal, tgl_masuk, tgl_expired, keterangan
    $q_fifo = mysqli_query($koneksi,
        "SELECT p.*, k.nama_kategori,
                DATEDIFF(p.tgl_expired, '$tgl') as sisa_hari
        FROM produk p
        JOIN kategori k ON p.kategori_id = k.id
        WHERE p.stok > 0
        ORDER BY p.tgl_expired ASC");
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
    body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
    .stat-card { border: 0; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); transition: .2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
    .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .table-fifo th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
    .badge-ews { font-size: .78rem; padding: .4em .8em; border-radius: 6px; }
    .chart-card { border: 0; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    </style>
    </head>
    <body>
    <div class="container-fluid px-4 py-3">

      <!-- Header baris atas -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold mb-0">Dashboard EWS</h5>
          <small class="text-muted">Hari ini: <?= tgl_indo($tgl) ?></small>
        </div>
        <a href="tambah.php" class="btn btn-dark btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Input Batch
        </a>
      </div>

      <!-- Kartu statistik -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-danger bg-opacity-15 text-danger"><i class="bi bi-exclamation-octagon-fill"></i></div>
              <div><div class="fw-bold fs-4 text-danger"><?= $r_kritis['t'] ?></div><div class="text-muted small">Kritis</div></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-warning bg-opacity-15 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
              <div><div class="fw-bold fs-4 text-warning"><?= $r_warning['t'] ?></div><div class="text-muted small">Peringatan</div></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-success bg-opacity-15 text-success"><i class="bi bi-check-circle-fill"></i></div>
              <div><div class="fw-bold fs-4 text-success"><?= $r_aman['t'] ?></div><div class="text-muted small">Aman</div></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-secondary bg-opacity-15 text-secondary"><i class="bi bi-x-circle-fill"></i></div>
              <div><div class="fw-bold fs-4 text-secondary"><?= $r_expired['t'] ?></div><div class="text-muted small">Expired</div></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-primary bg-opacity-15 text-primary"><i class="bi bi-box-seam-fill"></i></div>
              <div><div class="fw-bold fs-4 text-primary"><?= $r_produk['t'] ?></div><div class="text-muted small">Produk</div></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon bg-info bg-opacity-15 text-info"><i class="bi bi-cart-check-fill"></i></div>
              <div><div class="fw-bold fs-4 text-info"><?= $r_trx_hari['t'] ?></div><div class="text-muted small">Trx Hari Ini</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Grafik -->
      <div class="row g-3 mb-4">
        <div class="col-lg-8">
          <div class="card chart-card p-3">
            <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart-line me-2"></i>Pengeluaran Barang 7 Hari Terakhir</h6>
            <canvas id="chartTrx" height="100"></canvas>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card chart-card p-3 h-100">
            <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart me-2"></i>Distribusi Status EWS</h6>
            <canvas id="chartEWS" height="180"></canvas>
            <div class="mt-3">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span><span class="badge bg-danger me-1">&nbsp;</span>Kritis</span>
                <strong><?= $r_kritis['t'] ?> batch</strong>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span><span class="badge bg-warning me-1">&nbsp;</span>Peringatan</span>
                <strong><?= $r_warning['t'] ?> batch</strong>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span><span class="badge bg-success me-1">&nbsp;</span>Aman</span>
                <strong><?= $r_aman['t'] ?> batch</strong>
              </div>
              <div class="d-flex justify-content-between small text-muted">
                <span><span class="badge bg-secondary me-1">&nbsp;</span>Expired</span>
                <strong><?= $r_expired['t'] ?> batch</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabel FIFO -->
      <div class="card chart-card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
          <span class="fw-semibold">
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
              // FIX baris 157: gunakan $q_fifo (sudah didefinisikan di atas)
              while ($d = mysqli_fetch_assoc($q_fifo)):
                  $sisa = $d['sisa_hari'];
                  // FIX: status_expired() kini mengembalikan 3 elemen [$bgc, $tc, $lbl]
                  [$bgc, $tc, $lbl] = status_expired($sisa, $THRESHOLD_MERAH, $THRESHOLD_KUNING);
              ?>
              <tr>
                <td class="ps-3 text-muted"><?= $no++ ?></td>
                <td><span class="badge bg-dark fw-normal"><?= htmlspecialchars($d['no_batch']) ?></span></td>
                <td>
                  <strong><?= htmlspecialchars($d['nama_produk']) ?></strong>
                  <?php if (!empty($d['keterangan'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($d['keterangan']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
                <!-- FIX: kolom stok_sisa & satuan tidak ada di tabel — pakai stok -->
                <td class="text-center">
                  <span class="badge <?= $d['stok'] <= 5 ? 'bg-danger' : 'bg-primary' ?>">
                    <?= $d['stok'] ?> unit
                  </span>
                </td>
                <td><?= tgl_indo($d['tgl_masuk']) ?></td>
                <td><?= tgl_indo($d['tgl_expired']) ?></td>
                <td>
                  <?php if ($sisa >= 0): ?>
                    <strong><?= $sisa ?></strong> hari
                  <?php else: ?>
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
    new Chart(document.getElementById('chartTrx'), {
        type: 'bar',
        data: {
            labels: [<?= implode(',', $grafik_labels) ?>],
            datasets: [{
                label: 'Item Keluar',
                data: [<?= implode(',', $grafik_data) ?>],
                backgroundColor: 'rgba(33,37,41,.7)',
                borderRadius: 6
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('chartEWS'), {
        type: 'doughnut',
        data: {
            labels: ['Kritis', 'Peringatan', 'Aman', 'Expired'],
            datasets: [{
                data: [<?= $r_kritis['t'] ?>, <?= $r_warning['t'] ?>, <?= $r_aman['t'] ?>, <?= $r_expired['t'] ?>],
                backgroundColor: ['#dc3545', '#ffc107', '#198754', '#6c757d'],
                borderWidth: 2
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            cutout: '65%'
        }
    });
    </script>
    </body>
    </html>
