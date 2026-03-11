<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Core_Block_Adminhtml_Email_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('emailLogGrid');
        $this->setDefaultSort('created_at');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('core/email_log_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $helper = Mage::helper('core');

        $this->addColumn('log_id', [
            'header' => $helper->__('ID'),
            'align'  => 'right',
            'width'  => '50px',
            'index'  => 'log_id',
            'type'   => 'number',
        ]);

        $this->addColumn('subject', [
            'header' => $helper->__('Subject'),
            'index'  => 'subject',
        ]);

        $this->addColumn('email_to', [
            'header' => $helper->__('To'),
            'index'  => 'email_to',
        ]);

        $this->addColumn('email_from', [
            'header' => $helper->__('From'),
            'index'  => 'email_from',
        ]);

        $this->addColumn('content_type', [
            'header'  => $helper->__('Type'),
            'width'   => '60px',
            'index'   => 'content_type',
            'type'    => 'options',
            'options' => [
                'html' => 'HTML',
                'text' => 'Text',
            ],
        ]);

        $this->addColumn('status', [
            'header'  => $helper->__('Status'),
            'width'   => '80px',
            'index'   => 'status',
            'type'    => 'options',
            'options' => [
                'sent'   => $helper->__('Sent'),
                'failed' => $helper->__('Failed'),
            ],
        ]);

        $this->addColumn('created_at', [
            'header' => $helper->__('Date'),
            'width'  => '150px',
            'index'  => 'created_at',
            'type'   => 'datetime',
        ]);

        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));
        $this->addExportType('*/*/exportXml', $helper->__('Excel XML'));

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('log_id');
        $this->getMassactionBlock()->setFormFieldName('log_ids');

        $this->getMassactionBlock()->addItem('delete', [
            'label'   => Mage::helper('core')->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('core')->__('Are you sure you want to delete the selected email log entries?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
