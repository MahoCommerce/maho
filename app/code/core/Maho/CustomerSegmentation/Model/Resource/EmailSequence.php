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

class Maho_CustomerSegmentation_Model_Resource_EmailSequence extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/emailSequence', 'sequence_id');
    }

    /**
     * Check for duplicate step numbers in same segment before save
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        parent::_beforeSave($object);

        // Check for duplicate step number in same segment and trigger event
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), 'sequence_id')
            ->where('segment_id = ?', (int) $object->getSegmentId())
            ->where('trigger_event = ?', $object->getTriggerEvent())
            ->where('step_number = ?', (int) $object->getStepNumber());

        if ($object->getId()) {
            $select->where('sequence_id != ?', (int) $object->getId());
        }

        $existingId = $adapter->fetchOne($select);
        if ($existingId) {
            // Log for debugging
            Mage::log(
                sprintf(
                    'Duplicate step validation failed: segment_id=%s, trigger_event=%s, step_number=%s, current_id=%s, found_id=%s',
                    $object->getSegmentId(),
                    $object->getTriggerEvent(),
                    $object->getStepNumber(),
                    $object->getId(),
                    $existingId,
                ),
                Mage::LOG_DEBUG,
            );

            Mage::throwException(
                Mage::helper('customersegmentation')->__(
                    'Step number %d already exists for this segment %s trigger.',
                    $object->getStepNumber(),
                    $object->getTriggerEvent(),
                ),
            );
        }

        return $this;
    }

    /**
     * Clear sequence progress when sequence is deactivated
     */
    public function clearSequenceProgress(int $sequenceId): int
    {
        $progressResource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
        $adapter = $this->_getWriteAdapter();
        return $adapter->update(
            $progressResource->getMainTable(),
            ['status' => Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SKIPPED],
            [
                'sequence_id = ?' => $sequenceId,
                'status = ?' => Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED,
            ],
        );
    }

    /**
     * Get active sequences for a segment
     */
    public function getActiveSequencesForSegment(int $segmentId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('segment_id = ?', $segmentId)
            ->where('is_active = ?', 1)
            ->order('step_number ASC');

        return $adapter->fetchAll($select);
    }

    /**
     * Get next available step number for a segment and trigger event
     */
    public function getNextStepNumber(int $segmentId, string $triggerEvent = Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER): int
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), 'MAX(step_number)')
            ->where('segment_id = ?', $segmentId)
            ->where('trigger_event = ?', $triggerEvent);

        $maxStep = (int) $adapter->fetchOne($select);
        return $maxStep + 1;
    }

    /**
     * Reorder sequence steps after deletion
     */
    public function reorderSteps(int $segmentId, int $deletedStepNumber): void
    {
        $adapter = $this->_getWriteAdapter();

        // Move all steps after deleted step down by 1
        $adapter->update(
            $this->getMainTable(),
            ['step_number' => new Maho\Db\Expr('step_number - 1')],
            [
                'segment_id = ?' => $segmentId,
                'step_number > ?' => $deletedStepNumber,
            ],
        );
    }

    /**
     * Delete sequence and handle cleanup
     */
    #[\Override]
    protected function _beforeDelete(Mage_Core_Model_Abstract $object): self
    {
        parent::_beforeDelete($object);

        // Clear any pending progress for this sequence
        $this->clearSequenceProgress((int) $object->getId());

        return $this;
    }

    /**
     * Clean up step numbering after deletion
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $object): self
    {
        parent::_afterDelete($object);

        // Reorder remaining steps
        $this->reorderSteps((int) $object->getSegmentId(), (int) $object->getStepNumber());

        return $this;
    }

    /**
     * Get sequences with their template information
     */
    public function getSequencesWithTemplates(int $segmentId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['seq' => $this->getMainTable()])
            ->joinLeft(
                ['tpl' => $this->getTable('newsletter_template')],
                'seq.template_id = tpl.template_id',
                ['template_code', 'template_subject', 'template_sender_name', 'template_sender_email'],
            )
            ->joinLeft(
                ['rule' => $this->getTable('salesrule')],
                'seq.coupon_sales_rule_id = rule.rule_id',
                ['rule_name' => 'name', 'discount_amount', 'simple_action'],
            )
            ->where('seq.segment_id = ?', $segmentId)
            ->order('seq.step_number ASC');

        return $adapter->fetchAll($select);
    }
}
