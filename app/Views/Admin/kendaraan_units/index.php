<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/kendaraan-units.css') ?>?v=1.0.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- ===== KPI ===== -->
<div class="row g-3 mb-2">
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-primary">
      <div class="card-body">
        <div>
          <div class="stat-title">Total Kendaraan</div>
          <div class="stat-value"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">Semua unit</div>
        </div>
        <i class="bi bi-truck stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-success">
      <div class="card-body">
        <div>
          <div class="stat-title">SIAP</div>
          <div class="stat-value"><?= esc($countSiap ?? 0) ?></div>
          <div class="stat-sub">Bisa dipakai</div>
        </div>
        <i class="bi bi-check2-square stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-warning">
      <div class="card-body">
        <div>
          <div class="stat-title">DIPAKAI DINAS</div>
          <div class="stat-value"><?= esc($countDipakai ?? 0) ?></div>
          <div class="stat-sub">Sedang jalan</div>
        </div>
        <i class="bi bi-geo-alt stat-icon"></i>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-danger">
      <div class="card-body">
        <div>
          <div class="stat-title">DI BENGKEL</div>
          <div class="stat-value"><?= esc($countBengkel ?? 0) ?></div>
          <div class="stat-sub">Proses servis</div>
        </div>
        <i class="bi bi-wrench-adjustable stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<!-- ===== Toolbar (live search + filter + perPage) ===== -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-4">
        <label class="form-label">Cari</label>
        <input type="text" id="qInput" value="<?= esc($q ?? '') ?>" class="form-control"
               placeholder="Ketik No. Polisi / Merk Model">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Tipe</label>
        <?php $optTipe=[''=>'Semua','MOBIL'=>'MOBIL','MOTOR'=>'MOTOR','TRUCK'=>'TRUCK','BUS'=>'BUS','LAINNYA'=>'LAINNYA']; ?>
        <select name="tipe" class="form-select">
          <?php foreach($optTipe as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (($tipe ?? '')===$k)?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Status</label>
        <?php $optStatus=[''=>'Semua','SIAP'=>'SIAP','DIPAKAI_DINAS'=>'DIPAKAI_DINAS','DI_BENGKEL'=>'DI_BENGKEL','MENUNGGU_PERBAIKAN'=>'MENUNGGU_PERBAIKAN']; ?>
        <select name="status_kendaraan" class="form-select">
          <?php foreach($optStatus as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (($status_kendaraan ?? '')===$k)?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>"><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-2 d-flex align-items-end">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <button type="button" id="btnAdd" class="btn btn-primary ms-auto">
          <i class="bi bi-plus"></i> Tambah
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Table ===== -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Daftar Kendaraan</strong>
    <div class="small text-muted">Total: <span id="kendTotal"><?= esc($countTotal ?? 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 kendaraan-table">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th>No. Polisi</th>
            <th>Merk/Model</th>
            <th>Tipe</th>
            <th>Tahun</th>
            <th>Odo Terakhir</th>
            <th>Status</th>
            <th style="width:140px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody id="kendTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="8" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= esc($r['id']) ?></td>
              <td><code><?= esc($r['no_polisi']) ?></code></td>
              <td><?= esc($r['merk_model']) ?></td>
              <td><?= esc($r['tipe']) ?></td>
              <td><?= esc($r['tahun']) ?></td>
              <td><?= esc(number_format((int)$r['odometer_terakhir'])) ?></td>
              <td>
                <?php $map=['SIAP'=>'success','DIPAKAI_DINAS'=>'warning','DI_BENGKEL'=>'danger','MENUNGGU_PERBAIKAN'=>'secondary'];
                      $badge=$map[$r['status_kendaraan']]??'secondary'; ?>
                <span class="badge bg-<?= $badge ?>"><?= esc($r['status_kendaraan']) ?></span>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary btn-edit" data-id="<?= esc($r['id']) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-outline-danger btn-delete"
                          data-id="<?= esc($r['id']) ?>"
                          data-url="<?= base_url('kendaraan/delete/'.$r['id']) ?>"
                          data-name="<?= esc($r['no_polisi']) ?>">
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
    <nav id="kendPager"></nav>
  </div>
</div>

<!-- ===== Modal Create/Edit ===== -->
<div class="modal fade" id="kendaraanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="kendaraanModalTitle">Tambah Kendaraan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="kendaraanForm">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" id="_method" value="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">No. Polisi *</label>
              <input type="text" name="no_polisi" class="form-control" required maxlength="20">
              <div class="invalid-feedback" data-err="no_polisi"></div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Merk/Model *</label>
              <input type="text" name="merk_model" class="form-control" required maxlength="120">
              <div class="invalid-feedback" data-err="merk_model"></div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Tipe *</label>
              <select name="tipe" class="form-select">
                <option value="MOBIL">MOBIL</option>
                <option value="MOTOR">MOTOR</option>
                <option value="TRUCK">TRUCK</option>
                <option value="BUS">BUS</option>
                <option value="LAINNYA">LAINNYA</option>
              </select>
              <div class="invalid-feedback" data-err="tipe"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tahun</label>
              <input type="number" name="tahun" class="form-control" min="1900" max="<?= date('Y') ?>">
              <div class="invalid-feedback" data-err="tahun"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Odometer Terakhir (Km) *</label>
              <input type="number" name="odometer_terakhir" class="form-control" required min="0">
              <div class="invalid-feedback" data-err="odometer_terakhir"></div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status *</label>
              <select name="status_kendaraan" class="form-select">
                <option value="SIAP">SIAP</option>
                <option value="DIPAKAI_DINAS">DIPAKAI DINAS</option>
                <option value="DI_BENGKEL">DI BENGKEL</option>
                <option value="MENUNGGU_PERBAIKAN">MENUNGGU PERBAIKAN</option>
              </select>
              <div class="invalid-feedback" data-err="status_kendaraan"></div>
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
    kendaraan:       '<?= rtrim(base_url('kendaraan'), '/') ?>',
    kendaraanSearch: '<?= rtrim(base_url('kendaraan/search'), '/') ?>'
  };
</script>
<!-- pastikan SweetAlert2 sudah dimuat di layout -->
<script src="<?= base_url('assets/js-admin/kendaraan-units.js') ?>?v=2.0.1"></script>
<?= $this->endSection() ?>
