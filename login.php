<?php
// ─── Konfigurasi session aman (harus sebelum session_start) ───────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['user_id'])) { header("Location: indeks.php"); exit; }

// ─── Koneksi database ─────────────────────────────────────────────────────
$host = "localhost"; $user = "root"; $pass = ""; $db = "db_inventaris";
$koneksi = mysqli_connect($host, $user, $pass, $db);
mysqli_set_charset($koneksi, "utf8mb4");

// ─── Pastikan tabel login_attempts ada (auto-create jika belum) ───────────
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    username     VARCHAR(50) NOT NULL DEFAULT '',
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB");

// ─── Konfigurasi Rate Limiting ────────────────────────────────────────────
define('RL_MAX_ATTEMPTS',  5);          // maks gagal sebelum dikunci
define('RL_WINDOW_SEC',    15 * 60);    // jendela waktu: 15 menit
define('RL_LOCKOUT_SEC',   15 * 60);    // durasi lockout: 15 menit
define('RL_CLEANUP_PROB',  5);          // 5% chance bersihkan data lama

// ─── Helper: ambil IP klien (support proxy) ───────────────────────────────
function get_client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ─── Helper: hitung percobaan gagal dalam jendela waktu ──────────────────
function count_attempts($koneksi, $ip) {
    $ip  = mysqli_real_escape_string($koneksi, $ip);
    $win = RL_WINDOW_SEC;
    $r   = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) AS t FROM login_attempts
         WHERE ip_address = '$ip'
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL $win SECOND)"));
    return (int)($r['t'] ?? 0);
}

// ─── Helper: waktu sisa lockout (detik) ──────────────────────────────────
function lockout_remaining($koneksi, $ip) {
    $ip  = mysqli_real_escape_string($koneksi, $ip);
    $win = RL_WINDOW_SEC;
    $max = RL_MAX_ATTEMPTS;
    // Ambil attempt ke-N (pertama yang memicu lockout) dalam jendela
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT attempted_at FROM login_attempts
         WHERE ip_address = '$ip'
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL $win SECOND)
         ORDER BY attempted_at ASC
         LIMIT 1 OFFSET " . ($max - 1)));
    if (!$r) return 0;
    $unlock_at = strtotime($r['attempted_at']) + RL_LOCKOUT_SEC;
    return max(0, $unlock_at - time());
}

// ─── Helper: catat percobaan gagal ───────────────────────────────────────
function record_attempt($koneksi, $ip, $username) {
    $ip  = mysqli_real_escape_string($koneksi, $ip);
    $usr = mysqli_real_escape_string($koneksi, substr($username, 0, 50));
    mysqli_query($koneksi,
        "INSERT INTO login_attempts (ip_address, username) VALUES ('$ip', '$usr')");

    // Bersihkan record lama secara probabilistik (hindari overhead tiap request)
    if (rand(1, 100) <= RL_CLEANUP_PROB) {
        $old = RL_WINDOW_SEC * 2;
        mysqli_query($koneksi,
            "DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL $old SECOND)");
    }
}

// ─── Helper: reset attempts setelah login berhasil ───────────────────────
function clear_attempts($koneksi, $ip) {
    $ip = mysqli_real_escape_string($koneksi, $ip);
    mysqli_query($koneksi, "DELETE FROM login_attempts WHERE ip_address = '$ip'");
}

// ─── Format detik → menit:detik ──────────────────────────────────────────
function fmt_sisa($detik) {
    $m = floor($detik / 60);
    $s = $detik % 60;
    if ($m > 0) return "{$m} menit " . ($s > 0 ? "{$s} detik" : "");
    return "{$s} detik";
}

// ═════════════════════════════════════════════════════════════════════════
// PROSES LOGIN
// ═════════════════════════════════════════════════════════════════════════
$error        = '';
$error_type   = 'danger';   // 'danger' | 'warning'
$sisa_detik   = 0;
$attempts_now = 0;
$client_ip    = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Cek apakah sedang dalam masa lockout
    $attempts_now = count_attempts($koneksi, $client_ip);

    if ($attempts_now >= RL_MAX_ATTEMPTS) {
        $sisa_detik = lockout_remaining($koneksi, $client_ip);
        if ($sisa_detik > 0) {
            $error      = "Terlalu banyak percobaan gagal. Coba lagi dalam <strong>" . fmt_sisa($sisa_detik) . "</strong>.";
            $error_type = 'warning';
        } else {
            // Lockout sudah habis, reset & izinkan lanjut
            clear_attempts($koneksi, $client_ip);
            $attempts_now = 0;
        }
    }

    // 2. Proses autentikasi (hanya jika tidak dikunci)
    if (!$error) {
        $uname = mysqli_real_escape_string($koneksi, $_POST['username'] ?? '');
        $q     = mysqli_query($koneksi, "SELECT * FROM users WHERE username='$uname' LIMIT 1");
        $u     = mysqli_fetch_assoc($q);

        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            // ── Login berhasil ──────────────────────────────────────────
            clear_attempts($koneksi, $client_ip);
            session_regenerate_id(true);   // cegah session fixation
            $_SESSION['user_id']       = $u['id'];
            $_SESSION['user_nama']     = $u['nama'];
            $_SESSION['user_role']     = $u['role'];
            $_SESSION['login_time']    = time();    // untuk batas waktu mutlak
            $_SESSION['last_activity'] = time();    //untuk batas waktu idle
            $_SESSION['last_regen']    = time();    //untuk regenerasi id sesi
            $_SESSION['show_ews_alert'] = true;     // tampilkan popup EWS sekali setelah login

            header("Location: indeks.php");
            exit;

        } else {
            // ── Login gagal: catat & hitung sisa percobaan ─────────────
            record_attempt($koneksi, $client_ip, $_POST['username'] ?? '');
            $attempts_now++;

            $sisa_boleh = RL_MAX_ATTEMPTS - $attempts_now;
            if ($sisa_boleh <= 0) {
                $sisa_detik = RL_LOCKOUT_SEC;
                $error      = "Terlalu banyak percobaan gagal. Akses dikunci selama <strong>" . fmt_sisa(RL_LOCKOUT_SEC) . "</strong>.";
                $error_type = 'warning';
            } else {
                $error = "Username atau password salah. Sisa percobaan: <strong>{$sisa_boleh}x</strong>.";
            }
        }
    }
}

