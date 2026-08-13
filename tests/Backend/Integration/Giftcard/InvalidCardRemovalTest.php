<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Invalid gift card removal on totals collection', function (): void {

    beforeEach(function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }
        $this->product = $product;

        $this->makeQuote = function (): Mage_Sales_Model_Quote {
            $quote = Mage::getModel('sales/quote');
            $quote->setStoreId(1);
            $quote->addProduct($this->product, 1);

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
            $quote->getBillingAddress()->addData($addressData);
            $quote->getShippingAddress()->addData($addressData)
                ->setCollectShippingRates(true)
                ->setShippingMethod('flatrate_flatrate');

            return $quote;
        };

        $this->makeCard = function (string $status, ?string $expiresAt = null): Maho_Giftcard_Model_Giftcard {
            $giftcard = Mage::getModel('giftcard/giftcard');
            $giftcard->setCode(Mage::helper('giftcard')->generateCode());
            $giftcard->setStatus($status);
            $giftcard->setWebsiteIds([1]);
            $giftcard->setBalance(50.00);
            $giftcard->setInitialBalance(50.00);
            if ($expiresAt !== null) {
                $giftcard->setExpiresAt($expiresAt);
            }
            $giftcard->save();

            return $giftcard;
        };
    });

    test('collect drops invalid cards and keeps valid ones', function (): void {
        $valid = ($this->makeCard)(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $disabled = ($this->makeCard)(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);

        $quote = ($this->makeQuote)();
        $quote->setGiftcardCodes(json_encode([
            $disabled->getCode() => 50.00,
            $valid->getCode() => 50.00,
        ]));
        $quote->collectTotals();

        $storedCodes = json_decode((string) $quote->getGiftcardCodes(), true);
        expect($storedCodes)->toHaveKey($valid->getCode());
        expect($storedCodes)->not->toHaveKey($disabled->getCode());
        expect((float) $quote->getBaseGiftcardAmount())->toBeGreaterThan(0.0);
    });

    test('collect clears the code map when every card is invalid', function (): void {
        $pastDate = (new DateTime('-1 day', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $expired = ($this->makeCard)(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE, $pastDate);
        $disabled = ($this->makeCard)(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);

        $quote = ($this->makeQuote)();
        $quote->setGiftcardCodes(json_encode([
            $expired->getCode() => 50.00,
            $disabled->getCode() => 50.00,
        ]));
        $quote->collectTotals();

        expect($quote->getGiftcardCodes())->toBeNull();
        expect((float) $quote->getBaseGiftcardAmount())->toBe(0.0);
        expect((float) $quote->getGiftcardAmount())->toBe(0.0);
    });
});
