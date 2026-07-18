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
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App_Area::PART_EVENTS);

// Legacy Magento 1 REST is opt-in via apiplatform/protocols/legacy_rest.
// Disabled by default; operators must enable it consciously.
if (!Mage::helper('apiplatform')->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_LEGACY_REST)) {
    http_response_code(404);
    exit;
}

// query parameter "type" is set by .htaccess rewrite rule
$apiAlias = Mage::app()->getRequest()->getParam('type');

// check request could be processed by API2
if (in_array($apiAlias, Mage_Api2_Model_Server::getApiTypes())) {
    /** @var Mage_Api2_Model_Server $server */
    $server = Mage::getSingleton('api2/server');
    $server->run();
    exit;
}

/* @var $server Mage_Api_Model_Server */
$server = Mage::getSingleton('api/server');
if (!$apiAlias) {
    $adapterCode = 'default';
} else {
    $adapterCode = $server->getAdapterCodeByAlias($apiAlias);
}

// if no adapters found in aliases - find it by default, by code
if ($adapterCode === null) {
    $adapterCode = $apiAlias;
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
