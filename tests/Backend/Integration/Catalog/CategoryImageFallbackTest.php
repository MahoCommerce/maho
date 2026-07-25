<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for issue #1078: a category with no image of its own borrows the image of its
 * first product, resolved at runtime so it follows the catalog instead of going stale.
 */
function cifCreateProduct(string $sku, ?string $image): int
{
    $product = Mage::getModel('catalog/product');
    // Save in default scope, otherwise the values land on the current store view only and the
    // collection's default-scope join drops the product
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
    $product->setName($sku);
    $product->setSku($sku);
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4);
    $product->setWebsiteIds([1]);
    if ($image !== null) {
        $product->setData('image', $image);
        $product->setData('small_image', $image);
        $product->setData('thumbnail', $image);
    }
    $product->save();
    return (int) $product->getId();
}

/**
 * @param array<int, int> $productPositions productId => position
 */
function cifCreateCategory(
    string $name,
    array $productPositions,
    ?string $image = null,
    string $parentPath = '1/2',
    bool $isAnchor = false,
): Mage_Catalog_Model_Category {
    $category = Mage::getModel('catalog/category');
    $category->setName($name);
    $category->setPath($parentPath);
    $category->setIsActive(1);
    $category->setIsAnchor($isAnchor ? 1 : 0);
    $category->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
    $category->setAttributeSetId($category->getDefaultAttributeSetId());
    if ($image !== null) {
        $category->setData('image', $image);
    }
    $category->setPostedProducts($productPositions);
    $category->save();

    cifReindex();

    return Mage::getModel('catalog/category')->load($category->getId());
}

/**
 * The fallback reads catalog_category_product_index, which the test store leaves stale.
 */
function cifReindex(): void
{
    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_category_product')->reindexEverything();
}

function cifSetFallback(string $value): void
{
    Mage::app()->getStore(0)->setConfig(Mage_Catalog_Model_Category::XML_PATH_IMAGE_FALLBACK, $value);
    foreach (Mage::app()->getStores() as $store) {
        $store->setConfig(Mage_Catalog_Model_Category::XML_PATH_IMAGE_FALLBACK, $value);
    }
}

beforeEach(function () {
    cifSetFallback('1');
});

it('borrows the image of the first product by position, lowest entity id breaking ties', function () {
    $first = cifCreateProduct('cif-a-' . uniqid(), '/c/i/cif-a.jpg');
    $second = cifCreateProduct('cif-b-' . uniqid(), '/c/i/cif-b.jpg');
    $third = cifCreateProduct('cif-c-' . uniqid(), '/c/i/cif-c.jpg');

    // $second and $third tie on position; $second has the lower entity id and must win,
    // even though $first was created earliest.
    $category = cifCreateCategory('CIF Ordering', [$first => 2, $second => 1, $third => 1]);

    expect($category->getFallbackImage())->toBe('/c/i/cif-b.jpg');
});

it('exposes the borrowed image through getImageUrl() and getImagePath()', function () {
    $product = cifCreateProduct('cif-url-' . uniqid(), '/c/i/cif-url.jpg');
    $category = cifCreateCategory('CIF Url', [$product => 0]);

    $mediaConfig = Mage::getSingleton('catalog/product_media_config');

    expect($category->getImageUrl())->toBe((string) $mediaConfig->getMediaUrl('/c/i/cif-url.jpg'))
        ->and($category->getImagePath())->toBe((string) $mediaConfig->getMediaPath('/c/i/cif-url.jpg'));
});

it('keeps a manually uploaded category image ahead of the product one', function () {
    $product = cifCreateProduct('cif-manual-' . uniqid(), '/c/i/cif-product.jpg');
    $category = cifCreateCategory('CIF Manual', [$product => 0], 'cif-category.jpg');

    expect($category->getImageUrl())->toBe(Mage::getBaseUrl('media') . 'catalog/category/cif-category.jpg')
        ->and($category->getImagePath())->toBe(Mage::getBaseDir('media') . '/catalog/category/cif-category.jpg');
});

