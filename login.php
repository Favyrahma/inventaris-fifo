<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host="localhost";$user="root";$pass="";$db="db_inventaris";
    $koneksi=mysqli_connect($host,$user,$pass,$db);
    mysqli_set_charset($koneksi,"utf8mb4");
    $uname=mysqli_real_escape_string($koneksi,$_POST['username']);
    $q=mysqli_query($koneksi,"SELECT * FROM users WHERE username='$uname' LIMIT 1");
    $u=mysqli_fetch_assoc($q);
    if($u && password_verify($_POST['password'],$u['password'])){
        $_SESSION['user_id']=$u['id'];
        $_SESSION['user_nama']=$u['nama'];
        $_SESSION['user_role']=$u['role'];
        header("Location: indeks.php");
        exit;
    } else { $error="Username atau password salah."; }
}
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Inventaris FIFO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.card{border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);max-width:420px;width:100%;border:0;}
.card-header{background:linear-gradient(135deg,#212529,#343a40);border-radius:16px 16px 0 0!important;}
</style></head><body>
<div class="card"><div class="card-header text-center py-4">
<i class="bi bi-box-seam-fill text-warning" style="font-size:2.5rem"></i>
<h4 class="text-white mt-2 mb-0 fw-bold">Inventaris FIFO</h4>
<p class="text-secondary small mb-0">Monitoring Masa Kedaluwarsa Produk</p>
</div><div class="card-body p-4">
<?php if($error): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?=$error?></div><?php endif; ?>
<form method="POST">
<div class="mb-3"><label class="form-label fw-semibold">Username</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
<input type="text" name="username" class="form-control" placeholder="Username" required autofocus></div></div>
<div class="mb-4"><label class="form-label fw-semibold">Password</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
<input type="password" name="password" class="form-control" placeholder="Password" required></div></div>
<button type="submit" class="btn btn-dark w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-2"></i>Masuk</button>
</form>
<p class="text-center text-muted small mt-3 mb-0">Default login: <strong>admin</strong> / <strong>password</strong></p>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>