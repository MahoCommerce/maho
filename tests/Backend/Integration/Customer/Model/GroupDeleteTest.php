<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('never deletes the NOT LOGGED IN customer group, which guests use', function (): void {
    $group = Mage::getModel('customer/group')->load(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

    expect(fn() => $group->delete())->toThrow(Mage_Core_Exception::class, 'The group "NOT LOGGED IN" cannot be deleted');
    expect(Mage::getModel('customer/group')->load(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)->getId())->not->toBeNull();
});
