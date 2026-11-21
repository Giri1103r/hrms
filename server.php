<?php

/*
 * Router script for PHP's built-in development server
 * (php -S 127.0.0.1:8000 server.php).
 *
 * This app's front controller lives at the project root (index.php),
 * not in public/, so every asset URL is "/public/assets/...". Without
 * this router, the built-in server hands every request -- including
 * CSS/JS/image requests -- to index.php, which returns the Laravel
 * app's HTML instead of the static file, so pages load unstyled.
 *
 * This mirrors vendor/laravel/framework/.../resources/server.php,
 * adjusted to require index.php from the project root instead of
 * public/index.php.
 */

$root = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// If the request matches a real file on disk, let the built-in server
// serve it as-is (correct bytes, correct mime type).
if ($uri !== '/' && file_exists($root . $uri)) {
    return false;
}

require_once $root . '/index.php';
