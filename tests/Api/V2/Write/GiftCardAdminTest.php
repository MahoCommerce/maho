<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Gift Card admin surface tests
 *
 * Admin/service reads expose websiteIds and history, status and balance are
 * writable via the gated REST Put (balance changes record a history entry),
 * while the public balance check stays masked and read-only.
 *
 * @group write
 */

afterAll(function (): void {
    $codes = $GLOBALS['_test_gc_admin_codes'] ?? [];
    if (!empty($codes)) {
        try {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            foreach ($codes as $code) {
                $write->delete('giftcard', ['code = ?' => $code]);
            }
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
});

function trackAdminGiftCard(string $code): void
{
    $GLOBALS['_test_gc_admin_codes'][] = $code;
}

describe('GET /api/rest/v2/giftcards/{id} (admin read)', function (): void {

    it('exposes websiteIds, lifecycle timestamps and history', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 30.0,
            'websiteIds' => [1],
        ], adminToken());

        expect($create['status'])->toBeSuccessful();
        expect($create['json']['websiteIds'])->toBe([1]);
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        $read = apiGet("/api/rest/v2/giftcards/{$id}", adminToken());
        expect($read['status'])->toBe(200);
        expect($read['json']['websiteIds'])->toBe([1]);
        expect($read['json']['createdAt'])->not->toBeEmpty();
        expect($read['json']['updatedAt'])->not->toBeEmpty();
        expect($read['json']['history'])->toBeArray();

        // The purchase and delivery fields are written only by the gift card
        // product order flow, so a card created in the admin has none of them.
        // Null fields are omitted from REST responses (skip_null_values).
        expect($read['json']['purchaseOrderId'] ?? null)->toBeNull();
        expect($read['json']['purchaseOrderItemId'] ?? null)->toBeNull();
        expect($read['json']['emailScheduledAt'] ?? null)->toBeNull();
        expect($read['json']['emailSentAt'] ?? null)->toBeNull();
    });

    it('mirrors balance into initialBalance when a create only provides balance', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['balance' => 50.0], adminToken());

        expect($create['status'])->toBeSuccessful();
        trackAdminGiftCard($create['json']['code']);
        expect((float) $create['json']['balance'])->toBe(50.0);
        expect((float) $create['json']['initialBalance'])->toBe(50.0);
    });

    it('rejects a create whose balance is out of range, even with a valid initialBalance', function (): void {
        $overLimit = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 10.0,
            'balance' => 99999.0,
        ], adminToken());
        expect($overLimit['status'])->toBe(400);

        $negative = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 10.0,
            'balance' => -5.0,
        ], adminToken());
        expect($negative['status'])->toBe(400);
    });

    it('rejects an unknown websiteId on create', function (): void {
        $response = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 30.0,
            'websiteIds' => [99999],
        ], adminToken());

        expect($response['status'])->toBe(400);
    });

});

describe('PUT /api/rest/v2/giftcards/{id} (admin write)', function (): void {

    it('changes status for an admin token', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 20.0], adminToken());
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        $disable = apiPut("/api/rest/v2/giftcards/{$id}", ['status' => 'disabled'], adminToken());
        expect($disable['status'])->toBe(200);
        expect($disable['json']['status'])->toBe('disabled');

        $read = apiGet("/api/rest/v2/giftcards/{$id}", adminToken());
        expect($read['json']['status'])->toBe('disabled');

        $reactivate = apiPut("/api/rest/v2/giftcards/{$id}", ['status' => 'active'], serviceToken(['giftcards/write', 'giftcards/read']));
        expect($reactivate['status'])->toBe(200);
        expect($reactivate['json']['status'])->toBe('active');
    });

    it('rejects an invalid status value', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 20.0], adminToken());
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        $response = apiPut("/api/rest/v2/giftcards/{$id}", ['status' => 'frozen'], adminToken());
        expect($response['status'])->toBe(400);
    });

    it('adjusts balance and records an adjusted history entry', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 100.0], adminToken());
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        $adjust = apiPut("/api/rest/v2/giftcards/{$id}", [
            'balance' => 40.0,
            'comment' => 'Pest manual adjustment',
        ], adminToken());

        expect($adjust['status'])->toBe(200);
        expect((float) $adjust['json']['balance'])->toBe(40.0);
        expect((float) $adjust['json']['initialBalance'])->toBe(100.0);

        $history = $adjust['json']['history'];
        expect($history)->not->toBeEmpty();
        $entry = $history[0];
        expect($entry['action'])->toBe('adjusted');
        expect((float) $entry['amount'])->toBe(-60.0);
        expect((float) $entry['balanceBefore'])->toBe(100.0);
        expect((float) $entry['balanceAfter'])->toBe(40.0);
        expect($entry['comment'])->toBe('Pest manual adjustment');
        expect($entry['createdAt'])->not->toBeEmpty();
    });

    it('rejects out-of-range balances', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 20.0], adminToken());
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        expect(apiPut("/api/rest/v2/giftcards/{$id}", ['balance' => -1.0], adminToken())['status'])->toBe(400);
        expect(apiPut("/api/rest/v2/giftcards/{$id}", ['balance' => 10001.0], adminToken())['status'])->toBe(400);
    });

});

