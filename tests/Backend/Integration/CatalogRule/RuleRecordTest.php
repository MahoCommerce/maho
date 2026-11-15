<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

use Mage_CatalogRule_Model_Rule as Rule;

uses(Tests\MahoBackendTestCase::class);

/**
 * The model of a catalog price rule accepts the actions of the configuration, so a module adds an action with a
 * configuration node (#1348).
 */

const CATALOG_RULE_RECORD_NAME = 'Pest catalog rule record';

function catalogRuleRecordNew(): Rule
{
    /** @var Rule $rule */
    $rule = Mage::getModel('catalogrule/rule');
    return $rule->setName(CATALOG_RULE_RECORD_NAME)
        ->setIsActive(false)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setSimpleAction('by_percent')
        ->addData(['discount_amount' => 10]);
}

function catalogRuleRecordCleanup(): void
{
    foreach (Mage::getResourceModel('catalogrule/rule_collection')->addFieldToFilter('name', CATALOG_RULE_RECORD_NAME) as $rule) {
        $rule->delete();
    }
    $actions = Mage::getConfig()->getNode(Rule::XML_PATH_SIMPLE_ACTIONS);
    if ($actions && isset($actions->pest_action)) {
        unset($actions->pest_action);
    }
}

beforeEach(function (): void {
    catalogRuleRecordCleanup();
});

afterEach(function (): void {
    catalogRuleRecordCleanup();
});

it('lists the four core actions', function (): void {
    expect(Mage::getModel('catalogrule/rule')->getSimpleActions())->toBe(Rule::SIMPLE_ACTIONS);
});

it('refuses an action that the configuration does not list', function (): void {
    expect(fn() => catalogRuleRecordNew()->setSimpleAction('pest_action')->save())
        ->toThrow(Mage_Core_Exception_Input::class, 'The action must be one of: by_percent, by_fixed, to_percent, to_fixed.');
});

it('saves an action that a module adds to the configuration', function (): void {
    Mage::getConfig()->setNode(Rule::XML_PATH_SIMPLE_ACTIONS . '/pest_action', '');

    $rule = catalogRuleRecordNew()->setSimpleAction('pest_action');
    $rule->save();

    expect(Mage::getModel('catalogrule/rule')->load($rule->getId())->getSimpleAction())->toBe('pest_action');
});

it('tells whether the rule is in one of the websites', function (): void {
    $rule = catalogRuleRecordNew();

    expect($rule->isInWebsites([1, 99]))->toBeTrue()
        ->and($rule->isInWebsites(['1']))->toBeTrue()
        ->and($rule->isInWebsites([99]))->toBeFalse()
        ->and($rule->isInWebsites([]))->toBeFalse();
});
