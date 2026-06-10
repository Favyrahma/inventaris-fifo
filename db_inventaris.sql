
-- ============================================================
-- UPDATE: tambah kolom deskripsi di tabel kategori (jika belum ada)
-- ============================================================
ALTER TABLE kategori ADD COLUMN IF NOT EXISTS deskripsi TEXT DEFAULT NULL;
ALTER TABLE kategori ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Pastikan tabel users punya kolom created_at
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
