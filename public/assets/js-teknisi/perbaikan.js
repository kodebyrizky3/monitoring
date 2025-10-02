(function () {
  const page = document.getElementById('__page');
  const token = page?.dataset?.token || '';
  const baseUrl = `/ac/${encodeURIComponent(token)}`;

  const form = document.getElementById('formPerbaikan');
  const fotoAfter = document.getElementById('fotoAfter');
  const afterPreviewBox = document.getElementById('afterPreviewBox');
  const afterPreview = document.getElementById('afterPreview');
  const alertDone = document.getElementById('alertDone');
  const btnBackDetail = document.getElementById('btnBackDetail');
  const btnSubmit = document.getElementById('btnSubmit');

  const acName = document.getElementById('acName');
  const acKode = document.getElementById('acKode');
  const acStatus = document.getElementById('acStatus');
  const acLokasi = document.getElementById('acLokasi');
  const acThumb = document.getElementById('acThumb');
  const inputTicket = document.getElementById('ticket_id');

  function toastErr(msg){ alert(msg || 'Terjadi kesalahan.'); }
  function setLoading(on){
    if (on){ btnSubmit.disabled = true; btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...'; }
    else   { btnSubmit.disabled = false; btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Kirim &amp; Tandai Selesai'; }
  }

  async function init(){
    try {
      const res = await fetch(`${baseUrl}?format=json`, { headers:{'Accept':'application/json'} });
      if (!res.ok) throw new Error(`Init gagal (${res.status})`);
      const j = await res.json();
      if (!j.ok) throw new Error('Init gagal.');

      const ac = j.ac;
      acName.textContent   = ac.nomor_unik || 'AC';
      acKode.textContent   = ac.tipe_model || '—';
      acStatus.textContent = ac.status_ac || '—';
      acLokasi.textContent = ac.lokasi || '—';
      if (ac.foto_url){ acThumb.src = ac.foto_url; acThumb.classList.remove('d-none'); }

      inputTicket.value = j.ticket_id || '';
      btnBackDetail.href = baseUrl;
    } catch (e) {
      console.error(e); toastErr(e.message);
    }
  }

  fotoAfter?.addEventListener('change', () => {
    const f = fotoAfter.files?.[0];
    if (!f){ afterPreviewBox.classList.add('d-none'); afterPreview.removeAttribute('src'); return; }
    const r = new FileReader();
    r.onload = e => { afterPreview.src = e.target.result; afterPreviewBox.classList.remove('d-none'); };
    r.readAsDataURL(f);
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.classList.add('was-validated');
    if (!form.checkValidity()) return;

    try {
      setLoading(true);
      const fd = new FormData(form);   // csrf_field ikut
      const res = await fetch(`${baseUrl}/perbaikan`, { method:'POST', body: fd });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || j.error){ throw new Error(j.error || `Gagal simpan (${res.status})`); }

      alertDone.classList.remove('d-none');
      Array.from(form.elements).forEach(el => el.setAttribute('disabled','disabled'));
      if (j.redirect){ location.assign(j.redirect); }
    } catch (err) {
      console.error(err); toastErr(err.message);
    } finally {
      setLoading(false);
    }
  });

  init();
})();
