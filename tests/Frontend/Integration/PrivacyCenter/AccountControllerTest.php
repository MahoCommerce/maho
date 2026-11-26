<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * Run preDispatch() of the Privacy Center account controller as a guest and return the action name afterwards.
 */
function privacyCenterActionAfterPreDispatch(string $action): string
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/privacy/account/$action", 'GET'));
    $request->setPathInfo("/privacy/account/$action");
    $request->setRouteName('privacy');
    $request->setControllerName('account');
    $request->setActionName($action);
    $request->setControllerModule('Maho_PrivacyCenter');
    $request->setDispatched(true);
    Mage::app()->setRequest($request);

    new Maho_PrivacyCenter_AccountController($request, new Mage_Core_Controller_Response_Http())->preDispatch();

    return $request->getActionName();
}

describe('Privacy Center account controller when the module is off', function () {
    beforeEach(function () {
        // The session models would otherwise name a PHP session that another suite already started in this process
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

        $this->store = Mage::app()->getStore();
        $this->before = $this->store->getConfig('privacy/general/enabled');
        $this->store->setConfig('privacy/general/enabled', '0');
    });

    afterEach(function () {
        $this->store->setConfig('privacy/general/enabled', $this->before);
    });

    it('sends the other pages to the no-route page', function () {
        expect(privacyCenterActionAfterPreDispatch('requests'))->toBe('noRoute');
    });

    it('still serves the terms and conditions page', function () {
        expect(privacyCenterActionAfterPreDispatch('agreements'))->toBe('agreements');
    });

    it('still serves the acceptance of the terms and conditions', function () {
        expect(privacyCenterActionAfterPreDispatch('acceptAgreements'))->toBe('acceptAgreements');
    });
});
