(function () {
    'use strict';
    console.log('[bidang.js] v1.1.3');
  
    // ====== Modal helper ======
    var modalEl = document.getElementById('bdgModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    function ensureModal() {
      if (!modal) {
        var el = document.getElementById('bdgModal');
        if (el && window.bootstrap && window.bootstrap.Modal) modal = new bootstrap.Modal(el);
      }
      return modal;
    }
  
    // ====== DOM refs ======
    var form    = document.getElementById('bdgForm');
    var titleEl = document.getElementById('bdgModalTitle');
    var tbody   = document.getElementById('bdgTbody');
    var totalEl = document.getElementById('bdgTotal');
    var infoEl  = document.getElementById('liveInfo');
    var pagerEl = document.getElementById('bdgPager');
  
    var qInput  = document.getElementById('qInput');
    var perSel  = document.getElementById('perPageSelect');
    var btnAdd  = document.getElementById('btnAdd');
  
    var currentId = null;
    var formMode  = 'create'; // 'create' | 'edit'
  
    var state = {
      q: (qInput && qInput.value) || '',
      perPage: parseInt((perSel && perSel.value) || '10', 10),
      page: 1,
      pageCount: 1
    };
  
    var inflightController = null, reqSeq = 0;
  
    // ====== Utils ======
    function debounce(fn, ms){ var t; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); }, ms); }; }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function pageRange(cur, tot, w){ var h=Math.floor(w/2); var st=Math.max(1,cur-h), en=Math.min(tot,st+w-1); st=Math.max(1,en-w+1); var arr=[]; for(var i=st;i<=en;i++) arr.push(i); return arr; }
    function setInfoLoading(){ if(infoEl) infoEl.textContent='Memuat…'; }
    function setInfoCount(rowsLen){
      if(!infoEl) return;
      if(!rowsLen){ infoEl.textContent=''; return; }
      var start = ((state.page-1)*state.perPage)+1;
      var end   = ((state.page-1)*state.perPage)+rowsLen;
      infoEl.textContent = 'Menampilkan '+start+'–'+end + (state.q ? ' · Urut: terbaru' : '');
    }
    function syncAllCsrfInputs(){
      if(!window.CSRF || !window.CSRF.name || !window.CSRF.hash) return;
      document.querySelectorAll('input[name="'+window.CSRF.name+'"]').forEach(function(i){ i.value=window.CSRF.hash; });
    }
    function handleCsrfFromJson(json){ if(json && json.csrf){ window.CSRF.hash = json.csrf; syncAllCsrfInputs(); } }
  
    // Header AJAX wajib supaya CI4 -> isAJAX() = true
    var AJAX_HEADERS = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  
    // baca JSON aman (antisipasi HTML redirect)
    async function getJson(url, init) {
      init = init || {};
      // merge header default AJAX
      init.headers = Object.assign({}, AJAX_HEADERS, init.headers || {});
      if (!('cache' in init)) init.cache = 'no-store';
  
      var res = await fetch(url, init);
      var text = await res.text();
      try { return { res: res, json: JSON.parse(text) }; }
      catch(e){
        console.error('[bidang.js] response NOT JSON from', url, '\n', text.slice(0,400));
        throw new Error('Respon server bukan JSON. Cek route/redirect/CSRF.');
      }
    }
  
    // ====== List loader ======
    async function fetchList(){
      var mySeq = ++reqSeq;
      if (inflightController) inflightController.abort();
      inflightController = new AbortController();
      if(state.page < 1) state.page = 1;
  
      var base = (window.APP && window.APP.bidangSearch) || '/admin/bidang/search';
      var u = new URL(base, window.location.origin);
      u.searchParams.set('q', state.q || '');
      u.searchParams.set('perPage', String(state.perPage || 10));
      u.searchParams.set('page', String(state.page || 1));
  
      setInfoLoading();
  
      try{
        var r = await getJson(u.toString(), { signal: inflightController.signal });
        var res = r.res, json = r.json;
        handleCsrfFromJson(json);
        if (mySeq !== reqSeq) return;
        if(!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
  
        state.pageCount = parseInt(json.pageCount != null ? json.pageCount : 1, 10) || 1;
        if (state.page > state.pageCount){ state.page = state.pageCount; return fetchList(); }
  
        var rows = json.rows || [];
        tbody.innerHTML = rows.length ? rows.map(function(r,i){
          var no = ((state.page-1)*state.perPage) + (i+1);
          var delUrl = (((window.APP&&window.APP.bidang)||"/admin/bidang")+'/'+r.id+'/delete');
          return '\
            <tr>\
              <td>'+no+'</td>\
              <td>'+escapeHtml(r.nama)+'</td>\
              <td class="text-end">\
                <div class="btn-group btn-group-sm">\
                  <button type="button" class="btn btn-outline-primary btn-edit" data-id="'+r.id+'"><i class="bi bi-pencil"></i></button>\
                  <button type="button" class="btn btn-outline-danger btn-delete" data-id="'+r.id+'" data-url="'+delUrl+'" data-name="'+escapeHtml(r.nama)+'"><i class="bi bi-trash"></i></button>\
                </div>\
              </td>\
            </tr>';
        }).join('') : '<tr><td colspan="3" class="text-center text-muted">Tidak ada data.</td></tr>';
  
        if (totalEl) totalEl.textContent = parseInt(json.total != null ? json.total : 0, 10);
        renderPager();
        setInfoCount(rows.length);
      }catch(e){
        console.error(e);
        if(e.name==='AbortError') return;
        if(infoEl) infoEl.textContent = e.message || 'Gagal memuat';
      }
    }
  
    function renderPager(){
      if(!pagerEl) return;
      var p=state.page, n=state.pageCount;
      if(n<=1){ pagerEl.innerHTML=''; return; }
      var html = '<ul class="pagination mb-0 justify-content-end">';
      var btn = function(label, page, dis, act){
        return '<li class="page-item '+(dis?'disabled':'')+' '+(act?'active':'')+'"><a class="page-link" href="#" data-page="'+page+'">'+label+'</a></li>';
      };
      html += btn('&laquo;', Math.max(1,p-1), p===1, false);
      pageRange(p,n,5).forEach(function(pg){ html += btn(pg,pg,false,pg===p); });
      html += btn('&raquo;', Math.min(n,p+1), p===n, false);
      html += '</ul>';
      pagerEl.innerHTML = html;
      pagerEl.querySelectorAll('a.page-link').forEach(function(a){
        a.addEventListener('click', function(e){
          e.preventDefault();
          var pg = parseInt(a.getAttribute('data-page'),10);
          if(!isNaN(pg) && pg>=1 && pg<=state.pageCount && pg!==state.page){ state.page=pg; fetchList(); }
        });
      });
    }
  
    function clearErrors(){
      if(!form) return;
      form.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
      form.querySelectorAll('[data-err]').forEach(function(el){ el.textContent=''; });
    }
    function showErrors(errs){
      if(!form) return;
      Object.keys(errs||{}).forEach(function(k){
        var v = errs[k];
        var i = form.querySelector('[name="'+k+'"]');
        var h = form.querySelector('[data-err="'+k+'"]');
        if(i) i.classList.add('is-invalid');
        if(h) h.textContent = v;
      });
    }
  
    // ====== Delegation: Edit & Hapus ======
    if (tbody) {
      tbody.addEventListener('click', async function(e){
        var editBtn = e.target.closest('.btn-edit');
        var delBtn  = e.target.closest('.btn-delete');
  
        // EDIT
        if (editBtn) {
          currentId = editBtn.getAttribute('data-id');
          formMode  = 'edit';
          if (titleEl) titleEl.textContent = 'Edit Bidang';
          clearErrors();
          try{
            var url = (((window.APP && window.APP.bidang) || '/admin/bidang') + '/' + currentId);
            var r = await getJson(url); // headers + X-Requested-With otomatis
            var res = r.res, json = r.json;
            handleCsrfFromJson(json);
            if(!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
            var nama = (json.data && json.data.nama) || '';
            var inp = form && form.querySelector('[name="nama"]'); if(inp) inp.value = nama;
            var mInst = ensureModal(); if (mInst) mInst.show();
          }catch(err){
            console.error(err);
            if(window.Swal) Swal.fire({icon:'error',title:'Gagal',text:err.message||'Terjadi kesalahan'}); else alert(err.message||'Terjadi kesalahan');
          }
          return;
        }
  
        // HAPUS
        if (delBtn) {
          e.preventDefault();
          var url  = delBtn.getAttribute('data-url'); // /{id}/delete
          var name = delBtn.getAttribute('data-name') || 'bidang';
          if(!url) return;
  
          try{
            var ok = true;
            if (window.Swal) {
              var result = await Swal.fire({
                icon:'warning',
                title:'Hapus data?',
                html:'Bidang <b>'+escapeHtml(name)+'</b> akan dihapus.',
                showCancelButton:true,
                confirmButtonText:'Ya, hapus',
                cancelButtonText:'Batal',
                reverseButtons:true
              });
              ok = !!result.isConfirmed;
            } else {
              ok = confirm('Hapus data "'+name+'"?');
            }
            if(!ok) return;
  
            // gunakan form POST khusus delete (server-side redirect/flash)
            var f = document.getElementById('deleteForm');
            if(!f) return alert('Form hapus tidak ditemukan.');
            f.setAttribute('action', url);
  
            if(window.CSRF && window.CSRF.name && window.CSRF.hash){
              var tokenInput = f.querySelector('input[name="'+window.CSRF.name+'"]');
              if(!tokenInput){
                tokenInput = document.createElement('input');
                tokenInput.type='hidden';
                tokenInput.name=window.CSRF.name;
                f.appendChild(tokenInput);
              }
              tokenInput.value = window.CSRF.hash;
            }
            f.submit();
          }catch(err){
            console.error(err);
            if(window.Swal) Swal.fire({icon:'error',title:'Gagal',text:err.message||'Terjadi kesalahan'}); else alert(err.message||'Terjadi kesalahan');
          }
        }
      });
    }
  
    // ====== Filter & perPage ======
    if (qInput) qInput.addEventListener('input', debounce(function(){ state.q=qInput.value||''; state.page=1; fetchList(); }, 250));
    if (perSel) perSel.addEventListener('change', function(){ state.perPage=parseInt(perSel.value||'10',10); state.page=1; fetchList(); });
  
    // ====== Tambah ======
    if (btnAdd) btnAdd.addEventListener('click', function(){
      currentId = null;
      formMode  = 'create';
      if (titleEl) titleEl.textContent = 'Tambah Bidang';
      if (form) form.reset();
      clearErrors();
      var mInst = ensureModal(); if (mInst) mInst.show();
    });
  
    // ====== Submit (Tambah/Update) ======
    if (form) form.addEventListener('submit', async function(e){
      e.preventDefault();
      clearErrors();
  
      var fd = new FormData(form);
      // pastikan tidak ada _method tersisa
      fd.delete('_method');
      if(window.CSRF && window.CSRF.name && window.CSRF.hash) fd.set(window.CSRF.name, window.CSRF.hash);
  
      var base = (window.APP && window.APP.bidang) || '/admin/bidang';
      var url  = (formMode === 'edit' && currentId) ? (base + '/' + currentId + '/save') : base;
  
      try{
        var r = await getJson(url, { method:'POST', body: fd });
        var res = r.res, json = r.json;
        handleCsrfFromJson(json);
  
        if(!res.ok || !json.success){
          if(json.errors) showErrors(json.errors);
          if(window.Swal) Swal.fire({icon:'error',title:'Gagal',text:json.message||'Validasi gagal'}); else alert(json.message||'Validasi gagal');
          return;
        }
  
        var mInst = ensureModal(); if (mInst) mInst.hide();
        if(window.Swal) Swal.fire({icon:'success',title: (formMode==='edit')?'Data diperbarui':'Data ditambahkan',timer:1200,showConfirmButton:false,timerProgressBar:true});
        fetchList();
      }catch(err){
        console.error(err);
        if(window.Swal) Swal.fire({icon:'error',title:'Kesalahan',text:err.message||'Terjadi kesalahan'}); else alert(err.message||'Terjadi kesalahan');
      }
    });
  
    // ====== init ======
    (function init(){
      if(infoEl) infoEl.textContent = '';
      syncAllCsrfInputs();
      fetchList();
    })();
  
  })();  