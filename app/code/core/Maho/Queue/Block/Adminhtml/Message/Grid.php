<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

class Maho_Queue_Block_Adminhtml_Message_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('queueMessageGrid');
        $this->setDefaultSort('message_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $this->setCollection(Mage::getModel('queue/message')->getCollection());
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('queue');

        $this->addColumn('message_id', [
            'header' => $helper->__('ID'),
            'index'  => 'message_id',
            'width'  => '60px',
            'type'   => 'number',
        ]);

        $this->addColumn('queue', [
            'header' => $helper->__('Queue'),
            'index'  => 'queue',
            'width'  => '100px',
        ]);

        $this->addColumn('message_class', [
            'header' => $helper->__('Message'),
            'index'  => 'message_class',
        ]);

        $this->addColumn('status', [
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'width'   => '100px',
            'type'    => 'options',
            'options' => Maho_Queue_Model_Message::getStatusOptions(),
        ]);

        $this->addColumn('retries', [
            'header' => $helper->__('Retries'),
            'index'  => 'retries',
            'width'  => '70px',
            'type'   => 'number',
        ]);

        $this->addColumn('error_message', [
            'header'         => $helper->__('Error'),
            'index'          => 'error_message',
            'frame_callback' => [$this, 'decorateError'],
        ]);

        $this->addColumn('available_at', [
            'header' => $helper->__('Available'),
            'index'  => 'available_at',
            'width'  => '140px',
            'type'   => 'datetime',
        ]);

        $this->addColumn('created_at', [
            'header' => $helper->__('Queued'),
            'index'  => 'created_at',
            'width'  => '140px',
            'type'   => 'datetime',
        ]);

        $this->addColumn('action', [
            'header'   => $helper->__('Action'),
            'width'    => '80px',
            'type'     => 'action',
            'getter'   => 'getId',
            'actions'  => [
                [
                    'caption' => $helper->__('View'),
                    'url'     => ['base' => '*/*/view'],
                    'field'   => 'id',
                ],
            ],
            'filter'   => false,
            'sortable' => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): static
    {
        $helper = Mage::helper('queue');
        $this->setMassactionIdField('message_id');
        $this->getMassactionBlock()->setFormFieldName('message');

        $this->getMassactionBlock()->addItem('retry', [
            'label'   => $helper->__('Retry'),
            'url'     => $this->getUrl('*/*/massRetry'),
            'confirm' => $helper->__('Re-queue the selected messages?'),
        ]);

        $this->getMassactionBlock()->addItem('discard', [
            'label'   => $helper->__('Discard'),
            'url'     => $this->getUrl('*/*/massDiscard'),
            'confirm' => $helper->__('Permanently delete the selected messages?'),
        ]);

        return $this;
    }

    public function decorateError(mixed $value): string
    {
        $error = trim((string) $value);
        if ($error === '') {
            return '';
        }

        return $this->escapeHtml(mb_strimwidth($error, 0, 120, '...'));
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }
}
