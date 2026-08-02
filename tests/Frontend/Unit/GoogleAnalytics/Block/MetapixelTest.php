<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('Meta Pixel Advanced Matching', function () {
    beforeEach(function () {
        $this->block = new Mage_GoogleAnalytics_Block_Metapixel();
    });

    describe('buildAdvancedMatchingData', function () {
        it('normalizes and hashes email addresses', function () {
            $result = $this->block->buildAdvancedMatchingData(['em' => ' John.Doe@Example.COM ']);

            expect($result)->toBe(['em' => hash('sha256', 'john.doe@example.com')]);
        });

        it('keeps only digits in phone numbers entered in international format', function () {
            $result = $this->block->buildAdvancedMatchingData(['ph' => '+44 (0) 7911 123-456']);

            expect($result)->toBe(['ph' => hash('sha256', '4407911123456')]);
        });

        it('prepends the billing country calling code to national phone numbers', function () {
            expect($this->block->buildAdvancedMatchingData(['ph' => '(555) 123-4567', 'country' => 'US'])['ph'])
                ->toBe(hash('sha256', '15551234567'));
            expect($this->block->buildAdvancedMatchingData(['ph' => '07911 123456', 'country' => 'GB'])['ph'])
                ->toBe(hash('sha256', '447911123456'));
        });

        it('keeps the Italian trunk zero when prepending the country code', function () {
            expect($this->block->buildAdvancedMatchingData(['ph' => '06 1234567', 'country' => 'IT'])['ph'])
                ->toBe(hash('sha256', '39061234567'));
        });

        it('treats a 00 dial-out prefix as international format', function () {
            expect($this->block->buildAdvancedMatchingData(['ph' => '0044 7911 123456', 'country' => 'GB'])['ph'])
                ->toBe(hash('sha256', '447911123456'));
        });

        it('sends phone digits as entered when the country is unknown', function () {
            expect($this->block->buildAdvancedMatchingData(['ph' => '555-123-4567']))
                ->toBe(['ph' => hash('sha256', '5551234567')]);
        });

        it('does not double the country code when it was already entered', function () {
            expect($this->block->buildAdvancedMatchingData(['ph' => '1 555 123 4567', 'country' => 'US'])['ph'])
                ->toBe(hash('sha256', '15551234567'));
        });

        it('strips punctuation and whitespace from names and cities', function () {
            $result = $this->block->buildAdvancedMatchingData([
                'fn' => "O'Brien",
                'ct' => 'New York',
            ]);

            expect($result)->toBe([
                'fn' => hash('sha256', 'obrien'),
                'ct' => hash('sha256', 'newyork'),
            ]);
        });

        it('preserves non-latin characters in names', function () {
            $result = $this->block->buildAdvancedMatchingData(['ln' => 'Müller']);

            expect($result)->toBe(['ln' => hash('sha256', 'müller')]);
        });

        it('truncates US ZIP+4 codes to the 5-digit base', function () {
            $result = $this->block->buildAdvancedMatchingData(['zp' => '90210-1234', 'country' => 'US']);

            expect($result['zp'])->toBe(hash('sha256', '90210'));
        });

        it('keeps all digits of hyphenated non-US postal codes, dropping the separator', function () {
            $result = $this->block->buildAdvancedMatchingData(['zp' => '123-4567', 'country' => 'JP']);

            expect($result['zp'])->toBe(hash('sha256', '1234567'));
        });

        it('removes spaces from postal codes and lowercases them', function () {
            $result = $this->block->buildAdvancedMatchingData(['zp' => 'SW1A 1AA', 'country' => 'GB']);

            expect($result['zp'])->toBe(hash('sha256', 'sw1a1aa'));
        });

        it('lowercases the two-letter country code', function () {
            $result = $this->block->buildAdvancedMatchingData(['country' => 'GB']);

            expect($result)->toBe(['country' => hash('sha256', 'gb')]);
        });

        it('accepts only m or f as gender', function () {
            expect($this->block->buildAdvancedMatchingData(['ge' => 'm']))
                ->toBe(['ge' => hash('sha256', 'm')]);
            expect($this->block->buildAdvancedMatchingData(['ge' => 'x']))->toBe([]);
        });

        it('formats birthdates as YYYYMMDD', function () {
            expect($this->block->buildAdvancedMatchingData(['db' => '1985-04-12']))
                ->toBe(['db' => hash('sha256', '19850412')]);
            expect($this->block->buildAdvancedMatchingData(['db' => '1985-04-12 00:00:00']))
                ->toBe(['db' => hash('sha256', '19850412')]);
        });

        it('omits invalid birthdates', function () {
            expect($this->block->buildAdvancedMatchingData(['db' => 'not a date']))->toBe([]);
        });

        it('omits legacy zero-date birthdates', function () {
            expect($this->block->buildAdvancedMatchingData(['db' => '0000-00-00']))->toBe([]);
            expect($this->block->buildAdvancedMatchingData(['db' => '0000-00-00 00:00:00']))->toBe([]);
        });

        it('hashes the external id as provided', function () {
            $result = $this->block->buildAdvancedMatchingData(['external_id' => '42']);

            expect($result)->toBe(['external_id' => hash('sha256', '42')]);
        });

        it('omits empty and whitespace-only values', function () {
            $result = $this->block->buildAdvancedMatchingData([
                'em' => '',
                'fn' => '   ',
                'ln' => 'Doe',
            ]);

            expect($result)->toBe(['ln' => hash('sha256', 'doe')]);
        });

        it('returns an empty array for empty input', function () {
            expect($this->block->buildAdvancedMatchingData([]))->toBe([]);
        });

        it('outputs only 64-character lowercase hex values', function () {
            $result = $this->block->buildAdvancedMatchingData([
                'em' => 'john@example.com',
                'ph' => '+1 555 123 4567',
                'fn' => 'John',
                'ln' => 'Doe',
                'ge' => 'm',
                'db' => '1985-04-12',
                'ct' => 'Springfield',
                'st' => 'IL',
                'zp' => '62701',
                'country' => 'US',
                'external_id' => '42',
            ]);

            expect($result)->toHaveCount(11);
            foreach ($result as $value) {
                expect($value)->toMatch('/^[0-9a-f]{64}$/');
            }
        });
    });
});

