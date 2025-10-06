(() => {
  // =========================================================
  // Base URL dari meta tag
  // =========================================================
  const meta = document.querySelector('meta[name="base-url"]');
  const BASE_URL = meta ? meta.content : '/';

  // =========================================================
  // 1) NOTIFIKASI (SSE + fallback polling)
  // =========================================================
  const notifCountEl = document.getElementById('notifCount');
  const notifListEl  = document.getElementById('notifList');

  function renderNotif(count, items) {
    if (!notifCountEl || !notifListEl) return;

    // Badge jumlah
    if (count > 0) {
      notifCountEl.classList.remove('d-none');
      notifCountEl.textContent = count;
    } else {
      notifCountEl.classList.add('d-none');
    }

    // Isi dropdown
    notifListEl.innerHTML = '';
    notifListEl.insertAdjacentHTML(
      'beforeend',
      '<li class="dropdown-header">Laporan Masuk</li><li><hr class="dropdown-divider"></li>'
    );

    if (!items || items.length === 0) {
      notifListEl.insertAdjacentHTML(
        'beforeend',
        '<li class="px-3 text-muted small">Tidak ada laporan baru.</li>'
      );
      return;
    }

    items.forEach(it => {
      const id     = it.id ?? '';
      const judul  = it.judul || 'Laporan Baru';
      const waktu  = it.waktu || it.created_at || '';
      const status = it.status || '';
      notifListEl.insertAdjacentHTML(
        'beforeend',
        `<li>
          <a class="dropdown-item d-flex flex-column" href="${BASE_URL}laporan/${id}">
            <span class="fw-semibold">${judul}</span>
            <span class="text-muted small">${waktu} — ${status}</span>
          </a>
        </li>`
      );
    });
  }

  async function fetchLatest() {
    try {
      const res = await fetch(`${BASE_URL}notifications/latest`, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      renderNotif(data.count || 0, data.items || []);
    } catch (e) {
      console.error('notif latest error', e);
    }
  }

  function startPolling() {
    fetchLatest();
    // polling tiap 15 detik
    return setInterval(fetchLatest, 15000);
  }

  function startSSE() {
    if (!window.EventSource) return null;
    try {
      const es = new EventSource(`${BASE_URL}notifications/stream`);

      // event custom "laporan"
      es.addEventListener('laporan', (ev) => {
        try {
          const data = JSON.parse(ev.data);
          renderNotif(data.count || 0, data.items || []);
        } catch (e) {
          console.error('SSE parse error', e);
        }
      });

      es.onerror = () => {
        // koneksi putus -> biarkan caller yang fallback ke polling
        es.close();
      };

      return es;
    } catch (e) {
      console.error('SSE init error', e);
      return null;
    }
  }

  // =========================================================
  // 2) OFFCANVAS (MOBILE) – auto-hide & animasi stagger
  // =========================================================
  function initOffcanvasMobile() {
    const offEl = document.getElementById('offcanvasSidebar');
    if (!offEl) return;
    if (typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
      console.warn('Bootstrap Offcanvas belum tersedia.');
      return;
    }

    const off = bootstrap.Offcanvas.getOrCreateInstance(offEl);

    // Tutup otomatis saat layar berubah ke desktop
    const mq = window.matchMedia('(min-width: 768px)');
    mq.addEventListener('change', (e) => { if (e.matches) off.hide(); });

    // Tutup ketika klik link menu (kecuali toggle collapse)
    offEl.querySelectorAll('.nav-link').forEach(a => {
      a.addEventListener('click', () => {
        if (a.getAttribute('data-bs-toggle') === 'collapse') return;
        if (window.innerWidth < 768) off.hide();
      });
    });

    // Animasi item menu saat panel dibuka (stagger)
    offEl.addEventListener('show.bs.offcanvas', () => {
      const items = offEl.querySelectorAll('.nav > .nav-item');
      items.forEach((li, i) => {
        li.style.animationDelay = `${i * 50}ms`;
        li.classList.add('oc-item-animate');
      });
    });

    // Bersihkan animasi saat panel ditutup
    offEl.addEventListener('hidden.bs.offcanvas', () => {
      offEl.querySelectorAll('.oc-item-animate').forEach((li) => {
        li.style.animationDelay = '';
        li.classList.remove('oc-item-animate');
      });
    });
  }

  // =========================================================
  // 3) BOOT
  // =========================================================
  document.addEventListener('DOMContentLoaded', () => {
    // Notifikasi: coba SSE, fallback ke polling
    const es = startSSE();
    let pollingTimer = null;

    if (!es) {
      pollingTimer = startPolling();
    } else {
      // Kalau SSE kemudian CLOSED, mulai polling
      setInterval(() => {
        if (es.readyState === 2 /* CLOSED */ && !pollingTimer) {
          pollingTimer = startPolling();
        }
      }, 30000);
    }

    // Refresh notif saat kembali online
    window.addEventListener('online', fetchLatest);

    // Inisialisasi offcanvas mobile
    initOffcanvasMobile();
  });
})();
