# HRMS

Human Resource Management System built on Laravel 12.

## Run locally

```bash
php artisan serve
```

Then open http://127.0.0.1:8000. `php -S 127.0.0.1:8000 server.php` works too.

The front controller is the root `index.php` (not `public/index.php`). `php artisan serve` automatically uses the root `server.php` router, which hands requests to that front controller and serves the `/public/assets/...` files.

## Setup

```bash
composer install
php artisan migrate
php artisan db:seed --class=EmployeeImportSetupSeeder
php artisan db:seed --class=HolidayImportSetupSeeder
```
