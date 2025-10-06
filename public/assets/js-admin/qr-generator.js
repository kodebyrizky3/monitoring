(() => {
  'use strict';

  /* ===================== Utils ===================== */
  function randomHex(bytes = 16) {
    if (crypto?.getRandomValues) {
      const a = new Uint8Array(bytes); crypto.getRandomValues(a);
      return Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
    }
    let s = ''; for (let i = 0; i < bytes; i++) s += Math.floor(Math.random() * 256).toString(16).padStart(2, '0');
    return s;
  }
  const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const sanitizeKode = k => (k || '').toString().trim().toUpperCase();
  const makeURL = (base, token) => (base || '').replace(/\/+$/, '') + '/ac/' + token;
  function shortDisplay(url) {
    try { const u = new URL(url); return (u.host + u.pathname).replace(/\/+$/, ''); }
    catch { return url; }
  }

  const swalOK  = (title, text) => window.Swal
    ? Swal.fire({ icon: 'success', title: title || 'Berhasil', text: text || '', timer: 1400, showConfirmButton: false })
    : alert(title || 'Berhasil');
  const swalErr = (title, text) => window.Swal
    ? Swal.fire({ icon: 'error', title: title || 'Gagal', text: text || '' })
    : alert((title || 'Gagal') + (text ? (': ' + text) : ''));

  /* ============== Image helpers ============== */
  const fileToDataURL = f => new Promise((res, rej) => {
    const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(f);
  });
  const compressDataURL = (dataUrl, maxW = 1200, maxH = 1200, quality = 0.85) => new Promise((res) => {
    const img = new Image();
    img.onload = () => {
      const ratio = Math.min(maxW / img.width, maxH / img.height, 1);
      const w = Math.round(img.width * ratio), h = Math.round(img.height * ratio);
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      c.getContext('2d').drawImage(img, 0, 0, w, h);
      const type = dataUrl.startsWith('data:image/png') ? 'image/png' : 'image/jpeg';
      res(c.toDataURL(type, quality));
    };
    img.src = dataUrl;
  });
  function dataUrlToBlob(dataUrl) {
    const [meta, b64] = dataUrl.split(',');
    const mime = (meta.match(/data:(.*?);/) || [])[1] || 'image/jpeg';
    const bin = atob(b64);
    const u8 = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    return new Blob([u8], { type: mime });
  }

  function renderQR(targetId, text, size = 256) {
    const box = document.getElementById(targetId); if (!box) return;
    box.innerHTML = '';
    new QRCode(box, { text, width: size, height: size, correctLevel: QRCode.CorrectLevel.M });
  }

  /* ===================== State ===================== */
  let currentQR = null;
  let busy = false;

  /* ===================== Main ===================== */
  document.addEventListener('DOMContentLoaded', () => {
    const baseInput = document.getElementById('baseUrl');
    if (baseInput && !baseInput.value) baseInput.value = location.origin;

    const form     = document.getElementById('formQR');
    const alertBox = document.getElementById('alertBox');
    const btnOpen  = document.getElementById('btnOpen');
    const qrWrap   = document.getElementById('qrWrap');
    if (qrWrap) qrWrap.classList.add('is-empty'); // kolom QR besar disembunyikan dulu

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

    function applyPhoto(dataUrl) {
      photoDataUrl = dataUrl || null;
      if (photoDataUrl) {
        dzPreview.src = photoDataUrl;
        dzEmpty.classList.add('d-none');
        dzPreviewBox.classList.remove('d-none');
        pvImg.src = photoDataUrl; pvPhotoBox.classList.remove('d-none');
      } else {
        dzPreview.removeAttribute('src');
        dzPreviewBox.classList.add('d-none');
        dzEmpty.classList.remove('d-none');
        pvImg.removeAttribute('src'); pvPhotoBox.classList.add('d-none');
      }
    }

    // Bind foto
    btnPick?.addEventListener('click', (e) => { e.preventDefault(); fileInput.click(); });
    btnGanti?.addEventListener('click', (e) => { e.preventDefault(); fileInput.click(); });
    btnHapus?.addEventListener('click', (e) => { e.preventDefault(); applyPhoto(null); });
    fileInput?.addEventListener('change', async (e) => {
      const f = e.target.files?.[0]; if (!f || !f.type.startsWith('image/')) return;
      const raw = await fileToDataURL(f); const dataUrl = await compressDataURL(raw, 1200, 1200, 0.85);
      applyPhoto(dataUrl);
    });
    if (dz) {
      ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dragover'); }));
      ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('dragover'); }));
      dz.addEventListener('drop', async (e) => {
        const f = e.dataTransfer?.files?.[0]; if (!f || !f.type.startsWith('image/')) return;
        const raw = await fileToDataURL(f); const dataUrl = await compressDataURL(raw, 1200, 1200, 0.85);
        applyPhoto(dataUrl);
      });
    }

    // Generate
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (busy) return;
      if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
      busy = true;

      try {
        const fd     = new FormData(form);
        const nama   = (fd.get('nama')      || '').toString().trim();
        const merek  = (fd.get('merek')     || '').toString().trim();
        const model  = (fd.get('model')     || '').toString().trim();
        const serial = (fd.get('serial_no') || '').toString().trim();
        const lokasi = (fd.get('lokasi')    || '').toString().trim();
        const kode   = sanitizeKode(fd.get('kode_qr')); // opsional (view boleh tanpa field ini)
        const base   = (fd.get('base')      || location.origin).toString().trim();

        const token = randomHex(16);
        const url   = makeURL(base, token);

        // Preview teks
        setText('pvNama',    nama   || '—');
        setText('pvKode',    kode   || token);
        setText('pvMerek',   merek  || '—');
        setText('pvModelSn', `${model || '—'}${serial ? ` / ${serial}` : ''}`);
        setText('pvLokasi',  lokasi || '—');
        setText('pvUrl',     url);
        setText('cardUrlText', shortDisplay(url));

        const badge = document.getElementById('pvBadge');
        if (badge) { badge.className = 'badge text-bg-success status-badge'; badge.textContent = 'Normal'; }

        // QR: besar (layar) + kecil (untuk cetak di card kiri)
        renderQR('qrcode',   url, 256);
        renderQR('qrInCard', url, 180); // ukuran saat print dikunci mm via CSS
        qrWrap?.classList.remove('is-empty');
        if (btnOpen) btnOpen.href = url;

        currentQR = { token, url, nama, merek, model, serial, lokasi, status: 'normal', kode, base, photo: photoDataUrl || null };
        sessionStorage.setItem('lastQR', JSON.stringify(currentQR));

        // Notifikasi: kalau TIDAK simpan ke server, tampilkan 1x
        if (!saveUrl) swalOK('QR berhasil dibuat');

        // Persist ke server (opsional)
        if (saveUrl) {
          fd.set('token', token);
          if (photoDataUrl) {
            const blob = dataUrlToBlob(photoDataUrl);
            const fname = `${(kode || token).replace(/\W+/g, '_')}.jpg`;
            fd.set('foto', blob, fname);
          }

          try {
            const res = await fetch(saveUrl, {
              method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            let js = null; try { js = await res.json(); } catch {}
            if (!res.ok || !js || !js.ok) {
              const msg = (js && (js.error || JSON.stringify(js.detail || {}))) || `Gagal simpan (${res.status})`;
              throw new Error(msg);
            }
            if (btnOpen && js.url) {
              btnOpen.href = js.url;
              setText('pvUrl', js.url);
              setText('cardUrlText', shortDisplay(js.url));
              currentQR.url = js.url;
              sessionStorage.setItem('lastQR', JSON.stringify(currentQR));
            }
            // HANYA 1 popup (sukses simpan)
            swalOK('Disimpan', 'Data perangkat & foto tersimpan');
          } catch (e2) {
            console.error(e2);
            swalErr('Gagal simpan', e2.message || 'Terjadi kesalahan');
          }
        }

      } catch (e1) {
        console.error(e1);
        swalErr('Gagal', 'Terjadi kesalahan saat membuat QR.');
      } finally {
        busy = false;
      }
    });

    // Tombol
    document.getElementById('btnDownload')?.addEventListener('click', () => {
      const img = document.querySelector('#qrcode img') || document.querySelector('#qrcode canvas');
      if (!img) { swalErr('Gagal', 'Buat QR dulu.'); return; }
      const a = document.createElement('a');
      a.href = img.src || img.toDataURL('image/png');
      a.download = 'qr-perangkat.png';
      a.click();
    });

    document.getElementById('btnPrint')?.addEventListener('click', () => {
      if (!currentQR && !sessionStorage.getItem('lastQR')) {
        swalErr('Gagal', 'Buat QR dulu.');
        return;
      }
      window.print(); // CSS print hanya menampilkan .device-card
    });

    document.getElementById('btnJson')?.addEventListener('click', () => {
      const state = currentQR || (sessionStorage.getItem('lastQR') && JSON.parse(sessionStorage.getItem('lastQR')));
      if (!state) { swalErr('Gagal simpan', 'Buat QR dulu.'); return; }
      const payload = {
        token: state.token,
        kode_qr: state.kode || null,
        nama: state.nama,
        merek: state.merek || null,
        model: state.model || null,
        serial_no: state.serial || null,
        lokasi: state.lokasi || null,
        status: 'normal',
        url: state.url,
        foto: state.photo || null
      };
      try {
        const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (payload.kode_qr || payload.token || 'perangkat') + '.json';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1500);
        swalOK('Tersimpan', 'File JSON sudah diunduh.');
      } catch (e) {
        console.error(e);
        swalErr('Gagal simpan', 'Tidak bisa membuat file JSON.');
      }
    });

    document.getElementById('btnReset')?.addEventListener('click', () => {
      // Bersih
      ['qrcode','qrInCard'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = ''; });
      ['pvNama','pvKode','pvMerek','pvModelSn','pvLokasi','pvUrl','cardUrlText']
        .forEach(id => setText(id, '—'));
      const bb = document.getElementById('pvBadge'); if (bb) { bb.className = 'badge text-bg-secondary status-badge'; bb.textContent = 'Status'; }
      alertBox?.classList.add('d-none');
      if (btnOpen) btnOpen.href = '#';
      qrWrap?.classList.add('is-empty');

      photoDataUrl = null;
      dzPreview?.removeAttribute('src');
      dzPreviewBox?.classList.add('d-none');
      dzEmpty?.classList.remove('d-none');
      pvPhotoBox?.classList.add('d-none');

      currentQR = null;
      sessionStorage.removeItem('lastQR');
    });
  });
})();
