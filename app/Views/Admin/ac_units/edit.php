<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Edit Alat AC</h3>
  <a class="btn btn-outline-secondary" href="<?= site_url('admin/data-alat/ac/'.$row['id']) ?>">Kembali</a>
</div>

<?php if ($m = session()->getFlashdata('err')): ?>
  <div class="alert alert-danger"><?= esc($m) ?></div>
<?php endif ?>

<form action="<?= site_url('admin/data-alat/ac/'.$row['id'].'/save') ?>" method="post" class="row g-3">
  <?= csrf_field() ?>

  <div class="col-12">
    <label class="form-label">Kode QR</label>
    <input class="form-control" value="<?= esc($row['kode_qr']) ?>" readonly>
    <div class="form-text">Tidak dapat diubah.</div>
  </div>

  <div class="col-md-6">
    <label class="form-label">Nama Perangkat</label>
    <input name="nomor_unik" class="form-control" value="<?= esc($row['nomor_unik']) ?>" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">Merek</label>
    <input name="merek" class="form-control" value="<?= esc($merek) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Model</label>
    <input name="model" class="form-control" value="<?= esc($model) ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label">Serial Number (SN)</label>
    <input name="sn" class="form-control" value="<?= esc($sn) ?>">
    <div class="form-text">Disimpan ke catatan sebagai <code>SN=...</code></div>
  </div>

  <div class="col-md-5">
    <label class="form-label">Lokasi</label>
    <input name="lokasi" class="form-control" value="<?= esc($row['lokasi']) ?>" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">Status</label>
    <?php $status = $row['status_ac'] ?? 'NORMAL'; ?>
    <select name="status_ac" class="form-select" required>
      <?php foreach (['NORMAL','MENUNGGU_PERBAIKAN','DALAM_PERBAIKAN'] as $s): ?>
        <option value="<?= $s ?>" <?= $s===$status?'selected':'' ?>><?= $s ?></option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-12">
    <label class="form-label">Catatan (opsional)</label>
    <textarea name="catatan" class="form-control" rows="3" placeholder="opsional"><?= esc($row['catatan'] ?? '') ?></textarea>
  </div>

  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary">Simpan Perubahan</button>
    <a class="btn btn-secondary" href="<?= site_url('admin/data-alat/ac/'.$row['id']) ?>">Batal</a>
  </div>
</form>

<?= $this->endSection() ?>
