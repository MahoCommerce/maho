<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Find a configurable product of the sample data and one child of it.
 *
 * @return array{child_id: int, super_attribute_id: int, value_index: int}|null
 */
function feedVariantFixture(): ?array
{
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->setPageSize(20);

    foreach ($collection as $candidate) {
        $product = Mage::getModel('catalog/product')->setStoreId(1)->load($candidate->getId());
        /** @var Mage_Catalog_Model_Product_Type_Configurable $type */
        $type = $product->getTypeInstance(true);
        $attributes = $type->getConfigurableAttributesAsArray($product);
        if ($attributes === []) {
            continue;
        }
        foreach ($type->getUsedProducts(null, $product) as $child) {
            // The mapper takes the first parent, so the child must have only this one
            if (count($type->getParentIdsByChild($child->getId())) !== 1) {
                continue;
            }
            return [
                'child_id' => (int) $child->getId(),
                'super_attribute_id' => (int) $attributes[0]['id'],
                'value_index' => (int) $child->getData($attributes[0]['attribute_code']),
            ];
        }
    }

    return null;
}

test('a configurable child gets the parent prices plus its option price, not its own prices', function (): void {
    $fixture = feedVariantFixture();
    if ($fixture === null) {
        $this->markTestSkipped('No configurable product with a child');
    }

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $pricingTable = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute_pricing');
    $pricingWhere = ['product_super_attribute_id = ?' => $fixture['super_attribute_id']];
    $originalPricing = $adapter->fetchAll(
        $adapter->select()->from($pricingTable)->where('product_super_attribute_id = ?', $fixture['super_attribute_id']),
    );
    $adapter->delete($pricingTable, $pricingWhere);
    $adapter->insert($pricingTable, [
        'product_super_attribute_id' => $fixture['super_attribute_id'],
        'value_index' => $fixture['value_index'],
        'is_percent' => 0,
        'pricing_value' => 5.0,
        'website_id' => 0,
    ]);

    try {
        // The feed asks for the tax mode that the store stores, so no tax changes the prices
        $feed = Mage::getModel('feedmanager/feed')
            ->setPlatform('google')
            ->setStoreId(1)
            ->setTaxMode(Mage::helper('tax')->priceIncludesTax(1) ? 'incl' : 'excl');
        $mapper = new Maho_FeedManager_Model_Mapper($feed);
        $mapper->setExtractGallery(false);

        $child = Mage::getModel('catalog/product')->setStoreId(1)->load($fixture['child_id'])
            ->setPrice(999.0)
            ->setSpecialPrice(1.0);
        $parent = $mapper->getParentProduct($child)
            ->setPrice(20.0)
            ->setSpecialPrice(18.0)
            ->setSpecialFromDate(null)
            ->setSpecialToDate(null);
        $parentFinalPrice = (float) $parent->getFinalPrice();

        $row = $mapper->extractProductData($child);

        expect($row['price'])->toBe($parentFinalPrice + 5.0)
            ->and($row['final_price'])->toBe($parentFinalPrice + 5.0)
            ->and($row['regular_price'])->toBe(25.0)
            ->and($row['special_price'])->toBe(23.0)
            ->and($row['parent_price'])->toBe($parentFinalPrice + 5.0)
            ->and($row['parent_special_price'])->toBe(23.0);
    } finally {
        $adapter->delete($pricingTable, $pricingWhere);
        foreach ($originalPricing as $pricingRow) {
            $adapter->insert($pricingTable, $pricingRow);
        }
    }
});
