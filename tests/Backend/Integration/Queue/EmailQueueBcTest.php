<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

uses(Tests\MahoBackendTestCase::class);

function makeLegacyEmailQueue(): Mage_Core_Model_Email_Queue
{
    $queue = Mage::getModel('core/email_queue');
    $queue->setEntityId(42)
        ->setEntityType('order')
        ->setEventType('new_order');
    $queue->setMessageBody('<p>Order confirmation</p>');
    $queue->setMessageParameters([
        'subject' => 'Your order',
        'is_plain' => false,
        'from_email' => 'shop@example.com',
        'from_name' => 'Shop',
        'reply_to' => 'reply@example.com',
        'return_to' => 'bounce@example.com',
    ]);
    $queue->addRecipients('customer@example.com', 'Customer');

    return $queue;
}

beforeEach(function () {
    QueueManager::reset();
    clearQueueTable();
});

afterEach(function () {
    clearQueueTable();
    QueueManager::reset();
});

it('still instantiates via the legacy model alias', function () {
    expect(Mage::getModel('core/email_queue'))->toBeInstanceOf(Mage_Core_Model_Email_Queue::class);
});

it('dispatches a SendMessage onto the email queue via addMessageToQueue', function () {
    makeLegacyEmailQueue()->addMessageToQueue();

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['queue'])->toBe('email');
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    expect($rows[0]['message_class'])->toBe(Mage_Core_Model_Email_SendMessage::class);

    $message = QueueManager::serializer()->decode([
        'body' => (string) $rows[0]['body'],
        'headers' => ['type' => (string) $rows[0]['message_class']],
    ])->getMessage();

    expect($message->subject)->toBe('Your order');
    expect($message->fromEmail)->toBe('shop@example.com');
    expect($message->replyTo)->toBe('reply@example.com');
    expect($message->returnPath)->toBe('bounce@example.com');
    expect($message->recipients)->toBe([['customer@example.com', 'Customer', Mage_Core_Model_Email_Queue::EMAIL_TYPE_TO]]);
    expect($message->entityId)->toBe(42);
    expect($message->entityType)->toBe('order');
    expect($message->eventType)->toBe('new_order');
});

it('deduplicates force-checked messages while one is pending', function () {
    $first = makeLegacyEmailQueue()->setIsForceCheck(true);
    $first->addMessageToQueue();
    $second = makeLegacyEmailQueue()->setIsForceCheck(true);
    $second->addMessageToQueue();
    expect(fetchQueueRows())->toHaveCount(1);

    $different = makeLegacyEmailQueue()->setIsForceCheck(true);
    $different->setMessageBody('<p>Different body</p>');
    $different->addMessageToQueue();
    expect(fetchQueueRows())->toHaveCount(2);
});

it('rejects a message without recipients', function () {
    $queue = Mage::getModel('core/email_queue');
    $queue->setMessageBody('body')->setMessageParameters(['subject' => 's']);

    expect(fn() => $queue->addMessageToQueue())
        ->toThrow(Mage_Core_Exception::class, 'Message recipients data must be set.');
});

it('always queues template sends outside developer mode', function () {
    $template = Mage::getModel('core/email_template');
    $template->setSenderName('Shop')
        ->setSenderEmail('shop@example.com')
        ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
        ->setTemplateText('Queued body')
        ->setTemplateSubject('Queued subject');

    expect($template->send('dest@example.com', 'Dest'))->toBeTrue();

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['queue'])->toBe('email');
    expect($rows[0]['message_class'])->toBe(Mage_Core_Model_Email_SendMessage::class);
});

it('carries dispatch-time headers on the queued message', function () {
    $template = Mage::getModel('core/email_template');
    $template->setSenderName('Shop')
        ->setSenderEmail('shop@example.com')
        ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
        ->setTemplateText('Body')
        ->setTemplateSubject('Subject')
        ->setTemplateId(42);

    expect($template->send('dest@example.com', 'Dest'))->toBeTrue();

    $rows = fetchQueueRows();
    $message = \Maho\Queue\QueueManager::serializer()->decode([
        'body' => (string) $rows[0]['body'],
        'headers' => ['type' => (string) $rows[0]['message_class']],
    ])->getMessage();
    expect($message->headers)->toHaveKey('X-Maho-Template');
    expect($message->headers['X-Maho-Template'])->toBe('42');
});
