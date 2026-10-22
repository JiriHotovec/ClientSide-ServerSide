// Shared helpers: translations (from the #i18n JSON in the layout) and understandable htmx error messages.
(() => {
  'use strict';

  let data = { locale: document.documentElement.lang || 'en', strings: {} };
  try { data = JSON.parse(document.getElementById('i18n').textContent); } catch (e) { /* English fallback below */ }

  // MV.t('Hello :name', { name: 'x' }) returns the translated string with placeholders filled in.
  window.MV = {
    locale: data.locale,
    t: (key, vars = {}) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), data.strings[key] ?? key),
  };

  const MESSAGES = { 0: 'Could not reach the server. Check your connection and try again.', 403: 'You are not allowed to do this.', 404: 'This item no longer exists.', 419: 'Your session expired. Reload the page and try again.', 429: 'Too many attempts. Wait a minute and try again.' };
  const box = () => document.querySelector('[data-htmx-error]');

  document.addEventListener('htmx:responseError', (e) => {
    const b = box();
    if (b) b.textContent = MV.t(MESSAGES[e.detail.xhr.status] || 'Something went wrong on the server. Please try again.');
  });
  document.addEventListener('htmx:sendError', () => { const b = box(); if (b) b.textContent = MV.t(MESSAGES[0]); });
  document.addEventListener('htmx:beforeRequest', () => { const b = box(); if (b) b.textContent = ''; });

  // One live region that is never swapped: screen readers ignore live regions that arrive already filled.
  MV.announce = (text) => {
    const live = document.querySelector('[data-announce]');
    if (!live || !text) return;
    live.textContent = '';
    requestAnimationFrame(() => { live.textContent = text; });
  };

  // Swapped content marks what should be read out with [data-announce-swap] (value, or the element's text).
  const seen = new WeakSet();
  const pendingNotes = () => [...document.querySelectorAll('[data-announce-swap]')].filter((n) => !seen.has(n)).map((n) => {
    seen.add(n);
    return (n.dataset.announceSwap || n.textContent).trim();
  }).filter(Boolean);
  document.addEventListener('DOMContentLoaded', pendingNotes); // what is there on page load is read with the page

  // An outerHTML swap removes the focused control. Focus then goes to the first invalid field, an error summary,
  // a status message or the swapped region itself, never back to <body>.
  let swapped = null;
  document.addEventListener('htmx:beforeSwap', (e) => {
    const t = e.detail.target;
    swapped = t.id ? `#${CSS.escape(t.id)}` : t.getAttribute('aria-labelledby') ? `[aria-labelledby="${t.getAttribute('aria-labelledby')}"]` : null;
  });
  let queued = false;
  document.addEventListener('htmx:afterSettle', () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
      queued = false;
      MV.announce(pendingNotes().join(' '));
      const a = document.activeElement;
      const root = swapped && document.querySelector(swapped);
      swapped = null;
      if ((a && a !== document.body && a.isConnected) || !root || root.closest('dialog')) return;
      const to = root.querySelector('[aria-invalid="true"], [data-error-summary], .status[data-announce-swap]:not(:empty)') || root;
      if (to.tabIndex < 0 && !to.hasAttribute('tabindex') && !to.matches('input, select, textarea, button, a[href]')) to.setAttribute('tabindex', '-1');
      to.focus({ preventScroll: to === root && root.id === 'main' });
    });
  });
})();