describe('GraphQL adjustBalanceGiftCard', function (): void {

    it('rejects an out-of-range balance like the REST update does', function (): void {
        $code = 'PEST-GQL-BOUNDS-' . time();
        $create = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 20.0,
            'code' => $code,
        ], adminToken());
        expect($create['status'])->toBeSuccessful();
        trackAdminGiftCard($code);
        $id = (int) $create['json']['id'];

        foreach ([-1.0, 10001.0] as $newBalance) {
            $mutation = <<<GRAPHQL
            mutation {
                adjustBalanceGiftCard(input: {
                    code: "{$code}",
                    newBalance: {$newBalance}
                }) {
                    giftCard {
                        balance
                    }
                }
            }
            GRAPHQL;

            $response = gqlQuery($mutation, [], adminToken());
            expect($response['json'])->toHaveKey('errors');
        }

        $read = apiGet("/api/rest/v2/giftcards/{$id}", adminToken());
        expect((float) $read['json']['balance'])->toBe(20.0);
    });

});

describe('Gift card public/customer path stays read-only and masked', function (): void {

    it('masks the code on the public balance check and omits history', function (): void {
        $code = 'PEST-MASK-' . time();
        $create = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 15.0,
            'code' => $code,
        ], adminToken());
        expect($create['status'])->toBeSuccessful();
        trackAdminGiftCard($code);

        $query = <<<GRAPHQL
        {
            checkBalanceGiftCard(code: "{$code}") {
                code
                balance
                status
                history
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');

        $gc = $response['json']['data']['checkBalanceGiftCard'];
        expect($gc['code'])->not->toBe($code);
        expect($gc['code'])->toContain('****');
        expect((float) $gc['balance'])->toBe(15.0);
        expect($gc['history'] ?? [])->toBeEmpty();
    });

    it('denies writes without a token and with a customer token', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 20.0], adminToken());
        $id = (int) $create['json']['id'];
        trackAdminGiftCard($create['json']['code']);

        expect(apiPut("/api/rest/v2/giftcards/{$id}", ['status' => 'disabled'])['status'])->toBe(401);
        expect(apiPut("/api/rest/v2/giftcards/{$id}", ['status' => 'disabled'], customerToken())['status'])->toBeForbidden();
        expect(apiPut("/api/rest/v2/giftcards/{$id}", ['balance' => 1.0], customerToken())['status'])->toBeForbidden();
        expect(apiPost('/api/rest/v2/giftcards', ['initialBalance' => 5.0], customerToken())['status'])->toBeForbidden();

        // Customer tokens cannot read the admin detail either
        expect(apiGet("/api/rest/v2/giftcards/{$id}", customerToken())['status'])->toBeForbidden();

        // The card is untouched
        $read = apiGet("/api/rest/v2/giftcards/{$id}", adminToken());
        expect($read['json']['status'])->toBe('active');
        expect((float) $read['json']['balance'])->toBe(20.0);
    });

});
