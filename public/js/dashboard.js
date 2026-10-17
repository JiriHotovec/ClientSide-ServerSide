// Asynchronous loading of measurements (Fetch API). Chart and table are rendered from ONE response (A3).
(() => {
  'use strict';

  const fmtTime = (iso) => new Date(iso).toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
  });
  // Formats a value with the dataset's display settings (decimals, unit position/spacing).
  const fmtValue = (v, d, withUnit = true) => {
    const n = new Intl.NumberFormat(undefined, { minimumFractionDigits: d.decimals, maximumFractionDigits: d.decimals }).format(v);
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

  // Chart.js configuration shared by the card chart and the enlarged (zoom) chart.
  function buildConfig(d, label, line) {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    return {
      type: d.display.chart_type,
      data: {
        labels: d.data.map((r) => r.measured_at),
        datasets: [{
          label, data: d.data.map((r) => r.value), spanGaps: false, // null => gap in the line, never 0
          borderColor: line, backgroundColor: line, borderWidth: d.display.chart_type === 'bar' ? 0 : 2.5, pointRadius: d.data.length > 100 ? 0 : 3, tension: 0.2,
        }],
      },
      options: {
        animation: reduce ? false : undefined, responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true }, tooltip: { callbacks: { title: (i) => fmtTime(i[0].label), label: (i) => (i.raw === null ? 'Missing value' : fmtValue(i.raw, d.display)) } } },
        scales: {
          x: { ticks: { maxTicksLimit: 8, callback(v) { return new Date(this.getLabelForValue(v)).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); } } },
          y: { min: d.display.y_min ?? undefined, max: d.display.y_max ?? undefined, title: { display: true, text: d.type.unit }, ticks: { callback: (v) => fmtValue(v, d.display, false) } },
        },
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
    let chart = null;
    let controller = null;
    let loadedKey = null;
    let last = null;
    const zoomBtn = q('[data-zoom]');

    async function load() {
      controller?.abort();
      controller = new AbortController();
      loadedKey = null;
      last = null;
      zoomBtn.hidden = true;
      status.textContent = 'Loading measurements…';
      status.setAttribute('aria-busy', 'true');
      warning.hidden = true;
      hideAll();

      const range = state.period === 'custom' ? `&from=${encodeURIComponent(state.from)}&to=${encodeURIComponent(state.to)}&tz=${state.tz}` : '';
      const url = `${root.dataset.endpoint}?period=${encodeURIComponent(state.period)}${range}&type=${encodeURIComponent(type)}`;
      try {
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
        if (!res.ok) {
          const body = res.status === 422 ? await res.json().catch(() => null) : null;
          if (body?.errors) throw new Error(Object.values(body.errors).flat()[0]);
          const msg = { 401: 'Please log in again.', 403: 'You are not allowed to view this device.', 404: 'This dataset or device is not available.' }[res.status]
            || 'The server could not deliver the data right now. Please try again later.';
          throw new Error(msg);
        }
        render(await res.json());
        loadedKey = state.key();
      } catch (err) {
        if (err.name === 'AbortError') return;
        status.textContent = err instanceof TypeError ? 'Could not reach the server. Check your connection and try again.' : err.message;
      } finally {
        status.removeAttribute('aria-busy');
      }
    }

    function render(d) {
      const label = `${d.type.name} (${d.type.unit})`;
      if (!d.source_status.ok) {
        warning.textContent = `The external data source is currently unavailable: ${d.source_status.message} Showing the last stored measurements.`;
        warning.hidden = false;
      }

      if (d.summary.count === 0) {
        status.textContent = `No measurements of ${d.type.name} in the selected period. Missing data is not shown as zero.`;
        return;
      }
      // Success needs no message: the chart speaks for itself. Only explain the case without any valid value.
      status.textContent = d.summary.count - d.summary.missing === 0 ? 'No valid values in this period.' : '';
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
      chart = new Chart(canvas, buildConfig(d, label, chartColors(d)));
      canvas.parentElement.classList.add('chart-box');
    }

    // Lowest / average / highest of the valid values; missing values are skipped, never counted as zero.
    function renderStats(d) {
      const vals = d.data.map((r) => r.value).filter((v) => v !== null);
      if (!vals.length) return;
      const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
      q('[data-stat="min"]').textContent = fmtValue(Math.min(...vals), d.display);
      q('[data-stat="avg"]').textContent = fmtValue(avg, d.display);
      q('[data-stat="max"]').textContent = fmtValue(Math.max(...vals), d.display);
      q('[data-stats]').hidden = false;
    }

    // Loads only when the card is visible and its data is missing or for another period (lazy for hidden tabs).
    return { ensure() { if (loadedKey !== state.key()) load(); }, data: () => last };
  }

  function init(root) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const groups = [...root.querySelectorAll('[data-group]')];
    if (!groups.length) return; // device without datasets
    const state = { period: '24h', from: '', to: '', tz: new Date().getTimezoneOffset(), key() { return this.period === 'custom' ? `custom:${this.from}:${this.to}` : this.period; } };
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
      zoom.querySelector('[data-zoom-canvas]').setAttribute('aria-label', `Enlarged chart of ${d.type.name}. Open the data table for exact values.`);
      const stats = zoom.querySelector('[data-zoom-stats]');
      stats.replaceChildren(...[['Lowest', 'min'], ['Average', 'avg'], ['Highest', 'max']].map(([t, k]) => {
        const div = el('div'); div.append(el('dt', t), el('dd', card.querySelector(`[data-stat="${k}"]`).textContent)); return div;
      }));
      zoom.showModal();
      zoomChart?.destroy();
      zoomChart = new Chart(zoom.querySelector('[data-zoom-canvas]'), buildConfig(d, label, chartColors(d)));
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
      if (!from || !to) { err.textContent = 'Choose both dates and times.'; return; }
      if (from >= to) { err.textContent = 'The start must be before the end.'; return; }
      if ((Date.parse(to) - Date.parse(from)) / 86400000 > 92) { err.textContent = 'Choose a range of at most 92 days.'; return; }
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
    if (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.textContent = on ? 'Shrink' : 'Enlarge'; }
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
