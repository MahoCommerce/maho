<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Block_Adminhtml_Payment_Restriction_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('paymentRestrictionGrid');
        $this->setDefaultSort('restriction_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getModel('payment/restriction')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('restriction_id', [
            'header'    => Mage::helper('payment')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'restriction_id',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('payment')->__('Name'),
            'align'     => 'left',
            'index'     => 'name',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('payment')->__('Status'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'status',
            'type'      => 'options',
            'options'   => [
                1 => Mage::helper('payment')->__('Enabled'),
                0 => Mage::helper('payment')->__('Disabled'),
            ],
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('payment')->__('Created At'),
            'align'     => 'left',
            'width'     => '120px',
            'type'      => 'date',
            'index'     => 'created_at',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('payment')->__('Action'),
            'width'     => '100',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption' => Mage::helper('payment')->__('Edit'),
                    'url'     => ['base' => '*/*/edit'],
                    'field'   => 'id',
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('restriction_id');
        $this->getMassactionBlock()->setFormFieldName('restriction');

        $this->getMassactionBlock()->addItem('delete', [
            'label'    => Mage::helper('payment')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('payment')->__('Are you sure?'),
        ]);

        $statuses = [
            ['value' => 1, 'label' => Mage::helper('payment')->__('Enabled')],
            ['value' => 0, 'label' => Mage::helper('payment')->__('Disabled')],
        ];

        $this->getMassactionBlock()->addItem('status', [
            'label'      => Mage::helper('payment')->__('Change status'),
            'url'        => $this->getUrl('*/*/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => Mage::helper('payment')->__('Status'),
                    'values' => $statuses,
                ],
            ],
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
