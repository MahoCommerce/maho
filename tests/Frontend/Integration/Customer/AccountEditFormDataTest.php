<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The account edit page repopulates the form from session data left by a failed submit. That
 * data is the raw POST, so it can carry attributes outside the customer_account_edit form and a
 * foreign entity id. The repopulation must keep only the form's own attributes and must never
 * change the identity of the logged-in customer.
 */

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();
});

it('drops foreign attributes and keeps the logged-in identity when repopulating the edit form', function () {
    $customerId    = 999001;
    $originalGroup = 1;

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
    $customer->setId($customerId)
        ->setEntityId($customerId)
        ->setGroupId($originalGroup)
        ->setEmail('edit-' . uniqid() . '@example.com')
        ->setFirstname('Original')
        ->setLastname('User');

    Mage::getSingleton('customer/session')->setCustomer($customer);

    // Raw POST left by a failed edit submit: one real form field plus a foreign group and id.
    Mage::getSingleton('customer/session')->setCustomerFormData([
        'firstname' => 'Edited',
        'group_id'  => $originalGroup + 1,
        'entity_id' => 999999,
    ]);

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/account/edit', 'GET'),
    );
    $request->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName('edit')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Customer_AccountController(
        $request,
        new Mage_Core_Controller_Response_Http(),
    );

    try {
        $controller->editAction();
    } catch (Throwable) {
        // Rendering the page is not under test; the repopulation runs before any render.
    }

    $sessionCustomer = Mage::getSingleton('customer/session')->getCustomer();

    // Guard: the repopulation path ran, so the allowed field was applied.
    expect($sessionCustomer->getFirstname())->toBe('Edited')
        // The foreign attribute and the foreign id were dropped.
        ->and((int) $sessionCustomer->getId())->toBe($customerId)
        ->and((int) $sessionCustomer->getGroupId())->toBe($originalGroup);

    Mage::getSingleton('customer/session')->logout();
});
