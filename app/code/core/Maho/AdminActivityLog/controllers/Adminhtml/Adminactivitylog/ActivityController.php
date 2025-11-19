<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Adminhtml_Adminactivitylog_ActivityController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Admin Activity Log'));

        $this->loadLayout();
        $this->_setActiveMenu('system/adminactivitylog/activity');
        $this->_addBreadcrumb($this->__('Admin Activity Log'), $this->__('Admin Activity Log'));

        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    public function viewAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('adminactivitylog/activity')->load($id);

        if (!$model->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Activity log entry not found'));
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_activity', $model);

        $this->_title($this->__('System'))->_title($this->__('Admin Activity Log'))->_title($this->__('View Entry'));

        $this->loadLayout();
        $this->_setActiveMenu('system/adminactivitylog/activity');

        $this->_addBreadcrumb($this->__('Admin Activity Log'), $this->__('Admin Activity Log'));
        $this->_addBreadcrumb($this->__('View Entry'), $this->__('View Entry'));

        $this->renderLayout();
    }

    public function exportCsvAction(): void
    {
        $fileName = 'admin_activity_log.csv';
        $content = $this->getLayout()->createBlock('adminactivitylog/adminhtml_activity_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    public function exportXmlAction(): void
    {
        $fileName = 'admin_activity_log.xml';
        $content = $this->getLayout()->createBlock('adminactivitylog/adminhtml_activity_grid')
            ->getExcelFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/adminactivitylog/activity');
    }
}
