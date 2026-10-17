// Device management: all changes go through the REST API (fetch + JSON); lists/forms are htmx fragments.
(() => {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
  const say = (msg) => { const s = document.querySelector('[data-manage-status]'); if (s) s.textContent = msg; };

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

  const refreshList = () => htmx.trigger('#device-list', 'refresh');
  const resetForm = () => htmx.trigger('#device-form', 'reset-form');

  function deviceBody(form) {
    const f = new FormData(form);
    const text = (k) => (f.get(k) || '').toString().trim() || null;
    const body = {
      name: f.get('name'), location: text('location'), description: text('description'),
      is_public: f.has('is_public'), types: f.getAll('types[]').map(Number),
      source_url: text('source_url'), fetch_interval_min: Number(f.get('fetch_interval_min')) || 15,
    };
    const mapping = text('source_mapping');
    if (mapping) body.source_mapping = JSON.parse(mapping); // SyntaxError handled by caller
    else body.source_mapping = null;
    return body;
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-action]');
    if (!form) return;
    e.preventDefault();

    if (form.dataset.action === 'save-device') {
      const errBox = form.querySelector('[data-form-errors]');
      errBox.textContent = '';
      try {
        const id = form.dataset.id;
        const dev = await api(id ? 'PUT' : 'POST', id ? `/api/devices/${id}` : '/api/devices', deviceBody(form));
        say(`Device "${dev.data.name}" was ${id ? 'updated' : 'created'}.`);
        refreshList(); resetForm();
      } catch (err) {
        errBox.textContent = err instanceof SyntaxError ? 'The mapping is not valid JSON.' : err.errors?.join(' ') ?? err.message;
      }
    }

    if (form.dataset.action === 'create-type') {
      const f = Object.fromEntries(new FormData(form));
      try {
        const t = await api('POST', '/api/measurement-types', f);
        say(`Measurement type "${t.data.name}" was created and is now selectable in the device form.`);
        form.reset(); resetForm();
      } catch (err) { say(err.errors?.join(' ') ?? err.message); }
    }
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn || btn.type === 'submit') return;
    const { action, id } = btn.dataset;
    try {
      if (action === 'visibility') {
        const d = await api('PATCH', `/api/devices/${id}/visibility`, { is_public: btn.dataset.public === '1' });
        say(`"${d.data.name}" is now ${d.data.is_public ? 'public' : 'private'}.`);
        refreshList();
      } else if (action === 'delete') {
        if (!confirm(`Delete device "${btn.dataset.name}" and all its measurements? This cannot be undone.`)) return;
        await api('DELETE', `/api/devices/${id}`);
        say(`Device "${btn.dataset.name}" was deleted.`);
        refreshList(); resetForm();
      } else if (action === 'token') {
        if (!confirm('Generate a new ingest token? The previous token stops working.')) return;
        const r = await api('POST', `/api/devices/${id}/token`);
        say(`New token (shown only once, copy it now): ${r.token} – send readings with "Authorization: Bearer <token>" to POST /api/ingest.`);
      } else if (action === 'cancel-edit') {
        resetForm();
      }
    } catch (err) { say(err.errors?.join(' ') ?? err.message); }
  });

  // Move focus to the form heading after an edit form was loaded (keyboard / screen reader users).
  document.addEventListener('htmx:afterSwap', (e) => {
    if (e.target.id === 'device-form' && e.detail.requestConfig?.verb === 'get' && e.detail.requestConfig.triggeringEvent?.type !== 'load') {
      document.getElementById('form-h')?.focus();
    }
  });
})();
