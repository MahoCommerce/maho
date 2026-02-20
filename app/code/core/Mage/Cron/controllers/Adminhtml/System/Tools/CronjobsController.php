<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Adminhtml_System_Tools_CronjobsController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/cronjobs';

    #[\Override]
    public function preDispatch(): self
    {
        $this->_setForcedFormKeyActions('massDelete');
        return parent::preDispatch();
    }

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('system/tools/cronjobs');
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('System'),
            Mage::helper('cron')->__('System'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Tools'),
            Mage::helper('cron')->__('Tools'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Cron Jobs'),
            Mage::helper('cron')->__('Cron Jobs'),
        );
        $this->_addContent($this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs'));
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_grid')->toHtml(),
        );
    }

    public function massDeleteAction(): void
    {
        $scheduleIds = $this->getRequest()->getParam('schedule_ids');
        if (!is_array($scheduleIds)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
        } else {
            try {
                $collection = Mage::getModel('cron/schedule')->getCollection()
                    ->addFieldToFilter('schedule_id', ['in' => $scheduleIds]);
                $deletedCount = count($collection);
                foreach ($collection as $schedule) {
                    $schedule->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Total of %d cron job(s) were deleted.', $deletedCount),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }
}
