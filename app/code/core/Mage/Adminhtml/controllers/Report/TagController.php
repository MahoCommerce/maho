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

class Mage_Adminhtml_Report_TagController extends Mage_Adminhtml_Controller_Action
{
    public function _initAction()
    {
        $act = $this->getRequest()->getActionName();
        if (!$act) {
            $act = 'default';
        }

        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Tag'), Mage::helper('reports')->__('Tag'));
        return $this;
    }

    public function customerAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Customers'));

        $this->_initAction()
            ->_setActiveMenu('report/tag/customer')
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers Report'), Mage::helper('reports')->__('Customers Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_tag_customer'))
            ->renderLayout();
    }

    /**
     * Export customer's tags report to CSV format
     */
    public function exportCustomerCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_customer.csv', -1));
    }

    /**
     * Export customer's tags report to Excel XML format
     */
    public function exportCustomerExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_customer.xml', -1));
    }

    public function productAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Products'));

        $this->_initAction()
            ->_setActiveMenu('report/tag/product')
            ->_addBreadcrumb(Mage::helper('reports')->__('Poducts Report'), Mage::helper('reports')->__('Products Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_tag_product'))
            ->renderLayout();
    }

    /**
     * Export product's tags report to CSV format
     */
    public function exportProductCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_product_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_product.csv', -1));
    }

    /**
     * Export product's tags report to Excel XML format
     */
    public function exportProductExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_product_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_product.xml', -1));
    }

    public function popularAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Popular'));

        $this->_initAction()
            ->_setActiveMenu('report/tag/popular')
            ->_addBreadcrumb(Mage::helper('reports')->__('Popular Tags'), Mage::helper('reports')->__('Popular Tags'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_tag_popular'))
            ->renderLayout();
    }

    /**
     * Export popular tags report to CSV format
     */
    public function exportPopularCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_popular_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_popular.csv', -1));
    }

    /**
     * Export popular tags report to Excel XML format
     */
    public function exportPopularExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_popular_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_popular.xml', -1));
    }

    public function customerDetailAction(): void
    {
        $detailBlock = $this->getLayout()->createBlock('adminhtml/report_tag_customer_detail');

        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Customers'))
             ->_title($detailBlock->getHeaderText());

        $this->_initAction()
            ->_setActiveMenu('report/tag/customerDetail')
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers Report'), Mage::helper('reports')->__('Customers Report'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Customer Tags'), Mage::helper('reports')->__('Customer Tags'))
            ->_addContent($detailBlock)
            ->renderLayout();
    }

    /**
     * Export customer's tags detail report to CSV format
     */
    public function exportCustomerDetailCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_customer_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_customer_detail.csv', -1));
    }

    /**
     * Export customer's tags detail report to Excel XML format
     */
    public function exportCustomerDetailExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_customer_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_customer_detail.xml', -1));
    }

    public function productDetailAction(): void
    {
        $detailBlock = $this->getLayout()->createBlock('adminhtml/report_tag_product_detail');

        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Products'))
             ->_title($detailBlock->getHeaderText());

        $this->_initAction()
            ->_setActiveMenu('report/tag/productDetail')
            ->_addBreadcrumb(Mage::helper('reports')->__('Products Report'), Mage::helper('reports')->__('Products Report'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Product Tags'), Mage::helper('reports')->__('Product Tags'))
            ->_addContent($detailBlock)
            ->renderLayout();
    }

    /**
     * Export product's tags detail report to CSV format
     */
    public function exportProductDetailCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_product_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_product_detail.csv', -1));
    }

    /**
     * Export product's tags detail report to Excel XML format
     */
    public function exportProductDetailExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_product_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_product_detail.xml', -1));
    }

    public function tagDetailAction(): void
    {
        $detailBlock = $this->getLayout()->createBlock('adminhtml/report_tag_popular_detail');

        $this->_title($this->__('Reports'))
             ->_title($this->__('Tags'))
             ->_title($this->__('Popular'))
             ->_title($detailBlock->getHeaderText());

        $this->_initAction()
            ->_setActiveMenu('report/tag/tagDetail')
            ->_addBreadcrumb(Mage::helper('reports')->__('Popular Tags'), Mage::helper('reports')->__('Popular Tags'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Tag Detail'), Mage::helper('reports')->__('Tag Detail'))
            ->_addContent($detailBlock)
            ->renderLayout();
    }

    /**
     * Export tag detail report to CSV format
     */
    public function exportTagDetailCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_popular_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('tag_detail.csv', -1));
    }

    /**
     * Export tag detail report to Excel XML format
     */
    public function exportTagDetailExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_tag_popular_detail_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('tag_detail.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'customer' => Mage::getSingleton('admin/session')->isAllowed('report/tags/customer'),
            'productall', 'product' => Mage::getSingleton('admin/session')->isAllowed('report/tags/product'),
            'popular' => Mage::getSingleton('admin/session')->isAllowed('report/tags/popular'),
            default => Mage::getSingleton('admin/session')->isAllowed('report/tags'),
        };
    }
}
