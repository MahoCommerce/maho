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

/**
 * Sequence Progress Model - tracks customer progress through email sequences
 *
 * @method int getCustomerId()
 * @method int getSegmentId()
 * @method int getSequenceId()
 * @method int getQueueId()
 * @method int getStepNumber()
 * @method string getTriggerType()
 * @method string getScheduledAt()
 * @method string getSentAt()
 * @method string getStatus()
 * @method string getCreatedAt()
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setCustomerId(int $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setSegmentId(int $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setSequenceId(int $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setQueueId(int $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setStepNumber(int $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setTriggerType(string $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setScheduledAt(string $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setSentAt(string $value)
 * @method Maho_CustomerSegmentation_Model_SequenceProgress setStatus(string $value)
 */
class Maho_CustomerSegmentation_Model_SequenceProgress extends Mage_Core_Model_Abstract
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const TRIGGER_ENTER = 'enter';
    public const TRIGGER_EXIT = 'exit';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/sequenceProgress');
        $this->setIdFieldName('progress_id');
    }

    /**
     * Mark as sent and record timestamp
     */
    public function markAsSent(?int $queueId = null): self
    {
        if ($queueId) {
            $this->setQueueId($queueId);
        }
        $this->setStatus(self::STATUS_SENT)
             ->setSentAt(Mage::getSingleton('core/date')->gmtDate())
             ->save();
        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): self
    {
        $this->setStatus(self::STATUS_FAILED)->save();
        return $this;
    }

    /**
     * Mark as skipped (stopped sequence)
     */
    public function markAsSkipped(): self
    {
        $this->setStatus(self::STATUS_SKIPPED)->save();
        return $this;
    }

    /**
     * Check if this step is ready to send
     */
    public function isReadyToSend(): bool
    {
        if ($this->getStatus() !== self::STATUS_SCHEDULED) {
            return false;
        }

        $scheduledAt = strtotime($this->getScheduledAt());
        return $scheduledAt <= time();
    }

    /**
     * Get customer model
     */
    public function getCustomer(): ?Mage_Customer_Model_Customer
    {
        if ($this->getCustomerId()) {
            return Mage::getModel('customer/customer')->load($this->getCustomerId());
        }
        return null;
    }

    /**
     * Get segment model
     */
    public function getSegment(): ?Maho_CustomerSegmentation_Model_Segment
    {
        if ($this->getSegmentId()) {
            return Mage::getModel('customersegmentation/segment')->load($this->getSegmentId());
        }
        return null;
    }

    /**
     * Get sequence model
     */
    public function getSequence(): ?Maho_CustomerSegmentation_Model_EmailSequence
    {
        if ($this->getSequenceId()) {
            return Mage::getModel('customersegmentation/emailSequence')->load($this->getSequenceId());
        }
        return null;
    }

    /**
     * Get newsletter queue model
     */
    public function getQueue(): ?Mage_Newsletter_Model_Queue
    {
        if ($this->getQueueId()) {
            return Mage::getModel('newsletter/queue')->load($this->getQueueId());
        }
        return null;
    }

    /**
     * Get status options for admin forms
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_SCHEDULED => Mage::helper('customersegmentation')->__('Scheduled'),
            self::STATUS_SENT => Mage::helper('customersegmentation')->__('Sent'),
            self::STATUS_FAILED => Mage::helper('customersegmentation')->__('Failed'),
            self::STATUS_SKIPPED => Mage::helper('customersegmentation')->__('Skipped'),
        ];
    }

    /**
     * Get trigger type options for admin forms
     */
    public static function getTriggerTypeOptions(): array
    {
        return [
            self::TRIGGER_ENTER => Mage::helper('customersegmentation')->__('Enter Segment'),
            self::TRIGGER_EXIT => Mage::helper('customersegmentation')->__('Exit Segment'),
        ];
    }

    /**
     * Get formatted status
     */
    public function getStatusLabel(): string
    {
        $options = self::getStatusOptions();
        return $options[$this->getStatus()] ?? $this->getStatus();
    }

    /**
     * Get formatted trigger type
     */
    public function getTriggerTypeLabel(): string
    {
        $options = self::getTriggerTypeOptions();
        return $options[$this->getTriggerType()] ?? $this->getTriggerType();
    }

    /**
     * Validate progress data before save
     */
    public function validate(): bool
    {
        $errors = [];

        if (!$this->getCustomerId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Customer ID is required.');
        }

        if (!$this->getSegmentId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Segment ID is required.');
        }

        if (!$this->getSequenceId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Sequence ID is required.');
        }

        if (!$this->getStepNumber() || $this->getStepNumber() < 1) {
            $errors[] = Mage::helper('customersegmentation')->__('Step number must be greater than 0.');
        }

        if (!in_array($this->getTriggerType(), [self::TRIGGER_ENTER, self::TRIGGER_EXIT])) {
            $errors[] = Mage::helper('customersegmentation')->__('Invalid trigger type.');
        }

        if (!in_array($this->getStatus(), [self::STATUS_SCHEDULED, self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_SKIPPED])) {
            $errors[] = Mage::helper('customersegmentation')->__('Invalid status.');
        }

        if (!empty($errors)) {
            Mage::throwException(implode("\n", $errors));
        }

        return true;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        // Validate data
        $this->validate();

        // Set default status for new progress records
        if ($this->isObjectNew() && !$this->hasData('status')) {
            $this->setStatus(self::STATUS_SCHEDULED);
        }

        return $this;
    }
}
