<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminApiParity;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * An admin user cannot change the own role, on the user page or on the role page. The session loses the
 * permissions of the old role on its next request, so the change can lock the admin out.
 */

const OWN_ROLE_PASSWORD = 'pest-role-password-1234';

beforeEach(function (): void {
    $this->admin = AdminRoleFixture::login(['all']);
    $this->ownRoleId = (int) $this->admin->getRole()->getId();
    $this->otherRole = Mage::getModel('admin/role')->setData([
        'role_name' => 'pest_other_role_' . uniqid(),
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();
});

afterEach(function (): void {
    Mage::getSingleton('adminhtml/session')->getMessages(true);
    $this->otherRole->delete();
    AdminRoleFixture::delete(Mage::getModel('admin/user')->load($this->admin->getId()));
});

/**
 * @return list<int>
 */
function ownRoleIds(Mage_Admin_Model_User $user): array
{
    return array_map(intval(...), Mage::getModel('admin/user')->setId($user->getId())->getRoles());
}

/**
 * Post the user page of $user with the role $roleId.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function ownRoleSaveUser(Mage_Admin_Model_User $user, int $roleId): array
{
    return AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'save', [
        'user_id' => $user->getId(),
        'username' => $user->getUsername(),
        'firstname' => $user->getFirstname(),
        'lastname' => $user->getLastname(),
        'email' => $user->getEmail(),
        'is_active' => '1',
        'roles' => [$roleId],
        'current_password' => OWN_ROLE_PASSWORD,
    ], ['user_id' => $user->getId()]);
}

/**
 * Post the role page of $role with the user lists that the role grid writes.
 *
 * @param list<int> $users
 * @param list<int> $oldUsers
 * @return array{success: list<string>, errors: list<string>}
 */
function ownRoleSaveRole(Mage_Admin_Model_Roles $role, array $users, array $oldUsers): array
{
    $list = static fn(array $ids): string => implode('&', array_map(static fn(int $id): string => $id . '=true', $ids));

    return AdminApiParity::admin(Mage_Adminhtml_Permissions_RoleController::class, 'saveRole', [
        'role_id' => $role->getId(),
        'rolename' => $role->getRoleName(),
        'all' => '1',
        'in_role_user' => $list($users),
        'in_role_user_old' => $list($oldUsers),
        'current_password' => OWN_ROLE_PASSWORD,
    ]);
}

it('refuses to give the admin user who saves the user page another role', function (): void {
    $result = ownRoleSaveUser($this->admin, (int) $this->otherRole->getId());

    expect($result['errors'])->toBe([Mage::helper('adminhtml')->__('You cannot change your own role.')])
        ->and(ownRoleIds($this->admin))->toBe([$this->ownRoleId]);

    $service = Mage::getService('admin/user');
    expect(fn() => $service->save(
        $service->getById((int) $this->admin->getId()),
        (int) $this->otherRole->getId(),
        (int) $this->admin->getId(),
    ))->toThrow(Mage_Core_Exception_Conflict::class);
});

it('saves the own user page when the role does not change', function (): void {
    $result = ownRoleSaveUser($this->admin, $this->ownRoleId);

    expect($result['success'])->toBe([Mage::helper('adminhtml')->__('The user has been saved.')])
        ->and(ownRoleIds($this->admin))->toBe([$this->ownRoleId]);
});

it('refuses to remove the admin user who saves the role page from the own role', function (): void {
    $ownRole = Mage::getModel('admin/roles')->load($this->ownRoleId);
    $result = ownRoleSaveRole($ownRole, [], [(int) $this->admin->getId()]);

    expect($result['errors'])->toBe([Mage::helper('adminhtml')->__('You cannot change your own role.')])
        ->and(ownRoleIds($this->admin))->toBe([$this->ownRoleId]);
});

it('refuses to move the admin user who saves the role page to that role', function (): void {
    $otherRole = Mage::getModel('admin/roles')->load($this->otherRole->getId());
    $result = ownRoleSaveRole($otherRole, [(int) $this->admin->getId()], []);

    expect($result['errors'])->toBe([Mage::helper('adminhtml')->__('You cannot change your own role.')])
        ->and(ownRoleIds($this->admin))->toBe([$this->ownRoleId]);
});

it('saves the own role page when the admin user stays in it', function (): void {
    $ownRole = Mage::getModel('admin/roles')->load($this->ownRoleId);
    $adminId = (int) $this->admin->getId();
    $result = ownRoleSaveRole($ownRole, [$adminId], [$adminId]);

    expect($result['success'])->toBe([Mage::helper('adminhtml')->__('The role has been successfully saved.')])
        ->and(ownRoleIds($this->admin))->toBe([$this->ownRoleId]);
});
