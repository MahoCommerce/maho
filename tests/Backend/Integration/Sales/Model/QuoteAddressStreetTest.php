<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A street column holds one newline-joined string. setStreet() enforces that, but
 * setData()/addData() bypass the magic setter, so an array survived to the flat
 * table and prepareColumnValue() cast it to the literal string 'Array'.
 */
function quoteAddressStreetColumn(int $addressId): mixed
{
    $core = Mage::getSingleton('core/resource');
    $adapter = $core->getConnection('core_read');

    return $adapter->fetchOne(
        $adapter->select()
            ->from($core->getTableName('sales/quote_address'), ['street'])
            ->where('address_id = ?', $addressId),
    );
}

describe('quote address street column', function (): void {

    it('joins an array street applied with addData', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $address = $quote->getShippingAddress();
            $address->addData([
                'firstname' => 'Street',
                'lastname' => 'Tester',
                'street' => ['1 Test Street', 'Apt 7'],
                'city' => 'Los Angeles',
                'postcode' => '90210',
                'country_id' => 'US',
            ]);
            $address->save();

            expect(quoteAddressStreetColumn((int) $address->getId()))->toBe("1 Test Street\nApt 7");

            $reloaded = Mage::getModel('sales/quote_address')->load($address->getId());
            expect($reloaded->getStreet())->toBe(['1 Test Street', 'Apt 7']);
        } finally {
            $quote->delete();
        }
    });

    it('leaves a string street untouched', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $address = $quote->getShippingAddress();
            $address->addData([
                'street' => "1 Test Street\nApt 7",
                'country_id' => 'US',
            ]);
            $address->save();

            expect(quoteAddressStreetColumn((int) $address->getId()))->toBe("1 Test Street\nApt 7");
        } finally {
            $quote->delete();
        }
    });

});
