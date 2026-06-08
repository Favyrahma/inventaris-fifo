<?php
include 'koneksi.php';
include 'includes/navbar.php';

$id = intval($_GET['id'] ?? 0);
$d  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT * FROM produk WHERE id=$id"));
if (!$d) { echo "<script>alert('Data tidak ditemukan!');window.location='produk.php';</script>"; exit; }

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim(mysqli_real_escape_string($koneksi,$_POST['nama_produk']));
    $kategori_id = intval($_POST['kategori_id']);
    $harga_beli  = floatval($_POST['harga_beli']);
    $harga_jual  = floatval($_POST['harga_jual']);
    $stok        = intval($_POST['stok']);
    $tgl_masuk   = $_POST['tgl_masuk'];
    $tgl_expired = $_POST['tgl_expired'];
    $keterangan  = trim(mysqli_real_escape_string($koneksi,$_POST['keterangan']));

    $q = mysqli_query($koneksi,"UPDATE produk SET
        nama_produk='$nama_produk', kategori_id=$kategori_id,
        harga_beli=$harga_beli, harga_jual=$harga_jual, stok=$stok,
        tgl_masuk='$tgl_masuk', tgl_expired='$tgl_expired', keterangan='$keterangan'
        WHERE id=$id");

    if ($q) {
        echo "<script>alert('Data berhasil diperbarui!');window.location='produk.php';</script>";
    } else {
        $pesan = 'Gagal memperbarui: ' . mysqli_error($koneksi);
    }
}

$q_kat = mysqli_query($koneksi,"SELECT * FROM kategori ORDER BY nama_kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Batch – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{background:#f4f6fb;} .card{border:none;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.07);}</style>
</head>
<body>
<div class="container py-4" style="max-width:700px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-pencil me-2 text-warning"></i>Edit Batch: <?= htmlspecialchars($d['no_batch']) ?></h4>
        <a href="produk.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
    <?php if ($pesan): ?><div class="alert alert-danger"><?= $pesan ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">No. Batch</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($d['no_batch']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <?php while($k=mysqli_fetch_assoc($q_kat)): ?>
                                <option value="<?= $k['id'] ?>" <?= $k['id']==$d['kategori_id']?'selected':'' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" name="nama_produk" class="form-control" value="<?= htmlspecialchars($d['nama_produk']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Beli (Rp)</label>
                        <input type="number" name="harga_beli" class="form-control" value="<?= $d['harga_beli'] ?>" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" class="form-control" value="<?= $d['harga_jual'] ?>" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Stok</label>
                        <input type="number" name="stok" class="form-control" value="<?= $d['stok'] ?>" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tgl Masuk</label>
                        <input type="date" name="tgl_masuk" class="form-control" value="<?= $d['tgl_masuk'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tgl Expired</label>
                        <input type="date" name="tgl_expired" class="form-control" value="<?= $d['tgl_expired'] ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($d['keterangan']) ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="produk.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-warning text-white"><i class="bi bi-save me-1"></i>Perbarui</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>