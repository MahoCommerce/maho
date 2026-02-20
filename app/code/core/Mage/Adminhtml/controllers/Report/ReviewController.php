<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Report_ReviewController extends Mage_Adminhtml_Controller_Action
{
    public function _initAction()
    {
        $act = $this->getRequest()->getActionName();
        if (!$act) {
            $act = 'default';
        }

        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Review'), Mage::helper('reports')->__('Reviews'));
        return $this;
    }

    public function customerAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Reviews'))
             ->_title($this->__('Customer Reviews'));

        $this->_initAction()
            ->_setActiveMenu('report/review/customer')
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers Report'), Mage::helper('reports')->__('Customers Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_review_customer'))
            ->renderLayout();
    }

    /**
     * Export review customer report to CSV format
     */
    public function exportCustomerCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('review_customer.csv', -1));
    }

    /**
     * Export review customer report to Excel XML format
     */
    public function exportCustomerExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('review_customer.xml', -1));
    }

    public function productAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Reviews'))
             ->_title($this->__('Product Reviews'));

        $this->_initAction()
            ->_setActiveMenu('report/review/product')
            ->_addBreadcrumb(Mage::helper('reports')->__('Products Report'), Mage::helper('reports')->__('Products Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_review_product'))
            ->renderLayout();
    }

    /**
     * Export review product report to CSV format
     */
    public function exportProductCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_product_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('review_product.csv', -1));
    }

    /**
     * Export review product report to Excel XML format
     */
    public function exportProductExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_product_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('review_product.xml', -1));
    }

    public function productDetailAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Reviews'))
             ->_title($this->__('Product Reviews'))
             ->_title($this->__('Details'));

        $this->_initAction()
            ->_setActiveMenu('report/review/productDetail')
            ->_addBreadcrumb(Mage::helper('reports')->__('Products Report'), Mage::helper('reports')->__('Products Report'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Product Reviews'), Mage::helper('reports')->__('Product Reviews'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_review_detail'))
            ->renderLayout();
    }

    /**
     * Export review product detail report to CSV format
     */
    public function exportProductDetailCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('review_product_detail.csv', -1));
    }

    /**
     * Export review product detail report to ExcelXML format
     */
    public function exportProductDetailExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_review_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('review_product_detail.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'customer' => Mage::getSingleton('admin/session')->isAllowed('report/review/customer'),
            'product' => Mage::getSingleton('admin/session')->isAllowed('report/review/product'),
            default => Mage::getSingleton('admin/session')->isAllowed('report/review'),
        };
    }
}
