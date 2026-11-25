<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage
 */

define('MAHO_ROOT_DIR', dirname(__DIR__));
define('MAHO_PUBLIC_DIR', __DIR__);

require MAHO_ROOT_DIR . '/vendor/autoload.php';

if (!Mage::isInstalled()) {
    echo 'Application is not installed yet.';
    exit;
}

Mage::$headersSentThrowsException = false;
Mage::init('admin');

if (Mage::app()->isSchemaUpdatePending()) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Retry-After: 3600');
    http_response_code(503);
    echo 'The database is behind the installed code, run "./maho migrate".';
    exit;
}

Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App_Area::PART_EVENTS);

// query parameter "type" is set by .htaccess rewrite rule
$apiAlias = Mage::app()->getRequest()->getParam('type');
$isRest = in_array($apiAlias, Mage_Api2_Model_Server::getApiTypes());
$apiHelper = Mage::helper('apiplatform');

// Each legacy protocol is off by default. This file serves all of them, so check the switch of the requested one.
if ($isRest) {
    $protocol = Maho_ApiPlatform_Helper_Data::PROTOCOL_LEGACY_REST;
} else {
    /* @var $server Mage_Api_Model_Server */
    $server = Mage::getSingleton('api/server');
    // if no adapters found in aliases - find it by default, by code
    $adapterCode = $apiAlias ? ($server->getAdapterCodeByAlias($apiAlias) ?? $apiAlias) : 'default';
    $protocol = $apiHelper->getProtocolForAdapter($adapterCode === 'default'
        ? (string) Mage::getSingleton('api/config')->getNode('adapters/default/use')
        : (string) $adapterCode);
}
if ($protocol === null || !$apiHelper->isProtocolEnabled($protocol)) {
    http_response_code(404);
    exit;
}

// Several branches below exit, so the root span is closed at shutdown.
// finishRequest() runs first, so a slow collector never holds the API client.
$tracer = Mage::getTracer();
if ($tracer) {
    $rootSpan = Maho_OpenTelemetry_Model_Request::start($tracer);
    register_shutdown_function(static function () use ($tracer, $rootSpan, $apiAlias): void {
        Mage_Core_Controller_Response_Http::finishRequest();
        $statusCode = http_response_code() ?: 200;
        Maho_OpenTelemetry_Model_Request::describe($rootSpan, $statusCode, 'api/' . ($apiAlias ?: 'default'), 'api');
        Maho_OpenTelemetry_Model_Request::finish($tracer, $rootSpan, $statusCode);
    });
}

// check request could be processed by API2
if ($isRest) {
    /** @var Mage_Api2_Model_Server $rest */
    $rest = Mage::getSingleton('api2/server');
    $rest->run();
    exit;
}

try {
    $server->initialize($adapterCode);
    $server->run();
    Mage::app()->getResponse()->sendResponse();
} catch (Exception $e) {
    Mage::logException($e);
    echo $e->getMessage();
    exit;
}
