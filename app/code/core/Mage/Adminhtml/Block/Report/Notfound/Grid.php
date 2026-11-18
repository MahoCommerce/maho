<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Report_Notfound_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('notFoundReportGrid');
        $this->setDefaultSort('hits');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('reports/notFound_collection')->addRedirectTarget());
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('reports');

        $this->addColumn('request_path', [
            'header' => $helper->__('Address'),
            'index' => 'request_path',
            'filter_index' => 'main_table.request_path',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header' => Mage::helper('adminhtml')->__('Store View'),
                'index' => 'store_id',
                'filter_index' => 'main_table.store_id',
                'type' => 'store',
                'store_view' => true,
                'sortable' => false,
            ]);
        }

        $this->addColumn('hits', [
            'header' => $helper->__('Hits'),
            'index' => 'hits',
            'type' => 'number',
            'width' => '80px',
        ]);

        $this->addColumn('referer', [
            'header' => $helper->__('Last Referer'),
            'index' => 'referer',
            'renderer' => 'adminhtml/report_notfound_grid_renderer_referer',
        ]);

        $this->addColumn('first_seen_at', [
            'header' => $helper->__('First Seen'),
            'index' => 'first_seen_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);

        $this->addColumn('last_seen_at', [
            'header' => $helper->__('Last Seen'),
            'index' => 'last_seen_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);

        $this->addColumn('redirect_target', [
            'header' => $helper->__('Redirects To'),
            'index' => 'redirect_target',
            'filter' => 'adminhtml/widget_grid_column_filter_select',
            'options' => [
                1 => Mage::helper('adminhtml')->__('Yes'),
                0 => Mage::helper('adminhtml')->__('No'),
            ],
            'filter_condition_callback' => $this->filterRedirect(...),
            'sortable' => false,
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('adminhtml')->__('Action'),
            'renderer' => 'adminhtml/report_notfound_grid_renderer_redirect',
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
            'width' => '80px',
        ]);

        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('not_found_id');
        $this->setMassactionIdFilter('main_table.not_found_id');
        $this->getMassactionBlock()->setFormFieldName('not_found_id');
        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('reports')->__('Are you sure?'),
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
        return '';
    }

    private function filterRedirect(
        Mage_Reports_Model_Resource_NotFound_Collection $collection,
        Mage_Adminhtml_Block_Widget_Grid_Column $column,
    ): void {
        $value = $column->getFilter()->getValue();
        if ($value === null || $value === '') {
            return;
        }
        $collection->addRedirectFilter((bool) $value);
    }
}
