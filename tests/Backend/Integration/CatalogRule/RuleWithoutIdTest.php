<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('loads a missing catalog rule with no customer groups and no websites', function (): void {
    $rule = Mage::getModel('catalogrule/rule')->load(999999999);

    expect($rule->getId())->toBeNull()
        ->and($rule->getCustomerGroupIds())->toBe([])
        ->and($rule->getWebsiteIds())->toBe([]);
});

it('gives a new catalog rule and a new cart price rule no customer groups and no websites', function (): void {
    $catalogRule = Mage::getModel('catalogrule/rule');
    $cartRule = Mage::getModel('salesrule/rule');

    expect($catalogRule->getCustomerGroupIds())->toBe([])
        ->and($catalogRule->getWebsiteIds())->toBe([])
        ->and($cartRule->getCustomerGroupIds())->toBe([])
        ->and($cartRule->getWebsiteIds())->toBe([]);
});
