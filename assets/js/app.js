/* منطق اصلی: تم، ناوبری، ایجکس تسک‌ها، عادت‌ها، نظرسنجی دلایل */
(function () {
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  /* ---------- تم دارک/لایت ---------- */
  function applyTheme(t) { document.documentElement.setAttribute('data-theme', t); }
  applyTheme(window.APP.theme || 'auto');
  const tt = $('#themeToggle');
  if (tt) tt.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme');
    const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const eff = cur === 'auto' ? (sysDark ? 'dark' : 'light') : cur;
    const next = eff === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    document.cookie = 'theme=' + next + ';path=/;max-age=31536000';
    if (window.APP.loggedIn !== false) {
      fetch(window.APP.api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=theme&theme=' + next + '&csrf=' + encodeURIComponent(window.APP.csrf || '') }).catch(() => {});
    }
  });

  /* ---------- منوی موبایل ---------- */
  const ntb = $('#navToggle');
  if (ntb) ntb.addEventListener('click', () => $('#navLinks').classList.toggle('open'));

  /* ---------- شیت اقدام سریع (دکمه +) ---------- */
  const qbtn = $('#quickBtn'), qsheet = $('#quickSheet'), qov = $('#sheetOverlay');
  const closeSheet = () => { if (qsheet) qsheet.classList.remove('show'); if (qov) qov.classList.remove('show'); };
  if (qbtn) qbtn.addEventListener('click', () => { qsheet.classList.add('show'); qov.classList.add('show'); });
  if (qov) qov.addEventListener('click', closeSheet);

  /* ---------- توست ---------- */
  window.toast = function (msg, type) {
    const wrap = $('#toastWrap'); if (!wrap) { alert(msg); return; }
    const t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg;
    if (type === 'error') t.style.borderColor = 'var(--danger)';
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4200);
  };

  function post(params) {
    params.csrf = window.APP.csrf || '';
    return fetch(window.APP.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params).toString()
    }).then(r => r.json());
  }

  /* ---------- تیک تسک (روزانه/هفتگی) ---------- */
  $$('.check[data-task]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.task;
      const next = btn.classList.contains('on') ? 'pending' : 'done';
      btn.classList.toggle('on', next === 'done');
      const item = btn.closest('.item');
      if (item) item.classList.toggle('done', next === 'done');
      post({ action: 'task_toggle', id, status: next }).then(r => {
        if (r.ok) {
          toast(next === 'done' ? '✅ آفرین! انجام شد.' : 'تسک به حالت باز برگشت.');
          const ring = $('#todayRing');
          if (ring && r.percent !== undefined) ringChart(ring, r.percent, 'پیشرفت امروز');
        } else { btn.classList.toggle('on'); toast(r.error || 'خطا', 'error'); }
      }).catch(() => { btn.classList.toggle('on'); toast('اتصال برقرار نشد', 'error'); });
    });
  });

  /* ---------- «انجام نشد» → دریافت دلیل توسط هوش مصنوعی ---------- */
  const overlay = $('#skipOverlay');
  let skipTaskId = 0;
  $$('.miss-btn').forEach(b => b.addEventListener('click', () => {
    skipTaskId = b.dataset.task;
    if (overlay) { overlay.classList.add('show'); const t = $('#skipTaskName'); if (t) t.textContent = b.dataset.title || ''; }
  }));
  if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); });
    $$('#skipOverlay .reason-opt').forEach(o => o.addEventListener('click', () => {
      $$('#skipOverlay .reason-opt').forEach(x => x.classList.remove('sel'));
      o.classList.add('sel');
      const inp = o.querySelector('input'); if (inp) inp.checked = true;
    }));
    const sf = $('#skipForm');
    if (sf) sf.addEventListener('submit', e => {
      e.preventDefault();
      const cat = sf.querySelector('input[name="category"]:checked');
      if (!cat) { toast('یک دلیل انتخاب کنید', 'error'); return; }
      post({ action: 'task_skip', id: skipTaskId, category: cat.value, note: $('#skipNote').value }).then(r => {
        overlay.classList.remove('show');
        if (r.ok) {
          toast('🤖 ثبت شد — ' + r.message);
          const item = document.querySelector('.miss-btn[data-task="' + skipTaskId + '"]').closest('.item');
          if (item) { item.style.opacity = .45; item.querySelector('.check')?.classList.add('miss'); }
        } else toast(r.error || 'خطا', 'error');
      });
    });
  }

  /* ---------- پلنر ساعتی: ذخیره با تغییر ---------- */
  $$('.slot-input').forEach(inp => {
    let t = null;
    const save = () => {
      post({ action: 'slot_save', date: inp.dataset.date, hour: inp.dataset.hour, title: inp.value }).then(r => {
        if (r.ok) { inp.closest('.hour-row')?.classList.toggle('filled', inp.value.trim() !== ''); toast('💾 برنامه ساعت ذخیره شد.'); }
      });
    };
    inp.addEventListener('change', save);
    inp.addEventListener('keyup', () => { clearTimeout(t); t = setTimeout(save, 1400); });
  });

  /* ---------- ثبت پیشرفت عادت ---------- */
  $$('.habit-range').forEach(rng => {
    const out = document.querySelector('.habit-out[data-habit="' + rng.dataset.habit + '"][data-date="' + rng.dataset.date + '"]');
    rng.addEventListener('input', () => { if (out) out.textContent = Number(rng.value).toLocaleString('fa-IR') + '٪'; });
    let t = null;
    rng.addEventListener('change', () => {
      clearTimeout(t);
      t = setTimeout(() => post({ action: 'habit_log', habit: rng.dataset.habit, date: rng.dataset.date, progress: rng.value })
        .then(r => r.ok && toast('✅ پیشرفت «' + (rng.dataset.title || 'عادت') + '» ثبت شد.')), 250);
    });
  });

  /* ---------- گام‌های هدف ---------- */
  $$('.step-check').forEach(c => c.addEventListener('change', () => {
    post({ action: 'step_toggle', id: c.dataset.step, done: c.checked ? 1 : 0 }).then(r => {
      if (r.ok) {
        toast(c.checked ? '✅ یک گام دیگر تمام شد!' : 'گام به حالت باز برگشت.');
        const bar = document.querySelector('#goalBar' + c.dataset.goal);
        const lbl = document.querySelector('#goalPct' + c.dataset.goal);
        if (bar) bar.style.width = r.percent + '%';
        if (lbl) lbl.textContent = r.percent.toLocaleString('fa-IR') + '٪';
      }
    });
  }));

  /* ---------- انتخاب استان/شهر ---------- */
  const prov = $('#province'), city = $('#city');
  if (prov && city && window.CITIES) {
    prov.addEventListener('change', () => {
      city.innerHTML = '<option value="">— شهر را انتخاب کنید —</option>';
      (window.CITIES[prov.value] || []).forEach(c => {
        const o = document.createElement('option'); o.value = c; o.textContent = c; city.appendChild(o);
      });
      if (city.dataset.current) { city.value = city.dataset.current; city.dataset.current = ''; }
    });
    if (prov.value) {
      prov.dispatchEvent(new Event('change'));
      if (city.dataset.current) city.value = city.dataset.current;
    }
  }

  /* ---------- نظرسنجی اعلان‌ها (مرورگر) ---------- */
  let lastSeen = Date.now() / 1000 | 0;
  function poll() {
    post({ action: 'poll' }).then(r => {
      if (!r.ok) return;
      if (r.items && r.items.length) {
        r.items.forEach(n => {
          toast('🔔 ' + n.title);
          if (window.showBrowserNotif) window.showBrowserNotif(n.title, n.body);
        });
        const b = document.querySelector('.topbar .badge');
        if (b) b.textContent = Number(r.unread).toLocaleString('fa-IR');
        else {
          const link = document.querySelector('a[href$="notifications.php"]');
          if (link) { const s = document.createElement('span'); s.className = 'badge'; s.textContent = Number(r.unread).toLocaleString('fa-IR'); link.appendChild(s); }
        }
      }
    }).catch(() => {});
  }
  if (window.APP.api && document.querySelector('.topbar')) setInterval(poll, 45000);

  /* ---------- نمودارها: اجرای تعریف‌های داخل صفحه ---------- */
  window.renderCharts = function (defs) {
    (defs || []).forEach(d => {
      const el = document.getElementById(d.id);
      if (!el) return;
      if (d.type === 'line') lineChart(el, d.labels, d.values, d.opts || {});
      if (d.type === 'bar') barChart(el, d.labels, d.values, d.opts || {});
      if (d.type === 'donut') donutChart(el, d.items, d.opts || {});
      if (d.type === 'hbar') hbarChart(el, d.items, d.opts || {});
      if (d.type === 'ring') ringChart(el, d.percent, d.label);
    });
  };
  window.addEventListener('resize', () => { if (window.CHART_DEFS) window.renderCharts(window.CHART_DEFS); });
  if (window.CHART_DEFS) window.renderCharts(window.CHART_DEFS);
})();
