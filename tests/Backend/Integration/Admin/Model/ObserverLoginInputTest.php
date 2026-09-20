<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Admin_Model_Session::login() takes string credentials, so a client that posts
 * login[username][]=x used to reach it with an array and end the request with a TypeError
 * instead of the normal failed login.
 */
it('treats array-shaped admin login credentials as a normal failed login', function () {
    $request = Mage::app()->getRequest();
    $request->setInternallyForwarded(false);
    $request->setPost('form_key', Mage::getSingleton('core/session')->getFormKey());
    $request->setPost('login', [
        'username' => ['admin'],
        'password' => ['secret'],
        'twofa_verification_code' => ['123456'],
    ]);

    $observer = new Mage_Admin_Model_Observer();
    $observer->actionPreDispatchAdmin(new \Maho\Event\Observer());

    expect(Mage::getSingleton('admin/session')->getUser())->toBeNull()
        ->and($request->getPost('login'))->toBeNull()
        ->and($request->getActionName())->toBe('login');
});
