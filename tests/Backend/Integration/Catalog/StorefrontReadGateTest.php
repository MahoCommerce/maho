<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Mage\Catalog\Api\LayeredFilterProvider;
use Mage\Catalog\Api\ProductLinkProvider;
use Mage\Catalog\Api\ProductProvider;
use Mage\Core\Api\UrlResolverProvider;
use Mage\Review\Api\Review;
use Mage\Review\Api\ReviewProvider;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpFoundation\Request;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * A guest of the API reads what the storefront shows, through the same gates of the catalog services (#1348):
 * a product is enabled, visible and in the website, a category is active and in the tree of the store, and a linked
 * product is enabled, visible in the catalog and in the website. A backend token keeps its wider view.
 */

const READ_GATE_PREFIX = 'pest-read-gate-';

function readGateCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', ['like' => READ_GATE_PREFIX . '%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => READ_GATE_PREFIX . '%']) as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
    Mage::unregister('isSecureArea');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), ['request_path LIKE ?' => READ_GATE_PREFIX . '%']);
    Mage::app()->getCache()->clean(['API_PRODUCTS', 'API_REVIEWS']);
}

/**
 * @param array<string, mixed> $data
 */
function readGateProduct(string $name, array $data = []): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct();
    $product->setSku(READ_GATE_PREFIX . $name)
        ->setName('Pest Read Gate ' . $name)
        ->setPrice(10)
        ->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([1])
        ->setUrlKey(READ_GATE_PREFIX . $name . '-' . uniqid())
        ->setStockData(['qty' => 5, 'is_in_stock' => 1])
        ->addData($data);
    return $service->save($product);
}

function readGateCategory(string $name, bool $active): Mage_Catalog_Model_Category
{
    /** @var Mage_Catalog_Service_Category $service */
    $service = Mage::getService('catalog/category');
    $category = $service->newCategory((int) Mage::app()->getStore(1)->getRootCategoryId());
    $category->setName('Pest Read Gate ' . $name)
        ->setUrlKey(READ_GATE_PREFIX . $name . '-' . uniqid())
        ->setIsActive($active ? 1 : 0)
        ->setIsAnchor(1);
    return $service->save($category);
}

function readGateRewrite(string $targetPath): string
{
    $path = READ_GATE_PREFIX . uniqid();
    Mage::getModel('core/url_rewrite')
        ->setStoreId(1)
        ->setIdPath($path)
        ->setRequestPath($path)
        ->setTargetPath($targetPath)
        ->setIsSystem(false)
        ->save();
    return $path;
}

/**
 * @return list<int> the IDs of the products that the product list gives for $filters
 */
function readGateProductIds(array $filters, bool $backend = false): array
{
    $provider = new ProductProvider($backend ? AdminApiParity::adminSecurity() : null);
    $result = $provider->provide(new GetCollection(), [], ['filters' => $filters]);
    return array_map(fn($dto): int => (int) $dto->id, iterator_to_array($result));
}

beforeEach(function (): void {
    readGateCleanup();
    StoreContext::setStore(1);
});
afterEach(function (): void {
    readGateCleanup();
    new StoreContext()->reset();
});

