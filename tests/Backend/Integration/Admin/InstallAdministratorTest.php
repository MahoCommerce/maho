<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The installer creates the first admin user through Mage_Admin_Service_User, as admin:user:create does (#1348).
 */

afterEach(function (): void {
    Mage::getModel('admin/user')->load($this->username, 'username')->delete();
});

it('gives the first admin user the first top-level role and refuses invalid user data', function (): void {
    $this->username = 'pest_install_' . uniqid();
    $installer = Mage::getModel('install/installer');
    $data = [
        'username' => $this->username,
        'firstname' => 'Pest',
        'lastname' => 'Install',
        'email' => $this->username . '@example.com',
        'new_password' => 'pest-install-password-1234',
        'password_confirmation' => 'pest-install-password-1234',
    ];

    expect($installer->createAdministrator($data))->toBeTrue();

    $user = Mage::getModel('admin/user')->load($this->username, 'username');
    $invalid = ['username' => $this->username . '_bad', 'firstname' => '', 'lastname' => '', 'email' => ''];
    expect((int) $user->getRole()->getId())->toBe(Mage::getService('admin/user')->getDefaultRoleId())
        ->and(fn() => $installer->createAdministrator($invalid))->toThrow(Exception::class);
});
