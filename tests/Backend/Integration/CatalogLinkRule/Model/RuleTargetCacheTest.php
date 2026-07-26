<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Rule that counts how often the catalog is actually scanned, so the target-scan caching can be
 * asserted without touching the database.
 */
class Maho_CatalogLinkRule_Test_CountingRule extends Maho_CatalogLinkRule_Model_Rule
{
    public int $collectCalls = 0;
    public array $collectResult = [];

    #[\Override]
    protected function _collectMatchingTargetProductIds(?Mage_Catalog_Model_Product $sourceProduct = null): array
    {
        $this->collectCalls++;
        return $this->collectResult;
    }
}

describe('CatalogLinkRule target-scan caching', function () {
    test('target conditions without a source-match are flagged as source-independent', function () {
        $rule = Mage::getModel('cataloglinkrule/rule');
        $rule->getTargetConditions()->addCondition(Mage::getModel('cataloglinkrule/rule_target_product'));

        expect($rule->targetConditionsUseSourceProduct())->toBeFalse();
    });

    test('a source-match target condition marks the rule as source-dependent', function () {
        $rule = Mage::getModel('cataloglinkrule/rule');
        $rule->getTargetConditions()->addCondition(Mage::getModel('cataloglinkrule/rule_target_sourceMatch'));

        expect($rule->targetConditionsUseSourceProduct())->toBeTrue();
    });

    test('a source-match nested in a sub-combine is still detected', function () {
        $rule = Mage::getModel('cataloglinkrule/rule');
        $nested = Mage::getModel('cataloglinkrule/rule_target_combine');
        $nested->addCondition(Mage::getModel('cataloglinkrule/rule_target_sourceMatch'));
        $rule->getTargetConditions()->addCondition($nested);

        expect($rule->targetConditionsUseSourceProduct())->toBeTrue();
    });

    test('source-independent target scan is computed once and reused across source products', function () {
        $rule = new Maho_CatalogLinkRule_Test_CountingRule();
        $rule->collectResult = [11, 22, 33];

        $first = $rule->getMatchingTargetProductIds();
        $second = $rule->getMatchingTargetProductIds();

        // One catalog scan, reused for the second call.
        expect($rule->collectCalls)->toBe(1);
        // Same matched set both times (order may differ under random sort).
        expect($first)->toEqualCanonicalizing([11, 22, 33]);
        expect($second)->toEqualCanonicalizing([11, 22, 33]);
    });

    test('a deterministic sort keeps the cached order stable', function () {
        $rule = new Maho_CatalogLinkRule_Test_CountingRule();
        $rule->collectResult = [11, 22, 33];
        $rule->setSortOrder('price_asc');

        expect($rule->getMatchingTargetProductIds())->toBe([11, 22, 33]);
        expect($rule->getMatchingTargetProductIds())->toBe([11, 22, 33]);
        expect($rule->collectCalls)->toBe(1);
    });

    test('source-dependent target scan is recomputed per source product', function () {
        $rule = new Maho_CatalogLinkRule_Test_CountingRule();
        $rule->collectResult = [11, 22, 33];
        $rule->getTargetConditions()->addCondition(Mage::getModel('cataloglinkrule/rule_target_sourceMatch'));

        $rule->getMatchingTargetProductIds();
        $rule->getMatchingTargetProductIds();

        // No caching when the target set depends on the source product.
        expect($rule->collectCalls)->toBe(2);
    });
});
