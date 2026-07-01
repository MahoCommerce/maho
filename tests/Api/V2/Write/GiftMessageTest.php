<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Gift Message Tests (WRITE)
 *
 * Cart-level and per-item gift messages over REST and GraphQL, for guest and
 * authenticated carts. Gift options are enabled in the test store first since
 * they default off.
 *
 * @group write
 */

beforeAll(function (): void {
    \Mage::app();
    $config = \Mage::getModel('core/config');
    $config->saveConfig('sales/gift_options/allow_order', '1', 'default', 0);
    $config->saveConfig('sales/gift_options/allow_items', '1', 'default', 0);
    \Mage::app()->getCache()->cleanType('config');
});

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Create a guest cart with one item; returns [maskedId, firstItemId].
 *
 * @return array{0: ?string, 1: ?int}
 */
function makeGuestCartWithItem(): array
{
    $create = apiPost('/api/rest/v2/guest-carts', []);
    if (($create['status'] ?? 0) >= 300) {
        return [null, null];
    }
    trackCreated('quote', (int) $create['json']['id']);
    $maskedId = $create['json']['maskedId'];

    $add = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", [
        'sku' => fixtures('write_test_sku'),
        'qty' => 1,
    ]);
    $itemId = (int) ($add['json']['items'][0]['id'] ?? 0);

    return [$maskedId, $itemId ?: null];
}

describe('Guest cart gift messages', function (): void {

    it('sets, reads and removes a cart-level gift message', function (): void {
        [$maskedId] = makeGuestCartWithItem();
        expect($maskedId)->not->toBeNull();

        $set = apiPut("/api/rest/v2/guest-carts/{$maskedId}/gift-message", [
            'sender' => 'Alice',
            'recipient' => 'Bob',
            'message' => 'Happy Birthday!',
        ]);

        expect($set['status'])->toBeSuccessful();
        expect($set['json']['giftMessage'])->not->toBeNull();
        expect($set['json']['giftMessage']['message'])->toBe('Happy Birthday!');
        expect($set['json']['giftMessage']['sender'])->toBe('Alice');

        $remove = apiDelete("/api/rest/v2/guest-carts/{$maskedId}/gift-message");
        expect($remove['status'])->toBeSuccessful();
        expect($remove['json']['giftMessage'])->toBeNull();
    });

    it('sets a per-item gift message', function (): void {
        [$maskedId, $itemId] = makeGuestCartWithItem();
        if (!$maskedId || !$itemId) {
            $this->markTestSkipped('Could not build a guest cart with an item');
        }

        $set = apiPut("/api/rest/v2/guest-carts/{$maskedId}/items/{$itemId}/gift-message", [
            'sender' => 'Carol',
            'recipient' => 'Dave',
            'message' => 'Enjoy!',
        ]);

        expect($set['status'])->toBeSuccessful();
        $item = null;
        foreach ($set['json']['items'] as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $itemId) {
                $item = $candidate;
                break;
            }
        }
        expect($item)->not->toBeNull();
        expect($item['giftMessage'])->not->toBeNull();
        expect($item['giftMessage']['message'])->toBe('Enjoy!');
    });

});

describe('Authenticated cart gift messages', function (): void {

    it('sets a cart-level gift message for the owner', function (): void {
        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        expect($create['status'])->toBeSuccessful();
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);
        apiPost("/api/rest/v2/carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ], customerToken());

        $set = apiPut("/api/rest/v2/carts/{$cartId}/gift-message", [
            'sender' => 'Eve',
            'recipient' => 'Frank',
            'message' => 'Congratulations',
        ], customerToken());

        expect($set['status'])->toBeSuccessful();
        expect($set['json']['giftMessage']['message'])->toBe('Congratulations');
    });

    it('requires authentication', function (): void {
        $response = apiPut('/api/rest/v2/carts/1/gift-message', [
            'sender' => 'X', 'recipient' => 'Y', 'message' => 'Z',
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('GraphQL gift message mutations', function (): void {

    it('sets a gift message on a guest cart by masked id', function (): void {
        [$maskedId] = makeGuestCartWithItem();
        expect($maskedId)->not->toBeNull();

        // ApiPlatform names a custom mutation field {name}{ShortName}.
        $query = <<<'GRAPHQL'
        mutation SetGm($maskedId: String!, $sender: String!, $recipient: String!, $message: String!) {
            setGiftMessageCart(input: {
                maskedId: $maskedId,
                sender: $sender,
                recipient: $recipient,
                message: $message
            }) {
                cart { giftMessage }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [
            'maskedId' => $maskedId,
            'sender' => 'Grace',
            'recipient' => 'Heidi',
            'message' => 'Best wishes',
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['setGiftMessageCart']['cart']['giftMessage'])->not->toBeNull();
    });

});
