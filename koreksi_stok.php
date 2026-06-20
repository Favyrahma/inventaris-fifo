<?php
include 'koneksi.php';
cek_login();

// Hanya admin yang boleh akses
if (($_SESSION['user_role'] ?? 'kasir') !== 'admin') {
    echo "<script>alert('Akses ditolak! Hanya Admin yang dapat melakukan koreksi stok.');window.location='indeks.php';</script>";
    exit;
}

$tgl    = date('Y-m-d');
$pesan  = '';
$jenis_pesan = '';

// ── PROSES SIMPAN KOREKSI ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'koreksi') {
    $produk_id     = intval($_POST['produk_id']);
    $stok_fisik    = intval($_POST['stok_fisik']);
    $alasan_kode   = trim(mysqli_real_escape_string($koneksi, $_POST['alasan_kode']));
    $keterangan    = trim(mysqli_real_escape_string($koneksi, $_POST['keterangan']));
    $user_id       = intval($_SESSION['user_id']);

    // Validasi
    if ($produk_id <= 0 || $stok_fisik < 0) {
        $pesan = 'Data tidak valid. Pastikan produk dipilih dan stok fisik tidak negatif.';
        $jenis_pesan = 'danger';
    } elseif (empty($alasan_kode)) {
        $pesan = 'Alasan koreksi wajib dipilih.';
        $jenis_pesan = 'danger';
    } elseif (empty($keterangan)) {
        $pesan = 'Keterangan / catatan wajib diisi.';
        $jenis_pesan = 'danger';
    } else {
        // Ambil stok saat ini
        $q_produk = mysqli_query($koneksi, "SELECT * FROM produk WHERE id = $produk_id");
        $produk   = mysqli_fetch_assoc($q_produk);

        if (!$produk) {
            $pesan = 'Produk tidak ditemukan.';
            $jenis_pesan = 'danger';
        } else {
            $stok_sebelum = intval($produk['stok']);
            $selisih      = $stok_fisik - $stok_sebelum;

            if ($selisih === 0) {
                $pesan = 'Tidak ada perubahan — stok fisik sama dengan stok sistem (' . $stok_sebelum . ').';
                $jenis_pesan = 'warning';
            } else {
                $jenis_log = ($selisih > 0) ? 'masuk' : 'keluar';
                $qty_log   = abs($selisih);

                $label_alasan = [
                    'rusak'        => 'Barang Rusak/Cacat',
                    'hilang'       => 'Kehilangan/Susut',
                    'salah_hitung' => 'Kesalahan Hitung',
                    'kadaluwarsa'  => 'Produk Kadaluwarsa Dimusnahkan',
                    'retur'        => 'Retur ke Supplier',
                    'temuan'       => 'Temuan Stok Lebih',
                    'lainnya'      => 'Lainnya',
                ];
                $label = $label_alasan[$alasan_kode] ?? $alasan_kode;
                $ket_log = "KOREKSI STOK [{$label}]: {$keterangan}";

                // Update stok di tabel produk
                mysqli_query($koneksi, "UPDATE produk SET stok = $stok_fisik WHERE id = $produk_id");

                // Catat di stok_log dengan jenis koreksi
                $safe_ket = mysqli_real_escape_string($koneksi, $ket_log);
                mysqli_query($koneksi, "INSERT INTO stok_log
                    (produk_id, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
                    VALUES ($produk_id, '$jenis_log', $qty_log, $stok_sebelum, $stok_fisik, '$safe_ket')");

                $arah = $selisih > 0 ? "bertambah +{$selisih}" : "berkurang {$selisih}";
                $pesan = "Koreksi stok berhasil! Batch <strong>{$produk['no_batch']}</strong> — "
                       . "{$produk['nama_produk']}: stok {$arah} (dari {$stok_sebelum} → {$stok_fisik}).";
                $jenis_pesan = 'success';
            }
        }
    }
}

// ── FILTER & DATA PRODUK ─────────────────────────────────────────────────
$f_nama      = trim($_GET['nama'] ?? '');
$f_kategori  = intval($_GET['kategori'] ?? 0);

$where = "WHERE 1=1";
if ($f_nama)     $where .= " AND p.nama_produk LIKE '%" . mysqli_real_escape_string($koneksi, $f_nama) . "%'";
if ($f_kategori) $where .= " AND p.kategori_id = $f_kategori";

$q_produk = mysqli_query($koneksi, "
    SELECT p.*, k.nama_kategori,
           DATEDIFF(p.tgl_expired, '$tgl') as sisa_hari
    FROM produk p
    JOIN kategori k ON p.kategori_id = k.id
    $where
    ORDER BY p.nama_produk ASC, p.tgl_expired ASC
");
$daftar_produk = [];
while ($d = mysqli_fetch_assoc($q_produk)) $daftar_produk[] = $d;

$q_kat = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori");
$kategori_list = [];
while ($k = mysqli_fetch_assoc($q_kat)) $kategori_list[] = $k;

// ── RIWAYAT KOREKSI TERAKHIR ─────────────────────────────────────────────
$q_riwayat = mysqli_query($koneksi, "
    SELECT sl.*, p.nama_produk, p.no_batch
    FROM stok_log sl
    JOIN produk p ON sl.produk_id = p.id
    WHERE sl.keterangan LIKE 'KOREKSI STOK%'
    ORDER BY sl.created_at DESC
    LIMIT 20
");
$riwayat = [];
while ($r = mysqli_fetch_assoc($q_riwayat)) $riwayat[] = $r;

include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Koreksi Stok – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .badge-selisih-plus  { background: #198754; color: #fff; }
        .badge-selisih-minus { background: #dc3545; color: #fff; }
        .table-hover tbody tr:hover { background: #eef2ff; cursor: pointer; }
        .alasan-badge { font-size: .7rem; }
        #selectedInfo { transition: all .2s; }
        .stok-input-group input:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15); }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-pulse me-2 text-warning"></i>Koreksi Stok</h4>
            <small class="text-muted">Sesuaikan stok batch akibat kerusakan, kehilangan, atau selisih hitung tanpa menghapus batch</small>
        </div>
        <a href="produk.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Produk
        </a>
    </div>

    <?php if ($pesan): ?>
    <div class="alert alert-<?= $jenis_pesan ?> alert-dismissible d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-<?= $jenis_pesan === 'success' ? 'check-circle' : ($jenis_pesan === 'warning' ? 'exclamation-triangle' : 'x-circle') ?> fs-5 mt-1"></i>
        <div><?= $pesan ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ── KOLOM KIRI: Pilih Produk ── -->
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-search me-2 text-primary"></i>Pilih Batch Produk</h6>
                </div>
                <div class="card-body">
                    <!-- Filter -->
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-6">
                            <input type="text" name="nama" class="form-control form-control-sm"
                                   placeholder="Cari nama produk..." value="<?= htmlspecialchars($f_nama) ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="kategori" class="form-select form-select-sm">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $f_kategori == $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                        </div>
                    </form>

                    <!-- Tabel produk -->
                    <div style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>No. Batch</th>
                                <th>Nama Produk</th>
                                <th class="text-center">Stok</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Pilih</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($daftar_produk)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data produk.</td></tr>
                        <?php else: ?>
                        <?php foreach ($daftar_produk as $p):
                            [$bg, $tc, $lbl] = status_expired($p['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING);
                        ?>
                        <tr class="produk-row" style="cursor:pointer;"
                            data-id="<?= $p['id'] ?>"
                            data-batch="<?= htmlspecialchars($p['no_batch']) ?>"
                            data-nama="<?= htmlspecialchars($p['nama_produk']) ?>"
                            data-stok="<?= $p['stok'] ?>"
                            data-expired="<?= tgl_indo($p['tgl_expired']) ?>"
                            data-kategori="<?= htmlspecialchars($p['nama_kategori']) ?>">
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['no_batch']) ?></span></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['nama_kategori']) ?></small>
                            </td>
                            <td class="text-center fw-bold <?= $p['stok'] == 0 ? 'text-muted' : '' ?>">
                                <?= $p['stok'] ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $bg ?> <?= $tc ?>"><?= $lbl ?></span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-warning btn-sm btn-pilih"
                                    data-id="<?= $p['id'] ?>"
                                    data-batch="<?= htmlspecialchars($p['no_batch']) ?>"
                                    data-nama="<?= htmlspecialchars($p['nama_produk']) ?>"
                                    data-stok="<?= $p['stok'] ?>"
                                    data-expired="<?= tgl_indo($p['tgl_expired']) ?>"
                                    data-kategori="<?= htmlspecialchars($p['nama_kategori']) ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="text-muted mt-2" style="font-size:.8rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Menampilkan <?= count($daftar_produk) ?> batch. Klik tombol pensil untuk memilih dan melakukan koreksi.
                    </div>
                </div>
            </div>
        </div>

        <!-- ── KOLOM KANAN: Form Koreksi ── -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-3" style="background: linear-gradient(135deg,#f59e0b,#d97706);">
                    <h6 class="mb-0 fw-semibold text-white">
                        <i class="bi bi-clipboard-check me-2"></i>Form Koreksi Stok
                    </h6>
                </div>
                <div class="card-body">

                    <!-- Info produk terpilih -->
                    <div id="selectedInfo" class="alert alert-secondary py-2 mb-3">
                        <i class="bi bi-hand-point-left me-1"></i>
                        <span class="text-muted">Pilih batch produk dari tabel di sebelah kiri.</span>
                    </div>

                    <form method="POST" id="formKoreksi">
                        <input type="hidden" name="aksi" value="koreksi">
                        <input type="hidden" name="produk_id" id="f_produk_id">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Stok Sistem Saat Ini</label>
                            <input type="text" id="stok_sistem_display" class="form-control bg-light" readonly
                                   placeholder="—" style="font-size:1.1rem; font-weight:600; color:#0d6efd;">
                        </div>

                        <div class="mb-3 stok-input-group">
                            <label class="form-label fw-semibold">
                                Stok Fisik Hasil Hitung <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-boxes"></i></span>
                                <input type="number" name="stok_fisik" id="stok_fisik" class="form-control"
                                       min="0" placeholder="Masukkan jumlah stok fisik sebenarnya"
                                       oninput="hitungSelisih()">
                                <span class="input-group-text" id="badge_selisih">—</span>
                            </div>
                            <div class="form-text">Masukkan jumlah stok yang benar-benar ada secara fisik.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Alasan Koreksi <span class="text-danger">*</span></label>
                            <select name="alasan_kode" id="alasan_kode" class="form-select" required>
                                <option value="">— Pilih Alasan —</option>
                                <optgroup label="Stok Berkurang">
                                    <option value="rusak">🔴 Barang Rusak / Cacat</option>
                                    <option value="hilang">🔴 Kehilangan / Susut</option>
                                    <option value="kadaluwarsa">🔴 Produk Kadaluwarsa Dimusnahkan</option>
                                    <option value="retur">🔴 Retur ke Supplier</option>
                                    <option value="salah_hitung">🟡 Kesalahan Hitung (Kurang)</option>
                                </optgroup>
                                <optgroup label="Stok Bertambah">
                                    <option value="temuan">🟢 Temuan Stok Lebih</option>
                                    <option value="salah_hitung">🟡 Kesalahan Hitung (Lebih)</option>
                                </optgroup>
                                <optgroup label="Lainnya">
                                    <option value="lainnya">⚪ Lainnya</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Keterangan / Catatan <span class="text-danger">*</span>
                            </label>
                            <textarea name="keterangan" rows="3" class="form-control" required
                                      placeholder="Contoh: Ditemukan 3 botol pecah saat pengecekan gudang tanggal 20 Juni 2026..."></textarea>
                        </div>

                        <!-- Preview selisih -->
                        <div id="preview_selisih" class="alert alert-light border mb-3 d-none">
                            <div class="fw-semibold mb-1"><i class="bi bi-eye me-1"></i>Preview Perubahan</div>
                            <div class="d-flex justify-content-between">
                                <span>Stok Sistem:</span> <strong id="prev_sistem">—</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Stok Fisik:</span> <strong id="prev_fisik">—</strong>
                            </div>
                            <hr class="my-1">
                            <div class="d-flex justify-content-between">
                                <span>Selisih:</span>
                                <strong id="prev_selisih_text">—</strong>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning fw-bold" id="btnKoreksi" disabled>
                                <i class="bi bi-clipboard2-check me-1"></i>Simpan Koreksi Stok
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── RIWAYAT KOREKSI ── -->
    <div class="card mt-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-secondary"></i>Riwayat Koreksi Stok (20 Terakhir)</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($riwayat)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada riwayat koreksi stok.
            </div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Batch</th>
                        <th>Produk</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-center">Selisih</th>
                        <th class="text-center">Sebelum</th>
                        <th class="text-center">Sesudah</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat as $r):
                    $selisih_r = $r['stok_sesudah'] - $r['stok_sebelum'];
                    // Ekstrak alasan dari keterangan
                    preg_match('/\[(.+?)\]/', $r['keterangan'], $m_alasan);
                    $alasan_label = $m_alasan[1] ?? '-';
                    // Ekstrak catatan setelah ': '
                    $catatan = preg_replace('/^KOREKSI STOK \[.+?\]: /', '', $r['keterangan']);
                ?>
                <tr>
                    <td class="text-nowrap" style="font-size:.8rem;">
                        <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($r['no_batch']) ?></span></td>
                    <td><?= htmlspecialchars($r['nama_produk']) ?></td>
                    <td class="text-center">
                        <?php if ($selisih_r > 0): ?>
                            <span class="badge bg-success">Bertambah</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Berkurang</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-bold <?= $selisih_r > 0 ? 'text-success' : 'text-danger' ?>">
                        <?= ($selisih_r > 0 ? '+' : '') . $selisih_r ?>
                    </td>
                    <td class="text-center"><?= $r['stok_sebelum'] ?></td>
                    <td class="text-center fw-bold"><?= $r['stok_sesudah'] ?></td>
                    <td>
                        <span class="badge bg-light text-dark border alasan-badge"><?= htmlspecialchars($alasan_label) ?></span>
                        <div style="font-size:.78rem; color:#555;"><?= htmlspecialchars($catatan) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /container -->

<script>
let stokSistem = 0;

// Tombol pilih batch
document.querySelectorAll('.btn-pilih').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        pilihProduk(this);
    });
});

