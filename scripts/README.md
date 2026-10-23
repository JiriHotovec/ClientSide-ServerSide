# Balíček pro hosting (SQLite)

`build-package.sh` vyrobí zip připravený k nahrání na hosting: `vendor` bez dev závislostí, hotový frontend, zmigrovaná SQLite databáze, produkční `.env` a cachovaný config/routy/views.

## Požadavky (na tvém počítači)

`php`, `composer`, `node` + `npm`, `rsync`, `zip`, `openssl`.

## Build

```bash
scripts/build-package.sh https://tvoje-domena.cz [vystup.zip]
```

- `--with-data`: do zipu jde kopie tvé lokální `database/database.sqlite` (uživatelé, zařízení, měření). Doběhnou chybějící migrace, vyčistí se sessions a cache. Admin se nezakládá, přihlašuješ se stávajícími uživateli. Bez přepínače vznikne prázdná databáze s novým adminem.
- Výchozí výstup: `meteoview-package.zip` v kořeni projektu (je v `.gitignore`).
- Volitelné proměnné prostředí:
  - `ADMIN_EMAIL` (výchozí `admin@example.com`)
  - `ADMIN_PASSWORD` (výchozí náhodné)

```bash
ADMIN_EMAIL=ja@firma.cz ADMIN_PASSWORD='tajne-heslo' scripts/build-package.sh https://meteo.firma.cz
```

Na konci script vypíše cestu k zipu, admin přihlášení a cron příkaz. Poznamenej si je: heslo i token jsou jen tam a v `.env` uvnitř zipu.

Script pracuje v dočasné složce. Tvůj lokální `vendor`, `.env` a databáze se nemění.

## Nasazení na hosting

1. Rozbal zip na hosting.
2. Nastav web root na složku `public/`. Pokud to hosting neumí, funguje i kořenový `.htaccess` (Apache), který přesměruje vše do `public/`.
3. Ověř požadavky: PHP ≥ 8.3, rozšíření `pdo_sqlite`, zapisovatelné složky `storage/`, `bootstrap/cache/` a `database/`.
4. Otevři web a přihlas se údaji, které script vypsal. Heslo hned změň.
5. Nastav cron každou minutu (stahování měření):

   ```
   curl -s -H 'X-Cron-Token: <CRON_SECRET>' https://tvoje-domena.cz/api/cron/fetch
   ```

   Pokud hosting umí cron přímo s PHP, stačí `* * * * * php /cesta/k/artisan schedule:run`.

## Aktualizace

Postav nový zip a nahraj ho přes starý, ale **nepřepisuj `database/database.sqlite` ani `.env`**, jinak přijdeš o data a klíč. Nové migrace pak spusť `php artisan migrate --force`, nebo vlož novou databázi, pokud data nepotřebuješ.

## Řešení problémů

- **500 chyba:** zkontroluj práva k `storage/` a `bootstrap/cache/`, pak log v `storage/logs/`.
- **Změna `.env` se neprojeví:** config je cachovaný, smaž `bootstrap/cache/config.php` (nebo spusť `php artisan config:cache`).
- **Cron vrací 401:** token v hlavičce neodpovídá `CRON_SECRET` v `.env`.
