<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * Customer reports admin controller
 *
 * @package    Mage_Adminhtml
 */

class Mage_Adminhtml_Report_CustomerController extends Mage_Adminhtml_Controller_Action
{
    #[Maho\Config\Route('/admin/report_customer/_init')]
    public function _initAction()
    {
        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Customers'), Mage::helper('reports')->__('Customers'));
        return $this;
    }

    #[Maho\Config\Route('/admin/report_customer/accounts')]
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
    #[Maho\Config\Route('/admin/report_customer/exportAccountsCsv')]
    public function exportAccountsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_accounts_grid');
        $this->_prepareDownloadResponse('new_accounts.csv', $grid->getCsv(), 'text/csv');
    }

    /**
     * Export new accounts report grid to Excel XML format
     */
    #[Maho\Config\Route('/admin/report_customer/exportAccountsExcel')]
    public function exportAccountsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_accounts_grid');
        $this->_prepareDownloadResponse('accounts.xml', $grid->getExcel('accounts.xml'), 'application/vnd.ms-excel');
    }

    #[Maho\Config\Route('/admin/report_customer/orders')]
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
    #[Maho\Config\Route('/admin/report_customer/exportOrdersCsv')]
    public function exportOrdersCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_orders_grid');
        $this->_prepareDownloadResponse('customers_orders.csv', $grid->getCsv(), 'text/csv');
    }

    /**
     * Export customers most ordered report to Excel XML format
     */
    #[Maho\Config\Route('/admin/report_customer/exportOrdersExcel')]
    public function exportOrdersExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_orders_grid');
        $this->_prepareDownloadResponse('customers_orders.xml', $grid->getExcel('customers_orders.xml'), 'application/vnd.ms-excel');
    }

    #[Maho\Config\Route('/admin/report_customer/totals')]
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
    #[Maho\Config\Route('/admin/report_customer/exportTotalsCsv')]
    public function exportTotalsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_totals_grid');
        $this->_prepareDownloadResponse('customer_totals.csv', $grid->getCsv(), 'text/csv');
    }

    /**
     * Export customers biggest totals report to Excel XML format
     */
    #[Maho\Config\Route('/admin/report_customer/exportTotalsExcel')]
    public function exportTotalsExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_customer_totals_grid');
        $this->_prepareDownloadResponse('customer_totals.xml', $grid->getExcel('customer_totals.xml'), 'application/vnd.ms-excel');
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
