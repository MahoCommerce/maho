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

class Mage_Adminhtml_Block_Report_Sales_Invoiced_Grid extends Mage_Adminhtml_Block_Report_Grid_Abstract
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
        return ($this->getFilterData()->getData('report_type') == 'created_at_invoice')
            ? 'sales/report_invoiced_collection_invoiced'
            : 'sales/report_invoiced_collection_order';
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
            'totals_label'  => Mage::helper('sales')->__('Total'),
            'html_decorators' => ['nobr'],
        ]);

        $this->addColumn('orders_count', [
            'header'    => Mage::helper('sales')->__('Number of Orders'),
            'index'     => 'orders_count',
            'type'      => 'number',
            'total'     => 'sum',
            'sortable'  => false,
        ]);

        $this->addColumn('orders_invoiced', [
            'header'    => Mage::helper('sales')->__('Number of Invoiced Orders'),
            'index'     => 'orders_invoiced',
            'type'      => 'number',
            'total'     => 'sum',
            'sortable'  => false,
        ]);

        if ($this->getFilterData()->getStoreIds()) {
            $this->setStoreIds(explode(',', $this->getFilterData()->getStoreIds()));
        }
        $currencyCode = $this->getCurrentCurrencyCode();
        $rate = $this->getRate($currencyCode);

        $this->addColumn('invoiced', [
            'header'        => Mage::helper('sales')->__('Total Invoiced'),
            'type'          => 'currency',
            'currency_code' => $currencyCode,
            'index'         => 'invoiced',
            'total'         => 'sum',
            'sortable'      => false,
            'rate'          => $rate,
        ]);

        $this->addColumn('invoiced_captured', [
            'header'        => Mage::helper('sales')->__('Total Invoiced Paid'),
            'type'          => 'currency',
            'currency_code' => $currencyCode,
            'index'         => 'invoiced_captured',
            'total'         => 'sum',
            'sortable'      => false,
            'rate'          => $rate,
        ]);

        $this->addColumn('invoiced_not_captured', [
            'header'        => Mage::helper('sales')->__('Total Invoiced not Paid'),
            'type'          => 'currency',
            'currency_code' => $currencyCode,
            'index'         => 'invoiced_not_captured',
            'total'         => 'sum',
            'sortable'      => false,
            'rate'          => $rate,
        ]);

        $this->addExportType('*/*/exportInvoicedCsv', Mage::helper('adminhtml')->__('CSV'));
        $this->addExportType('*/*/exportInvoicedExcel', Mage::helper('adminhtml')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
