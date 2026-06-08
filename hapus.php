<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$id = intval($_GET['id'] ?? 0);
if ($id) {
    // Cek apakah ada detail transaksi
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) as total FROM detail_transaksi WHERE produk_id=$id"));
    if ($cek['total'] > 0) {
        echo "<script>alert('Batch tidak dapat dihapus karena sudah memiliki riwayat transaksi!');window.history.back();</script>";
    } else {
        mysqli_query($koneksi,"DELETE FROM stok_log WHERE produk_id=$id");
        mysqli_query($koneksi,"DELETE FROM produk WHERE id=$id");
        echo "<script>alert('Batch berhasil dihapus!');window.location='produk.php';</script>";
    }
} else {
    header("Location: produk.php");
}