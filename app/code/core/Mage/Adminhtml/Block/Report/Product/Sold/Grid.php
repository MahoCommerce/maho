<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Product_Sold_Grid extends Mage_Adminhtml_Block_Report_Grid
{
    /**
     * Sub report size
     *
     * @var int
     */
    protected $_subReportSize = 0;

    /**
     * Initialize Grid settings
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('gridProductsSold');
    }

    /**
     * Prepare collection object for grid
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareCollection()
    {
        parent::_prepareCollection();
        $this->getCollection()
            ->initReport('reports/product_sold_collection');
        return $this;
    }

    /**
     * Prepare Grid columns
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => Mage::helper('reports')->__('Product Name'),
            'index'     => 'order_items_name',
        ]);

        $this->addColumn('ordered_qty', [
            'header'    => Mage::helper('reports')->__('Quantity Ordered'),
            'width'     => '120px',
            'index'     => 'ordered_qty',
            'total'     => 'sum',
            'type'      => 'number',
        ]);

        $this->addExportType('*/*/exportSoldCsv', Mage::helper('reports')->__('CSV'));
        $this->addExportType('*/*/exportSoldExcel', Mage::helper('reports')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
