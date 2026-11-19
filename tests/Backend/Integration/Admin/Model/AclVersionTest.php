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
    new class extends Mage_Admin_Model_Observer {
        // The test has no browser session, so it has no session cookie to renew
        #[\Override]
        protected function endSession(Mage_Admin_Model_Session $session): void
        {
            $session->unsetAll();
        }
    }->actionPreDispatchAdmin(new \Maho\Event\Observer());
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

it('does not write an old acl_version back when a loaded user is saved', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    try {
        $resource = Mage::getResourceSingleton('admin/user');
        $copy = aclVersionReloadUser($user);
        $resource->bumpAclVersion([(int) $user->getId()]);
        $bumped = $resource->getAclVersion($user);

        $copy->setFirstname('Changed')->save();

        expect($resource->getAclVersion($user))->toBe($bumped)
            ->and(aclVersionReloadUser($user)->getFirstname())->toBe('Changed');
    } finally {
        AdminRoleFixture::delete($user);
    }
});

it('builds the ACL again when the session changes to a user with the same acl_version', function (): void {
    $first = AdminRoleFixture::login(['admin/sales']);
    $second = null;
    try {
        $second = AdminRoleFixture::login(['admin/customer']);

        expect(Mage::getSingleton('admin/session')->isAllowed('customer'))->toBeTrue();
    } finally {
        if ($second) {
            AdminRoleFixture::delete($second);
        }
        AdminRoleFixture::delete($first);
    }
});

it('refuses the session user in reloadUser() once the user has no role', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    $roleId = (int) $user->getRole()->getId();
    try {
        $session = Mage::getSingleton('admin/session');
        expect($session->reloadUser())->toBeTrue();

        Mage::getModel('admin/user')->setRoleId($roleId)->setUserId((int) $user->getId())->deleteFromRole();

        expect($session->reloadUser())->toBeFalse();
    } finally {
        aclVersionDeleteUser($user);
        Mage::getModel('admin/role')->load($roleId)->delete();
    }
});

function aclVersionApiRequest(): Symfony\Component\HttpFoundation\Request
{
    return Symfony\Component\HttpFoundation\Request::create(
        '/api/admin/graphql',
        'POST',
        [],
        [Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE => 'pest-session-id'],
    );
}

it('refuses an admin API request whose session user has no role', function (): void {
    $user = AdminRoleFixture::login(['admin/sales']);
    $roleId = (int) $user->getRole()->getId();
    try {
        expect(Maho\ApiPlatform\Security\AdminSessionAuthenticator::startAdminSession(aclVersionApiRequest()))->toBeTrue();

        Mage::getModel('admin/user')->setRoleId($roleId)->setUserId((int) $user->getId())->deleteFromRole();

        expect(Maho\ApiPlatform\Security\AdminSessionAuthenticator::startAdminSession(aclVersionApiRequest()))->toBeFalse();
    } finally {
        aclVersionDeleteUser($user);
        Mage::getModel('admin/role')->load($roleId)->delete();
        // startAdminSession() starts a PHP session, and a later test cannot rename an open one
        Mage_Core_Model_Session_Abstract::closeSession();
    }
});
