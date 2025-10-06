/* global bootstrap, Swal */
// public/assets/js-admin/kendaraan-units.js (v2.0.1 - compat, no optional chaining / nullish)

(function () {
  'use strict';

  // ======= refs =======
  var modalEl = document.getElementById('kendaraanModal');
  var modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
  var form    = document.getElementById('kendaraanForm');
  var titleEl = document.getElementById('kendaraanModalTitle');

  var tbody   = document.getElementById('kendTbody');
  var infoEl  = document.getElementById('liveInfo');
  var pagerEl = document.getElementById('kendPager');
  var qInput  = document.getElementById('qInput');
  var perSel  = document.getElementById('perPageSelect');
  var btnAdd  = document.getElementById('btnAdd');

  // optional filter selects
  var tipeSel   = document.querySelector('select[name="tipe"]');
  var statusSel = document.querySelector('select[name="status_kendaraan"]');

  // ======= state =======
  var currentId = null;
  var state = {
    q: (qInput && qInput.value) || '',
    tipe: (tipeSel && tipeSel.value) || '',
    status: (statusSel && statusSel.value) || '',
    perPage: parseInt((perSel && perSel.value) || '10', 10),
    page: 1,
    pageCount: 1
  };

  // in-flight guard
  var inflightController = null;
  var reqSeq = 0;

  // ======= utils =======
  function debounce(fn, ms){ var t; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); }, ms); }; }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); }); }
  function pageRange(current, total, width){ var half=Math.floor(width/2); var st=Math.max(1,current-half); var en=Math.min(total, st+width-1); st=Math.max(1,en-width+1); var arr=[]; for(var i=st;i<=en;i++) arr.push(i); return arr; }
  function setInfoLoading(){ if (infoEl) infoEl.textContent = 'Memuat…'; }
  function setInfoCount(rowsLen, total, page, perPage){
    if (!infoEl) return;
    if (!total) { infoEl.textContent=''; return; }
    var start = rowsLen ? ((page - 1) * perPage + 1) : 0;
    var end   = rowsLen ? ((page - 1) * perPage + rowsLen) : 0;
    infoEl.textContent = 'Menampilkan ' + start + '–' + end + ' dari ' + total + (state.q ? ' · Urut: relevansi' : '');
  }

  // ======= fetch & render list =======
  function fetchList() {
    var mySeq = ++reqSeq;
    if (inflightController && inflightController.abort) inflightController.abort();
    inflightController = new AbortController();

    var u = new URL((window.APP && window.APP.kendaraanSearch) || '/kendaraan/search', window.location.origin);
    u.searchParams.set('q', state.q || '');
    u.searchParams.set('tipe', state.tipe || '');
    u.searchParams.set('status_kendaraan', state.status || '');
    u.searchParams.set('perPage', String(state.perPage || 10));
    u.searchParams.set('page', String(state.page || 1));

    setInfoLoading();

    fetch(u.toString(), { headers:{'Accept':'application/json'}, signal: inflightController.signal, cache:'no-store' })
      .then(function(res){ return res.json().then(function(json){ return {res:res, json:json}; }); })
      .then(function(pack){
        if (mySeq !== reqSeq) return;
        var res = pack.res, json = pack.json;

        if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');

        // sync CSRF
        if (json.csrf && window.CSRF) {
          window.CSRF.hash = json.csrf;
          var inputs = document.querySelectorAll('input[name="'+window.CSRF.name+'"]');
          for (var i=0; i<inputs.length; i++) inputs[i].value = window.CSRF.hash;
        }

        var total     = (json.total != null ? parseInt(json.total,10) : 0);
        var pageCount = (json.pageCount != null ? parseInt(json.pageCount,10) : 1) || 1;
        state.pageCount = pageCount;
        if (state.page > pageCount) { state.page = pageCount; fetchList(); return; }

        var rows = json.rows || [];
        if (tbody) {
          if (rows.length) {
            tbody.innerHTML = rows.map(function(r){
              var tahunTxt = (r.tahun != null ? r.tahun : '');
              var odoTxt   = (r.odometer_terakhir != null ? r.odometer_terakhir : 0);
              return (
                '<tr>' +
                  '<td>'+ r.id +'</td>' +
                  '<td><code>'+ escapeHtml(r.no_polisi) +'</code></td>' +
                  '<td>'+ escapeHtml(r.merk_model) +'</td>' +
                  '<td>'+ escapeHtml(r.tipe) +'</td>' +
                  '<td>'+ tahunTxt +'</td>' +
                  '<td>'+ Number(odoTxt).toLocaleString('id-ID') +'</td>' +
                  '<td>'+ renderStatusBadge(r.status_kendaraan) +'</td>' +
                  '<td class="text-end">' +
                    '<div class="btn-group btn-group-sm">' +
                      '<button class="btn btn-outline-primary btn-edit" data-id="'+ r.id +'"><i class="bi bi-pencil"></i></button>' +
                      '<button class="btn btn-outline-danger btn-delete" data-id="'+ r.id +'" data-url="'+ escapeHtml(r.delete_url) +'" data-name="'+ escapeHtml(r.no_polisi) +'"><i class="bi bi-trash"></i></button>' +
                    '</div>' +
                  '</td>' +
                '</tr>'
              );
            }).join('');
          } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>';
          }
        }

        renderPager();
        bindRowActions();
        setInfoCount(rows.length, total, state.page, state.perPage);
      })
      .catch(function(e){
        if (e.name === 'AbortError') return;
        if (infoEl) infoEl.textContent = e.message || 'Gagal memuat';
      });
  }

  function renderStatusBadge(s) {
    var map = { 'SIAP':'success', 'DIPAKAI_DINAS':'warning', 'DI_BENGKEL':'danger', 'MENUNGGU_PERBAIKAN':'secondary' };
    var c = map[s] || 'secondary';
    return '<span class="badge bg-' + c + '">' + escapeHtml(s || '') + '</span>';
  }

  function renderPager() {
    if (!pagerEl) return;
    var p=state.page, n=state.pageCount;
    if (n <= 1) { pagerEl.innerHTML=''; return; }
    var html = '<ul class="pagination mb-0 justify-content-end">';
    var btn = function(label, page, disabled, active){
      return '<li class="page-item '+(disabled?'disabled':'')+' '+(active?'active':'')+'">'+
               '<a class="page-link" href="#" data-page="'+page+'">'+label+'</a>'+
             '</li>';
    };
    html += btn('&laquo;', Math.max(1, p-1), p===1, false);
    pageRange(p,n,5).forEach(function(pg){ html += btn(pg, pg, false, pg===p); });
    html += btn('&raquo;', Math.min(n, p+1), p===n, false);
    html += '</ul>';
    pagerEl.innerHTML = html;
    var links = pagerEl.querySelectorAll('a.page-link');
    for (var i=0;i<links.length;i++){
      links[i].addEventListener('click', function(e){
        e.preventDefault();
        var pg = parseInt(this.dataset.page,10);
        if (!isNaN(pg) && pg>=1 && pg<=state.pageCount && pg!==state.page) { state.page = pg; fetchList(); }
      });
    }
  }

  // ======= CRUD helpers =======
  function clearErrors(){
    if (!form) return;
    var inv = form.querySelectorAll('.is-invalid');
    for (var i=0;i<inv.length;i++) inv[i].classList.remove('is-invalid');
    var helps = form.querySelectorAll('[data-err]');
    for (var j=0;j<helps.length;j++) helps[j].textContent = '';
  }
  function showErrors(errs){
    if (!form) return;
    Object.keys(errs || {}).forEach(function(k){
      var i = form.querySelector('[name="'+k+'"]');
      var h = form.querySelector('[data-err="'+k+'"]');
      if (i) i.classList.add('is-invalid');
      if (h) h.textContent = errs[k];
    });
  }
  function fillForm(d){
    if (!form) return;
    ['no_polisi','merk_model','tipe','tahun','odometer_terakhir','status_kendaraan','catatan'].forEach(function(k){
      var el=form.querySelector('[name="'+k+'"]');
      if (el) el.value = (d && d[k] !== undefined && d[k] !== null) ? d[k] : '';
    });
  }

  function bindRowActions() {
    // Edit
    var edits = document.querySelectorAll('.btn-edit');
    for (var i=0;i<edits.length;i++){
      edits[i].onclick = function(){
        currentId = this.dataset.id;
        if (!currentId) return;
        if (titleEl) titleEl.textContent = 'Edit Kendaraan #' + currentId;
        var m = form && form.querySelector('#_method'); if (m) m.setAttribute('value','PUT');
        clearErrors();
        var url = ((window.APP && window.APP.kendaraan) || '/kendaraan') + '/' + currentId;
        fetch(url, { headers: { 'Accept':'application/json' }, cache:'no-store' })
          .then(function(res){ return res.json().then(function(json){ return {res:res, json:json}; }); })
          .then(function(pack){
            var res=pack.res, json=pack.json;
            if (json.csrf && window.CSRF) {
              window.CSRF.hash = json.csrf;
              var inputs = document.querySelectorAll('input[name="'+window.CSRF.name+'"]');
              for (var i=0;i<inputs.length;i++) inputs[i].value = window.CSRF.hash;
            }
            if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
            fillForm(json.data);
            if (modal) modal.show();
          })
          .catch(function(e){
            Swal.fire({ icon:'error', title:'Gagal', text: e.message || 'Terjadi kesalahan' });
          });
      };
    }

    // Delete (SweetAlert)
    var dels = document.querySelectorAll('.btn-delete');
    for (var j=0;j<dels.length;j++){
      dels[j].onclick = function(e){
        e.preventDefault();
        var url  = this.dataset.url;
        var name = this.dataset.name || ('kendaraan #' + this.dataset.id);
        if (!url) return;

        Swal.fire({
          icon: 'warning',
          title: 'Hapus data?',
          html: 'Kendaraan <b>'+ escapeHtml(name) +'</b> akan dihapus permanen.',
          showCancelButton: true,
          confirmButtonText: 'Ya, hapus',
          cancelButtonText: 'Batal',
          reverseButtons: true
        }).then(function(result){
          if (!result.isConfirmed) return;

          var fd = new FormData();
          fd.set('_method', 'DELETE');
          if (window.CSRF && window.CSRF.name && window.CSRF.hash) fd.set(window.CSRF.name, window.CSRF.hash);

          fetch(url, { method:'POST', body: fd, headers:{'Accept':'application/json'} })
            .then(function(res){ return res.json().then(function(json){ return {res:res, json:json}; }); })
            .then(function(pack){
              var res=pack.res, json=pack.json;
              if (json.csrf && window.CSRF) window.CSRF.hash = json.csrf;

              if (!res.ok || !json.success) {
                Swal.fire({ icon:'error', title:'Gagal', text: json.message || 'Gagal menghapus' });
                return;
              }
              Swal.fire({ icon:'success', title:'Terhapus', timer:1100, showConfirmButton:false, timerProgressBar:true });
              fetchList();
            })
            .catch(function(err){
              Swal.fire({ icon:'error', title:'Kesalahan', text: err.message || 'Terjadi kesalahan' });
            });
        });
      };
    }
  }

  // ======= Events =======
  if (qInput) qInput.addEventListener('input', debounce(function(){ state.q = qInput.value || ''; state.page = 1; fetchList(); }, 250));
  if (perSel) perSel.addEventListener('change', function(){ state.perPage = parseInt(perSel.value || '10',10); state.page=1; fetchList(); });
  if (tipeSel) tipeSel.addEventListener('change', function(){ state.tipe = tipeSel.value || ''; state.page=1; fetchList(); });
  if (statusSel) statusSel.addEventListener('change', function(){ state.status = statusSel.value || ''; state.page=1; fetchList(); });

  // Add
  if (btnAdd) btnAdd.addEventListener('click', function(){
    currentId = null;
    if (titleEl) titleEl.textContent = 'Tambah Kendaraan';
    if (form && form.reset) form.reset();
    var m = form && form.querySelector('#_method'); if (m) m.value = 'POST';
    clearErrors();
    if (modal) modal.show();
  });

  // Submit create/update
  if (form) form.addEventListener('submit', function(e){
    e.preventDefault();
    clearErrors();

    var fd = new FormData(form);
    if (window.CSRF && window.CSRF.name && window.CSRF.hash) fd.set(window.CSRF.name, window.CSRF.hash);

    var url = (window.APP && window.APP.kendaraan) ? window.APP.kendaraan : '/kendaraan';
    var isUpdate = (form.querySelector('#_method') && form.querySelector('#_method').value === 'PUT' && currentId);
    if (isUpdate) { url = url + '/' + currentId; fd.set('_method', 'PUT'); }

    fetch(url, { method:'POST', body: fd, headers:{'Accept':'application/json'}, cache:'no-store' })
      .then(function(res){ return res.json().then(function(json){ return {res:res, json:json}; }); })
      .then(function(pack){
        var res=pack.res, json=pack.json;
        if (json.csrf && window.CSRF) {
          window.CSRF.hash = json.csrf;
          var inputs = document.querySelectorAll('input[name="'+window.CSRF.name+'"]');
          for (var i=0;i<inputs.length;i++) inputs[i].value = window.CSRF.hash;
        }

        if (!res.ok || !json.success) {
          if (json.errors) showErrors(json.errors);
          Swal.fire({ icon:'error', title:'Gagal', text: json.message || 'Validasi gagal' });
          return;
        }

        if (modal) modal.hide();
        Swal.fire({ icon:'success', title: (isUpdate ? 'Data diperbarui' : 'Data ditambahkan'), timer:1400, showConfirmButton:false, timerProgressBar:true });
        fetchList();
      })
      .catch(function(err){
        Swal.fire({ icon:'error', title:'Kesalahan', text: err.message || 'Terjadi kesalahan' });
      });
  });

  // ======= init =======
  bindRowActions();
  if (infoEl) infoEl.textContent = '';
  fetchList();
})();
