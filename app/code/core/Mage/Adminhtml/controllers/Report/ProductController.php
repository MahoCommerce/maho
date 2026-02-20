<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Report_ProductController extends Mage_Adminhtml_Controller_Report_Abstract
{
    /**
     * Add report/products breadcrumbs
     *
     * @return $this
     */
    #[\Override]
    public function _initAction()
    {
        parent::_initAction();
        $this->_addBreadcrumb(Mage::helper('reports')->__('Products'), Mage::helper('reports')->__('Products'));
        return $this;
    }

    /**
     * Sold Products Report Action
     */
    public function soldAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Products'))
             ->_title($this->__('Products Ordered'));

        $this->_initAction()
            ->_setActiveMenu('report/products/sold')
            ->_addBreadcrumb(Mage::helper('reports')->__('Products Ordered'), Mage::helper('reports')->__('Products Ordered'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_product_sold'))
            ->renderLayout();
    }

    /**
     * Export Sold Products report to CSV format action
     */
    public function exportSoldCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_sold_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('products_ordered.csv', -1));
    }

    /**
     * Export Sold Products report to XML format action
     */
    public function exportSoldExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_sold_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('products_ordered.xml', -1));
    }

    /**
     * Most viewed products
     */
    public function viewedAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Products'))->_title($this->__('Most Viewed'));

        $this->_showLastExecutionTime(Mage_Reports_Model_Flag::REPORT_PRODUCT_VIEWED_FLAG_CODE, 'viewed');

        $this->_initAction()
            ->_setActiveMenu('report/products/viewed')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Products Most Viewed Report'), Mage::helper('adminhtml')->__('Products Most Viewed Report'));

        $gridBlock = $this->getLayout()->getBlock('report_product_viewed.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');

        $this->_initReportAction([
            $gridBlock,
            $filterFormBlock,
        ]);

        $this->renderLayout();
    }

    /**
     * Export products most viewed report to CSV format
     */
    public function exportViewedCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_viewed_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getCsvFile('products_mostviewed.csv', -1));
    }

    /**
     * Export products most viewed report to XML format
     */
    public function exportViewedExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_viewed_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse(...$grid->getExcelFile('products_mostviewed.xml', -1));
    }

    /**
     * Low stock action
     */
    public function lowstockAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Products'))
             ->_title($this->__('Low Stock'));

        $this->_initAction()
            ->_setActiveMenu('report/products/lowstock')
            ->_addBreadcrumb(Mage::helper('reports')->__('Low Stock'), Mage::helper('reports')->__('Low Stock'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_product_lowstock'))
            ->renderLayout();
    }

    /**
     * Export low stock products report to CSV format
     */
    public function exportLowstockCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_lowstock_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('products_lowstock.csv', -1));
    }

    /**
     * Export low stock products report to XML format
     */
    public function exportLowstockExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_lowstock_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('products_lowstock.xml', -1));
    }

    /**
     * Downloads action
     */
    public function downloadsAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Products'))
             ->_title($this->__('Downloads'));

        $this->_initAction()
            ->_setActiveMenu('report/products/downloads')
            ->_addBreadcrumb(Mage::helper('reports')->__('Downloads'), Mage::helper('reports')->__('Downloads'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_product_downloads'))
            ->renderLayout();
    }

    /**
     * Export products downloads report to CSV format
     */
    public function exportDownloadsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_downloads_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('products_downloads.csv', -1));
    }

    /**
     * Export products downloads report to XLS format
     */
    public function exportDownloadsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_product_downloads_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('products_downloads.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'lowstock', 'sold', 'viewed' => Mage::getSingleton('admin/session')->isAllowed("report/products/$action"),
            default => Mage::getSingleton('admin/session')->isAllowed('report/products'),
        };
    }
}
