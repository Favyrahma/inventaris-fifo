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

// Hitung alert untuk navbar badge
$tgl = date('Y-m-d');
//Query disesuaikan ke tabel produk anda
$q_alert = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk WHERE DATEDIFF(tgl_expired, '$tgl') <= 30 AND stok >0");
$r_alert  = mysqli_fetch_assoc($q_alert);
$total_alert = $r_alert['total'] ?? 0;

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
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>