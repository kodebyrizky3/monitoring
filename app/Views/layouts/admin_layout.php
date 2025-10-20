<?php helper('url'); ?>
<?php
  $brandLogo = base_url('assets/img/logo-kementerian.svg');
  $brandText = 'BRBIH';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title ?? 'BRBIH') ?></title>

  <meta name="base-url" content="<?= rtrim(base_url('/'), '/') ?>/">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=1.3.2">
  <?= $this->renderSection('styles') ?>
</head>
<body>

<div class="app-wrapper d-flex">
  <!-- Sidebar desktop (fixed) -->
  <aside id="appSidebar" class="app-sidebar d-none d-md-flex flex-column">
    <?= $this->include('partials/sidebar', [
      'showBrand'      => false,
      'activeMenu'     => $activeMenu ?? '',
      'brandLogo'      => $brandLogo,
      'collapseSuffix' => 'desktop',
    ]) ?>
  </aside>

  <!-- Offcanvas mobile -->
  <div class="offcanvas offcanvas-start shadow d-md-none"
       tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
    <div class="offcanvas-body p-0">
      <?= $this->include('partials/sidebar', [
        'showBrand'      => false,
        'activeMenu'     => $activeMenu ?? '',
        'brandLogo'      => $brandLogo,
        'collapseSuffix' => 'mobile',
      ]) ?>
    </div>
  </div>

  <!-- Main (scroll area) -->
  <!-- ⬇️ TAMBAH flex-grow-1 agar main melar penuh -->
  <main class="app-content d-flex flex-column flex-grow-1">
    <!-- TOPBAR (fixed via CSS) -->
    <div id="appTopbar">
      <?= $this->include('partials/topbar', [
        'title'     => $title ?? 'Dashboard',
        'brandLogo' => $brandLogo,
        'brandText' => $brandText
      ]) ?>
    </div>

    <!-- Konten halaman -->
    <div class="px-3 py-3 flex-grow-1 w-100">
      <?= $this->renderSection('content') ?>
    </div>

    <!-- Footer global -->
    <?= $this->include('partials/footer', ['brandText' => $brandText]) ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>?v=1.3.0"></script>
<?= $this->renderSection('scripts') ?>
<?= $this->include('partials/swal_flash') ?>
</body>
</html>
