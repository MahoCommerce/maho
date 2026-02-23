<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tests\Feature\Api;

use Tests\MahoApiTestCase;

/**
 * Integration tests for split payment operations
 *
 * These tests verify the placeOrderWithSplitPayments mutation works correctly.
 * They can be run against both the legacy controller and new API Platform.
 */
class SplitPaymentTest extends MahoApiTestCase
{
    private const TEST_SKU = 'ST-L-ALU'; // Simple product SKU

    /**
     * Test placing order with single cash payment
     */
    public function testPlaceOrderWithSingleCashPayment(): void
    {
        // Create cart and add product
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        // Add item to cart
        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        // Set addresses and shipping
        $this->setShippingAddress($cartId);
        $this->setShippingMethod($cartId, 'pickup_pickup');

        // Place order with split payment
        $response = $this->graphql('
            mutation PlaceOrderWithSplitPayments($input: PlaceOrderWithSplitPaymentsInput!) {
                placeOrderWithSplitPayments(input: $input) {
                    order {
                        id
                        incrementId
                        status
                        state
                        prices {
                            grandTotal
                            totalPaid
                        }
                    }
                    changeAmount
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'shippingMethod' => 'pickup_pickup',
                'registerId' => '1',
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 100.00,
                    ],
                ],
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'placeOrderWithSplitPayments');

        // Verify order was created
        $this->assertNotNull($result['order']['id'], 'Order should have an ID');
        $this->assertNotNull($result['order']['incrementId'], 'Order should have an increment ID');
        $this->assertIsNumeric($result['changeAmount'], 'Change amount should be numeric');
    }

    /**
     * Test placing order with multiple payment methods (split payment)
     */
    public function testPlaceOrderWithMultiplePayments(): void
    {
        // Create cart and add product
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        // Add item to cart
        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        // Set addresses and shipping
        $this->setShippingAddress($cartId);
        $this->setShippingMethod($cartId, 'pickup_pickup');

        // Get grand total to split correctly
        $cartResponse = $this->graphql('
            query GetCart($cartId: ID!) {
                cart(id: $cartId) {
                    prices {
                        grandTotal
                    }
                }
            }
        ', ['cartId' => $cartId]);
        $grandTotal = $cartResponse['data']['cart']['prices']['grandTotal'];
        $halfAmount = round($grandTotal / 2, 2);

        // Place order with split payment
        $response = $this->graphql('
            mutation PlaceOrderWithSplitPayments($input: PlaceOrderWithSplitPaymentsInput!) {
                placeOrderWithSplitPayments(input: $input) {
                    order {
                        id
                        incrementId
                        status
                        state
                        paymentMethod
                        prices {
                            grandTotal
                            totalPaid
                        }
                    }
                    changeAmount
                    payments {
                        id
                        method
                        amount
                    }
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'shippingMethod' => 'pickup_pickup',
                'registerId' => '1',
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => $halfAmount,
                    ],
                    [
                        'method' => 'eftpos',
                        'amount' => $grandTotal - $halfAmount,
                    ],
                ],
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'placeOrderWithSplitPayments');

        // Verify order was created
        $this->assertNotNull($result['order']['id']);
        $this->assertNotNull($result['order']['incrementId']);

        // Verify payment method is split payment
        $this->assertEquals('maho_pos_split', $result['order']['paymentMethod']);

        // Verify payments were recorded
        $this->assertCount(2, $result['payments'], 'Should have 2 payment records');
    }

    /**
     * Test placing order with cash payment over grand total (change calculation)
     */
    public function testPlaceOrderWithCashOverpayment(): void
    {
        // Create cart and add product
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        // Add item to cart
        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        // Set addresses and shipping
        $this->setShippingAddress($cartId);
        $this->setShippingMethod($cartId, 'pickup_pickup');

        // Get grand total
        $cartResponse = $this->graphql('
            query GetCart($cartId: ID!) {
                cart(id: $cartId) {
                    prices {
                        grandTotal
                    }
                }
            }
        ', ['cartId' => $cartId]);
        $grandTotal = $cartResponse['data']['cart']['prices']['grandTotal'];

        // Pay with more cash than needed
        $cashTendered = ceil($grandTotal) + 10;

        $response = $this->graphql('
            mutation PlaceOrderWithSplitPayments($input: PlaceOrderWithSplitPaymentsInput!) {
                placeOrderWithSplitPayments(input: $input) {
                    order {
                        id
                        incrementId
                        prices {
                            grandTotal
                        }
                    }
                    changeAmount
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'shippingMethod' => 'pickup_pickup',
                'registerId' => '1',
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => $cashTendered,
                    ],
                ],
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'placeOrderWithSplitPayments');

        // Verify change amount is calculated correctly
        $expectedChange = $cashTendered - $grandTotal;
        $this->assertEqualsWithDelta($expectedChange, $result['changeAmount'], 0.01, 'Change amount should be cash - grand total');
    }

    /**
     * Test placing order with insufficient payment should fail
     */
    public function testPlaceOrderWithInsufficientPaymentFails(): void
    {
        // Create cart and add product
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        // Add item to cart
        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        // Set addresses and shipping
        $this->setShippingAddress($cartId);
        $this->setShippingMethod($cartId, 'pickup_pickup');

        // Try to place order with insufficient payment
        $response = $this->graphql('
            mutation PlaceOrderWithSplitPayments($input: PlaceOrderWithSplitPaymentsInput!) {
                placeOrderWithSplitPayments(input: $input) {
                    order {
                        id
                    }
                    changeAmount
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'shippingMethod' => 'pickup_pickup',
                'registerId' => '1',
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 1.00, // Insufficient
                    ],
                ],
            ],
        ]);

        // Should have an error
        $this->assertArrayHasKey('errors', $response, 'Should have GraphQL errors');
        $this->assertNotEmpty($response['errors'], 'Errors should not be empty');
    }
}
