<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Detail Perangkat</h2>

        <form id="formQR"
              class="row g-3 needs-validation prevent-autogreen"
              novalidate autocomplete="off"
              data-save-url="<?= site_url('admin/qr/save') ?>">
          <?= csrf_field() ?>

          <div class="col-12">
            <label class="form-label">Nama Perangkat</label>
            <input name="nama" class="form-control" placeholder="AC Ruang Rapat" required maxlength="64">
            <div class="invalid-feedback">Wajib diisi.</div>
          </div>

          <div class="col-6">
            <label class="form-label">Merek</label>
            <input name="merek" class="form-control no-autovalid" placeholder="Daikin" maxlength="50">
          </div>
          <div class="col-6">
            <label class="form-label">Model</label>
            <input name="model" class="form-control no-autovalid" placeholder="FTKC25U" maxlength="50">
          </div>

          <div class="col-6">
            <label class="form-label">Serial No</label>
            <input name="serial_no" class="form-control no-autovalid" placeholder="SN12345" maxlength="100">
          </div>
          <div class="col-6">
            <label class="form-label">Lokasi</label>
            <input name="lokasi" class="form-control no-autovalid" placeholder="Lantai 2 - Ruang Rapat" maxlength="120">
          </div>

          <div class="col-6">
            <label class="form-label">Kapasitas (BTU)</label>
            <input name="kapasitas_btu" id="kapasitas_btu" class="form-control no-autovalid"
                   placeholder="12000" inputmode="numeric" pattern="\d*" maxlength="7">
            <div class="form-text">Hanya angka (contoh: 12000)</div>
          </div>

          <div class="col-6">
            <label class="form-label">Nomor BMN</label>
            <input
              name="bmn_no_display"
              id="bmn_no_display"
              class="form-control no-autovalid"
              placeholder="3.05.01.04.002 - 068"
              maxlength="20"
              autocomplete="off"
              pattern="^(\d\.\d{2}\.\d{2}\.\d{2}\.\d{3}\s-\s\d{3})$">
            <div class="form-text">
              Format otomatis: <code>X.XX.XX.XX.XXX - XXX</code> (ketik angka saja).
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Status AC</label>
            <select name="status" id="status" class="form-select no-autovalid">
              <option value="NORMAL" selected>NORMAL</option>
              <option value="RUSAK_RINGAN">Rusak Ringan</option>
              <option value="RUSAK_BERAT">Rusak Berat</option>
            </select>
          </div>

          <!-- FOTO (single) -->
          <div class="col-12">
            <label class="form-label">Foto AC (opsional)</label>
            <div id="dzFoto" class="dropzone rounded-3 p-3 text-center">
              <input type="file" accept="image/*" id="fotoAc" name="foto" class="d-none">
              <div id="dzEmpty" class="dz-empty">
                <i class="bi bi-image fs-2 d-block mb-2"></i>
                <div class="mb-2">Seret foto ke sini atau</div>
                <button class="btn btn-outline-secondary btn-sm" id="btnPick" type="button">Pilih Foto</button>
                <div class="form-text mt-2">Disarankan foto tampak depan + stiker serial.</div>
              </div>
              <div id="dzPreviewBox" class="dz-preview d-none">
                <img id="dzPreview" class="img-fluid rounded border" alt="Foto AC">
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
                  <button class="btn btn-outline-info btn-sm" id="btnCrop" type="button"><i class="bi bi-crop"></i> Crop</button>
                  <button class="btn btn-outline-secondary btn-sm" id="btnGanti" type="button">Ganti</button>
                  <button class="btn btn-outline-danger btn-sm" id="btnHapus" type="button">Hapus</button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Base URL publik</label>
            <input name="base" id="baseUrl" class="form-control" value="<?= rtrim(site_url(), '/') ?>"
                   pattern="https?://.+" required placeholder="https://domainmu.com">
            <div class="form-text">Contoh: https://domainmu.com</div>
            <div class="invalid-feedback">Format URL tidak valid.</div>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2 mt-2">
            <button id="btnGen" class="btn btn-primary" type="submit"><i class="bi bi-magic"></i> Generate</button>
            <button type="reset" id="btnReset" class="btn btn-outline-secondary">Reset</button>

            <button type="button" id="btnBulkOpen" class="btn btn-outline-dark ms-sm-auto" data-bs-toggle="modal" data-bs-target="#bulkModal">
              <i class="bi bi-list-check"></i> Bulk Input
            </button>
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
          <div class="col-md-6">
            <div class="device-card p-3 border rounded">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div id="pvNama" class="fw-semibold">—</div>
                  <div class="small text-muted">Kode QR: <code id="pvKode">—</code></div>
                </div>
                <span id="pvBadge" class="badge text-bg-secondary status-badge">Status</span>
              </div>

              <div id="pvPhotoBox" class="photo-box mt-3 d-none">
                <img id="pvImg" class="img-fluid rounded border" alt="Foto AC">
              </div>

              <div class="print-grid mt-3">
                <div class="qr-in-card"><div id="qrInCard" class="qr-box"></div></div>
                <div class="label-info">
                  <div class="kv"><span class="label">Merek:</span> <span id="pvMerek">—</span></div>
                  <div class="kv"><span class="label">Model/SN:</span> <span id="pvModelSn">—</span></div>
                  <div class="kv"><span class="label">Kapasitas:</span> <span id="pvKap">—</span></div>
                  <div class="kv"><span class="label">BMN:</span> <span id="pvBmn">—</span></div>
                  <div class="kv"><span class="label">Lokasi:</span> <span id="pvLokasi">—</span></div>
                  <div class="small text-muted url-line mt-1">
                    <i class="bi bi-link-45deg"></i>
                    <span id="cardUrlText" class="url-text">—</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-6 text-center">
            <div id="qrWrap" class="qr-wrap border rounded p-2 bg-white is-empty"><div id="qrcode"></div></div>
            <div class="mt-2"><div class="small text-muted">URL Publik:</div><div class="text-break" id="pvUrl">—</div></div>
            <div class="d-grid d-sm-flex gap-2 mt-3">
              <a id="btnOpen" href="#" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Buka URL</a>
              <button id="btnDownload" class="btn btn-outline-success btn-sm" type="button"><i class="bi bi-download"></i> Download PNG</button>
              <button id="btnPrint" class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-printer"></i> Cetak Label</button>
              <button id="btnJson" class="btn btn-outline-dark btn-sm" type="button"><i class="bi bi-filetype-json"></i> Simpan JSON</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Modal Crop Foto -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-crop"></i> Crop Foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="cropperCanvasWrap" class="bg-dark rounded overflow-hidden">
          <img id="cropImage" alt="Crop source">
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2">
          <div class="small text-muted">Rasio: 16:9</div>
          <div class="btn-group btn-group-sm">
            <button id="btnCropRotate" class="btn btn-outline-secondary" type="button" title="Putar 90°"><i class="bi bi-arrow-clockwise"></i> Rotate</button>
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

