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
})();
