// public/assets/js-admin/employees.js  (v1.2.0)
(function () {
  'use strict';

  // ======= refs =======
  const modalEl = document.getElementById('empModal');
  const modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form    = document.getElementById('empForm');
  const titleEl = document.getElementById('empModalTitle');
  const tbody   = document.getElementById('empTbody');
  const totalEl = document.getElementById('empTotal');
  const infoEl  = document.getElementById('liveInfo');
  const pagerEl = document.getElementById('empPager');
  const qInput  = document.getElementById('qInput');
  const perSel  = document.getElementById('perPageSelect');
  const btnAdd  = document.getElementById('btnAdd');

  // ======= state =======
  let currentId = null;
  let state = {
    q: (qInput?.value || ''),
    perPage: parseInt(perSel?.value || '10', 10),
    page: 1,
    pageCount: 1,
  };

  // in-flight protection
  let inflightController = null;
  let reqSeq = 0; // monotonically increases each fetch

  // ======= utils =======
  function debounce(fn, ms) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  }
  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function pageRange(current, total, width) {
    const half = Math.floor(width / 2);
    let start = Math.max(1, current - half);
    let end   = Math.min(total, start + width - 1);
    start     = Math.max(1, end - width + 1);
    const arr = [];
    for (let i = start; i <= end; i++) arr.push(i);
    return arr;
  }
  function setInfoLoading() {
    if (infoEl) infoEl.textContent = 'Memuat…';
  }
  function setInfoCount(rowsLen, total, page, perPage) {
    if (!infoEl) return;
    if (!total) { infoEl.textContent = ''; return; }
    const start = rowsLen ? ((page - 1) * perPage + 1) : 0;
    const end   = rowsLen ? ((page - 1) * perPage + rowsLen) : 0;
    infoEl.textContent = `Menampilkan ${start}–${end} dari ${total}` + (state.q ? ' · Urut: relevansi' : '');
  }

  // ======= list fetch & render =======
  async function fetchList() {
    const mySeq = ++reqSeq;
    inflightController?.abort();
    inflightController = new AbortController();

    // clamp page if needed
    if (state.page < 1) state.page = 1;

    const u = new URL(window.APP?.pegawaiSearch || '/pegawai/search', window.location.origin);
    u.searchParams.set('q', state.q || '');
    u.searchParams.set('perPage', String(state.perPage || 10));
    u.searchParams.set('page', String(state.page || 1));

    setInfoLoading();

    try {
      const res = await fetch(u.toString(), {
        headers: { 'Accept': 'application/json' },
        signal: inflightController.signal,
        cache: 'no-store',
      });

      const json = await res.json();

      // if a newer request already started, ignore this result
      if (mySeq !== reqSeq) return;

      if (!res.ok || !json.success) {
        throw new Error(json.message || 'Gagal memuat data');
      }

      // normalize paging
      const total     = parseInt(json.total ?? 0, 10);
      const pageCount = parseInt(json.pageCount ?? 1, 10) || 1;
      state.pageCount = pageCount;
      // if current page > pageCount (e.g., after filtering), reset to last page & refetch once
      if (state.page > pageCount) {
        state.page = pageCount;
        // recall but ensure not infinite loop due to identical pageCount
        return fetchList();
      }

      // render rows
      const rows = json.rows || [];
      tbody.innerHTML = rows.length ? rows.map(r => `
        <tr>
          <td>${r.id}</td>
          <td><code>${escapeHtml(r.kode_pegawai)}</code></td>
          <td>${escapeHtml(r.nama)}</td>
          <td>${escapeHtml(r.email)}</td>
          <td>${escapeHtml(r.no_telp)}</td>
          <td>${r.is_active ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>'}</td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary btn-edit" data-id="${r.id}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-outline-danger btn-delete" data-id="${r.id}" data-url="${r.delete_url}" data-name="${escapeHtml(r.nama)}"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>
      `).join('') : `<tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>`;

      // totals
      if (totalEl) totalEl.textContent = total;

      // pager
      renderPager();
      bindRowActions();

      // info
      setInfoCount(rows.length, total, state.page, state.perPage);

    } catch (e) {
      if (e.name === 'AbortError') return; // fine, superseded
      if (infoEl) infoEl.textContent = e.message || 'Gagal memuat';
    }
  }

  function renderPager() {
    if (!pagerEl) return;
    const p = state.page, n = state.pageCount;
    if (n <= 1) { pagerEl.innerHTML = ''; return; }

    let html = `<ul class="pagination mb-0 justify-content-end">`;
    const btn = (label, page, disabled=false, active=false) =>
      `<li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
         <a class="page-link" href="#" data-page="${page}">${label}</a>
       </li>`;

    html += btn('&laquo;', Math.max(1, p - 1), p === 1);
    const range = pageRange(p, n, 5);
    range.forEach(pg => { html += btn(pg, pg, false, pg === p); });
    html += btn('&raquo;', Math.min(n, p + 1), p === n);
    html += `</ul>`;

    pagerEl.innerHTML = html;

    pagerEl.querySelectorAll('a.page-link').forEach(a => a.addEventListener('click', (e) => {
      e.preventDefault();
      const pg = parseInt(a.dataset.page, 10);
      if (!isNaN(pg) && pg >= 1 && pg <= state.pageCount && pg !== state.page) {
        state.page = pg;
        fetchList();
      }
    }));
  }

  // ======= CRUD helpers =======
  function clearErrors() {
    form?.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
    form?.querySelectorAll('[data-err]').forEach(e => e.textContent = '');
  }
  function showErrors(errs) {
    Object.entries(errs || {}).forEach(([k, v]) => {
      const i = form?.querySelector('[name="' + k + '"]');
      const h = form?.querySelector('[data-err="' + k + '"]');
      if (i) i.classList.add('is-invalid');
      if (h) h.textContent = v;
    });
  }
  function fillForm(d) {
    ['kode_pegawai', 'nama', 'email', 'no_telp', 'is_active'].forEach(k => {
      const el = form?.querySelector('[name="' + k + '"]');
      if (!el) return;
      el.value = (d && d[k] !== undefined ? d[k] : '');
    });
  }

  function bindRowActions() {
    // Edit
    document.querySelectorAll('.btn-edit').forEach(btn => btn.onclick = async () => {
      currentId = btn.dataset.id;
      if (!currentId) return;
      titleEl && (titleEl.textContent = 'Edit Pegawai');
      form?.querySelector('#_method')?.setAttribute('value','PUT');
      clearErrors();
      try {
        const url = (window.APP?.pegawai || '/pegawai') + '/' + currentId;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        const json = await res.json();
        if (json.csrf) {
          window.CSRF.hash = json.csrf;
          // sync hidden inputs if exist
          document.querySelectorAll('input[name="' + window.CSRF.name + '"]')
            .forEach(i => i.value = window.CSRF.hash);
        }
        if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
        fillForm(json.data);
        modal?.show();
      } catch (e) {
        alert(e.message || 'Terjadi kesalahan');
      }
    });

    // Delete (nama)
    document.querySelectorAll('.btn-delete').forEach(btn => btn.onclick = async (e) => {
      e.preventDefault();
      const url  = btn.dataset.url;
      const name = btn.dataset.name || (btn.closest('tr')?.children[2]?.textContent?.trim()) || 'pegawai';
      if (!url) return;
  
      const result = await Swal.fire({
        icon: 'warning',
        title: 'Hapus data?',
        html: `Pegawai <b>${escapeHtml(name)}</b> akan dihapus permanen.`,
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
      });
  
      if (result.isConfirmed) {
        // kirim form delete (full reload → swal_flash akan tampil)
        const f = document.getElementById('deleteForm');
        f?.setAttribute('action', url);
        f?.submit();
      }
    });
  }

  // ======= Events =======
  // live search (debounced)
  qInput && qInput.addEventListener('input', debounce(() => {
    state.q = qInput.value || '';
    state.page = 1;
    fetchList();
  }, 250));

  // per page change
  perSel && perSel.addEventListener('change', () => {
    state.perPage = parseInt(perSel.value || '10', 10);
    state.page = 1;
    fetchList();
  });

  // add
  btnAdd && btnAdd.addEventListener('click', () => {
    currentId = null;
    titleEl && (titleEl.textContent = 'Tambah Pegawai');
    form?.reset();
    const m = form?.querySelector('#_method');
    if (m) m.value = 'POST';
    clearErrors();
    modal?.show();
  });

  // submit create/update
  // submit create/update
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    const fd = new FormData(form);
    if (window.CSRF?.name && window.CSRF?.hash) {
      fd.set(window.CSRF.name, window.CSRF.hash);
    }

    let url = window.APP?.pegawai || '/pegawai';
    const isUpdate = form.querySelector('#_method')?.value === 'PUT' && currentId;
    if (isUpdate) {
      url = url + '/' + currentId;
      fd.set('_method', 'PUT');
    }

    try {
      const res  = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      const json = await res.json();

      if (json.csrf) {
        window.CSRF.hash = json.csrf;
        document.querySelectorAll('input[name="' + window.CSRF.name + '"]')
          .forEach(i => i.value = window.CSRF.hash);
      }

      if (!res.ok || !json.success) {
        if (json.errors) showErrors(json.errors);
        // tampilkan error general pakai Swal
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: json.message || 'Validasi gagal',
        });
        return;
      }

      modal?.hide();

      // Swal sukses (toast cepat)
      Swal.fire({
        icon: 'success',
        title: isUpdate ? 'Data berhasil diperbarui' : 'Data berhasil ditambahkan',
        timer: 1400,
        showConfirmButton: false,
        timerProgressBar: true
      });

      // refresh list di halaman yang sama
      fetchList();
    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: 'Kesalahan',
        text: err.message || 'Terjadi kesalahan',
      });
    }
  });


  // ======= init =======
  // bind existing server-rendered rows
  bindRowActions();
  // initial info
  if (infoEl) infoEl.textContent = '';

  // optional: first fetch to normalize pager under client control
  // (uncomment if you want to always start from AJAX version)
  // fetchList();
})();
