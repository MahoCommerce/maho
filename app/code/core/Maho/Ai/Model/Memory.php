<?php

/**
 * A note the administrator asked the assistant to keep across conversations.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Memory extends Mage_Core_Model_Abstract
{
    /** Notes per administrator; the prompt lists them all, so the list stays short. */
    public const MAX_NOTES = 20;
    public const MAX_LENGTH = 200;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/memory');
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

    public function getNote(): ?string
    {
        $value = $this->getData('note');
        return $value === null ? null : (string) $value;
    }

    public function setNote(?string $value): static
    {
        return $this->setData('note', $value);
    }

    /**
     * @return list<array{id: int, note: string}>
     */
    public static function notesOf(int $adminUserId): array
    {
        $notes = [];
        foreach (Mage::getResourceModel('ai/memory_collection')->addFieldToFilter('admin_user_id', $adminUserId)->setOrder('memory_id', 'ASC') as $memory) {
            $notes[] = ['id' => (int) $memory->getId(), 'note' => (string) $memory->getNote()];
        }

        return $notes;
    }
}
