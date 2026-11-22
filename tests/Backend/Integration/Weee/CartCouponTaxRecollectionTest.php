<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('recalculates tax and fixed product tax discounts when the cart loses its discount', function (
    bool $includeFee,
    bool $taxAfterDiscount,
    bool $discountIncludesTax,
    bool $taxFee,
    bool $removeCoupon,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();

    try {
        $store = setStoreDisplayCurrency('USD', 'USD');
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        Mage::app()->setCurrentStore($storeId);
        foreach ([
            Mage_Weee_Helper_Data::XML_PATH_FPT_ENABLED => '1',
            'tax/weee/discount' => '1',
            'tax/weee/apply_vat' => (string) (int) $taxFee,
            'tax/weee/include_in_subtotal' => (string) (int) $includeFee,
            Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT => (string) (int) $taxAfterDiscount,
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DISCOUNT_TAX => (string) (int) $discountIncludesTax,
            Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => '0',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY => 'US',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $suffix = uniqid();
        $name = 'Cart fixed tax ' . $suffix;
        $productClass = Mage::getModel('tax/class')->setClassType('PRODUCT')->setClassName($name)->save();
        $customerClassId = (int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        $group = Mage::getModel('customer/group')->setCode($name)->setTaxClassId($customerClassId)->save();
        $groupId = (int) $group->getId();
        $rate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
            ->setCode($name)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(20.0));
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode($name)
            ->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerClassId])
            ->setTaxProductClass([(int) $productClass->getId()])->setTaxRate([(int) $rate->getId()]));

        $attribute = 'cart_fee_' . $suffix;
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $product = WeeeDiscountFixture::createProduct('vat-fee-' . $suffix, $attribute, [$websiteId], 100.0)['product'];
        Mage::getSingleton('catalog/product_action')->updateAttributes(
            [(int) $product->getId()],
            ['tax_class_id' => (int) $productClass->getId()],
            0,
        );
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());

        $couponCode = 'VAT-' . $suffix;
        Mage::getModel('salesrule/rule')
            ->setName($name)
            ->setIsActive(true)
            ->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($couponCode)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(10.0)
            ->save();

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
        $taxAddress = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($taxAddress);
        $quote->getShippingAddress()->addData($taxAddress);
        $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        $cart->collectAndSave($quote);

        $originalTotal = $taxFee ? 132.0 : 130.0;
        $originalTax = ($includeFee ? 20.0 : 30.0) + ($taxFee ? 2.0 : 0.0);
        $amounts = static function (Mage_Sales_Model_Quote $quote): array {
            $item = $quote->getAllVisibleItems()[0];
            $address = $quote->getShippingAddress();
            return [
                'total' => round((float) $quote->getGrandTotal(), 4),
                'base_total' => round((float) $quote->getBaseGrandTotal(), 4),
                'tax' => round((float) $address->getTaxAmount(), 4),
                'base_tax' => round((float) $address->getBaseTaxAmount(), 4),
                'item_discount' => round((float) $item->getDiscountAmount(), 4),
                'base_item_discount' => round((float) $item->getBaseDiscountAmount(), 4),
                'item_fee_discount' => round((float) $item->getWeeeDiscount(), 4),
                'base_item_fee_discount' => round((float) $item->getBaseWeeeDiscount(), 4),
                'address_fee_discount' => round((float) $address->getWeeeDiscount(), 4),
                'base_address_fee_discount' => round((float) $address->getBaseWeeeDiscount(), 4),
            ];
        };
        $original = $amounts($quote);
        expect($original['total'])->toBe($originalTotal)
            ->and($original['tax'])->toBe($originalTax);

        $cart->applyCoupon($quote, $couponCode, Mage_Checkout_Model_Actor::Staff);
        $discounted = $amounts($quote);
        expect($discounted['total'])->toBeLessThan($originalTotal)
            ->and($discounted['item_fee_discount'])->toBeGreaterThan(0.0)
            ->and($discounted['address_fee_discount'])->toBe($includeFee ? 0.0 : $discounted['item_fee_discount']);
        $cart->collectAndSave($quote);
        expect($amounts($quote))->toBe($discounted);

        if ($removeCoupon) {
            $cart->removeCoupon($quote);
        } else {
            $quote->getAllVisibleItems()[0]->setNoDiscount(true);
            $cart->collectAndSave($quote);
        }

        $immediately = $amounts($quote);
        $cart->collectAndSave($quote);
        $recollected = $amounts($quote);
        $quote = $cart->getById((int) $quote->getId());
        $cart->collectAndSave($quote);

        expect($immediately)->toBe($original)
            ->and($recollected)->toBe($original)
            ->and($amounts($quote))->toBe($original)
            ->and((bool) $quote->getAllVisibleItems()[0]->getNoDiscount())->toBe(!$removeCoupon);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with(['fee separate' => false, 'fee in subtotal' => true])
    ->with(['tax before discount' => false, 'tax after discount' => true])
    ->with(['discount on net' => false, 'discount on gross' => true])
    ->with(['fee not taxable' => false, 'fee taxable' => true])
    ->with(['coupon removed' => true, 'item no longer discounted' => false]);
