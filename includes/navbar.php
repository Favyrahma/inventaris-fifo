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

    $tgl = date('Y-m-d');
    $q_kritis_nav = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produk WHERE DATEDIFF(tgl_expired, '$tgl') BETWEEN 0 AND $THRESHOLD_MERAH AND stok > 0");
    $total_kritis_nav  = mysqli_fetch_assoc($q_kritis_nav)['t'] ?? 0;

    $q_expired_nav = mysqli_query($koneksi, "SELECT COUNT(*) as t FROM produk WHERE tgl_expired < '$tgl' AND stok > 0");
    $total_expired_nav = mysqli_fetch_assoc($q_expired_nav)['t'] ?? 0;

    $total_alert = $total_kritis_nav + $total_expired_nav;

    $current = basename($_SERVER['PHP_SELF']);
    function nav_active($page, $current) {
        return $page === $current ? 'active' : '';
    }

    $laporan_pages = ['laporan_stok.php','laporan_expired.php','laporan_transaksi.php','riwayat_stok.php'];
    $master_pages  = ['kategori.php','users.php','setting.php','koreksi_stok.php'];
    $laporan_open  = in_array($current, $laporan_pages);
    $master_open   = in_array($current, $master_pages);
    ?>
<style>
/* ===================================================
   SIDEBAR – Inventaris FIFO
   =================================================== */
:root {
    --sb-w      : 255px;
    --sb-w-mini : 64px;
    --sb-bg-top : #0f3460;
    --sb-bg-bot : #16213e;
    --sb-text   : rgba(255,255,255,.72);
    --sb-text-on: #ffffff;
    --sb-ease   : cubic-bezier(.4,0,.2,1);
}

/* ── Body offset ── */
body {
    margin-left: var(--sb-w) !important;
    transition : margin-left .28s var(--sb-ease);
}

/* ── Sidebar container ── */
#appSidebar {
    position       : fixed;
    top            : 0; left: 0;
    width          : var(--sb-w);
    height         : 100vh;
    background     : linear-gradient(180deg, var(--sb-bg-top) 0%, var(--sb-bg-bot) 100%);
    z-index        : 1040;
    display        : flex;
    flex-direction : column;
    overflow       : hidden;
    box-shadow     : 3px 0 18px rgba(0,0,0,.22);
    transition     : width .28s var(--sb-ease),
                     transform .28s var(--sb-ease),
                     box-shadow .28s;
}

/* ════════════════════════════════════
   HEADER / BRAND
   ════════════════════════════════════ */
.sb-header {
    display     : flex;
    align-items : center;
    gap         : .4rem;
    padding     : 1rem 1rem .9rem;
    border-bottom: 1px solid rgba(255,255,255,.1);
    flex-shrink : 0;
}
.sb-brand {
    display        : flex;
    align-items    : center;
    gap            : .65rem;
    text-decoration: none;
    flex           : 1;
    min-width      : 0;
    overflow       : hidden;
}
.sb-logo-icon {
    width         : 34px; height: 34px;
    border-radius : 9px;
    background    : rgba(255,255,255,.15);
    display       : flex; align-items:center; justify-content:center;
    font-size     : 1.1rem; color:#fff; flex-shrink:0;
}
.sb-brand-name { font-size:.9rem; font-weight:700; color:#fff; white-space:nowrap; line-height:1.25; }
.sb-brand-sub  { font-size:.67rem; color:rgba(255,255,255,.42); white-space:nowrap; }

/* ── Collapse toggle button ── */
#sbCollapseBtn {
    flex-shrink   : 0;
    width         : 28px; height: 28px;
    border-radius : 8px;
    background    : rgba(255,255,255,.1);
    border        : none;
    color         : rgba(255,255,255,.6);
    cursor        : pointer;
    display       : flex; align-items:center; justify-content:center;
    font-size     : .82rem;
    transition    : background .14s, color .14s;
    line-height   : 1;
}
#sbCollapseBtn:hover { background:rgba(255,255,255,.22); color:#fff; }
.sb-chevron-lr { transition: transform .28s var(--sb-ease); }

/* ════════════════════════════════════
   NAV AREA
   ════════════════════════════════════ */
.sb-nav {
    flex      : 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding   : .5rem .45rem .3rem;
}
.sb-nav::-webkit-scrollbar { width:4px; }
.sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,.2); border-radius:2px; }

.sb-label {
    font-size     : .63rem; font-weight:700;
    letter-spacing: .09em; text-transform:uppercase;
    color         : rgba(255,255,255,.32);
    padding       : .75rem .6rem .25rem;
    white-space   : nowrap;
}

