<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('charges and discounts fixed product tax once on the priced cart lines', function (
    ?int $bundlePriceType,
    bool $includeInSubtotal,
    float $childFee,
    array $baseFees,
    array $baseFeeDiscounts,
    array $feeDiscounts,
    float $baseTotal,
    float $total,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.8]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        Mage::app()->setCurrentStore($storeId);
        $store->setConfig(Mage_Weee_Helper_Data::XML_PATH_FPT_ENABLED, '1');
        $store->setConfig('tax/weee/discount', '1');
        $store->setConfig('tax/weee/apply_vat', '0');
        $store->setConfig('tax/weee/include_in_subtotal', (string) (int) $includeInSubtotal);
        $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY, 'US');
        $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX, '0');
        $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT, '1');

        $attribute = 'composite_fee_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($attribute);

        $newProduct = static function (string $type, ?float $price, float $fee) use ($attribute, $websiteId): Mage_Catalog_Model_Product {
            $product = Mage::getModel('catalog/product')->setStoreId(0)->setTypeId($type)
                ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
                ->setSku('composite-fee-' . uniqid())->setName('Composite fee test')
                ->setPrice($price)->setWeight(1)->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)->setTaxClassId(0)
                ->setWebsiteIds([$websiteId])->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
            if ($fee > 0.0) {
                $product->setData($attribute, [['website_id' => $websiteId, 'country' => 'US', 'state' => '*', 'price' => $fee]]);
            }
            return $product;
        };

        if ($bundlePriceType === null) {
            $optionCode = 'test_finish_' . uniqid();
            Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(Mage_Catalog_Model_Product::ENTITY, $optionCode, [
                'type' => 'int',
                'input' => 'select',
                'label' => 'Finish',
                'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
                'visible' => true,
                'is_configurable' => true,
                'user_defined' => true,
                'required' => false,
                'group' => 'General',
                'option' => ['values' => ['Matte']],
            ]);
            Mage::getSingleton('eav/config')->clear();
            $option = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $optionCode);
            $optionValue = (int) $option->getSource()->getAllOptions(false)[0]['value'];
            $child = $newProduct(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, 10.0, $childFee)->setData($optionCode, $optionValue)->save();
            $productIds = [(int) $child->getId()];
            $product = $newProduct(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE, 20.0, 0.12)
                ->setConfigurableAttributesData([['attribute_id' => (int) $option->getId(), 'position' => 0]])
                ->setCanSaveConfigurableAttributes(true)
                ->setConfigurableProductsData([$child->getId() => []])
                ->save();
            $buyRequest = ['qty' => 3, 'super_attribute' => [(int) $option->getId() => $optionValue]];
            $expectedQuantities = [3.0, 3.0];
        } else {
            $options = [];
            $selections = [];
            $productIds = [];
            foreach ([2, 1] as $position => $quantity) {
                $child = $newProduct(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, 10.0, $childFee)->save();
                $productIds[] = (int) $child->getId();
                $title = 'Selection ' . $position;
                $options[] = ['title' => $title, 'default_title' => $title, 'type' => 'select', 'required' => 1, 'position' => $position, 'delete' => ''];
                $selections[] = [[
                    'product_id' => $child->getId(),
                    'selection_qty' => $quantity,
                    'selection_can_change_qty' => 0,
                    'position' => 0,
                    'is_default' => 1,
                    'selection_price_type' => 0,
                    'selection_price_value' => 0,
                    'delete' => '',
                ]];
            }
            $dynamic = $bundlePriceType === Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC;
            $product = $newProduct(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE, $dynamic ? null : 20.0, $dynamic ? 0.77 : 0.12)
                ->setPriceType($bundlePriceType)->setSkuType(0)->setWeightType(0)->setShipmentType(0)->setPriceView(0)
                ->setCanSaveBundleSelections(true)->setBundleOptionsData($options)->setBundleSelectionsData($selections);
            Mage::register('product', $product);
            try {
                $product->save();
            } finally {
                Mage::unregister('product');
            }
            $buyRequest = ['qty' => 3, 'bundle_option' => []];
            $bundleType = $product->getTypeInstance(true);
            foreach ($bundleType->getSelectionsCollection($bundleType->getOptionsIds($product), $product) as $selection) {
                $buyRequest['bundle_option'][$selection->getOptionId()] = (int) $selection->getSelectionId();
            }
            $expectedQuantities = [3.0, 6.0, 3.0];
        }
        $productIds[] = (int) $product->getId();
        Mage::getResourceSingleton('cataloginventory/indexer_stock')->reindexProducts($productIds);

        $group = Mage::getModel('customer/group')->setCode('Composite fee ' . uniqid())
            ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
            ->save();
        $groupId = (int) $group->getId();
        Mage::getModel('salesrule/rule')->setName('Composite fee discount')->setIsActive(true)->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([$groupId])->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)->setDiscountAmount(10.0)
            ->setStopRulesProcessing(false)->save();

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
        $address = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
        $cart->addProduct($quote, $product, new Maho\DataObject($buyRequest), save: false);
        $unsavedChildren = $quote->getAllVisibleItems()[0]->getChildren();
        expect(array_map(static fn(Mage_Sales_Model_Quote_Item $item): ?int => $item->getParentItemId(), $unsavedChildren))
            ->toBe(array_fill(0, count($expectedQuantities) - 1, null));

        foreach (['initial', 'repeat', 'reload'] as $collection) {
            if ($collection === 'reload') {
                $quote = $cart->getById((int) $quote->getId());
            }
            $cart->collectAndSave($quote);

            $parent = $quote->getAllVisibleItems()[0];
            $children = $parent->getChildren();
            usort($children, static fn(Mage_Sales_Model_Quote_Item $a, Mage_Sales_Model_Quote_Item $b): int => $b->getQty() <=> $a->getQty());
            $items = [$parent, ...$children];
            $actualFees = [];
            $actualBaseFeeDiscounts = [];
            $actualFeeDiscounts = [];
            $taxTitles = [];
            $taxRowAmounts = [];
            foreach ($items as $item) {
                $applied = Mage::helper('core')->jsonDecode($item->getWeeeTaxApplied() ?: '[]');
                $actualFees[] = round((float) $item->getBaseWeeeTaxAppliedRowAmount(), 4);
                $actualBaseFeeDiscounts[] = (float) $item->getBaseWeeeDiscount();
                $actualFeeDiscounts[] = (float) $item->getWeeeDiscount();
                $taxTitles[] = array_column($applied, 'title');
                $taxRowAmounts[] = array_map(static fn(float $amount): float => round($amount, 4), array_column($applied, 'base_row_amount'));
            }

            expect($quote->getBaseGrandTotal())->toEqualWithDelta($baseTotal, 0.0001, $collection)
                ->and($quote->getGrandTotal())->toEqualWithDelta($total, 0.0001, $collection)
                ->and(array_map(static fn(Mage_Sales_Model_Quote_Item $item): float => (float) $item->getTotalQty(), $items))->toBe($expectedQuantities)
                ->and($actualFees)->toBe($baseFees)
                ->and($actualBaseFeeDiscounts)->toBe($baseFeeDiscounts)
                ->and($actualFeeDiscounts)->toBe($feeDiscounts)
                ->and($taxTitles)->toBe(array_map(static fn(float $fee): array => $fee > 0.0 ? ['Test fixed tax'] : [], $baseFees))
                ->and($taxRowAmounts)->toBe(array_map(static fn(float $fee): array => $fee > 0.0 ? [$fee] : [], $baseFees));
        }
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'dynamic bundle with tax outside the subtotal' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC, false, 0.04, [0.0, 0.24, 0.12], [0.0, 0.02, 0.02], [0.0, 0.02, 0.01], 81.32, 65.06],
    'dynamic bundle with tax in the subtotal' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC, true, 0.04, [0.0, 0.24, 0.12], [0.0, 0.02, 0.02], [0.0, 0.02, 0.01], 81.32, 65.06],
    'dynamic bundle with a whole dollar tax discount' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC, false, 10.0, [0.0, 60.0, 30.0], [0.0, 6.0, 3.0], [0.0, 4.8, 2.4], 162.0, 129.6],
    'fixed bundle with parent and child taxes outside the subtotal' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED, false, 0.04, [0.36, 0.0, 0.0], [0.04, 0.0, 0.0], [0.03, 0.0, 0.0], 54.32, 43.46],
    'fixed bundle with parent and child taxes in the subtotal' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED, true, 0.04, [0.36, 0.0, 0.0], [0.04, 0.0, 0.0], [0.03, 0.0, 0.0], 54.32, 43.46],
    'fixed bundle with parent tax only' => [Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED, false, 0.0, [0.36, 0.0, 0.0], [0.04, 0.0, 0.0], [0.03, 0.0, 0.0], 54.32, 43.46],
    'configurable with parent and child taxes' => [null, false, 0.04, [0.36, 0.0], [0.04, 0.0], [0.03, 0.0], 54.32, 43.46],
]);
