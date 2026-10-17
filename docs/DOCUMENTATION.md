# MeteoView – Technical Documentation

Laravel 13 / Blade application visualising meteorological and IoT sensor data. Guests browse public devices, users manage their own devices, administrators manage all of them.

## 1. Laravel & Blade implementation

| Layer | Location | Responsibility |
|---|---|---|
| Routes | `routes/web.php` (pages), `routes/rest.php` (session-authenticated JSON API, prefix `/api`, `web` middleware ⇒ CSRF), `routes/api.php` (stateless token endpoints `ingest`, `cron/fetch`), `routes/console.php` (scheduler) | |
| Controllers | `app/Http/Controllers` (pages, auth), `app/Http/Controllers/Api` (REST) | thin: validate → authorize → call service |
| Requests | `app/Http/Requests` | validation (`DeviceRequest`, `MeasurementQueryRequest`) |
| Resources | `app/Http/Resources/DeviceResource` | JSON shape; owner-only fields are omitted for everyone else |
| Services | `app/Services` | `MeasurementService` (period series), `DeviceService`, `IngestService` (idempotent upsert), `PullService` (external pull + mapper) |
| Policies | `app/Policies` | `DevicePolicy` (view/update/delete), `MeasurementTypePolicy` |
| Middleware | `app/Http/Middleware` | `EnsureRole`, `SecurityHeaders` (CSP etc.) |
| Support | `app/Support/UrlGuard` | SSRF protection |
| Views | `resources/views` | `layouts/app`, pages, `partials/device-panel` (shared dashboard), `manage/*` (htmx fragments) |
| Front-end | `public/js/dashboard.js`, `public/js/manage.js`, `public/css/app.css`, vendored `public/vendor/{htmx,chart.js,pico.css}` | no build step (Node not required) |

Styling uses **Pico CSS v2** (semantic, mobile-first, light/dark) plus a small `app.css`.

### Roles
- **Guest** – `/`, `/devices`, `/devices/{id}` (public only), `/login`, `/register`, public API.
- **User** (`role=user`, default for registrations; `role` is not mass-assignable) – `/dashboard`, `/manage/devices` (own devices only).
- **Administrator** (`role=admin`, created by seeder/DB only) – `/manage/devices` shows all devices; `/dashboard` returns 403.

## 2. Asynchronous data loading
- **Fetch API** (`dashboard.js`): loads `GET …/measurements?period=24h|7d&type=<key>`; **one JSON response feeds both chart (Chart.js) and table** (A3). Handles loading (`aria-busy` + live region), empty period, missing values (`null` → gap in chart, "Missing value" in table, never `0`), HTTP errors and an unavailable external source (`source_status`).
- **htmx**: personal dashboard device switch (`/dashboard/devices/{id}` fragment), device list refresh, create/edit form loading. htmx runs with `allowEval:false`.
- **htmx forms** (workspace, login, registration, profile): forms are normal server-side POST/PUT/DELETE forms enhanced with `hx-boost`. The server validates and answers with a redirect (or `HX-Redirect` for login/registration, `422` + re-rendered card for the profile); htmx swaps `#main` and the sidebar. Without JavaScript the same flow works as plain form posts. Handled in `ManageActionController`, `AuthController`, `ProfileController`; client glue is only `public/js/forms.js` (error messages) and `manage.js` (preview, menu).
- The JSON REST API (`routes/rest.php`) is unchanged and uses the same services/policies; chart data on dashboards is loaded with the Fetch API.

## 3. Database structure

| Table | Columns (main) |
|---|---|
| `users` | id, name, email (unique), password, **role** (`user`/`admin`) |
| `devices` | id, user_id→users, name, description, location, **is_public**, `ingest_token_hash` (sha-256, unique), `source_url`, `source_mapping` (JSON), `fetch_interval_min`, `last_fetched_at`, `last_error` |
| `measurement_types` | id, `key` (unique slug), name, unit, description, created_by→users, is_system – independent catalogue (temperature, humidity, pressure, wind speed, CO₂ seeded; users can add more) |
| `device_measurement_type` | device_id, measurement_type_id (PK pair) – which datasets a device reports |
| `measurements` | id, device_id, measurement_type_id, **value (nullable = missing)**, measured_at (UTC), source (`seed`/`ingest`/`pull`); UNIQUE(device_id, measurement_type_id, measured_at) |

Only Eloquent/migrations are used (no engine-specific SQL), so the same code runs on SQLite and MySQL.

### SQLite → MySQL
1. Create the DB, set in `.env`: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. `php artisan migrate --seed` (or `migrate:fresh --seed` for a clean start).
3. Run `composer test` once with `DB_CONNECTION=mysql` and a test DB if desired (default test run uses in-memory SQLite).

## 4. REST API (JSON)
Errors: `401` unauthenticated, `403` forbidden (foreign device), `404` unknown **or private (public API)**, `419` CSRF, `422` validation `{message, errors}`, `429` throttled.

