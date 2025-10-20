<?= $this->extend('layouts/user_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/u-home.css') ?>?v=3.1.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Appbar (sticky) -->
<div class="u-appbar px-2">
  <div class="d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-house-door text-primary fs-5"></i>
      <div>
        <div class="fw-semibold">Beranda</div>
        <div class="text-muted small">Akses cepat modul & status</div>
      </div>
    </div>

    <div class="dropdown">
      <a href="#" class="d-inline-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
        <img src="<?= esc($me['foto_url'] ?: base_url('assets/img/avatar-placeholder.svg')) ?>"
             alt="avatar" class="rounded-circle u-avatar border">
        <span class="ms-2 d-none d-sm-inline fw-medium"><?= esc($me['nama'] ?? $me['username'] ?? 'User') ?></span>
        <i class="bi bi-caret-down-fill ms-1 small text-muted"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li class="px-3 py-2 small text-muted">
          <div class="fw-semibold"><?= esc($me['nama'] ?? '-') ?></div>
          <div><?= esc($me['kode_pegawai'] ?? '-') ?> · <?= esc($me['bidang'] ?? '-') ?></div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?= site_url('user/profil') ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
        <li><a class="dropdown-item" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- Profil ringkas -->
<div class="card u-card shadow-sm mb-3">
  <div class="card-body d-flex align-items-center gap-3">
    <img src="<?= esc($me['foto_url'] ?: base_url('assets/img/avatar-placeholder.svg')) ?>"
         alt="avatar" class="rounded-circle border u-avatar-lg">
    <div class="flex-grow-1">
      <div class="fw-bold fs-5 mb-1"><?= esc($me['nama'] ?? $me['username'] ?? '—') ?></div>
      <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
        <span class="badge bg-light text-dark border"><?= esc($me['kode_pegawai'] ?? '—') ?></span>
        <span>•</span>
        <span class="badge bg-primary-subtle text-primary border"><?= esc($me['bidang'] ?? '—') ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Menu ke modul -->
<div class="row g-2 mb-3">
  <div class="col-6">
    <a href="<?= site_url('user/kendaraan') ?>" class="u-tile card shadow-sm text-decoration-none">
      <div class="card-body text-center">
        <div class="u-tile-icon"><i class="bi bi-truck"></i></div>
        <div class="fw-semibold">Kendaraan</div>
        <div class="text-muted small">Perbaikan, perjalanan, BBM</div>
      </div>
    </a>
  </div>
  <div class="col-6">
    <a href="<?= site_url('user/ac') ?>" class="u-tile card shadow-sm text-decoration-none">
      <div class="card-body text-center">
        <div class="u-tile-icon"><i class="bi bi-thermometer-snow"></i></div>
        <div class="fw-semibold">AC</div>
        <div class="text-muted small">Lapor & status tiket</div>
      </div>
    </a>
  </div>
</div>

<!-- Stat ringkas -->
<div class="row g-3">
  <div class="col-12 col-md-4">
    <div class="card u-card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Trip Aktif</div>
        <div class="fs-2 fw-bold" id="statTrip">0</div>
        <a href="<?= site_url('user/kendaraan/riwayat') ?>" class="link-primary small">Lihat</a>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card u-card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Tiket AC Aktif</div>
        <div class="fs-2 fw-bold" id="statAc">0</div>
        <a href="<?= site_url('user/ac/status') ?>" class="link-primary small">Lihat</a>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card u-card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Pengajuan Menunggu</div>
        <div class="fs-2 fw-bold" id="statPending">0</div>
        <span class="text-muted small">—</span>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  window.UHOME = { statUrl: '<?= site_url('user/home/stats') ?>' };
  window.AVATAR_PH = '<?= base_url('assets/img/avatar-placeholder.svg') ?>';
</script>
<script src="<?= base_url('assets/js-user/u-home.js') ?>?v=3.1.0"></script>
<?= $this->endSection() ?>