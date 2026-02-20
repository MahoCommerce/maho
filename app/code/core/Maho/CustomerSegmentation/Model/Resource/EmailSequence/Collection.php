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

class Maho_CustomerSegmentation_Model_Resource_EmailSequence_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/emailSequence');
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
     * Filter only active sequences
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Filter by trigger event
     */
    public function addTriggerFilter(string $triggerEvent): self
    {
        $this->addFieldToFilter('trigger_event', $triggerEvent);
        return $this;
    }

    /**
     * Order by step number
     */
    public function addStepNumberOrder(string $direction = 'ASC'): self
    {
        $this->setOrder('step_number', $direction);
        return $this;
    }

    /**
     * Join with newsletter template data
     */
    public function joinTemplateData(): self
    {
        $this->getSelect()->joinLeft(
            ['template' => $this->getTable('newsletter_template')],
            'main_table.template_id = template.template_id',
            [
                'template_code',
                'template_subject',
                'template_sender_name',
                'template_sender_email',
                'template_text',
            ],
        );
        return $this;
    }

    /**
     * Join with sales rule data for coupon information
     */
    public function joinSalesRuleData(): self
    {
        $this->getSelect()->joinLeft(
            ['salesrule' => $this->getTable('salesrule')],
            'main_table.coupon_sales_rule_id = salesrule.rule_id',
            [
                'rule_name' => 'name',
                'discount_amount',
                'simple_action',
                'rule_description' => 'description',
            ],
        );
        return $this;
    }

    /**
     * Join with segment data
     */
    public function joinSegmentData(): self
    {
        $this->getSelect()->joinLeft(
            ['segment' => $this->getTable('customer_segment')],
            'main_table.segment_id = segment.segment_id',
            [
                'segment_name' => 'name',
                'segment_is_active' => 'is_active',
                'auto_email_trigger',
                'auto_email_active',
            ],
        );
        return $this;
    }

    /**
     * Add progress statistics
     */
    public function addProgressStats(): self
    {
        $progressTable = $this->getTable('customer_segment_sequence_progress');

        $this->getSelect()
            ->joinLeft(
                ['progress_total' => $progressTable],
                'main_table.sequence_id = progress_total.sequence_id',
                [],
            )
            ->joinLeft(
                ['progress_sent' => $progressTable],
                'main_table.sequence_id = progress_sent.sequence_id AND progress_sent.status = ' . $this->getConnection()->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SENT),
                [],
            )
            ->joinLeft(
                ['progress_scheduled' => $progressTable],
                'main_table.sequence_id = progress_scheduled.sequence_id AND progress_scheduled.status = ' . $this->getConnection()->quote(Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED),
                [],
            )
            ->columns([
                'total_emails' => 'COUNT(DISTINCT progress_total.progress_id)',
                'sent_emails' => 'COUNT(DISTINCT progress_sent.progress_id)',
                'scheduled_emails' => 'COUNT(DISTINCT progress_scheduled.progress_id)',
            ])
            ->group('main_table.sequence_id');

        return $this;
    }

    /**
     * Filter sequences that have coupon generation enabled
     */
    public function addCouponFilter(): self
    {
        $this->addFieldToFilter('generate_coupon', 1);
        return $this;
    }

    /**
     * Filter sequences scheduled to run
     */
    public function addScheduledFilter(): self
    {
        $progressTable = $this->getTable('customer_segment_sequence_progress');

        $this->getSelect()->join(
            ['progress' => $progressTable],
            'main_table.sequence_id = progress.sequence_id',
            [],
        )
        ->where('progress.status = ?', Maho_CustomerSegmentation_Model_SequenceProgress::STATUS_SCHEDULED)
        ->where('progress.scheduled_at <= ?', Mage::getSingleton('core/date')->gmtDate())
        ->group('main_table.sequence_id');

        return $this;
    }
}
