<?php

/**
 * One admin assistant conversation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Conversation extends Mage_Core_Model_Abstract
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    /** A background run of the assistant works in it; the panel polls it until it is active again. */
    public const STATUS_RUNNING = 'running';

    /** Seconds one chat turn may hold the conversation before another request may take it. */
    public const LOCK_SECONDS = 300;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/conversation');
    }

    #[\Override]
    protected function _beforeDelete(): static
    {
        Maho_Ai_Model_Chat_Attachment::deleteForConversation($this);

        return parent::_beforeDelete();
    }

    public function getAdminUserId(): ?int
    {
        $value = $this->getData('admin_user_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminUserId(?int $value): static
    {
        return $this->setData('admin_user_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getPlatform(): ?string
    {
        $value = $this->getData('platform');
        return $value === null ? null : (string) $value;
    }

    public function setPlatform(?string $value): static
    {
        return $this->setData('platform', $value);
    }

    public function getModel(): ?string
    {
        $value = $this->getData('model');
        return $value === null ? null : (string) $value;
    }

    public function setModel(?string $value): static
    {
        return $this->setData('model', $value);
    }

    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    public function getContextRoute(): ?string
    {
        $value = $this->getData('context_route');
        return $value === null ? null : (string) $value;
    }

    public function setContextRoute(?string $value): static
    {
        return $this->setData('context_route', $value);
    }

    public function getContextEntityType(): ?string
    {
        $value = $this->getData('context_entity_type');
        return $value === null ? null : (string) $value;
    }

    public function setContextEntityType(?string $value): static
    {
        return $this->setData('context_entity_type', $value);
    }

    public function getContextEntityId(): ?int
    {
        $value = $this->getData('context_entity_id');
        return $value === null ? null : (int) $value;
    }

    public function setContextEntityId(?int $value): static
    {
        return $this->setData('context_entity_id', $value);
    }

    public function getLockedUntil(): ?string
    {
        $value = $this->getData('locked_until');
        return $value === null ? null : (string) $value;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function isOwnedBy(int $adminUserId): bool
    {
        return $this->getId() && $this->getAdminUserId() === $adminUserId;
    }

    /**
     * Take the conversation for one chat turn. False when another turn still holds it.
     */
    public function acquireLock(int $seconds = self::LOCK_SECONDS): bool
    {
        return $this->lockResource()->acquireLock($this, $seconds);
    }

    public function releaseLock(): void
    {
        $this->lockResource()->releaseLock($this);
    }

    private function lockResource(): Maho_Ai_Model_Resource_Conversation
    {
        $resource = $this->getResource();
        if (!$resource instanceof Maho_Ai_Model_Resource_Conversation) {
            throw new LogicException('The conversation resource model is not configured.');
        }

        return $resource;
    }

    /**
     * The write tool calls that still wait for the administrator.
     *
     * @return list<Maho_Ai_Model_Conversation_Message>
     */
    public function getPendingWrites(): array
    {
        if (!$this->getId()) {
            return [];
        }

        return array_values($this->messagesCollection()
            ->addFieldToFilter('tool_status', Maho_Ai_Model_Conversation_Message::TOOL_PENDING)
            ->getItems());
    }

    /**
     * Mark every pending write as cancelled, so the next model turn sees a closed round.
     */
    public function cancelPendingWrites(): void
    {
        foreach ($this->getPendingWrites() as $message) {
            $message->setToolStatus(Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED);
            $message->setContent(Maho_Ai_Model_Conversation_Message::cancelledResult());
            $message->save();
        }
    }

    /**
     * The messages of the conversation, oldest first.
     */
    public function messagesCollection(): Maho_Ai_Model_Resource_Conversation_Message_Collection
    {
        /** @var Maho_Ai_Model_Resource_Conversation_Message_Collection $collection */
        $collection = Mage::getResourceModel('ai/conversation_message_collection');
        $collection->addFieldToFilter('conversation_id', (int) $this->getId())
            ->setOrder('message_id', 'ASC');

        return $collection;
    }

    public function addMessage(array $data): Maho_Ai_Model_Conversation_Message
    {
        /** @var Maho_Ai_Model_Conversation_Message $message */
        $message = Mage::getModel('ai/conversation_message');
        $message->setData($data + ['conversation_id' => (int) $this->getId()]);
        $message->save();

        return $message;
    }

    #[\Override]
    protected function _beforeSave(): static
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$this->getId()) {
            $this->setData('created_at', $now);
        }
        $this->setData('updated_at', $now);

        return parent::_beforeSave();
    }
}
