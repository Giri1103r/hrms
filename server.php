<?php

/*
 * Router script for PHP's built-in development server.
 *
 * Works with both:
 *   php artisan serve
 *   php -S 127.0.0.1:8000 server.php
 *
 * This app's front controller lives at the project root (index.php),
 * not in public/, so every asset URL is "/public/assets/...".
 *
 * `php artisan serve` picks this file up automatically (base_path('server.php'))
 * but starts the built-in server with public/ as its document root, so the
 * server cannot find "/public/assets/..." on its own. In that case this
 * script serves the static file itself. When started from the project root
 * with `php -S`, the built-in server serves static files directly.
 */

$root = __DIR__;

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$file = realpath($root . $uri);

$isStaticFile = $uri !== '/'
    && $file !== false
    && is_file($file)
    && str_starts_with($file, $root . DIRECTORY_SEPARATOR)
    && !preg_match('#/\.#', $uri)                // no dotfiles (.env, .git, ...)
    && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php';

if ($isStaticFile) {
    // Started from the project root (php -S ... server.php): let the
    // built-in server send the file as-is.
    if (realpath(getcwd()) === $root) {
        return false;
    }

    // Started by `php artisan serve` (document root = public/): send it here.
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'map' => 'application/json',
        'webmanifest' => 'application/manifest+json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'html' => 'text/html',
    ];

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$extension]
        ?? (function_exists('mime_content_type') ? mime_content_type($file) : null)
        ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);

    return true;
}

require_once $root . '/index.php';
