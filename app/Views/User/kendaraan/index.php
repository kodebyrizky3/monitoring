<?php /* app/Views/User/kendaraan/index.php */ ?>

<?= $this->extend('layouts/user_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/u-kendaraan.css') ?>?v=1.1.1">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- ===== Header ===== -->
<div class="d-flex align-items-center gap-2 mb-2 u-toolbar px-2 pt-2">
  <a href="<?= base_url('u') ?>" class="btn btn-light btn-sm rounded-pill"><i class="bi bi-chevron-left"></i></a>
  <div>
    <div class="fw-semibold">Kendaraan</div>
    <div class="text-muted small">Ajukan perbaikan, perjalanan dinas, atau laporan kerusakan</div>
  </div>
</div>

<!-- ===== Pills Tabs ===== -->
<div class="px-2 mb-2">
  <ul class="nav u-tabs" id="kendTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-perbaikan" data-bs-toggle="tab" data-bs-target="#pane-perbaikan" type="button" role="tab" aria-controls="pane-perbaikan" aria-selected="true">
        <i class="bi bi-wrench me-1"></i> Perbaikan
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-perjalanan" data-bs-toggle="tab" data-bs-target="#pane-perjalanan" type="button" role="tab" aria-controls="pane-perjalanan" aria-selected="false">
        <i class="bi bi-geo-alt me-1"></i> Perjalanan
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-kerusakan" data-bs-toggle="tab" data-bs-target="#pane-kerusakan" type="button" role="tab" aria-controls="pane-kerusakan" aria-selected="false">
        <i class="bi bi-exclamation-triangle me-1"></i> Kerusakan
      </button>
    </li>
  </ul>
</div>

<!-- ===== Search (live) ===== -->
<div class="px-2">
  <div class="card u-card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-center">
        <div class="col-12">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input id="qInput" type="text" class="form-control" placeholder="Cari no. polisi / tujuan / tiket…">
          </div>
        </div>
        <div class="col-12">
          <div id="pillFilters" class="d-flex gap-2 flex-wrap"><!-- dynamic via JS --></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== List Panes ===== -->
<div class="tab-content px-2" id="kendTabContent">
  <!-- Perbaikan -->
  <div class="tab-pane fade show active" id="pane-perbaikan" role="tabpanel" aria-labelledby="tab-perbaikan">
    <div class="card u-card shadow-sm mb-3">
      <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <strong>Permintaan Perbaikan</strong>
        <button type="button" class="btn btn-sm btn-primary" id="btnAddPerbaikan"><i class="bi bi-plus"></i> Buat</button>
      </div>
      <div class="card-body p-0">
        <div id="listPerbaikan" class="u-list"></div>
        <div id="emptyPerbaikan" class="u-empty d-none">
          <div class="mb-2"><i class="bi bi-wrench" style="font-size:2rem;"></i></div>
          <div>Belum ada perbaikan.</div>
          <div class="mt-2"><button type="button" class="btn btn-primary btn-sm" id="btnEmptyAddPerbaikan">Buat Perbaikan</button></div>
        </div>
      </div>
      <div class="card-footer bg-white py-2 small text-muted d-flex justify-content-end">
        <span id="infoPerbaikan"></span>
      </div>
    </div>
  </div>

  <!-- Perjalanan -->
  <div class="tab-pane fade" id="pane-perjalanan" role="tabpanel" aria-labelledby="tab-perjalanan">
    <div class="card u-card shadow-sm mb-3">
      <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <strong>Perjalanan Dinas</strong>
        <button type="button" class="btn btn-sm btn-primary" id="btnAddPerjalanan"><i class="bi bi-plus"></i> Mulai</button>
      </div>
      <div class="card-body p-0">
        <div id="listPerjalanan" class="u-list"></div>
        <div id="emptyPerjalanan" class="u-empty d-none">
          <div class="mb-2"><i class="bi bi-geo-alt" style="font-size:2rem;"></i></div>
          <div>Belum ada perjalanan.</div>
          <div class="mt-2"><button type="button" class="btn btn-primary btn-sm" id="btnEmptyAddPerjalanan">Mulai Perjalanan</button></div>
        </div>
      </div>
      <div class="card-footer bg-white py-2 small text-muted d-flex justify-content-end">
        <span id="infoPerjalanan"></span>
      </div>
    </div>
  </div>

  <!-- Kerusakan -->
  <div class="tab-pane fade" id="pane-kerusakan" role="tabpanel" aria-labelledby="tab-kerusakan">
    <div class="card u-card shadow-sm mb-3">
      <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <strong>Laporan Kerusakan</strong>
        <button type="button" class="btn btn-sm btn-primary" id="btnAddKerusakan"><i class="bi bi-plus"></i> Laporkan</button>
      </div>
      <div class="card-body p-0">
        <div id="listKerusakan" class="u-list"></div>
        <div id="emptyKerusakan" class="u-empty d-none">
          <div class="mb-2"><i class="bi bi-exclamation-triangle" style="font-size:2rem;"></i></div>
          <div>Belum ada laporan kerusakan.</div>
          <div class="mt-2"><button type="button" class="btn btn-primary btn-sm" id="btnEmptyAddKerusakan">Buat Laporan</button></div>
        </div>
      </div>
      <div class="card-footer bg-white py-2 small text-muted d-flex justify-content-end">
        <span id="infoKerusakan"></span>
      </div>
    </div>
  </div>
