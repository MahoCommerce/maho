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
    bool $catalogPriceIncludesTax,
    bool $includeFee,
    bool $taxAfterDiscount,
    bool $discountIncludesTax,
    bool $taxFee,
    bool $removeCoupon,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();

    try {
        $netProductPrice = 100.0;
        $fee = 10.0;
        $vatRate = 0.20;
        $couponRate = 0.10;
        $productVat = $netProductPrice * $vatRate;
        $feeVat = $taxFee ? $fee * $vatRate : 0.0;
        $catalogPrice = $netProductPrice + ($catalogPriceIncludesTax ? $productVat : 0.0);

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
            Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => (string) (int) $catalogPriceIncludesTax,
            Mage_Tax_Model_Config::XML_PATH_ALGORITHM => Mage_Tax_Model_Calculation::CALC_TOTAL_BASE,
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
            ->setCode($name)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(100 * $vatRate));
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode($name)
            ->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerClassId])
            ->setTaxProductClass([(int) $productClass->getId()])->setTaxRate([(int) $rate->getId()]));

        $attribute = 'cart_fee_' . $suffix;
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $product = WeeeDiscountFixture::createProduct('vat-fee-' . $suffix, $attribute, [$websiteId], $catalogPrice, feeAmount: $fee)['product'];
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
            ->setDiscountAmount(100 * $couponRate)
            ->save();

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
        $taxAddress = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($taxAddress);
        $quote->getShippingAddress()->addData($taxAddress);
        $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        $cart->collectAndSave($quote);

        $originalTotal = $netProductPrice + $fee + $productVat + $feeVat;
        $originalTax = $productVat + $feeVat + ($includeFee ? 0.0 : $fee);
        $amounts = static function (Mage_Sales_Model_Quote $quote): array {
            $item = $quote->getAllVisibleItems()[0];
            $address = $quote->getShippingAddress();
            return [
                'total' => round((float) $quote->getGrandTotal(), 4),
                'base_total' => round((float) $quote->getBaseGrandTotal(), 4),
                'tax' => round((float) $address->getTaxAmount(), 4),
                'base_tax' => round((float) $address->getBaseTaxAmount(), 4),
                'hidden_tax' => round((float) $address->getTotalAmount('hidden_tax'), 4),
                'base_hidden_tax' => round((float) $address->getBaseTotalAmount('hidden_tax'), 4),
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

        $productDiscount = round($couponRate * ($netProductPrice + ($discountIncludesTax ? $productVat : 0.0)), 2);
        $feeDiscount = round($couponRate * ($fee + ($discountIncludesTax ? $feeVat : 0.0)), 2);
        $discountedVat = $productVat + $feeVat;
        if ($taxAfterDiscount) {
            // Maho extracts VAT from the merchandise discount using the catalogue price basis.
            $merchandiseVatRate = $catalogPriceIncludesTax ? $vatRate / (1 + $vatRate) : $vatRate;
            $discountedVat = round($productVat - $productDiscount * $merchandiseVatRate, 2);
            $discountedVat += $taxFee ? round(($fee - $feeDiscount) * $vatRate, 2) : 0.0;
        }
        // For catalogue prices including tax, Maho keeps the VAT difference in a separate total.
        $hiddenTax = $catalogPriceIncludesTax ? round($productVat + $feeVat - $discountedVat, 2) : 0.0;
        $netFee = round($fee - $feeDiscount, 2);
        $tax = round($discountedVat + ($includeFee ? 0.0 : $netFee), 2);
        $total = round($netProductPrice + $netFee + $discountedVat + $hiddenTax - $productDiscount, 2);
        $itemDiscount = round($productDiscount + ($includeFee ? $feeDiscount : 0.0), 2);
        $addressFeeDiscount = $includeFee ? 0.0 : $feeDiscount;
        expect($discounted)->toBe([
            'total' => $total,
            'base_total' => $total,
            'tax' => $tax,
            'base_tax' => $tax,
            'hidden_tax' => $hiddenTax,
            'base_hidden_tax' => $hiddenTax,
            'item_discount' => $itemDiscount,
            'base_item_discount' => $itemDiscount,
            'item_fee_discount' => $feeDiscount,
            'base_item_fee_discount' => $feeDiscount,
            'address_fee_discount' => $addressFeeDiscount,
            'base_address_fee_discount' => $addressFeeDiscount,
        ]);

        $taxTotal = $quote->getShippingAddress()->getTotals()['tax'];
        $taxBlock = new Mage_Tax_Block_Checkout_Tax()->setTotal($taxTotal);
        $displayedFees = array_map(static fn(int|float $amount): float => (float) $amount, $taxBlock->getAllWeee());
        expect($displayedFees)->toBe($includeFee ? [] : ['Test fixed tax' => $netFee]);

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
})->with(['catalogue prices excluding tax' => false, 'catalogue prices including tax' => true])
    ->with(['fee separate' => false, 'fee in subtotal' => true])
    ->with(['tax before discount' => false, 'tax after discount' => true])
    ->with(['discount on net' => false, 'discount on gross' => true])
    ->with(['fee not taxable' => false, 'fee taxable' => true])
    ->with(['coupon removed' => true, 'item no longer discounted' => false]);
