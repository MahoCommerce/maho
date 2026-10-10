<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Chat_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('aiChatGrid');
        $this->setDefaultSort('updated_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $collection = Mage::getResourceModel('ai/conversation_collection');
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

        $this->addColumn('conversation_id', [
            'header' => $helper->__('ID'),
            'index'  => 'conversation_id',
            'width'  => '60px',
            'type'   => 'number',
        ]);
        $this->addColumn('title', [
            'header' => $helper->__('Title'),
            'index'  => 'title',
        ]);
        $this->addColumn('username', [
            'header' => $helper->__('Administrator'),
            'index'  => 'username',
            'filter_index' => 'u.username',
            'width'  => '140px',
        ]);
        $this->addColumn('model', [
            'header' => $helper->__('Model'),
            'index'  => 'model',
            'width'  => '160px',
        ]);
        $this->addColumn('updated_at', [
            'header' => $helper->__('Last Activity'),
            'index'  => 'updated_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): static
    {
        $this->setMassactionIdField('conversation_id');
        $this->getMassactionBlock()->setFormFieldName('conversation_id');
        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('ai')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('ai')->__('Are you sure?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return '';
    }
}