### Public (no auth)
| Method & path | Notes |
|---|---|
| `GET /api/public/devices` | public devices only |
| `GET /api/public/devices/{id}` | 404 if private |
| `GET /api/public/devices/{id}/measurements?period=24h\|7d&type=<key>` | series (see below); 404 if private/unknown dataset |

Measurement response: `{device, type{key,name,unit}, period, from, to, source_status{ok,message,last_fetched_at}, summary{count,missing,latest}, data:[{measured_at, value|null, unit, source}]}`.

### Authenticated (session cookie + `X-CSRF-TOKEN` header)
| Method & path | Rule |
|---|---|
| `GET /api/devices` | own devices (admin: all) |
| `POST /api/devices` | any user; owner = current user |
| `GET/PUT/PATCH/DELETE /api/devices/{id}` | owner or admin, else 403 |
| `PATCH /api/devices/{id}/visibility` `{is_public:bool}` | owner or admin; effective immediately |
| `POST /api/devices/{id}/token` | new ingest token (plain value returned once) |
| `GET /api/devices/{id}/measurements` | owner or admin |
| `GET/POST /api/measurement-types`, `PUT/DELETE /api/measurement-types/{id}` | create by any user; change/delete by creator/admin, only if unused (409 otherwise) |

### Publishing own data – `POST /api/ingest`
`Authorization: Bearer <device token>` (no session/CSRF).
```json
{"measurements":[{"type":"temperature","value":21.4,"measured_at":"2026-10-17T10:00:00Z"},
                 {"type":"humidity","value":null}]}
```
`value` must be present (`null` = missing). Types not assigned to the device are rejected (counted in `rejected`). Same timestamp ⇒ upsert. Limit 500 readings/request, 60 requests/min.

### Cron – `GET|POST /api/cron/fetch`
For hosts without shell cron. Header `Authorization: Bearer <CRON_SECRET>` or `X-Cron-Token: <CRON_SECRET>` (compared with `hash_equals`; secrets in the query string are **not** accepted). Optional `?device=<id>`, `?force=1`. Returns `{devices, ok, failed, results[]}`. Equivalent CLI: `php artisan meteoview:fetch [--device=ID] [--force]`; the Laravel scheduler (`routes/console.php`) runs it every minute (needs the usual `* * * * * php artisan schedule:run`), each device honours its own `fetch_interval_min`.

## 4b. Administration pages and display settings
Pages (all behind login; users see their own devices, administrators can switch to "All devices"): `/manage` (card overview, paginated, filterable), `/manage/devices` (list + data source status), `/manage/devices/create` and `/{id}/edit`, `/manage/types` (list), `/manage/types/create` and `/{id}/edit`. Search, filters and pagination are plain GET requests enhanced with htmx (`hx-select="#results"`, URL is pushed); they also work without JavaScript. Saving uses server-side forms (htmx) that call the same services as the REST API.

**Measurement types are global or private.** Global (`is_public`) types are usable by everybody and only administrators can create/make them global; types created by users are private to the creator (and visible to admins). A device can only use types visible to its owner.

**Display settings** live on the type (defaults) and can be overridden per device (`device_measurement_type` pivot, `null` = inherit): `unit`, `unit_position` (before/after/none), `unit_space`, `decimals`, `color`, `chart_type` (line/bar), `y_min`, `y_max`. The measurement API returns the resolved `display` object, which the chart, table and cards use; `type_settings` on `POST/PUT /api/devices` carries the overrides, validated like the type defaults.

## 4c. Languages (CS/EN) and SEO
- **Only the interface is translated.** Names, locations and descriptions of devices and user-created measurement types stay as their authors wrote them, so nobody has to fill in two languages. Built-in measurement types (temperature, humidity, ...) are translated by key (`lang/*/measurement.php`); categories, validation messages, flash messages, API messages and the browser scripts' texts are translated too (`lang/cs.json`, `lang/cs/validation.php`). A test fails when a key used in code has no Czech translation.
- **One URL per language for public pages:** `/en`, `/cs`, `/en/devices`, `/cs/devices/{uuid}`, login and registration. `/`, `/devices`, `/devices/{uuid}`, `/login`, `/register` redirect to the visitor's language. Workspace pages (`/manage`, `/dashboard`) are not prefixed and follow the saved preference.
- **Language detection (SetLocale middleware):** URL prefix, then the account setting (`users.locale`), session, cookie, `Accept-Language`, default English. The switch on public pages is a normal link (crawlable); in the workspace it is a form and stores the choice on the account.
- **SEO:** unique `<title>` and meta description per page, `<link rel="canonical">`, `hreflang` alternates (+ `x-default`), Open Graph tags, `noindex` for login, registration, dashboard and workspace, `sitemap.xml` (every public page in both languages with alternates and `lastmod`, private devices excluded) and `robots.txt` (hides workspace, API and data popups).
- Numbers use a decimal comma in Czech; relative times ("před 10 minutami") are localized by Carbon.

