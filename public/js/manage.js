// Administration: all data changes go through the REST API (fetch + JSON). Lists/filters/pagination are server-rendered and enhanced with htmx.
(() => {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
  const KEY = 'meteoview-notice';
  const say = (msg) => { const s = document.querySelector('[data-manage-status]'); if (s) s.textContent = msg; };
  const flash = (msg) => { try { sessionStorage.setItem(KEY, msg); } catch (e) { /* storage unavailable */ } };
  const showFlash = () => {
    try { const m = sessionStorage.getItem(KEY); if (m) { sessionStorage.removeItem(KEY); say(m); } } catch (e) { /* ignore */ }
  };

  async function api(method, url, body) {
    const res = await fetch(url, {
      method, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const json = res.status === 204 ? null : await res.json().catch(() => null);
    if (!res.ok) {
      const errors = json?.errors ? Object.values(json.errors).flat() : [json?.message || `Request failed (${res.status}).`];
      const e = new Error(errors[0]); e.errors = errors; e.status = res.status; throw e;
    }
    return json;
  }

  const blank = (v) => v === undefined || v === null || String(v).trim() === '';
  const num = (v) => (blank(v) ? null : Number(v));

  // Collects the display fields of one container. For per-device overrides, empty values and unchanged colors are skipped (= inherit).
  function readDisplay(box, overrides) {
    const out = {};
    box.querySelectorAll('[data-field]').forEach((el) => {
      const f = el.dataset.field; let v = el.value;
      if (overrides && (blank(v) || (f === 'color' && v === el.dataset.default))) return;
      if (['decimals', 'y_min', 'y_max'].includes(f)) v = num(v);
      else if (f === 'unit_space') v = blank(v) ? null : v === '1';
      else if (blank(v)) v = null;
      out[f] = v;
    });
    return out;
  }

  function deviceBody(form) {
    const f = new FormData(form);
    const text = (k) => (f.get(k) || '').toString().trim() || null;
    const types = f.getAll('types[]').map(Number);
    const type_settings = {};
    types.forEach((id) => {
      const box = form.querySelector(`[data-type-settings="${id}"]`);
      if (box) type_settings[id] = readDisplay(box, true);
    });
    const body = {
      name: f.get('name'), location: text('location'), description: text('description'),
      is_public: f.has('is_public'), types, type_settings,
      source_url: text('source_url'), fetch_interval_min: Number(f.get('fetch_interval_min')) || 15,
    };
    const mapping = text('source_mapping');
    body.source_mapping = mapping ? JSON.parse(mapping) : null; // SyntaxError handled by caller
    return body;
  }

  function typeBody(form) {
    const f = new FormData(form);
    const body = { name: f.get('name'), description: blank(f.get('description')) ? null : f.get('description'), ...readDisplay(form, false) };
    if (form.querySelector('[name="is_public"]')) body.is_public = f.has('is_public');
    if (!form.dataset.id) body.key = f.get('key');
    return body;
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-action]');
    if (!form) return;
    e.preventDefault();
    const errBox = form.querySelector('[data-form-errors]');
    errBox.textContent = '';
    const id = form.dataset.id;
    try {
      if (form.dataset.action === 'save-device') {
        const r = await api(id ? 'PUT' : 'POST', id ? `/api/devices/${id}` : '/api/devices', deviceBody(form));
        flash(`Device "${r.data.name}" was ${id ? 'updated' : 'created'}.`);
      } else if (form.dataset.action === 'save-type') {
        const r = await api(id ? 'PUT' : 'POST', id ? `/api/measurement-types/${id}` : '/api/measurement-types', typeBody(form));
        flash(`Measurement type "${r.data.name}" was ${id ? 'updated' : 'created'}.`);
      } else return;
      location.href = form.dataset.redirect;
    } catch (err) {
      errBox.textContent = err instanceof SyntaxError ? 'The mapping is not valid JSON.' : err.errors?.join(' ') ?? err.message;
      errBox.scrollIntoView({ block: 'nearest' });
    }
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn || btn.type === 'submit') return;
    const { action, id } = btn.dataset;
    try {
      if (action === 'visibility') {
        const d = await api('PATCH', `/api/devices/${id}/visibility`, { is_public: btn.dataset.public === '1' });
        flash(`"${d.data.name}" is now ${d.data.is_public ? 'public' : 'private'}.`); location.reload();
      } else if (action === 'delete') {
        if (!confirm(`Delete device "${btn.dataset.name}" and all its measurements? This cannot be undone.`)) return;
        await api('DELETE', `/api/devices/${id}`);
        flash(`Device "${btn.dataset.name}" was deleted.`); location.reload();
      } else if (action === 'delete-type') {
        if (!confirm(`Delete measurement type "${btn.dataset.name}"?`)) return;
        await api('DELETE', `/api/measurement-types/${id}`);
        flash(`Measurement type "${btn.dataset.name}" was deleted.`); location.reload();
      } else if (action === 'token') {
        if (!confirm('Generate a new ingest token? The previous token stops working.')) return;
        const r = await api('POST', `/api/devices/${id}/token`);
        say(`New token (shown only once, copy it now): ${r.token}. Send readings with "Authorization: Bearer <token>" to POST /api/ingest.`);
      }
    } catch (err) { say(err.errors?.join(' ') ?? err.message); }
  });

  // Live preview of "21.4 °C" while editing display defaults.
  function preview(form) {
    const out = form.querySelector('[data-preview]');
    if (!out) return;
    const d = readDisplay(form, false);
    const n = (21.456).toFixed(Math.min(Math.max(d.decimals ?? 1, 0), 6));
    const unit = d.unit || '';
    const sp = d.unit_space === false ? '' : ' ';
    out.textContent = !unit || d.unit_position === 'none' ? n : d.unit_position === 'before' ? unit + sp + n : n + sp + unit;
    out.style.color = d.color || '';
  }
  document.addEventListener('input', (e) => { const f = e.target.closest('form[data-action="save-type"]'); if (f) preview(f); });
  document.addEventListener('change', (e) => { const f = e.target.closest('form[data-action="save-type"]'); if (f) preview(f); });

  // Color swatches in lists (set via CSSOM, which the strict CSP allows).
  const paint = (scope = document) => scope.querySelectorAll('[data-color]').forEach((el) => { el.style.backgroundColor = el.dataset.color; });

  // Open a device's display settings automatically when its dataset gets ticked.
  document.addEventListener('change', (e) => {
    const t = e.target.closest('[data-type-toggle]');
    if (t && !t.checked) t.closest('.type-pick')?.querySelector('details')?.removeAttribute('open');
  });

  const boot = (scope = document) => { paint(scope); scope.querySelectorAll?.('form[data-action="save-type"]').forEach(preview); };
  document.addEventListener('DOMContentLoaded', () => { showFlash(); boot(); });
  document.addEventListener('htmx:afterSettle', (e) => { paint(e.target); });
})();
