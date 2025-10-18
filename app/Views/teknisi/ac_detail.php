<?= $this->extend('layouts/teknisi_layout') ?>

<?php
// Ambil token dari argumen atau dari URL /ac/{token}
if (!isset($token) || !$token) {
  $uri   = service('uri');
  $segs  = $uri ? $uri->getSegments() : [];
  $idx   = array_search('ac', $segs);
  $token = ($idx !== false && isset($segs[$idx+1])) ? urldecode($segs[$idx+1]) : '';
}
?>

<?= $this->section('content') ?>
<div id="__page" data-token="<?= esc($token ?? '') ?>"></div>

<div class="card shadow-sm mb-3 overflow-hidden">
<!-- HERO PHOTO -->
  <div class="hero-photo ratio ratio-16x9 position-relative">
    <img id="acPhoto" class="w-100 h-100 object-fit-cover d-none" alt="Foto AC" loading="lazy">
    <div id="photoSkeleton" class="skeleton"></div>

    <!-- Tools kiri atas -->
    <div class="photo-tools position-absolute top-0 start-0 m-2">
      <button id="btnZoom" type="button" class="zoom-btn d-none" aria-label="Perbesar foto" title="Perbesar">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
    </div>
  </div>




  <!-- BODY -->
  <div class="card-body">
    <h1 class="h5 mb-1" id="namaAlat">Perangkat</h1>
    <div class="text-muted small mb-3">Kode: <code id="kodeQr">—</code></div>

    <div class="row g-2 quick-facts">
      <div class="col-12">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-check2-circle me-2"></i>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="text-muted small lh-1">Status</div>
            <!-- badge akan diganti class & text oleh JS -->
            <span id="badgeStatus" class="badge rounded-pill px-3 py-2 text-bg-secondary">Status</span>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-cpu me-2"></i>
          <div>
            <div class="text-muted small lh-1">Merek</div>
            <div class="fw-semibold" id="merek">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-upc-scan me-2"></i>
          <div>
            <div class="text-muted small lh-1">Model</div>
            <div class="fw-semibold small" id="modelOnly">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-hash me-2"></i>
          <div>
            <div class="text-muted small lh-1">Serial No</div>
            <div class="fw-semibold small" id="serialNo">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-123 me-2"></i>
          <div>
            <div class="text-muted small lh-1">No. BMN</div>
            <div class="fw-semibold small" id="noBmn">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-geo-alt me-2"></i>
          <div class="w-100">
            <div class="text-muted small lh-1">Lokasi</div>
            <div class="fw-semibold" id="lokasi">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-thermometer-half me-2"></i>
          <div>
            <div class="text-muted small lh-1">Freon Terakhir</div>
            <div class="fw-semibold small" id="lastFreon">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-clock-history me-2"></i>
          <div>
            <div class="text-muted small lh-1">Terakhir Perawatan</div>
            <div class="fw-semibold small" id="lastPerawatan">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-tools me-2"></i>
          <div>
            <div class="text-muted small lh-1">Terakhir Service</div>
            <div class="fw-semibold small" id="lastService">—</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-lightning-charge me-2"></i>
          <div>
            <div class="text-muted small lh-1">Ampere Terakhir</div>
            <div class="fw-semibold small" id="lastAmper">—</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Laporan -->
<div class="card shadow-sm">
  <div class="card-header bg-body fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-chat-left-text"></i> Laporan user aktif
  </div>
  <div id="laporanList" class="list-group list-group-flush">
    <div class="list-group-item text-center text-muted py-4">Memuat...</div>
  </div>
</div>

<!-- Modal Foto -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0">
        <h5 class="modal-title text-white mb-0">Foto Perangkat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0">
        <img id="modalPhoto" class="w-100 h-100 object-fit-contain" alt="Foto AC">
      </div>
    </div>
  </div>
</div>


<noscript>
  <div class="alert alert-warning mt-3">Aktifkan JavaScript untuk memuat detail perangkat.</div>
</noscript>
<?= $this->endSection() ?>

<?= $this->section('actionbar') ?>
<a href="#" id="btnPerbaikan" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
  <i class="bi bi-tools"></i>
  <span>Buat Laporan Perbaikan</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= base_url('assets/css/ac-detail.css') ?>?v=1.0.6" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js-teknisi/ac-detail.js') ?>?v=1.3.0"></script>
<?= $this->endSection() ?>
