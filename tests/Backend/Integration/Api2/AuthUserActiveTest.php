<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * HackerOne #4084309: an OAuth 1.0a access token stays valid after its admin user or customer
 * is deactivated, so the legacy REST API checks the account on each request.
 */

function api2ActiveCreateAdmin(): Mage_Admin_Model_User
{
    $username = 'api2_active_' . uniqid();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Api2',
        'lastname' => 'Active',
        'email' => $username . '@example.test',
        'password' => 'Temporary-P4ssword!',
        'is_active' => 1,
    ])->save();

    return $user;
}

function api2ActiveCreateCustomer(): Mage_Customer_Model_Customer
{
    /** @var Mage_Customer_Model_Customer $customer */
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(Mage::app()->getWebsite(true)->getId())
        ->setGroupId(1)
        ->setFirstname('Api2')
        ->setLastname('Active')
        ->setEmail('api2-active-' . uniqid() . '@example.test')
        ->setForceConfirmed()
        ->save();

    return $customer;
}

function api2ActiveDeactivateCustomer(Mage_Customer_Model_Customer $customer): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('customer/entity'),
        ['is_active' => 0],
        ['entity_id = ?' => (int) $customer->getId()],
    );
}

function api2ActiveRoleError(Mage_Api2_Model_Auth_User_Abstract $apiUser): ?int
{
    try {
        $apiUser->getRole();
    } catch (Mage_Api2_Exception $e) {
        return $e->getCode();
    }
    return null;
}

it('gives the role of an active admin user', function () {
    $user = api2ActiveCreateAdmin();
    /** @var Mage_Api2_Model_Acl_Global_Role $role */
    $role = Mage::getModel('api2/acl_global_role');
    $role->setRoleName('Api2 Active ' . uniqid())->save();

    try {
        Mage::getResourceModel('api2/acl_global_role')->saveAdminToRoleRelation($user->getId(), $role->getId());

        $apiUser = Mage::getModel('api2/auth_user_admin')->setUserId($user->getId());

        expect((int) $apiUser->getRole())->toBe((int) $role->getId());
    } finally {
        $user->delete();
        $role->delete();
    }
});

it('refuses an admin user that is deactivated', function () {
    $user = api2ActiveCreateAdmin();

    try {
        Mage::getModel('admin/user')->load($user->getId())->setIsActive(false)->save();

        $apiUser = Mage::getModel('api2/auth_user_admin')->setUserId($user->getId());

        expect(api2ActiveRoleError($apiUser))->toBe(Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
    } finally {
        $user->delete();
    }
});

it('refuses an admin user that no longer exists', function () {
    $user = api2ActiveCreateAdmin();
    $userId = (int) $user->getId();
    $user->delete();

    $apiUser = Mage::getModel('api2/auth_user_admin')->setUserId($userId);

    expect(api2ActiveRoleError($apiUser))->toBe(Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
});

it('gives the customer role to an active customer', function () {
    $customer = api2ActiveCreateCustomer();

    try {
        $apiUser = Mage::getModel('api2/auth_user_customer')->setUserId($customer->getId());

        expect((int) $apiUser->getRole())->toBe(Mage_Api2_Model_Acl_Global_Role::ROLE_CUSTOMER_ID);
    } finally {
        $customer->delete();
    }
});

it('refuses a customer that is deactivated', function () {
    $customer = api2ActiveCreateCustomer();

    try {
        api2ActiveDeactivateCustomer($customer);

        $apiUser = Mage::getModel('api2/auth_user_customer')->setUserId($customer->getId());

        expect(api2ActiveRoleError($apiUser))->toBe(Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
    } finally {
        $customer->delete();
    }
});

it('refuses a customer that no longer exists', function () {
    $customer = api2ActiveCreateCustomer();
    $customerId = (int) $customer->getId();
    $customer->delete();

    $apiUser = Mage::getModel('api2/auth_user_customer')->setUserId($customerId);

    expect(api2ActiveRoleError($apiUser))->toBe(Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
});
