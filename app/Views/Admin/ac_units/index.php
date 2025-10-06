# app/Views/Admin/ac_units/index.php
<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/ac-units.css') ?>?v=1.0.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- ====== KPI Ringkas (seragam seperti dashboard) ====== -->
<div class="row g-3 mb-2">
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-primary">
      <div class="card-body">
        <div>
          <div class="stat-title">Total AC</div>
          <div class="stat-value"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">Semua Unit</div>
        </div>
        <i class="bi bi-hdd-network stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-success">
      <div class="card-body">
        <div>
          <div class="stat-title">Status NORMAL</div>
          <div class="stat-value"><?= esc($countNormal ?? 0) ?></div>
          <div class="stat-sub">Unit sehat</div>
        </div>
        <i class="bi bi-check2-square stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-warning">
      <div class="card-body">
        <div>
          <div class="stat-title">Menunggu Perbaikan</div>
          <div class="stat-value"><?= esc($countWait ?? 0) ?></div>
          <div class="stat-sub">Butuh tindakan</div>
        </div>
        <i class="bi bi-tools stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-danger">
      <div class="card-body">
        <div>
          <div class="stat-title">Dalam Perbaikan</div>
          <div class="stat-value"><?= esc($countInProgress ?? 0) ?></div>
          <div class="stat-sub">Sedang dikerjakan</div>
        </div>
        <i class="bi bi-hourglass-split stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<!-- ====== Toolbar ====== -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="">
      <div class="col-12 col-md-3">
        <label class="form-label">Cari</label>
        <input type="text" name="q" value="<?= esc($q ?? '') ?>" class="form-control" placeholder="Kode QR / Nomor Unik / Model / Lokasi">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Status</label>
        <?php $opts=[''=>'Semua','NORMAL'=>'NORMAL','MENUNGGU_PERBAIKAN'=>'MENUNGGU_PERBAIKAN','DALAM_PERBAIKAN'=>'DALAM_PERBAIKAN']; ?>
        <select name="status" class="form-select">
          <?php foreach($opts as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($status??'')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select name="perPage" class="form-select" id="perPageSelect">
          <?php foreach([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ((int)($perPage??10) === $pp)?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-secondary flex-grow-1" type="submit"><i class="bi bi-filter"></i> Terapkan</button>
        <button type="button" id="btnAdd" class="btn btn-primary"><i class="bi bi-plus"></i> Tambah</button>
      </div>
      <div class="col-12 col-md-2 text-md-end">
        <button class="btn btn-outline-dark w-100" type="button" id="btnExport"><i class="bi bi-download"></i> Export</button>
      </div>
    </form>
  </div>
</div>

<!-- ====== Tabel Data ====== -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Daftar Unit AC</strong>
    <div class="small text-muted">Total: <?= esc($countTotal ?? 0) ?></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 ac-table">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th>Kode QR</th>
            <th>Nomor Unik</th>
            <th>Model</th>
            <th>BTU</th>
            <th>Lokasi</th>
            <th>Status</th>
            <th style="width:140px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows ?? [])): ?>
          <tr><td colspan="8" class="text-center text-muted">Belum ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= esc($r['id']) ?></td>
            <td><code><?= esc($r['kode_qr']) ?></code></td>
            <td><?= esc($r['nomor_unik']) ?></td>
            <td><?= esc($r['tipe_model']) ?></td>
            <td><?= esc($r['kapasitas_btu']) ?></td>
            <td><?= esc($r['lokasi']) ?></td>
            <td>
              <?php $map=['NORMAL'=>'success','MENUNGGU_PERBAIKAN'=>'warning','DALAM_PERBAIKAN'=>'danger']; $badge=$map[$r['status_ac']]??'secondary'; ?>
              <span class="badge bg-<?= $badge ?>"><?= esc($r['status_ac']) ?></span>
            </td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-primary" href="<?= base_url('admin/ac-units/'.$r['id'].'/edit') ?>"><i class="bi bi-pencil"></i></a>
                <button class="btn btn-outline-danger btn-delete" data-id="<?= esc($r['id']) ?>" data-url="<?= base_url('admin/ac-units/'.$r['id']) ?>"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white">
    <?= $pager->links() ?? '' ?>
  </div>
</div>

<!-- Delete form (hidden) -->
<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="DELETE">
</form>

<!-- ===== Modal Create/Edit ===== -->
<div class="modal fade" id="acModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="acModalTitle">Tambah Unit AC</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="acForm">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" id="_method" value="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Kode QR</label>
              <input type="text" name="kode_qr" class="form-control" required maxlength="64">
              <div class="invalid-feedback" data-err="kode_qr"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nomor Unik</label>
              <input type="text" name="nomor_unik" class="form-control" required maxlength="64">
              <div class="invalid-feedback" data-err="nomor_unik"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipe / Model</label>
              <input type="text" name="tipe_model" class="form-control" required maxlength="120">
              <div class="invalid-feedback" data-err="tipe_model"></div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Kapasitas (BTU)</label>
              <input type="number" name="kapasitas_btu" class="form-control" required min="8000">
              <div class="invalid-feedback" data-err="kapasitas_btu"></div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Lokasi</label>
              <input type="text" name="lokasi" class="form-control" required maxlength="120">
              <div class="invalid-feedback" data-err="lokasi"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status_ac" class="form-select">
                <option value="NORMAL">NORMAL</option>
                <option value="MENUNGGU_PERBAIKAN">MENUNGGU_PERBAIKAN</option>
                <option value="DALAM_PERBAIKAN">DALAM_PERBAIKAN</option>
              </select>
              <div class="invalid-feedback" data-err="status_ac"></div>
            </div>
            <div class="col-12">
              <label class="form-label">Catatan</label>
              <textarea name="catatan" rows="3" class="form-control"></textarea>
              <div class="invalid-feedback" data-err="catatan"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  window.CSRF = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
</script>
<script src="<?= base_url('assets/js-admin/ac-units.js') ?>?v=1.1.0"></script>
<?= $this->endSection() ?>


