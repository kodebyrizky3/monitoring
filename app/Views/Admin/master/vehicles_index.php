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
          <div class="stat-title">Total Kendaraan</div>
          <div class="stat-value"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">aktif di sistem</div>
        </div>
        <i class="bi bi-truck stat-icon"></i>
      </div>
    </div>
  </div>
</div>


<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-5">
        <label class="form-label">Cari</label>
        <input type="text" id="qInput" value="<?= esc($q ?? '') ?>" class="form-control" placeholder="Plat / Nama kendaraan">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ((int)($perPage??10)===$pp)?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Tampilan</label>
        <select id="filterSelect" class="form-select">
          <option value="all">Semua</option>
          <option value="active">Aktif</option>
          <option value="archived">Arsip</option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex align-items-end gap-2">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <button type="button" id="btnAdd" class="btn btn-primary"><i class="bi bi-plus"></i> Tambah</button>
      </div>
    </form>
  </div>
</div>


<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Data Kendaraan</strong>
    <div class="small text-muted">Total: <span id="vehTotal"><?= esc($countTotal ?? 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 emp-table">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">No</th>
            <th>Plat</th>
            <th>Nama</th>
            <th>BBM</th>
            <th>Tank (L)</th>
            <th>KM/L</th>
            <th>Stok (L)</th>
            <th>Aktif</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody id="vehTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="9" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else:
            $no = 0;
            foreach($rows as $r): $no++; ?>
            <tr>
              <td><?= $no ?></td>
              <td><?= esc($r['plat']) ?></td>
              <td><?= esc($r['nama'] ?? '') ?></td>
              <td><?= esc($r['fuel_type']) ?></td>
              <td><?= esc($r['kapasitas_tangki']) ?></td>
              <td><?= esc($r['km_per_liter']) ?></td>
              <td><?= esc($r['stok_liter_terkini']) ?></td>
              <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>' ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary btn-edit" data-id="<?= esc($r['id']) ?>"><i class="bi bi-pencil"></i></button>
                  <button type="button" class="btn btn-outline-danger btn-delete"
                          data-id="<?= esc($r['id']) ?>"
                          data-url="<?= base_url('admin/master/vehicles/'.$r['id']) ?>"
                          data-name="<?= esc($r['plat']) ?>">
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
    <nav id="vehPager"></nav>
  </div>
</div>

<!-- form hapus: hanya POST + CSRF -->
<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
</form>

<!-- Modal Add/Edit -->
<div class="modal fade" id="vehModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vehModalTitle">Tambah Kendaraan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="vehForm">
        <?= csrf_field() ?>
        <input type="hidden" id="vehId" value="">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Plat *</label>
              <input type="text" name="plat" class="form-control" required maxlength="20">
              <div class="invalid-feedback" data-err="plat"></div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Nama</label>
              <input type="text" name="nama" class="form-control" maxlength="120">
              <div class="invalid-feedback" data-err="nama"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipe BBM *</label>
              <select name="fuel_type" class="form-select" required>
                <?php foreach (['PERTALITE','PERTAMAX','SOLAR','DEX','LAINNYA'] as $ft): ?>
                  <option value="<?= $ft ?>"><?= $ft ?></option>
                <?php endforeach ?>
              </select>
              <div class="invalid-feedback" data-err="fuel_type"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Kapasitas Tangki (L) *</label>
              <input type="number" name="kapasitas_tangki" step="0.01" class="form-control" required>
              <div class="invalid-feedback" data-err="kapasitas_tangki"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">KM/L *</label>
              <input type="number" name="km_per_liter" step="0.01" class="form-control" required>
              <div class="invalid-feedback" data-err="km_per_liter"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Stok Awal (L)</label>
              <input type="number" name="stok_liter_terkini" step="0.01" class="form-control">
              <div class="invalid-feedback" data-err="stok_liter_terkini"></div>
            </div>
            <div class="col-md-6">
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
    vehicles:        '<?= rtrim(base_url('admin/master/vehicles'), '/') ?>',
    vehiclesSearch:  '<?= rtrim(base_url('admin/master/vehicles/search'), '/') ?>',
    vehiclesShow:    '<?= rtrim(base_url('admin/master/vehicles'), '/') ?>'
  };
</script>
<script src="<?= base_url('assets/js-admin/vehicles.js') ?>?v=1.2.0"></script>
<?= $this->endSection() ?>
