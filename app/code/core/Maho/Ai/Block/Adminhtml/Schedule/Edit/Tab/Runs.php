<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Edit_Tab_Runs extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public function __construct()
    {
        parent::__construct();
        $this->setId('aiScheduleRunGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setFilterVisibility(false);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $schedule = Mage::registry('ai_task_schedule');
        /** @var Maho_Ai_Model_Resource_Task_Collection $collection */
        $collection = Mage::getModel('ai/task')->getCollection();
        $collection->addFieldToFilter('schedule_id', $schedule instanceof Maho_Ai_Model_Task_Schedule ? (int) $schedule->getId() : 0);
        // A run that ended with writes to confirm is complete as a task, but it is not done for the owner.
        $pending = $collection->getConnection()->select()
            ->from(['m' => $collection->getTable('ai/conversation_message')], [new Maho\Db\Expr('COUNT(*)')])
            ->where('m.conversation_id = main_table.conversation_id')
            ->where('m.tool_status = ?', Maho_Ai_Model_Conversation_Message::TOOL_PENDING);
        $collection->getSelect()->columns(['pending_writes' => new Maho\Db\Expr('(' . $pending . ')')]);
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('ai');

        $this->addColumn('created_at', [
            'header' => $helper->__('Started'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '170px',
        ]);
        $this->addColumn('trigger', [
            'header' => $helper->__('Trigger'),
            'index' => 'context',
            'sortable' => false,
            'frame_callback' => $this->decorateTrigger(...),
            'width' => '100px',
        ]);
        $this->addColumn('status', [
            'header' => $helper->__('Status'),
            'index' => 'status',
            'frame_callback' => $this->decorateStatus(...),
            'width' => '170px',
        ]);
        $this->addColumn('result', [
            'header' => $helper->__('Result'),
            'index' => 'response',
            'sortable' => false,
            'frame_callback' => $this->decorateResult(...),
        ]);
        $this->addColumn('tokens', [
            'header' => $helper->__('Tokens'),
            'index' => 'input_tokens',
            'type' => 'number',
            'frame_callback' => $this->decorateTokens(...),
            'width' => '110px',
        ]);
        $this->addColumn('conversation', [
            'header' => $helper->__('Conversation'),
            'index' => 'conversation_id',
            'sortable' => false,
            'frame_callback' => $this->decorateConversation(...),
            'width' => '120px',
        ]);

        return parent::_prepareColumns();
    }

    /** The cell value is already escaped HTML, so the callbacks read the raw values from the row. */
    public function decorateTrigger(string $value, Maho_Ai_Model_Task $row): string
    {
        return $this->escapeHtml(empty($row->getContextArray()['manual']) ? Mage::helper('ai')->__('Schedule') : Mage::helper('ai')->__('Run Now'));
    }

    public function decorateStatus(string $value, Maho_Ai_Model_Task $row): string
    {
        $helper = Mage::helper('ai');
        $status = (string) $row->getData('status');
        if ($status === Maho_Ai_Model_Task::STATUS_COMPLETE && (int) $row->getData('pending_writes') > 0) {
            $status = self::STATUS_AWAITING_CONFIRMATION;
        }
        $labels = [
            Maho_Ai_Model_Task::STATUS_PENDING => $helper->__('Waiting for a queue worker'),
            Maho_Ai_Model_Task::STATUS_PROCESSING => $helper->__('Running'),
            Maho_Ai_Model_Task::STATUS_COMPLETE => $helper->__('Done'),
            Maho_Ai_Model_Task::STATUS_FAILED => $helper->__('Failed'),
            Maho_Ai_Model_Task::STATUS_CANCELLED => $helper->__('Cancelled'),
            self::STATUS_AWAITING_CONFIRMATION => $helper->__('Waiting for confirmation'),
        ];

        return $this->escapeHtml($labels[$status] ?? $status);
    }

    /** The answer of the run, or its error, cut to a few lines. The full text is in the conversation. */
    public function decorateResult(string $value, Maho_Ai_Model_Task $row): string
    {
        $text = $row->getData('status') === Maho_Ai_Model_Task::STATUS_FAILED
            ? (string) $row->getData('error_message')
            : (string) $row->getData('response');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        $short = mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text;

        return '<span title="' . $this->escapeHtml($text) . '">' . $this->escapeHtml($short) . '</span>';
    }

    public function decorateTokens(string $value, Maho_Ai_Model_Task $row): string
    {
        return $this->escapeHtml(number_format((int) $row->getData('input_tokens') + (int) $row->getData('output_tokens')));
    }

    public function decorateConversation(string $value, Maho_Ai_Model_Task $row): string
    {
        if (!$row->getConversationId()) {
            return '';
        }

        return '<a href="' . $this->escapeUrl($this->getUrl('adminhtml/ai_chat/open', ['id' => $row->getConversationId()])) . '">'
            . $this->escapeHtml(Mage::helper('ai')->__('Open')) . '</a>';
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/runs', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return '';
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('ai')->__('Runs');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab(): bool
    {
        $schedule = Mage::registry('ai_task_schedule');

        return $schedule instanceof Maho_Ai_Model_Task_Schedule && (bool) $schedule->getId();
    }

    #[\Override]
    public function isHidden(): bool
    {
        return !$this->canShowTab();
    }
}
