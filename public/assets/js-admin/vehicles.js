(function () {
  'use strict';

  // ====== DOM refs
  const qInput   = document.getElementById('qInput');
  const perSel   = document.getElementById('perPageSelect');
  const scopeSel = document.getElementById('filterSelect');
  const infoEl   = document.getElementById('liveInfo');
  const tbody    = document.getElementById('vehTbody');
  const pagerEl  = document.getElementById('vehPager');
  const totalEl  = document.getElementById('vehTotal');

  const btnAdd   = document.getElementById('btnAdd');
  const modalEl  = document.getElementById('vehModal');
  const vehModal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form     = document.getElementById('vehForm');
  const titleEl  = document.getElementById('vehModalTitle');
  const idInput  = document.getElementById('vehId');

  const CSRF = window.CSRF || { name: 'csrf_test_name', hash: '' };
  const APP  = window.APP  || {};

  // ====== State
  let state = {
    q: (qInput?.value || ''),
    perPage: parseInt(perSel?.value || '10', 10),
    page: 1,
    pageCount: 1,
    scope: (scopeSel?.value || 'all'),
  };
  let inflightController = null;
  let reqSeq = 0;

  // ====== Utils
  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
  function pageRange(current, total, width){
    const half=Math.floor(width/2);
    let start=Math.max(1,current-half);
    let end  =Math.min(total,start+width-1);
    start=Math.max(1,end-width+1);
    const arr=[]; for(let i=start;i<=end;i++) arr.push(i); return arr;
  }
  function setInfoLoading(){ if (infoEl) infoEl.textContent = 'Memuat…'; }
  function setInfoCount(rowsLen, total){
    if (!infoEl) return;
    if (!rowsLen) { infoEl.textContent = ''; return; }
    const start = ((state.page - 1) * state.perPage) + 1;
    const end   = ((state.page - 1) * state.perPage) + rowsLen;
    infoEl.textContent = `Menampilkan ${start}–${end} dari ${total}`;
  }
  function syncCsrfFromJson(json){
    if (json && json.csrf) {
      CSRF.hash = json.csrf;
      document.querySelectorAll(`input[name="${CSRF.name}"]`).forEach(i => i.value = CSRF.hash);
    }
  }
  function clearErrors(){
    form?.querySelectorAll('.is-invalid').forEach(e=>e.classList.remove('is-invalid'));
    form?.querySelectorAll('[data-err]').forEach(e=>e.textContent='');
  }
  function showErrors(errs){
    Object.entries(errs||{}).forEach(([k,v])=>{
      const i=form?.querySelector('[name="'+k+'"]');
      const h=form?.querySelector('[data-err="'+k+'"]');
      if (i) i.classList.add('is-invalid');
      if (h) h.textContent = v;
    });
  }
  function toNumberOrEmpty(v){
    if (v === '' || v === null || typeof v === 'undefined') return '';
    const n = Number(v);
    return Number.isFinite(n) ? n : '';
  }

  // ====== Renderers
  function renderRows(rows){
    if (!rows || !rows.length){
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">Tidak ada data.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map((r, idx) => {
      const no = ((state.page - 1) * state.perPage) + (idx + 1);
      const aktif = r.is_active ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>';
      const btns = (state.scope === 'archived')
        ? `<button type="button" class="btn btn-outline-success btn-restore" data-url="${r.restore_url}" data-name="${r.plat||''}">
             <i class="bi bi-arrow-counterclockwise"></i>
           </button>`
        : `<button type="button" class="btn btn-outline-primary btn-edit" data-id="${r.id}">
             <i class="bi bi-pencil"></i>
           </button>
           <button type="button" class="btn btn-outline-danger btn-delete" data-url="${r.delete_url}" data-name="${r.plat||''}">
             <i class="bi bi-trash"></i>
           </button>`;

      return `
        <tr>
          <td>${no}</td>
          <td>${(r.plat||'')}</td>
          <td>${(r.nama||'')}</td>
          <td>${(r.fuel_type||'')}</td>
          <td>${(r.kapasitas_tangki||0)}</td>
          <td>${(r.km_per_liter||0)}</td>
          <td>${(r.stok_liter_terkini||0)}</td>
          <td>${aktif}</td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">${btns}</div>
          </td>
        </tr>`;
    }).join('');
    bindRowActions();
  }

  function renderPager(){
    if (!pagerEl) return;
    const p = state.page, n = state.pageCount;
    if (n <= 1){ pagerEl.innerHTML = ''; return; }

    let html = `<ul class="pagination mb-0 justify-content-end">`;
    const btn = (label, page, disabled=false, active=false) =>
      `<li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
         <a class="page-link" href="#" data-page="${page}">${label}</a>
       </li>`;
    html += btn('&laquo;', Math.max(1, p-1), p===1);
    pageRange(p, n, 5).forEach(pg => { html += btn(pg, pg, false, pg===p); });
    html += btn('&raquo;', Math.min(n, p+1), p===n);
    html += `</ul>`;

    pagerEl.innerHTML = html;
    pagerEl.querySelectorAll('a.page-link').forEach(a => a.addEventListener('click', (e)=>{
      e.preventDefault();
      const pg = parseInt(a.dataset.page,10);
      if (!isNaN(pg) && pg>=1 && pg<=state.pageCount && pg!==state.page) { state.page = pg; fetchList(); }
    }));
  }

  // ====== Data fetch
  async function fetchList(){
    const mySeq = ++reqSeq;
    inflightController?.abort();
    inflightController = new AbortController();

    const u = new URL(APP.vehiclesSearch || '/admin/master/vehicles/search', window.location.origin);
    u.searchParams.set('q', state.q || '');
    u.searchParams.set('perPage', String(state.perPage || 10));
    u.searchParams.set('page', String(state.page || 1));
    u.searchParams.set('scope', state.scope);

    setInfoLoading();

    try {
      const res = await fetch(u.toString(), {
        headers: { 'Accept': 'application/json' },
        signal: inflightController.signal,
        cache: 'no-store'
      });
      const json = await res.json();
      syncCsrfFromJson(json);
      if (mySeq !== reqSeq) return;

      if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');

      state.pageCount = parseInt(json.pageCount ?? 1, 10) || 1;
      if (state.page > state.pageCount) { state.page = state.pageCount; return fetchList(); }

      renderRows(json.rows || []);
      renderPager();
      if (totalEl) totalEl.textContent = parseInt(json.total ?? 0, 10);
      setInfoCount((json.rows||[]).length, parseInt(json.total ?? 0,10));

    } catch (e) {
      if (e.name === 'AbortError') return;
      if (infoEl) infoEl.textContent = (e && e.message) ? e.message : 'Gagal memuat';
    }
  }

  // ====== Row actions
  function bindRowActions(){
    // Edit (scope != archived)
    document.querySelectorAll('.btn-edit').forEach(btn => btn.onclick = async () => {
      const id = btn.dataset.id;
      if (!id) return;
      try {
        const url = (APP.vehiclesShow || '/admin/master/vehicles') + '/' + id;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        const json = await res.json();
        syncCsrfFromJson(json);
        if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');

        const d = json.data || {};
        titleEl && (titleEl.textContent = 'Edit Kendaraan');
        idInput.value = d.id || '';
        form.plat.value = d.plat || '';
        form.nama.value = d.nama || '';
        form.fuel_type.value = d.fuel_type || 'PERTALITE';
        form.kapasitas_tangki.value = toNumberOrEmpty(d.kapasitas_tangki);
        form.km_per_liter.value     = toNumberOrEmpty(d.km_per_liter);
        form.stok_liter_terkini.value = toNumberOrEmpty(d.stok_liter_terkini);
        form.is_active.value = (typeof d.is_active !== 'undefined') ? String(d.is_active) : '1';

        clearErrors();
        vehModal?.show();
      } catch (e) {
        (window.Swal ? Swal : alert)({icon:'error', title:'Gagal', text: e.message || 'Terjadi kesalahan'});
      }
    });

    // Hapus (soft delete, POST ke /delete)
    document.querySelectorAll('.btn-delete').forEach(btn => btn.onclick = async (e) => {
      e.preventDefault();
      const url  = btn.dataset.url;
      const name = btn.dataset.name || 'kendaraan';
      if (!url) return;

      const ok = await Swal.fire({
        icon:'warning',
        title:'Hapus data?',
        html:`Kendaraan <b>${name}</b> akan diarsipkan (soft delete).`,
        showCancelButton:true,
        confirmButtonText:'Hapus',
        cancelButtonText:'Batal',
        reverseButtons:true
      }).then(r=>r.isConfirmed);
      if (!ok) return;

      const fd = new FormData();
      if (CSRF?.name && CSRF?.hash) fd.set(CSRF.name, CSRF.hash);

      const res = await fetch(url, { method:'POST', body: fd, headers:{'Accept':'application/json'} });
      const j   = await res.json();
      syncCsrfFromJson(j);

      if (!res.ok || !j.success){
        return Swal.fire({icon:'error', title:'Gagal', text: j.message || 'Tidak bisa menghapus'});
      }
      Swal.fire({icon:'success', title:'Diarsipkan', timer: 900, showConfirmButton:false});
      // jika saat ini scope=archived tidak berubah; kalau all/active, data akan hilang dari list
      fetchList();
    });

    // Restore (POST ke /restore)
    document.querySelectorAll('.btn-restore').forEach(btn => btn.onclick = async (e) => {
      e.preventDefault();
      const url  = btn.dataset.url;
      const name = btn.dataset.name || 'kendaraan';
      if (!url) return;

      const ok = await Swal.fire({
        icon:'question',
        title:'Pulihkan data?',
        html:`Kendaraan <b>${name}</b> akan dipulihkan.`,
        showCancelButton:true,
        confirmButtonText:'Pulihkan',
        cancelButtonText:'Batal',
        reverseButtons:true
      }).then(r=>r.isConfirmed);
      if (!ok) return;

      const fd = new FormData();
      if (CSRF?.name && CSRF?.hash) fd.set(CSRF.name, CSRF.hash);

      const res = await fetch(url, { method:'POST', body: fd, headers:{'Accept':'application/json'} });
      const j   = await res.json();
      syncCsrfFromJson(j);

      if (!res.ok || !j.success){
        return Swal.fire({icon:'error', title:'Gagal', text: j.message || 'Tidak bisa memulihkan'});
      }
      Swal.fire({icon:'success', title:'Dipulihkan', timer: 900, showConfirmButton:false});
      fetchList();
    });
  }

  // ====== Form submit (Tambah/Update)
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    // guard angka
    const tank = Number(form.kapasitas_tangki.value || 0);
    const kmpl = Number(form.km_per_liter.value || 0);
    if (tank <= 0 || kmpl <= 0) {
      if (tank <= 0) { form.kapasitas_tangki.classList.add('is-invalid'); form.querySelector('[data-err="kapasitas_tangki"]').textContent = 'Harus > 0'; }
      if (kmpl <= 0) { form.km_per_liter.classList.add('is-invalid'); form.querySelector('[data-err="km_per_liter"]').textContent = 'Harus > 0'; }
      return;
    }
    // stok awal tidak boleh negatif; opsional: <= kapasitas
    const stok = Number(form.stok_liter_terkini.value || 0);
    if (stok < 0) {
      form.stok_liter_terkini.classList.add('is-invalid');
      form.querySelector('[data-err="stok_liter_terkini"]').textContent = 'Tidak boleh negatif';
      return;
    }

    const isEdit = !!(idInput.value);
    const base   = (APP.vehicles || '/admin/master/vehicles').replace(/\/+$/,'');
    const url    = isEdit ? (base + '/' + idInput.value) : base;

    const fd = new FormData(form);
    if (CSRF?.name && CSRF?.hash) fd.set(CSRF.name, CSRF.hash);

    try {
      const res  = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      const json = await res.json();
      syncCsrfFromJson(json);

      if (!res.ok || !json.success) {
        if (json.errors) showErrors(json.errors);
        return Swal.fire({icon:'error', title:'Gagal', text: json.message || 'Validasi gagal'});
      }

      vehModal?.hide();
      Swal.fire({icon:'success', title: isEdit ? 'Diperbarui' : 'Tersimpan', timer: 1200, showConfirmButton: false});
      // refresh list
      fetchList();

    } catch (err) {
      Swal.fire({icon:'error', title:'Kesalahan', text: err.message || 'Terjadi kesalahan'});
    }
  });

  // ====== toolbar events
  qInput && qInput.addEventListener('input', debounce(() => {
    state.q = qInput.value || '';
    state.page = 1;
    fetchList();
  }, 250));

  perSel && perSel.addEventListener('change', () => {
    state.perPage = parseInt(perSel.value || '10', 10);
    state.page = 1;
    fetchList();
  });

  scopeSel && scopeSel.addEventListener('change', () => {
    state.scope = scopeSel.value || 'all';
    state.page = 1;
    // Saat lihat arsip, sembunyikan tombol tambah (opsional)
    if (btnAdd) btnAdd.style.display = (state.scope === 'archived') ? 'none' : '';
    fetchList();
  });

  btnAdd && btnAdd.addEventListener('click', () => {
    idInput.value = '';
    titleEl && (titleEl.textContent = 'Tambah Kendaraan');
    form.reset();
    // default fuel
    if (form.fuel_type) form.fuel_type.value = 'PERTALITE';
    clearErrors();
    vehModal?.show();
  });

  // reset form ketika modal ditutup (biar bersih saat buka lagi)
  modalEl?.addEventListener('hidden.bs.modal', () => {
    idInput.value = '';
    clearErrors();
  });

  // ===== init
  if (infoEl) infoEl.textContent = '';
  if (btnAdd && state.scope === 'archived') btnAdd.style.display = 'none';
  bindRowActions(); // untuk SSR rows pertama
})();
