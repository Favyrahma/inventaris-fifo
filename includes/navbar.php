<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $role = $_SESSION['user_role'] ?? 'kasir';
    $nama = $_SESSION['user_nama'] ?? 'user';

    // Hitung alert EWS untuk navbar badge & popup pertama login
    $tgl = date('Y-m-d');
    // Kritis: sisa hari 0..THRESHOLD_MERAH (ikut pengaturan EWS admin)
    $q_kritis_nav = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produk WHERE DATEDIFF(tgl_expired, '$tgl') BETWEEN 0 AND $THRESHOLD_MERAH AND stok > 0");
    $total_kritis_nav  = mysqli_fetch_assoc($q_kritis_nav)['t'] ?? 0;

    // Sudah kedaluwarsa tapi stok masih ada (belum dikoreksi/dibuang)
    $q_expired_nav = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produk WHERE tgl_expired < '$tgl' AND stok > 0");
    $total_expired_nav = mysqli_fetch_assoc($q_expired_nav)['t'] ?? 0;

    $total_alert = $total_kritis_nav + $total_expired_nav;   // dipakai badge navbar

    $current = basename($_SERVER['PHP_SELF']);
    function nav_active($page, $current) {
        return $page === $current ? 'active' : '';
    }
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4" style="background: linear-gradient(135deg,#0f3460,#16213e);">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="indeks.php">
                <i class="bi bi-box-seam me-1"></i> Inventaris FIFO
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('indeks.php', $current) ?>" href="indeks.php">Dashboard</a>
                    </li>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('produk.php', $current) ?>" href="produk.php">Stok Produk (Batch)</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= nav_active('transaksi.php', $current) ?>" href="transaksi.php">Transaksi Keluar</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($current,['laporan_stok.php','laporan_expired.php','laporan_transaksi.php'])?'active':'' ?>"
                        href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?= nav_active('laporan_stok.php',$current) ?>" href="laporan_stok.php">
                                <i class="bi bi-archive me-1"></i>Laporan Stok
                            </a></li>
                            <li><a class="dropdown-item <?= nav_active('laporan_expired.php',$current) ?>" href="laporan_expired.php">
                                <i class="bi bi-exclamation-triangle me-1"></i>Laporan Kedaluwarsa
                                <?php if ($total_alert > 0): ?>
                                    <span class="badge bg-danger ms-1"><?= $total_alert ?></span>
                                <?php endif; ?>
                            </a></li>
                            <li><a class="dropdown-item <?= nav_active('laporan_transaksi.php',$current) ?>" href="laporan_transaksi.php">
                                <i class="bi bi-receipt me-1"></i>Laporan Transaksi
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?= nav_active('riwayat_stok.php',$current) ?>" href="riwayat_stok.php">
                                <i class="bi bi-journal-text me-1"></i>Riwayat Stok Log
                            </a></li>
                        </ul>
                    </li>
                    <?php if ($role === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($current,['kategori.php','users.php','setting.php'])?'active':'' ?>"
                        href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Master Data
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?= nav_active('kategori.php',$current) ?>" href="kategori.php"><i class="bi bi-tags me-1"></i>Kategori</a></li>
                            <li><a class="dropdown-item <?= nav_active('users.php',$current) ?>" href="users.php"><i class="bi bi-people me-1"></i>Pengguna</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?= nav_active('koreksi_stok.php',$current) ?>" href="koreksi_stok.php"><i class="bi bi-clipboard2-pulse me-1"></i>Koreksi Stok</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?= nav_active('setting.php',$current) ?>" href="setting.php"><i class="bi bi-sliders me-1"></i>Pengaturan EWS</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($nama) ?>
                            <span class="badge bg-secondary ms-1"><?= ucfirst($role) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item <?= nav_active('profil.php',$current) ?>" href="profil.php">
                            <i class="bi bi-person-gear me-1"></i>Profil & Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php
    // ── Pop-up peringatan EWS saat pertama kali login ───────────────────────
    // Flag di-set oleh login.php saat login berhasil, lalu di-unset di sini
    // supaya popup hanya muncul SEKALI per sesi login (tidak setiap pindah halaman).
    $tampilkan_popup_ews = false;
    if (!empty($_SESSION['show_ews_alert'])) {
        unset($_SESSION['show_ews_alert']);
        $tampilkan_popup_ews = ($total_alert > 0);
    }
    ?>
    <?php if ($tampilkan_popup_ews): ?>
    <!-- Modal popup peringatan EWS (muncul otomatis sekali setelah login) -->
    <div class="modal fade" id="modalEwsAlert" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
        <div class="modal-header border-0 py-3" style="background:linear-gradient(135deg,#dc2626,#f59e0b)">
            <div class="d-flex align-items-center gap-2 text-white">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <h6 class="modal-title fw-bold mb-0">Peringatan Early Warning System</h6>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body py-4 px-4">
            <p class="text-muted mb-3" style="font-size:.9rem">
            Ada produk yang memerlukan perhatian Anda sebelum mulai bekerja:
            </p>
            <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
            <?php if ($total_expired_nav > 0): ?>
                <li class="d-flex justify-content-between align-items-center p-2 px-3 rounded-3" style="background:#f1f3f5">
                <span><i class="bi bi-x-octagon-fill text-secondary me-2"></i>Sudah kedaluwarsa</span>
                <span class="badge bg-secondary rounded-pill"><?= $total_expired_nav ?> batch</span>
                </li>
            <?php endif; ?>
            <?php if ($total_kritis_nav > 0): ?>
                <li class="d-flex justify-content-between align-items-center p-2 px-3 rounded-3" style="background:#fff5f5">
                <span><i class="bi bi-exclamation-circle-fill text-danger me-2"></i>Mendekati kedaluwarsa (&le; <?= (int)$THRESHOLD_MERAH ?> hari)</span>
                <span class="badge bg-danger rounded-pill"><?= $total_kritis_nav ?> batch</span>
                </li>
            <?php endif; ?>
            </ul>
        </div>
        <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2 justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
            <i class="bi bi-x-lg me-1"></i>Tutup
            </button>
            <a href="laporan_expired.php" class="btn btn-dark btn-sm px-3">
            <i class="bi bi-eye me-1"></i>Lihat Detail
            </a>
        </div>
        </div>
    </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var elEws = document.getElementById('modalEwsAlert');
        if (elEws) { new bootstrap.Modal(elEws).show(); }
    });
    </script>
    <?php endif; ?>
    <?php
    // ── Peringatan timeout di sisi klien ────────────────────────────────────
    // Hanya inject jika user sudah login (session ada)
    if (isset($_SESSION['user_id'], $_SESSION['last_activity'])):
        $sisa_idle = max(0, (int)(defined('SESSION_IDLE_LIMIT') ? SESSION_IDLE_LIMIT : 1800)
                            - (time() - $_SESSION['last_activity']));
        $warn_secs = (int)(defined('SESSION_WARN_BEFORE') ? SESSION_WARN_BEFORE : 120);
    ?>
    <!-- Modal peringatan timeout -->
    <div class="modal fade" id="modalTimeout" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
        <div class="modal-header border-0 py-3" style="background:linear-gradient(135deg,#dc2626,#991b1b)">
            <div class="d-flex align-items-center gap-2 text-white">
            <i class="bi bi-clock-history fs-5"></i>
            <h6 class="modal-title fw-bold mb-0">Sesi Hampir Berakhir</h6>
            </div>
        </div>
        <div class="modal-body text-center py-4 px-4">
            <p class="text-muted mb-1" style="font-size:.9rem">
            Sesi Anda akan berakhir dalam
            </p>
            <div class="fw-bold text-danger mb-3" style="font-size:2.4rem;line-height:1" id="toCountdown">--</div>
            <div class="progress mb-3" style="height:6px;border-radius:3px">
            <div class="progress-bar bg-danger" id="toBar" style="width:100%;transition:width 1s linear"></div>
            </div>
            <p class="text-muted" style="font-size:.82rem">
            Klik <strong>Tetap di Sini</strong> untuk melanjutkan sesi,<br>
            atau biarkan untuk logout otomatis.
            </p>
        </div>
        <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2 justify-content-center">
            <a href="logout.php" class="btn btn-outline-danger btn-sm px-4">
            <i class="bi bi-box-arrow-right me-1"></i>Logout Sekarang
            </a>
            <button type="button" class="btn btn-dark btn-sm px-4" id="btnTetap">
            <i class="bi bi-check-circle me-1"></i>Tetap di Sini
            </button>
        </div>
        </div>
    </div>
    </div>

    <script>
    (function () {
    var IDLE_LIMIT  = <?= (int)(defined('SESSION_IDLE_LIMIT')  ? SESSION_IDLE_LIMIT  : 1800) ?>;  // detik
    var WARN_BEFORE = <?= (int)(defined('SESSION_WARN_BEFORE') ? SESSION_WARN_BEFORE : 120) ?>;   // detik sebelum habis
    var sisaAwal    = <?= $sisa_idle ?>;  // sisa detik saat halaman dimuat

    var sisa     = sisaAwal;
    var modal    = null;
    var warned   = false;
    var cdEl     = document.getElementById('toCountdown');
    var barEl    = document.getElementById('toBar');
    var btnTetap = document.getElementById('btnTetap');

    function padZ(n) { return n < 10 ? '0' + n : String(n); }
    function formatSisa(s) {
        var m = Math.floor(s / 60);
        var d = s % 60;
        return padZ(m) + ':' + padZ(d);
    }

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('modalTimeout'));
        return modal;
    }

    var tick = setInterval(function () {
        sisa--;
        if (sisa <= 0) {
        clearInterval(tick);
        window.location.href = 'session_timeout.php?pesan=' +
            encodeURIComponent('Sesi Anda berakhir karena tidak aktif selama 30 menit.');
        return;
        }

        // Munculkan modal peringatan
        if (!warned && sisa <= WARN_BEFORE) {
        warned = true;
        getModal().show();
        }

        // Update countdown di modal
        if (warned) {
        cdEl.textContent = formatSisa(sisa);
        barEl.style.width = ((sisa / WARN_BEFORE) * 100) + '%';
        }
    }, 1000);

    // Tombol "Tetap di Sini" — ping server untuk refresh last_activity
    btnTetap.addEventListener('click', function () {
        fetch('ping_session.php', { method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function (r) { return r.json(); })
        .then(function (d) {
        if (d.ok) {
            sisa  = d.sisa;
            warned = false;
            getModal().hide();
        } else {
            window.location.href = 'session_timeout.php';
        }
        })
        .catch(function () {
        // Fallback: reload halaman (akan refresh last_activity lewat koneksi.php)
        window.location.reload();
        });
    });

    // Reset timer saat ada interaksi keyboard/mouse (setiap 30 detik max)
    var lastPing = Date.now();
    function onActivity() {
        var now = Date.now();
        if (now - lastPing < 30000) return;
        lastPing = now;
        fetch('ping_session.php', { method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function (r) { return r.json(); })
        .then(function (d) {
        if (d.ok) { sisa = d.sisa; warned = false; }
        else { window.location.href = 'session_timeout.php'; }
        }).catch(function(){});
    }
    document.addEventListener('mousemove', onActivity);
    document.addEventListener('keydown',   onActivity);
    document.addEventListener('click',     onActivity);
    document.addEventListener('scroll',    onActivity);
    })();
    </script>
    <?php endif; ?>