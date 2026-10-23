<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// MySQL compares these columns without regard to case, so every database must do the same

describe('getCaseInsensitiveEqualsSql()', function (): void {
    test('ignores letter case and keeps other differences', function (string $left, string $right, int $expected): void {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $condition = $adapter->getCaseInsensitiveEqualsSql($adapter->quote($left), $adapter->quote($right));

        expect((int) $adapter->fetchOne("SELECT CASE WHEN {$condition} THEN 1 ELSE 0 END"))->toBe($expected);
    })->with([
        'same case' => ['SAVE10', 'SAVE10', 1],
        'other case' => ['Save10', 'sAVE10', 1],
        'other value' => ['SAVE10', 'SAVE11', 0],
    ]);
});

describe('Customer email lookups ignore letter case', function (): void {
    beforeEach(function (): void {
        $this->email = 'case.' . uniqid() . '@example.com';
        $this->customer = Mage::getModel('customer/customer')
            ->setWebsiteId(1)
            ->setEmail($this->email)
            ->setFirstname('Case')
            ->setLastname('Lookup')
            ->save();
    });

    test('loadByEmail() finds the customer from an email in other case', function (): void {
        $found = Mage::getModel('customer/customer')->setWebsiteId(1)->loadByEmail(strtoupper($this->email));

        expect((int) $found->getId())->toBe((int) $this->customer->getId());
    });

    test('a second customer with the same email in other case is rejected', function (): void {
        $duplicate = Mage::getModel('customer/customer')
            ->setWebsiteId(1)
            ->setEmail(strtoupper($this->email))
            ->setFirstname('Case')
            ->setLastname('Duplicate');

        expect(fn() => $duplicate->save())->toThrow(Mage_Customer_Exception::class);
    });

    test('the newsletter subscriber is found from an email in other case', function (): void {
        Mage::getModel('newsletter/subscriber')
            ->setStoreId(1)
            ->setSubscriberEmail($this->email)
            ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->save();

        $found = Mage::getModel('newsletter/subscriber')->loadByEmail(strtoupper($this->email));

        expect($found->getSubscriberEmail())->toBe($this->email);
    });
});

describe('Coupon code lookups ignore letter case', function (): void {
    beforeEach(function (): void {
        $this->code = 'CASE-' . strtoupper(uniqid());
        $this->rule = Mage::getModel('salesrule/rule')
            ->setName('Case lookup ' . $this->code)
            ->setIsActive()
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([0, 1])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($this->code)
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(10)
            ->save();
    });

    test('loadByCode() finds the coupon from a code in other case', function (): void {
        $coupon = Mage::getModel('salesrule/coupon')->loadByCode(strtolower($this->code));

        expect((int) $coupon->getRuleId())->toBe((int) $this->rule->getId());
    });

    test('the rule validation filter finds the rule from a code in other case', function (): void {
        $ruleIds = Mage::getResourceModel('salesrule/rule_collection')
            ->setValidationFilter(1, 0, strtolower($this->code))
            ->getColumnValues('rule_id');

        expect(array_map(intval(...), $ruleIds))->toContain((int) $this->rule->getId());
    });
});

describe('Gift card code lookups ignore letter case', function (): void {
    beforeEach(function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('price', ['gt' => 10])
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1)
            ->getFirstItem();
        if (!$product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        $this->giftcard = Mage::getModel('giftcard/giftcard')
            ->setCode(Mage::helper('giftcard')->generateCode())
            ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
            ->setWebsiteIds([1])
            ->setBalance(10.00)
            ->setInitialBalance(10.00)
            ->save();

        $this->quote = Mage::getModel('sales/quote')->setStoreId(1);
        $this->quote->addProduct($product, 1);
        $addressData = [
            'country_id' => 'US',
            'region_id' => 12,
            'postcode' => '90210',
            'firstname' => 'Test',
            'lastname' => 'Customer',
            'street' => '123 Test St',
            'city' => 'Beverly Hills',
            'telephone' => '555-1234',
            'email' => 'test@example.com',
        ];
        $this->quote->getBillingAddress()->addData($addressData);
        $this->quote->getShippingAddress()->addData($addressData)
            ->setCollectShippingRates()
            ->setShippingMethod('flatrate_flatrate');
        $this->quote->getPayment()->importData(['method' => 'checkmo']);
    });

    test('a card applied with a code in other case is debited at checkout', function (): void {
        Mage::getService('checkout/cart')->applyGiftcard($this->quote, strtolower($this->giftcard->getCode()));
        (new Mage_Sales_Model_Service_Quote($this->quote))->submitOrder();

        $reloaded = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(0.00);
    });

    test('a card that is already applied cannot be applied again in other case', function (): void {
        $service = Mage::getService('checkout/cart');
        $service->applyGiftcard($this->quote, $this->giftcard->getCode());

        expect(fn() => $service->applyGiftcard($this->quote, strtolower($this->giftcard->getCode())))
            ->toThrow(Mage_Core_Exception_Conflict::class);
    });
});

describe('Postcode lookups ignore letter case', function (): void {
    test('a table rate for an uppercase postcode applies to a lowercase postcode', function (): void {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('shipping/tablerate');
        $condition = 'case_' . substr(uniqid(), -10);
        $adapter->insert($table, [
            'website_id' => 1,
            'dest_country_id' => 'GB',
            'dest_region_id' => 0,
            'dest_zip' => 'SW1A*',
            'condition_name' => $condition,
            'condition_value' => 0,
            'price' => 7.5,
            'cost' => 0,
        ]);

        try {
            $request = new Mage_Shipping_Model_Rate_Request();
            $request->setWebsiteId(1)
                ->setDestCountryId('GB')
                ->setDestRegionId(0)
                ->setDestPostcode('sw1a 1aa')
                ->setConditionName($condition)
                ->setData($condition, 1);

            $rate = Mage::getResourceModel('shipping/carrier_tablerate')->getRate($request);

            expect($rate)->toBeArray();
            expect((float) $rate['price'])->toBe(7.5);
        } finally {
            $adapter->delete($table, ['condition_name = ?' => $condition]);
        }
    });

    test('a tax rate for an uppercase postcode applies to a lowercase postcode', function (): void {
        $rate = Mage::getModel('tax/calculation_rate')
            ->setCode('Case lookup ' . uniqid())
            ->setTaxCountryId('GB')
            ->setTaxRegionId(0)
            ->setTaxPostcode('SW1A*')
            ->setRate(20)
            ->save();

        try {
            $request = new Maho\DataObject([
                'country_id' => 'GB',
                'region_id' => 0,
                'postcode' => 'sw1a 1aa',
            ]);

            $rateIds = Mage::getResourceSingleton('tax/calculation')->getApplicableRateIds($request);

            expect(array_map(intval(...), $rateIds))->toContain((int) $rate->getId());
        } finally {
            $rate->delete();
        }
    });
});
