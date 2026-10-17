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
- **Fetch + REST** (`manage.js`): create/update/delete device, visibility toggle, token regeneration, create measurement type; after success the htmx fragments are refreshed.

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

Latest result: **106 tests, 312 assertions, all passing; line coverage 93.7 %.**

| File | Covers |
|---|---|
| `AuthTest` | register/login/logout, role redirects, no admin self-assignment, throttling, session regeneration, admin blocked from `/dashboard` |
| `PublicAccessTest` | A7 public vs. private pages/API, no owner-only fields publicly |
| `MeasurementSeriesTest` | A1 dataset switching, A3 period filter, A4 nulls / empty / source error |
| `DeviceManagementTest` | A8 ownership + 401/403, A9 CRUD, A10 visibility, htmx fragments, tokens, measurement types |
| `IngestTest` | token auth, validation, foreign type, null, upsert, throttle |
| `PullAndCronTest` | pull mapping, errors, interval, failure isolation, SSRF matrix, cron secret/throttle |
| `SecurityTest` | CSRF 419, headers/CSP, no inline scripts, XSS escaping, mass assignment, hidden hashes |
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