/* Nav link */
.sb-link {
    display        : flex;
    align-items    : center;
    gap            : .55rem;
    padding        : .52rem .7rem;
    border-radius  : 8px;
    margin-bottom  : 2px;
    color          : var(--sb-text);
    text-decoration: none;
    font-size      : .875rem;
    transition     : background .14s, color .14s;
    cursor         : pointer;
    white-space    : nowrap;
}
.sb-link:hover  { background:rgba(255,255,255,.1);  color:var(--sb-text-on); }
.sb-link.active { background:rgba(255,255,255,.15); color:var(--sb-text-on); font-weight:600; }
.sb-link i.sb-icon { font-size:.98rem; width:18px; text-align:center; flex-shrink:0; }
.sb-link .sb-arr   { margin-left:auto; font-size:.62rem; color:rgba(255,255,255,.3); transition:transform .2s; flex-shrink:0; }
.sb-link[aria-expanded="true"] .sb-arr { transform:rotate(-180deg); }
.sb-badge-ml { margin-left:auto; flex-shrink:0; }
.sb-txt { overflow:hidden; }

/* Sub-menu */
.sb-sub { padding:2px 0 2px .5rem; }
.sb-sub .sb-link { font-size:.83rem; padding:.44rem .65rem; border-radius:6px; }

/* Divider */
.sb-hr { border-color:rgba(255,255,255,.1); margin:.45rem .4rem; }

/* ════════════════════════════════════
   USER PANEL
   ════════════════════════════════════ */
.sb-user {
    flex-shrink: 0;
    border-top : 1px solid rgba(255,255,255,.1);
    padding    : .7rem .9rem;
}
.sb-user-toggle {
    display        : flex;
    align-items    : center;
    gap            : .65rem;
    color          : #fff;
    text-decoration: none;
    width          : 100%;
}
.sb-user-toggle::after { display:none; }
.sb-avatar {
    width:34px; height:34px; border-radius:10px;
    background:rgba(255,255,255,.15);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0; color:#fff;
}
.sb-user-info   { overflow:hidden; flex:1; min-width:0; }
.sb-user-name   { font-size:.84rem; font-weight:600; color:#fff; line-height:1.2; white-space:nowrap; }
.sb-user-role   { font-size:.71rem; color:rgba(255,255,255,.48); white-space:nowrap; }
.sb-user-caret  { margin-left:auto; font-size:.7rem; color:rgba(255,255,255,.35); flex-shrink:0; }

/* ════════════════════════════════════
   MINI MODE  (body.sb-mini)
   ════════════════════════════════════ */
body.sb-mini { margin-left: var(--sb-w-mini) !important; }
body.sb-mini #appSidebar { width: var(--sb-w-mini); }

/* Rotate collapse button arrow */
body.sb-mini .sb-chevron-lr { transform: rotate(-180deg); }

/* Header in mini */
body.sb-mini .sb-header { justify-content:center; padding:.9rem .4rem; gap:0; flex-direction:column; }
body.sb-mini .sb-brand  { flex:0; gap:0; }
body.sb-mini .sb-brand-name,
body.sb-mini .sb-brand-sub { display:none; }
body.sb-mini #sbCollapseBtn { margin-top:.5rem; }

/* Nav in mini */
body.sb-mini .sb-nav   { padding:.45rem .3rem; overflow:visible; }
body.sb-mini .sb-label { display:none; }
body.sb-mini .sb-hr    { margin:.35rem .5rem; }
body.sb-mini .sb-txt   { display:none; }
body.sb-mini .sb-arr   { display:none; }
body.sb-mini .sb-badge-ml { display:none; }
body.sb-mini .sb-link  { justify-content:center; padding:.55rem 0; margin:2px 4px; }
body.sb-mini .sb-link i.sb-icon { width:auto; }
body.sb-mini .collapse { display:none !important; }
body.sb-mini .sb-sub   { display:none !important; }

/* User panel in mini */
body.sb-mini .sb-user        { padding:.65rem .3rem; }
body.sb-mini .sb-user-toggle { justify-content:center; gap:0; }
body.sb-mini .sb-user-info   { display:none; }
body.sb-mini .sb-user-caret  { display:none; }

/* Tooltip in mini mode (Bootstrap tooltip target) */
body.sb-mini .sb-link { position:relative; }

/* ════════════════════════════════════
   MOBILE TOP-BAR
   ════════════════════════════════════ */
#sbTopbar {
    display    : none;
    position   : fixed; top:0; left:0; right:0;
    height     : 54px;
    background : linear-gradient(135deg, var(--sb-bg-top), var(--sb-bg-bot));
    z-index    : 1039;
    align-items: center;
    padding    : 0 1rem;
    box-shadow : 0 2px 10px rgba(0,0,0,.22);
}
#sbTopbar .sb-top-title { color:#fff; font-weight:700; font-size:.92rem; margin-left:.5rem; }
#sbToggleBtn {
    background:none; border:none; color:#fff; padding:4px 6px;
    border-radius:6px; cursor:pointer; line-height:1;
}
#sbToggleBtn:hover { background:rgba(255,255,255,.12); }

