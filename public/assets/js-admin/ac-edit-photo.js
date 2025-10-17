// public/assets/js-admin/ac-edit-photo.js
(() => {
  'use strict';

  const form     = document.getElementById('formEditAC');
  const foto     = document.getElementById('foto');
  const removeEl = document.getElementById('removePhoto');

  const photoBox    = document.getElementById('photoBox');
  const photoEmpty  = document.getElementById('photoEmpty');
  const photoPrev   = document.getElementById('photoPreview');

  const btnPick   = document.getElementById('btnPick');
  const btnCrop   = document.getElementById('btnCrop');
  const btnDelete = document.getElementById('btnDelete');

  // Cropper modal
  const cropModalEl   = document.getElementById('cropModal');
  const cropImgEl     = document.getElementById('cropImage');
  const btnCropSave   = document.getElementById('btnCropSave');
  const btnCropReset  = document.getElementById('btnCropReset');
  const btnCropRotate = document.getElementById('btnCropRotate');

  let bsModal = null;
  let cropper = null;
  let dataUrlCurrent = photoPrev?.src || null; // data url/remote url yang aktif

  // Utils
  const fileToDataURL = (f) => new Promise((res, rej) => {
    const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(f);
  });
  const urlToDataURL = async (url) => {
    const resp = await fetch(url, { credentials: 'same-origin' });
    const blob = await resp.blob();
    return await new Promise((res) => { const r=new FileReader(); r.onload=()=>res(r.result); r.readAsDataURL(blob); });
  };
  const dataUrlToFile = async (dataUrl, filename='main.jpg') => {
    const [meta, b64] = dataUrl.split(',');
    const mime = (meta.match(/data:(.*?);/)||[])[1] || 'image/jpeg';
    const bin  = atob(b64); const u8 = new Uint8Array(bin.length);
    for (let i=0;i<bin.length;i++) u8[i] = bin.charCodeAt(i);
    return new File([u8], filename, { type:mime });
  };
  const setPreview = (src) => {
    if (src){
      photoPrev.src = src;
      photoBox.classList.remove('d-none');
      photoEmpty.classList.add('d-none');
      btnCrop.removeAttribute('disabled');
      btnDelete.removeAttribute('disabled');
      dataUrlCurrent = src;
      removeEl.value = '0';
    } else {
      photoPrev.removeAttribute('src');
      photoBox.classList.add('d-none');
      photoEmpty.classList.remove('d-none');
      btnCrop.setAttribute('disabled','disabled');
      btnDelete.setAttribute('disabled','disabled');
      dataUrlCurrent = null;
    }
  };

  // Pick / Change
  btnPick?.addEventListener('click', (e) => { e.preventDefault(); foto?.click(); });
  foto?.addEventListener('change', async (e) => {
    const f = e.target.files?.[0];
    if (!f || !f.type?.startsWith('image/')) return;
    const durl = await fileToDataURL(f);
    setPreview(durl);
  });

  // Delete
  btnDelete?.addEventListener('click', (e) => {
    e.preventDefault();
    // kosongkan file input
    if (foto) { try { foto.value = ''; } catch {} }
    setPreview(null);
    removeEl.value = '1'; // beri tahu server untuk hapus file lama
    if (window.Swal) Swal.fire({ icon:'success', title:'Foto dihapus', timer:1200, showConfirmButton:false });
  });

  // Cropper
  function openCropper() {
    if (!dataUrlCurrent) { if (window.Swal) Swal.fire('Tidak ada foto','Pilih foto dulu.','warning'); return; }
    // Kalau current adalah remote URL, jadikan dataURL dulu (untuk mem-bypass CORS & keamanan canvas)
    const doOpen = (src) => {
      cropImgEl.src = src;
      bsModal = bsModal || new bootstrap.Modal(cropModalEl);
      bsModal.show();
    };
    if (/^data:image\//i.test(dataUrlCurrent)) {
      doOpen(dataUrlCurrent);
    } else {
      // remote url
      urlToDataURL(dataUrlCurrent).then(doOpen).catch(()=>{
        doOpen(dataUrlCurrent); // fallback
      });
    }
  }
  btnCrop?.addEventListener('click', (e)=>{ e.preventDefault(); openCropper(); });

  cropModalEl?.addEventListener('shown.bs.modal', () => {
    if (cropper) cropper.destroy();
    cropper = new Cropper(cropImgEl, {
      viewMode: 1,
      dragMode: 'move',
      aspectRatio: 16/9,
      autoCropArea: 1,
      background: false,
      responsive: true,
      movable: true,
      zoomable: true,
      rotatable: true,
      scalable: false,
    });
  });
  cropModalEl?.addEventListener('hidden.bs.modal', () => {
    if (cropper) { cropper.destroy(); cropper = null; }
    cropImgEl.removeAttribute('src');
  });
  btnCropReset?.addEventListener('click', (e)=>{ e.preventDefault(); cropper?.reset(); });
  btnCropRotate?.addEventListener('click', (e)=>{ e.preventDefault(); cropper?.rotate(90); });

  btnCropSave?.addEventListener('click', async () => {
    if (!cropper) return;
    try{
      // Output 1280x720 (tajam, konsisten dengan detail/preview)
      const canvas = cropper.getCroppedCanvas({ width:1280, height:720, imageSmoothingQuality:'high' });
      const durl   = canvas.toDataURL('image/jpeg', 0.9);
      setPreview(durl);

      // Taruh hasil crop sebagai file ke input[type=file] supaya server menerima 'foto'
      const file = await dataUrlToFile(durl, 'main.jpg');
      const dt   = new DataTransfer();
      dt.items.add(file);
      foto.files = dt.files;
      removeEl.value = '0';

      bsModal?.hide();
      if (window.Swal) Swal.fire({ icon:'success', title:'Foto berhasil dicrop', timer:1300, showConfirmButton:false });
    }catch(err){
      console.error(err);
      if (window.Swal) Swal.fire('Gagal crop','Tidak bisa menyimpan hasil crop.','error');
    }
  });

  // UX angka-only untuk BTU & BMN
  function digitsOnly(el, maxLen=null){
    if (!el) return;
    const toDigits = v => (v || '').replace(/\D+/g, '');
    const sanitize = () => { el.value = toDigits(el.value); if (maxLen) el.value = el.value.slice(0,maxLen); };
    el.setAttribute('inputmode','numeric'); el.setAttribute('pattern','\\d*');
    el.addEventListener('input', sanitize);
    el.addEventListener('paste', e => {
      e.preventDefault();
      const t = (e.clipboardData||window.clipboardData).getData('text');
      const dig = toDigits(t).slice(0, maxLen || 1e9);
      const s = el.selectionStart ?? el.value.length, en = el.selectionEnd ?? el.value.length;
      el.value = el.value.slice(0,s) + dig + el.value.slice(en);
      const pos = s + dig.length; el.setSelectionRange(pos,pos);
    });
    el.addEventListener('keypress', e => {
      if (e.ctrlKey||e.metaKey||e.altKey) return;
      const c = e.which || e.keyCode; if (c===8||c===9||c===13) return;
      if (c < 48 || c > 57) e.preventDefault();
    });
  }
  digitsOnly(document.getElementById('kapasitas_btu'), 7);
  digitsOnly(document.getElementById('bmn_no_display'), 30);

  // Safety: kalau user submit & kita punya preview dari crop, file input sudah berisi File.
  // Tidak perlu fetch manual; biarkan form POST normal ke controller update().
})();
