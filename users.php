  <?php
  include 'koneksi.php';
  cek_login();

  // Proteksi: hanya admin
  if (($_SESSION['user_role'] ?? '') !== 'admin') {
      header("Location: indeks.php");
      exit;
  }

  $pesan   = '';
  $mode    = $_GET['mode'] ?? 'list';  // list | tambah | edit
  $id_edit = intval($_GET['id'] ?? 0);

  // Jangan bisa edit/hapus diri sendiri via URL langsung
  $id_saya = intval($_SESSION['user_id']);

  // ── PROSES FORM ────────────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $aksi     = $_POST['aksi'] ?? '';
      $nama     = trim(mysqli_real_escape_string($koneksi, $_POST['nama']     ?? ''));
      $username = trim(mysqli_real_escape_string($koneksi, $_POST['username'] ?? ''));
      $role     = in_array($_POST['role'] ?? '', ['admin','kasir']) ? $_POST['role'] : 'kasir';
      $password = $_POST['password'] ?? '';
      $konfirm  = $_POST['konfirm']  ?? '';

      // Validasi field wajib
      if ($nama === '' || $username === '') {
          $pesan = ['type'=>'danger','text'=>'Nama dan username tidak boleh kosong.'];
          $mode  = ($aksi === 'simpan_edit') ? 'edit' : 'tambah';

      } elseif ($aksi === 'simpan_tambah') {
          // Password wajib untuk user baru
          if (strlen($password) < 6) {
              $pesan = ['type'=>'danger','text'=>'Password minimal 6 karakter.'];
              $mode  = 'tambah';
          } elseif ($password !== $konfirm) {
              $pesan = ['type'=>'danger','text'=>'Konfirmasi password tidak cocok.'];
              $mode  = 'tambah';
          } else {
              // Cek username duplikat
              $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
                  "SELECT id FROM users WHERE username='$username'"));
              if ($cek) {
                  $pesan = ['type'=>'warning','text'=>"Username \"$username\" sudah digunakan."];
                  $mode  = 'tambah';
              } else {
                  $hash = password_hash($password, PASSWORD_DEFAULT);
                  mysqli_query($koneksi,
                      "INSERT INTO users (nama,username,password,role)
                      VALUES ('$nama','$username','$hash','$role')");
                  $pesan = ['type'=>'success','text'=>"Pengguna \"$nama\" berhasil ditambahkan."];
                  $mode  = 'list';
              }
          }

      } elseif ($aksi === 'simpan_edit') {
          $id_upd = intval($_POST['id']);
          // Cek username duplikat (kecuali diri sendiri)
          $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
              "SELECT id FROM users WHERE username='$username' AND id != $id_upd"));
          if ($cek) {
              $pesan   = ['type'=>'warning','text'=>"Username \"$username\" sudah digunakan."];
              $mode    = 'edit';
              $id_edit = $id_upd;
          } else {
              if ($password !== '') {
                  // Ganti password
                  if (strlen($password) < 6) {
                      $pesan   = ['type'=>'danger','text'=>'Password minimal 6 karakter.'];
                      $mode    = 'edit';
                      $id_edit = $id_upd;
                  } elseif ($password !== $konfirm) {
                      $pesan   = ['type'=>'danger','text'=>'Konfirmasi password tidak cocok.'];
                      $mode    = 'edit';
                      $id_edit = $id_upd;
                  } else {
                      $hash = password_hash($password, PASSWORD_DEFAULT);
                      mysqli_query($koneksi,
                          "UPDATE users SET nama='$nama',username='$username',
                          password='$hash',role='$role' WHERE id=$id_upd");
                      $pesan = ['type'=>'success','text'=>"Data pengguna berhasil diperbarui (password diubah)."];
                      $mode  = 'list';
                  }
              } else {
                  // Tidak ganti password
                  mysqli_query($koneksi,
                      "UPDATE users SET nama='$nama',username='$username',role='$role'
                      WHERE id=$id_upd");
                  $pesan = ['type'=>'success','text'=>"Data pengguna berhasil diperbarui."];
                  $mode  = 'list';
              }
          }
      }
  }

  // ── HAPUS ──────────────────────────────────────────────────────────────────
  if ($mode === 'hapus' && $id_edit > 0) {
      if ($id_edit === $id_saya) {
          $pesan = ['type'=>'danger','text'=>'Tidak dapat menghapus akun yang sedang login.'];
      } else {
          // Cek apakah user punya riwayat transaksi
          $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
              "SELECT COUNT(*) as total FROM transaksi WHERE kasir_id = $id_edit"));
          if ($cek['total'] > 0) {
              $pesan = ['type'=>'danger',
                        'text'=>"Pengguna tidak dapat dihapus karena memiliki {$cek['total']} riwayat transaksi."];
          } else {
              $nm = mysqli_fetch_assoc(mysqli_query($koneksi,
                  "SELECT nama FROM users WHERE id=$id_edit"))['nama'] ?? '';
              mysqli_query($koneksi, "DELETE FROM users WHERE id=$id_edit");
              $pesan = ['type'=>'success','text'=>"Pengguna \"$nm\" berhasil dihapus."];
          }
      }
      $mode = 'list';
  }

  // ── RESET PASSWORD ─────────────────────────────────────────────────────────
  if ($mode === 'reset' && $id_edit > 0) {
      $hash = password_hash('password', PASSWORD_DEFAULT);
      mysqli_query($koneksi, "UPDATE users SET password='$hash' WHERE id=$id_edit");
      $nm = mysqli_fetch_assoc(mysqli_query($koneksi,
          "SELECT nama FROM users WHERE id=$id_edit"))['nama'] ?? '';
      $pesan = ['type'=>'info',
                'text'=>"Password \"$nm\" berhasil direset ke: <strong>password</strong>"];
      $mode  = 'list';
  }

  // ── DATA VIEW ──────────────────────────────────────────────────────────────
  $q_list = mysqli_query($koneksi,
      "SELECT u.*, COUNT(t.id) as jml_trx
      FROM users u LEFT JOIN transaksi t ON t.kasir_id = u.id
      GROUP BY u.id ORDER BY u.role ASC, u.nama ASC");

  $data_edit = [];
  if ($mode === 'edit' && $id_edit > 0) {
      $data_edit = mysqli_fetch_assoc(mysqli_query($koneksi,
          "SELECT * FROM users WHERE id=$id_edit"));
      if (!$data_edit) { $mode = 'list'; }
  }

  include 'includes/navbar.php';
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manajemen Pengguna – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      body  { background:#f0f2f5; font-family:'Segoe UI',sans-serif; }
      .card { border:0; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
      .table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
      .avatar { width:38px;height:38px;border-radius:10px;display:flex;
                align-items:center;justify-content:center;font-weight:700;
                font-size:1rem;flex-shrink:0; }
      .pass-toggle { cursor:pointer; }
      .strength-bar { height:4px;border-radius:2px;transition:.3s; }
    </style>
  </head>
  <body>
  <div class="container-fluid px-4 py-3" style="max-width:1100px">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-0">
          <i class="bi bi-people me-2 text-primary"></i>Manajemen Pengguna
        </h4>
        <small class="text-muted">Master Data › Akun Pengguna Sistem</small>
      </div>
      <?php if ($mode === 'list'): ?>
      <a href="?mode=tambah" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
      </a>
      <?php endif; ?>
    </div>

    <!-- Alert -->
    <?php if (!empty($pesan)): ?>
    <div class="alert alert-<?= $pesan['type'] ?> alert-dismissible d-flex align-items-center gap-2">
      <i class="bi bi-<?= $pesan['type']==='success'?'check-circle':($pesan['type']==='info'?'info-circle':'exclamation-triangle') ?>-fill"></i>
      <span><?= $pesan['text'] ?></span>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- ── FORM TAMBAH / EDIT ───────────────────────────────────────────── -->
      <?php if ($mode === 'tambah' || $mode === 'edit'): ?>
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header py-3"
              style="background:linear-gradient(135deg,#0f3460,#16213e)">
            <h6 class="mb-0 text-white">
              <i class="bi bi-<?= $mode==='tambah'?'person-plus':'person-gear' ?> me-1"></i>
              <?= $mode==='tambah' ? 'Tambah Pengguna Baru' : 'Edit Data Pengguna' ?>
            </h6>
          </div>
          <div class="card-body p-4">
            <form method="POST" id="formUser" autocomplete="off">
              <?php if ($mode === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $data_edit['id'] ?>">
              <?php endif; ?>
              <input type="hidden" name="aksi"
                    value="<?= $mode==='tambah'?'simpan_tambah':'simpan_edit' ?>">

              <!-- Nama -->
              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Nama Lengkap <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" name="nama" class="form-control"
                        placeholder="Nama lengkap pengguna"
                        value="<?= htmlspecialchars($mode==='edit' ? $data_edit['nama'] : '') ?>"
                        required>
                </div>
              </div>

              <!-- Username -->
              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Username <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-at"></i></span>
                  <input type="text" name="username" class="form-control"
                        placeholder="Username untuk login"
                        value="<?= htmlspecialchars($mode==='edit' ? $data_edit['username'] : '') ?>"
                        pattern="[a-zA-Z0-9_]+" title="Hanya huruf, angka, dan underscore"
                        required>
                </div>
                <div class="form-text">Hanya huruf, angka, dan underscore (_).</div>
              </div>

              <!-- Role -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Role / Hak Akses <span class="text-danger">*</span></label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="role" id="roleAdmin"
                          value="admin"
                          <?= ($mode==='edit'&&$data_edit['role']==='admin')||($mode==='tambah')?'':'checked' ?>
                          <?= ($mode==='edit'&&$data_edit['role']==='admin')?'checked':'' ?>>
                    <label class="form-check-label" for="roleAdmin">
                      <span class="badge bg-danger">Admin</span>
                      <small class="text-muted d-block" style="font-size:11px">Akses penuh</small>
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="role" id="roleKasir"
                          value="kasir"
                          <?= ($mode==='edit'&&$data_edit['role']==='kasir')?'checked':'' ?>
                          <?= $mode==='tambah'?'checked':'' ?>>
                    <label class="form-check-label" for="roleKasir">
                      <span class="badge bg-info text-dark">Kasir</span>
                      <small class="text-muted d-block" style="font-size:11px">Transaksi & stok</small>
                    </label>
                  </div>
                </div>
              </div>

              <hr class="my-3">
              <?php if ($mode === 'edit'): ?>
              <p class="text-muted small mb-2">
                <i class="bi bi-shield-lock me-1"></i>
                Kosongkan password jika tidak ingin mengubah.
              </p>
              <?php endif; ?>

              <!-- Password -->
              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Password <?= $mode==='tambah' ? '<span class="text-danger">*</span>' : '' ?>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" id="inputPassword" class="form-control"
                        placeholder="<?= $mode==='tambah'?'Min. 6 karakter':'Kosongkan jika tidak diubah' ?>"
                        <?= $mode==='tambah'?'required':'' ?>
                        oninput="cekKekuatan(this.value)">
                  <button type="button" class="btn btn-outline-secondary pass-toggle"
                          onclick="togglePass('inputPassword',this)">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <!-- Strength bar -->
                <div class="mt-1 mb-0">
                  <div class="strength-bar bg-secondary w-0" id="strengthBar"></div>
                  <small id="strengthLabel" class="text-muted" style="font-size:11px"></small>
                </div>
              </div>

              <!-- Konfirmasi Password -->
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  Konfirmasi Password <?= $mode==='tambah' ? '<span class="text-danger">*</span>' : '' ?>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" name="konfirm" id="inputKonfirm" class="form-control"
                        placeholder="Ulangi password"
                        <?= $mode==='tambah'?'required':'' ?>>
                  <button type="button" class="btn btn-outline-secondary pass-toggle"
                          onclick="togglePass('inputKonfirm',this)">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <div id="matchInfo" class="form-text"></div>
              </div>

              <div class="d-flex gap-2 justify-content-end">
                <a href="users.php" class="btn btn-secondary btn-sm">
                  <i class="bi bi-x-circle me-1"></i>Batal
                </a>
                <button type="submit"
                        class="btn btn-<?= $mode==='tambah'?'primary':'warning text-white' ?> btn-sm">
                  <i class="bi bi-save me-1"></i>
                  <?= $mode==='tambah'?'Simpan':'Perbarui' ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Info Hak Akses -->
        <div class="card mt-3">
          <div class="card-body p-3">
            <h6 class="fw-semibold mb-3"><i class="bi bi-shield-check me-1 text-primary"></i>Info Hak Akses</h6>
            <div class="mb-2">
              <span class="badge bg-danger me-2">Admin</span>
              <small class="text-muted">Dashboard, Produk, Transaksi, Laporan, Master Data (Kategori & Pengguna)</small>
            </div>
            <div>
              <span class="badge bg-info text-dark me-2">Kasir</span>
              <small class="text-muted">Dashboard, Produk, Transaksi, Laporan (tanpa Master Data)</small>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── TABEL DAFTAR PENGGUNA ─────────────────────────────────────────── -->
      <div class="col-lg-<?= ($mode==='tambah'||$mode==='edit')?'7':'12' ?>">
        <div class="card">
          <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
              <i class="bi bi-people me-1 text-primary"></i>
              Daftar Pengguna
              <span class="badge bg-primary ms-1"><?= mysqli_num_rows($q_list) ?></span>
            </h6>
            <?php if ($mode==='list'): ?>
            <a href="?mode=tambah" class="btn btn-primary btn-sm">
              <i class="bi bi-person-plus me-1"></i>Tambah
            </a>
            <?php endif; ?>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                  <tr>
                    <th class="ps-3" style="width:50px">No</th>
                    <th>Pengguna</th>
                    <th>Username</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Trx</th>
                    <th>Bergabung</th>
                    <th class="text-center" style="width:160px">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($q_list) === 0):
                ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                      <i class="bi bi-inbox me-1"></i>Belum ada pengguna.
                    </td>
                  </tr>
                <?php else: while ($u = mysqli_fetch_assoc($q_list)):
                    $is_me    = ($u['id'] == $id_saya);
                    $is_admin = ($u['role'] === 'admin');
                    // Warna avatar berdasarkan role
                    $av_color = $is_admin ? '#dc3545' : '#0dcaf0';
                    $av_text  = $is_admin ? '#fff' : '#000';
                    $inisial  = mb_strtoupper(mb_substr($u['nama'], 0, 1));
                ?>
                  <tr class="<?= $is_me ? 'table-primary' : '' ?>
                            <?= ($mode==='edit'&&$id_edit==$u['id']) ? 'table-warning' : '' ?>">
                    <td class="ps-3 text-muted"><?= $no++ ?></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="avatar" style="background:<?= $av_color ?>;color:<?= $av_text ?>">
                          <?= $inisial ?>
                        </div>
                        <div>
                          <div class="fw-semibold">
                            <?= htmlspecialchars($u['nama']) ?>
                            <?php if ($is_me): ?>
                              <span class="badge bg-secondary ms-1" style="font-size:10px">Saya</span>
                            <?php endif; ?>
                          </div>
                          <small class="text-muted">ID #<?= $u['id'] ?></small>
                        </div>
                      </div>
                    </td>
                    <td>
                      <code class="text-dark"><?= htmlspecialchars($u['username']) ?></code>
                    </td>
                    <td class="text-center">
                      <span class="badge <?= $is_admin ? 'bg-danger' : 'bg-info text-dark' ?>">
                        <i class="bi bi-<?= $is_admin?'shield-fill':'person-fill' ?> me-1"></i>
                        <?= ucfirst($u['role']) ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-secondary"><?= $u['jml_trx'] ?></span>
                    </td>
                    <td class="small text-muted">
                      <?= $u['created_at'] ? tgl_indo(date('Y-m-d', strtotime($u['created_at']))) : '-' ?>
                    </td>
                    <td class="text-center">
                      <!-- Edit -->
                      <a href="?mode=edit&id=<?= $u['id'] ?>"
                        class="btn btn-outline-warning btn-sm" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>

                      <!-- Reset Password -->
                      <a href="?mode=reset&id=<?= $u['id'] ?>"
                        class="btn btn-outline-info btn-sm"
                        title="Reset password ke default"
                        onclick="return confirm('Reset password <?= addslashes($u['nama']) ?> ke \'password\'?')">
                        <i class="bi bi-arrow-repeat"></i>
                      </a>

                      <!-- Hapus -->
                      <?php if (!$is_me): ?>
                      <a href="?mode=hapus&id=<?= $u['id'] ?>"
                        class="btn btn-outline-danger btn-sm"
                        title="Hapus pengguna"
                        onclick="return confirm('Hapus pengguna \'<?= addslashes($u['nama']) ?>\'?\n\nPengguna dengan riwayat transaksi tidak dapat dihapus.')">
                        <i class="bi bi-trash"></i>
                      </a>
                      <?php else: ?>
                      <button class="btn btn-outline-secondary btn-sm" disabled title="Tidak dapat menghapus akun sendiri">
                        <i class="bi bi-trash"></i>
                      </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer text-muted small bg-white">
            <i class="bi bi-info-circle me-1"></i>
            <span class="badge bg-info text-dark me-1">Reset</span> = reset password ke <strong>password</strong>.
            Pengguna dengan riwayat transaksi tidak dapat dihapus.
            Baris <span class="badge bg-primary" style="font-size:10px">biru</span> = akun Anda saat ini.
          </div>
        </div>
      </div>

    </div><!-- end row -->
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Toggle show/hide password
  function togglePass(inputId, btn) {
      var inp = document.getElementById(inputId);
      var icon = btn.querySelector('i');
      if (inp.type === 'password') {
          inp.type = 'text';
          icon.className = 'bi bi-eye-slash';
      } else {
          inp.type = 'password';
          icon.className = 'bi bi-eye';
      }
  }

  // Password strength indicator
  function cekKekuatan(val) {
      var bar   = document.getElementById('strengthBar');
      var label = document.getElementById('strengthLabel');
      if (!bar) return;
      var score = 0;
      if (val.length >= 6)               score++;
      if (val.length >= 10)              score++;
      if (/[A-Z]/.test(val))            score++;
      if (/[0-9]/.test(val))            score++;
      if (/[^a-zA-Z0-9]/.test(val))     score++;

      var map = [
          ['0%',   'bg-secondary', ''],
          ['25%',  'bg-danger',    'Lemah'],
          ['50%',  'bg-warning',   'Cukup'],
          ['75%',  'bg-info',      'Kuat'],
          ['100%', 'bg-success',   'Sangat Kuat'],
      ];
      var s = val.length === 0 ? 0 : Math.min(score, 4);
      bar.style.width   = map[s][0];
      bar.className     = 'strength-bar ' + map[s][1];
      label.textContent = map[s][2];
  }

  // Konfirmasi password match
  var inp  = document.getElementById('inputPassword');
  var knf  = document.getElementById('inputKonfirm');
  var info = document.getElementById('matchInfo');
  if (knf && inp && info) {
      function cekMatch() {
          if (knf.value === '') { info.textContent = ''; return; }
          if (inp.value === knf.value) {
              info.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Password cocok</span>';
          } else {
              info.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Password tidak cocok</span>';
          }
      }
      knf.addEventListener('input', cekMatch);
      inp.addEventListener('input', cekMatch);
  }
  </script>
  </body>
  </html>
