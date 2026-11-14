<?php

/**
 * Reports > Not Found: the storefront paths that answered 404.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Report_NotfoundController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'report/notfound';

    #[Maho\Config\Route('/admin/report_notfound/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Not Found'));

        $this->loadLayout()
            ->_setActiveMenu('report/notfound')
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Not Found'), Mage::helper('reports')->__('Not Found'))
            ->_addContent($this->getLayout()->createBlock('adminhtml/report_notfound'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/report_notfound/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/report_notfound_grid')->toHtml(),
        );
    }

    #[Maho\Config\Route('/admin/report_notfound/massDelete', methods: ['POST'])]
    public function massDeleteAction(): void
    {
        $ids = array_map(intval(...), (array) $this->getRequest()->getParam('not_found_id', []));
        $deleted = Mage::getResourceSingleton('reports/notFound')->deleteByIds($ids);
        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('reports')->__('%d address(es) were deleted.', $deleted),
        );
        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/report_notfound/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/report_notfound_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('not_found.csv', -1));
    }
}
