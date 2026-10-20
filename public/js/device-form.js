// Device form: tabs, dataset picking (search, chips, appearance), chart editor helpers and the data source switch.
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const t = (k, v) => (window.MV ? MV.t(k, v) : k);
  const locale = () => (window.MV ? MV.locale : 'en');

  function init(form) {
    if (form.dataset.ready) return;
    form.dataset.ready = '1';
    form.classList.add('is-tabs'); // without JS all sections stay stacked and visible

    // ---- Tabs (ARIA): arrows / Home / End; the hash keeps the tab on reload ----
    const tabs = $$('[data-form-tabs] [data-tab]', form);
    const panels = $$('[data-panel]', form);
    const names = tabs.map((b) => b.dataset.tab);

    function show(name, focus) {
      if (!names.includes(name)) name = names[0];
      tabs.forEach((b) => {
        const on = b.dataset.tab === name;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        b.tabIndex = on ? 0 : -1;
        if (on && focus) b.focus();
      });
      panels.forEach((p) => { p.hidden = p.dataset.panel !== name; });
      try { history.replaceState(null, '', `#${name}`); } catch (e) { /* ignore */ }
    }
    tabs.forEach((b, i) => {
      b.addEventListener('click', () => show(b.dataset.tab));
      b.addEventListener('keydown', (e) => {
        const next = { ArrowRight: (i + 1) % tabs.length, ArrowLeft: (i - 1 + tabs.length) % tabs.length, Home: 0, End: tabs.length - 1 }[e.key];
        if (next === undefined) return;
        e.preventDefault();
        show(tabs[next].dataset.tab, true);
      });
    });
    show(names.includes(location.hash.slice(1)) ? location.hash.slice(1) : (form.dataset.errorTab || names[0]));

    // ---- Datasets ----
    const toggles = $$('[data-type-toggle]', form);
    const chips = $('[data-chips]', form);
    const chipsEmpty = $('[data-chips-empty]', form);
    const filter = $('[data-type-filter]', form);
    const setCount = (name, n) => { const el = $(`[data-count="${name}"]`, form); if (el) el.textContent = n || ''; };

    const checkedToggles = () => toggles.filter((c) => c.checked);

    function syncDatasets() {
      const checked = checkedToggles();
      const ids = new Set(checked.map((c) => c.value));
      toggles.forEach((c) => c.closest('[data-type-row]').querySelector('.appearance')?.classList.toggle('is-on', c.checked));

      $$('[data-chip]', chips).forEach((c) => c.remove());
      checked.forEach((c) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'chip'; b.dataset.chip = c.value;
        const x = document.createElement('span'); x.setAttribute('aria-hidden', 'true'); x.textContent = '×';
        const sr = document.createElement('span'); sr.className = 'sr-only'; sr.textContent = ` ${t('Remove')}`;
        b.append(`${c.dataset.name} `, x, sr);
        chips.insertBefore(b, chipsEmpty);
      });
      chipsEmpty.hidden = checked.length > 0;
      setCount('datasets', checked.length);

      $$('[data-map-row]', form).forEach((r) => r.classList.toggle('is-on', ids.has(r.dataset.mapRow)));
      const noneMapped = $$('[data-map-row].is-on', form).length === 0;
      const emptyMap = $('[data-map-empty]', form);
      if (emptyMap) emptyMap.hidden = !noneMapped;
      syncCharts();
    }
    form.addEventListener('change', (e) => { if (e.target.matches('[data-type-toggle]')) syncDatasets(); });
    chips.addEventListener('click', (e) => {
      const chip = e.target.closest('[data-chip]');
      if (!chip) return;
      const box = toggles.find((c) => c.value === chip.dataset.chip);
      if (box) { box.checked = false; syncDatasets(); }
      filter?.focus();
    });

    filter?.addEventListener('input', () => {
      const q = filter.value.trim().toLowerCase();
      let visible = 0;
      $$('[data-type-row]', form).forEach((row) => { const show = !q || row.dataset.search.includes(q); row.hidden = !show; if (show) visible += 1; });
      $$('[data-type-group]', form).forEach((g) => { g.hidden = !$$('[data-type-row]:not([hidden])', g).length; });
      const none = $('[data-type-filter-empty]', form);
      if (none) none.hidden = visible > 0;
    });

    // Appearance of one dataset: badge (default/customized), live preview, reset
    $$('[data-type-settings]', form).forEach((box) => {
      const defaults = JSON.parse(box.dataset.defaults);
      const fields = $$('[data-field]', box);
      const badge = $('[data-appearance-state]', box);
      const preview = $('[data-appearance-preview]', box);
      const btn = $('[data-appearance-toggle]', box);
      const body = $('[data-appearance-body]', box);
      const val = (f) => fields.find((x) => x.dataset.field === f)?.value ?? '';

      const update = () => {
        const custom = fields.some((f) => f.value !== '' && !(f.dataset.field === 'color' && f.value.toLowerCase() === (defaults.color || '').toLowerCase()));
        badge.textContent = custom ? badge.dataset.labelCustom : badge.dataset.labelDefault;
        badge.classList.toggle('badge-private', !custom);

        const unit = val('unit') || defaults.unit || '';
        const decimals = val('decimals') !== '' ? Math.min(Math.max(parseInt(val('decimals'), 10) || 0, 0), 6) : defaults.decimals;
        const pos = val('unit_position') || defaults.unit_position;
        const space = val('unit_space') !== '' ? val('unit_space') === '1' : defaults.unit_space;
        const n = new Intl.NumberFormat(locale(), { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(21.456);
        const sp = space ? ' ' : '';
        preview.textContent = !unit || pos === 'none' ? n : pos === 'before' ? unit + sp + n : n + sp + unit;
        preview.style.color = val('color');
      };
      box.addEventListener('input', update);
      box.addEventListener('change', update);
      btn.addEventListener('click', () => {
        const open = body.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? btn.dataset.labelOpen : btn.dataset.labelClosed;
      });
      $('[data-appearance-reset]', box).addEventListener('click', () => {
        fields.forEach((f) => { f.value = f.dataset.field === 'color' ? (f.dataset.default || defaults.color) : ''; });
        update();
      });
      update();
    });

    // ---- Charts ----
    const rowsEl = $('[data-chart-rows]', form);
    const chartEmpty = $('[data-chart-empty]', form);

    function syncCharts() {
      const rows = $$('[data-chart-row]', rowsEl);
      chartEmpty.hidden = rows.length > 0;
      setCount('charts', rows.length);
      const ids = new Set(checkedToggles().map((c) => c.value));
      rows.forEach((r) => {
        const orphan = !ids.has($('[data-chart-type]', r).value);
        $('[data-chart-orphan]', r).hidden = !orphan;
        r.classList.toggle('is-orphan', orphan);
      });
    }
    new MutationObserver(syncCharts).observe(rowsEl, { childList: true });

    form.addEventListener('click', (e) => {
      const row = e.target.closest('[data-chart-row]');
      if (row && e.target.closest('[data-chart-remove]')) { row.remove(); $('[data-chart-add]', form).focus(); return; }
      const move = row && e.target.closest('[data-chart-move]');
      if (move) {
        const sibling = move.dataset.chartMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        move.dataset.chartMove === 'up' ? sibling.before(row) : sibling.after(row);
        move.focus();
      }
    });
    form.addEventListener('change', (e) => { if (e.target.matches('[data-chart-type]')) syncCharts(); });

    // "One chart per dataset": one row for every ticked dataset, in its default style.
    $('[data-chart-autofill]', form).addEventListener('click', async () => {
      const checked = checkedToggles();
      if (!checked.length) { alert(t('Select at least one dataset above first.')); return; }
      for (const c of checked) {
        // eslint-disable-next-line no-await-in-loop
        await htmx.ajax('GET', rowsEl.dataset.rowUrl, { target: rowsEl, swap: 'beforeend', values: { 'types[]': checked.map((x) => x.value), pick: c.value, kind: c.dataset.defaultChart } });
      }
    });

    // ---- Data source: push or pull ----
    const modes = $$('[data-source-mode]', form);
    const syncMode = () => {
      const mode = modes.find((r) => r.checked)?.value;
      $$('[data-source-panel]', form).forEach((p) => p.toggleAttribute('data-inactive', p.dataset.sourcePanel !== mode));
    };
    modes.forEach((r) => r.addEventListener('change', syncMode));

    syncDatasets();
    syncMode();
  }

  // Error summary links jump to the tab that holds the error.
  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-goto-tab]');
    const form = $('[data-device-form]');
    if (!link || !form) return;
    e.preventDefault();
    $(`[data-form-tabs] [data-tab="${link.dataset.gotoTab}"]`, form)?.click();
    form.scrollIntoView({ block: 'start' });
  });

  const boot = (scope = document) => $$('[data-device-form]', scope).forEach(init);
  document.addEventListener('DOMContentLoaded', () => boot());
  document.addEventListener('htmx:load', (e) => boot(e.target));
})();
