<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

it('renders the configured price of an unavailable fixed bundle in both tax modes', function (): void {
    $unicodeRaw = '<span id="currency-encoding-probe">€</span>';
    $unicodeDom = new DOMDocument();
    $unicodeLoaded = $unicodeDom->loadHTML('<?xml encoding="UTF-8">' . $unicodeRaw);
    $unicodeNodes = (new DOMXPath($unicodeDom))->query("//*[@id='currency-encoding-probe']");
    expect(mb_check_encoding($unicodeRaw, 'UTF-8'))->toBeTrue()
        ->and($unicodeLoaded)->toBeTrue()->and($unicodeNodes->length)->toBe(1)
        ->and($unicodeNodes->item(0)->textContent)->toBe('€');
    $app = Mage::app();
    $previousStore = $app->getStore();
    $store = $app->getDefaultStoreView();
    $previousRequestedCurrency = $store->getData('requested_currency_code');
    $app->setCurrentStore($store);
    $resource = Mage::getResourceSingleton('catalog/product');
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    expect($resource->getWriteConnection())->toBe($write)
        ->and($write->getTransactionLevel())->toBe(0)
        ->and(Mage::registry('product'))->toBeNull()
        ->and($store->getCurrentCurrencyCode())->toBe($store->getBaseCurrencyCode());

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
        'currency/options/allow' => (string) $store->getConfig('currency/options/allow'),
    ];
    $savedSettings = [];
    foreach ($settings as $path => $value) {
        $savedSettings[$path] = $store->getConfig($path);
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

        $capture = static function (string $country, bool $inputIncludesTax, int $displayType, float $basePrice = 100.0, ?float $expectedFinal = null) use ($store, $bundleId, $guestClassId): array {
            $expectedFinal ??= $basePrice;
            $store->setConfig('shipping/origin/country_id', $country);
            $store->setConfig('tax/calculation/price_includes_tax', (string) (int) $inputIncludesTax);
            $store->setConfig('tax/display/type', (string) $displayType);
            $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($bundleId);
            expect((int) $product->getId())->toBe($bundleId)
                ->and($product->getPriceType())->toBe(Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED)
                ->and($product->getData('can_show_price'))->toBeNull()
                ->and($product->getCanShowPrice())->toBeNull()
                ->and($product->getStockItem()->getIsInStock())->toBeFalse()
                ->and($product->isSaleable())->toBeFalse()
                ->and(Mage::helper('catalog/product')->canShow($product))->toBeTrue()
                ->and($product->getCalculatedFinalPrice())->toBeNull()
                ->and((float) $product->getData('price'))->toEqualWithDelta($basePrice, 0.000001)
                ->and((float) $product->getFinalPrice())->toEqualWithDelta($expectedFinal, 0.000001);
            $type = $product->getTypeInstance(true);
            $optionIds = $type->getOptionsIds($product);
            expect(count($optionIds))->toBe(1)
                ->and(count($type->getSelectionsCollection($optionIds, $product)))->toBe(1);

            $calc = Mage::getSingleton('tax/calculation');
            $request = $calc->getRateRequest(null, null, null, $store)->setProductClassId($product->getTaxClassId());
            expect((int) $request->getCustomerClassId())->toBe($guestClassId)
                ->and((int) $request->getProductClassId())->toBe((int) $product->getTaxClassId())
                ->and($product->getData('tax_percent'))->toBeNull()
                ->and($product->getData('applied_rates'))->toBeNull();
            $oracle = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($bundleId);
            expect($oracle)->not->toBe($product)
                ->and((float) $oracle->getFinalPrice())->toEqualWithDelta($expectedFinal, 0.000001);
            $rounded = $store->roundPrice((float) $oracle->getFinalPrice());
            $tax = Mage::helper('tax');
            $net = (float) $tax->getPrice($oracle, $rounded, false, null, null, null, $store, null, false);
            $gross = (float) $tax->getPrice($oracle, $rounded, true, null, null, null, $store, null, false);
            $displayPrice = (float) $tax->getPrice($oracle, $rounded, null, null, null, null, $store, null, false);
            expect($product->getData('tax_percent'))->toBeNull()->and($product->getData('applied_rates'))->toBeNull();
            $layout = Mage::app()->getLayout();
            $parent = $layout->createBlock('catalog/product_view');
            $priceBlock = $layout->createBlock('bundle/catalog_product_price')
                ->setTemplate('bundle/catalog/product/view/price.phtml')->setProduct($product);
            $parent->setChild('bundle_prices', $priceBlock);
            $html = $parent->getChildHtml('bundle_prices', false);
            $dom = new DOMDocument();
            $old = libxml_use_internal_errors(true);
            try {
                expect(mb_check_encoding($html, 'UTF-8'))->toBeTrue();
                expect($dom->loadHTML('<?xml encoding="UTF-8">' . $html))->toBeTrue();
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($old);
            }
            $xpath = new DOMXPath($dom);
            $texts = [];
            $rawTexts = [];
            $ids = $displayType === Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH
                ? ['excluding' => "price-excluding-tax-{$bundleId}", 'including' => "price-including-tax-{$bundleId}"]
                : ['display' => "product-price-{$bundleId}"];
            foreach ($ids as $kind => $id) {
                $nodes = $xpath->query("//*[@id='{$id}']");
                expect($nodes)->not->toBeFalse()->and($nodes->length)->toBe(1);
                $texts[$kind] = trim($nodes->item(0)->textContent);
                $pattern = '~<span\b[^>]*\bid="' . preg_quote($id, '~') . '"[^>]*>(.*?)</span\s*>~su';
                expect(preg_match($pattern, $html, $rawMatch))->toBe(1);
                $rawTexts[$kind] = trim(html_entity_decode(strip_tags($rawMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                expect($texts[$kind])->toBe($rawTexts[$kind]);
            }
            expect($html)->not->toContain('bundle.reloadPrice()');
            $priceBlock->setWithoutPrice(true);
            $hiddenHtml = $priceBlock->toHtml();
            $priceBlock->unsWithoutPrice();
            expect($hiddenHtml)->not->toContain('price-excluding-tax-' . $bundleId)
                ->not->toContain('price-including-tax-' . $bundleId)
                ->not->toContain('product-price-' . $bundleId);
            $format = static fn(float $amount): string => trim(html_entity_decode(strip_tags(Mage::helper('core')->currency($amount, true, false)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $expected = $displayType === Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH
                ? ['excluding' => $format($displayPrice), 'including' => $format($gross)]
                : ['display' => $format($displayPrice)];
            return ['input' => $basePrice, 'raw_attribute' => (float) $product->getData('price'),
                'final' => (float) $product->getFinalPrice(),
                'group_price' => $product->getGroupPrice(), 'rate' => (float) $calc->getRate($request), 'net' => $net, 'gross' => $gross,
                'display_price' => $displayPrice, 'texts' => $texts, 'raw_texts' => $rawTexts,
                'html_utf8' => true, 'without_price_spans_absent' => true, 'expected' => $expected];
        };

        $both = Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH;
        $zero = $capture('FR', false, $both);
        $taxed = $capture('AT', false, $both);
        $included = $capture('AT', true, $both);
        $singleIncluding = $capture('AT', false, Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);
        $singleExcluding = $capture('AT', true, Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX);

        // Reload before each native attribute update; the creation object is never saved twice.
        $updatePrice = static function (float $price) use ($resource, $bundleId): void {
            $updated = Mage::getModel('catalog/product')->setStoreId(0)->load($bundleId);
            $updated->setPrice($price);
            $resource->saveAttribute($updated, 'price');
        };
        $updatePrice(100.024);
        $fractional = $capture('AT', false, $both, 100.024, 100.02);
        $fractionalExpected = trim(html_entity_decode(
            strip_tags($store->formatPrice(120.02, false)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));

        $baseCurrency = $store->getBaseCurrencyCode();
        $otherCurrency = $baseCurrency === 'EUR' ? 'USD' : 'EUR';
        Mage::getModel('directory/currency')->saveRates([$baseCurrency => [$otherCurrency => 3.0]]);
        $store->setConfig('currency/options/allow', $baseCurrency . ',' . $otherCurrency);
        $store->unsetData('available_currency_codes')->unsetData('disallowed_base_currency_code_index');
        $store->clearCurrencyRateMemos()->setRequestedCurrencyCode($otherCurrency);
        expect($store->getCurrentCurrencyCode())->toBe($otherCurrency)
            ->and($store->getCurrentCurrencyRate())->toBe(3.0);
        $updatePrice(100.01);
        $converted = $capture('AT', false, $both, 100.01);
        $formatConverted = static fn(float $price): string => trim(html_entity_decode(
            strip_tags($store->formatPrice($price, false)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
        expect($zero['rate'])->toBe(0.0)->and($zero['net'])->toBe(100.0)->and($zero['gross'])->toBe(100.0)
            ->and($zero['texts'])->toBe($zero['expected']);
        expect($taxed['rate'])->toBe(20.0)->and($taxed['net'])->toBe(100.0)->and($taxed['gross'])->toBe(120.0)
            ->and($taxed['texts'])->toBe($taxed['expected']);
        expect($included['rate'])->toBe(20.0)->and($included['net'])->toEqualWithDelta(83.33333333333333, 0.000001)->and($included['gross'])->toBe(100.0)
            ->and($included['texts'])->toBe($included['expected']);
        expect($singleIncluding['rate'])->toBe(20.0)->and($singleIncluding['display_price'])->toBe(120.0)
            ->and($singleIncluding['texts'])->toBe($singleIncluding['expected']);
        expect($singleExcluding['rate'])->toBe(20.0)->and($singleExcluding['display_price'])->toEqualWithDelta(83.33333333333333, 0.000001)
            ->and($singleExcluding['texts'])->toBe($singleExcluding['expected']);
        expect($fractional['input'])->toBe(100.024)->and($fractional['raw_attribute'])->toBe(100.024)
            ->and($fractional['final'])->toBe(100.02)
            ->and((float) $fractional['group_price'])->toBe(0.0)
            ->and($fractional['rate'])->toBe(20.0)->and($fractional['gross'])->toEqualWithDelta(120.024, 0.000001)
            ->and($fractional['texts'])->toBe($fractional['expected'])
            ->and($fractional['texts']['including'])->toBe($fractionalExpected);
        expect($converted['rate'])->toBe(20.0)->and($converted['gross'])->toEqualWithDelta(120.012, 0.000001)
            ->and($converted['texts'])->toBe($converted['expected'])
            ->and($converted['texts']['including'])->toBe($formatConverted(360.04));
    } finally {
        try {
            if ($transactionStarted) {
                $resource->rollBack();
            }
        } finally {
            foreach ($savedSettings as $path => $value) {
                $store->setConfig($path, $value);
            }
            $store->unsetData('available_currency_codes')->unsetData('disallowed_base_currency_code_index');
            $store->clearCurrencyRateMemos()->clearCurrentCurrency();
            if (is_string($previousRequestedCurrency)) {
                $store->setRequestedCurrencyCode($previousRequestedCurrency);
            }
            $app->setCurrentStore($previousStore);
        }
    }
});
