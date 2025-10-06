(function(){
    const perPage = document.getElementById('perPageSelect');
    perPage && perPage.addEventListener('change', () => { perPage.form && perPage.form.submit(); });
  
    // export (placeholder)
    const btnExport = document.getElementById('btnExport');
    btnExport && btnExport.addEventListener('click', () => { alert('Fitur export akan ditambahkan setelah CRUD selesai.'); });
  
    // ===== Modal CRUD =====
    const modalEl = document.getElementById('acModal');
    const modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
    const form    = document.getElementById('acForm');
    const titleEl = document.getElementById('acModalTitle');
    let currentId = null;
  
    // helper baseUrl
    function baseUrl(path){
      const base = '<?= rtrim(base_url('/'), '/') ?>';
      return base + '/' + path.replace(/^\//,'');
    }
  
    // Add (create)
    const btnAdd = document.getElementById('btnAdd');
    btnAdd && btnAdd.addEventListener('click', () => {
      currentId = null;
      titleEl.textContent = 'Tambah Unit AC';
      form.reset();
      form.querySelector('#_method').value = 'POST';
      clearErrors();
      modal && modal.show();
    });
  
    // Edit (load detail)
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', async () => {
        currentId = btn.dataset.id;
        titleEl.textContent = 'Edit Unit AC #' + currentId;
        form.querySelector('#_method').value = 'PUT';
        clearErrors();
        try {
          const res  = await fetch(baseUrl('ac-units/'+currentId), { headers: { 'Accept':'application/json' }});
          const json = await res.json();
          if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat data');
          if (json.csrf) window.CSRF.hash = json.csrf;
          fillForm(json.data);
          modal && modal.show();
        } catch (e) { alert(e.message); }
      });
    });
  
    // Submit create/update via AJAX
    form && form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors();
  
      const fd = new FormData(form);
      fd.set(window.CSRF.name, window.CSRF.hash);
  
      let url = baseUrl('ac-units');
      if (form.querySelector('#_method').value === 'PUT' && currentId) {
        url = baseUrl('ac-units/'+currentId);
        fd.set('_method', 'PUT'); // method spoofing
      }
  
      try {
        const res  = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept':'application/json' }});
        const json = await res.json();
  
        if (json.csrf) {
          window.CSRF.hash = json.csrf;
          document.querySelectorAll('input[name="'+window.CSRF.name+'"]').forEach(i => i.value = window.CSRF.hash);
        }
  
        if (!res.ok || !json.success) {
          if (json.errors) showErrors(json.errors);
          return alert(json.message || 'Validasi gagal');
        }
  
        // sukses → refresh halaman agar KPI & tabel update
        location.reload();
      } catch (err) {
        alert(err.message || 'Terjadi kesalahan');
      }
    });
  
    // Delete (form POST _method=DELETE)
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const url = btn.dataset.url;
        const id  = btn.dataset.id;
        if (!url) return;
        if (confirm('Hapus unit AC #'+id+' ?')) {
          const f = document.getElementById('deleteForm');
          f.setAttribute('action', url);
          f.submit();
        }
      });
    });
  
    function fillForm(d){
      const fields = ['kode_qr','nomor_unik','tipe_model','kapasitas_btu','lokasi','status_ac','catatan'];
      fields.forEach(k => {
        const el = form.querySelector('[name="'+k+'"]');
        if (el) el.value = (d && d[k] !== undefined) ? d[k] : '';
      });
    }
    function clearErrors(){
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('[data-err]').forEach(el => el.textContent = '');
    }
    function showErrors(errors){
      Object.entries(errors).forEach(([key,msg]) => {
        const input = form.querySelector('[name="'+key+'"]');
        const help  = form.querySelector('[data-err="'+key+'"]');
        if (input) input.classList.add('is-invalid');
        if (help)  help.textContent = msg;
      });
    }
  })();
  