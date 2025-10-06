(() => {
    // Global search (dummy – hanya trigger event)
    const qGlobal = document.getElementById('qGlobal');
    qGlobal?.addEventListener('input', () => {
      // nanti sambungkan ke request backend; untuk sekarang cukup console.log
      // console.log('search:', qGlobal.value);
    });
  
    // Filter: ubah label Lokasi/Plat sesuai tipe
    const fTipe = document.getElementById('fTipe');
    const lbl = document.getElementById('fLabelLokasiPlat');
    const fLokasiPlat = document.getElementById('fLokasiPlat');
    const setLabel = () => {
      if (fTipe?.value === 'kendaraan') {
        lbl.textContent = 'Plat';
        fLokasiPlat.placeholder = 'Plat';
      } else if (fTipe?.value === 'ac') {
        lbl.textContent = 'Lokasi';
        fLokasiPlat.placeholder = 'Lokasi';
      } else {
        lbl.textContent = 'Lokasi / Plat';
        fLokasiPlat.placeholder = 'Lokasi (AC) / Plat (Kendaraan)';
      }
    };
    fTipe?.addEventListener('change', setLabel); setLabel();
  
    // Tabs status (client-side only demo)
    document.querySelectorAll('#tabStatus .nav-link').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#tabStatus .nav-link').forEach(x => x.classList.remove('active'));
        a.classList.add('active');
        // nanti panggil backend sesuai a.dataset.status
      });
    });
  
    // Bulk select bar
    const chkAll = document.getElementById('chkAll');
    const bulkBar = document.getElementById('bulkBar');
    const bulkCount = document.getElementById('bulkCount');
  
    const updateBulk = () => {
      const selected = [...document.querySelectorAll('.chkRow:checked')];
      const n = selected.length;
      bulkCount.textContent = n;
      bulkBar.classList.toggle('d-none', n === 0);
      chkAll.checked = n > 0 && selected.length === document.querySelectorAll('.chkRow').length;
    };
  
    chkAll?.addEventListener('change', () => {
      document.querySelectorAll('.chkRow').forEach(c => c.checked = chkAll.checked);
      updateBulk();
    });
    document.querySelectorAll('.chkRow').forEach(c => c.addEventListener('change', updateBulk));
  
    // Offcanvas detail (placeholder)
    const ocEl = document.getElementById('ocDetail');
    const offcanvas = ocEl ? new bootstrap.Offcanvas(ocEl) : null;
    document.querySelectorAll('#tblKendala .link-detail, #tblKendala tr.row-kendala td:not(:first-child):not(:last-child)').forEach(el => {
      el.addEventListener('click', (e) => {
        // identifikasi baris
        const tr = e.currentTarget.closest('tr');
        const id = tr?.dataset.id || '-';
        const body = document.getElementById('detailBody');
        if (body) {
          body.innerHTML = `
            <div class="mb-2 d-flex align-items-center justify-content-between">
              <div><strong>#${id}</strong> <span class="badge st-proses ms-2">Proses</span></div>
              <div class="d-flex gap-1">
                <button class="btn btn-success btn-sm">ACC</button>
                <button class="btn btn-outline-danger btn-sm">Tolak</button>
                <button class="btn btn-outline-secondary btn-sm">Assign</button>
                <button class="btn btn-outline-primary btn-sm">Ubah Status</button>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted small">Info Inti</div>
              <div>Tipe: AC</div>
              <div>Asset: AC-001 (Ruang A)</div>
              <div>Severity: <span class="badge sev-high">High</span></div>
              <div>Dibuat: 2025-09-15 10:20 oleh Rizky</div>
            </div>
            <div class="mb-3">
              <div class="text-muted small">Lampiran</div>
              <div class="text-muted">— (belum ada)</div>
            </div>
            <div class="mb-3">
              <div class="text-muted small">Timeline</div>
              <ul class="small mb-0">
                <li>2025-09-15 10:20 — Dibuat</li>
                <li>2025-09-15 11:00 — ACC oleh Admin</li>
                <li>2025-09-15 13:00 — Assign ke Teknisi A</li>
                <li>2025-09-15 14:30 — Proses</li>
              </ul>
            </div>
            <div>
              <label class="form-label">Catatan Admin</label>
              <textarea class="form-control" rows="3" placeholder="Catatan internal..."></textarea>
            </div>
          `;
        }
        offcanvas?.show();
      });
    });
  
    // Tombol dummy
    document.getElementById('btnReset')?.addEventListener('click', () => {
      document.getElementById('fTipe').value = '';
      document.getElementById('fStatus').value = '';
      document.getElementById('fSeverity').value = '';
      document.getElementById('fFrom').value = '';
      document.getElementById('fTo').value = '';
      document.getElementById('fLokasiPlat').value = '';
      setLabel();
    });
  })();
  