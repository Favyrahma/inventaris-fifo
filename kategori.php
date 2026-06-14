<?php
include 'koneksi.php';
cek_login();
// Proteksi: hanya admin
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: indeks.php");
    exit;
}
$pesan  = '';
$mode   = $_GET['mode'] ?? 'list';   // list | tambah | edit
$id_edit = intval($_GET['id'] ?? 0);

// ── PROSES FORM ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi           = $_POST['aksi'] ?? '';
    $nama_kategori  = trim(mysqli_real_escape_string($koneksi, $_POST['nama_kategori'] ?? ''));
    $deskripsi      = trim(mysqli_real_escape_string($koneksi, $_POST['deskripsi'] ?? ''));
    
    if ($nama_kategori === '') {
        $pesan = ['type' => 'danger', 'text' => 'Nama kategori tidak boleh kosong.'];
        $mode  = ($aksi === 'simpan_edit') ? 'edit' : 'tambah';
    } else {
        if ($aksi === 'simpan_tambah') {
            // Cek duplikat
            $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id FROM kategori WHERE nama_kategori = '$nama_kategori'"));
            if ($cek) {
                $pesan = ['type' => 'warning', 'text' => "Kategori \"$nama_kategori\" sudah ada."];
                $mode  = 'tambah';
            } else {
                mysqli_query($koneksi,
                    "INSERT INTO kategori (nama_kategori, deskripsi) VALUES ('$nama_kategori','$deskripsi')");
                $pesan = ['type' => 'success', 'text' => "Kategori \"$nama_kategori\" berhasil ditambahkan."];
                $mode  = 'list';
            }
        } elseif ($aksi === 'simpan_edit') {
            $id_upd = intval($_POST['id']);
            // Cek duplikat (kecuali diri sendiri)
            $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id FROM kategori WHERE nama_kategori='$nama_kategori' AND id != $id_upd"));
            if ($cek) {
                $pesan   = ['type' => 'warning', 'text' => "Nama kategori \"$nama_kategori\" sudah digunakan."];
                $mode    = 'edit';
                $id_edit = $id_upd;
            } else {
                mysqli_query($koneksi,
                    "UPDATE kategori SET nama_kategori='$nama_kategori', deskripsi='$deskripsi' WHERE id=$id_upd");
                $pesan = ['type' => 'success', 'text' => "Kategori berhasil diperbarui."];
                $mode  = 'list';
            }
        }
    }
}

// ── HAPUS ──────────────────────────────────────────────────────────────────
if ($mode === 'hapus' && $id_edit > 0) {
    // Cek apakah kategori masih dipakai produk
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as total FROM produk WHERE kategori_id = $id_edit"));
    if ($cek['total'] > 0) {
        $pesan = ['type' => 'danger',
            'text' => "Kategori tidak dapat dihapus karena masih digunakan oleh {$cek['total']} produk."];
    } else {
        $nm = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT nama_kategori FROM kategori WHERE id=$id_edit"))['nama_kategori'];
        mysqli_query($koneksi, "DELETE FROM kategori WHERE id=$id_edit");
        $pesan = ['type' => 'success', 'text' => "Kategori \"$nm\" berhasil dihapus."];
    }
    $mode = 'list';
}

