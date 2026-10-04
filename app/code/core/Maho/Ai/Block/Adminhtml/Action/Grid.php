<?php

/**
 * The audit trail: one row per write the assistant ran, denied or failed, with who confirmed it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Action_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('aiActionGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $collection = Mage::getResourceModel('ai/conversation_message_collection');
        $collection->addFieldToFilter('main_table.is_write', 1)
            ->addFieldToFilter('main_table.tool_status', ['neq' => Maho_Ai_Model_Conversation_Message::TOOL_PENDING]);
        $collection->getSelect()
            ->joinLeft(
                ['c' => $collection->getTable('ai/conversation')],
                'c.conversation_id = main_table.conversation_id',
                ['conversation_title' => 'title', 'admin_user_id', 'conversation_model' => 'model'],
            )
            ->joinLeft(
                ['u' => $collection->getTable('admin/user')],
                'u.user_id = c.admin_user_id',
                ['username'],
            );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('ai');

        $this->addColumn('created_at', [
            'header' => $helper->__('When'),
            'index' => 'created_at',
            'filter_index' => 'main_table.created_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);
        $this->addColumn('username', [
            'header' => $helper->__('Administrator'),
            'index' => 'username',
            'filter_index' => 'u.username',
            'width' => '140px',
        ]);
        $this->addColumn('tool_name', [
            'header' => $helper->__('Action'),
            'index' => 'tool_name',
            'filter_index' => 'main_table.tool_name',
            'frame_callback' => $this->decorateTool(...),
            'width' => '200px',
        ]);
        $this->addColumn('tool_arguments', [
            'header' => $helper->__('Arguments'),
            'index' => 'tool_arguments',
            'filter_index' => 'main_table.tool_arguments',
            'frame_callback' => $this->decorateArguments(...),
        ]);
        $this->addColumn('tool_status', [
            'header' => $helper->__('Status'),
            'index' => 'tool_status',
            'filter_index' => 'main_table.tool_status',
            'type' => 'options',
            'options' => [
                Maho_Ai_Model_Conversation_Message::TOOL_DONE => $helper->__('Done'),
                Maho_Ai_Model_Conversation_Message::TOOL_ERROR => $helper->__('Failed'),
                Maho_Ai_Model_Conversation_Message::TOOL_DENIED => $helper->__('Denied'),
                Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED => $helper->__('Cancelled'),
            ],
            'width' => '90px',
        ]);
        $this->addColumn('undo_arguments', [
            'header' => $helper->__('Undo'),
            'index' => 'undo_arguments',
            'filter' => false,
            'sortable' => false,
            'frame_callback' => $this->decorateUndo(...),
            'width' => '90px',
        ]);
        $this->addColumn('conversation_title', [
            'header' => $helper->__('Conversation'),
            'index' => 'conversation_title',
            'filter_index' => 'c.title',
            'width' => '200px',
        ]);
        $this->addColumn('conversation_model', [
            'header' => $helper->__('Model'),
            'index' => 'conversation_model',
            'filter_index' => 'c.model',
            'width' => '140px',
        ]);
        $this->addExportType('*/*/exportActionsCsv', $helper->__('CSV'));

        return parent::_prepareColumns();
    }

    /** "content_cms_pages_update" reads as "Update cms pages", the way the panel shows it. */
    public function decorateTool(string $value): string
    {
        $parts = array_values(array_filter(explode('_', $value)));
        $verb = $parts === [] ? '' : $parts[count($parts) - 1];
        if (count($parts) > 2 && in_array($verb, ['create', 'update', 'delete'], true)) {
            $value = ucfirst($verb) . ' ' . implode(' ', array_slice($parts, 1, -1));
        }

        return $this->escapeHtml($value);
    }

    /** The arguments as one line of "key: value" pairs, cut at the width of the cell. */
    public function decorateArguments(string $value): string
    {
        try {
            $arguments = Mage::helper('core')->jsonDecode($value);
        } catch (\JsonException) {
            $arguments = null;
        }
        if (!is_array($arguments)) {
            return $this->escapeHtml(mb_substr($value, 0, 160));
        }
        $pairs = [];
        foreach ($arguments as $key => $argument) {
            $text = is_scalar($argument) || $argument === null ? (string) $argument : Mage::helper('core')->jsonEncode($argument);
            $pairs[] = $key . ': ' . (mb_strlen($text) > 60 ? mb_substr($text, 0, 60) . '…' : $text);
        }
        $line = implode(', ', $pairs);

        return '<span title="' . $this->escapeHtml($line) . '">' . $this->escapeHtml(mb_strlen($line) > 160 ? mb_substr($line, 0, 160) . '…' : $line) . '</span>';
    }

    public function decorateUndo(?string $value): string
    {
        return $value === null || $value === '' ? '' : $this->escapeHtml(Mage::helper('ai')->__('Available'));
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return '';
    }
}
