// public/assets/js-teknisi/common.js
(() => {
  'use strict';

  const base = (document.body?.dataset?.base || '').replace(/\/+$/, '');

  function joinUrl(root, ...parts) {
    const r = (root || '').replace(/\/+$/, '');
    const tail = parts.map(p => String(p||'').replace(/^\/+|\/+$/g,'')).filter(Boolean).join('/');
    if (!r) return tail ? '/' + tail : '/';
    return tail ? r + '/' + tail : r;
  }

  function getTokenFromPath() {
    const seg = location.pathname.replace(/^\/+|\/+$/g,'').split('/');
    const idx = seg.indexOf('ac');
    if (idx !== -1 && seg[idx+1]) return decodeURIComponent(seg[idx+1]);
    return '';
  }

  const qs = new URLSearchParams(location.search);
  let token = qs.get('t') || qs.get('qr') || '';
  if (!token) {
    const holder = document.getElementById('__page');
    token = holder?.dataset?.token || '';
  }
  if (!token) token = getTokenFromPath();

  function statusClass(s){
    return ({
      normal:'success',
      rusak:'danger',
      maintenance:'warning',
      diproses:'info',
      dipantau:'warning',
      'butuh-part':'danger'
    }[(s||'').toLowerCase()] || 'secondary');
  }

  async function fetchJson(url) {
    const res = await fetch(url, { cache:'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async function loadAC(t) {
    const tok = t || token;
    if (!tok) return null;

    // 1) API (kalau sudah ada backend)
    try {
      const url = joinUrl(base, 'api/ac', encodeURIComponent(tok));
      const obj = await fetchJson(url);
      if (obj && (obj.token || obj.kode || obj.nama)) return obj;
    } catch {}

    // 2) File per token
    try {
      const url = joinUrl(base, 'teknisi/data', encodeURIComponent(tok) + '.json');
      const obj = await fetchJson(url);
      if (obj) return obj;
    } catch {}

    // 3) File index
    try {
      const url = joinUrl(base, 'teknisi/data/ac.json');
      const db = await fetchJson(url);
      if (db && db[tok]) return db[tok];
    } catch {}

    return null;
  }

  async function postPerbaikan(t, payload) {
    const tok = t || token;
    try {
      const url = joinUrl(base, 'api/ac', encodeURIComponent(tok), 'perbaikan');
      const res = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload || {})
      });
      const data = await res.json().catch(()=>null);
      return { ok: res.ok, status: res.status, data };
    } catch (err) {
      return { ok:false, error: err?.message || 'network error' };
    }
  }

  window.Tech = { base, token, joinUrl, statusClass, loadAC, postPerbaikan };
})();
