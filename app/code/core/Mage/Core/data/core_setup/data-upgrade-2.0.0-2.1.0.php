<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * Move unsent legacy email queue rows onto the generic message queue, then
 * empty the legacy tables. The core_email_queue/core_email_queue_recipients
 * declarations are dropped from sql/schema.php in the next release.
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$queueTable = $installer->getTable('core_email_queue');
$recipientsTable = $installer->getTable('core_email_queue_recipients');

$select = $connection->select()
    ->from($queueTable)
    ->where('processed_at IS NULL');

foreach ($connection->fetchAll($select) as $row) {
    $recipients = [];
    $recipientRows = $connection->fetchAll(
        $connection->select()
            ->from($recipientsTable, ['recipient_email', 'recipient_name', 'email_type'])
            ->where('message_id = ?', (int) $row['message_id']),
    );
    foreach ($recipientRows as $recipientRow) {
        $recipients[] = [
            (string) $recipientRow['recipient_email'],
            (string) $recipientRow['recipient_name'],
            (int) $recipientRow['email_type'],
        ];
    }
    if ($recipients === []) {
        continue;
    }

    try {
        $parameters = Mage::helper('core')->jsonDecode((string) $row['message_parameters']);
    } catch (JsonException) {
        $parameters = [];
    }
    $parameters = new \Maho\DataObject(is_array($parameters) ? $parameters : []);

    \Maho\Queue\QueueManager::dispatch(
        new Mage_Core_Model_Email_SendMessage(
            subject: (string) $parameters->getSubject(),
            body: (string) $row['message_body'],
            isPlain: (bool) $parameters->getIsPlain(),
            fromEmail: (string) $parameters->getFromEmail(),
            fromName: (string) $parameters->getFromName(),
            recipients: $recipients,
            replyTo: $parameters->getReplyTo() !== null ? (string) $parameters->getReplyTo() : null,
            returnPath: $parameters->getReturnTo() !== null ? (string) $parameters->getReturnTo() : null,
            attachments: (array) $parameters->getAttachments(),
            entityId: $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            entityType: $row['entity_type'] !== null ? (string) $row['entity_type'] : null,
            eventType: $row['event_type'] !== null ? (string) $row['event_type'] : null,
        ),
        queue: Mage_Core_Model_Email_Queue::QUEUE_NAME,
    );
}

$connection->delete($recipientsTable);
$connection->delete($queueTable);

$installer->endSetup();
