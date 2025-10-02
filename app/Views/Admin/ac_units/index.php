<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/ac-units.css') ?>?v=1.1.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Kartu statistik -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card shadow-sm card-stat bg-primary text-white">
      <div class="card-body">
        <div class="stat-left">
          <div class="stat-title">Total AC</div>
          <div class="stat-value" id="statTotal"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">semua perangkat</div>
        </div>
        <div class="stat-right">
          <img src="<?= base_url('assets/img/logo-kementerian.svg') ?>" alt="" class="stat-logo">
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card shadow-sm card-stat bg-warning">
      <div class="card-body">
        <div class="stat-left">
          <div class="stat-title">Menunggu Perbaikan</div>
          <div class="stat-value" id="statWait"><?= esc($countWait ?? 0) ?></div>
          <div class="stat-sub">butuh tindakan</div>
        </div>
        <div class="stat-right">
          <i class="bi bi-hourglass-split stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card shadow-sm card-stat bg-info text-white">
      <div class="card-body">
        <div class="stat-left">
          <div class="stat-title">Dalam Perbaikan</div>
          <div class="stat-value" id="statDoing"><?= esc($countDoing ?? 0) ?></div>
          <div class="stat-sub">sedang dikerjakan</div>
        </div>
        <div class="stat-right">
          <i class="bi bi-tools stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card shadow-sm card-stat bg-success text-white">
      <div class="card-body">
        <div class="stat-left">
          <div class="stat-title">Normal</div>
          <div class="stat-value" id="statNormal"><?= esc($countNormal ?? 0) ?></div>
          <div class="stat-sub">berjalan baik</div>
        </div>
        <div class="stat-right">
          <i class="bi bi-check2-circle stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toolbar filter kiri, aksi kanan -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-4">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="qInput" class="form-control" placeholder="QR / Nama / Tipe / Lokasi">
        </div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Status</label>
        <select id="statusSelect" class="form-select">
          <option value="">Semua</option>
          <option value="MENUNGGU_PERBAIKAN">Menunggu Perbaikan</option>
          <option value="DALAM_PERBAIKAN">Dalam Perbaikan</option>
          <option value="NORMAL">Normal</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>"><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex align-items-end">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <a href="<?= site_url('admin/data-alat/ac/tambah') ?>" class="btn btn-primary ms-auto">
          <i class="bi bi-qr-code"></i> Tambah via QR
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Data Alat AC</strong>
    <div class="small text-muted">Total: <span id="acTotal"><?= esc(is_countable($rows)? count($rows) : 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 ac-table">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">ID</th>
            <th>QR</th>
            <th>Nama</th>
            <th>Tipe/Model</th>
            <th>Lokasi</th>
            <th>Status</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody id="acTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td><?= esc($r['id']) ?></td>
              <td><code><?= esc($r['kode_qr']) ?></code></td>
              <td><?= esc($r['nomor_unik']) ?></td>
              <td><?= esc($r['tipe_model']) ?></td>
              <td><?= esc($r['lokasi']) ?></td>
              <td>
                <?php
                  $map = ['NORMAL'=>'success','MENUNGGU_PERBAIKAN'=>'warning','DALAM_PERBAIKAN'=>'info'];
                  $cls = $map[$r['status_ac'] ?? ''] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $cls ?>"><?= esc($r['status_ac']) ?></span>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= site_url('admin/data-alat/ac/'.$r['id']) ?>" title="Detail"><i class="bi bi-eye"></i></a>
                  <a class="btn btn-outline-primary" href="<?= site_url('admin/data-alat/ac/'.$r['id'].'/edit') ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-success" href="<?= site_url('admin/data-alat/ac/'.$r['id'].'/qr/download') ?>" title="Unduh QR"><i class="bi bi-download"></i></a>
                  <button class="btn btn-outline-danger btn-delete"
                          data-url="<?= site_url('admin/data-alat/ac/'.$r['id'].'/delete') ?>"
                          data-name="<?= esc($r['nomor_unik']) ?>" title="Hapus"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white">
    <nav id="acPager"></nav>
  </div>
</div>

<!-- Delete form (hidden) -->
<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- SweetAlert2 (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  window.APP = Object.assign({}, window.APP, {
    acSearch: '<?= site_url('admin/data-alat/ac/search') ?>',
    flash: {
      ok:  <?= json_encode($flashOk ?? '') ?>,
      err: <?= json_encode($flashErr ?? '') ?>
    }
  });
</script>
<script src="<?= base_url('assets/js-admin/ac-units.js') ?>?v=1.2.1"></script>
<?= $this->endSection() ?>
