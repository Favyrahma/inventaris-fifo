<?php
/**
 * ping_session.php
 * Endpoint AJAX ringan — dipanggil oleh JS di navbar saat user klik
 * "Tetap di Sini" atau saat ada aktivitas mouse/keyboard.
 * Memperbarui $_SESSION['last_activity'] sehingga sesi tidak expired.
 */

// Hanya izinkan request AJAX
if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    exit('Forbidden');
}

// Tidak perlu koneksi DB — hanya perlu session
include 'koneksi.php';   // sudah handle session_start + cek timeout

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'reason' => 'not_logged_in']);
    exit;
}

// Perbarui aktivitas terakhir
$_SESSION['last_activity'] = time();

$sisa_idle = max(0,
    (int)(defined('SESSION_IDLE_LIMIT') ? SESSION_IDLE_LIMIT : 1800)
    - (time() - $_SESSION['last_activity'])
);

echo json_encode([
    'ok'   => true,
    'sisa' => $sisa_idle,
]);
