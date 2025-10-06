(function(){
  const { token, loadAC } = window.Tech || {};
  const base = document.body.dataset.base || '';

  // Local storage mini-DB utk demo
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

  // Prefill info AC
  (async ()=>{
    const d = token ? await loadAC(token) : null;
    if (d){
      document.getElementById('acName').textContent = d.nama || 'AC';
      document.getElementById('acKode').textContent = d.kode || token;
      document.getElementById('acLokasi').textContent = d.lokasi || '—';
      if (d.foto){ const th=document.getElementById('acThumb'); th.src=d.foto; th.classList.remove('d-none'); }
    } else {
      document.getElementById('acName').textContent = 'AC';
      document.getElementById('acKode').textContent = token || '—';
    }
  })();

  // Pastikan ada draft 'diproses' (kalau user masuk langsung via URL)
  const cur = db.get(token);
  if (!cur) db.upsert(token, { status:'diproses', started_at: Date.now(), timeline:[{at:Date.now(),type:'start',by:'teknisi'}] });

  // Preview foto after
  const file = document.getElementById('fotoAfter');
  const box  = document.getElementById('afterPreviewBox');
  const img  = document.getElementById('afterPreview');
  file?.addEventListener('change', ()=>{
    const f = file.files?.[0]; if (!f) { box.classList.add('d-none'); img.removeAttribute('src'); return; }
    const r=new FileReader(); r.onload=()=>{ img.src=r.result; box.classList.remove('d-none'); }; r.readAsDataURL(f);
  });

  // Submit → tandai selesai
  const form = document.getElementById('formPerbaikan');
  const done = document.getElementById('alertDone');
  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    if (!form.checkValidity()){ form.classList.add('was-validated'); return; }
    const fd = new FormData(form);
    const payload = {
      tindakan: (fd.get('tindakan')||'').toString(),
      part: (fd.get('part')||'').toString(),
      biaya: Number(fd.get('biaya')||0)||0,
      foto_after: img?.src || null
    };
    db.upsert(token, {
      status: 'selesai',
      progress: { ...payload, finished_at: Date.now() },
      timeline: [ ...(db.get(token)?.timeline||[]), { at: Date.now(), type:'finish', by:'teknisi' } ]
    });

    done.classList.remove('d-none');

    // Opsional: scroll ke alert
    setTimeout(()=> done.scrollIntoView({behavior:'smooth'}), 10);
  });

  // Kembali ke detail
  document.getElementById('btnBackDetail')?.addEventListener('click', (e)=>{
    e.preventDefault();
    location.href = `${base}/ac/${encodeURIComponent(token)}`;
  });
})();
