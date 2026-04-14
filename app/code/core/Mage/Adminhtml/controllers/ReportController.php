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

class Mage_Adminhtml_ReportController extends Mage_Adminhtml_Controller_Action
{
    #[Route('/admin/report/_init')]
    public function _initAction()
    {
        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Reports'), Mage::helper('adminhtml')->__('Reports'));
        return $this;
    }

    #[Route('/admin/report/search')]

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
    #[Route('/admin/report/exportSearchCsv')]
    public function exportSearchCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_search_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('search.csv', -1));
    }

    /**
     * Export search report to Excel XML format
     */
    #[Route('/admin/report/exportSearchExcel')]
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
