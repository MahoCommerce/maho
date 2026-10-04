<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('loads a missing catalog rule without querying its customer groups and websites', function (): void {
    $rule = Mage::getModel('catalogrule/rule')->load(999999999);

    expect($rule->getId())->toBeNull()
        ->and($rule->getData('customer_group_ids'))->toBeNull()
        ->and($rule->getData('website_ids'))->toBeNull();
});
