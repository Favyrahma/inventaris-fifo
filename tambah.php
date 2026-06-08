<?php
include 'koneksi.php';
include 'includes/navbar.php';

// Generate no batch otomatis
$q_last = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT no_batch FROM produk ORDER BY id DESC LIMIT 1"));
$last_num = 0;
if ($q_last) {
    preg_match('/(\d+)$/', $q_last['no_batch'], $m);
    $last_num = isset($m[1]) ? intval($m[1]) : 0;
}
$auto_batch = 'BCH-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_batch    = trim(mysqli_real_escape_string($koneksi, $_POST['no_batch']));
    $nama_produk = trim(mysqli_real_escape_string($koneksi, $_POST['nama_produk']));
    $kategori_id = intval($_POST['kategori_id']);
    $harga_beli  = floatval(str_replace(['.',','],['',''],$_POST['harga_beli']));
    $harga_jual  = floatval(str_replace(['.',','],['',''],$_POST['harga_jual']));
    $stok        = intval($_POST['stok']);
    $tgl_masuk   = $_POST['tgl_masuk'];
    $tgl_expired = $_POST['tgl_expired'];
    $keterangan  = trim(mysqli_real_escape_string($koneksi, $_POST['keterangan']));

    $q = mysqli_query($koneksi, "INSERT INTO produk
        (no_batch,nama_produk,kategori_id,harga_beli,harga_jual,stok,stok_awal,tgl_masuk,tgl_expired,keterangan)
        VALUES ('$no_batch','$nama_produk',$kategori_id,$harga_beli,$harga_jual,$stok,$stok,$tgl_masuk,'$tgl_expired','$keterangan')");

    if ($q) {
        $pid = mysqli_insert_id($koneksi);
        mysqli_query($koneksi,"INSERT INTO stok_log (produk_id,jenis,qty,stok_sebelum,stok_sesudah,keterangan)
            VALUES ($pid,'masuk',$stok,0,$stok,'Batch baru: $no_batch')");
        $pesan = 'success';
    } else {
        $pesan = 'error: ' . mysqli_error($koneksi);
    }
}

$q_kat = mysqli_query($koneksi,"SELECT * FROM kategori ORDER BY nama_kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tambah Batch – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{background:#f4f6fb;} .card{border:none;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.07);}</style>
</head>
<body>
<div class="container py-4" style="max-width:700px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Barang Masuk (Batch Baru)</h4>
        <a href="produk.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <?php if ($pesan === 'success'): ?>
        <div class="alert alert-success alert-dismissible">
            <i class="bi bi-check-circle me-1"></i>
            Batch berhasil ditambahkan! <a href="index.php" class="alert-link">Kembali ke Dashboard</a> atau tambah batch lagi di bawah.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>Gagal menyimpan data. <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="mb-0"><i class="bi bi-inbox me-1"></i>Form Input Barang Masuk (Per Batch)</h6>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nomor Batch <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="text" name="no_batch" class="form-control" value="<?= $auto_batch ?>" required>
                        </div>
                        <div class="form-text">Terisi otomatis, bisa diubah.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php while($k = mysqli_fetch_assoc($q_kat)): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Paracetamol 500mg" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Beli (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="harga_beli" class="form-control" value="0" min="0" step="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Jual (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="harga_jual" class="form-control" value="0" min="0" step="100">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Jumlah Stok <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="stok" class="form-control" min="1" required>
                            <span class="input-group-text">unit</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tanggal Masuk <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tanggal Kedaluwarsa <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_expired" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Opsional..."></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Batch</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>