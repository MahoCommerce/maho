<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartRequest;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

uses(Tests\MahoFrontendTestCase::class);

it('throws for an address field of the wrong type', function (array $field, string $message): void {
    $address = $field + ['firstName' => 'One', 'lastName' => 'Pass', 'street' => ['1 Main St'], 'city' => 'Los Angeles', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100'];

    expect(fn() => CartRequest::mapAddress($address))->toThrow(NotNormalizableValueException::class, $message);
})->with([
    'a flag that is a word' => [['saveInAddressBook' => 'maybe'], 'The type of the "saveInAddressBook" attribute must be "bool", "string" given.'],
    'a region id that is a list' => [['regionId' => [12]], 'The type of the "regionId" attribute must be "int", "array" given.'],
]);

it('names the address in the error of a field of the wrong type', function (): void {
    expect(fn() => CartRequest::mapAddress(['regionId' => [12]], 'billingAddress'))
        ->toThrow(NotNormalizableValueException::class, 'The type of the "billingAddress.regionId" attribute must be "int", "array" given.');
});
