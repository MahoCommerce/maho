<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;

/**
 * Back-compat shim over the Maho message queue. Callers keep building this
 * object and calling addMessageToQueue(); the message now lands on the
 * generic queue as a Mage_Core_Model_Email_SendMessage handled by
 * Mage_Core_Model_Email_SendMessageHandler, with retries and backoff.
 * New code should dispatch a Mage_Core_Model_Email_SendMessage via
 * \Maho\Queue\QueueManager::dispatch() directly.
 *
 * @method $this setMessageParameters(array $value)
 */
class Mage_Core_Model_Email_Queue extends \Maho\DataObject
{
    /**
     * Email types
     */
    public const EMAIL_TYPE_TO  = 0;
    public const EMAIL_TYPE_CC  = 1;
    public const EMAIL_TYPE_BCC = 2;

    public const QUEUE_NAME = 'email';

    /**
     * Store message recipients list
     *
     * @var list<array{0: string, 1: string, 2: int}>
     */
    protected array $_recipients = [];

    /**
     * Dispatch this message onto the queue.
     *
     * With is_force_check set, a dedupe key derived from entity, event, body,
     * and recipients makes re-dispatching a no-op while an identical message
     * is still pending (replaces the old wasEmailQueued() check).
     *
     * @return $this
     */
    public function addMessageToQueue(): self
    {
        if (empty($this->_recipients) || empty($this->_recipients[0])) {
            $error = Mage::helper('core')->__('Message recipients data must be set.');
            Mage::throwException($error);
        }

        $parameters = new \Maho\DataObject((array) $this->getMessageParameters());
        $body = (string) $this->getMessageBody();

        $message = new Mage_Core_Model_Email_SendMessage(
            subject: (string) $parameters->getSubject(),
            body: $body,
            isPlain: (bool) $parameters->getIsPlain(),
            fromEmail: (string) $parameters->getFromEmail(),
            fromName: (string) $parameters->getFromName(),
            recipients: $this->_recipients,
            replyTo: $parameters->getReplyTo() !== null ? (string) $parameters->getReplyTo() : null,
            returnPath: $parameters->getReturnTo() !== null ? (string) $parameters->getReturnTo() : null,
            attachments: (array) $parameters->getAttachments(),
            entityId: $this->getData('entity_id') !== null ? (int) $this->getData('entity_id') : null,
            entityType: $this->getData('entity_type') !== null ? (string) $this->getData('entity_type') : null,
            eventType: $this->getData('event_type') !== null ? (string) $this->getData('event_type') : null,
        );

        $dedupeKey = null;
        if ($this->getIsForceCheck()) {
            $dedupeKey = md5(implode('|', [
                (string) $this->getData('entity_id'),
                (string) $this->getData('entity_type'),
                (string) $this->getData('event_type'),
                md5($body),
                md5(serialize($this->_recipients)),
            ]));
        }

        try {
            QueueManager::dispatch($message, queue: self::QUEUE_NAME, dedupeKey: $dedupeKey);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Add message recipients by email type
     *
     * @param array|string $emails
     * @param array|string|null $names
     * @param int $type
     * @return $this
     */
    public function addRecipients($emails, $names = null, $type = self::EMAIL_TYPE_TO)
    {
        $_supportedEmailTypes = [
            self::EMAIL_TYPE_TO,
            self::EMAIL_TYPE_CC,
            self::EMAIL_TYPE_BCC,
        ];
        $type = in_array($type, $_supportedEmailTypes) ? $type : self::EMAIL_TYPE_TO;
        $emails = array_values((array) $emails);
        $names = is_array($names) ? $names : (array) $names;
        $names = array_values($names);
        foreach ($emails as $key => $email) {
            $this->_recipients[] = [$email, $names[$key] ?? '', $type];
        }
        return $this;
    }

    /**
     * Clean recipients data from object
     *
     * @return $this
     */
    public function clearRecipients()
    {
        $this->_recipients = [];
        return $this;
    }

    /**
     * Set message recipients data
     *
     * @return $this
     */
    public function setRecipients(array $recipients)
    {
        $this->_recipients = $recipients;
        return $this;
    }

    /**
     * Get message recipients list
     *
     * @return array
     */
    public function getRecipients()
    {
        return $this->_recipients;
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData('entity_id');
        return $value === null ? null : (int) $value;
    }

    public function setEntityId(?int $value): static
    {
        return $this->setData('entity_id', $value);
    }

    public function getEntityType(): ?string
    {
        $value = $this->getData('entity_type');
        return $value === null ? null : (string) $value;
    }

    public function setEntityType(?string $value): static
    {
        return $this->setData('entity_type', $value);
    }

    public function getEventType(): ?string
    {
        $value = $this->getData('event_type');
        return $value === null ? null : (string) $value;
    }

    public function setEventType(?string $value): static
    {
        return $this->setData('event_type', $value);
    }

    public function getIsForceCheck(): ?bool
    {
        $value = $this->getData('is_force_check');
        return $value === null ? null : (bool) $value;
    }

    public function setIsForceCheck(?bool $value): static
    {
        return $this->setData('is_force_check', $value);
    }

    public function getMessageBody(): ?string
    {
        $value = $this->getData('message_body');
        return $value === null ? null : (string) $value;
    }

    public function setMessageBody(?string $value): static
    {
        return $this->setData('message_body', $value);
    }
}
