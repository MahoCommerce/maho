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
 * A refused newsletter subscription is a validation result, not an application fault.
 * The visitor must see the message, and the exception log must stay untouched. The same
 * rule applies to an unsubscribe request that carries a stale code.
 */

function newsletterExceptionLogSize(): int
{
    clearstatcache();

    $total = 0;
    foreach (glob(Mage::getBaseDir('var') . DS . 'log' . DS . 'exception*.log') ?: [] as $file) {
        $total += (int) filesize($file);
    }

    return $total;
}

function newsletterSessionMessages(): string
{
    $texts = [];
    foreach (Mage::getSingleton('core/session')->getMessages()->getItems() as $message) {
        $texts[] = $message->getText();
    }

    return implode("\n", $texts);
}

function dispatchNewsletterAction(string $path, string $action, array $params, string $method): void
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($path, $method, $params));
    $request->setPathInfo($path);
    $request->setRouteName('newsletter');
    $request->setControllerName('subscriber');
    $request->setActionName($action);
    $request->setControllerModule('Mage_Newsletter');
    $request->setDispatched(true);

    $controller = new Mage_Newsletter_SubscriberController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig('dev/log/active', 1);
});

it('shows the error but logs nothing when the subscribe form carries an invalid address', function () {
    $sizeBefore = newsletterExceptionLogSize();

    dispatchNewsletterAction('/newsletter/subscriber/new', 'new', [
        'email' => 'newsletter-invalid-' . uniqid(),
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ], 'POST');

    expect(newsletterSessionMessages())->toContain('Please enter a valid email address.');
    expect(newsletterExceptionLogSize())->toBe($sizeBefore);
});

it('shows the error but logs nothing when an unsubscribe link carries a stale code', function () {
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setEmail('newsletter-stale-' . uniqid() . '@example.com');
    $subscriber->setStoreId((int) Mage::app()->getStore()->getId());
    $subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    $subscriber->setSubscriberConfirmCode(substr(md5(uniqid('', true)), 0, 32));
    $subscriber->save();

    $sizeBefore = newsletterExceptionLogSize();

    dispatchNewsletterAction('/newsletter/subscriber/unsubscribe', 'unsubscribe', [
        'id' => (int) $subscriber->getId(),
        'code' => 'definitely-not-the-real-code',
    ], 'GET');

    expect(newsletterSessionMessages())->toContain('Invalid subscription confirmation code.');
    expect(newsletterExceptionLogSize())->toBe($sizeBefore);
});