/* Overlay (mobile) */
#sbOverlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.45); z-index:1038;
}
#sbOverlay.show { display:block; }

/* ── Mobile breakpoint ── */
@media (max-width: 991.98px) {
    #appSidebar { transform:translateX(calc(-1 * var(--sb-w))); box-shadow:none; }
    #appSidebar.sb-open { transform:translateX(0); box-shadow:4px 0 24px rgba(0,0,0,.3); }
    #sbTopbar   { display:flex; }
    #sbCollapseBtn { display:none; }
    body         { margin-left:0 !important; padding-top:54px; transition:none; }
}

/* ── Print ── */
@media print {
    #appSidebar,#sbTopbar,#sbOverlay { display:none !important; }
    body { margin-left:0 !important; padding-top:0 !important; }
}
</style>

<!-- Mobile top-bar -->
<div id="sbTopbar">
    <button id="sbToggleBtn" aria-label="Toggle sidebar">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="sb-top-title"><i class="bi bi-box-seam me-1"></i>Inventaris FIFO</span>
</div>

<!-- Overlay (mobile) -->
<div id="sbOverlay"></div>

<!-- ════════════════════════════════
     SIDEBAR
     ════════════════════════════════ -->
<nav id="appSidebar" aria-label="Navigasi utama">

    <!-- Header / Brand -->
    <div class="sb-header">
        <a href="indeks.php" class="sb-brand" title="Inventaris FIFO">
            <div class="sb-logo-icon"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="sb-brand-name">Inventaris FIFO</div>
                <div class="sb-brand-sub">Sistem Manajemen Stok</div>
            </div>
        </a>
        <button id="sbCollapseBtn" title="Sembunyikan/tampilkan sidebar" aria-label="Toggle sidebar">
            <i class="bi bi-chevron-left sb-chevron-lr"></i>
        </button>
    </div>

    <!-- Scrollable nav -->
    <div class="sb-nav">

        <div class="sb-label">Menu Utama</div>

        <a class="sb-link <?= nav_active('indeks.php', $current) ?>" href="indeks.php" title="Dashboard">
            <i class="bi bi-speedometer2 sb-icon"></i><span class="sb-txt">Dashboard</span>
        </a>

        <?php if ($role === 'admin'): ?>
        <a class="sb-link <?= nav_active('produk.php', $current) ?>" href="produk.php" title="Stok Produk (Batch)">
            <i class="bi bi-archive sb-icon"></i><span class="sb-txt">Stok Produk (Batch)</span>
        </a>
        <?php endif; ?>

        <a class="sb-link <?= nav_active('transaksi.php', $current) ?>" href="transaksi.php" title="Transaksi Keluar">
            <i class="bi bi-cart3 sb-icon"></i><span class="sb-txt">Transaksi Keluar</span>
        </a>

        <hr class="sb-hr">
        <div class="sb-label">Laporan</div>

        <?php
        $laporan_pages = ['laporan_stok.php','laporan_expired.php','laporan_transaksi.php','riwayat_stok.php'];
        $laporan_open  = in_array($current, $laporan_pages);
        ?>
        <a class="sb-link <?= $laporan_open ? 'active' : '' ?>"
           href="#sbLaporan" data-bs-toggle="collapse"
           aria-expanded="<?= $laporan_open ? 'true' : 'false' ?>"
           data-mini-href="laporan_stok.php" title="Laporan">
            <i class="bi bi-file-earmark-bar-graph sb-icon"></i>
            <span class="sb-txt">Laporan</span>
            <?php if ($total_alert > 0): ?>
                <span class="badge bg-danger rounded-pill sb-badge-ml"><?= $total_alert ?></span>
            <?php else: ?>
                <i class="bi bi-chevron-down sb-arr"></i>
            <?php endif; ?>
        </a>
        <div class="collapse <?= $laporan_open ? 'show' : '' ?>" id="sbLaporan">
            <div class="sb-sub">
                <a class="sb-link <?= nav_active('laporan_stok.php',$current) ?>" href="laporan_stok.php" title="Laporan Stok">
                    <i class="bi bi-archive sb-icon"></i><span class="sb-txt">Laporan Stok</span>
                </a>
                <a class="sb-link <?= nav_active('laporan_expired.php',$current) ?>" href="laporan_expired.php" title="Kedaluwarsa">
                    <i class="bi bi-exclamation-triangle sb-icon"></i><span class="sb-txt">Kedaluwarsa</span>
                    <?php if ($total_alert > 0): ?>
                        <span class="badge bg-danger rounded-pill sb-badge-ml"><?= $total_alert ?></span>
                    <?php endif; ?>
                </a>
                <a class="sb-link <?= nav_active('laporan_transaksi.php',$current) ?>" href="laporan_transaksi.php" title="Laporan Transaksi">
                    <i class="bi bi-receipt sb-icon"></i><span class="sb-txt">Laporan Transaksi</span>
                </a>
                <a class="sb-link <?= nav_active('riwayat_stok.php',$current) ?>" href="riwayat_stok.php" title="Riwayat Stok Log">
                    <i class="bi bi-journal-text sb-icon"></i><span class="sb-txt">Riwayat Stok Log</span>
                </a>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
        <hr class="sb-hr">
        <div class="sb-label">Master Data</div>

        <?php
        $master_pages = ['kategori.php','users.php','setting.php','koreksi_stok.php'];
        $master_open  = in_array($current, $master_pages);
        ?>
        <a class="sb-link <?= $master_open ? 'active' : '' ?>"
           href="#sbMaster" data-bs-toggle="collapse"
           aria-expanded="<?= $master_open ? 'true' : 'false' ?>"
           data-mini-href="kategori.php" title="Master Data">
            <i class="bi bi-gear sb-icon"></i>
            <span class="sb-txt">Master Data</span>
            <i class="bi bi-chevron-down sb-arr"></i>
        </a>
        <div class="collapse <?= $master_open ? 'show' : '' ?>" id="sbMaster">
            <div class="sb-sub">
                <a class="sb-link <?= nav_active('kategori.php',$current) ?>" href="kategori.php" title="Kategori">
                    <i class="bi bi-tags sb-icon"></i><span class="sb-txt">Kategori</span>
                </a>
                <a class="sb-link <?= nav_active('users.php',$current) ?>" href="users.php" title="Pengguna">
                    <i class="bi bi-people sb-icon"></i><span class="sb-txt">Pengguna</span>
                </a>
                <a class="sb-link <?= nav_active('koreksi_stok.php',$current) ?>" href="koreksi_stok.php" title="Koreksi Stok">
                    <i class="bi bi-clipboard2-pulse sb-icon"></i><span class="sb-txt">Koreksi Stok</span>
                </a>
                <a class="sb-link <?= nav_active('setting.php',$current) ?>" href="setting.php" title="Pengaturan EWS">
                    <i class="bi bi-sliders sb-icon"></i><span class="sb-txt">Pengaturan EWS</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /sb-nav -->

    <!-- User panel (bottom) -->
    <div class="sb-user">
        <div class="dropdown dropup w-100">
            <a href="#" class="sb-user-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="sb-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="sb-user-info">
                    <div class="sb-user-name text-truncate"><?= htmlspecialchars($nama) ?></div>
                    <div class="sb-user-role"><?= ucfirst($role) ?></div>
                </div>
                <i class="bi bi-chevron-up sb-user-caret"></i>
            </a>
            <ul class="dropdown-menu shadow-sm border-0 mb-1" style="min-width:190px">
                <li>
                    <a class="dropdown-item <?= nav_active('profil.php',$current) ?>" href="profil.php">
                        <i class="bi bi-person-gear me-2"></i>Profil &amp; Password
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

