(() => {
  const root = document.documentElement;
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Day / night toggle
  const toggle = document.querySelector('.toggle');
  const sync = () => {
    const day = root.dataset.theme === 'day';
    toggle.setAttribute('aria-pressed', String(day));
    toggle.setAttribute('aria-label', day ? 'Switch to night' : 'Switch to day');
    document.querySelector('meta[name="theme-color"]').content = day ? '#f8ead2' : '#0d110e';
  };
  toggle.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'day' ? 'night' : 'day';
    try { localStorage.setItem('fm-theme', root.dataset.theme); } catch (e) {}
    sync();
  });
  sync();

  // Kinetic headline: split into letters, give a few a swash or handwritten
  // costume, then keep swapping costumes so the name never sits still.
  const letters = [];
  let delay = 0;
  document.querySelectorAll('.kinetic .line').forEach(line => {
    for (const c of line.dataset.text) {
      const s = document.createElement('span');
      s.className = 'ch' + (c === ' ' ? ' sp' : '');
      s.textContent = c === ' ' ? ' ' : c;
      s.setAttribute('aria-hidden', 'true');
      s.style.animationDelay = (delay += 0.06) + 's';
      line.appendChild(s);
      if (/[A-Z]/.test(c)) letters.push(s);
    }
  });
  const costumes = ['alt-swash', 'alt-hand'];
  const dress = (s, i) => { s.classList.remove(...costumes); if (i != null) s.classList.add(costumes[i]); };
  // Starting looks: the double O in MOONROT and the X in FOX. After that,
  // one letter changes per beat and no more than three wear a costume.
  const worn = [];
  const wear = (s, i) => { dress(s, i); worn.push(s); };
  wear(letters[2], 1); wear(letters[4], 0); wear(letters[5], 0);
  if (!still) {
    setInterval(() => {
      const bare = letters.filter(s => !worn.includes(s));
      wear(bare[Math.floor(Math.random() * bare.length)], Math.floor(Math.random() * 2));
      dress(worn.shift(), null);
    }, 1100);
  }

  // Scroll reveals
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.15 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  // Starfield
  const cv = document.querySelector('.stars');
  const ctx = cv.getContext('2d');
  let stars = [], w, h;
  const resize = () => {
    const dpr = Math.min(devicePixelRatio || 1, 2);
    w = cv.width = innerWidth * dpr; h = cv.height = innerHeight * dpr;
    const n = Math.round((innerWidth * innerHeight) / 9000);
    stars = Array.from({ length: n }, () => ({
      x: Math.random() * w, y: Math.random() * h,
      r: (Math.random() * 1.3 + 0.3) * dpr,
      t: Math.random() * Math.PI * 2, v: 0.004 + Math.random() * 0.012,
      dy: (Math.random() * 0.08 + 0.02) * dpr
    }));
  };
  const draw = () => {
    const rgb = getComputedStyle(root).getPropertyValue('--star').trim();
    ctx.clearRect(0, 0, w, h);
    for (const s of stars) {
      s.t += s.v; s.y -= still ? 0 : s.dy;
      if (s.y < -2) s.y = h + 2;
      ctx.fillStyle = `rgba(${rgb},${0.25 + 0.55 * (0.5 + 0.5 * Math.sin(s.t))})`;
      ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fill();
    }
    if (!still) requestAnimationFrame(draw);
  };
  addEventListener('resize', resize);
  resize(); draw();

  // Letter signup
  const form = document.querySelector('.signup');
  const note = form.querySelector('.note');
  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    note.textContent = 'Sending…';
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      note.textContent = data.message;
      if (data.ok) form.reset();
    } catch (e) {
      note.textContent = 'Something went sideways. Try again in a minute.';
    }
  });
})();
