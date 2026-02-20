<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_Giftcard_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('giftcardGrid');
        $this->setDefaultSort('giftcard_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('giftcard/giftcard')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('giftcard_id', [
            'header' => Mage::helper('giftcard')->__('ID'),
            'align'  => 'right',
            'width'  => '50px',
            'index'  => 'giftcard_id',
        ]);

        $this->addColumn('code', [
            'header' => Mage::helper('giftcard')->__('Code'),
            'align'  => 'left',
            'index'  => 'code',
        ]);

        $this->addColumn('status', [
            'header'  => Mage::helper('giftcard')->__('Status'),
            'align'   => 'left',
            'width'   => '80px',
            'index'   => 'status',
            'type'    => 'options',
            'options' => [
                Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE => 'Active',
                Maho_Giftcard_Model_Giftcard::STATUS_USED => 'Used',
                Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED => 'Expired',
                Maho_Giftcard_Model_Giftcard::STATUS_DISABLED => 'Disabled',
            ],
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('website_id', [
                'header'  => Mage::helper('giftcard')->__('Website'),
                'align'   => 'left',
                'width'   => '100px',
                'index'   => 'website_id',
                'type'    => 'options',
                'options' => Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(),
            ]);
        }

        $this->addColumn('balance', [
            'header'   => Mage::helper('giftcard')->__('Balance'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'balance',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('initial_balance', [
            'header'   => Mage::helper('giftcard')->__('Initial Balance'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'initial_balance',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('recipient_email', [
            'header' => Mage::helper('giftcard')->__('Recipient Email'),
            'align'  => 'left',
            'index'  => 'recipient_email',
        ]);

        $this->addColumn('purchase_order_id', [
            'header' => Mage::helper('giftcard')->__('Order #'),
            'align'  => 'right',
            'width'  => '100px',
            'index'  => 'purchase_order_id',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Order',
        ]);

        $this->addColumn('expires_at', [
            'header' => Mage::helper('giftcard')->__('Expires'),
            'align'  => 'left',
            'width'  => '120px',
            'index'  => 'expires_at',
            'type'   => 'datetime',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('giftcard')->__('Created'),
            'align'  => 'left',
            'width'  => '120px',
            'index'  => 'created_at',
            'type'   => 'datetime',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('giftcard')->__('Action'),
            'width'     => '100',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption' => Mage::helper('giftcard')->__('Edit'),
                    'url'     => ['base' => '*/*/edit'],
                    'field'   => 'id',
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
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('giftcard_id');
        $this->getMassactionBlock()->setFormFieldName('giftcard');

        $this->getMassactionBlock()->addItem('delete', [
            'label'   => Mage::helper('giftcard')->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('giftcard')->__('Are you sure?'),
        ]);

        $statuses = [
            Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE => 'Active',
            Maho_Giftcard_Model_Giftcard::STATUS_DISABLED => 'Disabled',
        ];

        $this->getMassactionBlock()->addItem('status', [
            'label'      => Mage::helper('giftcard')->__('Change status'),
            'url'        => $this->getUrl('*/*/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => Mage::helper('giftcard')->__('Status'),
                    'values' => $statuses,
                ],
            ],
        ]);

        $this->getMassactionBlock()->addItem('print_pdf', [
            'label' => Mage::helper('giftcard')->__('Print PDF'),
            'url'   => $this->getUrl('*/giftcard_print/massPdf'),
        ]);

        return $this;
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
