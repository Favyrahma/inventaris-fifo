<?php
include 'koneksi.php';
include 'includes/navbar.php';

// Generate no batch otomatis
// (query ini tidak pakai input user, aman tanpa prepared statement)
$q_last   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT no_batch FROM produk ORDER BY id DESC LIMIT 1"));
$last_num = 0;
if ($q_last) {
    preg_match('/(\d+)$/', $q_last['no_batch'], $m);
    $last_num = isset($m[1]) ? intval($m[1]) : 0;
}
$auto_batch = 'BCH-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);

$pesan = '';
$err   = [];    // kumpul semua error sebelum INSERT
$old   = [];    // simpan nilai lama agar form tidak kosong setelah gagal

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Ambil & bersihkan input ──────────────────────────────────────────
    $old['no_batch']    = trim($_POST['no_batch']    ?? '');
    $old['nama_produk'] = trim($_POST['nama_produk'] ?? '');
    $old['kategori_id'] = intval($_POST['kategori_id'] ?? 0);
    $old['harga_beli']  = floatval(str_replace(['.', ','], ['', ''], $_POST['harga_beli'] ?? 0));
    $old['harga_jual']  = floatval(str_replace(['.', ','], ['', ''], $_POST['harga_jual'] ?? 0));
    $old['stok']        = intval($_POST['stok'] ?? 0);
    $old['tgl_masuk']   = $_POST['tgl_masuk']   ?? '';
    $old['tgl_expired'] = $_POST['tgl_expired'] ?? '';
    $old['keterangan']  = trim($_POST['keterangan'] ?? '');

    // ── DIHAPUS: baris mysqli_real_escape_string tidak diperlukan lagi ──
    // Prepared statement otomatis menangani escaping, jadi 3 baris ini
    // cukup dihapus seluruhnya:
    //   $no_batch    = mysqli_real_escape_string($koneksi, $old['no_batch']);
    //   $nama_produk = mysqli_real_escape_string($koneksi, $old['nama_produk']);
    //   $keterangan  = mysqli_real_escape_string($koneksi, $old['keterangan']);

    // ── Validasi PHP (server-side) ───────────────────────────────────────
    if ($old['no_batch'] === '')
        $err['no_batch']    = 'Nomor batch wajib diisi.';

    if ($old['nama_produk'] === '')
        $err['nama_produk'] = 'Nama produk wajib diisi.';

    if ($old['kategori_id'] === 0)
        $err['kategori_id'] = 'Pilih kategori produk.';

    if ($old['stok'] < 1)
        $err['stok']        = 'Jumlah stok minimal 1 unit.';

    if ($old['harga_jual'] > 0 && $old['harga_beli'] > 0 && $old['harga_jual'] < $old['harga_beli'])
        $err['harga_jual']  = 'Harga jual tidak boleh lebih kecil dari harga beli.';

    if ($old['tgl_masuk'] === '')
        $err['tgl_masuk']   = 'Tanggal masuk wajib diisi.';

    if ($old['tgl_expired'] === '')
        $err['tgl_expired'] = 'Tanggal kedaluwarsa wajib diisi.';

    // ── Validasi utama: expired HARUS lebih besar dari masuk ────────────
    if ($old['tgl_masuk'] !== '' && $old['tgl_expired'] !== '') {
        if ($old['tgl_expired'] <= $old['tgl_masuk']) {
            $err['tgl_expired'] = 'Tanggal kedaluwarsa harus SETELAH tanggal masuk ('
                                . date('d-m-Y', strtotime($old['tgl_masuk'])) . ').';
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // QUERY 1: Cek duplikat no_batch
    // ────────────────────────────────────────────────────────────────────
    //
    // SEBELUM (rentan SQL Injection):
    //   $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
    //       "SELECT id FROM produk WHERE no_batch = '$no_batch'"));
    //   if ($cek) $err['no_batch'] = "Nomor batch \"$no_batch\" sudah digunakan.";
    //
    // SESUDAH (prepared statement):
    if ($old['no_batch'] !== '' && !isset($err['no_batch'])) {
        $stmt = $koneksi->prepare("SELECT id FROM produk WHERE no_batch = ?");
        // "s" = tipe string untuk parameter pertama (?)
        $stmt->bind_param("s", $old['no_batch']);
        $stmt->execute();
        $cek = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($cek) $err['no_batch'] = "Nomor batch \"{$old['no_batch']}\" sudah digunakan.";
    }

    // ── INSERT jika tidak ada error ──────────────────────────────────────
    if (empty($err)) {

        // ────────────────────────────────────────────────────────────────
        // QUERY 2: INSERT ke tabel produk
        // ────────────────────────────────────────────────────────────────
        //
        // SEBELUM (rentan SQL Injection):
        //   $q = mysqli_query($koneksi, "INSERT INTO produk
        //       (no_batch, nama_produk, kategori_id, harga_beli, harga_jual,
        //        stok, stok_awal, tgl_masuk, tgl_expired, keterangan)
        //       VALUES
        //       ('$no_batch', '$nama_produk', {$old['kategori_id']},
        //        {$old['harga_beli']}, {$old['harga_jual']},
        //        {$old['stok']}, {$old['stok']},
        //        '{$old['tgl_masuk']}', '{$old['tgl_expired']}', '$keterangan')");
        //
        // SESUDAH:
        $stmt = $koneksi->prepare("INSERT INTO produk
            (no_batch, nama_produk, kategori_id, harga_beli, harga_jual,
             stok, stok_awal, tgl_masuk, tgl_expired, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        //
        // Penjelasan karakter tipe di bind_param("ssiddiisss", ...):
        //   s = no_batch      (string)
        //   s = nama_produk   (string)
        //   i = kategori_id   (integer)
        //   d = harga_beli    (double/float)
        //   d = harga_jual    (double/float)
        //   i = stok          (integer)
        //   i = stok_awal     (integer)
        //   s = tgl_masuk     (string, format 'YYYY-MM-DD')
        //   s = tgl_expired   (string, format 'YYYY-MM-DD')
        //   s = keterangan    (string)
        $stmt->bind_param(
            "ssiddiisss",
            $old['no_batch'],
            $old['nama_produk'],
            $old['kategori_id'],
            $old['harga_beli'],
            $old['harga_jual'],
            $old['stok'],
            $old['stok'],         // stok_awal = sama dengan stok awal masuk
            $old['tgl_masuk'],
            $old['tgl_expired'],
            $old['keterangan']
        );
        $stmt->execute();
        $ok  = ($stmt->affected_rows > 0);
        $pid = mysqli_insert_id($koneksi); // ambil ID baris yang baru diinsert
        $stmt->close();

        if ($ok) {
            // ────────────────────────────────────────────────────────────
            // QUERY 3: INSERT ke tabel stok_log
            // ────────────────────────────────────────────────────────────
            //
            // SEBELUM:
            //   mysqli_query($koneksi, "INSERT INTO stok_log
            //       (produk_id, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
            //       VALUES ($pid, 'masuk', {$old['stok']}, 0, {$old['stok']},
            //               'Batch baru: $no_batch')");
            //
            // SESUDAH:
            $jenis        = 'masuk';
            $stok_sebelum = 0;
            $ket_log      = 'Batch baru: ' . $old['no_batch'];

            $stmt2 = $koneksi->prepare("INSERT INTO stok_log
                (produk_id, jenis, qty, stok_sebelum, stok_sesudah, keterangan)
                VALUES (?, ?, ?, ?, ?, ?)");
            //
            // Penjelasan karakter tipe di bind_param("isiiis", ...):
            //   i = produk_id     (integer)
            //   s = jenis         (string: 'masuk')
            //   i = qty           (integer)
            //   i = stok_sebelum  (integer: 0)
            //   i = stok_sesudah  (integer)
            //   s = keterangan    (string)
            $stmt2->bind_param(
                "isiiis",
                $pid,
                $jenis,
                $old['stok'],
                $stok_sebelum,
                $old['stok'],
                $ket_log
            );
            $stmt2->execute();
            $stmt2->close();

            $pesan = 'success';
            $old   = [];    // kosongkan form setelah berhasil
        } else {
            $pesan = 'db_error';
        }
    }
}

$q_kat = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori");

// Helper: nilai lama jika ada, fallback ke default
function old($field, $default = '') {
    global $old;
    return htmlspecialchars($old[$field] ?? $default);
}
?>
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tambah Batch – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
        .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        /* Highlight field error */
        .is-invalid ~ .invalid-feedback { display:block; }
        /* Animasi shake saat error */
        @keyframes shake {
        0%,100%{transform:translateX(0)}
        20%,60%{transform:translateX(-6px)}
        40%,80%{transform:translateX(6px)}
        }
        .shake { animation:shake .35s ease; }
        /* Preview sisa hari */
        #preview-hari { font-size:12px; margin-top:4px; min-height:18px; }
    </style>
    </head>
    <body>
    <div class="container py-4" style="max-width:720px">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
        <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Barang Masuk (Batch Baru)
        </h4>
        <a href="produk.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if ($pesan === 'success'): ?>
        <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <div>
            Batch berhasil ditambahkan!
            <a href="indeks.php" class="alert-link">Kembali ke Dashboard</a>
            atau tambah batch baru di bawah.
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($pesan === 'db_error'): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-x-circle-fill"></i>
        Gagal menyimpan ke database. Silakan coba lagi.
        </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" id="errBanner">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Terdapat <?= count($err) ?> kesalahan pada form:</strong>
            <ul class="mb-0 mt-1">
            <?php foreach ($err as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-3" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
        <h6 class="mb-0 text-white">
            <i class="bi bi-inbox me-1"></i>Form Input Barang Masuk (Per Batch)
        </h6>
        </div>
        <div class="card-body p-4">
        <form method="POST" id="formTambah" novalidate>
            <div class="row g-3">

            <!-- No Batch -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                Nomor Batch <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                <span class="input-group-text"><i class="bi bi-hash"></i></span>
                <input type="text" name="no_batch" id="no_batch"
                        class="form-control <?= isset($err['no_batch']) ? 'is-invalid' : '' ?>"
                        value="<?= old('no_batch', $auto_batch) ?>" required>
                <?php if (isset($err['no_batch'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err['no_batch']) ?></div>
                <?php endif; ?>
                </div>
                <div class="form-text">Terisi otomatis, bisa diubah.</div>
            </div>

            <!-- Kategori -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                Kategori <span class="text-danger">*</span>
                </label>
                <select name="kategori_id" id="kategori_id"
                        class="form-select <?= isset($err['kategori_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">-- Pilih Kategori --</option>
                <?php while ($k = mysqli_fetch_assoc($q_kat)): ?>
                    <option value="<?= $k['id'] ?>"
                    <?= ($old['kategori_id'] ?? 0) == $k['id'] ? 'selected' : '' ?>>
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
                    placeholder="Contoh: Paracetamol 500mg"
                    value="<?= old('nama_produk') ?>" required>
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
                        class="form-control <?= isset($err['harga_beli']) ? 'is-invalid' : '' ?>"
                        value="<?= old('harga_beli', 0) ?>" min="0" step="100">
                </div>
            </div>

            <!-- Harga Jual -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Harga Jual (Rp)</label>
                <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" name="harga_jual" id="harga_jual"
                        class="form-control <?= isset($err['harga_jual']) ? 'is-invalid' : '' ?>"
                        value="<?= old('harga_jual', 0) ?>" min="0" step="100">
                <?php if (isset($err['harga_jual'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err['harga_jual']) ?></div>
                <?php endif; ?>
                </div>
                <div id="preview-margin" class="form-text"></div>
            </div>

            <!-- Stok -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                Jumlah Stok <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                <input type="number" name="stok"
                        class="form-control <?= isset($err['stok']) ? 'is-invalid' : '' ?>"
                        value="<?= old('stok', '') ?>" min="1" required>
                <span class="input-group-text">unit</span>
                <?php if (isset($err['stok'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err['stok']) ?></div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Tanggal Masuk -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                Tanggal Masuk <span class="text-danger">*</span>
                </label>
                <input type="date" name="tgl_masuk" id="tgl_masuk"
                    class="form-control <?= isset($err['tgl_masuk']) ? 'is-invalid' : '' ?>"
                    value="<?= old('tgl_masuk', date('Y-m-d')) ?>"
                    max="<?= date('Y-m-d') ?>" required>
                <?php if (isset($err['tgl_masuk'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['tgl_masuk']) ?></div>
                <?php endif; ?>
                <div class="form-text">Tidak boleh lebih dari hari ini.</div>
            </div>

            <!-- Tanggal Expired -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                Tanggal Kedaluwarsa <span class="text-danger">*</span>
                </label>
                <input type="date" name="tgl_expired" id="tgl_expired"
                    class="form-control <?= isset($err['tgl_expired']) ? 'is-invalid' : '' ?>"
                    value="<?= old('tgl_expired') ?>" required>
                <?php if (isset($err['tgl_expired'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($err['tgl_expired']) ?></div>
                <?php endif; ?>
                <!-- Preview sisa hari real-time -->
                <div id="preview-hari"></div>
            </div>

            <!-- Keterangan -->
            <div class="col-12">
                <label class="form-label fw-semibold">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2"
                        placeholder="Opsional..."><?= old('keterangan') ?></textarea>
            </div>

            <!-- Tombol -->
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="indeks.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary" id="btnSimpan">
                <i class="bi bi-save me-1"></i>Simpan Batch
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

        const hari  = Math.round((new Date(expired) - new Date()) / 86400000);
        const selisih = Math.round((new Date(expired) - new Date(masuk)) / 86400000);

        // Validasi: expired harus setelah masuk
        if (masuk && expired && expired <= masuk) {
            inpExpired.classList.add('is-invalid');
            // Buat atau update feedback
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
        if (!expired) { prevHari.innerHTML = ''; return; }
        let warna, teks;
        if (hari < 0)   { warna = 'text-secondary'; teks = `Sudah lewat ${Math.abs(hari)} hari`; }
        else if (hari <= 30)  { warna = 'text-danger';    teks = `Sisa ${hari} hari (Kritis)`; }
        else if (hari <= 90)  { warna = 'text-warning';   teks = `Sisa ${hari} hari (Peringatan)`; }
        else                  { warna = 'text-success';   teks = `Sisa ${hari} hari (Aman)`; }

        prevHari.innerHTML = `<span class="${warna}"><i class="bi bi-clock me-1"></i>${teks}</span>`;
    }

    /* ── 2. Update min date expired saat tanggal masuk berubah ─────────────── */
    function updateMinExpired() {
        const masuk = inpMasuk.value;
        if (masuk) {
            // Min expired = masuk + 1 hari
            const d = new Date(masuk);
            d.setDate(d.getDate() + 1);
            inpExpired.min = d.toISOString().split('T')[0];

            // Jika nilai expired saat ini tidak valid, reset
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
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        const masuk   = inpMasuk.value;
        const expired = inpExpired.value;
        const beli    = parseFloat(inpBeli.value) || 0;
        const jual    = parseFloat(inpJual.value) || 0;
        let ada_error = false;

        // Cek expired > masuk
        if (masuk && expired && expired <= masuk) {
            e.preventDefault();
            ada_error = true;
            inpExpired.classList.add('is-invalid', 'shake');
            setTimeout(() => inpExpired.classList.remove('shake'), 400);
            inpExpired.focus();
        }

        // Cek harga jual >= harga beli (jika keduanya diisi)
        if (beli > 0 && jual > 0 && jual < beli) {
            e.preventDefault();
            ada_error = true;
            inpJual.classList.add('is-invalid', 'shake');
            setTimeout(() => inpJual.classList.remove('shake'), 400);
        }

        if (ada_error) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    /* ── Pasang event listener ──────────────────────────────────────────────── */
    inpMasuk.addEventListener('change', updateMinExpired);
    inpExpired.addEventListener('change', updatePreviewHari);
    inpBeli.addEventListener('input', updateMargin);
    inpJual.addEventListener('input', updateMargin);

    // Inisialisasi saat halaman load
    updateMinExpired();
    updateMargin();

    // Scroll ke error banner jika ada
    const errBanner = document.getElementById('errBanner');
    if (errBanner) errBanner.scrollIntoView({ behavior: 'smooth', block: 'start' });
    </script>
    </body>
    </html>
