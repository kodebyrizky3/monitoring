<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Detail Alat AC</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= route_to('admin.ac.index') ?>">Kembali</a>
    <a class="btn btn-primary" href="<?= route_to('admin.ac.edit', $row['id']) ?>">Edit</a>
  </div>
</div>

<?php if ($m = session()->getFlashdata('ok')): ?>
  <div class="alert alert-success"><?= esc($m) ?></div>
<?php endif ?>
<?php if ($m = session()->getFlashdata('err')): ?>
  <div class="alert alert-danger"><?= esc($m) ?></div>
<?php endif ?>

<div class="row g-3">
  <!-- Kolom kiri: ringkasan -->
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Kode QR</dt>
          <dd class="col-sm-8">
            <code><?= esc($row['kode_qr']) ?></code>
            <?php if (!empty($row['kode_qr'])): ?>
              <div class="small mt-1">
                Token teknisi:
                <a href="<?= site_url('ac/'.rawurlencode($row['kode_qr'])) ?>" target="_blank">/ac/<?= esc($row['kode_qr']) ?></a>
                · <a href="<?= site_url('ac/'.rawurlencode($row['kode_qr']).'/perbaikan') ?>" target="_blank">Form Perbaikan</a>
              </div>
            <?php endif ?>
          </dd>

          <dt class="col-sm-4">Nama Perangkat</dt>
          <dd class="col-sm-8"><?= esc($row['nomor_unik']) ?></dd>

          <dt class="col-sm-4">Merek</dt>
          <dd class="col-sm-8"><?= esc($merek ?? '') ?></dd>

          <dt class="col-sm-4">Model</dt>
          <dd class="col-sm-8"><?= esc($model ?? '') ?></dd>

          <dt class="col-sm-4">Serial Number</dt>
          <dd class="col-sm-8"><?= esc($sn ?? '') ?: '—' ?></dd>

          <dt class="col-sm-4">Lokasi</dt>
          <dd class="col-sm-8"><?= esc($row['lokasi']) ?></dd>

          <dt class="col-sm-4">Kapasitas BTU</dt>
          <dd class="col-sm-8"><?= esc($row['kapasitas_btu']) ?></dd>

          <dt class="col-sm-4">Status</dt>
          <dd class="col-sm-8">
            <span class="badge bg-secondary"><?= esc($row['status_ac']) ?></span>
          </dd>

          <dt class="col-sm-4">Catatan</dt>
          <dd class="col-sm-8"><pre class="mb-0"><?= esc($row['catatan'] ?? '') ?></pre></dd>
        </dl>
      </div>
    </div>
  </div>

  <!-- Kolom kanan: QR + riwayat -->
  <div class="col-lg-6">
    <!-- QR card -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">QR Perangkat</div>
      <div class="card-body d-flex flex-column align-items-center text-center">
        <?php if (!empty($row['kode_qr'])): ?>
          <img id="qrImg" class="img-fluid border rounded p-2"
               src="<?= route_to('admin.qr.show', $row['kode_qr']) ?>"
               alt="QR: <?= esc($row['kode_qr']) ?>" style="max-width:280px">
          <div class="mt-3 d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?= route_to('admin.ac.qr.download', $row['id']) ?>">
              <i class="bi bi-download me-1"></i> Download PNG
            </a>
            <button class="btn btn-outline-secondary" id="btnPrintQr">
              <i class="bi bi-printer me-1"></i> Cetak
            </button>
          </div>
        <?php else: ?>
          <div class="text-muted">Belum ada kode QR.</div>
        <?php endif ?>
      </div>
    </div>

    <!-- Riwayat perbaikan -->
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Riwayat Perbaikan</div>
      <div class="card-body p-0">
        <?php if (empty($repairs)): ?>
          <div class="p-3 text-muted">Belum ada laporan perbaikan.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Waktu</th>
                  <th>Teknisi</th>
                  <th>Tindakan</th>
                  <th>Hasil</th>
                  <th>Biaya</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($repairs as $r): ?>
                  <tr>
                    <td><?= $r['submitted_at'] ? date('d M Y H:i', strtotime($r['submitted_at'])) : '—' ?></td>
                    <td><?= esc($r['teknisi_nama']) ?></td>
                    <td><?= esc(mb_strimwidth($r['tindakan'] ?? '', 0, 40, '…')) ?></td>
                    <td><?= esc(mb_strimwidth($r['hasil_perbaikan'] ?? '', 0, 40, '…')) ?></td>
                    <td>
                      <?php if (!is_null($r['biaya'])): ?>
                        Rp <?= number_format((float)$r['biaya'],0,',','.') ?>
                      <?php else: ?> — <?php endif ?>
                    </td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<div class="mt-3 d-flex justify-content-between">
  <form action="<?= route_to('admin.ac.delete', $row['id']) ?>" method="post"
        onsubmit="return confirm('Hapus AC ini beserta riwayat & tiketnya?')">
    <?= csrf_field() ?>
    <button class="btn btn-outline-danger">Hapus Perangkat</button>
  </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- di section scripts show.php -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    const ok  = "<?= esc(session()->getFlashdata('ok') ?? '') ?>";
    const err = "<?= esc(session()->getFlashdata('err') ?? '') ?>";
    if (ok) {
      Swal.fire({ icon:'success', title:'Berhasil', text: ok, timer: 1600, showConfirmButton:false, timerProgressBar:true });
    } else if (err) {
      Swal.fire({ icon:'error', title:'Gagal', text: err });
    }
  })();
</script>

<script>
(function(){
  const btnPrint = document.getElementById('btnPrintQr');
  btnPrint?.addEventListener('click', function(){
    const img = document.getElementById('qrImg');
    if(!img?.src) return;
    const w = window.open('', '_blank', 'width=360,height=480');
    w.document.write(`
      <html><head><title>Cetak QR</title>
      <style>body{margin:0;padding:16px;text-align:center;font:14px/1.4 system-ui;}
      img{max-width:100%;height:auto;border:1px solid #ddd;border-radius:8px;padding:8px;}
      .t{margin-top:10px}</style></head><body>
      <img src="${img.src}" alt="QR">
      <div class="t"><?= esc($row['nomor_unik']) ?></div>
      <div class="t"><small>Token: <?= esc($row['kode_qr']) ?></small></div>
      <script>window.onload=function(){setTimeout(()=>window.print(),250)}<\/script>
      </body></html>`);
    w.document.close();
  });
})();
</script>
<?= $this->endSection() ?>
