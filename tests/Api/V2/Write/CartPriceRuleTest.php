<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 cart price rules: fields, coupons of the rule, store labels, lists and access.
 *
 * @group write
 */

const CPRW_PATH = '/api/rest/v2/cart-price-rules';

afterAll(function (): void {
    foreach (cprwRuleIds() as $ruleId) {
        $rule = Mage::getModel('salesrule/rule')->load($ruleId);
        if ($rule->getId()) {
            $rule->delete();
        }
    }
    cleanupTestData();
});

function &cprwRuleIds(): array
{
    static $ids = [];
    return $ids;
}

function cprwCreate(array $fields = [], #[\SensitiveParameter]
?string $token = null): array
{
    $response = apiPost(CPRW_PATH, $fields + [
        'name' => 'Pest rule ' . substr(uniqid(), -6),
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
    ], $token ?? adminToken());
    if (isset($response['json']['id'])) {
        cprwRuleIds()[] = (int) $response['json']['id'];
    }
    return $response;
}

function cprwFields(array $response): array
{
    return array_column($response['json']['details']['errors'] ?? [], 'field');
}

function cprwCode(string $prefix = 'PESTCPR'): string
{
    return $prefix . strtoupper(substr(uniqid(), -7));
}

function cprwMembers(array $response): array
{
    return $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];
}