// Klik seluruh baris juga bisa memilih
document.querySelectorAll('.produk-row').forEach(row => {
    row.addEventListener('click', function() {
        const btn = this.querySelector('.btn-pilih');
        pilihProduk(btn);
    });
});

function pilihProduk(btn) {
    const id       = btn.dataset.id;
    const batch    = btn.dataset.batch;
    const nama     = btn.dataset.nama;
    const stok     = parseInt(btn.dataset.stok);
    const expired  = btn.dataset.expired;
    const kategori = btn.dataset.kategori;

    stokSistem = stok;

    document.getElementById('f_produk_id').value = id;
    document.getElementById('stok_sistem_display').value = stok;
    document.getElementById('stok_fisik').value = '';
    document.getElementById('badge_selisih').textContent = '—';
    document.getElementById('badge_selisih').className = 'input-group-text';
    document.getElementById('preview_selisih').classList.add('d-none');
    document.getElementById('btnKoreksi').disabled = true;

    // Highlight baris terpilih
    document.querySelectorAll('.produk-row').forEach(r => r.classList.remove('table-warning'));
    btn.closest('tr').classList.add('table-warning');

    const info = document.getElementById('selectedInfo');
    info.className = 'alert alert-warning py-2 mb-3';
    info.innerHTML = `<strong><i class="bi bi-check-circle me-1"></i>Terpilih:</strong>
        <span class="badge bg-secondary ms-1">${batch}</span>
        <strong class="ms-2">${nama}</strong>
        <span class="text-muted ms-2">| Kategori: ${kategori}</span>
        <span class="text-muted ms-2">| Exp: ${expired}</span>`;

    document.getElementById('stok_fisik').focus();
}

