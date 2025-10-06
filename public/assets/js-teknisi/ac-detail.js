(function(){
  const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const statusClass = s => ({normal:'success', rusak:'danger', maintenance:'warning', diproses:'info'}[(s||'').toLowerCase()] || 'secondary');

  const base  = document.body.dataset.base || '';
  const token = window.Tech?.token || '';
  const loadAC = window.Tech?.loadAC;

  const KEY='repairs';
  const db = {
    all(){ try{ return JSON.parse(localStorage.getItem(KEY)||'{}'); }catch{ return {}; } },
    get(t){ return this.all()[t] || null; },
    save(map){ localStorage.setItem(KEY, JSON.stringify(map)); },
    upsert(t, patch){
      const map=this.all();
      const cur= map[t] || { token:t, status:'diproses', timeline:[] };
      map[t] = { ...cur, ...patch };
      this.save(map);
      return map[t];
    }
  };

  // Online badge
  const netBadge = document.getElementById('netBadge');
  function updateNet(){
    if (!netBadge) return;
    if (navigator.onLine){ netBadge.textContent='Online'; netBadge.className='badge bg-success'; }
    else { netBadge.textContent='Offline'; netBadge.className='badge bg-secondary'; }
    netBadge.classList.remove('d-none');
  }
  window.addEventListener('online',updateNet); window.addEventListener('offline',updateNet); updateNet();

  document.getElementById('btnRefresh')?.addEventListener('click', ()=>location.reload());

  // Load data AC
  (async ()=>{
    const d = token ? await loadAC(token) : null;
    if (!d){
      setText('namaAlat','AC'); setText('kodeQr', token || '—');
      document.getElementById('laporanList').innerHTML =
        `<div class="list-group-item text-center text-muted py-4">Data tidak ditemukan.</div>`;
      document.getElementById('photoSkeleton')?.remove();
      return;
    }
    setText('namaAlat', d.nama || 'AC');
    setText('kodeQr', d.kode || token);
    setText('merek', d.merek || '—');
    setText('modelSn', `${d.model || '—'}${d.serial_no ? ' / ' + d.serial_no : ''}`);
    setText('lokasi', d.lokasi || '—');

    const st = d.status || 'normal';
    const badge = document.getElementById('badgeStatus');
    badge.textContent = st.charAt(0).toUpperCase()+st.slice(1);
    badge.className = `badge rounded-pill px-3 py-2 text-bg-${statusClass(st)}`;

    const url = d.foto || '';
    const photo = document.getElementById('acPhoto');
    const skel  = document.getElementById('photoSkeleton');
    const btnZoom = document.getElementById('btnZoom');
    const modalPhoto = document.getElementById('modalPhoto');
    if (url){
      photo.src = url;
      photo.onload = ()=>{ photo.classList.remove('d-none'); skel?.remove(); btnZoom.classList.remove('d-none'); };
      photo.onerror = ()=> skel?.remove();
      btnZoom.addEventListener('click', ()=>{
        modalPhoto.src = url;
        new bootstrap.Modal(document.getElementById('photoModal')).show();
      });
    } else skel?.remove();

    const list = document.getElementById('laporanList');
    list.innerHTML = d.laporan?.length
      ? d.laporan.map(l=>`<div class="list-group-item">
          <div class="d-flex align-items-start justify-content-between gap-3">
            <div class="flex-grow-1">
              <div class="fw-semibold">${l.judul || 'Laporan'}</div>
              <div class="text-muted small">${l.deskripsi || ''}</div>
              <div class="text-muted small mt-1"><i class="bi bi-clock me-1"></i>${l.created_at || ''}</div>
            </div>
            <span class="badge text-bg-secondary">${l.status || '-'}</span>
          </div>
        </div>`).join('')
      : `<div class="list-group-item text-center text-muted py-4">Tidak ada laporan aktif dari user.</div>`;
  })();

  // CTA: Buat Laporan Perbaikan
  document.getElementById('btnPerbaikan')?.addEventListener('click', (e)=>{
    e.preventDefault();
    if (!token) return;
    const cur = db.get(token);
    db.upsert(token, {
      status: 'diproses',
      started_at: cur?.started_at || Date.now(),
      timeline: [ ...(cur?.timeline||[]), { at: Date.now(), type:'start', by:'teknisi' } ]
    });
    location.href = `${base}/ac/${encodeURIComponent(token)}/perbaikan`;
  });
})();
