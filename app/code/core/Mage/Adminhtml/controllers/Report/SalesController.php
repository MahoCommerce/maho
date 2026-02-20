<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Report_SalesController extends Mage_Adminhtml_Controller_Report_Abstract
{
    /**
     * Add report/sales breadcrumbs
     *
     * @return $this
     */
    #[\Override]
    public function _initAction()
    {
        parent::_initAction();
        $this->_addBreadcrumb(Mage::helper('reports')->__('Sales'), Mage::helper('reports')->__('Sales'));
        return $this;
    }

    public function salesAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Sales'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_ORDER_FLAG_CODE, 'sales');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/sales')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Sales Report'), Mage::helper('adminhtml')->__('Sales Report'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_sales.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    public function bestsellersAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Products'))->_title($this->__('Bestsellers'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_BESTSELLERS_FLAG_CODE, 'bestsellers');

        $this->_initAction()
            ->_setActiveMenu('report/products/bestsellers')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Products Bestsellers Report'), Mage::helper('adminhtml')->__('Products Bestsellers Report'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_bestsellers.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export bestsellers report grid to CSV format
     */
    public function exportBestsellersCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_bestsellers_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('bestsellers.csv', -1));
    }

    /**
     * Export bestsellers report grid to Excel XML format
     */
    public function exportBestsellersExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_bestsellers_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('bestsellers.xml', -1));
    }

    /**
     * Export sales report grid to CSV format
     */
    public function exportSalesCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_sales_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('sales.csv', -1));
    }

    /**
     * Export sales report grid to Excel XML format
     */
    public function exportSalesExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_sales_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('sales.xml', -1));
    }

    public function taxAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Tax'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_TAX_FLAG_CODE, 'tax');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/tax')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Tax'), Mage::helper('adminhtml')->__('Tax'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_tax.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export tax report grid to CSV format
     */
    public function exportTaxCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_tax_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tax.csv', -1));
    }

    /**
     * Export tax report grid to Excel XML format
     */
    public function exportTaxExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_tax_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tax.xml', -1));
    }

    public function shippingAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Shipping'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_SHIPPING_FLAG_CODE, 'shipping');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/shipping')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Shipping'), Mage::helper('adminhtml')->__('Shipping'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_shipping.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export shipping report grid to CSV format
     */
    public function exportShippingCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_shipping_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shipping.csv', -1));
    }

    /**
     * Export shipping report grid to Excel XML format
     */
    public function exportShippingExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_shipping_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shipping.xml', -1));
    }

    public function invoicedAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Total Invoiced'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_INVOICE_FLAG_CODE, 'invoiced');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/invoiced')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Total Invoiced'), Mage::helper('adminhtml')->__('Total Invoiced'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_invoiced.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export invoiced report grid to CSV format
     */
    public function exportInvoicedCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_invoiced_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('invoiced.csv', -1));
    }

    /**
     * Export invoiced report grid to Excel XML format
     */
    public function exportInvoicedExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_invoiced_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('invoiced.xml', -1));
    }

    public function refundedAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Total Refunded'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_REFUNDED_FLAG_CODE, 'refunded');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/refunded')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Total Refunded'), Mage::helper('adminhtml')->__('Total Refunded'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_refunded.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export refunded report grid to CSV format
     */
    public function exportRefundedCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_refunded_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('refunded.csv', -1));
    }

    /**
     * Export refunded report grid to Excel XML format
     */
    public function exportRefundedExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_refunded_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('refunded.xml', -1));
    }

    public function couponsAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Coupons'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_COUPONS_FLAG_CODE, 'coupons');

        $this->_initAction()
            ->_setActiveMenu('report/salesroot/coupons')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Coupons'), Mage::helper('adminhtml')->__('Coupons'));

        $gridBlock = $this->getLayout()->getBlock('report_sales_coupons.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export coupons report grid to CSV format
     */
    public function exportCouponsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_coupons_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('coupons.csv', -1));
    }

    /**
     * Export coupons report grid to Excel XML format
     */
    public function exportCouponsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_sales_coupons_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('coupons.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'sales' => $this->_getSession()->isAllowed('report/salesroot/sales'),
            'tax' => $this->_getSession()->isAllowed('report/salesroot/tax'),
            'shipping' => $this->_getSession()->isAllowed('report/salesroot/shipping'),
            'invoiced' => $this->_getSession()->isAllowed('report/salesroot/invoiced'),
            'refunded' => $this->_getSession()->isAllowed('report/salesroot/refunded'),
            'coupons' => $this->_getSession()->isAllowed('report/salesroot/coupons'),
            'bestsellers' => $this->_getSession()->isAllowed('report/products/bestsellers'),
            default => $this->_getSession()->isAllowed('report/salesroot'),
        };
    }
}
