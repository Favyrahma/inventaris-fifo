    <?php
    include 'koneksi.php';
    cek_login();

    $id_user = intval($_SESSION['user_id']);

    // ── Ambil data user saat ini ──────────────────────────────────────────────
    $user = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT id, nama, username, role, created_at FROM users WHERE id = $id_user LIMIT 1"));

    if (!$user) {
        session_destroy();
        header("Location: login.php"); exit;
    }

    // ── Inisialisasi variabel pesan ───────────────────────────────────────────
    $pesan_profil   = '';
    $pesan_password = '';
    $err_profil     = [];
    $err_password   = [];

    // ════════════════════════════════════════════════════════════════════════════
    // AKSI 1 — Update nama & username
    // ════════════════════════════════════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_profil') {

        $nama_baru     = trim($_POST['nama']     ?? '');
        $username_baru = trim($_POST['username'] ?? '');

        if ($nama_baru === '')
            $err_profil['nama'] = 'Nama lengkap tidak boleh kosong.';

        if ($username_baru === '')
            $err_profil['username'] = 'Username tidak boleh kosong.';
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username_baru))
            $err_profil['username'] = 'Username hanya boleh huruf, angka, dan underscore.';

        // Cek username duplikat (kecuali milik sendiri)
        if (empty($err_profil['username'])) {
            $un_esc = mysqli_real_escape_string($koneksi, $username_baru);
            $cek    = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id FROM users WHERE username='$un_esc' AND id != $id_user"));
            if ($cek) $err_profil['username'] = "Username \"$username_baru\" sudah digunakan pengguna lain.";
        }

        if (empty($err_profil)) {
            $nm_esc = mysqli_real_escape_string($koneksi, $nama_baru);
            $un_esc = mysqli_real_escape_string($koneksi, $username_baru);
            mysqli_query($koneksi,
                "UPDATE users SET nama='$nm_esc', username='$un_esc' WHERE id=$id_user");

            // Perbarui session agar navbar langsung berubah
            $_SESSION['user_nama'] = $nama_baru;
            $user['nama']     = $nama_baru;
            $user['username'] = $username_baru;

            $pesan_profil = 'success';
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // AKSI 2 — Ganti password
    // ════════════════════════════════════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ganti_password') {

        $pw_lama    = $_POST['pw_lama']    ?? '';
        $pw_baru    = $_POST['pw_baru']    ?? '';
        $pw_konfirm = $_POST['pw_konfirm'] ?? '';

        // Ambil hash password saat ini dari DB
        $row_pw = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT password FROM users WHERE id = $id_user LIMIT 1"));

        if ($pw_lama === '')
            $err_password['pw_lama'] = 'Masukkan password lama terlebih dahulu.';
        elseif (!password_verify($pw_lama, $row_pw['password']))
            $err_password['pw_lama'] = 'Password lama tidak sesuai.';

        if (strlen($pw_baru) < 6)
            $err_password['pw_baru'] = 'Password baru minimal 6 karakter.';
        elseif ($pw_baru === $pw_lama)
            $err_password['pw_baru'] = 'Password baru tidak boleh sama dengan password lama.';

        if (empty($err_password['pw_baru']) && $pw_baru !== $pw_konfirm)
            $err_password['pw_konfirm'] = 'Konfirmasi password tidak cocok.';

        if (empty($err_password)) {
            $hash = password_hash($pw_baru, PASSWORD_DEFAULT);
            mysqli_query($koneksi, "UPDATE users SET password='$hash' WHERE id=$id_user");
            $pesan_password = 'success';
        }
    }

    // ── Statistik aktivitas user ──────────────────────────────────────────────
    $stat_trx = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as total FROM transaksi WHERE kasir_id = $id_user"));
    $stat_batch = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as total FROM stok_log WHERE jenis='masuk' AND keterangan LIKE 'Batch baru%'"));
    $stat_trx_bulan = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as total FROM transaksi
        WHERE kasir_id=$id_user AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"));

    include 'includes/navbar.php';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Profil Saya – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
        .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }

        /* Avatar inisial besar */
        .avatar-xl {
            width:80px; height:80px; border-radius:16px;
            display:flex; align-items:center; justify-content:center;
            font-size:2rem; font-weight:700; flex-shrink:0;
            background:linear-gradient(135deg,#0f3460,#0d6efd);
            color:#fff; letter-spacing:1px;
        }

        /* Stat box */
        .stat-box {
            border-radius:10px; padding:14px 16px; text-align:center;
            background:var(--bs-gray-100);
        }
        .stat-box .val { font-size:1.6rem; font-weight:700; }
        .stat-box .lbl { font-size:11.5px; color:#6c757d; }

        /* Password strength */
        .strength-wrap { height:4px; background:#dee2e6; border-radius:2px; margin-top:6px; }
        .strength-bar  { height:100%; border-radius:2px; transition:width .25s, background .25s; width:0; }

        /* Animasi shake error */
        @keyframes shake {
        0%,100%{transform:translateX(0)}
        20%,60%{transform:translateX(-5px)}
        40%,80%{transform:translateX(5px)}
        }
        .shake { animation:shake .3s ease; }

        /* Ikon kunci berputar saat sukses */
        @keyframes pop {
        0%{transform:scale(1)} 50%{transform:scale(1.3)} 100%{transform:scale(1)}
        }
        .pop { animation:pop .4s ease; }
    </style>
    </head>
    <body>
    <div class="container py-4" style="max-width:860px">

    <!-- ── Header halaman ──────────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Profil Saya</h4>
        <small class="text-muted">Kelola informasi akun dan keamanan</small>
        </div>
        <a href="indeks.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <!-- ── Kartu identitas ─────────────────────────────────────────────── -->
    <div class="card mb-3 p-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-xl">
            <?= mb_strtoupper(mb_substr($user['nama'], 0, 2)) ?>
        </div>
        <div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['nama']) ?></h5>
            <div class="text-muted small mb-1">
            <i class="bi bi-at me-1"></i><?= htmlspecialchars($user['username']) ?>
            </div>
            <span class="badge <?= $user['role']==='admin'?'bg-danger':'bg-info text-dark' ?>">
            <i class="bi bi-<?= $user['role']==='admin'?'shield-fill':'person-fill' ?> me-1"></i>
            <?= ucfirst($user['role']) ?>
            </span>
            <span class="badge bg-secondary ms-1">
            <i class="bi bi-calendar me-1"></i>
            Bergabung <?= tgl_indo(date('Y-m-d', strtotime($user['created_at']))) ?>
            </span>
        </div>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            <div class="stat-box">
            <div class="val text-primary"><?= $stat_trx['total'] ?></div>
            <div class="lbl">Total Transaksi</div>
            </div>
            <div class="stat-box">
            <div class="val text-success"><?= $stat_trx_bulan['total'] ?></div>
            <div class="lbl">Trx Bulan Ini</div>
            </div>
        </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- ══════════════════════════════════════════════════════════════════
            KOLOM KIRI — Form Edit Profil
        ══════════════════════════════════════════════════════════════════ -->
        <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#0f3460,#16213e)">
            <h6 class="mb-0 text-white">
                <i class="bi bi-person-gear me-1"></i>Informasi Akun
            </h6>
            </div>
            <div class="card-body p-4">

            <?php if ($pesan_profil === 'success'): ?>
                <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 py-2">
                <i class="bi bi-check-circle-fill"></i>
                Profil berhasil diperbarui.
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($err_profil)): ?>
                <div class="alert alert-warning py-2" id="errProfil">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?= htmlspecialchars(array_values($err_profil)[0]) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formProfil" novalidate>
                <input type="hidden" name="aksi" value="update_profil">

                <!-- Nama Lengkap -->
                <div class="mb-3">
                <label class="form-label fw-semibold">
                    Nama Lengkap <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="nama" id="inp_nama"
                        class="form-control <?= isset($err_profil['nama']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars($user['nama']) ?>" required>
                    <?php if (isset($err_profil['nama'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err_profil['nama']) ?></div>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Username -->
                <div class="mb-3">
                <label class="form-label fw-semibold">
                    Username <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-at"></i></span>
                    <input type="text" name="username" id="inp_username"
                        class="form-control <?= isset($err_profil['username']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars($user['username']) ?>"
                        pattern="[a-zA-Z0-9_]+" required>
                    <?php if (isset($err_profil['username'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err_profil['username']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-text">Hanya huruf, angka, dan underscore (_).</div>
                </div>

                <!-- Role (read-only) -->
                <div class="mb-4">
                <label class="form-label fw-semibold">Role / Hak Akses</label>
                <div class="input-group">
                    <span class="input-group-text">
                    <i class="bi bi-<?= $user['role']==='admin'?'shield-fill':'person-fill' ?>"></i>
                    </span>
                    <input type="text" class="form-control bg-light text-muted"
                        value="<?= ucfirst($user['role']) ?>" disabled>
                </div>
                <div class="form-text">Role hanya dapat diubah oleh admin lain.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-save me-1"></i>Simpan Perubahan Profil
                </button>
            </form>
            </div>
        </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
            KOLOM KANAN — Form Ganti Password
        ══════════════════════════════════════════════════════════════════ -->
        <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header py-3" style="background:linear-gradient(135deg,#712B13,#D85A30)">
            <h6 class="mb-0 text-white">
                <i class="bi bi-shield-lock me-1"></i>Ganti Password
            </h6>
            </div>
            <div class="card-body p-4">

            <?php if ($pesan_password === 'success'): ?>
                <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 py-2">
                <i class="bi bi-lock-fill pop" id="lockIcon"></i>
                Password berhasil diubah. Gunakan password baru untuk login berikutnya.
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($err_password)): ?>
                <div class="alert alert-danger py-2" id="errPassword">
                <i class="bi bi-x-circle-fill me-1"></i>
                <?= htmlspecialchars(array_values($err_password)[0]) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formPassword" novalidate autocomplete="off">
                <input type="hidden" name="aksi" value="ganti_password">

                <!-- Password Lama -->
                <div class="mb-3">
                <label class="form-label fw-semibold">
                    Password Lama <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="pw_lama" id="pw_lama"
                        class="form-control <?= isset($err_password['pw_lama']) ? 'is-invalid' : '' ?>"
                        placeholder="Masukkan password saat ini" autocomplete="current-password" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="togglePass('pw_lama', this)" tabindex="-1">
                    <i class="bi bi-eye"></i>
                    </button>
                    <?php if (isset($err_password['pw_lama'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err_password['pw_lama']) ?></div>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Password Baru -->
                <div class="mb-2">
                <label class="form-label fw-semibold">
                    Password Baru <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="pw_baru" id="pw_baru"
                        class="form-control <?= isset($err_password['pw_baru']) ? 'is-invalid' : '' ?>"
                        placeholder="Min. 6 karakter" autocomplete="new-password"
                        oninput="cekKekuatan(this.value)" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="togglePass('pw_baru', this)" tabindex="-1">
                    <i class="bi bi-eye"></i>
                    </button>
                    <?php if (isset($err_password['pw_baru'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err_password['pw_baru']) ?></div>
                    <?php endif; ?>
                </div>
                <!-- Strength bar -->
                <div class="strength-wrap mt-2">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div id="strengthLabel" class="form-text" style="min-height:18px"></div>
                </div>

                <!-- Konfirmasi Password Baru -->
                <div class="mb-4">
                <label class="form-label fw-semibold">
                    Konfirmasi Password Baru <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                    <input type="password" name="pw_konfirm" id="pw_konfirm"
                        class="form-control <?= isset($err_password['pw_konfirm']) ? 'is-invalid' : '' ?>"
                        placeholder="Ulangi password baru" autocomplete="new-password"
                        oninput="cekMatch()" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="togglePass('pw_konfirm', this)" tabindex="-1">
                    <i class="bi bi-eye"></i>
                    </button>
                    <?php if (isset($err_password['pw_konfirm'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($err_password['pw_konfirm']) ?></div>
                    <?php endif; ?>
                </div>
                <div id="matchInfo" class="form-text" style="min-height:18px"></div>
                </div>

                <!-- Tips keamanan -->
                <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12px">
                <i class="bi bi-lightbulb me-1 text-warning"></i>
                <strong>Tips password kuat:</strong> Gunakan kombinasi huruf besar, huruf kecil, angka, dan simbol. Minimal 8 karakter.
                </div>

                <button type="submit" class="btn btn-danger w-100" id="btnGanti">
                <i class="bi bi-shield-lock me-1"></i>Ganti Password
                </button>
            </form>
            </div>
        </div>
        </div>

    </div><!-- end row -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    /* ── Tampilkan/sembunyikan password ──────────────────────────────────────── */
    function togglePass(id, btn) {
        const inp  = document.getElementById(id);
        const icon = btn.querySelector('i');
        const show = inp.type === 'password';
        inp.type   = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    /* ── Indikator kekuatan password ─────────────────────────────────────────── */
    function cekKekuatan(val) {
        const bar   = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 6)               score++;
        if (val.length >= 10)              score++;
        if (/[A-Z]/.test(val))            score++;
        if (/[0-9]/.test(val))            score++;
        if (/[^a-zA-Z0-9]/.test(val))     score++;

        const level = [
            { w:'0%',   bg:'#dee2e6', lbl:'' },
            { w:'25%',  bg:'#dc3545', lbl:'Lemah' },
            { w:'50%',  bg:'#ffc107', lbl:'Cukup' },
            { w:'75%',  bg:'#0dcaf0', lbl:'Kuat' },
            { w:'100%', bg:'#198754', lbl:'Sangat Kuat' },
        ];
        const s = val.length === 0 ? 0 : Math.min(score, 4);
        bar.style.width      = level[s].w;
        bar.style.background = level[s].bg;
        label.textContent    = level[s].lbl;

        // Periksa juga konfirmasi setelah strength update
        cekMatch();
    }

    /* ── Cek kecocokan password baru & konfirmasi ──────────────────────────── */
    function cekMatch() {
        const baru    = document.getElementById('pw_baru').value;
        const konfirm = document.getElementById('pw_konfirm').value;
        const info    = document.getElementById('matchInfo');
        const inp     = document.getElementById('pw_konfirm');

        if (konfirm === '') { info.textContent = ''; inp.classList.remove('is-invalid','is-valid'); return; }

        if (baru === konfirm) {
            info.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Password cocok</span>';
            inp.classList.remove('is-invalid'); inp.classList.add('is-valid');
        } else {
            info.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Password tidak cocok</span>';
            inp.classList.add('is-invalid'); inp.classList.remove('is-valid');
        }
    }

    /* ── Validasi form ganti password sebelum submit ────────────────────────── */
    document.getElementById('formPassword').addEventListener('submit', function(e) {
        const lama    = document.getElementById('pw_lama').value;
        const baru    = document.getElementById('pw_baru').value;
        const konfirm = document.getElementById('pw_konfirm').value;
        let stop = false;

        if (lama === '') {
            shake('pw_lama'); stop = true;
        }
        if (baru.length < 6) {
            shake('pw_baru'); stop = true;
        }
        if (baru !== konfirm) {
            shake('pw_konfirm'); stop = true;
        }
        if (stop) e.preventDefault();
    });

    /* ── Validasi form profil sebelum submit ────────────────────────────────── */
    document.getElementById('formProfil').addEventListener('submit', function(e) {
        const nama = document.getElementById('inp_nama').value.trim();
        const un   = document.getElementById('inp_username').value.trim();
        let stop   = false;

        if (nama === '') { shake('inp_nama'); stop = true; }
        if (!/^[a-zA-Z0-9_]+$/.test(un)) { shake('inp_username'); stop = true; }
        if (stop) e.preventDefault();
    });

    /* ── Helper animasi shake ───────────────────────────────────────────────── */
    function shake(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('shake');
        el.focus();
        setTimeout(() => el.classList.remove('shake'), 350);
    }

    /* ── Scroll ke error banner jika ada ───────────────────────────────────── */
    ['errProfil','errPassword'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
    });
    </script>
    </body>
    </html>
