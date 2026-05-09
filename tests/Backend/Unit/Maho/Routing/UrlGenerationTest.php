<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Mage_Adminhtml_Helper_Data as AdminhtmlHelper;
use Maho\Routing\RouteCollectionBuilder;

uses(Tests\MahoBackendTestCase::class);

/**
 * Pin a set of URL generation outputs so that regressions in the compiled
 * generator (CompiledUrlGenerator), the reverse-lookup map, or the legacy
 * string-building fallback show up immediately.
 *
 * We assert on paths (the base URL varies per install), which keeps the
 * tests stable across environments.
 */

function urlPath(string $route, ?array $params = null): string
{
    $url = Mage::getUrl($route, $params);
    $base = Mage::getBaseUrl();
    return substr($url, strlen(rtrim($base, '/')));
}

function resetAdminFrontNameCacheForUrl(): void
{
    $ref = new ReflectionClass(RouteCollectionBuilder::class);
    $prop = $ref->getProperty('adminFrontName');
    $prop->setValue(null, null);
}

describe('Mage::getUrl() via compiled generator (routed frontend URLs)', function () {
    it('generates a simple frontend URL with trailing slash', function () {
        expect(urlPath('checkout/cart'))->toBe('/checkout/cart/');
    });

    it('generates the customer login URL', function () {
        expect(urlPath('customer/account/login'))->toBe('/customer/account/login/');
    });

    it('substitutes path variables inline (no key/value segments)', function () {
        // catalog/product/view has #[Route('/catalog/product/view/{id}')] —
        // the id should land inline, not as an appended /id/14/ segment.
        expect(urlPath('catalog/product/view', ['id' => 14]))->toBe('/catalog/product/view/14/');
    });

    it('appends non-path-variable params as query string on frontend URLs', function () {
        // 'category' is not a declared path variable of catalog.product.view —
        // it should end up in the query string.
        $path = urlPath('catalog/product/view', ['id' => 14, 'category' => 5]);
        expect($path)->toBe('/catalog/product/view/14/?category=5');
    });
});

describe('Mage::getUrl() admin URLs', function () {
    beforeEach(function () {
        resetAdminFrontNameCacheForUrl();
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '0');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, '');
    });
    afterEach(fn() => resetAdminFrontNameCacheForUrl());

    it('uses the configured admin frontName in generated admin URLs', function () {
        // adminhtml/dashboard (no explicit action) uses the default action 'index'.
        expect(urlPath('adminhtml/dashboard'))->toBe('/admin/dashboard/index/');
    });

    it('appends non-path-variable params as /key/value segments on admin URLs', function () {
        // Admin URLs use path-style extras, not query string.
        $path = urlPath('adminhtml/dashboard', ['store' => 1]);
        expect($path)->toBe('/admin/dashboard/index/store/1/');
    });

    it('substitutes a custom admin path when use_custom_admin_path is on', function () {
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_USE_CUSTOM_ADMIN_PATH, '1');
        Mage::getConfig()->setNode(AdminhtmlHelper::XML_PATH_CUSTOM_ADMIN_PATH, 'backoffice');
        resetAdminFrontNameCacheForUrl();

        expect(urlPath('adminhtml/dashboard'))->toBe('/backoffice/dashboard/index/');
    });
});

describe('Mage::getUrl() home and fallback', function () {
    it('returns the base URL for an empty route', function () {
        expect(urlPath(''))->toBe('/');
    });

    it('accepts _query param and appends it as a query string', function () {
        expect(urlPath('', ['_query' => ['foo' => 'bar']]))->toBe('/?foo=bar');
    });

    it('falls back to legacy string-building for routes not in the compiled table', function () {
        // No #[Route] attribute matches frontName 'paypaluk' (real route uses 'payflow'),
        // so Mage::getUrl('paypaluk/...') must fall through to legacy path construction.
        expect(urlPath('paypaluk/express/start'))->toBe('/paypaluk/express/start/');
    });

    it('resolves the module whose route frontName differs from module key (PaypalUk → payflow)', function () {
        expect(urlPath('payflow/express/start'))->toBe('/payflow/express/start/');
    });
});