describe('Meta Pixel Purchase event', function () {
    beforeEach(function () {
        $this->block = new Mage_GoogleAnalytics_Block_Metapixel();
    });

    it('sends the catalog product SKU and tax-inclusive prices', function () {
        $product = Mage::getModel('catalog/product')->setSku('basesku123');
        $item = Mage::getModel('sales/order_item')
            ->setSku('basesku123-custom option')
            ->setQtyOrdered(2)
            ->setBasePrice(100.00)
            ->setBasePriceInclTax(122.00)
            ->setProduct($product);
        $order = Mage::getModel('sales/order')
            ->setBaseGrandTotal(254.00)
            ->setBaseCurrencyCode('EUR')
            ->setIncrementId('100000042');
        $order->addItem($item);

        $eventData = $this->block->getPurchaseEventData($order);

        expect($eventData['content_ids'])->toBe(['basesku123']);
        expect($eventData['contents'][0]['id'])->toBe('basesku123');
        expect($eventData['contents'][0]['quantity'])->toBe(2);
        expect($eventData['contents'][0]['item_price'])->toBe(122.0);
        expect($eventData['value'])->toBe(254.0);
        expect($eventData['currency'])->toBe('EUR');
        expect($eventData['num_items'])->toBe(2);
        expect($eventData['order_id'])->toBe('100000042');
    });

    it('falls back to the order item SKU when the product no longer exists', function () {
        $item = Mage::getModel('sales/order_item')
            ->setSku('deleted-sku')
            ->setQtyOrdered(1)
            ->setBasePriceInclTax(10.00)
            ->setProduct(Mage::getModel('catalog/product'));
        $order = Mage::getModel('sales/order')->setBaseGrandTotal(10.00);
        $order->addItem($item);

        expect($this->block->getPurchaseEventData($order)['content_ids'])->toBe(['deleted-sku']);
    });

    it('falls back to the tax-exclusive price when the incl-tax column is null', function () {
        $item = Mage::getModel('sales/order_item')
            ->setSku('legacy-sku')
            ->setQtyOrdered(1)
            ->setBasePrice(100.00)
            ->setProduct(Mage::getModel('catalog/product'));
        $order = Mage::getModel('sales/order')->setBaseGrandTotal(100.00);
        $order->addItem($item);

        expect($this->block->getPurchaseEventData($order)['contents'][0]['item_price'])->toBe(100.0);
    });

    it('returns null for an order without visible items', function () {
        expect($this->block->getPurchaseEventData(Mage::getModel('sales/order')))->toBeNull();
    });
});
