<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="row g-3">
  <!-- FORM -->
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Detail Perangkat</h2>

        <form id="formQR" class="row g-3 needs-validation" novalidate
              data-save-url="<?= site_url('admin/qr/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="status" value="normal">

          <div class="col-12">
            <label class="form-label">Nama Perangkat</label>
            <input name="nama" class="form-control" placeholder="AC Ruang Rapat" required>
            <div class="invalid-feedback">Wajib diisi.</div>
          </div>

          <div class="col-6">
            <label class="form-label">Merek</label>
            <input name="merek" class="form-control" placeholder="Daikin">
          </div>
          <div class="col-6">
            <label class="form-label">Model</label>
            <input name="model" class="form-control" placeholder="FTKC25U">
          </div>

          <div class="col-6">
            <label class="form-label">Serial No</label>
            <input name="serial_no" class="form-control" placeholder="SN12345">
          </div>
          <div class="col-6">
            <label class="form-label">Lokasi</label>
            <input name="lokasi" class="form-control" placeholder="Lantai 2 - Ruang Rapat">
          </div>

          <!-- FOTO -->
          <div class="col-12">
            <label class="form-label">Foto AC (opsional)</label>
            <div id="dzFoto" class="dropzone rounded-3 p-3 text-center">
              <input type="file" accept="image/*" id="fotoAc" name="foto" class="d-none">
              <div id="dzEmpty" class="dz-empty">
                <i class="bi bi-image fs-2 d-block mb-2"></i>
                <div class="mb-2">Seret foto ke sini atau</div>
                <button class="btn btn-outline-secondary btn-sm" id="btnPick" type="button">Pilih Foto</button>
                <div class="form-text mt-2">Disarankan foto tampak depan + stiker serial. Akan dikompres.</div>
              </div>
              <div id="dzPreviewBox" class="dz-preview d-none">
                <img id="dzPreview" class="img-fluid rounded border" alt="Foto AC">
                <div class="d-flex gap-2 justify-content-center mt-2">
                  <button class="btn btn-outline-secondary btn-sm" id="btnGanti" type="button">Ganti</button>
                  <button class="btn btn-outline-danger btn-sm" id="btnHapus" type="button">Hapus</button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Base URL publik</label>
            <input name="base" id="baseUrl" class="form-control" value="<?= rtrim(site_url(), '/') ?>">
            <div class="form-text">Ubah jika domain/subfolder berbeda.</div>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2 mt-2">
            <button id="btnGen" class="btn btn-primary" type="submit">
              <i class="bi bi-magic"></i> Generate
            </button>
            <button type="reset" id="btnReset" class="btn btn-outline-secondary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- PREVIEW / QR -->
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h2 class="h6 mb-3">Preview & QR</h2>

        <div id="alertBox" class="alert alert-success d-none"></div>

        <div class="row g-3 align-items-start">
          <!-- KIRI: CARD (ini yang dipakai saat cetak) -->
          <div class="col-md-6">
            <div class="device-card p-3 border rounded">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div id="pvNama" class="fw-semibold">—</div>
                  <div class="small text-muted">Kode: <code id="pvKode">—</code></div>
                </div>
                
              </div>

              <!-- Foto preview layar -->
              <div id="pvPhotoBox" class="photo-box mt-3 d-none">
                <img id="pvImg" class="img-fluid rounded border" alt="Foto AC">
              </div>

              <!-- Grid konten untuk CETAK (juga tampil di layar, tapi rapi) -->
              <div class="print-grid mt-3">
                <!-- QR kecil khusus cetak -->
                <div class="qr-in-card">
                  <div id="qrInCard" class="qr-box"></div>
                </div>
                <!-- Info kanan -->
                <div class="label-info">
                  <div class="kv"><span class="label">Merek:</span> <span id="pvMerek">—</span></div>
                  <div class="kv"><span class="label">Model/SN:</span> <span id="pvModelSn">—</span></div>
                  <div class="kv"><span class="label">Lokasi:</span> <span id="pvLokasi">—</span></div>
                  <div class="small text-muted url-line mt-1">
                    <i class="bi bi-link-45deg"></i>
                    <span id="cardUrlText" class="url-text">—</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- KANAN: QR besar + aksi -->
          <div class="col-md-6 text-center">
            <div id="qrWrap" class="qr-wrap border rounded p-2 bg-white is-empty">
              <div id="qrcode"></div>
            </div>
            <div class="mt-2">
              <div class="small text-muted">URL Publik:</div>
              <div class="text-break" id="pvUrl">—</div>
            </div>
            <div class="d-grid d-sm-flex gap-2 mt-3">
              <a id="btnOpen" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right"></i> Buka URL
              </a>
              <button id="btnDownload" class="btn btn-outline-success btn-sm" type="button">
                <i class="bi bi-download"></i> Download PNG
              </button>
              <button id="btnPrint" class="btn btn-outline-secondary btn-sm" type="button">
                <i class="bi bi-printer"></i> Cetak Label
              </button>
              <button id="btnJson" class="btn btn-outline-dark btn-sm" type="button">
                <i class="bi bi-filetype-json"></i> Simpan JSON
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= base_url('assets/css/admin-qr.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= base_url('assets/js-admin/qr-generator.js') ?>"></script>
<?= $this->endSection() ?>
