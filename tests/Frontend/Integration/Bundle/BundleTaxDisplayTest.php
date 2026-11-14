<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

it('shows the tax amounts in the configured price of an unavailable fixed bundle', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store);
    $resource = Mage::getResourceSingleton('catalog/product');

    $settings = [
        'tax/calculation/price_includes_tax' => '0',
        'tax/calculation/based_on' => 'origin',
        'tax/calculation/cross_border_trade_enabled' => '0',
        'tax/display/type' => (string) Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH,
        'shipping/origin/country_id' => 'AT',
        'shipping/origin/region_id' => '0',
        'shipping/origin/postcode' => '1010',
        'sales/msrp/enabled' => '0',
        'tax/weee/enable' => '0',
    ];
    foreach ($settings as $path => $value) {
        $store->setConfig($path, $value);
    }

    $transactionStarted = false;
    try {
        $resource->beginTransaction();
        $transactionStarted = true;
        $guestClassId = (int) Mage::getModel('customer/group')
            ->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        expect(Mage::getSingleton('tax/calculation')->getCustomer())->toBeFalse()
            ->and($guestClassId)->toBeGreaterThan(0);
        $suffix = bin2hex(random_bytes(6));
        $taxClass = Mage::getModel('tax/class')
            ->setClassName('Bundle price test ' . $suffix)
            ->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
            ->save();
        $rate = Mage::getModel('tax/calculation_rate')
            ->setCode('Bundle price test ' . $suffix)
            ->setTaxCountryId('AT')->setTaxRegionId(0)->setTaxPostcode('*')->setRate(20)->save();
        Mage::getModel('tax/calculation_rule')
            ->setCode('Bundle price test ' . $suffix)->setPriority(0)->setPosition(0)
            ->setTaxRate([$rate->getId()])
            ->setTaxCustomerClass([$guestClassId])
            ->setTaxProductClass([$taxClass->getId()])->save();

        $makeProduct = static function (string $type, array $data) use ($store, $suffix): Mage_Catalog_Model_Product {
            $product = Mage::getModel('catalog/product')->setStoreId(0)->setTypeId($type)
                ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
                ->setSku('bundle-tax-' . $type . '-' . $suffix)->setName('Bundle tax test ' . $type)
                ->setWeight(1)->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setWebsiteIds([(int) $store->getWebsiteId()])->addData($data);
            Mage::register('product', $product);
            try {
                $product->save();
            } finally {
                Mage::unregister('product');
            }
            return $product;
        };
        $selection = $makeProduct(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 1, 'tax_class_id' => 0,
            'stock_data' => ['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 10],
        ]);
        $bundle = $makeProduct(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE, [
            'price' => 100, 'price_type' => Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED,
            'tax_class_id' => (int) $taxClass->getId(), 'sku_type' => 0, 'weight_type' => 0,
            'shipment_type' => 0, 'price_view' => 0, 'can_save_bundle_selections' => true,
            'stock_data' => ['use_config_manage_stock' => 0, 'manage_stock' => 1, 'is_in_stock' => 0, 'qty' => 0],
            'bundle_options_data' => [[
                'title' => 'Selection', 'default_title' => 'Selection', 'type' => 'select',
                'required' => 1, 'position' => 0, 'delete' => '',
            ]],
            'bundle_selections_data' => [[[
                'product_id' => (int) $selection->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 0,
                'position' => 0, 'is_default' => 1, 'selection_price_type' => 0, 'selection_price_value' => 0, 'delete' => '',
            ]]],
        ]);
        $bundleId = (int) $bundle->getId();
        expect($bundleId)->toBeGreaterThan(0);

        $format = static fn(float $amount): string => trim(html_entity_decode(
            strip_tags($store->formatPrice($amount, false)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
        $capture = static function (string $country, bool $inputIncludesTax, int $displayType) use ($store, $bundleId): array {
            $store->setConfig('shipping/origin/country_id', $country);
            $store->setConfig('tax/calculation/price_includes_tax', (string) (int) $inputIncludesTax);
            $store->setConfig('tax/display/type', (string) $displayType);
            $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($bundleId);
            expect($product->getPriceType())->toBe(Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED)
                ->and($product->isSaleable())->toBeFalse();

            $layout = Mage::app()->getLayout();
            $parent = $layout->createBlock('catalog/product_view');
            $priceBlock = $layout->createBlock('bundle/catalog_product_price')
                ->setTemplate('bundle/catalog/product/view/price.phtml')->setProduct($product);
            $parent->setChild('bundle_prices', $priceBlock);
            $html = $parent->getChildHtml('bundle_prices', false);
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($dom);
            $ids = $displayType === Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH
                ? ['excluding' => "price-excluding-tax-{$bundleId}", 'including' => "price-including-tax-{$bundleId}"]
                : ['display' => "product-price-{$bundleId}"];
            $texts = [];
            foreach ($ids as $kind => $id) {
                $nodes = $xpath->query("//*[@id='{$id}']");
                expect($nodes->length)->toBe(1);
                $texts[$kind] = trim($nodes->item(0)->textContent);
            }
            return $texts;
        };

        $both = Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
        expect($capture('FR', false, $both))->toBe(['excluding' => $format(100.0), 'including' => $format(100.0)])
            ->and($capture('AT', false, $both))->toBe(['excluding' => $format(100.0), 'including' => $format(120.0)])
            ->and($capture('AT', true, $both))->toBe(['excluding' => $format(83.33), 'including' => $format(100.0)])
            ->and($capture('AT', false, Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX))->toBe(['display' => $format(120.0)])
            ->and($capture('AT', true, Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX))->toBe(['display' => $format(83.33)]);

        // Reload before each native attribute update; the creation object is never saved twice.
        $updatePrice = static function (float $price) use ($resource, $bundleId): void {
            $updated = Mage::getModel('catalog/product')->setStoreId(0)->load($bundleId);
            $updated->setPrice($price);
            $resource->saveAttribute($updated, 'price');
        };
        $updatePrice(100.024);
        expect($capture('AT', false, $both)['including'])->toBe($format(120.02));

        $baseCurrency = $store->getBaseCurrencyCode();
        $otherCurrency = $baseCurrency === 'EUR' ? 'USD' : 'EUR';
        Mage::getModel('directory/currency')->saveRates([$baseCurrency => [$otherCurrency => 3.0]]);
        $store->setConfig('currency/options/allow', $baseCurrency . ',' . $otherCurrency);
        $store->unsetData('available_currency_codes')->unsetData('disallowed_base_currency_code_index');
        $store->clearCurrencyRateMemos()->setRequestedCurrencyCode($otherCurrency);
        $updatePrice(100.01);
        expect($capture('AT', false, $both)['including'])->toBe($format(360.04));
    } finally {
        if ($transactionStarted) {
            $resource->rollBack();
        }
    }
});
