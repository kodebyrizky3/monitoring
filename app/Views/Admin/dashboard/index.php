<?= $this->extend('layouts/admin_layout') ?>

<!-- CSS khusus halaman -->
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>?v=1.0.0">
<?= $this->endSection() ?>

<?= $this->section('content') ?>



<div class="row g-3">
  <!-- Total Alat -->
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-primary">
      <div class="card-body">
        <div>
          <div class="stat-title">Total Alat</div>
          <div class="stat-value"><?= esc($totalAlat ?? 0) ?></div>
          <div class="stat-sub">AC <?= esc($totalAC ?? 0) ?> | Kendaraan <?= esc($totalKendaraan ?? 0) ?></div>
        </div>
        <i class="bi bi-hdd-network stat-icon"></i>
      </div>
    </div>
  </div>

  <!-- Pending Verifikasi -->
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-success">
      <div class="card-body">
        <div>
          <div class="stat-title">Pending Verifikasi</div>
          <div class="stat-value"><?= esc($pendingVerif ?? 0) ?></div>
          <div class="stat-sub">&nbsp;</div>
        </div>
        <i class="bi bi-hourglass-split stat-icon"></i>
      </div>
    </div>
  </div>

  <!-- Perlu Perbaikan -->
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-warning">
      <div class="card-body">
        <div>
          <div class="stat-title">Perlu Perbaikan</div>
          <div class="stat-value"><?= esc($perluPerbaikan ?? 0) ?></div>
          <div class="stat-sub">&nbsp;</div>
        </div>
        <i class="bi bi-tools stat-icon"></i>
      </div>
    </div>
  </div>

  <!-- Selesai Bulan Ini -->
  <div class="col-12 col-lg-3">
    <div class="card shadow-sm card-stat bg-danger">
      <div class="card-body">
        <div>
          <div class="stat-title">Selesai Bulan Ini</div>
          <div class="stat-value"><?= esc($selesaiBulanIni ?? 0) ?></div>
          <div class="stat-sub">&nbsp;</div>
        </div>
        <i class="bi bi-check2-square stat-icon"></i>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12 col-xl-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Grafik Kendala Bulanan</strong></div>
      <div class="card-body" style="height:320px;">
        <canvas id="kendalaChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Aktivitas Terbaru</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-aktivitas">
            <thead class="table-light">
              <tr><th>User</th><th>Kegiatan</th><th>Waktu</th></tr>
            </thead>
            <tbody>
              <?php if (!empty($latest)): ?>
                <?php foreach ($latest as $row): ?>
                  <tr>
                    <td><?= esc($row['dibuat_oleh'] ?? 'User') ?></td>
                    <td><?= esc($row['judul'] ?? 'Laporan') ?></td>
                    <td><span class="text-nowrap small"><?= esc($row['created_at'] ?? '') ?></span></td>
                  </tr>
                <?php endforeach ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted">Belum ada aktivitas.</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<!-- JS khusus halaman: MUAT Chart.js DULU baru dashboard.js -->
<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // tombol refresh (opsional)
  document.getElementById('refreshDash')?.addEventListener('click', () => {
    location.reload();
  });
</script>
<script src="<?= base_url('assets/js-admin/dashboard.js') ?>?v=1.0.0"></script>
<?= $this->endSection() ?>