describe('a product by URL key', function (): void {
    it('reaches a guest only when the storefront shows the product', function (): void {
        $hidden = readGateProduct('hidden', ['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE]);
        $visible = readGateProduct('visible');

        expect(readGateProductIds(['urlKey' => $hidden->getUrlKey()]))->toBe([])
            ->and(readGateProductIds(['urlKey' => $visible->getUrlKey()]))->toBe([(int) $visible->getId()])
            ->and(readGateProductIds(['urlKey' => $hidden->getUrlKey()], backend: true))->toBe([(int) $hidden->getId()]);
    });
});

describe('the products of a category', function (): void {
    it('reach a guest only when the storefront shows the category', function (): void {
        $active = readGateCategory('active', true);
        $inactive = readGateCategory('inactive', false);
        $product = readGateProduct('in-categories', ['category_ids' => [(int) $active->getId(), (int) $inactive->getId()]]);

        expect(readGateProductIds(['categoryId' => (int) $active->getId()]))->toBe([(int) $product->getId()])
            ->and(readGateProductIds(['categoryId' => (int) $inactive->getId()]))->toBe([])
            ->and(Mage::helper('catalog/category')->canShow($inactive))->toBeFalse()
            ->and(Mage::helper('catalog/category')->canShow($active))->toBeTrue();
    });

    it('give a guest no layered filters for a category that the storefront does not show', function (): void {
        $provider = new LayeredFilterProvider();
        $category = null;
        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('level', ['gteq' => 2])
            ->addIsActiveFilter();
        foreach ($categories as $candidate) {
            if (iterator_count($provider->provide(new GetCollection(), [], ['filters' => ['categoryId' => $candidate->getId()]])) > 0) {
                $category = Mage::getModel('catalog/category')->load($candidate->getId());
                break;
            }
        }
        expect($category)->not->toBeNull();

        try {
            $category->setIsActive(0)->save();
            expect(iterator_count($provider->provide(new GetCollection(), [], ['filters' => ['categoryId' => $category->getId()]])))->toBe(0);
        } finally {
            $category->setIsActive(1)->save();
        }
    });
});

describe('the related products of a product', function (): void {
    it('list for a guest only the products that the storefront lists', function (): void {
        $disabled = readGateProduct('link-disabled', ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED]);
        $hidden = readGateProduct('link-hidden', ['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH]);
        $shown = readGateProduct('link-shown');
        $product = Mage::getService('catalog/product')->getById((int) readGateProduct('with-links')->getId());
        Mage::getService('catalog/product_link')->setLinks($product, 'related', [
            (int) $disabled->getId() => ['position' => 1],
            (int) $hidden->getId() => ['position' => 2],
            (int) $shown->getId() => ['position' => 3],
        ]);

        $links = function (bool $backend) use ($product): array {
            $provider = new ProductLinkProvider($backend ? AdminApiParity::adminSecurity() : null);
            $request = Request::create("/api/rest/v2/products/{$product->getId()}/links/related");
            $result = $provider->provide(new GetCollection(), ['productId' => (int) $product->getId()], ['request' => $request]);
            return array_map(fn($link): int => $link->linkedProductId, $result);
        };
        $embedded = new ProductProvider(null)->loadProductDto((int) $product->getId())->relatedProducts;

        expect($links(false))->toBe([(int) $shown->getId()])
            ->and(array_column($embedded, 'id'))->toBe([(int) $shown->getId()])
            ->and($links(true))->toEqualCanonicalizing([(int) $disabled->getId(), (int) $hidden->getId(), (int) $shown->getId()]);
    });
});

describe('the reviews of a product', function (): void {
    it('reach a guest only when the storefront shows the product', function (): void {
        $product = readGateProduct('reviewed', ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED]);
        $review = Mage::getModel('review/review');
        $review->setEntityId((int) $review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue((int) $product->getId())
            ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
            ->setTitle('Pest read gate')
            ->setDetail('A review of a disabled product')
            ->setNickname('Pest')
            ->setStoreId(1)
            ->setStores([1])
            ->save();

        $list = fn() => new ReviewProvider(null)->provide(
            new GetCollection(class: Review::class),
            ['productId' => (int) $product->getId()],
        );
        $one = fn() => new ReviewProvider(null)->provide(new Get(class: Review::class), ['id' => (int) $review->getId()]);

        expect($list)->toThrow(Mage_Core_Exception_NoSuchEntity::class)
            ->and($one)->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });
});

describe('the URL resolver', function (): void {
    it('finds only what the storefront shows', function (): void {
        $disabled = readGateProduct('resolve-disabled', ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED]);
        $shown = readGateProduct('resolve-shown');
        $inactive = readGateCategory('resolve-inactive', false);
        $resolve = fn(string $path): string => new UrlResolverProvider()->provide(new Get(name: 'url_resolver'), context: ['filters' => ['path' => $path]])->type;

        expect($resolve(readGateRewrite("catalog/product/view/id/{$disabled->getId()}")))->toBe('not_found')
            ->and($resolve(readGateRewrite("catalog/category/view/id/{$inactive->getId()}")))->toBe('not_found')
            ->and($resolve(readGateRewrite("catalog/product/view/id/{$shown->getId()}")))->toBe('product');
    });
});