function hitungSelisih() {
    const fisik = parseInt(document.getElementById('stok_fisik').value);
    const badge = document.getElementById('badge_selisih');
    const preview = document.getElementById('preview_selisih');
    const btnKoreksi = document.getElementById('btnKoreksi');

    if (isNaN(fisik) || !document.getElementById('f_produk_id').value) {
        badge.textContent = '—';
        badge.className = 'input-group-text';
        preview.classList.add('d-none');
        btnKoreksi.disabled = true;
        return;
    }

    const selisih = fisik - stokSistem;

    if (selisih === 0) {
        badge.textContent = '±0 (tidak ada perubahan)';
        badge.className = 'input-group-text text-muted';
        preview.classList.add('d-none');
        btnKoreksi.disabled = true;
    } else {
        const arah = selisih > 0 ? '+' + selisih : selisih;
        badge.textContent = arah;
        badge.className = selisih > 0
            ? 'input-group-text fw-bold text-success'
            : 'input-group-text fw-bold text-danger';

        // Update preview
        document.getElementById('prev_sistem').textContent = stokSistem;
        document.getElementById('prev_fisik').textContent = fisik;
        const ps = document.getElementById('prev_selisih_text');
        ps.textContent = (selisih > 0 ? '+' : '') + selisih;
        ps.className = selisih > 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

        preview.classList.remove('d-none');
        btnKoreksi.disabled = false;
    }
}

// Konfirmasi sebelum submit
document.getElementById('formKoreksi').addEventListener('submit', function(e) {
    const fisik = parseInt(document.getElementById('stok_fisik').value);
    const selisih = fisik - stokSistem;
    const alasan = document.getElementById('alasan_kode').value;
    if (!alasan) { e.preventDefault(); alert('Pilih alasan koreksi terlebih dahulu.'); return; }
    const arah = selisih > 0 ? `bertambah +${selisih}` : `berkurang ${selisih}`;
    if (!confirm(`Konfirmasi Koreksi Stok:\n\nStok akan ${arah} (${stokSistem} → ${fisik})\n\nLanjutkan?`)) {
        e.preventDefault();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
