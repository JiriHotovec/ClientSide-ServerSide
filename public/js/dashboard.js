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

  function init(root) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const q = (s) => root.querySelector(s);
    const typeSelect = q('[data-control="type"]');
    if (!typeSelect) return; // device without datasets
    const state = { period: '24h', chart: null, controller: null };

    const status = q('[data-status]');
    const warning = q('[data-source-warning]');
    const hideAll = () => ['[data-current]', '[data-chart-wrap]', '[data-table-wrap]'].forEach((s) => { q(s).hidden = true; });

    async function load() {
      state.controller?.abort();
      state.controller = new AbortController();
      status.textContent = 'Loading measurements…';
      status.setAttribute('aria-busy', 'true');
      warning.hidden = true;
      hideAll();

      const url = `${root.dataset.endpoint}?period=${encodeURIComponent(state.period)}&type=${encodeURIComponent(typeSelect.value)}`;
      try {
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: state.controller.signal });
        if (!res.ok) {
          const msg = { 401: 'Please log in again.', 403: 'You are not allowed to view this device.', 404: 'This dataset or device is not available.' }[res.status]
            || 'The server could not deliver the data right now. Please try again later.';
          throw new Error(msg);
        }
        render(await res.json());
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
      const present = d.summary.count - d.summary.missing;
      status.textContent = `${d.summary.count} readings, ${d.summary.missing} missing, from ${fmtTime(d.from)} to ${fmtTime(d.to)}.`;
      if (present === 0) status.textContent += ' No valid values in this period.';

      const cur = q('[data-current]');
      if (d.summary.latest) {
        q('[data-current-value]').textContent = fmtValue(d.summary.latest.value, d.display);
        q('[data-current-time]').textContent = `(${fmtTime(d.summary.latest.measured_at)}, source: ${d.summary.latest.source})`;
        cur.hidden = false;
      }

      renderChart(d, label);
      renderTable(d, label);
    }

    function renderChart(d, label) {
      q('[data-chart-wrap]').hidden = false;
      const canvas = q('[data-chart]');
      state.chart?.destroy();
      const css = getComputedStyle(document.documentElement);
      const line = d.display.color || css.getPropertyValue('--chart-line').trim() || '#0077b6';
      if (window.Chart) { Chart.defaults.color = css.getPropertyValue('--muted').trim(); Chart.defaults.borderColor = css.getPropertyValue('--line').trim(); }
      if (!window.Chart) return; // table remains available as the text alternative
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      state.chart = new Chart(canvas, {
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
      });
      canvas.parentElement.classList.add('chart-box');
    }

    function renderTable(d, label) {
      q('[data-caption]').textContent = `${label} – last ${d.period === '24h' ? '24 hours' : '7 days'}, newest first`;
      const tbody = q('[data-rows]');
      tbody.replaceChildren(...[...d.data].reverse().map((r) => {
        const tr = el('tr');
        tr.append(el('td', fmtTime(r.measured_at)));
        tr.append(r.value === null ? el('td', 'Missing value', 'missing') : el('td', fmtValue(r.value, d.display, false)));
        tr.append(el('td', r.unit), el('td', r.source));
        return tr;
      }));
      q('[data-table-wrap]').hidden = false;
    }

    typeSelect.addEventListener('change', load);
    root.querySelectorAll('[data-period]').forEach((btn) => btn.addEventListener('click', () => {
      state.period = btn.dataset.period;
      root.querySelectorAll('[data-period]').forEach((b) => {
        const on = b === btn;
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.classList.toggle('outline', !on);
      });
      load();
    }));
    load();
  }

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
