<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

// query parameter "type" is set by .htaccess rewrite rule
$apiAlias = Mage::app()->getRequest()->getParam('type');

// check request could be processed by API2
if (in_array($apiAlias, Mage_Api2_Model_Server::getApiTypes())) {
    // emulate index.php entry point for correct URLs generation in API
    Mage::register('custom_entry_point', true);
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
    // emulate index.php entry point for correct URLs generation in API
    Mage::register('custom_entry_point', true);
    $server->run();
    Mage::app()->getResponse()->sendResponse();
} catch (Exception $e) {
    Mage::logException($e);
    echo $e->getMessage();
    exit;
}