// ── DATA UNTUK VIEW ────────────────────────────────────────────────────────
$q_list = mysqli_query($koneksi,
    "SELECT k.*, COUNT(p.id) as jml_produk
    FROM kategori k LEFT JOIN produk p ON p.kategori_id = k.id
    GROUP BY k.id ORDER BY k.nama_kategori ASC");

$data_edit = [];
if ($mode === 'edit' && $id_edit > 0) {
    $data_edit = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM kategori WHERE id = $id_edit"));
    if (!$data_edit) { $mode = 'list'; }
}

include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manajemen Kategori – Inventaris FIFO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
.card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
.badge-produk { font-size:.8rem; min-width:32px; }
</style>
</head>
<body>
<div class="container-fluid px-4 py-3" style="max-width:1100px">
<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-tags me-2 text-primary"></i>Manajemen Kategori
        </h4>
        <small class="text-muted">Master Data › Kategori Produk</small>
    </div>
    <?php if ($mode !== 'tambah' && $mode !== 'edit'): ?>
    <a href="?mode=tambah" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Tambah Kategori
    </a>
    <?php endif; ?>
</div>

<!-- Alert pesan -->
<?php if (!empty($pesan)): ?>
<div class="alert alert-<?= $pesan['type'] ?> alert-dismissible d-flex align-items-center gap-2">
    <i class="bi bi-<?= $pesan['type']==='success'?'check-circle':'exclamation-triangle' ?>-fill"></i>
    <?= htmlspecialchars($pesan['text']) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
<!-- ── FORM TAMBAH / EDIT ───────────────────────────────────────────── -->
<?php if ($mode === 'tambah' || $mode === 'edit'): ?>
<div class="col-lg-5">
    <div class="card">
        <div class="card-header py-3" style="background:linear-gradient(135deg,#0f3460,#16213e)">
            <h6 class="mb-0 text-white">
                <i class="bi bi-<?= $mode==='tambah'?'plus-circle':'pencil' ?> me-1"></i>
                <?= $mode==='tambah' ? 'Tambah Kategori Baru' : 'Edit Kategori' ?>
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <?php if ($mode === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $data_edit['id'] ?>">
                <?php endif; ?>
                <input type="hidden" name="aksi" value="<?= $mode==='tambah'?'simpan_tambah':'simpan_edit' ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Nama Kategori <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-tag"></i></span>
                        <input type="text" name="nama_kategori" class="form-control"
                            placeholder="Contoh: Obat-obatan"
                            value="<?= htmlspecialchars($mode==='edit' ? $data_edit['nama_kategori'] : '') ?>"
                            required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Deskripsi</label>
                    <!-- PERBAIKAN: Menambahkan ?? '' untuk menghindari undefined index -->
                    <textarea name="deskripsi" class="form-control" rows="3"
                        placeholder="Keterangan singkat (opsional)"><?= htmlspecialchars($mode==='edit' ? ($data_edit['deskripsi'] ?? '') : '') ?></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <a href="kategori.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-<?= $mode==='tambah'?'primary':'warning text-white' ?> btn-sm">
                        <i class="bi bi-save me-1"></i>
                        <?= $mode==='tambah' ? 'Simpan' : 'Perbarui' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── TABEL DAFTAR ─────────────────────────────────────────────────── -->
<div class="col-lg-<?= ($mode==='tambah'||$mode==='edit') ? '7' : '12' ?>">
    <div class="card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-list-ul me-1 text-primary"></i>
                Daftar Kategori
                <span class="badge bg-primary ms-1">
                    <?= mysqli_num_rows($q_list) ?>
                </span>
            </h6>
            <?php if ($mode === 'list'): ?>
            <a href="?mode=tambah" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Tambah
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3" style="width:50px">No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th class="text-center">Jml Produk</th>
                            <th class="text-center" style="width:130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    if (mysqli_num_rows($q_list) === 0):
                    ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-1"></i>Belum ada kategori.
                            </td>
                        </tr>
                    <?php else: while ($k = mysqli_fetch_assoc($q_list)): ?>
                        <tr class="<?= ($mode==='edit' && $id_edit==$k['id']) ? 'table-warning' : '' ?>">
                            <td class="ps-3 text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:34px;height:34px;flex-shrink:0">
                                        <i class="bi bi-tag-fill" style="font-size:.85rem"></i>
                                    </div>
                                    <strong><?= htmlspecialchars($k['nama_kategori']) ?></strong>
                                </div>
                            </td>
                            <td class="text-muted small">
                                <!-- PERBAIKAN: Menggunakan !empty() untuk mencegah Undefined index -->
                                <?= !empty($k['deskripsi']) ? htmlspecialchars($k['deskripsi']) : '<span class="fst-italic">—</span>' ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-produk <?= $k['jml_produk'] > 0 ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= $k['jml_produk'] ?> produk
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="?mode=edit&id=<?= $k['id'] ?>" class="btn btn-outline-warning btn-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?mode=hapus&id=<?= $k['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Hapus kategori \'<?= addslashes($k['nama_kategori']) ?>\'?\nKategori yang masih dipakai produk tidak dapat dihapus.')"
                                    title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted small bg-white">
            <i class="bi bi-info-circle me-1"></i>
            Kategori yang masih memiliki produk tidak dapat dihapus.
        </div>
    </div>
</div>
</div><!-- end row -->
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>