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

class Maho_CustomerSegmentation_Model_Resource_SequenceProgress_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/sequenceProgress');
    }

    /**
     * Filter by customer ID
     */
    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter by segment ID
     */
    public function addSegmentFilter(int $segmentId): self
    {
        $this->addFieldToFilter('segment_id', $segmentId);
        return $this;
    }

    /**
     * Filter by sequence ID
     */
    public function addSequenceFilter(int $sequenceId): self
    {
        $this->addFieldToFilter('sequence_id', $sequenceId);
        return $this;
    }

    /**
     * Filter by status
     */
    public function addStatusFilter(string $status): self
    {
        $this->addFieldToFilter('status', $status);
        return $this;
    }

    /**
     * Filter by trigger type
     */
    public function addTriggerTypeFilter(string $triggerType): self
    {
        $this->addFieldToFilter('trigger_type', $triggerType);
        return $this;
    }

    /**
     * Filter only scheduled items ready to send
     */
    public function addReadyToSendFilter(): self
    {
        $this->addFieldToFilter('status', Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED)
             ->addFieldToFilter('scheduled_at', ['lteq' => Mage::getSingleton('core/date')->gmtDate()]);
        return $this;
    }

    /**
     * Filter only active sequences
     */
    public function addActiveSequenceFilter(): self
    {
        $this->getSelect()->join(
            ['sequence' => $this->getTable('customer_segment_email_sequence')],
            'main_table.sequence_id = sequence.sequence_id',
            [],
        )
        ->where('sequence.is_active = ?', 1);

        return $this;
    }

    /**
     * Filter only active segments
     */
    public function addActiveSegmentFilter(): self
    {
        $this->getSelect()->join(
            ['segment' => $this->getTable('customer_segment')],
            'main_table.segment_id = segment.segment_id',
            [],
        )
        ->where('segment.auto_email_active = ?', 1);

        return $this;
    }

    /**
     * Join with customer data
     */
    public function joinCustomerData(): self
    {
        $this->getSelect()->join(
            ['customer' => $this->getTable('customer_entity')],
            'main_table.customer_id = customer.entity_id',
            ['customer_email' => 'email', 'customer_firstname' => 'firstname', 'customer_lastname' => 'lastname'],
        );
        return $this;
    }

    /**
     * Join with segment data
     */
    public function joinSegmentData(): self
    {
        $this->getSelect()->join(
            ['segment' => $this->getTable('customer_segment')],
            'main_table.segment_id = segment.segment_id',
            ['segment_name' => 'name', 'segment_is_active' => 'is_active'],
        );
        return $this;
    }

    /**
     * Join with sequence data
     */
    public function joinSequenceData(): self
    {
        $this->getSelect()->join(
            ['sequence' => $this->getTable('customer_segment_email_sequence')],
            'main_table.sequence_id = sequence.sequence_id',
            [
                'template_id',
                'step_number',
                'delay_minutes',
                'generate_coupon',
                'coupon_prefix',
                'sequence_is_active' => 'is_active',
            ],
        );
        return $this;
    }

    /**
     * Join with newsletter template data
     */
    public function joinTemplateData(): self
    {
        $this->getSelect()->join(
            ['template' => $this->getTable('newsletter_template')],
            'sequence.template_id = template.template_id',
            ['template_code', 'template_subject', 'template_sender_name'],
        );
        return $this;
    }

    /**
     * Join with newsletter queue data for sent items
     */
    public function joinQueueData(): self
    {
        $this->getSelect()->joinLeft(
            ['queue' => $this->getTable('newsletter_queue')],
            'main_table.queue_id = queue.queue_id',
            ['queue_status', 'queue_start_at', 'queue_finish_at'],
        );
        return $this;
    }

    /**
     * Add date range filter
     */
    public function addDateRangeFilter(?string $from = null, ?string $to = null): self
    {
        if ($from) {
            $this->addFieldToFilter('created_at', ['gteq' => $from]);
        }
        if ($to) {
            $this->addFieldToFilter('created_at', ['lteq' => $to]);
        }
        return $this;
    }

    /**
     * Order by scheduled time
     */
    public function addScheduledOrder(string $direction = 'ASC'): self
    {
        $this->setOrder('scheduled_at', $direction);
        return $this;
    }

    /**
     * Order by creation time
     */
    public function addCreatedOrder(string $direction = 'DESC'): self
    {
        $this->setOrder('created_at', $direction);
        return $this;
    }

    /**
     * Group by customer to get unique customers
     */
    public function groupByCustomer(): self
    {
        $this->getSelect()->group('main_table.customer_id');
        return $this;
    }

    /**
     * Add newsletter subscriber status filter
     */
    public function addSubscriberFilter(): self
    {
        $this->getSelect()->join(
            ['subscriber' => $this->getTable('newsletter_subscriber')],
            'main_table.customer_id = subscriber.customer_id',
            [],
        )
        ->where('subscriber.subscriber_status = ?', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

        return $this;
    }
}
