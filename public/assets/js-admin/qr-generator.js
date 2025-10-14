(() => {
  'use strict';

  // ===== Utils
  function randomHex(bytes = 16) {
    if (crypto?.getRandomValues) {
      const a = new Uint8Array(bytes); crypto.getRandomValues(a);
      return Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
    }
    let s=''; for (let i=0;i<bytes;i++) s += Math.floor(Math.random()*256).toString(16).padStart(2,'0');
    return s;
  }
  const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const shortDisplay = (url) => { try { const u = new URL(url); return (u.host + u.pathname).replace(/\/+$/, ''); } catch { return url; } };
  const swalOK  = (t, d) => (window.Swal ? Swal.fire({ icon:'success', title:t||'Berhasil', text:d||'', timer:1400, showConfirmButton:false }) : alert(t||'Berhasil'));
  const swalErr = (t, d) => (window.Swal ? Swal.fire({ icon:'error',   title:t||'Gagal',   text:d||'' }) : alert((t||'Gagal') + (d?': '+d:'')));

  // image helpers
  const fileToDataURL = f => new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(f); });
  const compressDataURL = (dataUrl, maxW=1200, maxH=1200, q=0.85) => new Promise((res) => {
    const img = new Image();
    img.onload = () => {
      const ratio = Math.min(maxW/img.width, maxH/img.height, 1);
      const w = Math.round(img.width*ratio), h = Math.round(img.height*ratio);
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      c.getContext('2d').drawImage(img,0,0,w,h);
      const type = dataUrl.startsWith('data:image/png') ? 'image/png' : 'image/jpeg';
      res(c.toDataURL(type, q));
    };
    img.src = dataUrl;
  });
  function dataUrlToBlob(dataUrl){
    const [meta,b64] = dataUrl.split(',');
    const mime = (meta.match(/data:(.*?);/)||[])[1] || 'image/jpeg';
    const bin = atob(b64); const u8 = new Uint8Array(bin.length);
    for (let i=0;i<bin.length;i++) u8[i] = bin.charCodeAt(i);
    return new Blob([u8], { type:mime });
  }
  function renderQR(targetId, text, size=256){
    const box = document.getElementById(targetId); if (!box) return;
    box.innerHTML = '';
    new QRCode(box, { text, width:size, height:size, correctLevel: QRCode.CorrectLevel.M });
  }

  // ===== Main
  document.addEventListener('DOMContentLoaded', () => {
    const form     = document.getElementById('formQR');
    const qrWrap   = document.getElementById('qrWrap');
    const alertBox = document.getElementById('alertBox');
    const btnOpen  = document.getElementById('btnOpen');
    const pvBadge  = document.getElementById('pvBadge');
    const statusSel= document.getElementById('status');

    const baseInput = document.getElementById('baseUrl');
    if (baseInput && !baseInput.value) baseInput.value = location.origin;
    qrWrap?.classList.add('is-empty');

    const saveUrl  = form?.dataset?.saveUrl || null;

    // Foto widgets
    const dz            = document.getElementById('dzFoto');
    const fileInput     = document.getElementById('fotoAc');
    const btnPick       = document.getElementById('btnPick');
    const btnGanti      = document.getElementById('btnGanti');
    const btnHapus      = document.getElementById('btnHapus');
    const dzEmpty       = document.getElementById('dzEmpty');
    const dzPreviewBox  = document.getElementById('dzPreviewBox');
    const dzPreview     = document.getElementById('dzPreview');
    const pvPhotoBox    = document.getElementById('pvPhotoBox');
    const pvImg         = document.getElementById('pvImg');

    let photoDataUrl = null;

    function applyPhoto(dataUrl){
      photoDataUrl = dataUrl || null;
      if (photoDataUrl){
        if (dzPreview) dzPreview.src = photoDataUrl;
        dzEmpty?.classList.add('d-none');
        dzPreviewBox?.classList.remove('d-none');
        if (pvImg) pvImg.src = photoDataUrl;
        pvPhotoBox?.classList.remove('d-none');
      } else {
        if (dzPreview) dzPreview.removeAttribute('src');
        dzPreviewBox?.classList.add('d-none');
        dzEmpty?.classList.remove('d-none');
        if (pvImg) pvImg.removeAttribute('src');
        pvPhotoBox?.classList.add('d-none');
      }
    }

    btnPick?.addEventListener('click', e => { e.preventDefault(); fileInput?.click(); });
    btnGanti?.addEventListener('click', e => { e.preventDefault(); fileInput?.click(); });
    btnHapus?.addEventListener('click', e => { e.preventDefault(); applyPhoto(null); });
    fileInput?.addEventListener('change', async e => {
      const f = e.target.files?.[0]; if (!f || !f.type.startsWith('image/')) return;
      const raw = await fileToDataURL(f); const dataUrl = await compressDataURL(raw, 1200, 1200, 0.85);
      applyPhoto(dataUrl);
    });
    if (dz){
      ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
      ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
      dz.addEventListener('drop', async e => {
        const f = e.dataTransfer?.files?.[0]; if (!f || !f.type.startsWith('image/')) return;
        const raw = await fileToDataURL(f); const dataUrl = await compressDataURL(raw, 1200, 1200, 0.85);
        applyPhoto(dataUrl);
      });
    }

    // ======== LIMITERS (UX)
    function digitsOnly(el, maxLen=null){
      if (!el) return;
      const toDigits = v => (v || '').replace(/\D+/g, '');
      const sanitize = () => { el.value = toDigits(el.value); if (maxLen) el.value = el.value.slice(0, maxLen); };
      el.setAttribute('inputmode', 'numeric'); el.setAttribute('pattern', '\\d*');
      el.addEventListener('input', sanitize);
      el.addEventListener('paste', e => {
        e.preventDefault();
        const t = (e.clipboardData || window.clipboardData).getData('text');
        const dig = toDigits(t).slice(0, maxLen || 1e9);
        const s = el.selectionStart ?? el.value.length, en = el.selectionEnd ?? el.value.length;
        el.value = el.value.slice(0, s) + dig + el.value.slice(en);
        const pos = s + dig.length; el.setSelectionRange(pos, pos);
      });
      el.addEventListener('keypress', e => {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        const c = e.which || e.keyCode;
        if (c === 8 || c === 9 || c === 13) return;
        if (c < 48 || c > 57) e.preventDefault();
      });
    }
    digitsOnly(document.getElementById('kapasitas_btu'), 7);
    digitsOnly(document.getElementById('bmn_no_display'), 30);
    ['nama','merek','model','lokasi'].forEach(n => {
      const el = form?.querySelector(`[name="${n}"]`);
      el?.addEventListener('blur', () => { el.value = (el.value || '').replace(/\s+/g, ' ').trim(); });
    });

    // ======== Soft validation per-field (touched rule)
    if (form){
      const fields = Array.from(form.querySelectorAll('input,select,textarea'));
      const touched = new WeakSet();

      const paint = (el) => {
        // netralkan yang kosong (tidak kasih centang)
        const val = (el.value || '').trim();
        if (!val && !el.required){
          el.classList.remove('is-valid','is-invalid');
          return;
        }
        // jika ada value atau required → cek validity
        const ok = el.checkValidity();
        el.classList.toggle('is-valid',   touched.has(el) && ok);
        el.classList.toggle('is-invalid', touched.has(el) && !ok);
      };

      fields.forEach(el=>{
        el.addEventListener('input', () => { touched.add(el); paint(el); });
        el.addEventListener('blur',  () => { touched.add(el); paint(el); });
        // state awal netral
        el.classList.remove('is-valid','is-invalid');
      });
    }

    // ======== Badge status
    function labelStatus(code){
      switch (code) {
        case 'NORMAL': return 'NORMAL';
        case 'RUSAK_RINGAN': return 'Rusak Ringan';
        case 'RUSAK_BERAT': return 'Rusak Berat';
        default: return 'Status';
      }
    }
    function classForStatus(code){
      switch (code) {
        case 'NORMAL': return 'badge text-bg-success status-badge';
        case 'RUSAK_RINGAN': return 'badge text-bg-warning status-badge';
        case 'RUSAK_BERAT': return 'badge text-bg-danger status-badge';
        default: return 'badge text-bg-secondary status-badge';
      }
    }
    function applyStatusBadge(code){
      if (!pvBadge) return;
      pvBadge.className = classForStatus(code);
      pvBadge.textContent = labelStatus(code);
    }

    // ======== SUBMIT
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();

      // tampilkan validasi bootstrap hanya jika ada yang invalid
      if (!form.checkValidity()) {
        form.classList.add('was-validated'); // akan memunculkan merah untuk yang salah
        return;
      } else {
        // jika valid semua, bersihkan state validasi supaya tidak ada "centang hijau" nyangkut
        form.classList.remove('was-validated');
        form.querySelectorAll('.is-valid,.is-invalid').forEach(el=>el.classList.remove('is-valid','is-invalid'));
      }

      const fd     = new FormData(form);
      const nama   = (fd.get('nama')      || '').toString().trim();
      const merek  = (fd.get('merek')     || '').toString().trim();
      const model  = (fd.get('model')     || '').toString().trim();
      const serial = (fd.get('serial_no') || '').toString().trim();
      const lokasi = (fd.get('lokasi')    || '').toString().trim();
      const base   = (fd.get('base')      || location.origin).toString().trim();
      let   status = (fd.get('status')    || 'NORMAL').toString().trim().toUpperCase().replace(/\s+/g,'_');

      const bmn = ((fd.get('bmn_no_display') || '') + '').replace(/\D+/g, '');
      const kap = ((fd.get('kapasitas_btu')  || '') + '').replace(/\D+/g, '') || '12000';
      fd.set('bmn_no_display', bmn);
      fd.set('kapasitas_btu', kap);
      fd.set('status', status);

      const token = randomHex(16);
      const url   = (base.replace(/\/+$/,'') + '/ac/' + token);

      // preview
      setText('pvNama',    nama   || '—');
      setText('pvKode',    token);
      setText('pvMerek',   merek  || '—');
      setText('pvModelSn', `${model || '—'}${serial ? ` / ${serial}` : ''}`);
      setText('pvLokasi',  lokasi || '—');
      setText('pvBmn',     bmn    || '—');
      setText('pvKap',     kap + ' BTU');
      setText('pvUrl',     url);
      setText('cardUrlText', shortDisplay(url));
      applyStatusBadge(status);
      renderQR('qrcode', url, 256);
      renderQR('qrInCard', url, 180);
      qrWrap?.classList.remove('is-empty');
      if (btnOpen) btnOpen.href = url;

      // foto → FormData
      if (photoDataUrl) {
        const blob = dataUrlToBlob(photoDataUrl);
        const fname = `${token}.jpg`;
        fd.set('foto', blob, fname);
      }
      fd.set('token', token);

      if (saveUrl) {
        try {
          const res = await fetch(saveUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' },
            credentials: 'same-origin'
          });

          let js = null, txt = null;
          try { js = await res.json(); } catch { try { txt = await res.text(); } catch {} }

          // refresh CSRF hidden input
          if (js && js.csrf && js.csrf_token) {
            const inp = form.querySelector(`input[name="${js.csrf_token}"]`);
            if (inp) inp.value = js.csrf;
          }

          if (!res.ok || !js || js.ok !== true) {
            const msg = (js && (js.error || js.message)) || (txt ? txt.slice(0,200) : '') || `Gagal simpan (${res.status})`;
            throw new Error(msg);
          }

          if (btnOpen && js.url) {
            btnOpen.href = js.url;
            setText('pvUrl', js.url);
            setText('cardUrlText', shortDisplay(js.url));
          }
          swalOK('Disimpan', 'Data perangkat & foto tersimpan');

        } catch (err) {
          console.error('Save error:', err);
          const msg = (err && err.message && err.message !== '{}') ? err.message : 'Terjadi kesalahan saat menyimpan.';
          swalErr('Gagal simpan', msg);
        }
      } else {
        swalOK('QR berhasil dibuat');
      }

      // simpan state
      try {
        const state = { token, url, nama, merek, model, serial, lokasi, bmn, kap, status, base, photo: photoDataUrl || null };
        sessionStorage.setItem('lastQR', JSON.stringify(state));
      } catch {}
    });

    // ======== RESET
    function resetUI(){
      try {
        ['qrcode','qrInCard'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = ''; });
        ['pvNama','pvKode','pvMerek','pvModelSn','pvLokasi','pvBmn','pvKap','pvUrl','cardUrlText'].forEach(id => setText(id,'—'));
        applyStatusBadge(''); // reset badge
        qrWrap?.classList.add('is-empty');
        btnOpen && (btnOpen.href = '#');

        // foto & dropzone
        const pvImg = document.getElementById('pvImg');
        const dzPreview = document.getElementById('dzPreview');
        const dzPreviewBox = document.getElementById('dzPreviewBox');
        const dzEmpty = document.getElementById('dzEmpty');

        pvImg?.removeAttribute('src'); document.getElementById('pvPhotoBox')?.classList.add('d-none');
        dzPreview?.removeAttribute('src'); dzPreviewBox?.classList.add('d-none'); dzEmpty?.classList.remove('d-none');
        if (fileInput) fileInput.value = '';
        photoDataUrl = null;

        alertBox?.classList.add('d-none');
        form?.classList.remove('was-validated');
        form?.querySelectorAll('.is-valid,.is-invalid').forEach(el=>el.classList.remove('is-valid','is-invalid'));
        statusSel && (statusSel.value = 'NORMAL');

        try { sessionStorage.removeItem('lastQR'); } catch {}
      } catch (e) {
        console.error('Reset UI error:', e);
        swalErr('Reset gagal', 'Terjadi kesalahan saat mereset tampilan.');
      }
    }
    document.getElementById('btnReset')?.addEventListener('click', () => setTimeout(resetUI, 0));
    form?.addEventListener('reset', () => setTimeout(resetUI, 0));

    // ===== Lainnya
    document.getElementById('btnDownload')?.addEventListener('click', () => {
      const img = document.querySelector('#qrcode img') || document.querySelector('#qrcode canvas');
      if (!img) { swalErr('Gagal', 'Buat QR dulu.'); return; }
      const a = document.createElement('a');
      a.href = img.src || img.toDataURL?.('image/png');
      a.download = 'qr-perangkat.png';
      a.click();
    });
    document.getElementById('btnPrint')?.addEventListener('click', () => {
      try { if (!sessionStorage.getItem('lastQR')) { swalErr('Gagal', 'Buat QR dulu.'); return; } } catch {}
      window.print();
    });
    document.getElementById('btnJson')?.addEventListener('click', () => {
      let state = null; try { state = sessionStorage.getItem('lastQR') && JSON.parse(sessionStorage.getItem('lastQR')); } catch {}
      if (!state) { swalErr('Gagal simpan', 'Buat QR dulu.'); return; }
      const payload = {
        token: state.token,
        kode_qr: state.token,
        nama: state.nama,
        merek: state.merek || null,
        model: state.model || null,
        serial_no: state.serial || null,
        bmn_no_display: state.bmn || null,
        kapasitas_btu: state.kap || '12000',
        lokasi: state.lokasi || null,
        status: state.status || 'NORMAL',
        url: state.url,
        foto: state.photo || null
      };
      try {
        const blob = new Blob([JSON.stringify(payload, null, 2)], { type:'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (state.token || 'perangkat') + '.json';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1500);
        swalOK('Tersimpan', 'File JSON sudah diunduh.');
      } catch (e) {
        console.error(e);
        swalErr('Gagal simpan', 'Tidak bisa membuat file JSON.');
      }
    });
  });
})();
