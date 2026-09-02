<?php

/**
 * Laravel - PHP Development Server Router
 * Allows running the app from the project root with:
 *   php -S 127.0.0.1:8081 server.php
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'
);

// Serve static files from public/ directly
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

// Route everything else through public/index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require_once __DIR__.'/public/index.php';
