<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Adminhtml_Adminactivitylog_LoginController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Login Activity'));

        $this->loadLayout();
        $this->_setActiveMenu('system/adminactivitylog/login');
        $this->_addBreadcrumb($this->__('Login Activity'), $this->__('Login Activity'));

        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    public function exportCsvAction(): void
    {
        $fileName = 'admin_login_activity.csv';
        $content = $this->getLayout()->createBlock('adminactivitylog/adminhtml_login_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

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
