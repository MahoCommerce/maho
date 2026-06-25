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
 * Behavioural coverage for RFC 8058 one-click unsubscribe on
 * Mage_Newsletter_SubscriberController::unsubscribeAction.
 *
 * The routing-level guard lives in MethodNotAllowedTest / NewsletterUnsubscribeMethodsTest
 * (the route must accept POST). This test exercises the action end to end: a POST must
 * actually flip the subscriber to UNSUBSCRIBED and return 200, and a POST with a wrong code
 * must leave the subscriber untouched while still returning 200 (no enumeration oracle, no
 * client-side error for a stale link).
 */

function createOneClickSubscriber(int $status): Mage_Newsletter_Model_Subscriber
{
    $store = Mage::app()->getDefaultStoreView();
    $uniqueId = uniqid('oneclick_', true);

    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setEmail("oneclick.{$uniqueId}@newsletter.test");
    $subscriber->setStoreId((int) $store->getId());
    $subscriber->setSubscriberStatus($status);
    $subscriber->setSubscriberConfirmCode(substr(md5($uniqueId), 0, 32));
    $subscriber->save();

    return $subscriber;
}

function postUnsubscribe(int $id, string $code): Mage_Core_Controller_Response_Http
{
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    // Keep the action self-contained: a non-empty unsubscribe-email template would make
    // unsubscribe() attempt a transactional send. Disabling it pins the test to the status
    // transition and HTTP code, not the mail transport.
    Mage::app()->getStore()->setConfig('newsletter/subscription/un_email_template', '');

    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register('symfony_session', $session);

    $symfonyRequest = SymfonyRequest::create(
        '/newsletter/subscriber/unsubscribe/?id=' . $id . '&code=' . $code,
        'POST',
    );
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setPathInfo('/newsletter/subscriber/unsubscribe');
    $request->setRouteName('newsletter');
    $request->setControllerName('subscriber');
    $request->setActionName('unsubscribe');
    $request->setControllerModule('Mage_Newsletter');
    $request->setDispatched(true);

    $response = new Mage_Core_Controller_Response_Http();
    (new Mage_Newsletter_SubscriberController($request, $response))->unsubscribeAction();

    return $response;
}

it('unsubscribes the subscriber and returns 200 on a one-click POST with a valid code', function () {
    $subscriber = createOneClickSubscriber(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

    $response = postUnsubscribe((int) $subscriber->getId(), (string) $subscriber->getCode());

    expect($response->getHttpResponseCode())->toBe(200);

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
    expect((int) $reloaded->getSubscriberStatus())
        ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
});

it('returns 200 but leaves the subscriber subscribed on a POST with a wrong code', function () {
    $subscriber = createOneClickSubscriber(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

    $response = postUnsubscribe((int) $subscriber->getId(), 'definitely-not-the-real-code');

    expect($response->getHttpResponseCode())->toBe(200);

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
    expect((int) $reloaded->getSubscriberStatus())
        ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
});
