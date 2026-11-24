# HRMS

Human Resource Management System built on Laravel 12.

## Features

- **Employee Masters & Administration** — employee records, user accounts, roles and permissions, notifications, upload logs.
- **Attendance** — daily attendance tracking.
- **Leave** — apply leave with monthly credit accrual, approval workflow, and yearly reset.
- **Shift** — shift change requests.
- **Holiday** — holiday calendar management.
- **Master data** — supporting lookup/reference data used across the app.

## Requirements

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL (or another database supported by Laravel)

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed --class=EmployeeImportSetupSeeder
php artisan db:seed --class=HolidayImportSetupSeeder

npm run build   # or `npm run dev` while developing
```

## Run locally

```bash
php artisan serve
```

Then open http://127.0.0.1:8000. `php -S 127.0.0.1:8000 server.php` works too.

The front controller is the root `index.php` (not `public/index.php`). `php artisan serve` automatically uses the root `server.php` router, which hands requests to that front controller and serves the `/public/assets/...` files.

## Testing

```bash
php artisan test
```
