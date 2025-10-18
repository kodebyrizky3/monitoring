<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/employees.css') ?>?v=1.5.1">
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
          <div class="stat-sub">bisa login/pakai</div>
        </div>
        <i class="bi bi-check2-circle stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-3">
        <label class="form-label">Cari</label>
        <input type="text" id="qInput" value="<?= esc($q ?? '') ?>" class="form-control" placeholder="Kode / Nama">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ((int)($perPage??10)===$pp)?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Tampilan</label>
        <select id="filterSelect" class="form-select">
          <option value="all"      <?= (($scope??'all')==='all')?'selected':''; ?>>Semua</option>
          <option value="active"   <?= (($scope??'all')==='active')?'selected':''; ?>>Aktif</option>
          <option value="inactive" <?= (($scope??'all')==='inactive')?'selected':''; ?>>Tidak Aktif</option>
          <option value="archived" <?= (($scope??'all')==='archived')?'selected':''; ?>>Arsip</option>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-end gap-2">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <button type="button" id="btnExport" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Export CSV</button>
        <button type="button" id="btnAdd" class="btn btn-primary"><i class="bi bi-plus"></i> Tambah</button>
      </div>
    </form>
  </div>
</div>

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
            <th style="width:80px;">No</th>
            <th>Kode</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Aktif</th>
            <th style="width:180px;"></th>
          </tr>
        </thead>
        <tbody id="empTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="6" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else:
            $currPage = $pager?->getCurrentPage() ?? 1;
            $no = (($currPage - 1) * ($perPage ?? 10));
            foreach($rows as $r): $no++; ?>
            <tr>
              <td><?= $no ?></td>
              <td><code><?= esc($r['kode_pegawai']) ?></code></td>
              <td><?= esc($r['nama']) ?></td>
              <td><?= esc($r['username'] ?? '') ?></td>
              <td><?= (int)$r['is_active'] ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>' ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-info btn-detail" data-id="<?= esc($r['id']) ?>"><i class="bi bi-card-text"></i></button>
                  <button class="btn btn-outline-primary btn-edit" data-id="<?= esc($r['id']) ?>"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-outline-danger btn-delete" data-id="<?= esc($r['id']) ?>" data-url="<?= base_url('admin/pegawai/'.$r['id']) ?>" data-name="<?= esc($r['nama']) ?>"><i class="bi bi-archive"></i></button>
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

<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="DELETE">
</form>

<div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="empModalTitle">Tambah Pegawai</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="empForm" enctype="multipart/form-data">
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
              <label class="form-label">Bidang</label>
              <select name="bidang_id" class="form-select">
                <option value="">— Pilih —</option>
                <?php foreach (($bidangOptions ?? []) as $b): ?>
                  <option value="<?= (int)$b['id'] ?>"><?= esc($b['nama']) ?></option>
                <?php endforeach ?>
              </select>
              <div class="invalid-feedback" data-err="bidang_id"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Username (opsional)</label>
              <input type="text" name="username" class="form-control" maxlength="64">
              <div class="invalid-feedback" data-err="username"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Password (opsional)</label>
              <div class="input-group">
                <input type="password" name="password" class="form-control" maxlength="100">
                <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="bi bi-eye"></i></button>
              </div>
              <div class="invalid-feedback" data-err="password"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Aktif</label>
              <select name="is_active" class="form-select">
                <option value="1">Ya</option>
                <option value="0">Tidak</option>
              </select>
              <div class="invalid-feedback" data-err="is_active"></div>
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
            <!-- Instagram (opsional) -->
            <div class="col-md-6">
              <label class="form-label">Instagram (opsional)</label>
              <div class="input-group">
                <span class="input-group-text">@</span>
                <input type="text" name="instagram_username" class="form-control" maxlength="64" placeholder="username saja (tanpa @)">
              </div>
              <div class="invalid-feedback" data-err="instagram_username"></div>
            </div>
            <!-- Foto (opsional, max 1MB) -->
            <div class="col-md-6">
              <label class="form-label">Foto (opsional, JPG/PNG maks 1MB)</label>
              <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
              <div class="invalid-feedback" data-err="foto"></div>
            </div>

          </div>
        </div>
        <!-- FOOTER DI LUAR FORM -->
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" form="empForm">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="empDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Detail Pegawai</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img id="empAvatar" src="<?= base_url('assets/img/avatar-placeholder.svg') ?>" alt="avatar" class="rounded-circle shadow-sm" style="width:72px;height:72px;object-fit:cover;">
          <div>
            <div class="fw-semibold" id="empName">—</div>
            <div class="text-muted small" id="empKode">—</div>
            <div class="small" id="empBidang">—</div>
          </div>
        </div>
        <div class="row g-2 small" id="empDetailGrid"></div>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  window.CSRF = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
  window.APP  = {
    pegawai:        '<?= rtrim(base_url('admin/pegawai'), '/') ?>',
    pegawaiSearch:  '<?= rtrim(base_url('admin/pegawai/search'), '/') ?>',
    pegawaiExport:  '<?= rtrim(base_url('admin/pegawai/export'), '/') ?>',
    avatarPlaceholder: '<?= base_url('assets/img/avatar-placeholder.png') ?>'
  };
</script>

<script src="<?= base_url('assets/js-admin/employees.js') ?>?v=1.5.1"></script>
<?= $this->endSection() ?>
