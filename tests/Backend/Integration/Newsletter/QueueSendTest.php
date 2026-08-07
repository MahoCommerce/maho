<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

uses(Tests\MahoBackendTestCase::class);

const NEWSLETTER_TEST_PREFIX = 'queue-send-test';

function makeNewsletterCampaign(int $recipients, ?string $startAt = 'now', int $status = Mage_Newsletter_Model_Queue::STATUS_SENDING): Mage_Newsletter_Model_Queue
{
    /** @var Mage_Newsletter_Model_Template $template */
    $template = Mage::getModel('newsletter/template');
    $template->setTemplateCode(NEWSLETTER_TEST_PREFIX . ' ' . uniqid())
        ->setTemplateType(Mage_Newsletter_Model_Template::TYPE_HTML)
        ->setTemplateSubject('Campaign')
        ->setTemplateSenderName('Shop')
        ->setTemplateSenderEmail('shop@example.com')
        ->setTemplateText('<p>Hello</p>')
        ->save();

    /** @var Mage_Newsletter_Model_Queue $queue */
    $queue = Mage::getModel('newsletter/queue');
    $queue->setTemplateId($template->getId())
        ->setNewsletterType(Mage_Newsletter_Model_Template::TYPE_HTML)
        ->setNewsletterSubject('Campaign')
        ->setNewsletterSenderName('Shop')
        ->setNewsletterSenderEmail('shop@example.com')
        ->setNewsletterText('<p>Hello</p>')
        ->setQueueStatus($status)
        ->setQueueStartAt($startAt === null ? null : Mage::app()->getLocale()->formatDateForDb($startAt))
        ->save();

    $subscriberIds = [];
    for ($i = 0; $i < $recipients; $i++) {
        /** @var Mage_Newsletter_Model_Subscriber $subscriber */
        $subscriber = Mage::getModel('newsletter/subscriber');
        $subscriber->setStoreId(1)
            ->setSubscriberEmail(NEWSLETTER_TEST_PREFIX . '-' . uniqid() . '@example.com')
            ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->save();
        $subscriberIds[] = (int) $subscriber->getId();
    }

    if ($subscriberIds !== []) {
        $queue->addSubscribersToQueue($subscriberIds);
    }

    return $queue;
}

function newsletterQueueRows(?int $queueId = null): array
{
    $rows = array_values(array_filter(
        fetchQueueRows(),
        fn(array $row): bool => $row['message_class'] === Mage_Newsletter_Model_Queue_SendMessage::class,
    ));

    if ($queueId === null) {
        return $rows;
    }

    return array_values(array_filter($rows, function (array $row) use ($queueId): bool {
        $envelope = QueueManager::serializer()->decode([
            'body' => (string) $row['body'],
            'headers' => ['type' => (string) $row['message_class']],
        ]);

        return $envelope->getMessage()->queueId === $queueId;
    }));
}

function newsletterEmailRows(): array
{
    return array_values(array_filter(
        fetchQueueRows(),
        fn(array $row): bool => $row['message_class'] === Mage_Core_Model_Email_SendMessage::class,
    ));
}

function newsletterLinkRows(int $queueId): array
{
    $adapter = queueAdapter();
    $table = Mage::getSingleton('core/resource')->getTableName('newsletter/queue_link');

    return $adapter->fetchAll(
        $adapter->select()->from($table)->where('queue_id = ?', $queueId)->order('queue_link_id ASC'),
    );
}

beforeEach(function () {
    QueueManager::reset();
    clearQueueTable();
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Helper_Data::XML_PATH_SEND_BATCH_SIZE, '2');
});

afterEach(function () {
    clearQueueTable();
    QueueManager::reset();

    // Campaigns, links and store links all cascade from the template
    $templates = Mage::getResourceModel('newsletter/template_collection')
        ->addFieldToFilter('template_code', ['like' => NEWSLETTER_TEST_PREFIX . '%']);
    foreach ($templates as $template) {
        $template->delete();
    }
    $subscribers = Mage::getResourceModel('newsletter/subscriber_collection')
        ->addFieldToFilter('subscriber_email', ['like' => NEWSLETTER_TEST_PREFIX . '%']);
    foreach ($subscribers as $subscriber) {
        $subscriber->delete();
    }
});

it('dispatches one batch onto the newsletter queue and dedupes repeat dispatches', function () {
    $queue = makeNewsletterCampaign(1);

    $queue->scheduleSending();
    $queue->scheduleSending();

    $rows = newsletterQueueRows((int) $queue->getId());
    expect($rows)->toHaveCount(1);
    expect($rows[0]['queue'])->toBe(Mage_Newsletter_Model_Queue::QUEUE_NAME);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    expect($rows[0]['dedupe_key'])->toBe('newsletter_' . $queue->getId());
});

it('queues nothing for a campaign scheduled for later', function () {
    $queue = makeNewsletterCampaign(1, '+1 hour', Mage_Newsletter_Model_Queue::STATUS_NEVER);

    $queue->scheduleSending();

    expect(newsletterQueueRows((int) $queue->getId()))->toHaveCount(0);
    expect($queue->isReadyToSend())->toBeFalse();
});

