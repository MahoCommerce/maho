<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for #1420: clearing "Create Permanent Redirect for old URL" must stop the 301.
 *
 * The url key field carries a checkbox and a hidden input of the same name, both disabled until
 * the key changes. An unchecked checkbox posts nothing, so the hidden input is the only thing
 * that can post the "no redirect" answer. onUrlkeyChanged() read it as the immediate next
 * sibling of the url key field, but the renderer puts a <br> between them, so the hidden input
 * stayed disabled. The controller saw no value, kept the store default and wrote the 301.
 *
 * Only a browser can catch that: the controller obeys the posted value either way, and the repo
 * has no js test harness.
 */

const URL_KEY_REDIRECT_SKU_PREFIX = 'pest-url-key-redirect-';
const URL_KEY_REDIRECT_ADMIN_USERNAME = 'pest_url_key_redirect';
const URL_KEY_REDIRECT_ADMIN_PASSWORD = 'PestUrlKeyRedirect2026!';

beforeEach(function () {
    deleteUrlKeyRedirectFixtures();
});

afterEach(function () {
    deleteUrlKeyRedirectFixtures();
});

function createUrlKeyRedirectProduct(string $suffix, string $urlKey): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setSku(URL_KEY_REDIRECT_SKU_PREFIX . $suffix)
        ->setName('Pest Url Key ' . $suffix)
        // Every required attribute, or client-side validation stops the save before the post.
        ->setDescription('Pest url key description')
        ->setShortDescription('Pest url key short description')
        ->setPrice(31.50)
        ->setWeight(1)
        ->setTaxClassId(0)
        ->setUrlKey($urlKey)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1])
        ->save();

    return $product;
}

function deleteUrlKeyRedirectFixtures(): void
{
    /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
    $products = Mage::getResourceModel('catalog/product_collection');
    $products->addAttributeToFilter('sku', ['like' => URL_KEY_REDIRECT_SKU_PREFIX . '%']);

    // Product deletion is admin-guarded, hence isSecureArea.
    Mage::register('isSecureArea', true);
    try {
        foreach ($products as $product) {
            $product->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }

    $user = Mage::getModel('admin/user')->loadByUsername(URL_KEY_REDIRECT_ADMIN_USERNAME);
    if ($user->getId()) {
        $roleId = (int) $user->getRole()->getRoleId();
        $user->delete();
        if ($roleId) {
            Mage::getModel('admin/roles')->load($roleId)->delete();
        }
    }
}

/** A throwaway admin with full access, so the test does not depend on the install's password. */
function createUrlKeyRedirectAdminUser(): void
{
    $role = Mage::getModel('admin/roles')
        ->setName('Pest Url Key Redirect')
        ->setRoleType('G')
        ->setParentId(0)
        ->save();

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources(['all'])
        ->saveRel();

    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => URL_KEY_REDIRECT_ADMIN_USERNAME,
        'firstname' => 'Pest',
        'lastname' => 'UrlKey',
        'email' => URL_KEY_REDIRECT_ADMIN_USERNAME . '@example.test',
        'password' => URL_KEY_REDIRECT_ADMIN_PASSWORD,
        'is_active' => 1,
    ])->save();

    Mage::getModel('admin/user')->load($user->getId())
        ->setRoleIds([$role->getId()])
        ->saveRelations();
}

/** The number of permanent redirects that point away from $urlKey. */
function countUrlKeyRedirects(string $urlKey): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()
        ->from(['r' => $resource->getTableName('core/url_rewrite')], ['total' => new Maho\Db\Expr('COUNT(*)')])
        ->where('request_path LIKE ?', $urlKey . '%')
        ->where('options = ?', 'RP');

    return (int) $adapter->fetchOne($select);
}

/** Open the product, put $newUrlKey in the url key field and save. */
function saveProductWithUrlKey(int $productId, string $newUrlKey, bool $keepRedirect): void
{
    $page = adminLoginAndVisit(
        URL_KEY_REDIRECT_ADMIN_USERNAME,
        URL_KEY_REDIRECT_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $productId,
        '#url_key',
    );

    // The checkbox and the hidden input stay disabled until this change enables them.
    $page->fill('#url_key', $newUrlKey);

    if ($keepRedirect) {
        $page->check('#url_key_create_redirect');
    } else {
        $page->uncheck('#url_key_create_redirect');
    }

    // The wait covers the navigation starting, which waitForPageLoad() cannot.
    $page->click('button[title="Save"]')->wait(2);

    // Only the product grid holds this id, so the edit page cannot satisfy the wait.
    waitForPageLoad($page, '#productGrid');
}

it('creates no permanent redirect when the admin clears the redirect checkbox', function () {
    $product = createUrlKeyRedirectProduct('off', 'pest-url-key-off-old');
    createUrlKeyRedirectAdminUser();

    saveProductWithUrlKey((int) $product->getId(), 'pest-url-key-off-new', keepRedirect: false);

    expect(countUrlKeyRedirects('pest-url-key-off-old'))->toBe(0);
});

it('creates a permanent redirect when the admin keeps the redirect checkbox', function () {
    $product = createUrlKeyRedirectProduct('on', 'pest-url-key-on-old');
    createUrlKeyRedirectAdminUser();

    saveProductWithUrlKey((int) $product->getId(), 'pest-url-key-on-new', keepRedirect: true);

    // The save writes one row per store view, so the exact count depends on the install.
    expect(countUrlKeyRedirects('pest-url-key-on-old'))->toBeGreaterThan(0);
});
