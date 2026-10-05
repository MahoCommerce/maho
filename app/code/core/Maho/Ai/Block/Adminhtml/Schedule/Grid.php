<?php

/**
 * Grid of the scheduled assistant tasks of every administrator.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('aiScheduleGrid');
        $this->setDefaultSort('schedule_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $collection = Mage::getResourceModel('ai/task_schedule_collection');
        $collection->getSelect()->joinLeft(
            ['u' => $collection->getTable('admin/user')],
            'u.user_id = main_table.admin_user_id',
            ['username'],
        );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('ai');

        $this->addColumn('schedule_id', [
            'header' => $helper->__('ID'),
            'index' => 'schedule_id',
            'width' => '60px',
            'type' => 'number',
        ]);
        $this->addColumn('title', [
            'header' => $helper->__('Title'),
            'index' => 'title',
        ]);
        $this->addColumn('instruction', [
            'header' => $helper->__('Instruction'),
            'index' => 'instruction',
            'truncate' => 200,
            'nl2br' => true,
        ]);
        $this->addColumn('cron_expr', [
            'header' => $helper->__('Schedule (store time)'),
            'index' => 'cron_expr',
            'width' => '120px',
        ]);
        $this->addColumn('notify', [
            'header' => $helper->__('Notifies'),
            'index' => 'notify',
            'width' => '120px',
            'frame_callback' => $this->renderAudience(...),
        ]);
        $this->addColumn('username', [
            'header' => $helper->__('Administrator'),
            'index' => 'username',
            'filter_index' => 'u.username',
            'width' => '120px',
        ]);
        $this->addColumn('is_active', [
            'header' => $helper->__('Status'),
            'index' => 'is_active',
            'filter_index' => 'main_table.is_active',
            'type' => 'options',
            'options' => [1 => $helper->__('Active'), 0 => $helper->__('Paused')],
            'width' => '90px',
        ]);
        $this->addColumn('next_run_at', [
            'header' => $helper->__('Next Run'),
            'index' => 'next_run_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);
        $this->addColumn('last_run_at', [
            'header' => $helper->__('Last Run'),
            'index' => 'last_run_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);

        $this->addColumn('action', [
            'header' => $helper->__('Action'),
            'index' => 'schedule_id',
            'width' => '110px',
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
            'frame_callback' => $this->renderActions(...),
        ]);

        return parent::_prepareColumns();
    }

    public function renderAudience(mixed $value, Maho_Ai_Model_Task_Schedule $row): string
    {
        $helper = Mage::helper('ai');

        return match ((string) $row->getNotify()) {
            '', Maho_Ai_Model_Task_Schedule::NOTIFY_SELF => $this->escapeHtml($helper->__('The creator')),
            Maho_Ai_Model_Task_Schedule::NOTIFY_EVERYONE => $this->escapeHtml($helper->__('Every administrator')),
            default => $this->escapeHtml($helper->__('Allowed: %s', (string) $row->getNotify())),
        };
    }

    /** The mass actions, for one row: Run Now, Pause or Resume, Edit and Delete. */
    public function renderActions(mixed $value, Maho_Ai_Model_Task_Schedule $row): string
    {
        $helper = Mage::helper('ai');
        $id = ['id' => $row->getId()];
        $links = [
            [$helper->__('Run Now'), $this->getUrl('*/*/run', $id), null],
            $row->getIsActive()
                ? [$helper->__('Pause'), $this->getUrl('*/*/status', $id + ['active' => 0]), null]
                : [$helper->__('Resume'), $this->getUrl('*/*/status', $id + ['active' => 1]), null],
            [$helper->__('Edit'), $this->getUrl('*/*/edit', $id), null],
            [$helper->__('Delete'), $this->getUrl('*/*/delete', $id), $helper->__('Are you sure?')],
        ];
        $html = [];
        foreach ($links as [$label, $url, $confirm]) {
            $onclick = $confirm === null ? '' : sprintf(' onclick="return confirm(\'%s\')"', $this->escapeHtml($this->jsQuoteEscape($confirm)));
            $html[] = sprintf('<a href="%s"%s>%s</a>', $this->escapeUrl($url), $onclick, $this->escapeHtml($label));
        }

        return implode('<br>', $html);
    }

    #[\Override]
    protected function _prepareMassaction(): static
    {
        $helper = Mage::helper('ai');
        $this->setMassactionIdField('schedule_id');
        $this->getMassactionBlock()->setFormFieldName('schedule_id');
        $this->getMassactionBlock()->addItem('run', [
            'label' => $helper->__('Run Now'),
            'url' => $this->getUrl('*/*/massRun'),
        ]);
        $this->getMassactionBlock()->addItem('enable', [
            'label' => $helper->__('Resume'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 1]),
        ]);
        $this->getMassactionBlock()->addItem('disable', [
            'label' => $helper->__('Pause'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 0]),
        ]);
        $this->getMassactionBlock()->addItem('delete', [
            'label' => $helper->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => $helper->__('Are you sure?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
