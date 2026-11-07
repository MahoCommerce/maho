<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

function accountSaveExceptionLogSize(): int
{
    clearstatcache();

    $total = 0;
    foreach (glob(Mage::getBaseDir('var') . DS . 'log' . DS . 'exception*.log') ?: [] as $file) {
        $total += (int) filesize($file);
    }

    return $total;
}

beforeEach(function () {
    $this->user = AdminRoleFixture::login(['admin/system/myaccount']);
    Mage::app()->getStore()->setConfig('dev/log/active', 1);
});

afterEach(function () {
    AdminRoleFixture::delete($this->user);
});

it('logs an unexpected exception of the account save and shows the generic error', function () {
    // processCreate() throws a WebAuthnException, not a Mage_Core_Exception, for client data it cannot read
    Mage::getSingleton('admin/session')->setPasskeyChallenge(random_bytes(32));
    $params = [
        'username' => $this->user->getUsername(),
        'firstname' => $this->user->getFirstname(),
        'lastname' => $this->user->getLastname(),
        'email' => $this->user->getEmail(),
        'current_password' => 'pest-role-password-1234',
        'passkey_value' => json_encode([
            'attestationObject' => base64_encode('not an attestation'),
            'clientDataJSON' => base64_encode('not client data'),
        ]),
    ];
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/system_account/save', 'POST', $params),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName('system_account')
        ->setActionName('save')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    $sizeBefore = accountSaveExceptionLogSize();

    $controller = new Mage_Adminhtml_System_AccountController($request, new Mage_Core_Controller_Response_Http());
    try {
        $controller->saveAction();
    } catch (Throwable) {
        // The trailing redirect builds an admin url that needs no assertion here
    }

    $errors = array_map(
        fn($message) => $message->getText(),
        Mage::getSingleton('adminhtml/session')->getMessages(true)->getErrors(),
    );
    expect($errors)->toBe(['An error occurred while saving account.'])
        ->and(accountSaveExceptionLogSize())->toBeGreaterThan($sizeBefore);
});