it('skips products that are disabled, not visible in catalog, or have no image', function () {
    $noImage = cifCreateProduct('cif-noimg-' . uniqid(), null);

    $placeholder = cifCreateProduct('cif-placeholder-' . uniqid(), 'no_selection');

    $disabled = cifCreateProduct('cif-disabled-' . uniqid(), '/c/i/cif-disabled.jpg');
    Mage::getModel('catalog/product')->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->load($disabled)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)->save();

    $hidden = cifCreateProduct('cif-hidden-' . uniqid(), '/c/i/cif-hidden.jpg');
    Mage::getModel('catalog/product')->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->load($hidden)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)->save();

    $good = cifCreateProduct('cif-good-' . uniqid(), '/c/i/cif-good.jpg');

    $category = cifCreateCategory('CIF Skipping', [
        $noImage => 1,
        $placeholder => 2,
        $disabled => 3,
        $hidden => 4,
        $good => 5,
    ]);

    expect($category->getFallbackImage())->toBe('/c/i/cif-good.jpg');
});

it('reaches into child categories for an anchor category holding no products of its own', function () {
    $childProduct = cifCreateProduct('cif-anchor-child-' . uniqid(), '/c/i/cif-anchor-child.jpg');

    $anchor = cifCreateCategory('CIF Anchor', [], null, '1/2', true);
    cifCreateCategory('CIF Anchor Child', [$childProduct => 0], null, '1/2/' . $anchor->getId());

    // Reload: the indexer only writes the anchor's descendant rows once the child exists
    $anchor = Mage::getModel('catalog/category')->load($anchor->getId());

    expect($anchor->getFallbackImage())->toBe('/c/i/cif-anchor-child.jpg');
});

it('prefers a product assigned directly to an anchor category over one inherited from a child', function () {
    $childProduct = cifCreateProduct('cif-anchor-inh-' . uniqid(), '/c/i/cif-anchor-inherited.jpg');
    $directProduct = cifCreateProduct('cif-anchor-dir-' . uniqid(), '/c/i/cif-anchor-direct.jpg');

    // The indexer offsets inherited positions by (child position + 1) * (level + 1) * 10000,
    // so a directly assigned product sorts first whatever its own position
    $anchor = cifCreateCategory('CIF Anchor Direct', [$directProduct => 99], null, '1/2', true);
    cifCreateCategory('CIF Anchor Direct Child', [$childProduct => 0], null, '1/2/' . $anchor->getId());

    $anchor = Mage::getModel('catalog/category')->load($anchor->getId());

    expect($anchor->getFallbackImage())->toBe('/c/i/cif-anchor-direct.jpg');
});

it('ignores child category products when the category is not an anchor', function () {
    $childProduct = cifCreateProduct('cif-noanchor-' . uniqid(), '/c/i/cif-noanchor.jpg');

    $parent = cifCreateCategory('CIF No Anchor', [], null, '1/2', false);
    cifCreateCategory('CIF No Anchor Child', [$childProduct => 0], null, '1/2/' . $parent->getId());

    $parent = Mage::getModel('catalog/category')->load($parent->getId());

    expect($parent->getFallbackImage())->toBeNull();
});

it('returns nothing when the category holds no usable product', function () {
    $category = cifCreateCategory('CIF Empty', []);

    expect($category->getFallbackImage())->toBeNull()
        ->and($category->getImageUrl())->toBe('')
        ->and($category->getImagePath())->toBe('');
});

it('does nothing when the fallback is switched off', function () {
    $product = cifCreateProduct('cif-off-' . uniqid(), '/c/i/cif-off.jpg');
    $category = cifCreateCategory('CIF Off', [$product => 0]);

    cifSetFallback('0');

    expect($category->getFallbackImage())->toBeNull()
        ->and($category->getImageUrl())->toBe('');
});

it('follows the catalog instead of caching a stale image across model loads', function () {
    $first = cifCreateProduct('cif-stale-a-' . uniqid(), '/c/i/cif-stale-a.jpg');
    $category = cifCreateCategory('CIF Stale', [$first => 1]);

    expect($category->getFallbackImage())->toBe('/c/i/cif-stale-a.jpg');

    $second = cifCreateProduct('cif-stale-b-' . uniqid(), '/c/i/cif-stale-b.jpg');
    $category->setPostedProducts([$second => 0, $first => 1]);
    $category->save();
    cifReindex();

    $reloaded = Mage::getModel('catalog/category')->load($category->getId());
    expect($reloaded->getFallbackImage())->toBe('/c/i/cif-stale-b.jpg');
});
