(function(){
  // util kecil
  const $ = (id)=> document.getElementById(id);
  const setText = (id, v) => { const el = $(id); if (el) el.textContent = v; };

  // === SAMAKAN DENGAN LIST: NORMAL / RUSAK_RINGAN / RUSAK_BERAT ===
  function badgeClass(status){
    switch ((status||'').toUpperCase()) {
      case 'NORMAL':        return 'text-bg-success';
      case 'RUSAK_RINGAN':  return 'text-bg-warning';
      case 'RUSAK_BERAT':   return 'text-bg-danger';
      default:              return 'text-bg-secondary';
    }
  }

  function splitTipeModel(s){
    if (!s) return {merek:'—', model:'—'};
    const p = s.trim().split(/\s+/);
    if (p.length === 1) return {merek:p[0], model:'—'};
    return {merek:p[0], model:p.slice(1).join(' ')};
  }
  function absUrl(path){
    if (!path) return null;
    try {
      if(/^https?:\/\//i.test(path)) return path;
      if (path.startsWith('/')) return location.origin+path;
      return location.origin+'/'+path.replace(/^\/+/,'');
    } catch { return path; }
  }

  async function fetchDetail(token){
    const url = `${location.origin}/ac/${encodeURIComponent(token)}?format=json`;
    const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    if (!res.ok) throw new Error(await res.text() || `Gagal memuat (${res.status})`);
    const js = await res.json();
    if (!js || !js.ok || !js.ac) throw new Error('Payload tidak valid');
    return js;
  }

  function wirePhotoZoom(src){
    const img = $('acPhoto');
    const sk  = $('photoSkeleton');
    const btn = $('btnZoom');
    const mod = $('photoModal');
    const mi  = $('modalPhoto');

    if (!src){ img?.classList.add('d-none'); sk?.classList.add('d-none'); btn?.classList.add('d-none'); return; }

    img.onload = () => { img.classList.remove('d-none'); sk?.classList.add('d-none'); btn?.classList.remove('d-none'); };
    img.onerror = () => { sk?.classList.add('d-none'); };
    img.src = src; if (mi) mi.src = src;

    const open = () => {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal){
        new bootstrap.Modal(mod).show();
      } else {
        window.open(src, '_blank');
      }
    };
    img.addEventListener('click', open);
    btn?.addEventListener('click', open);
  }

  function renderAC(ac, tickets){
    setText('namaAlat', ac.nomor_unik || ac.kode_qr || 'Perangkat');
    setText('kodeQr', ac.kode_qr || '—');

    const tm = splitTipeModel(ac.tipe_model);
    setText('merek', tm.merek || '—');
    // tampilkan "model / SN" kalau ada; kalau tidak, strip
    setText('modelSn', [tm.model||null, ac.catatan||null].filter(Boolean).join(' / ') || '—');
    setText('lokasi', ac.lokasi || '—');

    const badge = $('badgeStatus');
    if (badge){
      badge.className = `badge rounded-pill px-3 py-2 ${badgeClass(ac.status_ac)}`;
      badge.textContent = (ac.status_ac || 'NORMAL');
    }

    // foto
    wirePhotoZoom(absUrl(ac.foto_url));

    // tombol buat perbaikan
    const btnPerbaikan = $('btnPerbaikan');
    if (btnPerbaikan){
      const tok = ac.kode_qr || ac.nomor_unik;
      btnPerbaikan.href = `${location.origin}/ac/${encodeURIComponent(tok)}/perbaikan`;
    }

    // daftar laporan aktif
    const list = $('laporanList');
    if (list){
      list.innerHTML = '';
      if (!tickets || tickets.length === 0){
        list.innerHTML = '<div class="list-group-item text-center text-muted py-4">Tidak ada laporan aktif.</div>';
      } else {
        tickets.forEach(t=>{
          const st = (t.status||'').toUpperCase();
          const bcls = badgeClass(st==='AKTIF' ? 'RUSAK_RINGAN' : st); // fallback sederhana
          const item = document.createElement('div');
          item.className = 'list-group-item';
          item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">${(t.judul||'Laporan')}</div>
                <div class="small text-muted">${(t.deskripsi||'')}</div>
              </div>
              <span class="badge ${bcls}">${(t.status||'AKTIF')}</span>
            </div>`;
          list.appendChild(item);
        });
      }
    }
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    // token dari data-attr atau path
    const holder = $('__page');
    let token = holder?.dataset?.token || '';
    if (!token){
      const seg = (location.pathname||'/').split('/').filter(Boolean);
      const idx = seg.indexOf('ac'); if (idx>=0 && seg[idx+1]) token = decodeURIComponent(seg[idx+1]);
    }
    if (!token) return;

    try {
      const data = await fetchDetail(token);
      renderAC(data.ac, data.tickets || []);
    } catch (err){
      console.error(err);
      $('photoSkeleton')?.classList.add('d-none');
      const list = $('laporanList');
      if (list) list.innerHTML = '<div class="list-group-item text-danger">Gagal memuat data. Coba scan ulang.</div>';
    }
  });
})();