describe('Cart price rule fields', function (): void {

    it('creates, reads, updates and deletes a rule', function (): void {
        $token = adminToken();
        $code = cprwCode();
        $create = cprwCreate([
            'name' => 'Pest full rule',
            'description' => 'All fields',
            'isActive' => true,
            'websiteIds' => [1, 2],
            'customerGroupIds' => [1, 2],
            'couponType' => 'specific',
            'couponCode' => $code,
            'usesPerCoupon' => 5,
            'usesPerCustomer' => 2,
            'fromDate' => '2030-01-01',
            'toDate' => '2030-12-31',
            'sortOrder' => 3,
            'stopRulesProcessing' => true,
            'isRss' => true,
            'simpleAction' => 'buy_x_get_y',
            'discountAmount' => 1,
            'discountQty' => 4,
            'discountStep' => 3,
            'applyToShipping' => true,
            'simpleFreeShipping' => 2,
        ], $token);

        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        $get = apiGet(CPRW_PATH . "/{$id}", $token);
        expect($get['status'])->toBe(200);
        $json = $get['json'];
        expect($json['name'])->toBe('Pest full rule')
            ->and($json['description'])->toBe('All fields')
            ->and($json['isActive'])->toBeTrue()
            ->and($json['websiteIds'])->toBe([1, 2])
            ->and($json['customerGroupIds'])->toBe([1, 2])
            ->and($json['couponType'])->toBe('specific')
            ->and($json['couponCode'])->toBe($code)
            ->and($json['usesPerCoupon'])->toBe(5)
            ->and($json['usesPerCustomer'])->toBe(2)
            ->and($json['fromDate'])->toBe('2030-01-01')
            ->and($json['toDate'])->toBe('2030-12-31')
            ->and($json['sortOrder'])->toBe(3)
            ->and($json['stopRulesProcessing'])->toBeTrue()
            ->and($json['isRss'])->toBeTrue()
            ->and($json['simpleAction'])->toBe('buy_x_get_y')
            ->and((float) $json['discountAmount'])->toBe(1.0)
            ->and((float) $json['discountQty'])->toBe(4.0)
            ->and($json['discountStep'])->toBe(3)
            ->and($json['applyToShipping'])->toBeTrue()
            ->and($json['simpleFreeShipping'])->toBe(2)
            ->and($json['couponCount'])->toBe(1)
            ->and($json['primaryCouponId'])->toBeInt()
            ->and($json['timesUsed'])->toBe(0)
            ->and($json['conditions']['type'])->toBe('salesrule/rule_condition_combine')
            ->and($json['actions']['type'])->toBe('salesrule/rule_condition_product_combine');

        // Only the fields in the body change
        $update = apiPut(CPRW_PATH . "/{$id}", ['name' => 'Pest full rule 2', 'discountStep' => 0], $token);
        expect($update['status'])->toBe(200)
            ->and($update['json']['name'])->toBe('Pest full rule 2')
            ->and($update['json']['discountStep'])->toBe(0)
            ->and($update['json']['description'])->toBe('All fields')
            ->and($update['json']['couponCode'])->toBe($code)
            ->and($update['json']['websiteIds'])->toBe([1, 2]);

        // A client can send back what it read
        $echo = apiPut(CPRW_PATH . "/{$id}", $update['json'], $token);
        expect($echo['status'])->toBe(200);

        expect(apiDelete(CPRW_PATH . "/{$id}", $token)['status'])->toBe(204)
            ->and(apiGet(CPRW_PATH . "/{$id}", $token)['status'])->toBe(404);
    });

    it('lists every missing and wrong field in one answer', function (): void {
        $missing = apiPost(CPRW_PATH, ['description' => 'x'], adminToken());
        expect($missing['status'])->toBe(400)
            ->and($missing['json']['error'])->toBe('validation_error')
            ->and(cprwFields($missing))->toBe(['name', 'websiteIds', 'customerGroupIds']);

        $wrong = cprwCreate([
            'isActive' => 'yes',
            'usesPerCoupon' => -1,
            'fromDate' => '31/12/2030',
            'simpleAction' => 'to_percent',
            'unknownField' => 1,
            'customerGroupIds' => [999999],
        ]);
        expect($wrong['status'])->toBe(400)
            ->and(cprwFields($wrong))->toEqualCanonicalizing(['unknownField', 'isActive', 'usesPerCoupon', 'customerGroupIds', 'fromDate', 'simpleAction']);
    });

    it('rejects a percent discount above 100 and a start date after the end date', function (): void {
        $percent = cprwCreate(['simpleAction' => 'by_percent', 'discountAmount' => 150]);
        expect($percent['status'])->toBe(400)
            ->and(cprwFields($percent))->toBe(['discountAmount']);

        $fixed = cprwCreate(['simpleAction' => 'by_fixed', 'discountAmount' => 150]);
        expect($fixed['status'])->toBe(201);

        // The check uses the stored action when the body changes only the amount
        $toPercent = apiPut(CPRW_PATH . "/{$fixed['json']['id']}", ['simpleAction' => 'by_percent'], adminToken());
        expect($toPercent['status'])->toBe(400)
            ->and(cprwFields($toPercent))->toBe(['discountAmount']);

        $dates = cprwCreate(['fromDate' => '2030-02-01', 'toDate' => '2030-01-31']);
        expect($dates['status'])->toBe(400)
            ->and(cprwFields($dates))->toBe(['toDate']);
    });

    it('changes the coupon type and keeps the codes unique', function (): void {
        $token = adminToken();
        $code = cprwCode();
        $rule = cprwCreate(['couponType' => 'specific', 'couponCode' => $code]);
        $id = $rule['json']['id'];
        expect($rule['json']['primaryCouponId'])->toBeInt();

        $duplicate = cprwCreate(['couponType' => 'specific', 'couponCode' => strtolower($code)]);
        expect($duplicate['status'])->toBe(400)
            ->and(cprwFields($duplicate))->toBe(['couponCode']);

        expect(cprwFields(cprwCreate(['couponType' => 'specific'])))->toBe(['couponCode'])
            ->and(cprwFields(cprwCreate(['couponType' => 'specific', 'couponCode' => 'bad code!'])))->toBe(['couponCode'])
            ->and(cprwFields(cprwCreate(['couponType' => 'none', 'couponCode' => cprwCode()])))->toBe(['couponCode']);

        $auto = apiPut(CPRW_PATH . "/{$id}", ['couponType' => 'auto'], $token);
        expect($auto['status'])->toBe(200)
            ->and($auto['json']['couponType'])->toBe('auto')
            ->and($auto['json']['couponCode'] ?? null)->toBeNull()
            ->and($auto['json']['primaryCouponId'] ?? null)->toBeNull()
            ->and($auto['json']['couponCount'])->toBe(0);

        $newCode = cprwCode();
        $specific = apiPut(CPRW_PATH . "/{$id}", ['couponType' => 'specific', 'couponCode' => $newCode], $token);
        expect($specific['status'])->toBe(200)
            ->and($specific['json']['couponCode'])->toBe($newCode);

        // The own code does not count as a duplicate
        $same = apiPut(CPRW_PATH . "/{$id}", ['couponCode' => $newCode], $token);
        expect($same['status'])->toBe(200);

        $none = apiPut(CPRW_PATH . "/{$id}", ['couponType' => 'none'], $token);
        expect($none['json']['couponType'])->toBe('none')
            ->and($none['json']['primaryCouponId'] ?? null)->toBeNull();
    });

    it('keeps a date set on the primary coupon when an update leaves out toDate', function (): void {
        $token = adminToken();
        $rule = cprwCreate(['couponType' => 'specific', 'couponCode' => cprwCode()]);
        $couponId = $rule['json']['primaryCouponId'];

        expect(apiPut("/api/rest/v2/coupons/{$couponId}", ['expirationDate' => '2031-05-05'], $token)['status'])->toBe(200);
        expect(apiPut(CPRW_PATH . "/{$rule['json']['id']}", ['name' => 'Pest expiration kept'], $token)['status'])->toBe(200);
        expect((string) apiGet("/api/rest/v2/coupons/{$couponId}", $token)['json']['expirationDate'])->toContain('2031-05-05');
    });

    it('gives a new primary coupon the end date of the rule', function (string $couponType): void {
        $token = adminToken();
        $rule = cprwCreate(['couponType' => $couponType, 'toDate' => '2030-12-31']);
        $update = apiPut(CPRW_PATH . "/{$rule['json']['id']}", ['couponType' => 'specific', 'couponCode' => cprwCode()], $token);

        expect($update['status'])->toBe(200)
            ->and($update['json']['primaryCouponId'])->toBeInt()
            ->and((string) apiGet("/api/rest/v2/coupons/{$update['json']['primaryCouponId']}", $token)['json']['expirationDate'])->toContain('2030-12-31');
    })->with(['none', 'auto']);

    it('replaces the store labels', function (): void {
        $token = adminToken();
        $rule = cprwCreate(['storeLabels' => [['storeId' => 0, 'label' => 'Default'], ['storeId' => 1, 'label' => 'English']]]);
        expect($rule['json']['storeLabels'])->toBe([['storeId' => 0, 'label' => 'Default'], ['storeId' => 1, 'label' => 'English']]);
        $id = $rule['json']['id'];

        $replaced = apiPut(CPRW_PATH . "/{$id}", ['storeLabels' => [['storeId' => 1, 'label' => 'Only English']]], $token);
        expect($replaced['json']['storeLabels'])->toBe([['storeId' => 1, 'label' => 'Only English']]);

        $bad = apiPut(CPRW_PATH . "/{$id}", ['storeLabels' => [['storeId' => 999999, 'label' => 'x'], ['storeId' => 1]]], $token);
        expect($bad['status'])->toBe(400)
            ->and(cprwFields($bad))->toBe(['storeLabels[0].storeId', 'storeLabels[1].label']);
    });

});

