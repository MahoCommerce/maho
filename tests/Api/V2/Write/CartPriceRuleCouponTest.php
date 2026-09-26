<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 coupons of cart price rules: generation, lists and deletes.
 *
 * @group write
 */

const CPRCP_PATH = '/api/rest/v2/cart-price-rules';

afterAll(function (): void {
    foreach (cprcpRuleIds() as $ruleId) {
        $rule = Mage::getModel('salesrule/rule')->load($ruleId);
        if ($rule->getId()) {
            $rule->delete();
        }
    }
    cleanupTestData();
});

function &cprcpRuleIds(): array
{
    static $ids = [];
    return $ids;
}

function cprcpRule(array $fields = []): int
{
    $response = apiPost(CPRCP_PATH, $fields + [
        'name' => 'Pest coupon rule ' . substr(uniqid(), -6),
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
        'couponType' => 'auto',
    ], adminToken());
    expect($response['status'])->toBe(201);
    cprcpRuleIds()[] = (int) $response['json']['id'];
    return (int) $response['json']['id'];
}

function cprcpGenerate(int $ruleId, array $body, ?string $token = null): array
{
    return apiPost(CPRCP_PATH . "/{$ruleId}/coupons/generate", $body, $token ?? adminToken());
}

function cprcpMembers(array $response): array
{
    return $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];
}

describe('Cart price rule coupon generation', function (): void {

    it('generates coupons with the requested format', function (): void {
        $ruleId = cprcpRule(['usesPerCoupon' => 3, 'usesPerCustomer' => 1, 'toDate' => '2031-01-31']);

        $response = cprcpGenerate($ruleId, ['qty' => 5, 'length' => 6, 'format' => 'num', 'prefix' => 'PX-', 'suffix' => '-SX', 'dash' => 3]);

        expect($response['status'])->toBe(201)
            ->and($response['json']['generatedCount'])->toBe(5)
            ->and($response['json']['coupons'])->toHaveCount(5);
        foreach ($response['json']['coupons'] as $coupon) {
            expect($coupon['code'])->toMatch('/^PX-\d{3}-\d{3}-SX$/')
                ->and($coupon['isPrimary'])->toBeFalse()
                ->and($coupon['type'])->toBe(1)
                ->and($coupon['usageLimit'])->toBe(3)
                ->and($coupon['usagePerCustomer'])->toBe(1)
                ->and((string) $coupon['expirationDate'])->toContain('2031-01-31');
        }

        $rule = apiGet(CPRCP_PATH . "/{$ruleId}", adminToken());
        expect($rule['json']['couponCount'])->toBe(5);
    });

    it('uses the configured defaults for the settings that the body leaves out', function (): void {
        $ruleId = cprcpRule();
        $response = cprcpGenerate($ruleId, ['qty' => 1]);

        expect($response['status'])->toBe(201)
            ->and($response['json']['coupons'][0]['code'])->toMatch('/^[A-Z0-9-]+$/');
    });

    it('generates only for a rule with automatic coupons', function (): void {
        $specific = cprcpRule(['couponType' => 'specific', 'couponCode' => 'PESTGEN' . strtoupper(substr(uniqid(), -6))]);
        $none = cprcpRule(['couponType' => 'none']);

        expect(cprcpGenerate($specific, ['qty' => 1])['status'])->toBe(409)
            ->and(cprcpGenerate($none, ['qty' => 1])['status'])->toBe(409);
    });

    it('limits the settings', function (array $body, string $field): void {
        $ruleId = cprcpRule();
        $response = cprcpGenerate($ruleId, $body);

        expect($response['status'])->toBe(400)
            ->and($response['json']['details']['field'])->toBe($field);
    })->with([
        'no quantity' => [[], 'qty'],
        'too many' => [['qty' => 1001], 'qty'],
        'no length' => [['qty' => 1, 'length' => 0], 'length'],
        'too long' => [['qty' => 1, 'length' => 33], 'length'],
        'unknown format' => [['qty' => 1, 'format' => 'emoji'], 'format'],
        'prefix with a space' => [['qty' => 1, 'prefix' => 'A B'], 'prefix'],
        'long suffix' => [['qty' => 1, 'suffix' => str_repeat('S', 33)], 'suffix'],
        'dash after the end' => [['qty' => 1, 'length' => 4, 'dash' => 5], 'dash'],
        'unknown field' => [['qty' => 1, 'count' => 2], 'count'],
    ]);

});

