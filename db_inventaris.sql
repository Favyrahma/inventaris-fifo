-- ============================================================
-- DATABASE: db_inventaris
-- Sistem Informasi Inventaris & Early Warning System (EWS)
-- Metode FIFO (First In First Out)
-- ============================================================
-- Skema lengkap (CREATE TABLE) untuk seluruh modul:
--   users, kategori, produk, transaksi, detail_transaksi,
--   stok_log, setting_toko
-- Jalankan file ini dari KOSONG (DROP lalu CREATE) untuk
-- instalasi baru, atau gunakan bagian "MIGRASI" di paling
-- bawah jika database lama sudah ada datanya.
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_inventaris
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_inventaris;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. USERS — akun login (admin & kasir)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. KATEGORI — kategori produk
-- ------------------------------------------------------------
DROP TABLE IF EXISTS kategori;
CREATE TABLE kategori (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL UNIQUE,
    deskripsi     TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. PRODUK — data produk per BATCH (kunci dari metode FIFO)
--    Satu nama produk bisa punya banyak baris (banyak batch)
--    dengan tanggal masuk & tanggal kedaluwarsa berbeda-beda.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS produk;
CREATE TABLE produk (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    no_batch     VARCHAR(50)  NOT NULL UNIQUE,
    nama_produk  VARCHAR(150) NOT NULL,
    kategori_id  INT NOT NULL,
    harga_beli   DECIMAL(15,2) NOT NULL DEFAULT 0,
    harga_jual   DECIMAL(15,2) NOT NULL DEFAULT 0,
    stok         INT NOT NULL DEFAULT 0,
    stok_awal    INT NOT NULL DEFAULT 0,
    tgl_masuk    DATE NOT NULL,
    tgl_expired  DATE NOT NULL,
    keterangan   TEXT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produk_kategori FOREIGN KEY (kategori_id)
        REFERENCES kategori(id) ON DELETE RESTRICT,
    INDEX idx_produk_expired (tgl_expired),
    INDEX idx_produk_nama (nama_produk)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. TRANSAKSI — header transaksi barang keluar
-- ------------------------------------------------------------
DROP TABLE IF EXISTS transaksi;
CREATE TABLE transaksi (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi  VARCHAR(50) NOT NULL UNIQUE,
    kasir_id        INT NOT NULL,
    total_item      INT NOT NULL DEFAULT 0,
    total_nilai     DECIMAL(15,2) NOT NULL DEFAULT 0,
    keterangan      TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaksi_kasir FOREIGN KEY (kasir_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_transaksi_tgl (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. DETAIL_TRANSAKSI — rincian item per transaksi
-- ------------------------------------------------------------
DROP TABLE IF EXISTS detail_transaksi;
CREATE TABLE detail_transaksi (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id  INT NOT NULL,
    produk_id     INT NOT NULL,
    qty           INT NOT NULL,
    harga_satuan  DECIMAL(15,2) NOT NULL,
    subtotal      DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_detail_transaksi FOREIGN KEY (transaksi_id)
        REFERENCES transaksi(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_produk FOREIGN KEY (produk_id)
        REFERENCES produk(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. STOK_LOG — jejak audit perubahan stok (masuk/keluar)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS stok_log;
CREATE TABLE stok_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    produk_id     INT NOT NULL,
    jenis         ENUM('masuk','keluar') NOT NULL,
    qty           INT NOT NULL,
    stok_sebelum  INT NOT NULL,
    stok_sesudah  INT NOT NULL,
    keterangan    VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stoklog_produk FOREIGN KEY (produk_id)
        REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. SETTING_TOKO — konfigurasi threshold EWS & info toko
--    (dibaca otomatis oleh koneksi.php; jika tabel/baris tidak
--     ada, sistem fallback ke 30/90 hari)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS setting_toko;
CREATE TABLE setting_toko (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko        VARCHAR(150) NOT NULL DEFAULT 'Toko Saya',
    alamat           VARCHAR(255) DEFAULT NULL,
    no_hp            VARCHAR(30)  DEFAULT NULL,
    threshold_merah  INT NOT NULL DEFAULT 30,
    threshold_kuning INT NOT NULL DEFAULT 90,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATA AWAL (SAMPLE DATA)
-- ============================================================

-- Setting toko default
INSERT INTO setting_toko (nama_toko, alamat, no_hp, threshold_merah, threshold_kuning) VALUES
('Toko Sumber Sehat', 'Jl. Contoh No. 123, Kota Anda', '0812-3456-7890', 30, 90);

-- User default — password untuk KEDUANYA: "password"
-- Hash bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (nama, username, password, role) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Kasir Utama',   'kasir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kasir');

-- Kategori
INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Obat-obatan',        'Obat resep dan bebas terbatas'),
('Suplemen & Vitamin',  'Vitamin, suplemen kesehatan'),
('Alat Kesehatan',      'Masker, alat tes, perlengkapan medis'),
('Makanan & Minuman',   'Produk konsumsi dengan tanggal kedaluwarsa'),
('Kosmetik',            'Produk perawatan & kecantikan');

-- Produk per batch — tanggal relatif ke hari ini agar status EWS
-- selalu terlihat realistis (Aman/Peringatan/Kritis/Expired) saat demo
INSERT INTO produk (no_batch, nama_produk, kategori_id, harga_beli, harga_jual, stok, stok_awal, tgl_masuk, tgl_expired, keterangan) VALUES
-- Status: KEDALUWARSA (sudah lewat)
('BCH-001', 'Paracetamol 500mg', 1,  5000,  8000, 25, 50, DATE_SUB(CURDATE(), INTERVAL 200 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'Batch lama, belum dimusnahkan'),
-- Status: KRITIS (<= 30 hari)
('BCH-002', 'Paracetamol 500mg', 1,  5500,  8500, 30, 30, DATE_SUB(CURDATE(), INTERVAL 150 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Segera keluarkan dulu'),
('BCH-006', 'Madu Murni 250ml',  4, 35000, 55000, 15, 15, DATE_SUB(CURDATE(), INTERVAL 60 DAY),  DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Hampir habis masa berlaku'),
-- Status: PERINGATAN (31-90 hari)
('BCH-003', 'Vitamin C 1000mg',  2, 12000, 18000, 20, 20, DATE_SUB(CURDATE(), INTERVAL 30 DAY),  DATE_ADD(CURDATE(), INTERVAL 60 DAY), NULL),
('BCH-008', 'Susu Ensure 400g',  4,120000,150000, 10, 10, DATE_SUB(CURDATE(), INTERVAL 20 DAY),  DATE_ADD(CURDATE(), INTERVAL 75 DAY), NULL),
-- Status: AMAN (> 90 hari)
('BCH-004', 'Amoxicillin 500mg', 1,  8000, 13000, 40, 40, DATE_SUB(CURDATE(), INTERVAL 10 DAY),  DATE_ADD(CURDATE(), INTERVAL 365 DAY), NULL),
('BCH-005', 'Antangin JRG',      1,  4000,  6500, 100,100, DATE_SUB(CURDATE(), INTERVAL 5 DAY),   DATE_ADD(CURDATE(), INTERVAL 540 DAY), NULL),
('BCH-007', 'Masker N95',        3, 15000, 25000, 60, 60, DATE_SUB(CURDATE(), INTERVAL 3 DAY),   DATE_ADD(CURDATE(), INTERVAL 700 DAY), NULL),
-- Batch kedua Vitamin C (lebih baru) — untuk demo multi-batch FIFO
('BCH-009', 'Vitamin C 1000mg',  2, 12500, 19000, 50, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 300 DAY), 'Batch baru');

-- Contoh transaksi keluar (untuk demo Laporan Transaksi & Cetak Struk)
INSERT INTO transaksi (kode_transaksi, kasir_id, total_item, total_nilai, keterangan, created_at) VALUES
(CONCAT('TRX-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-001'), 2, 3, 22500, 'Pembelian umum', NOW());

SET @trx_id := LAST_INSERT_ID();

-- Detail transaksi contoh: 2x Paracetamol (BCH-001, expired duluan) + 1x Antangin
INSERT INTO detail_transaksi (transaksi_id, produk_id, qty, harga_satuan, subtotal) VALUES
(@trx_id, (SELECT id FROM produk WHERE no_batch='BCH-001'), 2, 8000, 16000),
(@trx_id, (SELECT id FROM produk WHERE no_batch='BCH-005'), 1, 6500, 6500);

-- Catatan: stok produk di atas SUDAH disesuaikan (dikurangi) pada baris
-- INSERT produk supaya konsisten dengan log contoh ini.
INSERT INTO stok_log (produk_id, jenis, qty, stok_sebelum, stok_sesudah, keterangan, created_at) VALUES
((SELECT id FROM produk WHERE no_batch='BCH-001'), 'keluar', 2, 27, 25, CONCAT('Transaksi: TRX-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-001'), NOW()),
((SELECT id FROM produk WHERE no_batch='BCH-005'), 'keluar', 1, 101, 100, CONCAT('Transaksi: TRX-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-001'), NOW());

-- ============================================================
-- MIGRASI (untuk database LAMA yang sudah berjalan)
-- Jalankan blok di bawah ini SAJA jika tidak ingin DROP tabel
-- yang sudah ada datanya. Aman dijalankan berkali-kali.
-- ============================================================
-- ALTER TABLE kategori   ADD COLUMN IF NOT EXISTS deskripsi  TEXT DEFAULT NULL;
-- ALTER TABLE kategori   ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE users      ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
--
-- CREATE TABLE IF NOT EXISTS setting_toko (
--     id               INT AUTO_INCREMENT PRIMARY KEY,
--     nama_toko        VARCHAR(150) NOT NULL DEFAULT 'Toko Saya',
--     alamat           VARCHAR(255) DEFAULT NULL,
--     no_hp            VARCHAR(30)  DEFAULT NULL,
--     threshold_merah  INT NOT NULL DEFAULT 30,
--     threshold_kuning INT NOT NULL DEFAULT 90,
--     updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB;
--
-- INSERT INTO setting_toko (nama_toko, threshold_merah, threshold_kuning)
-- SELECT 'Toko Saya', 30, 90
-- WHERE NOT EXISTS (SELECT 1 FROM setting_toko);
