<?php

/**
 * Router for PHP's built-in web server (`php -S host:port -t public tests/router.php`).
 *
 * Mirrors the /api/* rewrite rules in public/.htaccess so the REST/GraphQL API
 * is reachable under the built-in server (used by the API integration test
 * suite in CI). Production deployments route through Apache/.htaccess or nginx
 * directly and never load this file.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

$publicDir = dirname(__DIR__) . '/public';

// Run from the public/ dir so Mage::setRoot()'s dirname(getcwd()) resolves the
// app root exactly as php-fpm does in production (CWD = the entry-script dir).
// Without this, `php -S` launched from the repo root resolves the root one level
// too high and rest.php reports the app as not installed.
chdir($publicDir);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Let the built-in server serve existing static files as-is.
if ($uri !== '/' && is_file($publicDir . $uri)) {
    return false;
}

// Order mirrors public/.htaccess: /api/rest/v2 must be checked before the bare
// /api/rest legacy rule, and the legacy SOAP/RPC paths fall through to the front
// controller (index.php), where Maho_ApiPlatform_IndexController gates them.
if (preg_match('#^/api/rest/v2(/|$)#', $uri)) {
    require $publicDir . '/rest.php';
    return true;
}

if (preg_match('#^/api/rest(/|$)#', $uri)) {
    $_GET['type'] = 'rest';
    $_REQUEST['type'] = 'rest';
    $_SERVER['QUERY_STRING'] = ltrim('type=rest&' . ($_SERVER['QUERY_STRING'] ?? ''), '&');
    require $publicDir . '/api.php';
    return true;
}

if (preg_match('#^/api/(soap|v2_soap|xmlrpc|jsonrpc)(/|$)#', $uri)) {
    require $publicDir . '/index.php';
    return true;
}

if (preg_match('#^/api(/|$)#', $uri)) {
    require $publicDir . '/rest.php';
    return true;
}

require $publicDir . '/index.php';
return true;
