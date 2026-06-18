<?php
/**
 * session_timeout.php
 * Ditampilkan saat sesi berakhir (idle timeout atau batas mutlak).
 * Tidak perlu include koneksi.php — sesi sudah dihancurkan sebelum redirect ke sini.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Jika masih login (akses langsung ke URL ini), jangan tampilkan
if (isset($_SESSION['user_id'])) {
    header('Location: indeks.php');
    exit;
}

$pesan = htmlspecialchars($_GET['pesan'] ?? 'Sesi Anda telah berakhir. Silakan login kembali.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sesi Berakhir – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .timeout-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 24px 64px rgba(0,0,0,.45);
      max-width: 440px;
      width: 100%;
      overflow: hidden;
    }
    .timeout-header {
      background: linear-gradient(135deg, #dc2626, #991b1b);
      padding: 32px 24px 28px;
      text-align: center;
      color: #fff;
    }
    .timeout-header .icon-ring {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
    }
    .timeout-body { padding: 28px 32px 32px; }
    .timeout-body .pesan-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .9rem;
      color: #7f1d1d;
      margin-bottom: 22px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }
    /* Progress bar countdown */
    .countdown-bar {
      height: 6px;
      border-radius: 3px;
      background: #e5e7eb;
      overflow: hidden;
      margin-bottom: 6px;
    }
    .countdown-bar .fill {
      height: 100%;
      background: linear-gradient(90deg, #dc2626, #f97316);
      border-radius: 3px;
      transition: width .9s linear;
    }
    .countdown-label {
      font-size: .78rem;
      color: #6b7280;
      text-align: right;
      margin-bottom: 22px;
    }
    .btn-login {
      background: linear-gradient(135deg, #0f3460, #16213e);
      color: #fff;
      border: 0;
      border-radius: 10px;
      padding: 12px;
      font-weight: 600;
      font-size: 1rem;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      transition: opacity .15s;
    }
    .btn-login:hover { opacity: .88; color: #fff; }
    .info-row {
      display: flex;
      justify-content: space-between;
      font-size: .8rem;
      color: #9ca3af;
      margin-top: 16px;
    }
  </style>
</head>
<body>

<div class="timeout-card">

  <!-- Header merah -->
  <div class="timeout-header">
    <div class="icon-ring">
      <i class="bi bi-clock-history" style="font-size:2rem"></i>
    </div>
    <h4 class="fw-bold mb-1">Sesi Berakhir</h4>
    <p class="mb-0 opacity-75" style="font-size:.9rem">Inventaris FIFO + EWS</p>
  </div>

  <!-- Body -->
  <div class="timeout-body">

    <!-- Pesan penyebab timeout -->
    <div class="pesan-box">
      <i class="bi bi-exclamation-triangle-fill text-danger flex-shrink-0 mt-1"></i>
      <span><?= $pesan ?></span>
    </div>

    <!-- Countdown redirect otomatis -->
    <div class="countdown-bar">
      <div class="fill" id="fillBar" style="width:100%"></div>
    </div>
    <div class="countdown-label">
      Redirect otomatis dalam <strong id="cdNum">10</strong> detik…
    </div>

    <!-- Tombol login -->
    <a href="login.php" class="btn-login text-decoration-none">
      <i class="bi bi-box-arrow-in-right"></i>
      Login Kembali
    </a>

    <div class="info-row">
      <span><i class="bi bi-shield-lock me-1"></i>Sesi dihapus untuk keamanan</span>
      <span id="waktuSekarang"></span>
    </div>

  </div><!-- /timeout-body -->
</div><!-- /timeout-card -->

<script>
(function () {
  var total  = 10;   // detik countdown
  var sisa   = total;
  var bar    = document.getElementById('fillBar');
  var label  = document.getElementById('cdNum');
  var waktu  = document.getElementById('waktuSekarang');

  // Tampilkan jam sekarang
  function padZ(n) { return n < 10 ? '0' + n : n; }
  function updateWaktu() {
    var d = new Date();
    waktu.textContent = padZ(d.getHours()) + ':' + padZ(d.getMinutes()) + ':' + padZ(d.getSeconds());
  }
  updateWaktu();
  setInterval(updateWaktu, 1000);

  // Countdown
  var timer = setInterval(function () {
    sisa--;
    label.textContent = sisa;
    bar.style.width = ((sisa / total) * 100) + '%';
    if (sisa <= 0) {
      clearInterval(timer);
      window.location.href = 'login.php';
    }
  }, 1000);
})();
</script>

</body>
</html>
