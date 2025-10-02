<?= $this->extend('layouts/teknisi_layout') ?>

<?= $this->section('content') ?>
<div id="__page" data-token="<?= esc($token ?? '') ?>"></div>

<!-- Ringkas info AC -->
<div class="card shadow-sm mb-3">
  <div class="card-body d-flex gap-3 align-items-center">
    <div class="thumb ratio ratio-1x1 rounded overflow-hidden bg-body-secondary" style="width:72px">
      <img id="acThumb" class="w-100 h-100 object-fit-cover d-none" alt="Foto AC">
    </div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-semibold" id="acName">AC</div>
          <div class="small text-muted">Kode: <code id="acKode">—</code></div>
        </div>
        <span id="acStatus" class="badge rounded-pill text-bg-info">On Progress</span>
      </div>
      <div class="small text-muted mt-1" id="acLokasi">—</div>
    </div>
  </div>
</div>

<!-- Form Perbaikan -->
<form id="formPerbaikan" class="card shadow-sm needs-validation" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="ticket_id" id="ticket_id">

  <div class="card-header bg-body fw-semibold border-0 px-3 px-sm-4 pt-3">
    <i class="bi bi-clipboard2-check me-1"></i> Laporan Perbaikan
  </div>

  <div class="card-body px-3 px-sm-4 pb-3">
    <!-- Nama Teknisi (WAJIB) -->
    <div class="mb-3">
      <label class="form-label mb-2">Nama Teknisi <span class="text-danger">*</span></label>
      <input name="teknisi_nama" class="form-control" placeholder="cth: Budi Setiawan"
             required minlength="3" maxlength="120" autocomplete="name">
      <div class="invalid-feedback">Nama teknisi wajib diisi.</div>
    </div>

    <!-- Tindakan (WAJIB) -->
    <div class="mb-3">
      <label class="form-label mb-2">Tindakan perbaikan <span class="text-danger">*</span></label>
      <textarea name="tindakan" class="form-control" rows="3"
        placeholder="Contoh: Ganti kapasitor 35µF, tambah freon 200gr, bersihkan coil" required></textarea>
      <div class="invalid-feedback">Wajib diisi.</div>
    </div>

    <!-- Hasil (WAJIB) -->
    <div class="mb-3">
      <label class="form-label mb-2">Hasil perbaikan <span class="text-danger">*</span></label>
      <textarea name="hasil_perbaikan" class="form-control" rows="3"
        placeholder="Contoh: AC normal kembali, suhu turun, arus kompresor stabil" required></textarea>
      <div class="invalid-feedback">Wajib diisi.</div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label mb-2">Part (opsional)</label>
        <input name="part" class="form-control" placeholder="Kapasitor 35µF">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label mb-2">Biaya (opsional)</label>
        <input type="number" name="biaya" class="form-control" placeholder="0" min="0" step="1">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label mb-2">Foto setelah perbaikan (opsional)</label>
      <input type="file" accept="image/*" capture="environment" id="fotoAfter" name="fotoAfter" class="form-control">
      <div id="afterPreviewBox" class="mt-2 d-none">
        <img id="afterPreview" class="img-fluid rounded border" alt="Foto sesudah">
      </div>
    </div>
  </div>

  <div class="card-footer bg-transparent border-0 px-3 px-sm-4 pb-3 d-grid gap-2">
    <button class="btn btn-primary btn-lg" type="submit" id="btnSubmit">
      <i class="bi bi-check2-circle me-1"></i> Kirim & Tandai Selesai
    </button>
    <a id="btnBackDetail" href="#" class="btn btn-outline-secondary">Kembali ke Detail</a>
  </div>
</form>

<!-- Alert sukses -->
<div id="alertDone" class="alert alert-success mt-3 d-none">
  <i class="bi bi-patch-check-fill me-1"></i> Laporan dikirim (tiket menunggu verifikasi admin).
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js-teknisi/perbaikan.js') ?>"></script>
<?= $this->endSection() ?>
