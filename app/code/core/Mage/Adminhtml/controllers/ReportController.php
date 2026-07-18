<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_ReportController extends Mage_Adminhtml_Controller_Action
{
    #[Maho\Config\Route('/admin/report/_init')]
    public function _initAction()
    {
        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Reports'), Mage::helper('adminhtml')->__('Reports'));
        return $this;
    }

    #[Maho\Config\Route('/admin/report/search')]
    public function searchAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Search Terms'));

        Mage::dispatchEvent('on_view_report', ['report' => 'search']);

        $this->_initAction()
            ->_setActiveMenu('report/search')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Search Terms'), Mage::helper('adminhtml')->__('Search Terms'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_search'))
            ->renderLayout();
    }

    /**
     * Export search report grid to CSV format
     */
    #[Maho\Config\Route('/admin/report/exportSearchCsv')]
    public function exportSearchCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_search_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('search.csv', -1));
    }

    /**
     * Export search report to Excel XML format
     */
    #[Maho\Config\Route('/admin/report/exportSearchExcel')]
    public function exportSearchExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_search_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('search.xml', -1));
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'search' => Mage::getSingleton('admin/session')->isAllowed('report/search'),
            default => Mage::getSingleton('admin/session')->isAllowed('report'),
        };
    }
}
