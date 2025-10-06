// public/assets/js-user/u-kendaraan.js  (v1.3.0)
(function(){
  'use strict';

  // ====== refs
  const qInput = document.getElementById('qInput');

  // lists & info
  const lists = {
    perbaikan:  { list: document.getElementById('listPerbaikan'),  empty: document.getElementById('emptyPerbaikan'),  info: document.getElementById('infoPerbaikan') },
    perjalanan: { list: document.getElementById('listPerjalanan'), empty: document.getElementById('emptyPerjalanan'), info: document.getElementById('infoPerjalanan') },
    kerusakan:  { list: document.getElementById('listKerusakan'),  empty: document.getElementById('emptyKerusakan'),  info: document.getElementById('infoKerusakan') },
  };

  // buttons
  const btnAddPerbaikan    = document.getElementById('btnAddPerbaikan');
  const btnAddPerjalanan   = document.getElementById('btnAddPerjalanan');
  const btnAddKerusakan    = document.getElementById('btnAddKerusakan');
  const btnEmptyAddPerbaikan  = document.getElementById('btnEmptyAddPerbaikan');
  const btnEmptyAddPerjalanan = document.getElementById('btnEmptyAddPerjalanan');
  const btnEmptyAddKerusakan  = document.getElementById('btnEmptyAddKerusakan');

  // modals & forms
  const modalSvc  = document.getElementById('modalPerbaikan')  ? new bootstrap.Modal(document.getElementById('modalPerbaikan'))  : null;
  const formSvc   = document.getElementById('formPerbaikan');

  const modalTrip = document.getElementById('modalPerjalanan') ? new bootstrap.Modal(document.getElementById('modalPerjalanan')) : null;
  const formTrip  = document.getElementById('formPerjalanan');

  const modalTix  = document.getElementById('modalKerusakan')  ? new bootstrap.Modal(document.getElementById('modalKerusakan'))  : null;
  const formTix   = document.getElementById('formKerusakan');

  // selects (di modal)
  const selSvcKend = document.getElementById('svcKendaraan');
  const selTripKend= document.getElementById('tripKendaraan');
  const selTripEmp = document.getElementById('tripPengemudi');
  const selTixKend = document.getElementById('tixKendaraan');
  const tripOdoAwal= document.getElementById('tripOdoAwal');

  // bottom bar buttons
  const baPerbaikan  = document.getElementById('baPerbaikan');
  const baPerjalanan = document.getElementById('baPerjalanan');
  const baKerusakan  = document.getElementById('baKerusakan');

  // cache metadata kendaraan untuk prefill odo
  let KEND_CACHE = {};

  // ====== helpers
  function syncCsrf(newHash){
    if (!newHash || !window.CSRF?.name) return;
    window.CSRF.hash = newHash;
    document.querySelectorAll('input[name="'+window.CSRF.name+'"]').forEach(i => i.value = newHash);
  }
  function debounce(fn, t){ let id; return (...a)=>{ clearTimeout(id); id=setTimeout(()=>fn(...a), t); }; }
  function escapeHtml(s) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    };
    return String(s ?? '').replace(/[&<>"']/g, ch => map[ch]);
  }
  

  // set active color on bottom bar (bikin tidak selalu biru)
  function syncBottomBar(active) {
    const map = { perbaikan: baPerbaikan, perjalanan: baPerjalanan, kerusakan: baKerusakan };
    Object.entries(map).forEach(([k, btn]) => {
      if (!btn) return;
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-outline-primary');
      if (k === active) {
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
      }
    });
  }

  // tab change -> sync bottom bar + fetch list
  document.querySelectorAll('#kendTabs button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
      const key = (e.target.getAttribute('data-bs-target') || '').replace('#pane-',''); // perbaikan/perjalanan/kerusakan
      if (!key) return;
      syncBottomBar(key);
      fetchList(key, true);
    });
  });

  // bottom bar click -> trigger tab
  baPerbaikan   && baPerbaikan.addEventListener('click',  () => document.getElementById('tab-perbaikan')?.click());
  baPerjalanan  && baPerjalanan.addEventListener('click', () => document.getElementById('tab-perjalanan')?.click());
  baKerusakan   && baKerusakan.addEventListener('click',  () => document.getElementById('tab-kerusakan')?.click());

  // initial active
  syncBottomBar('perbaikan');

  // ====== options loader (dropdown from DB)
  async function loadOptions(url) {
    const res = await fetch(url, { headers: { 'Accept':'application/json' }, cache:'no-store' });
    const json = await res.json();
    if (json.csrf) syncCsrf(json.csrf);
    if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
    return json.rows || [];
  }
  async function fillSelect(sel, rows, selectedId='') {
    if (!sel) return;
    sel.innerHTML = '<option value="">Pilih...</option>';
    rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.text;
      // simpan metadata kalau ada (untuk kendaraan: odo & status)
      if (typeof r.odo !== 'undefined') {
        opt.dataset.odo = r.odo;
        opt.dataset.status = r.status || '';
      }
      if (String(r.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  // ====== open modals -> load selects
  btnAddPerbaikan && btnAddPerbaikan.addEventListener('click', async () => {
    try {
      const kend = await loadOptions(APP.optVehicles + '?limit=300'); // semua kendaraan
      await fillSelect(selSvcKend, kend);
      modalSvc && modalSvc.show();
    } catch (e) { alert(e.message || 'Gagal memuat kendaraan'); }
  });
  btnEmptyAddPerbaikan && btnEmptyAddPerbaikan.addEventListener('click', () => btnAddPerbaikan?.click());

  btnAddPerjalanan && btnAddPerjalanan.addEventListener('click', async () => {
    try {
      // hanya kendaraan SIAP agar UX sesuai aturan
      const [kend, emp] = await Promise.all([
        loadOptions(APP.optVehicles + '?onlyReady=1&limit=300'),
        loadOptions(APP.optEmployees + '?active=1&limit=300'),
      ]);

      // simpan cache odo untuk prefill
      KEND_CACHE = {};
      kend.forEach(k => { KEND_CACHE[String(k.id)] = { odo: k.odo || 0, status: k.status || '' }; });

      await fillSelect(selTripKend, kend);
      await fillSelect(selTripEmp,  emp);

      // reset odo awal saat modal dibuka
      if (tripOdoAwal) { tripOdoAwal.value = ''; tripOdoAwal.min = 0; }

      modalTrip && modalTrip.show();
    } catch (e) { alert(e.message || 'Gagal memuat opsi'); }
  });
  btnEmptyAddPerjalanan && btnEmptyAddPerjalanan.addEventListener('click', () => btnAddPerjalanan?.click());

  // Prefill odo saat kendaraan dipilih
  selTripKend && selTripKend.addEventListener('change', () => {
    const id  = String(selTripKend.value || '');
    const meta = KEND_CACHE[id];
    if (meta && tripOdoAwal) {
      tripOdoAwal.value = meta.odo || 0;
      tripOdoAwal.min   = meta.odo || 0;
    }
  });

  btnAddKerusakan && btnAddKerusakan.addEventListener('click', async () => {
    try {
      const kend = await loadOptions(APP.optVehicles + '?limit=300');
      await fillSelect(selTixKend, kend);
      modalTix && modalTix.show();
    } catch (e) { alert(e.message || 'Gagal memuat kendaraan'); }
  });
  btnEmptyAddKerusakan && btnEmptyAddKerusakan.addEventListener('click', () => btnAddKerusakan?.click());

  // ====== list fetcher
  let qState = '';

  function getSearchEndpoint(tab){
    // Prefer struktur baru per-tab
    if (APP.search && APP.search[tab]) return APP.search[tab];
    // Fallback ke satu endpoint lama: /u/kendaraan/search?tab=...
    if (APP.searchUrl) {
      const u = new URL(APP.searchUrl, window.location.origin);
      u.searchParams.set('tab', tab);
      return u.toString();
    }
    // terakhir: coba relative
    return '/u/kendaraan/' + tab + '/search';
  }

  async function fetchList(tab='perbaikan', reset=false){
    try {
      const url = new URL(getSearchEndpoint(tab), window.location.origin);
      url.searchParams.set('q', qState);
      const res = await fetch(url.toString(), { headers: { 'Accept':'application/json' }, cache:'no-store' });
      const json = await res.json();
      if (json.csrf) syncCsrf(json.csrf);
      if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat');
      renderList(tab, json.rows || [], json.total || 0);
    } catch (e) {
      console.error(e);
      renderList(tab, [], 0);
    }
  }

  function renderList(tab, rows, total){
    const target = lists[tab];
    if (!target) return;
    const { list, empty, info } = target;
    if (!list) return;

    if (!rows.length) {
      list.innerHTML = '';
      empty && empty.classList.remove('d-none');
      if (info) info.textContent = '0 item';
      return;
    }
    empty && empty.classList.add('d-none');

    list.innerHTML = rows.map(r => {
      if (tab === 'perjalanan') {
        // rows: {id, no_polisi, tujuan, odo_awal, odo_akhir?, status, waktu}
        return `
          <a class="u-item" href="javascript:void(0)" data-id="${r.id}">
            <div class="u-title">
              ${escapeHtml(r.no_polisi)}
              <span class="badge bg-secondary ms-1">${escapeHtml(r.status)}</span>
            </div>
            <div class="u-sub">${escapeHtml(r.tujuan || '-')}</div>
            <div class="u-time">${escapeHtml(r.waktu || '')}</div>
          </a>`;
      } else if (tab === 'kerusakan') {
        // rows: {id, no_polisi, deskripsi, status, waktu}
        return `
          <a class="u-item" href="javascript:void(0)" data-id="${r.id}">
            <div class="u-title">${escapeHtml(r.no_polisi)}</div>
            <div class="u-sub">${escapeHtml(r.deskripsi || '-')}</div>
            <div class="u-time">${escapeHtml(r.waktu || '')}</div>
          </a>`;
      } else { // perbaikan
        // rows: {id, no_polisi, keluhan, status, waktu}
        return `
          <a class="u-item" href="javascript:void(0)" data-id="${r.id}">
            <div class="u-title">
              ${escapeHtml(r.no_polisi)}
              <span class="badge bg-warning text-dark ms-1">${escapeHtml(r.status)}</span>
            </div>
            <div class="u-sub">${escapeHtml(r.keluhan || '-')}</div>
            <div class="u-time">${escapeHtml(r.waktu || '')}</div>
          </a>`;
      }
    }).join('');

    if (info) info.textContent = `${total} item`;
  }

  // live search
  qInput && qInput.addEventListener('input', debounce(() => {
    qState = qInput.value || '';
    const activeBtn = document.querySelector('#kendTabs .nav-link.active');
    const key = activeBtn?.getAttribute('data-bs-target')?.replace('#pane-','') || 'perbaikan';
    fetchList(key, true);
  }, 300));

  // ====== submit helpers
  async function submitForm(url, fd) {
    if (window.CSRF?.name && window.CSRF?.hash && !fd.has(window.CSRF.name)) {
      fd.set(window.CSRF.name, window.CSRF.hash);
    }
    const res  = await fetch(url, { method:'POST', body: fd, headers:{'Accept':'application/json'}, cache:'no-store' });
    const json = await res.json();
    if (json.csrf) syncCsrf(json.csrf);
    if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memproses');
    return json;
  }

  // ====== submit forms
  formSvc && formSvc.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const fd = new FormData(formSvc);
      await submitForm(APP.svcStoreUrl, fd);
      modalSvc && modalSvc.hide();
      document.getElementById('tab-perbaikan')?.click(); // refresh list perbaikan
    } catch (err) {
      alert(err.message || 'Gagal mengirim perbaikan');
    }
  });

  formTrip && formTrip.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const fd = new FormData(formTrip); // include foto_dash_berangkat
      await submitForm(APP.tripStartUrl, fd);
      modalTrip && modalTrip.hide();
      document.getElementById('tab-perjalanan')?.click(); // refresh list perjalanan
    } catch (err) {
      alert(err.message || 'Gagal memulai perjalanan');
    }
  });

  formTix && formTix.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const fd = new FormData(formTix);
      await submitForm(APP.tixStoreUrl, fd);
      modalTix && modalTix.hide();
      document.getElementById('tab-kerusakan')?.click(); // refresh list kerusakan
    } catch (err) {
      alert(err.message || 'Gagal mengirim laporan');
    }
  });

  // ===== init first load
  fetchList('perbaikan', true);
})();
