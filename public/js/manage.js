// Workspace enhancements. Data changes are plain server-side forms enhanced with htmx (see the Blade views);
// this file only adds small UI behaviour.
(() => {
  'use strict';

  const blank = (v) => v === undefined || v === null || String(v).trim() === '';

  // Live preview of "21.4 °C" while editing the display defaults of a measurement type.
  function preview(form) {
    const out = form.querySelector('[data-preview]');
    if (!out) return;
    const v = (f) => form.querySelector(`[data-field="${f}"]`)?.value;
    const decimals = Math.min(Math.max(parseInt(v('decimals'), 10) || 0, 0), 6);
    const n = (21.456).toFixed(decimals);
    const unit = v('unit') || '';
    const sp = v('unit_space') === '0' ? '' : ' ';
    const pos = v('unit_position');
    out.textContent = !unit || pos === 'none' ? n : pos === 'before' ? unit + sp + n : n + sp + unit;
    out.style.color = blank(v('color')) ? '' : v('color');
  }
  ['input', 'change'].forEach((t) => document.addEventListener(t, (e) => {
    const f = e.target.closest('[data-type-form]');
    if (f) preview(f);
  }));

  // Color swatches in lists (set via CSSOM, which the strict CSP allows).
  const paint = (scope = document) => scope.querySelectorAll('[data-color]').forEach((el) => { el.style.backgroundColor = el.dataset.color; });

  // Collapse a dataset's display settings when it gets unticked.
  document.addEventListener('change', (e) => {
    const t = e.target.closest('[data-type-toggle]');
    if (t && !t.checked) t.closest('.type-pick')?.querySelector('details')?.removeAttribute('open');
  });

  // Account menu: close on Escape (focus back to the trigger) and on outside click.
  document.addEventListener('keydown', (e) => {
    const m = document.querySelector('[data-user-menu][open]');
    if (e.key === 'Escape' && m) { m.removeAttribute('open'); m.querySelector('summary').focus(); }
  });
  document.addEventListener('click', (e) => {
    const m = document.querySelector('[data-user-menu][open]');
    if (m && !m.contains(e.target)) m.removeAttribute('open');
  });

  // Chart editor: move or remove a row (the order of the rows is the order of the charts).
  document.addEventListener('click', (e) => {
    const row = e.target.closest('[data-chart-row]');
    if (!row) return;
    if (e.target.closest('[data-chart-remove]')) { row.remove(); return; }
    const move = e.target.closest('[data-chart-move]');
    if (!move) return;
    const sibling = move.dataset.chartMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    move.dataset.chartMove === 'up' ? sibling.before(row) : sibling.after(row);
    move.focus();
  });

  const boot = (scope = document) => { paint(scope); scope.querySelectorAll('[data-type-form]').forEach(preview); };
  document.addEventListener('DOMContentLoaded', () => boot());
  document.addEventListener('htmx:afterSettle', (e) => boot(e.target.isConnected ? e.target : document));
})();
