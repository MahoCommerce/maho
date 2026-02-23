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
 * Integration tests for payment query operations
 *
 * These tests verify:
 * - recordPayment mutation (add payment to existing order)
 * - orderPayments query (get all POS payments for an order)
 * - orderPaymentSummary query (get payment totals by method)
 */
class PaymentQueryTest extends MahoApiTestCase
{
    private const TEST_SKU = 'ST-L-ALU';

    /**
     * Test recording a payment against an existing order
     */
    public function testRecordPayment(): void
    {
        // First create an order with split payment
        $orderId = $this->createTestOrder();

        // Record an additional payment
        $response = $this->graphql('
            mutation RecordPayment($input: RecordPaymentInput!) {
                recordPayment(input: $input) {
                    id
                    orderId
                    method
                    amount
                    transactionId
                    createdAt
                }
            }
        ', [
            'input' => [
                'orderId' => $orderId,
                'method' => 'eftpos',
                'amount' => 25.00,
                'transactionId' => 'TXN-' . time(),
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'recordPayment');

        $this->assertNotNull($result['id'], 'Payment should have an ID');
        $this->assertEquals($orderId, $result['orderId']);
        $this->assertEquals('eftpos', $result['method']);
        $this->assertEquals(25.00, $result['amount']);
    }

    /**
     * Test querying all payments for an order
     */
    public function testOrderPayments(): void
    {
        // Create an order with split payments
        $orderId = $this->createTestOrderWithPayments();

        // Query payments
        $response = $this->graphql('
            query OrderPayments($orderId: ID!) {
                orderPayments(orderId: $orderId) {
                    id
                    orderId
                    method
                    amount
                    transactionId
                    createdAt
                }
            }
        ', [
            'orderId' => $orderId,
        ]);

        $result = $this->assertGraphQLData($response, 'orderPayments');

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result), 'Should have at least one payment');

        // Verify payment structure
        $payment = $result[0];
        $this->assertArrayHasKey('id', $payment);
        $this->assertArrayHasKey('orderId', $payment);
        $this->assertArrayHasKey('method', $payment);
        $this->assertArrayHasKey('amount', $payment);
    }

    /**
     * Test querying payment summary for an order
     */
    public function testOrderPaymentSummary(): void
    {
        // Create an order with multiple payment methods
        $orderId = $this->createTestOrderWithPayments();

        // Query payment summary
        $response = $this->graphql('
            query OrderPaymentSummary($orderId: ID!) {
                orderPaymentSummary(orderId: $orderId) {
                    method
                    methodTitle
                    totalAmount
                    paymentCount
                }
            }
        ', [
            'orderId' => $orderId,
        ]);

        $result = $this->assertGraphQLData($response, 'orderPaymentSummary');

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result), 'Should have at least one payment method summary');

        // Verify summary structure
        $summary = $result[0];
        $this->assertArrayHasKey('method', $summary);
        $this->assertArrayHasKey('methodTitle', $summary);
        $this->assertArrayHasKey('totalAmount', $summary);
        $this->assertArrayHasKey('paymentCount', $summary);
    }

    /**
     * Test recording payment on non-existent order fails
     */
    public function testRecordPaymentOnInvalidOrderFails(): void
    {
        $response = $this->graphql('
            mutation RecordPayment($input: RecordPaymentInput!) {
                recordPayment(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'orderId' => '999999999', // Non-existent
                'method' => 'cash',
                'amount' => 10.00,
            ],
        ]);

        $this->assertArrayHasKey('errors', $response);
        $this->assertNotEmpty($response['errors']);
    }

    /**
     * Helper to create a test order and return its ID
     */
    private function createTestOrder(): int
    {
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        $this->addItemToCart($cartId, self::TEST_SKU, 1);
        $this->setShippingAddress($cartId);
        $this->setShippingMethod($cartId, 'pickup_pickup');

        $response = $this->graphql('
            mutation PlaceOrder($input: PlaceOrderInput!) {
                placeOrder(input: $input) {
                    id
                    incrementId
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
            ],
        ]);

        $order = $this->assertGraphQLData($response, 'placeOrder');
        return (int) $order['id'];
    }

    /**
     * Helper to create a test order with split payments
     */
    private function createTestOrderWithPayments(): int
    {
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        $this->addItemToCart($cartId, self::TEST_SKU, 1);
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
        $halfAmount = round($grandTotal / 2, 2);

        $response = $this->graphql('
            mutation PlaceOrderWithSplitPayments($input: PlaceOrderWithSplitPaymentsInput!) {
                placeOrderWithSplitPayments(input: $input) {
                    order {
                        id
                    }
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'shippingMethod' => 'pickup_pickup',
                'registerId' => '1',
                'payments' => [
                    ['method' => 'cash', 'amount' => $halfAmount],
                    ['method' => 'eftpos', 'amount' => $grandTotal - $halfAmount],
                ],
            ],
        ]);

        $order = $this->assertGraphQLData($response, 'placeOrderWithSplitPayments');
        return (int) $order['order']['id'];
    }
}
