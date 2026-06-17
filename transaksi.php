  <?php
  include 'koneksi.php';
  cek_login();
  include 'includes/navbar.php';

  $tgl   = date('Y-m-d');
  $pesan = '';

  // ── Proses simpan transaksi ────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
      $items = json_decode($_POST['items_json'], true);
      $ket   = trim(mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? ''));
      $kasir = $_SESSION['user_id'];

      if (empty($items)) {
          $pesan = 'error:Tidak ada item yang dipilih.';
      } else {
          // generateKodeTrx() kini konsisten tersedia dari koneksi.php
          $kode       = generateKodeTrx($koneksi);
          $total_item = 0;
          $total_nilai= 0;

          foreach ($items as $item) {
              $total_item  += intval($item['qty']);
              $total_nilai += floatval($item['subtotal']);
          }

          mysqli_begin_transaction($koneksi);
          try {
              mysqli_query($koneksi,
                  "INSERT INTO transaksi (kode_transaksi,kasir_id,total_item,total_nilai,keterangan)
                  VALUES ('$kode',$kasir,$total_item,$total_nilai,'$ket')");
              $trx_id = mysqli_insert_id($koneksi);

              foreach ($items as $item) {
                  $pid    = intval($item['produk_id']);
                  $qty    = intval($item['qty']);
                  $harga  = floatval($item['harga_jual']);
                  $subtot = floatval($item['subtotal']);

                  $cur = mysqli_fetch_assoc(mysqli_query($koneksi,
                      "SELECT stok FROM produk WHERE id = $pid"));
                  $stok_before = intval($cur['stok']);
                  $stok_after  = $stok_before - $qty;

                  mysqli_query($koneksi,
                      "UPDATE produk SET stok = $stok_after WHERE id = $pid");

                  mysqli_query($koneksi,
                      "INSERT INTO detail_transaksi (transaksi_id,produk_id,qty,harga_satuan,subtotal)
                      VALUES ($trx_id,$pid,$qty,$harga,$subtot)");

                  mysqli_query($koneksi,
                      "INSERT INTO stok_log (produk_id,jenis,qty,stok_sebelum,stok_sesudah,keterangan)
                      VALUES ($pid,'keluar',$qty,$stok_before,$stok_after,'Transaksi: $kode')");
              }

              mysqli_commit($koneksi);
              $pesan    = "success:$kode";
              $last_trx_id  = $trx_id;   // simpan untuk link struk
          } catch (Exception $e) {
              mysqli_rollback($koneksi);
              $pesan = "error:" . $e->getMessage();
          }
      }
  }

  // ── Produk tersedia (FIFO order) ───────────────────────────────────────────
  $q_produk = mysqli_query($koneksi,
      "SELECT p.*, k.nama_kategori, DATEDIFF(p.tgl_expired,'$tgl') as sisa_hari
      FROM produk p JOIN kategori k ON p.kategori_id = k.id
      WHERE p.stok > 0
      ORDER BY p.tgl_expired ASC");
  $produk_list = [];
  while ($r = mysqli_fetch_assoc($q_produk)) $produk_list[] = $r;

  // ── Riwayat transaksi ─────────────────────────────────────────────────────
  $q_trx = mysqli_query($koneksi,
      "SELECT t.*, u.nama as kasir_nama
      FROM transaksi t JOIN users u ON t.kasir_id = u.id
      ORDER BY t.created_at DESC LIMIT 15");
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Barang Keluar – Inventaris FIFO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
      body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
      .card { border: 0; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
      .produk-item { cursor: pointer; transition: .15s; }
      .produk-item:hover { background: #e8f0fe; }
      .fifo-badge { font-size: 10px; }
    </style>
  </head>
  <body>
  <div class="container-fluid py-4 px-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-cart me-2 text-primary"></i>Transaksi Barang Keluar (FIFO)</h4>

    <?php
    // Deklarasi default (jika tidak ada POST)
    $last_trx_id = $last_trx_id ?? 0;

    if ($pesan !== '' && strpos($pesan, 'success') === 0):
        $kode_sukses = explode(':', $pesan, 2)[1];
        // Ambil ID transaksi dari kode jika $last_trx_id belum tersedia
        if (empty($last_trx_id)) {
            $r_id = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id FROM transaksi WHERE kode_transaksi='".mysqli_real_escape_string($koneksi,$kode_sukses)."' LIMIT 1"));
            $last_trx_id = $r_id['id'] ?? 0;
        }
    ?>
    <div class="alert alert-success alert-dismissible d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <div>
        <i class="bi bi-check-circle-fill me-2"></i>
        Transaksi <strong><?= htmlspecialchars($kode_sukses) ?></strong> berhasil disimpan!
      </div>
      <div class="d-flex gap-2 flex-shrink-0">
        <?php if ($last_trx_id > 0): ?>
        <a href="struk.php?id=<?= $last_trx_id ?>&auto=1" target="_blank"
          class="btn btn-sm btn-warning text-dark fw-semibold">
          <i class="bi bi-printer-fill me-1"></i>Cetak Struk
        </a>
        <a href="struk.php?id=<?= $last_trx_id ?>" target="_blank"
          class="btn btn-sm btn-outline-dark">
          <i class="bi bi-eye me-1"></i>Lihat Struk
        </a>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
    <?php elseif ($pesan !== '' && strpos($pesan, 'error') === 0):
        $pesan_err = explode(':', $pesan, 2)[1];
    ?>
    <div class="alert alert-danger">
      <i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($pesan_err) ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Panel Pilih Produk -->
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="bi bi-list-ul me-1"></i>Daftar Produk (Urutan FIFO)</h6>
            <small class="opacity-75">Expired terdekat tampil pertama</small>
          </div>
          <div class="card-body p-0">
            <div class="p-2 border-bottom">
              <input type="text" id="searchProduk" class="form-control form-control-sm"
                    placeholder="Cari produk...">
            </div>
            <div style="max-height:500px;overflow-y:auto;">
              <table class="table table-sm mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th>Produk</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="tabelProduk">
                <?php foreach ($produk_list as $p):
                    $s = getStatus($p['sisa_hari'], $THRESHOLD_MERAH, $THRESHOLD_KUNING);
                ?>
                  <tr class="produk-item"
                      data-id="<?= $p['id'] ?>"
                      data-nama="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>"
                      data-batch="<?= htmlspecialchars($p['no_batch'], ENT_QUOTES) ?>"
                      data-stok="<?= $p['stok'] ?>"
                      data-harga="<?= $p['harga_jual'] ?>"
                      data-expired="<?= tgl_indo($p['tgl_expired']) ?>">
                    <td>
                      <div class="fw-semibold small"><?= htmlspecialchars($p['nama_produk']) ?></div>
                      <div class="text-muted" style="font-size:11px">
                        <span class="badge bg-dark fifo-badge"><?= $p['no_batch'] ?></span>
                        Exp: <?= tgl_indo($p['tgl_expired']) ?>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-primary"><?= $p['stok'] ?></span>
                    </td>
                    <td class="text-center">
                      <span class="badge <?= $s['class'] ?> fifo-badge"><?= $s['label'] ?></span>
                    </td>
                    <td>
                      <button type="button"
                              class="btn btn-outline-primary btn-sm py-0 px-1 btn-tambah-item"
                              title="Tambah ke keranjang">
                        <i class="bi bi-plus"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel Keranjang -->
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header bg-success text-white">
            <h6 class="mb-0"><i class="bi bi-cart-check me-1"></i>Keranjang Pengeluaran</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-2">
                <thead class="table-light">
                  <tr>
                    <th>Produk</th>
                    <th class="text-center" style="width:100px">Qty</th>
                    <th class="text-end">Harga</th>
                    <th class="text-end">Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="tbodyKeranjang">
                  <tr id="emptyRow">
                    <td colspan="5" class="text-center text-muted py-3">
                      <i class="bi bi-cart me-1"></i>Belum ada item. Klik produk di kiri.
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="fw-bold">
                    <td colspan="3" class="text-end">TOTAL:</td>
                    <td class="text-end text-primary" id="totalNilai">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <form method="POST" id="formTrx">
              <input type="hidden" name="items_json" id="itemsJson">
              <div class="mb-3">
                <label class="form-label fw-semibold">Keterangan</label>
                <input type="text" name="keterangan" class="form-control form-control-sm"
                      placeholder="Opsional (nama penerima, catatan, dll)">
              </div>
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearKeranjang()">
                  <i class="bi bi-trash me-1"></i>Kosongkan
                </button>
                <button type="submit" name="simpan" class="btn btn-success"
                        onclick="return prepareSubmit()">
                  <i class="bi bi-check-circle me-1"></i>Proses Pengeluaran
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Riwayat Transaksi -->
        <div class="card mt-3">
          <div class="card-header bg-white border-bottom py-2">
            <h6 class="mb-0 fw-semibold">
              <i class="bi bi-clock-history me-1 text-secondary"></i>Riwayat Transaksi Terbaru
            </h6>
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Kode</th><th>Tanggal</th><th>Kasir</th>
                  <th class="text-center">Item</th><th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php while ($t = mysqli_fetch_assoc($q_trx)): ?>
                <tr>
                  <td><code><?= htmlspecialchars($t['kode_transaksi']) ?></code></td>
                  <td><?= tgl_indo(date('Y-m-d', strtotime($t['created_at']))) ?></td>
                  <td><?= htmlspecialchars($t['kasir_nama']) ?></td>
                  <td class="text-center"><?= $t['total_item'] ?></td>
                  <td class="text-end"><?= rupiah($t['total_nilai']) ?></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- end col keranjang -->

    </div><!-- end row -->
  </div><!-- end container -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  let keranjang = [];

  function formatRp(n) {
      return 'Rp ' + parseInt(n).toLocaleString('id-ID');
  }

  function renderKeranjang() {
      const tbody  = document.getElementById('tbodyKeranjang');
      const empty  = document.getElementById('emptyRow');
      const total  = document.getElementById('totalNilai');

      if (keranjang.length === 0) {
          tbody.innerHTML = '';
          tbody.appendChild(empty);
          total.textContent = 'Rp 0';
          return;
      }

      let html = '';
      let sum  = 0;
      keranjang.forEach(function(item, i) {
          const sub = item.qty * item.harga;
          sum += sub;
          html += '<tr>' +
              '<td><div class="fw-semibold small">' + item.nama + '</div>' +
              '<div class="text-muted" style="font-size:11px"><span class="badge bg-dark fifo-badge">' + item.batch + '</span> Exp: ' + item.expired + '</div></td>' +
              '<td class="text-center"><div class="input-group input-group-sm" style="width:90px">' +
                  '<button type="button" class="btn btn-outline-secondary py-0 px-1" onclick="ubahQty(' + i + ',-1)">-</button>' +
                  '<input type="number" class="form-control text-center py-0 px-1" value="' + item.qty + '" min="1" max="' + item.stok + '" onchange="setQty(' + i + ',this.value)">' +
                  '<button type="button" class="btn btn-outline-secondary py-0 px-1" onclick="ubahQty(' + i + ',1)">+</button>' +
              '</div></td>' +
              '<td class="text-end small">' + formatRp(item.harga) + '</td>' +
              '<td class="text-end small fw-semibold">' + formatRp(sub) + '</td>' +
              '<td><button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="hapusItem(' + i + ')"><i class="bi bi-x"></i></button></td>' +
          '</tr>';
      });
      tbody.innerHTML = html;
      total.textContent = formatRp(sum);
  }

  function tambahItem(row) {
      var id    = row.dataset.id;
      var nama  = row.dataset.nama;
      var batch = row.dataset.batch;
      var stok  = parseInt(row.dataset.stok);
      var harga = parseFloat(row.dataset.harga);
      var exp   = row.dataset.expired;
      var idx   = keranjang.findIndex(function(k){ return k.id == id; });
      if (idx >= 0) {
          if (keranjang[idx].qty < stok) keranjang[idx].qty++;
          else alert('Stok tidak mencukupi!');
      } else {
          keranjang.push({ id:id, nama:nama, batch:batch, stok:stok, harga:harga, expired:exp, qty:1 });
      }
      renderKeranjang();
  }

  function ubahQty(i, delta) {
      keranjang[i].qty = Math.max(1, Math.min(keranjang[i].stok, keranjang[i].qty + delta));
      renderKeranjang();
  }
  function setQty(i, val) {
      keranjang[i].qty = Math.max(1, Math.min(keranjang[i].stok, parseInt(val) || 1));
      renderKeranjang();
  }
  function hapusItem(i) { keranjang.splice(i, 1); renderKeranjang(); }
  function clearKeranjang() {
      if (confirm('Kosongkan keranjang?')) { keranjang = []; renderKeranjang(); }
  }
  function prepareSubmit() {
      if (keranjang.length === 0) { alert('Keranjang masih kosong!'); return false; }
      var items = keranjang.map(function(k){
          return { produk_id:k.id, qty:k.qty, harga_jual:k.harga, subtotal:k.qty*k.harga };
      });
      document.getElementById('itemsJson').value = JSON.stringify(items);
      return true;
  }

  // Event listener tombol + di tabel
  document.querySelectorAll('.btn-tambah-item').forEach(function(btn){
      btn.addEventListener('click', function(e){
          e.stopPropagation();
          tambahItem(this.closest('tr'));
      });
  });
  // Klik baris
  document.querySelectorAll('#tabelProduk tr.produk-item').forEach(function(row){
      row.addEventListener('click', function(e){
          if (e.target.closest('button')) return;
          tambahItem(this);
      });
  });
  // Search
  document.getElementById('searchProduk').addEventListener('input', function(){
      var val = this.value.toLowerCase();
      document.querySelectorAll('#tabelProduk tr').forEach(function(row){
          row.style.display = row.textContent.toLowerCase().indexOf(val) >= 0 ? '' : 'none';
      });
  });
  </script>
  </body>
  </html>
