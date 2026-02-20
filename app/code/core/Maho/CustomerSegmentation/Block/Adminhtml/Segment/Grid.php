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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customerSegmentGrid');
        $this->setDefaultSort('segment_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getModel('customersegmentation/segment')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('segment_id', [
            'header'    => Mage::helper('customersegmentation')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'segment_id',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('customersegmentation')->__('Name'),
            'align'     => 'left',
            'index'     => 'name',
        ]);

        $this->addColumn('is_active', [
            'header'    => Mage::helper('customersegmentation')->__('Status'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'is_active',
            'type'      => 'options',
            'options'   => [
                1 => 'Active',
                0 => 'Inactive',
            ],
        ]);

        $this->addColumn('refresh_mode', [
            'header'    => Mage::helper('customersegmentation')->__('Mode'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'refresh_mode',
            'type'      => 'options',
            'options'   => [
                'auto'   => Mage::helper('customersegmentation')->__('Automatic'),
                'manual' => Mage::helper('customersegmentation')->__('Manual'),
            ],
        ]);

        $this->addColumn('matched_customers_count', [
            'header'    => Mage::helper('customersegmentation')->__('Customers'),
            'align'     => 'center',
            'width'     => '100px',
            'index'     => 'matched_customers_count',
            'type'      => 'number',
        ]);

        $this->addColumn('last_refresh_at', [
            'header'    => Mage::helper('customersegmentation')->__('Last Refreshed'),
            'align'     => 'left',
            'width'     => '160px',
            'type'      => 'datetime',
            'index'     => 'last_refresh_at',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('customersegmentation')->__('Action'),
            'width'     => '120px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption'   => Mage::helper('customersegmentation')->__('Edit'),
                    'url'       => ['base' => '*/*/edit'],
                    'field'     => 'id',
                ],
                [
                    'caption'   => Mage::helper('customersegmentation')->__('Refresh'),
                    'url'       => ['base' => '*/*/refresh'],
                    'field'     => 'id',
                    'confirm'   => Mage::helper('customersegmentation')->__('Are you sure you want to refresh this segment?'),
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
            'index'     => 'stores',
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('segment_id');
        $this->getMassactionBlock()->setFormFieldName('segment');

        $this->getMassactionBlock()->addItem('delete', [
            'label'    => Mage::helper('customersegmentation')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('customersegmentation')->__('Are you sure?'),
        ]);

        $statuses = [
            1 => Mage::helper('customersegmentation')->__('Active'),
            0 => Mage::helper('customersegmentation')->__('Inactive'),
        ];

        $this->getMassactionBlock()->addItem('status', [
            'label' => Mage::helper('customersegmentation')->__('Change status'),
            'url'   => $this->getUrl('*/*/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => Mage::helper('customersegmentation')->__('Status'),
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