// ── Apakah IP sedang dikunci? (untuk disable tombol di UI) ────────────────
$is_locked = false;
if (!$_POST) {
    $attempts_now = count_attempts($koneksi, $client_ip);
    if ($attempts_now >= RL_MAX_ATTEMPTS) {
        $sisa_detik = lockout_remaining($koneksi, $client_ip);
        if ($sisa_detik > 0) {
            $is_locked  = true;
            $error      = "IP Anda sedang dikunci. Coba lagi dalam <strong>" . fmt_sisa($sisa_detik) . "</strong>.";
            $error_type = 'warning';
        }
    }
}
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – Inventaris FIFO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg,#1a1a2e,#16213e,#0f3460); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.card { border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.4); max-width:420px; width:100%; border:0; }
.card-header { background:linear-gradient(135deg,#212529,#343a40); border-radius:16px 16px 0 0 !important; }
.attempt-bar { height: 4px; background: #dee2e6; border-radius: 2px; overflow: hidden; margin-top: 8px; }
.attempt-fill { height: 100%; border-radius: 2px; transition: width .4s ease, background .4s ease; }
#countdown { font-variant-numeric: tabular-nums; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header text-center py-4">
    <i class="bi bi-box-seam-fill text-warning" style="font-size:2.5rem"></i>
    <h4 class="text-white mt-2 mb-0 fw-bold">Inventaris FIFO</h4>
    <p class="text-secondary small mb-0">Monitoring Masa Kedaluwarsa Produk</p>
  </div>
  <div class="card-body p-4">

    <?php if ($error): ?>
    <div class="alert alert-<?= $error_type ?> py-2 mb-3">
      <i class="bi bi-<?= $error_type === 'warning' ? 'shield-lock' : 'exclamation-circle' ?> me-2"></i>
      <?= $error ?>
      <?php if ($is_locked && $sisa_detik > 0): ?>
      <div class="mt-1 small">
        Hitung mundur: <strong id="countdown"><?= fmt_sisa($sisa_detik) ?></strong>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" <?= $is_locked ? 'onsubmit="return false;"' : '' ?>>
      <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="username" class="form-control" placeholder="Username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 <?= $is_locked ? 'disabled' : 'required autofocus' ?>>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control" placeholder="Password"
                 <?= $is_locked ? 'disabled' : 'required' ?>>
        </div>
      </div>

      <?php
        // Progress bar percobaan (hanya tampil kalau sudah ada kegagalan)
        $pct   = min(100, round($attempts_now / RL_MAX_ATTEMPTS * 100));
        $color = $pct >= 100 ? '#dc3545' : ($pct >= 60 ? '#ffc107' : '#198754');
        if ($attempts_now > 0 && !$is_locked):
      ?>
      <div class="mb-3">
        <div class="d-flex justify-content-between small text-muted mb-1">
          <span><i class="bi bi-shield-exclamation me-1"></i>Percobaan gagal</span>
          <span><?= $attempts_now ?> / <?= RL_MAX_ATTEMPTS ?></span>
        </div>
        <div class="attempt-bar">
          <div class="attempt-fill" style="width:<?= $pct ?>%; background:<?= $color ?>;"></div>
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold" <?= $is_locked ? 'disabled' : '' ?>
              id="btnLogin">
        <i class="bi bi-<?= $is_locked ? 'shield-lock' : 'box-arrow-in-right' ?> me-2"></i>
        <?= $is_locked ? 'Akses Dikunci' : 'Masuk' ?>
      </button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
      Default login: <strong>admin</strong> / <strong>password</strong>
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($is_locked && $sisa_detik > 0): ?>
<script>
// Hitung mundur & reload otomatis saat lockout selesai
let remaining = <?= (int)$sisa_detik ?>;
const el = document.getElementById('countdown');
const timer = setInterval(() => {
    remaining--;
    if (remaining <= 0) {
        clearInterval(timer);
        location.reload();
        return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    el.textContent = m > 0
        ? m + ' menit ' + (s > 0 ? s + ' detik' : '')
        : s + ' detik';
}, 1000);
</script>
<?php endif; ?>
</body></html>
