(() => {
    const canvas = document.getElementById('bg-net');
    if (!canvas) return;
  
    const ctx = canvas.getContext('2d');
    const DPR = Math.min(window.devicePixelRatio || 1, 2);
    let w, h, particles = [];
    const MODE = canvas.dataset.mode === 'attract' ? 'attract' : 'repulse';
  
    function resize() {
      w = canvas.width  = Math.floor(window.innerWidth  * DPR);
      h = canvas.height = Math.floor(window.innerHeight * DPR);
      canvas.style.width  = window.innerWidth + 'px';
      canvas.style.height = window.innerHeight + 'px';
      createParticles();
    }
    window.addEventListener('resize', resize);
  
    const mouse = { x:null, y:null };
    window.addEventListener('mousemove', e => { mouse.x = e.clientX * DPR; mouse.y = e.clientY * DPR; });
    window.addEventListener('mouseleave', () => { mouse.x = mouse.y = null; });
  
    function createParticles() {
      // density lebih ringan supaya halus di device rendah
      const count = Math.min(160, Math.floor((window.innerWidth * window.innerHeight) / 13000));
      particles = Array.from({length:count}, () => ({
        x: Math.random()*w, y: Math.random()*h,
        vx: (Math.random()-0.5)*0.40*DPR, vy: (Math.random()-0.5)*0.40*DPR
      }));
    }
  
    const LINK_DIST = 150 * DPR;
    const NODE_R = 1.3 * DPR;
    const MOUSE_RADIUS = 130 * DPR;
    const MOUSE_STRENGTH = 0.08;
  
    function tick() {
      ctx.clearRect(0,0,w,h);
  
      for (const p of particles){
        if (mouse.x !== null){
          const dx = p.x - mouse.x, dy = p.y - mouse.y;
          const d  = Math.hypot(dx, dy);
          if (d < MOUSE_RADIUS && d > 1){
            const dir = (MODE === 'repulse') ? 1 : -1;
            const f = dir * (1 - d/MOUSE_RADIUS) * MOUSE_STRENGTH;
            p.vx += (dx/d) * f; p.vy += (dy/d) * f;
          }
        }
        p.x += p.vx; p.y += p.vy;
        p.vx *= 0.98; p.vy *= 0.98;
        if (p.x <= 0 || p.x >= w) p.vx *= -1;
        if (p.y <= 0 || p.y >= h) p.vy *= -1;
        p.x = Math.max(0, Math.min(w, p.x));
        p.y = Math.max(0, Math.min(h, p.y));
      }
  
      // garis
      for (let i=0;i<particles.length;i++){
        for (let j=i+1;j<particles.length;j++){
          const a = particles[i], b = particles[j];
          const dx=a.x-b.x, dy=a.y-b.y, d=Math.hypot(dx,dy);
          if (d < LINK_DIST){
            const alpha = 0.25 * (1 - d/LINK_DIST);
            ctx.strokeStyle = `rgba(30,102,255,${alpha})`;
            ctx.lineWidth = DPR;
            ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(b.x,b.y); ctx.stroke();
          }
        }
      }
  
      // titik
      ctx.fillStyle = 'rgba(30,102,255,0.7)';
      for (const p of particles){ ctx.beginPath(); ctx.arc(p.x,p.y,NODE_R,0,Math.PI*2); ctx.fill(); }
  
      requestAnimationFrame(tick);
    }
  
    resize(); tick();
  
    // toggle password (kalau elemen ada)
    const toggler = document.getElementById('togglePwd');
    const pwd = document.getElementById('password');
    if (toggler && pwd){
      toggler.addEventListener('click', () => {
        const type = pwd.type === 'password' ? 'text' : 'password';
        pwd.type = type;
        toggler.textContent = type === 'password' ? '👁' : '🙈';
      });
    }
  })();
  