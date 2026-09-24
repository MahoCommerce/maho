<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * @return array<string, int|string|null>
 */
function aclRuleOrderRow(string $resourceId, string $permission): array
{
    return [
        'role_type' => 'G',
        'role_id' => 900001,
        'resource_id' => $resourceId,
        'privileges' => '',
        'assert_id' => 0,
        'permission' => $permission,
    ];
}

it('keeps the deny rules of child resources when the rows come before the rule of the parent', function (): void {
    $acl = Mage::getModel('admin/acl');
    Mage::getSingleton('admin/config')->loadAclResources($acl);
    $resource = Mage::getResourceModel('admin/acl');
    $resource->loadRoles($acl, [
        ['role_id' => 900001, 'parent_id' => 0, 'role_type' => 'G', 'user_id' => 0],
        ['role_id' => 900002, 'parent_id' => 900001, 'role_type' => 'U', 'user_id' => 900003],
    ]);

    // The children first, as a database can return the rows without an ORDER BY
    $resource->loadRules($acl, [
        aclRuleOrderRow('admin/customer', 'deny'),
        aclRuleOrderRow('admin/report/salesroot/sales', 'allow'),
        aclRuleOrderRow('admin/report/products', 'deny'),
        aclRuleOrderRow('admin/report/salesroot', 'allow'),
        aclRuleOrderRow('admin/report', 'allow'),
        aclRuleOrderRow('admin', 'allow'),
    ]);

    expect($acl->isAllowed('U900003', 'admin/report/salesroot/sales'))->toBeTrue()
        ->and($acl->isAllowed('U900003', 'admin/customer'))->toBeFalse()
        ->and($acl->isAllowed('U900003', 'admin/customer/manage'))->toBeFalse()
        ->and($acl->isAllowed('U900003', 'admin/report/products/bestsellers'))->toBeFalse();
});
