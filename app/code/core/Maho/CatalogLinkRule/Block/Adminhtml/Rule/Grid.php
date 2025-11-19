<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('cataloglinkruleGrid');
        $this->setDefaultSort('priority');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('cataloglinkrule/rule_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('rule_id', [
            'header'    => Mage::helper('cataloglinkrule')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'rule_id',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('cataloglinkrule')->__('Rule Name'),
            'align'     => 'left',
            'index'     => 'name',
        ]);

        $this->addColumn('link_type_id', [
            'header'    => Mage::helper('cataloglinkrule')->__('Link Type'),
            'width'     => '150px',
            'index'     => 'link_type_id',
            'type'      => 'options',
            'options'   => Mage::helper('cataloglinkrule')->getLinkTypes(),
        ]);

        $this->addColumn('priority', [
            'header'    => Mage::helper('cataloglinkrule')->__('Priority'),
            'width'     => '100px',
            'align'     => 'right',
            'index'     => 'priority',
        ]);

        $this->addColumn('is_active', [
            'header'    => Mage::helper('cataloglinkrule')->__('Status'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'is_active',
            'type'      => 'options',
            'options'   => [
                0 => Mage::helper('cataloglinkrule')->__('Disabled'),
                1 => Mage::helper('cataloglinkrule')->__('Enabled'),
            ],
        ]);

        $this->addColumn('from_date', [
            'header'    => Mage::helper('cataloglinkrule')->__('From Date'),
            'width'     => '120px',
            'index'     => 'from_date',
            'type'      => 'date',
        ]);

        $this->addColumn('to_date', [
            'header'    => Mage::helper('cataloglinkrule')->__('To Date'),
            'width'     => '120px',
            'index'     => 'to_date',
            'type'      => 'date',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('cataloglinkrule')->__('Action'),
            'width'     => '100px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption' => Mage::helper('cataloglinkrule')->__('Edit'),
                    'url'     => ['base' => '*/*/edit'],
                    'field'   => 'id',
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('rule_id');
        $this->getMassactionBlock()->setFormFieldName('rule_ids');

        $this->getMassactionBlock()->addItem('delete', [
            'label'    => Mage::helper('cataloglinkrule')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('cataloglinkrule')->__('Are you sure?'),
        ]);

        $this->getMassactionBlock()->addItem('status', [
            'label' => Mage::helper('cataloglinkrule')->__('Change status'),
            'url'  => $this->getUrl('*/*/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name' => 'status',
                    'type' => 'select',
                    'class' => 'required-entry',
                    'label' => Mage::helper('cataloglinkrule')->__('Status'),
                    'values' => [
                        1 => Mage::helper('cataloglinkrule')->__('Enabled'),
                        0 => Mage::helper('cataloglinkrule')->__('Disabled'),
                    ],
                ],
            ],
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl(mixed $row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