<!-- Modal Bulk -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-list-check"></i> Bulk Input AC</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <form id="bulkForm" autocomplete="off"
              data-bulk-url="<?= site_url('admin/qr/bulk-save') ?>"
              data-diag-url="<?= site_url('admin/qr/diag') ?>">
          <?= csrf_field() ?>

          <div class="alert alert-info small">
            <div class="fw-semibold mb-1">Format CSV (12 kolom urut):</div>
            <code>Nama, Merek, Model, Serial No, Lokasi, Kapasitas BTU, Nomor BMN, Status, Tekanan Freon Terakhir, Amper Terakhir, Terakhir Service, Terakhir Perawatan</code>
          </div>

          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label">Unggah CSV</label>
              <input type="file" id="bulkFile" accept=".csv,.txt" class="form-control">
              <div class="d-flex align-items-center gap-3 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTemplate"><i class="bi bi-download"></i> Download Template</button>
              </div>
              <div class="form-text">Maksimal 1000 baris per unggahan.</div>

              <div class="alert alert-secondary mt-3 small">
                <div class="fw-semibold mb-1">Aturan tanggal:</div>
                <ul class="mb-1">
                  <li>CSV pakai <code>DD-MM-YYYY</code> (contoh: <code>20-09-2025</code>).</li>
                  <li>Jika salah format → disimpan kosong (tidak error).</li>
                </ul>
              </div>

              <div class="alert alert-secondary mt-3 small">
                <div class="fw-semibold mb-1">Aturan Status AC:</div>
                <ul class="mb-1">
                  <li>Status Harus <code>NORMAL/RUSAK_RINGAN/RUSAK_BERAT</code></li>
                  <li>Jika salah pengetikan maka status akan disimpan sebagai <code>NORMAL</code>.</li>
                </ul>
                <b>Catatan: Pastikan untuk memeriksa kembali data sebelum mengunggah.</b>
              </div>

              <div class="mt-3">
                <label class="form-label">Unggah ZIP Foto (opsional)</label>
                <input type="file" name="images_zip" id="imagesZip" accept=".zip" class="form-control">
                <div class="form-text">
                  Pencocokan foto berdasarkan <b>13 digit Nomor BMN</b> saja. Separator (<code>.</code>, <code>-</code>, spasi) di CSV & nama file <b>bebas</b>.
                  Contoh nama file yang cocok: <code>3050104002068.jpg</code>, <code>3.05.01.04.002 - 068.png</code>.
                  JPG/PNG/WebP, ≤ 5 MB per file.
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">Preview</label>
              <div class="table-responsive border rounded" style="max-height:380px; overflow:auto;">
                <table class="table table-sm table-hover align-middle mb-0" id="bulkPreviewTable">
                  <thead>
                    <tr>
                      <th>#</th><th>Nama</th><th>Merek</th><th>Model</th><th>Serial</th><th>Lokasi</th>
                      <th>BTU</th><th>BMN</th><th>Status</th><th>Freon</th><th>Amper</th><th>Ter. Service</th><th>Ter. Perawatan</th>
                    </tr>
                  </thead>
                  <tbody id="bulkPreviewTbody">
                    <tr><td colspan="13" class="text-center text-muted py-4">Belum ada data.</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="small mt-2"><span id="bulkCount" class="text-muted">0 baris siap disimpan</span></div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <div class="me-auto small text-muted" id="bulkMsg"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-success" id="btnBulkSave" disabled><i class="bi bi-save"></i> Simpan Semua</button>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
<link href="<?= base_url('assets/css/admin-qr.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script src="<?= base_url('assets/js-admin/qr-generator.js') ?>"></script>
<script src="<?= base_url('assets/js-admin/qr-bulk.js') ?>"></script>
<?= $this->endSection() ?>
