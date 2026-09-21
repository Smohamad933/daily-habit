/* کتابخانه نمودار سبک بدون وابستگی خارجی — خطی، میله‌ای، دونات، میله افقی */
(function () {
  function prep(canvas, h) {
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.clientWidth || canvas.parentElement.clientWidth || 600;
    canvas.width = w * dpr; canvas.height = h * dpr;
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const cs = getComputedStyle(document.documentElement);
    const pick = v => cs.getPropertyValue(v).trim();
    return { ctx, w, h, muted: pick('--muted') || '#888', line: pick('--line') || '#ddd', text: pick('--text') || '#333' };
  }
  function font(ctx, s) { ctx.font = s + 'px Abar, Vazirmatn, Tahoma, sans-serif'; }

  /* نمودار خطی: روند درصد پیشرفت */
  window.lineChart = function (canvas, labels, values, opts) {
    opts = opts || {};
    const { ctx, w, h, muted, line, text } = prep(canvas, opts.height || 220);
    const pad = { r: 12, l: 40, t: 16, b: 28 };
    const cw = w - pad.l - pad.r, ch = h - pad.t - pad.b;
    const yMax = opts.yMax || 100;
    font(ctx, 10.5);

    // خطوط راهنما
    ctx.strokeStyle = line; ctx.fillStyle = muted; ctx.lineWidth = 1;
    for (let g = 0; g <= 4; g++) {
      const y = pad.t + ch - (g / 4) * ch;
      ctx.beginPath(); ctx.moveTo(pad.r, y); ctx.lineTo(w - pad.l, y); ctx.stroke();
      ctx.textAlign = 'right';
      ctx.fillText((yMax * g / 4) + '', w - pad.l + 26, y + 4);
    }
    if (!values.length) { ctx.fillStyle = muted; ctx.textAlign = 'center'; ctx.fillText('داده‌ای نیست', w / 2, h / 2); return; }

    const step = values.length > 1 ? cw / (values.length - 1) : cw / 2;
    const xAt = i => values.length > 1 ? pad.r + i * step : pad.r + cw / 2;
    const yAt = v => pad.t + ch - (Math.min(v, yMax) / yMax) * ch;

    // سطح زیر منحنی
    const color = opts.color || '#12633e';
    ctx.beginPath();
    values.forEach((v, i) => { const x = xAt(i), y = yAt(v); i ? ctx.lineTo(x, y) : ctx.moveTo(x, y); });
    ctx.strokeStyle = color; ctx.lineWidth = 2.4; ctx.lineJoin = 'round'; ctx.stroke();
    ctx.lineTo(xAt(values.length - 1), pad.t + ch); ctx.lineTo(xAt(0), pad.t + ch); ctx.closePath();
    ctx.globalAlpha = .13; ctx.fillStyle = color; ctx.fill(); ctx.globalAlpha = 1;

    // نقاط + برچسب‌ها
    ctx.fillStyle = color;
    values.forEach((v, i) => { ctx.beginPath(); ctx.arc(xAt(i), yAt(v), 3.4, 0, 7); ctx.fill(); });
    ctx.fillStyle = muted; ctx.textAlign = 'center';
    const skip = Math.ceil(labels.length / Math.max(3, Math.floor(cw / 46)));
    labels.forEach((lb, i) => { if (i % skip === 0 || i === labels.length - 1) ctx.fillText(lb, xAt(i), h - 8); });
  };

  /* نمودار میله‌ای: درصد هفته‌های ماه */
  window.barChart = function (canvas, labels, values, opts) {
    opts = opts || {};
    const { ctx, w, h, muted, line } = prep(canvas, opts.height || 200);
    const pad = { r: 12, l: 12, t: 14, b: 30 };
    const cw = w - pad.l - pad.r, ch = h - pad.t - pad.b;
    const yMax = opts.yMax || 100;
    ctx.strokeStyle = line;
    for (let g = 0; g <= 4; g++) {
      const y = pad.t + ch - (g / 4) * ch;
      ctx.beginPath(); ctx.moveTo(pad.r, y); ctx.lineTo(w - pad.l, y); ctx.stroke();
    }
    if (!values.length) { font(ctx, 11); ctx.fillStyle = muted; ctx.textAlign = 'center'; ctx.fillText('داده‌ای نیست', w / 2, h / 2); return; }
    const bw = Math.min(64, cw / values.length * .55);
    const step = cw / values.length;
    font(ctx, 10.5);
    values.forEach((v, i) => {
      const x = pad.l + i * step + step / 2 - bw / 2;
      const bh = (Math.min(v, yMax) / yMax) * ch;
      const grad = ctx.createLinearGradient(0, pad.t + ch - bh, 0, pad.t + ch);
      grad.addColorStop(0, opts.color || '#258a57'); grad.addColorStop(1, '#12633e');
      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.roundRect ? ctx.roundRect(x, pad.t + ch - bh, bw, bh, 6) : ctx.rect(x, pad.t + ch - bh, bw, bh);
      ctx.fill();
      ctx.fillStyle = muted; ctx.textAlign = 'center';
      ctx.fillText(labels[i], x + bw / 2, h - 9);
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text');
      ctx.fillText(Math.round(v) + '٪', x + bw / 2, pad.t + ch - bh - 5);
    });
  };

  /* دونات: ترکیب دلایل */
  window.donutChart = function (canvas, items, opts) {
    opts = opts || {};
    const { ctx, w, h, muted } = prep(canvas, opts.height || 210);
    const cx = w / 2, cy = h / 2, R = Math.min(w, h) / 2 - 14, r = R * .62;
    const total = items.reduce((s, i) => s + i.value, 0);
    font(ctx, 11);
    if (!total) { ctx.fillStyle = muted; ctx.textAlign = 'center'; ctx.fillText('داده‌ای نیست', cx, cy); return; }
    let a = -Math.PI / 2;
    items.forEach(it => {
      const a2 = a + (it.value / total) * Math.PI * 2;
      ctx.beginPath(); ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, R, a, a2); ctx.closePath();
      ctx.fillStyle = it.color; ctx.fill();
      a = a2;
    });
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.textAlign = 'center';
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text');
    font(ctx, 16); ctx.fillText(opts.center || total, cx, cy + 5);
  };

  /* میله افقی با برچسب (مثلاً تحلیل ساعت‌ها یا دلایل) */
  window.hbarChart = function (canvas, items, opts) {
    opts = opts || {};
    const rowH = 30;
    const { ctx, w, h, muted } = prep(canvas, opts.height || Math.max(120, items.length * rowH + 16));
    const padL = 118, padR = 44;
    const max = Math.max(1, ...items.map(i => i.value));
    font(ctx, 11);
    items.forEach((it, i) => {
      const y = 10 + i * rowH;
      ctx.fillStyle = muted; ctx.textAlign = 'right';
      ctx.fillText(String(it.label).slice(0, 16), padL - 8, y + 15);
      const bw = (w - padL - padR) * (it.value / max);
      ctx.fillStyle = it.color || '#12633e';
      ctx.beginPath();
      ctx.roundRect ? ctx.roundRect(padL, y + 4, Math.max(3, bw), 16, 6) : ctx.rect(padL, y + 4, Math.max(3, bw), 16);
      ctx.fill();
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text');
      ctx.textAlign = 'left';
      ctx.fillText(it.suffix !== undefined ? it.suffix : it.value, padL + bw + 6, y + 17);
    });
  };

  /* حلقه پیشرفت بزرگ */
  window.ringChart = function (el, percent, label) {
    const R = 62, C = 2 * Math.PI * R;
    const val = Math.max(0, Math.min(100, percent));
    el.innerHTML =
      '<svg width="150" height="150" viewBox="0 0 150 150">' +
      '<circle cx="75" cy="75" r="' + R + '" fill="none" stroke="var(--chip)" stroke-width="12"/>' +
      '<circle cx="75" cy="75" r="' + R + '" fill="none" stroke="url(#gr)" stroke-width="12" stroke-linecap="round"' +
      ' stroke-dasharray="' + C + '" stroke-dashoffset="' + (C * (1 - val / 100)) + '"/>' +
      '<defs><linearGradient id="gr" x1="0" y1="0" x2="1" y2="1">' +
      '<stop offset="0" stop-color="#12633e"/><stop offset="1" stop-color="#258a57"/></linearGradient></defs></svg>' +
      '<div class="ring-txt"><b>' + val.toLocaleString('fa-IR') + '٪</b><span>' + label + '</span></div>';
  };
})();
