<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Scan_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('accessibilityScanGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('accessibilityscan/scan_collection'));
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('accessibilityscan');

        $this->addColumn('scan_id', [
            'header' => $helper->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'scan_id',
            'type' => 'number',
        ]);

        $this->addColumn('url', [
            'header' => $helper->__('URL'),
            'align' => 'left',
            'index' => 'url',
        ]);

        $this->addColumn('wcag_level', [
            'header' => $helper->__('WCAG Level'),
            'align' => 'center',
            'width' => '90px',
            'index' => 'wcag_level',
            'type' => 'options',
            'options' => ['A' => 'A', 'AA' => 'AA', 'AAA' => 'AAA'],
        ]);

        $this->addColumn('status', [
            'header' => $helper->__('Status'),
            'align' => 'center',
            'width' => '90px',
            'index' => 'status',
            'type' => 'options',
            'options' => [
                Maho_AccessibilityScan_Model_Scan::STATUS_PENDING => $helper->__('Pending'),
                Maho_AccessibilityScan_Model_Scan::STATUS_RUNNING => $helper->__('Running'),
                Maho_AccessibilityScan_Model_Scan::STATUS_COMPLETE => $helper->__('Complete'),
                Maho_AccessibilityScan_Model_Scan::STATUS_FAILED => $helper->__('Failed'),
            ],
        ]);

        $this->addColumn('total_violations', [
            'header' => $helper->__('Violations'),
            'align' => 'center',
            'width' => '80px',
            'index' => 'total_violations',
            'type' => 'number',
        ]);

        $this->addColumn('violations_critical', [
            'header' => $helper->__('Critical'),
            'align' => 'center',
            'width' => '70px',
            'index' => 'violations_critical',
            'type' => 'number',
        ]);

        $this->addColumn('violations_serious', [
            'header' => $helper->__('Serious'),
            'align' => 'center',
            'width' => '70px',
            'index' => 'violations_serious',
            'type' => 'number',
        ]);

        $this->addColumn('created_at', [
            'header' => $helper->__('Started'),
            'align' => 'left',
            'width' => '160px',
            'index' => 'created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('completed_at', [
            'header' => $helper->__('Completed'),
            'align' => 'left',
            'width' => '160px',
            'index' => 'completed_at',
            'type' => 'datetime',
        ]);

        return parent::_prepareColumns();
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
