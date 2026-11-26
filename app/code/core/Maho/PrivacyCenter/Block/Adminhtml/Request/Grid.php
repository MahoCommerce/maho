<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Block_Adminhtml_Request_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('privacyRequestGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('privacycenter/request_collection'));
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('privacycenter');
        $this->addColumn('request_id', [
            'header' => $helper->__('ID'),
            'index' => 'request_id',
            'type' => 'number',
            'width' => '60px',
        ]);
        $this->addColumn('created_at', [
            'header' => $helper->__('Created'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);
        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header' => $helper->__('Store View'),
                'index' => 'store_id',
                'type' => 'store',
                'store_view' => true,
            ]);
        }
        $this->addColumn('type', [
            'header' => $helper->__('Type'),
            'index' => 'type',
            'type' => 'options',
            'options' => Mage::getModel('privacycenter/source_type')->toOptionHash(),
            'width' => '120px',
        ]);
        $this->addColumn('email', [
            'header' => $helper->__('Email'),
            'index' => 'email',
        ]);
        $this->addColumn('customer_id', [
            'header' => $helper->__('Customer'),
            'index' => 'customer_id',
            'width' => '100px',
            'frame_callback' => $this->decorateCustomer(...),
        ]);
        $this->addColumn('status', [
            'header' => $helper->__('Status'),
            'index' => 'status',
            'type' => 'options',
            'options' => Mage::getModel('privacycenter/source_status')->toOptionHash(),
            'width' => '140px',
        ]);
        $this->addColumn('scheduled_at', [
            'header' => $helper->__('Erasure Date'),
            'index' => 'scheduled_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);
        $this->addColumn('approved_at', [
            'header' => $helper->__('Approved'),
            'index' => 'approved_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);
        $this->addColumn('processed_at', [
            'header' => $helper->__('Processed'),
            'index' => 'processed_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);
        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));
        return parent::_prepareColumns();
    }

    /**
     * @param string|null $value
     * @param Maho_PrivacyCenter_Model_Request $row
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @param bool $isExport
     */
    public function decorateCustomer($value, $row, $column, $isExport): string
    {
        if (!$row->getCustomerId()) {
            return $isExport ? '' : Mage::helper('privacycenter')->__('Guest');
        }
        if ($isExport) {
            return (string) $row->getCustomerId();
        }
        $url = $this->getUrl('*/customer/edit', ['id' => $row->getCustomerId()]);
        return '<a href="' . $this->escapeUrl($url) . '">#' . (int) $row->getCustomerId() . '</a>';
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        if (!Mage::getSingleton('admin/session')->isAllowed('customer/privacy/process')) {
            return $this;
        }
        $helper = Mage::helper('privacycenter');
        $this->setMassactionIdField('request_id');
        $this->getMassactionBlock()->setFormFieldName('request');
        $this->getMassactionBlock()->addItem('approve', [
            'label' => $helper->__('Approve Erasure'),
            'url' => $this->getUrl('*/*/massApprove'),
            'confirm' => $helper->__('Approve the selected erasure requests? Each one runs at the end of its cooling-off period.'),
        ]);
        $this->getMassactionBlock()->addItem('reject', [
            'label' => $helper->__('Reject'),
            'url' => $this->getUrl('*/*/massReject'),
            'confirm' => $helper->__('Reject the selected requests?'),
        ]);
        return $this;
    }

    #[\Override]
    public function getRowUrl(\Maho\DataObject $row): string
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
