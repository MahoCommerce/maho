<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/**
 * Queue message for sending one transactional email, handled asynchronously
 * by Mage_Core_Model_Email_SendMessageHandler.
 */
final readonly class Mage_Core_Model_Email_SendMessage
{
    /**
     * @param list<array{0: string, 1: string, 2: int}> $recipients  [email, name, type] triples (type: Mage_Core_Model_Email_Queue::EMAIL_TYPE_*)
     * @param list<array<string, mixed>>                $attachments Attachment descriptors for Mage_Core_Model_Email_Attachment::applyDescriptors()
     * @param array<string, string>                     $headers     Extra text headers (e.g. List-Unsubscribe, X-Maho-Template)
     */
    public function __construct(
        public string $subject,
        public string $body,
        public bool $isPlain,
        public string $fromEmail,
        public string $fromName,
        public array $recipients,
        public ?string $replyTo = null,
        public ?string $returnPath = null,
        public array $attachments = [],
        public array $headers = [],
        public ?int $entityId = null,
        public ?string $entityType = null,
        public ?string $eventType = null,
    ) {}
}
