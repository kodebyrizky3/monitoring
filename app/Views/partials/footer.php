<?php
/**
 * Footer global admin
 * Include: <?= $this->include('partials/footer', ['brandText' => 'BRBIH']) ?>
 */
$year      = date('Y');
$appName   = config('App')->appName ?? 'BRBIH Monitor';
$brand     = $brandText ?? $appName;
$ciVersion = \CodeIgniter\CodeIgniter::CI_VERSION ?? '4.x';
?>
<footer class="app-footer border-top bg-white py-2 py-md-3 px-3" role="contentinfo">
  <div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-1 small text-muted">
      <div>© <?= esc($year) ?> <?= esc($brand) ?> • Balai Riset Ikan Hias</div>
    </div>
  </div>
</footer>
