<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Find a configurable product with two saleable children that have different values of the first configurable attribute.
 *
 * @return array{product_id: int, super_attribute_id: int, attribute_id: int, buy_requests: list<array<int, int>>}|null
 */
function configurableWithTwoChildren(): ?array
{
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId(1)
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize(20);

    foreach ($collection as $candidate) {
        $product = Mage::getModel('catalog/product')->setStoreId(1)->load($candidate->getId());
        $type = $product->getTypeInstance(true);
        $attributes = $type->getConfigurableAttributesAsArray($product);
        if ($attributes === []) {
            continue;
        }

        $superAttributes = [];
        foreach ($type->getUsedProducts(null, $product) as $child) {
            if (!$child->isSaleable()) {
                continue;
            }
            $superAttribute = [];
            foreach ($attributes as $attribute) {
                $superAttribute[(int) $attribute['attribute_id']] = (int) $child->getData($attribute['attribute_code']);
            }
            $superAttributes[$superAttribute[(int) $attributes[0]['attribute_id']]] ??= $superAttribute;
            if (count($superAttributes) === 2) {
                return [
                    'product_id' => (int) $product->getId(),
                    'super_attribute_id' => (int) $attributes[0]['id'],
                    'attribute_id' => (int) $attributes[0]['attribute_id'],
                    'buy_requests' => array_values($superAttributes),
                ];
            }
        }
    }

    return null;
}

test('each cart line of one configurable product keeps its own option price after the quote is loaded again', function (): void {
    $setup = configurableWithTwoChildren();
    if ($setup === null) {
        $this->markTestSkipped('No configurable product with two saleable children');
    }

    [$withSurcharge, $withoutSurcharge] = $setup['buy_requests'];
    $surcharge = 85.0;
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $pricingTable = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute_pricing');
    $pricingWhere = ['product_super_attribute_id = ?' => $setup['super_attribute_id']];
    $originalPricing = $adapter->fetchAll(
        $adapter->select()->from($pricingTable)->where('product_super_attribute_id = ?', $setup['super_attribute_id']),
    );
    $adapter->delete($pricingTable, $pricingWhere);
    $adapter->insert($pricingTable, [
        'product_super_attribute_id' => $setup['super_attribute_id'],
        'value_index' => $withSurcharge[$setup['attribute_id']],
        'is_percent' => 0,
        'pricing_value' => $surcharge,
        'website_id' => 0,
    ]);

    try {
        $quote = Mage::getModel('sales/quote')->setStoreId(1);
        foreach ([$withSurcharge, $withoutSurcharge] as $superAttribute) {
            $product = Mage::getModel('catalog/product')->setStoreId(1)->load($setup['product_id']);
            $quote->addProduct($product, new \Maho\DataObject(['qty' => 1, 'super_attribute' => $superAttribute]));
        }
        $quote->getShippingAddress()->setCountryId('US');
        $quote->collectTotals()->save();

        $loadedQuote = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
        Mage::getSingleton('checkout/session')->replaceQuote($loadedQuote);
        $loadedQuote->setTotalsCollectedFlag(false)->collectTotals();

        $prices = array_map(
            fn(Mage_Sales_Model_Quote_Item $item): float => (float) $item->getPrice(),
            array_values($loadedQuote->getAllVisibleItems()),
        );

        expect($prices)->toHaveCount(2)
            ->and($prices[0] - $prices[1])->toBe($surcharge);
    } finally {
        $adapter->delete($pricingTable, $pricingWhere);
        foreach ($originalPricing as $row) {
            $adapter->insert($pricingTable, $row);
        }
        if (isset($quote) && $quote->getId()) {
            $quote->delete();
        }
    }
});

it('keeps a configurable surcharge at zero when a later catalog rule applies', function (bool $listingFirst, float $surcharge): void {
    $setup = configurableWithTwoChildren();
    if ($setup === null) {
        $this->markTestSkipped('No configurable product with two saleable children');
    }

    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    $connection->beginTransaction();
    $app = Mage::app();
    $previousStore = $app->getStore();
    try {
        $app->setCurrentStore(1);
        $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
        $websiteId = (int) $app->getStore()->getWebsiteId();
        $product = Mage::getModel('catalog/product')->setStoreId(1)->load($setup['product_id']);
        $selection = $setup['buy_requests'][0];
        $pricing = $resource->getTableName('catalog/product_super_attribute_pricing');
        $connection->delete($pricing, ['product_super_attribute_id = ?' => $setup['super_attribute_id']]);
        $connection->insert($pricing, [
            'product_super_attribute_id' => $setup['super_attribute_id'],
            'value_index' => $selection[$setup['attribute_id']], 'is_percent' => 0, 'pricing_value' => $surcharge, 'website_id' => 0,
        ]);
        foreach ([100, 50] as $priority => $percentage) {
            $rule = Mage::getModel('catalogrule/rule')->setName('Configurable option discount')
                ->setIsActive(true)->setWebsiteIds([$websiteId])->setCustomerGroupIds([0])
                ->setSortOrder($priority)->setSimpleAction('by_fixed')->setDiscountAmount(1)
                ->setSubIsEnable(true)->setSubSimpleAction('by_percent')->setSubDiscountAmount($percentage)->save();
            $rule->getResource()->applyToProduct($rule, $product, [$websiteId]);
        }

        $swatchPrice = static function () use ($setup, $surcharge, $listingFirst): float {
            $listing = Mage::getModel('catalog/product')->setStoreId(1)->load($setup['product_id']);
            if (!$listingFirst) {
                $listing->setParentId(42);
            }
            $listing->setListSwatchAttrId($setup['attribute_id']);
            Mage::helper('configurableswatches/list_price')->attachConfigurableProductChildrenPricesMapping([$listing], 1);
            expect($listing->hasData('parent_id'))->toBe(!$listingFirst)
                ->and($listing->getParentId())->toBe($listingFirst ? null : 42)
                ->and(Mage::getModel('catalogrule/rule')->calcProductPriceRule($listing, $surcharge))->toBe($listingFirst ? $surcharge - 2.0 : 0.0);
            return (float) array_column($listing->getSwatchPrices(), 'price', 'oldPrice')[(int) $surcharge];
        };
        $listed = $listingFirst ? $swatchPrice() : null;
        $quote = Mage::getModel('sales/quote')->setStoreId(1);
        foreach ($setup['buy_requests'] as $request) {
            $parent = Mage::getModel('catalog/product')->setStoreId(1)->load($setup['product_id']);
            $quote->addProduct($parent, new Maho\DataObject(['qty' => 1, 'super_attribute' => $request]));
        }
        $quote->getShippingAddress()->setCountryId('US');
        $quote->collectTotals()->save();
        $savedQuote = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
        $items = array_values($savedQuote->getAllVisibleItems());
        $listed ??= $swatchPrice();
        expect($items)->toHaveCount(2)
            ->and([$listed, (float) $items[0]->getPrice() - (float) $items[1]->getPrice()])->toBe([0.0, 0.0]);
    } finally {
        $app->setCurrentStore($previousStore);
        $connection->rollBack();
    }
})->with(['catalog listing first' => [true, 20.0], 'cart first' => [false, 24.0]]);
