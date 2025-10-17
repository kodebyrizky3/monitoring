<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<style>
  .photo-box img{ width:100%; height:auto; object-fit:cover; max-height:260px; }
  #cropperWrap{ min-height:360px; max-height:70vh; display:grid; place-items:center; background:#111; }
  #cropImage{ max-width:100%; height:auto; display:block; }
  .cropper-view-box,.cropper-face{ border-radius:8px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
  /** Variabel yang di-passing controller:
   * $row, $merek, $model, $sn, $photoUrl
   * route save: route_to('admin.ac.update', $row['id'])
   */
?>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <strong>Edit Alat AC</strong>
  </div>
  <div class="card-body">
    <form id="formEditAC"
          action="<?= route_to('admin.ac.update', $row['id']) ?>"
          method="post" enctype="multipart/form-data" class="row g-3">
      <?= csrf_field() ?>

      <div class="col-12 col-md-6">
        <label class="form-label">Nama / Nomor Unik</label>
        <input name="nomor_unik" class="form-control" required
               value="<?= esc($row['nomor_unik'] ?? '') ?>">
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label">Merek</label>
        <input name="merek" class="form-control" value="<?= esc($merek ?? '') ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Model</label>
        <input name="model" class="form-control" value="<?= esc($model ?? '') ?>">
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label">Serial No</label>
        <input name="sn" class="form-control" value="<?= esc($sn ?? '') ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Kapasitas (BTU)</label>
        <input name="kapasitas_btu" id="kapasitas_btu" class="form-control"
               inputmode="numeric" pattern="\d*" maxlength="7"
               value="<?= esc($row['kapasitas_btu'] ?? '') ?>">
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label">No. BMN</label>
        <input name="bmn_no_display" id="bmn_no_display" class="form-control"
               inputmode="numeric" pattern="\d*" maxlength="30"
               value="<?= esc($row['bmn_no_display'] ?? '') ?>">
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label">Status</label>
        <select name="status_ac" class="form-select">
          <?php
          $st = (string)($row['status_ac'] ?? 'NORMAL');
          $opts = ['NORMAL'=>'NORMAL','RUSAK_RINGAN'=>'Rusak Ringan','RUSAK_BERAT'=>'Rusak Berat'];
          foreach($opts as $k=>$v):
          ?>
            <option value="<?= $k ?>" <?= $st===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Lokasi</label>
        <input name="lokasi" class="form-control" value="<?= esc($row['lokasi'] ?? '') ?>">
      </div>

      <!-- ===== FOTO + CROP ===== -->
      <div class="col-12">
        <label class="form-label">Foto (opsional)</label>
        <div class="border rounded p-3">
          <input type="hidden" name="remove_photo" id="removePhoto" value="0">
          <input type="file" accept="image/*" id="foto" name="foto" class="d-none">

          <div id="photoBox" class="photo-box <?= $photoUrl ? '' : 'd-none' ?>">
            <img id="photoPreview" class="img-fluid rounded border"
                 src="<?= $photoUrl ? esc($photoUrl) : '' ?>" alt="Foto AC">
          </div>

          <div id="photoEmpty" class="text-center text-muted py-4 <?= $photoUrl ? 'd-none' : '' ?>">
            <i class="bi bi-image fs-2 d-block mb-2"></i>
            Belum ada foto.
          </div>

          <div class="d-flex flex-wrap gap-2 mt-2">
            <button class="btn btn-outline-secondary btn-sm" id="btnPick" type="button">
              <i class="bi bi-image"></i> Pilih/Ganti Foto
            </button>
            <button class="btn btn-outline-info btn-sm" id="btnCrop" type="button" <?= $photoUrl ? '' : 'disabled' ?>>
              <i class="bi bi-crop"></i> Crop
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnDelete" type="button" <?= $photoUrl ? '' : 'disabled' ?>>
              <i class="bi bi-trash"></i> Hapus
            </button>
          </div>
        </div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2">
        <a href="<?= route_to('admin.ac.show',$row['id']) ?>" class="btn btn-outline-secondary">Batal</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Modal Crop ===== -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-crop"></i> Crop Foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="cropperWrap" class="rounded overflow-hidden">
          <img id="cropImage" alt="Crop source">
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2">
          <div class="small text-muted">Rasio 16:9 (sesuai preview & detail)</div>
          <div class="btn-group btn-group-sm">
            <button id="btnCropRotate" class="btn btn-outline-secondary" type="button"><i class="bi bi-arrow-clockwise"></i> Rotate</button>
            <button id="btnCropReset"  class="btn btn-outline-secondary" type="button"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btnCropCancel" type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="btnCropSave"   type="button" class="btn btn-primary"><i class="bi bi-check2"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js-admin/ac-edit-photo.js') ?>?v=1.0.0"></script>
<?= $this->endSection() ?>
