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

use Maho\Config\Route;

class Mage_Adminhtml_Report_ShopcartController extends Mage_Adminhtml_Controller_Action
{
    #[Route('/admin/report_shopcart/_init')]
    public function _initAction()
    {
        $act = $this->getRequest()->getActionName();
        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Shopping Cart'), Mage::helper('reports')->__('Shopping Cart'));
        return $this;
    }

    #[Route('/admin/report_shopcart/customer')]

    public function customerAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Shopping Cart'))
             ->_title($this->__('Customer Shopping Carts'));

        $this->_initAction()
            ->_setActiveMenu('report/shopcart/customer')
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers Report'), Mage::helper('reports')->__('Customers Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_shopcart_customer'))
            ->renderLayout();
    }

    /**
     * Export shopcart customer report to CSV format
     */
    #[Route('/admin/report_shopcart/exportCustomerCsv')]
    public function exportCustomerCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shopcart_customer.csv', -1));
    }

    /**
     * Export shopcart customer report to Excel XML format
     */
    #[Route('/admin/report_shopcart/exportCustomerExcel')]
    public function exportCustomerExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_customer_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shopcart_customer.xml', -1));
    }

    #[Route('/admin/report_shopcart/product')]

    public function productAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Shopping Cart'))
             ->_title($this->__('Products in Carts'));

        $this->_initAction()
            ->_setActiveMenu('report/shopcart/product')
            ->_addBreadcrumb(Mage::helper('reports')->__('Products Report'), Mage::helper('reports')->__('Products Report'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_shopcart_product'))
            ->renderLayout();
    }

    /**
     * Export products report grid to CSV format
     */
    #[Route('/admin/report_shopcart/exportProductCsv')]
    public function exportProductCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_product_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shopcart_product.csv', -1));
    }

    /**
     * Export products report to Excel XML format
     */
    #[Route('/admin/report_shopcart/exportProductExcel')]
    public function exportProductExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_product_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shopcart_product.xml', -1));
    }

    #[Route('/admin/report_shopcart/abandoned')]

    public function abandonedAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Shopping Cart'))
             ->_title($this->__('Abandoned Carts'));

        $this->_initAction()
            ->_setActiveMenu('report/shopcart/abandoned')
            ->_addBreadcrumb(Mage::helper('reports')->__('Abandoned Carts'), Mage::helper('reports')->__('Abandoned Carts'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_shopcart_abandoned'))
            ->renderLayout();
    }

    /**
     * Export abandoned carts report grid to CSV format
     */
    #[Route('/admin/report_shopcart/exportAbandonedCsv')]
    public function exportAbandonedCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_abandoned_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shopcart_abandoned.csv', -1));
    }

    /**
     * Export abandoned carts report to Excel XML format
     */
    #[Route('/admin/report_shopcart/exportAbandonedExcel')]
    public function exportAbandonedExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_shopcart_abandoned_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shopcart_abandoned.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'customer' => Mage::getSingleton('admin/session')->isAllowed('report/shopcart/customer'),
            'product' => Mage::getSingleton('admin/session')->isAllowed('report/shopcart/product'),
            'abandoned' => Mage::getSingleton('admin/session')->isAllowed('report/shopcart/abandoned'),
            default => Mage::getSingleton('admin/session')->isAllowed('report/shopcart'),
        };
    }
}
