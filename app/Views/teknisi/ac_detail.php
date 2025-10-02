<?= $this->extend('layouts/teknisi_layout') ?>

<?php
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
    <div class="photo-tools position-absolute top-0 start-0 m-2 d-flex gap-2">
      <button id="btnZoom" class="btn btn-fab btn-light btn-sm d-none" aria-label="Perbesar foto" title="Perbesar">
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
            <div class="text-muted small lh-1">Model / SN</div>
            <div class="fw-semibold small" id="modelSn">—</div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="fact d-flex align-items-center p-2 rounded border bg-body">
          <i class="bi bi-geo-alt me-2"></i>
          <div class="w-100">
            <div class="text-muted small lh-1">Lokasi</div>
            <div class="fw-semibold" id="lokasi">—</div>
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
<style>
  :root{
    --card-pad: 1rem;
  }
  .object-fit-cover   { object-fit: cover; }
  .object-fit-contain { object-fit: contain; }
  .hero-photo         { background: var(--bs-secondary-bg); }

  /* Skeleton shimmer */
  .skeleton { position:absolute; inset:0;
    background:linear-gradient(90deg, rgba(0,0,0,.06) 25%, rgba(0,0,0,.10) 37%, rgba(0,0,0,.06) 63%);
    background-size:400% 100%; animation:shimmer 1.4s ease-in-out infinite; }
  @keyframes shimmer { from {background-position:100% 0} to {background-position:0 0} }

  /* FAB button look */
  .btn-fab { border-radius: 999px; width: 36px; height: 36px; display:inline-flex; align-items:center; justify-content:center; box-shadow: 0 2px 8px rgba(0,0,0,.15); }

  /* Quick facts */
  .quick-facts .fact i { font-size: 1.1rem; color: var(--bs-secondary-color); }
  .quick-facts .fact   { transition: background-color .2s ease; }
  .quick-facts .fact:hover { background-color: var(--bs-tertiary-bg); }

  /* Better card spacing on mobile */
  @media (max-width: 576px){
    .card-body{ padding: var(--card-pad); }
  }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js-teknisi/ac-detail.js') ?>"></script>
<script>
// Fallback ringan: buka modal saat tombol/thumbnail di-klik
(() => {
  const img  = document.getElementById('acPhoto');
  const skel = document.getElementById('photoSkeleton');
  const btn  = document.getElementById('btnZoom');
  const m    = document.getElementById('photoModal');
  const mi   = document.getElementById('modalPhoto');

  const open = () => {
    if (!img || !img.src) return;
    mi.src = img.currentSrc || img.src;
    new bootstrap.Modal(m).show();
  };

  if (img) {
    img.addEventListener('load', () => {
      img.classList.remove('d-none');
      skel?.classList.add('d-none');
      btn?.classList.remove('d-none');
    });
    img.addEventListener('click', open);
  }
  btn?.addEventListener('click', open);
})();
</script>
<?= $this->endSection() ?>
