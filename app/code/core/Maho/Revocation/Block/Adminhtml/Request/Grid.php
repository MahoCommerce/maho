<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Block_Adminhtml_Request_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('revocationRequestGrid');
        $this->setDefaultSort('received_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('revocation/request_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('revocation');

        $this->addColumn('request_id', [
            'header' => $helper->__('ID'),
            'align' => 'right',
            'width' => '60px',
            'index' => 'request_id',
            'type' => 'number',
        ]);

        $this->addColumn('received_at', [
            'header' => $helper->__('Received'),
            'index' => 'received_at',
            'width' => '160px',
            'type' => 'datetime',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header' => $helper->__('Store View'),
                'index' => 'store_id',
                'type' => 'store',
                'store_view' => true,
            ]);
        }

        $this->addColumn('verified', [
            'header' => $helper->__('Verified'),
            'index' => 'verified',
            'width' => '80px',
            'type' => 'options',
            'options' => [
                0 => $helper->__('No'),
                1 => $helper->__('Yes'),
            ],
        ]);

        $this->addColumn('customer_name', [
            'header' => $helper->__('Customer Name'),
            'index' => 'customer_name',
        ]);

        $this->addColumn('email', [
            'header' => $helper->__('Email'),
            'index' => 'email',
        ]);

        $this->addColumn('order_reference', [
            'header' => $helper->__('Order Reference'),
            'index' => 'order_reference',
            'width' => '120px',
        ]);

        $this->addColumn('order_id', [
            'header' => $helper->__('Matched Order'),
            'index' => 'order_id',
            'width' => '100px',
            'frame_callback' => [$this, 'decorateOrder'],
        ]);

        $this->addColumn('suppressed_at', [
            'header' => $helper->__('Email Suppressed'),
            'index' => 'suppressed_at',
            'width' => '140px',
            'type' => 'datetime',
        ]);

        $this->addColumn('processed_status', [
            'header' => $helper->__('Processed Status'),
            'index' => 'processed_status',
            'width' => '120px',
            'type' => 'options',
            'options' => Mage::getModel('revocation/source_processedStatus')->toOptionHash(),
        ]);

        $this->addColumn('processed_at', [
            'header' => $helper->__('Processed At'),
            'index' => 'processed_at',
            'width' => '160px',
            'type' => 'datetime',
        ]);

        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));

        return parent::_prepareColumns();
    }

    /**
     * @param string $value
     * @param Maho_Revocation_Model_Request $row
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @param bool $isExport
     */
    public function decorateOrder($value, $row, $column, $isExport): string
    {
        if (!$row->getOrderId()) {
            return '';
        }
        if ($isExport) {
            return (string) $row->getOrderId();
        }
        $url = $this->getUrl('*/sales_order/view', ['order_id' => $row->getOrderId()]);
        return '<a href="' . $this->escapeHtml($url) . '">#' . (int) $row->getOrderId() . '</a>';
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $helper = Mage::helper('revocation');
        $this->setMassactionIdField('request_id');
        $this->getMassactionBlock()->setFormFieldName('request');

        $this->getMassactionBlock()->addItem('accept', [
            'label' => $helper->__('Mark as Accepted'),
            'url' => $this->getUrl('*/*/massAccept'),
            'confirm' => $helper->__('Mark the selected requests as accepted? The matched orders are not modified.'),
        ]);

        $this->getMassactionBlock()->addItem('reject', [
            'label' => $helper->__('Mark as Rejected'),
            'url' => $this->getUrl('*/*/massReject'),
            'confirm' => $helper->__('Mark the selected requests as rejected? The matched orders are not modified.'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
