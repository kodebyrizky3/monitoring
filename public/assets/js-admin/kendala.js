(function(){
  'use strict';

  // ===== refs
  const infoEl   = document.getElementById('liveInfo');
  const tbody    = document.getElementById('kendTbody');
  const pagerEl  = document.getElementById('kendPager');
  const qInput   = document.getElementById('searchInput');
  const selMod   = document.getElementById('moduleSelect');
  const selStat  = document.getElementById('statusSelect');

  // modal
  const modalEl  = document.getElementById('kendDetailModal');
  const chipMod  = document.getElementById('chipModule');
  const chipType = document.getElementById('chipType');
  const metaEl   = document.getElementById('detailMeta');
  const btnApprove = document.getElementById('btnDetailApprove');
  const btnReject  = document.getElementById('btnDetailReject');
  let   bsModal  = null;

  // ===== state
  let state = { q:'', module:'SEMUA', status:'SEMUA', page:1, perPage:10, pageCount:1 };
  let inflightCtrl = null;
  let csrf = { name: window.KENDALA.csrfName, value: window.KENDALA.csrfValue };
  let detailCtx = { type:null, id:null }; // 'ticket' | 'service'

  // ===== utils
  function setInfo(t){ if (infoEl) infoEl.textContent = t || ''; }
  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
  function escapeHtml(s){
    s = String(s ?? '');
    s = s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
         .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    return s;
  }
  function rangeAround(cur, total, width){
    const half = Math.floor(width/2);
    let st = Math.max(1, cur - half);
    let en = Math.min(total, st + width - 1);
    st = Math.max(1, en - width + 1);
    const arr = [];
    for (let i=st; i<=en; i++) arr.push(i);
    return arr;
  }
  function typeLabel(t){
    const v = String(t||'').toLowerCase();
    if (v === 'service')   return 'Permintaan Perbaikan';
    if (v === 'perjalanan')return 'Perjalanan Dinas';
    return 'Laporan Kerusakan';
  }
  function typeIcon(t){
    const v = String(t||'').toLowerCase();
    if (v === 'service')    return 'bi-wrench';
    if (v === 'perjalanan') return 'bi-geo-alt';
    return 'bi-exclamation-triangle';
  }
  function moduleIcon(m){
    const v = String(m||'').toLowerCase();
    return (v === 'ac') ? 'bi-wind' : 'bi-car-front';
  }
  function badgeHtml(s){
    const map = { PENDING:'warning', APPROVED:'primary', REJECTED:'danger', DONE:'success' };
    const cls = map[s] || 'secondary';
    return `<span class="badge bg-${cls}">${escapeHtml(s||'-')}</span>`;
  }

  async function postJSON(url, payload={}){
    const body = new URLSearchParams();
    for (const [k,v] of Object.entries(payload)) body.append(k, v);
    body.append(csrf.name, csrf.value);

    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Accept':'application/json' },
      body
    });
    const json = await res.json().catch(()=>({success:false,message:'Respon bukan JSON'}));
    // refresh CSRF bila server kirim balik
    if (json && json.csrf) {
      csrf.value = json.csrf;
      const metaVal = document.querySelector('meta[name="csrf-token-value"]');
      if (metaVal) metaVal.setAttribute('content', csrf.value);
    }
    if (!res.ok || !json.success) throw new Error(json.message || ('HTTP '+res.status));
    return json;
  }

  // ===== fetch & render LIST
  async function fetchList(){
    inflightCtrl?.abort?.();
    inflightCtrl = new AbortController();

    try {
      setInfo('Memuat…');
      const u = new URL(window.KENDALA.searchUrl, window.location.origin);
      u.searchParams.set('q', state.q);
      u.searchParams.set('module', state.module);
      u.searchParams.set('status', state.status);
      u.searchParams.set('page', String(state.page));
      u.searchParams.set('perPage', String(state.perPage));

      const res  = await fetch(u.toString(), { headers:{'Accept':'application/json'}, signal: inflightCtrl.signal, cache:'no-store' });
      const json = await res.json().catch(()=>({success:false,message:'Respon bukan JSON'}));
      if (!res.ok || !json.success) throw new Error(json.message || ('HTTP '+res.status));

      renderRows(json.rows || []);
      renderPager(json.page || 1, json.pageCount || 1);
      setInfo(`Menampilkan ${(json.rows||[]).length} dari ${json.total||0}`);
      if (json.csrf) { csrf.value = json.csrf; }
    } catch (err) {
      console.error(err);
      renderRows([]);
      renderPager(1, 1);
      setInfo('Gagal memuat: ' + (err.message || 'Unknown error'));
    }
  }

  function actionButtons(row){
    // CUMA "Detail" di tabel — approve/reject di modal
    const type = (row.item_type || '').toLowerCase(); // 'ticket' | 'service' | 'perjalanan'
    const id   = row.item_id;
    return `
      <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-act="detail" data-type="${escapeHtml(type)}" data-id="${escapeHtml(id)}">
          <i class="bi bi-eye"></i> Detail
        </button>
      </div>
    `;
  }

  function renderRows(rows){
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td class="text-nowrap"><i class="bi ${moduleIcon(r.module)} me-1"></i>${escapeHtml(r.module)}</td>
        <td class="text-nowrap"><span class="type-chip"><i class="bi ${typeIcon(r.item_type)}"></i>${escapeHtml(typeLabel(r.item_type))}</span></td>
        <td>${escapeHtml(r.subject || '-')}</td>
        <td>${badgeHtml(r.status_norm)}</td>
        <td class="text-nowrap small">${escapeHtml(r.created_at || '')}</td>
        <td>${actionButtons(r)}</td>
      </tr>
    `).join('');
  }

  function renderPager(page, pageCount){
    if (!pagerEl) return;
    state.page = page; state.pageCount = pageCount;
    if (pageCount <= 1) { pagerEl.innerHTML=''; return; }
    const li = (label, goto, disabled=false, active=false) =>
      `<li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
         <a class="page-link" href="#" data-page="${goto}">${label}</a>
       </li>`;
    let html = `<ul class="pagination mb-0 justify-content-end">`;
    html += li('&laquo;', Math.max(1, page-1), page===1, false);
    rangeAround(page, pageCount, 5).forEach(p => html += li(String(p), p, false, p===page));
    html += li('&raquo;', Math.min(pageCount, page+1), page===pageCount, false);
    html += `</ul>`;
    pagerEl.innerHTML = html;

    pagerEl.querySelectorAll('a.page-link').forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      const p = parseInt(a.getAttribute('data-page'), 10);
      if (!isNaN(p) && p>=1 && p<=state.pageCount && p!==state.page) { state.page = p; fetchList(); }
    }));
  }

  // ===== DETAIL + Modal
  tbody?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act="detail"]');
    if (!btn) return;
    await openDetail(btn.getAttribute('data-type'), btn.getAttribute('data-id'));
  });

  async function openDetail(type, id){
    setInfo('Memuat detail…');
    try {
      let url;
      if (type === 'service') url = window.KENDALA.detailServiceUrl(id);
      else                    url = window.KENDALA.detailTicketUrl(id); // default: ticket/kerusakan

      const res  = await fetch(url, { headers:{'Accept':'application/json'}, cache:'no-store' });
      const json = await res.json().catch(()=>({success:false,message:'Respon bukan JSON'}));
      if (!res.ok || !json.success) throw new Error(json.message || ('HTTP '+res.status));

      detailCtx = { type: (json.type || type || 'ticket'), id };
      renderDetail(json);
      if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
      bsModal.show();
      setInfo('');
    } catch (err) {
      console.error(err);
      setInfo('Gagal memuat detail: ' + (err.message || 'Unknown error'));
    }
  }

  function badgeHtmlStatus(s){
    const map = { PENDING:'warning', APPROVED:'primary', REJECTED:'danger', DONE:'success' };
    const cls = map[s] || 'secondary';
    return `<span class="badge bg-${cls}">${escapeHtml(s||'-')}</span>`;
  }

  function renderDetail(payload){
    const body  = document.getElementById('kendDetailBody');
    const d     = payload.data || {};
    const moduleName = payload.module || 'kendaraan';
    const typeName   = payload.type   || 'ticket';

    chipMod.innerHTML  = `<i class="bi ${moduleIcon(moduleName)}"></i> ${escapeHtml(moduleName)}`;
    chipType.innerHTML = `<i class="bi ${typeIcon(typeName)}"></i> ${escapeHtml(typeLabel(typeName))}`;
    metaEl.textContent = `Dibuat ${d.created_at || '-'} • Diupdate ${d.updated_at || '-'}`;

    const photos = (d.photos || []).filter(p => p && p.url);

    body.innerHTML = `
      <div class="row g-3">
        <div class="col-12 col-lg-7">
          <dl class="row dl-compact">
            ${d.kendaraan ? `<dt class="col-sm-4">Kendaraan</dt><dd class="col-sm-8">${escapeHtml(d.kendaraan)}</dd>` : ''}
            ${d.pelapor   ? `<dt class="col-sm-4">Pelapor</dt><dd class="col-sm-8">${escapeHtml(d.pelapor)}</dd>` : ''}
            ${d.pemohon   ? `<dt class="col-sm-4">Pemohon</dt><dd class="col-sm-8">${escapeHtml(d.pemohon)}</dd>` : ''}
            ${d.subject   ? `<dt class="col-sm-4">Subject</dt><dd class="col-sm-8">${escapeHtml(d.subject)}</dd>` : ''}
            <dt class="col-sm-4">Detail</dt><dd class="col-sm-8"><pre class="mb-0" style="white-space:pre-wrap">${escapeHtml(d.detail || '-')}</pre></dd>
            <dt class="col-sm-4">Status</dt><dd class="col-sm-8">${badgeHtmlStatus(d.status_norm)}</dd>
          </dl>
        </div>
        <div class="col-12 col-lg-5">
          <div class="row g-2">
            ${
              photos.length
                ? photos.map(p => `
                  <div class="col-6">
                    <a href="${p.url}" target="_blank" class="d-block border rounded overflow-hidden">
                      <img src="${p.url}" class="u-photo" alt="${escapeHtml(p.caption || 'Foto')}">
                    </a>
                    ${p.caption ? `<div class="small text-muted mt-1">${escapeHtml(p.caption)}</div>` : ''}
                  </div>
                `).join('')
                : `<div class="text-muted">Tidak ada foto terlampir.</div>`
            }
          </div>
        </div>
      </div>
    `;

    const canAct = (String(d.status_norm).toUpperCase() === 'PENDING');
    btnApprove.disabled = !canAct;
    btnReject.disabled  = !canAct;
  }

  btnApprove?.addEventListener('click', async () => {
    if (!detailCtx.id) return;
    if (!confirm('Setujui laporan ini?')) return;
    await doAction(detailCtx.type, 'approve', detailCtx.id);
    fetchList();
    bsModal?.hide();
  });
  btnReject?.addEventListener('click', async () => {
    if (!detailCtx.id) return;
    if (!confirm('Tolak laporan ini?')) return;
    await doAction(detailCtx.type, 'reject', detailCtx.id);
    fetchList();
    bsModal?.hide();
  });

  async function doAction(type, act, id){
    let url;
    if (act === 'approve') {
      url = (type === 'service') ? window.KENDALA.approveServiceUrl(id) : window.KENDALA.approveTicketUrl(id);
    } else {
      url = (type === 'service') ? window.KENDALA.rejectServiceUrl(id)  : window.KENDALA.rejectTicketUrl(id);
    }
    setInfo('Memproses…');
    await postJSON(url, {}); // POST + CSRF
    setInfo('Berhasil diperbarui.');
  }

  // ===== events & init
  qInput?.addEventListener('input', debounce(() => { state.q = qInput.value || ''; state.page=1; fetchList(); }, 250));
  selMod?.addEventListener('change', () => { state.module = selMod.value || 'SEMUA'; state.page=1; fetchList(); });
  selStat?.addEventListener('change', () => { state.status = selStat.value || 'SEMUA'; state.page=1; fetchList(); });

  fetchList();
})();
