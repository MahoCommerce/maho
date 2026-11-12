<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin mass actions and the fast update of the API write products without a product save, through
 * Mage_Catalog_Service_Product (#1348). The API product cache, the category product index and the URL rewrites
 * must follow each of these writes, whichever transport starts it.
 */

const WRITE_WITHOUT_SAVE_SKU = 'pest-write-without-save';

function writeWithoutSaveCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', WRITE_WITHOUT_SAVE_SKU) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => 'pest-wws-%']) as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
    Mage::unregister('isSecureArea');
}

/**
 * @param list<int> $websiteIds
 */
function writeWithoutSaveProduct(array $websiteIds = [1]): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct();
    $product->setSku(WRITE_WITHOUT_SAVE_SKU)
        ->setName('Pest Write Without Save')
        ->setPrice(10)
        ->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds($websiteIds)
        ->setUrlKey('pest-write-without-save-' . uniqid())
        ->setStockData(['qty' => 5, 'is_in_stock' => 1]);
    return $service->save($product);
}

function writeWithoutSaveCategory(): Mage_Catalog_Model_Category
{
    $rootId = (int) Mage::app()->getStore(1)->getRootCategoryId();
    /** @var Mage_Catalog_Service_Category $service */
    $service = Mage::getService('catalog/category');
    $category = $service->newCategory($rootId);
    $category->setName('Pest Write Without Save')
        ->setUrlKey('pest-wws-' . uniqid())
        ->setIsActive(1)
        ->setIsAnchor(1)
        ->setIncludeInMenu(1);
    return $service->save($category);
}

/**
 * Return the request path of the URL rewrite without a category of the product $productId in the store view 1.
 */
function writeWithoutSaveProductPath(int $productId): ?string
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $path = $adapter->fetchOne(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), 'request_path')
            ->where('product_id = ?', $productId)
            ->where('category_id IS NULL')
            ->where('store_id = ?', 1),
    );
    return $path === false ? null : (string) $path;
}

function writeWithoutSaveApiCacheIsClean(): bool
{
    return Mage::app()->getCache()->load('pest_write_without_save') === false;
}

function writeWithoutSaveFillApiCache(int $productId): void
{
    Mage::app()->getCache()->save('cached', 'pest_write_without_save', ['API_PRODUCTS', "API_PRODUCT_{$productId}"]);
}

beforeEach(fn() => writeWithoutSaveCleanup());
afterEach(fn() => writeWithoutSaveCleanup());

describe('the API product cache', function (): void {
    it('is cleaned after a write without a product save', function (Closure $write): void {
        $product = writeWithoutSaveProduct();
        $category = writeWithoutSaveCategory();
        writeWithoutSaveFillApiCache((int) $product->getId());
        expect(writeWithoutSaveApiCacheIsClean())->toBeFalse();

        $write((int) $product->getId(), (int) $category->getId());

        expect(writeWithoutSaveApiCacheIsClean())->toBeTrue();
    })->with([
        'admin mass status' => [fn(int $id) => Mage::getService('catalog/product')->updateStatus([$id], Mage_Catalog_Model_Product_Status::STATUS_DISABLED, 0)],
        'admin mass attribute update' => [fn(int $id) => Mage::getService('catalog/product')->updateAttributes([$id], ['name' => 'Renamed'], 0)],
        'admin mass website change' => [fn(int $id) => Mage::getService('catalog/product')->changeWebsites([$id], [], [1])],
        'category assignment of the API fast update' => [fn(int $id, int $categoryId) => Mage::getService('catalog/product')->setCategoryIds($id, [$categoryId])],
        'store value removal of the API fast update' => [function (int $id): void {
            Mage::getSingleton('catalog/product_action')->updateAttributes([$id], ['name' => 'Store name'], 1);
            writeWithoutSaveFillApiCache($id);
            Mage::getService('catalog/product')->deleteStoreValues($id, ['name'], 1);
        }],
    ]);
});

describe('a category assignment without a product save', function (): void {
    it('updates the category product index and the URL rewrites of the product', function (): void {
        $product = writeWithoutSaveProduct();
        $category = writeWithoutSaveCategory();
        $productId = (int) $product->getId();
        $categoryId = (int) $category->getId();
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $indexRows = fn(): int => (int) $adapter->fetchOne(
            $adapter->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('catalog/category_product_index'), 'COUNT(*)')
                ->where('product_id = ?', $productId)
                ->where('category_id = ?', $categoryId)
                ->where('store_id = ?', 1),
        );
        $rewrites = fn(): int => (int) $adapter->fetchOne(
            $adapter->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), 'COUNT(*)')
                ->where('product_id = ?', $productId)
                ->where('category_id = ?', $categoryId)
                ->where('store_id = ?', 1),
        );
        expect($indexRows())->toBe(0)->and($rewrites())->toBe(0);

        Mage::getService('catalog/product')->setCategoryIds($productId, [$categoryId]);
        expect($indexRows())->toBe(1)->and($rewrites())->toBeGreaterThan(0);

        Mage::getService('catalog/product')->setCategoryIds($productId, []);
        expect($indexRows())->toBe(0);
    });
});

describe('a store value removal without a product save', function (): void {
    it('gives the store view the URL rewrite of the default URL key', function (): void {
        $product = writeWithoutSaveProduct();
        $productId = (int) $product->getId();
        $defaultKey = (string) $product->getUrlKey();
        $storeKey = 'pest-wws-store-' . uniqid();
        Mage::getService('catalog/product')->updateAttributes([$productId], ['url_key' => $storeKey], 1);
        expect(writeWithoutSaveProductPath($productId))->toStartWith($storeKey);

        Mage::getService('catalog/product')->deleteStoreValues($productId, ['url_key'], 1);

        expect(writeWithoutSaveProductPath($productId))->toStartWith($defaultKey);
    });
});

describe('a website change without a product save', function (): void {
    it('gives the product the URL rewrites of the new website', function (): void {
        $productId = (int) writeWithoutSaveProduct([])->getId();
        expect(writeWithoutSaveProductPath($productId))->toBeNull();

        Mage::getService('catalog/product')->changeWebsites([$productId], [1], []);

        expect(writeWithoutSaveProductPath($productId))->not->toBeNull();
    });
});
