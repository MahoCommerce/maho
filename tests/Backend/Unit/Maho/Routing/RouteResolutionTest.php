<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Routing\RouteCollectionBuilder;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

uses(Tests\MahoBackendTestCase::class);

/**
 * Comprehensive smoke test over the compiled Symfony route table:
 * every listed URL must resolve to the named controller + action. This
 * catches regressions in `composer dump-autoload`'s attribute compiler
 * and in the CompiledUrlMatcher data file itself.
 *
 * The paths sampled cover every area (frontend, adminhtml, install) and
 * every route kind: static, path-variable, nested-path-variable.
 */

function matchPath(string $path): array
{
    $matcher = RouteCollectionBuilder::createMatcher(new RequestContext());
    return $matcher->match($path);
}

dataset('frontend_routes', [
    'home'                  => ['/',                              Mage_Cms_IndexController::class,              'indexAction'],
    'cms (cms frontname)'   => ['/cms',                           Mage_Cms_IndexController::class,              'indexAction'],
    'cms page view'         => ['/cms/page/view/2',               Mage_Cms_PageController::class,               'viewAction'],
    'catalog product view'  => ['/catalog/product/view/1',        Mage_Catalog_ProductController::class,        'viewAction'],
    'catalog category view' => ['/catalog/category/view/2',       Mage_Catalog_CategoryController::class,       'viewAction'],
    'checkout cart'         => ['/checkout/cart',                 Mage_Checkout_CartController::class,          'indexAction'],
    'checkout onepage'      => ['/checkout/onepage',              Mage_Checkout_OnepageController::class,       'indexAction'],
    'customer login'        => ['/customer/account/login',        Mage_Customer_AccountController::class,       'loginAction'],
    'customer create'       => ['/customer/account/create',       Mage_Customer_AccountController::class,       'createAction'],
    'wishlist'              => ['/wishlist',                      Mage_Wishlist_IndexController::class,         'indexAction'],
    'search results'        => ['/catalogsearch/result',          Mage_CatalogSearch_ResultController::class,   'indexAction'],
    'contacts'              => ['/contacts',                      Mage_Contacts_IndexController::class,         'indexAction'],
]);

dataset('admin_routes', [
    'dashboard'          => ['/admin/dashboard/index',          Mage_Adminhtml_DashboardController::class,          'indexAction'],
    'catalog product'    => ['/admin/catalog_product/index',    Mage_Adminhtml_Catalog_ProductController::class,    'indexAction'],
    'catalog category'   => ['/admin/catalog_category/index',   Mage_Adminhtml_Catalog_CategoryController::class,   'indexAction'],
    'sales order'        => ['/admin/sales_order/index',        Mage_Adminhtml_Sales_OrderController::class,        'indexAction'],
    'system config'      => ['/admin/system_config/index',      Mage_Adminhtml_System_ConfigController::class,      'indexAction'],
    'cms page'           => ['/admin/cms_page/index',           Mage_Adminhtml_Cms_PageController::class,           'indexAction'],
    'cms block'          => ['/admin/cms_block/index',          Mage_Adminhtml_Cms_BlockController::class,          'indexAction'],
    'customer'           => ['/admin/customer/index',           Mage_Adminhtml_CustomerController::class,           'indexAction'],
    'promo catalog'      => ['/admin/promo_catalog/index',      Mage_Adminhtml_Promo_CatalogController::class,      'indexAction'],
]);

dataset('install_routes', [
    'install index'     => ['/install',                    Mage_Install_IndexController::class,    'indexAction'],
    'wizard'            => ['/install/wizard',             Mage_Install_WizardController::class,   'indexAction'],
    'wizard license'    => ['/install/wizard/license',     Mage_Install_WizardController::class,   'licenseAction'],
    'wizard locale'     => ['/install/wizard/locale',      Mage_Install_WizardController::class,   'localeAction'],
    'wizard config'     => ['/install/wizard/configuration', Mage_Install_WizardController::class, 'configurationAction'],
    'wizard complete'   => ['/install/wizard/complete',    Mage_Install_WizardController::class,   'completeAction'],
]);

describe('Compiled matcher resolves frontend routes', function () {
    it('matches %s', function (string $path, string $expectedController, string $expectedAction) {
        $params = matchPath($path);
        expect($params['_maho_controller'] ?? null)->toBe($expectedController);
        expect($params['_maho_action'] ?? null)->toBe($expectedAction);
        expect($params['_maho_area'] ?? null)->toBe('frontend');
    })->with('frontend_routes');
});

describe('Compiled matcher resolves admin routes', function () {
    it('matches %s', function (string $path, string $expectedController, string $expectedAction) {
        $params = matchPath($path);
        expect($params['_maho_controller'] ?? null)->toBe($expectedController);
        expect($params['_maho_action'] ?? null)->toBe($expectedAction);
        expect($params['_maho_area'] ?? null)->toBe('adminhtml');
        expect($params['_adminFrontName'] ?? null)->toBe('admin');
    })->with('admin_routes');
});

describe('Compiled matcher resolves install routes', function () {
    it('matches %s', function (string $path, string $expectedController, string $expectedAction) {
        $params = matchPath($path);
        expect($params['_maho_controller'] ?? null)->toBe($expectedController);
        expect($params['_maho_action'] ?? null)->toBe($expectedAction);
        expect($params['_maho_area'] ?? null)->toBe('install');
    })->with('install_routes');
});

describe('Compiled matcher route table integrity', function () {
    it('loads a non-trivial number of compiled routes', function () {
        $compiled = Maho::getCompiledAttributes();
        // The exact count drifts as routes are added/removed; bound it so the
        // test catches "compiled file was truncated" but not normal churn.
        expect(count($compiled['routes']))->toBeGreaterThan(500);
    });

    it('has a populated reverse-lookup table for URL generation', function () {
        $compiled = Maho::getCompiledAttributes();
        // Mage::getUrl() resolves frontName/controller/action to a route name via
        // this table, so a missing or truncated reverseLookup silently breaks URL
        // generation. Bound it like the routes count.
        expect($compiled['reverseLookup'])->toBeArray();
        expect(count($compiled['reverseLookup']))->toBeGreaterThan(500);
    });

    it('has a controllerLookup map for resolveControllerClass', function () {
        $compiled = Maho::getCompiledAttributes();
        // Used by ControllerDispatcher::resolveControllerClass() + the legacy-path
        // fallback. The lookup stores full controller class FQCNs so the runtime
        // never has to reconstruct them by convention.
        expect($compiled['controllerLookup']['catalog/product'] ?? null)
            ->toBe('Mage_Catalog_ProductController');
        expect($compiled['controllerLookup']['__admin__/dashboard'] ?? null)
            ->toBe('Mage_Adminhtml_DashboardController');
        expect($compiled['controllerLookup']['__install__/wizard'] ?? null)
            ->toBe('Mage_Install_WizardController');
        // Maho-style admin module: class lives at controllers/Adminhtml/<Group>/, so the
        // FQCN has an `_Adminhtml_` infix. This is the case that broke forward-dispatch
        // when the lookup stored the module name and the runtime had to reconstruct.
        expect($compiled['controllerLookup']['__admin__/feedmanager_feed'] ?? null)
            ->toBe('Maho_FeedManager_Adminhtml_Feedmanager_FeedController');
    });

    it('rejects paths outside the route table via ResourceNotFoundException', function () {
        expect(fn() => matchPath('/definitely-not-a-real-path'))
            ->toThrow(ResourceNotFoundException::class);
    });
});
