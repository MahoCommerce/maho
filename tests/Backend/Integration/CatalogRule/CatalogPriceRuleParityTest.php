<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\CatalogRule\Api\CatalogPriceRuleProcessor;
use Mage\CatalogRule\Api\CatalogPriceRuleProvider;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page and the API save and delete catalog price rules through Mage_CatalogRule_Service_Rule (#1348).
 * Each test gives the same input to both transports and compares the outcome.
 */

const CATALOG_RULE_PARITY_NAME = 'Pest catalog rule parity';

/**
 * @param array<string, mixed> $fields the fields of the admin form, on top of a valid rule
 * @return array{success: list<string>, errors: list<string>}
 */
function catalogRuleParityAdmin(string $action, array $fields): array
{
    return AdminApiParity::admin(Mage_Adminhtml_Promo_CatalogController::class, $action, array_filter($fields + [
        'name' => CATALOG_RULE_PARITY_NAME,
        'is_active' => '1',
        'website_ids' => ['1'],
        'customer_group_ids' => ['0', '1'],
        'simple_action' => 'by_percent',
        'discount_amount' => '10',
        'rule' => ['conditions' => ['1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1']]],
    ], fn($value) => $value !== null));
}

/**
 * @param array<string, mixed> $body the JSON body, on top of a valid rule
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function catalogRuleParityApi(array $body, ?int $deleteId = null): array
{
    $request = AdminApiParity::jsonRequest($deleteId ? 'DELETE' : 'POST', '/api/rest/v2/catalog-price-rules', $deleteId ? [] : array_filter($body + [
        'name' => CATALOG_RULE_PARITY_NAME,
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
        'discountAmount' => 10,
    ], fn($value) => $value !== null));
    $security = AdminApiParity::adminSecurity();
    return AdminApiParity::api($request, fn() => new CatalogPriceRuleProcessor($security, new CatalogPriceRuleProvider($security))
        ->process(null, $deleteId ? new Delete() : new Post(), $deleteId ? ['id' => $deleteId] : [], ['request' => $request]));
}

function catalogRuleParityIds(): array
{
    return Mage::getResourceModel('catalogrule/rule_collection')->addFieldToFilter('name', CATALOG_RULE_PARITY_NAME)->getAllIds();
}

function catalogRuleParityCleanup(): void
{
    foreach (catalogRuleParityIds() as $id) {
        Mage::getModel('catalogrule/rule')->load($id)->delete();
    }
}

function catalogRuleParityFlag(): int
{
    return (int) Mage::getModel('catalogrule/flag')->loadSelf()->getState();
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    catalogRuleParityCleanup();
    $this->flagState = catalogRuleParityFlag();
});

afterEach(function (): void {
    catalogRuleParityCleanup();
    Mage::getModel('catalogrule/flag')->loadSelf()->setState($this->flagState)->save();
});

it('saves and deletes the same rule and marks the rules as changed on both transports', function (): void {
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();
    expect(catalogRuleParityAdmin('save', ['from_date' => '2030-01-01'])['errors'])->toBe([])
        ->and(catalogRuleParityFlag())->toBe(1)
        ->and(Mage::getModel('catalogrule/rule')->load(catalogRuleParityIds()[0])->getFromDate())->toBe('2030-01-01');
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();
    expect(catalogRuleParityAdmin('delete', ['id' => catalogRuleParityIds()[0]])['success'])->toBe(['The rule has been deleted.'])
        ->and(catalogRuleParityFlag())->toBe(1)
        ->and(catalogRuleParityIds())->toBe([]);

    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();
    expect(catalogRuleParityApi(['fromDate' => '2030-01-01'])['status'])->toBe(200)
        ->and(catalogRuleParityFlag())->toBe(1);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();
    expect(catalogRuleParityApi([], (int) catalogRuleParityIds()[0])['status'])->toBe(200)
        ->and(catalogRuleParityFlag())->toBe(1)
        ->and(catalogRuleParityIds())->toBe([]);
});

it('refuses the same rule with the same message on both transports', function (array $admin, array $api, string $field, string $message): void {
    $adminResult = catalogRuleParityAdmin('save', $admin);
    $apiResult = catalogRuleParityApi($api);

    expect($adminResult['errors'])->toBe([$message])
        ->and($apiResult['status'])->toBe(422)
        ->and($apiResult['fields'])->toBe([$field])
        ->and($apiResult['errors'])->toBe([$message])
        ->and(catalogRuleParityIds())->toBe([]);
})->with([
    'a start date after the end date' => [
        ['from_date' => '2030-02-01', 'to_date' => '2030-01-31'],
        ['fromDate' => '2030-02-01', 'toDate' => '2030-01-31'],
        'toDate',
        'End Date must be greater than Start Date.',
    ],
    'a percent discount above 100' => [
        ['discount_amount' => '101'],
        ['discountAmount' => 101],
        'discountAmount',
        'The discount amount must not be more than 100 for by_percent and to_percent.',
    ],
    'a sub product percent discount above 100' => [
        ['sub_is_enable' => '1', 'sub_simple_action' => 'to_percent', 'sub_discount_amount' => '150'],
        ['subIsEnable' => true, 'subSimpleAction' => 'to_percent', 'subDiscountAmount' => 150],
        'subDiscountAmount',
        'The discount amount must not be more than 100 for by_percent and to_percent.',
    ],
    'no customer group' => [
        ['customer_group_ids' => null],
        ['customerGroupIds' => []],
        'customerGroupIds',
        'Customer Groups must be specified.',
    ],
    'an unknown website' => [
        ['website_ids' => ['999999']],
        ['websiteIds' => [999999]],
        'websiteIds',
        'Unknown website ID: 999999.',
    ],
]);
