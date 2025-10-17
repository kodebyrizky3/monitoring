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
          <div class="stat-title">Total Bidang</div>
          <div class="stat-value"><?= esc($countTotal ?? 0) ?></div>
          <div class="stat-sub">aktif di sistem</div>
        </div>
        <i class="bi bi-diagram-3 stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" onsubmit="return false;">
      <div class="col-12 col-md-6">
        <label class="form-label">Cari</label>
        <input type="text" id="qInput" value="<?= esc($q ?? '') ?>" class="form-control" placeholder="Nama bidang...">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Per Halaman</label>
        <select id="perPageSelect" class="form-select">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ((int)($perPage??10)===$pp)?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-end gap-2">
        <div id="liveInfo" class="text-muted small me-auto"></div>
        <button type="button" id="btnAdd" class="btn btn-primary"><i class="bi bi-plus"></i> Tambah</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong>Data Bidang</strong>
    <div class="small text-muted">Total: <span id="bdgTotal"><?= esc($countTotal ?? 0) ?></span></div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0 emp-table">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">No</th>
            <th>Nama Bidang</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody id="bdgTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else:
            $currPage = $pager?->getCurrentPage() ?? 1;
            $no = (($currPage - 1) * ($perPage ?? 10));
            foreach($rows as $r): $no++; ?>
            <tr>
              <td><?= $no ?></td>
              <td><?= esc($r['nama']) ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary btn-edit" data-id="<?= esc($r['id']) ?>"><i class="bi bi-pencil"></i></button>
                  <button type="button" class="btn btn-outline-danger btn-delete"
                          data-id="<?= esc($r['id']) ?>"
                          data-url="<?= base_url('admin/bidang/'.$r['id'].'/delete') ?>"
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
    <nav id="bdgPager"></nav>
  </div>
</div>

<!-- form hapus: hanya POST + CSRF (tanpa _method) -->
<form id="deleteForm" method="post" class="d-none">
  <?= csrf_field() ?>
</form>

<div class="modal fade" id="bdgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bdgModalTitle">Tambah Bidang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="bdgForm">
        <?= csrf_field() ?>
        <!-- HAPUS input _method -->
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Bidang</label>
            <input type="text" name="nama" class="form-control" required maxlength="120">
            <div class="invalid-feedback" data-err="nama"></div>
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
    bidang:        '<?= rtrim(base_url('admin/bidang'), '/') ?>',
    bidangSearch:  '<?= rtrim(base_url('admin/bidang/search'), '/') ?>'
  };
</script>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js-admin/bidang.js') ?>?v=1.1.2"></script>
<?= $this->endSection() ?>