describe('Cart price rule lists', function (): void {

    it('filters, sorts and pages the rules', function (): void {
        $token = adminToken();
        $tag = 'PestList' . substr(uniqid(), -6);
        $code = cprwCode();
        $alpha = cprwCreate(['name' => "{$tag} alpha", 'isActive' => true, 'couponType' => 'specific', 'couponCode' => $code, 'fromDate' => '2030-01-01', 'toDate' => '2030-01-31']);
        $beta = cprwCreate(['name' => "{$tag} beta", 'websiteIds' => [2], 'customerGroupIds' => [2], 'couponType' => 'auto', 'conditions' => [
            'type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => true, 'conditions' => [[
                'type' => 'salesrule/rule_condition_product_found', 'aggregator' => 'all', 'value' => true, 'conditions' => [
                    ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '2'],
                ],
            ]],
        ]]);
        $gamma = cprwCreate(['name' => "{$tag} gamma", 'description' => 'third one']);
        expect([$alpha['status'], $beta['status'], $gamma['status']])->toBe([201, 201, 201]);

        $names = fn(array $response): array => array_column(cprwMembers($response), 'name');
        $list = fn(string $query): array => apiGet(CPRW_PATH . "?search={$tag}&{$query}", $token);

        $all = $list('sort=name&order=asc');
        expect($all['status'])->toBe(200)
            ->and($names($all))->toBe(["{$tag} alpha", "{$tag} beta", "{$tag} gamma"])
            ->and($all['json']['totalItems'])->toBe(3)
            ->and(cprwMembers($all)[0])->not->toHaveKey('conditions');

        expect($names($list('sort=name&order=desc')))->toBe(["{$tag} gamma", "{$tag} beta", "{$tag} alpha"])
            ->and($names($list('sort=name&itemsPerPage=2&page=2')))->toBe(["{$tag} gamma"])
            ->and($names($list('isActive=true')))->toBe(["{$tag} alpha"])
            ->and($names($list('couponType=auto')))->toBe(["{$tag} beta"])
            ->and($names($list('couponType=none')))->toBe(["{$tag} gamma"])
            ->and($names($list('websiteId=2')))->toBe(["{$tag} beta"])
            ->and($names($list('customerGroupId=2')))->toBe(["{$tag} beta"])
            ->and($names($list('activeOn=2030-01-15&sort=name')))->toBe(["{$tag} alpha", "{$tag} beta", "{$tag} gamma"])
            ->and($names($list('activeOn=2030-02-15&sort=name')))->toBe(["{$tag} beta", "{$tag} gamma"])
            ->and($names($list("code={$code}")))->toBe(["{$tag} alpha"])
            ->and($names($list('usesAttribute=quote_item_qty')))->toBe(["{$tag} beta"]);

        expect($names(apiGet(CPRW_PATH . '?search=' . urlencode("{$tag} third"), $token)))->toBe(["{$tag} gamma"])
            ->and($names(apiGet(CPRW_PATH . "?search={$code}", $token)))->toBe(["{$tag} alpha"]);

        expect($list('sort=discount')['status'])->toBe(400)
            ->and($list('couponType=other')['status'])->toBe(400)
            ->and($list('activeOn=tomorrow')['status'])->toBe(400);
    });

});