</div>

<!-- ===== Sticky bottom actions (mobile) ===== -->
<div class="u-bottom-actions p-2 d-flex gap-2">
  <button type="button" class="btn btn-outline-primary" id="baPerbaikan"><i class="bi bi-wrench me-1"></i> Perbaikan</button>
  <button type="button" class="btn btn-outline-primary" id="baPerjalanan"><i class="bi bi-geo-alt me-1"></i> Perjalanan</button>
  <button type="button" class="btn btn-outline-primary" id="baKerusakan"><i class="bi bi-exclamation-triangle me-1"></i> Kerusakan</button>
</div>

<!-- ===== Floating action (desktop only) ===== -->
<button type="button" class="btn btn-primary u-fab" id="fabAction"><i class="bi bi-plus-lg"></i></button>

<!-- ========================= Modals ========================= -->

<!-- Modal: Perbaikan -->
<div class="modal fade" id="modalPerbaikan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Buat Perbaikan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <!-- ✅ penting untuk upload file -->
      <form id="formPerbaikan" enctype="multipart/form-data" method="post">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Kendaraan *</label>
            <select class="form-select" name="kendaraan_id" id="svcKendaraan" required>
              <option value="">Pilih kendaraan...</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Keluhan *</label>
            <textarea class="form-control" rows="3" name="keluhan" placeholder="Contoh: mesin bergetar saat idle…" required></textarea>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Odometer (km)</label>
              <input type="number" class="form-control" name="odometer" min="0" placeholder="cth: 45321">
            </div>
            <div class="col-6">
              <label class="form-label">Lampiran Foto</label>
              <input type="file" class="form-control" name="foto[]" accept="image/*" multiple>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Kirim</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: Perjalanan (Start) -->
<div class="modal fade" id="modalPerjalanan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mulai Perjalanan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formPerjalanan" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Kendaraan *</label>
            <select class="form-select" name="kendaraan_id" id="tripKendaraan" required>
              <option value="">Pilih kendaraan...</option>
            </select>
            <div class="form-text">Odometer awal akan terisi otomatis dari data terakhir.</div>
          </div>

          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Pengemudi *</label>
              <select class="form-select" name="pengemudi_id" id="tripPengemudi" required>
                <option value="">Pilih pengemudi...</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Berangkat</label>
              <input type="datetime-local" class="form-control" name="berangkat_at" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
          </div>

          <div class="mb-3 mt-1">
            <label class="form-label">Tujuan *</label>
            <input type="text" class="form-control" name="tujuan" placeholder="cth: Kunjungan dinas ke Kanwil" required>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Odo Awal (km) *</label>
              <input type="number" class="form-control" name="odo_awal" id="tripOdoAwal" min="0" required>
            </div>
            <div class="col-6">
              <label class="form-label">Fuel Awal *</label>
              <input type="text" class="form-control" name="fuel_awal" placeholder="cth: 60% / 10 L" required>
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Perkiraan Kembali</label>
              <input type="datetime-local" class="form-control" name="estimasi_kembali_at">
            </div>
            <div class="col-6">
              <label class="form-label">Lokasi Berangkat</label>
              <input type="text" class="form-control" name="lokasi_berangkat" placeholder="cth: Gedung A / Koordinat">
            </div>
          </div>

          <div class="mt-2">
            <label class="form-label">Foto Dashboard (berangkat) *</label>
            <input type="file" class="form-control" name="foto_dash_berangkat" accept="image/*" required>
          </div>

          <div class="mt-2">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" rows="2" name="catatan_berangkat"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Mulai</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: Kerusakan -->
<div class="modal fade" id="modalKerusakan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Laporan Kerusakan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formKerusakan">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Kendaraan *</label>
            <select class="form-select" name="kendaraan_id" id="tixKendaraan" required>
              <option value="">Pilih kendaraan...</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi *</label>
            <textarea class="form-control" rows="3" name="deskripsi" required placeholder="Ceritakan kerusakan yang terlihat"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label">Foto (opsional)</label>
            <input type="file" class="form-control" name="foto[]" accept="image/*" multiple>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Kirim</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  // CSRF & endpoints untuk JS
  window.CSRF = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
  window.APP  = {
    searchUrl:     '<?= rtrim(base_url('u/kendaraan/search'), '/') ?>',
    svcStoreUrl:   '<?= rtrim(base_url('u/kendaraan/perbaikan'), '/') ?>',
    tripStartUrl:  '<?= rtrim(base_url('u/kendaraan/perjalanan'), '/') ?>',
    tixStoreUrl:   '<?= rtrim(base_url('u/kendaraan/kerusakan'), '/') ?>',
    optVehicles:   '<?= rtrim(base_url('u/options/kendaraan'), '/') ?>',
    optEmployees:  '<?= rtrim(base_url('u/options/pegawai'), '/') ?>',
  };
</script>
<script src="<?= base_url('assets/js-user/u-kendaraan.js') ?>?v=1.1.1"></script>
<?= $this->endSection() ?>
