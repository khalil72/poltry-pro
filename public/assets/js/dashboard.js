(function () {
  'use strict';
  var grid = document.getElementById('statGrid');
  if (!grid) return;
  var form = document.getElementById('dashFilters');
  var status = document.getElementById('dashStatus');
  var charts = {};
  var last = null;

  function dark() { return document.documentElement.getAttribute('data-bs-theme') === 'dark'; }
  // Validated with the dataviz palette checker: fixed order, one set per theme.
  function palette() { return dark() ? ['#2FA070', '#BE8A1C', '#5A8AD0'] : ['#1F7A52', '#D08A0B', '#3B6FB6']; }
  function ink() { return dark() ? '#aeb9b4' : '#5d6a64'; }
  function gridColor() { return dark() ? 'rgba(255,255,255,.07)' : 'rgba(18,56,43,.07)'; }

  function n(v) { return Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
  function compact(v) { return Number(v).toLocaleString(undefined, { notation: 'compact', maximumFractionDigits: 1 }); }

  function tile(icon, label, value, note, cls) {
    var d = document.createElement('div');
    d.className = 'stat ' + (cls || '');
    var l = document.createElement('div'); l.className = 'stat-label';
    var i = document.createElement('i'); i.className = 'bi ' + icon; i.setAttribute('aria-hidden', 'true');
    l.appendChild(i); l.appendChild(document.createTextNode(label));
    var v = document.createElement('div'); v.className = 'stat-value'; v.textContent = value;
    d.appendChild(l); d.appendChild(v);
    if (note) { var o = document.createElement('div'); o.className = 'stat-note'; o.textContent = note; d.appendChild(o); }
    return d;
  }

  function renderStats(s, cur) {
    grid.innerHTML = '';
    var add = function () { grid.appendChild(tile.apply(null, arguments)); };
    add('bi-buildings', 'Total farms', n(s.farms));
    add('bi-house-door', 'Total sheds', n(s.sheds));
    add('bi-feather', 'Total birds', n(s.birds));
    add('bi-collection', 'Active batches', n(s.active_batches));
    add('bi-people', 'Customers', n(s.customers));
    add('bi-truck', 'Suppliers', n(s.suppliers));
    add('bi-box-seam', 'Feed stock', n(s.feed_kg) + ' kg');
    add('bi-egg', 'Eggs today', n(s.eggs_today), n(s.eggs_stock) + ' good eggs in stock');
    add('bi-heartbreak', 'Mortality rate', s.mortality_pct + '%', 'Active batches: dead ÷ initial birds');
    if (grid.dataset.money === '1') {
      add('bi-receipt', 'Total sales', cur + ' ' + n(s.sales), 'In selected period, before tax');
      add('bi-wallet2', 'Total expenses', cur + ' ' + n(s.expenses), 'Operating. Direct costs: ' + n(s.direct_costs));
      add('bi-graph-up-arrow', 'Net profit', cur + ' ' + n(s.net_profit), 'In selected period', s.net_profit < 0 ? 'stat-loss' : '');
    }
  }

  function roundBar(ds) { return Object.assign({ borderRadius: 4, borderSkipped: 'start', maxBarThickness: 26, borderWidth: 0 }, ds); }

  function draw(id, type, labels, datasets, opts) {
    var el = document.getElementById(id);
    if (!el) return;
    opts = opts || {};
    var config = {
        responsive: true, maintainAspectRatio: false, animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: datasets.length > 1, position: 'top', align: 'end', labels: { color: ink(), boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' } },
          tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + Number(c.parsed.y).toLocaleString(undefined, { maximumFractionDigits: 2 }); } } }
        },
        scales: {
          x: { stacked: !!opts.stacked, grid: { display: false }, ticks: { color: ink(), maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
          y: { stacked: !!opts.stacked, beginAtZero: true, grid: { color: gridColor() }, border: { display: false }, ticks: { color: ink(), callback: compact, maxTicksLimit: 6 } }
        }
    };
    if (charts[id]) {
      charts[id].data = { labels: labels, datasets: datasets };
      charts[id].options = config;
      charts[id].update('none');
    } else {
      charts[id] = new Chart(el, { type: type, data: { labels: labels, datasets: datasets }, options: config });
    }
  }

  function renderCharts(c) {
    var p = palette();
    if (c.revenue) {
      draw('cRevenue', 'bar', c.labels, [roundBar({ label: 'Revenue', data: c.revenue, backgroundColor: p[0] })]);
      draw('cExpenses', 'bar', c.labels, [roundBar({ label: 'Operating expenses', data: c.opex, backgroundColor: p[0] }), roundBar({ label: 'Direct costs', data: c.cogs, backgroundColor: p[1] })], { stacked: true });
      draw('cSalesPurchases', 'bar', c.labels, [roundBar({ label: 'Sales', data: c.sales_total, backgroundColor: p[0] }), roundBar({ label: 'Purchases', data: c.purchases_total, backgroundColor: p[1] })]);
    }
    draw('cEggs', 'line', c.eggs.labels, [
      { label: 'Good eggs', data: c.eggs.good, borderColor: p[0], backgroundColor: p[0], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: .25 },
      { label: 'Damaged and broken', data: c.eggs.bad, borderColor: p[1], backgroundColor: p[1], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: .25 }
    ]);
    draw('cMortality', 'bar', c.labels, [roundBar({ label: 'Dead birds', data: c.mortality, backgroundColor: p[0] })]);
    draw('cFeed', 'bar', c.labels, [roundBar({ label: 'Feed used (kg)', data: c.feed, backgroundColor: p[0] })]);
    draw('cBatches', 'bar', c.batches.labels, [roundBar({ label: 'Birds placed', data: c.batches.initial, backgroundColor: p[0] }), roundBar({ label: 'Birds now', data: c.batches.current, backgroundColor: p[1] })]);
  }

  function load() {
    var q = new URLSearchParams(new FormData(form)).toString();
    status.textContent = 'Loading…';
    fetch(grid.dataset.url + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Request failed'); return j; }); })
      .then(function (j) { last = j; renderStats(j.stats, j.currency); renderCharts(j.charts); status.textContent = ''; })
      .catch(function (e) { status.textContent = 'The figures could not be loaded: ' + e.message; });
  }

  form.addEventListener('submit', function (ev) { ev.preventDefault(); load(); });
  document.addEventListener('pp:theme', function () { if (last) renderCharts(last.charts); });
  load();
})();