describe('Cart price rule access', function (): void {

    it('checks the grants of each operation', function (): void {
        $rule = cprwCreate();
        $id = $rule['json']['id'];

        expect(apiGet(CPRW_PATH . "/{$id}")['status'])->toBe(401)
            ->and(apiGet(CPRW_PATH . "/{$id}", customerToken())['status'])->toBe(403)
            ->and(apiGet(CPRW_PATH, customerToken())['status'])->toBe(403);

        $reader = serviceToken(['cart-price-rules/read']);
        expect(apiGet(CPRW_PATH . "/{$id}", $reader)['status'])->toBe(200)
            ->and(apiPut(CPRW_PATH . "/{$id}", ['name' => 'x'], $reader)['status'])->toBe(403)
            ->and(apiDelete(CPRW_PATH . "/{$id}", $reader)['status'])->toBe(403)
            ->and(cprwCreate([], $reader)['status'])->toBe(403);

        expect(cprwCreate([], serviceToken(['cart-price-rules/create']))['status'])->toBe(201)
            ->and(apiPut(CPRW_PATH . "/{$id}", ['name' => 'Pest writer'], serviceToken(['cart-price-rules/write']))['status'])->toBe(200)
            ->and(apiDelete(CPRW_PATH . "/{$id}", serviceToken(['cart-price-rules/delete']))['status'])->toBe(204);
    });

    it('denies an admin whose role does not include cart price rules', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_cpr_acl_' . substr(uniqid(), -6));
        expect(apiGet(CPRW_PATH, $token)['status'])->toBe(403);
    });

    it('limits a restricted token to the rules of its websites', function (): void {
        $tag = 'PestScope' . substr(uniqid(), -6);
        $own = cprwCreate(['name' => "{$tag} own", 'websiteIds' => [1]]);
        $foreign = cprwCreate(['name' => "{$tag} foreign", 'websiteIds' => [2]]);
        $shared = cprwCreate(['name' => "{$tag} shared", 'websiteIds' => [1, 2], 'storeLabels' => [['storeId' => 0, 'label' => 'Default']]]);

        $restricted = serviceToken(['cart-price-rules/all'], [1]);
        $names = array_column(cprwMembers(apiGet(CPRW_PATH . "?search={$tag}&sort=name", $restricted)), 'name');
        expect($names)->toBe(["{$tag} own", "{$tag} shared"]);

        expect(apiGet(CPRW_PATH . "/{$foreign['json']['id']}", $restricted)['status'])->toBe(404)
            ->and(apiPut(CPRW_PATH . "/{$foreign['json']['id']}", ['name' => 'x'], $restricted)['status'])->toBe(404)
            ->and(apiGet(CPRW_PATH . "/{$shared['json']['id']}", $restricted)['status'])->toBe(200)
            ->and(apiPut(CPRW_PATH . "/{$shared['json']['id']}", ['name' => 'x'], $restricted)['status'])->toBe(403)
            ->and(apiDelete(CPRW_PATH . "/{$shared['json']['id']}", $restricted)['status'])->toBe(403)
            ->and(cprwCreate(['websiteIds' => [2]], $restricted)['status'])->toBe(403)
            ->and(apiPut(CPRW_PATH . "/{$own['json']['id']}", ['websiteIds' => [1, 2]], $restricted)['status'])->toBe(403);

        // Labels of stores outside the allowlist stay, and the token cannot write them
        $labelled = cprwCreate(['name' => "{$tag} labels", 'storeLabels' => [['storeId' => 0, 'label' => 'Default'], ['storeId' => 1, 'label' => 'Store one']]]);
        $id = $labelled['json']['id'];
        $update = apiPut(CPRW_PATH . "/{$id}", ['storeLabels' => [['storeId' => 1, 'label' => 'Changed']]], $restricted);
        expect($update['status'])->toBe(200)
            ->and($update['json']['storeLabels'])->toBe([['storeId' => 0, 'label' => 'Default'], ['storeId' => 1, 'label' => 'Changed']])
            ->and(apiPut(CPRW_PATH . "/{$id}", ['storeLabels' => [['storeId' => 0, 'label' => 'x']]], $restricted)['status'])->toBe(403);
    });

});