it('dispatches a scheduled campaign once its start date is moved up', function () {
    $queue = makeNewsletterCampaign(1, '+1 day', Mage_Newsletter_Model_Queue::STATUS_NEVER);

    $queue->scheduleSending();
    $queue->setQueueStartAt(Mage::app()->getLocale()->formatDateForDb('now'))->save();
    $queue->scheduleSending();

    $rows = newsletterQueueRows((int) $queue->getId());
    expect($rows)->toHaveCount(1);
    expect($rows[0]['available_at'])->toBeLessThanOrEqual(Mage_Core_Model_Locale::nowUtc());
});

it('sends a batch, marks its recipients and chains the next batch', function () {
    $queue = makeNewsletterCampaign(3);

    Mage::getSingleton('newsletter/queue_sendMessageHandler')
        ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));

    $sent = array_filter(newsletterLinkRows((int) $queue->getId()), fn(array $row): bool => $row['letter_sent_at'] !== null);
    expect($sent)->toHaveCount(2);

    $emails = newsletterEmailRows();
    expect($emails)->toHaveCount(2);
    expect($emails[0]['queue'])->toBe(Mage_Newsletter_Model_Queue::QUEUE_NAME);

    expect(newsletterQueueRows((int) $queue->getId()))->toHaveCount(1);

    $queue->load($queue->getId());
    expect((int) $queue->getQueueStatus())->toBe(Mage_Newsletter_Model_Queue::STATUS_SENDING);
    expect($queue->getUnsentSubscribersCount())->toBe(1);
});

it('finishes the campaign once the last batch has gone out', function () {
    $queue = makeNewsletterCampaign(1);

    Mage::getSingleton('newsletter/queue_sendMessageHandler')
        ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));

    expect(newsletterEmailRows())->toHaveCount(1);
    expect(newsletterQueueRows((int) $queue->getId()))->toHaveCount(0);

    $queue->load($queue->getId());
    expect((int) $queue->getQueueStatus())->toBe(Mage_Newsletter_Model_Queue::STATUS_SENT);
    expect($queue->getQueueFinishAt())->not->toBeNull();
});

it('starts a campaign left as not sent once its batch is handled', function () {
    $queue = makeNewsletterCampaign(3, 'now', Mage_Newsletter_Model_Queue::STATUS_NEVER);

    Mage::getSingleton('newsletter/queue_sendMessageHandler')
        ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));

    $queue->load($queue->getId());
    expect((int) $queue->getQueueStatus())->toBe(Mage_Newsletter_Model_Queue::STATUS_SENDING);
});

it('sends nothing for a paused campaign', function () {
    $queue = makeNewsletterCampaign(2, 'now', Mage_Newsletter_Model_Queue::STATUS_PAUSE);

    $queue->scheduleSending();
    Mage::getSingleton('newsletter/queue_sendMessageHandler')
        ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));

    expect(fetchQueueRows())->toHaveCount(0);
    $sent = array_filter(newsletterLinkRows((int) $queue->getId()), fn(array $row): bool => $row['letter_sent_at'] !== null);
    expect($sent)->toBeEmpty();
});

it('leaves the campaign alone while another batch holds the lock', function () {
    $queue = makeNewsletterCampaign(2);
    $lock = Mage::getSingleton('core/lock');

    expect($lock->acquire('newsletter_queue_' . $queue->getId()))->toBeTrue();
    try {
        Mage::getSingleton('newsletter/queue_sendMessageHandler')
            ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));
    } finally {
        $lock->release('newsletter_queue_' . $queue->getId());
    }

    expect(fetchQueueRows())->toHaveCount(0);
});

it('links recipients at the first batch, not at save', function () {
    $queue = makeNewsletterCampaign(0, 'now', Mage_Newsletter_Model_Queue::STATUS_NEVER);

    /** @var Mage_Newsletter_Model_Subscriber $subscriber */
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setStoreId(1)
        ->setSubscriberEmail(NEWSLETTER_TEST_PREFIX . '-' . uniqid() . '@example.com')
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->save();

    $queue->setStores([1])->save();
    expect(newsletterLinkRows((int) $queue->getId()))->toHaveCount(0);

    Mage::getSingleton('newsletter/queue_sendMessageHandler')
        ->__invoke(new Mage_Newsletter_Model_Queue_SendMessage((int) $queue->getId()));

    $linked = array_map('intval', array_column(newsletterLinkRows((int) $queue->getId()), 'subscriber_id'));
    expect($linked)->toContain((int) $subscriber->getId());
});

it('drops a not-yet-sent audience when the campaign stores change', function () {
    $queue = makeNewsletterCampaign(2, 'now', Mage_Newsletter_Model_Queue::STATUS_NEVER);
    expect(newsletterLinkRows((int) $queue->getId()))->toHaveCount(2);

    $queue->setStores([])->save();

    expect(newsletterLinkRows((int) $queue->getId()))->toHaveCount(0);
});

it('schedules due campaigns only', function () {
    $due = makeNewsletterCampaign(1);
    $future = makeNewsletterCampaign(1, '+1 day', Mage_Newsletter_Model_Queue::STATUS_NEVER);

    Mage::helper('newsletter')->scheduleDueQueues();

    expect(newsletterQueueRows((int) $due->getId()))->toHaveCount(1);
    expect(newsletterQueueRows((int) $future->getId()))->toHaveCount(0);
});
