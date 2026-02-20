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

/**
 * Customer reports admin controller
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Report_CustomerController extends Mage_Adminhtml_Controller_Action
{
    public function _initAction()
    {
        $act = $this->getRequest()->getActionName();
        if (!$act) {
            $act = 'default';
        }

        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers'), Mage::helper('reports')->__('Customers'));
        return $this;
    }

    public function accountsAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Customers'))
             ->_title($this->__('New Accounts'));

        $this->_initAction()
            ->_setActiveMenu('report/customers/accounts')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('New Accounts'), Mage::helper('adminhtml')->__('New Accounts'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_customer_accounts'))
            ->renderLayout();
    }

    /**
     * Export new accounts report grid to CSV format
     */
    public function exportAccountsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_accounts_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('new_accounts.csv', -1));
    }

    /**
     * Export new accounts report grid to Excel XML format
     */
    public function exportAccountsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_accounts_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('accounts.xml', -1));
    }

    public function ordersAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Customers'))
             ->_title($this->__('Customers by Number of Orders'));

        $this->_initAction()
            ->_setActiveMenu('report/customers/orders')
            ->_addBreadcrumb(
                Mage::helper('reports')->__('Customers by Number of Orders'),
                Mage::helper('reports')->__('Customers by Number of Orders'),
            )
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_customer_orders'))
            ->renderLayout();
    }

    /**
     * Export customers most ordered report to CSV format
     */
    public function exportOrdersCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_orders_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('customers_orders.csv', -1));
    }

    /**
     * Export customers most ordered report to Excel XML format
     */
    public function exportOrdersExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_orders_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('customers_orders.xml', -1));
    }

    public function totalsAction(): void
    {
        $this->_title($this->__('Reports'))
             ->_title($this->__('Customers'))
             ->_title($this->__('Customers by Orders Total'));

        $this->_initAction()
            ->_setActiveMenu('report/customers/totals')
            ->_addBreadcrumb(
                Mage::helper('reports')->__('Customers by Orders Total'),
                Mage::helper('reports')->__('Customers by Orders Total'),
            )
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_customer_totals'))
            ->renderLayout();
    }

    /**
     * Export customers biggest totals report to CSV format
     */
    public function exportTotalsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_totals_grid');
        $this->_prepareDownloadResponse(...$grid->getCsv('cuatomer_totals.csv', -1));
    }

    /**
     * Export customers biggest totals report to Excel XML format
     */
    public function exportTotalsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_totals_grid');
        $this->_prepareDownloadResponse(...$grid->getExcel('customer_totals.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'accounts' => Mage::getSingleton('admin/session')->isAllowed('report/customers/accounts'),
            'orders' => Mage::getSingleton('admin/session')->isAllowed('report/customers/orders'),
            'totals' => Mage::getSingleton('admin/session')->isAllowed('report/customers/totals'),
            default => Mage::getSingleton('admin/session')->isAllowed('report/customers'),
        };
    }
}
