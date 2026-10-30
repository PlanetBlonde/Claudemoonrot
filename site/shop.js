(() => {
  // The bag lives in this browser only; checkout.php re-prices everything server-side.
  const KEY = 'fm-bag';
  const catalog = {};
  let stock = {};

  const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
  const save = bag => { try { localStorage.setItem(KEY, JSON.stringify(bag)); } catch (e) {} };
  let bag = load();

  const money = n => '$' + (Number.isInteger(n) ? n : n.toFixed(2));
  const count = () => Object.values(bag).reduce((a, b) => a + b, 0);

  // Read name, price and photo off each card so the bag needs no extra data file.
  document.querySelectorAll('.product[data-id]').forEach(card => {
    const id = card.dataset.id;
    const price = parseFloat(card.querySelector('.price').textContent.replace('$', ''));
    if (isNaN(price)) return;
    catalog[id] = {
      name: card.querySelector('h3').textContent,
      price,
      img: card.querySelector('.shot img')?.getAttribute('src') || ''
    };
  });

  const drawer = document.createElement('aside');
  drawer.className = 'bag';
  drawer.setAttribute('aria-label', 'Your bag');
  drawer.hidden = true;
  drawer.innerHTML = `
    <div class="bag-head"><h2>Your <span class="hand">bag</span></h2><button class="bag-x" type="button" aria-label="Close bag">&times;</button></div>
    <ul class="bag-list"></ul>
    <div class="bag-foot">
      <p class="bag-row"><span>Subtotal</span><span class="bag-sub"></span></p>
      <p class="bag-row"><span>Shipping (US, flat)</span><span>$6</span></p>
      <p class="bag-row total"><span>Total</span><span class="bag-total"></span></p>
      <button class="bag-go" type="button">Checkout</button>
      <p class="bag-note" role="status" aria-live="polite"></p>
      <p class="bag-fine">Secure payment by Stripe. <a href="policies.html">Shipping &amp; returns</a> &middot; <a href="privacy.html">Privacy</a></p>
    </div>`;
  const tab = document.createElement('button');
  tab.className = 'bag-tab';
  tab.type = 'button';
  tab.innerHTML = 'Bag <span class="bag-count">0</span>';
  document.body.append(drawer, tab);

  const list = drawer.querySelector('.bag-list');
  const note = drawer.querySelector('.bag-note');

  const render = () => {
    for (const id of Object.keys(bag)) if (!catalog[id]) delete bag[id];
    save(bag);
    const n = count();
    tab.querySelector('.bag-count').textContent = n;
    tab.classList.toggle('has', n > 0);
    list.innerHTML = n ? '' : '<li class="bag-empty">Nothing in here yet but moonlight.</li>';
    let sub = 0;
    for (const [id, qty] of Object.entries(bag)) {
      const it = catalog[id];
      sub += it.price * qty;
      const max = stock[id] ?? qty;
      const li = document.createElement('li');
      li.innerHTML = `<img src="${it.img}" alt="" width="56" height="56">
        <div><p class="bag-name"></p><p class="bag-price">${money(it.price)}</p></div>
        <div class="bag-qty">
          <button type="button" data-d="-1" aria-label="One fewer">&minus;</button>
          <span>${qty}</span>
          <button type="button" data-d="1" aria-label="One more" ${qty >= max ? 'disabled' : ''}>+</button>
        </div>`;
      li.querySelector('.bag-name').textContent = it.name;
      li.querySelectorAll('[data-d]').forEach(b => b.onclick = () => change(id, +b.dataset.d));
      list.append(li);
    }
    drawer.querySelector('.bag-sub').textContent = money(sub);
    drawer.querySelector('.bag-total').textContent = n ? money(sub + 6) : '$0';
    drawer.querySelector('.bag-go').disabled = !n;
    // Buttons on the page follow stock and what's already in the bag.
    document.querySelectorAll('.buy.add').forEach(b => {
      const left = (stock[b.dataset.id] ?? 1) - (bag[b.dataset.id] || 0);
      const sold = (stock[b.dataset.id] ?? 1) <= 0;
      b.disabled = left <= 0;
      b.textContent = sold ? 'Sold out' : left <= 0 ? 'In your bag' : 'Add to bag';
    });
  };

  const change = (id, d) => {
    bag[id] = Math.max(0, Math.min((bag[id] || 0) + d, stock[id] ?? 99));
    if (!bag[id]) delete bag[id];
    note.textContent = '';
    render();
  };

  const show = on => {
    drawer.hidden = !on;
    tab.setAttribute('aria-expanded', String(on));
    if (on) drawer.querySelector('.bag-x').focus();
  };

  document.querySelectorAll('.buy.add').forEach(b => b.addEventListener('click', () => {
    change(b.dataset.id, 1);
    show(true);
  }));
  tab.addEventListener('click', () => show(drawer.hidden));
  drawer.querySelector('.bag-x').addEventListener('click', () => show(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !drawer.hidden) show(false); });

  drawer.querySelector('.bag-go').addEventListener('click', async e => {
    const btn = e.currentTarget;
    btn.disabled = true;
    note.textContent = 'Opening secure checkout…';
    try {
      const res = await fetch('checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: Object.entries(bag).map(([id, qty]) => ({ id, qty })) })
      });
      const data = await res.json();
      if (data.ok) { location.href = data.url; return; }
      note.textContent = data.message || 'Checkout hiccuped. Try again in a minute.';
    } catch (err) {
      note.textContent = 'Checkout hiccuped. Try again in a minute.';
    }
    btn.disabled = false;
  });

  render();
  fetch('stock.php', { cache: 'no-store' })
    .then(r => r.json())
    .then(d => { stock = d.stock || {}; render(); })
    .catch(() => {});
})();
