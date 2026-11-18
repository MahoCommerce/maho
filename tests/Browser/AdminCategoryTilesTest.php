<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The tile view of the Category Products tab (issue #1624): the merchant drags a tile, saves the category and
 * finds the new order after a reload, and a category that sorts by something else shows read-only tiles.
 */
const CATEGORY_TILES_SKU_PREFIX = 'pest-category-tiles-';
const CATEGORY_TILES_ADMIN_USERNAME = 'pest_category_tiles';
const CATEGORY_TILES_ADMIN_PASSWORD = 'PestCategoryTiles2026!';

beforeEach(function () {
    deleteCategoryTilesFixtures();
});

afterEach(function () {
    deleteCategoryTilesFixtures();
});

/**
 * A category with three products at the positions 1, 2 and 3.
 *
 * @return array{category: int, products: list<int>}
 */
function createCategoryTilesFixtures(?string $sort = null): array
{
    $ids = [];
    foreach (['alpha', 'beta', 'gamma'] as $name) {
        $product = Mage::getModel('catalog/product');
        $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setSku(CATEGORY_TILES_SKU_PREFIX . $name)
            ->setName('Pest Tiles ' . ucfirst($name))
            ->setPrice(19.99)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->save();
        $ids[] = (int) $product->getId();
    }

    $category = Mage::getModel('catalog/category');
    $category->setName('Pest Category Tiles')
        ->setPath('1/2')
        ->setIsActive(1)
        ->setIncludeInMenu(0)
        ->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT)
        ->setAttributeSetId($category->getDefaultAttributeSetId())
        ->setPostedProducts([$ids[0] => 1, $ids[1] => 2, $ids[2] => 3]);
    if ($sort !== null) {
        $category->setData('available_sort_by', ['position', $sort])->setData('default_sort_by', $sort);
    }
    $category->save();

    return ['category' => (int) $category->getId(), 'products' => $ids];
}

function deleteCategoryTilesFixtures(): void
{
    Mage::register('isSecureArea', true);
    try {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
        $products = Mage::getResourceModel('catalog/product_collection');
        $products->addAttributeToFilter('sku', ['like' => CATEGORY_TILES_SKU_PREFIX . '%']);
        foreach ($products as $product) {
            $product->delete();
        }
        $categories = Mage::getResourceModel('catalog/category_collection');
        $categories->addAttributeToFilter('name', 'Pest Category Tiles');
        foreach ($categories as $category) {
            $category->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }

    $user = Mage::getModel('admin/user')->loadByUsername(CATEGORY_TILES_ADMIN_USERNAME);
    if ($user->getId()) {
        $roleId = (int) $user->getRole()->getRoleId();
        $user->delete();
        if ($roleId) {
            Mage::getModel('admin/roles')->load($roleId)->delete();
        }
    }
}

/** Log in with a throwaway admin and open the tile view of the Category Products tab. */
function visitCategoryTiles(int $categoryId): object
{
    $role = Mage::getModel('admin/roles')->setName('Pest Category Tiles')->setRoleType('G')->setParentId(0)->save();
    Mage::getModel('admin/rules')->setRoleId($role->getId())->setResources(['all'])->saveRel();
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => CATEGORY_TILES_ADMIN_USERNAME,
        'firstname' => 'Pest',
        'lastname' => 'Tiles',
        'email' => CATEGORY_TILES_ADMIN_USERNAME . '@example.test',
        'password' => CATEGORY_TILES_ADMIN_PASSWORD,
        'is_active' => 1,
    ])->save();
    Mage::getModel('admin/user')->setRoleId($role->getId())->setUserId($user->getId())->add();

    $page = adminLoginAndVisit(
        CATEGORY_TILES_ADMIN_USERNAME,
        CATEGORY_TILES_ADMIN_PASSWORD,
        '/admin/catalog_category/edit/id/' . $categoryId,
        '#category_info_tabs_products',
    );
    $page->click('#category_info_tabs_products');
    $page->click('#category_products_view_tiles');
    $page->text('.merchandiser-tile:visible');

    return $page;
}

/** The product IDs of the tiles, in the order of the page. */
function categoryTilesOrder(object $page): string
{
    return (string) $page->script("[...document.querySelectorAll('.merchandiser-tile')].map((tile) => tile.dataset.id).join(',')");
}

it('keeps the order that the merchant drags after Save Category and a reload', function () {
    $fixtures = createCategoryTilesFixtures();
    [$alpha, $beta, $gamma] = $fixtures['products'];

    $page = visitCategoryTiles($fixtures['category']);
    expect(categoryTilesOrder($page))->toBe("$alpha,$beta,$gamma");

    $page->drag(".merchandiser-tile[data-id=\"$gamma\"] .merchandiser-image", ".merchandiser-tile[data-id=\"$alpha\"] .merchandiser-image");
    waitForScript($page, "document.querySelector('.merchandiser-tile').dataset.id === '$gamma'");
    $page->click('button.save');
    $page->text('.success-msg');

    $page->navigate($page->url());
    $page->click('#category_info_tabs_products');
    $page->click('#category_products_view_tiles');
    $page->text('.merchandiser-tile:visible');

    expect(categoryTilesOrder($page))->toBe("$gamma,$alpha,$beta");
});

it('saves the product that the merchant pins', function () {
    $fixtures = createCategoryTilesFixtures();
    [, $beta] = $fixtures['products'];

    $page = visitCategoryTiles($fixtures['category']);
    $page->click(".merchandiser-tile[data-id=\"$beta\"] .merchandiser-pin");
    $page->click('button.save');
    $page->text('.success-msg');

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $isPinned = $adapter->fetchOne($adapter->select()
        ->from($resource->getTableName('catalog/category_product'), ['is_pinned'])
        ->where('category_id = ?', $fixtures['category'])
        ->where('product_id = ?', $beta));

    expect((int) $isPinned)->toBe(1);
});

it('shows read-only tiles and the reason when the category sorts by Bestsellers', function () {
    $fixtures = createCategoryTilesFixtures('bestsellers');

    $page = visitCategoryTiles($fixtures['category']);

    $page->assertSee('This category sorts by Bestsellers.')
        ->assertNotPresent('.merchandiser-tile .merchandiser-select')
        ->assertNotPresent('.merchandiser-tile .merchandiser-pin');
});