describe('Cart price rule coupon lists and deletes', function (): void {

    it('lists the coupons of a rule with filters and pages', function (): void {
        $ruleId = cprcpRule();
        $generated = cprcpGenerate($ruleId, ['qty' => 4, 'length' => 8, 'prefix' => 'LST'])['json']['coupons'];
        $usedId = $generated[0]['id'];
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update($resource->getTableName('salesrule/coupon'), ['times_used' => 2], ['coupon_id = ?' => $usedId]);

        $all = apiGet(CPRCP_PATH . "/{$ruleId}/coupons", adminToken());
        expect($all['status'])->toBe(200)
            ->and($all['json']['totalItems'])->toBe(4)
            ->and(array_column(cprcpMembers($all), 'id'))->toBe(array_column($generated, 'id'));

        $page = apiGet(CPRCP_PATH . "/{$ruleId}/coupons?itemsPerPage=3&page=2", adminToken());
        expect(cprcpMembers($page))->toHaveCount(1);

        $used = apiGet(CPRCP_PATH . "/{$ruleId}/coupons?isUsed=true", adminToken());
        expect(array_column(cprcpMembers($used), 'id'))->toBe([$usedId])
            ->and(cprcpMembers($used)[0]['timesUsed'])->toBe(2);
        expect(cprcpMembers(apiGet(CPRCP_PATH . "/{$ruleId}/coupons?isUsed=false", adminToken())))->toHaveCount(3);

        $search = apiGet(CPRCP_PATH . "/{$ruleId}/coupons?search=" . $generated[1]['code'], adminToken());
        expect(array_column(cprcpMembers($search), 'id'))->toBe([$generated[1]['id']]);

        $specific = cprcpRule(['couponType' => 'specific', 'couponCode' => 'PESTLST' . strtoupper(substr(uniqid(), -6))]);
        $primary = apiGet(CPRCP_PATH . "/{$specific}/coupons?isPrimary=true", adminToken());
        expect(cprcpMembers($primary))->toHaveCount(1)
            ->and(cprcpMembers($primary)[0]['isPrimary'])->toBeTrue();

        expect(apiGet(CPRCP_PATH . '/999999999/coupons', adminToken())['status'])->toBe(404);
    });

    it('deletes one generated coupon and keeps the primary coupon', function (): void {
        $ruleId = cprcpRule();
        $coupons = cprcpGenerate($ruleId, ['qty' => 2])['json']['coupons'];

        expect(apiDelete(CPRCP_PATH . "/{$ruleId}/coupons/{$coupons[0]['id']}", adminToken())['status'])->toBe(204)
            ->and(cprcpMembers(apiGet(CPRCP_PATH . "/{$ruleId}/coupons", adminToken())))->toHaveCount(1)
            ->and(apiDelete(CPRCP_PATH . "/{$ruleId}/coupons/{$coupons[0]['id']}", adminToken())['status'])->toBe(404);

        $other = cprcpRule();
        expect(apiDelete(CPRCP_PATH . "/{$other}/coupons/{$coupons[1]['id']}", adminToken())['status'])->toBe(404);

        $specific = cprcpRule(['couponType' => 'specific', 'couponCode' => 'PESTDEL' . strtoupper(substr(uniqid(), -6))]);
        $primaryId = apiGet(CPRCP_PATH . "/{$specific}", adminToken())['json']['primaryCouponId'];
        expect(apiDelete(CPRCP_PATH . "/{$specific}/coupons/{$primaryId}", adminToken())['status'])->toBe(409)
            ->and(apiGet(CPRCP_PATH . "/{$specific}", adminToken())['json']['primaryCouponId'])->toBe($primaryId);
    });

    it('deletes many generated coupons of the rule and skips other IDs', function (): void {
        $ruleId = cprcpRule();
        $coupons = cprcpGenerate($ruleId, ['qty' => 3])['json']['coupons'];
        $otherRule = cprcpRule();
        $otherCoupon = cprcpGenerate($otherRule, ['qty' => 1])['json']['coupons'][0];

        $response = apiPost(CPRCP_PATH . "/{$ruleId}/coupons/mass-delete", [
            'ids' => [$coupons[0]['id'], $coupons[1]['id'], $otherCoupon['id']],
        ], adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['deletedCount'])->toBe(2)
            ->and(array_column(cprcpMembers(apiGet(CPRCP_PATH . "/{$ruleId}/coupons", adminToken())), 'id'))->toBe([$coupons[2]['id']])
            ->and(cprcpMembers(apiGet(CPRCP_PATH . "/{$otherRule}/coupons", adminToken())))->toHaveCount(1);

        expect(apiPost(CPRCP_PATH . "/{$ruleId}/coupons/mass-delete", ['ids' => []], adminToken())['status'])->toBe(400)
            ->and(apiPost(CPRCP_PATH . "/{$ruleId}/coupons/mass-delete", ['ids' => range(1, 1001)], adminToken())['status'])->toBe(400)
            ->and(apiPost(CPRCP_PATH . "/{$ruleId}/coupons/mass-delete", ['ids' => ['x']], adminToken())['status'])->toBe(400);
    });

});

describe('Cart price rule coupon access', function (): void {

    it('checks the grants and the websites of the token', function (): void {
        $ruleId = cprcpRule();
        $coupon = cprcpGenerate($ruleId, ['qty' => 1])['json']['coupons'][0];

        $reader = serviceToken(['cart-price-rules/read']);
        expect(apiGet(CPRCP_PATH . "/{$ruleId}/coupons", $reader)['status'])->toBe(200)
            ->and(cprcpGenerate($ruleId, ['qty' => 1], $reader)['status'])->toBe(403)
            ->and(apiDelete(CPRCP_PATH . "/{$ruleId}/coupons/{$coupon['id']}", $reader)['status'])->toBe(403)
            ->and(apiGet(CPRCP_PATH . "/{$ruleId}/coupons", customerToken())['status'])->toBe(403);

        expect(cprcpGenerate($ruleId, ['qty' => 1], serviceToken(['cart-price-rules/write']))['status'])->toBe(201)
            ->and(apiDelete(CPRCP_PATH . "/{$ruleId}/coupons/{$coupon['id']}", serviceToken(['cart-price-rules/delete']))['status'])->toBe(204);

        $foreign = cprcpRule(['websiteIds' => [2]]);
        $shared = cprcpRule(['websiteIds' => [1, 2]]);
        $restricted = serviceToken(['cart-price-rules/all'], [1]);
        expect(apiGet(CPRCP_PATH . "/{$foreign}/coupons", $restricted)['status'])->toBe(404)
            ->and(apiGet(CPRCP_PATH . "/{$shared}/coupons", $restricted)['status'])->toBe(200)
            ->and(cprcpGenerate($shared, ['qty' => 1], $restricted)['status'])->toBe(403)
            ->and(cprcpGenerate($ruleId, ['qty' => 1], $restricted)['status'])->toBe(201);
    });

});
