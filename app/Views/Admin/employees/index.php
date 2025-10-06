<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/employees.css') ?>?v=1.0.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-2">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm card-stat bg-primary">
      <div class="card-body">
        <div>
          <div class="stat-title">Total Pegawai</div>
          <div class="stat-value"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">semua data</div>
        </div>
        <i class="bi bi-people stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm card-stat bg-success">
      <div class="card-body">
        <div>
          <div class="stat-title">Aktif</div>
          <div class="stat-value"><?= esc($countActive ?? 0) ?></div>
          <div class="stat-sub">bisa dipilih</div>
        </div>
        <i class="bi bi-check2-circle stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<!-- Toolbar -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-4">
        <label class="form-label">Cari</label>
        <input type="text" id="qInput" value="<?= esc($q ?? '') ?>" class="form-control"
               placeholder="Ketik untuk mencari... (Kode / Nama / Email / Telp)">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ((int)($perPage??10)===$pp)?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-12 col-md-6 d-flex align-items-end">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <button type="button" id="btnAdd" class="btn btn-primary ms-auto">
          <i class="bi bi-plus"></i> Tambah
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Tabel (hanya 1x) -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Data Pegawai</strong>
    <div class="small text-muted">Total: <span id="empTotal"><?= esc($countTotal ?? 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 emp-table">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">ID</th>
            <th>Kode</th>
            <th>Nama</th>
            <th>Email</th>
            <th>No. Telp</th>
            <th>Aktif</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody id="empTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td><?= esc($r['id']) ?></td>
              <td><code><?= esc($r['kode_pegawai']) ?></code></td>
              <td><?= esc($r['nama']) ?></td>
              <td><?= esc($r['email']) ?></td>
              <td><?= esc($r['no_telp']) ?></td>
              <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>' ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary btn-edit" data-id="<?= esc($r['id']) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-outline-danger btn-delete"
                          data-id="<?= esc($r['id']) ?>"
                          data-url="<?= base_url('pegawai/'.$r['id']) ?>"
                          data-name="<?= esc($r['nama']) ?>">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white">
    <nav id="empPager"></nav>
  </div>
</div>

<!-- Delete form (hidden) -->
<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="DELETE">
</form>

<!-- Modal Create/Edit -->
<div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="empModalTitle">Tambah Pegawai</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="empForm">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" id="_method" value="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Kode Pegawai</label>
              <input type="text" name="kode_pegawai" class="form-control" required maxlength="32">
              <div class="invalid-feedback" data-err="kode_pegawai"></div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Nama</label>
              <input type="text" name="nama" class="form-control" required maxlength="120">
              <div class="invalid-feedback" data-err="nama"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" maxlength="160">
              <div class="invalid-feedback" data-err="email"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">No. Telp</label>
              <input type="text" name="no_telp" class="form-control" maxlength="32">
              <div class="invalid-feedback" data-err="no_telp"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Aktif</label>
              <select name="is_active" class="form-select">
                <option value="1">Ya</option>
                <option value="0">Tidak</option>
              </select>
              <div class="invalid-feedback" data-err="is_active"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  window.CSRF = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
  window.APP  = {
    pegawai:       '<?= rtrim(base_url('pegawai'), '/') ?>',
    pegawaiSearch: '<?= rtrim(base_url('pegawai/search'), '/') ?>'
  };
</script>
<script src="<?= base_url('assets/js-admin/employees.js') ?>?v=1.1.0"></script>
<?= $this->endSection() ?>
  