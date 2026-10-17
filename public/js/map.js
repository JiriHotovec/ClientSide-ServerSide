// Maps (Leaflet + OpenStreetMap): pick a device location, show public devices, small map on a device page.
(() => {
  'use strict';
  if (!window.L) return; // Leaflet blocked: forms still work with plain coordinates

  const t = (k, v) => MV.t(k, v);
  const script = document.currentScript;
  L.Icon.Default.imagePath = script?.dataset.leafletImages || '/vendor/leaflet/images/';
  const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
  const el = (tag, text, cls) => { const e = document.createElement(tag); if (text !== undefined) e.textContent = text; if (cls) e.className = cls; return e; };

  function baseMap(node, opts = {}) {
    const map = L.map(node, { scrollWheelZoom: false, ...opts });
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);
    map.on('focus', () => map.scrollWheelZoom.enable());
    map.on('blur', () => map.scrollWheelZoom.disable());
    return map;
  }

  // ---- Overview map: opens from the search bar; shows the public devices that match the current search ----
  // The data (JSON) travels with the htmx-swapped results, so the map and the button follow every search.
  function initOverview(dialog) {
    const button = document.querySelector('[data-overview-open]');
    const count = button?.querySelector('[data-map-count]');
    let map = null;
    let layer = null;

    const devices = () => {
      try { return JSON.parse(document.querySelector('[data-map-data]')?.textContent || '[]'); } catch (e) { return []; }
    };

    const draw = () => {
      const list = devices();
      if (!map) return;
      layer.clearLayers();
      map.invalidateSize();
      const points = list.map((d) => {
        const marker = L.marker([d.lat, d.lng], { title: d.name, alt: d.name });
        const box = el('div', undefined, 'map-popup');
        const link = el('a', d.name); link.href = d.url;
        box.append(link);
        if (d.location) box.append(el('br'), el('span', d.location));
        marker.bindPopup(box);
        marker.addTo(layer);
        return [d.lat, d.lng];
      });
      if (!points.length) map.setView([49.8, 15.5], 6);
      else points.length === 1 ? map.setView(points[0], 11) : map.fitBounds(points, { padding: [30, 30], maxZoom: 12 });
    };

    const sync = () => {
      const n = devices().length;
      if (button) button.hidden = n === 0;
      if (count) count.textContent = n;
      if (dialog.open) draw();
    };

    button?.addEventListener('click', () => {
      dialog.showModal();
      if (!map) { map = baseMap(dialog.querySelector('[data-map-canvas]')); layer = L.layerGroup().addTo(map); }
      draw();
      dialog.querySelector('h2').focus();
    });
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
    document.addEventListener('htmx:afterSettle', sync);
  }

  // ---- Small map on a device page ----
  function initStatic(node) {
    if (node.dataset.ready) { node._map?.invalidateSize(); return; } // already built: only fix its size
    node.dataset.ready = '1';
    const pos = [parseFloat(node.dataset.lat), parseFloat(node.dataset.lng)];
    const map = baseMap(node, { dragging: true });
    node._map = map;
    map.setView(pos, 12);
    L.marker(pos, { title: node.dataset.name || '', alt: node.dataset.name || '' }).addTo(map);
  }

  // ---- Location picker in the device form ----
  function initPicker(root) {
    const q = (s) => root.querySelector(s);
    const dialog = document.getElementById(root.dataset.dialog);
    const latField = q('[data-lat]');
    const lngField = q('[data-lng]');
    const locationInput = root.closest('form').querySelector('[name="location"]');
    const coords = root.closest('form').querySelector('[data-coords]');
    const clearBtn = q('[data-map-clear]');
    const dLat = dialog.querySelector('[data-dlg-lat]');
    const dLng = dialog.querySelector('[data-dlg-lng]');
    const results = dialog.querySelector('[data-map-results]');
    const message = dialog.querySelector('[data-map-message]');
    const searchInput = dialog.querySelector('[data-map-search-input]');
    // The result list rolls out under the field; aria-expanded tells assistive technology when it is there.
    const closeResults = () => { results.replaceChildren(); searchInput.setAttribute('aria-expanded', 'false'); };
    let map = null;
    let marker = null;

    const showCoords = () => {
      const has = latField.value !== '' && lngField.value !== '';
      coords.textContent = has ? `${Number(latField.value).toFixed(5)}, ${Number(lngField.value).toFixed(5)}` : '';
      clearBtn.hidden = !has;
    };

    function place(lat, lng, zoom) {
      lat = Math.round(lat * 1e6) / 1e6; lng = Math.round(lng * 1e6) / 1e6;
      dLat.value = lat; dLng.value = lng;
      if (!marker) {
        marker = L.marker([lat, lng], { draggable: true, title: t('Location') }).addTo(map);
        marker.on('dragend', () => { const p = marker.getLatLng(); place(p.lat, p.lng); });
      } else marker.setLatLng([lat, lng]);
      if (zoom) map.setView([lat, lng], zoom);
    }

    function ensureMap() {
      if (map) { map.invalidateSize(); return; }
      map = baseMap(dialog.querySelector('[data-map-canvas]'));
      const has = latField.value !== '' && lngField.value !== '';
      has ? map.setView([+latField.value, +lngField.value], 12) : map.setView([49.8, 15.5], 6);
      if (has) place(+latField.value, +lngField.value);
      map.on('click', (e) => place(e.latlng.lat, e.latlng.lng));
    }

    q('[data-map-open]').addEventListener('click', () => {
      message.textContent = ''; closeResults();
      dialog.showModal();
      ensureMap();
      const has = latField.value !== '';
      if (has) { dLat.value = latField.value; dLng.value = lngField.value; } else { dLat.value = ''; dLng.value = ''; }
      dialog.querySelector('[data-map-search-input]').focus();
    });

    // Typing coordinates is the keyboard-friendly alternative to clicking the map.
    const fromInputs = () => {
      const lat = parseFloat(dLat.value); const lng = parseFloat(dLng.value);
      if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) place(lat, lng, Math.max(map.getZoom(), 10));
    };
    dLat.addEventListener('change', fromInputs);
    dLng.addEventListener('change', fromInputs);

    // Place search through our own proxy of OpenStreetMap's Nominatim.
    dialog.querySelector('[data-map-search]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const query = dialog.querySelector('[data-map-search-input]').value.trim();
      closeResults();
      if (query.length < 2) return;
      message.textContent = t('Searching…');
      try {
        const res = await fetch(`${root.dataset.searchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error();
        const { data } = await res.json();
        message.textContent = data.length ? '' : t('No places found.');
        if (data.length) searchInput.setAttribute('aria-expanded', 'true');
        data.forEach((p) => {
          const li = el('li'); const b = el('button', p.name); b.type = 'button';
          b.addEventListener('click', () => { place(p.lat, p.lng, 13); closeResults(); message.textContent = t('Pin placed. Adjust it if needed and confirm.'); });
          li.append(b); results.append(li);
        });
      } catch (err) { message.textContent = t('The place search failed. Try again later.'); }
    });

    dialog.querySelector('[data-map-confirm]').addEventListener('click', async () => {
      if (dLat.value === '' || dLng.value === '') { message.textContent = t('Choose a place on the map first.'); return; }
      latField.value = dLat.value; lngField.value = dLng.value;
      showCoords();
      dialog.close();
      if (locationInput && locationInput.value.trim() === '') { // fill an empty place name from the pin
        try {
          const res = await fetch(`${root.dataset.reverseUrl}?lat=${encodeURIComponent(latField.value)}&lng=${encodeURIComponent(lngField.value)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          if (res.ok) { const { name } = await res.json(); if (name && locationInput.value.trim() === '') locationInput.value = name.slice(0, 100); }
        } catch (err) { /* the name stays empty; the coordinates are saved anyway */ }
      }
    });

    clearBtn.addEventListener('click', () => { latField.value = ''; lngField.value = ''; showCoords(); q('[data-map-open]').focus(); });
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) dialog.close();
      else if (!e.target.closest('.map-search-field')) closeResults(); // click elsewhere rolls the list up
    });
    searchInput.addEventListener('keydown', (e) => { if (e.key === 'Escape' && results.children.length) { e.stopPropagation(); e.preventDefault(); closeResults(); } });
    showCoords();
  }

  const boot = () => {
    document.querySelectorAll('[data-overview-map]:not([data-ready])').forEach((n) => { n.dataset.ready = '1'; initOverview(n); });
    // A map inside a hidden tab is built when the tab is shown (Leaflet cannot size a hidden container).
    document.querySelectorAll('[data-static-map]:not([data-ready])').forEach((n) => { if (!n.closest('[hidden]')) initStatic(n); });
    document.querySelectorAll('[data-location-picker]:not([data-ready])').forEach((n) => { n.dataset.ready = '1'; initPicker(n); });
  };
  document.addEventListener('meteo:map-visible', (e) => initStatic(e.detail));
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('htmx:load', boot);
})();
