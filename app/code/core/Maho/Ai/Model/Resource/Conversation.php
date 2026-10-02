<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Resource_Conversation extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/conversation', 'conversation_id');
    }

    /**
     * One UPDATE guarded by the current lock state, so two turns that start at the same
     * time cannot both take the conversation.
     */
    public function acquireLock(Maho_Ai_Model_Conversation $conversation, int $seconds): bool
    {
        $adapter = $this->_getWriteAdapter();
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $until = Mage::app()->getLocale()->formatDateForDb(sprintf('+%d seconds', $seconds));

        $updated = $adapter->update(
            $this->getMainTable(),
            ['locked_until' => $until],
            [
                'conversation_id = ?' => (int) $conversation->getId(),
                '(locked_until IS NULL OR locked_until < ?)' => $now,
            ],
        );
        if ($updated > 0) {
            $conversation->setData('locked_until', $until);
        }

        return $updated > 0;
    }

    public function releaseLock(Maho_Ai_Model_Conversation $conversation): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['locked_until' => null],
            ['conversation_id = ?' => (int) $conversation->getId()],
        );
        $conversation->setData('locked_until');
    }
}
