<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The newsletter tab of the admin customer page filters the queues by the subscriber of the
 * customer. A customer who never subscribed has no subscriber ID. The filter once compared the
 * integer column with '', which PostgreSQL rejects, so the customer page did not open.
 */
it('finds no queue for a customer who has no subscriber, without comparing the ID with an empty string', function () {
    $collection = Mage::getResourceModel('newsletter/queue_collection')
        ->addTemplateInfo()
        ->addSubscriberFilter(null);

    expect((string) $collection->getSelect())->not->toContain("subscriber_id = ''")
        ->and($collection->load()->count())->toBe(0);
});

it('finds the queues of a subscriber', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->setSubscriberEmail('queue-filter-' . uniqid() . '@example.com')
            ->setStoreId(1)
            ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->save();
        $template = Mage::getModel('newsletter/template')
            ->setTemplateCode('queue-filter-' . uniqid())
            ->setTemplateSubject('Queue filter test')
            ->setTemplateText('Text')
            ->setTemplateSenderName('Store')
            ->setTemplateSenderEmail('store@example.com')
            ->setTemplateType(Mage_Core_Model_Template::TYPE_HTML)
            ->save();
        $queue = Mage::getModel('newsletter/queue')
            ->setTemplateId((int) $template->getId())
            ->setNewsletterSubject('Queue filter test')
            ->setNewsletterText('Text')
            ->setNewsletterSenderName('Store')
            ->setNewsletterSenderEmail('store@example.com')
            ->setQueueStatus(Mage_Newsletter_Model_Queue::STATUS_NEVER)
            ->save();
        $adapter->insert(Mage::getSingleton('core/resource')->getTableName('newsletter/queue_link'), [
            'queue_id' => (int) $queue->getId(),
            'subscriber_id' => (int) $subscriber->getId(),
        ]);

        $ids = Mage::getResourceModel('newsletter/queue_collection')
            ->addSubscriberFilter((int) $subscriber->getId())
            ->getColumnValues('queue_id');

        expect(array_map(intval(...), $ids))->toBe([(int) $queue->getId()]);
    } finally {
        $adapter->rollBack();
    }
});
