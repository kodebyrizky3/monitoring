<?php
$brandLogo = $brandLogo ?? base_url('assets/img/logo-kementerian.svg'); // fallback
?>
<nav class="navbar app-topbar sticky-top">
  <div class="container-fluid topbar-container position-relative">

    <!-- Kiri: toggler + brand -->
    <div class="topbar-left d-flex align-items-center gap-2">
      <button class="btn btn-topbar d-lg-none me-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
        <i class="bi bi-list"></i>
      </button>

      <img src="<?= $brandLogo ?>" alt="Logo Kementerian" class="brand-img-topbar">
      <strong class="brand-text-topbar">BRBIH</strong>
    </div>

    <!-- Tengah: judul halaman (benar-benar center) -->
    <div class="topbar-center position-absolute top-50 start-50 translate-middle">
      <span class="page-title-center text-truncate"><?= esc($title ?? '') ?></span>
    </div>

    <!-- Kanan: aksi -->
    <div class="topbar-right d-flex align-items-center gap-2 ms-auto">
      <div class="dropdown">
        <button class="btn btn-topbar position-relative" data-bs-toggle="dropdown" aria-expanded="false" id="notifBell">
          <i class="bi bi-bell fs-5"></i>
          <span id="notifCount" class="position-absolute top-0 start-100 badge rounded-pill bg-danger d-none">0</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-notif" id="notifList" style="min-width:320px;">
          <li class="dropdown-header">Laporan Masuk</li>
          <li><hr class="dropdown-divider"></li>
          <li class="px-3 text-muted small">Tidak ada laporan baru.</li>
        </ul>
      </div>
    </div>

  </div>
</nav>
