<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Resource_SequenceProgress extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/sequenceProgress', 'progress_id');
    }

    /**
     * Get customers with active sequences for a segment and trigger type
     */
    public function getActiveSequenceCustomers(int $segmentId, string $triggerType): array
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable(), 'customer_id')
            ->where('segment_id = ?', $segmentId)
            ->where('trigger_type = ?', $triggerType)
            ->where('status = ?', Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED)
            ->group('customer_id');

        return $adapter->fetchCol($select);
    }

    /**
     * Stop sequences for specific customers
     */
    public function stopSequencesForCustomers(int $segmentId, array $customerIds, string $triggerType): int
    {
        if (empty($customerIds)) {
            return 0;
        }

        $adapter = $this->_getWriteAdapter();

        $affected = $adapter->update(
            $this->getMainTable(),
            ['status' => Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SKIPPED],
            [
                'segment_id = ?' => $segmentId,
                'customer_id IN (?)' => $customerIds,
                'trigger_type = ?' => $triggerType,
                'status = ?' => Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED,
            ],
        );

        return $affected;
    }

    /**
     * Get scheduled sequences ready to send
     */
    public function getReadyToSendSequences(int $limit = 100): array
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from(['p' => $this->getMainTable()])
            ->join(
                ['s' => $this->getTable('customersegmentation/emailSequence')],
                'p.sequence_id = s.sequence_id',
                ['template_id', 'max_sends', 'generate_coupon', 'coupon_sales_rule_id', 'coupon_prefix', 'coupon_expires_days'],
            )
            ->join(
                ['seg' => $this->getTable('customersegmentation/segment')],
                'p.segment_id = seg.segment_id',
                ['segment_name' => 'name'],
            )
            ->join(
                ['c' => $this->getTable('customer/entity')],
                'p.customer_id = c.entity_id',
                ['customer_email' => 'email', 'store_id'],
            )
            ->joinLeft(
                ['sub' => $this->getTable('newsletter/subscriber')],
                'p.customer_id = sub.customer_id',
                ['subscriber_status'],
            )
            ->where('p.status = ?', Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED)
            ->where('p.scheduled_at <= ?', Mage::getSingleton('core/date')->gmtDate())
            ->where('s.is_active = ?', 1)
            ->where('seg.auto_email_active = ?', 1)
            ->where('sub.subscriber_status = ?', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->limit($limit)
            ->order('p.scheduled_at ASC');

        return $adapter->fetchAll($select);
    }

    /**
     * Check if customer has active sequence for specific trigger
     */
    public function hasActiveSequence(int $customerId, int $segmentId, string $triggerType): bool
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable(), 'COUNT(*)')
            ->where('customer_id = ?', $customerId)
            ->where('segment_id = ?', $segmentId)
            ->where('trigger_type = ?', $triggerType)
            ->where('status IN (?)', [
                Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED,
                Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT,
            ]);

        return (int) $adapter->fetchOne($select) > 0;
    }

    /**
     * Check if customer has any active sequence for specific segment (regardless of trigger)
     */
    public function hasAnyActiveSequence(int $customerId, int $segmentId): bool
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable(), 'COUNT(*)')
            ->where('customer_id = ?', $customerId)
            ->where('segment_id = ?', $segmentId)
            ->where('status IN (?)', [Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED]);

        return (int) $adapter->fetchOne($select) > 0;
    }

    /**
     * Get progress statistics for a sequence
     */
    public function getSequenceStats(int $sequenceId): array
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable(), [
                'total' => 'COUNT(*)',
                'scheduled' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED) . ' THEN 1 ELSE 0 END)'),
                'sent' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT) . ' THEN 1 ELSE 0 END)'),
                'failed' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_FAILED) . ' THEN 1 ELSE 0 END)'),
                'skipped' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SKIPPED) . ' THEN 1 ELSE 0 END)'),
            ])
            ->where('sequence_id = ?', $sequenceId);

        return $adapter->fetchRow($select) ?: [];
    }

    /**
     * Get progress statistics for a segment
     */
    public function getSegmentStats(int $segmentId): array
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable(), [
                'total' => 'COUNT(*)',
                'scheduled' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED) . ' THEN 1 ELSE 0 END)'),
                'sent' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT) . ' THEN 1 ELSE 0 END)'),
                'failed' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_FAILED) . ' THEN 1 ELSE 0 END)'),
                'skipped' => new Maho\Db\Expr('SUM(CASE WHEN status = ' . $adapter->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SKIPPED) . ' THEN 1 ELSE 0 END)'),
                'unique_customers' => 'COUNT(DISTINCT customer_id)',
            ])
            ->where('segment_id = ?', $segmentId);

        return $adapter->fetchRow($select) ?: [];
    }

    /**
     * Clean up old completed progress records
     */
    public function cleanupOldProgress(int $daysOld = 90): int
    {
        $adapter = $this->_getWriteAdapter();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        return $adapter->delete(
            $this->getMainTable(),
            [
                'status IN (?)' => [
                    Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT,
                    Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_FAILED,
                    Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SKIPPED,
                ],
                'created_at < ?' => $cutoffDate,
            ],
        );
    }

    /**
     * Get customer's automation history
     */
    public function getCustomerHistory(int $customerId, int $limit = 50): array
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from(['p' => $this->getMainTable()])
            ->join(
                ['s' => $this->getTable('customer_segment_email_sequence')],
                'p.sequence_id = s.sequence_id',
                ['step_number', 'template_id'],
            )
            ->join(
                ['seg' => $this->getTable('customer_segment')],
                'p.segment_id = seg.segment_id',
                ['segment_name' => 'name'],
            )
            ->joinLeft(
                ['tpl' => $this->getTable('newsletter_template')],
                's.template_id = tpl.template_id',
                ['template_code', 'template_subject'],
            )
            ->where('p.customer_id = ?', $customerId)
            ->order('p.created_at DESC')
            ->limit($limit);

        return $adapter->fetchAll($select);
    }

    /**
     * Create progress records for a customer entering a sequence
     */
    public function createSequenceProgress(
        int $customerId,
        int $segmentId,
        array $sequences,
        string $triggerType,
    ): void {
        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            $data = [];
            $now = Mage::getSingleton('core/date')->gmtDate();

            foreach ($sequences as $sequence) {
                $scheduledAt = $now;
                if ($sequence['delay_minutes'] > 0) {
                    $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$sequence['delay_minutes']} minutes"));
                }

                $data[] = [
                    'customer_id' => $customerId,
                    'segment_id' => $segmentId,
                    'sequence_id' => $sequence['sequence_id'],
                    'step_number' => $sequence['step_number'],
                    'trigger_type' => $triggerType,
                    'scheduled_at' => $scheduledAt,
                    'status' => Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED,
                    'created_at' => $now,
                ];
            }

            if (!empty($data)) {
                $adapter->insertMultiple($this->getMainTable(), $data);
            }

            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            Mage::logException($e);
            throw $e;
        }
    }

    /**
     * Check for duplicate progress records before save
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        parent::_beforeSave($object);

        // Prevent duplicate progress records for same customer/sequence/trigger
        if ($object->isObjectNew()) {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from($this->getMainTable(), 'progress_id')
                ->where('customer_id = ?', $object->getCustomerId())
                ->where('sequence_id = ?', $object->getSequenceId())
                ->where('trigger_type = ?', $object->getTriggerType())
                ->where('status IN (?)', [
                    Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED,
                    Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT,
                ]);

            $existingId = $adapter->fetchOne($select);
            if ($existingId) {
                Mage::throwException(
                    Mage::helper('customersegmentation')->__(
                        'Progress record already exists for this customer and sequence.',
                    ),
                );
            }
        }

        return $this;
    }

    /**
     * Get read adapter for external access
     */
    public function getReadAdapter(): \Maho\Db\Adapter\AdapterInterface
    {
        return $this->_getReadAdapter();
    }
}
