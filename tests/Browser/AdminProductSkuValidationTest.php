<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for #1267: an ajax validation error must reach the form, not abort the request.
 *
 * catalog_product/validate reports a duplicate SKU as data, { error: true, attribute: 'sku' },
 * with a 200. mahoFetch() turns an { error: true } payload into a thrown MahoError, so
 * varienForm._validate() landed in its catch, called _processFailure() and sent the browser to
 * BASE_URL. The admin lost the form and the message and arrived on the dashboard.
 *
 * Only a browser can catch that: the controller returns the right payload either way, and the
 * repo has no js test harness.
 */

const SKU_VALIDATION_SKU_PREFIX = 'pest-sku-validation-';
const SKU_VALIDATION_ADMIN_USERNAME = 'pest_sku_validation';
const SKU_VALIDATION_ADMIN_PASSWORD = 'PestSkuValidation2026!';

beforeEach(function () {
    deleteSkuValidationFixtures();
});

afterEach(function () {
    deleteSkuValidationFixtures();
});

function createSkuValidationProduct(string $sku, string $name): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setSku(SKU_VALIDATION_SKU_PREFIX . $sku)
        ->setName($name)
        // Every required attribute, or client-side validation stops the save before the ajax call.
        ->setDescription($name . ' description')
        ->setShortDescription($name . ' short description')
        ->setPrice(24.99)
        ->setWeight(1)
        ->setTaxClassId(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1])
        ->save();

    return $product;
}

function deleteSkuValidationFixtures(): void
{
    /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
    $products = Mage::getResourceModel('catalog/product_collection');
    $products->addAttributeToFilter('sku', ['like' => SKU_VALIDATION_SKU_PREFIX . '%']);

    // Product deletion is admin-guarded, hence isSecureArea.
    Mage::register('isSecureArea', true);
    try {
        foreach ($products as $product) {
            $product->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }

    $user = Mage::getModel('admin/user')->loadByUsername(SKU_VALIDATION_ADMIN_USERNAME);
    if ($user->getId()) {
        $roleId = (int) $user->getRole()->getRoleId();
        $user->delete();
        if ($roleId) {
            Mage::getModel('admin/roles')->load($roleId)->delete();
        }
    }
}

/** A throwaway admin with full access, so the test doesn't depend on the install's password. */
function createSkuValidationAdminUser(): void
{
    $role = Mage::getModel('admin/roles')
        ->setName('Pest Sku Validation')
        ->setRoleType('G')
        ->setParentId(0)
        ->save();

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources(['all'])
        ->saveRel();

    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => SKU_VALIDATION_ADMIN_USERNAME,
        'firstname' => 'Pest',
        'lastname' => 'Sku',
        'email' => SKU_VALIDATION_ADMIN_USERNAME . '@example.test',
        'password' => SKU_VALIDATION_ADMIN_PASSWORD,
        'is_active' => 1,
    ])->save();

    Mage::getModel('admin/user')->load($user->getId())
        ->setRoleIds([$role->getId()])
        ->saveRelations();
}

it('reports a duplicate sku on the product form instead of leaving for the dashboard', function () {
    $taken = createSkuValidationProduct('taken', 'Pest Sku Taken');
    $edited = createSkuValidationProduct('edited', 'Pest Sku Edited');

    createSkuValidationAdminUser();

    $page = adminLoginAndVisit(
        SKU_VALIDATION_ADMIN_USERNAME,
        SKU_VALIDATION_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $edited->getId(),
        '#sku',
    );

    $page->fill('#sku', $taken->getSku())
        ->click('button[title="Save"]');

    // Reading the advice waits for the ajax round trip to paint it. Pre-fix it never appeared:
    // the browser navigated away instead.
    $page->text('#advice-validate-ajax-sku:visible');

    $page->assertSee('must be unique');

    // By url: the admin menu carries a visible "Dashboard" link on every page.
    expect($page->url())->toContain('catalog_product/edit');
});