</nav>

<script>
(function () {
    var MINI_KEY = 'sbMini';
    var body     = document.body;
    var sidebar  = document.getElementById('appSidebar');
    var colBtn   = document.getElementById('sbCollapseBtn');

    /* ── Restore mini state from localStorage ── */
    if (localStorage.getItem(MINI_KEY) === '1') {
        body.classList.add('sb-mini');
    }

    /* ── Desktop collapse toggle ── */
    if (colBtn) {
        colBtn.addEventListener('click', function () {
            var isMini = body.classList.toggle('sb-mini');
            localStorage.setItem(MINI_KEY, isMini ? '1' : '0');
        });
    }

    /* ── Collapse triggers: navigate directly when in mini mode ── */
    document.querySelectorAll('[data-mini-href]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (body.classList.contains('sb-mini')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.location.href = this.dataset.miniHref;
            }
        }, true); /* capture phase — runs before Bootstrap */
    });

    /* ── Bootstrap tooltips on icons (mini mode only) ── */
    var tooltips = [];
    function enableTooltips() {
        if (typeof bootstrap === 'undefined') return;
        document.querySelectorAll('#appSidebar .sb-link[title]').forEach(function (el) {
            try {
                tooltips.push(new bootstrap.Tooltip(el, {
                    placement : 'right',
                    trigger   : 'hover',
                    container : 'body'
                }));
            } catch(e){}
        });
    }
    function destroyTooltips() {
        tooltips.forEach(function (t) { try { t.dispose(); } catch(e){} });
        tooltips = [];
    }

    function syncTooltips() {
        if (body.classList.contains('sb-mini')) {
            if (tooltips.length === 0) enableTooltips();
        } else {
            destroyTooltips();
        }
    }

    /* ── Mobile toggle (overlay) ── */
    var mobileBtn = document.getElementById('sbToggleBtn');
    var overlay   = document.getElementById('sbOverlay');

    function openMobile()  { sidebar.classList.add('sb-open');    overlay.classList.add('show'); }
    function closeMobile() { sidebar.classList.remove('sb-open'); overlay.classList.remove('show'); }

    if (mobileBtn) mobileBtn.addEventListener('click', function () {
        sidebar.classList.contains('sb-open') ? closeMobile() : openMobile();
    });
    if (overlay) overlay.addEventListener('click', closeMobile);

    /* Init tooltips and watch for mini toggle */
    document.addEventListener('DOMContentLoaded', function () {
        syncTooltips();
        if (colBtn) {
            colBtn.addEventListener('click', syncTooltips);
        }
    });
})();
</script>
<?php
    $tampilkan_popup_ews = false;
    if (!empty($_SESSION['show_ews_alert'])) {
        unset($_SESSION['show_ews_alert']);
        $tampilkan_popup_ews = ($total_alert > 0);
    }
    ?>
    <?php if ($tampilkan_popup_ews): ?>
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
    if (isset($_SESSION['user_id'], $_SESSION['last_activity'])):
        $sisa_idle = max(0, (int)(defined('SESSION_IDLE_LIMIT') ? SESSION_IDLE_LIMIT : 1800)
                            - (time() - $_SESSION['last_activity']));
        $warn_secs = (int)(defined('SESSION_WARN_BEFORE') ? SESSION_WARN_BEFORE : 120);
    ?>
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
            <p class="text-muted mb-1" style="font-size:.9rem">Sesi Anda akan berakhir dalam</p>
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
    var IDLE_LIMIT  = <?= (int)(defined('SESSION_IDLE_LIMIT')  ? SESSION_IDLE_LIMIT  : 1800) ?>;
    var WARN_BEFORE = <?= (int)(defined('SESSION_WARN_BEFORE') ? SESSION_WARN_BEFORE : 120) ?>;
    var sisaAwal    = <?= $sisa_idle ?>;
    var sisa = sisaAwal, modal = null, warned = false;
    var cdEl = document.getElementById('toCountdown');
    var barEl = document.getElementById('toBar');
    var btnTetap = document.getElementById('btnTetap');
    function padZ(n) { return n < 10 ? '0'+n : String(n); }
    function formatSisa(s) { var m=Math.floor(s/60),d=s%60; return padZ(m)+':'+padZ(d); }
    function getModal() { if (!modal) modal = new bootstrap.Modal(document.getElementById('modalTimeout')); return modal; }
    var tick = setInterval(function () {
        sisa--;
        if (sisa <= 0) { clearInterval(tick); window.location.href = 'session_timeout.php?pesan='+encodeURIComponent('Sesi Anda berakhir karena tidak aktif selama 30 menit.'); return; }
        if (!warned && sisa <= WARN_BEFORE) { warned = true; getModal().show(); }
        if (warned) { cdEl.textContent = formatSisa(sisa); barEl.style.width = ((sisa/WARN_BEFORE)*100)+'%'; }
    }, 1000);
    btnTetap.addEventListener('click', function () {
        fetch('ping_session.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){sisa=d.sisa;warned=false;getModal().hide();}else{window.location.href='session_timeout.php';} })
        .catch(function(){window.location.reload();});
    });
    var lastPing = Date.now();
    function onActivity() {
        var now = Date.now(); if (now-lastPing < 30000) return; lastPing = now;
        fetch('ping_session.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){return r.json();}).then(function(d){ if(d.ok){sisa=d.sisa;warned=false;}else{window.location.href='session_timeout.php';} }).catch(function(){});
    }
    document.addEventListener('mousemove',onActivity);
    document.addEventListener('keydown',onActivity);
    document.addEventListener('click',onActivity);
    document.addEventListener('scroll',onActivity);
    })();
    </script>
    <?php endif; ?>
