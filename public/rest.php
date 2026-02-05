<?php

/**
 * Maho REST API v2 Entry Point
 *
 * Bootstraps Maho and Symfony API Platform for REST API requests.
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
Mage::setIsDeveloperMode(false); // Disable dev mode for clean error responses
Mage::init('admin');
Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);

// Check for admin endpoint access - validate admin session
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/api/admin/') !== false) {
    // Load adminhtml area for session handling
    Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App_Area::PART_EVENTS);

    // Read request body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $formKey = $input['form_key'] ?? null;

    // Find admin session cookie (might have different name prefix)
    $adminCookieName = null;
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'admin') !== false || $name === 'adminhtml' || $name === 'backend') {
            $adminCookieName = $name;
            break;
        }
    }

    // Try to restore admin session using Mage session handling
    $adminSession = Mage::getSingleton('admin/session');

    $isAuthenticated = false;
    $adminUser = null;

    // Check if admin session is logged in
    if ($adminSession->isLoggedIn()) {
        $isAuthenticated = true;
        $adminUser = $adminSession->getUser();
    }

    // If form_key provided but session not detected, try to validate form key
    if (!$isAuthenticated && $formKey) {
        // Get admin user from session by checking if form_key matches any active admin session
        $coreSession = Mage::getSingleton('core/session');
        $sessionFormKey = $coreSession->getData('_form_key');

        // Also check adminhtml session
        $adminhtmlSession = Mage::getSingleton('adminhtml/session');
        $adminhtmlFormKey = $adminhtmlSession->getData('_form_key');

        if (($sessionFormKey === $formKey || $adminhtmlFormKey === $formKey) && $adminSession->getUser()) {
            $isAuthenticated = true;
            $adminUser = $adminSession->getUser();
        }
    }

    if ($isAuthenticated && $adminUser) {
        $_SERVER['MAHO_ADMIN_USER_ID'] = $adminUser->getId();
        $_SERVER['MAHO_ADMIN_USERNAME'] = $adminUser->getUsername();
        $_SERVER['MAHO_STORE_ID'] = $input['variables']['storeId'] ?? 1;
    }
}

// Include Symfony API Platform front controller
require MAHO_ROOT_DIR . '/app/code/core/Maho/ApiPlatform/symfony/public/index.php';
