<?php
include 'koneksi.php';
include 'includes/navbar.php';
$tgl = date('Y-m-d');
$search        = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
if ($search)                       $where .= " AND (p.nama_produk LIKE '%$search%' OR p.no_batch LIKE '%$search%')";
if ($filter_status === 'aman')     $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') > $THRESHOLD_KUNING";
if ($filter_status === 'warning')  $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') BETWEEN " . ($THRESHOLD_MERAH + 1) . " AND $THRESHOLD_KUNING";
if ($filter_status === 'kritis')   $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') BETWEEN 0 AND $THRESHOLD_MERAH";
if ($filter_status === 'expired')  $where .= " AND DATEDIFF(p.tgl_expired,'$tgl') < 0";
$q = mysqli_query(
  $koneksi,
  "SELECT p.*, k.nama_kategori, DATEDIFF(p.tgl_expired,'$tgl') as sisa_hari
FROM produk p JOIN kategori k ON p.kategori_id = k.id
$where ORDER BY p.tgl_expired ASC"
);
$total_semua = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produk"))['t'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Produk & Batch – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body {
      background: #f0f2f5;
      font-family: 'Segoe UI', sans-serif;
    }

    .card {
      border: 0;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    }

    /* ── Tabel Produk ── */
    .table-produk {
      font-size: 0.85rem;
    }

    .table-produk th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      padding: 0.6rem 0.5rem;
      font-weight: 600;
    }

    .table-produk td {
      padding: 0.5rem 0.5rem;
      vertical-align: middle;
    }

    .table-produk td.fw-semibold {
      font-size: 0.85rem;
    }

    .table-produk .badge {
      font-size: 0.75rem;
      padding: 0.3em 0.6em;
    }

    .table-produk .btn-sm {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
    }

    .table-produk .btn-sm i {
      font-size: 0.8rem;
    }
  </style>
</head>

<body>
  <div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0"><i class="bi bi-archive me-2 text-primary"></i>Daftar Produk & Batch</h4>
      <a href="tambah.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Tambah Batch
      </a>
    </div>
    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
          <div class="col-md-5">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" name="search" class="form-control" placeholder="Cari produk atau no. batch..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </div>
          <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="aman" <?= $filter_status === 'aman'   ? 'selected' : '' ?>>Aman</option>
              <option value="warning" <?= $filter_status === 'warning' ? 'selected' : '' ?>>Peringatan</option>
              <option value="kritis" <?= $filter_status === 'kritis' ? 'selected' : '' ?>>Kritis</option>
              <option value="expired" <?= $filter_status === 'expired' ? 'selected' : '' ?>>Sudah Kedaluwarsa</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="produk.php" class="btn btn-secondary btn-sm">Reset</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-produk">
            <thead class="table-dark">
              <tr>
                <th class="ps-3">No</th>
                <th>No. Batch</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th class="text-end">Harga Beli</th>
                <th class="text-end">Harga Jual</th>
                <th class="text-center">Stok</th>
                <th>Tgl Masuk</th>
                <th>Tgl Expired</th>
                <th>Sisa Hari</th>
                <th class="text-center">Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($q) === 0) : ?>
                <tr>
                  <td colspan="12" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox me-1"></i>Tidak ada data ditemukan.
                  </td>
                </tr>
              <?php else : ?>
                <?php
                $no = 1;
                while ($d = mysqli_fetch_assoc($q)) :
                  // FIX baris 88: getStatus() kini tersedia dari koneksi.php
                  $s = getStatus($d['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING);
                ?>
                  <tr class="<?= $s['row'] ?>">
                    <td class="ps-3"><?= $no++ ?></td>
                    <td><span class="badge bg-dark"><?= htmlspecialchars($d['no_batch']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($d['nama_produk']) ?></td>
                    <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
                    <td class="text-end"><?= rupiah($d['harga_beli']) ?></td>
                    <td class="text-end"><?= rupiah($d['harga_jual']) ?></td>
                    <td class="text-center">
                      <span class="badge <?= $d['stok'] <= 5 ? 'bg-danger' : 'bg-primary' ?>">
                        <?= $d['stok'] ?>
                      </span>
                    </td>
                    <td><?= tgl_indo($d['tgl_masuk']) ?></td>
                    <td><?= tgl_indo($d['tgl_expired']) ?></td>
                    <td>
                      <?php if ($d['sisa_hari'] >= 0) : ?>
                        <?= $d['sisa_hari'] ?> hari
                      <?php else : ?>
                        <span class="text-danger">-<?= abs($d['sisa_hari']) ?> hari</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                    </td>
                    <td class="text-center">
                      <a href="edit.php?id=<?= $d['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="hapus.php?id=<?= $d['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus batch <?= addslashes($d['no_batch']) ?>?')" title="Hapus">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer text-muted small">
        Menampilkan <?= mysqli_num_rows($q) ?> dari <?= $total_semua ?> batch terdaftar
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>