<?php /* Admin: Data Kendala */ ?>
<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin-kendala.css') ?>?v=1.0.2">
<style>
  .type-chip { font-weight:600; }
  .type-chip i { margin-right:.35rem; }
  .u-photo { aspect-ratio:1/1; object-fit:cover; width:100%; display:block; }
  .dl-compact dt { width:32%; }
  .dl-compact dd { width:68%; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<meta name="csrf-token-name" content="<?= csrf_token() ?>">
<meta name="csrf-token-value" content="<?= csrf_hash() ?>">

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <a href="<?= base_url('admin/kendala/export') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export
    </a>
  </div>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input id="searchInput" type="text" class="form-control" placeholder="subject / detail…">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Modul</label>
        <select id="moduleSelect" class="form-select">
          <option value="SEMUA">Semua Modul</option>
          <option value="kendaraan">Kendaraan</option>
          <!-- <option value="ac">AC</option> -->
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select id="statusSelect" class="form-select">
          <option value="SEMUA">Semua Status</option>
          <option value="PENDING">PENDING</option>
          <option value="APPROVED">APPROVED</option>
          <option value="REJECTED">REJECTED</option>
          <option value="DONE">DONE</option>
        </select>
      </div>
      <div class="col-md-2 text-end">
        <div id="liveInfo" class="small text-muted">Memuat…</div>
      </div>
    </div>
  </div>
</div>

<!-- Tabel -->
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:110px">Modul</th>
          <th style="width:180px">Tipe</th>
          <th>Subject</th>
          <th style="width:110px">Status</th>
          <th style="width:160px">Dibuat</th>
          <th style="width:120px">Aksi</th>
        </tr>
      </thead>
      <tbody id="kendTbody">
        <tr><td colspan="6" class="text-center text-muted">Memuat…</td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-end">
    <nav id="kendPager"></nav>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="kendDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <h5 class="modal-title mb-0">Detail</h5>
          <span id="chipModule" class="badge bg-dark-subtle text-dark-emphasis">-</span>
          <span id="chipType" class="badge bg-primary-subtle text-primary-emphasis type-chip">-</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="kendDetailBody"><div class="text-muted">Memuat…</div></div>
      </div>
      <div class="modal-footer">
        <div class="me-auto small text-muted" id="detailMeta">—</div>
        <button id="btnDetailReject" type="button" class="btn btn-outline-danger">
          <i class="bi bi-x"></i> Reject
        </button>
        <button id="btnDetailApprove" type="button" class="btn btn-success">
          <i class="bi bi-check2"></i> Approve
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  window.KENDALA = {
    base: '<?= rtrim(site_url(), '/') ?>',
    searchUrl: '<?= base_url('admin/kendala/search') ?>',
    exportUrl: '<?= base_url('admin/kendala/export') ?>',
    detailTicketUrl:  (id) => '<?= base_url('admin/kendala/ticket') ?>/'+id,
    detailServiceUrl: (id) => '<?= base_url('admin/kendala/service') ?>/'+id,
    approveTicketUrl: (id) => '<?= base_url('admin/kendala/ticket') ?>/'+id+'/approve',
    rejectTicketUrl:  (id) => '<?= base_url('admin/kendala/ticket') ?>/'+id+'/reject',
    approveServiceUrl:(id) => '<?= base_url('admin/kendala/service') ?>/'+id+'/approve',
    rejectServiceUrl: (id) => '<?= base_url('admin/kendala/service') ?>/'+id+'/reject',
    csrfName:  document.querySelector('meta[name="csrf-token-name"]')?.content || '<?= csrf_token() ?>',
    csrfValue: document.querySelector('meta[name="csrf-token-value"]')?.content || '<?= csrf_hash() ?>',
  };
</script>
<script src="<?= base_url('assets/js-admin/kendala.js') ?>?v=1.3.1"></script>
<?= $this->endSection() ?>
