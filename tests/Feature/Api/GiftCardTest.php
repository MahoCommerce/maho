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
 * Integration tests for gift card operations
 *
 * These tests verify:
 * - checkGiftcardBalance query
 * - applyGiftcardToCart mutation
 * - removeGiftcardFromCart mutation
 */
class GiftCardTest extends MahoApiTestCase
{
    private const TEST_SKU = 'ST-L-ALU';

    /**
     * Test checking balance of a valid gift card
     *
     * Note: This requires a valid gift card code in the database
     */
    public function testCheckGiftcardBalance(): void
    {
        $this->markTestSkipped('Requires a valid gift card code in database');

        $response = $this->graphql('
            query CheckBalance($code: String!) {
                checkGiftcardBalance(code: $code) {
                    code
                    balance
                    expirationDate
                    status
                }
            }
        ', [
            'code' => 'TESTGC001', // Replace with actual test gift card
        ]);

        $result = $this->assertGraphQLData($response, 'checkGiftcardBalance');

        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('balance', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertIsNumeric($result['balance']);
    }

    /**
     * Test checking balance of invalid gift card returns error
     */
    public function testCheckInvalidGiftcardBalanceFails(): void
    {
        $response = $this->graphql('
            query CheckBalance($code: String!) {
                checkGiftcardBalance(code: $code) {
                    code
                    balance
                }
            }
        ', [
            'code' => 'INVALID-CODE-12345',
        ]);

        $this->assertArrayHasKey('errors', $response);
        $this->assertNotEmpty($response['errors']);
    }

    /**
     * Test applying gift card to cart
     *
     * Note: This requires a valid gift card code in the database
     */
    public function testApplyGiftcardToCart(): void
    {
        $this->markTestSkipped('Requires a valid gift card code in database');

        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        $response = $this->graphql('
            mutation ApplyGiftcard($input: ApplyGiftCardInput!) {
                applyGiftcardToCart(input: $input) {
                    id
                    appliedGiftcard {
                        code
                        amount
                    }
                    prices {
                        grandTotal
                        giftcardAmount
                    }
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'giftcardCode' => 'TESTGC001', // Replace with actual test gift card
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'applyGiftcardToCart');

        $this->assertNotNull($result['appliedGiftcard']);
        $this->assertGreaterThan(0, $result['prices']['giftcardAmount'] ?? 0);
    }

    /**
     * Test applying invalid gift card fails
     */
    public function testApplyInvalidGiftcardFails(): void
    {
        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        $response = $this->graphql('
            mutation ApplyGiftcard($input: ApplyGiftCardInput!) {
                applyGiftcardToCart(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'giftcardCode' => 'INVALID-CODE-12345',
            ],
        ]);

        $this->assertArrayHasKey('errors', $response);
        $this->assertNotEmpty($response['errors']);
    }

    /**
     * Test removing gift card from cart
     *
     * Note: This requires applying a gift card first
     */
    public function testRemoveGiftcardFromCart(): void
    {
        $this->markTestSkipped('Requires a valid gift card code in database');

        $cart = $this->createTestCart();
        $cartId = $cart['id'];

        $this->addItemToCart($cartId, self::TEST_SKU, 1);

        // First apply gift card
        $this->graphql('
            mutation ApplyGiftcard($input: ApplyGiftCardInput!) {
                applyGiftcardToCart(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'giftcardCode' => 'TESTGC001',
            ],
        ]);

        // Then remove it
        $response = $this->graphql('
            mutation RemoveGiftcard($input: RemoveGiftCardInput!) {
                removeGiftcardFromCart(input: $input) {
                    id
                    appliedGiftcard {
                        code
                    }
                    prices {
                        grandTotal
                        giftcardAmount
                    }
                }
            }
        ', [
            'input' => [
                'cartId' => $cartId,
                'giftcardCode' => 'TESTGC001',
            ],
        ]);

        $result = $this->assertGraphQLData($response, 'removeGiftcardFromCart');

        $this->assertNull($result['appliedGiftcard']);
        $this->assertEquals(0, $result['prices']['giftcardAmount'] ?? 0);
    }
}
