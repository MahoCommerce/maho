<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A catalog rule discounts the price the website sells at. For a website in another currency
 * that price is derived from the default-scope value and the rate, exactly as the price index
 * derives it, so a rule price computed from the raw default value would be in the wrong currency
 * and, being higher than the indexed price, would never apply. The currency is an ISO 4217 "X" code.
 */
const RULE_DERIVED_CODE = 'rule_derived_ws';
const RULE_DERIVED_CURRENCY = 'XTR';
const RULE_DERIVED_PRICE = 100.0;
const RULE_DERIVED_RATE = 0.5;
const RULE_DERIVED_RULE_NAME = 'pest-rule-derived';

function ruleDerivedWebsite(): Mage_Core_Model_Website
{
    return createPriceWebsite(RULE_DERIVED_CODE, 94);
}

function ruleDerivedConfigure(int $scope): void
{
    if ($scope === 0) {
        restorePriceScope(RULE_DERIVED_CODE);
    } else {
        configurePriceWebsite(RULE_DERIVED_CODE, RULE_DERIVED_CURRENCY, $scope);
    }
}

function ruleDerivedStoreId(): int
{
    return (int) Mage::app()->getStore(RULE_DERIVED_CODE)->getId();
}

function ruleDerivedWrite(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function ruleDerivedTable(string $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

function ruleDerivedProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('rule-derived', RULE_DERIVED_PRICE, ruleDerivedWebsite());
}

function ruleDerivedWriteStorePrice(int $productId, float $value): void
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price');
    ruleDerivedWrite()->insert($attribute->getBackend()->getTable(), [
        'attribute_id' => (int) $attribute->getId(),
        'store_id'     => ruleDerivedStoreId(),
        'entity_id'    => $productId,
        'value'        => $value,
    ]);
}

/** Written straight to catalogrule_product: the rule model would rebuild it from conditions. */
function ruleDerivedSeedRule(int $productId, int $websiteId): void
{
    $write = ruleDerivedWrite();
    $table = ruleDerivedTable('catalogrule/rule');

    $write->insert($table, [
        'name' => RULE_DERIVED_RULE_NAME,
        'is_active' => 1,
        'from_date' => '2026-01-01',
        'to_date' => '2026-12-31',
        'sort_order' => 0,
        'simple_action' => 'by_percent',
        'discount_amount' => 10,
        'stop_rules_processing' => 0,
    ]);
    $ruleId = (int) $write->fetchOne(
        $write->select()->from($table, 'rule_id')->where('name = ?', RULE_DERIVED_RULE_NAME)->order('rule_id DESC')->limit(1),
    );

    $write->insert(ruleDerivedTable('catalogrule/rule_product'), [
        'rule_id' => $ruleId,
        'from_time' => strtotime('2026-01-01 00:00:00 UTC'),
        'to_time' => strtotime('2026-12-31 00:00:00 UTC'),
        'customer_group_id' => 0,
        'product_id' => $productId,
        'website_id' => $websiteId,
        'sort_order' => 0,
        'action_operator' => 'by_percent',
        'action_amount' => 10,
        'action_stop' => 0,
    ]);
}

function ruleDerivedReindex(): void
{
    Mage::getModel('catalogrule/action_index_refresh', [
        'connection' => ruleDerivedWrite(),
        'factory'    => Mage::getModel('core/factory'),
        'resource'   => Mage::getResourceSingleton('catalogrule/rule'),
        'app'        => Mage::app(),
        'value'      => null,
    ])->execute();
}

/** @return list<float> */
function ruleDerivedPrices(int $productId, int $websiteId): array
{
    $write = ruleDerivedWrite();

    return array_map('floatval', $write->fetchCol(
        $write->select()
            ->from(ruleDerivedTable('catalogrule/rule_product_price'), 'rule_price')
            ->where('product_id = ?', $productId)
            ->where('website_id = ?', $websiteId),
    ));
}

beforeEach(function () {
    ruleDerivedWebsite();
    ruleDerivedConfigure(1);
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RULE_DERIVED_CURRENCY => RULE_DERIVED_RATE],
    ]);
    $this->product = ruleDerivedProduct();
    ruleDerivedSeedRule((int) $this->product->getId(), (int) ruleDerivedWebsite()->getId());
});

afterEach(function () {
    $write = ruleDerivedWrite();
    $ruleIds = $write->fetchCol(
        $write->select()->from(ruleDerivedTable('catalogrule/rule'), 'rule_id')->where('name = ?', RULE_DERIVED_RULE_NAME),
    );
    if ($ruleIds) {
        $write->delete(ruleDerivedTable('catalogrule/rule_product'), ['rule_id IN (?)' => $ruleIds]);
        $write->delete(ruleDerivedTable('catalogrule/rule'), ['rule_id IN (?)' => $ruleIds]);
    }
    if (isset($this->product) && $this->product->getId()) {
        $write->delete(ruleDerivedTable('catalogrule/rule_product_price'), ['product_id = ?' => (int) $this->product->getId()]);
        $this->product->delete();
    }
    ruleDerivedConfigure(0);
    dropCurrencyRates(RULE_DERIVED_CURRENCY);
    deletePriceWebsite(RULE_DERIVED_CODE);
});

it('discounts the derived website price rather than the default-scope value', function () {
    ruleDerivedReindex();

    $prices = ruleDerivedPrices((int) $this->product->getId(), (int) ruleDerivedWebsite()->getId());
    expect($prices)->not->toBeEmpty();
    foreach ($prices as $price) {
        expect($price)->toEqualWithDelta(RULE_DERIVED_PRICE * RULE_DERIVED_RATE * 0.9, 0.0001);
    }
});

it('discounts an explicit website price as it is', function () {
    ruleDerivedWriteStorePrice((int) $this->product->getId(), 80.0);

    ruleDerivedReindex();

    $prices = ruleDerivedPrices((int) $this->product->getId(), (int) ruleDerivedWebsite()->getId());
    expect($prices)->not->toBeEmpty();
    foreach ($prices as $price) {
        expect($price)->toEqualWithDelta(72.0, 0.0001);
    }
});

it('writes no rule price for a website that has no rate', function () {
    dropCurrencyRates(RULE_DERIVED_CURRENCY);

    ruleDerivedReindex();

    expect(ruleDerivedPrices((int) $this->product->getId(), (int) ruleDerivedWebsite()->getId()))->toBe([]);
});
