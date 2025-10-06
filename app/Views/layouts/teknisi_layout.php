<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= esc($title ?? 'Teknisi') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= base_url('assets/css/teknisi.css') ?>" rel="stylesheet">

  <?= $this->renderSection('styles') ?>
</head>
<body class="bg-body-tertiary" data-base="<?= rtrim(site_url(), '/') ?>">

<nav class="navbar navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-2">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <i class="bi bi-wrench-adjustable-circle"></i><span>Teknisi</span>
      </a>
      <span id="netBadge" class="badge bg-success d-none">Online</span>
    </div>
    <button id="btnRefresh" class="btn btn-outline-light btn-sm">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>
</nav>

<main class="container py-3">
  <?= $this->renderSection('content') ?>
</main>

<div class="action-bar">
  <?= $this->renderSection('actionbar') ?>
</div>
<div class="action-bar-spacer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js-teknisi/common.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
