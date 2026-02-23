<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * API v2 Gift Card Write Tests
 *
 * Tests gift card creation and balance adjustment via REST and GraphQL.
 *
 * @group write
 */

afterAll(function (): void {
    // Clean up any gift cards created during tests
    $codes = giftCardTestCodes();
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

describe('POST /api/giftcards', function (): void {

    it('requires authentication', function (): void {
        $response = apiPost('/api/giftcards', [
            'initialBalance' => 50.0,
        ]);

        expect($response['status'])->toBeUnauthorized();
    });

    it('creates a gift card with auto-generated code', function (): void {
        $response = apiPost('/api/giftcards', [
            'initialBalance' => 25.0,
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('code');
        expect($response['json']['code'])->toBeString();
        expect($response['json']['code'])->not->toBeEmpty();
        expect($response['json'])->toHaveKey('balance');
        expect((float) $response['json']['balance'])->toBe(25.0);
        expect($response['json'])->toHaveKey('initialBalance');
        expect((float) $response['json']['initialBalance'])->toBe(25.0);
        expect($response['json'])->toHaveKey('status');
        expect($response['json']['status'])->toBe('active');

        // Track for cleanup
        registerGiftCardCode($response['json']['code']);
    });

    it('creates a gift card with custom code', function (): void {
        $code = 'TEST-API-' . time();

        $response = apiPost('/api/giftcards', [
            'initialBalance' => 100.0,
            'code' => $code,
            'recipientName' => 'Test Recipient',
            'recipientEmail' => 'test@example.com',
            'message' => 'Test gift card',
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json']['code'])->toBe($code);
        expect((float) $response['json']['balance'])->toBe(100.0);
        expect($response['json']['recipientName'])->toBe('Test Recipient');
        expect($response['json']['recipientEmail'])->toBe('test@example.com');
        expect($response['json']['message'])->toBe('Test gift card');

        registerGiftCardCode($code);
    });

    it('rejects zero balance', function (): void {
        $response = apiPost('/api/giftcards', [
            'initialBalance' => 0,
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
    });

    it('rejects negative balance', function (): void {
        $response = apiPost('/api/giftcards', [
            'initialBalance' => -50.0,
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
    });

    it('rejects balance over 10000', function (): void {
        $response = apiPost('/api/giftcards', [
            'initialBalance' => 10001.0,
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
    });

    it('rejects duplicate custom code', function (): void {
        $code = 'TEST-DUP-' . time();

        // Create first
        $response1 = apiPost('/api/giftcards', [
            'initialBalance' => 10.0,
            'code' => $code,
        ], adminToken());

        expect($response1['status'])->toBeSuccessful();
        registerGiftCardCode($code);

        // Try duplicate
        $response2 = apiPost('/api/giftcards', [
            'initialBalance' => 10.0,
            'code' => $code,
        ], adminToken());

        expect($response2['status'])->toBe(409);
    });

});

describe('GraphQL Gift Card mutations', function (): void {

    it('creates a gift card via GraphQL', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createGiftcardGiftCard(input: {
                initialBalance: 75.0
            }) {
                giftCard {
                    id
                    _id
                    code
                    balance
                    initialBalance
                    status
                    currencyCode
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');

        $gc = $response['json']['data']['createGiftcardGiftCard']['giftCard'];
        expect((float) $gc['balance'])->toBe(75.0);
        expect((float) $gc['initialBalance'])->toBe(75.0);
        expect($gc['status'])->toBe('active');
        expect($gc['code'])->toBeString();

        registerGiftCardCode($gc['code']);
    });

    it('creates a gift card with recipient info via GraphQL', function (): void {
        $code = 'GQL-' . time();

        $query = <<<GRAPHQL
        mutation {
            createGiftcardGiftCard(input: {
                initialBalance: 50.0,
                code: "{$code}",
                recipientName: "Jane Doe",
                recipientEmail: "jane@example.com",
                senderName: "John Smith",
                message: "Happy Birthday!"
            }) {
                giftCard {
                    code
                    balance
                    recipientName
                    recipientEmail
                    senderName
                    message
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');

        $gc = $response['json']['data']['createGiftcardGiftCard']['giftCard'];
        expect($gc['code'])->toBe($code);
        expect($gc['recipientName'])->toBe('Jane Doe');
        expect($gc['senderName'])->toBe('John Smith');
        expect($gc['message'])->toBe('Happy Birthday!');

        registerGiftCardCode($code);
    });

    it('adjusts gift card balance via GraphQL', function (): void {
        // First create a gift card
        $code = 'ADJ-' . time();

        $createQuery = <<<GRAPHQL
        mutation {
            createGiftcardGiftCard(input: {
                initialBalance: 100.0,
                code: "{$code}"
            }) {
                giftCard {
                    code
                    balance
                }
            }
        }
        GRAPHQL;

        $createResponse = gqlQuery($createQuery, [], adminToken());
        expect($createResponse['status'])->toBe(200);
        registerGiftCardCode($code);

        // Now adjust balance
        $adjustQuery = <<<GRAPHQL
        mutation {
            adjustGiftcardBalanceGiftCard(input: {
                code: "{$code}",
                newBalance: 50.0,
                comment: "Test adjustment"
            }) {
                giftCard {
                    code
                    balance
                    initialBalance
                }
            }
        }
        GRAPHQL;

        $adjustResponse = gqlQuery($adjustQuery, [], adminToken());

        expect($adjustResponse['status'])->toBe(200);
        expect($adjustResponse['json'])->not->toHaveKey('errors');

        $gc = $adjustResponse['json']['data']['adjustGiftcardBalanceGiftCard']['giftCard'];
        expect($gc['code'])->toBe($code);
        expect((float) $gc['balance'])->toBe(50.0);
        expect((float) $gc['initialBalance'])->toBe(100.0);
    });

    it('rejects adjusting non-existent gift card', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            adjustGiftcardBalanceGiftCard(input: {
                code: "NONEXISTENT-999",
                newBalance: 50.0
            }) {
                giftCard {
                    code
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['json'])->toHaveKey('errors');
    });

    it('rejects unauthenticated gift card creation', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createGiftcardGiftCard(input: {
                initialBalance: 25.0
            }) {
                giftCard {
                    id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['json'])->toHaveKey('errors');
    });

    it('rejects customer-only token for gift card creation', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createGiftcardGiftCard(input: {
                initialBalance: 25.0
            }) {
                giftCard {
                    id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        // Customer role should not be able to create gift cards
        expect($response['json'])->toHaveKey('errors');
    });

});

// Helper to track gift card codes for cleanup
function registerGiftCardCode(string $code): void
{
    $GLOBALS['_test_gc_codes'][] = $code;
}

function giftCardTestCodes(): array
{
    return $GLOBALS['_test_gc_codes'] ?? [];
}
