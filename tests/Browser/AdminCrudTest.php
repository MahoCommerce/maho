<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Pest\Browser\Playwright\Playwright;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const CRUD_ADMIN_USER = 'admin-crud-test';
// The admin password policy asks for at least 14 characters
const CRUD_ADMIN_PASSWORD = 'CrudTestPassword123!';
const CRUD_CUSTOMER_EMAIL = 'admin-crud-customer@example.test';
const CRUD_PRODUCT_SKU = 'admin-crud-test-product';

/**
 * Create forms whose run cannot end in a delete, with the step that ends it.
 */
const CRUD_KNOWN_ENDS = [
    // An order status has no delete action.
    'sales_order_status/new' => ['delete', 'no delete url on the edit page'],
];

beforeEach(function () {
    createCrudAdmin();
});

afterEach(function () {
    deleteCrudAdmin();
    deleteCrudSalesFixtures();
});

function deleteCrudAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(CRUD_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

function createCrudAdmin(): void
{
    deleteCrudAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(CRUD_ADMIN_USER)
        ->setFirstname('Admin')
        ->setLastname('Crud')
        ->setEmail('admin-crud@example.test')
        ->setPassword(CRUD_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

function deleteCrudSalesFixtures(): void
{
    $store = Mage::app()->getDefaultStoreView();
    Mage::register('isSecureArea', true, true);
    try {
        $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $store->getWebsiteId())->loadByEmail(CRUD_CUSTOMER_EMAIL);
        if ($customer->getId()) {
            $customer->delete();
        }
        $productId = Mage::getModel('catalog/product')->getIdBySku(CRUD_PRODUCT_SKU);
        if ($productId) {
            Mage::getModel('catalog/product')->load($productId)->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
}

/**
 * A customer with a default address and a simple product in stock, in the default store view.
 *
 * @return array{int, int, int} the customer id, the store id and the product id
 */
function createCrudSalesFixtures(): array
{
    deleteCrudSalesFixtures();
    $store = Mage::app()->getDefaultStoreView();

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setStore($store)
        ->setGroupId(1)
        ->setFirstname('Admin')
        ->setLastname('Crud')
        ->setEmail(CRUD_CUSTOMER_EMAIL)
        ->save();

    Mage::getModel('customer/address')
        ->setCustomerId((int) $customer->getId())
        ->setFirstname('Admin')
        ->setLastname('Crud')
        ->setStreet('1 Test Street')
        ->setCity('Portland')
        ->setRegionId(49)
        ->setPostcode('97201')
        ->setCountryId('US')
        ->setTelephone('555-0100')
        ->setIsDefaultBilling()
        ->setIsDefaultShipping()
        ->save();

    $product = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setSku(CRUD_PRODUCT_SKU)
        ->setName('Admin CRUD test product')
        ->setPrice(30.0)
        ->setWeight(1.0)
        ->setTaxClassId(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([(int) $store->getWebsiteId()])
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1])
        ->save();

    return [(int) $customer->getId(), (int) $store->getId(), (int) $product->getId()];
}

/**
 * Await a function of admin-crud.js. A crawl or a CRUD run takes longer than the default
 * 5 second timeout of a browser call.
 */
function crudCall(object $page, string $function, mixed $arg = null): mixed
{
    $timeout = Playwright::timeout();
    Playwright::setTimeout(300_000);
    try {
        return $page->page()->evaluate($function, $arg);
    } finally {
        Playwright::setTimeout($timeout);
    }
}

/**
 * Log in, open $path and load admin-crud.js into it.
 */
function crudAdminPage(string $path = '/admin/dashboard', string $readySelector = '.nav-bar:visible'): object
{
    $page = adminLoginAndVisit(CRUD_ADMIN_USER, CRUD_ADMIN_PASSWORD, $path, $readySelector);
    $page->script((string) file_get_contents(__DIR__ . '/admin-crud.js'));
    $page->page()->evaluate('([password, productId]) => { window.__crud.password = password; window.__crud.productId = productId; }', [
        CRUD_ADMIN_PASSWORD,
        (int) Mage::getResourceModel('catalog/product_collection')->setPageSize(1)->getFirstItem()->getId(),
    ]);

    return $page;
}

it('loads every admin menu page and every config section', function () {
    expect(crudCall(crudAdminPage(), '() => window.__crud.crawl()'))->toBe([]);
});

it('creates, reads, updates and deletes through every admin create form', function () {
    $page = crudAdminPage();
    $forms = crudCall($page, '() => window.__crud.createForms()');

    expect($forms)->not->toBeEmpty();

    $failures = [];
    foreach ($forms as $url) {
        $report = crudCall($page, 'url => window.__crud.one(url)', $url);
        $steps = $report['steps'];
        $last = array_key_last($steps);

        if (isset(CRUD_KNOWN_ENDS[$report['form']])) {
            [$step, $reason] = CRUD_KNOWN_ENDS[$report['form']];
            if ($last === $step && $steps[$last] === $reason) {
                continue;
            }
        } elseif ($last === 'delete' && preg_match('/deleted|removed/i', $steps['delete'])) {
            continue;
        }

        $failures[] = $report['form'] . ' ' . ($report['edit'] ?? '') . ' ' . json_encode($steps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    expect($failures)->toBe([]);
});

it('saves every config section with no change', function () {
    $page = crudAdminPage();
    $sections = crudCall($page, '() => window.__crud.configSections()');

    $failures = [];
    foreach ($sections as $url) {
        $outcome = crudCall($page, 'url => window.__crud.saveSection(url)', $url);
        if ($outcome !== 'The configuration has been saved.') {
            $failures[] = preg_replace('#.*/section/([^/]+)/.*#', '$1', $url) . ': ' . $outcome;
        }
    }

    expect($failures)->toBe([]);
});

it('runs the admin sales flow from a new order to a credit memo', function () {
    [$customerId, $storeId, $productId] = createCrudSalesFixtures();
    $page = crudAdminPage('/admin/sales_order_create/start', '#order-customer-selector');

    $steps = crudCall($page, 'ids => window.__crud.salesFlow(...ids)', [$customerId, $storeId, $productId]);

    expect($steps)->toBe([
        'quote' => 'ok',
        'order' => 'The order has been created.',
        'invoice' => 'The invoice and shipment have been created.',
        'invoice pdf' => 'ok',
        'shipment pdf' => 'ok',
        'creditmemo' => 'The credit memo has been created.',
        'reorder' => 'ok',
    ]);
});
