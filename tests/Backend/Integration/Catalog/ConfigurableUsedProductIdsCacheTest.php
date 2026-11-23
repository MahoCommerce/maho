<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the cache of the used product IDs on the configurable product', function () {
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->setPageSize(20);

    $product = null;
    foreach ($collection as $candidate) {
        $loaded = Mage::getModel('catalog/product')->load($candidate->getId());
        if ($loaded->getTypeInstance(true)->getUsedProducts(null, $loaded) !== []) {
            $product = $loaded;
            break;
        }
    }
    if ($product === null) {
        $this->markTestSkipped('The sample data has no configurable product with children.');
    }

    $type = $product->getTypeInstance(true);
    $ids = $type->getUsedProductIds($product);

    expect($ids)->not->toBe([])
        ->and($product->getData('_cache_instance_product_ids'))->toBe($ids);
});
