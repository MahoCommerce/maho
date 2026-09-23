<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Coupon Tests (WRITE)
 *
 * Covers coupon scoping (websiteIds / customerGroupIds), per-coupon
 * expirationDate and the rule-level knobs exposed by the Coupon resource.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Coupon scoping', function (): void {

    it('creates a coupon with explicit websiteIds and customerGroupIds that round-trip', function (): void {
        $token = adminToken();
        $code = 'PestScope' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [1],
            'customerGroupIds' => [1],
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);

        // Exactly the requested scope, not the historical all-groups/all-websites default.
        expect($get['json']['websiteIds'])->toBe([1]);
        expect($get['json']['customerGroupIds'])->toBe([1]);
        expect($get['json']['customerGroupIds'])->not->toContain(0);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps the all-groups default when scoping fields are omitted', function (): void {
        $token = adminToken();
        $code = 'PestAll' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'fixed',
            'discountAmount' => 5,
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        // NOT LOGGED IN (0) is only present under the all-groups default.
        expect($get['json']['customerGroupIds'])->toContain(0);
        expect($get['json']['customerGroupIds'])->toContain(1);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('rejects unknown website and customer group ids', function (): void {
        $token = adminToken();

        $badWebsite = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestBadW' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [999999],
        ], $token);
        expect($badWebsite['status'])->toBeGreaterThanOrEqual(400);
        expect($badWebsite['status'])->toBeLessThan(500);

        $badGroup = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestBadG' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
            'customerGroupIds' => [999999],
        ], $token);
        expect($badGroup['status'])->toBeGreaterThanOrEqual(400);
        expect($badGroup['status'])->toBeLessThan(500);
    });

});

