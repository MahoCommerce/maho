<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A rate refresh (removeAllShippingRates() + requestShippingRates()) marks the
 * DB-loaded rates deleted and appends fresh ones under the same codes.
 * getShippingRateByCode() used to return the first code match, the deleted
 * stale rate, which made the place-order gate reject every method whose rates
 * were persisted by an earlier request.
 */
function quoteAddressRate(string $carrier, string $method): Mage_Sales_Model_Quote_Address_Rate
{
    /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
    $rate = Mage::getModel('sales/quote_address_rate');
    $rate->setCode($carrier . '_' . $method)
        ->setCarrier($carrier)
        ->setMethod($method)
        ->setPrice(5.0);
    return $rate;
}

describe('quote address shipping rate lookup', function (): void {

    it('returns the fresh rate when a deleted rate shares its code', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $address = $quote->getShippingAddress();
            $address->addShippingRate(quoteAddressRate('flatrate', 'flatrate'));
            $address->removeAllShippingRates();
            $fresh = quoteAddressRate('flatrate', 'flatrate');
            $address->addShippingRate($fresh);

            $rate = $address->getShippingRateByCode('flatrate_flatrate');
            expect($rate)->toBe($fresh)
                ->and($rate->isDeleted())->toBeFalse();
        } finally {
            $quote->delete();
        }
    });

    it('returns false when only deleted rates match', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $address = $quote->getShippingAddress();
            $address->addShippingRate(quoteAddressRate('flatrate', 'flatrate'));
            $address->removeAllShippingRates();

            expect($address->getShippingRateByCode('flatrate_flatrate'))->toBeFalse();
        } finally {
            $quote->delete();
        }
    });

});
