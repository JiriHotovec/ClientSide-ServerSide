// Shared htmx behaviour: understandable messages when a request fails (htmx does not swap error responses).
(() => {
  'use strict';

  const MESSAGES = {
    0: 'Could not reach the server. Check your connection and try again.',
    403: 'You are not allowed to do this.',
    404: 'This item no longer exists.',
    419: 'Your session expired. Reload the page and try again.',
    429: 'Too many attempts. Wait a minute and try again.',
  };
  const box = () => document.querySelector('[data-htmx-error]');

  document.addEventListener('htmx:responseError', (e) => {
    const b = box();
    if (b) b.textContent = MESSAGES[e.detail.xhr.status] || 'Something went wrong on the server. Please try again.';
  });
  document.addEventListener('htmx:sendError', () => { const b = box(); if (b) b.textContent = MESSAGES[0]; });
  document.addEventListener('htmx:beforeRequest', () => { const b = box(); if (b) b.textContent = ''; });
})();
