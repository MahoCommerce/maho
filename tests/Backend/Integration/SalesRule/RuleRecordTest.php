<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use Mage_SalesRule_Model_Rule as Rule;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The rules of one cart price rule are in the model, so that code that saves the model gets them (#1348). A module
 * adds an action through the configuration, and an old stored value does not refuse a save that leaves it alone.
 */

const RULE_RECORD_NAME = 'Pest rule record';

function ruleRecordNew(): Rule
{
    /** @var Rule $rule */
    $rule = Mage::getModel('salesrule/rule');
    return $rule->setName(RULE_RECORD_NAME)
        ->setIsActive(false)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setCouponType(Rule::COUPON_TYPE_NO_COUPON)
        ->setSimpleAction(Rule::BY_PERCENT_ACTION)
        ->setDiscountAmount(10.0);
}

function ruleRecordCleanup(): void
{
    foreach (Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', RULE_RECORD_NAME) as $rule) {
        $rule->delete();
    }
    $actions = Mage::getConfig()->getNode(Rule::XML_PATH_SIMPLE_ACTIONS);
    if ($actions && isset($actions->pest_action)) {
        unset($actions->pest_action);
    }
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    ruleRecordCleanup();
});

afterEach(function (): void {
    ruleRecordCleanup();
});

it('lists the four core actions', function (): void {
    expect(Mage::getModel('salesrule/rule')->getSimpleActions())->toBe(Rule::SIMPLE_ACTIONS);
});

it('refuses an action that the configuration does not list', function (): void {
    expect(fn() => ruleRecordNew()->setSimpleAction('pest_action')->save())
        ->toThrow(Mage_Core_Exception_Input::class, 'The action must be one of: by_percent, by_fixed, cart_fixed, buy_x_get_y.');
});

it('saves an action that a module adds to the configuration', function (): void {
    Mage::getConfig()->setNode(Rule::XML_PATH_SIMPLE_ACTIONS . '/pest_action', '');

    $rule = ruleRecordNew()->setSimpleAction('pest_action');
    $rule->save();

    expect(Mage::getModel('salesrule/rule')->load($rule->getId())->getSimpleAction())->toBe('pest_action')
        ->and(Mage::getModel('salesrule/rule')->getSimpleActions())->toContain('pest_action');
});

it('saves a counter of a rule that holds an old value that the rules of today refuse', function (): void {
    $rule = ruleRecordNew();
    $rule->save();
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('salesrule/rule'),
        ['discount_amount' => 150, 'simple_action' => 'action_of_a_removed_module'],
        ['rule_id = ?' => (int) $rule->getId()],
    );

    /** @var Rule $stored */
    $stored = Mage::getModel('salesrule/rule')->load($rule->getId());
    $stored->setTimesUsed(3)->save();

    expect((int) Mage::getModel('salesrule/rule')->load($rule->getId())->getTimesUsed())->toBe(3);

    /** @var Rule $changed */
    $changed = Mage::getModel('salesrule/rule')->load($rule->getId());
    expect(fn() => $changed->setSimpleAction(Rule::BY_PERCENT_ACTION)->save())
        ->toThrow(Mage_Core_Exception_Input::class, 'The discount amount must not be more than 100 for a percent discount.');
});

it('saves a specific coupon rule without a code in the model, and requires the code in the service', function (): void {
    $rule = ruleRecordNew()->setCouponType(Rule::COUPON_TYPE_SPECIFIC);
    $rule->save();

    expect((int) Mage::getModel('salesrule/rule')->load($rule->getId())->getCouponType())->toBe(Rule::COUPON_TYPE_SPECIFIC)
        ->and(fn() => Mage::getService('salesrule/rule')->save(ruleRecordNew()->setCouponType(Rule::COUPON_TYPE_SPECIFIC)))
        ->toThrow(Mage_Core_Exception_Input::class, 'The coupon code is required for a specific coupon.');

    /** @var Rule $stored */
    $stored = Mage::getModel('salesrule/rule')->load($rule->getId());
    Mage::getService('salesrule/rule')->save($stored->setDiscountAmount(20.0));

    expect((float) Mage::getModel('salesrule/rule')->load($rule->getId())->getDiscountAmount())->toBe(20.0);
});

it('refuses a condition whose attribute holds an HTML tag, in the model and on the admin page', function (): void {
    $rule = ruleRecordNew();
    $rule->getConditions()->loadArray([
        'type' => 'salesrule/rule_condition_combine',
        'aggregator' => 'all',
        'value' => '1',
        'conditions' => [
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal<b>x</b>', 'operator' => '>=', 'value' => '10'],
        ],
    ]);

    expect(fn() => $rule->save())->toThrow(Mage_Core_Exception_Input::class, 'Wrong rule specified');

    Mage::unregister('current_promo_quote_rule');
    $admin = AdminApiParity::admin(Mage_Adminhtml_Promo_QuoteController::class, 'save', [
        'name' => RULE_RECORD_NAME,
        'is_active' => '0',
        'website_ids' => ['1'],
        'customer_group_ids' => ['0', '1'],
        'coupon_type' => (string) Rule::COUPON_TYPE_NO_COUPON,
        'simple_action' => Rule::BY_PERCENT_ACTION,
        'discount_amount' => '10',
        'rule' => ['conditions' => [
            '1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1'],
            '1--1' => ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal<b>x</b>', 'operator' => '>=', 'value' => '10'],
        ]],
    ]);

    expect($admin['errors'])->toBe(['Wrong rule specified'])
        ->and(Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', RULE_RECORD_NAME)->getSize())->toBe(0);
});
