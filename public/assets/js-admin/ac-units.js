// public/assets/js-admin/ac-units.js  (v1.2.1)
(function(){
  'use strict';

  const tbody   = document.getElementById('acTbody');
  const pagerEl = document.getElementById('acPager');
  const totalEl = document.getElementById('acTotal');
  const infoEl  = document.getElementById('liveInfo');

  const qInput  = document.getElementById('qInput');
  const stSel   = document.getElementById('statusSelect');
  const perSel  = document.getElementById('perPageSelect');

  let inflightController = null;
  let reqSeq = 0;

  const state = {
    q: (qInput?.value || ''),
    status: (stSel?.value || ''),
    perPage: parseInt(perSel?.value || '10', 10),
    page: 1,
    pageCount: 1
  };

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function debounce(fn,ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
  function pageRange(cur,total,width){ const h=Math.floor(width/2); let s=Math.max(1,cur-h); let e=Math.min(total,s+width-1); s=Math.max(1,e-width+1); const arr=[]; for(let i=s;i<=e;i++) arr.push(i); return arr; }

  function setInfoLoading(){ if(infoEl) infoEl.textContent='Memuat…'; }
  function setInfoCount(rowsLen,total,page,perPage){
    if(!infoEl){return;}
    if(!total){ infoEl.textContent=''; return; }
    const start = rowsLen ? ((page-1)*perPage + 1) : 0;
    const end   = rowsLen ? ((page-1)*perPage + rowsLen) : 0;
    infoEl.textContent = `Menampilkan ${start}–${end} dari ${total}` + (state.q? ' · Urut: terbaru':'');
  }

  function renderPager(){
    if(!pagerEl){ return; }
    const p = state.page, n = state.pageCount;
    if(n <= 1){ pagerEl.innerHTML = ''; return; }

    const btn = (label,page,disabled=false,active=false)=>
      `<li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
         <a class="page-link" href="#" data-page="${page}">${label}</a>
       </li>`;
    let html = `<ul class="pagination mb-0 justify-content-end">`;
    html += btn('&laquo;', Math.max(1,p-1), p===1);
    pageRange(p,n,5).forEach(pg=>{ html += btn(pg, pg, false, pg===p); });
    html += btn('&raquo;', Math.min(n,p+1), p===n);
    html += `</ul>`;
    pagerEl.innerHTML = html;

    pagerEl.querySelectorAll('a.page-link').forEach(a=>{
      a.addEventListener('click', e=>{
        e.preventDefault();
        const pg = parseInt(a.dataset.page,10);
        if(!isNaN(pg) && pg>=1 && pg<=state.pageCount && pg!==state.page){
          state.page = pg;
          fetchList();
        }
      });
    });
  }

  function bindDelete(){
    document.querySelectorAll('.btn-delete').forEach(btn=>{
      btn.onclick = async (e)=>{
        e.preventDefault();
        const url  = btn.dataset.url;
        const name = btn.dataset.name || 'perangkat';
        if(!url) return;

        const res = await Swal.fire({
          icon: 'warning',
          title: 'Hapus data?',
          html: `AC <b>${escapeHtml(name)}</b> akan dihapus beserta riwayat, foto & QR-nya.`,
          showCancelButton: true,
          confirmButtonText: 'Ya, hapus',
          cancelButtonText: 'Batal',
          reverseButtons: true
        });
        if(!res.isConfirmed) return;

        const form = document.getElementById('deleteForm');
        form.setAttribute('action', url);
        form.submit(); // reload → flash ok/err ditangani di showFlash()
      };
    });
  }

  async function fetchList(){
    const mySeq = ++reqSeq;
    inflightController?.abort();
    inflightController = new AbortController();

    const u = new URL(window.APP?.acSearch || '/admin/data-alat/ac/search', window.location.origin);
    u.searchParams.set('q', state.q || '');
    u.searchParams.set('status', state.status || '');
    u.searchParams.set('perPage', String(state.perPage || 10));
    u.searchParams.set('page', String(state.page || 1));

    setInfoLoading();

    try{
      const res  = await fetch(u.toString(), { headers:{'Accept':'application/json'}, signal:inflightController.signal, cache:'no-store' });
      const json = await res.json();
      if(mySeq !== reqSeq) return;
      if(!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat');

      const rows = json.rows || [];
      const total= parseInt(json.total ?? 0,10);
      state.pageCount = parseInt(json.pageCount ?? 1,10) || 1;
      if(state.page > state.pageCount){ state.page = state.pageCount; return fetchList(); }

      if(totalEl) totalEl.textContent = total;

      const badge = (st)=>{
        const m = {'NORMAL':'success','MENUNGGU_PERBAIKAN':'warning','DALAM_PERBAIKAN':'info'};
        return `<span class="badge bg-${m[st]||'secondary'}">${escapeHtml(st||'')}</span>`;
      };

      tbody.innerHTML = rows.length ? rows.map(r=>`
        <tr>
          <td>${r.id}</td>
          <td><code>${escapeHtml(r.kode_qr)}</code></td>
          <td>${escapeHtml(r.nomor_unik)}</td>
          <td>${escapeHtml(r.tipe_model)}</td>
          <td>${escapeHtml(r.lokasi)}</td>
          <td>${badge(r.status_ac)}</td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a class="btn btn-outline-secondary" href="${r.show_url}" title="Detail"><i class="bi bi-eye"></i></a>
              <a class="btn btn-outline-primary"   href="${r.edit_url}" title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-outline-success"   href="${r.dl_qr_url}" title="Unduh QR"><i class="bi bi-download"></i></a>
              <button class="btn btn-outline-danger btn-delete" data-url="${r.del_url}" data-name="${escapeHtml(r.nomor_unik)}" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>
      `).join('') : `<tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>`;

      renderPager();
      bindDelete();
      setInfoCount(rows.length, total, state.page, state.perPage);

      // UPDATE CARDS
      if (json.counters) {
        const c = json.counters;
        const elTotal  = document.getElementById('statTotal');
        const elWait   = document.getElementById('statWait');
        const elDoing  = document.getElementById('statDoing');
        const elNormal = document.getElementById('statNormal');
        if (elTotal)  elTotal.textContent  = c.total ?? 0;
        if (elWait)   elWait.textContent   = c.wait ?? 0;
        if (elDoing)  elDoing.textContent  = c.doing ?? 0;
        if (elNormal) elNormal.textContent = c.normal ?? 0;
      }

    }catch(err){
      if(err.name==='AbortError') return;
      if(infoEl) infoEl.textContent = err.message || 'Gagal memuat';
    }
  }

  // Events
  qInput && qInput.addEventListener('input', debounce(()=>{ state.q=qInput.value||''; state.page=1; fetchList(); }, 250));
  stSel  && stSel.addEventListener('change', ()=>{ state.status=stSel.value||''; state.page=1; fetchList(); });
  perSel && perSel.addEventListener('change', ()=>{ state.perPage=parseInt(perSel.value||'10',10); state.page=1; fetchList(); });

  // Init
  bindDelete();
  if(infoEl) infoEl.textContent='';

  // SweetAlert flash (tambah/edit/hapus)
  (function showFlash(){
    const ok  = window.APP?.flash?.ok  || '';
    const err = window.APP?.flash?.err || '';
    if (ok) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: ok,
        timer: 1600,
        showConfirmButton: false,
        timerProgressBar: true
      });
    } else if (err) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: err
      });
    }
  })();

  // Optional: aktifkan agar pager & tabel full via AJAX dari awal
  // fetchList();
})();
