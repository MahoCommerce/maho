<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\GraphQL\CartMutationHandler;

uses(Tests\MahoBackendTestCase::class);

it('lists the shipping methods of a cart without a shipping country with no PHP warning', function (): void {
    $quote = Mage::getModel('sales/quote')->setStoreId(1);
    $quote->addProduct(loadSimplePricedProduct(), 1);
    $quote->save();

    // PHPUnit does not fail a test on a warning, and developer mode turns a warning into an exception
    set_error_handler(static function (int $errno, string $errstr): never {
        throw new ErrorException($errstr, 0, $errno);
    }, E_WARNING);
    try {
        $result = new CartMutationHandler(new CartMapper())->handleShippingMethods(['cartId' => (int) $quote->getId()]);

        expect(array_column($result['availableShippingMethods'], 'carrierCode'))->toContain('flatrate');
    } finally {
        restore_error_handler();
        $quote->delete();
    }
});