## 4d. Chart styles, built-in types and maps
- **Built-in measurement types** (`App\Support\SystemTypes`, ~50: weather, air quality, water and soil, electricity/solar power, gas): seeded by `db:seed`; `php artisan meteoview:sync-types` adds the missing ones to an existing database without touching edits.
- **Chart styles:** line, area, step, bars, points, gauge, wind rose (`App\Support\ChartKinds`). The type sets the default style; the device owner can arrange any number of charts per device (dataset + style + optional title, in order) in the device form (`device_charts` table). A device without an arrangement shows one default chart per dataset.
- **Maps (OpenStreetMap via vendored Leaflet):** devices have optional `latitude`/`longitude`. The device form opens a map dialog (click, drag the pin, type coordinates or search a place); an empty place name is filled from the pin. The public device list shows all matching devices with a pin on a map, and a device page shows a small map. Place search goes through our proxy (`/geocode/search`, `/geocode/reverse`, login required, throttled, cached 24 h, identifying User-Agent) so the browser never calls Nominatim directly. The CSP only gained `img-src https://tile.openstreetmap.org`.

## 5. External API integration status
- **Integration-ready, generic JSON pull**: a device can define `source_url` and a mapping, e.g.
  ```json
  {"timestamp":"current.time","values":{"temperature":"current.temperature_2m","humidity":"current.relative_humidity_2m"}}
  ```
  `items_path` (e.g. `"hourly"`) is supported for list payloads. Values that are missing/non-numeric are stored as `null`.
- Status: implemented and covered by tests with faked HTTP (`Http::fake`). **No third-party provider is configured by default**; no live call is made until a user sets a source URL.
- Failures (HTTP error, timeout, invalid JSON, blocked URL) are stored in `devices.last_error`, shown in the management table and as a warning on dashboards; one failing device never stops others.
- Safeguards: http(s) only, no credentials in URL, private/loopback/link-local/reserved IPs rejected, connection pinned to the vetted IP (anti DNS-rebinding), no redirects, 10 s timeout, 1 MB limit.

## 6. Security measures
CSRF (web group; htmx `hx-headers` + `X-CSRF-TOKEN` on fetch), session regeneration on login / invalidation on logout, hashed passwords + `Password::defaults()` (min 10, letters+numbers), rate limits (login, register, ingest, cron, REST), generic login error, policies on every device/type route (IDOR → 403), `$fillable` allow-lists (`role`, `user_id`, `is_public`, token never mass-assigned), Blade escaping, strict CSP (`default-src 'self'`, no inline scripts/styles) + `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, query-builder bindings only, whitelisted `period`, ingest tokens stored as hashes, cron secret via `hash_equals`, SSRF guard (see §5). `composer audit` reports no advisories.

## 7. Accessibility (WCAG 2.2 AA)
Skip link, landmarks, one `h1` per page, `lang`, labelled controls, `aria-current`, `aria-pressed` period buttons, `aria-live` status regions for loading/empty/error, `aria-invalid` + `role=alert` on form errors, visible focus (3 px), targets ≥ 44 px for actions, tables with caption and `th scope`, chart has an equivalent data table and `prefers-reduced-motion` is respected, mobile-first responsive layout, light/dark themes. Automated smoke tests in `tests/Feature/AccessibilityTest.php`; a manual keyboard/screen-reader pass is still recommended before release.

## 8. Testing
`composer test` (Pest 5 on PHPUnit, in-memory SQLite) · `composer test:coverage` (PCOV, fails below 80 %).

Latest result: **294 tests, all passing.**

| File | Covers |
|---|---|
| `AuthTest` | register/login/logout, role redirects, no admin self-assignment, throttling, session regeneration, admin blocked from `/dashboard` |
| `PublicAccessTest` | A7 public vs. private pages/API, no owner-only fields publicly |
| `MeasurementSeriesTest` | A1 dataset switching, A3 period filter, A4 nulls / empty / source error |
| `DeviceManagementTest` | A8 ownership + 401/403, A9 CRUD, A10 visibility, htmx fragments, tokens, measurement types |
| `IngestTest` | token auth, validation, foreign type, null, upsert, throttle |
| `PullAndCronTest` | pull mapping, errors, interval, failure isolation, SSRF matrix, cron secret/throttle |
| `SecurityTest` | CSRF 419, headers/CSP, no inline scripts, XSS escaping, mass assignment, hidden hashes |
| `ManagePagesTest` | admin pages, own vs. all scope, filters, pagination, global/private type visibility |
| `MeasurementTypeDisplayTest` | type creation (private/global), display validation, per-device overrides, value formatting |
| `LocalizationTest`, `SeoTest` | language detection and switching, redirects, translated pages/messages, hreflang/canonical/noindex, sitemap, robots |
| `AccessibilityTest` | structure, labels, live regions, captions, aria on errors |
| `tests/Unit/*` | mapper, UrlGuard, policies, MeasurementService |

Not automated: real-browser E2E (chart rendering, keyboard walkthrough).

## 9. Running locally
```
composer install
cp .env.example .env && php artisan key:generate   # already done in this repo
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```
Seeded accounts (password `password`): `admin@example.com`, `alice@example.com`, `bob@example.com`. Set `CRON_SECRET` in `.env`.
