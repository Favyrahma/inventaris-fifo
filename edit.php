    <?php
    include 'koneksi.php';
    include 'includes/navbar.php';

    $id = intval($_GET['id'] ?? 0);
    $d  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM produk WHERE id = $id"));
    if (!$d) {
        echo "<script>alert('Data tidak ditemukan!');window.location='produk.php';</script>"; exit;
    }

    $pesan = '';
    $err   = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // ── Ambil & bersihkan input ──────────────────────────────────────────
        $nama_produk = trim(mysqli_real_escape_string($koneksi, $_POST['nama_produk'] ?? ''));
        $kategori_id = intval($_POST['kategori_id'] ?? 0);
        $harga_beli  = floatval($_POST['harga_beli'] ?? 0);
        $harga_jual  = floatval($_POST['harga_jual'] ?? 0);
        $stok        = intval($_POST['stok'] ?? 0);
        $tgl_masuk   = $_POST['tgl_masuk']   ?? '';
        $tgl_expired = $_POST['tgl_expired'] ?? '';
        $keterangan  = trim(mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? ''));

        // ── Validasi PHP (server-side) ───────────────────────────────────────
        if ($nama_produk === '')
            $err['nama_produk'] = 'Nama produk wajib diisi.';

        if ($kategori_id === 0)
            $err['kategori_id'] = 'Pilih kategori produk.';

        if ($stok < 0)
            $err['stok']        = 'Stok tidak boleh negatif.';

        if ($harga_jual > 0 && $harga_beli > 0 && $harga_jual < $harga_beli)
            $err['harga_jual']  = 'Harga jual tidak boleh lebih kecil dari harga beli.';

        if ($tgl_masuk === '')
            $err['tgl_masuk']   = 'Tanggal masuk wajib diisi.';

        if ($tgl_expired === '')
            $err['tgl_expired'] = 'Tanggal kedaluwarsa wajib diisi.';

        // ── Validasi utama: expired HARUS lebih besar dari masuk ────────────
        if ($tgl_masuk !== '' && $tgl_expired !== '') {
            if ($tgl_expired <= $tgl_masuk) {
                $err['tgl_expired'] = 'Tanggal kedaluwarsa harus SETELAH tanggal masuk ('
                                    . date('d-m-Y', strtotime($tgl_masuk)) . ').';
            }
        }

        // ── UPDATE jika tidak ada error ──────────────────────────────────────
        if (empty($err)) {
            $q = mysqli_query($koneksi, "UPDATE produk SET
                nama_produk  = '$nama_produk',
                kategori_id  = $kategori_id,
                harga_beli   = $harga_beli,
                harga_jual   = $harga_jual,
                stok         = $stok,
                tgl_masuk    = '$tgl_masuk',
                tgl_expired  = '$tgl_expired',
                keterangan   = '$keterangan'
                WHERE id = $id");

            if ($q) {
                // Simpan nilai baru ke $d agar form menampilkan data terkini
                $d['nama_produk'] = htmlspecialchars_decode($nama_produk);
                $d['kategori_id'] = $kategori_id;
                $d['harga_beli']  = $harga_beli;
                $d['harga_jual']  = $harga_jual;
                $d['stok']        = $stok;
                $d['tgl_masuk']   = $tgl_masuk;
                $d['tgl_expired'] = $tgl_expired;
                $d['keterangan']  = htmlspecialchars_decode($keterangan);
                $pesan = 'success';
            } else {
                $pesan = 'db_error';
            }
        } else {
            // Isi $d dengan nilai POST agar form tidak reset ke data lama
            $d['nama_produk'] = htmlspecialchars_decode($nama_produk);
            $d['kategori_id'] = $kategori_id;
            $d['harga_beli']  = $harga_beli;
            $d['harga_jual']  = $harga_jual;
            $d['stok']        = $stok;
            $d['tgl_masuk']   = $tgl_masuk;
            $d['tgl_expired'] = $tgl_expired;
            $d['keterangan']  = htmlspecialchars_decode($keterangan);
        }
    }

    $q_kat = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Batch – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
        .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        .is-invalid ~ .invalid-feedback { display:block; }
        @keyframes shake {
        0%,100%{transform:translateX(0)}
        20%,60%{transform:translateX(-6px)}
        40%,80%{transform:translateX(6px)}
        }
        .shake { animation:shake .35s ease; }
        #preview-hari { font-size:12px; margin-top:4px; min-height:18px; }
    </style>
    </head>
    <body>
    <div class="container py-4" style="max-width:720px">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
        <i class="bi bi-pencil me-2 text-warning"></i>Edit Batch:
        <span class="badge bg-dark ms-1"><?= htmlspecialchars($d['no_batch']) ?></span>
        </h4>
        <a href="produk.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if ($pesan === 'success'): ?>
        <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        Data batch berhasil diperbarui.
        <a href="produk.php" class="alert-link ms-1">Kembali ke daftar produk</a>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'db_error'): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-x-circle-fill"></i>
        Gagal memperbarui data. Silakan coba lagi.
        </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" id="errBanner">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Terdapat <?= count($err) ?> kesalahan:</strong>
            <ul class="mb-0 mt-1">
            <?php foreach ($err as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-3" style="background:linear-gradient(135deg,#ffc107,#e0a800)">
        <h6 class="mb-0 text-dark fw-semibold">
            <i class="bi bi-pencil-square me-1"></i>Formulir Edit Data Batch
        </h6>
        </div>
        <div class="card-body p-4">
        <form method="POST" id="formEdit" novalidate>
            <div class="row g-3">

            <!-- No Batch (read-only) -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">No. Batch</label>
                <input type="text" class="form-control bg-light text-muted"
                    value="<?= htmlspecialchars($d['no_batch']) ?>" disabled>
                <div class="form-text">Nomor batch tidak dapat diubah.</div>
            </div>

            <!-- Kategori -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                Kategori <span class="text-danger">*</span>
                </label>
                <select name="kategori_id"
                        class="form-select <?= isset($err['kategori_id']) ? 'is-invalid' : '' ?>" required>
                <?php while ($k = mysqli_fetch_assoc($q_kat)): ?>
                    <option value="<?= $k['id'] ?>"
                    <?= $k['id'] == $d['kategori_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
                </select>
                <?php if (isset($err['kategori_id'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($err['kategori_id']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Nama Produk -->
            <div class="col-12">
                <label class="form-label fw-semibold">
                Nama Produk <span class="text-danger">*</span>
                </label>
                <input type="text" name="nama_produk"
                    class="form-control <?= isset($err['nama_produk']) ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars($d['nama_produk']) ?>" required>
                <?php if (isset($err['nama_produk'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['nama_produk']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Harga Beli -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Harga Beli (Rp)</label>
                <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" name="harga_beli" id="harga_beli"
                        class="form-control" value="<?= $d['harga_beli'] ?>" min="0">
                </div>
            </div>

            <!-- Harga Jual -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Harga Jual (Rp)</label>
                <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" name="harga_jual" id="harga_jual"
                        class="form-control <?= isset($err['harga_jual']) ? 'is-invalid' : '' ?>"
                        value="<?= $d['harga_jual'] ?>" min="0">
                <?php if (isset($err['harga_jual'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err['harga_jual']) ?></div>
                <?php endif; ?>
                </div>
                <div id="preview-margin" class="form-text"></div>
            </div>

            <!-- Stok -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Stok (unit)</label>
                <input type="number" name="stok"
                    class="form-control <?= isset($err['stok']) ? 'is-invalid' : '' ?>"
                    value="<?= $d['stok'] ?>" min="0">
                <?php if (isset($err['stok'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['stok']) ?></div>
                <?php endif; ?>
                <div class="form-text">Stok awal: <?= $d['stok_awal'] ?> unit.</div>
            </div>

            <!-- Tanggal Masuk -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                Tgl Masuk <span class="text-danger">*</span>
                </label>
                <input type="date" name="tgl_masuk" id="tgl_masuk"
                    class="form-control <?= isset($err['tgl_masuk']) ? 'is-invalid' : '' ?>"
                    value="<?= $d['tgl_masuk'] ?>" required>
                <?php if (isset($err['tgl_masuk'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['tgl_masuk']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Tanggal Expired -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                Tgl Kedaluwarsa <span class="text-danger">*</span>
                </label>
                <input type="date" name="tgl_expired" id="tgl_expired"
                    class="form-control <?= isset($err['tgl_expired']) ? 'is-invalid' : '' ?>"
                    value="<?= $d['tgl_expired'] ?>" required>
                <?php if (isset($err['tgl_expired'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['tgl_expired']) ?></div>
                <?php endif; ?>
                <!-- Preview sisa hari real-time -->
                <div id="preview-hari"></div>
            </div>

            <!-- Keterangan -->
            <div class="col-12">
                <label class="form-label fw-semibold">Keterangan</label>
                <textarea name="keterangan" class="form-control"
                        rows="2"><?= htmlspecialchars($d['keterangan'] ?? '') ?></textarea>
            </div>

            <!-- Tombol -->
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="produk.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning text-dark fw-semibold">
                <i class="bi bi-save me-1"></i>Perbarui Data
                </button>
            </div>

            </div>
        </form>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const inpMasuk   = document.getElementById('tgl_masuk');
    const inpExpired = document.getElementById('tgl_expired');
    const prevHari   = document.getElementById('preview-hari');
    const inpBeli    = document.getElementById('harga_beli');
    const inpJual    = document.getElementById('harga_jual');
    const prevMargin = document.getElementById('preview-margin');

    /* ── 1. Preview sisa hari + validasi real-time ─────────────────────────── */
    function updatePreviewHari() {
        const masuk   = inpMasuk.value;
        const expired = inpExpired.value;

        if (!expired) { prevHari.innerHTML = ''; return; }

        // Validasi expired > masuk
        if (masuk && expired <= masuk) {
            inpExpired.classList.add('is-invalid');
            let fb = inpExpired.nextElementSibling;
            if (!fb || !fb.classList.contains('invalid-feedback')) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                inpExpired.after(fb);
            }
            fb.textContent = 'Tanggal kedaluwarsa harus setelah tanggal masuk.';
            prevHari.innerHTML = '';
            return;
        }

        // Hapus error jika sudah valid
        inpExpired.classList.remove('is-invalid');
        const fb = inpExpired.nextElementSibling;
        if (fb && fb.classList.contains('invalid-feedback')) fb.textContent = '';

        // Preview sisa hari dari hari ini
        const hari = Math.round((new Date(expired) - new Date()) / 86400000);
        let warna, teks;
        if (hari < 0)        { warna = 'text-secondary'; teks = `Sudah lewat ${Math.abs(hari)} hari`; }
        else if (hari <= 30) { warna = 'text-danger';    teks = `Sisa ${hari} hari (Kritis)`; }
        else if (hari <= 90) { warna = 'text-warning';   teks = `Sisa ${hari} hari (Peringatan)`; }
        else                 { warna = 'text-success';   teks = `Sisa ${hari} hari (Aman)`; }

        prevHari.innerHTML = `<span class="${warna}"><i class="bi bi-clock me-1"></i>${teks}</span>`;
    }

    /* ── 2. Update min date expired saat masuk berubah ─────────────────────── */
    function updateMinExpired() {
        const masuk = inpMasuk.value;
        if (masuk) {
            const d = new Date(masuk);
            d.setDate(d.getDate() + 1);
            inpExpired.min = d.toISOString().split('T')[0];

            if (inpExpired.value && inpExpired.value <= masuk) {
                inpExpired.value = '';
                prevHari.innerHTML = '';
            }
        }
        updatePreviewHari();
    }

    /* ── 3. Preview margin harga ────────────────────────────────────────────── */
    function updateMargin() {
        const beli = parseFloat(inpBeli.value) || 0;
        const jual = parseFloat(inpJual.value) || 0;
        if (beli <= 0 || jual <= 0) { prevMargin.textContent = ''; return; }

        const margin = ((jual - beli) / beli * 100).toFixed(1);
        const laba   = jual - beli;

        if (jual < beli) {
            inpJual.classList.add('is-invalid');
            prevMargin.innerHTML = `<span class="text-danger">Harga jual di bawah harga beli!</span>`;
        } else {
            inpJual.classList.remove('is-invalid');
            prevMargin.innerHTML = `<span class="text-success">
                Laba Rp ${laba.toLocaleString('id-ID')} (margin ${margin}%)
            </span>`;
        }
    }

    /* ── 4. Validasi submit form ────────────────────────────────────────────── */
    document.getElementById('formEdit').addEventListener('submit', function(e) {
        const masuk   = inpMasuk.value;
        const expired = inpExpired.value;
        const beli    = parseFloat(inpBeli.value) || 0;
        const jual    = parseFloat(inpJual.value) || 0;
        let ada_error = false;

        if (masuk && expired && expired <= masuk) {
            e.preventDefault();
            ada_error = true;
            inpExpired.classList.add('is-invalid', 'shake');
            setTimeout(() => inpExpired.classList.remove('shake'), 400);
            inpExpired.focus();
        }

        if (beli > 0 && jual > 0 && jual < beli) {
            e.preventDefault();
            ada_error = true;
            inpJual.classList.add('is-invalid', 'shake');
            setTimeout(() => inpJual.classList.remove('shake'), 400);
        }

        if (ada_error) window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ── Pasang event listener ──────────────────────────────────────────────── */
    inpMasuk.addEventListener('change', updateMinExpired);
    inpExpired.addEventListener('change', updatePreviewHari);
    inpBeli.addEventListener('input', updateMargin);
    inpJual.addEventListener('input', updateMargin);

    // Inisialisasi saat load
    updateMinExpired();
    updateMargin();

    const errBanner = document.getElementById('errBanner');
    if (errBanner) errBanner.scrollIntoView({ behavior: 'smooth', block: 'start' });
    </script>
    </body>
    </html>
