<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

/**
 * Sequence Progress Model - tracks customer progress through email sequences
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
             ->setSentAt(Mage::app()->getLocale()->formatDateForDb('now'))
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

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getSegmentId(): ?int
    {
        $value = $this->getData('segment_id');
        return $value === null ? null : (int) $value;
    }

    public function setSegmentId(?int $value): static
    {
        return $this->setData('segment_id', $value);
    }

    public function getSequenceId(): ?int
    {
        $value = $this->getData('sequence_id');
        return $value === null ? null : (int) $value;
    }

    public function setSequenceId(?int $value): static
    {
        return $this->setData('sequence_id', $value);
    }

    public function getQueueId(): ?int
    {
        $value = $this->getData('queue_id');
        return $value === null ? null : (int) $value;
    }

    public function setQueueId(?int $value): static
    {
        return $this->setData('queue_id', $value);
    }

    public function getStepNumber(): ?int
    {
        $value = $this->getData('step_number');
        return $value === null ? null : (int) $value;
    }

    public function setStepNumber(?int $value): static
    {
        return $this->setData('step_number', $value);
    }

    public function getTriggerType(): ?string
    {
        $value = $this->getData('trigger_type');
        return $value === null ? null : (string) $value;
    }

    public function setTriggerType(?string $value): static
    {
        return $this->setData('trigger_type', $value);
    }

    public function getScheduledAt(): ?string
    {
        $value = $this->getData('scheduled_at');
        return $value === null ? null : (string) $value;
    }

    public function setScheduledAt(?string $value): static
    {
        return $this->setData('scheduled_at', $value);
    }

    public function getSentAt(): ?string
    {
        $value = $this->getData('sent_at');
        return $value === null ? null : (string) $value;
    }

    public function setSentAt(?string $value): static
    {
        return $this->setData('sent_at', $value);
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

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }
}