describe('Coupon expiration date', function (): void {

    it('round-trips a per-coupon expirationDate and clears it with an empty string', function (): void {
        $token = adminToken();
        $code = 'PestExp' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 15,
            'expirationDate' => '2030-12-31',
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect((string) $create['json']['expirationDate'])->toContain('2030-12-31');

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        expect((string) $get['json']['expirationDate'])->toContain('2030-12-31');
        // Distinct from the rule-level toDate, which was never set.
        expect($get['json']['toDate'] ?? null)->toBeNull();

        $update = apiPut("/api/rest/v2/coupons/{$id}", [
            'expirationDate' => '',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['expirationDate'] ?? null)->toBeNull();

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps a custom expirationDate across updates that do not touch it', function (): void {
        $token = adminToken();
        $code = 'PestExpK' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 15,
            'expirationDate' => '2030-12-31',
        ], $token);
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        // Saving the rule re-syncs the coupon expiration to the rule's (unset)
        // toDate, so an unrelated update must not wipe the custom date.
        $update = apiPut("/api/rest/v2/coupons/{$id}", [
            'discountAmount' => 20,
        ], $token);
        expect($update['status'])->toBe(200);
        expect((string) ($update['json']['expirationDate'] ?? ''))->toContain('2030-12-31');

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect((string) ($get['json']['expirationDate'] ?? ''))->toContain('2030-12-31');

        // An explicit toDate change re-syncs the coupon to the rule window.
        $sync = apiPut("/api/rest/v2/coupons/{$id}", [
            'toDate' => '2031-06-30',
        ], $token);
        expect($sync['status'])->toBe(200);
        expect((string) ($sync['json']['expirationDate'] ?? ''))->toContain('2031-06-30');

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});

describe('Coupon rule-level knobs', function (): void {

    it('round-trips buy-X-get-Y quantities and processing flags', function (): void {
        $token = adminToken();
        $code = 'PestKnob' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'buy_x_get_y',
            'discountAmount' => 1,
            'discountQty' => 2,
            'discountStep' => 3,
            'sortOrder' => 5,
            'stopRulesProcessing' => true,
            'applyToShipping' => true,
            'simpleFreeShipping' => 1,
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        expect((float) $get['json']['discountQty'])->toBe(2.0);
        expect($get['json']['discountStep'])->toBe(3);
        expect($get['json']['sortOrder'])->toBe(5);
        expect($get['json']['stopRulesProcessing'])->toBeTrue();
        expect($get['json']['applyToShipping'])->toBeTrue();
        expect($get['json']['simpleFreeShipping'])->toBe(1);

        // Read-only coupon metadata is exposed.
        expect($get['json'])->toHaveKey('isPrimary');
        expect($get['json'])->toHaveKey('createdAt');

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});

describe('Coupon minimum subtotal', function (): void {

    it('saves, changes and removes the minimum subtotal condition', function (): void {
        $token = adminToken();
        $code = 'PestMinimum' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'fixed',
            'discountAmount' => 5,
            'minimumSubtotal' => 50,
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect(apiGet("/api/rest/v2/coupons/{$id}", $token)['json']['minimumSubtotal'])->toEqual(50);

        // The stored condition must not come back next to the new one.
        $update = apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 75], $token);
        expect($update['status'])->toBe(200);
        expect(apiGet("/api/rest/v2/coupons/{$id}", $token)['json']['minimumSubtotal'])->toEqual(75);

        $remove = apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 0], $token);
        expect($remove['status'])->toBe(200);
        expect(apiGet("/api/rest/v2/coupons/{$id}", $token)['json']['minimumSubtotal'] ?? null)->toBeNull();

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});

function couponTestRuleId(int $couponId): int
{
    return (int) apiGet("/api/rest/v2/coupons/{$couponId}", adminToken())['json']['ruleId'];
}

describe('Coupon and cart price rule', function (): void {

    it('changes the minimum subtotal in place and keeps the other conditions', function (): void {
        $token = adminToken();
        $create = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestRich' . substr(uniqid(), -6),
            'discountType' => 'fixed',
            'discountAmount' => 5,
            'minimumSubtotal' => 30,
        ], $token);
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        $ruleId = couponTestRuleId($id);

        $found = [
            'type' => 'salesrule/rule_condition_product_found', 'aggregator' => 'all', 'value' => true,
            'conditions' => [
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '2'],
            ],
        ];
        $subtotal = ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '30'];
        $tree = apiPut("/api/rest/v2/cart-price-rules/{$ruleId}", ['conditions' => [
            'type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => true,
            'conditions' => [$found, $subtotal],
        ]], $token);
        expect($tree['status'])->toBe(200);

        $types = fn(): array => array_column(apiGet("/api/rest/v2/cart-price-rules/{$ruleId}", $token)['json']['conditions']['conditions'], 'value', 'type');

        expect(apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 75], $token)['status'])->toBe(200);
        $afterChange = apiGet("/api/rest/v2/cart-price-rules/{$ruleId}", $token)['json']['conditions']['conditions'];
        expect(array_column($afterChange, 'type'))->toBe(['salesrule/rule_condition_product_found', 'salesrule/rule_condition_address'])
            ->and($afterChange[1]['value'])->toBe('75')
            ->and(apiGet("/api/rest/v2/coupons/{$id}", $token)['json']['minimumSubtotal'])->toEqual(75);

        expect(apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 0], $token)['status'])->toBe(200)
            ->and(array_keys($types()))->toBe(['salesrule/rule_condition_product_found']);

        expect(apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 40], $token)['status'])->toBe(200)
            ->and(array_keys($types()))->toBe(['salesrule/rule_condition_product_found', 'salesrule/rule_condition_address']);

        // With ANY at the root, a minimum would no longer be a minimum
        expect(apiPut("/api/rest/v2/cart-price-rules/{$ruleId}", ['conditions' => [
            'type' => 'salesrule/rule_condition_combine', 'aggregator' => 'any', 'value' => true,
            'conditions' => [$found],
        ]], $token)['status'])->toBe(200);
        expect(apiPut("/api/rest/v2/coupons/{$id}", ['minimumSubtotal' => 60], $token)['status'])->toBe(409);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('deletes only a generated coupon and keeps its rule', function (): void {
        $token = adminToken();
        $rule = apiPost('/api/rest/v2/cart-price-rules', [
            'name' => 'Pest generated delete ' . substr(uniqid(), -6),
            'websiteIds' => [1],
            'customerGroupIds' => [1],
            'couponType' => 'auto',
        ], $token);
        expect($rule['status'])->toBe(201);
        $ruleId = (int) $rule['json']['id'];
        $coupons = apiPost("/api/rest/v2/cart-price-rules/{$ruleId}/coupons/generate", ['qty' => 2], $token)['json']['coupons'];

        expect(apiDelete("/api/rest/v2/coupons/{$coupons[0]['id']}", $token)['status'])->toBeIn([200, 204]);
        $after = apiGet("/api/rest/v2/cart-price-rules/{$ruleId}", $token);
        expect($after['status'])->toBe(200)
            ->and($after['json']['couponCount'])->toBe(1)
            ->and(apiGet("/api/rest/v2/coupons/{$coupons[1]['id']}", $token)['status'])->toBe(200);

        expect(apiDelete("/api/rest/v2/cart-price-rules/{$ruleId}", $token)['status'])->toBe(204);
    });

    it('deletes the rule with its primary coupon', function (): void {
        $token = adminToken();
        $create = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestPrimary' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
        ], $token);
        $id = (int) $create['json']['id'];
        $ruleId = couponTestRuleId($id);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204])
            ->and(apiGet("/api/rest/v2/cart-price-rules/{$ruleId}", $token)['status'])->toBe(404);
    });

    it('shows the rule of a coupon in the cart price rules', function (): void {
        $token = adminToken();
        $code = 'PestShared' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 15,
            'description' => 'Pest shared rule',
            'websiteIds' => [1],
            'customerGroupIds' => [1],
            'minimumSubtotal' => 20,
        ], $token);
        $id = (int) $create['json']['id'];
        $ruleId = couponTestRuleId($id);

        $rule = apiGet("/api/rest/v2/cart-price-rules/{$ruleId}", $token);
        expect($rule['status'])->toBe(200)
            ->and($rule['json']['couponType'])->toBe('specific')
            ->and($rule['json']['couponCode'])->toBe($code)
            ->and($rule['json']['primaryCouponId'])->toBe($id)
            ->and($rule['json']['simpleAction'])->toBe('by_percent')
            ->and((float) $rule['json']['discountAmount'])->toBe(15.0)
            ->and($rule['json']['conditions']['conditions'][0]['value'])->toBe('20');

        $list = apiGet('/api/rest/v2/cart-price-rules?code=' . $code, $token);
        expect(array_column($list['json']['member'] ?? [], 'id'))->toBe([$ruleId]);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});
