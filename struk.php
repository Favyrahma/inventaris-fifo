    <?php
        /**
         * struk.php — Cetak struk transaksi
         *
         * Parameter GET:
         *   id   (int)  — ID transaksi (wajib)
         *   auto (1)    — auto trigger window.print() begitu halaman load
         */

        include 'koneksi.php';
        cek_login();

        $id          = intval($_GET['id']       ?? 0);
        $auto        = intval($_GET['auto']     ?? 0);
        $uang_masuk  = intval($_GET['uang']     ?? 0);   // uang diterima dari kasir
        $kembalian   = intval($_GET['kembalian']?? 0);   // kembalian ke pelanggan

        if ($id <= 0) {
            echo "<div class='container mt-5 alert alert-danger'>ID transaksi tidak valid.</div>"; exit;
        }

        // ── AMBIL HEADER TRANSAKSI ────────────────────────────────────────────────
        $q_trx = mysqli_fetch_assoc(mysqli_query($koneksi, "
            SELECT t.*, u.nama AS kasir_nama
            FROM transaksi t JOIN users u ON t.kasir_id = u.id
            WHERE t.id = $id LIMIT 1
        "));

        if (!$q_trx) {
            echo "<div class='container mt-5 alert alert-danger'>
                    Transaksi <strong>#$id</strong> tidak ditemukan.</div>"; exit;
        }

        // ── AMBIL DETAIL ITEM ─────────────────────────────────────────────────────
        $q_items = mysqli_query($koneksi, "
            SELECT dt.qty, dt.harga_satuan, dt.subtotal,
                p.nama_produk, p.no_batch
            FROM detail_transaksi dt JOIN produk p ON dt.produk_id = p.id
            WHERE dt.transaksi_id = $id
            ORDER BY dt.id ASC
        ");
        $items = [];
        while ($r = mysqli_fetch_assoc($q_items)) $items[] = $r;

        // ── HPP & LABA (hanya admin — tidak tampil di struk cetak) ───────────────
        $q_hpp  = mysqli_fetch_assoc(mysqli_query($koneksi, "
            SELECT COALESCE(SUM(dt.qty * p.harga_beli), 0) AS hpp
            FROM detail_transaksi dt JOIN produk p ON dt.produk_id = p.id
            WHERE dt.transaksi_id = $id
        "));
        $hpp      = floatval($q_hpp['hpp']);
        $omzet    = floatval($q_trx['total_nilai']);
        $laba     = $omzet - $hpp;
        $margin   = $omzet > 0 ? ($laba / $omzet * 100) : 0;
        $is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Struk <?= htmlspecialchars($q_trx['kode_transaksi']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <style>
            /* ─────────────── LAYAR ─────────────────────────────────────────── */
            body {
            background: #e2e8f0;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 16px 40px;
            }

            .toolbar {
            width: 100%;
            max-width: 400px;
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            }
            .toolbar .btn { flex: 1; font-size: 13px; }

            /* ─────────────── STRUK THERMAL ─────────────────────────────────── */
            .struk-wrap {
            width: 100%;
            max-width: 400px;
            /* Efek kertas bertumpuk */
            position: relative;
            }
            .struk-wrap::before, .struk-wrap::after {
            content: '';
            position: absolute;
            left: 4px; right: 4px;
            height: 100%;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            z-index: 0;
            }
            .struk-wrap::before { top: 6px; }
            .struk-wrap::after  { top: 10px; opacity:.5; }

            .struk {
            background: #fff;
            position: relative;
            z-index: 2;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0,0,0,.14);
            overflow: hidden;
            }

            /* Efek perforasi (gigi atas dan bawah) */
            .perf {
            width: 100%;
            height: 14px;
            background: radial-gradient(
                circle at 50% 0, #e2e8f0 8px, transparent 9px
            ) repeat-x;
            background-size: 18px 14px;
            }
            .perf.bottom {
            transform: scaleY(-1);
            }

            .struk-body {
            padding: 8px 24px 16px;
            font-size: 13px;
            color: #1a1a1a;
            }

            /* Header toko */
            .store-header {
            text-align: center;
            padding: 12px 0 10px;
            }
            .store-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .3px;
            margin-bottom: 3px;
            }
            .store-sub {
            font-size: 11.5px;
            color: #555;
            line-height: 1.6;
            }

            /* Garis putus */
            .dash {
            border: none;
            border-top: 1px dashed #bbb;
            margin: 10px 0;
            }

            /* Baris info transaksi */
            .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 4px;
            gap: 8px;
            }
            .info-row .lbl { color: #666; flex-shrink: 0; }
            .info-row .val { font-weight: 500; text-align: right; word-break: break-word; }

            /* Tabel item */
            .item-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
            }
            .item-table th {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #666;
            padding: 4px 0;
            border-bottom: 1px dashed #bbb;
            }
            .item-table th.r, .item-table td.r { text-align: right; }
            .item-table td {
            padding: 6px 0 4px;
            vertical-align: top;
            font-size: 12.5px;
            }
            .item-name { font-weight: 600; }
            .item-detail {
            font-size: 10.5px;
            color: #777;
            margin-top: 2px;
            display: flex;
            gap: 6px;
            align-items: center;
            }
            .batch-pill {
            background: #1e293b;
            color: #fff;
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 3px;
            letter-spacing: .2px;
            }
            .qty-col { padding-left: 8px !important; white-space: nowrap; }
            .sub-col { text-align: right; white-space: nowrap; font-weight: 500; }

            /* Ringkasan total */
            .total-block { margin: 2px 0; }
            .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 3px;
            }
            .total-row.grand {
            font-size: 17px;
            font-weight: 800;
            border-top: 1.5px solid #111;
            padding-top: 6px;
            margin-top: 4px;
            }
            .total-row.lunas {
            font-size: 12px;
            font-weight: 600;
            color: #059669;
            }
            .total-row.kembalian {
            font-size: 16px;
            font-weight: 800;
            color: #0f766e;
            background: #f0fdf4;
            border-radius: 6px;
            padding: 5px 8px;
            margin: 4px -4px;
            }

            /* Info admin (tidak tercetak) */
            .admin-box {
            background: #f0f9ff;
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 12px;
            font-size: 12px;
            }
            .admin-box .head {
            font-weight: 700;
            color: #0369a1;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
            }
            .admin-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            }
            .admin-row .albl { color: #0369a1; }

            /* Footer struk */
            .struk-footer {
            text-align: center;
            padding: 8px 0 4px;
            font-size: 11.5px;
            color: #777;
            line-height: 1.9;
            }
            .struk-footer .thanks {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 2px;
            }
            .struk-footer .tagline {
            font-size: 10px;
            color: #aaa;
            letter-spacing: .05em;
            margin-top: 4px;
            }

            /* ─────────────── PRINT ──────────────────────────────────────────── */
            @media print {
            html, body {
                background: #fff;
                padding: 0;
                margin: 0;
                display: block;
            }
            .toolbar    { display: none !important; }
            .admin-box  { display: none !important; }
            .struk-wrap { max-width: 100%; }
            .struk-wrap::before, .struk-wrap::after { display: none; }
            .struk      { box-shadow: none; border-radius: 0; }
            .struk-body { padding: 4px 8px 8px; font-size: 10pt; }
            .item-table td, .item-table th { font-size: 9pt; }
            .store-name  { font-size: 13pt; }
            .total-row.grand { font-size: 12pt; }

            @page {
                size: 80mm auto;
                margin: 4mm 3mm;
            }
            }
        </style>
        </head>
        <body>

        <!-- Toolbar (tidak ikut cetak) -->
        <div class="toolbar">
        <button onclick="window.print()" class="btn btn-dark">
            <i class="bi bi-printer-fill me-1"></i>Cetak
        </button>
        <a href="laporan_transaksi.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt me-1"></i>Laporan
        </a>
        <a href="transaksi.php" class="btn btn-outline-primary">
            <i class="bi bi-cart me-1"></i>Transaksi
        </a>
        </div>

        <!-- Struk Thermal -->
        <div class="struk-wrap">
        <div class="struk">

            <!-- Perforasi atas -->
            <div class="perf"></div>

            <div class="struk-body">

            <!-- ── Header Toko ───────────────────────── -->
            <div class="store-header">
                <div class="store-name"><?= htmlspecialchars($NAMA_TOKO) ?></div>
                <?php if ($ALAMAT_TOKO): ?>
                <div class="store-sub"><?= nl2br(htmlspecialchars($ALAMAT_TOKO)) ?></div>
                <?php endif; ?>
                <?php if ($HP_TOKO): ?>
                <div class="store-sub">Telp/WA: <?= htmlspecialchars($HP_TOKO) ?></div>
                <?php endif; ?>
            </div>

            <div class="dash"></div>

            <!-- ── Info Transaksi ─────────────────────── -->
            <div class="info-row">
                <span class="lbl">No. Struk</span>
                <span class="val"><strong><?= htmlspecialchars($q_trx['kode_transaksi']) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="lbl">Tanggal</span>
                <span class="val"><?= tgl_indo(date('Y-m-d', strtotime($q_trx['created_at']))) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Jam</span>
                <span class="val"><?= date('H:i:s', strtotime($q_trx['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Kasir</span>
                <span class="val"><?= htmlspecialchars($q_trx['kasir_nama']) ?></span>
            </div>
            <?php if (!empty($q_trx['keterangan'])): ?>
            <div class="info-row">
                <span class="lbl">Ket.</span>
                <span class="val"><?= htmlspecialchars($q_trx['keterangan']) ?></span>
            </div>
            <?php endif; ?>

            <div class="dash"></div>

            <!-- ── Item Produk (FIFO batch info) ────────── -->
            <table class="item-table">
                <thead>
                <tr>
                    <th>Produk</th>
                    <th class="r qty-col">Qty</th>
                    <th class="r sub-col">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                    <div class="item-name"><?= htmlspecialchars($it['nama_produk']) ?></div>
                    <div class="item-detail">
                        <span class="batch-pill"><?= htmlspecialchars($it['no_batch']) ?></span>
                        <span>@ <?= rupiah($it['harga_satuan']) ?></span>
                    </div>
                    </td>
                    <td class="qty-col r"><?= $it['qty'] ?>x</td>
                    <td class="sub-col"><?= rupiah($it['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="dash"></div>

            <!-- ── Total & Status Bayar ──────────────────── -->
            <div class="total-block">
                <div class="total-row">
                <span>Total Item</span>
                <span><?= $q_trx['total_item'] ?> unit</span>
                </div>
                <div class="total-row grand">
                <span>TOTAL BAYAR</span>
                <span><?= rupiah($omzet) ?></span>
                </div>
                <?php if ($uang_masuk > 0): ?>
                <div class="total-row" style="font-size:13px;margin-top:6px">
                <span>Uang Diterima</span>
                <span><?= rupiah($uang_masuk) ?></span>
                </div>
                <div class="total-row kembalian">
                <span><i class="bi bi-arrow-return-left me-1"></i>Kembalian</span>
                <span><?= rupiah($kembalian) ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row lunas mt-1">
                <span><i class="bi bi-check-circle-fill me-1"></i>LUNAS</span>
                <span><?= date('H:i', strtotime($q_trx['created_at'])) ?></span>
                </div>
            </div>

            <!-- ── Kotak Admin (tidak tercetak) ─────────── -->
            <?php if ($is_admin && $hpp > 0): ?>
            <div class="admin-box">
                <div class="head"><i class="bi bi-eye-fill"></i>Info Admin — tidak ikut dicetak</div>
                <div class="admin-row">
                <span class="albl">HPP Total</span>
                <span><?= rupiah($hpp) ?></span>
                </div>
                <div class="admin-row">
                <span class="albl">Laba Kotor</span>
                <span class="<?= $laba >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                    <?= rupiah($laba) ?>
                </span>
                </div>
                <div class="admin-row">
                <span class="albl">Margin</span>
                <span class="fw-semibold"><?= number_format($margin, 1) ?>%</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Footer ────────────────────────────────── -->
            <div class="struk-footer">
                <div class="thanks">Terima Kasih!</div>
                <div>Silakan cek kembali barang Anda</div>
                <div>Barang yang sudah dibeli</div>
                <div>tidak dapat dikembalikan</div>
                <div class="tagline">— Sistem Inventaris FIFO + EWS —</div>
            </div>

            </div><!-- end struk-body -->

            <!-- Perforasi bawah -->
            <div class="perf bottom"></div>

        </div><!-- end .struk -->
        </div><!-- end .struk-wrap -->

        <script>
        <?php if ($auto): ?>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 450);
        });
        <?php endif; ?>
        </script>
        </body>
        </html>