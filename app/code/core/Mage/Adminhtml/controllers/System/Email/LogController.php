<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Adminhtml_System_Email_LogController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/email_log';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('massDelete');
        return parent::preDispatch();
    }

    #[Maho\Config\Route('/admin/system_email_log/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Email Log'));

        $this->loadLayout()
            ->_setActiveMenu('system/tools/email_log')
            ->_addBreadcrumb(
                Mage::helper('core')->__('System'),
                Mage::helper('core')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('core')->__('Email Log'),
                Mage::helper('core')->__('Email Log'),
            );

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_email_log/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_email_log/view')]
    public function viewAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $log = Mage::getModel('core/email_log')->load($id);

        if (!$log->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('core')->__('This email log entry no longer exists.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_email_log', $log);

        $this->_title($this->__('System'))
            ->_title($this->__('Email Log'))
            ->_title($this->__('View Entry'));

        $this->loadLayout()
            ->_setActiveMenu('system/tools/email_log')
            ->_addBreadcrumb(
                Mage::helper('core')->__('System'),
                Mage::helper('core')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('core')->__('Email Log'),
                Mage::helper('core')->__('Email Log'),
            )
            ->_addBreadcrumb(
                Mage::helper('core')->__('View Entry'),
                Mage::helper('core')->__('View Entry'),
            );

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_email_log/massDelete')]
    public function massDeleteAction(): void
    {
        $logIds = $this->getRequest()->getPost('log_ids');

        try {
            if (!is_array($logIds)) {
                Mage::throwException(Mage::helper('core')->__('Please select email log entries to delete.'));
            }

            $deletedCount = 0;
            foreach ($logIds as $logId) {
                $log = Mage::getModel('core/email_log')->load($logId);
                if ($log->getId()) {
                    $log->delete();
                    $deletedCount++;
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', $deletedCount),
            );
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (\Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('An error occurred while deleting email log entries.'),
            );
            Mage::logException($e);
        }

        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/system_email_log/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('core/adminhtml_email_log_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('email_log.csv', -1));
    }

    #[Maho\Config\Route('/admin/system_email_log/exportXml')]
    public function exportXmlAction(): void
    {
        $grid = $this->getLayout()->createBlock('core/adminhtml_email_log_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('email_log.xml', -1));
    }
}
