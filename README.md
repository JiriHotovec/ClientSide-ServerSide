# ClientSide-ServerSide – MeteoView
The semestral project for university purposes: a Laravel 13 + Blade application that visualises meteorological and IoT sensor data (public/private devices, user and admin roles, REST API, htmx + Fetch front-end).

Quick start:
```
composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate:fresh --seed
php artisan serve        # demo logins: admin@/alice@/bob@example.com, password "password"
composer test            # Pest; composer test:coverage for coverage
```
Full documentation: [docs/DOCUMENTATION.md](docs/DOCUMENTATION.md).
