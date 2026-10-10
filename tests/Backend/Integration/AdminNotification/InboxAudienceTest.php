<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<string> $resources
 */
function inboxAudienceAdmin(string $username, array $resources): Mage_Admin_Model_User
{
    $role = Mage::getModel('admin/role')->setData([
        'role_name' => $username . '_role',
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ]);
    $role->save();
    Mage::getModel('admin/rules')->setRoleId($role->getId())->setResources($resources)->saveRel();
    $user = Mage::getModel('admin/user')->setData([
        'username' => $username,
        'firstname' => 'Pest',
        'lastname' => 'Inbox',
        'email' => $username . '@example.test',
        'password' => 'pest-inbox-password-1234',
        'is_active' => 1,
    ]);
    $user->save();
    Mage::getModel('admin/user')->setRoleId($role->getId())->setUserId($user->getId())->add();

    return Mage::getModel('admin/user')->load($user->getId());
}

function inboxAudienceLogin(?Mage_Admin_Model_User $user): void
{
    $session = Mage::getSingleton('admin/session');
    $session->setUser($user);
    $user === null ? $session->setAcl(null) : $session->refreshAcl($user);
}

/**
 * @return list<string>
 */
function inboxAudienceTitles(): array
{
    $collection = Mage::getModel('adminnotification/inbox')->getCollection()->addRemoveFilter()->addAudienceFilter();
    $collection->addFieldToFilter('title', ['like' => 'Pest audience %']);

    return array_values(array_map(static fn($row): string => (string) $row->getTitle(), $collection->getItems()));
}

it('shows a notification to its own audience only: everyone, one administrator, or the ones allowed a resource', function (): void {
    $writer = inboxAudienceAdmin('pest_inbox_writer', ['admin/cms/page']);
    $reader = inboxAudienceAdmin('pest_inbox_reader', ['admin/sales/order']);
    $inbox = Mage::getModel('adminnotification/inbox');
    $notice = Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE;
    try {
        $inbox->add($notice, 'Pest audience everyone', '');
        $inbox->add($notice, 'Pest audience writer', '', '', true, (int) $writer->getId());
        $inbox->add($notice, 'Pest audience pages', '', '', true, null, 'admin/cms/page');

        inboxAudienceLogin($writer);
        expect(inboxAudienceTitles())->toEqualCanonicalizing(['Pest audience everyone', 'Pest audience writer', 'Pest audience pages']);

        inboxAudienceLogin($reader);
        expect(inboxAudienceTitles())->toBe(['Pest audience everyone']);
        $hidden = Mage::getModel('adminnotification/inbox')->load('Pest audience writer', 'title');
        expect($hidden->isVisibleToCurrentAdmin())->toBeFalse();
        expect(Mage::getModel('adminnotification/inbox')->load('Pest audience everyone', 'title')->isVisibleToCurrentAdmin())->toBeTrue();
    } finally {
        inboxAudienceLogin(null);
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('adminnotification/inbox'),
            ['title LIKE ?' => 'Pest audience %'],
        );
        foreach ([$writer, $reader] as $user) {
            $role = $user->getRole();
            $user->delete();
            if ($role->getId()) {
                Mage::getModel('admin/role')->load($role->getId())->delete();
            }
        }
    }
});
