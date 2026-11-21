# HRMS

Human Resource Management System built on Laravel 12.

## Run locally

```bash
php -S 127.0.0.1:8000 server.php
```

The front controller is the root `index.php`, so use `server.php` rather than `php artisan serve`.

## Setup

```bash
composer install
php artisan migrate
php artisan db:seed --class=EmployeeImportSetupSeeder
php artisan db:seed --class=HolidayImportSetupSeeder
```
