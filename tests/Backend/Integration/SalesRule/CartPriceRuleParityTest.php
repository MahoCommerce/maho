<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\SalesRule\Api\CartPriceRuleCouponProcessor;
use Mage\SalesRule\Api\CartPriceRuleProcessor;
use Mage\SalesRule\Api\CartPriceRuleProvider;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page and the API save cart price rules and generate coupons through Mage_SalesRule_Service_Rule (#1348).
 * Each test gives the same input to both transports and compares the outcome.
 */

const CART_RULE_PARITY_NAME = 'Pest cart rule parity';

/**
 * @param array<string, mixed> $fields the fields of the admin form, on top of a valid rule
 * @return array{success: list<string>, errors: list<string>}
 */
function cartRuleParityAdmin(array $fields): array
{
    Mage::unregister('current_promo_quote_rule');
    return AdminApiParity::admin(Mage_Adminhtml_Promo_QuoteController::class, 'save', array_filter($fields + [
        'name' => CART_RULE_PARITY_NAME,
        'is_active' => '1',
        'website_ids' => ['1'],
        'customer_group_ids' => ['0', '1'],
        'coupon_type' => (string) Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
        'simple_action' => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
        'discount_amount' => '10',
        'rule' => ['conditions' => ['1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1']]],
    ], fn($value) => $value !== null));
}

