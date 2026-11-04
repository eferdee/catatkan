/* ===== CatatKan — logika dashboard (vanilla JS, tanpa library) ===== */
(() => {
  'use strict';

  /* ============================== util dasar ============================== */
  const boot = JSON.parse(document.getElementById('sw-boot').textContent);
  const CATS = boot.categories;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nf = new Intl.NumberFormat('id-ID');
  const rp = n => 'Rp ' + nf.format(Math.round(n));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ico = (name) => `<svg class="i"><use href="#i-${name}"/></svg>`;
  const pad = n => String(n).padStart(2, '0');
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const lsGet = k => { try { return localStorage.getItem(k); } catch { return null; } };
  const lsSet = (k, v) => { try { localStorage.setItem(k, v); } catch { /* abaikan */ } };
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  function compact(n) {
    const a = Math.abs(n), sign = n < 0 ? '-' : '';
    const f = (v, u) => sign + String(Math.round(v * 10) / 10).replace('.', ',') + ' ' + u;
    if (a >= 1e9) return f(a / 1e9, 'M');
    if (a >= 1e6) return f(a / 1e6, 'jt');
    if (a >= 1e3) return f(a / 1e3, 'rb');
    return sign + String(Math.round(a));
  }
  const pct1 = n => Math.abs(n).toFixed(1).replace('.', ',');

  const isoOf = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  function fmtDate(iso) { const [y, m, d] = iso.split('-').map(Number); return `${pad(d)} ${MONTHS[m - 1]} ${y}`; }
  function dateLabel(iso) {
    const now = new Date();
    if (iso === isoOf(now)) return 'Hari ini';
    const y = new Date(now); y.setDate(now.getDate() - 1);
    return iso === isoOf(y) ? 'Kemarin' : fmtDate(iso);
  }
  const greeting = () => { const h = new Date().getHours(); return h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam'; };
  const firstName = boot.user.name.trim().split(/\s+/)[0];
  const initials = name => { const p = name.trim().split(/\s+/); return ((p[0] || '')[0] || '') + ((p[1] || '')[0] || ''); };

  /* ================================= API ================================= */
  class ApiError extends Error {
    constructor(message, status, errors = {}) { super(message); this.status = status; this.errors = errors; }
  }

  async function api(url, { method = 'GET', body } = {}) {
    const opt = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (method !== 'GET') {
      opt.headers['Content-Type'] = 'application/json';
      opt.headers['X-CSRF-Token'] = boot.csrf;
      opt.body = JSON.stringify(body || {});
    }
    let res;
    try { res = await fetch(url, opt); } catch { throw new ApiError('Tidak dapat terhubung ke server.', 0); }
    let data = null;
    try { data = await res.json(); } catch { /* bukan JSON */ }
    if (res.status === 401) { location.href = 'login.php'; throw new ApiError('Sesi berakhir.', 401); }
    if (res.status === 419) { toast('Sesi kedaluwarsa, memuat ulang…', 'error'); setTimeout(() => location.reload(), 900); throw new ApiError('Sesi kedaluwarsa.', 419); }
    if (!res.ok || !data || data.ok === false) throw new ApiError((data && data.message) || 'Terjadi kesalahan.', res.status, (data && data.errors) || {});
    return data;
  }

  function toast(msg, type = 'ok') {
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    $('#toasts').append(el);
    requestAnimationFrame(() => el.classList.add('in'));
    setTimeout(() => { el.classList.remove('in'); setTimeout(() => el.remove(), 300); }, 3200);
  }

  /* ================================ state ================================ */
  const state = {
    s: null,
    months: 6,
    hide: lsGet('sw-hide') === '1',
    tx: { page: 1, q: '', type: '', category: '', month: '' },
    items: new Map(),
    view: 'dashboard',
    prev: {},
  };

  /* ============================ animasi angka ============================ */
  function setNumber(el, key, to, fmt = rp) {
    const from = state.prev[key] ?? 0;
    state.prev[key] = to;
    if (reduceMotion || from === to) { el.textContent = fmt(to); return; }
    const t0 = performance.now(), dur = 750;
    const step = now => {
      const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(from + (to - from) * e);
      if (p < 1) requestAnimationFrame(step); else el.textContent = fmt(to);
    };
    requestAnimationFrame(step);
  }

  function fillBars(root) {
    requestAnimationFrame(() => requestAnimationFrame(() => {
      $$('.progress span[data-w]', root).forEach(el => { el.style.width = el.dataset.w + '%'; });
    }));
  }

  /* ================================ chart ================================ */
  const smooth = pts => pts.map(([px, py], i, a) => i === 0
    ? `M${px},${py}`
    : `C${(a[i - 1][0] + px) / 2},${a[i - 1][1]} ${(a[i - 1][0] + px) / 2},${py} ${px},${py}`).join(' ');

  function niceMax(v) {
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const f = v / p;
    const n = [1, 2, 4, 6, 8, 10].find(c => c >= f) || 10;
    return n * p;
  }

  function lineChart(host, labels, series) {
    const W = host.clientWidth;
    if (W < 50) return;                         // tersembunyi — digambar ulang saat tampil
    const H = host.clientHeight || 260;
    const m = { t: 12, r: 12, b: 28, l: 46 };
    const iw = W - m.l - m.r, ih = H - m.t - m.b, n = labels.length;
    const maxV = niceMax(Math.max(100000, ...series.flatMap(s => s.values)));
    const x = i => m.l + (n === 1 ? iw / 2 : i * iw / (n - 1));
    const y = v => m.t + ih - (v / maxV) * ih;
    const id = host.id;

    let svg = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" role="img" aria-label="Grafik">`;
    svg += '<defs>' + series.map((s, k) => `<linearGradient id="${id}-g${k}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" style="stop-color:${s.color};stop-opacity:.28"/><stop offset="100%" style="stop-color:${s.color};stop-opacity:0"/></linearGradient>`).join('') + '</defs>';
    for (let t = 0; t <= 4; t++) {
      const v = maxV * t / 4, yy = y(v);
      svg += `<line class="grid-line" x1="${m.l}" x2="${W - m.r}" y1="${yy}" y2="${yy}"/><text class="ax" x="${m.l - 8}" y="${yy + 3.5}" text-anchor="end">${compact(v)}</text>`;
    }
    const step = n > 8 && W < 520 ? 2 : 1;
    labels.forEach((l, i) => {
      if ((n - 1 - i) % step === 0) svg += `<text class="ax" x="${x(i)}" y="${H - 8}" text-anchor="middle">${esc(l)}</text>`;
    });
    series.forEach((s, k) => {
      const line = smooth(s.values.map((v, i) => [x(i), y(v)]));
      svg += `<path d="${line} L${x(n - 1)},${y(0)} L${x(0)},${y(0)} Z" fill="url(#${id}-g${k})"/>`;
      svg += `<path d="${line}" fill="none" style="stroke:${s.color}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>`;
    });
    svg += `<line data-cross x1="0" x2="0" y1="${m.t}" y2="${m.t + ih}" style="stroke:var(--line-2)" stroke-width="1.5" stroke-dasharray="4 4" opacity="0"/>`;
    series.forEach((s, k) => { svg += `<circle data-dot="${k}" r="5" cx="0" cy="0" style="fill:var(--surface);stroke:${s.color}" stroke-width="2.5" opacity="0"/>`; });
    svg += '</svg><div class="chart-tip"></div>';
    host.innerHTML = svg;
    host.style.touchAction = 'pan-y';

    const cross = $('[data-cross]', host), tip = $('.chart-tip', host), dots = $$('[data-dot]', host);
    const show = ev => {
      const px = ev.clientX - host.getBoundingClientRect().left;
      const i = Math.max(0, Math.min(n - 1, Math.round((px - m.l) / iw * (n - 1))));
      const cx = x(i);
      cross.setAttribute('x1', cx); cross.setAttribute('x2', cx); cross.setAttribute('opacity', 1);
      dots.forEach((d, k) => { d.setAttribute('cx', cx); d.setAttribute('cy', y(series[k].values[i])); d.setAttribute('opacity', 1); });
      tip.innerHTML = `<b>${esc(labels[i])}</b>` +
        series.map(s => `<div><span><i style="background:${s.color}"></i>${esc(s.name)}</span><em>${rp(s.values[i])}</em></div>`).join('') +
        (series.length === 2 ? `<div><span>Selisih</span><em>${rp(series[0].values[i] - series[1].values[i])}</em></div>` : '');
      tip.style.opacity = 1;
      const tw = tip.offsetWidth;
      let left = cx + 14;
      if (left + tw > W) left = cx - tw - 14;
      tip.style.transform = `translate(${Math.max(0, left)}px, ${m.t}px)`;
    };
    const hide = () => {
      cross.setAttribute('opacity', 0);
      dots.forEach(d => d.setAttribute('opacity', 0));
      tip.style.opacity = 0;
    };
    host.onpointermove = show;
    host.onpointerdown = show;
    host.onpointerleave = hide;
  }

  function donut(items, total) {
    const r = 70, c = 2 * Math.PI * r, sw = 24, mid = 90;
    let off = 0;
    const segs = items.map(it => {
      const len = it.value / total * c;
      const dash = Math.max(.5, len - (items.length > 1 ? 3 : 0));
      const el = `<circle cx="${mid}" cy="${mid}" r="${r}" fill="none" stroke="${it.color}" stroke-width="${sw}" stroke-dasharray="${dash} ${c - dash}" stroke-dashoffset="${-off}" transform="rotate(-90 ${mid} ${mid})"><title>${esc(it.label)}: ${rp(it.value)}</title></circle>`;
      off += len;
      return el;
    }).join('');
    return `<svg viewBox="0 0 180 180" role="img" aria-label="Pengeluaran per kategori"><circle cx="${mid}" cy="${mid}" r="${r}" fill="none" style="stroke:var(--surface-3)" stroke-width="${sw}"/>${segs}<text class="t1" x="90" y="85" text-anchor="middle">Total</text><text class="t2" x="90" y="106" text-anchor="middle">${compact(total)}</text></svg>`;
  }

  function sparkline(values, color) {
    const w = 100, h = 36, n = values.length, max = Math.max(...values);
    if (max === 0) return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><line x1="0" x2="${w}" y1="${h - 3}" y2="${h - 3}" style="stroke:var(--line-2)" stroke-width="2" stroke-dasharray="3 4" vector-effect="non-scaling-stroke"/></svg>`;
    const line = smooth(values.map((v, i) => [i * w / (n - 1), h - 3 - (v / max) * (h - 9)]));
    return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><path d="${line} L${w},${h} L0,${h} Z" style="fill:${color};opacity:.13"/><path d="${line}" fill="none" style="stroke:${color}" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>`;
  }

  /* ============================ render: umum ============================ */
  const VIEWS = {
    dashboard: { title: 'Dashboard', sub: () => `${greeting()}, ${firstName}. Ini ringkasan keuanganmu${state.s ? ' — ' + state.s.period.label : ''}.` },
    transactions: { title: 'Transaksi', sub: () => 'Semua catatan pemasukan dan pengeluaranmu.' },
    budgets: { title: 'Anggaran', sub: () => 'Atur batas pengeluaran tiap kategori setiap bulan.' },
    analytics: { title: 'Analisis', sub: () => 'Gambaran pola keuanganmu' + (state.s ? ' — ' + state.s.period.label : '') + '.' },
    settings: { title: 'Pengaturan', sub: () => 'Kelola akun, tampilan, dan target tabunganmu.' },
  };

  function updateMeta() {
    const v = VIEWS[state.view];
    $('#pageTitle').textContent = v.title;
    $('#pageSub').textContent = v.sub();
    document.title = v.title + ' — CatatKan';
  }

  function renderUser() {
    const u = boot.user, ini = initials(u.name) || '?';
    ['#sideAvatar', '#topAvatar', '#setAvatar'].forEach(sel => { $(sel).textContent = ini; });
    $('#sideName').textContent = u.name; $('#sideEmail').textContent = u.email;
    $('#setName').textContent = u.name; $('#setEmail').textContent = u.email;
  }

  const emptyBlock = (emoji, title, text) => `<div class="empty"><span class="emoji">${emoji}</span><b>${esc(title)}</b>${esc(text)}</div>`;

  /* ============================ render: dashboard ============================ */
  function renderHero(s) {
    const bal = $('#heroBalance');
    if (state.hide) { bal.textContent = 'Rp ••••••••'; state.prev.balance = s.balance.total; }
    else setNumber(bal, 'balance', s.balance.total);
    $('#heroEye').innerHTML = ico(state.hide ? 'eye-off' : 'eye');
    $('#heroEye').setAttribute('aria-label', state.hide ? 'Tampilkan saldo' : 'Sembunyikan saldo');
    $('#heroPeriod').textContent = s.period.label;
    const d = s.balance.delta_pct;
    $('#heroDelta').innerHTML = d == null
      ? '<span class="pill neutral">Baru mulai</span>'
      : `<span class="pill ${d >= 0 ? 'good' : 'bad'}">${ico(d >= 0 ? 'arrow-up-right' : 'arrow-down-left')}${pct1(d)}%</span><span>dibanding bulan lalu</span>`;
    $('#heroIncome').textContent = rp(s.month.income);
    $('#heroExpense').textContent = rp(s.month.expense);
  }

  function deltaHTML(delta, upGood, tailUp, tailDown) {
    if (delta == null) return '<span class="pill neutral">Baru</span><span>Belum ada pembanding</span>';
    if (Math.abs(delta) < 0.05) return '<span class="pill neutral">0%</span><span>sama dengan periode sama bulan lalu</span>';
    const up = delta > 0, good = up === upGood;
    return `<span class="pill ${good ? 'good' : 'bad'}">${ico(up ? 'arrow-up-right' : 'arrow-down-left')}${pct1(delta)}%</span><span>${up ? tailUp : tailDown} dari periode sama bulan lalu</span>`;
  }

  function renderStats(s) {
    const mk = (el, key, o) => {
      el.innerHTML = `<div class="stat-head"><span class="eyebrow">${o.label}</span><span class="stat-icon ${o.cls}">${ico(o.icon)}</span></div>
        <div class="stat-value">${rp(state.prev[key] ?? 0)}</div>
        <div class="stat-foot">${deltaHTML(o.delta, o.upGood, o.tailUp, o.tailDown)}</div>
        <div class="spark">${sparkline(o.series, o.color)}</div>`;
      setNumber($('.stat-value', el), key, o.value);
    };
    mk($('#statIncome'), 'statIn', { label: 'Pemasukan bulan ini', icon: 'arrow-down-left', cls: '', value: s.month.income, delta: s.month.income_delta, upGood: true, tailUp: 'naik', tailDown: 'turun', color: 'var(--c-income)', series: s.spark.income });
    mk($('#statExpense'), 'statOut', { label: 'Pengeluaran bulan ini', icon: 'arrow-up-right', cls: 'out', value: s.month.expense, delta: s.month.expense_delta, upGood: false, tailUp: 'lebih boros', tailDown: 'lebih hemat', color: 'var(--c-expense)', series: s.spark.expense });
  }

  function renderCashflow() {
    if (!state.s) return;
    const cf = state.months === 6 ? state.s.cashflow.slice(-6) : state.s.cashflow;
    lineChart($('#cashflowChart'), cf.map(c => c.label), [
      { name: 'Pemasukan', color: 'var(--c-income)', values: cf.map(c => c.income) },
      { name: 'Pengeluaran', color: 'var(--c-expense)', values: cf.map(c => c.expense) },
    ]);
    const net = cf.reduce((a, c) => a + c.income - c.expense, 0);
    $('#cashflowMeta').textContent = `${state.months} bulan terakhir · ${net >= 0 ? 'surplus' : 'defisit'} ${rp(Math.abs(net))}`;
  }

  const budgetState = b => (b.pct >= 100 ? 'over' : b.pct >= 85 ? 'warn' : '');
  const leftText = (limit, spent) => (limit - spent >= 0 ? 'Sisa ' + rp(limit - spent) : 'Lebih ' + rp(spent - limit));

  function budgetRow(b) {
    const st = budgetState(b);
    return `<div class="budget-row">
      <div class="budget-top"><div class="budget-name"><span class="budget-emoji">${b.emoji}</span><span>${esc(b.category)}</span></div><small>${compact(b.spent)} / ${compact(b.limit)}</small></div>
      <div class="progress ${st}"><span data-w="${Math.min(100, b.pct)}"></span></div>
      <div class="budget-note"><span class="${st ? 'flag ' + st : ''}">${b.pct}% terpakai</span><span>${leftText(b.limit, b.spent)}</span></div>
    </div>`;
  }

  function renderDashBudgets(s) {
    const box = $('#dashBudgets');
    box.innerHTML = s.budgets.length
      ? `<div class="budget-list">${s.budgets.slice(0, 4).map(budgetRow).join('')}</div>`
      : emptyBlock('🎯', 'Belum ada anggaran', 'Atur batas pengeluaran di menu Anggaran.');
    fillBars(box);
  }

  function txRowHTML(t, actions = true) {
    const sign = t.type === 'income' ? '+' : '−';
    return `<div class="tx-row${actions ? '' : ' ro'}">
      <div class="tx-main"><span class="tx-ico" style="--c:${t.color}">${t.emoji}</span>
        <div class="tx-txt"><b>${esc(t.name)}</b><small>${esc(t.category)}${t.note ? ' · ' + esc(t.note) : ''}${actions ? '' : ' · ' + dateLabel(t.date)}</small></div></div>
      ${actions ? `<div class="tx-date">${dateLabel(t.date)}</div>` : ''}
      <div class="tx-amt ${t.type}">${sign} ${rp(t.amount)}</div>
      ${actions ? `<div class="tx-actions">
        <button class="icon-btn sm" data-act="edit" data-id="${t.id}" aria-label="Ubah ${esc(t.name)}">${ico('edit')}</button>
        <button class="icon-btn sm del" data-act="del" data-id="${t.id}" aria-label="Hapus ${esc(t.name)}">${ico('trash')}</button></div>` : ''}
    </div>`;
  }

  function renderRecent(s) {
    $('#dashRecent').innerHTML = s.recent.length
      ? s.recent.map(t => txRowHTML(t, false)).join('')
      : emptyBlock('🧾', 'Belum ada transaksi', 'Transaksi terbarumu akan muncul di sini.');
  }

  const INS_ICON = { warning: 'alert', positive: 'check', tip: 'bulb' };
  const insightHTML = i => `<div class="insight ${i.type}"><span class="ic">${ico(INS_ICON[i.type] || 'bulb')}</span><div><b>${esc(i.title)}</b><p>${esc(i.text)}</p></div></div>`;

  function renderInsights(s) {
    $('#dashInsights').innerHTML = `<div class="insights">${s.insights.slice(0, 2).map(insightHTML).join('')}</div>`;
  }

  function renderGoal(g) {
    $('#goalCard').innerHTML = `
      <div class="card-head"><div><h2>Target tabungan</h2><p>${esc(g.title)}</p></div><a class="link" href="#/settings">Ubah</a></div>
      <div class="goal">
        <div class="ring" style="--p:${g.pct}"><b>${g.pct}%</b></div>
        <div class="goal-info">
          <b>${rp(g.saved)} <span style="color:var(--muted);font-weight:600">/ ${rp(g.target)}</span></b>
          <p>${g.saved >= g.target ? 'Target tercapai — selamat! 🎉' : 'Tinggal ' + rp(g.target - g.saved) + ' lagi untuk mencapai targetmu.'}</p>
          <div class="goal-actions"><button class="btn btn-primary btn-sm" id="depositBtn">${ico('plus')}Tambah tabungan</button></div>
        </div>
      </div>`;
  }

  /* ============================ render: anggaran ============================ */
  function renderBudgets() {
    const s = state.s, list = s.budgets;
    const totalLimit = list.reduce((a, b) => a + b.limit, 0);
    const totalSpent = list.reduce((a, b) => a + b.spent, 0);
    const pct = totalLimit ? Math.round(totalSpent / totalLimit * 100) : 0;
    const st = budgetState({ pct });
    const addBtn = `<button class="btn btn-primary" data-act="budget-add">${ico('plus')}Tambah anggaran</button>`;

    $('#budgetTotal').innerHTML = list.length
      ? `<div class="bt-main"><span class="eyebrow">Total anggaran — ${esc(s.period.label)}</span>
          <div class="bt-num">${rp(totalSpent)} <small>/ ${rp(totalLimit)}</small></div>
          <div class="progress ${st}"><span data-w="${Math.min(100, pct)}"></span></div>
          <div class="budget-note"><span>${pct}% terpakai</span><span>${leftText(totalLimit, totalSpent)}</span></div></div>${addBtn}`
      : `<div class="bt-main"><b>Belum ada anggaran</b><p class="muted-p" style="margin:6px 0 0">Tetapkan batas pengeluaran per kategori agar kamu mendapat peringatan sebelum kebablasan.</p></div>${addBtn}`;

    const used = new Set(list.map(b => b.category));
    const hasFree = Object.keys(CATS.expense).some(n => !used.has(n));
    $('#budgetGrid').innerHTML = list.map(b => {
      const bs = budgetState(b);
      return `<article class="card budget-card">
        <div class="bc-head"><div class="budget-name"><span class="budget-emoji">${b.emoji}</span>${esc(b.category)}</div>
          <button class="icon-btn sm" data-act="budget-edit" data-cat="${esc(b.category)}" aria-label="Ubah anggaran ${esc(b.category)}">${ico('edit')}</button></div>
        <div class="bc-amt">${rp(b.spent)} <small>/ ${rp(b.limit)}</small></div>
        <div class="progress ${bs}"><span data-w="${Math.min(100, b.pct)}"></span></div>
        <div class="budget-note"><span class="${bs ? 'flag ' + bs : ''}">${b.pct}% terpakai</span><span>${leftText(b.limit, b.spent)}</span></div>
      </article>`;
    }).join('') + (hasFree ? `<button class="card budget-card add" data-act="budget-add"><span class="plus">${ico('plus')}</span>Tambah anggaran</button>` : '');
    fillBars($('#view-budgets'));
  }

  /* ============================ render: analisis ============================ */
  function renderTrend() {
    if (!state.s) return;
    const cf = state.s.cashflow;
    lineChart($('#trendChart'), cf.map(c => c.label), [
      { name: 'Pemasukan', color: 'var(--c-income)', values: cf.map(c => c.income) },
      { name: 'Pengeluaran', color: 'var(--c-expense)', values: cf.map(c => c.expense) },
    ]);
  }

  function renderAnalytics() {
    const s = state.s, st = s.stats;
    const kpi = (label, value, note) => `<article class="card kpi"><span class="eyebrow">${label}</span><div class="v">${value}</div><small>${note}</small></article>`;
    $('#kpis').innerHTML =
      kpi('Rasio tabungan', st.saving_rate == null ? '–' : Math.round(st.saving_rate) + '%', 'dari pemasukan bulan ini') +
      kpi('Rata-rata harian', rp(st.avg_daily), `selama ${s.period.days_elapsed} hari berjalan`) +
      kpi('Proyeksi pengeluaran', rp(st.projected_expense), 'perkiraan sampai akhir bulan') +
      kpi('Pengeluaran terbesar', st.biggest ? rp(st.biggest.amount) : '–', st.biggest ? esc(st.biggest.name) : 'Belum ada data');

    $('#donutPeriod').textContent = s.period.label;
    const cats = s.categories, total = cats.reduce((a, c) => a + c.amount, 0);
    $('#donutBox').innerHTML = total > 0
      ? `<div class="donut-wrap">${donut(cats.map(c => ({ label: c.category, value: c.amount, color: c.color })), total)}
          <div class="donut-legend">${cats.map(c => `<div><i style="background:${c.color}"></i><span>${c.emoji} ${esc(c.category)}</span><b>${rp(c.amount)}</b><em>${Math.round(c.pct)}%</em></div>`).join('')}</div></div>`
      : emptyBlock('📊', 'Belum ada pengeluaran', 'Grafik kategori muncul setelah ada transaksi bulan ini.');

    const max = Math.max(1, ...s.top_merchants.map(m => m.amount));
    $('#merchants').innerHTML = s.top_merchants.length
      ? s.top_merchants.map((m, i) => `<div class="merchant-row"><span class="rank">${i + 1}</span>
          <div class="mid"><b>${esc(m.name)}</b><div class="bar"><i style="width:${Math.max(4, m.amount / max * 100)}%"></i></div></div>
          <div class="amt">${rp(m.amount)}<small>${m.count}× transaksi</small></div></div>`).join('')
      : emptyBlock('🏪', 'Belum ada data merchant', 'Belum ada pengeluaran bulan ini.');

    $('#allInsights').innerHTML = `<div class="insights">${s.insights.map(insightHTML).join('')}</div>`;
    renderTrend();
  }

  /* ============================ render: pengaturan ============================ */
  function renderSettings() {
    const g = state.s.goal;
    if (!$('#goalForm').contains(document.activeElement)) {
      $('#goalTitle').value = g.title;
      $('#goalTarget').value = nf.format(g.target);
      $('#goalSaved').value = nf.format(g.saved);
    }
    markThemeSeg();
  }
  function markThemeSeg() {
    const mode = window.SWTheme ? window.SWTheme.get() : 'auto';
    $$('#themeSeg button').forEach(b => b.classList.toggle('on', b.dataset.mode === mode));
  }

  function renderAll() {
    const s = state.s;
    s.recent.forEach(t => state.items.set(String(t.id), t));
    $('#emptyState').hidden = s.has_data;
    renderHero(s); renderStats(s); renderCashflow(); renderDashBudgets(s);
    renderRecent(s); renderInsights(s); renderGoal(s.goal);
    renderBudgets(); renderAnalytics(); renderSettings(); updateMeta();
  }

  async function loadSummary() {
    try {
      state.s = await api('api/summary.php');
      renderAll();
    } catch (err) {
      if (err.status !== 401 && err.status !== 419) toast(err.message, 'error');
    }
  }

  /* ============================ transaksi (daftar) ============================ */
  let txReq = 0;
  async function loadTx() {
    const f = state.tx, p = new URLSearchParams();
    if (f.q) p.set('q', f.q);
    if (f.type) p.set('type', f.type);
    if (f.category) p.set('category', f.category);
    if (f.month) p.set('month', f.month);
    $('#exportBtn').href = 'api/transactions.php?' + new URLSearchParams([...p, ['action', 'export']]);
    p.set('page', String(f.page)); p.set('per_page', '10');

    const req = ++txReq;
    try {
      const d = await api('api/transactions.php?' + p);
      if (req !== txReq) return;
      f.page = d.page;
      d.items.forEach(t => state.items.set(String(t.id), t));
      $('#txSummary').innerHTML = `<span><b>${nf.format(d.total)}</b> transaksi</span><span>Pemasukan <b class="in">${rp(d.sum_income)}</b></span><span>Pengeluaran <b class="out">${rp(d.sum_expense)}</b></span>`;
      $('#txList').innerHTML = d.items.length
        ? d.items.map(t => txRowHTML(t, true)).join('')
        : emptyBlock('🔍', 'Tidak ada transaksi', f.q || f.type || f.category || f.month ? 'Coba ubah atau hapus filter pencarianmu.' : 'Tambahkan transaksi pertamamu dengan tombol +.');
      $('#txPager').innerHTML = d.pages > 1
        ? `<span>Halaman ${d.page} dari ${d.pages}</span><div class="pg">
            <button class="icon-btn sm" data-page="${d.page - 1}" ${d.page <= 1 ? 'disabled' : ''} aria-label="Sebelumnya">${ico('chev-left')}</button>
            <button class="icon-btn sm" data-page="${d.page + 1}" ${d.page >= d.pages ? 'disabled' : ''} aria-label="Berikutnya">${ico('chev-right')}</button></div>`
        : '';
    } catch (err) {
      if (err.status !== 401 && err.status !== 419) toast(err.message, 'error');
    }
  }

  async function refresh() {
    await loadSummary();
    if (state.view === 'transactions') await loadTx();
  }

  /* ============================ helper form & dialog ============================ */
  const clearErrs = form => {
    $$('.field.invalid', form).forEach(f => f.classList.remove('invalid'));
    $$('[data-err]', form).forEach(e => { e.textContent = ''; });
  };
  const showErrs = (form, errors) => {
    Object.entries(errors).forEach(([k, msg]) => {
      const slot = $(`[data-err="${k}"]`, form);
      if (slot) { slot.textContent = msg; const f = slot.closest('.field'); if (f) f.classList.add('invalid'); }
    });
  };
  const setBusy = (btn, busy) => { btn.disabled = busy; btn.classList.toggle('busy', busy); };
  const moneyVal = el => Number(el.value.replace(/\D/g, '')) || 0;
  const moneyInput = el => el.addEventListener('input', () => {
    const d = el.value.replace(/\D/g, '').slice(0, 13);
    el.value = d ? nf.format(Number(d)) : '';
  });
  ['#txAmount', '#budgetLimit', '#depositAmount', '#goalTarget', '#goalSaved'].forEach(sel => moneyInput($(sel)));

  $$('dialog.dlg').forEach(dlg => dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); }));

  function confirmBox({ title, text, ok = 'Hapus' }) {
    const d = $('#confirmDialog');
    $('#confirmTitle').textContent = title;
    $('#confirmText').textContent = text;
    $('#confirmOk').textContent = ok;
    return new Promise(resolve => {
      d.returnValue = '';
      d.addEventListener('close', () => resolve(d.returnValue === 'ok'), { once: true });
      d.showModal();
    });
  }

  /* ============================ dialog transaksi ============================ */
  const tf = { id: null, type: 'expense', category: '' };

  function renderChips() {
    if (!CATS[tf.type][tf.category]) tf.category = '';
    $('#txCats').innerHTML = Object.entries(CATS[tf.type]).map(([name, m]) =>
      `<button type="button" class="chip${name === tf.category ? ' on' : ''}" data-cat="${esc(name)}">${m.emoji} ${esc(name)}</button>`).join('');
  }
  function setTxType(type) {
    tf.type = type;
    $$('#txTypeSeg button').forEach(b => b.classList.toggle('on', b.dataset.type === type));
    renderChips();
  }

  function openTx(t) {
    clearErrs($('#txForm'));
    tf.id = t ? t.id : null;
    tf.category = t ? t.category : '';
    $('#txTitle').textContent = t ? 'Ubah transaksi' : 'Tambah transaksi';
    $('#txAmount').value = t ? nf.format(t.amount) : '';
    $('#txName').value = t ? t.name : '';
    $('#txDate').value = t ? t.date : isoOf(new Date());
    $('#txDate').max = isoOf(new Date());
    $('#txNote').value = t ? t.note : '';
    setTxType(t ? t.type : 'expense');
    $('#txDialog').showModal();
    setTimeout(() => $('#txAmount').focus(), 80);
  }

  $('#txTypeSeg').addEventListener('click', e => {
    const b = e.target.closest('button[data-type]');
    if (b && b.dataset.type !== tf.type) { tf.category = ''; setTxType(b.dataset.type); }
  });
  $('#txCats').addEventListener('click', e => {
    const c = e.target.closest('.chip');
    if (c) { tf.category = c.dataset.cat; renderChips(); }
  });

  $('#txForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget, btn = $('#txSubmit');
    clearErrs(form);
    const body = {
      name: $('#txName').value.trim(), amount: moneyVal($('#txAmount')), type: tf.type,
      category: tf.category, date: $('#txDate').value, note: $('#txNote').value.trim(),
    };
    const errs = {};
    if (!body.amount) errs.amount = 'Masukkan jumlah yang valid.';
    if (!body.name) errs.name = 'Nama transaksi wajib diisi.';
    if (!body.category) errs.category = 'Pilih salah satu kategori.';
    if (!body.date) errs.date = 'Pilih tanggal.';
    if (Object.keys(errs).length) return showErrs(form, errs);

    setBusy(btn, true);
    try {
      await api('api/transactions.php?action=' + (tf.id ? 'update' : 'create'), { method: 'POST', body: tf.id ? { ...body, id: tf.id } : body });
      $('#txDialog').close();
      toast(tf.id ? 'Transaksi diperbarui' : 'Transaksi ditambahkan');
      await refresh();
    } catch (err) {
      if (Object.keys(err.errors || {}).length) showErrs(form, err.errors); else toast(err.message, 'error');
    } finally { setBusy(btn, false); }
  });

  async function deleteTx(id) {
    const t = state.items.get(String(id));
    const ok = await confirmBox({ title: 'Hapus transaksi?', text: `“${t ? t.name : 'Transaksi ini'}” akan dihapus permanen.` });
    if (!ok) return;
    try {
      await api('api/transactions.php?action=delete', { method: 'POST', body: { id: Number(id) } });
      toast('Transaksi dihapus');
      await refresh();
    } catch (err) { toast(err.message, 'error'); }
  }

  /* ============================ dialog anggaran ============================ */
  const bf = { editing: null };

  function openBudget(cat) {
    const form = $('#budgetForm');
    clearErrs(form);
    const used = new Set(state.s.budgets.map(b => b.category));
    const names = Object.keys(CATS.expense).filter(n => (cat ? n === cat : !used.has(n)));
    if (!names.length) { toast('Semua kategori sudah punya anggaran.'); return; }
    bf.editing = cat || null;
    $('#budgetCat').innerHTML = names.map(n => `<option value="${esc(n)}">${CATS.expense[n].emoji} ${esc(n)}</option>`).join('');
    $('#budgetCat').disabled = !!cat;
    const cur = cat ? state.s.budgets.find(b => b.category === cat) : null;
    $('#budgetLimit').value = cur ? nf.format(cur.limit) : '';
    $('#budgetTitle').textContent = cat ? 'Ubah anggaran' : 'Tambah anggaran';
    $('#budgetDelete').hidden = !cat;
    $('#budgetDialog').showModal();
    setTimeout(() => $('#budgetLimit').focus(), 80);
  }

  $('#budgetForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget, btn = $('button[type="submit"]', form);
    clearErrs(form);
    const body = { category: $('#budgetCat').value, limit: moneyVal($('#budgetLimit')) };
    if (!body.limit) return showErrs(form, { limit: 'Masukkan batas anggaran.' });
    setBusy(btn, true);
    try {
      await api('api/budgets.php?action=save', { method: 'POST', body });
      $('#budgetDialog').close();
      toast('Anggaran disimpan');
      await refresh();
    } catch (err) {
      if (Object.keys(err.errors || {}).length) showErrs(form, err.errors); else toast(err.message, 'error');
    } finally { setBusy(btn, false); }
  });

  $('#budgetDelete').addEventListener('click', async () => {
    const cat = bf.editing;
    if (!cat) return;
    const ok = await confirmBox({ title: 'Hapus anggaran?', text: `Anggaran “${cat}” akan dihapus. Transaksimu tidak ikut terhapus.` });
    if (!ok) return;
    try {
      await api('api/budgets.php?action=delete', { method: 'POST', body: { category: cat } });
      $('#budgetDialog').close();
      toast('Anggaran dihapus');
      await refresh();
    } catch (err) { toast(err.message, 'error'); }
  });

  /* ============================ tabungan & target ============================ */
  function openDeposit() {
    clearErrs($('#depositForm'));
    $('#depositFor').textContent = 'Untuk: ' + state.s.goal.title;
    $('#depositAmount').value = '';
    $('#depositDialog').showModal();
    setTimeout(() => $('#depositAmount').focus(), 80);
  }

  $('#depositForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget, btn = $('button[type="submit"]', form);
    clearErrs(form);
    const amount = moneyVal($('#depositAmount'));
    if (!amount) return showErrs(form, { amount: 'Masukkan jumlah yang valid.' });
    setBusy(btn, true);
    try {
      await api('api/goal.php?action=deposit', { method: 'POST', body: { amount } });
      $('#depositDialog').close();
      toast('Tabungan ditambahkan');
      await refresh();
    } catch (err) {
      if (Object.keys(err.errors || {}).length) showErrs(form, err.errors); else toast(err.message, 'error');
    } finally { setBusy(btn, false); }
  });

  $('#goalForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget, btn = $('button[type="submit"]', form);
    clearErrs(form);
    const body = { title: $('#goalTitle').value.trim(), target: moneyVal($('#goalTarget')), saved: moneyVal($('#goalSaved')) };
    const errs = {};
    if (!body.title) errs.title = 'Nama target wajib diisi.';
    if (!body.target) errs.target = 'Masukkan target yang valid.';
    if (Object.keys(errs).length) return showErrs(form, errs);
    setBusy(btn, true);
    try {
      await api('api/goal.php?action=save', { method: 'POST', body });
      toast('Target tabungan disimpan');
      await refresh();
    } catch (err) {
      if (Object.keys(err.errors || {}).length) showErrs(form, err.errors); else toast(err.message, 'error');
    } finally { setBusy(btn, false); }
  });

  /* ============================ aksi lain ============================ */
  async function logout() {
    try { await api('api/auth.php?action=logout', { method: 'POST' }); } catch { /* tetap keluar */ }
    location.href = 'login.php';
  }
  $('#logoutBtn').addEventListener('click', logout);
  $('#logoutBtn2').addEventListener('click', logout);

  const resetBtn = $('#resetBtn');   // tidak dirender untuk akun demo
  if (resetBtn) resetBtn.addEventListener('click', async () => {
    const ok = await confirmBox({
      title: 'Hapus semua data?',
      text: 'Semua transaksi, anggaran, dan target tabunganmu akan dihapus permanen dan tidak bisa dikembalikan. Akunmu tetap ada.',
      ok: 'Ya, hapus semua',
    });
    if (!ok) return;
    setBusy(resetBtn, true);
    try {
      await api('api/reset.php', { method: 'POST' });
      state.items.clear();
      state.tx.page = 1;
      toast('Semua data dihapus');
      location.hash = '#/dashboard';
      await refresh();
    } catch (err) { toast(err.message, 'error'); }
    finally { setBusy(resetBtn, false); }
  });

  $('#heroEye').addEventListener('click', () => {
    state.hide = !state.hide;
    lsSet('sw-hide', state.hide ? '1' : '0');
    if (state.s) renderHero(state.s);
  });

  $('#themeBtn').addEventListener('click', () => {
    window.SWTheme.set(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    markThemeSeg();
  });
  $('#themeSeg').addEventListener('click', e => {
    const b = e.target.closest('button[data-mode]');
    if (b) { window.SWTheme.set(b.dataset.mode); markThemeSeg(); }
  });

  $('#periodSeg').addEventListener('click', e => {
    const b = e.target.closest('button[data-months]');
    if (!b) return;
    state.months = Number(b.dataset.months);
    $$('#periodSeg button').forEach(x => x.classList.toggle('on', x === b));
    renderCashflow();
  });

  /* ---- filter transaksi ---- */
  const allCats = [...new Set([...Object.keys(CATS.expense), ...Object.keys(CATS.income)])];
  $('#txCatFilter').insertAdjacentHTML('beforeend', allCats.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join(''));
  $('#txSearch').addEventListener('input', debounce(e => { state.tx.q = e.target.value.trim(); state.tx.page = 1; loadTx(); }, 300));
  $('#txTypeFilter').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    $$('#txTypeFilter button').forEach(x => x.classList.toggle('on', x === b));
    state.tx.type = b.dataset.type; state.tx.page = 1; loadTx();
  });
  $('#txCatFilter').addEventListener('change', e => { state.tx.category = e.target.value; state.tx.page = 1; loadTx(); });
  $('#txMonthFilter').addEventListener('change', e => { state.tx.month = e.target.value; state.tx.page = 1; loadTx(); });
  $('#txPager').addEventListener('click', e => {
    const b = e.target.closest('[data-page]');
    if (b && !b.disabled) { state.tx.page = Number(b.dataset.page); loadTx(); }
  });

  /* ---- delegasi klik global ---- */
  document.addEventListener('click', e => {
    const t = e.target;
    if (t.closest('[data-open-tx]')) return openTx();
    const close = t.closest('[data-close]');
    if (close) return close.closest('dialog').close();
    if (t.closest('#depositBtn')) return openDeposit();
    const act = t.closest('[data-act]');
    if (!act) return;
    switch (act.dataset.act) {
      case 'edit': { const item = state.items.get(String(act.dataset.id)); if (item) openTx(item); break; }
      case 'del': deleteTx(act.dataset.id); break;
      case 'budget-add': openBudget(null); break;
      case 'budget-edit': openBudget(act.dataset.cat); break;
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key.toLowerCase() !== 'n' || e.metaKey || e.ctrlKey || e.altKey) return;
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) || document.querySelector('dialog[open]')) return;
    e.preventDefault();
    openTx();
  });

  /* ================================ router ================================ */
  function route() {
    const raw = location.hash.replace(/^#\/?/, '') || 'dashboard';
    const view = VIEWS[raw] ? raw : 'dashboard';
    state.view = view;
    $$('.view').forEach(el => { el.hidden = el.id !== 'view-' + view; });
    $$('[data-nav]').forEach(a => {
      const on = a.dataset.nav === view;
      a.classList.toggle('active', on);
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    updateMeta();
    window.scrollTo(0, 0);
    if (view === 'dashboard') renderCashflow();
    if (view === 'analytics') renderTrend();
    if (view === 'transactions') loadTx();
    if (view === 'budgets' && state.s) fillBars($('#view-budgets'));
  }
  window.addEventListener('hashchange', route);
  window.addEventListener('resize', debounce(() => {
    if (state.view === 'dashboard') renderCashflow();
    if (state.view === 'analytics') renderTrend();
  }, 150));

  /* ================================ mulai ================================ */
  renderUser();
  route();
  loadSummary();
})();
