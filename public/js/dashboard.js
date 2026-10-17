// Asynchronous loading of measurements (Fetch API). Chart and table are rendered from ONE response (A3).
(() => {
  'use strict';

  const t = (k, v) => MV.t(k, v);
  const fmtTime = (iso) => new Date(iso).toLocaleString(MV.locale, {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
  });
  // Formats a value with the dataset's display settings (decimals, unit position/spacing).
  const fmtValue = (v, d, withUnit = true) => {
    const n = new Intl.NumberFormat(MV.locale, { minimumFractionDigits: d.decimals, maximumFractionDigits: d.decimals }).format(v);
    if (!withUnit || !d.unit || d.unit_position === 'none') return n;
    const sp = d.unit_space ? '\u00a0' : '';
    return d.unit_position === 'before' ? d.unit + sp + n : n + sp + d.unit;
  };
  const el = (tag, text, cls) => {
    const e = document.createElement(tag);
    if (text !== undefined) e.textContent = text;
    if (cls) e.className = cls;
    return e;
  };

  // Compass names of the 16 wind-rose sectors (north first).
  const COMPASS = {
    en: ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'],
    cs: ['S', 'SSV', 'SV', 'VSV', 'V', 'VJV', 'JV', 'JJV', 'J', 'JJZ', 'JZ', 'ZJZ', 'Z', 'ZSZ', 'SZ', 'SSZ'],
  };

  const validValues = (d) => d.data.map((r) => r.value).filter((v) => v !== null);

  // Chart.js configuration shared by the card chart and the enlarged (zoom) chart. `kind` is the chart style.
  function buildConfig(d, label, line, kind) {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const css = getComputedStyle(document.documentElement);
    const grid = css.getPropertyValue('--line').trim() || '#ddd';
    const base = { animation: reduce ? false : undefined, responsive: true, maintainAspectRatio: false };
    const shortTime = (ms) => new Date(ms).toLocaleString(MV.locale, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    if (kind === 'gauge') return gaugeConfig(d, line, grid, base);
    if (kind === 'rose') return roseConfig(d, line, base);

    const timeAxis = {
      x: { ticks: { maxTicksLimit: 8, callback(v) { return shortTime(kind === 'scatter' ? v : this.getLabelForValue(v)); } } },
      y: { min: d.display.y_min ?? undefined, max: d.display.y_max ?? undefined, title: { display: true, text: d.type.unit }, ticks: { callback: (v) => fmtValue(v, d.display, false) } },
    };
    const tooltip = {
      callbacks: {
        title: (i) => fmtTime(kind === 'scatter' ? i[0].parsed.x : i[0].label),
        label: (i) => { const v = i.parsed.y ?? i.raw; return v === null || v === undefined ? t('Missing value') : fmtValue(v, d.display); },
      },
    };
    const plugins = { legend: { display: true }, tooltip };
    const point = d.data.length > 100 ? 0 : 3;

    if (kind === 'scatter') { // missing values are simply not drawn
      return {
        type: 'scatter',
        data: { datasets: [{ label, data: d.data.filter((r) => r.value !== null).map((r) => ({ x: Date.parse(r.measured_at), y: r.value })), borderColor: line, backgroundColor: line, pointRadius: point || 2 }] },
        options: { ...base, plugins, scales: timeAxis },
      };
    }

    const asBar = kind === 'bar';
    return {
      type: asBar ? 'bar' : 'line',
      data: {
        labels: d.data.map((r) => r.measured_at),
        datasets: [{
          label, data: d.data.map((r) => r.value), spanGaps: false, // null => gap in the line, never 0
          borderColor: line, backgroundColor: kind === 'area' ? `${line}33` : line, fill: kind === 'area',
          stepped: kind === 'step' ? 'middle' : false, borderWidth: asBar ? 0 : 2.5, pointRadius: point, tension: kind === 'step' ? 0 : 0.2,
        }],
      },
      options: { ...base, plugins, scales: timeAxis },
    };
  }

  // Half-circle gauge of the latest valid value between the axis minimum and maximum (or the period's range).
  function gaugeConfig(d, line, grid, base) {
    const vals = validValues(d);
    const latest = d.summary.latest?.value ?? vals[vals.length - 1] ?? 0;
    const min = d.display.y_min ?? Math.min(...vals, latest);
    const max = d.display.y_max ?? Math.max(...vals, latest);
    const span = Math.max(max - min, 1e-9);
    const share = Math.min(Math.max((latest - min) / span, 0), 1);
    return {
      type: 'doughnut',
      data: { labels: [t('Value'), ''], datasets: [{ data: [share * span, (1 - share) * span], backgroundColor: [line, grid], borderWidth: 0 }] },
      options: { ...base, rotation: -90, circumference: 180, cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } },
      plugins: [{
        id: 'gaugeText',
        afterDraw(chart) {
          const arc = chart.getDatasetMeta(0).data[0];
          if (!arc) return;
          const { ctx } = chart;
          ctx.save();
          ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--ink').trim() || '#000';
          ctx.textAlign = 'center';
          ctx.font = `500 ${Math.round(arc.outerRadius / 3.2)}px ${getComputedStyle(document.body).fontFamily}`;
          ctx.fillText(fmtValue(latest, d.display), arc.x, arc.y - arc.outerRadius * 0.12);
          ctx.font = `400 ${Math.round(arc.outerRadius / 9)}px ${getComputedStyle(document.body).fontFamily}`;
          ctx.fillText(`${fmtValue(min, d.display, false)} – ${fmtValue(max, d.display)}`, arc.x, arc.y + arc.outerRadius * 0.12);
          ctx.restore();
        },
      }],
    };
  }

  // Wind rose: how often the wind (or any direction in degrees) came from each of 16 compass sectors.
  function roseConfig(d, line, base) {
    const counts = new Array(16).fill(0);
    validValues(d).forEach((deg) => { counts[Math.floor((((deg % 360) + 360) % 360) / 22.5 + 0.5) % 16] += 1; });
    const total = counts.reduce((a, b) => a + b, 0) || 1;
    const names = COMPASS[MV.locale] || COMPASS.en;
    return {
      type: 'polarArea',
      data: { labels: names, datasets: [{ data: counts, backgroundColor: `${line}99`, borderColor: line, borderWidth: 1 }] },
      options: {
        ...base,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (i) => `${i.label}: ${Math.round((i.raw / total) * 100)} %` } } },
        scales: { r: { ticks: { display: false }, angleLines: { display: true } } },
      },
    };
  }

  function chartColors(d) {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color = css.getPropertyValue('--muted').trim();
    Chart.defaults.borderColor = css.getPropertyValue('--line').trim();
    return d.display.color || css.getPropertyValue('--chart-line').trim() || '#0077b6';
  }

  // One card = one dataset (chart + table). The period is shared by all cards of the panel.
  function makeCard(root, card, state) {
    const q = (s) => card.querySelector(s);
    const status = q('[data-status]');
    const warning = q('[data-source-warning]');
    const hideAll = () => ['[data-stats]', '[data-chart-wrap]'].forEach((s) => { q(s).hidden = true; });
    const type = card.dataset.type;
    const kind = card.dataset.kind || null;
    let kindOf = (d) => kind || d.display.chart_type;
    let chart = null;
    let seq = 0; // ignores answers of outdated requests
    let loadedKey = null;
    let last = null;
    const zoomBtn = q('[data-zoom]');

    async function load() {
      const mine = ++seq;
      loadedKey = null;
      last = null;
      zoomBtn.hidden = true;
      status.textContent = t('Loading measurements…');
      status.setAttribute('aria-busy', 'true');
      warning.hidden = true;
      hideAll();

      try {
        const d = await state.series(root.dataset.endpoint, type);
        if (mine !== seq) return;
        render(d);
        loadedKey = state.key();
      } catch (err) {
        if (mine !== seq) return;
        status.textContent = err instanceof TypeError ? t('Could not reach the server. Check your connection and try again.') : err.message;
      } finally {
        if (mine === seq) status.removeAttribute('aria-busy');
      }
    }

    function render(d) {
      const label = `${d.type.name} (${d.type.unit})`;
      if (!d.source_status.ok) {
        warning.textContent = t('The external data source is currently unavailable: :message Showing the last stored measurements.', { message: d.source_status.message });
        warning.hidden = false;
      }

      if (d.summary.count === 0) {
        status.textContent = t('No measurements of :name in the selected period. Missing data is not shown as zero.', { name: d.type.name });
        return;
      }
      // Success needs no message: the chart speaks for itself. Only explain the case without any valid value.
      status.textContent = d.summary.count - d.summary.missing === 0 ? t('No valid values in this period.') : '';
      renderStats(d);
      last = d;
      zoomBtn.hidden = !window.Chart;
      renderChart(d, label);
    }

    function renderChart(d, label) {
      q('[data-chart-wrap]').hidden = false;
      const canvas = q('[data-chart]');
      chart?.destroy();
      if (!window.Chart) return; // the data table popup remains available as the text alternative
      chart = new Chart(canvas, buildConfig(d, label, chartColors(d), kindOf(d)));
      canvas.parentElement.classList.add('chart-box');
    }

    // Lowest / average / highest of the valid values; missing values are skipped, never counted as zero.
    function renderStats(d) {
      const vals = d.data.map((r) => r.value).filter((v) => v !== null);
      if (!vals.length || kindOf(d) === 'rose') return; // an average of compass degrees would be meaningless
      const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
      q('[data-stat="min"]').textContent = fmtValue(Math.min(...vals), d.display);
      q('[data-stat="avg"]').textContent = fmtValue(avg, d.display);
      q('[data-stat="max"]').textContent = fmtValue(Math.max(...vals), d.display);
      q('[data-stats]').hidden = false;
    }

    // Loads only when the card is visible and its data is missing or for another period (lazy for hidden tabs).
    return { ensure() { if (loadedKey !== state.key()) load(); }, data: () => last, kind: () => kindOf(last) };
  }

  async function fetchSeries(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) {
      const body = res.status === 422 ? await res.json().catch(() => null) : null;
      if (body?.errors) throw new Error(Object.values(body.errors).flat()[0]);
      throw new Error({ 401: t('Please log in again.'), 403: t('You are not allowed to view this device.'), 404: t('This dataset or device is not available.') }[res.status]
        || t('The server could not deliver the data right now. Please try again later.'));
    }
    return res.json();
  }

  function init(root) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const groups = [...root.querySelectorAll('[data-group]')];
    if (!groups.length) return; // device without datasets
    const state = {
      period: '24h', from: '', to: '', tz: new Date().getTimezoneOffset(),
      key() { return this.period === 'custom' ? `custom:${this.from}:${this.to}` : this.period; },
      cache: new Map(),
      // One request per dataset and period, shared by all charts of the same dataset.
      series(endpoint, type) {
        const k = `${this.key()}|${type}`;
        if (!this.cache.has(k)) {
          const range = this.period === 'custom' ? `&from=${encodeURIComponent(this.from)}&to=${encodeURIComponent(this.to)}&tz=${this.tz}` : '';
          const p = fetchSeries(`${endpoint}?period=${encodeURIComponent(this.period)}${range}&type=${encodeURIComponent(type)}`).catch((e) => { this.cache.delete(k); throw e; });
          this.cache.set(k, p);
        }
        return this.cache.get(k);
      },
    };
    const cards = new Map([...root.querySelectorAll('[data-card]')].map((c) => [c, makeCard(root, c, state)]));
    let active = groups[0];

    const ensureActive = () => active.querySelectorAll('[data-card]').forEach((c) => cards.get(c).ensure());
    const syncHidden = () => {
      const set = (n, v) => { const i = root.querySelector(`[data-period-state][name="${n}"]`); if (i) i.value = v; };
      set('tz', state.tz); set('period', state.period); set('from', state.period === 'custom' ? state.from : ''); set('to', state.period === 'custom' ? state.to : '');
    };

    // Group row (Weather, Air quality, ...): only the active group loads its charts.
    const row = root.querySelector('[data-group-menu]');
    row?.querySelectorAll('button[data-category]').forEach((btn) => btn.addEventListener('click', () => {
      row.querySelectorAll('button[data-category]').forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
      groups.forEach((g) => { g.hidden = g.dataset.group !== btn.dataset.category; });
      active = groups.find((g) => g.dataset.group === btn.dataset.category);
      const onMap = active.dataset.group === 'location';
      const controls = root.querySelector('.range-controls');
      if (controls) controls.hidden = onMap; // the time period means nothing for the map
      active.querySelectorAll('[data-static-map]').forEach((n) => document.dispatchEvent(new CustomEvent('meteo:map-visible', { detail: n })));
      ensureActive();
    }));

    // Enlarge a chart (magnifier): the same data is drawn again in a large dialog.
    const zoom = root.querySelector('[data-zoom-dialog]');
    let zoomChart = null;
    root.querySelectorAll('[data-zoom]').forEach((btn) => btn.addEventListener('click', () => {
      const card = btn.closest('[data-card]');
      const d = cards.get(card).data();
      if (!d || !window.Chart) return;
      const label = `${d.type.name} (${d.type.unit})`;
      zoom.querySelector('[data-zoom-title]').textContent = d.type.name;
      zoom.querySelector('[data-zoom-canvas]').setAttribute('aria-label', t('Enlarged chart of :name. Open the data table for exact values.', { name: d.type.name }));
      const stats = zoom.querySelector('[data-zoom-stats]');
      stats.replaceChildren(...[['Lowest', 'min'], ['Average', 'avg'], ['Highest', 'max']].map(([label2, k]) => {
        const div = el('div'); div.append(el('dt', t(label2)), el('dd', card.querySelector(`[data-stat="${k}"]`).textContent)); return div;
      }));
      zoom.showModal();
      zoomChart?.destroy();
      zoomChart = new Chart(zoom.querySelector('[data-zoom-canvas]'), buildConfig(d, label, chartColors(d), cards.get(card).kind()));
      zoom.querySelector('[data-zoom-title]').focus();
    }));
    zoom?.addEventListener('close', () => { zoomChart?.destroy(); zoomChart = null; });

    // Period: presets and a custom date range.
    const form = root.querySelector('[data-range-form]');
    // Defaults: last 7 days in the viewer's local time (datetime-local expects "YYYY-MM-DDTHH:MM").
    const localInput = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    if (form) {
      form.elements.to.value = localInput(new Date());
      form.elements.from.value = localInput(new Date(Date.now() - 7 * 86400000));
    }
    syncHidden();
    const customBtn = root.querySelector('[data-period="custom"]');
    const mark = (btn) => root.querySelectorAll('[data-period]').forEach((b) => {
      const on = b === btn;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('outline', !on);
    });
    root.querySelectorAll('[data-period]').forEach((btn) => btn.addEventListener('click', () => {
      if (btn.dataset.period === 'custom') {
        const open = form.hidden;
        form.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) form.querySelector('input').focus();
        return;
      }
      form.hidden = true;
      customBtn.setAttribute('aria-expanded', 'false');
      state.period = btn.dataset.period;
      mark(btn); syncHidden(); ensureActive();
    }));
    form?.addEventListener('submit', (e) => {
      e.preventDefault();
      const err = form.querySelector('[data-range-error]');
      const from = form.elements.from.value;
      const to = form.elements.to.value;
      err.textContent = '';
      if (!from || !to) { err.textContent = t('Choose both dates and times.'); return; }
      if (from >= to) { err.textContent = t('The start must be before the end.'); return; }
      if ((Date.parse(to) - Date.parse(from)) / 86400000 > 92) { err.textContent = t('Choose a range of at most 92 days.'); return; }
      Object.assign(state, { period: 'custom', from, to });
      form.hidden = true;
      customBtn.setAttribute('aria-expanded', 'false');
      customBtn.focus();
      mark(customBtn); syncHidden(); ensureActive();
    });
    ensureActive();
  }

  // Data table popup: htmx fills the dialog, then we open it as a modal (focus is restored to the button on close).
  document.addEventListener('htmx:afterSwap', (e) => {
    const body = e.target.closest?.('[data-dialog-body]') || (e.target.matches?.('[data-dialog-body]') ? e.target : null);
    if (!body) return;
    const dialog = body.closest('dialog');
    if (!dialog.open) dialog.showModal();
    localizeTimes(body);
    syncExpand(dialog);
    body.querySelector('h2')?.focus();
  });
  document.addEventListener('click', (e) => {
    if (e.target.matches?.('dialog[data-dialog]')) e.target.close(); // click on the backdrop
    // The custom range floats above the content: close it on outside click or Escape.
    document.querySelectorAll('[data-range-form]:not([hidden])').forEach((f) => {
      if (!f.parentElement.contains(e.target)) closeRange(f);
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('[data-range-form]:not([hidden])').forEach((f) => { closeRange(f); f.parentElement.querySelector('[data-period="custom"]').focus(); });
  });
  function closeRange(f) {
    f.hidden = true;
    f.parentElement.querySelector('[data-period="custom"]').setAttribute('aria-expanded', 'false');
  }
  // "Enlarge" makes the popup nearly full screen; the choice survives table reloads inside the dialog.
  const syncExpand = (dialog) => {
    const on = dialog.classList.contains('is-wide');
    const b = dialog.querySelector('[data-expand]');
    if (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.textContent = on ? t('Shrink') : t('Enlarge'); }
  };
  document.addEventListener('click', (e) => {
    const b = e.target.closest?.('[data-expand]');
    if (!b) return;
    const dialog = b.closest('dialog');
    dialog.classList.toggle('is-wide');
    syncExpand(dialog);
  });
  const localizeTimes = (scope) => scope.querySelectorAll('time[data-local]').forEach((t) => { t.textContent = fmtTime(t.dateTime); });

  const initAll = (scope = document) => scope.querySelectorAll('[data-meteo]').forEach(init);
  document.addEventListener('DOMContentLoaded', () => initAll());
  document.addEventListener('htmx:load', (e) => initAll(e.target));

  // Device switcher on the personal dashboard (htmx): request /dashboard/devices/{selected id}.
  document.addEventListener('htmx:configRequest', (e) => {
    const t = e.detail.elt;
    if (t && t.id === 'device-switch') {
      e.detail.path = `${t.dataset.base}/${encodeURIComponent(t.value)}`;
      e.detail.parameters = {};
    }
  });
})();
