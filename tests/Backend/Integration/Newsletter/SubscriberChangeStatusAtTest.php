<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * newsletter_subscriber.change_status_at is stamped by the resource model on every
 * real status transition. Customer segmentation ("Status Change Date") and the
 * newsletter API field both read it, so a save that does not touch the status must
 * leave it alone and an explicitly supplied value must survive.
 */

function makeChangeStatusSubscriber(int $status): Mage_Newsletter_Model_Subscriber
{
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setEmail('changestatus.' . uniqid('', true) . '@newsletter.test');
    $subscriber->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $subscriber->setSubscriberStatus($status);
    $subscriber->save();

    return $subscriber;
}

function backdateChangeStatusAt(int $subscriberId, string $date): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('newsletter/subscriber'),
        ['change_status_at' => $date],
        ['subscriber_id = ?' => $subscriberId],
    );
}

it('stamps change_status_at when a subscriber is created', function () {
    $subscriber = makeChangeStatusSubscriber(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

    expect($reloaded->getChangeStatusAt())->not()->toBeEmpty();
    expect(strtotime($reloaded->getChangeStatusAt() . ' UTC'))->toBeGreaterThan(time() - 300);
    expect(strtotime($reloaded->getChangeStatusAt() . ' UTC'))->toBeLessThan(time() + 120);
});

it('restamps change_status_at when the status changes', function () {
    $subscriber = makeChangeStatusSubscriber(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    backdateChangeStatusAt((int) $subscriber->getId(), '2020-01-01 00:00:00');

    $subscriber = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
    $subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED)->save();

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

    expect(strtotime($reloaded->getChangeStatusAt() . ' UTC'))->toBeGreaterThan(time() - 300);
});

it('leaves change_status_at untouched on a save that does not change the status', function () {
    $subscriber = makeChangeStatusSubscriber(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    backdateChangeStatusAt((int) $subscriber->getId(), '2020-01-01 00:00:00');

    $subscriber = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
    $subscriber->setSubscriberConfirmCode($subscriber->randomSequence())->save();

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

    expect(date('Y-m-d', strtotime($reloaded->getChangeStatusAt())))->toBe('2020-01-01');
});

it('keeps an explicitly supplied change_status_at', function () {
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setEmail('changestatus.' . uniqid('', true) . '@newsletter.test');
    $subscriber->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());
    $subscriber->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    $subscriber->setChangeStatusAt('2019-06-15 10:00:00');
    $subscriber->save();

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

    expect(date('Y-m-d', strtotime($reloaded->getChangeStatusAt())))->toBe('2019-06-15');
});

it('stamps change_status_at through subscribe()', function () {
    Mage::app()->getStore()->setConfig('newsletter/subscription/confirm', '0');
    Mage::app()->getStore()->setConfig('newsletter/subscription/success_email_template', '');

    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->subscribe('changestatus.' . uniqid('', true) . '@newsletter.test');

    $reloaded = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

    expect((int) $reloaded->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    expect(strtotime($reloaded->getChangeStatusAt() . ' UTC'))->toBeGreaterThan(time() - 300);
});
