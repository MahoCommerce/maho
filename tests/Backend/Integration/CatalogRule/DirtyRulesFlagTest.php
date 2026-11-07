<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function dirtyRulesTable(string $entity): string
{
    return Mage::getSingleton('core/resource')->getTableName($entity);
}

beforeEach(function (): void {
    $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->write->beginTransaction();
    $this->write->delete(dirtyRulesTable('catalogrule/rule_product_price'));
    $this->write->delete(dirtyRulesTable('catalogrule/rule'));
});

afterEach(function (): void {
    $this->write->rollBack();
});

it('clears the flag when there is no rule and no rule price', function (): void {
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();

    expect(Mage::getModel('catalogrule/flag')->loadSelf()->hasChangesToApply())->toBeFalse()
        ->and((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(0);
});

it('keeps the flag when a rule exists', function (): void {
    $this->write->insert(dirtyRulesTable('catalogrule/rule'), ['name' => 'Dirty flag test rule']);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();

    expect(Mage::getModel('catalogrule/flag')->loadSelf()->hasChangesToApply())->toBeTrue();
});

it('keeps the flag when the last rule is gone but its prices are still there', function (): void {
    $productId = (int) $this->write->fetchOne(
        $this->write->select()->from(dirtyRulesTable('catalog/product'), 'entity_id')->limit(1),
    );
    $this->write->insert(dirtyRulesTable('catalogrule/rule_product_price'), [
        'rule_date' => '2026-01-01',
        'customer_group_id' => 0,
        'product_id' => $productId,
        'rule_price' => 10,
        'website_id' => 1,
        'latest_start_date' => '2026-01-01',
    ]);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();

    expect(Mage::getModel('catalogrule/flag')->loadSelf()->hasChangesToApply())->toBeTrue();
});

it('reports no changes when the flag is not set', function (): void {
    $this->write->insert(dirtyRulesTable('catalogrule/rule'), ['name' => 'Dirty flag test rule']);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();

    expect(Mage::getModel('catalogrule/flag')->loadSelf()->hasChangesToApply())->toBeFalse();
});
