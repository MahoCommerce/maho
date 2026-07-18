<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

class Maho_AdminActivityLog_Adminhtml_Adminactivitylog_LoginController extends Mage_Adminhtml_Controller_Action
{
    #[Maho\Config\Route('/admin/adminactivitylog_login/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Login Activity'));

        $this->loadLayout();
        $this->_setActiveMenu('system/adminactivitylog/login');
        $this->_addBreadcrumb($this->__('Login Activity'), $this->__('Login Activity'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/adminactivitylog_login/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/adminactivitylog_login/exportCsv')]
    public function exportCsvAction(): void
    {
        $fileName = 'admin_login_activity.csv';
        $content = $this->getLayout()->createBlock('adminactivitylog/adminhtml_login_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    #[Maho\Config\Route('/admin/adminactivitylog_login/exportXml')]
    public function exportXmlAction(): void
    {
        $fileName = 'admin_login_activity.xml';
        $content = $this->getLayout()->createBlock('adminactivitylog/adminhtml_login_grid')
            ->getExcelFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/adminactivitylog/login');
    }
}
