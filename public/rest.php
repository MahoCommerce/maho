<?php

/**
 * Maho REST API v2 Entry Point
 *
 * Bootstraps Maho and Symfony API Platform for REST API requests.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

define('MAHO_ROOT_DIR', dirname(__DIR__));
define('MAHO_PUBLIC_DIR', __DIR__);

require MAHO_ROOT_DIR . '/vendor/autoload.php';

if (!Mage::isInstalled()) {
    header('Content-Type: application/json');
    http_response_code(503);
    // Raw json_encode is intentional here: Mage is not initialized yet, so
    // Mage::helper('core')->jsonEncode() is unavailable at this point.
    echo json_encode(['error' => 'Application is not installed yet.']);
    exit;
}

// Initialize Maho (required for API Platform to access models/config)
Mage::$headersSentThrowsException = false;
// Force developer mode off for the duration of the request: any warning emitted
// by Mage::init() below would print before the Symfony kernel boots and would
// corrupt the JSON response. Errors are still captured by Mage::log() and the
// configured Monolog handlers, so debugging info isn't lost, just kept out of
// the response body.
Mage::setIsDeveloperMode(false);
Mage::init('admin');

if (Mage::app()->isSchemaUpdatePending()) {
    header('Content-Type: application/json');
    header('Retry-After: 3600');
    http_response_code(503);
    echo json_encode(['error' => 'The database is behind the installed code, run "./maho migrate".']);
    exit;
}

Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_API, Mage_Core_Model_App_Area::PART_EVENTS);

$tracer = Mage::getTracer();
$rootSpan = $tracer ? Maho_OpenTelemetry_Model_Request::start($tracer) : null;

// Boot Symfony kernel.
// Always use prod mode with debug=false to prevent trace leakage in API responses;
// errors are still logged. Store context, admin-session bridging, and env-var
// resolution all happen inside the kernel, see EventListener/ and Kernel::__construct().
$kernel = new Maho\ApiPlatform\Kernel('prod', false);

$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$statusCode = 500;
try {
    $response = $kernel->handle($request);
    $statusCode = $response->getStatusCode();

    // send() hands the response to the client and releases the worker under FPM,
    // so the telemetry export below costs the caller nothing
    $response->send();
    $kernel->terminate($request, $response);

    if ($rootSpan) {
        Maho_OpenTelemetry_Model_Request::describe($rootSpan, $statusCode, $request->attributes->get('_route'), 'api');
    }
} finally {
    if ($tracer) {
        Maho_OpenTelemetry_Model_Request::finish($tracer, $rootSpan, $statusCode);
    }
}
