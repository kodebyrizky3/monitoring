<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/ac-units.css') ?>?v=1.1.0">
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
          <div class="stat-title">Rusak Ringan</div>
          <div class="stat-value" id="statRingan"><?= (int)($stats['ringan'] ?? 0) ?></div>
          <div class="stat-sub">butuh perhatian</div>
        </div>
        <i class="bi bi-exclamation-triangle stat-icon"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-3 col-md-6">
    <div class="card shadow-sm card-stat bg-danger">
      <div class="card-body">
        <div>
          <div class="stat-title">Rusak Berat</div>
          <div class="stat-value" id="statBerat"><?= (int)($stats['berat'] ?? 0) ?></div>
          <div class="stat-sub">prioritas tinggi</div>
        </div>
        <i class="bi bi-x-octagon stat-icon"></i>
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
                 placeholder="Nama / Tipe / Kapasitas / BMN / Lokasi">
        </div>
      </div>

      <div class="col-6 col-lg-3">
        <label class="form-label">Status</label>
        <select id="statusSelect" class="form-select">
          <option value="">Semua</option>
          <option value="RUSAK_RINGAN">Rusak Ringan</option>
          <option value="RUSAK_BERAT">Rusak Berat</option>
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
        <div class="toolbar-actions ms-auto">
          <a href="<?= route_to('admin.ac.add') ?>" class="btn btn-qr-add" title="Tambah via QR">
            <i class="bi bi-qr-code-scan"></i><span>Tambah via QR</span>
          </a>
          <button type="button" id="btnExport" class="btn btn-export-excel" title="Export ke Excel">
            <i class="bi bi-file-earmark-excel"></i><span>Export Excel</span>
          </button>
        </div>
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
            <th class="col-id">ID</th>
            <th class="col-nama">Nama</th>
            <th class="col-tipe">Tipe/Model</th>
            <th class="col-btu">Kapasitas (BTU)</th>
            <th class="col-bmn">No. BMN</th>
            <th class="col-lokasi">Lokasi</th>
            <th class="col-status">Status</th>
            <th class="col-aksi text-end">Aksi</th>
          </tr>
        </thead>
        <tbody id="acTbody">
          <?php if (empty($rows ?? [])): ?>
            <tr><td colspan="8" class="text-center text-muted">Belum ada data.</td></tr>
          <?php else: foreach ($rows as $r):
            $st = (string)($r['status_ac'] ?? '');
            $badge = ['NORMAL'=>'success','RUSAK_RINGAN'=>'warning','RUSAK_BERAT'=>'danger'][$st] ?? 'secondary';
          ?>
            <tr>
              <td><?= esc($r['id']) ?></td>
              <td class="col-nama"><?= esc($r['nomor_unik']) ?></td>
              <td class="col-tipe"><?= esc($r['tipe_model']) ?></td>
              <td><?= esc($r['kapasitas_btu'] ?? '-') ?></td>
              <td class="col-bmn"><?= esc($r['bmn_no_display'] ?? '-') ?></td>
              <td class="col-lokasi"><?= esc($r['lokasi']) ?></td>
              <td><span class="badge bg-<?= $badge ?>"><?= esc($st) ?></span></td>
              <td class="text-end col-aksi">
                <div class="action-wrap">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= route_to('admin.ac.show',$r['id']) ?>" title="Detail"><i class="bi bi-eye"></i></a>
                  <a class="btn btn-outline-primary btn-sm"  href="<?= route_to('admin.ac.edit',$r['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-success btn-sm"  href="<?= route_to('admin.ac.qr.download',$r['id']) ?>" title="Unduh QR"><i class="bi bi-download"></i></a>
                  <button class="btn btn-outline-danger btn-sm btn-delete"
                          data-url="<?= route_to('admin.ac.delete',$r['id']) ?>"
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
    acExport: '<?= rtrim(base_url('admin/data-alat/ac/export'), '/') ?>', // endpoint export (pastikan route + controller ada)
    flash: {
      ok:  <?= json_encode(session()->getFlashdata('ok')  ?? '') ?>,
      err: <?= json_encode(session()->getFlashdata('err') ?? '') ?>,
    }
  };
</script>
<script src="<?= base_url('assets/js-admin/ac-units.js') ?>?v=1.4.7"></script>
<?= $this->endSection() ?>
