(function(){
  const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

  function badgeClass(status){
    switch ((status||'').toUpperCase()) {
      case 'NORMAL': return 'text-bg-success';
      case 'MENUNGGU_PERBAIKAN': return 'text-bg-warning';
      case 'DALAM_PERBAIKAN': return 'text-bg-primary';
      default: return 'text-bg-secondary';
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
    try { if(/^https?:\/\//i.test(path)) return path; if (path.startsWith('/')) return location.origin+path; return location.origin+'/'+path.replace(/^\/+/,''); } catch { return path; }
  }
  async function fetchDetail(token){
    const url = `${location.origin}/ac/${encodeURIComponent(token)}?format=json`;
    const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    if (!res.ok) throw new Error(await res.text() || `Gagal memuat (${res.status})`);
    const js = await res.json();
    if (!js || !js.ok || !js.ac) throw new Error('Payload tidak valid');
    return js;
  }
  function renderAC(ac, tickets){
    setText('namaAlat', ac.nomor_unik || ac.kode_qr || 'Perangkat');
    setText('kodeQr', ac.kode_qr || '—');

    const tm = splitTipeModel(ac.tipe_model);
    setText('merek', tm.merek || '—');
    setText('modelSn', [tm.model||null, ac.catatan||null].filter(Boolean).join(' / ') || '—');
    setText('lokasi', ac.lokasi || '—');

    const badge = document.getElementById('badgeStatus');
    if (badge){ badge.className = `badge rounded-pill px-3 py-2 ${badgeClass(ac.status_ac)}`; badge.textContent = (ac.status_ac || 'NORMAL'); }

    const img = document.getElementById('acPhoto');
    const skeleton = document.getElementById('photoSkeleton');
    const btnZoom = document.getElementById('btnZoom');
    const modalImg = document.getElementById('modalPhoto');

    if (ac.foto_url){
      const src = absUrl(ac.foto_url);
      img.onload = () => { img.classList.remove('d-none'); skeleton?.classList.add('d-none'); btnZoom?.classList.remove('d-none'); };
      img.onerror = () => { skeleton?.classList.add('d-none'); };
      img.src = src;
      if (modalImg) modalImg.src = src;
      btnZoom?.addEventListener('click', ()=>{
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal){
          const m = new bootstrap.Modal(document.getElementById('photoModal')); m.show();
        } else { window.open(src, '_blank'); }
      });
    } else {
      img.classList.add('d-none');
      skeleton?.classList.add('d-none');
      btnZoom?.classList.add('d-none');
    }

    const btnPerbaikan = document.getElementById('btnPerbaikan');
    if (btnPerbaikan){
      const tok = ac.kode_qr || ac.nomor_unik;
      btnPerbaikan.href = `${location.origin}/ac/${encodeURIComponent(tok)}/perbaikan`;
    }

    const list = document.getElementById('laporanList');
    if (list){
      list.innerHTML = '';
      if (!tickets || tickets.length === 0){
        list.innerHTML = '<div class="list-group-item text-center text-muted py-4">Tidak ada laporan aktif.</div>';
      } else {
        tickets.forEach(t=>{
          const item = document.createElement('div');
          item.className = 'list-group-item';
          item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">${(t.judul||'Laporan')}</div>
                <div class="small text-muted">${(t.deskripsi||'')}</div>
              </div>
              <span class="badge ${badgeClass(t.status||'') }">${(t.status||'AKTIF')}</span>
            </div>`;
          list.appendChild(item);
        });
      }
    }
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    const holder = document.getElementById('__page');
    let token = holder?.dataset?.token || '';
    if (!token){
      const seg = (location.pathname||'/').split('/').filter(Boolean);
      const idx = seg.indexOf('ac'); if (idx>=0 && seg[idx+1]) token = decodeURIComponent(seg[idx+1]);
    }
    if (!token) return;

    try { const data = await fetchDetail(token); renderAC(data.ac, data.tickets || []); }
    catch (err){ console.error(err); document.getElementById('photoSkeleton')?.classList.add('d-none');
      const list = document.getElementById('laporanList'); if (list) list.innerHTML = '<div class="list-group-item text-danger">Gagal memuat data. Coba scan ulang.</div>'; }
  });
})();
