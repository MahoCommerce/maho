<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for serialized grids losing their selection on an ajax reload.
 *
 * The product Related/Up-sell/Cross-sell/Grouped tabs, the tag "assigned products" grid and
 * the blog category posts grid all pair a grid with a serializerController: the checked rows
 * live in js and travel back to the server in a reload param (products_related[] here), from
 * which the block rebuilds its "in list" filter. When serializerController stopped writing
 * that param, sorting a column posted nothing, the block filtered on an empty set and the
 * grid rendered "No records found" with every checkbox lost.
 *
 * Only a browser can catch that: the server side works fine when the param arrives, and the
 * repo has no js test harness.
 */

const RELATED_GRID_SKU_PREFIX = 'pest-related-grid-';
const RELATED_GRID_ADMIN_USERNAME = 'pest_related_grid';
const RELATED_GRID_ADMIN_PASSWORD = 'PestRelatedGrid2026!';
const RELATED_GRID_ALPHA = 'Pest Related Alpha';
const RELATED_GRID_BETA = 'Pest Related Beta';

beforeEach(function () {
    deleteRelatedGridFixtures();

    // Admin urls carry a secret key derived from the session's form key, which this process
    // can't mint, so navigating straight to the product edit page needs the key turned off.
    Mage::getModel('core/config')->saveConfig(
        Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY,
        '0',
    );
    Mage::app()->cleanCache();
});

afterEach(function () {
    Mage::getModel('core/config')->deleteConfig(
        Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY,
    );
    Mage::app()->cleanCache();
    deleteRelatedGridFixtures();
});

/** A simple, saleable product the admin grid can list. */
function createRelatedGridProduct(string $sku, string $name): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setSku(RELATED_GRID_SKU_PREFIX . $sku)
        ->setName($name)
        ->setPrice(19.99)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1])
        ->save();

    return $product;
}

/**
 * A parent product with two related products.
 *
 * @return array{parent: int, alpha: int, beta: int} product ids
 */
function createRelatedGridFixtures(): array
{
    $alpha = createRelatedGridProduct('alpha', RELATED_GRID_ALPHA);
    $beta = createRelatedGridProduct('beta', RELATED_GRID_BETA);

    $parent = createRelatedGridProduct('parent', 'Pest Related Parent');
    $parent->setRelatedLinkData([
        $alpha->getId() => ['position' => 1],
        $beta->getId() => ['position' => 2],
    ])->save();

    return [
        'parent' => (int) $parent->getId(),
        'alpha' => (int) $alpha->getId(),
        'beta' => (int) $beta->getId(),
    ];
}

function deleteRelatedGridFixtures(): void
{
    /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
    $products = Mage::getResourceModel('catalog/product_collection');
    $products->addAttributeToFilter('sku', ['like' => RELATED_GRID_SKU_PREFIX . '%']);

    // Product deletion is admin-guarded, hence isSecureArea.
    Mage::register('isSecureArea', true);
    try {
        foreach ($products as $product) {
            $product->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }

    $user = Mage::getModel('admin/user')->loadByUsername(RELATED_GRID_ADMIN_USERNAME);
    if ($user->getId()) {
        $roleId = (int) $user->getRole()->getRoleId();
        $user->delete();
        if ($roleId) {
            Mage::getModel('admin/roles')->load($roleId)->delete();
        }
    }
}

/** A throwaway admin with full access, so the test doesn't depend on the install's password. */
function createRelatedGridAdminUser(): void
{
    $role = Mage::getModel('admin/roles')
        ->setName('Pest Related Grid')
        ->setRoleType('G')
        ->setParentId(0)
        ->save();

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources(['all'])
        ->saveRel();

    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => RELATED_GRID_ADMIN_USERNAME,
        'firstname' => 'Pest',
        'lastname' => 'Grid',
        'email' => RELATED_GRID_ADMIN_USERNAME . '@example.test',
        'password' => RELATED_GRID_ADMIN_PASSWORD,
        'is_active' => 1,
    ])->save();

    Mage::getModel('admin/user')
        ->setRoleId($role->getId())
        ->setUserId($user->getId())
        ->add();
}

/** Log in and open the product's Related Products tab, with both related products listed. */
function visitRelatedProductsTab(int $parentId): object
{
    createRelatedGridAdminUser();

    $page = visit(MahoServer::baseUrl() . '/admin')
        ->fill('#username', RELATED_GRID_ADMIN_USERNAME)
        ->fill('#login', RELATED_GRID_ADMIN_PASSWORD)
        ->click('#step1 input[type="submit"]');

    waitForPageLoad($page, '.nav-bar:visible');
    $page->navigate(MahoServer::baseUrl() . '/admin/catalog_product/edit/id/' . $parentId);
    waitForPageLoad($page, '#product_info_tabs_related');

    // An inactive tab keeps its content attached, so `:visible` is what distinguishes the
    // tab having opened from the grid merely existing in the page.
    $page->click('#product_info_tabs_related');
    $page->text('#related_product_grid_table:visible');

    return $page->assertSee(RELATED_GRID_ALPHA)
        ->assertSee(RELATED_GRID_BETA);
}

/** Sort the grid by a column and wait for the reloaded markup, sorted or not, to land. */
function sortRelatedGridBy(object $page, string $columnId): object
{
    $page->click('#related_product_grid_table th[data-column-id="' . $columnId . '"] a');

    // Only the reloaded header carries the "sorted" class, and reading it waits for it,
    // so this returns on the new markup whether or not it still lists anything.
    $page->text('#related_product_grid_table th[data-column-id="' . $columnId . '"].sorted:visible');

    return $page;
}

/** The grid's "in list" checkbox for a product. It's rendered without a name attribute. */
function relatedGridCheckbox(int $productId): string
{
    return '#related_product_grid_table input[type="checkbox"][value="' . $productId . '"]';
}

it('keeps the related products listed and checked after sorting a column', function () {
    $ids = createRelatedGridFixtures();

    $page = sortRelatedGridBy(visitRelatedProductsTab($ids['parent']), 'name');

    // Pre-fix the reload posted no selection, so the grid came back empty.
    $page->assertSee(RELATED_GRID_ALPHA)
        ->assertSee(RELATED_GRID_BETA)
        ->assertDontSee('No records found')
        ->assertChecked(relatedGridCheckbox($ids['alpha']))
        ->assertChecked(relatedGridCheckbox($ids['beta']));
});

it('carries an unchecked row through the sort instead of restoring it', function () {
    $ids = createRelatedGridFixtures();

    $page = visitRelatedProductsTab($ids['parent'])
        ->uncheck(relatedGridCheckbox($ids['beta']));

    // The grid filters on "in list = yes", so only the still-checked product may come back.
    sortRelatedGridBy($page, 'name')
        ->assertSee(RELATED_GRID_ALPHA)
        ->assertDontSee(RELATED_GRID_BETA)
        ->assertDontSee('No records found');
});
