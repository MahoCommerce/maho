<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * An admin user with a role that allows only the given ACL resources, logged in to the admin session.
 */
final class AdminRoleFixture
{
    /**
     * @param list<string> $resources
     */
    public static function login(array $resources): \Mage_Admin_Model_User
    {
        $role = \Mage::getModel('admin/role')->setData([
            'role_name' => 'pest_role_' . uniqid(),
            'role_type' => \Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
            'parent_id' => 0,
        ])->save();
        \Mage::getModel('admin/rules')->setRoleId($role->getId())->setResources($resources)->saveRel();

        $user = \Mage::getModel('admin/user')->setData([
            'username' => 'pest_role_' . uniqid(),
            'firstname' => 'Pest',
            'lastname' => 'Role',
            'email' => uniqid('pest-role-') . '@example.test',
            'password' => 'pest-role-password-1234',
            'is_active' => 1,
        ])->save();
        \Mage::getModel('admin/user')->setRoleId($role->getId())->setUserId($user->getId())->add();
        $user = \Mage::getModel('admin/user')->load($user->getId());

        $session = \Mage::getSingleton('admin/session');
        $session->setUser($user);
        $session->refreshAcl($user);
        return $user;
    }

    public static function delete(\Mage_Admin_Model_User $user): void
    {
        // Log out first: the activity log cannot record the deletion of the admin who does it
        $session = \Mage::getSingleton('admin/session');
        $session->setUser(null);
        $session->setAcl(null);

        $role = $user->getRole();
        $user->delete();
        if ($role->getId()) {
            \Mage::getModel('admin/role')->load($role->getId())->delete();
        }
    }
}
