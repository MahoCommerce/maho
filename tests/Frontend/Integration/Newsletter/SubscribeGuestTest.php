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
 * The footer form treats every address the same way. A registered customer who is not logged
 * in subscribes like anybody else, and the form never reveals that an address owns an account.
 * The confirmation click is what proves that the address owner agreed, so the endpoint also
 * shares the newsletter_subscribe budget with the REST and GraphQL path.
 */

function subscribeFromFooter(string $email): void
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        '/newsletter/subscriber/new',
        'POST',
        ['email' => $email, 'form_key' => Mage::getSingleton('core/session')->getFormKey()],
    ));
    $request->setPathInfo('/newsletter/subscriber/new');
    $request->setRouteName('newsletter');
    $request->setControllerName('subscriber');
    $request->setActionName('new');
    $request->setControllerModule('Mage_Newsletter');
    $request->setDispatched(true);

    $controller = new Mage_Newsletter_SubscriberController($request, new Mage_Core_Controller_Response_Http());
    $controller->newAction();
}

function footerMessages(): string
{
    $texts = [];
    foreach (Mage::getSingleton('core/session')->getMessages(true)->getItems() as $message) {
        $texts[] = $message->getText();
    }

    return implode("\n", $texts);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG, '1');
    // Pin the action to the status transition. A non-empty template would send real mail.
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRM_EMAIL_TEMPLATE, '');
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_SUCCESS_EMAIL_TEMPLATE, '');

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
});

it('subscribes a registered customer who is not logged in', function () {
    $email = 'footer-owner-' . uniqid() . '@example.com';

    Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setEmail($email)
        ->setFirstname('Owner')
        ->setLastname('User')
        ->setPassword('Password123!')
        ->save();

    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '1');

    subscribeFromFooter($email);

    $messages = footerMessages();
    expect($messages)->not->toContain('already assigned to another user');
    expect($messages)->toContain('Confirmation request has been sent.');

    $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
    expect((int) $subscriber->getId())->toBeGreaterThan(0);
    expect((int) $subscriber->getSubscriberStatus())
        ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE);
});

it('requires a confirmation click by default so the address owner decides', function () {
    expect(Mage::getStoreConfigFlag(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG))->toBeTrue();
});

it('stops a client that subscribes more addresses than the budget allows', function () {
    Mage::app()->getStore()->setConfig('system/rate_limit/newsletter_subscribe', '2');
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '0');

    $blocked = 'footer-flood-3-' . uniqid() . '@example.com';

    subscribeFromFooter('footer-flood-1-' . uniqid() . '@example.com');
    subscribeFromFooter('footer-flood-2-' . uniqid() . '@example.com');
    footerMessages();

    subscribeFromFooter($blocked);

    expect(footerMessages())->toContain('Too Soon');
    expect((int) Mage::getModel('newsletter/subscriber')->loadByEmail($blocked)->getId())->toBe(0);
});
