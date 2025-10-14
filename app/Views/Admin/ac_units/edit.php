<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>
<h3 class="mb-3">Edit Alat AC</h3>

<?php if ($m = session()->getFlashdata('ok')): ?>
  <div class="alert alert-success"><?= esc($m) ?></div>
<?php endif ?>
<?php if ($m = session()->getFlashdata('err')): ?>
  <div class="alert alert-danger"><?= esc($m) ?></div>
<?php endif ?>

<form action="<?= route_to('admin.ac.update', $row['id']) ?>" method="post" enctype="multipart/form-data" class="row g-3">
  <?= csrf_field() ?>

  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="row g-3">
          <!-- Nama / Merek / Model -->
          <div class="col-md-6">
            <label class="form-label">Nama Perangkat</label>
            <input name="nomor_unik" class="form-control" value="<?= esc($row['nomor_unik'] ?? '') ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Merek</label>
            <input name="merek" class="form-control" value="<?= esc($merek ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Model</label>
            <input name="model" class="form-control" value="<?= esc($model ?? '') ?>">
          </div>

          <!-- SN / BTU / BMN -->
          <div class="col-md-4">
            <label class="form-label">Serial Number</label>
            <input name="serial_no" class="form-control" value="<?= esc($row['serial_no'] ?? ($sn ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Kapasitas (BTU)</label>
            <input name="kapasitas_btu" class="form-control" placeholder="9000 / 12000 / 18000" value="<?= esc($row['kapasitas_btu'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nomor BMN</label>
            <input name="bmn_no_display" class="form-control" value="<?= esc($row['bmn_no_display'] ?? '') ?>">
          </div>

          <!-- Lokasi / Status -->
          <div class="col-md-8">
            <label class="form-label">Lokasi</label>
            <input name="lokasi" class="form-control" value="<?= esc($row['lokasi'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status_ac" class="form-select">
              <option value="NORMAL"        <?= ($row['status_ac'] ?? '')==='NORMAL'?'selected':'' ?>>NORMAL</option>
              <option value="RUSAK_RINGAN"  <?= ($row['status_ac'] ?? '')==='RUSAK_RINGAN'?'selected':'' ?>>RUSAK RINGAN</option>
              <option value="RUSAK_BERAT"   <?= ($row['status_ac'] ?? '')==='RUSAK_BERAT'?'selected':'' ?>>RUSAK BERAT</option>
            </select>
          </div>

          <!-- (Catatan dihilangkan sesuai permintaan) -->
        </div>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <a href="<?= route_to('admin.ac.show', $row['id']) ?>" class="btn btn-outline-secondary">Batal</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Simpan</button>
    </div>
  </div>

  <!-- FOTO -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Foto AC</div>
      <div class="card-body">
        <div id="imgBox" class="mb-2">
          <?php if (!empty($photoUrl)): ?>
            <img id="preview" src="<?= esc($photoUrl) ?>" class="img-fluid rounded border" alt="Foto AC">
          <?php else: ?>
            <img id="preview" src="" class="img-fluid rounded border d-none" alt="Foto AC">
            <div class="text-muted">Belum ada foto.</div>
          <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <input type="file" accept="image/*" id="foto" name="foto" class="d-none">
          <button class="btn btn-outline-primary" type="button" id="btnPilih"><i class="bi bi-image"></i> Pilih Foto</button>
          <button class="btn btn-outline-danger" type="button" id="btnHapus"><i class="bi bi-trash"></i> Hapus Foto</button>
        </div>

        <input type="hidden" name="remove_photo" id="remove_photo" value="0">
        <div class="form-text mt-2">Format: JPG/PNG/WEBP. Jika pilih foto baru, file lama otomatis terganti.</div>
      </div>
    </div>
  </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){
  'use strict';
  const fileInput = document.getElementById('foto');
  const btnPilih  = document.getElementById('btnPilih');
  const btnHapus  = document.getElementById('btnHapus');
  const preview   = document.getElementById('preview');
  const rmInput   = document.getElementById('remove_photo');

  btnPilih?.addEventListener('click', (e)=>{ e.preventDefault(); fileInput.click(); });

  fileInput?.addEventListener('change', (e)=>{
    const f = e.target.files?.[0];
    if (!f || !f.type.startsWith('image/')) return;
    const r = new FileReader();
    r.onload = () => {
      preview.src = r.result;
      preview.classList.remove('d-none');
      rmInput.value = '0'; // jangan hapus
    };
    r.readAsDataURL(f);
  });

  btnHapus?.addEventListener('click', ()=>{
    preview.removeAttribute('src');
    preview.classList.add('d-none');
    if (fileInput) fileInput.value = '';
    rmInput.value = '1'; // hapus di server
  });
})();
</script>
<?= $this->endSection() ?>
