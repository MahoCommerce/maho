<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Report_Sales_Bestsellers_Grid extends Mage_Adminhtml_Block_Report_Grid_Abstract
{
    protected $_columnGroupBy = 'period';

    public function __construct()
    {
        parent::__construct();
        $this->setCountTotals(true);
    }

    #[\Override]
    public function getResourceCollectionName()
    {
        return 'sales/report_bestsellers_collection';
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('period', [
            'header'        => Mage::helper('sales')->__('Period'),
            'index'         => 'period',
            'width'         => 100,
            'sortable'      => false,
            'period_type'   => $this->getPeriodType(),
            'renderer'      => 'adminhtml/report_sales_grid_column_renderer_date',
            'totals_label'  => Mage::helper('adminhtml')->__('Total'),
            'html_decorators' => ['nobr'],
        ]);

        $this->addColumn('product_name', [
            'header'    => Mage::helper('sales')->__('Product Name'),
            'index'     => 'product_name',
            'type'      => 'string',
            'sortable'  => false,
        ]);

        if ($this->getFilterData()->getStoreIds()) {
            $this->setStoreIds(explode(',', $this->getFilterData()->getStoreIds()));
        }
        $currencyCode = $this->getCurrentCurrencyCode();

        $this->addColumn('product_price', [
            'type'          => 'currency',
            'currency_code' => $currencyCode,
            'index'         => 'product_price',
            'sortable'      => false,
            'rate'          => $this->getRate($currencyCode),
        ]);

        $this->addColumn('qty_ordered', [
            'header'    => Mage::helper('sales')->__('Quantity Ordered'),
            'index'     => 'qty_ordered',
            'type'      => 'number',
            'total'     => 'sum',
            'sortable'  => false,
        ]);

        $this->addExportType('*/*/exportBestsellersCsv', Mage::helper('adminhtml')->__('CSV'));
        $this->addExportType('*/*/exportBestsellersExcel', Mage::helper('adminhtml')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
