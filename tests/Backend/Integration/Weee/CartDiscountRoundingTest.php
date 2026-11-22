<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('apportions cart discounts on fixed product taxes in each currency on every collection', function (
    string $currency,
    float $percent,
    float $price,
    float $feeAmount,
    bool $includeInSubtotal,
    array $expectedFeeDiscounts,
    float $expectedBaseTotal,
    float $expectedTotal,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.8]]);
        $store = setStoreDisplayCurrency($currency, 'USD,EUR');
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

        $attribute = 'cart_fee_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $group = Mage::getModel('customer/group')
            ->setCode('Cart FPT test ' . uniqid())
            ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
            ->save();
        $groupId = (int) $group->getId();
        Mage::getModel('salesrule/rule')
            ->setName('Cart FPT test ' . uniqid())
            ->setIsActive(true)
            ->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount($percent)
            ->setStopRulesProcessing(false)
            ->save();

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
        $address = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);
        for ($line = 0; $line < count($expectedFeeDiscounts); $line++) {
            $fixture = WeeeDiscountFixture::createProduct(
                'cart-fee-' . uniqid(),
                $attribute,
                [$websiteId],
                $price,
                feeAmount: $feeAmount,
            );
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($fixture['product']->getId());
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        }

        foreach (['initial', 'repeat', 'reload'] as $collection) {
            if ($collection === 'reload') {
                $quote = $cart->getById((int) $quote->getId());
            }
            $cart->collectAndSave($quote);
            $feeDiscounts = array_map(
                static fn(Mage_Sales_Model_Quote_Item $item): array => [$item->getBaseWeeeDiscount(), $item->getWeeeDiscount()],
                $quote->getAllVisibleItems(),
            );
            sort($feeDiscounts);
            expect($quote->getBaseCurrencyCode())->toBe('USD')
                ->and($quote->getQuoteCurrencyCode())->toBe($currency)
                ->and($feeDiscounts)->toBe($expectedFeeDiscounts)
                ->and($quote->getBaseGrandTotal())->toEqualWithDelta($expectedBaseTotal, 0.0001)
                ->and($quote->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.0001);
        }
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'three fees in USD' => ['USD', 10.0, 10.0, 0.12, false, [[0.01, 0.01], [0.01, 0.01], [0.02, 0.02]], 27.32, 27.32],
    'different base and display fee discounts' => ['EUR', 10.0, 10.0, 0.16, false, [[0.02, 0.01]], 9.14, 7.32],
    'three fees in different currencies' => ['EUR', 10.0, 10.0, 0.12, false, [[0.01, 0.01], [0.01, 0.01], [0.02, 0.01]], 27.32, 21.86],
    'fractional percentage with FPT excluded from the subtotal' => ['USD', 10.5, 0.13, 0.13, false, [[0.02, 0.02]], 0.23, 0.23],
    'fractional percentage with FPT included in the subtotal' => ['USD', 10.5, 0.13, 0.13, true, [[0.02, 0.02]], 0.23, 0.23],
]);
