<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * GHSA-fwmh-c5gm-2pqm: a change of permissions reaches each session of the user, and a session of a
 * user without a role ends.
 */

function aclVersionReloadUser(Mage_Admin_Model_User $user): Mage_Admin_Model_User
{
    return Mage::getModel('admin/user')->load($user->getId());
}

function aclVersionRunObserver(): Mage_Core_Controller_Request_Http
{
    $request = Mage::app()->getRequest();
    $request->setActionName('index');
    $request->setInternallyForwarded(false);
    new Mage_Admin_Model_Observer()->actionPreDispatchAdmin(new \Maho\Event\Observer());
    return $request;
}

function aclVersionDeleteUser(Mage_Admin_Model_User $user): void
{
    $session = Mage::getSingleton('admin/session');
    $session->setUser(null);
    $session->setAcl(null);
    Mage::getModel('admin/user')->load($user->getId())->delete();
}

it('builds the ACL again in each session of a user when the rules of the role change', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    try {
        $session = Mage::getSingleton('admin/session');
        $oldAcl = $session->getAcl();
        $oldVersion = $session->getAclVersion();
        expect($session->isAllowed('admin/system'))->toBeFalse();

        Mage::getModel('admin/rules')->setRoleId((int) $user->getRole()->getId())->setResources(['all'])->saveRel();

        $session->refreshAcl(aclVersionReloadUser($user));
        expect($session->getAcl())->not->toBe($oldAcl)
            ->and($session->isAllowed('admin/system'))->toBeTrue();

        // A second session of the same user still has the ACL from before the change
        $session->setAcl($oldAcl)->setAclVersion($oldVersion);
        $session->refreshAcl(aclVersionReloadUser($user));
        expect($session->getAcl())->not->toBe($oldAcl)
            ->and($session->isAllowed('admin/system'))->toBeTrue();
    } finally {
        AdminRoleFixture::delete($user);
    }
});

it('builds the ACL again in each session of a user when other code sets reload_acl_flag', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    try {
        $session = Mage::getSingleton('admin/session');
        $oldAcl = $session->getAcl();
        $oldVersion = $session->getAclVersion();

        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('admin/user'),
            ['reload_acl_flag' => 1],
            ['user_id = ?' => (int) $user->getId()],
        );

        $session->refreshAcl(aclVersionReloadUser($user));
        expect($session->getAcl())->not->toBe($oldAcl);

        $session->setAcl($oldAcl)->setAclVersion($oldVersion);
        $session->refreshAcl(aclVersionReloadUser($user));
        expect($session->getAcl())->not->toBe($oldAcl)
            ->and(aclVersionReloadUser($user)->getReloadAclFlag())->toBeFalse();
    } finally {
        AdminRoleFixture::delete($user);
    }
});

it('keeps the session of a user that still has a role', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    try {
        aclVersionRunObserver();

        expect(Mage::getSingleton('admin/session')->getUser()?->getId())->toBe($user->getId());
    } finally {
        AdminRoleFixture::delete($user);
    }
});

it('ends the session of a user that was removed from its role', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    $roleId = (int) $user->getRole()->getId();
    try {
        Mage::getModel('admin/user')->setRoleId($roleId)->setUserId((int) $user->getId())->deleteFromRole();

        $request = aclVersionRunObserver();

        expect(Mage::getSingleton('admin/session')->getUser())->toBeNull()
            ->and($request->getActionName())->toBe('login');
    } finally {
        aclVersionDeleteUser($user);
        Mage::getModel('admin/role')->load($roleId)->delete();
    }
});

it('ends the sessions of the members of a deleted role', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    $roleId = (int) $user->getRole()->getId();
    try {
        $versionBefore = Mage::getResourceSingleton('admin/user')->getAclVersion($user);
        Mage::getModel('admin/roles')->load($roleId)->delete();

        expect(Mage::getResourceSingleton('admin/user')->getAclVersion($user))->toBe($versionBefore + 1);

        aclVersionRunObserver();

        expect(Mage::getSingleton('admin/session')->getUser())->toBeNull();
    } finally {
        aclVersionDeleteUser($user);
    }
});
