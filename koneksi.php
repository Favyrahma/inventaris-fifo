    <?php
    if (session_status() === PHP_SESSION_NONE) {
        // Konfigurasi cookie sesi — HttpOnly + SameSite sebelum session_start()
        session_set_cookie_params([
            'lifetime' => 0,          // cookie hilang saat browser ditutup
            'path'     => '/',
            'httponly' => true,        // tidak bisa diakses JavaScript
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // ═══════════════════════════════════════════════════════════════
    // SESSION TIMEOUT — konfigurasi & pengecekan
    // ═══════════════════════════════════════════════════════════════
    define('SESSION_IDLE_LIMIT',   30 * 60);   // 30 menit tidak aktif → logout
    define('SESSION_ABS_LIMIT',  8 * 60 * 60); // 8 jam mutlak sejak login → logout
    define('SESSION_WARN_BEFORE',       2 * 60); // munculkan peringatan 2 menit sebelum idle timeout

    // Hanya jalankan pengecekan jika user sudah login
    if (isset($_SESSION['user_id'])) {
        $now = time();

        // ── Cek batas waktu MUTLAK (sejak login pertama) ──────────────────
        if (isset($_SESSION['login_time']) && ($now - $_SESSION['login_time']) > SESSION_ABS_LIMIT) {
            _do_timeout('Sesi Anda telah mencapai batas maksimum 8 jam. Silakan login kembali.');
        }

        // ── Cek batas waktu IDLE (sejak aktivitas terakhir) ───────────────
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT) {
            _do_timeout('Sesi Anda berakhir karena tidak aktif selama 30 menit.');
        }

        // ── Perbarui timestamp aktivitas terakhir ─────────────────────────
        $_SESSION['last_activity'] = $now;

        // ── Regenerasi session ID secara berkala (tiap 15 menit) ──────────
        if (!isset($_SESSION['last_regen']) || ($now - $_SESSION['last_regen']) > 900) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = $now;
        }
    }

    /**
     * Hancurkan sesi dan redirect ke halaman timeout dengan pesan.
     * Dipisah jadi fungsi agar bisa dipanggil sebelum output apapun.
     */
    function _do_timeout($pesan = '') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        $url = 'session_timeout.php' . ($pesan ? '?pesan=' . urlencode($pesan) : '');
        header('Location: ' . $url);
        exit;
    }

    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "db_inventaris";

    $koneksi = mysqli_connect($host, $user, $pass, $db);
    if (!$koneksi) {
        die("<div class='alert alert-danger m-3'>Koneksi database gagal: " . mysqli_connect_error() . "</div>");
    }
    mysqli_set_charset($koneksi, "utf8mb4");

    // Threshold EWS & info toko — ambil dari DB jika tabel setting_toko ada, fallback ke default
    $THRESHOLD_MERAH  = 30;
    $THRESHOLD_KUNING = 90;
    $NAMA_TOKO   = 'Toko Saya';
    $ALAMAT_TOKO = '';
    $HP_TOKO     = '';
    $q_set = mysqli_query($koneksi, "SHOW TABLES LIKE 'setting_toko'");
    if (mysqli_num_rows($q_set) > 0) {
        $setting = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM setting_toko LIMIT 1"));
        if ($setting) {
            $THRESHOLD_MERAH  = $setting['threshold_merah']  ?? 30;
            $THRESHOLD_KUNING = $setting['threshold_kuning'] ?? 90;
            $NAMA_TOKO   = $setting['nama_toko'] ?? 'Toko Saya';
            $ALAMAT_TOKO = $setting['alamat']    ?? '';
            $HP_TOKO     = $setting['no_hp']     ?? '';
        }
    }

    // ─────────────────────────────────────────────
    // HELPER FUNCTIONS
    // ─────────────────────────────────────────────

    /** Format tanggal ke format Indonesia: 07 Jun 2026 */
    function tgl_indo($tgl) {
        if (!$tgl || $tgl === '0000-00-00') return '-';
        $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        $d = explode('-', $tgl);
        return $d[2] . ' ' . $bulan[(int)$d[1]] . ' ' . $d[0];
    }

    /** Format angka ke Rupiah */
    function rupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }

    /**
     * Mengembalikan [class_bg, class_text, label] berdasarkan sisa hari.
     * Digunakan di indeks.php (3 elemen destructure).
     */
    function status_expired($sisa, $merah = 30, $kuning = 90) {
        if ($sisa < 0)            return ['bg-secondary', 'text-white', 'Expired'];
        elseif ($sisa <= $merah)  return ['bg-danger',    'text-white', 'Kritis'];
        elseif ($sisa <= $kuning) return ['bg-warning',   'text-dark',  'Peringatan'];
        else                      return ['bg-success',   'text-white', 'Aman'];
    }

    /**
     * Mengembalikan array ['label','class','row'] — digunakan di produk.php & transaksi.php.
     * Alias agar kedua konvensi pemanggilan bisa berjalan.
     */
    function getStatus($sisa, $merah = 30, $kuning = 90) {
        if ($sisa < 0)            return ['label' => 'Expired',     'class' => 'bg-secondary text-white', 'row' => 'table-secondary'];
        elseif ($sisa <= $merah)  return ['label' => 'Kritis',      'class' => 'bg-danger text-white',    'row' => 'table-danger'];
        elseif ($sisa <= $kuning) return ['label' => 'Peringatan',  'class' => 'bg-warning text-dark',    'row' => 'table-warning'];
        else                      return ['label' => 'Aman',        'class' => 'bg-success text-white',   'row' => ''];
    }

    /** Redirect ke login jika belum login, dan inisialisasi timestamp sesi */
    function cek_login() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        // Pastikan timestamp sesi ada (untuk akun yang login sebelum fitur ini ditambahkan)
        if (!isset($_SESSION['login_time']))    $_SESSION['login_time']    = time();
        if (!isset($_SESSION['last_activity'])) $_SESSION['last_activity'] = time();
    }

    /**
     * Generate kode transaksi unik per hari.
     * Format: TRX-20260607-001
     * Nama ini KONSISTEN dengan panggilan di transaksi.php (generateKodeTrx).
     */
    function generateKodeTrx($koneksi) {
        $q = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi WHERE DATE(created_at) = CURDATE()");
        $r = mysqli_fetch_assoc($q);
        return "TRX-" . date('Ymd') . "-" . str_pad(($r['total'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    }

    // Alias generate_no_transaksi → generateKodeTrx (jaga kompatibilitas)
    function generate_no_transaksi($koneksi) {
        return generateKodeTrx($koneksi);
    }

    /** Generate nomor batch otomatis */
    function generate_no_batch($koneksi) {
        $q = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk");
        $r = mysqli_fetch_assoc($q);
        return "BCH-" . str_pad(($r['total'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    }