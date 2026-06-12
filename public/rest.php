<?php

/**
 * Maho REST API v2 Entry Point
 *
 * Bootstraps Maho and Symfony API Platform for REST API requests.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
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
    echo json_encode(['error' => 'Application is not installed yet.']);
    exit;
}

// Initialize Maho (required for API Platform to access models/config)
Mage::$headersSentThrowsException = false;
// Force developer mode off for the duration of the request: any warning emitted
// by Mage::init() below would print before the Symfony kernel boots and would
// corrupt the JSON response. Errors are still captured by Mage::log() and the
// configured Monolog handlers, so debugging info isn't lost — just kept out of
// the response body.
Mage::setIsDeveloperMode(false);
Mage::init('admin');
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_API, Mage_Core_Model_App_Area::PART_EVENTS);

// Boot Symfony kernel.
// Always use prod mode with debug=false to prevent trace leakage in API responses;
// errors are still logged. Store context, admin-session bridging, and env-var
// resolution all happen inside the kernel — see EventListener/ and Kernel::__construct().
$kernel = new Maho\ApiPlatform\Kernel('prod', false);

$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
