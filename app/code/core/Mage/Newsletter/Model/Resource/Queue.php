<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Model_Resource_Queue extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Recipient links written per INSERT when snapshotting the audience
     */
    public const LINK_INSERT_CHUNK = 1000;

    #[\Override]
    protected function _construct()
    {
        $this->_init('newsletter/queue', 'queue_id');
    }

    /**
     * Add subscribers to queue
     */
    public function addSubscribersToQueue(Mage_Newsletter_Model_Queue $queue, array $subscriberIds)
    {
        if (count($subscriberIds) == 0) {
            Mage::throwException(Mage::helper('newsletter')->__('No subscribers selected.'));
        }

        if (!$queue->getId() && $queue->getQueueStatus() != Mage_Newsletter_Model_Queue::STATUS_NEVER) {
            Mage::throwException(Mage::helper('newsletter')->__('Invalid queue selected.'));
        }

        $adapter = $this->_getWriteAdapter();

        $select = $adapter->select();
        $select->from($this->getTable('newsletter/queue_link'), 'subscriber_id')
            ->where('queue_id = ?', $queue->getId())
            ->where('subscriber_id in (?)', $subscriberIds);

        $usedIds = $adapter->fetchCol($select);
        $rows = [];
        foreach (array_diff($subscriberIds, $usedIds) as $subscriberId) {
            $rows[] = ['queue_id' => $queue->getId(), 'subscriber_id' => $subscriberId];
        }

        if ($rows === []) {
            return;
        }

        $adapter->beginTransaction();
        try {
            foreach (array_chunk($rows, self::LINK_INSERT_CHUNK) as $chunk) {
                $adapter->insertMultiple($this->getTable('newsletter/queue_link'), $chunk);
            }
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }
    }

    /**
     * Snapshot the campaign audience as queue links, once.
     *
     * Deferred to the first batch rather than done on save: the recipients are
     * then the ones subscribed when the campaign goes out, not when it was
     * written, and editing a draft no longer rewrites the whole audience.
     */
    public function materializeRecipients(Mage_Newsletter_Model_Queue $queue): void
    {
        $stores = $this->getStores($queue);
        if ($stores === [] || $this->getRecipientCount($queue) > 0) {
            return;
        }

        $subscriberIds = Mage::getResourceModel('newsletter/subscriber_collection')
            ->addFieldToFilter('store_id', ['in' => $stores])
            ->useOnlySubscribed()
            ->getAllIds();

        if ($subscriberIds !== []) {
            $this->addSubscribersToQueue($queue, $subscriberIds);
        }
    }

    /**
     * Recipients linked to this campaign, sent or not
     */
    public function getRecipientCount(Mage_Newsletter_Model_Queue $queue): int
    {
        $adapter = $this->_getReadAdapter();

        return (int) $adapter->fetchOne(
            $adapter->select()
                ->from($this->getTable('newsletter/queue_link'), new Maho\Db\Expr('COUNT(*)'))
                ->where('queue_id = ?', $queue->getId()),
        );
    }

    /**
     * Removes subscriber from queue
     */
    public function removeSubscribersFromQueue(Mage_Newsletter_Model_Queue $queue): void
    {
        $this->_getWriteAdapter()->delete(
            $this->getTable('newsletter/queue_link'),
            [
                'queue_id = ?' => $queue->getId(),
                'letter_sent_at IS NULL',
            ],
        );
    }

    /**
     * Links queue to store
     *
     * @return $this
     */
    public function setStores(Mage_Newsletter_Model_Queue $queue)
    {
        // Stores decide the audience, which is snapshotted at the first batch: changing
        // them afterwards could only strand the recipients already linked.
        if ((int) $queue->getQueueStatus() !== Mage_Newsletter_Model_Queue::STATUS_NEVER) {
            return $this;
        }

        $adapter = $this->_getWriteAdapter();
        $adapter->delete(
            $this->getTable('newsletter/queue_store_link'),
            ['queue_id = ?' => $queue->getId()],
        );

        $stores = $queue->getStores();
        if (!is_array($stores)) {
            $stores = [];
        }

        foreach ($stores as $storeId) {
            $data = [];
            $data['store_id'] = $storeId;
            $data['queue_id'] = $queue->getId();
            $adapter->insert($this->getTable('newsletter/queue_store_link'), $data);
        }

        $this->removeSubscribersFromQueue($queue);

        return $this;
    }

    /**
     * Returns queue linked stores
     *
     * @return array
     */
    public function getStores(Mage_Newsletter_Model_Queue $queue)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()->from($this->getTable('newsletter/queue_store_link'), 'store_id')
            ->where('queue_id = :queue_id');

        if (!($result = $adapter->fetchCol($select, ['queue_id' => $queue->getId()]))) {
            $result = [];
        }

        return $result;
    }

    /**
     * Saving template after saving queue action
     *
     * @param Mage_Newsletter_Model_Queue $queue
     * @return $this
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $queue)
    {
        if ($queue->getSaveStoresFlag()) {
            $this->setStores($queue);
        }
        return $this;
    }
}