/**
 * @param array<string, mixed> $body the JSON body, on top of a valid rule
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function cartRuleParityApi(array $body): array
{
    $request = AdminApiParity::jsonRequest('POST', '/api/rest/v2/cart-price-rules', array_filter($body + [
        'name' => CART_RULE_PARITY_NAME,
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
        'discountAmount' => 10,
    ], fn($value) => $value !== null));
    $security = AdminApiParity::adminSecurity();
    return AdminApiParity::api($request, fn() => new CartPriceRuleProcessor($security, new CartPriceRuleProvider($security))
        ->process(null, new Post(), [], ['request' => $request]));
}

function cartRuleParityCount(): int
{
    return Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', CART_RULE_PARITY_NAME)->getSize();
}

function cartRuleParityCleanup(): void
{
    foreach (Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', CART_RULE_PARITY_NAME) as $rule) {
        $rule->delete();
    }
}

function cartRuleParityCode(): string
{
    return 'PESTPARITY' . strtoupper(substr(uniqid(), -6));
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    cartRuleParityCleanup();
});

afterEach(function (): void {
    cartRuleParityCleanup();
});

it('saves the same rule on both transports', function (): void {
    expect(cartRuleParityAdmin(['from_date' => '2030-01-01', 'to_date' => '2030-12-31'])['errors'])->toBe([]);
    $rule = Mage::getModel('salesrule/rule')->load(CART_RULE_PARITY_NAME, 'name');
    expect($rule->getFromDate())->toBe('2030-01-01')
        ->and($rule->getWebsiteIds())->toEqual([1]);
    cartRuleParityCleanup();

    $api = cartRuleParityApi(['fromDate' => '2030-01-01', 'toDate' => '2030-12-31']);
    expect($api['status'])->toBe(200)
        ->and($api['result']->fromDate)->toBe('2030-01-01')
        ->and(cartRuleParityCount())->toBe(1);
});

it('refuses the same rule with the same message on both transports', function (array $admin, array $api, string $field, string $message): void {
    $adminResult = cartRuleParityAdmin($admin);
    $apiResult = cartRuleParityApi($api);

    expect($adminResult['errors'])->toBe([$message])
        ->and($apiResult['status'])->toBe(422)
        ->and($apiResult['fields'])->toBe([$field])
        ->and($apiResult['errors'])->toBe([$message])
        ->and(cartRuleParityCount())->toBe(0);
})->with([
    'a start date after the end date' => [
        ['from_date' => '2030-02-01', 'to_date' => '2030-01-31'],
        ['fromDate' => '2030-02-01', 'toDate' => '2030-01-31'],
        'toDate',
        'End Date must be greater than Start Date.',
    ],
    'a percent discount above 100' => [
        ['discount_amount' => '150'],
        ['discountAmount' => 150],
        'discountAmount',
        'The discount amount must not be more than 100 for a percent discount.',
    ],
    'no website' => [
        ['website_ids' => null],
        ['websiteIds' => []],
        'websiteIds',
        'Websites must be specified.',
    ],
    'an unknown customer group' => [
        ['customer_group_ids' => ['999999']],
        ['customerGroupIds' => [999999]],
        'customerGroupIds',
        'Unknown customer group ID: 999999.',
    ],
    'a coupon code with a space' => [
        ['coupon_type' => (string) Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC, 'coupon_code' => 'BAD CODE'],
        ['couponType' => 'specific', 'couponCode' => 'BAD CODE'],
        'couponCode',
        'The coupon code must have 3 to 64 characters of A-Z, a-z, 0-9, _ and -.',
    ],
    'a specific coupon without a code' => [
        ['coupon_type' => (string) Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC],
        ['couponType' => 'specific'],
        'couponCode',
        'The coupon code is required for a specific coupon.',
    ],
]);

it('refuses a coupon code that another coupon has in other letter case on both transports', function (): void {
    $code = cartRuleParityCode();
    expect(cartRuleParityAdmin(['coupon_type' => (string) Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC, 'coupon_code' => $code])['errors'])->toBe([]);

    $message = "Coupon code '" . strtolower($code) . "' already exists";
    expect(cartRuleParityAdmin(['coupon_type' => (string) Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC, 'coupon_code' => strtolower($code)])['errors'])->toBe([$message])
        ->and(cartRuleParityApi(['couponType' => 'specific', 'couponCode' => strtolower($code)]))->toMatchArray(['status' => 409, 'errors' => [$message]])
        ->and(cartRuleParityCount())->toBe(1);
});

it('generates coupons with the same settings and refuses the same settings on both transports', function (): void {
    expect(cartRuleParityApi(['couponType' => 'auto'])['status'])->toBe(200);
    $rule = Mage::getModel('salesrule/rule')->load(CART_RULE_PARITY_NAME, 'name');

    $adminGenerate = function (array $params) use ($rule): array {
        Mage::unregister('current_promo_quote_rule');
        $request = new Mage_Core_Controller_Request_Http(
            Symfony\Component\HttpFoundation\Request::create('/admin/promo_quote/generate', 'POST', $params + ['id' => $rule->getId(), 'isAjax' => '1']),
        );
        $response = new Mage_Core_Controller_Response_Http();
        Mage::app()->setRequest($request);
        new Mage_Adminhtml_Promo_QuoteController($request, $response)->generateAction();
        AdminApiParity::adminMessages();
        return json_decode((string) $response->getBody(), true);
    };
    $apiGenerate = function (array $body) use ($rule): array {
        $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/cart-price-rules/{$rule->getId()}/coupons/generate", $body);
        $security = AdminApiParity::adminSecurity();
        return AdminApiParity::api($request, fn() => new CartPriceRuleCouponProcessor($security, new CartPriceRuleProvider($security))
            ->process(null, new Post(name: 'cart_price_rule_coupons_generate'), ['ruleId' => $rule->getId()], ['request' => $request]));
    };

    expect($adminGenerate(['qty' => '2', 'length' => '6', 'format' => 'num', 'prefix' => 'PA-', 'suffix' => '', 'dash' => '0']))->not->toHaveKey('error')
        ->and($apiGenerate(['qty' => 2, 'length' => 6, 'format' => 'num', 'prefix' => 'PB-', 'dash' => 0])['status'])->toBe(200);
    $codes = Mage::getResourceModel('salesrule/coupon_collection')->addRuleToFilter($rule)->getColumnValues('code');
    expect($codes)->toHaveCount(4)
        ->and(preg_grep('/^P[AB]-\d{6}$/', $codes))->toHaveCount(4);

    $message = 'The code length must be from 1 to 32.';
    expect($adminGenerate(['qty' => '1', 'length' => '33', 'format' => 'num']))->toBe(['error' => $message])
        ->and($apiGenerate(['qty' => 1, 'length' => 33, 'format' => 'num']))->toMatchArray(['status' => 422, 'fields' => ['length'], 'errors' => [$message]]);
});

it('deletes only the generated coupons of the rule from the admin coupon grid', function (): void {
    expect(cartRuleParityApi(['couponType' => 'auto'])['status'])->toBe(200)
        ->and(cartRuleParityApi(['couponType' => 'specific', 'couponCode' => cartRuleParityCode()])['status'])->toBe(200);
    [$auto, $specific] = array_values(Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', CART_RULE_PARITY_NAME)->setOrder('rule_id', 'ASC')->getItems());
    /** @var Mage_SalesRule_Service_Rule $service */
    $service = Mage::getService('salesrule/rule');
    $service->generateCoupons(Mage::getModel('salesrule/rule')->load($auto->getId()), 2);
    $generatedIds = Mage::getResourceModel('salesrule/coupon_collection')->addRuleToFilter($auto)->getAllIds();
    $primaryId = (int) Mage::getModel('salesrule/rule')->load($specific->getId())->getPrimaryCoupon()->getId();

    Mage::unregister('current_promo_quote_rule');
    AdminApiParity::admin(Mage_Adminhtml_Promo_QuoteController::class, 'couponsMassDelete', ['id' => $auto->getId(), 'ids' => [...$generatedIds, $primaryId]]);

    expect(Mage::getResourceModel('salesrule/coupon_collection')->addRuleToFilter($auto)->getSize())->toBe(0)
        ->and(Mage::getModel('salesrule/coupon')->load($primaryId)->getId())->not->toBeNull();
});

it('checks the website, the customer group and the uses of a coupon', function (): void {
    $code = cartRuleParityCode();
    expect(cartRuleParityApi(['couponType' => 'specific', 'couponCode' => $code, 'isActive' => true, 'customerGroupIds' => [1], 'usesPerCoupon' => 1])['status'])->toBe(200);
    /** @var Mage_SalesRule_Service_Rule $service */
    $service = Mage::getService('salesrule/rule');

    expect($service->checkCoupon($code, 1, 1)->getCode())->toBe($code)
        ->and(fn() => $service->checkCoupon($code, 1, Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))->toThrow(Mage_SalesRule_Exception::class, 'Coupon is not valid for this customer group')
        ->and(fn() => $service->checkCoupon($code, 999999))->toThrow(Mage_SalesRule_Exception::class, 'Coupon is not valid for this website');

    Mage::getModel('salesrule/coupon')->loadByCode($code)->setTimesUsed(1)->save();
    expect(fn() => $service->checkCoupon($code, 1))->toThrow(Mage_SalesRule_Exception::class, 'Coupon usage limit reached');
});
