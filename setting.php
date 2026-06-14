<?php
include 'koneksi.php';
cek_login();

// Proteksi: hanya admin
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: indeks.php");
    exit;
}

$pesan = '';

// Pastikan tabel setting_toko ada (jaga-jaga jika SQL lama belum dimigrasi)
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS setting_toko (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko VARCHAR(150) NOT NULL DEFAULT 'Toko Saya',
    alamat VARCHAR(255) DEFAULT NULL,
    no_hp VARCHAR(30) DEFAULT NULL,
    threshold_merah INT NOT NULL DEFAULT 30,
    threshold_kuning INT NOT NULL DEFAULT 90,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// ── PROSES SIMPAN ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_toko = trim(mysqli_real_escape_string($koneksi, $_POST['nama_toko'] ?? ''));
    $alamat    = trim(mysqli_real_escape_string($koneksi, $_POST['alamat'] ?? ''));
    $no_hp     = trim(mysqli_real_escape_string($koneksi, $_POST['no_hp'] ?? ''));
    $t_merah   = intval($_POST['threshold_merah'] ?? 30);
    $t_kuning  = intval($_POST['threshold_kuning'] ?? 90);

    if ($nama_toko === '') {
        $pesan = ['type' => 'danger', 'text' => 'Nama toko tidak boleh kosong.'];
    } elseif ($t_merah <= 0 || $t_kuning <= 0) {
        $pesan = ['type' => 'danger', 'text' => 'Threshold harus berupa angka positif.'];
    } elseif ($t_merah >= $t_kuning) {
        $pesan = ['type' => 'danger',
                  'text' => "Threshold Kritis ($t_merah hari) harus LEBIH KECIL dari Threshold Peringatan ($t_kuning hari)."];
    } else {
        // Cek apakah sudah ada baris setting
        $cek = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id FROM setting_toko LIMIT 1"));
        if ($cek) {
            mysqli_query($koneksi, "UPDATE setting_toko SET
                nama_toko='$nama_toko', alamat='$alamat', no_hp='$no_hp',
                threshold_merah=$t_merah, threshold_kuning=$t_kuning
                WHERE id={$cek['id']}");
        } else {
            mysqli_query($koneksi, "INSERT INTO setting_toko
                (nama_toko, alamat, no_hp, threshold_merah, threshold_kuning)
                VALUES ('$nama_toko','$alamat','$no_hp',$t_merah,$t_kuning)");
        }
        $pesan = ['type' => 'success', 'text' => 'Pengaturan berhasil disimpan.'];

        // Refresh nilai threshold lokal agar preview di bawah langsung update
        $THRESHOLD_MERAH  = $t_merah;
        $THRESHOLD_KUNING = $t_kuning;
    }
}

// ── DATA SAAT INI ──────────────────────────────────────────────────────────
$setting = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM setting_toko LIMIT 1"));
if (!$setting) {
    $setting = [
        'nama_toko' => 'Toko Saya', 'alamat' => '', 'no_hp' => '',
        'threshold_merah' => $THRESHOLD_MERAH, 'threshold_kuning' => $THRESHOLD_KUNING,
    ];
}

// Statistik dampak threshold (preview real-time terhadap nilai DI FORM, bukan tersimpan)
$tgl = date('Y-m-d');
function hitungDampak($koneksi, $tgl, $merah, $kuning) {
    $k = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN 0 AND $merah AND stok>0"));
    $w = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk WHERE DATEDIFF(tgl_expired,'$tgl') BETWEEN " . ($merah+1) . " AND $kuning AND stok>0"));
    $a = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) as t FROM produk WHERE DATEDIFF(tgl_expired,'$tgl') > $kuning AND stok>0"));
    return [$k['t'], $w['t'], $a['t']];
}
[$dampak_kritis, $dampak_warning, $dampak_aman] = hitungDampak($koneksi, $tgl, $setting['threshold_merah'], $setting['threshold_kuning']);

include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pengaturan – Inventaris FIFO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
    .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
    .ews-preview { border-radius:10px; padding:14px; text-align:center; }
    .range-track { position:relative; height:34px; border-radius:8px; overflow:hidden; display:flex; }
    .range-seg { display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#fff; }
  </style>
</head>
<body>
<div class="container-fluid px-4 py-3" style="max-width:900px">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-0">
        <i class="bi bi-sliders me-2 text-primary"></i>Pengaturan Sistem
      </h4>
      <small class="text-muted">Master Data › Threshold EWS & Info Toko</small>
    </div>
  </div>

  <!-- Alert -->
  <?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $pesan['type'] ?> alert-dismissible d-flex align-items-center gap-2">
    <i class="bi bi-<?= $pesan['type']==='success'?'check-circle':'exclamation-triangle' ?>-fill"></i>
    <?= htmlspecialchars($pesan['text']) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <form method="POST" id="formSetting">
  <div class="row g-3">

    <!-- Info Toko -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header py-3" style="background:linear-gradient(135deg,#0f3460,#16213e)">
          <h6 class="mb-0 text-white"><i class="bi bi-shop me-1"></i>Informasi Toko</h6>
        </div>
        <div class="card-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Toko <span class="text-danger">*</span></label>
            <input type="text" name="nama_toko" class="form-control"
                   value="<?= htmlspecialchars($setting['nama_toko']) ?>" required>
            <div class="form-text">Akan tampil di header struk transaksi & laporan.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Alamat</label>
            <textarea name="alamat" class="form-control" rows="2"
                      placeholder="Opsional"><?= htmlspecialchars($setting['alamat'] ?? '') ?></textarea>
          </div>
          <div class="mb-1">
            <label class="form-label fw-semibold">No. Telepon / WhatsApp</label>
            <input type="text" name="no_hp" class="form-control"
                   value="<?= htmlspecialchars($setting['no_hp'] ?? '') ?>" placeholder="Opsional">
          </div>
        </div>
      </div>
    </div>

    <!-- Threshold EWS -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header py-3" style="background:linear-gradient(135deg,#0f3460,#16213e)">
          <h6 class="mb-0 text-white"><i class="bi bi-thermometer-half me-1"></i>Threshold Early Warning System</h6>
        </div>
        <div class="card-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">
              <span class="badge bg-danger me-1">Kritis</span>
              Batas hari sisa (≤)
            </label>
            <div class="input-group">
              <input type="number" name="threshold_merah" id="inpMerah" class="form-control"
                     value="<?= (int)$setting['threshold_merah'] ?>" min="1" max="365" required>
              <span class="input-group-text">hari</span>
            </div>
            <div class="form-text">Produk dengan sisa ≤ nilai ini berstatus <strong>Kritis</strong> (merah).</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">
              <span class="badge bg-warning text-dark me-1">Peringatan</span>
              Batas hari sisa (≤)
            </label>
            <div class="input-group">
              <input type="number" name="threshold_kuning" id="inpKuning" class="form-control"
                     value="<?= (int)$setting['threshold_kuning'] ?>" min="2" max="730" required>
              <span class="input-group-text">hari</span>
            </div>
            <div class="form-text">Produk dengan sisa antara batas Kritis dan nilai ini berstatus <strong>Peringatan</strong> (kuning). Lebih dari ini = <strong>Aman</strong> (hijau).</div>
          </div>

          <!-- Preview visual rentang -->
          <label class="form-label fw-semibold mt-2">Pratinjau Rentang Status</label>
          <div class="range-track mb-1">
            <div class="range-seg bg-danger" style="width:30%">Kritis</div>
            <div class="range-seg bg-warning text-dark" style="width:35%">Peringatan</div>
            <div class="range-seg bg-success" style="width:35%">Aman</div>
          </div>
          <div class="d-flex justify-content-between small text-muted">
            <span>0 hari</span>
            <span id="lblMerah">≤ <?= (int)$setting['threshold_merah'] ?> hari</span>
            <span id="lblKuning">≤ <?= (int)$setting['threshold_kuning'] ?> hari</span>
            <span>seterusnya</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Dampak perubahan -->
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0 fw-semibold">
            <i class="bi bi-graph-up me-1 text-primary"></i>Dampak Threshold Saat Ini Terhadap Data
          </h6>
        </div>
        <div class="card-body">
          <div class="row g-3 text-center">
            <div class="col-md-4">
              <div class="ews-preview bg-danger bg-opacity-10">
                <div class="fs-3 fw-bold text-danger"><?= $dampak_kritis ?></div>
                <div class="text-muted small">Batch berstatus Kritis</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ews-preview bg-warning bg-opacity-10">
                <div class="fs-3 fw-bold text-warning"><?= $dampak_warning ?></div>
                <div class="text-muted small">Batch berstatus Peringatan</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ews-preview bg-success bg-opacity-10">
                <div class="fs-3 fw-bold text-success"><?= $dampak_aman ?></div>
                <div class="text-muted small">Batch berstatus Aman</div>
              </div>
            </div>
          </div>
          <p class="text-muted small mb-0 mt-3">
            <i class="bi bi-info-circle me-1"></i>
            Angka di atas dihitung berdasarkan nilai threshold yang <strong>tersimpan saat ini</strong>.
            Simpan pengaturan untuk melihat dampak dari nilai baru.
          </p>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
      <a href="indeks.php" class="btn btn-secondary">
        <i class="bi bi-x-circle me-1"></i>Batal
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-1"></i>Simpan Pengaturan
      </button>
    </div>
  </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Update label pratinjau secara live saat input diubah
const inpMerah  = document.getElementById('inpMerah');
const inpKuning = document.getElementById('inpKuning');
const lblMerah  = document.getElementById('lblMerah');
const lblKuning = document.getElementById('lblKuning');

function updatePreview() {
    lblMerah.textContent  = '≤ ' + (inpMerah.value || 0) + ' hari';
    lblKuning.textContent = '≤ ' + (inpKuning.value || 0) + ' hari';
}
inpMerah.addEventListener('input', updatePreview);
inpKuning.addEventListener('input', updatePreview);

// Validasi: merah harus < kuning sebelum submit
document.getElementById('formSetting').addEventListener('submit', function(e){
    const m = parseInt(inpMerah.value), k = parseInt(inpKuning.value);
    if (m >= k) {
        e.preventDefault();
        alert('Threshold Kritis (' + m + ' hari) harus lebih kecil dari Threshold Peringatan (' + k + ' hari).');
    }
});
</script>
</body>
</html>