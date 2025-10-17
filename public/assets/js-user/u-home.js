(function(){
  'use strict';

  function avatarFallback(){
    document.querySelectorAll('img.u-avatar, img.u-avatar-lg').forEach(function(img){
      img.addEventListener('error', function(){
        if (window.AVATAR_PH && !img.src.includes('avatar-placeholder.svg')) {
          img.src = window.AVATAR_PH;
        }
      }, { once:true });
    });
  }

  async function loadStats(){
    try{
      if (!window.UHOME || !UHOME.statUrl) return;
      const res = await fetch(UHOME.statUrl, { headers: {'X-Requested-With':'XMLHttpRequest'}, cache: 'no-store' });
      if (!res.ok) return;
      const j = await res.json();
      const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = (val ?? 0); };
      set('statTrip', j.tripAktif);
      set('statAc', j.tiketAc);
      set('statPending', j.pending);
      if (j && j.csrf && typeof window.syncCsrf === 'function') window.syncCsrf(j.csrf);
    } catch(e) { /* silent */ }
  }

  document.addEventListener('DOMContentLoaded', function(){
    avatarFallback();
    loadStats();
  });
})();
