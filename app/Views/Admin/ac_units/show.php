<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<style>
  .ac-photo{
    width:100%; max-height:320px; object-fit:cover; border-radius:.5rem; border:1px solid #e5e7eb;
  }
  .qr-wrap img{ max-width:280px; }
  .copy-btn{ border:0; background:transparent; padding:0 .35rem; line-height:1; color:#6b7280; }
  .copy-btn:hover{ color:#111827; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Detail Alat AC</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= route_to('admin.ac.index') ?>">
      <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <a class="btn btn-primary" href="<?= route_to('admin.ac.edit', $row['id']) ?>">
      <i class="bi bi-pencil"></i> Edit
    </a>
  </div>
</div>

<?php
  // ==== QR image source ====
  $token     = $row['kode_qr'] ?? '';
  $dataUrl   = $token ? site_url('ac/'.rawurlencode($token)) : '';
  $cachedRel = $token ? 'uploads/qrcodes/'.rawurlencode($token).'.png' : '';
  $cachedAbs = $cachedRel ? FCPATH.$cachedRel : null;
  $qrSrc     = ($token && $cachedAbs && is_file($cachedAbs))
                ? base_url($cachedRel)
                : ($token ? 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&qzone=2&data='.rawurlencode($dataUrl) : '');

  // ==== Badge status (enum) ====
  $badgeMap = ['NORMAL'=>'success','RUSAK_RINGAN'=>'warning','RUSAK_BERAT'=>'danger'];
  $badgeCls = $badgeMap[$row['status_ac'] ?? ''] ?? 'secondary';

  // ==== Serial display ====
  $serialDisplay = trim((string)($row['serial_no'] ?? ''));
  if ($serialDisplay === '' && isset($sn) && trim((string)$sn) !== '') {
    $serialDisplay = trim((string)$sn);
  }

  // Nilai tambahan dari controller (fallback aman)
  $lastServiceAt     = $lastServiceAt     ?? null;
  $lastMaintenanceAt = $lastMaintenanceAt ?? null;
  $lastFreon         = $lastFreon         ?? null;
  $lastFreonAt       = $lastFreonAt       ?? null;
  $lastAmper         = $lastAmper         ?? null;
  $lastAmperAt       = $lastAmperAt       ?? null;

  $merek = $merek ?? '';
  $model = $model ?? '';
?>

<div class="row g-3">
  <!-- KOLOM KIRI -->
  <div class="col-lg-6">

    <!-- FOTO AC -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Foto AC</div>
      <div class="card-body">
        <?php if (!empty($photoUrl)): ?>
          <img src="<?= esc($photoUrl) ?>" alt="Foto AC" class="ac-photo">
          <div class="small text-muted mt-2">
            Pratinjau foto perangkat. Ubah di halaman <a href="<?= route_to('admin.ac.edit', $row['id']) ?>">Edit</a>.
          </div>
        <?php else: ?>
          <div class="text-muted">Belum ada foto perangkat.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RINGKASAN + AKSI HAPUS -->
    <div class="card shadow-sm">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Nama Perangkat</dt>
          <dd class="col-sm-8"><?= esc($row['nomor_unik'] ?? '-') ?></dd>

          <dt class="col-sm-4">Merek</dt>
          <dd class="col-sm-8"><?= esc($merek) ?></dd>

          <dt class="col-sm-4">Model</dt>
          <dd class="col-sm-8"><?= esc($model ?: ($row['tipe_model'] ?? '')) ?></dd>

          <dt class="col-sm-4">Serial Number</dt>
          <dd class="col-sm-8">
            <?= $serialDisplay !== '' ? esc($serialDisplay) : '—' ?>
            <?php if ($serialDisplay !== ''): ?>
              <button class="copy-btn" type="button" data-copy="<?= esc($serialDisplay) ?>" title="Salin SN">
                <i class="bi bi-clipboard"></i>
              </button>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4">Nomor BMN</dt>
          <dd class="col-sm-8">
            <?= !empty($row['bmn_no_display']) ? esc($row['bmn_no_display']) : '—' ?>
            <?php if (!empty($row['bmn_no_display'])): ?>
              <button class="copy-btn" type="button" data-copy="<?= esc($row['bmn_no_display']) ?>" title="Salin BMN">
                <i class="bi bi-clipboard"></i>
              </button>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4">Kapasitas BTU</dt>
          <dd class="col-sm-8"><?= !empty($row['kapasitas_btu']) ? esc($row['kapasitas_btu']) : '—' ?></dd>

          <dt class="col-sm-4">Lokasi</dt>
          <dd class="col-sm-8"><?= esc($row['lokasi'] ?? '-') ?></dd>

          <dt class="col-sm-4">Status</dt>
          <dd class="col-sm-8">
            <span class="badge bg-<?= $badgeCls ?>"><?= esc($row['status_ac'] ?? '-') ?></span>
          </dd>

          <dt class="col-sm-4">Terakhir Servis</dt>
          <dd class="col-sm-8">
            <?= $lastServiceAt ? date('d M Y H:i', strtotime($lastServiceAt)) : '—' ?>
          </dd>

          <dt class="col-sm-4">Terakhir Perawatan</dt>
          <dd class="col-sm-8">
            <?= $lastMaintenanceAt ? date('d M Y H:i', strtotime($lastMaintenanceAt)) : '—' ?>
          </dd>

          <dt class="col-sm-4">Tekanan Freon Terakhir</dt>
          <dd class="col-sm-8">
            <?= ($lastFreon !== null && $lastFreon !== '') ? esc($lastFreon) : '—' ?>
            <?php if (!empty($lastFreonAt)): ?>
              <small class="text-muted ms-1">(<?= date('d M Y H:i', strtotime($lastFreonAt)) ?>)</small>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4">Ampere Terakhir</dt>
          <dd class="col-sm-8">
            <?= ($lastAmper !== null && $lastAmper !== '') ? esc($lastAmper) : '—' ?>
            <?php if (!empty($lastAmperAt)): ?>
              <small class="text-muted ms-1">(<?= date('d M Y H:i', strtotime($lastAmperAt)) ?>)</small>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4">Kode QR</dt>
          <dd class="col-sm-8">
            <?= $token ? '<code>'.esc($token).'</code>' : '—' ?>
            <?php if ($token): ?>
              <div class="small mt-1">
                Token teknisi:
                <a href="<?= site_url('ac/'.rawurlencode($token)) ?>" target="_blank">/ac/<?= esc($token) ?></a>
                · <a href="<?= site_url('ac/'.rawurlencode($token).'/perbaikan') ?>" target="_blank">Form Perbaikan</a>
              </div>
            <?php endif; ?>
          </dd>
        </dl>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">ID: <?= (int)($row['id'] ?? 0) ?></span>
        <form id="deleteForm" action="<?= route_to('admin.ac.delete', $row['id']) ?>" method="post" class="m-0">
          <?= csrf_field() ?>
          <button type="button" id="btnDelete" class="btn btn-outline-danger">
            <i class="bi bi-trash"></i> Hapus Perangkat
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- KOLOM KANAN -->
  <div class="col-lg-6">

    <!-- QR -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">QR Perangkat</div>
      <div class="card-body d-flex flex-column align-items-center text-center">
        <?php if ($token): ?>
          <div class="qr-wrap">
            <img id="qrImg" class="img-fluid border rounded p-2" src="<?= esc($qrSrc) ?>" alt="QR: <?= esc($token) ?>">
          </div>
          <div class="mt-3 d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= route_to('admin.ac.qr.download', $row['id']) ?>">
              <i class="bi bi-download me-1"></i> Download PNG
            </a>
            <button class="btn btn-outline-secondary" id="btnPrintQr">
              <i class="bi bi-printer me-1"></i> Cetak
            </button>
          </div>
        <?php else: ?>
          <div class="text-muted">Belum ada kode QR.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIWAYAT PERBAIKAN -->
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
                  <td><?= esc($r['teknisi_nama'] ?? '-') ?></td>
                  <td><?= esc(mb_strimwidth($r['tindakan'] ?? '', 0, 40, '…')) ?></td>
                  <td><?= esc(mb_strimwidth($r['hasil_perbaikan'] ?? '', 0, 40, '…')) ?></td>
                  <td>
                    <?php if (!is_null($r['biaya'])): ?>
                      Rp <?= number_format((float)$r['biaya'],0,',','.') ?>
                    <?php else: ?> — <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  // Cetak QR
  document.getElementById('btnPrintQr')?.addEventListener('click', function(){
    const img = document.getElementById('qrImg');
    if(!img?.src) return;
    const w = window.open('', '_blank', 'width=360,height=520');
    w.document.write(`
      <html><head><title>Cetak QR</title>
      <style>
        body{margin:0;padding:16px;text-align:center;font:14px/1.4 system-ui;}
        img{max-width:100%;height:auto;border:1px solid #ddd;border-radius:8px;padding:8px;}
        .t{margin-top:10px}
      </style></head><body>
      <img src="${img.src}" alt="QR">
      <div class="t"><?= esc($row['nomor_unik'] ?? '-') ?></div>
      <div class="t"><small>Token: <?= esc($token) ?></small></div>
      <script>window.onload=function(){setTimeout(()=>window.print(),250)}<\/script>
      </body></html>`);
    w.document.close();
  });

  // Konfirmasi hapus
  document.getElementById('btnDelete')?.addEventListener('click', async () => {
    const res = await Swal.fire({
      icon: 'warning',
      title: 'Hapus perangkat?',
      html: 'Data AC beserta foto, QR, riwayat perbaikan, dan tiket akan <b>dihapus permanen</b>.',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      reverseButtons: true
    });
    if (res.isConfirmed) document.getElementById('deleteForm').submit();
  });

  // Salin ke clipboard (SN/BMN)
  document.querySelectorAll('.copy-btn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      try{
        await navigator.clipboard.writeText(btn.dataset.copy||'');
        btn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
        setTimeout(()=>{ btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1100);
      }catch(e){}
    });
  });

  // Flash message
  const ok  = <?= json_encode(session()->getFlashdata('ok') ?: '') ?>;
  const err = <?= json_encode(session()->getFlashdata('err') ?: '') ?>;
  if (ok)  Swal.fire({icon:'success', title:'Berhasil', text:ok, timer:1500, showConfirmButton:false});
  if (err) Swal.fire({icon:'error',   title:'Gagal',   text:err});
})();
</script>
<?= $this->endSection() ?>
