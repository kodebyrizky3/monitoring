<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<style>
  .card-stat{ border:0; border-radius:.9rem; color:#fff; }
  .card-stat .card-body{ min-height:110px; display:flex; justify-content:space-between; align-items:flex-start; }
  .card-stat .stat-title{ font-size:.9rem; opacity:.9; margin-bottom:.25rem; }
  .card-stat .stat-value{ font-size:2rem; font-weight:700; line-height:1; }
  .card-stat .stat-sub{ font-size:.9rem; opacity:.9; margin-top:.35rem; }
  .card-stat .stat-icon{ font-size:2.2rem; opacity:.9; align-self:center; }
  .emp-table th,.emp-table td{ white-space:nowrap; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Kartu Statistik -->
<div class="row g-3 mb-3">
  <div class="col-12 col-xl-3 col-md-6">
    <div class="card shadow-sm card-stat bg-primary">
      <div class="card-body">
        <div>
          <div class="stat-title">Total AC</div>
          <div class="stat-value" id="statTotal"><?= (int)($stats['total'] ?? 0) ?></div>
          <div class="stat-sub">semua perangkat</div>
        </div>
        <i class="bi bi-box-seam stat-icon"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-3 col-md-6">
    <div class="card shadow-sm card-stat bg-warning text-dark">
      <div class="card-body">
        <div>
          <div class="stat-title">Menunggu Perbaikan</div>
          <div class="stat-value" id="statPending"><?= (int)($stats['pending'] ?? 0) ?></div>
          <div class="stat-sub">butuh tindakan</div>
        </div>
        <i class="bi bi-hourglass-split stat-icon"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-3 col-md-6">
    <div class="card shadow-sm card-stat bg-info">
      <div class="card-body">
        <div>
          <div class="stat-title">Dalam Perbaikan</div>
          <div class="stat-value" id="statProgress"><?= (int)($stats['progress'] ?? 0) ?></div>
          <div class="stat-sub">sedang dikerjakan</div>
        </div>
        <i class="bi bi-tools stat-icon"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-3 col-md-6">
    <div class="card shadow-sm card-stat bg-success">
      <div class="card-body">
        <div>
          <div class="stat-title">Normal</div>
          <div class="stat-value" id="statNormal"><?= (int)($stats['normal'] ?? 0) ?></div>
          <div class="stat-sub">berjalan baik</div>
        </div>
        <i class="bi bi-patch-check stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<!-- Toolbar -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-lg-5">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="qInput" class="form-control"
                 placeholder="QR / Nama / Tipe / Lokasi">
        </div>
      </div>

      <div class="col-6 col-lg-3">
        <label class="form-label">Status</label>
        <select id="statusSelect" class="form-select">
          <option value="">Semua</option>
          <option value="MENUNGGU_PERBAIKAN">Menunggu Perbaikan</option>
          <option value="DALAM_PERBAIKAN">Dalam Perbaikan</option>
          <option value="NORMAL">Normal</option>
        </select>
      </div>

      <div class="col-6 col-lg-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>"><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="col-12 col-lg-2 d-flex align-items-end">
        <a href="<?= route_to('admin.ac.add') ?>" class="btn btn-primary ms-auto">
          <i class="bi bi-qr-code-scan me-1"></i> Tambah via QR
        </a>
      </div>
    </form>
    <div id="liveInfo" class="small text-muted mt-2"></div>
  </div>
</div>

<!-- Tabel -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Data Alat AC</strong>
    <div class="small text-muted">Total: <span id="acTotal"><?= (int)($stats['total'] ?? 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 emp-table">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th>QR</th>
            <th>Nama</th>
            <th>Tipe/Model</th>
            <th>Lokasi</th>
            <th>Status</th>
            <th style="width:160px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody id="acTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= esc($r['id']) ?></td>
              <td><code><?= esc($r['kode_qr']) ?></code></td>
              <td><?= esc($r['nomor_unik']) ?></td>
              <td><?= esc($r['tipe_model']) ?></td>
              <td><?= esc($r['lokasi']) ?></td>
              <td><span class="badge bg-secondary"><?= esc($r['status_ac']) ?></span></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= route_to('admin.ac.show',$r['id']) ?>"><i class="bi bi-eye"></i></a>
                  <a class="btn btn-outline-primary" href="<?= route_to('admin.ac.edit',$r['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-success" href="<?= route_to('admin.ac.qr.download',$r['id']) ?>"><i class="bi bi-download"></i></a>
                  <button class="btn btn-outline-danger btn-delete"
                          data-url="<?= route_to('admin.ac.delete',$r['id']) ?>"
                          data-name="<?= esc($r['nomor_unik']) ?>"><i class="bi bi-trash"></i></button>
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

<!-- Delete form (hidden, untuk submit redirect) -->
<form id="deleteForm" class="d-none" method="post">
  <?= csrf_field() ?>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  window.APP = {
    acSearch: '<?= rtrim(base_url('admin/data-alat/ac/search'), '/') ?>',
    flash: {
      ok:  <?= json_encode(session()->getFlashdata('ok')  ?? '') ?>,
      err: <?= json_encode(session()->getFlashdata('err') ?? '') ?>,
    }
  };
</script>
<script src="<?= base_url('assets/js-admin/ac-units.js') ?>?v=1.3.0"></script>
<?= $this->endSection() ?>
