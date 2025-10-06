<?php
$active        = $activeMenu ?? '';
$showBrand     = isset($showBrand) ? (bool) $showBrand : true;
$brandLogo     = $brandLogo ?? base_url('assets/img/logo-kementerian.svg');

// suffix unik yang dikirim dari layout: 'desktop' atau 'mobile'
$collapseSuffix = $collapseSuffix ?? 'default';
$menuAlatId     = 'menuAlat_' . $collapseSuffix;

// kalau sidebar mobile, klik link akan menutup offcanvas
$isMobileSidebar = ($collapseSuffix === 'mobile');
$dismissAttr = $isMobileSidebar ? ' data-bs-dismiss="offcanvas"' : '';
?>
<nav class="sidebar-nav w-100">
  <ul class="nav flex-column pb-3">
    <li class="nav-item">
      <a class="nav-link <?= ($active??'')==='dashboard'?'active':'' ?>" href="<?= base_url('dashboard') ?>"<?= $dismissAttr ?>>
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= ($active??'')==='data-kendala'?'active':'' ?>" href="<?= base_url('admin/kendala') ?>"<?= $dismissAttr ?>>
        <i class="bi bi-exclamation-triangle me-2"></i>Data Kendala
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= ($active??'')==='qr'?'active':'' ?>" href="<?= base_url('admin/qr') ?>"<?= $dismissAttr ?>>
        <i class="bi bi-qr-code me-2"></i>Generate QR
      </a>
    </li>

    <li class="nav-item">
      <button class="nav-link d-flex justify-content-between align-items-center w-100 text-start"
              data-bs-toggle="collapse"
              data-bs-target="#<?= esc($menuAlatId) ?>"
              aria-expanded="false"
              aria-controls="<?= esc($menuAlatId) ?>">
        <span><i class="bi bi-hdd-network me-2"></i>Data Alat</span>
        <i class="bi bi-chevron-down small sidebar-caret"></i>
      </button>
      <div class="collapse" id="<?= esc($menuAlatId) ?>">
        <ul class="nav flex-column ms-4 border-start">
          <li><a class="nav-link <?= $active==='ac-units'?'active':'' ?>" href="<?= base_url('ac-units') ?>"<?= $dismissAttr ?>>
              AC
            </a></li>
          <li><a class="nav-link <?= ($active??'')==='kendaraan'?'active':'' ?>" href="<?= base_url('kendaraan') ?>"<?= $dismissAttr ?>>Kendaraan</a></li>
        </ul>
      </div>
    </li>

    <li class="nav-item"><a class="nav-link <?= ($active??'')==='laporan'?'active':'' ?>" href="<?= base_url('laporan') ?>"<?= $dismissAttr ?>><i class="bi bi-file-earmark-text me-2"></i>Laporan</a></li>

        <li class="nav-item">
      <a class="nav-link <?= ($active??'')==='employees'?'active':'' ?>" href="<?= base_url('pegawai') ?>"<?= $dismissAttr ?>>
        <i class="bi bi-people me-2"></i>Pegawai
      </a>
    </li>

    <li class="nav-item"><a class="nav-link <?= ($active??'')==='pengaturan'?'active':'' ?>" href="<?= base_url('pengaturan') ?>"<?= $dismissAttr ?>><i class="bi bi-gear me-2"></i>Pengaturan</a></li>
    <li class="nav-item mt-auto"><a class="nav-link text-danger" href="<?= base_url('logout') ?>"<?= $dismissAttr ?>><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
  </ul>
</nav>
