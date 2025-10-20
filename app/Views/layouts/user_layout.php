<?php
// app/Views/layouts/user_layout.php
$title = isset($title) ? (string)$title : 'Aplikasi';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= esc($title) ?></title>

  <!-- Bootstrap 5 (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

  <!-- Base light (lama, dipakai lagi) -->
  <style>
    body { background:#f7f8fa; }
    .container-narrow { max-width: 720px; }
    .u-card { border-radius: 14px; }
    .u-toolbar { position: sticky; top: 0; z-index: 1020; background: linear-gradient(#f7f8fa,#f7f8fa); }
    .u-bottom-actions {
      position: sticky; bottom: 0; background: rgba(255,255,255,.95);
      border-top: 1px solid rgba(0,0,0,.06);
    }
    .u-fab {
      position: fixed; right: 16px; bottom: 84px; border-radius: 999px;
      display: none;
    }
    @media (min-width: 992px) { .u-fab { display: inline-flex; } }
  </style>

  <?= $this->renderSection('styles') ?>
</head>
<body>

  <main class="container container-narrow py-2">
    <?= $this->renderSection('content') ?>
  </main>

  <!-- Bootstrap JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <!-- CSRF helper -->
  <script>
    window.CSRF = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
    window.syncCsrf = function (hash) {
      if (!hash) return;
      window.CSRF.hash = hash;
      document.querySelectorAll('input[name="<?= csrf_token() ?>"]').forEach(i => i.value = hash);
    };
    <?php if (session('success')): ?>
      Swal.fire({ icon:'success', title: 'Berhasil', text: <?= json_encode((string)session('success')) ?>, timer: 1300, showConfirmButton: false });
    <?php endif ?>
    <?php if (session('error')): ?>
      Swal.fire({ icon:'error', title: 'Gagal', text: <?= json_encode((string)session('error')) ?> });
    <?php endif ?>
  </script>

  <?= $this->renderSection('scripts') ?>
  <?= view('partials/swal_flash') ?>
</body>
</html>