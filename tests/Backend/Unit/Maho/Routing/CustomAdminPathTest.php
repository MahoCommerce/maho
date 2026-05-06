<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Mage_Adminhtml_Helper_Data as AdminhtmlHelper;
use Maho\Routing\ControllerDispatcher;
use Maho\Routing\RouteCollectionBuilder;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Reset the compile-time admin frontName cache so tests can swap configs.
 */
function resetAdminFrontNameCache(): void
{
    $ref = new ReflectionClass(RouteCollectionBuilder::class);
    $prop = $ref->getProperty('adminFrontName');
    $prop->setValue(null, null);
}

function makeRequest(): Mage_Core_Controller_Request_Http
{
    return new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
}

describe('RouteCollectionBuilder::getAdminFrontName()', function () {
    beforeEach(fn() => resetAdminFrontNameCache());
    afterEach(fn() => resetAdminFrontNameCache());

    it('returns the default admin base_path when use_custom_path is off', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '0');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, '');

        expect(RouteCollectionBuilder::getAdminFrontName())->toBe('admin');
    });

    it('returns the custom path when use_custom_path is enabled and custom_path is set', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '1');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, 'backoffice');

        expect(RouteCollectionBuilder::getAdminFrontName())->toBe('backoffice');
    });

    it('falls back to default when use_custom_path is enabled but custom_path is empty', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '1');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, '');

        expect(RouteCollectionBuilder::getAdminFrontName())->toBe('admin');
    });
});

describe('RouteCollectionBuilder::normalizeFrontName()', function () {
    beforeEach(fn() => resetAdminFrontNameCache());
    afterEach(fn() => resetAdminFrontNameCache());

    it('maps the configured admin frontName to the sentinel regardless of case', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '1');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, 'BackOffice');

        expect(RouteCollectionBuilder::normalizeFrontName('backoffice'))
            ->toBe(RouteCollectionBuilder::ADMIN_SENTINEL);
        expect(RouteCollectionBuilder::normalizeFrontName('BACKOFFICE'))
            ->toBe(RouteCollectionBuilder::ADMIN_SENTINEL);
    });

    it('leaves non-admin frontNames as-is (lowercased)', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '0');

        expect(RouteCollectionBuilder::normalizeFrontName('catalog'))->toBe('catalog');
        expect(RouteCollectionBuilder::normalizeFrontName('notadmin'))->toBe('notadmin');
    });
});

describe('ControllerDispatcher::dispatch() admin frontName forgery rejection', function () {
    beforeEach(function () {
        resetAdminFrontNameCache();
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '0');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, '');
    });
    afterEach(fn() => resetAdminFrontNameCache());

    it('rejects admin routes whose matched frontName does not match the configured one', function () {
        $dispatcher = new ControllerDispatcher();
        $request = makeRequest();
        $response = new Mage_Core_Controller_Response_Http();

        $params = [
            '_maho_controller' => Mage_Adminhtml_IndexController::class,
            '_maho_action' => 'indexAction',
            '_maho_module' => 'Mage_Adminhtml',
            '_maho_controller_name' => 'index',
            '_maho_area' => 'adminhtml',
            '_adminFrontName' => 'notadmin',
        ];

        $result = $dispatcher->dispatch($params, $request, $response);

        expect($result)->toBeFalse();
        expect($request->isDispatched())->toBeFalse();
    });

    it('rejects admin routes with a missing _adminFrontName param', function () {
        $dispatcher = new ControllerDispatcher();
        $request = makeRequest();
        $response = new Mage_Core_Controller_Response_Http();

        $params = [
            '_maho_controller' => Mage_Adminhtml_IndexController::class,
            '_maho_action' => 'indexAction',
            '_maho_module' => 'Mage_Adminhtml',
            '_maho_controller_name' => 'index',
            '_maho_area' => 'adminhtml',
        ];

        $result = $dispatcher->dispatch($params, $request, $response);

        expect($result)->toBeFalse();
    });

    it('rejects the default admin frontName when a custom path is configured', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '1');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, 'backoffice');
        resetAdminFrontNameCache();

        $dispatcher = new ControllerDispatcher();
        $request = makeRequest();
        $response = new Mage_Core_Controller_Response_Http();

        $params = [
            '_maho_controller' => Mage_Adminhtml_IndexController::class,
            '_maho_action' => 'indexAction',
            '_maho_module' => 'Mage_Adminhtml',
            '_maho_controller_name' => 'index',
            '_maho_area' => 'adminhtml',
            '_adminFrontName' => 'admin',
        ];

        expect($dispatcher->dispatch($params, $request, $response))->toBeFalse();
    });
});
